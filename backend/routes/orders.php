<?php
/**
 * 订单相关路由
 */

// 获取订单列表
if (preg_match('/^\/orders$/', $path) && $method === 'GET') {
    $userId = getSecureUserId();

    $limit = intval($_GET['limit'] ?? 20);
    $offset = intval($_GET['offset'] ?? 0);
    $status = $_GET['status'] ?? null;

    if (!$userId) {
        apiBadRequest('user_id 参数缺失');
    }

    $sql = "SELECT o.*, s.name as service_name, s.name_en as service_name_en, c.name as country_name, c.name_en as country_name_en, s.icon as service_icon, c.flag as country_flag,
               COALESCE(o.total_price, o.total_cost, 0) as total_cost, ROUND(COALESCE(o.total_price, o.total_cost, 0) / o.quantity) as price_points,
               (SELECT code FROM sms_messages WHERE order_id = o.id ORDER BY received_at ASC LIMIT 1) as sms_code,
               (SELECT received_at FROM sms_messages WHERE order_id = o.id ORDER BY received_at ASC LIMIT 1) as sms_received_at
        FROM orders o
        LEFT JOIN services s ON o.service_id = s.id
        LEFT JOIN countries c ON o.country_id = c.id
        WHERE o.user_id = ?";
    $params = [$userId];

    if ($status) {
        $sql .= " AND o.status = ?";
        $params[] = $status;
    }

    $sql .= " ORDER BY o.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;

    $orders = $db->query($sql, $params)->fetchAll();

    $orders = array_map(function($order) {
        if (!empty($order['service_icon'])) {
            $order['service_icon'] = getLocalImageUrl($order['service_icon'], '/pic/fuwu/');
        }
        if (!empty($order['country_flag'])) {
            $order['country_flag'] = getLocalImageUrl($order['country_flag'], '/pic/country/');
        }
        return $order;
    }, $orders);

    echo json_encode(['success' => true, 'data' => $orders]);
    exit;
}

// 创建订单
if ($path === '/orders' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $serviceId = $input['service_id'] ?? null;
    $countryId = $input['country_id'] ?? null;
    $quantity = intval($input['quantity'] ?? 1);
    $userId = $input['user_id'] ?? null;
    $pricePoints = $input['price_points'] ?? null;
    
    if (!$userId) {
        $userId = getCurrentUserIdFromToken();
    }
    
    if (!$serviceId || !$countryId || !$userId) {
        apiBadRequest('参数缺失');
    }

    $currentUserId = getCurrentUserIdFromToken();
    if ($currentUserId && $userId != $currentUserId) {
        apiUnauthorized('无权为其他用户下单');
    }

    $db->beginTransaction();
    try {
        $user = $db->query("SELECT balance FROM users WHERE id = ?", [$userId])->fetch();
        if (!$user) {
            apiNotFound('用户不存在');
        }

        // 永远以服务端计算的 price_points 为准，忽略前端传来的值
        $pricePoints = calculateServicePricePoints($db, $serviceId, $countryId, $userId);

        $totalCost = $pricePoints * $quantity;
        
        // 获取真实美元成本价
        $usdPrice = $db->query(
            "SELECT price FROM service_countries WHERE service_id = ? AND country_id = ? AND is_published = 1 AND active = 1 LIMIT 1",
            [$serviceId, $countryId]
        )->fetchColumn();
        
        $realCostPrice = $usdPrice ? round(floatval($usdPrice) * $quantity, 4) : ($totalCost * 0.7);
        $realProfit = $totalCost - ($realCostPrice * 100);
        
        if ($user['balance'] < $totalCost) {
            apiBadRequest('积分不足');
        }
        
        $orderId = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
        
        $db->query(
            "INSERT INTO orders (id, user_id, service_id, country_id, quantity, total_price, cost_price, profit, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')",
            [$orderId, $userId, $serviceId, $countryId, $quantity, $totalCost, $realCostPrice, $realProfit]
        );
        
        $db->query(
            "UPDATE users SET balance = balance - ? WHERE id = ?",
            [$totalCost, $userId]
        );
        
        $balanceAfter = $db->query("SELECT balance FROM users WHERE id = ?", [$userId])->fetchColumn();
        
        $txnId = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
        
        $db->query(
            "INSERT INTO credit_transactions (id, user_id, type, amount, balance_after, description) VALUES (?, ?, 'order', ?, ?, ?)",
            [$txnId, $userId, -$totalCost, $balanceAfter, "订单 #$orderId"]
        );
        
        $db->commit();
        
        logUserActivity($db, $userId, 'create_order', 'order', $orderId);
        
        echo json_encode([
            'success' => true,
            'data' => [
                'id' => $orderId,
                'service_id' => $serviceId,
                'country_id' => $countryId,
                'quantity' => $quantity,
                'price_points' => $pricePoints,
                'total_cost' => $totalCost,
                'status' => 'pending'
            ]
        ]);
    } catch (Exception $e) {
        $db->rollBack();
        apiServerError('创建订单失败');
    }
    exit;
}

// 激活订单（必须在 /orders/(.+) 之前）
if (preg_match('/^\/orders\/(.+)\/activate$/', $path, $matches) && $method === 'POST') {
    $orderId = $matches[1];
    
    $order = $db->query("SELECT * FROM orders WHERE id = ?", [$orderId])->fetch();
    if (!$order) {
        apiNotFound('订单不存在');
    }
    
    requireOrderOwner($order);
    
    if ($order['status'] !== 'pending') {
        apiBadRequest('订单状态不正确');
    }
    
    $heroInfo = $db->query(
        "SELECT s.code as service_code, c.hero_country_id 
         FROM orders o 
         LEFT JOIN services s ON o.service_id = s.id 
         LEFT JOIN countries c ON o.country_id = c.id 
         WHERE o.id = ?",
        [$orderId]
    )->fetch();
    
    if (!$heroInfo || empty($heroInfo['service_code']) || empty($heroInfo['hero_country_id'])) {
        apiServerError('服务或国家配置错误');
    }
    
    $result = $heroSMS->getNumber($heroInfo['service_code'], $heroInfo['hero_country_id']);
    
    if (!$result['success']) {
        $db->query("UPDATE orders SET status = 'failed', error_message = ? WHERE id = ?", [$result['message'], $orderId]);
        
        $refundTxnId = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
        
        $refundAmount = intval($order['total_price']);
        $db->query(
            "UPDATE users SET balance = balance + ? WHERE id = ?",
            [$refundAmount, $order['user_id']]
        );
        
        $refundBalanceAfter = $db->query("SELECT balance FROM users WHERE id = ?", [$order['user_id']])->fetchColumn();
        
        $db->query(
            "INSERT INTO credit_transactions (id, user_id, type, amount, balance_after, description) VALUES (?, ?, 'refund', ?, ?, ?)",
            [$refundTxnId, $order['user_id'], $refundAmount, $refundBalanceAfter, "订单 #$orderId 激活失败退款"]
        );
        
        apiError('激活失败: ' . $result['message'], 400, 'activation_failed', [
            'order_id' => $orderId,
            'refunded' => true
        ]);
    }

    // 通知 HeroSMS 开始等待短信
    $heroStatusResult = $heroSMS->setStatus($result['heroOrderId'], 1);
    if (!$heroStatusResult['success']) {
        error_log("HeroSMS setStatus(1) failed for order $orderId: " . ($heroStatusResult['message'] ?? 'unknown'));
        // 不阻塞流程，即使 setStatus 失败也继续
    }

    $db->query(
        "UPDATE orders SET status = 'active', phone_number = ?, hero_order_id = ?, activated_at = NOW(), expires_at = DATE_ADD(NOW(), INTERVAL 20 MINUTE) WHERE id = ?",
        [$result['phoneNumber'], $result['heroOrderId'], $orderId]
    );
    
    $expiresAt = $db->query("SELECT expires_at FROM orders WHERE id = ?", [$orderId])->fetchColumn();
    
    logUserActivity($db, $order['user_id'], 'activate_order', 'order', $orderId);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'id' => $orderId,
            'phone_number' => $result['phoneNumber'],
            'status' => 'active',
            'expires_at' => $expiresAt
        ]
    ]);
    exit;
}

// 获取订单短信（必须在 /orders/(.+) 之前）
if (preg_match('/^\/orders\/(.+)\/sms$/', $path, $matches) && $method === 'GET') {
    $orderId = $matches[1];
    
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

// 取消订单（必须在 /orders/(.+) 之前）
if (preg_match('/^\/orders\/(.+)\/cancel$/', $path, $matches) && $method === 'POST') {
    $orderId = $matches[1];
    
    $order = $db->query("SELECT * FROM orders WHERE id = ?", [$orderId])->fetch();
    if (!$order) {
        apiNotFound('订单不存在');
    }
    
    requireOrderOwner($order);
    
    if ($order['status'] !== 'pending') {
        apiBadRequest('订单状态不正确');
    }
    
    $db->beginTransaction();
    try {
        $db->query("UPDATE orders SET status = 'cancelled' WHERE id = ?", [$orderId]);
        
        $cancelTxnId = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
        
        $cancelRefundAmount = intval($order['total_price']);
        $db->query(
            "UPDATE users SET balance = balance + ? WHERE id = ?",
            [$cancelRefundAmount, $order['user_id']]
        );
        
        $cancelBalanceAfter = $db->query("SELECT balance FROM users WHERE id = ?", [$order['user_id']])->fetchColumn();
        
        $db->query(
            "INSERT INTO credit_transactions (id, user_id, type, amount, balance_after, description) VALUES (?, ?, 'refund', ?, ?, ?)",
            [$cancelTxnId, $order['user_id'], $cancelRefundAmount, $cancelBalanceAfter, "订单 #$orderId 取消退款"]
        );
        
        $db->commit();
        
        logUserActivity($db, $order['user_id'], 'cancel_order', 'order', $orderId);
        
        echo json_encode(['success' => true, 'message' => '订单已取消']);
    } catch (Exception $e) {
        $db->rollBack();
        apiServerError('取消订单失败');
    }
    exit;
}

// 获取订单详情（必须放在子路由之后）
if (preg_match('/^\/orders\/([a-f0-9\-]+)$/', $path, $matches) && $method === 'GET') {
    $orderId = $matches[1];

    $order = $db->query("
        SELECT o.*, s.name as service_name, s.name_en as service_name_en, c.name as country_name, c.name_en as country_name_en, s.icon as service_icon, c.flag as country_flag, c.code as country_code,
               COALESCE(o.total_price, o.total_cost, 0) as total_cost, ROUND(COALESCE(o.total_price, o.total_cost, 0) / o.quantity) as price_points,
               (SELECT code FROM sms_messages WHERE order_id = o.id ORDER BY received_at ASC LIMIT 1) as sms_code,
               (SELECT received_at FROM sms_messages WHERE order_id = o.id ORDER BY received_at ASC LIMIT 1) as sms_received_at,
               (SELECT content FROM sms_messages WHERE order_id = o.id ORDER BY received_at ASC LIMIT 1) as sms_content
        FROM orders o
        LEFT JOIN services s ON o.service_id = s.id
        LEFT JOIN countries c ON o.country_id = c.id
        WHERE o.id = ?
    ", [$orderId])->fetch();
    
    if (!$order) {
        apiNotFound('订单不存在');
    }
    
    requireOrderOwner($order);
    
    if (!empty($order['service_icon'])) {
        $order['service_icon'] = getLocalImageUrl($order['service_icon'], '/pic/fuwu/');
    }
    if (!empty($order['country_flag'])) {
        $order['country_flag'] = getLocalImageUrl($order['country_flag'], '/pic/country/');
    }
    
    echo json_encode(['success' => true, 'data' => $order]);
    exit;
}

// 兼容/orders/create端点
if ($path === '/orders/create' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $serviceId = $input['service_id'] ?? null;
    $countryId = $input['country_id'] ?? null;
    $quantity = intval($input['quantity'] ?? 1);
    $userId = $input['user_id'] ?? null;

    if (!$userId) {
        $userId = getCurrentUserIdFromToken();
    }

    if (!$serviceId || !$countryId || !$userId) {
        apiBadRequest('参数缺失');
    }

    $currentUserId = getCurrentUserIdFromToken();
    if ($currentUserId && $userId != $currentUserId) {
        apiUnauthorized('无权为其他用户下单');
    }

    $db->beginTransaction();
    try {
        $user = $db->query("SELECT balance FROM users WHERE id = ?", [$userId])->fetch();
        if (!$user) {
            apiNotFound('用户不存在');
        }

        // 永远以服务端计算的 price_points 为准
        $pricePoints = calculateServicePricePoints($db, $serviceId, $countryId, $userId);

        $totalCost = $pricePoints * $quantity;
        
        if ($user['balance'] < $totalCost) {
            apiBadRequest('积分不足');
        }
        
        $orderId = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
        
        $db->query(
            "INSERT INTO orders (id, user_id, service_id, country_id, quantity, total_price, cost_price, profit, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')",
            [$orderId, $userId, $serviceId, $countryId, $quantity, $totalCost, $totalCost * 0.7, $totalCost * 0.3]
        );
        
        $db->query(
            "UPDATE users SET balance = balance - ? WHERE id = ?",
            [$totalCost, $userId]
        );
        
        $createBalanceAfter = $db->query("SELECT balance FROM users WHERE id = ?", [$userId])->fetchColumn();
        
        $createTxnId = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
        
        $db->query(
            "INSERT INTO credit_transactions (id, user_id, type, amount, balance_after, description) VALUES (?, ?, 'order', ?, ?, ?)",
            [$createTxnId, $userId, -$totalCost, $createBalanceAfter, "订单 #$orderId"]
        );
        
        $db->commit();
        
        logUserActivity($db, $userId, 'create_order', 'order', $orderId);
        
        echo json_encode([
            'success' => true,
            'data' => [
                'id' => $orderId,
                'service_id' => $serviceId,
                'country_id' => $countryId,
                'quantity' => $quantity,
                'price_points' => $pricePoints,
                'total_cost' => $totalCost,
                'status' => 'pending'
            ]
        ]);
    } catch (Exception $e) {
        $db->rollBack();
        apiServerError('创建订单失败');
    }
    exit;
}
