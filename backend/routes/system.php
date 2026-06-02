<?php
/**
 * 系统设置路由
 */

// 获取系统设置
if ($path === '/system/settings' && $method === 'GET') {
    $settings = $db->query("SELECT * FROM system_settings")->fetchAll();
    
    $result = [];
    foreach ($settings as $setting) {
        $result[$setting['key']] = $setting['value'];
    }
    
    echo json_encode(['success' => true, 'data' => $result]);
    exit;
}

// 健康检查
if ($path === '/health' && $method === 'GET') {
    echo json_encode([
        'success' => true,
        'status' => 'ok',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}
