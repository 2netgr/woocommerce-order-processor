#!/usr/bin/env php
<?php
/**
 * WooDashboard — Přírůstkový cron
 *
 * Logika:
 *   - Pokud nebyl nikdy spuštěn full sync → provede full sync (všechny objednávky)
 *   - Jinak → přírůstkový sync (jen objednávky od posledního syncu)
 *   - Jednou týdně automaticky provede full sync jako kontrolu
 *   - Přírůstkový sync taky překontroluje posledních 30 dní (zachytí změny stavů)
 *
 * Crontab:
 *   0 2 * * * /usr/bin/php /cesta/k/cron.php >> /var/log/woo-cron.log 2>&1
 *
 * HTTP (nastav CRON_SECRET v config.php):
 *   https://vasedomena.cz/cron.php?token=TAJNY_TOKEN
 */

$isCli = php_sapi_name() === 'cli';

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/src/Auth.php';
require_once __DIR__ . '/src/Helpers.php';
require_once __DIR__ . '/src/Database.php';
require_once __DIR__ . '/src/WooApi.php';

if (!$isCli) {
    $token = isset($_GET['token']) ? $_GET['token'] : '';
    if (CRON_SECRET === '' || !hash_equals(CRON_SECRET, $token)) {
        http_response_code(403); die('Forbidden');
    }
    header('Content-Type: text/plain; charset=utf-8');
}

// Jak stará může být full sync aby se nespouštěl znovu (dny)
define('FULL_SYNC_INTERVAL_DAYS', 7);
// Kolik dní zpět překontrolovat při přírůstkovém syncu (změny stavů)
define('INCREMENTAL_OVERLAP_DAYS', 30);

function cronLog($msg) {
    echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n";
    flush();
}

// ── Plný sync — projde všechny objednávky ─────────────────────
function fullSync($store, $db) {
    $storeId = (int)$store['id'];
    $api     = new WooApi($store);
    $today   = date('Y-m-d');

    $revenueStatuses = json_decode(isset($store['revenue_statuses']) ? $store['revenue_statuses'] : '[]', true);
    if (empty($revenueStatuses)) $revenueStatuses = array('completed', 'processing', 'on-hold');

    $revenue = array();

    $stats = $api->fetchAllOrdersPaged(function($batch, $page) use (&$revenue, $revenueStatuses) {
        foreach ($batch as $order) {
            $cur    = isset($order['currency']) ? $order['currency'] : 'CZK';
            $status = isset($order['status'])   ? $order['status']   : '';
            $total  = (float)(isset($order['total']) ? $order['total'] : 0);

            if (!isset($revenue[$cur])) {
                $revenue[$cur] = array('total_orders' => 0, 'gross_sales' => 0, 'net_revenue' => 0, 'refunded' => 0);
            }
            $revenue[$cur]['total_orders']++;
            $revenue[$cur]['gross_sales'] += $total;
            if (in_array($status, $revenueStatuses)) {
                $revenue[$cur]['net_revenue'] += $total;
            }
            if ($status === 'refunded') {
                $revenue[$cur]['refunded'] += $total;
            }
        }
        if ($page % 5 === 0) cronLog("    ... stránka $page");
    });

    if (!empty($stats['errors'])) {
        cronLog("  ✗ API chyba: " . implode(', ', $stats['errors']));
        $db->logCron($storeId, 'full_sync', 'error', implode('; ', $stats['errors']));
        return false;
    }

    // Ulož snapshot (přepíše dnešní)
    foreach ($revenue as $cur => $data) {
        $db->saveSnapshot($storeId, $cur, $data, $today);
        cronLog(sprintf('  ✓ %s: %s obj. | čistý obrat: %s %s',
            $cur,
            number_format($data['total_orders']),
            number_format($data['net_revenue'], 2, ',', ' '),
            $cur
        ));
    }

    $db->setCronState($storeId, 'last_full_sync', date('Y-m-d H:i:s'));
    $db->setCronState($storeId, 'incremental_from', date('Y-m-d H:i:s'));
    cronLog("  ✓ Full sync: {$stats['total']} objednávek na {$stats['pages']} stránkách");
    return true;
}

// ── Přírůstkový sync — jen nové + posledních N dní ───────────
function incrementalSync($store, $db) {
    $storeId = (int)$store['id'];
    $api     = new WooApi($store);
    $state   = $db->getCronState($storeId);
    $today   = date('Y-m-d');

    $revenueStatuses = json_decode(isset($store['revenue_statuses']) ? $store['revenue_statuses'] : '[]', true);
    if (empty($revenueStatuses)) $revenueStatuses = array('completed', 'processing', 'on-hold');

    // Načti aktuální snapshot jako základ
    $snapshots = $db->getSnapshotTotals($storeId);
    $revenue   = array();
    foreach ($snapshots as $snap) {
        $revenue[$snap['currency']] = array(
            'total_orders' => (int)$snap['total_orders'],
            'gross_sales'  => (float)$snap['gross_sales'],
            'net_revenue'  => (float)$snap['net_revenue'],
            'refunded'     => (float)$snap['refunded'],
        );
    }

    // Datum od kdy načíst — buď posledni sync minus overlap, nebo 30 dní zpět
    $overlapDate = date('Y-m-d', strtotime('-' . INCREMENTAL_OVERLAP_DAYS . ' days'));
    $fromDate    = $state['incremental_from'] ? date('Y-m-d', strtotime($state['incremental_from'])) : $overlapDate;
    // Vždy jdi aspoň INCREMENTAL_OVERLAP_DAYS zpět kvůli změnám stavů
    if ($fromDate > $overlapDate) $fromDate = $overlapDate;

    cronLog("  → Načítám objednávky od: $fromDate");

    // Objednávky od fromDate — stránkovaně
    $newOrders  = 0;
    $changedIds = array(); // ID objednávek z overlap okna (budeme je přepočítávat)
    $page       = 1;
    $perPage    = 100;
    $errors     = array();

    // Nejdřív odečti overlap objednávky ze snapshotu (přepočítáme je čerstvě)
    $overlapOrders = fetchOrdersFrom($api, $overlapDate, $errors);
    if (!empty($errors)) {
        cronLog("  ✗ API chyba: " . implode(', ', $errors));
        $db->logCron($storeId, 'incremental', 'error', implode('; ', $errors));
        return false;
    }

    // Odečti staré hodnoty overlap objednávek ze snapshotu
    $overlapOld = getStoredOrdersInRange($db, $storeId, $overlapDate);
    foreach ($overlapOld as $old) {
        $cur = $old['currency'];
        if (!isset($revenue[$cur])) continue;
        $revenue[$cur]['total_orders']--;
        $revenue[$cur]['gross_sales'] -= (float)$old['total'];
        if (in_array($old['status'], $revenueStatuses)) {
            $revenue[$cur]['net_revenue'] -= (float)$old['total'];
        }
        if ($old['status'] === 'refunded') {
            $revenue[$cur]['refunded'] -= (float)$old['total'];
        }
    }

    // Přičti čerstvá data z overlap okna
    foreach ($overlapOrders as $order) {
        $cur    = isset($order['currency']) ? $order['currency'] : 'CZK';
        $status = isset($order['status'])   ? $order['status']   : '';
        $total  = (float)(isset($order['total']) ? $order['total'] : 0);

        if (!isset($revenue[$cur])) {
            $revenue[$cur] = array('total_orders' => 0, 'gross_sales' => 0, 'net_revenue' => 0, 'refunded' => 0);
        }
        $revenue[$cur]['total_orders']++;
        $revenue[$cur]['gross_sales'] += $total;
        if (in_array($status, $revenueStatuses)) {
            $revenue[$cur]['net_revenue'] += $total;
        }
        if ($status === 'refunded') {
            $revenue[$cur]['refunded'] += $total;
        }
    }

    $newOrders = count($overlapOrders);

    // Ulož aktualizovaný snapshot
    foreach ($revenue as $cur => $data) {
        // Zajisti nezáporné hodnoty
        $data['total_orders'] = max(0, $data['total_orders']);
        $data['net_revenue']  = max(0, $data['net_revenue']);
        $db->saveSnapshot($storeId, $cur, $data, $today);
        cronLog(sprintf('  ✓ %s: %s obj. | čistý obrat: %s %s',
            $cur,
            number_format($data['total_orders']),
            number_format($data['net_revenue'], 2, ',', ' '),
            $cur
        ));
    }

    $db->setCronState($storeId, 'last_incremental', date('Y-m-d H:i:s'));
    $db->setCronState($storeId, 'incremental_from', date('Y-m-d H:i:s'));
    cronLog("  ✓ Přírůstkový sync: zpracováno $newOrders objednávek (okno: $overlapDate → dnes)");
    return true;
}

function fetchOrdersFrom($api, $fromDate, &$errors) {
    $all     = array();
    $page    = 1;
    $perPage = 100;

    while (true) {
        $batch = $api->get('orders', array(
            'per_page'  => $perPage,
            'page'      => $page,
            'orderby'   => 'date',
            'order'     => 'asc',
            'after'     => $fromDate . 'T00:00:00',
            '_fields'   => 'id,status,total,currency,date_created',
        ));

        if ($api->isError($batch)) { $errors[] = $api->errorMessage($batch); break; }
        if (empty($batch)) break;

        $all = array_merge($all, $batch);
        if (count($batch) < $perPage) break;
        $page++;
        if ($page > 200) break;
    }
    return $all;
}

function getStoredOrdersInRange($db, $storeId, $fromDate) {
    // Vrátí objednávky z cache které jsou v overlap okně
    // (abychom věděli co odečíst před přepočtem)
    $st = $db->getCronStatePdo($storeId, $fromDate);
    return $st;
}

// ── Refresh cache objednávek ───────────────────────────────────
function refreshOrderCache($store, $db) {
    $storeId = (int)$store['id'];
    $api     = new WooApi($store);
    $db->invalidateCache($storeId);
    $orders = $api->fetchOrders((int)$store['max_orders']);
    if (!$api->isError($orders)) {
        $db->upsertOrders($storeId, $orders);
        cronLog('  ✓ Cache objednávek: ' . count($orders) . ' záznamů');
    } else {
        cronLog('  ⚠ Cache objednávek: ' . $api->errorMessage($orders));
    }
}

// ── Hlavní smyčka ──────────────────────────────────────────────
cronLog('=== WooDashboard Cron START ===');
$db     = Database::get();
$stores = $db->getStores(true);

if (empty($stores)) { cronLog('Žádné aktivní obchody.'); exit(0); }
cronLog('Obchodů: ' . count($stores));

$errors = 0;

foreach ($stores as $store) {
    $storeId = (int)$store['id'];
    cronLog("\n── {$store['name']} ──");
    $start = microtime(true);

    try {
        $state       = $db->getCronState($storeId);
        $needFullSync = false;

        if (empty($state['last_full_sync'])) {
            cronLog('  → První spuštění — provádím full sync...');
            $needFullSync = true;
        } else {
            $daysSinceFull = (time() - strtotime($state['last_full_sync'])) / 86400;
            if ($daysSinceFull >= FULL_SYNC_INTERVAL_DAYS) {
                cronLog(sprintf('  → Poslední full sync před %.1f dny — obnovuji...', $daysSinceFull));
                $needFullSync = true;
            } else {
                cronLog(sprintf('  → Přírůstkový sync (full sync před %.1f dny)', $daysSinceFull));
            }
        }

        $ok = $needFullSync ? fullSync($store, $db) : incrementalSync($store, $db);

        if ($ok) {
            refreshOrderCache($store, $db);
            $duration = round(microtime(true) - $start, 1);
            $type     = $needFullSync ? 'full_sync' : 'incremental';
            $db->logCron($storeId, $type, 'ok', '', $duration);
            cronLog("  Hotovo za {$duration}s");
        }

    } catch (Exception $e) {
        cronLog('  ✗ VÝJIMKA: ' . $e->getMessage());
        $db->logCron($storeId, 'cron', 'exception', $e->getMessage());
        $errors++;
    }
}

cronLog("\n=== WooDashboard Cron KONEC | chyby: $errors ===");
exit($errors > 0 ? 1 : 0);
