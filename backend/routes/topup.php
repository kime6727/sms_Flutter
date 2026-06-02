<?php
/**
 * 充值相关路由
 */

// 获取充值套餐
if ($path === '/topup-packages' && $method === 'GET') {
    $packages = $db->query("SELECT * FROM topup_packages WHERE is_active = 1 ORDER BY sort_order ASC, id ASC")->fetchAll();
    echo json_encode(['success' => true, 'data' => $packages]);
    exit;
}

// 创建充值订单
if ($path === '/topup' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $userId = $input['user_id'] ?? null;
    $packageId = $input['package_id'] ?? null;
    $paymentMethod = $input['payment_method'] ?? 'apple_iap';
    $appleProductId = $input['apple_product_id'] ?? null;
    
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
        
        $topupOrder = $db->query(
            "INSERT INTO topup_orders (user_id, package_id, amount, points, payment_method, apple_product_id, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')",
            [$userId, $packageId, $totalCost, $points, $paymentMethod, $appleProductId]
        );
        
        $orderId = $db->lastInsertId();
        
        $db->commit();
        
        logUserActivity($db, $userId, 'create_topup_order', 'topup', $orderId);
        
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
        apiServerError('创建充值订单失败');
    }
    exit;
}

// Apple IAP验证
if ($path === '/topup/verify-apple' && $method === 'POST') {
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
        $order = $db->query("SELECT * FROM topup_orders WHERE id = ? AND user_id = ?", [$orderId, $userId])->fetch();
        if (!$order) {
            apiNotFound('订单不存在');
        }
        
        if ($order['status'] !== 'pending') {
            apiBadRequest('订单状态不正确');
        }
        
        $db->query("UPDATE topup_orders SET status = 'completed', completed_at = NOW() WHERE id = ?", [$orderId]);
        
        $db->query(
            "UPDATE users SET balance = balance + ?, has_topup_history = 1 WHERE id = ?",
            [$order['points'], $userId]
        );
        
        $db->query(
            "INSERT INTO credit_transactions (user_id, type, amount, balance_after, description) VALUES (?, 'topup', ?, (SELECT balance FROM users WHERE id = ?), ?)",
            [$userId, $order['points'], $userId, "充值订单 #$orderId"]
        );
        
        $db->commit();
        
        logUserActivity($db, $userId, 'complete_topup', 'topup', $orderId);
        
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
