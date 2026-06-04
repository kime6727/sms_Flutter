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

// ====================
// 全局异常处理：让 500 错误能看到具体原因
// ====================
ini_set('display_errors', '0');
error_reporting(E_ALL);

set_exception_handler(function ($e) {
    $msg = $e->getMessage();
    $file = $e->getFile();
    $line = $e->getLine();
    error_log("[uncaught] " . get_class($e) . ": " . $msg . " at $file:$line");
    $detail = get_class($e) . ": " . $msg . " at " . basename($file) . ":" . $line;
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode([
        'success' => false,
        'error' => '服务器内部错误',
        'detail' => $detail,
    ], JSON_UNESCAPED_UNICODE);
    exit;
});

set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) return false;
    error_log("[php error] $message at $file:$line");
    return false;
});

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
require_once __DIR__ . '/lib/Installer.php';

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

// 提前解析请求路径（在 DB 初始化前就准备好，错误页能正确分流到 /install）
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($requestUri, PHP_URL_PATH) ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// 移除前缀（如果有）
if (strpos($path, '/api') === 0) {
    $path = substr($path, 4);
}

// 确保路径以 / 开头
if (strpos($path, '/') !== 0) {
    $path = '/' . $path;
}

// 初始化数据库 - 失败时输出友好错误页而不是 500 空 body
try {
    $db = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT);
} catch (Exception $e) {
    $errMsg = $e->getMessage();
    error_log("[index] DB init failed: " . $errMsg);

    // 未连接成功时，/install 路径返回友好的诊断页（HTML）
    if (preg_match('#^/install(/.*)?$#', $path)) {
        header('Content-Type: text/html; charset=utf-8');
        http_response_code(503);
        echo <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<title>数据库未就绪 - SMS 接码平台</title>
<style>
  body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
         background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
         min-height: 100vh; margin: 0; display: flex; align-items: center;
         justify-content: center; padding: 20px; }
  .card { background: #fff; border-radius: 12px; box-shadow: 0 20px 60px rgba(0,0,0,0.3);
          max-width: 720px; width: 100%; overflow: hidden; }
  .header { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: #fff; padding: 30px; }
  .header h1 { margin: 0 0 8px 0; font-size: 22px; }
  .body { padding: 30px; }
  .err { background: #fee2e2; color: #991b1b; padding: 16px; border-radius: 8px;
         border-left: 4px solid #dc2626; font-family: monospace; font-size: 13px;
         white-space: pre-wrap; word-break: break-all; }
  table { width: 100%; border-collapse: collapse; margin-top: 16px; font-size: 14px; }
  th, td { padding: 8px 12px; text-align: left; border-bottom: 1px solid #eee; }
  th { color: #666; font-weight: 500; width: 140px; }
  td { font-family: monospace; font-weight: 600; }
  .hint { margin-top: 20px; padding: 12px; background: #fff3cd; color: #856404;
          border-radius: 6px; font-size: 13px; }
</style>
</head>
<body>
<div class="card">
  <div class="header">
    <h1>⚠️ 数据库连接失败</h1>
    <small>SMS 接码平台 - 安装向导</small>
  </div>
  <div class="body">
    <div class="err">$errMsg</div>
    <table>
      <tr><th>Host</th><td>DB_HOST</td></tr>
      <tr><th>Port</th><td>DB_PORT</td></tr>
      <tr><th>Database</th><td>DB_NAME</td></tr>
      <tr><th>User</th><td>DB_USER</td></tr>
    </table>
    <div class="hint">
      💡 请在 dokploy → sms-receiver → Environment 中检查 DB_HOST/DB_PORT/DB_NAME/DB_USER/DB_PASS 是否正确。
      修复后刷新此页重试。
    </div>
  </div>
</div>
</body>
</html>
HTML;
        exit;
    }

    // 其他路径返回 JSON
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(503);
    echo json_encode([
        'success' => false,
        'error' => 'database_unavailable',
        'message' => $errMsg,
        'install_url' => '/install',
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

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

// 解析请求路径和方法（已在上方 DB 初始化前完成）

// WordPress 风格自安装：
//   - 数据库连上后，先检查是否已初始化（admins 表是否存在）
//   - 没初始化但用户访问 /install* 路径：继续走（让 install 路由处理）
//   - 没初始化且访问其它路径：返回 503 引导用户访问 /install
$installer = new Installer();
$_installReady = $installer->checkDatabase($db);
if (!$_installReady && !preg_match('#^/install(/.*)?$#', $path)) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(503);
    echo json_encode([
        'success' => false,
        'error' => 'database_not_initialized',
        'message' => '数据库未初始化，请访问 /install 完成首次安装',
        'install_url' => '/install',
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}
unset($installer);

// API Key 验证中间件
$publicPaths = ['/health', '/banners', '/settings', '/auth/password-login', '/auth/manual-register', '/auth/forgot-password', '/auth/reset-password', '/install', '/install/status', '/install/run'];
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
    require_once __DIR__ . '/routes/install.php';
    
    // ====================
    // Webhook 路由（独立处理，不走 auth 也不走 API Key 中间件）
    // ====================
    if ($path === '/webhook/hero-sms' && $method === 'POST') {
        require __DIR__ . '/webhook.php';
        exit;
    }

    // 临时调试 - 看 service_countries 表的 Telegram/Kazakhstan 记录
    if ($path === '/debug/sc' && $method === 'GET') {
        header('Content-Type: application/json; charset=utf-8');
        $rows = $db->query(
            "SELECT sc.*, s.hero_service_id, s.name as svc_name, c.hero_country_id, c.name_en as country_name
             FROM service_countries sc
             LEFT JOIN services s ON sc.service_id = s.id
             LEFT JOIN countries c ON sc.country_id = c.id
             WHERE s.hero_service_id = 'tg' AND c.name_en = 'Kazakhstan'"
        )->fetchAll();
        echo json_encode(['rows' => $rows], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    // 如果没有匹配的路由，返回404
    apiNotFound('接口不存在');

} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
    // 调试阶段：把 detail 透传，便于排查；正式上线时把 detail 改成 false
    $detail = get_class($e) . ': ' . $e->getMessage() . ' at ' . basename($e->getFile()) . ':' . $e->getLine();
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => '服务器内部错误',
        'detail' => $detail,
    ], JSON_UNESCAPED_UNICODE);
    exit;
} finally {
    // 记录请求日志
    $requestEndTime = microtime(true);
    $duration = round(($requestEndTime - $requestStartTime) * 1000, 2);
    $currentUserId = getCurrentUserIdFromToken();
    Logger::logRequest($method, $path, $currentUserId ?? 'guest', $duration, http_response_code());
}
?>
