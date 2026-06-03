<?php
/**
 * 用户相关路由
 */

// 获取用户资料
if ($path === '/user/profile' && $method === 'GET') {
    $userId = getSecureUserId();

    if (!$userId) {
        apiBadRequest('user_id 参数缺失');
    }
    
    $user = $db->query(
        "SELECT id, username, email, nickname, avatar, device_id, balance, total_spent, order_count, created_at, last_login, has_topup_history FROM users WHERE id = ?",
        [$userId]
    )->fetch();
    
    if (!$user) {
        apiNotFound('用户不存在');
    }
    
    $hasTopupHistory = (bool)$user['has_topup_history'];
    $firstTopupCountdownHours = null;
    
    if (!$hasTopupHistory) {
        $createdAt = strtotime($user['created_at']);
        $now = time();
        $hoursSinceRegistration = ($now - $createdAt) / 3600;
        $countdownHours = max(0, 24 - $hoursSinceRegistration);
        $firstTopupCountdownHours = $countdownHours > 0 ? intval($countdownHours) : null;
    }
    
    $totalSpent = floatval($user['total_spent']);
    $membership = $db->query(
        "SELECT * FROM membership_levels WHERE min_spent <= ? AND active = 1 ORDER BY min_spent DESC LIMIT 1",
        [$totalSpent]
    )->fetch();
    
    $nextLevel = $db->query(
        "SELECT * FROM membership_levels WHERE min_spent > ? AND active = 1 ORDER BY min_spent ASC LIMIT 1",
        [$totalSpent]
    )->fetch();
    
    $progress = 0;
    $neededForNext = 0;
    if ($nextLevel) {
        $neededForNext = floatval($nextLevel['min_spent']) - $totalSpent;
        $currentLevelMax = $membership ? floatval($membership['min_spent']) : 0;
        $range = floatval($nextLevel['min_spent']) - $currentLevelMax;
        $progress = $range > 0 ? (($totalSpent - $currentLevelMax) / $range) * 100 : 100;
    }
    
    $allLevels = $db->query(
        "SELECT * FROM membership_levels WHERE active = 1 ORDER BY min_spent ASC"
    )->fetchAll();
    
    echo json_encode([
        'success' => true,
        'data' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'] ?? '',
            'nickname' => $user['nickname'] ?? '',
            'avatar' => $user['avatar'] ?? '',
            'device_id' => $user['device_id'] ?? '',
            'balance' => intval($user['balance']),
            'points' => intval($user['balance']),
            'total_spent' => floatval($user['total_spent']),
            'order_count' => intval($user['order_count']),
            'created_at' => $user['created_at'],
            'last_login' => $user['last_login'],
            'has_topup_history' => $hasTopupHistory,
            'first_topup_countdown_hours' => $firstTopupCountdownHours,
            'membership' => $membership ? [
                'level' => $membership['name'],
                'level_cn' => $membership['name_cn'],
                'min_spent' => floatval($membership['min_spent']),
                'discount' => floatval($membership['discount']),
                'icon' => $membership['icon'],
                'color' => $membership['color']
            ] : null,
            'next_level' => $nextLevel ? [
                'level' => $nextLevel['name'],
                'level_cn' => $nextLevel['name_cn'],
                'min_spent' => floatval($nextLevel['min_spent']),
                'discount' => floatval($nextLevel['discount'])
            ] : null,
            'progress' => [
                'current' => $totalSpent,
                'needed' => $neededForNext,
                'percentage' => round(min(100, max(0, $progress)), 1)
            ],
            'all_levels' => array_map(function($l) {
                return [
                    'name' => $l['name'],
                    'name_cn' => $l['name_cn'],
                    'min_spent' => floatval($l['min_spent']),
                    'discount' => floatval($l['discount']),
                    'icon' => $l['icon'],
                    'color' => $l['color']
                ];
            }, $allLevels)
        ]
    ]);
    exit;
}

// 更新用户资料
if ($path === '/user/profile' && $method === 'PUT') {
    $userId = getCurrentUserIdFromToken();
    if (!$userId) {
        apiUnauthorized('请先登录');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $nickname = $input['nickname'] ?? null;
    $avatar = $input['avatar'] ?? null;
    $email = $input['email'] ?? null;
    $oldPassword = $input['old_password'] ?? null;
    $newPassword = $input['new_password'] ?? null;
    $setPassword = $input['set_password'] ?? null;
    
    $db->beginTransaction();
    try {
        // 修改邮箱（需验证密码）
        if ($email !== null) {
            if (!$oldPassword) {
                apiError('修改邮箱需要验证原密码', 400, 'password_required');
            }
            
            $user = $db->query("SELECT password_hash FROM users WHERE id = ?", [$userId])->fetch();
            if (!$user || !$user['password_hash']) {
                apiError('当前未设置密码', 400, 'no_password_set');
            }
            
            if (!password_verify($oldPassword, $user['password_hash'])) {
                apiError('原密码不正确', 400, 'wrong_password');
            }
            
            // 检查邮箱是否已被其他用户使用
            $existingEmail = $db->query(
                "SELECT id FROM users WHERE email = ? AND id != ?",
                [$email, $userId]
            )->fetch();
            if ($existingEmail) {
                apiError('该邮箱已被其他用户使用', 400, 'email_exists');
            }
            
            $db->query(
                "UPDATE users SET email = ? WHERE id = ?",
                [$email, $userId]
            );
        }
        
        // 修改密码
        if ($oldPassword && $newPassword) {
            if (strlen($newPassword) < 8) {
                apiError('新密码长度至少8位', 400, 'password_too_short');
            }

            $user = $db->query("SELECT password_hash FROM users WHERE id = ?", [$userId])->fetch();
            if (!$user || !$user['password_hash']) {
                apiError('当前未设置密码', 400, 'no_password_set');
            }

            if (!password_verify($oldPassword, $user['password_hash'])) {
                apiError('原密码不正确', 400, 'wrong_password');
            }

            $db->query(
                "UPDATE users SET password_hash = ? WHERE id = ?",
                [password_hash($newPassword, PASSWORD_BCRYPT), $userId]
            );
        } elseif ($setPassword) {
            if (strlen($setPassword) < 8) {
                apiError('密码长度至少8位', 400, 'password_too_short');
            }
            
            $db->query(
                "UPDATE users SET password_hash = ? WHERE id = ?",
                [password_hash($setPassword, PASSWORD_BCRYPT), $userId]
            );
        }
        
        // 修改昵称和头像（无需验证）
        if ($nickname !== null || $avatar !== null) {
            $updates = [];
            $params = [];
            if ($nickname !== null) {
                $updates[] = 'nickname = ?';
                $params[] = $nickname;
            }
            if ($avatar !== null) {
                $updates[] = 'avatar = ?';
                $params[] = $avatar;
            }
            $params[] = $userId;
            $db->query(
                "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?",
                $params
            );
        }
        
        $db->commit();
        logUserActivity($db, $userId, 'update_profile', 'user', $userId);
        
        echo json_encode(['success' => true, 'message' => '资料更新成功']);
    } catch (Exception $e) {
        $db->rollBack();
        apiServerError('更新失败');
    }
    exit;
}

// 获取交易记录
if ($path === '/user/transactions' && $method === 'GET') {
    $userId = getSecureUserId();

    $page = intval($_GET['page'] ?? 1);
    $limit = intval($_GET['limit'] ?? 20);
    $offset = ($page - 1) * $limit;

    if (!$userId) {
        apiBadRequest('user_id 参数缺失');
    }

    $transactions = $db->query(
        "SELECT * FROM credit_transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?",
        [$userId, $limit, $offset]
    )->fetchAll();
    
    $result = array_map(function($t) {
        return [
            'id' => $t['id'],
            'type' => $t['type'],
            'amount' => intval($t['amount']),
            'balance_after' => intval($t['balance_after']),
            'description' => $t['description'],
            'created_at' => $t['created_at']
        ];
    }, $transactions);
    
    echo json_encode(['success' => true, 'data' => $result]);
    exit;
}

// 删除账号
if ($path === '/user/delete' && $method === 'DELETE') {
    $userId = getSecureUserId();

    if (!$userId) {
        apiBadRequest('user_id 参数缺失');
    }
    
    $user = $db->query("SELECT id FROM users WHERE id = ?", [$userId])->fetch();
    if (!$user) {
        apiNotFound('用户不存在');
    }
    
    $db->beginTransaction();
    try {
        $db->query("DELETE FROM devices WHERE user_id = ?", [$userId]);
        $db->query("DELETE FROM notifications WHERE user_id = ?", [$userId]);
        $db->query("DELETE FROM credit_transactions WHERE user_id = ?", [$userId]);
        $db->query("DELETE FROM orders WHERE user_id = ?", [$userId]);
        $db->query("DELETE FROM user_activity_logs WHERE user_id = ?", [$userId]);
        
        logUserActivity($db, $userId, 'delete_account', 'user', $userId);
        
        $db->query("DELETE FROM users WHERE id = ?", [$userId]);
        
        $db->commit();
        
        echo json_encode(['success' => true, 'message' => '账号已删除']);
    } catch (Exception $e) {
        $db->rollBack();
        apiServerError('删除账号失败');
    }
    exit;
}
