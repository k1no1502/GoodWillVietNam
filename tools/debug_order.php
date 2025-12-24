<?php
require_once __DIR__ . '/../config/database.php';

$id = (int)($argv[1] ?? 0);
if ($id <= 0) {
    fwrite(STDERR, "Usage: php tools/debug_order.php <order_id>\n");
    exit(1);
}

$hasShippingGeo = !empty(Database::fetchAll("SHOW COLUMNS FROM orders LIKE 'shipping_lat'"));
$sql = $hasShippingGeo
    ? "SELECT order_id, status, shipping_last_mile_status, shipping_lat, shipping_lng, shipping_address FROM orders WHERE order_id = ?"
    : "SELECT order_id, status, shipping_last_mile_status, shipping_address FROM orders WHERE order_id = ?";
$o = Database::fetch($sql, [$id]);
var_export($o);
echo PHP_EOL;
