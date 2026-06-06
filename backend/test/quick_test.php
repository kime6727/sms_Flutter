<?php
/**
 * 快速测试脚本 - 逐个测试，避免整体超时
 * 找出挂起的接口
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(60);

$apiUrl = 'http://127.0.0.1:9091';

$tests = [
    ['GET', '/health'],
    ['GET', '/settings'],
    ['GET', '/banners'],
    ['GET', '/services'],
    ['GET', '/countries'],
    ['GET', '/service-countries'],
    ['GET', '/service-countries/published'],
    ['GET', '/coefficients/default'],
    ['GET', '/coefficients/services'],
    ['GET', '/membership/levels'],
    ['GET', '/topup-packages'],
    ['GET', '/points/packages'],
    ['GET', '/payment/packages'],
    ['GET', '/payment-configs'],
    ['GET', '/price/calculate?service_id=1&country_id=1'],
    ['GET', '/services/price?service_id=1&country_id=1'],
    ['GET', '/services/price/calculated?service_id=1&country_id=1'],
    ['GET', '/stock?service_id=1&country_id=1'],
    ['GET', '/recommend/numbers?service_id=1&country_id=1&limit=5'],
    ['GET', '/user/profile'],
    ['GET', '/user/balance'],
    ['GET', '/user/membership'],
    ['GET', '/user/transactions'],
    ['GET', '/orders'],
    ['GET', '/stats'],
    ['GET', '/notifications'],
    ['POST', '/auth/manual-register'],
    ['POST', '/devices/register'],
    ['POST', '/notifications/read-all'],
];

foreach ($tests as $i => $t) {
    [$method, $path] = $t;
    $url = $apiUrl . $path;
    $start = microtime(true);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_CUSTOMREQUEST => $method,
    ]);

    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    $time = round((microtime(true) - $start) * 1000);
    curl_close($ch);

    $status = $err ? 'TIMEOUT/ERR' : ($code >= 200 && $code < 300 ? 'OK' : ($code == 0 ? 'CONN-FAIL' : 'HTTP-ERR'));
    echo sprintf("[%02d] %s %-40s %s (%dms) %s\n",
        $i + 1,
        $method,
        $path,
        str_pad((string)$code, 4),
        $time,
        $status
    );
    if ($err) echo "    ERR: $err\n";
}
