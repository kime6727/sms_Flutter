<?php
/**
 * 辅助函数
 */

/**
 * 从 system_settings 表获取设置值
 */
function getSetting($db, $key, $default = null) {
    $result = $db->query("SELECT value FROM system_settings WHERE `key` = ?", [$key])->fetchColumn();
    return $result !== false ? $result : $default;
}

/**
 * 安全获取当前用户ID — Token优先，query参数仅开发模式允许
 *
 * 生产环境中禁止通过 URL 参数传递 user_id，防止越权访问。
 * 仅在 Token 认证失败且 APP_ENV=development 时才允许 query 参数作为 fallback。
 */
function getSecureUserId() {
    // 优先使用 Token 认证
    $userId = getCurrentUserIdFromToken();
    if ($userId) {
        return $userId;
    }

    // 仅开发模式允许 query param fallback
    if (defined('APP_ENV') && APP_ENV === 'development') {
        $input = json_decode(file_get_contents('php://input'), true);
        return $input['user_id'] ?? ($_GET['user_id'] ?? null);
    }

    return null;
}

/**
 * 从当前请求中获取用户ID（仅从 Token 解析）
 *
 * 安全修复：不再从 body/query 读取 user_id，防止越权
 * 任何调用 getCurrentUserIdFromToken() 的代码必须使用有效 token
 */
function getCurrentUserIdFromToken() {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/Bearer (.+)/', $authHeader, $matches)) {
        $tokenData = Auth::verifyToken($matches[1]);
        if ($tokenData !== false) {
            return $tokenData['user_id'];
        }
    }
    return null;
}

/**
 * 验证订单所有者
 */
function requireOrderOwner($order) {
    $currentUserId = getCurrentUserIdFromToken();
    if (!$currentUserId || $order['user_id'] != $currentUserId) {
        apiUnauthorized('无权访问此订单');
    }
}

/**
 * 计算服务价格（考虑会员折扣）
 */
function calculateServicePricePoints($db, $serviceId, $countryId, $userId = null) {
    $serviceCountry = $db->query(
        "SELECT sc.price
         FROM service_countries sc
         WHERE sc.service_id = ? AND sc.country_id = ? AND sc.is_published = 1 AND sc.is_active = 1",
        [$serviceId, $countryId]
    )->fetch();
    
    if (!$serviceCountry) {
        apiNotFound('服务国家组合不存在');
    }
    
    $basePriceCents = floatval($serviceCountry['price']) * 100;
    
    // 获取系统默认系数
    $defaultBefore = floatval(getSetting($db, 'default_coefficient_before', '4'));
    $defaultAfter = floatval(getSetting($db, 'default_coefficient_after', '4.5'));
    
    // 获取服务自定义系数
    $serviceCoef = $db->query("SELECT coefficient_before, coefficient_after FROM service_coefficients WHERE service_id = ?", [$serviceId])->fetch();
    $coefBefore = ($serviceCoef && $serviceCoef['coefficient_before'] !== null) ? floatval($serviceCoef['coefficient_before']) : $defaultBefore;
    $coefAfter = ($serviceCoef && $serviceCoef['coefficient_after'] !== null) ? floatval($serviceCoef['coefficient_after']) : $defaultAfter;
    
    // 判断用户是否充值过
    $hasTopup = false;
    if ($userId) {
        $user = $db->query("SELECT has_topup_history FROM users WHERE id = ?", [$userId])->fetch();
        $hasTopup = $user && intval($user['has_topup_history']) === 1;
    }
    
    $coefficient = $hasTopup ? $coefAfter : $coefBefore;
    $finalPrice = $basePriceCents * $coefficient;
    
    // 应用会员折扣
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
    
    return intval(ceil($finalPrice));
}

/**
 * 获取本地图片URL
 */
function getLocalImageUrl($icon, $basePath) {
    if (empty($icon)) {
        return '';
    }
    
    // 如果已经是完整URL，直接返回
    if (preg_match('/^https?:\/\//', $icon)) {
        return $icon;
    }
    
    // 如果是相对路径，转换为完整URL
    $appUrl = defined('APP_URL') ? APP_URL : '';
    return $appUrl . $basePath . $icon;
}

/**
 * 从短信内容中提取验证码
 */
function extractVerificationCode($sms) {
    // 匹配 4-6 位数字
    if (preg_match('/\b(\d{4,6})\b/', $sms, $matches)) {
        return $matches[1];
    }
    
    // 匹配 "code: 123456" 格式
    if (preg_match('/code[:\s=]+(\d{4,6})/i', $sms, $matches)) {
        return $matches[1];
    }
    
    // 匹配 "verification code: 123456" 格式
    if (preg_match('/verification\s+code[:\s=]+(\d{4,6})/i', $sms, $matches)) {
        return $matches[1];
    }
    
    // 匹配 "Your code is 123456" 格式
    if (preg_match('/code\s+is\s+(\d{4,6})/i', $sms, $matches)) {
        return $matches[1];
    }
    
    // 匹配 "Код: 123456" 俄语格式
    if (preg_match('/Код[:\s=]+(\d{4,6})/i', $sms, $matches)) {
        return $matches[1];
    }
    
    return null;
}
