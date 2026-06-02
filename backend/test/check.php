<?php
/**
 * SMS 接码平台 - 综合检测工具
 * 
 * 合并了原有的 4 个检测文件：
 * - deploy_check.php (部署检测)
 * - check_services.php (服务数据检测)
 * - quick_check.php (快速检测)
 * - init_test_data.php (初始化测试数据)
 * 
 * 使用说明：
 * 1. 直接访问：http://your-domain/test/check.php
 * 2. 添加 ?action=init 可初始化测试数据
 * 3. 添加 ?format=json 可获取 JSON 格式输出
 * 
 * 安全提示：部署完成后建议删除此文件或添加访问限制
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(60);

$backendRoot = dirname(__DIR__);
require_once $backendRoot . '/config/database.php';
require_once $backendRoot . '/lib/Database.php';
require_once $backendRoot . '/lib/Auth.php';

// JSON mode
$isJson = isset($_GET['format']) && $_GET['format'] === 'json';
$action = $_GET['action'] ?? 'check';

if ($isJson) {
    header('Content-Type: application/json');
}

function pass($msg) { return ['status' => 'pass', 'msg' => $msg]; }
function fail($msg) { return ['status' => 'fail', 'msg' => $msg]; }
function warn($msg) { return ['status' => 'warn', 'msg' => $msg]; }

function httpGet($url, $headers = [], $timeout = 10) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    if ($error) {
        return ['error' => $error, 'body' => null, 'code' => $httpCode];
    }
    return ['error' => null, 'body' => $result, 'code' => $httpCode];
}

function renderResult($title, $results) {
    $allPass = true;
    $hasWarn = false;
    echo "<h3>{$title}</h3>";
    echo "<table>";
    foreach ($results as $r) {
        if ($r['status'] === 'fail') {
            $allPass = false;
            echo "<tr class='fail'><td>❌</td><td>{$r['msg']}</td></tr>";
        } elseif ($r['status'] === 'warn') {
            $hasWarn = true;
            echo "<tr class='warn'><td>⚠️</td><td>{$r['msg']}</td></tr>";
        } else {
            echo "<tr class='pass'><td>✅</td><td>{$r['msg']}</td></tr>";
        }
    }
    echo "</table>";
    if ($allPass && !$hasWarn) {
        echo "<div class='section-pass'>全部通过</div>";
    } elseif ($allPass) {
        echo "<div class='section-warn'>全部通过（有警告项）</div>";
    } else {
        echo "<div class='section-fail'>存在失败项，请检查修复</div>";
    }
}

// 连接数据库
try {
    $db = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT);
} catch (Exception $e) {
    if ($isJson) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    } else {
        die("数据库连接失败: " . $e->getMessage());
    }
    exit;
}

// 计算基础 URL
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
$basePath = rtrim(dirname($scriptDir), '/');
$baseUrl = $protocol . '://' . $host . $basePath;

// ========== 初始化测试数据 ==========
if ($action === 'init') {
    if ($isJson) {
        echo json_encode(['success' => false, 'error' => '初始化不支持 JSON 模式']);
        exit;
    }
    
    try {
        $db->beginTransaction();
        
        $services = [
            ['WhatsApp', 'WhatsApp', 'wa', 1],
            ['Telegram', 'Telegram', 'tg', 2],
            ['Google', 'Google', 'go', 3],
            ['Facebook', 'Facebook', 'fb', 4],
            ['Instagram', 'Instagram', 'ig', 5],
            ['Twitter', 'Twitter', 'tw', 6],
            ['TikTok', 'TikTok', 'tt', 7],
            ['Microsoft', 'Microsoft', 'ms', 8],
            ['Amazon', 'Amazon', 'az', 9],
            ['Apple', 'Apple', 'ap', 10],
        ];
        
        $serviceIds = [];
        echo "<h2>1️⃣ 添加服务</h2>";
        foreach ($services as $service) {
            $exists = $db->query("SELECT id FROM services WHERE code = ?", [$service[2]])->fetchColumn();
            if ($exists) {
                echo "<p class='info'>ℹ️ 服务 {$service[0]} 已存在</p>";
                $serviceIds[$service[2]] = $exists;
            } else {
                $db->query(
                    "INSERT INTO services (name, display_name, code, is_published, active, sort_order) VALUES (?, ?, ?, 1, 1, ?)",
                    $service
                );
                $serviceIds[$service[2]] = $db->lastInsertId();
                echo "<p class='success'>✅ 添加服务: {$service[0]}</p>";
            }
        }
        
        $countries = [
            ['United States', '美国', 'US', '1'],
            ['United Kingdom', '英国', 'GB', '44'],
            ['China', '中国', 'CN', '86'],
            ['India', '印度', 'IN', '91'],
            ['Russia', '俄罗斯', 'RU', '7'],
            ['Canada', '加拿大', 'CA', '1'],
            ['Germany', '德国', 'DE', '49'],
            ['France', '法国', 'FR', '33'],
            ['Japan', '日本', 'JP', '81'],
            ['South Korea', '韩国', 'KR', '82'],
        ];
        
        $countryIds = [];
        echo "<h2>2️⃣ 添加国家</h2>";
        foreach ($countries as $country) {
            $exists = $db->query("SELECT id FROM countries WHERE code = ?", [$country[2]])->fetchColumn();
            if ($exists) {
                echo "<p class='info'>ℹ️ 国家 {$country[1]} 已存在</p>";
                $countryIds[$country[2]] = $exists;
            } else {
                $db->query(
                    "INSERT INTO countries (name, display_name, code, phone_code, active) VALUES (?, ?, ?, ?, 1)",
                    $country
                );
                $countryIds[$country[2]] = $db->lastInsertId();
                echo "<p class='success'>✅ 添加国家: {$country[1]}</p>";
            }
        }
        
        $configs = [
            ['wa', 'US', 100], ['wa', 'GB', 80], ['wa', 'CN', 50], ['wa', 'IN', 40], ['wa', 'RU', 60],
            ['tg', 'US', 90], ['tg', 'GB', 70], ['tg', 'CN', 45], ['tg', 'RU', 55],
            ['go', 'US', 120], ['go', 'GB', 100], ['go', 'CN', 60],
            ['fb', 'US', 110], ['fb', 'GB', 90],
            ['ig', 'US', 95], ['ig', 'GB', 75],
            ['tw', 'US', 85],
            ['tt', 'US', 105],
            ['ms', 'US', 115],
            ['az', 'US', 125],
        ];
        
        echo "<h2>3️⃣ 添加服务国家配置</h2>";
        $addedCount = 0;
        foreach ($configs as $config) {
            $serviceId = $serviceIds[$config[0]] ?? null;
            $countryId = $countryIds[$config[1]] ?? null;
            if (!$serviceId || !$countryId) continue;
            
            $exists = $db->query("SELECT id FROM service_countries WHERE service_id = ? AND country_id = ?", [$serviceId, $countryId])->fetchColumn();
            if ($exists) {
                echo "<p class='info'>ℹ️ 配置已存在</p>";
            } else {
                $db->query(
                    "INSERT INTO service_countries (service_id, country_id, price, is_published, active) VALUES (?, ?, ?, 1, 1)",
                    [$serviceId, $countryId, $config[2]]
                );
                $addedCount++;
                echo "<p class='success'>✅ 添加配置: {$config[0]} + {$config[1]} = {$config[2]} 积分</p>";
            }
        }
        
        $db->commit();
        
        $finalServices = $db->query("SELECT COUNT(*) FROM services WHERE is_published=1 AND active=1")->fetchColumn();
        $finalCountries = $db->query("SELECT COUNT(*) FROM countries WHERE active=1")->fetchColumn();
        $finalSC = $db->query("SELECT COUNT(*) FROM service_countries WHERE is_published=1 AND active=1")->fetchColumn();
        
        echo "<div class='section-pass'>";
        echo "<h2>🎉 数据初始化完成！</h2>";
        echo "<p>已发布服务: {$finalServices} | 活跃国家: {$finalCountries} | 服务国家配置: {$finalSC}</p>";
        echo "<p><a href='?action=check' class='btn'>返回检测页面</a></p>";
        echo "</div>";
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        echo "<div class='section-fail'><h2>❌ 初始化失败</h2><p>错误: " . htmlspecialchars($e->getMessage()) . "</p></div>";
    }
    exit;
}

// ========== 检测模式 ==========
if ($isJson) {
    $results = [];
    
    // PHP 环境
    $results['php'] = [
        'version' => phpversion(),
        'extensions' => [],
    ];
    foreach (['pdo', 'pdo_mysql', 'json', 'curl', 'openssl', 'mbstring'] as $ext) {
        $results['php']['extensions'][$ext] = extension_loaded($ext);
    }
    
    // 数据库
    $results['database'] = [
        'connected' => true,
        'version' => $db->query("SELECT VERSION()")->fetchColumn(),
        'tables' => [],
    ];
    $requiredTables = ['users', 'orders', 'services', 'countries', 'service_countries', 'sms_messages', 'system_settings', 'admins', 'notifications', 'payment_configs', 'payment_records', 'credit_transactions'];
    foreach ($requiredTables as $table) {
        $exists = $db->query("SHOW TABLES LIKE '{$table}'")->fetch();
        $results['database']['tables'][$table] = $exists ? true : false;
    }
    
    // 数据统计
    $results['data'] = [
        'services' => $db->query("SELECT COUNT(*) FROM services")->fetchColumn(),
        'countries' => $db->query("SELECT COUNT(*) FROM countries")->fetchColumn(),
        'service_countries' => $db->query("SELECT COUNT(*) FROM service_countries")->fetchColumn(),
        'users' => $db->query("SELECT COUNT(*) FROM users")->fetchColumn(),
        'orders' => $db->query("SELECT COUNT(*) FROM orders")->fetchColumn(),
    ];
    
    echo json_encode(['success' => true, 'results' => $results], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMS 接码平台 - 综合检测</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f7fa; color: #333; padding: 20px; }
        .container { max-width: 1000px; margin: 0 auto; }
        h1 { text-align: center; margin-bottom: 10px; color: #1a1a2e; }
        .subtitle { text-align: center; color: #666; margin-bottom: 30px; font-size: 14px; }
        .nav { text-align: center; margin-bottom: 30px; }
        .nav a { display: inline-block; padding: 10px 20px; margin: 0 5px; background: #4CAF50; color: white; text-decoration: none; border-radius: 6px; }
        .nav a:hover { background: #45a049; }
        .nav a.secondary { background: #2196F3; }
        .nav a.secondary:hover { background: #1976D2; }
        .section { background: white; border-radius: 8px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h3 { color: #1a1a2e; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #eee; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 8px 12px; border-bottom: 1px solid #f0f0f0; }
        tr.pass td { color: #2e7d32; }
        tr.fail td { color: #c62828; }
        tr.warn td { color: #f57c00; }
        .section-pass { background: #e8f5e9; color: #2e7d32; padding: 10px; border-radius: 4px; margin-top: 10px; text-align: center; }
        .section-fail { background: #ffebee; color: #c62828; padding: 10px; border-radius: 4px; margin-top: 10px; text-align: center; }
        .section-warn { background: #fff3e0; color: #f57c00; padding: 10px; border-radius: 4px; margin-top: 10px; text-align: center; }
        .success { color: #2e7d32; background: #e8f5e9; padding: 10px; margin: 10px 0; border-radius: 4px; }
        .error { color: #c62828; background: #ffebee; padding: 10px; margin: 10px 0; border-radius: 4px; }
        .info { color: #1565c0; background: #e3f2fd; padding: 10px; margin: 10px 0; border-radius: 4px; }
        .btn { display: inline-block; padding: 10px 20px; background: #4CAF50; color: white; text-decoration: none; border-radius: 4px; margin: 10px 5px; }
        .btn:hover { background: #45a049; }
        .summary { background: white; border-radius: 8px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); text-align: center; }
        .summary .count { font-size: 48px; font-weight: bold; }
        .summary .pass .count { color: #2e7d32; }
        .summary .fail .count { color: #c62828; }
        .summary .warn .count { color: #f57c00; }
        .summary-item { display: inline-block; margin: 0 30px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 SMS 接码平台 - 综合检测</h1>
        <p class="subtitle">部署检测 | 服务数据 | 性能索引 | 初始化数据</p>
        
        <div class="nav">
            <a href="?action=check">🔍 运行检测</a>
            <a href="?action=init" class="secondary">🚀 初始化测试数据</a>
            <a href="?format=json" class="secondary">📄 JSON 输出</a>
        </div>

<?php

// ========== 1. PHP 环境检测 ==========
$phpResults = [];
$phpVersion = phpversion();
$phpResults[] = version_compare($phpVersion, '7.4', '>=') 
    ? pass("PHP 版本: {$phpVersion}") 
    : fail("PHP 版本过低: {$phpVersion}，需要 7.4+");

$requiredExtensions = ['pdo', 'pdo_mysql', 'json', 'curl', 'openssl', 'mbstring'];
foreach ($requiredExtensions as $ext) {
    $phpResults[] = extension_loaded($ext) ? pass("扩展 {$ext} 已安装") : fail("扩展 {$ext} 未安装");
}

$phpResults[] = function_exists('random_bytes') ? pass('random_bytes 函数可用') : fail('random_bytes 函数不可用');
$phpResults[] = function_exists('password_hash') ? pass('password_hash 函数可用') : fail('password_hash 函数不可用');
$phpResults[] = pass("upload_max_filesize: " . ini_get('upload_max_filesize') . ", post_max_size: " . ini_get('post_max_size') . ", max_execution_time: " . ini_get('max_execution_time') . "s");
$phpResults[] = pass("时区: " . date_default_timezone_get());

renderResult('1️⃣ PHP 环境检测', $phpResults);

// ========== 2. 配置文件检测 ==========
$configResults = [];
$envFile = $backendRoot . '/.env';
$configResults[] = file_exists($envFile) ? pass('.env 文件存在') : fail('.env 文件不存在');

if (file_exists($envFile)) {
    $env = parse_ini_file($envFile);
    $configResults[] = !empty($env['DB_HOST']) ? pass("DB_HOST: {$env['DB_HOST']}") : fail('DB_HOST 未配置');
    $configResults[] = !empty($env['DB_NAME']) ? pass("DB_NAME: {$env['DB_NAME']}") : fail('DB_NAME 未配置');
    $configResults[] = !empty($env['DB_USER']) ? pass("DB_USER: {$env['DB_USER']}") : fail('DB_USER 未配置');
    $configResults[] = !empty($env['DB_PASS']) ? pass('DB_PASS 已配置') : fail('DB_PASS 未配置');
    $configResults[] = !empty($env['AUTH_SECRET_KEY']) ? pass('AUTH_SECRET_KEY 已配置') : fail('AUTH_SECRET_KEY 未配置');
    $configResults[] = !empty($env['APP_URL']) ? pass("APP_URL: {$env['APP_URL']}") : warn('APP_URL 未配置');
    $configResults[] = !empty($env['CORS_ALLOWED_ORIGINS']) ? pass("CORS_ALLOWED_ORIGINS: {$env['CORS_ALLOWED_ORIGINS']}") : warn('CORS_ALLOWED_ORIGINS 未配置');
}

renderResult('2️⃣ 配置文件检测', $configResults);

// ========== 3. 数据库连接检测 ==========
$dbResults = [];
$dbResults[] = pass("数据库连接成功: " . DB_HOST . "/" . DB_NAME);
$version = $db->query("SELECT VERSION()")->fetchColumn();
$dbResults[] = pass("MySQL 版本: {$version}");
$dbTime = $db->query("SELECT NOW()")->fetchColumn();
$dbResults[] = pass("数据库时间: {$dbTime}");

renderResult('3️⃣ 数据库连接检测', $dbResults);

// ========== 4. 数据库表结构检测 ==========
$tableResults = [];
$requiredTables = [
    'admins' => '管理员表',
    'countries' => '国家表',
    'orders' => '订单表',
    'services' => '服务表',
    'service_countries' => '服务国家关联表',
    'sms_messages' => '短信消息表',
    'system_settings' => '系统设置表',
    'users' => '用户表',
    'notifications' => '通知表',
    'payment_configs' => '支付配置表',
    'payment_records' => '支付记录表',
    'credit_transactions' => '积分变动表',
];

foreach ($requiredTables as $table => $desc) {
    $exists = $db->query("SHOW TABLES LIKE '{$table}'")->fetch();
    $tableResults[] = $exists ? pass("表 {$table} ({$desc}) 存在") : fail("表 {$table} ({$desc}) 不存在");
}

renderResult('4️⃣ 数据库表结构检测', $tableResults);

// ========== 5. 系统设置检测 ==========
$settingsResults = [];
$requiredSettings = [
    'api_key' => 'API Key',
    'pending_order_expire_hours' => '待使用订单过期时间(小时)',
    'order_timeout' => '订单超时时间(分钟)',
    'register_bonus_min' => '注册赠送积分最小值',
    'register_bonus_max' => '注册赠送积分最大值',
    'default_coefficient_before' => '默认系数(首次充值前)',
    'default_coefficient_after' => '默认系数(首次充值后)',
];

foreach ($requiredSettings as $key => $desc) {
    $setting = $db->query("SELECT value FROM system_settings WHERE `key` = ?", [$key])->fetch();
    if ($setting) {
        $settingsResults[] = pass("{$desc} ({$key}): {$setting['value']}");
    } else {
        $settingsResults[] = fail("{$desc} ({$key}) 未配置");
    }
}

renderResult('5️⃣ 系统设置检测', $settingsResults);

// ========== 6. 管理员账号检测 ==========
$adminResults = [];
$adminCount = $db->query("SELECT COUNT(*) FROM admins")->fetchColumn();
$adminResults[] = $adminCount > 0 ? pass("管理员账号数量: {$adminCount}") : fail('没有管理员账号');
$activeAdmins = $db->query("SELECT COUNT(*) FROM admins WHERE status = 'active'")->fetchColumn();
$adminResults[] = $activeAdmins > 0 ? pass("活跃管理员: {$activeAdmins}") : warn('没有活跃的管理员账号');

renderResult('6️⃣ 管理员账号检测', $adminResults);

// ========== 7. 基础数据检测 ==========
$dataResults = [];
$serviceCount = $db->query("SELECT COUNT(*) FROM services")->fetchColumn();
$publishedServiceCount = $db->query("SELECT COUNT(*) FROM services WHERE is_published = 1 AND active = 1")->fetchColumn();
$dataResults[] = $serviceCount > 0 ? pass("服务总数: {$serviceCount}，已上架: {$publishedServiceCount}") : warn('服务表为空');

$countryCount = $db->query("SELECT COUNT(*) FROM countries")->fetchColumn();
$activeCountryCount = $db->query("SELECT COUNT(*) FROM countries WHERE active = 1")->fetchColumn();
$dataResults[] = $countryCount > 0 ? pass("国家总数: {$countryCount}，活跃: {$activeCountryCount}") : warn('国家表为空');

$serviceCountryCount = $db->query("SELECT COUNT(*) FROM service_countries")->fetchColumn();
$publishedSCCount = $db->query("SELECT COUNT(*) FROM service_countries WHERE is_published = 1 AND active = 1")->fetchColumn();
$dataResults[] = $serviceCountryCount > 0 ? pass("服务国家配置: {$serviceCountryCount}，已上架: {$publishedSCCount}") : warn('服务国家配置为空');

$userCount = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$dataResults[] = pass("用户总数: {$userCount}");
$orderCount = $db->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$dataResults[] = pass("订单总数: {$orderCount}");

$paymentConfigCount = $db->query("SELECT COUNT(*) FROM payment_configs WHERE active = 1")->fetchColumn();
$dataResults[] = $paymentConfigCount > 0 ? pass("支付配置: {$paymentConfigCount} 个已启用") : warn('没有启用的支付配置');

renderResult('7️⃣ 基础数据检测', $dataResults);

// ========== 8. 服务数据详情 ==========
$services = $db->query("SELECT id, name, code, is_published, active, sort_order FROM services WHERE is_published = 1 AND active = 1 ORDER BY sort_order ASC, id ASC LIMIT 10")->fetchAll();
$serviceCountries = $db->query("SELECT sc.id, sc.service_id, sc.country_id, s.name as service_name, c.name as country_name, sc.price, sc.is_published, sc.active FROM service_countries sc JOIN services s ON sc.service_id = s.id JOIN countries c ON sc.country_id = c.id WHERE sc.is_published = 1 AND sc.active = 1 LIMIT 10")->fetchAll();

echo "<div class='section'>";
echo "<h3>8️⃣ 服务数据详情</h3>";
echo "<p>服务数量: " . count($services) . " (显示前10条)</p>";
if (count($services) > 0) {
    echo "<table><tr><th>ID</th><th>名称</th><th>代码</th><th>已发布</th><th>活跃</th><th>排序</th></tr>";
    foreach ($services as $s) {
        echo "<tr><td>{$s['id']}</td><td>{$s['name']}</td><td>{$s['code']}</td><td>" . ($s['is_published'] ? '是' : '否') . "</td><td>" . ($s['active'] ? '是' : '否') . "</td><td>{$s['sort_order']}</td></tr>";
    }
    echo "</table>";
}
echo "<p style='margin-top:15px'>服务国家配置: " . count($serviceCountries) . " (显示前10条)</p>";
if (count($serviceCountries) > 0) {
    echo "<table><tr><th>服务</th><th>国家</th><th>价格(积分)</th><th>已发布</th><th>活跃</th></tr>";
    foreach ($serviceCountries as $sc) {
        echo "<tr><td>{$sc['service_name']}</td><td>{$sc['country_name']}</td><td>{$sc['price']}</td><td>" . ($sc['is_published'] ? '是' : '否') . "</td><td>" . ($sc['active'] ? '是' : '否') . "</td></tr>";
    }
    echo "</table>";
}
echo "</div>";

// ========== 9. 核心库文件检测 ==========
$libResults = [];
$libFiles = [
    'Database.php' => '数据库类',
    'Auth.php' => '认证类',
    'HeroSMS.php' => 'HeroSMS API 类',
    'AppleIAP.php' => 'Apple IAP 类',
    'KeyManager.php' => '密钥管理类',
    'Logger.php' => '日志类',
];
foreach ($libFiles as $file => $desc) {
    $path = $backendRoot . '/lib/' . $file;
    $libResults[] = file_exists($path) ? pass("{$file} ({$desc}) 存在") : fail("{$file} ({$desc}) 不存在");
}

$routeFiles = ['auth.php', 'user.php', 'orders.php', 'services.php', 'topup.php', 'notifications.php', 'payment.php', 'system.php'];
foreach ($routeFiles as $file) {
    $path = $backendRoot . '/routes/' . $file;
    $libResults[] = file_exists($path) ? pass("routes/{$file} 存在") : fail("routes/{$file} 不存在");
}

$libResults[] = file_exists($backendRoot . '/index.php') ? pass("index.php (API 入口) 存在") : fail("index.php 不存在");

renderResult('9️⃣ 核心文件检测', $libResults);

// ========== 10. API 接口检测 ==========
$apiResults = [];
$apiResults[] = pass("后端地址: {$baseUrl}/");

$htaccess = $backendRoot . '/.htaccess';
if (file_exists($htaccess)) {
    $apiResults[] = pass('.htaccess 文件存在');
    $htaccessContent = file_get_contents($htaccess);
    if (strpos($htaccessContent, 'RewriteEngine') !== false) {
        $apiResults[] = pass('URL 重写规则已配置');
    } else {
        $apiResults[] = warn('.htaccess 中未找到 RewriteEngine 规则');
    }
} else {
    $apiResults[] = warn('.htaccess 文件不存在');
}

// 健康检查
$resp = httpGet("{$baseUrl}/api.php?path=/health");
if ($resp['body']) {
    $data = json_decode($resp['body'], true);
    if ($data && isset($data['status']) && $data['status'] === 'ok') {
        $apiResults[] = pass("健康检查接口 (/health) 正常");
    } else {
        $apiResults[] = fail("健康检查接口返回异常: " . $resp['body']);
    }
} else {
    $apiResults[] = warn("健康检查接口无法访问: " . ($resp['error'] ?: "HTTP {$resp['code']}"));
}

// 系统设置接口
$apiKey = $db->query("SELECT value FROM system_settings WHERE `key` = ?", ['api_key'])->fetchColumn() ?: '';
if ($apiKey) {
    $resp = httpGet("{$baseUrl}/api.php?path=/settings", ["X-API-Key: {$apiKey}"]);
    if ($resp['body']) {
        $data = json_decode($resp['body'], true);
        if ($data && isset($data['success']) && $data['success']) {
            $apiResults[] = pass("系统设置接口 (/settings) 正常");
        } else {
            $apiResults[] = fail("系统设置接口返回异常");
        }
    } else {
        $apiResults[] = warn("系统设置接口无法访问");
    }
} else {
    $apiResults[] = warn('API Key 未配置，跳过接口检测');
}

renderResult('🔟 API 接口检测', $apiResults);

// ========== 11. 性能索引检测 ==========
$indexResults = [];
$indexes = $db->query("
    SELECT TABLE_NAME, INDEX_NAME, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) as COLUMNS
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = '" . DB_NAME . "'
    AND INDEX_NAME LIKE 'idx_%'
    GROUP BY TABLE_NAME, INDEX_NAME
    ORDER BY TABLE_NAME, INDEX_NAME
")->fetchAll();

$indexResults[] = pass("找到 " . count($indexes) . " 个性能索引");

$keyIndexes = [
    'idx_orders_user_status' => 'orders',
    'idx_orders_status_expires' => 'orders',
    'idx_sc_service_country' => 'service_countries',
    'idx_notifications_user_read' => 'notifications',
    'idx_payment_transaction' => 'payment_records',
];

foreach ($keyIndexes as $indexName => $tableName) {
    $exists = $db->query(
        "SELECT COUNT(*) FROM information_schema.STATISTICS 
         WHERE TABLE_SCHEMA = '" . DB_NAME . "' 
         AND TABLE_NAME = ? AND INDEX_NAME = ?",
        [$tableName, $indexName]
    )->fetchColumn();
    
    $indexResults[] = $exists ? pass("{$indexName}") : warn("{$indexName} (缺失)");
}

renderResult('1️⃣1️⃣ 性能索引检测', $indexResults);

// ========== 12. 日志目录检测 ==========
$logResults = [];
$systemLogDir = '/var/log/sms-receiver';
$localLogDir = $backendRoot . '/logs';

if (is_dir($systemLogDir)) {
    $logResults[] = pass("系统日志目录存在: {$systemLogDir}");
    $logResults[] = is_writable($systemLogDir) ? pass("日志目录可写") : fail("日志目录不可写");
} elseif (is_dir($localLogDir)) {
    $logResults[] = pass("本地日志目录存在: {$localLogDir}");
    $logResults[] = is_writable($localLogDir) ? pass("日志目录可写") : fail("日志目录不可写");
} else {
    $logResults[] = warn("日志目录不存在");
    if (@mkdir($localLogDir, 0755, true)) {
        $logResults[] = pass("已创建日志目录: {$localLogDir}");
    } else {
        $logResults[] = fail("创建日志目录失败");
    }
}

renderResult('1️⃣2️⃣ 日志目录检测', $logResults);

?>
    </div>
</body>
</html>
