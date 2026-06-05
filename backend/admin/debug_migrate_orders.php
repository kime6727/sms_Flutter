<?php
/**
 * 临时调试：迁移老订单（quantity>1 单条 row）→ 拆成 N 条独立 order rows
 * 访问: https://sms.niceapp.eu.cc/admin/debug_migrate_orders.php?token=DEBUG_TOKEN_2026
 *
 * 用完即删
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lib/Database.php';

$SECRET = 'DEBUG_TOKEN_2026';
$token = $_GET['token'] ?? '';
if ($token !== $SECRET) { http_response_code(403); echo json_encode(['error' => 'bad token']); exit; }

$db = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT);

// 找出所有 quantity > 1 的老订单
$oldOrders = $db->query(
    "SELECT * FROM orders WHERE quantity > 1 ORDER BY created_at"
)->fetchAll();

$migrated = [];
foreach ($oldOrders as $old) {
    $qty = intval($old['quantity']);
    if ($qty <= 1) continue;

    // 检查这个订单是否已经迁移过（看有没有同 user_id service_id country_id 的同 created_at 订单）
    $existing = $db->query(
        "SELECT COUNT(*) FROM orders WHERE user_id = ? AND service_id = ? AND country_id = ? AND created_at = ? AND id != ?",
        [$old['user_id'], $old['service_id'], $old['country_id'], $old['created_at'], $old['id']]
    )->fetchColumn();

    if ($existing >= $qty - 1) {
        // 已经迁移过
        $migrated[] = ['id' => $old['id'], 'action' => 'skip', 'reason' => 'already migrated'];
        continue;
    }

    $db->beginTransaction();
    try {
        $batchId = bin2hex(random_bytes(16));
        $pricePerOrder = floatval($old['total_price']) / $qty;
        $costPerOrder = floatval($old['cost_price'] ?? 0) / $qty;
        $profitPerOrder = floatval($old['profit'] ?? 0) / $qty;
        $purchaseExpiresAt = $old['purchase_expires_at'] ?? date('Y-m-d H:i:s', strtotime($old['created_at']) + 72*3600);

        // 把老订单改为 quantity=1 + 共享 batch_id
        $db->query(
            "UPDATE orders SET quantity = 1, batch_id = ?, purchase_expires_at = ? WHERE id = ?",
            [$batchId, $purchaseExpiresAt, $old['id']]
        );

        // 插入 (qty - 1) 条新订单
        for ($i = 1; $i < $qty; $i++) {
            $db->insert('orders', [
                'user_id' => $old['user_id'],
                'service_id' => $old['service_id'],
                'country_id' => $old['country_id'],
                'quantity' => 1,
                'batch_id' => $batchId,
                'purchase_expires_at' => $purchaseExpiresAt,
                'total_price' => $pricePerOrder,
                'cost_price' => $costPerOrder,
                'profit' => $profitPerOrder,
                'status' => $old['status'],
                'created_at' => $old['created_at'],
                'phone_number' => $old['phone_number'] ?? null,
                'hero_order_id' => $old['hero_order_id'] ?? null,
                'hero_status' => $old['hero_status'] ?? null,
                'activated_at' => $old['activated_at'] ?? null,
                'completed_at' => $old['completed_at'] ?? null,
                'expires_at' => $old['expires_at'] ?? null,
            ]);
        }

        $db->commit();
        $migrated[] = [
            'id' => $old['id'],
            'action' => 'migrated',
            'qty' => $qty,
            'batch_id' => $batchId,
        ];
    } catch (Exception $e) {
        $db->rollBack();
        $migrated[] = ['id' => $old['id'], 'action' => 'error', 'error' => $e->getMessage()];
    }
}

echo json_encode(['success' => true, 'migrated' => $migrated], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
