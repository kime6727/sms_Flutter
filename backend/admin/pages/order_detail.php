<?php
/**
 * 订单详情（AJAX）
 */

$orderId = $_GET['id'] ?? '';

if (!$orderId) {
    echo '<p class="text-danger">订单不存在</p>';
    return;
}

// 处理管理员操作
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['admin_action'] ?? '';
    
    if ($action === 'refund') {
        $order = $db->query("SELECT * FROM orders WHERE id = ?", [$orderId])->fetch();
        if ($order && ($order['status'] === 'active' || $order['status'] === 'completed' || $order['status'] === 'expired')) {
            // 退还积分
            $db->query("UPDATE users SET balance = balance + ? WHERE id = ?", [$order['total_price'], $order['user_id']]);
            // 更新订单状态
            $db->query("UPDATE orders SET status = 'refunded', refunded_at = NOW() WHERE id = ?", [$orderId]);
            // 取消HeroSMS号码
            if ($order['hero_order_id']) {
                require_once __DIR__ . '/../../lib/HeroSMS.php';
                require_once __DIR__ . '/../../lib/KeyManager.php';
                $heroSmsApiKey = KeyManager::getHeroSmsApiKey();
                if ($heroSmsApiKey) {
                    $heroSMS = new HeroSMS($heroSmsApiKey, HEROSMS_BASE_URL);
                    $heroSMS->cancelNumber($order['hero_order_id']);
                }
            }
            $success = '订单已退款，积分已退还给用户';
        } else {
            $error = '当前状态无法退款';
        }
    } elseif ($action === 'cancel') {
        $order = $db->query("SELECT * FROM orders WHERE id = ?", [$orderId])->fetch();
        if ($order && ($order['status'] === 'pending' || $order['status'] === 'active')) {
            // 取消HeroSMS号码
            if ($order['hero_order_id']) {
                require_once __DIR__ . '/../../lib/HeroSMS.php';
                require_once __DIR__ . '/../../lib/KeyManager.php';
                $heroSmsApiKey = KeyManager::getHeroSmsApiKey();
                if ($heroSmsApiKey) {
                    $heroSMS = new HeroSMS($heroSmsApiKey, HEROSMS_BASE_URL);
                    $heroSMS->cancelNumber($order['hero_order_id']);
                }
            }
            // 更新订单状态为过期（不退积分）
            $db->query("UPDATE orders SET status = 'expired', cancelled_at = NOW() WHERE id = ?", [$orderId]);
            $success = '订单已设为过期（积分未退还）';
        } else {
            $error = '当前状态无法取消';
        }
    }
}

$order = $db->query(
    "SELECT o.*, u.username, u.device_id, u.email, u.balance, s.name as service_name, c.name as country_name
     FROM orders o 
     LEFT JOIN users u ON o.user_id = u.id 
     LEFT JOIN services s ON o.service_id = s.id
     LEFT JOIN countries c ON o.country_id = c.id
     WHERE o.id = ?",
    [$orderId]
)->fetch();

if (!$order) {
    echo '<p class="text-danger">订单不存在</p>';
    return;
}

// 获取短信记录
$smsMessages = $db->query(
    "SELECT * FROM sms_messages WHERE order_id = ? ORDER BY received_at DESC",
    [$orderId]
)->fetchAll();

$statusMap = [
    'pending' => ['未使用', 'warning'],
    'active' => ['等待短信', 'primary'],
    'completed' => ['已完成', 'success'],
    'cancelled' => ['已取消', 'secondary'],
    'expired' => ['已过期', 'danger'],
    'refunded' => ['已退款', 'info']
];
$statusInfo = $statusMap[$order['status']] ?? [$order['status'], 'secondary'];
?>

<?php if(isset($success)): ?>
<div class="alert alert-success" style="background: #d1fae5; color: #065f46; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px;">
    ✅ <?= htmlspecialchars($success) ?>
</div>
<?php endif; ?>

<?php if(isset($error)): ?>
<div class="alert alert-danger" style="background: #fee2e2; color: #991b1b; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px;">
    ❌ <?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
    <h4 style="margin:0;">📋 订单详情</h4>
    <a href="?page=orders" class="btn" style="background:#e2e8f0;color:#64748b;">← 返回列表</a>
</div>

<div class="card" style="margin-bottom:20px;">
    <div style="padding:20px;border-bottom:1px solid #e2e8f0;">
        <h5 style="margin:0;">订单信息</h5>
    </div>
    <div style="padding:20px;">
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:20px;">
            <div>
                <p style="margin:0 0 4px 0;color:#64748b;font-size:13px;">订单号</p>
                <p style="margin:0;font-weight:600;font-size:14px;"><?= htmlspecialchars($order['id']) ?></p>
            </div>
            <div>
                <p style="margin:0 0 4px 0;color:#64748b;font-size:13px;">服务</p>
                <p style="margin:0;font-weight:600;"><?= htmlspecialchars($order['service_name'] ?? '-') ?></p>
            </div>
            <div>
                <p style="margin:0 0 4px 0;color:#64748b;font-size:13px;">国家</p>
                <p style="margin:0;font-weight:600;"><?= htmlspecialchars($order['country_name'] ?? '-') ?></p>
            </div>
            <div>
                <p style="margin:0 0 4px 0;color:#64748b;font-size:13px;">号码</p>
                <p style="margin:0;font-weight:600;"><?= htmlspecialchars($order['phone_number'] ?? '-') ?></p>
            </div>
            <div>
                <p style="margin:0 0 4px 0;color:#64748b;font-size:13px;">价格</p>
                <p style="margin:0;font-weight:600;color:#6366f1;"><?= $order['total_price'] ?> 积分</p>
            </div>
            <div>
                <p style="margin:0 0 4px 0;color:#64748b;font-size:13px;">状态</p>
                <p style="margin:0;"><span class="badge badge-<?= $statusInfo[1] ?>"><?= $statusInfo[0] ?></span></p>
            </div>
            <div>
                <p style="margin:0 0 4px 0;color:#64748b;font-size:13px;">创建时间</p>
                <p style="margin:0;font-weight:600;"><?= $order['created_at'] ?></p>
            </div>
            <div>
                <p style="margin:0 0 4px 0;color:#64748b;font-size:13px;">过期时间</p>
                <p style="margin:0;font-weight:600;"><?= $order['expires_at'] ?? '-' ?></p>
            </div>
            <div>
                <p style="margin:0 0 4px 0;color:#64748b;font-size:13px;">完成时间</p>
                <p style="margin:0;font-weight:600;"><?= $order['completed_at'] ?? '-' ?></p>
            </div>
        </div>
    </div>
</div>

<div class="card" style="margin-bottom:20px;">
    <div style="padding:20px;border-bottom:1px solid #e2e8f0;">
        <h5 style="margin:0;">用户信息</h5>
    </div>
    <div style="padding:20px;">
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:20px;">
            <div>
                <p style="margin:0 0 4px 0;color:#64748b;font-size:13px;">用户ID</p>
                <p style="margin:0;font-weight:600;font-size:12px;"><?= htmlspecialchars(substr($order['user_id'], 0, 16)) ?>...</p>
            </div>
            <div>
                <p style="margin:0 0 4px 0;color:#64748b;font-size:13px;">用户名</p>
                <p style="margin:0;font-weight:600;"><?= htmlspecialchars($order['username'] ?? '-') ?></p>
            </div>
            <div>
                <p style="margin:0 0 4px 0;color:#64748b;font-size:13px;">当前余额</p>
                <p style="margin:0;font-weight:600;color:#6366f1;"><?= $order['balance'] ?? '-' ?> 积分</p>
            </div>
        </div>
    </div>
</div>

<div class="card" style="margin-bottom:20px;">
    <div style="padding:20px;border-bottom:1px solid #e2e8f0;">
        <h5 style="margin:0;">HeroSMS 信息</h5>
    </div>
    <div style="padding:20px;">
        <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:20px;">
            <div>
                <p style="margin:0 0 4px 0;color:#64748b;font-size:13px;">Hero订单ID</p>
                <p style="margin:0;font-weight:600;"><?= htmlspecialchars($order['hero_order_id'] ?? '-') ?></p>
            </div>
            <div>
                <p style="margin:0 0 4px 0;color:#64748b;font-size:13px;">Hero状态</p>
                <p style="margin:0;font-weight:600;"><?= htmlspecialchars($order['hero_status'] ?? '-') ?></p>
            </div>
        </div>
    </div>
</div>

<?php if(!empty($smsMessages)): ?>
<div class="card" style="margin-bottom:20px;">
    <div style="padding:20px;border-bottom:1px solid #e2e8f0;">
        <h5 style="margin:0;">短信记录 (<?= count($smsMessages) ?>)</h5>
    </div>
    <div style="padding:20px;">
        <table>
            <thead>
                <tr>
                    <th>时间</th>
                    <th>发送者</th>
                    <th>验证码</th>
                    <th>内容</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($smsMessages as $sms): ?>
                <tr>
                    <td><small><?= $sms['received_at'] ?></small></td>
                    <td><?= htmlspecialchars($sms['sender'] ?? '-') ?></td>
                    <td><strong style="color:#10b981;"><?= htmlspecialchars($sms['code'] ?? '-') ?></strong></td>
                    <td><small><?= htmlspecialchars($sms['content']) ?></small></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div style="padding:20px;border-bottom:1px solid #e2e8f0;">
        <h5 style="margin:0;">🔧 管理员操作</h5>
    </div>
    <div style="padding:20px;">
        <div style="display:flex;gap:12px;flex-wrap:wrap;">
            <?php if($order['status'] === 'pending' || $order['status'] === 'active'): ?>
            <form method="POST" onsubmit="return confirm('确定取消此订单？积分不会退还给用户。')">
                <input type="hidden" name="admin_action" value="cancel">
                <button type="submit" class="btn" style="background:#f59e0b;color:white;">⚠️ 取消订单（不退积分）</button>
            </form>
            <?php endif; ?>
            
            <?php if($order['status'] === 'active' || $order['status'] === 'completed' || $order['status'] === 'expired'): ?>
            <form method="POST" onsubmit="return confirm('确定退款？将退还 <?= $order['total_price'] ?> 积分给用户。')">
                <input type="hidden" name="admin_action" value="refund">
                <button type="submit" class="btn" style="background:#ef4444;color:white;">💰 退款（退还 <?= $order['total_price'] ?> 积分）</button>
            </form>
            <?php endif; ?>
        </div>
        <p style="margin-top:16px;color:#64748b;font-size:13px;">
            💡 提示：取消订单不会退还积分，退款会将积分退还给用户余额
        </p>
    </div>
</div>