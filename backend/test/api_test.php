<?php
/**
 * SMS 接码平台 - 综合 API 接口测试工具
 *
 * 自动测试所有后端 API 端点，支持 JSON/HTML 两种输出格式
 *
 * 访问：
 *   http://localhost:8080/test/api_test.php
 *   http://localhost:8080/test/api_test.php?format=json  (JSON 输出)
 *   http://localhost:8080/test/api_test.php?run=1        (自动执行测试)
 *   http://localhost:8080/test/api_test.php?api_url=https://sms.niceapp.eu.cc  (指定后端)
 *
 * 安全提示：测试完成后建议删除此文件
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(120);

$isJson = isset($_GET['format']) && $_GET['format'] === 'json';
$autoRun = isset($_GET['run']) && $_GET['run'] == '1';

// 读取 .env 配置
$envFile = dirname(__DIR__) . '/.env';
$env = [];
if (file_exists($envFile)) {
    $env = parse_ini_file($envFile);
}

$API_KEY = $env['API_KEY'] ?? '';

// 允许通过 query 参数覆盖后端地址（默认用 .env 配置或 GET 参数）
$apiUrl = $_GET['api_url'] ?? ($env['APP_URL'] ?? 'https://sms.niceapp.eu.cc');
$apiUrl = rtrim($apiUrl, '/');

if ($isJson) {
    header('Content-Type: application/json; charset=utf-8');
}

// ========== HTTP 工具函数 ==========

function httpRequest($method, $url, $data = null, $headers = [], $timeout = 8) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

    $defaultHeaders = [
        'Accept: application/json',
        'X-API-Key: ' . ($GLOBALS['API_KEY'] ?? ''),
        'X-Device-Id: test_device_' . uniqid(),
    ];
    $allHeaders = array_merge($defaultHeaders, $headers);

    if ($data !== null) {
        if (is_array($data)) {
            $payload = json_encode($data, JSON_UNESCAPED_UNICODE);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            $allHeaders[] = 'Content-Type: application/json';
        } else {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
    }

    curl_setopt($ch, CURLOPT_HTTPHEADER, $allHeaders);

    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);

    return [
        'method' => $method,
        'url' => $url,
        'code' => $httpCode,
        'body' => $result,
        'error' => $error,
        'time' => $info['total_time'] ?? 0,
    ];
}

function parseResponse($response) {
    if (empty($response['body'])) {
        return ['success' => false, 'error' => 'Empty response', 'code' => $response['code']];
    }
    $data = json_decode($response['body'], true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['success' => false, 'error' => 'Invalid JSON: ' . json_last_error_msg(), 'raw' => substr($response['body'], 0, 200)];
    }
    return $data;
}

// ========== 测试用例定义 ==========

// 每个测试用例：[name, method, path, body?, query?, requireAuth?, specialHeader?]
$TEST_CASES = [
    // 公开接口 - 无需认证
    ['group' => '公开接口', 'name' => '健康检查', 'method' => 'GET', 'path' => '/health'],
    ['group' => '公开接口', 'name' => '系统设置', 'method' => 'GET', 'path' => '/settings'],
    ['group' => '公开接口', 'name' => '横幅列表', 'method' => 'GET', 'path' => '/banners'],
    ['group' => '公开接口', 'name' => '服务列表', 'method' => 'GET', 'path' => '/services'],
    ['group' => '公开接口', 'name' => '国家列表', 'method' => 'GET', 'path' => '/countries'],
    ['group' => '公开接口', 'name' => '服务国家组合', 'method' => 'GET', 'path' => '/service-countries'],
    ['group' => '公开接口', 'name' => '已发布服务国家', 'method' => 'GET', 'path' => '/service-countries/published'],
    ['group' => '公开接口', 'name' => '默认系数', 'method' => 'GET', 'path' => '/coefficients/default'],
    ['group' => '公开接口', 'name' => '服务系数', 'method' => 'GET', 'path' => '/coefficients/services'],
    ['group' => '公开接口', 'name' => '会员等级', 'method' => 'GET', 'path' => '/membership/levels'],
    ['group' => '公开接口', 'name' => '充值套餐(legacy)', 'method' => 'GET', 'path' => '/topup-packages'],
    ['group' => '公开接口', 'name' => '积分套餐', 'method' => 'GET', 'path' => '/points/packages'],
    ['group' => '公开接口', 'name' => '支付套餐', 'method' => 'GET', 'path' => '/payment/packages'],
    ['group' => '公开接口', 'name' => '支付配置列表', 'method' => 'GET', 'path' => '/payment-configs'],

    // 业务流程接口 - 需要 token
    ['group' => '业务流程', 'name' => '注册新用户(已成功,这里查账号)', 'method' => 'GET', 'path' => '/auth/account-info', 'auth' => true, 'special' => 'account_info'],
    ['group' => '业务流程', 'name' => '用户资料', 'method' => 'GET', 'path' => '/user/profile', 'auth' => true],
    ['group' => '业务流程', 'name' => '用户余额', 'method' => 'GET', 'path' => '/user/balance', 'auth' => true],
    ['group' => '业务流程', 'name' => '用户会员信息', 'method' => 'GET', 'path' => '/user/membership', 'auth' => true],
    ['group' => '业务流程', 'name' => '交易记录', 'method' => 'GET', 'path' => '/user/transactions', 'auth' => true],
    ['group' => '业务流程', 'name' => '设备注册', 'method' => 'POST', 'path' => '/devices/register', 'auth' => true, 'body' => ['device_id' => 'test_device_' . uniqid(), 'device_type' => 'ios']],
    ['group' => '业务流程', 'name' => '订单列表', 'method' => 'GET', 'path' => '/orders', 'auth' => true, 'query' => ['limit' => 5, 'offset' => 0]],
    ['group' => '业务流程', 'name' => '用户统计', 'method' => 'GET', 'path' => '/stats', 'auth' => true],
    ['group' => '业务流程', 'name' => '通知列表', 'method' => 'GET', 'path' => '/notifications', 'auth' => true],
    ['group' => '业务流程', 'name' => '标记所有通知已读', 'method' => 'POST', 'path' => '/notifications/read-all', 'auth' => true],
    ['group' => '业务流程', 'name' => '创建订单(缺参)', 'method' => 'POST', 'path' => '/orders', 'auth' => true, 'body' => [], 'expect_status' => 400],

    // 错误用例
    ['group' => '认证错误', 'name' => '密码登录(错误)', 'method' => 'POST', 'path' => '/auth/password-login', 'body' => ['login' => 'wronguser', 'password' => 'wrongpass'], 'expect_status' => 401],
    ['group' => '认证错误', 'name' => '密码登录(缺参)', 'method' => 'POST', 'path' => '/auth/password-login', 'body' => [], 'expect_status' => 400],
    ['group' => '认证错误', 'name' => '绑定邮箱(无token)', 'method' => 'POST', 'path' => '/auth/bind-email', 'body' => ['email' => 'test@test.com'], 'expect_status' => 400],
];

// ========== 测试运行 ==========

function runTest($case, $apiUrl, $token = null) {
    $url = $apiUrl . $case['path'];
    if (!empty($case['query'])) {
        $url .= '?' . http_build_query($case['query']);
    }

    $headers = [];
    $body = $case['body'] ?? null;
    $useToken = !empty($case['auth']) && $token;

    if ($useToken) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }

    // 特殊处理：注册
    if (isset($case['special']) && $case['special'] === 'register') {
        $body = [
            'email' => 'apitest_' . time() . '_' . rand(1000, 9999) . '@test.com',
            'password' => 'Test1234!@#',
            'device_id' => 'test_device_' . uniqid(),
        ];
    }

    $response = httpRequest($case['method'], $url, $body, $headers);
    $data = parseResponse($response);

    $expectedStatus = $case['expect_status'] ?? 200;
    $isExpectedStatus = $response['code'] == $expectedStatus;

    // 判定测试是否通过
    $passed = false;
    $reason = '';

    if (!empty($case['expect_status'])) {
        // 期望特定错误码
        $passed = $isExpectedStatus;
        $reason = $passed ? '预期错误码正确' : "期望 HTTP {$expectedStatus}, 实际 {$response['code']}";
    } else {
        // 正常接口
        $passed = $response['code'] >= 200 && $response['code'] < 300;
        $reason = $passed ? 'HTTP 2xx' : "HTTP {$response['code']}";
        if ($passed && isset($data['success']) && $data['success'] === false) {
            $passed = false;
            $reason = '业务失败: ' . ($data['error'] ?? $data['message'] ?? 'unknown');
        }
    }

    return [
        'group' => $case['group'] ?? '其他',
        'name' => $case['name'],
        'method' => $case['method'],
        'path' => $case['path'],
        'url' => $url,
        'http_code' => $response['code'],
        'time_ms' => round($response['time'] * 1000, 2),
        'passed' => $passed,
        'reason' => $reason,
        'response_summary' => is_array($data) ? [
            'success' => $data['success'] ?? null,
            'error' => $data['error'] ?? null,
            'message' => $data['message'] ?? null,
            'data_count' => isset($data['data']) && is_array($data['data']) ? count($data['data']) : null,
        ] : null,
        'auth_used' => $useToken,
    ];
}

// 如果是自动运行模式
if ($autoRun) {
    $results = [];
    $token = null;
    $testEmail = 'apitest_' . time() . '_' . rand(1000, 9999) . '@test.com';
    $testPassword = 'Test1234!@#';

    // 先生成一个测试 token (通过注册)
    $regResp = httpRequest('POST', $apiUrl . '/auth/manual-register', [
        'email' => $testEmail,
        'password' => $testPassword,
        'device_id' => 'test_device_' . uniqid(),
    ]);
    $regData = parseResponse($regResp);

    if (is_array($regData) && !empty($regData['token'])) {
        $token = $regData['token'];
    }

    // 动态获取真实 service_id 和 country_id（用 service-countries 第一个返回的）
    $realServiceId = null;
    $realCountryId = null;
    $scResp = httpRequest('GET', $apiUrl . '/service-countries');
    $scData = parseResponse($scResp);
    if (is_array($scData) && !empty($scData['data'][0])) {
        $realServiceId = $scData['data'][0]['service_id'];
        $realCountryId = $scData['data'][0]['country_id'];
    }

    // 动态构造一些"依赖真实数据"的测试用例
    $dynamicCases = [];
    if ($realServiceId && $realCountryId) {
        $dynamicCases[] = ['group' => '公开接口', 'name' => '价格计算(真实ID)', 'method' => 'GET', 'path' => '/price/calculate', 'query' => ['service_id' => $realServiceId, 'country_id' => $realCountryId]];
        $dynamicCases[] = ['group' => '公开接口', 'name' => '服务价格(真实ID)', 'method' => 'GET', 'path' => '/services/price', 'query' => ['service_id' => $realServiceId, 'country_id' => $realCountryId]];
        $dynamicCases[] = ['group' => '公开接口', 'name' => '计算后服务价格(真实ID)', 'method' => 'GET', 'path' => '/services/price/calculated', 'query' => ['service_id' => $realServiceId, 'country_id' => $realCountryId]];
        $dynamicCases[] = ['group' => '公开接口', 'name' => '库存查询(真实ID)', 'method' => 'GET', 'path' => '/stock', 'query' => ['service_id' => $realServiceId, 'country_id' => $realCountryId]];
        $dynamicCases[] = ['group' => '公开接口', 'name' => '推荐号码(真实ID)', 'method' => 'GET', 'path' => '/recommend/numbers', 'query' => ['service_id' => $realServiceId, 'country_id' => $realCountryId, 'limit' => 5]];
    }

    $allCases = array_merge($TEST_CASES, $dynamicCases);
    foreach ($allCases as $case) {
        $results[] = runTest($case, $apiUrl, $token);
    }

    // 统计
    $groupStats = [];
    foreach ($results as $r) {
        $g = $r['group'];
        if (!isset($groupStats[$g])) {
            $groupStats[$g] = ['pass' => 0, 'fail' => 0, 'total' => 0];
        }
        $groupStats[$g]['total']++;
        if ($r['passed']) $groupStats[$g]['pass']++;
        else $groupStats[$g]['fail']++;
    }

    $totalPass = array_sum(array_column($groupStats, 'pass'));
    $totalFail = array_sum(array_column($groupStats, 'fail'));
    $totalAll = array_sum(array_column($groupStats, 'total'));

    if ($isJson) {
        echo json_encode([
            'success' => true,
            'api_url' => $apiUrl,
            'summary' => [
                'total' => $totalAll,
                'pass' => $totalPass,
                'fail' => $totalFail,
                'pass_rate' => $totalAll > 0 ? round($totalPass / $totalAll * 100, 1) : 0,
            ],
            'group_stats' => $groupStats,
            'token_obtained' => !empty($token),
            'real_ids' => ['service_id' => $realServiceId, 'country_id' => $realCountryId],
            'results' => $results,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
}

?><!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SMS 接码平台 - API 接口测试</title>
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f7fa; color: #333; padding: 20px; line-height: 1.6; }
    .container { max-width: 1200px; margin: 0 auto; }
    h1 { text-align: center; margin-bottom: 10px; color: #1a1a2e; }
    .subtitle { text-align: center; color: #666; margin-bottom: 30px; font-size: 14px; }
    .nav { text-align: center; margin-bottom: 30px; }
    .nav a { display: inline-block; padding: 10px 20px; margin: 0 5px; background: #4CAF50; color: white; text-decoration: none; border-radius: 6px; }
    .nav a:hover { background: #45a049; }
    .nav a.secondary { background: #2196F3; }
    .nav a.secondary:hover { background: #1976D2; }
    .config { background: white; border-radius: 8px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    .config code { background: #f0f0f0; padding: 2px 6px; border-radius: 3px; font-size: 13px; }
    .summary { display: flex; justify-content: space-around; background: white; border-radius: 8px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    .summary-item { text-align: center; }
    .summary-item .count { font-size: 36px; font-weight: bold; }
    .summary-item .label { color: #666; font-size: 14px; }
    .summary-item.pass .count { color: #2e7d32; }
    .summary-item.fail .count { color: #c62828; }
    .summary-item.total .count { color: #1976d2; }
    .summary-item.rate .count { color: #f57c00; }
    .group { background: white; border-radius: 8px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    .group h2 { color: #1a1a2e; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #eee; }
    .group-stats { display: inline-block; margin-left: 10px; font-size: 14px; color: #666; }
    .group-stats .pass { color: #2e7d32; font-weight: bold; }
    .group-stats .fail { color: #c62828; font-weight: bold; }
    table { width: 100%; border-collapse: collapse; font-size: 13px; }
    th, td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #f0f0f0; }
    th { background: #f8f9fa; color: #666; font-weight: 500; font-size: 12px; text-transform: uppercase; }
    tr.test-row td:first-child { width: 30px; text-align: center; }
    tr.test-row.pass td:first-child { color: #2e7d32; font-size: 18px; }
    tr.test-row.fail td:first-child { color: #c62828; font-size: 18px; }
    tr.test-row.fail { background: #fff5f5; }
    .method-tag { display: inline-block; padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: bold; color: white; }
    .method-GET { background: #4caf50; }
    .method-POST { background: #ff9800; }
    .method-PUT { background: #2196f3; }
    .method-DELETE { background: #f44336; }
    .path-cell { font-family: monospace; font-size: 12px; color: #555; }
    .code-cell { font-family: monospace; font-weight: bold; }
    .code-2xx { color: #2e7d32; }
    .code-4xx { color: #f57c00; }
    .code-5xx { color: #c62828; }
    .reason { font-size: 12px; color: #666; }
    .auth-tag { display: inline-block; padding: 1px 5px; background: #9c27b0; color: white; border-radius: 3px; font-size: 10px; margin-left: 5px; }
    .no-token-warning { background: #fff3e0; color: #856404; padding: 10px; border-radius: 4px; margin: 10px 0; }
    .api-url-input { padding: 6px 10px; border: 1px solid #ccc; border-radius: 4px; width: 300px; font-family: monospace; }
    .btn { display: inline-block; padding: 8px 16px; background: #4CAF50; color: white; text-decoration: none; border-radius: 4px; border: none; cursor: pointer; font-size: 14px; }
    .btn:hover { background: #45a049; }
</style>
</head>
<body>
<div class="container">
    <h1>🧪 SMS 接码平台 - API 接口测试</h1>
    <p class="subtitle">自动化测试所有后端 API 端点</p>

    <div class="nav">
        <form method="get" style="display: inline-block; margin: 0 5px;">
            <input type="text" name="api_url" class="api-url-input" placeholder="后端地址" value="<?= htmlspecialchars($apiUrl) ?>">
            <input type="hidden" name="run" value="1">
            <button type="submit" class="btn">🚀 开始测试</button>
        </form>
        <a href="?run=1&api_url=<?= urlencode($apiUrl) ?>&format=json" class="secondary">📄 JSON 输出</a>
        <a href="?action=check" class="secondary">🔍 系统检测</a>
    </div>

    <div class="config">
        <strong>配置信息：</strong><br>
        后端地址: <code><?= htmlspecialchars($apiUrl) ?></code><br>
        API Key: <code><?= !empty($API_KEY) ? substr($API_KEY, 0, 16) . '...' : '(未配置)' ?></code><br>
        测试用例数: <code><?= count($TEST_CASES) ?></code>
    </div>

    <?php if ($autoRun): ?>
        <?php if (empty($token)): ?>
            <div class="no-token-warning">
                ⚠️ <strong>注册失败</strong>：无法获取测试 token。需要认证的接口将全部失败。<br>
                错误：<?= htmlspecialchars($regData['error'] ?? 'unknown') ?> (HTTP <?= $regResp['code'] ?>)
            </div>
        <?php endif; ?>

        <div class="summary">
            <div class="summary-item total">
                <div class="count"><?= $totalAll ?></div>
                <div class="label">总测试数</div>
            </div>
            <div class="summary-item pass">
                <div class="count"><?= $totalPass ?></div>
                <div class="label">通过</div>
            </div>
            <div class="summary-item fail">
                <div class="count"><?= $totalFail ?></div>
                <div class="label">失败</div>
            </div>
            <div class="summary-item rate">
                <div class="count"><?= $totalAll > 0 ? round($totalPass / $totalAll * 100, 1) : 0 ?>%</div>
                <div class="label">通过率</div>
            </div>
        </div>

        <?php
        // 按 group 分组显示
        $grouped = [];
        foreach ($results as $r) {
            $grouped[$r['group']][] = $r;
        }
        foreach ($grouped as $groupName => $items):
            $gs = $groupStats[$groupName];
        ?>
            <div class="group">
                <h2>
                    📦 <?= htmlspecialchars($groupName) ?>
                    <span class="group-stats">
                        <span class="pass">✓ <?= $gs['pass'] ?> 通过</span> /
                        <span class="fail">✗ <?= $gs['fail'] ?> 失败</span> /
                        共 <?= $gs['total'] ?>
                    </span>
                </h2>
                <table>
                    <thead>
                        <tr>
                            <th>状态</th>
                            <th>名称</th>
                            <th>方法</th>
                            <th>路径</th>
                            <th>HTTP</th>
                            <th>耗时</th>
                            <th>说明</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($items as $r): ?>
                        <tr class="test-row <?= $r['passed'] ? 'pass' : 'fail' ?>">
                            <td><?= $r['passed'] ? '✅' : '❌' ?></td>
                            <td>
                                <?= htmlspecialchars($r['name']) ?>
                                <?php if ($r['auth_used']): ?>
                                    <span class="auth-tag">AUTH</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="method-tag method-<?= $r['method'] ?>"><?= $r['method'] ?></span></td>
                            <td class="path-cell"><?= htmlspecialchars($r['path']) ?></td>
                            <td class="code-cell code-<?= substr((string)$r['http_code'], 0, 1) ?>xx"><?= $r['http_code'] ?></td>
                            <td><?= $r['time_ms'] ?>ms</td>
                            <td class="reason">
                                <?= htmlspecialchars($r['reason']) ?>
                                <?php if ($r['response_summary']): ?>
                                    <br>
                                    <small style="color: #999;">
                                        <?php
                                        $s = $r['response_summary'];
                                        $parts = [];
                                        if ($s['success'] !== null) $parts[] = 'success=' . ($s['success'] ? 'true' : 'false');
                                        if ($s['error']) $parts[] = 'error=' . $s['error'];
                                        if ($s['message']) $parts[] = 'message=' . $s['message'];
                                        if ($s['data_count'] !== null) $parts[] = 'data_count=' . $s['data_count'];
                                        echo htmlspecialchars(implode(' | ', $parts));
                                        ?>
                                    </small>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>

        <div class="config">
            <strong>失败接口列表：</strong><br>
            <?php if ($totalFail === 0): ?>
                🎉 全部通过！
            <?php else: ?>
                <?php foreach ($results as $r): ?>
                    <?php if (!$r['passed']): ?>
                        ❌ <code><?= $r['method'] ?> <?= $r['path'] ?></code> - <?= htmlspecialchars($r['reason']) ?><br>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    <?php else: ?>
        <div class="group">
            <h2>📋 准备测试的接口</h2>
            <p>点击上方"开始测试"按钮运行所有测试。</p>
            <p>共 <strong><?= count($TEST_CASES) ?></strong> 个测试用例，覆盖以下分组：</p>
            <?php
            $groups = [];
            foreach ($TEST_CASES as $c) {
                $groups[$c['group']] = ($groups[$c['group']] ?? 0) + 1;
            }
            ?>
            <ul style="margin: 10px 0 10px 20px;">
                <?php foreach ($groups as $g => $count): ?>
                    <li><strong><?= htmlspecialchars($g) ?></strong>：<?= $count ?> 个</li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

</div>
</body>
</html>
