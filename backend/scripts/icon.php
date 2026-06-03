<?php
/**
 * 图标代理接口
 * 
 * 支持的类型：
 * - ?type=service&icon=tg0.webp    → 服务图标
 * - ?type=country&icon=1.svg       → 国家国旗
 * - ?icon=tg0.webp                 → 兼容旧版（默认服务图标）
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$type = $_GET['type'] ?? 'service';
$icon = $_GET['icon'] ?? '';

if (empty($icon)) {
    http_response_code(400);
    echo json_encode(['error' => 'Icon name is required']);
    exit;
}

// 根据类型确定目录
switch ($type) {
    case 'country':
        $iconFile = __DIR__ . '/pic/country/' . basename($icon);
        break;
    case 'service':
    default:
        // 优先使用 pic/fuwu 目录，如果不存在则使用 assets/icons
        $iconFile = __DIR__ . '/pic/fuwu/' . basename($icon);
        if (!file_exists($iconFile)) {
            $iconFile = __DIR__ . '/assets/icons/' . basename($icon);
        }
        break;
}

if (!file_exists($iconFile)) {
    http_response_code(404);
    echo json_encode(['error' => 'Icon not found', 'type' => $type, 'path' => $iconFile]);
    exit;
}

$ext = pathinfo($iconFile, PATHINFO_EXTENSION);
$mimeTypes = [
    'webp' => 'image/webp',
    'png' => 'image/png',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'gif' => 'image/gif',
    'svg' => 'image/svg+xml'
];

$mime = $mimeTypes[$ext] ?? 'application/octet-stream';

header('Content-Type: ' . $mime);
header('Cache-Control: public, max-age=86400');
readfile($iconFile);
