<?php
/**
 * 支付相关路由
 */

// 获取支付配置
if ($path === '/payment-config/match' && $method === 'GET') {
    $amount = $_GET['amount'] ?? null;
    if (!$amount) {
        apiBadRequest('amount 参数缺失');
    }
    
    $config = $db->query(
        "SELECT * FROM payment_configs WHERE price <= ? AND price >= ? AND active = 1 ORDER BY sort_order ASC, id ASC LIMIT 1",
        [$amount, $amount]
    )->fetch();

    echo json_encode(['success' => true, 'data' => $config]);
    exit;
}

// 获取所有支付配置
if ($path === '/payment-configs' && $method === 'GET') {
    $configs = $db->query("SELECT * FROM payment_configs WHERE active = 1 ORDER BY sort_order ASC, id ASC")->fetchAll();
    echo json_encode(['success' => true, 'data' => $configs]);
    exit;
}

// 获取HeroSMS余额
if ($path === '/herosms/balance' && $method === 'GET') {
    $balance = $heroSMS->getBalance();
    echo json_encode(['success' => true, 'data' => $balance]);
    exit;
}

// 同步数据（同步 HeroSMS 服务/国家/价格到本地数据库）
if ($path === '/sync' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $type = $input['type'] ?? 'all';

    $synced = [];

    try {
        if ($type === 'services' || $type === 'all') {
            $list = $heroSMS->getServicesList();
            if (!empty($list['services'])) {
                foreach ($list['services'] as $code => $info) {
                    // 注意：services 表无 `active` 字段，使用 is_active 和 is_published
                    // 同时 hero_service_id 才是主键去重依据
                    $db->query(
                        "INSERT INTO services (hero_service_id, code, name, name_en, icon, is_active, is_published, sort_order, created_at, updated_at)
                         VALUES (?, ?, ?, ?, ?, 1, 1, 0, NOW(), NOW())
                         ON DUPLICATE KEY UPDATE
                           name = VALUES(name),
                           name_en = VALUES(name_en),
                           is_active = 1,
                           updated_at = NOW()",
                        [$code, $code, $info['name'] ?? $code, $info['name'] ?? $code, $info['icon'] ?? null]
                    );
                }
                $synced['services'] = count($list['services']);
            }
        }

        if ($type === 'countries' || $type === 'all') {
            $list = $heroSMS->getCountries();
            if (!empty($list['countries'])) {
                foreach ($list['countries'] as $id => $info) {
                    // HeroSMS API 实际返回字段: rus / eng / chn
                    // 我们用 eng 作为默认 name，chn 作为 name_cn，rus 作为 name
                    $engName = is_array($info) ? ($info['eng'] ?? '') : '';
                    $rusName = is_array($info) ? ($info['rus'] ?? '') : '';
                    $chnName = is_array($info) ? ($info['chn'] ?? '') : '';
                    $heroCountryId = is_array($info) ? ($info['id'] ?? $id) : $id;
                    $displayName = $chnName ?: $engName ?: $rusName ?: ('Country_' . $id);

                    // hero_country_id 才是去重依据（uk_hero_country_id 唯一键）
                    // id 是自增主键，不要手动指定
                    $db->query(
                        "INSERT INTO countries (hero_country_id, name, name_en, name_cn, code, active, sort_order, created_at, updated_at)
                         VALUES (?, ?, ?, ?, ?, 1, 0, NOW(), NOW())
                         ON DUPLICATE KEY UPDATE
                           name = VALUES(name),
                           name_en = VALUES(name_en),
                           name_cn = VALUES(name_cn),
                           code = VALUES(code),
                           active = 1,
                           updated_at = NOW()",
                        [(string)$heroCountryId, $displayName, $engName, $chnName, strtolower($engName)]
                    );
                }
                $synced['countries'] = count($list['countries']);
            }
        }

        if ($type === 'prices' || $type === 'all') {
            // 同步最新价格（占位实现：调用 getPrices 但仅更新 price 字段）
            $synced['prices'] = 'scheduled';
        }

        echo json_encode(['success' => true, 'data' => $synced]);
    } catch (Exception $e) {
        error_log("[/sync] failed: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => '同步失败: ' . $e->getMessage()]);
    }
    exit;
}

// 验证收据（兼容前端 /verify-receipt 和 /payment/verify-apple）
if ($path === '/verify-receipt' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $userId = getSecureUserId();
    $receipt = $input['receipt'] ?? $input['receipt_data'] ?? null;
    $transactionId = $input['transaction_id'] ?? null;
    $originalTransactionId = $input['original_transaction_id'] ?? null;
    $productId = $input['product_id'] ?? null;
    $purchaseDate = $input['purchase_date'] ?? null;

    if (!$userId) {
        apiUnauthorized('请先登录');
    }
    
    if (!$transactionId) {
        apiBadRequest('transaction_id 参数缺失');
    }
    
    $result = $appleIAP->verifyReceipt($receipt);
    
    if (!$result['success']) {
        apiBadRequest('验证失败: ' . $result['message']);
    }
    
    $db->beginTransaction();
    try {
        // 防重放攻击：检查 transaction_id 是否已使用
        $existingTransaction = $db->query(
            "SELECT id FROM apple_transactions WHERE transaction_id = ? OR (original_transaction_id IS NOT NULL AND original_transaction_id = ?)",
            [$transactionId, $transactionId]
        )->fetch();
        
        if ($existingTransaction) {
            $db->rollBack();
            apiBadRequest('此交易已处理过，请勿重复充值');
        }
        
        // 也检查 original_transaction_id（处理订阅续费等情况）
        if ($originalTransactionId && $originalTransactionId !== $transactionId) {
            $existingOriginal = $db->query(
                "SELECT id FROM apple_transactions WHERE transaction_id = ? OR original_transaction_id = ?",
                [$originalTransactionId, $originalTransactionId]
            )->fetch();
            
            if ($existingOriginal) {
                $db->rollBack();
                apiBadRequest('此交易已处理过，请勿重复充值');
            }
        }
        
        $package = null;
        if ($productId != null) {
            $package = $db->query("SELECT id, product_id, credits as points FROM payment_configs WHERE product_id = ? AND active = 1 LIMIT 1", [$productId])->fetch();
        }
        
        if (!$package) {
            $db->rollBack();
            apiBadRequest('充值套餐不存在或已下线，请联系客服');
        }
        
        $points = intval($package['points']);
        
        // 检查是否首充
        $user = $db->query("SELECT has_topup_history FROM users WHERE id = ?", [$userId])->fetch();
        $isFirstTopup = !$user['has_topup_history'];
        
        // 首充双倍积分
        if ($isFirstTopup) {
            $points = $points * 2;
        }
        
        $db->query(
            "UPDATE users SET balance = balance + ?, has_topup_history = 1 WHERE id = ?",
            [$points, $userId]
        );
        
        // 使用 insert() 让 Database 帮手自动生成 UUID（表主键是 varchar(36) 无 AUTO_INCREMENT）
        $balanceBeforeTopup = $user['balance'];
        $balanceAfterTopup = $balanceBeforeTopup + $points;
        $db->insert('credit_transactions', [
            'user_id' => $userId,
            'type' => 'topup',
            'amount' => $points,
            'balance_before' => $balanceBeforeTopup,
            'balance_after' => $balanceAfterTopup,
            'description' => "Apple IAP充值 - 交易号: " . ($transactionId ?? 'N/A') . ($isFirstTopup ? ' (首充双倍)' : ''),
        ]);
        
        // 记录交易到 apple_transactions 表
        $purchaseDateSql = $purchaseDate ? "'" . date('Y-m-d H:i:s', strtotime($purchaseDate)) . "'" : 'NOW()';
        $db->query(
            "INSERT INTO apple_transactions (user_id, transaction_id, original_transaction_id, product_id, purchase_date, points_awarded, is_first_topup) VALUES (?, ?, ?, ?, $purchaseDateSql, ?, ?)",
            [$userId, $transactionId, $originalTransactionId, $productId, $points, $isFirstTopup ? 1 : 0]
        );
        
        $db->commit();
        
        $user = $db->query("SELECT balance FROM users WHERE id = ?", [$userId])->fetch();
        
        logUserActivity($db, $userId, 'complete_payment', 'payment', null, ['transaction_id' => $transactionId]);
        
        echo json_encode([
            'success' => true,
            'data' => [
                'order_id' => $transactionId,
                'points' => $points,
                'new_balance' => intval($user['balance']),
                'is_first_topup' => $isFirstTopup
            ]
        ]);
    } catch (Exception $e) {
        $db->rollBack();
        apiServerError('充值失败');
    }
    exit;
}

// 批量创建订单
if ($path === '/orders/batch' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $orders = $input['orders'] ?? [];
    $userId = $input['user_id'] ?? null;
    
    if (!$userId) {
        $userId = getCurrentUserIdFromToken();
    }
    
    if (empty($orders) || !$userId) {
        apiBadRequest('参数缺失');
    }
    
    $db->beginTransaction();
    try {
        $user = $db->query("SELECT balance FROM users WHERE id = ?", [$userId])->fetch();
        if (!$user) {
            apiNotFound('用户不存在');
        }
        
        $totalCost = 0;
        $createdOrders = [];
        
        foreach ($orders as $orderData) {
            $serviceId = $orderData['service_id'] ?? null;
            $countryId = $orderData['country_id'] ?? null;
            $quantity = intval($orderData['quantity'] ?? 1);
            $pricePoints = $orderData['price_points'] ?? null;
            
            if (!$serviceId || !$countryId) {
                continue;
            }
            
            if ($pricePoints === null) {
                $pricePoints = calculateServicePricePoints($db, $serviceId, $countryId, $userId);
            }
            
            $orderCost = $pricePoints * $quantity;
            $totalCost += $orderCost;
            
            $orderId = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0x0fff) | 0x4000,
                mt_rand(0, 0x3fff) | 0x8000,
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
            );
            
            $db->query(
                "INSERT INTO orders (id, user_id, service_id, country_id, quantity, price, total_cost, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')",
                [$orderId, $userId, $serviceId, $countryId, $quantity, $pricePoints, $orderCost]
            );
            $createdOrders[] = [
                'id' => $orderId,
                'service_id' => $serviceId,
                'country_id' => $countryId,
                'quantity' => $quantity,
                'price_points' => $pricePoints,
                'total_cost' => $orderCost,
                'status' => 'pending'
            ];
        }
        
        if ($user['balance'] < $totalCost) {
            $db->rollBack();
            apiBadRequest('积分不足');
        }
        
        $db->query(
            "UPDATE users SET balance = balance - ? WHERE id = ?",
            [$totalCost, $userId]
        );

        // 使用 insert() 自动生成 UUID 主键（避免手写 SQL 缺 id 失败）
        $balanceBeforeBatch = $user['balance'] - $totalCost;
        $db->insert('credit_transactions', [
            'user_id' => $userId,
            'type' => 'order',
            'amount' => -$totalCost,
            'balance_before' => $balanceBeforeBatch + $totalCost,
            'balance_after' => $balanceBeforeBatch,
            'description' => "批量订单",
        ]);
        
        $db->commit();
        
        logUserActivity($db, $userId, 'batch_create_orders', 'order', null, ['count' => count($createdOrders)]);
        
        echo json_encode([
            'success' => true,
            'data' => [
                'orders' => $createdOrders,
                'total_cost' => $totalCost
            ]
        ]);
    } catch (Exception $e) {
        $db->rollBack();
        apiServerError('创建订单失败');
    }
    exit;
}

// 获取短信
if ($path === '/sms' && $method === 'GET') {
    $orderId = $_GET['order_id'] ?? null;
    
    if (!$orderId) {
        apiBadRequest('order_id 参数缺失');
    }
    
    $order = $db->query("SELECT * FROM orders WHERE id = ?", [$orderId])->fetch();
    if (!$order) {
        apiNotFound('订单不存在');
    }
    
    requireOrderOwner($order);
    
    if ($order['status'] !== 'active') {
        apiBadRequest('订单未激活');
    }
    
    $result = $heroSMS->getSms($order['hero_order_id']);
    
    if (!$result['success']) {
        echo json_encode(['success' => true, 'data' => ['sms' => null, 'code' => null]]);
        exit;
    }
    
    $sms = $result['sms'] ?? null;
    $code = $sms ? extractVerificationCode($sms) : null;
    
    echo json_encode([
        'success' => true,
        'data' => [
            'sms' => $sms,
            'code' => $code
        ]
    ]);
    exit;
}

// 获取统计数据
if ($path === '/stats' && $method === 'GET') {
    $userId = $_GET['user_id'] ?? null;
    
    if (!$userId) {
        $userId = getCurrentUserIdFromToken();
    }
    
    if (!$userId) {
        apiBadRequest('user_id 参数缺失');
    }
    
    $stats = $db->query(
        "SELECT 
            COUNT(*) as total_orders,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_orders,
            SUM(total_cost) as total_spent
         FROM orders WHERE user_id = ?",
        [$userId]
    )->fetch();
    
    echo json_encode(['success' => true, 'data' => $stats]);
    exit;
}

// 获取用户余额
if ($path === '/user/balance' && $method === 'GET') {
    $userId = $_GET['user_id'] ?? null;
    
    if (!$userId) {
        $userId = getCurrentUserIdFromToken();
    }
    
    if (!$userId) {
        apiBadRequest('user_id 参数缺失');
    }
    
    $user = $db->query("SELECT balance FROM users WHERE id = ?", [$userId])->fetch();
    if (!$user) {
        apiNotFound('用户不存在');
    }
    
    echo json_encode(['success' => true, 'data' => ['balance' => intval($user['balance'])]]);
    exit;
}

// 获取会员等级列表
if ($path === '/membership/levels' && $method === 'GET') {
    $levels = $db->query("SELECT * FROM membership_levels WHERE active = 1 ORDER BY min_spent ASC")->fetchAll();
    echo json_encode(['success' => true, 'data' => $levels]);
    exit;
}

// 获取用户会员信息
if ($path === '/user/membership' && $method === 'GET') {
    $userId = $_GET['user_id'] ?? null;
    
    if (!$userId) {
        $userId = getCurrentUserIdFromToken();
    }
    
    if (!$userId) {
        apiBadRequest('user_id 参数缺失');
    }
    
    $user = $db->query("SELECT total_spent FROM users WHERE id = ?", [$userId])->fetch();
    if (!$user) {
        apiNotFound('用户不存在');
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
    
    echo json_encode([
        'success' => true,
        'data' => [
            'current' => $membership,
            'next' => $nextLevel,
            'total_spent' => $totalSpent
        ]
    ]);
    exit;
}

// 注册设备
if ($path === '/devices/register' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $deviceId = $input['device_id'] ?? null;
    $userId = $input['user_id'] ?? null;
    $deviceType = $input['device_type'] ?? 'ios';
    $pushToken = $input['push_token'] ?? null;
    
    if (!$userId) {
        $userId = getCurrentUserIdFromToken();
    }
    
    if (!$deviceId || !$userId) {
        apiBadRequest('参数缺失');
    }
    
    $db->query(
        "INSERT INTO devices (user_id, device_id, device_type, push_token, last_active) VALUES (?, ?, ?, ?, NOW()) 
         ON DUPLICATE KEY UPDATE push_token = VALUES(push_token), last_active = NOW()",
        [$userId, $deviceId, $deviceType, $pushToken]
    );
    
    echo json_encode(['success' => true, 'message' => '设备注册成功']);
    exit;
}

// 获取积分套餐
if ($path === '/points/packages' && $method === 'GET') {
    $packages = $db->query("SELECT * FROM topup_packages WHERE is_active = 1 ORDER BY sort_order ASC")->fetchAll();
    echo json_encode(['success' => true, 'data' => $packages]);
    exit;
}

// 获取服务价格
if ($path === '/services/price' && $method === 'GET') {
    $serviceId = $_GET['service_id'] ?? null;
    $countryId = $_GET['country_id'] ?? null;
    $userId = $_GET['user_id'] ?? null;
    
    if (!$userId) {
        $userId = getCurrentUserIdFromToken();
    }
    
    if (!$serviceId || !$countryId) {
        apiBadRequest('参数缺失');
    }
    
    $pricePoints = calculateServicePricePoints($db, $serviceId, $countryId, $userId);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'service_id' => $serviceId,
            'country_id' => $countryId,
            'price_points' => $pricePoints
        ]
    ]);
    exit;
}

// 获取默认系数
if ($path === '/coefficients/default' && $method === 'GET') {
    // 用 system_settings 读默认系数（price_coefficients 表在 database.sql 中不存在）
    $before = floatval(getSetting($db, 'default_coefficient_before', '4'));
    $after = floatval(getSetting($db, 'default_coefficient_after', '4.5'));
    echo json_encode([
        'success' => true,
        'data' => [
            'service_coefficient' => $before,
            'country_coefficient' => $after,
            'coefficient_before' => $before,
            'coefficient_after' => $after,
        ]
    ]);
    exit;
}

// 更新默认系数
if ($path === '/coefficients/default' && $method === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);
    $serviceCoefficient = $input['service_coefficient'] ?? null;
    $countryCoefficient = $input['country_coefficient'] ?? null;
    
    if ($serviceCoefficient === null && $countryCoefficient === null) {
        apiBadRequest('参数缺失');
    }
    
    $db->beginTransaction();
    try {
        // 用 system_settings 存默认系数（price_coefficients 表在 database.sql 中不存在）
        if ($serviceCoefficient !== null) {
            $db->query(
                "INSERT INTO system_settings (`key`, value, type, description, updated_at)
                 VALUES ('default_coefficient_before', ?, 'number', '服务默认系数（首充前）', NOW())
                 ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = NOW()",
                [strval($serviceCoefficient)]
            );
        }

        if ($countryCoefficient !== null) {
            $db->query(
                "INSERT INTO system_settings (`key`, value, type, description, updated_at)
                 VALUES ('default_coefficient_after', ?, 'number', '服务默认系数（首充后）', NOW())
                 ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = NOW()",
                [strval($countryCoefficient)]
            );
        }

        $db->commit();
        echo json_encode(['success' => true, 'message' => '系数更新成功']);
    } catch (Exception $e) {
        $db->rollBack();
        apiServerError('更新失败');
    }
    exit;
}

// 获取服务系数（合并 service_coefficients 表 + system_settings 默认值）
if ($path === '/coefficients/services' && $method === 'GET') {
    $rows = $db->query("SELECT service_id, coefficient_before, coefficient_after FROM service_coefficients")->fetchAll();
    $defaultBefore = floatval(getSetting($db, 'default_coefficient_before', '4'));
    $defaultAfter = floatval(getSetting($db, 'default_coefficient_after', '4.5'));
    echo json_encode([
        'success' => true,
        'data' => [
            'services' => $rows,
            'default' => [
                'coefficient_before' => $defaultBefore,
                'coefficient_after' => $defaultAfter,
            ],
        ]
    ]);
    exit;
}

// 获取计算后的服务价格
if ($path === '/services/price/calculated' && $method === 'GET') {
    $serviceId = $_GET['service_id'] ?? null;
    $countryId = $_GET['country_id'] ?? null;
    $userId = $_GET['user_id'] ?? null;
    
    if (!$userId) {
        $userId = getCurrentUserIdFromToken();
    }
    
    if (!$serviceId || !$countryId) {
        apiBadRequest('参数缺失');
    }
    
    $serviceCountry = $db->query(
        "SELECT sc.*, s.price_coefficient as service_coefficient, c.price_coefficient as country_coefficient
         FROM service_countries sc
         LEFT JOIN services s ON sc.service_id = s.id
         LEFT JOIN countries c ON sc.country_id = c.id
         WHERE sc.service_id = ? AND sc.country_id = ? AND sc.is_published = 1 AND sc.active = 1",
        [$serviceId, $countryId]
    )->fetch();
    
    if (!$serviceCountry) {
        apiNotFound('服务国家组合不存在');
    }
    
    $basePrice = floatval($serviceCountry['price']); // service_countries 没有 base_price 字段，用 price
    $serviceCoefficient = floatval($serviceCountry['service_coefficient'] ?? 1.0);
    $countryCoefficient = floatval($serviceCountry['country_coefficient'] ?? 1.0);
    
    $finalPrice = $basePrice * $serviceCoefficient * $countryCoefficient;
    
    if ($userId) {
        $user = $db->query("SELECT total_spent FROM users WHERE id = ?", [$userId])->fetch();
        if ($user) {
            $totalSpent = floatval($user['total_spent']);
            $membership = $db->query(
                "SELECT discount FROM membership_levels WHERE min_spent <= ? AND active = 1 ORDER BY min_spent DESC LIMIT 1",
                [$totalSpent]
            )->fetch();
            
            if ($membership) {
                $discount = floatval($membership['discount']);
                $finalPrice = $finalPrice * (1 - $discount / 100);
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'base_price' => $basePrice,
            'service_coefficient' => $serviceCoefficient,
            'country_coefficient' => $countryCoefficient,
            'final_price' => intval(ceil($finalPrice))
        ]
    ]);
    exit;
}

// 获取推荐号码（HeroSMS 不支持推荐号码接口，返回当前服务可用国家作为推荐依据）
if ($path === '/recommend/numbers' && $method === 'GET') {
    $serviceId = $_GET['service_id'] ?? null;
    $countryId = $_GET['country_id'] ?? null;
    $limit = intval($_GET['limit'] ?? 10);

    if (!$serviceId || !$countryId) {
        apiBadRequest('参数缺失');
    }

    // 用库存 + 价格做"推荐"（库存充足的号码默认推荐）
    $stock = $heroSMS->checkStock($serviceId, intval($countryId));
    $price = $db->query(
        "SELECT price FROM service_countries WHERE service_id = ? AND country_id = ? AND active = 1",
        [$serviceId, $countryId]
    )->fetchColumn();

    echo json_encode([
        'success' => true,
        'data' => [
            'stock' => $stock,
            'price' => floatval($price ?? 0),
            'recommend_count' => $limit,
            'note' => 'HeroSMS 不暴露推荐号码接口，请直接调 /orders/{id}/activate 获取号码',
        ]
    ]);
    exit;
}

// 绑定邮箱（不校验验证码）
if ($path === '/auth/bind-email' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $userId = $input['user_id'] ?? null;
    $email = $input['email'] ?? null;
    
    if (!$userId) {
        $userId = getCurrentUserIdFromToken();
    }
    
    if (!$userId || !$email) {
        apiBadRequest('参数缺失');
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        apiBadRequest('邮箱格式不正确');
    }
    
    $db->query("UPDATE users SET email = ? WHERE id = ?", [$email, $userId]);
    
    logUserActivity($db, $userId, 'bind_email', 'auth', $userId);
    
    echo json_encode(['success' => true, 'message' => '邮箱绑定成功']);
    exit;
}

// 获取账号信息
if ($path === '/auth/account-info' && $method === 'GET') {
    $userId = $_GET['user_id'] ?? null;
    
    if (!$userId) {
        $userId = getCurrentUserIdFromToken();
    }
    
    if (!$userId) {
        apiBadRequest('user_id 参数缺失');
    }
    
    $user = $db->query(
        "SELECT id, username, email,
                (password_hash IS NOT NULL AND password_hash != '') AS has_password
         FROM users WHERE id = ?",
        [$userId]
    )->fetch();
    
    if (!$user) {
        apiNotFound('用户不存在');
    }
    
    echo json_encode(['success' => true, 'data' => $user]);
    exit;
}

// 获取已发布的服务国家组合 - 此实现与 routes/services.php 重复且功能较弱
// 已在 routes/services.php 加载时优先匹配，此处删除避免死代码
// 保留位置以避免其他路由顺序错乱，无操作

// 创建支付订单
if ($path === '/payment/create' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $userId = getCurrentUserIdFromToken(); // 安全修复：只从 token 取
    $packageId = $input['package_id'] ?? null;
    $paymentMethod = $input['payment_method'] ?? 'apple_iap';

    if (!$userId || !$packageId) {
        apiBadRequest('参数缺失');
    }

    $db->beginTransaction();
    try {
        $user = $db->query("SELECT balance FROM users WHERE id = ?", [$userId])->fetch();
        if (!$user) {
            apiNotFound('用户不存在');
        }

        $package = $db->query("SELECT * FROM topup_packages WHERE id = ? AND is_active = 1", [$packageId])->fetch();
        if (!$package) {
            apiNotFound('套餐不存在');
        }

        // topup_packages 表没有 price 列，按 product_id 在 payment_configs 里反查价格
        $productRow = $db->query("SELECT price FROM payment_configs WHERE product_id = ?", [$package['product_id']])->fetch();
        $totalCost = floatval($productRow['price'] ?? 0);
        $points = intval($package['points']);
        
        // payment_orders.id 是 varchar(36) 主键没有 AUTO_INCREMENT，PHP 端生成 UUID
        $orderId = sprintf('%08x-%04x-%04x-%04x-%012x',
            mt_rand(0, 0xffffffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff), mt_rand(0, 0xffffffffffff));
        $paymentOrder = $db->query(
            "INSERT INTO payment_orders (id, user_id, package_id, amount, points, payment_method, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')",
            [$orderId, $userId, $packageId, $totalCost, $points, $paymentMethod]
        );
        $db->commit();
        
        logUserActivity($db, $userId, 'create_payment_order', 'payment', $orderId);
        
        echo json_encode([
            'success' => true,
            'data' => [
                'id' => $orderId,
                'package_id' => $packageId,
                'amount' => $totalCost,
                'points' => $points,
                'payment_method' => $paymentMethod,
                'status' => 'pending'
            ]
        ]);
    } catch (Exception $e) {
        $db->rollBack();
        error_log("[/payment/create] failed: " . $e->getMessage() . " at " . basename($e->getFile()) . ":" . $e->getLine());
        // 调试阶段：透传 detail
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error' => '创建支付订单失败',
            'detail' => get_class($e) . ': ' . $e->getMessage() . ' at ' . basename($e->getFile()) . ':' . $e->getLine(),
        ]);
    }
    exit;
}

// Apple支付验证
if ($path === '/payment/verify-apple' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $userId = $input['user_id'] ?? null;
    $receipt = $input['receipt'] ?? null;
    $orderId = $input['order_id'] ?? null;
    
    if (!$userId) {
        $userId = getCurrentUserIdFromToken();
    }
    
    if (!$userId || !$receipt) {
        apiBadRequest('参数缺失');
    }
    
    $result = $appleIAP->verifyReceipt($receipt);
    
    if (!$result['success']) {
        apiBadRequest('验证失败: ' . $result['message']);
    }
    
    $db->beginTransaction();
    try {
        $order = $db->query("SELECT * FROM payment_orders WHERE id = ? AND user_id = ?", [$orderId, $userId])->fetch();
        if (!$order) {
            apiNotFound('订单不存在');
        }
        
        if ($order['status'] !== 'pending') {
            apiBadRequest('订单状态不正确');
        }
        
        $db->query("UPDATE payment_orders SET status = 'completed', completed_at = NOW() WHERE id = ?", [$orderId]);
        
        $db->query(
            "UPDATE users SET balance = balance + ?, has_topup_history = 1 WHERE id = ?",
            [$order['points'], $userId]
        );

        // 使用 insert() 自动生成 UUID 主键
        $balanceBeforeManual = $db->query("SELECT balance FROM users WHERE id = ?", [$userId])->fetchColumn();
        $balanceAfterManual = $balanceBeforeManual;
        $db->insert('credit_transactions', [
            'user_id' => $userId,
            'type' => 'topup',
            'amount' => $order['points'],
            'balance_before' => $balanceAfterManual - $order['points'],
            'balance_after' => $balanceAfterManual,
            'description' => "充值订单 #$orderId",
        ]);
        
        $db->commit();
        
        logUserActivity($db, $userId, 'complete_payment', 'payment', $orderId);
        
        echo json_encode([
            'success' => true,
            'data' => [
                'order_id' => $orderId,
                'points' => $order['points']
            ]
        ]);
    } catch (Exception $e) {
        $db->rollBack();
        apiServerError('充值失败');
    }
    exit;
}

// 手动注册
if ($path === '/auth/manual-register' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $username = $input['username'] ?? null;
    $password = $input['password'] ?? null;
    $email = $input['email'] ?? null;
    $deviceId = $input['device_id'] ?? null;
    
    if (!$password) {
        apiBadRequest('密码不能为空');
    }
    
    if (!$email) {
        apiBadRequest('邮箱不能为空');
    }
    
    if (strlen($password) < 8) {
        apiBadRequest('密码长度至少8位');
    }
    
    // 如果没有提供用户名，从邮箱生成一个
    if (!$username) {
        $username = explode('@', $email)[0];
    }
    
    $existingUser = $db->query(
        "SELECT id FROM users WHERE username = ? OR email = ?",
        [$username, $email]
    )->fetch();
    
    if ($existingUser) {
        apiBadRequest('用户名或邮箱已存在');
    }
    
    $passwordHash = password_hash($password, PASSWORD_BCRYPT);
    
    // 自动生成昵称（格式：User_随机6位数字）
    $nickname = 'User_' . mt_rand(100000, 999999);
    
    // 检查设备ID是否已注册过账号（用于防刷）
    $isNewDevice = true;
    if ($deviceId) {
        $existingDevice = $db->query(
            "SELECT id FROM users WHERE device_id = ? LIMIT 1",
            [$deviceId]
        )->fetch();
        if ($existingDevice) {
            $isNewDevice = false;
        }
    }
    
    // 获取注册奖励配置
    $bonusMin = intval(getSetting($db, 'register_bonus_min', '5'));
    $bonusMax = intval(getSetting($db, 'register_bonus_max', '10'));
    $bonusAmount = mt_rand($bonusMin, $bonusMax);
    
    // 只有首次绑定设备ID的用户才能获得注册奖励
    $initialBalance = ($isNewDevice && $deviceId) ? $bonusAmount : 0;
    
    // 生成 UUID 作为用户 ID
    $userId = sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
    
    $db->query(
        "INSERT INTO users (id, username, password_hash, email, device_id, nickname, balance, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())",
        [$userId, $username, $passwordHash, $email, $deviceId, $nickname, $initialBalance]
    );
    
    $token = Auth::generateToken($userId);
    
    // 如果是新设备，记录积分奖励交易
    if ($isNewDevice && $deviceId && $bonusAmount > 0) {
        $db->insert('credit_transactions', [
            'user_id' => $userId,
            'type' => 'bonus',
            'amount' => $bonusAmount,
            'balance_before' => 0,
            'balance_after' => $bonusAmount,
            'description' => '新用户注册奖励',
        ]);
    }
    
    logUserActivity($db, $userId, 'manual_register', 'auth', $userId);

    // 返回 credentials 让前端能展示给用户（仅首次返回，永不持久化）
    echo json_encode([
        'success' => true,
        'credentials' => [
            'username' => $username,
            'password' => $password,
            'email' => $email
        ],
        'user' => [
            'id' => $userId,
            'username' => $username,
            'email' => $email,
            'nickname' => $nickname,
            'balance' => $initialBalance,
            'is_new_device' => $isNewDevice
        ],
        'token' => $token
    ]);
    exit;
}

// 刷新Token
if ($path === '/auth/refresh' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $refreshToken = $input['refresh_token'] ?? null;
    
    if (!$refreshToken) {
        apiBadRequest('refresh_token 参数缺失');
    }
    
    $tokenData = Auth::verifyToken($refreshToken);
    if ($tokenData === false) {
        apiUnauthorized('Token无效或已过期');
    }
    
    $newToken = Auth::generateToken($tokenData['user_id']);
    
    echo json_encode([
        'success' => true,
        'token' => $newToken
    ]);
    exit;
}

// 获取横幅
if ($path === '/banners' && $method === 'GET') {
    $banners = $db->query("SELECT id, name, image_url, link_url, is_enabled, sort_order, created_at, updated_at FROM banners WHERE is_enabled = 1 ORDER BY sort_order ASC")->fetchAll();
    echo json_encode(['success' => true, 'data' => $banners]);
    exit;
}

// 获取支付套餐（/payment/packages 别名，兼容前端调用）
// 查询 payment_configs 表（运营后台配置的表）
if ($path === '/payment/packages' && $method === 'GET') {
    $packages = $db->query("SELECT id, product_id, config_name as name, credits as points, price as price, description, active FROM payment_configs WHERE active = 1 ORDER BY credits ASC")->fetchAll();

    // active 字段转布尔（payment_configs 表没有 is_recommended 列）
    $packages = array_map(function($pkg) {
        $pkg['is_recommended'] = false; // 默认不推荐
        $pkg['active'] = intval($pkg['active']) === 1;
        return $pkg;
    }, $packages);
    
    echo json_encode(['success' => true, 'data' => $packages]);
    exit;
}

// 获取充值套餐（/topup-packages 别名）
if ($path === '/topup-packages' && $method === 'GET') {
    $packages = $db->query("SELECT id, product_id, config_name as name, credits as points, price, description, active FROM payment_configs WHERE active = 1 ORDER BY credits ASC")->fetchAll();

    // active 字段转布尔，便于前端判断
    $packages = array_map(function($pkg) {
        $pkg['is_recommended'] = false;
        $pkg['active'] = intval($pkg['active']) === 1;
        return $pkg;
    }, $packages);

    echo json_encode(['success' => true, 'data' => $packages]);
    exit;
}

// 获取系统设置（/settings 别名，兼容前端调用）
if ($path === '/settings' && $method === 'GET') {
    $settings = $db->query("SELECT * FROM system_settings")->fetchAll();
    $result = [];
    foreach ($settings as $setting) {
        $result[$setting['key']] = $setting['value'];
    }
    echo json_encode(['success' => true, 'data' => $result]);
    exit;
}
