<?php
/**
 * 临时调试：给指定 user 加积分
 * 访问: https://sms.niceapp.eu.cc/admin/debug_charge.php?user_id=xxx&amount=10000&token=DEBUG_TOKEN_2026
 *
 * 用完即删
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../helpers/functions.php';

$SECRET = 'DEBUG_TOKEN_2026';

$token = $_GET['token'] ?? '';
$userId = $_GET['user_id'] ?? '';
$amount = intval($_GET['amount'] ?? 0);

if ($token !== $SECRET) { http_response_code(403); echo json_encode(['error' => 'bad token']); exit; }
if (!$userId || $amount == 0) { http_response_code(400); echo json_encode(['error' => 'user_id and amount required']); exit; }

$db = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT);

try {
    $db->beginTransaction();

    $user = $db->query("SELECT id, balance FROM users WHERE id = ?", [$userId])->fetch();
    if (!$user) { http_response_code(404); echo json_encode(['error' => 'user not found']); exit; }

    $balanceBefore = intval($user['balance']);
    $balanceAfter = $balanceBefore + $amount;

    $db->query("UPDATE users SET balance = balance + ? WHERE id = ?", [$amount, $userId]);
    $db->insert('credit_transactions', [
        'user_id' => $userId,
        'type' => 'admin_adjust',
        'amount' => $amount,
        'balance_before' => $balanceBefore,
        'balance_after' => $balanceAfter,
        'description' => 'DEBUG charge via debug_charge.php',
    ]);

    $db->commit();

    echo json_encode(['success' => true, 'user_id' => $userId, 'before' => $balanceBefore, 'after' => $balanceAfter]);
} catch (Exception $e) {
    $db->rollBack();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
