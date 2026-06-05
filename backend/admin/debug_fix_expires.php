<?php
/**
 * 临时调试：补齐 active 订单的 expires_at (激活时设的 20 分钟倒计时)
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

// 找出 active 订单但 expires_at 为 null 的
$activeOrders = $db->query(
    "SELECT id, status, activated_at, expires_at FROM orders WHERE status = 'active' AND (expires_at IS NULL OR expires_at < NOW())"
)->fetchAll();

$timeoutMin = intval(getSetting($db, 'order_timeout', '20'));

$fixed = [];
foreach ($activeOrders as $o) {
    // 如果 expires_at 为 null 或者过期了, 重设为 activated_at + 20min
    $baseTime = $o['activated_at'] ?? 'NOW()';
    $db->query(
        "UPDATE orders SET expires_at = DATE_ADD(?, INTERVAL ? MINUTE) WHERE id = ?",
        [$baseTime, $timeoutMin, $o['id']]
    );
    $newExpires = $db->query("SELECT expires_at FROM orders WHERE id = ?", [$o['id']])->fetchColumn();
    $fixed[] = ['id' => $o['id'], 'activated_at' => $o['activated_at'], 'new_expires_at' => $newExpires];
}

echo json_encode(['success' => true, 'fixed_count' => count($fixed), 'fixed' => $fixed], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
