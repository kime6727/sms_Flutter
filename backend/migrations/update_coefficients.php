<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lib/Database.php';

$db = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT);

echo "更新默认系数...\n";

$db->query("UPDATE system_settings SET value = '2' WHERE `key` = 'default_coefficient_before'");
echo "✅ default_coefficient_before = 2\n";

$db->query("UPDATE system_settings SET value = '4' WHERE `key` = 'default_coefficient_after'");
echo "✅ default_coefficient_after = 4\n";

echo "系数更新完成！\n";
echo "说明：充值前系数=2（低价吸引），充值后系数=4（高价盈利）\n";
?>
