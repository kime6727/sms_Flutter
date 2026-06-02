<?php
/**
 * 执行 banners 表迁移
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lib/Database.php';

$db = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT);

echo "开始执行 banners 表迁移...\n";

try {
    // 读取 SQL 文件
    $sql = file_get_contents(__DIR__ . '/add_banners_table.sql');
    
    // 执行 SQL
    $db->query($sql);
    
    echo "✓ banners 表创建成功\n";
    
    // 插入示例数据（可选）
    $sampleBanners = [
        [
            'name' => '欢迎使用SMS接码平台',
            'image_url' => 'https://via.placeholder.com/750x300/6366f1/ffffff?text=Welcome+Banner',
            'link_url' => 'https://example.com',
            'is_enabled' => 1,
            'sort_order' => 1
        ],
    ];
    
    foreach ($sampleBanners as $banner) {
        $existing = $db->query("SELECT id FROM banners WHERE name = ?", [$banner['name']])->fetch();
        if (!$existing) {
            $db->insert('banners', $banner);
            echo "✓ 示例Banner '{$banner['name']}' 插入成功\n";
        }
    }
    
    echo "\n迁移完成！\n";
    
} catch (Exception $e) {
    echo "✗ 迁移失败: " . $e->getMessage() . "\n";
    exit(1);
}
