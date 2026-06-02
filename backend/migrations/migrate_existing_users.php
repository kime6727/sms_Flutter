<?php
/**
 * 为现有用户生成账号和密码
 * 执行时间：2026-03-27
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lib/Database.php';

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
    $db = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT);
    
    // 获取所有没有 password_hash 的用户
    $users = $db->query("SELECT id, username FROM users WHERE password_hash IS NULL OR password_hash = ''")->fetchAll();
    
    echo "找到 " . count($users) . " 个需要迁移的用户\n";
    
    $successCount = 0;
    $failCount = 0;
    
    foreach ($users as $user) {
        try {
            // 如果用户已有 username，使用现有的；否则生成新的
            $username = !empty($user['username']) ? $user['username'] : generateUsername($db);
            $password = generatePassword();
            $passwordHash = password_hash($password, PASSWORD_BCRYPT);
            
            // 更新密码，如果没有 username 也一起更新
            if (empty($user['username'])) {
                $db->query(
                    "UPDATE users SET username = ?, password_hash = ? WHERE id = ?",
                    [$username, $passwordHash, $user['id']]
                );
            } else {
                $db->query(
                    "UPDATE users SET password_hash = ? WHERE id = ?",
                    [$passwordHash, $user['id']]
                );
            }
            
            echo "用户 {$user['id']}: {$username} / {$password}\n";
            $successCount++;
            
        } catch (Exception $e) {
            echo "用户 {$user['id']} 迁移失败: " . $e->getMessage() . "\n";
            $failCount++;
        }
    }
    
    echo "\n迁移完成！\n";
    echo "成功: {$successCount}\n";
    echo "失败: {$failCount}\n";
    
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
    exit(1);
}
