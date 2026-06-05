<?php
/**
 * 临时调试：补齐 active 订单的 expires_at，并把过期的标 expired
 * 访问: https://sms.niceapp.eu.cc/admin/debug_fix_expires.php?token=DEBUG_TOKEN_2026
 *
 * 用完即删
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../helpers/functions.php';

$SECRET = 'DEBUG_TOKEN_2026';
$token = $_GET['token'] ?? '';
if ($token !== $SECRET) { http_response_code(403); echo json_encode(['error' => 'bad token']); exit; }

$db = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT);
$timeoutMin = intval(getSetting($db, 'order_timeout', '20'));

// Step 1: 找出 active 订单但 expires_at 为 null 的
$nullExp = $db->query(
    "SELECT id, activated_at, hero_order_id FROM orders WHERE status = 'active' AND expires_at IS NULL"
)->fetchAll();

$fixedNull = [];
foreach ($nullExp as $o) {
    $baseTime = $o['activated_at'] ?? date('Y-m-d H:i:s');
    $db->query(
        "UPDATE orders SET expires_at = DATE_ADD(?, INTERVAL ? MINUTE) WHERE id = ?",
        [$baseTime, $timeoutMin, $o['id']]
    );
    $newExpires = $db->query("SELECT expires_at FROM orders WHERE id = ?", [$o['id']])->fetchColumn();
    $fixedNull[] = ['id' => $o['id'], 'new_expires_at' => $newExpires];
}

// Step 2: 找出 active 订单但已过期的
$overdue = $db->query(
    "SELECT id, hero_order_id, phone_number, expires_at FROM orders WHERE status = 'active' AND expires_at < NOW()"
)->fetchAll();

$expired = [];
foreach ($overdue as $o) {
    $db->query(
        "UPDATE orders SET status = 'expired', updated_at = NOW() WHERE id = ?",
        [$o['id']]
    );
    $expired[] = [
        'id' => $o['id'],
        'was_expires_at' => $o['expires_at'],
        'hero_order_id' => $o['hero_order_id'],
    ];
}

echo json_encode([
    'success' => true,
    'fixed_null_count' => count($fixedNull),
    'expired_overdue_count' => count($expired),
    'fixed_null' => $fixedNull,
    'expired_overdue' => $expired,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
