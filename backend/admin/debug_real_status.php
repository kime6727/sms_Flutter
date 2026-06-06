<?php
/**
 * 临时调试:看所有关键表 schema + 真实数据
 * 访问: https://sms.niceapp.eu.cc/admin/debug_real_status.php?token=DEBUG_TOKEN_2026
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lib/Database.php';

$SECRET = 'DEBUG_TOKEN_2026';
$token = $_GET['token'] ?? '';
if ($token !== $SECRET) { http_response_code(403); echo json_encode(['error' => 'bad token']); exit; }

$db = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT);

$out = ['success' => true];

// 1. countries
try {
    $cols = $db->query("SHOW COLUMNS FROM countries")->fetchAll();
    $out['countries_schema'] = array_map(fn($r) => ['field' => $r['Field'], 'type' => $r['Type'], 'null' => $r['Null'], 'key' => $r['Key'], 'default' => $r['Default']], $cols);
    $out['countries_data'] = $db->query("SELECT id, name, hero_country_id, code, active, sort_order FROM countries ORDER BY id LIMIT 10")->fetchAll();
    $out['countries_count'] = $db->query("SELECT COUNT(*) FROM countries")->fetchColumn();
} catch (Throwable $e) { $out['countries_error'] = $e->getMessage(); }

// 2. users
try {
    $out['users_data'] = $db->query("SELECT id, email, username, status FROM users ORDER BY id LIMIT 10")->fetchAll();
    $out['users_count'] = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
} catch (Throwable $e) { $out['users_error'] = $e->getMessage(); }

// 3. coefficients (or whatever table)
foreach (['coefficients', 'service_coefficients', 'system_settings'] as $tbl) {
    try {
        if ($db->query("SHOW TABLES LIKE '$tbl'")->fetch()) {
            $cols = $db->query("SHOW COLUMNS FROM $tbl")->fetchAll();
            $out["${tbl}_schema"] = array_map(fn($r) => ['field' => $r['Field'], 'type' => $r['Type'], 'null' => $r['Null'], 'default' => $r['Default']], $cols);
            $out["${tbl}_data"] = $db->query("SELECT * FROM $tbl LIMIT 5")->fetchAll();
        }
    } catch (Throwable $e) { $out["${tbl}_error"] = $e->getMessage(); }
}

// 4. payment_configs
try {
    $cols = $db->query("SHOW COLUMNS FROM payment_configs")->fetchAll();
    $out['payment_configs_schema'] = array_map(fn($r) => ['field' => $r['Field'], 'type' => $r['Type'], 'null' => $r['Null'], 'default' => $r['Default']], $cols);
    $out['payment_configs_data'] = $db->query("SELECT * FROM payment_configs LIMIT 5")->fetchAll();
    $out['payment_configs_count'] = $db->query("SELECT COUNT(*) FROM payment_configs")->fetchColumn();

    // 自愈: payment_configs.id 加 AUTO_INCREMENT
    $idInfo = $db->query("SHOW COLUMNS FROM payment_configs WHERE Field = 'id'")->fetch();
    if ($idInfo && stripos($idInfo['Extra'] ?? '', 'auto_increment') === false) {
        try {
            $db->query("ALTER TABLE payment_configs MODIFY COLUMN id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY");
            $out['payment_configs_id_fixed'] = 'YES - AUTO_INCREMENT added';
        } catch (Throwable $e) {
            $out['payment_configs_id_fixed'] = 'NO: ' . $e->getMessage();
        }
    } else {
        $out['payment_configs_id_fixed'] = 'already has auto_increment';
    }
} catch (Throwable $e) { $out['payment_configs_error'] = $e->getMessage(); }

// 5. services
try {
    $cols = $db->query("SHOW COLUMNS FROM services")->fetchAll();
    $out['services_schema'] = array_map(fn($r) => ['field' => $r['Field'], 'type' => $r['Type'], 'null' => $r['Null'], 'default' => $r['Default']], $cols);
    $out['services_data'] = $db->query("SELECT id, name, code, published FROM services ORDER BY id LIMIT 5")->fetchAll();
} catch (Throwable $e) { $out['services_error'] = $e->getMessage(); }

echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
