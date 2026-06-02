<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lib/Database.php';

$db = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT);

echo "开始修复 payment_configs 表...\n";

// 检查 is_recommended 字段是否存在
$columns = $db->query("SHOW COLUMNS FROM payment_configs LIKE 'is_recommended'")->fetchAll();
if (!empty($columns)) {
    echo "✅ is_recommended 字段已存在\n";
} else {
    echo "➕ 添加 is_recommended 字段...\n";
    $db->query("ALTER TABLE payment_configs ADD COLUMN is_recommended TINYINT(1) DEFAULT 0 AFTER description");
    echo "✅ is_recommended 字段添加成功\n";
}

// 确保 credits 字段存在
$columns = $db->query("SHOW COLUMNS FROM payment_configs LIKE 'credits'")->fetchAll();
if (!empty($columns)) {
    echo "✅ credits 字段已存在\n";
} else {
    echo "➕ 添加 credits 字段...\n";
    $db->query("ALTER TABLE payment_configs ADD COLUMN credits INT NOT NULL DEFAULT 100 AFTER product_id");
    echo "✅ credits 字段添加成功\n";
}

echo "\n🎉 payment_configs 表修复完成！\n";

// 验证
echo "\n📋 当前表结构:\n";
$allColumns = $db->query("SHOW COLUMNS FROM payment_configs")->fetchAll();
foreach ($allColumns as $c) {
    echo "   {$c['Field']} - {$c['Type']}\n";
}

echo "\n📋 当前配置:\n";
$allConfigs = $db->query("SELECT id, product_id, config_name, credits, is_recommended FROM payment_configs ORDER BY credits ASC")->fetchAll();
foreach ($allConfigs as $c) {
    echo "   [{$c['id']}] {$c['product_id']} -> {$c['credits']} 积分 ({$c['config_name']}) 推荐:" . ($c['is_recommended'] ? '是' : '否') . "\n";
}
?>
