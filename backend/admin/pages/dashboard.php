<?php
/**
 * 数据概览页面
 */

require_once __DIR__ . '/../../lib/HeroSMS.php';
require_once __DIR__ . '/../../lib/KeyManager.php';

$today = date('Y-m-d');

$totalUsers = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$newUsersToday = $db->query("SELECT COUNT(*) FROM users WHERE DATE(created_at) = ?", [$today])->fetchColumn();
$totalUserBalance = $db->query("SELECT COALESCE(SUM(balance), 0) FROM users")->fetchColumn();
$totalPaidUsers = $db->query("SELECT COUNT(*) FROM users WHERE total_spent > 0")->fetchColumn();
try {
    $paidUsersToday = $db->query("SELECT COUNT(DISTINCT user_id) FROM payment_records WHERE DATE(created_at) = ?", [$today])->fetchColumn();
} catch (Exception $e) {
    $paidUsersToday = 0;
}

$totalOrders = $db->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$completedOrders = $db->query("SELECT COUNT(*) FROM orders WHERE status = 'completed'")->fetchColumn();
$activeOrders = $db->query("SELECT COUNT(*) FROM orders WHERE status = 'active'")->fetchColumn();
$todayOrders = $db->query("SELECT COUNT(*) FROM orders WHERE DATE(created_at) = ?", [$today])->fetchColumn();

// 总收入（美元）从支付记录计算
$totalRevenueUSD = $db->query("SELECT COALESCE(SUM(amount), 0) FROM payment_records")->fetchColumn();
// 今日消耗积分从订单计算（total_price 存的是积分）
$todaySpentPoints = $db->query("SELECT COALESCE(SUM(total_price), 0) FROM orders WHERE DATE(created_at) = ?", [$today])->fetchColumn();

$heroSmsApiKey = KeyManager::getHeroSmsApiKey();
$heroSMS = $heroSmsApiKey ? new HeroSMS($heroSmsApiKey, HEROSMS_BASE_URL) : null;
$heroBalance = $heroSMS ? $heroSMS->getBalance() : ['success' => false, 'message' => 'API key not configured'];

$totalServices = $db->query("SELECT COUNT(*) FROM services")->fetchColumn();
$publishedServices = $db->query("SELECT COUNT(*) FROM services WHERE is_published = 1")->fetchColumn();
$totalCountries = $db->query("SELECT COUNT(*) FROM countries")->fetchColumn();
$priceRecords = $db->query("SELECT COUNT(*) FROM service_countries")->fetchColumn();

$recentOrders = $db->query(
    "SELECT o.*, u.username FROM orders o
     LEFT JOIN users u ON o.user_id = u.id
     ORDER BY o.created_at DESC LIMIT 10"
)->fetchAll();
?>

<div class="top-bar">
    <h4>📊 数据概览</h4>
    <div class="top-bar-right">
        <span class="time"><?= date('Y-m-d H:i:s') ?></span>
        <div class="admin-info">
            <div class="admin-avatar"><?= substr($admin['username'] ?? 'A', 0, 1) ?></div>
            <span class="admin-name"><?= htmlspecialchars($admin['username'] ?? 'Admin') ?></span>
        </div>
    </div>
</div>

<div class="stat-row">
    <div class="stat-card">
        <div class="stat-icon users">👥</div>
        <h3><?= number_format($totalUsers) ?></h3>
        <p>总用户数</p>
    </div>
    <div class="stat-card">
        <div class="stat-icon orders">🆕</div>
        <h3><?= number_format($newUsersToday) ?></h3>
        <p>今日新增</p>
    </div>
    <div class="stat-card">
        <div class="stat-icon balance">💎</div>
        <h3><?= number_format($totalUserBalance, 0) ?></h3>
        <p>用户总积分</p>
    </div>
    <div class="stat-card">
        <div class="stat-icon revenue">💰</div>
        <h3><?= number_format($todaySpentPoints, 0) ?></h3>
        <p>今日消耗积分</p>
    </div>
</div>

<div class="stat-row">
    <div class="stat-card">
        <div class="stat-icon users">👤</div>
        <h3><?= number_format($totalPaidUsers) ?></h3>
        <p>总付费人数</p>
    </div>
    <div class="stat-card">
        <div class="stat-icon orders">💳</div>
        <h3><?= number_format($paidUsersToday) ?></h3>
        <p>今日付费人数</p>
    </div>
    <div class="stat-card">
        <div class="stat-icon balance">📱</div>
        <h3 class="<?= $heroBalance['success'] && $heroBalance['balance'] < 5 ? 'text-danger' : 'text-success' ?>">
            $<?= $heroBalance['success'] ? number_format($heroBalance['balance'], 2) : 'N/A' ?>
        </h3>
        <p>HeroSMS 余额</p>
    </div>
    <div class="stat-card">
        <div class="stat-icon orders">🛒</div>
        <h3><?= number_format($totalOrders) ?></h3>
        <p>订单总数</p>
    </div>
</div>

<div class="stat-row">
    <div class="stat-card">
        <div class="stat-icon orders">✓</div>
        <h3 class="text-success"><?= number_format($completedOrders) ?></h3>
        <p>成功接收验证码</p>
    </div>
    <div class="stat-card">
        <div class="stat-icon orders">⏳</div>
        <h3 class="text-primary"><?= number_format($activeOrders) ?></h3>
        <p>进行中订单</p>
    </div>
    <div class="stat-card">
        <div class="stat-icon users">🌍</div>
        <h3><?= number_format($totalCountries) ?></h3>
        <p>国家总数</p>
    </div>
    <div class="stat-card">
        <div class="stat-icon revenue">💎</div>
        <h3><?= number_format($priceRecords) ?></h3>
        <p>价格配置</p>
    </div>
</div>

<?php if($totalServices > 0): ?>
<div style="margin-top:20px;padding:16px;background:#fef3c7;border:1px solid #fde68a;border-radius:12px;">
    <div style="font-weight:600;color:#92400e;margin-bottom:12px;">📌 待处理事项</div>
    <div style="display:flex;gap:24px;">
        <div>
            <span style="font-size:24px;font-weight:700;color:#b45309;"><?= $totalServices - $publishedServices ?></span>
            <span style="color:#78350f;"> 个服务待上架</span>
        </div>
        <div>
            <a href="?page=services" style="color:#6366f1;font-weight:600;text-decoration:none;">去上架 →</a>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h5>📋 最近订单</h5>
        <a href="?page=orders" class="btn btn-primary btn-sm">查看全部</a>
    </div>
    <div class="table-responsive">
    <table>
        <thead>
            <tr>
                <th>订单号</th>
                <th>用户</th>
                <th>服务</th>
                <th>国家</th>
                <th>号码</th>
                <th>价格</th>
                <th>状态</th>
                <th>时间</th>
            </tr>
        </thead>
        <tbody>
            <?php if(empty($recentOrders)): ?>
            <tr>
                <td colspan="8" style="text-align:center;color:#64748b;padding:40px;">暂无订单数据</td>
            </tr>
            <?php else: ?>
            <?php foreach($recentOrders as $order): ?>
            <tr>
                <td><code style="background:#f1f5f9;padding:2px 6px;border-radius:4px;font-size:12px;"><?= substr($order['id'], 0, 12) ?>...</code></td>
                <td><?= htmlspecialchars($order['username'] ?? substr($order['user_id'], 0, 8)) ?></td>
                <td><?= htmlspecialchars($order['service_name'] ?? '-') ?></td>
                <td><?= htmlspecialchars($order['country_name'] ?? '-') ?></td>
                <td><code style="background:#f1f5f9;padding:2px 6px;border-radius:4px;font-size:12px;"><?= htmlspecialchars($order['phone_number'] ?? '-') ?></code></td>
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
                    $status = $statusMap[$order['status']] ?? [$order['status'], 'badge-secondary'];
                    ?>
                    <span class="badge <?= $status[1] ?>"><?= $status[0] ?></span>
                </td>
                <td><span style="color:#64748b;font-size:13px;"><?= date('m-d H:i', strtotime($order['created_at'])) ?></span></td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    </div>
</div>

<script>
// Dashboard auto-refresh every 60 seconds
setTimeout(function() {
    location.reload();
}, 60000);
</script>