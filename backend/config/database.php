<?php
/**
 * 数据库配置文件
 *
 * 配置读取顺序（后者覆盖前者）:
 *   1. .env 文件（backend/.env）— 本地开发 / 自托管
 *   2. 容器进程环境变量（getenv）— dokploy / k8s / docker run --env-file
 *
 * 安全说明：
 *   1. 敏感配置禁止硬编码，必须从 .env 或环境变量读取
 *   2. .env 文件不应提交到版本控制
 *   3. 生产环境必须设置 APP_ENV=production
 *   4. 生产环境必须配置正确的 APP_URL 和 CORS_ALLOWED_ORIGINS
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

// 统一的回退读取：.env 中没有时，从容器进程环境变量读
// 适配 dokploy / k8s ConfigMap / docker run --env-file 等部署方式
function dbEnv(string $key, $env, $default = '') {
    $val = $env[$key] ?? null;
    if ($val === null || $val === '') {
        $val = getenv($key);
        if ($val === false || $val === '') {
            $val = $default;
        }
    }
    return $val;
}

// 数据库配置（.env 优先，缺失则回退到 getenv，再缺失才用默认值）
$dbHost = dbEnv('DB_HOST', $env, 'localhost');
$dbPort = (int)dbEnv('DB_PORT', $env, 3306);
$dbName = dbEnv('DB_NAME', $env, 'sms_receiver');
$dbUser = dbEnv('DB_USER', $env, 'root');
$dbPass = dbEnv('DB_PASS', $env, '');

// 用 defined() 守卫，避免 webhook 等独立 require 时重复定义触发 fatal
if (!defined('DB_HOST')) define('DB_HOST', $dbHost);
if (!defined('DB_PORT')) define('DB_PORT', $dbPort);
if (!defined('DB_NAME')) define('DB_NAME', $dbName);
if (!defined('DB_USER')) define('DB_USER', $dbUser);
if (!defined('DB_PASS')) define('DB_PASS', $dbPass);

// SSL 配置（云数据库如 TiDB Cloud / 阿里云 RDS / AWS RDS 需要）
// 优先从 .env 读，缺失则从系统环境变量读
$dbSslCa = dbEnv('DB_SSL_CA', $env, '');
$dbSslCaContent = dbEnv('DB_SSL_CA_CONTENT', $env, '');
$dbSslVerify = strtolower((string)dbEnv('DB_SSL_VERIFY', $env, 'true'));
$dbSslVerify = !in_array($dbSslVerify, ['0', 'false', 'no', 'off'], true);
$dbSslEnabled = !empty($dbSslCa) || !empty($dbSslCaContent) || getenv('DB_SSL_ENABLED') === '1';

define('DB_SSL_ENABLED', $dbSslEnabled);
define('DB_SSL_CA', $dbSslCa);
define('DB_SSL_CA_CONTENT', $dbSslCaContent);
define('DB_SSL_VERIFY', $dbSslVerify);

// API 配置 - 生产环境必须从 .env 或环境变量读取，不允许使用默认值
$apiKey = dbEnv('API_KEY', $env, '');

// 生产环境下 API Key 不能为空
$appEnv = dbEnv('APP_ENV', $env, 'development');
if ($appEnv === 'production' && empty($apiKey)) {
    error_log('SECURITY ERROR: API_KEY is not set in production environment');
    http_response_code(500);
    die(json_encode(['error' => 'Server configuration error']));
}

define('API_KEY', $apiKey);

// hero-sms API 密钥 - 必须从数据库 system_settings 表读取
// 禁止在 .env 或代码中硬编码
define('HEROSMS_BASE_URL', dbEnv('HEROSMS_BASE_URL', $env, 'https://hero-sms.com/stubs/handler_api.php'));
define('APPLE_SHARED_SECRET', dbEnv('APPLE_SHARED_SECRET', $env, ''));

// CORS 配置 - 生产环境必须限制来源
$corsOrigins = dbEnv('CORS_ALLOWED_ORIGINS', $env, '*');
if ($appEnv === 'production' && $corsOrigins === '*') {
    // 生产环境如果没有配置 CORS，使用 APP_URL 作为默认值
    $appUrl = dbEnv('APP_URL', $env, '');
    if (!empty($appUrl)) {
        $corsOrigins = $appUrl;
    }
}
define('CORS_ALLOWED_ORIGINS', $corsOrigins);

// 应用配置
define('APP_NAME', dbEnv('APP_NAME', $env, 'SMS 接码平台'));
define('APP_URL', dbEnv('APP_URL', $env, 'https://newsms.weburl.cloudns.be'));
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
