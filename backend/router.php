<?php
/**
 * PHP 内置服务器的路由器
 *
 * 用法: php -S 0.0.0.0:PORT -t /app router.php
 *
 * 功能：
 *   1. 真实存在的静态文件（包括 /app 子目录下的图片/css/js）
 *      直接返回文件内容
 *   2. 其他请求全部走 index.php 处理（保持现有 API 路由逻辑）
 */

// 真实存在的文件 / 目录 → 直接返回
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__ . $uri;

if ($uri !== '/' && file_exists($file) && !is_dir($file)) {
    return false; // 让 PHP 内置服务器自己处理静态文件
}

// 其它全部走 index.php（包含所有 API 路由 + /install 安装页）
require_once __DIR__ . '/index.php';
