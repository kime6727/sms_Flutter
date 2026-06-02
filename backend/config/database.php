<?php
/**
 * 数据库配置文件
 *
 * 安全说明：
 * 1. 所有敏感配置优先从 .env 文件读取
 * 2. .env 文件不应提交到版本控制
 * 3. 生产环境必须设置 APP_ENV=production
 * 4. 生产环境必须配置正确的 APP_URL 和 CORS_ALLOWED_ORIGINS
 */

$env_file = __DIR__ . '/../.env';
$env = [];
if (file_exists($env_file)) {
    $fp = fopen($env_file, 'r');
    if ($fp) {
        if (flock($fp, LOCK_SH)) {
            $env = parse_ini_file($env_file, false, INI_SCANNER_RAW);
            flock($fp, LOCK_UN);
        }
        fclose($fp);
    }
}

// 数据库配置
$dbHost = $env['DB_HOST'] ?? 'localhost';
$dbPort = $env['DB_PORT'] ?? 3306;
$dbName = $env['DB_NAME'] ?? 'sms_receiver';
$dbUser = $env['DB_USER'] ?? 'root';
$dbPass = $env['DB_PASS'] ?? '';

// 如果数据库密码为空，尝试从环境变量读取（生产环境推荐方式）
if (empty($dbPass)) {
    $dbPass = getenv('DB_PASS') ?: '';
}

define('DB_HOST', $dbHost);
define('DB_PORT', $dbPort);
define('DB_NAME', $dbName);
define('DB_USER', $dbUser);
define('DB_PASS', $dbPass);

// API 配置 - 生产环境必须从 .env 读取，不允许使用默认值
$apiKey = $env['API_KEY'] ?? '';

// 如果 .env 中没有设置，尝试从环境变量读取
if (empty($apiKey)) {
    $apiKey = getenv('API_KEY') ?: '';
}

// 生产环境下 API Key 不能为空
$appEnv = $env['APP_ENV'] ?? 'development';
if ($appEnv === 'production') {
    if (empty($apiKey)) {
        error_log('SECURITY ERROR: API_KEY is not set in production environment');
        http_response_code(500);
        die(json_encode(['error' => 'Server configuration error']));
    }
}

define('API_KEY', $apiKey);

// hero-sms API 密钥 - 必须从数据库 system_settings 表读取
// 禁止在 .env 或代码中硬编码
define('HEROSMS_BASE_URL', $env['HEROSMS_BASE_URL'] ?? 'https://hero-sms.com/stubs/handler_api.php');
define('APPLE_SHARED_SECRET', $env['APPLE_SHARED_SECRET'] ?? '');

// CORS 配置 - 生产环境必须限制来源
$corsOrigins = $env['CORS_ALLOWED_ORIGINS'] ?? '*';
if ($appEnv === 'production' && $corsOrigins === '*') {
    // 生产环境如果没有配置 CORS，使用 APP_URL 作为默认值
    $appUrl = $env['APP_URL'] ?? '';
    if (!empty($appUrl)) {
        $corsOrigins = $appUrl;
    }
}
define('CORS_ALLOWED_ORIGINS', $corsOrigins);

// 应用配置
define('APP_NAME', $env['APP_NAME'] ?? 'SMS 接码平台');
define('APP_URL', $env['APP_URL'] ?? 'https://newsms.weburl.cloudns.be');
define('APP_ENV', $appEnv);

// 时区
date_default_timezone_set('Asia/Shanghai');

// 错误处理
if (APP_ENV === 'production') {
    ini_set('display_errors', 0);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
}
