<?php
/**
 * Session 统一配置
 * 所有需要 session 的文件都应该包含此文件
 */

// 检测是否 HTTPS（包括反向代理情况）
$isHttps = false;
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    $isHttps = true;
} elseif (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
    $isHttps = true;
} elseif (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') {
    $isHttps = true;
}

// 配置 session cookie 参数
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', $isHttps);
ini_set('session.cookie_samesite', 'Lax');

// 启动 session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
