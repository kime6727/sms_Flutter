<?php
/**
 * 运营后台 - 登录页面
 */

// 加载统一 session 配置（必须放在最前面）
require_once __DIR__ . '/../config/session_config.php';

// 加载配置
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lib/Database.php';

$error = '';
$success = '';

try {
    $db = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT);

    // 检查是否需要创建默认管理员
    $adminCount = 0;
    try {
        $adminCount = $db->query("SELECT COUNT(*) FROM admins")->fetchColumn();
    } catch (Exception $e) {
        // 表可能不存在
    }

    if ($adminCount == 0) {
        $defaultPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $db->insert('admins', [
            'id' => 'admin_001',
            'username' => 'admin',
            'password' => $defaultPassword,
            'email' => 'admin@example.com',
            'role' => 'admin',
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s')
        ]);
        $success = '默认管理员账号已创建: admin / admin123';
    }

    // 处理登录
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $error = '请输入用户名和密码';
        } else {
            $admin = $db->query(
                "SELECT * FROM admins WHERE username = ? AND status = 'active'",
                [$username]
            )->fetch();

            if ($admin && password_verify($password, $admin['password'])) {
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['admin_role'] = $admin['role'];
                $db->query(
                    "UPDATE admins SET last_login = NOW(), login_ip = ? WHERE id = ?",
                    [$_SERVER['REMOTE_ADDR'] ?? '', $admin['id']]
                );
                // 确保 session 数据写入
                session_write_close();
                // 使用绝对路径跳转
                $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                header("Location: $protocol://$host/admin/index.php");
                exit;
            } else {
                $error = '用户名或密码错误';
            }
        }
    }
} catch (Exception $e) {
    $error = '系统错误，请稍后重试';
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登录 - 运营后台</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            background: white;
            border-radius: 16px;
            padding: 40px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            margin: 20px;
        }
        .login-icon {
            width: 60px;
            height: 60px;
            background: #4f46e5;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 28px;
        }
        h4 { text-align: center; margin-bottom: 24px; color: #333; }
        .mb-3 { margin-bottom: 16px; }
        .form-label { display: block; margin-bottom: 6px; color: #555; font-weight: 500; }
        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.2s;
        }
        .form-control:focus {
            outline: none;
            border-color: #4f46e5;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }
        .btn-primary {
            width: 100%;
            padding: 14px;
            background: #4f46e5;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-primary:hover { background: #4338ca; }
        .text-center { text-align: center; }
        .text-muted { color: #888; font-size: 14px; }
        .mt-3 { margin-top: 16px; }
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-size: 14px;
        }
        .alert-error { background: #fee2e2; color: #dc2626; }
        .alert-success { background: #d1fae5; color: #059669; }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-icon">🔐</div>
        <h4>运营后台</h4>

        <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label">用户名</label>
                <input type="text" name="username" class="form-control" required autofocus placeholder="请输入用户名">
            </div>
            <div class="mb-3">
                <label class="form-label">密码</label>
                <input type="password" name="password" class="form-control" required placeholder="请输入密码">
            </div>
            <button type="submit" class="btn-primary">登录</button>
        </form>

        <div class="text-center mt-3 text-muted">
            <small>默认账号: admin / admin123</small>
        </div>
    </div>
</body>
</html>