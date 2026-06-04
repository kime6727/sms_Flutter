<?php
/**
 * Session 调试页面
 * 访问: https://sms.niceapp.eu.cc/admin/debug.php
 */

// 配置 session cookie 参数以支持 HTTPS
if (!session_id()) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on');
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>Session 调试</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #f5f5f5; }
        .section { background: white; padding: 20px; margin-bottom: 20px; border-radius: 8px; }
        h2 { margin-top: 0; }
        pre { background: #f0f0f0; padding: 10px; border-radius: 4px; overflow-x: auto; }
        .ok { color: green; }
        .error { color: red; }
        .warning { color: orange; }
    </style>
</head>
<body>
    <div class="section">
        <h2>Session 状态</h2>
        <p>Session ID: <?= session_id() ?: '<span class="error">未启动</span>' ?></p>
        <p>Session 状态: <?= session_status() === PHP_SESSION_ACTIVE ? '<span class="ok">活跃</span>' : '<span class="error">未活跃</span>' ?></p>
        <h3>Session 数据:</h3>
        <pre><?php print_r($_SESSION); ?></pre>
    </div>

    <div class="section">
        <h2>Cookie 信息</h2>
        <pre><?php print_r($_COOKIE); ?></pre>
    </div>

    <div class="section">
        <h2>服务器信息</h2>
        <p>HTTPS: <?= isset($_SERVER['HTTPS']) ? $_SERVER['HTTPS'] : '未设置' ?></p>
        <p>HTTP_HOST: <?= $_SERVER['HTTP_HOST'] ?? '未设置' ?></p>
        <p>REQUEST_URI: <?= $_SERVER['REQUEST_URI'] ?? '未设置' ?></p>
        <p>REMOTE_ADDR: <?= $_SERVER['REMOTE_ADDR'] ?? '未设置' ?></p>
        <p>HTTP_X_FORWARDED_PROTO: <?= $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '未设置' ?></p>
        <p>HTTP_X_FORWARDED_FOR: <?= $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '未设置' ?></p>
    </div>

    <div class="section">
        <h2>PHP Session 配置</h2>
        <p>session.save_path: <?= session_save_path() ?></p>
        <p>session.cookie_path: <?= ini_get('session.cookie_path') ?></p>
        <p>session.cookie_domain: <?= ini_get('session.cookie_domain') ?: '未设置' ?></p>
        <p>session.cookie_secure: <?= ini_get('session.cookie_secure') ?></p>
        <p>session.cookie_httponly: <?= ini_get('session.cookie_httponly') ?></p>
        <p>session.cookie_samesite: <?= ini_get('session.cookie_samesite') ?></p>
        <p>session.use_cookies: <?= ini_get('session.use_cookies') ?></p>
        <p>session.use_only_cookies: <?= ini_get('session.use_only_cookies') ?></p>
    </div>

    <div class="section">
        <h2>Session 目录权限</h2>
        <?php
        $sessionPath = session_save_path();
        if (empty($sessionPath)) {
            $sessionPath = sys_get_temp_dir();
        }
        echo "<p>路径: $sessionPath</p>";
        if (is_dir($sessionPath)) {
            echo "<p class=\"ok\">目录存在</p>";
            if (is_writable($sessionPath)) {
                echo "<p class=\"ok\">目录可写</p>";
            } else {
                echo "<p class=\"error\">目录不可写！</p>";
            }
            $files = glob($sessionPath . '/sess_*');
            echo "<p>Session 文件数量: " . count($files) . "</p>";
        } else {
            echo "<p class=\"error\">目录不存在！</p>";
        }
        ?>
    </div>

    <div class="section">
        <h2>测试操作</h2>
        <p><a href="?action=set">设置测试 Session 数据</a></p>
        <p><a href="?action=clear">清除 Session</a></p>
        <p><a href="debug.php">刷新页面</a></p>
        <?php
        if (isset($_GET['action'])) {
            if ($_GET['action'] === 'set') {
                $_SESSION['test_time'] = date('Y-m-d H:i:s');
                $_SESSION['test_data'] = 'Hello World';
                echo "<p class=\"ok\">Session 数据已设置！</p>";
            } elseif ($_GET['action'] === 'clear') {
                session_destroy();
                echo "<p class=\"warning\">Session 已清除！</p>";
            }
        }
        ?>
    </div>
</body>
</html>
