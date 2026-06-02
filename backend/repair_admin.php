<?php
/**
 * 管理员账号修复脚本
 * 用于重置 admin 账号的密码为 admin123
 */

// 加载配置
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/lib/Database.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== SMS 接码平台 管理员修复脚本 ===\n\n";

try {
    $db = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT);

    $username = 'admin';
    $newPassword = 'admin123';
    $hash = password_hash($newPassword, PASSWORD_DEFAULT);

    // 检查用户是否存在
    $admin = $db->query("SELECT * FROM admins WHERE username = ?", [$username])->fetch();

    if ($admin) {
        // 更新现有账号
        $db->update('admins', 
            ['password' => $hash, 'status' => 'active'], 
            "username = ?", 
            [$username]
        );
        echo "✓ 账号 '{$username}' 已更新。\n";
    } else {
        // 创建新账号
        $db->insert('admins', [
            'id' => 'admin_001',
            'username' => $username,
            'password' => $hash,
            'email' => 'admin@example.com',
            'role' => 'admin',
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s')
        ]);
        echo "✓ 账号 '{$username}' 不存在，已新建。\n";
    }

    echo "✓ 密码已重置为: {$newPassword}\n";
    echo "\n修复完成！请尝试使用 admin / admin123 登录。\n";
    echo "登录地址: " . APP_URL . "/admin/\n";

} catch (Exception $e) {
    echo "✗ 错误: " . $e->getMessage() . "\n";
}
?>
