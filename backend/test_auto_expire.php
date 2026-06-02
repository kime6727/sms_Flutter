<?php
/**
 * 测试自动过期功能
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/lib/Database.php';
require_once __DIR__ . '/lib/HeroSMS.php';
require_once __DIR__ . '/lib/KeyManager.php';

$db = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT);
$heroSmsApiKey = KeyManager::getHeroSmsApiKey();
$heroSMS = $heroSmsApiKey ? new HeroSMS($heroSmsApiKey, HEROSMS_BASE_URL) : null;

echo "=== 测试自动过期功能 ===\n\n";

// 1. 查询超时的active订单
echo "1. 查询超时的active订单...\n";
$expiredOrders = $db->query(
    "SELECT id, status, expires_at, TIMESTAMPDIFF(MINUTE, expires_at, NOW()) as minutes_over 
     FROM orders 
     WHERE status = 'active' AND expires_at < NOW()
     LIMIT 10"
)->fetchAll();

echo "找到 " . count($expiredOrders) . " 个超时订单\n";
foreach ($expiredOrders as $order) {
    echo "  - {$order['id']}: 超时 {$order['minutes_over']} 分钟\n";
}
echo "\n";

// 2. 执行自动过期检查
echo "2. 执行自动过期检查...\n";
$activeOrders = $db->query(
    "SELECT id, hero_order_id, status, expires_at
     FROM orders
     WHERE status = 'active' AND expires_at IS NOT NULL"
)->fetchAll();

$expiredCount = 0;
foreach ($activeOrders as $order) {
    $expiresAt = strtotime($order['expires_at']);
    if ($expiresAt > 0 && time() > $expiresAt) {
        $db->query(
            "UPDATE orders SET status = 'expired', updated_at = NOW() WHERE id = ?",
            [$order['id']]
        );
        if ($order['hero_order_id']) {
            $heroSMS->cancelNumber($order['hero_order_id']);
        }
        echo "  ✓ 订单 {$order['id']} 已标记为过期\n";
        $expiredCount++;
    }
}

if ($expiredCount === 0) {
    echo "  没有需要过期的订单\n";
}
echo "\n";

// 3. 验证结果
echo "3. 验证结果...\n";
$stillExpired = $db->query(
    "SELECT COUNT(*) FROM orders WHERE status = 'active' AND expires_at < NOW()"
)->fetchColumn();

if ($stillExpired == 0) {
    echo "  ✓ 所有超时订单已正确标记为过期\n";
} else {
    echo "  ✗ 还有 {$stillExpired} 个超时订单未处理\n";
}

echo "\n=== 测试完成 ===\n";
