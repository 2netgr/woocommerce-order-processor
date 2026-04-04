<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/src/Lang.php';
require_once __DIR__ . '/src/Auth.php';
require_once __DIR__ . '/src/Helpers.php';
require_once __DIR__ . '/src/Database.php';
require_once __DIR__ . '/src/WooApi.php';

Auth::init();
if (!Auth::isLoggedIn()) {
    Helpers::json(array('error' => 'Unauthorized'), 401);
}

$db     = Database::get();
$action = Helpers::get('action');

if ($action === 'order_detail') {
    $storeId = (int)Helpers::get('store_id');
    $orderId = (int)Helpers::get('order_id');
    $store   = $db->getStore($storeId);
    if (!$store) Helpers::json(array('error' => 'Obchod nenalezen'), 404);

    $api    = new WooApi($store);
    $detail = $api->fetchOrderDetail($orderId);
    if ($api->isError($detail)) Helpers::json(array('error' => $api->errorMessage($detail)), 502);
    Helpers::json($detail);
}

if ($action === 'test_connection') {
    Auth::verifyCsrf();
    $storeId = (int)Helpers::get('store_id');
    $store   = $db->getStore($storeId);
    if (!$store) Helpers::json(array('error' => 'Obchod nenalezen'), 404);
    $api = new WooApi($store);
    Helpers::json($api->testConnection());
}

if ($action === 'refresh_store') {
    Auth::verifyCsrf();
    $storeId = (int)Helpers::get('store_id');
    $store   = $db->getStore($storeId);
    if (!$store) Helpers::json(array('error' => 'Obchod nenalezen'), 404);

    $db->invalidateCache($storeId);
    $api    = new WooApi($store);
    $orders = $api->fetchOrders((int)$store['max_orders']);
    if ($api->isError($orders)) Helpers::json(array('error' => $api->errorMessage($orders)), 502);
    $db->upsertOrders($storeId, $orders);
    Helpers::json(array('ok' => true, 'count' => count($orders)));
}

if ($action === 'refresh_all') {
    Auth::verifyCsrf();
    $stores  = $db->getStores(true);
    $results = array();
    foreach ($stores as $store) {
        $db->invalidateCache((int)$store['id']);
        $api    = new WooApi($store);
        $orders = $api->fetchOrders((int)$store['max_orders']);
        if ($api->isError($orders)) {
            $results[] = array('store' => $store['name'], 'error' => $api->errorMessage($orders));
        } else {
            $db->upsertOrders((int)$store['id'], $orders);
            $results[] = array('store' => $store['name'], 'ok' => true, 'count' => count($orders));
        }
    }
    Helpers::json(array('results' => $results));
}

Helpers::json(array('error' => 'Neznámá akce'), 400);
