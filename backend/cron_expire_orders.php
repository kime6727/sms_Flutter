<?php
/**
 * 订单过期处理脚本
 * 
 * 说明：
 * 1. 将超过过期时间的 pending 状态订单标记为 expired
 * 2. 不退还已扣积分
 * 3. 发送过期通知给用户
 * 
 * 运行方式：
 * - 手动运行: php cron_expire_orders.php
 * - Crontab: 每10分钟运行一次 (crontab -e 添加定时任务)
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/lib/Database.php';

$db = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT);

// 获取配置的过期时间（小时），默认72小时
$settings = $db->query("SELECT key, value FROM system_settings WHERE key = 'pending_order_expire_hours'")->fetch();
$expireHours = $settings ? intval($settings['value']) : 72;

if ($expireHours < 1) {
    $expireHours = 72;
}

echo "[" . date('Y-m-d H:i:s') . "] 开始执行订单过期任务，过期时间: {$expireHours} 小时\n";

// 查找所有已过期的 pending 订单
$expiredOrders = $db->query(
    "SELECT * FROM orders 
     WHERE status = 'pending' 
     AND expires_at IS NOT NULL 
     AND expires_at < NOW()",
    []
)->fetchAll();

if (empty($expiredOrders)) {
    echo "[" . date('Y-m-d H:i:s') . "] 没有需要过期的订单\n";
    exit(0);
}

echo "[" . date('Y-m-d H:i:s') . "] 找到 " . count($expiredOrders) . " 个待过期订单\n";

$expiredCount = 0;
$notifiedCount = 0;

foreach ($expiredOrders as $order) {
    try {
        // 更新订单状态为 expired
        $db->query(
            "UPDATE orders SET status = 'expired', updated_at = NOW() WHERE id = ?",
            [$order['id']]
        );
        $expiredCount++;
        
        // 发送过期通知
        try {
            $db->insert('notifications', [
                'user_id' => $order['user_id'],
                'type' => 'order_expired',
                'title' => '号码已过期',
                'content' => "订单 #{$order['id']} 中的 {$order['service_name']} - {$order['country_name']} 号码已过期",
                'related_order_id' => $order['id'],
                'created_at' => date('Y-m-d H:i:s')
            ]);
            $notifiedCount++;
        } catch (Exception $e) {
            echo "  通知失败 (订单 {$order['id']}): " . $e->getMessage() . "\n";
        }
        
        echo "  已过期: {$order['id']} ({$order['service_name']} - {$order['country_name']})\n";
    } catch (Exception $e) {
        echo "  处理失败 (订单 {$order['id']}): " . $e->getMessage() . "\n";
    }
}

echo "[" . date('Y-m-d H:i:s') . "] 完成！已过期: {$expiredCount} 个，已通知: {$notifiedCount} 个\n";
