<?php
/**
 * SMS 接码平台 - 运营后台
 */

// 加载统一 session 配置（必须放在最前面）
require_once __DIR__ . '/../config/session_config.php';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lib/Database.php';

$db = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT);

if (basename($_SERVER['REQUEST_URI']) === 'login.php' || basename($_SERVER['SCRIPT_NAME']) === 'login.php') {
    include __DIR__ . '/login.php';
    exit;
}

$adminId = $_SESSION['admin_id'] ?? null;

if (!$adminId) {
    header('Location: login.php');
    exit;
}

$admin = $db->query("SELECT * FROM admins WHERE id = ?", [$adminId])->fetch();

$page = $_GET['page'] ?? 'dashboard';
$action = $_GET['action'] ?? null;

// 处理 POST 请求（在输出任何 HTML 之前）
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postPage = $_GET['page'] ?? null;
    if ($postPage) {
        $pageFile = __DIR__ . "/pages/{$postPage}.php";
        if (file_exists($pageFile)) {
            require_once $pageFile;
            exit;
        }
    }
}

// 如果是 AJAX 请求，直接处理页面逻辑并退出
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    $pageFile = __DIR__ . "/pages/{$page}.php";
    if (file_exists($pageFile)) {
        require_once $pageFile;
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMS Manager - 运营后台</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --secondary: #64748b;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --light: #f1f5f9;
            --dark: #0f172a;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--light);
            color: #333;
            line-height: 1.6;
        }
        .layout { display: flex; min-height: 100vh; }
        .sidebar {
            width: 240px;
            background: linear-gradient(180deg, #1e1b4b 0%, #312e81 100%);
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }
        .sidebar-brand {
            padding: 24px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .sidebar-brand h4 { margin: 0 0 4px 0; font-size: 18px; font-weight: 700; }
        .sidebar-brand span { font-size: 12px; opacity: 0.7; }
        .sidebar-nav { padding: 16px 0; }
        .sidebar a {
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            padding: 12px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-radius: 8px;
            margin: 4px 12px;
            transition: all 0.2s;
            font-size: 14px;
            font-weight: 500;
        }
        .sidebar a:hover { background: rgba(255,255,255,0.15); color: white; }
        .sidebar a.active { background: var(--primary); color: white; }
        .sidebar a .icon { font-size: 18px; width: 24px; text-align: center; }
        .sidebar-footer { position: absolute; bottom: 0; width: 100%; padding: 16px 12px; border-top: 1px solid rgba(255,255,255,0.1); }
        .sidebar-footer a { color: rgba(255,255,255,0.5); margin: 0; }
        .sidebar-footer a:hover { color: white; background: transparent; }
        .main { margin-left: 240px; flex: 1; padding: 24px; }
        .top-bar {
            background: white;
            border-radius: 12px;
            padding: 16px 24px;
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .top-bar h4 { margin: 0; font-weight: 600; color: var(--dark); font-size: 18px; }
        .top-bar-right { display: flex; align-items: center; gap: 16px; }
        .top-bar .time { color: var(--secondary); font-size: 14px; }
        .admin-info { display: flex; align-items: center; gap: 10px; }
        .admin-avatar {
            width: 36px; height: 36px; border-radius: 50%;
            background: var(--primary); color: white;
            display: flex; align-items: center; justify-content: center;
            font-weight: 600; font-size: 14px;
        }
        .admin-name { font-weight: 500; }
        .stat-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 24px; }
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            border: 1px solid rgba(0,0,0,0.03);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0,0,0,0.1); }
        .stat-icon {
            width: 48px; height: 48px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 24px; margin-bottom: 16px;
        }
        .stat-icon.users { background: #e0e7ff; color: #6366f1; }
        .stat-icon.orders { background: #d1fae5; color: #10b981; }
        .stat-icon.revenue { background: #fef3c7; color: #f59e0b; }
        .stat-icon.balance { background: #fee2e2; color: #ef4444; }
        .stat-card h3 { margin: 0 0 4px 0; font-size: 28px; font-weight: 700; color: var(--dark); }
        .stat-card p { margin: 0; color: var(--secondary); font-size: 14px; }
        .card {
            background: white;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            border: 1px solid rgba(0,0,0,0.03);
            margin-bottom: 24px;
        }
        .card-header { display: flex; justify-content: space-between; align-items: center; padding-bottom: 16px; border-bottom: 1px solid #e2e8f0; margin-bottom: 16px; }
        .card-header h5 { margin: 0; font-weight: 600; color: var(--dark); font-size: 16px; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 12px 16px; color: var(--secondary); font-weight: 500; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid #e2e8f0; }
        td { padding: 16px; border-bottom: 1px solid #f1f5f9; font-size: 14px; vertical-align: middle; }
        tr:hover { background: #f8fafc; }
        .badge {
            padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600;
            display: inline-block;
        }
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-info { background: #dbeafe; color: #1e40af; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-secondary { background: #f3f4f6; color: #6b7280; }
        .btn {
            padding: 8px 16px; border-radius: 8px; border: none; cursor: pointer;
            font-size: 14px; font-weight: 500; text-decoration: none; display: inline-block;
            transition: all 0.2s;
        }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-dark); }
        .btn-sm { padding: 4px 10px; font-size: 12px; }
        .text-success { color: #10b981; }
        .text-danger { color: #ef4444; }
        .text-primary { color: #6366f1; }
        .text-muted { color: #64748b; }
        .row { display: flex; gap: 20px; }
        .col-4 { flex: 1; }
        .table-responsive { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
        @media (max-width: 768px) {
            .sidebar { width: 100%; position: relative; height: auto; z-index: 100; }
            .sidebar-nav { display: flex; flex-wrap: wrap; padding: 10px; }
            .sidebar a { margin: 4px; padding: 8px 12px; font-size: 13px; flex: 1 1 calc(50% - 10px); min-width: 120px; }
            .sidebar-footer { position: relative; border-top: 1px solid rgba(255,255,255,0.1); padding: 10px; }
            .main { margin-left: 0; padding: 16px; }
            .stat-row { grid-template-columns: 1fr 1fr; gap: 12px; }
            .stat-card { padding: 16px; }
            .stat-card h3 { font-size: 20px; }
            .row { flex-direction: column; }
            .top-bar { flex-direction: column; align-items: flex-start; gap: 12px; }
        }
        @media (max-width: 480px) {
            .stat-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="layout">
        <aside class="sidebar">
            <div class="sidebar-brand">
                <h4>📱 SMS Manager</h4>
                <span>运营控制台</span>
            </div>
            <nav class="sidebar-nav">
                <a href="?page=dashboard" class="<?= $page=='dashboard'?'active':'' ?>">
                    <span class="icon">📊</span>数据概览
                </a>
                <a href="?page=orders" class="<?= $page=='orders'?'active':'' ?>">
                    <span class="icon">📋</span>订单管理
                </a>
                <a href="?page=credits" class="<?= $page=='credits'?'active':'' ?>">
                    <span class="icon">💰</span>积分记录
                </a>
                <a href="?page=users" class="<?= $page=='users'?'active':'' ?>">
                    <span class="icon">👥</span>用户管理
                </a>
                <a href="?page=services" class="<?= $page=='services'?'active':'' ?>">
                    <span class="icon">⚙️</span>服务配置
                </a>
                <a href="?page=countries" class="<?= $page=='countries'?'active':'' ?>">
                    <span class="icon">🌍</span>国家管理
                </a>
                <a href="?page=banners" class="<?= $page=='banners'?'active':'' ?>">
                    <span class="icon">🎨</span>Banner管理
                </a>
                <a href="?page=payments" class="<?= $page=='payments'?'active':'' ?>">
                    <span class="icon">💳</span>支付记录
                </a>
                <a href="?page=packages" class="<?= $page=='packages'?'active':'' ?>">
                    <span class="icon">💎</span>充值套餐
                </a>
                <a href="?page=settings" class="<?= $page=='settings'?'active':'' ?>">
                    <span class="icon">🔧</span>系统设置
                </a>
                <a href="?page=coefficients" class="<?= $page=='coefficients'?'active':'' ?>">
                    <span class="icon">📊</span>价格系数
                </a>
            </nav>
            <div class="sidebar-footer">
                <a href="?page=logout">
                    <span class="icon">🚪</span>退出登录
                </a>
            </div>
        </aside>
        <main class="main">
            <?php
            switch($page) {
                case 'dashboard': include 'pages/dashboard.php'; break;
                case 'orders': include 'pages/orders.php'; break;
                case 'credits': include 'pages/credits.php'; break;
                case 'users': include 'pages/users.php'; break;
                case 'services': include 'pages/services.php'; break;
                case 'countries': include 'pages/countries.php'; break;
                case 'service_countries': include 'pages/service_countries.php'; break;
                case 'banners': include 'pages/banners.php'; break;
                case 'payments': include 'pages/payments.php'; break;
                case 'packages': include 'pages/packages.php'; break;
                case 'settings': include 'pages/settings.php'; break;
                case 'coefficients': include 'pages/coefficients.php'; break;
                case 'logout':
                    session_destroy();
                    header('Location: login.php');
                    exit;
                default: include 'pages/dashboard.php';
            }
            ?>
        </main>
    </div>
</body>
</html>
