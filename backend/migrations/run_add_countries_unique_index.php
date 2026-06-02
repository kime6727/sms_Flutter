<?php
/**
 * 执行数据库迁移：添加countries表唯一索引
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lib/Database.php';

$db = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT);

echo "开始执行迁移...\n";

try {
    // 检查索引是否已存在
    $result = $db->query("SHOW INDEX FROM countries WHERE Key_name = 'uk_hero_country_id'")->fetchAll();
    
    if (empty($result)) {
        echo "添加 countries.hero_country_id 唯一索引...\n";
        $db->query("ALTER TABLE `countries` ADD UNIQUE KEY `uk_hero_country_id` (`hero_country_id`)");
        echo "✓ 成功添加 countries.hero_country_id 唯一索引\n";
    } else {
        echo "✓ countries.hero_country_id 唯一索引已存在\n";
    }
    
    // 检查service_countries联合唯一索引
    $result = $db->query("SHOW INDEX FROM service_countries WHERE Key_name = 'uk_service_country'")->fetchAll();
    
    if (empty($result)) {
        echo "添加 service_countries 联合唯一索引...\n";
        $db->query("ALTER TABLE `service_countries` ADD UNIQUE KEY `uk_service_country` (`service_id`, `country_id`)");
        echo "✓ 成功添加 service_countries 联合唯一索引\n";
    } else {
        echo "✓ service_countries 联合唯一索引已存在\n";
    }
    
    echo "\n迁移完成！\n";
    
} catch (Exception $e) {
    echo "✗ 迁移失败: " . $e->getMessage() . "\n";
    exit(1);
}
