<?php
/**
 * 临时调试:看 credit_transactions 等表 schema
 * 访问: https://sms.niceapp.eu.cc/admin/debug_credit_schema.php?token=DEBUG_TOKEN_2026
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lib/Database.php';

$SECRET = 'DEBUG_TOKEN_2026';
$token = $_GET['token'] ?? '';
if ($token !== $SECRET) { http_response_code(403); echo json_encode(['error' => 'bad token']); exit; }

$db = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT);

$out = ['success' => true];

foreach (['credit_transactions', 'users', 'payment_configs', 'service_coefficients', 'system_settings'] as $tbl) {
    try {
        $cols = $db->query("SHOW COLUMNS FROM $tbl")->fetchAll();
        $out["${tbl}_schema"] = array_map(fn($r) => [
            'field' => $r['Field'],
            'type' => $r['Type'],
            'null' => $r['Null'],
            'default' => $r['Default'] === null ? 'NULL' : $r['Default']
        ], $cols);
    } catch (Throwable $e) {
        $out["${tbl}_error"] = $e->getMessage();
    }
}

// 自愈: credit_transactions balance_before/balance_after default '0' (避免 INSERT 缺值)
foreach (['balance_before', 'balance_after'] as $f) {
    try {
        $col = $db->query("SHOW COLUMNS FROM credit_transactions WHERE Field = ?", [$f])->fetch();
        if ($col && $col['Null'] === 'NO' && ($col['Default'] === null || $col['Default'] === 'NULL' || $col['Default'] === '')) {
            $db->query("ALTER TABLE credit_transactions MODIFY COLUMN $f DECIMAL(10,2) NOT NULL DEFAULT 0.00");
            $out["credit_transactions_{$f}_fixed"] = 'YES';
        }
    } catch (Throwable $e) {
        $out["credit_transactions_{$f}_error"] = $e->getMessage();
    }
}

echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
