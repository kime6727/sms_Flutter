<?php
/**
 * 服务管理相关字段一键补齐脚本
 * 解决 services 页面打不开（缺 is_pinned / tag 字段）的问题
 *
 * 用法：浏览器访问 /admin/migrate_services_schema.php
 *  或命令行：php admin/migrate_services_schema.php
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lib/Database.php';

$db = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT);

$isCli = (php_sapi_name() === 'cli');
if (!$isCli) {
    header('Content-Type: text/html; charset=utf-8');
    echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>服务字段迁移</title><style>body{font-family:monospace;padding:20px;background:#0f172a;color:#e2e8f0;}h1{color:#10b981;}.ok{color:#10b981;}.skip{color:#94a3b8;}.fail{color:#ef4444;}.box{background:#1e293b;padding:16px;border-radius:8px;margin:12px 0;}</style></head><body>";
    echo "<h1>🔧 服务管理字段迁移</h1>";
}

function logMsg($msg, $isCli, $type = 'ok') {
    $cls = ['ok' => 'ok', 'skip' => 'skip', 'fail' => 'fail'][$type] ?? 'ok';
    $icon = ['ok' => '✓', 'skip' => '·', 'fail' => '✗'][$type] ?? '·';
    if ($isCli) {
        $color = ['ok' => "\033[32m", 'skip' => "\033[90m", 'fail' => "\033[31m"];
        $reset = "\033[0m";
        echo "{$color[$type]}{$icon}{$reset} {$msg}\n";
    } else {
        echo "<div class='{$cls}'>{$icon} {$msg}</div>";
    }
}

function runMigration($db, $colName, $table, $alterSql, $isCli) {
    try {
        $cols = array_column($db->query("SHOW COLUMNS FROM {$table}")->fetchAll(), 'Field');
        if (in_array($colName, $cols)) {
            logMsg("[{$table}] 字段 {$colName} 已存在，跳过", $isCli, 'skip');
            return true;
        }
        $db->query($alterSql);
        logMsg("[{$table}] 已添加字段 {$colName}", $isCli, 'ok');
        return true;
    } catch (Exception $e) {
        logMsg("[{$table}] 添加字段 {$colName} 失败: " . $e->getMessage(), $isCli, 'fail');
        return false;
    }
}

function runIndexMigration($db, $indexName, $table, $alterSql, $isCli) {
    try {
        $indexes = array_column($db->query("SHOW INDEX FROM {$table}")->fetchAll(), 'Key_name');
        if (in_array($indexName, $indexes)) {
            logMsg("[{$table}] 索引 {$indexName} 已存在，跳过", $isCli, 'skip');
            return true;
        }
        $db->query($alterSql);
        logMsg("[{$table}] 已添加索引 {$indexName}", $isCli, 'ok');
        return true;
    } catch (Exception $e) {
        logMsg("[{$table}] 添加索引 {$indexName} 失败: " . $e->getMessage(), $isCli, 'fail');
        return false;
    }
}

if ($isCli) echo "🔧 服务管理字段迁移\n\n";
else echo "<div class='box'>开始执行迁移...</div>";

// ============== services 表 ==============
logMsg("—— services 表 ——", $isCli, 'skip');

runMigration($db, 'is_pinned', 'services',
    "ALTER TABLE services ADD COLUMN `is_pinned` tinyint DEFAULT '0' COMMENT '是否置顶显示' AFTER `sort_order`",
    $isCli);
runMigration($db, 'tag', 'services',
    "ALTER TABLE services ADD COLUMN `tag` tinyint DEFAULT '0' COMMENT '标签: 0=无 1=热门 2=推荐' AFTER `is_pinned`",
    $isCli);
runMigration($db, 'name_en', 'services',
    "ALTER TABLE services ADD COLUMN `name_en` varchar(255) DEFAULT NULL AFTER `name`",
    $isCli);
runMigration($db, 'name_cn', 'services',
    "ALTER TABLE services ADD COLUMN `name_cn` varchar(255) DEFAULT NULL AFTER `name_en`",
    $isCli);
runIndexMigration($db, 'idx_pinned', 'services',
    "ALTER TABLE services ADD KEY `idx_pinned` (`is_pinned`)",
    $isCli);
runIndexMigration($db, 'uk_hero_service_id', 'services',
    "ALTER TABLE services ADD UNIQUE KEY `uk_hero_service_id` (`hero_service_id`)",
    $isCli);

// ============== service_countries 表 ==============
logMsg("", $isCli);
logMsg("—— service_countries 表 ——", $isCli, 'skip');

runMigration($db, 'custom_price', 'service_countries',
    "ALTER TABLE service_countries ADD COLUMN `custom_price` decimal(10,4) DEFAULT NULL COMMENT '自定义价格(覆盖 HeroSMS 返回的成本价)' AFTER `price`",
    $isCli);
runMigration($db, 'coefficient', 'service_countries',
    "ALTER TABLE service_countries ADD COLUMN `coefficient` decimal(5,2) NOT NULL DEFAULT '1.00' COMMENT '价格倍数' AFTER `custom_price`",
    $isCli);
runMigration($db, 'stock', 'service_countries',
    "ALTER TABLE service_countries ADD COLUMN `stock` int NOT NULL DEFAULT '0' COMMENT '库存数量' AFTER `price`",
    $isCli);

// ============== 验证结果 ==============
if ($isCli) echo "\n🔍 验证结果：\n";
else echo "<div class='box'><b>🔍 验证结果：</b><br>";

$checks = [
    'services.is_pinned' => "SHOW COLUMNS FROM services LIKE 'is_pinned'",
    'services.tag' => "SHOW COLUMNS FROM services LIKE 'tag'",
    'services.name_en' => "SHOW COLUMNS FROM services LIKE 'name_en'",
    'services.name_cn' => "SHOW COLUMNS FROM services LIKE 'name_cn'",
    'service_countries.custom_price' => "SHOW COLUMNS FROM service_countries LIKE 'custom_price'",
    'service_countries.coefficient' => "SHOW COLUMNS FROM service_countries LIKE 'coefficient'",
    'service_countries.stock' => "SHOW COLUMNS FROM service_countries LIKE 'stock'",
];
$allOk = true;
foreach ($checks as $name => $sql) {
    $row = $db->query($sql)->fetch();
    if ($row) {
        logMsg("{$name} ✓", $isCli, 'ok');
    } else {
        logMsg("{$name} ✗ 缺失", $isCli, 'fail');
        $allOk = false;
    }
}

if ($isCli) {
    if ($allOk) {
        echo "\n\033[32m✓ 全部完成，现在可以打开服务配置页面了。\033[0m\n";
    } else {
        echo "\n\033[31m✗ 有字段未补齐，请检查报错信息。\033[0m\n";
    }
} else {
    if ($allOk) {
        echo "<br><br><b style='color:#10b981;'>✓ 全部完成，现在可以打开「⚙️ 服务配置」页面了。</b>";
        echo "<br><br><a href='index.php?page=services' style='background:#10b981;color:white;padding:10px 20px;border-radius:8px;text-decoration:none;'>→ 打开服务配置</a>";
        echo "<br><br><a href='index.php?page=services&action=sync_all' style='background:#6366f1;color:white;padding:10px 20px;border-radius:8px;text-decoration:none;'>→ 同步 HeroSMS 数据</a>";
    } else {
        echo "<br><br><b style='color:#ef4444;'>✗ 有字段未补齐，请检查报错信息。</b>";
    }
    echo "</div></body></html>";
}
