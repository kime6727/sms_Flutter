<?php
/**
 * SMS 接码平台 - PHP 后端入口文件
 * 模块化重构版本
 */

// CORS 配置 - 最大兼容性，允许所有来源访问
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Device-Id, X-API-Key, X-Requested-With, Accept, Origin');
header('Access-Control-Expose-Headers: Content-Type, Authorization, X-Total-Count');
header('Access-Control-Max-Age: 86400');
header('Content-Type: application/json; charset=utf-8');

// 处理 OPTIONS 预检请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// 加载配置
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';

// 加载 .env 文件并定义 APP_URL 常量
$env_file = __DIR__ . '/.env';
if (file_exists($env_file)) {
    $env = parse_ini_file($env_file, false, INI_SCANNER_RAW);
    if (!defined('APP_URL')) {
        define('APP_URL', $env['APP_URL'] ?? '');
    }
}

// 加载库文件
require_once __DIR__ . '/lib/Database.php';
require_once __DIR__ . '/lib/HeroSMS.php';
require_once __DIR__ . '/lib/AppleIAP.php';
require_once __DIR__ . '/lib/Auth.php';
require_once __DIR__ . '/lib/Logger.php';
require_once __DIR__ . '/lib/KeyManager.php';
require_once __DIR__ . '/lib/RateLimiter.php';

// 加载辅助函数
require_once __DIR__ . '/helpers/functions.php';

// 统一错误响应格式
function apiSuccess($data = null, $message = 'success') {
    return json_encode([
        'success' => true,
        'message' => $message,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);
}

function apiError($error, $httpCode = 400, $code = null, $extraData = null) {
    http_response_code($httpCode);
    $response = [
        'success' => false,
        'error' => $error
    ];
    if ($code !== null) {
        $response['code'] = $code;
    }
    if ($extraData !== null && is_array($extraData)) {
        $response = array_merge($response, $extraData);
    }
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

function apiBadRequest($error) {
    apiError($error, 400);
}

function apiUnauthorized($error = '未授权') {
    apiError($error, 401);
}

function apiNotFound($error = '资源不存在') {
    apiError($error, 404);
}

function apiServerError($error = '服务器内部错误') {
    apiError($error, 500);
}

// 初始化数据库
$db = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT);

// 初始化服务 - hero-sms API 密钥从数据库安全读取
$heroSmsApiKey = KeyManager::getHeroSmsApiKey();
if (empty($heroSmsApiKey)) {
    error_log('SECURITY ERROR: hero-sms API key is not set in database');
}
$heroSMS = new HeroSMS($heroSmsApiKey, HEROSMS_BASE_URL);
$appleIAP = new AppleIAP(APPLE_SHARED_SECRET);

// 自动注册 Webhook（仅在 URL 有变化时更新 HeroSMS）
if (!empty($heroSmsApiKey) && defined('APP_URL') && !empty(APP_URL)) {
    $cachedWebhookUrl = $db->query(
        "SELECT `value` FROM system_settings WHERE `key` = 'webhook_configured_url'"
    )->fetchColumn();
    $currentWebhookUrl = rtrim(APP_URL, '/') . '/webhook/hero-sms';

    if ($cachedWebhookUrl !== $currentWebhookUrl) {
        try {
            $heroSMS->setWebhookUrl($currentWebhookUrl);
            $db->query(
                "INSERT INTO system_settings (`key`, `value`, `updated_at`) VALUES ('webhook_configured_url', ?, NOW())
                 ON DUPLICATE KEY UPDATE `value` = ?, `updated_at` = NOW()",
                [$currentWebhookUrl, $currentWebhookUrl]
            );
            error_log("Webhook auto-registered: $currentWebhookUrl");
        } catch (Exception $e) {
            error_log("Webhook auto-registration failed: " . $e->getMessage());
        }
    }
}

// 操作日志辅助函数
function logUserActivity($db, $userId, $action, $resource = null, $resourceId = null, $details = null) {
    try {
        $ipAddress = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        if (strlen($userAgent) > 255) {
            $userAgent = substr($userAgent, 0, 255);
        }
        
        $db->insert('user_activity_logs', [
            'user_id' => $userId,
            'action' => $action,
            'resource' => $resource,
            'resource_id' => $resourceId,
            'details' => $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    } catch (Exception $e) {
        // 日志记录失败不影响主流程
        error_log("Failed to log user activity: " . $e->getMessage());
    }
}

// 解析请求路径和方法
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($requestUri, PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// 移除前缀（如果有）
if (strpos($path, '/api') === 0) {
    $path = substr($path, 4);
}

// 确保路径以 / 开头
if (strpos($path, '/') !== 0) {
    $path = '/' . $path;
}

// API Key 验证中间件
$publicPaths = ['/health', '/banners', '/settings', '/auth/password-login', '/auth/manual-register', '/auth/forgot-password', '/auth/reset-password'];
$isPublicPath = in_array($path, $publicPaths);

// 检查是否需要 API Key 验证（公开路径不需要）
if (!$isPublicPath) {
    $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? null;
    $expectedApiKey = KeyManager::get('app_api_key');
    
    if (!empty($expectedApiKey) && $apiKey !== $expectedApiKey) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'API Key 无效或缺失'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// 记录请求开始时间
$requestStartTime = microtime(true);

try {
    // 加载路由
    require_once __DIR__ . '/routes/auth.php';
    require_once __DIR__ . '/routes/user.php';
    require_once __DIR__ . '/routes/orders.php';
    require_once __DIR__ . '/routes/services.php';
    require_once __DIR__ . '/routes/topup.php';
    require_once __DIR__ . '/routes/notifications.php';
    require_once __DIR__ . '/routes/payment.php';
    require_once __DIR__ . '/routes/system.php';
    
    // 如果没有匹配的路由，返回404
    apiNotFound('接口不存在');
    
} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage());
    apiServerError('服务器内部错误');
} finally {
    // 记录请求日志
    $requestEndTime = microtime(true);
    $duration = round(($requestEndTime - $requestStartTime) * 1000, 2);
    $currentUserId = getCurrentUserIdFromToken();
    Logger::logRequest($method, $path, $currentUserId ?? 'guest', $duration, http_response_code());
}
?>
