<?php
/**
 * PHP内置服务器路由脚本
 * 将所有 /api/* 请求转发到 api.php
 */

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// 如果请求的是/api/开头的路径，转发到api.php
if (strpos($uri, '/api/') === 0 || $uri === '/api') {
    // 将路径信息传递给api.php
    $_SERVER['PATH_INFO'] = $uri;
    $_GET['path'] = $uri;
    require __DIR__ . '/api.php';
    return true;
}

// 如果请求的是/icon路径，转发到icon.php
if (strpos($uri, '/icon') === 0) {
    $_GET['icon'] = ltrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY) ?? '', 'icon=');
    require __DIR__ . '/icon.php';
    return true;
}

// 如果请求的是/webhook路径，转发到webhook.php
if (strpos($uri, '/webhook') === 0) {
    require __DIR__ . '/webhook.php';
    return true;
}

// 其他请求返回false，让PHP服务器处理静态文件
return false;