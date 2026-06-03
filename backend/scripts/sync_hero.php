<?php
/**
 * 清空并重新同步 HeroSMS 数据
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/lib/Database.php';
require_once __DIR__ . '/lib/HeroSMS.php';
require_once __DIR__ . '/lib/KeyManager.php';

$db = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT);
$heroSmsApiKey = KeyManager::getHeroSmsApiKey();
if (empty($heroSmsApiKey)) {
    die("ERROR: hero-sms API key is not set in database\n");
}
$hero = new HeroSMS($heroSmsApiKey, HEROSMS_BASE_URL);

// HeroSMS CDN 图标URL
define('SERVICE_ICON_BASE', 'https://cdn.hero-sms.com/assets/img/service/');
define('COUNTRY_ICON_BASE', 'https://cdn.hero-sms.com/assets/img/country/');

echo "========================================\n";
echo "清空并重新同步 HeroSMS 数据\n";
echo "时间: " . date('Y-m-d H:i:s') . "\n";
echo "========================================\n\n";

// 1. 清空现有数据
echo "【准备】清空旧数据...\n";
$db->query("SET FOREIGN_KEY_CHECKS = 0");
$db->query("TRUNCATE TABLE service_countries");
$db->query("TRUNCATE TABLE services");
$db->query("TRUNCATE TABLE countries");
$db->query("SET FOREIGN_KEY_CHECKS = 1");
echo "     清空完成!\n\n";

// 2. 获取余额
echo "【1/4】获取账户余额...\n";
$balance = $hero->getBalance();
if ($balance['success']) {
    echo "     余额: $" . number_format($balance['balance'], 2) . "\n";
} else {
    echo "     警告: 无法获取余额 (" . ($balance['error'] ?? '未知') . ")\n";
}

// 3. 同步服务列表
echo "\n【2/4】同步服务列表...\n";
$result = $hero->getServicesList();
if ($result['success'] && !empty($result['services'])) {
    $total = count($result['services']);
    echo "     共 {$total} 个服务，开始导入...\n";

    foreach ($result['services'] as $i => $s) {
        $code = $s['code'] ?? '';
        $name = $s['name'] ?? $code;
        $code = strtolower($code);

        // 服务图标: https://cdn.hero-sms.com/assets/img/service/{code}0.webp
        $icon = SERVICE_ICON_BASE . $code . '0.webp';

        $db->insert('services', [
            'hero_service_id' => $code,
            'name' => $name,
            'name_en' => $name,
            'name_cn' => $name,
            'code' => $code,
            'icon' => $icon,
            'active' => 1,
            'is_published' => 0,
            'sort_order' => $i,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        if (($i + 1) % 100 == 0) {
            echo "     已处理 " . ($i + 1) . "/{$total} 个服务...\n";
        }
    }
    echo "     完成! 新增 {$total} 个服务\n";
} else {
    echo "     失败: " . ($result['error'] ?? '无法获取服务列表') . "\n";
}

// 4. 同步国家列表
echo "\n【3/4】同步国家列表...\n";
$countriesResult = $hero->getCountries();
if ($countriesResult['success'] && !empty($countriesResult['countries'])) {
    $total = count($countriesResult['countries']);
    echo "     共 {$total} 个国家，开始导入...\n";

    foreach ($countriesResult['countries'] as $i => $c) {
        $heroId = $c['id'] ?? $i;
        $nameEn = $c['eng'] ?? $c['rus'] ?? 'Unknown';
        $nameCn = $c['chn'] ?? $nameEn;
        $iso = $c['iso'] ?? '';
        $phoneCode = $c['phone_code'] ?? $hero->getPhoneCodeByCountryId($heroId);

        // 国家图标: https://cdn.hero-sms.com/assets/img/country/{hero_country_id}.svg
        $flag = COUNTRY_ICON_BASE . $heroId . '.svg';

        $db->insert('countries', [
            'hero_country_id' => $heroId,
            'name' => $nameEn,
            'name_en' => $nameEn,
            'name_cn' => $nameCn,
            'code' => $iso,
            'flag' => $flag,
            'phone_code' => $phoneCode,
            'active' => 1,
            'sort_order' => $heroId,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        if (($i + 1) % 50 == 0) {
            echo "     已处理 " . ($i + 1) . "/{$total} 个国家...\n";
        }
    }
    echo "     完成! 新增 {$total} 个国家\n";
} else {
    echo "     失败: " . ($countriesResult['error'] ?? '无法获取国家列表') . "\n";
}

// 5. 同步服务-国家价格 (逐个服务获取)
echo "\n【4/4】同步服务价格...\n";
$services = $db->query("SELECT id, hero_service_id FROM services")->fetchAll();
$totalPrices = 0;

echo "     共 " . count($services) . " 个服务，开始获取价格...\n";

foreach ($services as $service) {
    $priceResult = $hero->getServiceCountries($service['hero_service_id']);

    if ($priceResult['success'] && !empty($priceResult['countries'])) {
        foreach ($priceResult['countries'] as $price) {
            $country = $db->query(
                "SELECT id FROM countries WHERE hero_country_id = ?",
                [$price['id']]
            )->fetch();

            if ($country) {
                $db->insert('service_countries', [
                    'service_id' => $service['id'],
                    'country_id' => $country['id'],
                    'price' => $price['cost'] ?? 0,
                    'active' => 1,
                    'is_published' => 0,
                    'is_auto' => 0,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                $totalPrices++;
            }
        }
    }

    echo "     已处理服务: {$service['hero_service_id']} ({$service['id']}/" . count($services) . ")\n";
}

echo "     完成! 新增 {$totalPrices} 个价格记录\n";

echo "\n========================================\n";
echo "同步完成!\n";
echo "========================================\n";
echo "\n注意: 所有服务和国家默认未上架\n";
echo "      请登录后台手动上架服务和国家\n";
echo "      访问: " . APP_URL . "/admin/\n";
echo "========================================\n";