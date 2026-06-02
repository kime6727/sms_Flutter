<?php
/**
 * 修复 payment_configs 表缺少 credits 字段
 * 执行: php backend/migrations/fix_payment_configs.php
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lib/Database.php';

$db = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT);

echo "开始修复 payment_configs 表...\n";

// 1. 检查 credits 字段是否存在
$columns = $db->query("SHOW COLUMNS FROM payment_configs LIKE 'credits'")->fetchAll();
if (!empty($columns)) {
    echo "✅ credits 字段已存在\n";
} else {
    // 2. 添加 credits 字段
    echo "➕ 添加 credits 字段...\n";
    $db->query("ALTER TABLE payment_configs ADD COLUMN credits INT NOT NULL DEFAULT 100 AFTER product_id");
    echo "✅ credits 字段添加成功\n";
}

// 3. 更新现有记录的 credits 值
echo "📝 更新现有配置的积分数量...\n";

$configs = $db->query("SELECT id, product_id, config_name FROM payment_configs")->fetchAll();

$updates = [
    'com.smsreceiver.points.100' => 100,
    'com.smsreceiver.points.500' => 500,
    'com.smsreceiver.points.1000' => 1050,
    'com.smsreceiver.points.2000' => 2150,
    'com.smsreceiver.points.5000' => 5500,
    'smsreceiver_1' => 99,
    'smsreceiver_3' => 999,
];

foreach ($configs as $config) {
    if (isset($updates[$config['product_id']])) {
        $credits = $updates[$config['product_id']];
        $db->query("UPDATE payment_configs SET credits = ? WHERE id = ?", [$credits, $config['id']]);
        echo "   ✅ {$config['config_name']} -> {$credits} 积分\n";
    }
}

// 4. 确保至少有一个默认配置
$defaultExists = $db->query("SELECT COUNT(*) FROM payment_configs WHERE product_id = 'com.smsreceiver.points.100'")->fetchColumn();
if (!$defaultExists) {
    echo "➕ 创建默认配置...\n";
    $db->query("INSERT INTO payment_configs (product_id, credits, display_price, config_name, description, active, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)", [
        'com.smsreceiver.points.100',
        100,
        0.99,
        '100积分',
        '基础充值套餐',
        1,
        date('Y-m-d H:i:s')
    ]);
    echo "✅ 默认配置创建成功\n";
}

echo "\n🎉 payment_configs 表修复完成！\n";

// 验证
echo "\n📋 当前配置:\n";
$allConfigs = $db->query("SELECT id, product_id, credits, config_name FROM payment_configs ORDER BY credits ASC")->fetchAll();
foreach ($allConfigs as $c) {
    echo "   [{$c['id']}] {$c['product_id']} -> {$c['credits']} 积分 ({$c['config_name']})\n";
}
?>