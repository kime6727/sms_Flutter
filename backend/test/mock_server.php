<?php
/**
 * Local Mock Backend for Testing
 *
 * 启动一个本地的 PHP 开发服务器，模拟生产后端来验证修复后的代码。
 * 使用方式:  php -S localhost:9090 test/mock_server.php
 *
 * 直接复用生产数据库（从 .env 读），只是入口是 mock_server.php
 * 而非 index.php，避免正常流程的认证/路径解析。
 */

// 加载配置
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/Auth.php';

// === 本地测试覆盖：env 中显式指定 TEST_* 变量时，强制覆盖 .env 读到的值 ===
// 用例：DB_HOST=127.0.0.1 DB_PORT=3399 时，连接到本机临时 MySQL（.env 写的是 localhost）
foreach (['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASS'] as $k) {
    $v = getenv('TEST_' . $k);
    if ($v !== false) {  // 包括空字符串，认为"显式指定了"，要覆盖
        $GLOBALS['__TEST_OVERRIDE_' . $k] = $v;
    }
}

// 从 .env 读取 APP_URL
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $env = parse_ini_file($envFile);
    if (!defined('APP_URL')) define('APP_URL', $env['APP_URL'] ?? 'http://localhost:9090');
}

// 解析请求
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($requestUri, PHP_URL_PATH) ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// === 静态文件 / 测试页面优先：非 /api 路径直接返回文件系统中的文件 ===
if (strpos($path, '/api') !== 0 && $path !== '/api') {
    $filePath = __DIR__ . '/..' . $path;
    // 安全：不允许跳出 backend 目录
    $real = realpath($filePath);
    $rootReal = realpath(__DIR__ . '/..');
    if ($real && $rootReal && strpos($real, $rootReal) === 0 && is_file($real)) {
        $ext = pathinfo($real, PATHINFO_EXTENSION);
        $mimeMap = [
            'php' => 'text/html; charset=utf-8',
            'html' => 'text/html; charset=utf-8',
            'json' => 'application/json; charset=utf-8',
            'css' => 'text/css; charset=utf-8',
            'js' => 'application/javascript; charset=utf-8',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'svg' => 'image/svg+xml',
        ];
        header('Content-Type: ' . ($mimeMap[$ext] ?? 'application/octet-stream'));
        // 如果是 PHP 文件，让 PHP 内置服务器处理（return false）
        if ($ext === 'php') {
            return false;
        }
        readfile($real);
        exit;
    }
    // 找不到资源
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    echo "<h1>404 Not Found</h1><p>$path</p>";
    exit;
}

// 去掉前缀
if (strpos($path, '/api') === 0) {
    $path = substr($path, 4);
}
if (strpos($path, '/') !== 0) {
    $path = '/' . $path;
}

// 解析查询参数
parse_str(parse_url($requestUri, PHP_URL_QUERY) ?? '', $queryParams);
$_GET = array_merge($_GET, $queryParams);

// 初始化 DB
try {
    $testHost = $GLOBALS['__TEST_OVERRIDE_DB_HOST'] ?? DB_HOST;
    $testPort = (int)($GLOBALS['__TEST_OVERRIDE_DB_PORT'] ?? DB_PORT);
    $testName = $GLOBALS['__TEST_OVERRIDE_DB_NAME'] ?? DB_NAME;
    $testUser = $GLOBALS['__TEST_OVERRIDE_DB_USER'] ?? DB_USER;
    // DB_PASS 可能为空（无密码），所以用 array_key_exists 区分"未设置"和"设置为空"
    $testPass = array_key_exists('__TEST_OVERRIDE_DB_PASS', $GLOBALS) ? $GLOBALS['__TEST_OVERRIDE_DB_PASS'] : DB_PASS;
    $db = new Database($testHost, $testName, $testUser, $testPass, $testPort);
} catch (Exception $e) {
    header('Content-Type: application/json');
    http_response_code(503);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}

// CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Device-Id, X-API-Key');
header('Content-Type: application/json; charset=utf-8');

if ($method === 'OPTIONS') { http_response_code(204); exit; }

// 公共错误响应函数
function apiError($error, $httpCode = 400, $code = null, $extraData = null) {
    http_response_code($httpCode);
    $response = ['success' => false, 'error' => $error];
    if ($code) $response['code'] = $code;
    if ($extraData) $response = array_merge($response, $extraData);
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}
function apiBadRequest($error) { apiError($error, 400); }
function apiUnauthorized($error = '未授权') { apiError($error, 401, 'UNAUTHORIZED'); }
function apiForbidden($error = '禁止访问') { apiError($error, 403, 'FORBIDDEN'); }
function apiNotFound($error = '资源不存在') { apiError($error, 404); }
function apiServerError($error = '服务器内部错误') { apiError($error, 500, 'INTERNAL_ERROR'); }

// 加载辅助函数
require_once __DIR__ . '/../helpers/functions.php';

// 加载所有路由
require_once __DIR__ . '/../routes/auth.php';
require_once __DIR__ . '/../routes/user.php';
require_once __DIR__ . '/../routes/orders.php';
require_once __DIR__ . '/../routes/services.php';
require_once __DIR__ . '/../routes/topup.php';
require_once __DIR__ . '/../routes/notifications.php';
require_once __DIR__ . '/../routes/payment.php';
require_once __DIR__ . '/../routes/system.php';

// 404
apiNotFound('接口不存在: ' . $path);
