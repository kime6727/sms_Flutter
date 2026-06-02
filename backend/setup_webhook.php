<?php
/**
 * 配置 HeroSMS Webhook 地址（运行一次即可）
 *
 * 使用: cd backend && php setup_webhook.php
 *
 * 此脚本将当前服务的 webhook URL 注册到 HeroSMS，
 * 使 HeroSMS 在短信到达时主动 POST 通知到本系统。
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/lib/Database.php';
require_once __DIR__ . '/lib/HeroSMS.php';
require_once __DIR__ . '/lib/KeyManager.php';

// 从 .env 读取本服务的访问地址
$env_file = __DIR__ . '/.env';
if (!file_exists($env_file)) {
    die("错误: .env 文件不存在\n");
}
$env = parse_ini_file($env_file, false, INI_SCANNER_RAW);
$appUrl = rtrim($env['APP_URL'] ?? '', '/');

if (empty($appUrl)) {
    die("错误: APP_URL 未在 .env 中配置\n");
}

$webhookUrl = $appUrl . '/webhook/hero-sms';

echo "========================================\n";
echo "  HeroSMS Webhook 配置脚本\n";
echo "========================================\n";
echo "Webhook URL: $webhookUrl\n";
echo "HeroSMS 将在短信到达时 POST 到此地址\n\n";

// 检查 webhook.php 文件是否存在
$webhookFile = __DIR__ . '/webhook.php';
if (!file_exists($webhookFile)) {
    die("错误: webhook.php 不存在，请先创建 webhook 处理脚本\n");
}
echo "✓ webhook.php 已就绪\n\n";

echo "确认配置? (y/n): ";
$confirm = trim(fgets(STDIN));
if (strtolower($confirm) !== 'y') {
    die("已取消\n");
}

// 读取 HeroSMS API Key
$heroSmsApiKey = KeyManager::getHeroSmsApiKey();
if (empty($heroSmsApiKey)) {
    die("错误: HeroSMS API Key 未在数据库 system_settings 中配置\n");
}

// 初始化 HeroSMS
$db = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT);
$heroSMS = new HeroSMS($heroSmsApiKey, HEROSMS_BASE_URL);

echo "正在配置 webhook...\n";

// 1. 先检查当前 webhook 配置
$currentWebhook = $heroSMS->getWebhookUrl();
if ($currentWebhook['success'] && !empty($currentWebhook['url'])) {
    echo "当前 HeroSMS 上的 webhook URL: " . $currentWebhook['url'] . "\n";
    if (rtrim($currentWebhook['url'], '/') === $webhookUrl) {
        echo "✓ Webhook 已经正确配置，无需更新\n";
        exit(0);
    }
    echo "→ 需要更新为: $webhookUrl\n\n";
}

// 2. 设置 webhook URL
$result = $heroSMS->setWebhookUrl($webhookUrl);

if ($result['success']) {
    // 保存到数据库，标记已配置
    try {
        $db->query(
            "INSERT INTO system_settings (`key`, `value`, `updated_at`) VALUES ('webhook_configured_url', ?, NOW())
             ON DUPLICATE KEY UPDATE `value` = ?, `updated_at` = NOW()",
            [$webhookUrl, $webhookUrl]
        );
        echo "✓ Webhook 配置成功!\n";
        echo "  URL: $webhookUrl\n";
        echo "  HeroSMS 现在会将短信通知 POST 到此地址\n";
        echo "  设置已保存到数据库 system_settings\n";
    } catch (Exception $e) {
        echo "⚠ Webhook 已在 HeroSMS 配置，但数据库保存失败: " . $e->getMessage() . "\n";
    }
} else {
    $error = $result['message'] ?? $result['error'] ?? '未知错误';
    echo "✗ 配置失败: $error\n";

    // 提供诊断建议
    echo "\n可能的原因:\n";
    echo "  1. HeroSMS API Key 无效或过期\n";
    echo "  2. APP_URL 在 HeroSMS 端不可达（检查公网访问）\n";
    echo "  3. HeroSMS API 端点暂时不可用\n";
    echo "\n请验证 APP_URL ($appUrl) 是否可从公网访问:\n";
    echo "  curl -I $webhookUrl\n";

    exit(1);
}

echo "\n配置完成!\n";
