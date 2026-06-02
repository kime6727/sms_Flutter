<?php
/**
 * 为现有用户生成账号和密码（网页版）
 * 访问方式：http://your-domain.com/migrations/migrate_existing_users_web.php
 * 
 * 安全提示：执行完成后请删除此文件！
 */

// 设置为纯文本输出
header('Content-Type: text/plain; charset=utf-8');

// 引入数据库配置
require_once __DIR__ . '/../config/database.php';

function generateUsername($db) {
    $maxAttempts = 10;
    for ($i = 0; $i < $maxAttempts; $i++) {
        $randomNumber = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $username = 'user_' . $randomNumber;
        
        // 检查是否已存在
        $existing = $db->query("SELECT id FROM users WHERE username = ?", [$username])->fetch();
        if (!$existing) {
            return $username;
        }
    }
    
    // 如果随机生成失败，使用时间戳
    return 'user_' . substr(time(), -6);
}

function generatePassword() {
    $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $lowercase = 'abcdefghijklmnopqrstuvwxyz';
    $numbers = '0123456789';
    
    // 确保至少包含一个大写、一个小写、一个数字
    $password = '';
    $password .= $uppercase[rand(0, strlen($uppercase) - 1)];
    $password .= $lowercase[rand(0, strlen($lowercase) - 1)];
    $password .= $lowercase[rand(0, strlen($lowercase) - 1)];
    $password .= $numbers[rand(0, strlen($numbers) - 1)];
    $password .= $numbers[rand(0, strlen($numbers) - 1)];
    $password .= $numbers[rand(0, strlen($numbers) - 1)];
    $password .= $numbers[rand(0, strlen($numbers) - 1)];
    $password .= $numbers[rand(0, strlen($numbers) - 1)];
    
    // 打乱顺序
    return str_shuffle($password);
}

try {
    $db = new Database();
    
    echo "===========================================\n";
    echo "用户账号密码迁移工具\n";
    echo "===========================================\n\n";
    
    // 获取所有没有 username 的用户
    $users = $db->query("SELECT id FROM users WHERE username IS NULL OR username = ''")->fetchAll();
    
    echo "找到 " . count($users) . " 个需要迁移的用户\n\n";
    
    if (count($users) == 0) {
        echo "所有用户都已有账号密码，无需迁移。\n";
        exit;
    }
    
    $successCount = 0;
    $failCount = 0;
    $credentials = [];
    
    foreach ($users as $user) {
        try {
            $username = generateUsername($db);
            $password = generatePassword();
            $passwordHash = password_hash($password, PASSWORD_BCRYPT);
            
            $db->query(
                "UPDATE users SET username = ?, password_hash = ? WHERE id = ?",
                [$username, $passwordHash, $user['id']]
            );
            
            $credentials[] = [
                'user_id' => $user['id'],
                'username' => $username,
                'password' => $password
            ];
            
            echo "✓ 用户 {$user['id']}: {$username} / {$password}\n";
            $successCount++;
            
        } catch (Exception $e) {
            echo "✗ 用户 {$user['id']} 迁移失败: " . $e->getMessage() . "\n";
            $failCount++;
        }
    }
    
    echo "\n===========================================\n";
    echo "迁移完成！\n";
    echo "成功: {$successCount}\n";
    echo "失败: {$failCount}\n";
    echo "===========================================\n\n";
    
    if (!empty($credentials)) {
        echo "请保存以下账号密码信息：\n\n";
        echo "用户ID\t\t\t\t账号\t\t密码\n";
        echo "-----------------------------------------------------------\n";
        foreach ($credentials as $cred) {
            echo "{$cred['user_id']}\t{$cred['username']}\t{$cred['password']}\n";
        }
        echo "\n";
    }
    
    echo "\n⚠️  重要提示：\n";
    echo "1. 请将上述账号密码信息保存到安全的地方\n";
    echo "2. 执行完成后请立即删除此文件（migrate_existing_users_web.php）\n";
    echo "3. 用户可以在 App 设置中查看自己的账号密码\n";
    
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
    exit(1);
}
