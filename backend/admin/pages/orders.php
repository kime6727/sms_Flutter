<?php
/**
 * 订单管理页面
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
        $orders = $db->query(
            "SELECT o.id, o.user_id, o.service_name, o.country_name, o.phone_number, o.status, o.total_price, o.cost_price, o.profit, o.hero_order_id, o.hero_status, o.created_at, o.updated_at, o.expires_at, o.completed_at, s.name as full_service_name, c.name as full_country_name
             FROM orders o
             LEFT JOIN services s ON o.service_id = s.id
             LEFT JOIN countries c ON o.country_id = c.id
             WHERE o.user_id = ?
             ORDER BY o.created_at DESC
             LIMIT 50",
            [$userId]
        )->fetchAll(PDO::FETCH_ASSOC);
        
        header('Content-Type: application/json');
        echo json_encode(['orders' => $orders]);
    } catch (Exception $e) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// 处理批量删除订单操作
if (($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'batch_delete_orders') || (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && ($_POST['action'] ?? '') === 'batch_delete_orders')) {
    $orderIds = $_POST['order_ids'] ?? [];
    
    if (!is_array($orderIds) || empty($orderIds)) {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => '请选择要删除的订单']);
            exit;
        }
        header("Location: ?page=orders&error=" . urlencode("请选择要删除的订单"));
        exit;
    }
    
    $deletedCount = 0;
    
    foreach ($orderIds as $orderId) {
        // 删除订单相关的短信记录
        $db->query("DELETE FROM sms_messages WHERE order_id = ?", [$orderId]);
        // 删除订单
        $db->query("DELETE FROM orders WHERE id = ?", [$orderId]);
        $deletedCount++;
    }
    
    $message = "成功删除 {$deletedCount} 个订单";
    
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => $message, 'deleted' => $deletedCount]);
        exit;
    }
    header("Location: ?page=orders&msg=" . urlencode($message));
    exit;
}

// 处理批量导出订单操作
if (($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'batch_export_orders') || (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && ($_POST['action'] ?? '') === 'batch_export_orders')) {
    $orderIds = $_POST['order_ids'] ?? [];
    
    if (!is_array($orderIds) || empty($orderIds)) {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => '请选择要导出的订单']);
            exit;
        }
        header("Location: ?page=orders&error=" . urlencode("请选择要导出的订单"));
        exit;
    }
    
    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
    $orders = $db->query(
        "SELECT o.id, o.user_id, u.username, o.service_name, o.country_name, o.phone_number, o.status, o.total_price, o.cost_price, o.profit, o.created_at, o.expires_at, o.completed_at, sm.code as sms_code, sm.received_at as sms_time
         FROM orders o
         LEFT JOIN users u ON o.user_id = u.id
         LEFT JOIN sms_messages sm ON o.id = sm.order_id
         WHERE o.id IN ($placeholders)",
        $orderIds
    )->fetchAll();
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="orders_export_' . date('YmdHis') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM
    
    fputcsv($output, ['订单ID', '用户ID', '用户名', '服务', '国家', '号码', '验证码', '价格', '成本', '利润', '状态', '创建时间', '过期时间', '完成时间', '短信接收时间']);
    
    $statusMap = [
        'active' => '进行中',
        'completed' => '已完成',
        'expired' => '已过期',
        'cancelled' => '已取消',
        'pending' => '待处理',
        'refunded' => '已退款'
    ];
    
    foreach ($orders as $order) {
        fputcsv($output, [
            $order['id'],
            $order['user_id'],
            $order['username'] ?? '',
            $order['service_name'] ?? '',
            $order['country_name'] ?? '',
            $order['phone_number'] ?? '',
            $order['sms_code'] ?? '',
            $order['total_price'],
            $order['cost_price'] ?? '',
            $order['profit'] ?? '',
            $statusMap[$order['status']] ?? $order['status'],
            $order['created_at'],
            $order['expires_at'] ?? '',
            $order['completed_at'] ?? '',
            $order['sms_time'] ?? ''
        ]);
    }
    
    fclose($output);
    exit;
}

$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$currentPage = intval($_GET['p'] ?? 1);
$limit = 20;
$offset = ($currentPage - 1) * $limit;

$where = "1=1";
$params = [];

if ($search) {
    $where .= " AND (o.id LIKE ? OR o.phone_number LIKE ? OR u.username LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status) {
    $where .= " AND o.status = ?";
    $params[] = $status;
}

$total = $db->query(
    "SELECT COUNT(*) FROM orders o 
     LEFT JOIN users u ON o.user_id = u.id 
     LEFT JOIN services s ON o.service_id = s.id
     LEFT JOIN countries c ON o.country_id = c.id
     WHERE $where",
    $params
)->fetchColumn();

$orders = $db->query(
    "SELECT o.*, u.username, u.device_id, s.name as service_name, c.name as country_name, sm.code as sms_code, sm.received_at as sms_time
     FROM orders o
     LEFT JOIN users u ON o.user_id = u.id
     LEFT JOIN services s ON o.service_id = s.id
     LEFT JOIN countries c ON o.country_id = c.id
     LEFT JOIN sms_messages sm ON o.id = sm.order_id
     WHERE $where
     ORDER BY o.created_at DESC
     LIMIT $limit OFFSET $offset",
    $params
)->fetchAll();

$totalPages = ceil($total / $limit);

$msg = $_GET['msg'] ?? '';
$error = $_GET['error'] ?? '';
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
    <h4 style="margin:0;">📋 订单管理</h4>
    <div style="display:flex;gap:12px;align-items:center;">
        <span style="color:#64748b;">共 <?= number_format($total) ?> 条记录</span>
        <span id="selectedOrderCount" style="color:#6366f1;font-weight:600;display:none;">已选择 <span id="selectedOrderNum">0</span> 个</span>
        <button id="batchDeleteOrderBtn" onclick="batchDeleteOrders()" style="background:#ef4444;color:white;border:none;padding:8px 16px;border-radius:8px;cursor:pointer;font-size:14px;font-weight:600;display:none;">🗑️ 批量删除</button>
        <button id="batchExportOrderBtn" onclick="batchExportOrders()" style="background:#8b5cf6;color:white;border:none;padding:8px 16px;border-radius:8px;cursor:pointer;font-size:14px;font-weight:600;display:none;">📥 批量导出</button>
    </div>
</div>

<?php if($msg): ?>
<div style="background:#d1fae5;color:#065f46;padding:12px 16px;border-radius:8px;margin-bottom:16px;">✅ <?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<?php if($error): ?>
<div style="background:#fee2e2;color:#991b1b;padding:12px 16px;border-radius:8px;margin-bottom:16px;">❌ <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="card">
    <div style="padding:20px;">
        <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:center;">
            <input type="hidden" name="page" value="orders">
            <input type="text" name="search" placeholder="搜索订单号/号码/用户" value="<?= htmlspecialchars($search) ?>" style="flex:1;min-width:200px;padding:10px 14px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;">
            <select name="status" style="padding:10px 14px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;min-width:120px;">
                <option value="">全部状态</option>
                <option value="pending" <?= $status=='pending'?'selected':'' ?>>未使用</option>
                <option value="active" <?= $status=='active'?'selected':'' ?>>等待短信</option>
                <option value="completed" <?= $status=='completed'?'selected':'' ?>>已完成</option>
                <option value="expired" <?= $status=='expired'?'selected':'' ?>>已过期</option>
                <option value="cancelled" <?= $status=='cancelled'?'selected':'' ?>>已取消</option>
                <option value="refunded" <?= $status=='refunded'?'selected':'' ?>>已退款</option>
            </select>
            <button type="submit" class="btn btn-primary" style="white-space:nowrap;">🔍 搜索</button>
        </form>
    </div>
</div>

<div class="card" style="margin-top:20px;">
    <div class="table-responsive">
    <table>
        <thead>
            <tr>
                <th style="width:40px;">
                    <input type="checkbox" id="selectAllOrders" onchange="toggleSelectAllOrders(this)" style="cursor:pointer;width:16px;height:16px;">
                </th>
                <th>订单号</th>
                <th>用户</th>
                <th>服务</th>
                <th>国家</th>
                <th>号码/验证码</th>
                <th>价格</th>
                <th>订单状态</th>
                <th>创建时间</th>
                <th>过期时间</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            <?php if(empty($orders)): ?>
            <tr>
                <td colspan="11" style="text-align:center;color:#64748b;padding:40px;">暂无订单数据</td>
            </tr>
            <?php else: ?>
            <?php foreach($orders as $order): ?>
            <tr>
                <td>
                    <input type="checkbox" class="order-checkbox" value="<?= $order['id'] ?>" onchange="updateOrderSelection()" style="cursor:pointer;width:16px;height:16px;">
                </td>
                <td><small style="color:#64748b;"><?= htmlspecialchars(substr($order['id'], 0, 16)) ?></small></td>
                <td>
                    <small><?= htmlspecialchars($order['username'] ?? substr($order['user_id'], 0, 8)) ?></small><br>
                    <small style="color:#94a3b8;font-size:11px;"><?= htmlspecialchars(substr($order['device_id'] ?? '', 0, 12)) ?>...</small>
                </td>
                <td><?= htmlspecialchars($order['service_name'] ?? '-') ?></td>
                <td><?= htmlspecialchars($order['country_name'] ?? '-') ?></td>
                <td>
                    <div style="display:flex;flex-direction:column;gap:4px;">
                        <?php if($order['phone_number']): ?>
                        <span style="font-weight:600;color:#1e293b;"><?= htmlspecialchars($order['phone_number']) ?></span>
                        <?php else: ?>
                        <span style="color:#94a3b8;">-</span>
                        <?php endif; ?>
                        
                        <?php if($order['sms_code']): ?>
                        <div style="display:flex;align-items:center;gap:4px;">
                            <span style="background:#f0fdf4;color:#166534;padding:2px 6px;border-radius:4px;font-size:11px;font-weight:bold;border:1px solid #bbf7d0;">验证码: <?= htmlspecialchars($order['sms_code']) ?></span>
                        </div>
                        <?php elseif($order['status'] === 'active'): ?>
                        <small style="color:#64748b;font-size:11px;">⏳ 等待短信...</small>
                        <?php endif; ?>
                    </div>
                </td>
                <td><?= $order['total_price'] ?> 积分</td>
                <td>
                    <?php
                    $statusMap = [
                        'active' => ['进行中', 'badge-success'],
                        'completed' => ['已完成', 'badge-info'],
                        'expired' => ['已过期', 'badge-danger'],
                        'cancelled' => ['已取消', 'badge-secondary'],
                        'pending' => ['待处理', 'badge-warning']
                    ];
                    $statusInfo = $statusMap[$order['status']] ?? [$order['status'], 'badge-secondary'];
                    ?>
                    <span class="badge <?= $statusInfo[1] ?>"><?= $statusInfo[0] ?></span>
                </td>
                <td><small style="color:#64748b;"><?= date('m-d H:i', strtotime($order['created_at'])) ?></small></td>
                <td><small style="color:#64748b;"><?= $order['expires_at'] ? date('m-d H:i', strtotime($order['expires_at'])) : '-' ?></small></td>
                <td>
                    <div style="display:flex;gap:4px;flex-wrap:wrap;">
                        <a href="?page=order_detail&id=<?= $order['id'] ?>" style="background:#6366f1;color:white;padding:4px 10px;border-radius:6px;text-decoration:none;font-size:12px;">👁️ 查看</a>
                        <?php if($order['status'] === 'pending' || $order['status'] === 'active'): ?>
                        <form method="POST" action="?page=order_detail&id=<?= $order['id'] ?>" style="display:inline;" onsubmit="return confirm('确定取消此订单？积分不会退还。')">
                            <input type="hidden" name="admin_action" value="cancel">
                            <button type="submit" style="background:#f59e0b;color:white;border:none;padding:4px 10px;border-radius:6px;cursor:pointer;font-size:12px;">⚠️ 取消</button>
                        </form>
                        <?php endif; ?>
                        <?php if($order['status'] === 'active' || $order['status'] === 'completed' || $order['status'] === 'expired'): ?>
                        <form method="POST" action="?page=order_detail&id=<?= $order['id'] ?>" style="display:inline;" onsubmit="return confirm('确定退款？将退还 <?= $order['total_price'] ?> 积分。')">
                            <input type="hidden" name="admin_action" value="refund">
                            <button type="submit" style="background:#ef4444;color:white;border:none;padding:4px 10px;border-radius:6px;cursor:pointer;font-size:12px;">💰 退款</button>
                        </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    </div>

    <?php if($totalPages > 1): ?>
    <div style="padding:16px;display:flex;justify-content:center;gap:4px;flex-wrap:wrap;">
        <?php for($i=1; $i<=$totalPages; $i++): ?>
        <a href="?page=orders&p=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= $status ?>"
           style="padding:6px 12px;border:1px solid #e2e8f0;border-radius:6px;text-decoration:none;color:#64748b;font-size:13px;<?= $i==$currentPage?'background:#6366f1;color:white;border-color:#6366f1;':'' ?>">
            <?= $i ?>
        </a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<script>
// 批量操作相关函数
function toggleSelectAllOrders(checkbox) {
    const checkboxes = document.querySelectorAll('.order-checkbox');
    checkboxes.forEach(cb => cb.checked = checkbox.checked);
    updateOrderSelection();
}

function updateOrderSelection() {
    const checkboxes = document.querySelectorAll('.order-checkbox:checked');
    const count = checkboxes.length;
    const selectAllCheckbox = document.getElementById('selectAllOrders');
    const totalCheckboxes = document.querySelectorAll('.order-checkbox').length;
    
    // 更新全选框状态
    if (selectAllCheckbox) {
        selectAllCheckbox.checked = count === totalCheckboxes && count > 0;
        selectAllCheckbox.indeterminate = count > 0 && count < totalCheckboxes;
    }
    
    // 显示/隐藏批量操作按钮
    const selectedCountEl = document.getElementById('selectedOrderCount');
    const selectedNumEl = document.getElementById('selectedOrderNum');
    const batchDeleteBtn = document.getElementById('batchDeleteOrderBtn');
    const batchExportBtn = document.getElementById('batchExportOrderBtn');
    
    if (selectedCountEl) selectedCountEl.style.display = count > 0 ? 'block' : 'none';
    if (selectedNumEl) selectedNumEl.textContent = count;
    if (batchDeleteBtn) batchDeleteBtn.style.display = count > 0 ? 'block' : 'none';
    if (batchExportBtn) batchExportBtn.style.display = count > 0 ? 'block' : 'none';
}

function getSelectedOrderIds() {
    const checkboxes = document.querySelectorAll('.order-checkbox:checked');
    return Array.from(checkboxes).map(cb => cb.value);
}

function batchDeleteOrders() {
    const orderIds = getSelectedOrderIds();
    if (orderIds.length === 0) {
        alert('请先选择要删除的订单');
        return;
    }
    
    if (!confirm(`⚠️ 警告：确定要删除选中的 ${orderIds.length} 个订单吗？\n\n此操作将同时删除订单相关的短信记录，且不可恢复！`)) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'batch_delete_orders');
    orderIds.forEach(id => formData.append('order_ids[]', id));
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData,
        headers: {'X-Requested-With': 'XMLHttpRequest'}
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert(data.message || '批量删除成功');
            window.location.reload();
        } else {
            alert('删除失败: ' + (data.error || '未知错误'));
        }
    })
    .catch(err => {
        alert('请求失败: ' + err.message);
    });
}

function batchExportOrders() {
    const orderIds = getSelectedOrderIds();
    if (orderIds.length === 0) {
        alert('请先选择要导出的订单');
        return;
    }
    
    // 创建隐藏表单提交
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = window.location.href;
    
    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'action';
    actionInput.value = 'batch_export_orders';
    form.appendChild(actionInput);
    
    orderIds.forEach(id => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'order_ids[]';
        input.value = id;
        form.appendChild(input);
    });
    
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}
</script>