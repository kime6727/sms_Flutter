<?php
/**
 * 定时任务：检查订单超时
 * 使用 Cron 每分钟执行一次
 * 
 * 功能：
 * 1. 检查 active 订单是否超过 20 分钟（号码使用已过期）
 * 2. 检查 pending 订单是否超过 24 小时未激活
 * 
 * 注意：短信接收已通过 Webhook 实现，此脚本不再轮询短信
 * 
 * 使用方法:
 * crontab -e
 * 添加: * * * * * php /path/to/backend/cron/check-timeout.php >> /var/log/sms-receiver/cron.log 2>&1
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/HeroSMS.php';
require_once __DIR__ . '/../lib/KeyManager.php';
require_once __DIR__ . '/../helpers/functions.php';

// 初始化
$db = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT);
$heroSmsApiKey = KeyManager::getHeroSmsApiKey();
$heroSMS = null;
if (!empty($heroSmsApiKey)) {
    $heroSMS = new HeroSMS($heroSmsApiKey, HEROSMS_BASE_URL);
}

$logFile = '/var/log/sms-receiver/cron.log';
$logDir = dirname($logFile);

// 确保日志目录存在
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}

function logMessage($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message\n";
    @file_put_contents($logFile, $logMessage, FILE_APPEND);
    echo $logMessage;
}

logMessage("开始检查超时订单");

// 读取配置：激活超时分钟数 / 购买未激活过期小时数
$activationTimeoutMinutes = intval(getSetting($db, 'order_timeout', '20'));
$pendingExpireHours = intval(getSetting($db, 'pending_order_expire_hours', '72'));

// ============================================
// 1. 检查 active 订单是否超过 20 分钟
// ============================================
$activeOrders = $db->query(
    "SELECT id, user_id, hero_order_id, phone_number, total_price, expires_at
     FROM orders
     WHERE status = 'active'
     AND expires_at IS NOT NULL
     AND expires_at < NOW()
     ORDER BY expires_at ASC"
)->fetchAll();

logMessage("发现 " . count($activeOrders) . " 个超时的 active 订单");

foreach ($activeOrders as $order) {
    $heroOrderId = $order['hero_order_id'];
    $orderId = $order['id'];
    $userId = $order['user_id'];
    $totalPrice = intval($order['total_price']);

    logMessage("处理超时订单: $orderId (HeroID: $heroOrderId, 过期时间: {$order['expires_at']})");

    // 业务规则:激活后未在超时时间内收到验证码 = 订单过期,**积分不退**
    // 原因:号码已分配给 HeroSMS 平台,平台已收取费用
    $db->beginTransaction();
    try {
        // 标记为过期（不退积分）
        $db->query(
            "UPDATE orders SET status = 'expired', updated_at = NOW() WHERE id = ?",
            [$orderId]
        );

        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        logMessage("订单 $orderId 状态更新失败: " . $e->getMessage());
    }

    // 取消 HeroSMS 号码（释放资源）
    if ($heroSMS && $heroOrderId) {
        try {
            $cancelResult = $heroSMS->cancelNumber($heroOrderId);
            if ($cancelResult['success']) {
                logMessage("订单 $orderId HeroSMS 号码已取消");
            } else {
                logMessage("订单 $orderId HeroSMS 取消失败: " . ($cancelResult['message'] ?? '未知错误'));
            }
        } catch (Exception $e) {
            logMessage("订单 $orderId 取消 HeroSMS 号码异常: " . $e->getMessage());
        }
    }

    // 发送过期通知（提示用户,不再说"积分退回"）
    try {
        $orderInfo = $db->query(
            "SELECT user_id, service_id, country_id FROM orders WHERE id = ?",
            [$orderId]
        )->fetch();

        if ($orderInfo) {
            $serviceInfo = $db->query(
                "SELECT name FROM services WHERE id = ?",
                [$orderInfo['service_id']]
            )->fetchColumn();

            $countryInfo = $db->query(
                "SELECT name FROM countries WHERE id = ?",
                [$orderInfo['country_id']]
            )->fetchColumn();

            $db->insert('notifications', [
                'user_id' => $orderInfo['user_id'],
                'type' => 'order_expired',
                'title' => '订单已过期',
                'body' => "订单 #{$orderId} 中的 {$serviceInfo} - {$countryInfo} 已超过 {$activationTimeoutMinutes} 分钟未收到验证码,订单已过期(号码资源已被占用,积分不予退还)",
                'related_order_id' => $orderId,
                'created_at' => date('Y-m-d H:i:s')
            ]);

            logMessage("订单 $orderId 过期通知已发送");
        }
    } catch (Exception $e) {
        logMessage("发送通知失败: " . $e->getMessage());
    }
}

// ============================================
// 2. 检查 pending 订单是否超过 N 小时未激活（默认 72 = 3 天）
// ============================================
$pendingOrders = $db->query(
    "SELECT id, user_id, service_id, country_id, total_price, purchase_expires_at, created_at
     FROM orders
     WHERE status = 'pending'
     AND (
         (purchase_expires_at IS NOT NULL AND purchase_expires_at < NOW())
         OR (purchase_expires_at IS NULL AND created_at < DATE_SUB(NOW(), INTERVAL ? HOUR))
     )",
    [$pendingExpireHours]
)->fetchAll();

logMessage("发现 " . count($pendingOrders) . " 个超时的 pending 订单");

foreach ($pendingOrders as $order) {
    $orderId = $order['id'];
    $userId = $order['user_id'];
    $totalPrice = intval($order['total_price']);

    logMessage("处理过期 pending 订单: $orderId");

    // 业务规则: 购买后未在 N 小时内激活 = 订单过期,**积分不退**
    $db->beginTransaction();
    try {
        $db->query(
            "UPDATE orders SET status = 'expired', updated_at = NOW() WHERE id = ?",
            [$orderId]
        );

        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        logMessage("订单 $orderId 状态更新失败: " . $e->getMessage());
    }

    // 发送过期通知
    try {
        $serviceInfo = $db->query(
            "SELECT name FROM services WHERE id = ?",
            [$order['service_id']]
        )->fetchColumn();

        $countryInfo = $db->query(
            "SELECT name FROM countries WHERE id = ?",
            [$order['country_id']]
        )->fetchColumn();

        $db->insert('notifications', [
            'user_id' => $userId,
            'type' => 'order_expired',
            'title' => '订单已过期',
            'body' => "订单 #{$orderId} 中的 {$serviceInfo} - {$countryInfo} 已超过 {$pendingExpireHours} 小时未激活,订单已过期(积分不予退还)",
            'related_order_id' => $orderId,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        logMessage("订单 $orderId 过期通知已发送");
    } catch (Exception $e) {
        logMessage("发送通知失败: " . $e->getMessage());
    }
}

logMessage("超时检查完成");
?>
