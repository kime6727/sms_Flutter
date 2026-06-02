<?php
/**
 * 数据库迁移脚本 - 添加发布功能
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../lib/Database.php';

echo "=== 开始数据库迁移 ===\n\n";

try {
    $db = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT);

    // 1. 添加 is_published 字段到 services 表
    echo "1. 检查 services.is_published 字段...\n";
    $columns = $db->query("SHOW COLUMNS FROM services LIKE 'is_published'")->fetchAll();
    if (empty($columns)) {
        $db->query("ALTER TABLE services ADD COLUMN is_published TINYINT DEFAULT 0 AFTER active");
        echo "   ✓ 已添加 services.is_published 字段\n";
    } else {
        echo "   - services.is_published 已存在\n";
    }

    // 2. 添加 is_published 和 is_auto 字段到 service_countries 表
    echo "2. 检查 service_countries.is_published 字段...\n";
    $columns = $db->query("SHOW COLUMNS FROM service_countries LIKE 'is_published'")->fetchAll();
    if (empty($columns)) {
        $db->query("ALTER TABLE service_countries ADD COLUMN is_published TINYINT DEFAULT 0 AFTER price");
        echo "   ✓ 已添加 service_countries.is_published 字段\n";
    } else {
        echo "   - service_countries.is_published 已存在\n";
    }

    echo "3. 检查 service_countries.is_auto 字段...\n";
    $columns = $db->query("SHOW COLUMNS FROM service_countries LIKE 'is_auto'")->fetchAll();
    if (empty($columns)) {
        $db->query("ALTER TABLE service_countries ADD COLUMN is_auto TINYINT DEFAULT 0 AFTER is_published");
        echo "   ✓ 已添加 service_countries.is_auto 字段\n";
    } else {
        echo "   - service_countries.is_auto 已存在\n";
    }

    // 3. 同步现有数据 - 把所有已active的服务标记为已发布
    echo "4. 同步现有数据...\n";
    $updated = $db->query("UPDATE services SET is_published = 1 WHERE active = 1")->rowCount();
    echo "   ✓ 已将 $updated 个活跃服务标记为已发布\n";

    echo "\n=== 迁移完成 ===\n";
    echo "现在可以访问后台了。\n";

} catch (Exception $e) {
    echo "✗ 迁移失败: " . $e->getMessage() . "\n";
}
