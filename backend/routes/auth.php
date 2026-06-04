<?php
/**
 * 认证相关路由
 */

// 密码登录
if ($path === '/auth/password-login' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $login = $input['login'] ?? null;
    $password = $input['password'] ?? null;
    
    if (!$login || !$password) {
        apiBadRequest('账号和密码不能为空');
    }
    
    $user = $db->query(
        "SELECT id, username, email, password_hash, balance, total_spent, order_count, created_at 
         FROM users 
         WHERE (username = ? OR email = ?) AND status = 'active'",
        [$login, $login]
    )->fetch();
    
    if (!$user) {
        apiUnauthorized('账号或密码错误');
    }
    
    if (!password_verify($password, $user['password_hash'])) {
        apiUnauthorized('账号或密码错误');
    }
    
    $db->query("UPDATE users SET last_login = NOW() WHERE id = ?", [$user['id']]);
    $token = Auth::generateToken($user['id']);
    
    logUserActivity($db, $user['id'], 'password_login', 'auth', $user['id']);
    
    echo json_encode([
        'success' => true,
        'user' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'balance' => floatval($user['balance']),
            'total_spent' => floatval($user['total_spent']),
            'order_count' => intval($user['order_count']),
            'created_at' => $user['created_at']
        ],
        'token' => $token
    ]);
    exit;
}

// 修改密码（安全修复：user_id 从 token 取，不接受 body）
if ($path === '/auth/change-password' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $userId = getCurrentUserIdFromToken(); // 不再用 body 里的 user_id
    $oldPassword = $input['old_password'] ?? null;
    $newPassword = $input['new_password'] ?? null;

    if (!$userId) {
        apiUnauthorized('请先登录');
    }
    if (!$oldPassword || !$newPassword) {
        apiBadRequest('参数缺失');
    }
    
    if (strlen($newPassword) < 8) {
        apiBadRequest('新密码长度不能少于8位');
    }
    
    $user = $db->query(
        "SELECT id, password_hash FROM users WHERE id = ?",
        [$userId]
    )->fetch();
    
    if (!$user) {
        apiNotFound('用户不存在');
    }
    
    if (!password_verify($oldPassword, $user['password_hash'])) {
        apiUnauthorized('旧密码错误');
    }
    
    $newPasswordHash = password_hash($newPassword, PASSWORD_BCRYPT);
    $db->query(
        "UPDATE users SET password_hash = ? WHERE id = ?",
        [$newPasswordHash, $userId]
    );
    
    logUserActivity($db, $userId, 'change_password', 'auth', $userId);
    
    echo json_encode(['success' => true, 'message' => '密码修改成功']);
    exit;
}

// 验证密码（安全修复：user_id 从 token 取）
if ($path === '/auth/verify-password' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $userId = getCurrentUserIdFromToken();
    $password = $input['password'] ?? null;

    if (!$userId) {
        apiUnauthorized('请先登录');
    }
    if (!$password) {
        apiBadRequest('参数缺失');
    }
    
    $user = $db->query(
        "SELECT password_hash FROM users WHERE id = ?",
        [$userId]
    )->fetch();
    
    if (!$user) {
        apiNotFound('用户不存在');
    }
    
    $valid = password_verify($password, $user['password_hash']);
    
    echo json_encode(['success' => true, 'valid' => $valid]);
    exit;
}

// 忘记密码（直接重置为随机密码，不发送验证码）
if ($path === '/auth/forgot-password' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $email = $input['email'] ?? null;

    if (!$email) {
        apiBadRequest('邮箱不能为空');
    }

    // 频率限制：同邮箱 5 次/小时，同 IP 20 次/小时
    if (!RateLimiter::hitByIpAndSubject($db, 'forgot_password', $email, 5, 20, 3600)) {
        apiError('操作过于频繁，请稍后再试', 429, 'rate_limited');
    }

    $user = $db->query(
        "SELECT id, email FROM users WHERE email = ? AND status = 'active'",
        [$email]
    )->fetch();

    if (!$user) {
        // 故意返回成功，避免暴露邮箱是否注册
        echo json_encode([
            'success' => true,
            'message' => '如果该邮箱已注册，新密码将发送到您的邮箱'
        ]);
        exit;
    }

    // 用密码学安全的方式生成 10 位密码
    $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $lowercase = 'abcdefghijklmnopqrstuvwxyz';
    $numbers = '0123456789';
    $chars = $uppercase . $lowercase . $numbers;
    $newPassword = '';
    $newPassword .= $uppercase[random_int(0, strlen($uppercase) - 1)];
    $newPassword .= $lowercase[random_int(0, strlen($lowercase) - 1)];
    $newPassword .= $lowercase[random_int(0, strlen($lowercase) - 1)];
    $newPassword .= $numbers[random_int(0, strlen($numbers) - 1)];
    for ($i = 0; $i < 6; $i++) {
        $newPassword .= $chars[random_int(0, strlen($chars) - 1)];
    }
    $newPassword = str_shuffle($newPassword);

    $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT);
    $db->query("UPDATE users SET password_hash = ? WHERE id = ?", [$passwordHash, $user['id']]);

    // 生成一次性 reset_token，30 分钟有效。把它放到 reset link 中通过邮件发送
    // 这里采用「服务端不出明文 token，邮件中只放哈希后用到的原文」方式不安全，
    // 正确做法：把原文 token 放在邮件链接里，库中只存 hash
    $resetToken = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $resetToken);
    $expiresAt = date('Y-m-d H:i:s', time() + 1800);
    $db->insert('password_reset_tokens', [
        'user_id' => $user['id'],
        'token_hash' => $tokenHash,
        'expires_at' => $expiresAt,
        'used' => 0,
        'created_at' => date('Y-m-d H:i:s')
    ]);

    logUserActivity($db, $user['id'], 'forgot_password_reset', 'auth', $user['id']);

    // 把新密码 + 重置 token 一并发送
    try {
        $subject = 'Simu SMS - 密码重置';
        $message = "您好，\n\n您的 Simu SMS 账户密码已重置。\n\n新密码: $newPassword\n\n"
                 . "如需自定义密码，请使用以下重置令牌：\n$resetToken\n"
                 . "（30 分钟内有效，可通过应用内「重置密码」页使用）\n\n"
                 . "此致\nSimu SMS 团队";
        $headers = 'From: no-reply@sms.niceapp.eu.cc' . "\r\n" .
                   'Content-Type: text/plain; charset=UTF-8';
        @mail($user['email'], $subject, $message, $headers);
    } catch (Exception $e) {
        error_log("Failed to send password reset email: " . $e->getMessage());
    }

    // 安全修复：不再在 JSON 响应中返回明文密码
    // 新密码仅通过邮件/通知发送给用户，避免日志/中间人泄露
    echo json_encode([
        'success' => true,
        'message' => '密码已重置，新密码已发送至您绑定的邮箱'
    ]);
    exit;
}

// 重置密码 — 必须先调用 /auth/forgot-password 拿到一次性 token
if ($path === '/auth/reset-password' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $email = $input['email'] ?? null;
    $newPassword = $input['new_password'] ?? null;
    $resetToken = $input['reset_token'] ?? null;

    if (!$email || !$newPassword || !$resetToken) {
        apiBadRequest('参数缺失');
    }

    // 频率限制：同邮箱 5 次/小时，同 IP 20 次/小时
    if (!RateLimiter::hitByIpAndSubject($db, 'reset_password', $email, 5, 20, 3600)) {
        apiError('操作过于频繁，请稍后再试', 429, 'rate_limited');
    }

    if (strlen($newPassword) < 8) {
        apiBadRequest('新密码长度不能少于8位');
    }

    // 校验一次性 token：必须是 /auth/forgot-password 在 30 分钟内签发
    $tokenHash = hash('sha256', $resetToken);
    $tokenRow = $db->query(
        "SELECT id, user_id, expires_at, used FROM password_reset_tokens
         WHERE token_hash = ? AND expires_at > NOW() AND used = 0
         ORDER BY id DESC LIMIT 1",
        [$tokenHash]
    )->fetch();

    if (!$tokenRow) {
        apiUnauthorized('重置链接无效或已过期，请重新获取');
    }

    $user = $db->query(
        "SELECT id FROM users WHERE id = ? AND email = ? AND status = 'active'",
        [$tokenRow['user_id'], $email]
    )->fetch();

    if (!$user) {
        apiNotFound('用户不存在');
    }

    $db->beginTransaction();
    try {
        $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT);
        $db->query("UPDATE users SET password_hash = ? WHERE id = ?", [$passwordHash, $user['id']]);
        $db->query("UPDATE password_reset_tokens SET used = 1 WHERE id = ?", [$tokenRow['id']]);
        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        apiServerError('密码重置失败');
    }

    logUserActivity($db, $user['id'], 'reset_password', 'auth', $user['id']);

    echo json_encode(['success' => true, 'message' => '密码重置成功']);
    exit;
}
