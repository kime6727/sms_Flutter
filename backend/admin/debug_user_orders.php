<?php
/**
 * 临时调试：看用户的 orders
 * 访问: https://sms.niceapp.eu.cc/admin/debug_user_orders.php?email=xxx&token=DEBUG_TOKEN_2026
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../helpers/functions.php';

$SECRET = 'DEBUG_TOKEN_2026';
$token = $_GET['token'] ?? '';
$email = $_GET['email'] ?? '';
$userId = $_GET['user_id'] ?? '';

if ($token !== $SECRET) { http_response_code(403); echo json_encode(['error' => 'bad token']); exit; }

$db = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT);

if ($userId) {
    $user = $db->query("SELECT id, email, balance FROM users WHERE id = ?", [$userId])->fetch();
} else if ($email) {
    $user = $db->query("SELECT id, email, balance FROM users WHERE email = ?", [$email])->fetch();
}
if (!$user) { http_response_code(404); echo json_encode(['error' => 'user not found']); exit; }

$orders = $db->query(
    "SELECT id, service_id, country_id, quantity, batch_id, purchase_expires_at, total_price, status, created_at
     FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 30",
    [$user['id']]
)->fetchAll();

// 看 orders 表 schema
$cols = $db->query("SHOW COLUMNS FROM orders")->fetchAll();

echo json_encode([
    'user' => $user,
    'orders_count' => count($orders),
    'orders' => $orders,
    'orders_table_columns' => array_map(function($c) { return $c['Field'] . ($c['Null'] === 'NO' ? ' NOT NULL' : ' NULL') . ($c['Key'] ? ' ['.$c['Key'].']' : ''); }, $cols),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
