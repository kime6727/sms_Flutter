<?php
/**
 * 用户管理页面
 */

// 自愈: credit_transactions.balance_before / balance_after NOT NULL default 0.00
// (避免 INSERT 缺 balance_before/after 报 1364 错)
static $creditTxSchemaFixed = false;
if (!$creditTxSchemaFixed) {
    try {
        if (!isset($db)) {
            require_once __DIR__ . '/../../config/database.php';
            require_once __DIR__ . '/../../lib/Database.php';
            $db = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT);
        }
        foreach (['balance_before', 'balance_after'] as $f) {
            $col = $db->query("SHOW COLUMNS FROM credit_transactions WHERE Field = ?", [$f])->fetch();
            if ($col && $col['Null'] === 'NO' && ($col['Default'] === null || $col['Default'] === 'NULL' || $col['Default'] === '')) {
                $db->query("ALTER TABLE credit_transactions MODIFY COLUMN $f DECIMAL(10,2) NOT NULL DEFAULT 0.00");
                error_log("[users.php schema fix] credit_transactions.$f DEFAULT 0.00");
            }
        }
        $creditTxSchemaFixed = true;
    } catch (Throwable $e) {
        error_log('[users.php schema fix] failed: ' . $e->getMessage());
    }
}

// 处理重置密码操作
if (($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reset_password') || (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && ($_POST['action'] ?? '') === 'reset_password')) {
    $userId = $_POST['user_id'] ?? null;
    if ($userId) {
        $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $lowercase = 'abcdefghijklmnopqrstuvwxyz';
        $numbers = '0123456789';
        
        $newPassword = '';
        $newPassword .= $uppercase[rand(0, strlen($uppercase) - 1)];
        $newPassword .= $lowercase[rand(0, strlen($lowercase) - 1)];
        $newPassword .= $lowercase[rand(0, strlen($lowercase) - 1)];
        $newPassword .= $numbers[rand(0, strlen($numbers) - 1)];
        $newPassword .= $numbers[rand(0, strlen($numbers) - 1)];
        $newPassword .= $numbers[rand(0, strlen($numbers) - 1)];
        $newPassword .= $numbers[rand(0, strlen($numbers) - 1)];
        $newPassword .= $numbers[rand(0, strlen($numbers) - 1)];
        $newPassword = str_shuffle($newPassword);
        
        $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT);
        try {
            $db->query("UPDATE users SET password_hash = ? WHERE id = ?", [$passwordHash, $userId]);
        } catch (Throwable $e) {
            error_log('[users.php reset_password] ' . $e->getMessage());
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => '重置失败：' . $e->getMessage()]);
                exit;
            }
            header("Location: ?page=users&error=" . urlencode("重置失败：" . $e->getMessage()));
            exit;
        }

        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'password' => $newPassword]);
            exit;
        }
        header("Location: ?page=users&msg=" . urlencode("密码已重置，新密码: " . $newPassword));
        exit;
    }
}

// 处理修改邮箱操作
if (($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_email') || (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && ($_POST['action'] ?? '') === 'update_email')) {
    $userId = $_POST['user_id'] ?? null;
    $email = $_POST['email'] ?? null;
    
    if ($userId) {
        if ($email) {
            $existing = $db->query("SELECT id FROM users WHERE email = ? AND id != ?", [$email, $userId])->fetch();
            if ($existing) {
                if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'error' => '邮箱已被其他用户使用']);
                    exit;
                }
                header("Location: ?page=users&error=" . urlencode("邮箱已被其他用户使用"));
                exit;
            }
        }
        
        try {
            $db->query("UPDATE users SET email = ? WHERE id = ?", [$email ?: null, $userId]);
        } catch (Throwable $e) {
            error_log('[users.php update_email] ' . $e->getMessage());
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => '邮箱更新失败：' . $e->getMessage()]);
                exit;
            }
            header("Location: ?page=users&error=" . urlencode("邮箱更新失败：" . $e->getMessage()));
            exit;
        }
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            exit;
        }
        header("Location: ?page=users&msg=" . urlencode("邮箱已更新"));
        exit;
    }
}

// 处理修改备注操作
if (($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_notes') || (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && ($_POST['action'] ?? '') === 'update_notes')) {
    $userId = $_POST['user_id'] ?? null;
    $notes = $_POST['notes'] ?? null;
    
    if ($userId) {
        try {
            $db->query("UPDATE users SET notes = ? WHERE id = ?", [$notes, $userId]);
        } catch (Throwable $e) {
            error_log('[users.php update_notes] ' . $e->getMessage());
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => '备注更新失败：' . $e->getMessage()]);
                exit;
            }
            header("Location: ?page=users&error=" . urlencode("备注更新失败：" . $e->getMessage()));
            exit;
        }
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            exit;
        }
        header("Location: ?page=users&msg=" . urlencode("备注已更新"));
        exit;
    }
}

// 处理创建新用户操作
if (($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_user') || (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && ($_POST['action'] ?? '') === 'create_user')) {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (!$email) {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => '邮箱不能为空']);
            exit;
        }
        header("Location: ?page=users&error=" . urlencode("邮箱不能为空"));
        exit;
    }
    
    if (!$password || strlen($password) < 6) {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => '密码长度至少6位']);
            exit;
        }
        header("Location: ?page=users&error=" . urlencode("密码长度至少6位"));
        exit;
    }
    
    $existing = $db->query("SELECT id FROM users WHERE email = ?", [$email])->fetch();
    if ($existing) {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => '邮箱已被使用']);
            exit;
        }
        header("Location: ?page=users&error=" . urlencode("邮箱已被使用"));
        exit;
    }
    
    $userId = 'user_' . bin2hex(random_bytes(8));
    
    $maxAttempts = 10;
    $username = null;
    for ($i = 0; $i < $maxAttempts; $i++) {
        $randomNumber = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $tempUsername = 'user_' . $randomNumber;
        
        $existingUsername = $db->query("SELECT id FROM users WHERE username = ?", [$tempUsername])->fetch();
        if (!$existingUsername) {
            $username = $tempUsername;
            break;
        }
    }
    
    if (!$username) {
        $username = 'user_' . substr(time(), -6);
    }
    
    $passwordHash = password_hash($password, PASSWORD_BCRYPT);
    
    $bonusMin = intval($db->query("SELECT value FROM system_settings WHERE `key` = ?", ['register_bonus_min'])->fetchColumn() ?: '5');
    $bonusMax = intval($db->query("SELECT value FROM system_settings WHERE `key` = ?", ['register_bonus_max'])->fetchColumn() ?: '20');

    if ($bonusMin > $bonusMax) {
        $bonusMin = $bonusMax;
    }

    $bonusCredits = $bonusMin === $bonusMax ? $bonusMin : rand($bonusMin, $bonusMax);

    try {
        $db->insert('users', [
            'id' => $userId,
            'device_id' => 'admin_' . bin2hex(random_bytes(8)),
            'username' => $username,
            'email' => $email,
            'password_hash' => $passwordHash,
            'balance' => $bonusCredits,
            'total_spent' => 0,
            'order_count' => 0,
            'status' => 'active',
            'role' => 'user',
            'created_at' => date('Y-m-d H:i:s'),
            'register_ip' => $_SERVER['REMOTE_ADDR'] ?? 'admin'
        ]);

        if ($bonusCredits > 0) {
            $db->insert('credit_transactions', [
                'id' => 'txn_' . bin2hex(random_bytes(8)),
                'user_id' => $userId,
                'type' => 'bonus',
                'amount' => $bonusCredits,
                'balance_before' => 0,
                'balance_after' => $bonusCredits,
                'description' => '运营后台创建用户赠送',
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
    } catch (Throwable $e) {
        error_log('[users.php create_user] ' . $e->getMessage());
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => '创建失败：' . $e->getMessage()]);
            exit;
        }
        header("Location: ?page=users&error=" . urlencode("创建失败：" . $e->getMessage()));
        exit;
    }
    
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'username' => $username, 'password' => $password, 'credits' => $bonusCredits]);
        exit;
    }
    header("Location: ?page=users&msg=" . urlencode("用户创建成功！用户名: {$username}, 密码: {$password}, 初始积分: {$bonusCredits}"));
    exit;
}

// 处理加积分操作
if (($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_balance') || (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && ($_POST['action'] ?? '') === 'add_balance')) {
    $userId = $_POST['user_id'] ?? null;
    $amount = intval($_POST['amount'] ?? 0);
    if ($userId && $amount != 0) {
        try {
            // 先查当前余额,用于流水记录
            $currentUser = $db->query("SELECT balance FROM users WHERE id = ?", [$userId])->fetch();
            if (!$currentUser) {
                if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'error' => '用户不存在']);
                    exit;
                }
                header("Location: ?page=users&error=" . urlencode("用户不存在"));
                exit;
            }
            $balanceBefore = intval($currentUser['balance']);
            $balanceAfter = $balanceBefore + $amount;

            $db->query("UPDATE users SET balance = balance + ? WHERE id = ?", [$amount, $userId]);

            // 记录积分流水(运营后台手动调整)
            $db->insert('credit_transactions', [
                'id' => 'txn_' . bin2hex(random_bytes(8)),
                'user_id' => $userId,
                'type' => $amount > 0 ? 'bonus' : 'refund',
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'description' => '运营后台手动调整: ' . ($amount > 0 ? '+' : '') . $amount,
                'created_at' => date('Y-m-d H:i:s')
            ]);
        } catch (Throwable $e) {
            error_log('[users.php add_balance] ' . $e->getMessage());
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => '积分更新失败：' . $e->getMessage()]);
                exit;
            }
            header("Location: ?page=users&error=" . urlencode("积分更新失败：" . $e->getMessage()));
            exit;
        }
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            exit;
        }
        header("Location: ?page=users&msg=" . urlencode("积分已更新"));
        exit;
    }
}

// 处理封禁/解封操作
if (($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_status') || (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && ($_POST['action'] ?? '') === 'toggle_status')) {
    $userId = $_POST['user_id'] ?? null;
    if ($userId) {
        $user = $db->query("SELECT status FROM users WHERE id = ?", [$userId])->fetch();
        if ($user) {
            $newStatus = $user['status'] === 'active' ? 'banned' : 'active';
            try {
                $db->query("UPDATE users SET status = ? WHERE id = ?", [$newStatus, $userId]);
            } catch (Throwable $e) {
                error_log('[users.php toggle_status] ' . $e->getMessage());
                if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'error' => '操作失败：' . $e->getMessage()]);
                    exit;
                }
                header("Location: ?page=users&error=" . urlencode("操作失败：" . $e->getMessage()));
                exit;
            }
            $msg = $newStatus === 'active' ? '用户已解封' : '用户已封禁';
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'newStatus' => $newStatus]);
                exit;
            }
            header("Location: ?page=users&msg=" . urlencode($msg));
            exit;
        }
    }
}

// 处理删除用户操作
if (($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_user') || (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && ($_POST['action'] ?? '') === 'delete_user')) {
    $userId = $_POST['user_id'] ?? null;
    if ($userId) {
        $user = $db->query("SELECT role FROM users WHERE id = ?", [$userId])->fetch();
        if ($user && $user['role'] === 'admin') {
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => '不能删除管理员账号']);
                exit;
            }
            header("Location: ?page=users&error=" . urlencode("不能删除管理员账号"));
            exit;
        }

        try {
            $db->query("DELETE FROM users WHERE id = ?", [$userId]);
        } catch (Throwable $e) {
            error_log('[users.php delete_user] ' . $e->getMessage());
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => '删除失败：' . $e->getMessage()]);
                exit;
            }
            header("Location: ?page=users&error=" . urlencode("删除失败：" . $e->getMessage()));
            exit;
        }

        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            exit;
        }
        header("Location: ?page=users&msg=" . urlencode("用户已删除"));
        exit;
    }
}

// 处理批量删除用户操作
if (($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'batch_delete') || (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && ($_POST['action'] ?? '') === 'batch_delete')) {
    $userIds = $_POST['user_ids'] ?? [];
    
    if (!is_array($userIds) || empty($userIds)) {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => '请选择要删除的用户']);
            exit;
        }
        header("Location: ?page=users&error=" . urlencode("请选择要删除的用户"));
        exit;
    }
    
    $deletedCount = 0;
    $skippedCount = 0;
    
    foreach ($userIds as $userId) {
        $user = $db->query("SELECT role FROM users WHERE id = ?", [$userId])->fetch();
        if ($user && $user['role'] === 'admin') {
            $skippedCount++;
            continue;
        }

        try {
            $db->query("DELETE FROM users WHERE id = ?", [$userId]);
            $deletedCount++;
        } catch (Throwable $e) {
            error_log('[users.php batch_delete] ' . $e->getMessage() . " user_id={$userId}");
            $skippedCount++;
        }
    }
    
    $message = "成功删除 {$deletedCount} 个用户";
    if ($skippedCount > 0) {
        $message .= "，跳过 {$skippedCount} 个管理员账号";
    }
    
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => $message, 'deleted' => $deletedCount, 'skipped' => $skippedCount]);
        exit;
    }
    header("Location: ?page=users&msg=" . urlencode($message));
    exit;
}

// 处理批量导出用户操作
if (($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'batch_export') || (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && ($_POST['action'] ?? '') === 'batch_export')) {
    $userIds = $_POST['user_ids'] ?? [];
    
    if (!is_array($userIds) || empty($userIds)) {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => '请选择要导出的用户']);
            exit;
        }
        header("Location: ?page=users&error=" . urlencode("请选择要导出的用户"));
        exit;
    }
    
    $placeholders = implode(',', array_fill(0, count($userIds), '?'));
    $users = $db->query(
        "SELECT id, username, email, device_id, balance, total_spent, order_count, status, created_at, last_login, register_ip 
         FROM users 
         WHERE id IN ($placeholders)",
        $userIds
    )->fetchAll();
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="users_export_' . date('YmdHis') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM
    
    fputcsv($output, ['用户ID', '用户名', '邮箱', '设备ID', '余额', '消费总额', '订单数', '状态', '注册时间', '最后登录', '注册IP']);
    
    foreach ($users as $user) {
        fputcsv($output, [
            $user['id'],
            $user['username'] ?? '',
            $user['email'] ?? '',
            $user['device_id'] ?? '',
            $user['balance'],
            $user['total_spent'],
            $user['order_count'],
            $user['status'] === 'active' ? '正常' : '禁用',
            $user['created_at'],
            $user['last_login'] ?? '',
            $user['register_ip'] ?? ''
        ]);
    }
    
    fclose($output);
    exit;
}

$search = $_GET['search'] ?? '';
$statusFilter = $_GET['status_filter'] ?? '';
$currentPage = intval($_GET['p'] ?? 1);
$limit = 20;
$offset = ($currentPage - 1) * $limit;

$where = "1=1";
$params = [];

if ($search) {
    $where .= " AND (id LIKE ? OR username LIKE ? OR device_id LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($statusFilter) {
    $where .= " AND status = ?";
    $params[] = $statusFilter;
}

$total = $db->query("SELECT COUNT(*) FROM users WHERE $where", $params)->fetchColumn();

$users = $db->query(
    "SELECT * FROM users WHERE $where ORDER BY created_at DESC LIMIT $limit OFFSET $offset",
    $params
)->fetchAll();

$totalPages = ceil($total / $limit);

$msg = $_GET['msg'] ?? '';
$error = $_GET['error'] ?? '';
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
    <h4 style="margin:0;">👥 用户管理</h4>
    <div style="display:flex;gap:12px;align-items:center;">
        <span style="color:#64748b;">共 <?= number_format($total) ?> 用户</span>
        <span id="selectedCount" style="color:#6366f1;font-weight:600;display:none;">已选择 <span id="selectedNum">0</span> 个</span>
        <button id="batchDeleteBtn" onclick="batchDelete()" style="background:#ef4444;color:white;border:none;padding:8px 16px;border-radius:8px;cursor:pointer;font-size:14px;font-weight:600;display:none;">🗑️ 批量删除</button>
        <button id="batchExportBtn" onclick="batchExport()" style="background:#8b5cf6;color:white;border:none;padding:8px 16px;border-radius:8px;cursor:pointer;font-size:14px;font-weight:600;display:none;">📥 批量导出</button>
        <button onclick="showCreateUserModal()" style="background:#10b981;color:white;border:none;padding:8px 16px;border-radius:8px;cursor:pointer;font-size:14px;font-weight:600;">➕ 创建用户</button>
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
            <input type="hidden" name="page" value="users">
            <input type="text" name="search" placeholder="搜索用户ID/用户名/设备ID" value="<?= htmlspecialchars($search) ?>" style="flex:1;min-width:200px;padding:10px 14px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;">
            <select name="status_filter" style="padding:10px 14px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;min-width:120px;">
                <option value="">全部状态</option>
                <option value="active" <?= $statusFilter=='active'?'selected':'' ?>>正常</option>
                <option value="banned" <?= $statusFilter=='banned'?'selected':'' ?>>已封禁</option>
            </select>
            <button type="submit" class="btn btn-primary" style="white-space:nowrap;">🔍 搜索</button>
        </form>
    </div>
</div>

<div class="card" style="margin-top:20px;">
    <table>
        <thead>
            <tr>
                <th style="width:40px;">
                    <input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)" style="cursor:pointer;width:16px;height:16px;">
                </th>
                <th>用户ID</th>
                <th>用户名</th>
                <th>邮箱</th>
                <th>设备ID</th>
                <th>余额(积分)</th>
                <th>消费总额</th>
                <th>订单数</th>
                <th>状态</th>
                <th>注册时间</th>
                <th>最后登录</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            <?php if(empty($users)): ?>
            <tr>
                <td colspan="12" style="text-align:center;color:#64748b;padding:40px;">暂无用户数据</td>
            </tr>
            <?php else: ?>
            <?php foreach($users as $user): ?>
            <tr>
                <td>
                    <input type="checkbox" class="user-checkbox" value="<?= $user['id'] ?>" onchange="updateSelection()" style="cursor:pointer;width:16px;height:16px;">
                </td>
                <td><small style="color:#64748b;"><?= htmlspecialchars(substr($user['id'], 0, 12)) ?>...</small></td>
                <td><?= htmlspecialchars($user['username'] ?? '-') ?></td>
                <td><small><?= htmlspecialchars($user['email'] ?? '-') ?></small></td>
                <td><small style="color:#64748b;"><?= htmlspecialchars(substr($user['device_id'] ?? '', 0, 12)) ?>...</small></td>
                <td><strong style="color:#6366f1;"><?= $user['balance'] ?></strong></td>
                <td><?= $user['total_spent'] ?></td>
                <td><?= $user['order_count'] ?></td>
                <td>
                    <span class="badge <?= $user['status']=='active'?'badge-success':'badge-secondary' ?>">
                        <?= $user['status']=='active'?'正常':'禁用' ?>
                    </span>
                </td>
                <td><small style="color:#64748b;"><?= date('Y-m-d', strtotime($user['created_at'])) ?></small></td>
                <td><small style="color:#64748b;"><?= $user['last_login'] ? date('Y-m-d H:i', strtotime($user['last_login'])) : '-' ?></small></td>
                <td>
                    <div style="display:flex;gap:4px;flex-wrap:wrap;">
                        <button onclick="showDetail('<?= $user['id'] ?>', '<?= htmlspecialchars($user['username'] ?? '') ?>', '<?= htmlspecialchars($user['email'] ?? '') ?>', '<?= htmlspecialchars($user['device_id'] ?? '') ?>', '<?= $user['balance'] ?>', '<?= $user['total_spent'] ?>', '<?= $user['order_count'] ?>', '<?= $user['status'] ?>', '<?= htmlspecialchars($user['created_at']) ?>', '<?= htmlspecialchars($user['last_login'] ?? '') ?>', '<?= htmlspecialchars($user['register_ip'] ?? '') ?>', '<?= htmlspecialchars($user['notes'] ?? '') ?>')" style="background:#3b82f6;color:white;border:none;padding:5px 8px;border-radius:6px;cursor:pointer;font-size:12px;font-weight:600;">📋 详情</button>
                        <button onclick="resetPassword('<?= $user['id'] ?>', '<?= htmlspecialchars($user['username'] ?? $user['id']) ?>')" style="background:#f59e0b;color:white;border:none;padding:5px 8px;border-radius:6px;cursor:pointer;font-size:12px;font-weight:600;">🔑 密码</button>
                        <button onclick="editUser('<?= $user['id'] ?>', '<?= htmlspecialchars($user['username'] ?? '-') ?>', '<?= htmlspecialchars($user['email'] ?? '') ?>', '<?= htmlspecialchars($user['notes'] ?? '') ?>')" style="background:#8b5cf6;color:white;border:none;padding:5px 8px;border-radius:6px;cursor:pointer;font-size:12px;font-weight:600;">✏️ 编辑</button>
                        <button onclick="addBalance('<?= $user['id'] ?>', '<?= htmlspecialchars($user['username'] ?? $user['id']) ?>')" style="background:#6366f1;color:white;border:none;padding:5px 8px;border-radius:6px;cursor:pointer;font-size:12px;font-weight:600;">💰 积分</button>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('确定<?= $user['status']=='active'?'封禁':'解封' ?>此用户？')">
                            <input type="hidden" name="action" value="toggle_status">
                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                            <button type="submit" style="background:<?= $user['status']=='active'?'#ef4444':'#10b981' ?>;color:white;border:none;padding:5px 8px;border-radius:6px;cursor:pointer;font-size:12px;font-weight:600;">
                                <?= $user['status']=='active'?'🚫':'✅' ?>
                            </button>
                        </form>
                        <button onclick="deleteUser('<?= $user['id'] ?>', '<?= htmlspecialchars($user['username'] ?? '-') ?>')" style="background:#ef4444;color:white;border:none;padding:5px 8px;border-radius:6px;cursor:pointer;font-size:12px;font-weight:600;">🗑️</button>
                        <a href="?page=orders&search=<?= urlencode($user['id']) ?>" style="background:#f1f5f9;color:#475569;border:1px solid #e2e8f0;padding:4px 8px;border-radius:6px;text-decoration:none;font-size:12px;font-weight:600;">📋</a>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if($totalPages > 1): ?>
    <div style="padding:16px;display:flex;justify-content:center;gap:4px;flex-wrap:wrap;">
        <?php for($i=1; $i<=$totalPages; $i++): ?>
        <a href="?page=users&p=<?= $i ?>&search=<?= urlencode($search) ?>"
           style="padding:6px 12px;border:1px solid #e2e8f0;border-radius:6px;text-decoration:none;color:#64748b;font-size:13px;<?= $i==$currentPage?'background:#6366f1;color:white;border-color:#6366f1;':'' ?>">
            <?= $i ?>
        </a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<!-- 加积分弹窗 -->
<div id="balanceModal" style="display:none; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5); align-items:center; justify-content:center;">
    <div class="card" style="width:400px; padding:24px;">
        <h5 style="margin-top:0;">💰 为用户加积分</h5>
        <p id="modalUser" style="font-size:14px; color:#64748b; margin-bottom:20px;"></p>
        <form method="POST">
            <input type="hidden" name="action" value="add_balance">
            <input type="hidden" name="user_id" id="modalUserId">
            <div style="margin-bottom:16px;">
                <label style="display:block; margin-bottom:8px; font-size:14px;">积分金额 (正数为加，负数为减)</label>
                <input type="number" name="amount" placeholder="输入积分数" required 
                    style="width:100%; padding:10px; border:1px solid #e2e8f0; border-radius:8px;">
            </div>
            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" onclick="closeModal()" class="btn btn-secondary">取消</button>
                <button type="submit" class="btn btn-primary">确定</button>
            </div>
        </form>
    </div>
</div>

<!-- 重置密码弹窗 -->
<div id="passwordModal" style="display:none; position:fixed; z-index:1001; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5); align-items:center; justify-content:center;">
    <div class="card" style="width:450px; padding:24px;">
        <h5 style="margin-top:0;">🔑 重置用户密码</h5>
        <p id="passwordUser" style="font-size:14px; color:#64748b; margin-bottom:16px;"></p>
        <div style="background:#fef3c7; border:1px solid #f59e0b; border-radius:8px; padding:12px; margin-bottom:16px;">
            <div style="display:flex; align-items:center; gap:8px; color:#92400e;">
                <span>⚠️</span>
                <span style="font-size:13px;">重置后旧密码将失效，请通知用户使用新密码登录</span>
            </div>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="user_id" id="passwordUserId">
            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" onclick="closePasswordModal()" class="btn btn-secondary">取消</button>
                <button type="submit" class="btn btn-primary" style="background:#f59e0b;" onclick="return confirm('确定重置此用户密码？')">确定重置</button>
            </div>
        </form>
    </div>
</div>

<!-- 编辑用户信息弹窗 -->
<div id="editModal" style="display:none; position:fixed; z-index:1002; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5); align-items:center; justify-content:center;">
    <div class="card" style="width:500px; padding:24px;">
        <h5 style="margin-top:0;">✏️ 编辑用户信息</h5>
        <p id="editUserName" style="font-size:14px; color:#64748b; margin-bottom:16px;"></p>
        <div style="margin-bottom:16px;">
            <label style="display:block; margin-bottom:8px; font-size:14px; font-weight:600;">邮箱地址</label>
            <input type="email" id="editEmailInput" placeholder="输入邮箱地址" 
                style="width:100%; padding:10px; border:1px solid #e2e8f0; border-radius:8px;">
        </div>
        <div style="margin-bottom:16px;">
            <label style="display:block; margin-bottom:8px; font-size:14px; font-weight:600;">备注</label>
            <textarea id="editNotesInput" placeholder="输入备注信息" rows="3"
                style="width:100%; padding:10px; border:1px solid #e2e8f0; border-radius:8px; resize:vertical; font-family:inherit;"></textarea>
        </div>
        <div style="display:flex; justify-content:flex-end; gap:10px;">
            <button type="button" onclick="closeEditModal()" class="btn btn-secondary">取消</button>
            <button type="button" onclick="saveEditInfo()" class="btn btn-primary" style="background:#8b5cf6;">保存</button>
        </div>
    </div>
</div>

<!-- 用户详情弹窗 -->
<div id="detailModal" style="display:none; position:fixed; z-index:1003; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5); align-items:flex-start; justify-content:center; padding-top:60px; overflow-y:auto;">
    <div class="card" style="width:700px; padding:24px; margin-bottom:60px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h5 style="margin:0;">📋 用户详细信息</h5>
            <button onclick="closeDetailModal()" style="background:none; border:none; font-size:20px; cursor:pointer; color:#64748b;">✕</button>
        </div>
        <div id="detailContent"></div>
        <div id="detailOrders" style="margin-top:20px;"></div>
        <div id="detailCredits" style="margin-top:20px;"></div>
    </div>
</div>

<!-- 创建用户弹窗 -->
<div id="createUserModal" style="display:none; position:fixed; z-index:1004; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5); align-items:center; justify-content:center;">
    <div class="card" style="width:500px; padding:24px;">
        <h5 style="margin-top:0;">➕ 创建新用户</h5>
        <div style="background:#ecfdf5; border:1px solid #10b981; border-radius:8px; padding:12px; margin-bottom:16px;">
            <div style="display:flex; align-items:start; gap:8px; color:#065f46;">
                <span>ℹ️</span>
                <div style="font-size:13px;">
                    <div>系统将自动生成：</div>
                    <div>• 唯一用户ID</div>
                    <div>• 随机用户名</div>
                    <div>• 初始积分（根据系统配置）</div>
                </div>
            </div>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="create_user">
            <div style="margin-bottom:16px;">
                <label style="display:block; margin-bottom:8px; font-size:14px; font-weight:600;">邮箱地址 *</label>
                <input type="email" name="email" placeholder="请输入邮箱地址" required
                    style="width:100%; padding:10px; border:1px solid #e2e8f0; border-radius:8px;">
            </div>
            <div style="margin-bottom:16px;">
                <label style="display:block; margin-bottom:8px; font-size:14px; font-weight:600;">登录密码 *</label>
                <input type="text" name="password" placeholder="请输入密码（至少6位）" required
                    style="width:100%; padding:10px; border:1px solid #e2e8f0; border-radius:8px;">
                <div style="font-size:12px; color:#64748b; margin-top:4px;">用户可使用邮箱和密码登录App</div>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" onclick="closeCreateUserModal()" class="btn btn-secondary">取消</button>
                <button type="submit" class="btn btn-primary" style="background:#10b981;">创建</button>
            </div>
        </form>
    </div>
</div>

<script>
let currentEditUserId = '';

function ajaxPost(action, data) {
    const formData = new FormData();
    formData.append('action', action);
    for (const [key, value] of Object.entries(data)) {
        formData.append(key, value);
    }
    return fetch(window.location.href, {
        method: 'POST',
        body: formData,
        headers: {'X-Requested-With': 'XMLHttpRequest'}
    }).then(r => r.json());
}

function addBalance(id, name) {
    document.getElementById('modalUserId').value = id;
    document.getElementById('modalUser').innerText = '正在为用户 [' + name + '] 调整余额';
    document.getElementById('balanceModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('balanceModal').style.display = 'none';
}

// 覆盖默认的form submit，改用AJAX
document.addEventListener('DOMContentLoaded', function() {
    const balanceForm = document.querySelector('#balanceModal form');
    if (balanceForm) {
        balanceForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const userId = document.getElementById('modalUserId').value;
            const amount = balanceForm.querySelector('input[name="amount"]').value;
            
            ajaxPost('add_balance', {user_id: userId, amount: amount}).then(data => {
                if (data.success) {
                    alert('积分已更新');
                    window.location.reload();
                } else {
                    alert('更新失败: ' + (data.error || '未知错误'));
                }
            }).catch(() => alert('请求失败'));
        });
    }
    
    const passwordForm = document.querySelector('#passwordModal form');
    if (passwordForm) {
        passwordForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const userId = document.getElementById('passwordUserId').value;
            
            ajaxPost('reset_password', {user_id: userId}).then(data => {
                if (data.success) {
                    alert('密码已重置，新密码: ' + data.password);
                    window.location.reload();
                } else {
                    alert('重置失败: ' + (data.error || '未知错误'));
                }
            }).catch(() => alert('请求失败'));
        });
    }
    
    const createForm = document.querySelector('#createUserModal form');
    if (createForm) {
        createForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const email = createForm.querySelector('input[name="email"]').value;
            const password = createForm.querySelector('input[name="password"]').value;
            
            ajaxPost('create_user', {email: email, password: password}).then(data => {
                if (data.success) {
                    alert('用户创建成功！\n用户名: ' + data.username + '\n密码: ' + data.password + '\n初始积分: ' + data.credits);
                    window.location.reload();
                } else {
                    alert('创建失败: ' + (data.error || '未知错误'));
                }
            }).catch(() => alert('请求失败'));
        });
    }
});

function resetPassword(id, name) {
    document.getElementById('passwordUserId').value = id;
    document.getElementById('passwordUser').innerText = '正在为用户 [' + name + '] 重置密码';
    document.getElementById('passwordModal').style.display = 'flex';
}

function closePasswordModal() {
    document.getElementById('passwordModal').style.display = 'none';
}

function editUser(id, username, email, notes) {
    currentEditUserId = id;
    document.getElementById('editUserName').innerText = '用户: ' + username;
    document.getElementById('editEmailInput').value = email;
    document.getElementById('editNotesInput').value = notes;
    document.getElementById('editModal').style.display = 'flex';
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

function saveEditInfo() {
    const email = document.getElementById('editEmailInput').value;
    const notes = document.getElementById('editNotesInput').value;
    
    Promise.all([
        ajaxPost('update_email', {user_id: currentEditUserId, email: email}),
        ajaxPost('update_notes', {user_id: currentEditUserId, notes: notes})
    ]).then(results => {
        const emailResult = results[0];
        if (!emailResult.success) {
            alert('邮箱保存失败: ' + (emailResult.error || '未知错误'));
            return;
        }
        closeEditModal();
        window.location.reload();
    }).catch(err => {
        alert('保存失败: ' + err.message);
    });
}

function showDetail(id, username, email, deviceId, balance, totalSpent, orderCount, status, createdAt, lastLogin, registerIp, notes) {
    const statusText = status === 'active' ? '✅ 正常' : '🚫 已封禁';
    const statusColor = status === 'active' ? '#10b981' : '#ef4444';
    
    document.getElementById('detailContent').innerHTML = `
        <div style="display:grid; gap:16px;">
            <div style="background:#f8fafc; border-radius:8px; padding:16px;">
                <div style="font-size:12px; color:#64748b; margin-bottom:4px;">用户ID</div>
                <div style="font-family:monospace; font-size:14px;">${id}</div>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                <div style="background:#f8fafc; border-radius:8px; padding:16px;">
                    <div style="font-size:12px; color:#64748b; margin-bottom:4px;">用户名</div>
                    <div style="font-size:14px; font-weight:600;">${username || '-'}</div>
                </div>
                <div style="background:#f8fafc; border-radius:8px; padding:16px;">
                    <div style="font-size:12px; color:#64748b; margin-bottom:4px;">邮箱</div>
                    <div style="font-size:14px;">${email || '-'}</div>
                </div>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:16px;">
                <div style="background:#f8fafc; border-radius:8px; padding:16px;">
                    <div style="font-size:12px; color:#64748b; margin-bottom:4px;">余额(积分)</div>
                    <div style="font-size:18px; font-weight:700; color:#6366f1;">${balance}</div>
                </div>
                <div style="background:#f8fafc; border-radius:8px; padding:16px;">
                    <div style="font-size:12px; color:#64748b; margin-bottom:4px;">消费总额</div>
                    <div style="font-size:18px; font-weight:700;">${totalSpent}</div>
                </div>
                <div style="background:#f8fafc; border-radius:8px; padding:16px;">
                    <div style="font-size:12px; color:#64748b; margin-bottom:4px;">订单数</div>
                    <div style="font-size:18px; font-weight:700;">${orderCount}</div>
                </div>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                <div style="background:#f8fafc; border-radius:8px; padding:16px;">
                    <div style="font-size:12px; color:#64748b; margin-bottom:4px;">状态</div>
                    <div style="font-size:14px; font-weight:600; color:${statusColor};">${statusText}</div>
                </div>
                <div style="background:#f8fafc; border-radius:8px; padding:16px;">
                    <div style="font-size:12px; color:#64748b; margin-bottom:4px;">注册时间</div>
                    <div style="font-size:14px;">${createdAt}</div>
                </div>
            </div>
            <div style="background:#f8fafc; border-radius:8px; padding:16px;">
                <div style="font-size:12px; color:#64748b; margin-bottom:4px;">设备ID</div>
                <div style="font-family:monospace; font-size:13px; word-break:break-all;">${deviceId || '-'}</div>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                <div style="background:#f8fafc; border-radius:8px; padding:16px;">
                    <div style="font-size:12px; color:#64748b; margin-bottom:4px;">注册IP</div>
                    <div style="font-size:14px;">${registerIp || '-'}</div>
                </div>
                <div style="background:#f8fafc; border-radius:8px; padding:16px;">
                    <div style="font-size:12px; color:#64748b; margin-bottom:4px;">最后登录</div>
                    <div style="font-size:14px;">${lastLogin || '-'}</div>
                </div>
            </div>
            ${notes ? `
            <div style="background:#f8fafc; border-radius:8px; padding:16px;">
                <div style="font-size:12px; color:#64748b; margin-bottom:4px;">备注</div>
                <div style="font-size:14px; white-space:pre-wrap;">${notes}</div>
            </div>
            ` : ''}
        </div>
    `;
    
    // 加载订单列表
    loadUserOrders(id);
    // 加载积分记录
    loadUserCredits(id);
    
    document.getElementById('detailModal').style.display = 'flex';
}

function loadUserOrders(userId) {
    const ordersDiv = document.getElementById('detailOrders');
    ordersDiv.innerHTML = '<div style="color:#64748b;">加载中...</div>';
    
    fetch('pages/orders.php?ajax=1&user_id=' + encodeURIComponent(userId))
        .then(r => {
            if (!r.ok) {
                return r.text().then(text => { throw new Error('HTTP ' + r.status + ': ' + text); });
            }
            return r.json();
        })
        .then(data => {
            if (data.orders && data.orders.length > 0) {
                let html = '<div style="font-weight:600;margin-bottom:12px;">📋 订单记录（' + data.orders.length + ' 条）</div>';
                html += '<div style="max-height:300px;overflow-y:auto;border:1px solid #e2e8f0;border-radius:8px;">';
                html += '<table style="width:100%;font-size:13px;border-collapse:collapse;">';
                html += '<thead><tr style="background:#f8fafc;border-bottom:1px solid #e2e8f0;">';
                html += '<th style="padding:8px;text-align:left;">订单ID</th>';
                html += '<th style="padding:8px;text-align:left;">服务</th>';
                html += '<th style="padding:8px;text-align:left;">手机号</th>';
                html += '<th style="padding:8px;text-align:center;">积分</th>';
                html += '<th style="padding:8px;text-align:center;">状态</th>';
                html += '<th style="padding:8px;text-align:left;">时间</th>';
                html += '</tr></thead><tbody>';
                
                data.orders.forEach(function(order) {
                    let statusBadge = '';
                    if (order.status === 'pending') statusBadge = '<span style="background:#fef3c7;color:#92400e;padding:2px 8px;border-radius:4px;font-size:11px;">待使用</span>';
                    else if (order.status === 'activated') statusBadge = '<span style="background:#dbeafe;color:#1e40af;padding:2px 8px;border-radius:4px;font-size:11px;">使用中</span>';
                    else if (order.status === 'completed') statusBadge = '<span style="background:#d1fae5;color:#065f46;padding:2px 8px;border-radius:4px;font-size:11px;">已完成</span>';
                    else if (order.status === 'expired') statusBadge = '<span style="background:#fee2e2;color:#991b1b;padding:2px 8px;border-radius:4px;font-size:11px;">已过期</span>';
                    else statusBadge = '<span style="background:#f1f5f9;color:#64748b;padding:2px 8px;border-radius:4px;font-size:11px;">' + order.status + '</span>';
                    
                    html += '<tr style="border-bottom:1px solid #f1f5f9;">';
                    html += '<td style="padding:8px;font-family:monospace;font-size:11px;">' + (order.id || '').substring(0, 16) + '...</td>';
                    html += '<td style="padding:8px;">' + (order.service_name || '-') + '</td>';
                    html += '<td style="padding:8px;">' + (order.phone_number || '-') + '</td>';
                    html += '<td style="padding:8px;text-align:center;">' + (order.total_price !== undefined ? Number(order.total_price) : '-') + '</td>';
                    html += '<td style="padding:8px;text-align:center;">' + statusBadge + '</td>';
                    html += '<td style="padding:8px;font-size:11px;color:#64748b;">' + (order.created_at || '') + '</td>';
                    html += '</tr>';
                });
                
                html += '</tbody></table></div>';
                ordersDiv.innerHTML = html;
            } else {
                ordersDiv.innerHTML = '<div style="color:#64748b;font-size:14px;">暂无订单记录</div>';
            }
        })
        .catch(() => {
            ordersDiv.innerHTML = '<div style="color:#ef4444;">加载订单失败</div>';
        });
}

function loadUserCredits(userId) {
    const creditsDiv = document.getElementById('detailCredits');
    creditsDiv.innerHTML = '<div style="color:#64748b;">加载中...</div>';
    
    fetch('pages/credits.php?ajax=1&user_id=' + encodeURIComponent(userId))
        .then(r => {
            if (!r.ok) {
                return r.text().then(text => { throw new Error('HTTP ' + r.status + ': ' + text); });
            }
            return r.json();
        })
        .then(data => {
            if (data.transactions && data.transactions.length > 0) {
                let html = '<div style="font-weight:600;margin-bottom:12px;">💰 积分变动记录（' + data.transactions.length + ' 条）</div>';
                html += '<div style="max-height:300px;overflow-y:auto;border:1px solid #e2e8f0;border-radius:8px;">';
                html += '<table style="width:100%;font-size:13px;border-collapse:collapse;">';
                html += '<thead><tr style="background:#f8fafc;border-bottom:1px solid #e2e8f0;">';
                html += '<th style="padding:8px;text-align:left;">类型</th>';
                html += '<th style="padding:8px;text-align:right;">变动</th>';
                html += '<th style="padding:8px;text-align:right;">变动后余额</th>';
                html += '<th style="padding:8px;text-align:left;">说明</th>';
                html += '<th style="padding:8px;text-align:left;">时间</th>';
                html += '</tr></thead><tbody>';
                
                data.transactions.forEach(function(txn) {
                    const amount = parseFloat(txn.amount);
                    const balanceAfter = parseFloat(txn.balance_after);
                    const amountColor = amount > 0 ? '#10b981' : '#ef4444';
                    const amountPrefix = amount > 0 ? '+' : '';
                    let typeIcon = '';
                    if (txn.type === 'purchase') typeIcon = '📦 购买';
                    else if (txn.type === 'topup') typeIcon = '💎 充值';
                    else if (txn.type === 'bonus') typeIcon = '🎁 赠送';
                    else if (txn.type === 'refund') typeIcon = '↩️ 退款';
                    else typeIcon = txn.type;
                    
                    html += '<tr style="border-bottom:1px solid #f1f5f9;">';
                    html += '<td style="padding:8px;">' + typeIcon + '</td>';
                    html += '<td style="padding:8px;text-align:right;font-weight:600;color:' + amountColor + ';">' + amountPrefix + amount + '</td>';
                    html += '<td style="padding:8px;text-align:right;">' + balanceAfter + '</td>';
                    html += '<td style="padding:8px;font-size:12px;color:#64748b;">' + (txn.description || '-') + '</td>';
                    html += '<td style="padding:8px;font-size:11px;color:#64748b;">' + (txn.created_at || '') + '</td>';
                    html += '</tr>';
                });
                
                html += '</tbody></table></div>';
                creditsDiv.innerHTML = html;
            } else {
                creditsDiv.innerHTML = '<div style="color:#64748b;font-size:14px;">暂无积分变动记录</div>';
            }
        })
        .catch(() => {
            creditsDiv.innerHTML = '<div style="color:#ef4444;">加载积分记录失败</div>';
        });
}

function closeDetailModal() {
    document.getElementById('detailModal').style.display = 'none';
}

function showCreateUserModal() {
    document.getElementById('createUserModal').style.display = 'flex';
}

function closeCreateUserModal() {
    document.getElementById('createUserModal').style.display = 'none';
}

// 批量操作相关函数
function toggleSelectAll(checkbox) {
    const checkboxes = document.querySelectorAll('.user-checkbox');
    checkboxes.forEach(cb => cb.checked = checkbox.checked);
    updateSelection();
}

function updateSelection() {
    const checkboxes = document.querySelectorAll('.user-checkbox:checked');
    const count = checkboxes.length;
    const selectAllCheckbox = document.getElementById('selectAll');
    const totalCheckboxes = document.querySelectorAll('.user-checkbox').length;
    
    // 更新全选框状态
    selectAllCheckbox.checked = count === totalCheckboxes && count > 0;
    selectAllCheckbox.indeterminate = count > 0 && count < totalCheckboxes;
    
    // 显示/隐藏批量操作按钮
    document.getElementById('selectedCount').style.display = count > 0 ? 'block' : 'none';
    document.getElementById('selectedNum').textContent = count;
    document.getElementById('batchDeleteBtn').style.display = count > 0 ? 'block' : 'none';
    document.getElementById('batchExportBtn').style.display = count > 0 ? 'block' : 'none';
}

function getSelectedUserIds() {
    const checkboxes = document.querySelectorAll('.user-checkbox:checked');
    return Array.from(checkboxes).map(cb => cb.value);
}

function batchDelete() {
    const userIds = getSelectedUserIds();
    if (userIds.length === 0) {
        alert('请先选择要删除的用户');
        return;
    }
    
    if (!confirm(`确定要删除选中的 ${userIds.length} 个用户吗？此操作不可恢复！`)) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'batch_delete');
    userIds.forEach(id => formData.append('user_ids[]', id));
    
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

function batchExport() {
    const userIds = getSelectedUserIds();
    if (userIds.length === 0) {
        alert('请先选择要导出的用户');
        return;
    }
    
    // 创建隐藏表单提交
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = window.location.href;
    
    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'action';
    actionInput.value = 'batch_export';
    form.appendChild(actionInput);
    
    userIds.forEach(id => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'user_ids[]';
        input.value = id;
        form.appendChild(input);
    });
    
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}

function deleteUser(id, name) {
    if (!confirm('确定删除用户 [' + name + '] 吗？此操作不可恢复！')) {
        return;
    }
    
    ajaxPost('delete_user', {user_id: id}).then(data => {
        if (data.success) {
            alert('用户已删除');
            window.location.reload();
        } else {
            alert('删除失败: ' + (data.error || '未知错误'));
        }
    }).catch(() => alert('请求失败'));
}

function showCreateUserModal() {
    document.getElementById('createUserModal').style.display = 'flex';
}

function closeCreateUserModal() {
    document.getElementById('createUserModal').style.display = 'none';
}

function deleteUser(id, name) {
    if (!confirm('️ 警告：删除用户将同时删除该用户的所有订单和积分记录，此操作不可恢复！\n\n确定要删除用户 [' + name + '] 吗？')) {
        return;
    }
    
    ajaxPost('delete_user', {user_id: id}).then(data => {
        if (data.success) {
            alert('用户已删除');
            window.location.reload();
        } else {
            alert('删除失败: ' + (data.error || '未知错误'));
        }
    }).catch(() => alert('请求失败'));
}

// 点击遮罩层关闭弹窗
document.querySelectorAll('[id$="Modal"]').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.style.display = 'none';
        }
    });
});
</script>