<?php
/**
 * 服务相关路由
 */

// 获取服务列表
if ($path === '/services' && $method === 'GET') {
    $services = $db->query("SELECT * FROM services WHERE is_published = 1 ORDER BY sort_order ASC, id ASC")->fetchAll();

    $services = array_map(function($service) {
        // hero_code 字段供客户端用 CDN 拼图标 URL（hero_service_id）
        $service['hero_code'] = $service['hero_service_id'] ?? '';
        // icon 字段保留但优先用 hero_code 拼 URL
        if (!empty($service['icon'])) {
            $service['icon'] = getLocalImageUrl($service['icon'], '/pic/fuwu/');
        }
        return $service;
    }, $services);

    echo json_encode(['success' => true, 'data' => $services]);
    exit;
}

// 获取国家列表
if ($path === '/countries' && $method === 'GET') {
    $countries = $db->query("SELECT * FROM countries WHERE active = 1 ORDER BY sort_order ASC, id ASC")->fetchAll();
    
    $countries = array_map(function($country) {
        if (!empty($country['flag'])) {
            $country['flag'] = getLocalImageUrl($country['flag'], '/pic/country/');
        }
        return $country;
    }, $countries);
    
    echo json_encode(['success' => true, 'data' => $countries]);
    exit;
}

// 获取服务国家组合
if ($path === '/service-countries' && $method === 'GET') {
    $serviceId = $_GET['service_id'] ?? null;
    $countryId = $_GET['country_id'] ?? null;
    $userId = $_GET['user_id'] ?? null;
    
    $sql = "SELECT sc.id, sc.service_id, sc.country_id, sc.price, sc.is_published, sc.is_active,
                   s.name as service_name, s.name_en as service_name_en, s.name_cn as service_name_cn, s.icon as service_icon, s.hero_service_id as service_code,
                   c.name as country_name, c.name_en as country_name_en, c.name_cn as country_name_cn, c.code as country_code, c.flag as country_flag, c.phone_code, c.hero_country_id
            FROM service_countries sc
            LEFT JOIN services s ON sc.service_id = s.id
            LEFT JOIN countries c ON sc.country_id = c.id
            WHERE sc.is_published = 1 AND sc.is_active = 1";
    
    $params = [];
    if ($serviceId) {
        $sql .= " AND sc.service_id = ?";
        $params[] = $serviceId;
    }
    if ($countryId) {
        $sql .= " AND sc.country_id = ?";
        $params[] = $countryId;
    }
    
    $sql .= " ORDER BY sc.id ASC";
    
    $serviceCountries = $db->query($sql, $params)->fetchAll();
    
    $serviceCountries = array_map(function($sc) {
        if (!empty($sc['service_icon'])) {
            $sc['service_icon'] = getLocalImageUrl($sc['service_icon'], '/pic/fuwu/');
        }
        // country_flag / hero_country_id: hero_country_id 直接给客户端用 CDN 拼 URL
        // country_flag 字段为 null 时不返回空 URL
        return $sc;
    }, $serviceCountries);
    
    echo json_encode(['success' => true, 'data' => $serviceCountries]);
    exit;
}

// 获取已发布的服务国家组合（别名路由）- 返回积分价格
if ($path === '/service-countries/published' && $method === 'GET') {
    $serviceId = $_GET['service_id'] ?? null;
    $countryId = $_GET['country_id'] ?? null;
    $userId = $_GET['user_id'] ?? null;
    
    $sql = "SELECT sc.id, sc.service_id, sc.country_id, sc.price, sc.is_published, sc.is_active,
                   s.name as service_name, s.name_en as service_name_en, s.name_cn as service_name_cn, s.icon as service_icon, s.hero_service_id as service_code,
                   c.name as country_name, c.name_en as country_name_en, c.name_cn as country_name_cn, c.code as country_code, c.flag as country_flag, c.phone_code, c.hero_country_id
            FROM service_countries sc
            LEFT JOIN services s ON sc.service_id = s.id
            LEFT JOIN countries c ON sc.country_id = c.id
            WHERE sc.is_published = 1 AND sc.is_active = 1";
    
    $params = [];
    if ($serviceId) {
        $sql .= " AND sc.service_id = ?";
        $params[] = $serviceId;
    }
    if ($countryId) {
        $sql .= " AND sc.country_id = ?";
        $params[] = $countryId;
    }
    
    $sql .= " ORDER BY sc.id ASC";
    
    $serviceCountries = $db->query($sql, $params)->fetchAll();
    
    // 获取系统默认系数
    $defaultBefore = floatval(getSetting($db, 'default_coefficient_before', '4'));
    $defaultAfter = floatval(getSetting($db, 'default_coefficient_after', '4.5'));
    
    // 获取所有服务的自定义系数（一次性查询，避免N+1）
    $serviceCoefs = [];
    $coefsResult = $db->query("SELECT service_id, coefficient_before, coefficient_after FROM service_coefficients")->fetchAll();
    foreach ($coefsResult as $coef) {
        $serviceCoefs[$coef['service_id']] = $coef;
    }
    
    // 判断用户是否充值过（决定使用 before 还是 after 系数）
    $hasTopup = false;
    if ($userId) {
        $user = $db->query("SELECT has_topup_history FROM users WHERE id = ?", [$userId])->fetch();
        $hasTopup = $user && intval($user['has_topup_history']) === 1;
    }
    
    $serviceCountries = array_map(function($sc) use ($defaultBefore, $defaultAfter, $serviceCoefs, $hasTopup) {
        if (!empty($sc['service_icon'])) {
            $sc['service_icon'] = getLocalImageUrl($sc['service_icon'], '/pic/fuwu/');
        }
        // country_flag / hero_country_id: hero_country_id 直接给客户端用 CDN 拼 URL
        // country_flag 字段为 null 时不返回空 URL
        
        // 计算积分价格: price(美元) * 100(转为分) * 系数
        $basePriceCents = floatval($sc['price']) * 100;
        $serviceId = intval($sc['service_id']);
        
        // 获取服务系数
        $serviceCoef = $serviceCoefs[$serviceId] ?? null;
        $coefBefore = ($serviceCoef && $serviceCoef['coefficient_before'] !== null) 
            ? floatval($serviceCoef['coefficient_before']) 
            : $defaultBefore;
        $coefAfter = ($serviceCoef && $serviceCoef['coefficient_after'] !== null) 
            ? floatval($serviceCoef['coefficient_after']) 
            : $defaultAfter;
        
        // 根据用户是否充值过选择系数
        $coefficient = $hasTopup ? $coefAfter : $coefBefore;
        
        // 计算积分价格并向上取整
        $pricePoints = intval(ceil($basePriceCents * $coefficient));
        
        $sc['price_points'] = $pricePoints;
        $sc['coefficient'] = $coefficient;
        
        return $sc;
    }, $serviceCountries);
    
    echo json_encode(['success' => true, 'data' => $serviceCountries]);
    exit;
}

// 计算价格
if ($path === '/price/calculate' && $method === 'GET') {
    $serviceId = $_GET['service_id'] ?? null;
    $countryId = $_GET['country_id'] ?? null;
    $userId = $_GET['user_id'] ?? null;
    
    if (!$userId) {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/Bearer (.+)/', $authHeader, $matches)) {
            $tokenData = Auth::verifyToken($matches[1]);
            if ($tokenData !== false) {
                $userId = $tokenData['user_id'];
            }
        }
    }
    
    if (!$serviceId || !$countryId) {
        apiBadRequest('service_id 和 country_id 参数缺失');
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

// 获取库存
if ($path === '/stock' && $method === 'GET') {
    $serviceId = $_GET['service_id'] ?? null;
    $countryId = $_GET['country_id'] ?? null;

    if (!$serviceId || !$countryId) {
        apiBadRequest('service_id 和 country_id 参数缺失');
    }

    // 用文件缓存代替 cache 表（避免依赖不存在的表）
    $cacheKey = "stock_{$serviceId}_{$countryId}";
    $cacheFile = sys_get_temp_dir() . '/sms_stock_' . md5($cacheKey) . '.json';
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 60) {
        $cached = json_decode(@file_get_contents($cacheFile), true);
        if ($cached) {
            echo json_encode(['success' => true, 'data' => $cached, 'cached' => true]);
            exit;
        }
    }

    // 调 HeroSMS checkStock（方法名是 checkStock 不是 getStock，参数是 service_code + country_id）
    // serviceId 参数实际上前端传的是 service_id，需要 JOIN 一下拿到 service code
    $serviceRow = $db->query("SELECT code FROM services WHERE id = ?", [$serviceId])->fetch();
    $serviceCode = $serviceRow['code'] ?? $serviceId;
    $stock = $heroSMS->checkStock($serviceCode, intval($countryId));

    // 写到文件缓存
    @file_put_contents($cacheFile, json_encode($stock));

    echo json_encode(['success' => true, 'data' => $stock, 'cached' => false]);
    exit;
}
