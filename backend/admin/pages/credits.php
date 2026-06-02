<?php
/**
 * 积分变动记录页面
 */

// 独立AJAX请求：需要自己加载数据库连接
if (!empty($_GET['ajax']) && !empty($_GET['user_id'])) {
    if (!isset($db)) {
        require_once __DIR__ . '/../../config/database.php';
        require_once __DIR__ . '/../../lib/Database.php';
        $db = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT);
    }
    
    try {
        $userId = $_GET['user_id'];
        $transactions = $db->query(
            "SELECT id, user_id, type, amount, balance_after, description, created_at
             FROM credit_transactions
             WHERE user_id = ?
             ORDER BY created_at DESC
             LIMIT 50",
            [$userId]
        )->fetchAll(PDO::FETCH_ASSOC);
        
        header('Content-Type: application/json');
        echo json_encode(['transactions' => $transactions]);
    } catch (Exception $e) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// 分页
$currentPage = intval($_GET['p'] ?? 1);
$limit = 30;
$offset = ($currentPage - 1) * $limit;

$search = $_GET['search'] ?? '';
$type = $_GET['type'] ?? '';

$where = "1=1";
$params = [];

if ($search) {
    $where .= " AND (ct.user_id LIKE ? OR u.username LIKE ? OR ct.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($type) {
    $where .= " AND ct.type = ?";
    $params[] = $type;
}

$total = $db->query(
    "SELECT COUNT(*) FROM credit_transactions ct
     LEFT JOIN users u ON ct.user_id = u.id
     WHERE $where",
    $params
)->fetchColumn();

$transactions = $db->query(
    "SELECT ct.*, u.username
     FROM credit_transactions ct
     LEFT JOIN users u ON ct.user_id = u.id
     WHERE $where
     ORDER BY ct.created_at DESC
     LIMIT $limit OFFSET $offset",
    $params
)->fetchAll();

$totalPages = max(1, ceil($total / $limit));
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
    <h4 style="margin:0;">💰 积分变动记录</h4>
    <span style="color:#64748b;">共 <?= number_format($total) ?> 条记录</span>
</div>

<div class="card">
    <div style="padding:20px;">
        <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:center;">
            <input type="hidden" name="page" value="credits">
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="搜索用户ID/用户名/备注"
                style="padding:10px 14px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;width:220px;">
            <select name="type" style="padding:10px 14px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;">
                <option value="">全部类型</option>
                <option value="purchase" <?= $type==='purchase'?'selected':'' ?>>📦 购买消耗</option>
                <option value="topup" <?= $type==='topup'?'selected':'' ?>>💎 充值</option>
                <option value="bonus" <?= $type==='bonus'?'selected':'' ?>>🎁 赠送</option>
                <option value="refund" <?= $type==='refund'?'selected':'' ?>>↩️ 退款</option>
            </select>
            <button class="btn btn-primary btn-sm" type="submit">🔍 搜索</button>
            <?php if($search || $type): ?>
            <a href="?page=credits" class="btn btn-sm" style="background:#e2e8f0;color:#64748b;text-decoration:none;">重置</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
    <table>
        <thead>
            <tr>
                <th>记录ID</th>
                <th>用户</th>
                <th>类型</th>
                <th style="text-align:right;">变动积分</th>
                <th style="text-align:right;">变动后余额</th>
                <th>说明</th>
                <th>时间</th>
            </tr>
        </thead>
        <tbody>
            <?php if(empty($transactions)): ?>
            <tr>
                <td colspan="7" style="text-align:center;color:#64748b;padding:40px;">暂无积分变动记录</td>
            </tr>
            <?php else: ?>
            <?php foreach($transactions as $txn): ?>
            <tr>
                <td><small style="font-family:monospace;font-size:11px;"><?= htmlspecialchars(substr($txn['id'], 0, 20)) ?>...</small></td>
                <td>
                    <div style="font-weight:500;font-size:13px;"><?= htmlspecialchars($txn['username'] ?? '未知') ?></div>
                    <div style="font-family:monospace;font-size:11px;color:#94a3b8;"><?= htmlspecialchars(substr($txn['user_id'], 0, 12)) ?>...</div>
                </td>
                <td>
                    <?php
                    $typeMap = [
                        'purchase' => '📦 购买消耗',
                        'topup' => '💎 充值',
                        'bonus' => '🎁 赠送',
                        'refund' => '↩️ 退款'
                    ];
                    echo htmlspecialchars($typeMap[$txn['type']] ?? $txn['type']);
                    ?>
                </td>
                <td style="text-align:right;">
                    <strong style="color:<?= $txn['amount'] > 0 ? '#10b981' : '#ef4444' ?>;">
                        <?= $txn['amount'] > 0 ? '+' : '' ?><?= number_format($txn['amount']) ?>
                    </strong>
                </td>
                <td style="text-align:right;font-weight:600;"><?= number_format($txn['balance_after']) ?></td>
                <td style="font-size:13px;color:#64748b;"><?= htmlspecialchars($txn['description'] ?? '-') ?></td>
                <td style="font-size:13px;color:#64748b;"><?= htmlspecialchars($txn['created_at']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    </div>
    
    <?php if($totalPages > 1): ?>
    <div style="padding:16px 20px;border-top:1px solid #e2e8f0;display:flex;justify-content:center;gap:4px;">
        <?php for($i = 1; $i <= $totalPages; $i++): ?>
            <?php if($i == $currentPage): ?>
            <span style="padding:8px 14px;background:#6366f1;color:white;border-radius:6px;font-size:13px;font-weight:600;"><?= $i ?></span>
            <?php else: ?>
            <a href="?page=credits&p=<?= $i ?><?= $search ? '&search='.urlencode($search) : '' ?><?= $type ? '&type='.$type : '' ?>"
               style="padding:8px 14px;background:white;color:#334155;border:1px solid #e2e8f0;border-radius:6px;text-decoration:none;font-size:13px;"><?= $i ?></a>
            <?php endif; ?>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>
