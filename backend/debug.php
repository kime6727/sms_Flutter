<?php
/**
 * 调试端点 - 用于诊断部署问题
 *
 * 访问: /debug.php
 *
 * 显示:
 *   - PHP 版本 / 模块 / 内存 / 时区
 *   - 当前请求信息
 *   - 文件系统（/app 目录结构）
 *   - 环境变量（DB_* 相关）
 *   - 数据库连接测试
 *   - 数据库初始化状态
 */

header('Content-Type: text/plain; charset=utf-8');

echo "=== PHP Info ===\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "SAPI: " . PHP_SAPI . "\n";
echo "Memory Limit: " . ini_get('memory_limit') . "\n";
echo "Timezone: " . date_default_timezone_get() . "\n";
echo "Loaded php.ini: " . php_ini_loaded_file() . "\n";

echo "\n=== PHP Modules (filtered) ===\n";
$wanted = ['pdo', 'pdo_mysql', 'mysqli', 'bcmath', 'curl', 'mbstring', 'openssl', 'xml', 'zip', 'tokenizer', 'fileinfo', 'ctype', 'iconv', 'json', 'openssl', 'session'];
$loaded = get_loaded_extensions();
foreach ($wanted as $ext) {
    $mark = in_array($ext, $loaded) ? '[OK]' : '[!!]';
    echo "  $mark $ext\n";
}

echo "\n=== Request Info ===\n";
echo "REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'N/A') . "\n";
echo "REQUEST_METHOD: " . ($_SERVER['REQUEST_METHOD'] ?? 'N/A') . "\n";
echo "SERVER_NAME: " . ($_SERVER['SERVER_NAME'] ?? 'N/A') . "\n";
echo "SERVER_PORT: " . ($_SERVER['SERVER_PORT'] ?? 'N/A') . "\n";
echo "REMOTE_ADDR: " . ($_SERVER['REMOTE_ADDR'] ?? 'N/A') . "\n";

echo "\n=== Filesystem ===\n";
$appDir = '/app';
echo "Document root: $appDir\n";
echo "Exists: " . (is_dir($appDir) ? 'YES' : 'NO') . "\n";
if (is_dir($appDir)) {
    $items = scandir($appDir);
    $items = array_diff($items, ['.', '..']);
    $items = array_slice($items, 0, 30);
    foreach ($items as $item) {
        $full = $appDir . '/' . $item;
        $type = is_dir($full) ? 'D' : 'F';
        $size = is_file($full) ? filesize($full) : '-';
        echo "  [$type] $item ($size)\n";
    }
}

echo "\n=== Critical Files ===\n";
$critical = [
    '/app/index.php',
    '/app/router.php',
    '/app/database.sql',
    '/app/lib/Database.php',
    '/app/lib/Installer.php',
    '/app/routes/install.php',
    '/app/config/database.php',
];
foreach ($critical as $f) {
    echo "  " . (file_exists($f) ? '[OK]' : '[!!]') . " $f\n";
}

echo "\n=== Env Vars (DB-related) ===\n";
foreach ($_ENV as $k => $v) {
    if (preg_match('/^(DB_|HEROSMS_|APP_|API_|AUTH_)/i', $k)) {
        $display = $v;
        if (strlen($v) > 50) $display = substr($v, 0, 47) . '...';
        // 隐藏敏感值
        if (preg_match('/(KEY|SECRET|PASS|TOKEN)/i', $k)) {
            $display = '***' . strlen($v) . 'chars***';
        }
        echo "  $k = $display\n";
    }
}
// 也从 getenv 取
foreach (['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASS', 'APP_URL', 'HEROSMS_API_KEY', 'PORT'] as $k) {
    $v = getenv($k);
    if ($v !== false && $v !== '') {
        $display = $v;
        if (preg_match('/(KEY|SECRET|PASS|TOKEN)/i', $k)) {
            $display = '***' . strlen($v) . 'chars***';
        }
        echo "  (getenv) $k = $display\n";
    }
}

echo "\n=== Database Connection Test ===\n";
try {
    if (file_exists('/app/config/database.php')) {
        require_once '/app/config/database.php';
        echo "  DB_HOST: " . (defined('DB_HOST') ? DB_HOST : 'NOT DEFINED') . "\n";
        echo "  DB_PORT: " . (defined('DB_PORT') ? DB_PORT : 'NOT DEFINED') . "\n";
        echo "  DB_NAME: " . (defined('DB_NAME') ? DB_NAME : 'NOT DEFINED') . "\n";
        echo "  DB_USER: " . (defined('DB_USER') ? DB_USER : 'NOT DEFINED') . "\n";
        echo "  DB_PASS: " . (defined('DB_PASS') ? (strlen(DB_PASS) . ' chars') : 'NOT DEFINED') . "\n";

        if (file_exists('/app/lib/Database.php')) {
            require_once '/app/lib/Database.php';
            $db = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT);
            echo "  Connection: OK\n";
            $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            echo "  Tables: " . count($tables) . "\n";
            if (count($tables) > 0) {
                echo "  First 10: " . implode(', ', array_slice($tables, 0, 10)) . "\n";
            }
        } else {
            echo "  [!!] /app/lib/Database.php not found\n";
        }
    } else {
        echo "  [!!] /app/config/database.php not found\n";
    }
} catch (Exception $e) {
    echo "  [FAIL] " . $e->getMessage() . "\n";
}

echo "\n=== Done ===\n";
