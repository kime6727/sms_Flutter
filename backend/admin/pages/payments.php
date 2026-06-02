<?php
/**
 * 支付记录查询页面
 */

$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$currentPage = intval($_GET['p'] ?? 1);
$limit = 20;
$offset = ($currentPage - 1) * $limit;

$where = "1=1";
$params = [];

if ($search) {
    $where .= " AND (pr.id LIKE ? OR pr.user_id LIKE ? OR pr.transaction_id LIKE ? OR u.username LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status) {
    $where .= " AND pr.status = ?";
    $params[] = $status;
}

if ($dateFrom) {
    $where .= " AND DATE(pr.created_at) >= ?";
    $params[] = $dateFrom;
}

if ($dateTo) {
    $where .= " AND DATE(pr.created_at) <= ?";
    $params[] = $dateTo;
}

$total = $db->query(
    "SELECT COUNT(*) FROM payment_records pr
     LEFT JOIN users u ON pr.user_id = u.id
     WHERE $where",
    $params
)->fetchColumn();

$payments = $db->query(
    "SELECT pr.*, u.username, u.device_id
     FROM payment_records pr
     LEFT JOIN users u ON pr.user_id = u.id
     WHERE $where
     ORDER BY pr.created_at DESC
     LIMIT $limit OFFSET $offset",
    $params
)->fetchAll();

$totalPages = ceil($total / $limit);

// 统计信息
$totalAmount = $db->query(
    "SELECT COALESCE(SUM(pr.amount), 0) FROM payment_records pr LEFT JOIN users u ON pr.user_id = u.id WHERE $where",
    $params
)->fetchColumn();

$todayAmount = $db->query(
    "SELECT COALESCE(SUM(amount), 0) FROM payment_records WHERE DATE(created_at) = ?",
    [date('Y-m-d')]
)->fetchColumn();

$todayCount = $db->query(
    "SELECT COUNT(*) FROM payment_records WHERE DATE(created_at) = ?",
    [date('Y-m-d')]
)->fetchColumn();
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
    <h4 style="margin:0;">💳 支付记录</h4>
    <span style="color:#64748b;">共 <?= number_format($total) ?> 条记录</span>
</div>

<div class="stat-row" style="grid-template-columns: repeat(3, 1fr);">
    <div class="stat-card">
        <div class="stat-icon revenue">💰</div>
        <h3>$<?= number_format($totalAmount, 2) ?></h3>
        <p>筛选总金额</p>
    </div>
    <div class="stat-card">
        <div class="stat-icon orders">📅</div>
        <h3>$<?= number_format($todayAmount, 2) ?></h3>
        <p>今日收入</p>
    </div>
    <div class="stat-card">
        <div class="stat-icon users">👥</div>
        <h3><?= number_format($todayCount) ?></h3>
        <p>今日支付笔数</p>
    </div>
</div>

<div class="card">
    <div style="padding:20px;">
        <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
            <input type="hidden" name="page" value="payments">
            <div style="flex:1;min-width:200px;">
                <label style="display:block;margin-bottom:6px;color:#64748b;font-size:13px;">搜索</label>
                <input type="text" name="search" placeholder="订单号/用户/交易ID" value="<?= htmlspecialchars($search) ?>" style="width:100%;padding:10px 14px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;">
            </div>
            <div style="min-width:120px;">
                <label style="display:block;margin-bottom:6px;color:#64748b;font-size:13px;">状态</label>
                <select name="status" style="width:100%;padding:10px 14px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;">
                    <option value="">全部</option>
                    <option value="completed" <?= $status=='completed'?'selected':'' ?>>成功</option>
                    <option value="failed" <?= $status=='failed'?'selected':'' ?>>失败</option>
                    <option value="refunded" <?= $status=='refunded'?'selected':'' ?>>已退款</option>
                </select>
            </div>
            <div style="min-width:140px;">
                <label style="display:block;margin-bottom:6px;color:#64748b;font-size:13px;">开始日期</label>
                <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>" style="width:100%;padding:10px 14px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;">
            </div>
            <div style="min-width:140px;">
                <label style="display:block;margin-bottom:6px;color:#64748b;font-size:13px;">结束日期</label>
                <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>" style="width:100%;padding:10px 14px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;">
            </div>
            <button type="submit" class="btn btn-primary" style="white-space:nowrap;">🔍 搜索</button>
            <a href="?page=payments" class="btn" style="background:#e2e8f0;color:#64748b;white-space:nowrap;">重置</a>
        </form>
    </div>
</div>

<div class="card" style="margin-top:20px;">
    <div class="table-responsive">
    <table>
        <thead>
            <tr>
                <th>记录ID</th>
                <th>用户</th>
                <th>交易ID</th>
                <th>产品ID</th>
                <th>金额</th>
                <th>环境</th>
                <th>状态</th>
                <th>创建时间</th>
            </tr>
        </thead>
        <tbody>
            <?php if(empty($payments)): ?>
            <tr>
                <td colspan="8" style="text-align:center;color:#64748b;padding:40px;">暂无支付记录</td>
            </tr>
            <?php else: ?>
            <?php foreach($payments as $payment): ?>
            <tr>
                <td><small style="color:#64748b;"><?= htmlspecialchars(substr($payment['id'], 0, 12)) ?>...</small></td>
                <td>
                    <small><?= htmlspecialchars($payment['username'] ?? substr($payment['user_id'], 0, 8)) ?></small><br>
                    <small style="color:#94a3b8;font-size:11px;"><?= htmlspecialchars(substr($payment['device_id'] ?? '', 0, 12)) ?>...</small>
                </td>
                <td><small style="font-family:monospace;"><?= htmlspecialchars(substr($payment['transaction_id'], 0, 20)) ?>...</small></td>
                <td><code style="background:#f1f5f9;padding:2px 6px;border-radius:4px;font-size:12px;"><?= htmlspecialchars($payment['product_id']) ?></code></td>
                <td><strong style="color:#10b981;">$<?= number_format($payment['amount'], 2) ?></strong></td>
                <td>
                    <span class="badge <?= $payment['environment']=='Production'?'badge-success':'badge-warning' ?>">
                        <?= htmlspecialchars($payment['environment'] ?? '-') ?>
                    </span>
                </td>
                <td>
                    <?php
                    $statusMap = [
                        'completed' => ['成功', 'badge-success'],
                        'failed' => ['失败', 'badge-danger'],
                        'refunded' => ['已退款', 'badge-warning']
                    ];
                    $statusInfo = $statusMap[$payment['status']] ?? [$payment['status'], 'badge-secondary'];
                    ?>
                    <span class="badge <?= $statusInfo[1] ?>"><?= $statusInfo[0] ?></span>
                </td>
                <td><small style="color:#64748b;"><?= date('Y-m-d H:i', strtotime($payment['created_at'])) ?></small></td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    </div>

    <?php if($totalPages > 1): ?>
    <div style="padding:16px;display:flex;justify-content:center;gap:4px;flex-wrap:wrap;">
        <?php if($currentPage > 1): ?>
        <a href="?page=payments&p=<?= $currentPage-1 ?>&search=<?= urlencode($search) ?>&status=<?= $status ?>&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>"
           style="padding:6px 12px;border:1px solid #e2e8f0;border-radius:6px;text-decoration:none;color:#64748b;font-size:13px;">
            ← 上一页
        </a>
        <?php endif; ?>
        
        <?php
        $startPage = max(1, $currentPage - 2);
        $endPage = min($totalPages, $currentPage + 2);
        for($i=$startPage; $i<=$endPage; $i++):
        ?>
        <a href="?page=payments&p=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= $status ?>&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>"
           style="padding:6px 12px;border:1px solid #e2e8f0;border-radius:6px;text-decoration:none;color:#64748b;font-size:13px;<?= $i==$currentPage?'background:#6366f1;color:white;border-color:#6366f1;':'' ?>">
            <?= $i ?>
        </a>
        <?php endfor; ?>
        
        <?php if($currentPage < $totalPages): ?>
        <a href="?page=payments&p=<?= $currentPage+1 ?>&search=<?= urlencode($search) ?>&status=<?= $status ?>&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>"
           style="padding:6px 12px;border:1px solid #e2e8f0;border-radius:6px;text-decoration:none;color:#64748b;font-size:13px;">
            下一页 →
        </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
