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
        "SELECT * FROM payment_configs WHERE min_amount <= ? AND max_amount >= ? AND is_active = 1 ORDER BY priority DESC LIMIT 1",
        [$amount, $amount]
    )->fetch();
    
    echo json_encode(['success' => true, 'data' => $config]);
    exit;
}

// 获取所有支付配置
if ($path === '/payment-configs' && $method === 'GET') {
    $configs = $db->query("SELECT * FROM payment_configs WHERE is_active = 1 ORDER BY priority DESC")->fetchAll();
    echo json_encode(['success' => true, 'data' => $configs]);
    exit;
}

// 获取HeroSMS余额
if ($path === '/herosms/balance' && $method === 'GET') {
    $balance = $heroSMS->getBalance();
    echo json_encode(['success' => true, 'data' => $balance]);
    exit;
}

// 同步数据
if ($path === '/sync' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $type = $input['type'] ?? null;
    
    if (!$type) {
        apiBadRequest('type 参数缺失');
    }
    
    $result = $heroSMS->sync($type);
    echo json_encode(['success' => true, 'data' => $result]);
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
        
        $db->query(
            "INSERT INTO credit_transactions (user_id, type, amount, balance_after, description) VALUES (?, 'topup', ?, (SELECT balance FROM users WHERE id = ?), ?)",
            [$userId, $points, $userId, "Apple IAP充值 - 交易号: " . ($transactionId ?? 'N/A') . ($isFirstTopup ? ' (首充双倍)' : '')]
        );
        
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
        
        $db->query(
            "INSERT INTO credit_transactions (user_id, type, amount, balance_after, description) VALUES (?, 'order', -?, (SELECT balance FROM users WHERE id = ?), ?)",
            [$userId, $totalCost, $userId, "批量订单"]
        );
        
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
    $coefficients = $db->query("SELECT * FROM price_coefficients WHERE is_default = 1")->fetchAll();
    echo json_encode(['success' => true, 'data' => $coefficients]);
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
        if ($serviceCoefficient !== null) {
            $db->query(
                "UPDATE price_coefficients SET value = ? WHERE type = 'service' AND is_default = 1",
                [$serviceCoefficient]
            );
        }
        
        if ($countryCoefficient !== null) {
            $db->query(
                "UPDATE price_coefficients SET value = ? WHERE type = 'country' AND is_default = 1",
                [$countryCoefficient]
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

// 获取服务系数
if ($path === '/coefficients/services' && $method === 'GET') {
    $coefficients = $db->query("SELECT * FROM price_coefficients WHERE type = 'service'")->fetchAll();
    echo json_encode(['success' => true, 'data' => $coefficients]);
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
    
    $basePrice = floatval($serviceCountry['base_price']);
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

// 获取推荐号码
if ($path === '/recommend/numbers' && $method === 'GET') {
    $serviceId = $_GET['service_id'] ?? null;
    $countryId = $_GET['country_id'] ?? null;
    $limit = intval($_GET['limit'] ?? 10);
    
    if (!$serviceId || !$countryId) {
        apiBadRequest('参数缺失');
    }
    
    $numbers = $heroSMS->getRecommendNumbers($serviceId, $countryId, $limit);
    echo json_encode(['success' => true, 'data' => $numbers]);
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
        "SELECT id, username, email, has_password FROM users WHERE id = ?",
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
    $userId = $input['user_id'] ?? null;
    $packageId = $input['package_id'] ?? null;
    $paymentMethod = $input['payment_method'] ?? 'apple_iap';
    
    if (!$userId) {
        $userId = getCurrentUserIdFromToken();
    }
    
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
        
        $totalCost = floatval($package['price']);
        $points = intval($package['points']);
        
        $paymentOrder = $db->query(
            "INSERT INTO payment_orders (user_id, package_id, amount, points, payment_method, status) VALUES (?, ?, ?, ?, ?, 'pending')",
            [$userId, $packageId, $totalCost, $points, $paymentMethod]
        );
        
        $orderId = $db->lastInsertId();
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
        apiServerError('创建支付订单失败');
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
        
        $db->query(
            "INSERT INTO credit_transactions (user_id, type, amount, balance_after, description) VALUES (?, 'topup', ?, (SELECT balance FROM users WHERE id = ?), ?)",
            [$userId, $order['points'], $userId, "充值订单 #$orderId"]
        );
        
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
        $txId = 'txn_' . bin2hex(random_bytes(8));
        $db->query(
            "INSERT INTO credit_transactions (id, user_id, type, amount, balance_after, description, created_at) VALUES (?, ?, 'bonus', ?, ?, '新用户注册奖励', NOW())",
            [$txId, $userId, $bonusAmount, $bonusAmount]
        );
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
    $packages = $db->query("SELECT id, product_id, config_name as name, credits as points, display_price as price, description, is_recommended, active FROM payment_configs WHERE active = 1 ORDER BY credits ASC")->fetchAll();
    
    // 将 is_recommended 和 active 转换为布尔值
    $packages = array_map(function($pkg) {
        $pkg['is_recommended'] = intval($pkg['is_recommended']) === 1;
        $pkg['active'] = intval($pkg['active']) === 1;
        return $pkg;
    }, $packages);
    
    echo json_encode(['success' => true, 'data' => $packages]);
    exit;
}

// 获取充值套餐（/topup-packages 别名）
if ($path === '/topup-packages' && $method === 'GET') {
    $packages = $db->query("SELECT id, product_id, config_name as name, credits as points, display_price as price, description, is_recommended, active FROM payment_configs WHERE active = 1 ORDER BY credits ASC")->fetchAll();
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
