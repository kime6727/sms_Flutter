<?php
/**
 * HeroSMS Webhook 接收接口
 * 
 * 当 HeroSMS 收到短信后，会 POST 到此接口
 * 
 * 配置方法：
 * 1. 在 HeroSMS 后台 → Settings → Webhooks
 * 2. 添加地址：https://你的域名/webhook/hero-sms
 * 
 * 数据格式：
 * {
 *     "activationId": 123456,
 *     "service": "go",
 *     "text": "Sms text",
 *     "code": "12345",
 *     "country": 2,
 *     "receivedAt": "2026-01-29T11:28:14Z"
 * }
 */

// 设置错误报告
error_reporting(0);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/lib/Database.php';
require_once __DIR__ . '/lib/HeroSMS.php';

// 只接受 POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// 读取请求体
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);

if (!$input || !isset($input['activationId'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid payload', 'received' => substr($rawInput, 0, 200)]);
    exit;
}

$activationId = $input['activationId'];
$smsText = $input['text'] ?? '';
$smsCode = $input['code'] ?? null;
$receivedAt = $input['receivedAt'] ?? date('Y-m-d H:i:s');
$service = $input['service'] ?? '';
$country = $input['country'] ?? 0;

// 转换时间格式
if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $receivedAt)) {
    $receivedAt = str_replace('Z', '', $receivedAt);
}

// 日志
$logFile = __DIR__ . '/logs/webhook.log';
$logDir = dirname($logFile);
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}

function logMessage($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message\n";
    @file_put_contents($logFile, $logMessage, FILE_APPEND);
}

logMessage("Webhook 收到: activationId=$activationId, service=$service, country=$country, code=$smsCode");

try {
    $db = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT);
    
    // 根据 hero_order_id 查找订单
    $order = $db->query(
        "SELECT id, user_id, status, expires_at FROM orders WHERE hero_order_id = ?",
        [$activationId]
    )->fetch();
    
    if (!$order) {
        logMessage("订单不存在: hero_order_id=$activationId");
        http_response_code(200);
        echo json_encode(['status' => 'ignored', 'reason' => 'order not found']);
        exit;
    }
    
    $orderId = $order['id'];
    $userId = $order['user_id'];
    $currentStatus = $order['status'];
    $expiresAt = $order['expires_at'];
    
    // 如果订单已经是完成状态，跳过
    if ($currentStatus === 'completed') {
        logMessage("订单已完成: orderId=$orderId");
        http_response_code(200);
        echo json_encode(['status' => 'ignored', 'reason' => 'order already completed']);
        exit;
    }
    
    // 检查订单是否已超时（超过20分钟）
    if ($expiresAt && strtotime($expiresAt) < time()) {
        logMessage("订单已超时: orderId=$orderId, expires_at=$expiresAt");
        http_response_code(200);
        echo json_encode(['status' => 'ignored', 'reason' => 'order expired']);
        exit;
    }
    
    // 如果没有提取到验证码，尝试从短信文本中提取
    if (empty($smsCode)) {
        $smsCode = extractVerificationCode($smsText);
    }
    
    // 使用事务保证数据一致性
    $db->beginTransaction();
    try {
        // 保存短信到数据库
        $existingSms = $db->query(
            "SELECT id FROM sms_messages WHERE order_id = ? AND content = ?",
            [$orderId, $smsText]
        )->fetch();
        
        if (!$existingSms) {
            $db->insert('sms_messages', [
                'order_id' => $orderId,
                'sender' => 'HeroSMS_Webhook',
                'content' => $smsText,
                'code' => $smsCode,
                'received_at' => $receivedAt
            ]);
            logMessage("短信已保存: orderId=$orderId, code=$smsCode");
        }
        
        // 更新订单状态为已完成
        $db->query(
            "UPDATE orders SET status = 'completed', completed_at = NOW(), sms_code = ? WHERE id = ?",
            [$smsCode, $orderId]
        );
        logMessage("订单已更新为完成: orderId=$orderId");
        
        // 创建通知
        $db->insert('notifications', [
            'user_id' => $userId,
            'type' => 'sms_received',
            'title' => '验证码已收到',
            'body' => $smsCode ? "您的验证码: $smsCode" : "您收到新的短信，请在订单中查看",
            'related_order_id' => $orderId,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        logMessage("通知已创建: userId=$userId, orderId=$orderId");
        
        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
    
    // 调用 HeroSMS 完成激活（停止计费）- 在事务外执行，避免阻塞
    try {
        require_once __DIR__ . '/lib/KeyManager.php';
        $heroSmsApiKey = KeyManager::getHeroSmsApiKey();
        if ($heroSmsApiKey) {
            $heroSMS = new HeroSMS($heroSmsApiKey, HEROSMS_BASE_URL);
            $completeResult = $heroSMS->complete($activationId);
            if ($completeResult['success']) {
                logMessage("HeroSMS 激活已完成: activationId=$activationId");
            } else {
                logMessage("HeroSMS 完成激活失败: " . ($completeResult['message'] ?? '未知错误'));
            }
        } else {
            logMessage("HeroSMS API key not configured in database");
        }
    } catch (Exception $e) {
        logMessage("调用 HeroSMS complete 失败: " . $e->getMessage());
    }
    
    // 返回 200 告诉 HeroSMS 已成功接收
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'order_id' => $orderId,
        'code' => $smsCode
    ]);
    
} catch (Exception $e) {
    logMessage("处理失败: " . $e->getMessage());
    // 不返回 200，让 HeroSMS 重试
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

/**
 * 从短信内容中提取验证码
 */
function extractVerificationCode($sms) {
    // 匹配 "code: 123456" 格式
    if (preg_match('/code[:\s=]+(\d{4,6})/i', $sms, $matches)) {
        return $matches[1];
    }
    
    // 匹配 "verification code: 123456" 格式
    if (preg_match('/verification\s+code[:\s=]+(\d{4,6})/i', $sms, $matches)) {
        return $matches[1];
    }
    
    // 匹配 "Your code is 123456" 格式
    if (preg_match('/code\s+is\s+(\d{4,6})/i', $sms, $matches)) {
        return $matches[1];
    }
    
    // 匹配 "Код: 123456" 俄语格式
    if (preg_match('/Код[:\s=]+(\d{4,6})/i', $sms, $matches)) {
        return $matches[1];
    }
    
    // 匹配 4-6 位连续数字
    if (preg_match('/\b(\d{4,6})\b/', $sms, $matches)) {
        return $matches[1];
    }
    
    return null;
}
