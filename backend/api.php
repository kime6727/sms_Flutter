<?php
/**
 * API 路由入口文件
 * 支持两种调用方式:
 * 1. http://localhost/api.php?path=/health (参数格式)
 * 2. http://localhost/api/services (路径格式，通过router.php转发)
 */

// 支持两种路由格式
if (isset($_GET['path'])) {
    $path = $_GET['path'];
    // 如果路径已经包含 /api 前缀，去掉它
    if (strpos($path, '/api/') === 0) {
        $path = substr($path, 4);
    } elseif ($path === '/api') {
        $path = '/';
    }
    $_SERVER['REQUEST_URI'] = '/api' . $path;
} else {
    // 格式2: api.php/services (路径信息)
    $uri = $_SERVER['REQUEST_URI'] ?? $_SERVER['PATH_INFO'] ?? '';
    // 移除 api.php 部分，保留后面的路径
    if (strpos($uri, '/api.php') !== false) {
        $path = substr($uri, strpos($uri, '/api.php') + 8);
        if (empty($path) || $path === '/') {
            $path = '/health';
        }
        // 确保路径以 / 开头
        if (strpos($path, '/') !== 0) {
            $path = '/' . $path;
        }
        $_SERVER['REQUEST_URI'] = '/api' . $path;
    } else {
        $_SERVER['REQUEST_URI'] = '/api/health';
    }
}

// 转发到 index.php
require __DIR__ . '/index.php';
