<?php
// ini_set('display_errors', 1); error_reporting(E_ALL);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/src/Lang.php';
require_once __DIR__ . '/src/Auth.php';
require_once __DIR__ . '/src/Helpers.php';
require_once __DIR__ . '/src/Database.php';
require_once __DIR__ . '/src/WooApi.php';
require_once __DIR__ . '/src/UpdateChecker.php';

Auth::init();

// Přepnutí jazyka
if (isset($_GET['setlang'])) {
    Lang::setLang($_GET['setlang']);
    Helpers::redirect('index.php');
}

if (isset($_GET['logout'])) { Auth::logout(); Helpers::redirect('index.php'); }

if (Helpers::isPost() && isset($_POST['password'])) {
    if (Auth::isLockedOut()) {
        $loginLocked = Auth::lockoutRemainingSeconds();
    } elseif (Auth::login(Helpers::post('password'))) {
        Helpers::redirect('index.php');
    } else {
        $loginError    = true;
        $loginAttempts = Auth::loginAttemptsLeft();
    }
}

if (!Auth::isLoggedIn()) { require __DIR__ . '/views/login.php'; exit; }

// ── Renderovací helper ─────────────────────────────────────────
function renderOrderRow($o, $storeId, $color, $storeUrl = '')
{
    $statusArr   = Helpers::statusLabel(isset($o['status']) ? $o['status'] : '');
    $statusText  = $statusArr[0];
    $statusColor = $statusArr[1];
    $statusBg    = $statusArr[2];
    $customer    = Helpers::e(isset($o['customer'])      ? $o['customer']      : '—');
    $total       = Helpers::formatPrice(
                       (float)(isset($o['total'])        ? $o['total']         : 0),
                       isset($o['currency'])             ? $o['currency']      : 'CZK');
    $date        = Helpers::formatDate(isset($o['date_created']) ? $o['date_created'] : '');
    $num         = Helpers::e(isset($o['order_number'])  ? $o['order_number']  : (isset($o['order_id']) ? $o['order_id'] : ''));
    $orderId     = (int)(isset($o['order_id'])           ? $o['order_id']      : 0);
    $status      = Helpers::e(isset($o['status'])        ? $o['status']        : '');
    ?>
    <tr class="order-row" data-store="<?= $storeId ?>" data-id="<?= $orderId ?>" data-status="<?= $status ?>" data-url="<?= Helpers::e(rtrim($storeUrl, '/')) ?>">
      <td><span class="order-num">#<?= $num ?></span></td>
      <td><span class="order-date"><?= $date ?></span></td>
      <td><span class="order-customer"><?= $customer ?></span></td>
      <td><span class="order-total"><?= $total ?></span></td>
      <td>
        <span class="status-badge" style="color:<?= $statusColor ?>;background:<?= $statusBg ?>;">
          <span class="status-dot" style="background:<?= $statusColor ?>"></span>
          <?= Helpers::e($statusText) ?>
        </span>
      </td>
      <td><span class="expand-icon">›</span></td>
    </tr>
    <tr class="detail-row">
      <td colspan="6">
        <div class="detail-inner" id="detail-<?= $storeId ?>-<?= $orderId ?>">
          <div class="detail-placeholder"><?= t('order_detail_loading') ?></div>
        </div>
      </td>
    </tr>
    <?php
}

// ── Kontrola aktualizace (nenápadně, z cache) ────────────────
$updateAvailable = UpdateChecker::check();

// ── Data ───────────────────────────────────────────────────────
$db     = Database::get();
$stores = $db->getStores(true);

$allStoresData     = array();
$totalOrdersShown  = 0;
$totalOrdersCached = 0;
$globalRevenue     = array();
$globalOrders      = 0;

foreach ($stores as $store) {
    $storeId = (int)$store['id'];
    $api     = new WooApi($store);

    if (!$db->isCacheValid($storeId, CACHE_TTL_MINUTES)) {
        $fresh = $api->fetchOrders((int)$store['max_orders']);
        if (!$api->isError($fresh)) {
            $db->upsertOrders($storeId, $fresh);
        }
    }

    $visibleStatuses = json_decode(isset($store['visible_statuses']) ? $store['visible_statuses'] : 'null', true);
    $previewRows     = (int)$store['preview_rows'];
    $allOrders       = $db->getOrdersFromCache($storeId, $visibleStatuses, (int)$store['max_orders']);
    $cachedTotal     = $db->getTotalCachedOrders($storeId);

    $revenueStatuses = json_decode(isset($store['revenue_statuses']) ? $store['revenue_statuses'] : '[]', true);
    if (empty($revenueStatuses)) $revenueStatuses = array('completed', 'processing', 'on-hold');
    $recentRevenue = array();
    foreach ($allOrders as $o) {
        if (in_array($o['status'], $revenueStatuses)) {
            $cur = isset($o['currency']) ? $o['currency'] : 'CZK';
            $recentRevenue[$cur] = (isset($recentRevenue[$cur]) ? $recentRevenue[$cur] : 0) + (float)$o['total'];
        }
    }

    $snapshots   = $db->getSnapshotTotals($storeId);
    $snapshotAge = $db->getSnapshotAge($storeId);

    foreach ($snapshots as $snap) {
        $cur = $snap['currency'];
        $globalRevenue[$cur] = (isset($globalRevenue[$cur]) ? $globalRevenue[$cur] : 0) + (float)$snap['net_revenue'];
    }
    if (!empty($snapshots)) {
        $globalOrders += (int)$snapshots[0]['total_orders'];
    }

    $totalOrdersShown  += count($allOrders);
    $totalOrdersCached += $cachedTotal;

    $allStoresData[] = array(
        'store'         => $store,
        'orders'        => $allOrders,
        'cachedTotal'   => $cachedTotal,
        'previewRows'   => $previewRows,
        'recentRevenue' => $recentRevenue,
        'snapshots'     => $snapshots,
        'snapshotAge'   => $snapshotAge,
    );
}

$allStatuses = Helpers::allStatuses();
$pageTitle   = t('dash_title') . ' — ' . APP_NAME;
require __DIR__ . '/views/layout_top.php';
?>

<div class="page-header">
  <h1 class="page-title"><?= t('dash_title') ?></h1>
</div>

<?php if (!empty($updateAvailable)): ?>
<div class="update-banner">
  <span class="update-banner-icon">🆕</span>
  <div class="update-banner-text">
    <strong><?= t('update_available', Helpers::e($updateAvailable['version'])) ?></strong>
    <?= t('update_current', APP_VERSION) ?>
    · <a href="<?= Helpers::e($updateAvailable['url']) ?>" target="_blank" rel="noopener"><?= t('update_link') ?></a>
  </div>
</div>
<?php endif; ?>

<!-- Stats řádek 1 -->
<div class="stats-row stats-row--sm">
  <div class="stat-card stat-card--inline">
    <div class="stat-label"><?= t('dash_stores') ?></div>
    <div class="stat-value"><?= count($stores) ?></div>
  </div>
  <div class="stat-card stat-card--inline">
    <div class="stat-label"><?= t('dash_shown') ?></div>
    <div class="stat-value"><?= $totalOrdersShown ?></div>
    <div class="stat-sub"><?= t('dash_shown_sub') ?></div>
  </div>
  <div class="stat-card stat-card--inline">
    <div class="stat-label"><?= t('dash_cached') ?></div>
    <div class="stat-value"><?= $totalOrdersCached ?></div>
    <div class="stat-sub"><?= t('dash_cached_sub') ?></div>
  </div>
  <?php if ($globalOrders > 0): ?>
  <div class="stat-card stat-card--inline">
    <div class="stat-label"><?= t('dash_total_orders') ?></div>
    <div class="stat-value"><?= Helpers::numberFormat($globalOrders) ?></div>
    <div class="stat-sub"><?= t('dash_total_orders_sub') ?></div>
  </div>
  <?php endif; ?>
</div>

<!-- Stats řádek 2 — tržby -->
<div class="stats-row stats-row--revenue">
  <?php if (!empty($globalRevenue)): ?>
    <?php foreach ($globalRevenue as $cur => $amount): ?>
    <div class="stat-card stat-card--accent">
      <div class="stat-label"><?= t('dash_revenue') ?> <?= Helpers::e($cur) ?></div>
      <div class="stat-value stat-value--mono"><?= Helpers::formatPrice($amount, $cur) ?></div>
      <div class="stat-sub"><?= t('dash_revenue_sub') ?></div>
    </div>
    <?php endforeach; ?>
  <?php else: ?>
    <div class="stat-card stat-card--muted">
      <div class="stat-label"><?= t('dash_revenue') ?></div>
      <div class="stat-value" style="font-size:1rem;color:var(--muted)"><?= t('dash_run_cron') ?></div>
      <div class="stat-sub"><?= t('dash_run_cron_sub') ?></div>
    </div>
  <?php endif; ?>
</div>

<!-- Filtr stavů -->
<div class="status-filter-bar">
  <span class="filter-label"><?= t('dash_filter_label') ?></span>
  <div class="filter-statuses">
    <button class="filter-btn filter-btn--all filter-btn--active-all"
            onclick="toggleStatus('__all__')"><?= t('all') ?></button>
    <?php foreach ($allStatuses as $st):
      $stArr   = Helpers::statusLabel($st);
      $stLabel = $stArr[0]; $stColor = $stArr[1]; $stBg = $stArr[2];
    ?>
    <button class="filter-btn filter-btn--active"
            data-status="<?= $st ?>"
            style="--fc:<?= $stColor ?>;--fbg:<?= $stBg ?>"
            onclick="toggleStatus('<?= $st ?>')">
      <span class="status-dot" style="background:<?= $stColor ?>"></span>
      <?= Helpers::e($stLabel) ?>
    </button>
    <?php endforeach; ?>
  </div>
</div>

<!-- Obchody -->
<?php foreach ($allStoresData as $sd):
  $store       = $sd['store'];
  $orders      = $sd['orders'];
  $color       = Helpers::e($store['color']);
  $storeId     = (int)$store['id'];
  $previewRows = $sd['previewRows'];
  $visible     = array_slice($orders, 0, $previewRows);
  $hidden      = array_slice($orders, $previewRows);
  $hasMore     = !empty($hidden);
?>
<section class="store-section" id="store-<?= $storeId ?>">
  <div class="store-header">
    <div class="store-header-left">
      <span class="store-dot" style="background:<?= $color ?>"></span>
      <div>
        <div class="store-name"><?= Helpers::e($store['name']) ?></div>
        <div class="store-url"><?= Helpers::e($store['url']) ?></div>
      </div>
    </div>
    <div class="store-header-right">
      <?php if (!empty($sd['snapshots'])): ?>
      <div class="revenue-group">
        <?php foreach ($sd['snapshots'] as $snap): ?>
        <div class="revenue-badge revenue-badge--alltime"
             style="border-color:<?= $color ?>55;color:<?= $color ?>;background:<?= $color ?>0d;">
          <span class="revenue-badge-amount"><?= Helpers::formatPrice((float)$snap['net_revenue'], $snap['currency']) ?></span>
          <span class="revenue-badge-label"><?= t('dash_revenue_total', Helpers::numberFormat((int)$snap['total_orders'])) ?></span>
        </div>
        <?php endforeach; ?>
        <?php if ($sd['snapshotAge']): ?>
        <span class="snapshot-age"><?= t('snapshot_age', Helpers::formatRelative($sd['snapshotAge'])) ?></span>
        <?php endif; ?>
      </div>
      <?php elseif (!empty($sd['recentRevenue'])): ?>
      <div class="revenue-group">
        <?php foreach ($sd['recentRevenue'] as $rcur => $amount): ?>
        <div class="revenue-badge" style="border-color:<?= $color ?>44;color:<?= $color ?>;">
          <span class="revenue-badge-amount"><?= Helpers::formatPrice($amount, $rcur) ?></span>
          <span class="revenue-badge-label"><?= t('dash_revenue_recent', (int)$store['max_orders']) ?></span>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
      <div class="store-meta-right">
        <span class="store-count"><?= t('dash_orders_count', count($orders), $sd['cachedTotal']) ?></span>
        <button class="btn btn--xs btn--secondary"
                onclick="refreshStore(<?= $storeId ?>, this)" title="<?= t('nav_refresh') ?>">↺</button>
      </div>
    </div>
  </div>

  <?php if (empty($orders)): ?>
    <div class="orders-wrap">
      <div class="empty-orders"><?= t('dash_no_orders') ?></div>
    </div>
  <?php else: ?>
  <div class="orders-wrap">
    <table class="orders-table">
      <thead>
        <tr>
          <th><?= t('order_number') ?></th>
          <th><?= t('order_date') ?></th>
          <th><?= t('order_customer') ?></th>
          <th><?= t('order_total') ?></th>
          <th><?= t('order_status') ?></th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($visible as $o): renderOrderRow($o, $storeId, $color, $store['url']); endforeach; ?>
      </tbody>
      <?php if ($hasMore): ?>
      <tbody class="hidden-orders" id="hidden-<?= $storeId ?>" style="display:none">
        <?php foreach ($hidden as $o): renderOrderRow($o, $storeId, $color, $store['url']); endforeach; ?>
      </tbody>
      <?php endif; ?>
    </table>
    <?php if ($hasMore): ?>
    <div class="show-more-wrap" id="more-wrap-<?= $storeId ?>">
      <button class="btn-show-more" onclick="showMore(<?= $storeId ?>, this)">
        <?= t('dash_show_more', count($hidden)) ?>
      </button>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</section>
<?php endforeach; ?>

<?php if (empty($stores)): ?>
<div class="empty-page">
  <div class="empty-icon">🛒</div>
  <p><?= t('dash_no_stores') ?><br>
  <a href="settings.php?tab=add"><?= t('dash_add_first') ?></a></p>
</div>
<?php endif; ?>

<div id="toast" class="toast" style="display:none"></div>
<script>
const CSRF = <?= json_encode(Auth::csrfToken()) ?>;
const I18N = {
  order_detail_customer : <?= json_encode(t('order_detail_customer')) ?>,
  order_detail_email    : <?= json_encode(t('order_detail_email')) ?>,
  order_detail_phone    : <?= json_encode(t('order_detail_phone')) ?>,
  order_detail_payment  : <?= json_encode(t('order_detail_payment')) ?>,
  order_detail_note     : <?= json_encode(t('order_detail_note')) ?>,
  order_items_product   : <?= json_encode(t('order_items_product')) ?>,
  order_items_qty       : <?= json_encode(t('order_items_qty')) ?>,
  order_items_unit      : <?= json_encode(t('order_items_unit')) ?>,
  order_items_total     : <?= json_encode(t('order_items_total')) ?>,
  order_items_empty     : <?= json_encode(t('order_items_empty')) ?>,
  order_error           : <?= json_encode(t('order_error')) ?>,
  toast_refreshing      : <?= json_encode(t('toast_refreshing')) ?>,
  toast_refresh_ok      : <?= json_encode(t('toast_refresh_ok')) ?>,
  toast_refresh_err     : <?= json_encode(t('toast_refresh_err')) ?>,
  toast_refreshed       : <?= json_encode(t('toast_refreshed')) ?>,
  toast_testing         : <?= json_encode(t('toast_testing')) ?>,
  toast_net_error       : <?= json_encode(t('toast_net_error')) ?>,
  open_in_shop          : <?= json_encode(t('open_in_shop')) ?>,
};
</script>
<?php require __DIR__ . '/views/layout_bottom.php'; ?>
