<?php
/**
 * 用户账号密码迁移工具
 * 访问方式：https://newsms.weburl.cloudns.be/admin/migrations/migrate_users.php
 */

session_start();

// 简单的安全检查（可选）
$secret_key = $_GET['key'] ?? '';
if ($secret_key !== 'migrate2026') {
    die('Access denied. Please add ?key=migrate2026 to URL');
}

// 设置为纯文本输出
header('Content-Type: text/html; charset=utf-8');

// 引入数据库配置
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../lib/Database.php';

function generateUsername($db) {
    $maxAttempts = 10;
    for ($i = 0; $i < 10; $i++) {
        $randomNumber = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $username = 'user_' . $randomNumber;
        
        $existing = $db->query("SELECT id FROM users WHERE username = ?", [$username])->fetch();
        if (!$existing) {
            return $username;
        }
    }
    
    return 'user_' . substr(time(), -6);
}

function generatePassword() {
    $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $lowercase = 'abcdefghijklmnopqrstuvwxyz';
    $numbers = '0123456789';
    
    $password = '';
    $password .= $uppercase[rand(0, strlen($uppercase) - 1)];
    $password .= $lowercase[rand(0, strlen($lowercase) - 1)];
    $password .= $lowercase[rand(0, strlen($lowercase) - 1)];
    $password .= $numbers[rand(0, strlen($numbers) - 1)];
    $password .= $numbers[rand(0, strlen($numbers) - 1)];
    $password .= $numbers[rand(0, strlen($numbers) - 1)];
    $password .= $numbers[rand(0, strlen($numbers) - 1)];
    $password .= $numbers[rand(0, strlen($numbers) - 1)];
    
    return str_shuffle($password);
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>用户账号密码迁移</title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 20px;
            line-height: 1.6;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: #252526;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.3);
        }
        h1 {
            color: #4ec9b0;
            border-bottom: 2px solid #4ec9b0;
            padding-bottom: 10px;
        }
        .success {
            color: #4ec9b0;
        }
        .error {
            color: #f48771;
        }
        .warning {
            color: #dcdcaa;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background: #1e1e1e;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border: 1px solid #3e3e42;
        }
        th {
            background: #2d2d30;
            color: #4ec9b0;
            font-weight: bold;
        }
        tr:hover {
            background: #2a2d2e;
        }
        .stats {
            background: #2d2d30;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .button {
            background: #0e639c;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            margin: 10px 5px;
        }
        .button:hover {
            background: #1177bb;
        }
        .copy-btn {
            background: #4ec9b0;
            color: #1e1e1e;
            padding: 5px 10px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            font-size: 12px;
        }
        .copy-btn:hover {
            background: #5fd9c0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔐 用户账号密码迁移工具</h1>
        
        <?php
        try {
            echo '<p class="warning">🔍 正在连接数据库...</p>';
            $db = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT);
            echo '<p class="success">✅ 数据库连接成功</p>';
            
            // 先查看总用户数
            $totalUsers = $db->query("SELECT COUNT(*) as count FROM users")->fetch();
            echo '<p>📊 数据库中共有 <strong>' . $totalUsers['count'] . '</strong> 个用户</p>';
            
            // 获取所有没有 password_hash 的用户
            echo '<p class="warning">🔍 正在查询需要迁移的用户...</p>';
            $users = $db->query("SELECT id, device_id, username FROM users WHERE password_hash IS NULL OR password_hash = ''")->fetchAll();
            echo '<p class="success">✅ 查询完成</p>';
            
            echo '<div class="stats">';
            echo '<p>📊 找到 <strong>' . count($users) . '</strong> 个需要迁移的用户</p>';
            echo '</div>';
            
            if (count($users) == 0) {
                echo '<p class="success">✅ 所有用户都已有账号密码，无需迁移。</p>';
                echo '<p><a href="../index.php">返回后台</a></p>';
                exit;
            }
            
            $successCount = 0;
            $failCount = 0;
            $credentials = [];
            
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
                    
                    $credentials[] = [
                        'user_id' => $user['id'],
                        'device_id' => $user['device_id'],
                        'username' => $username,
                        'password' => $password
                    ];
                    
                    $successCount++;
                    
                } catch (Exception $e) {
                    $failCount++;
                    echo '<p class="error">✗ 用户 ' . htmlspecialchars($user['id']) . ' 迁移失败: ' . htmlspecialchars($e->getMessage()) . '</p>';
                }
            }
            
            echo '<div class="stats">';
            echo '<p class="success">✅ 成功: ' . $successCount . '</p>';
            if ($failCount > 0) {
                echo '<p class="error">❌ 失败: ' . $failCount . '</p>';
            }
            echo '</div>';
            
            if (!empty($credentials)) {
                echo '<h2>📋 生成的账号密码列表</h2>';
                echo '<p class="warning">⚠️ 请立即保存以下信息到安全的地方！</p>';
                
                echo '<table id="credentialsTable">';
                echo '<thead><tr>';
                echo '<th>用户ID</th>';
                echo '<th>设备ID</th>';
                echo '<th>账号</th>';
                echo '<th>密码</th>';
                echo '<th>操作</th>';
                echo '</tr></thead>';
                echo '<tbody>';
                
                foreach ($credentials as $cred) {
                    echo '<tr>';
                    echo '<td>' . htmlspecialchars($cred['user_id']) . '</td>';
                    echo '<td>' . htmlspecialchars(substr($cred['device_id'], 0, 20)) . '...</td>';
                    echo '<td><strong>' . htmlspecialchars($cred['username']) . '</strong></td>';
                    echo '<td><code>' . htmlspecialchars($cred['password']) . '</code></td>';
                    echo '<td><button class="copy-btn" onclick="copyCredentials(\'' . 
                         htmlspecialchars($cred['username']) . '\', \'' . 
                         htmlspecialchars($cred['password']) . '\')">复制</button></td>';
                    echo '</tr>';
                }
                
                echo '</tbody></table>';
                
                echo '<button class="button" onclick="exportToCSV()">📥 导出为 CSV</button>';
                echo '<button class="button" onclick="copyAllCredentials()">📋 复制全部</button>';
            }
            
            echo '<div class="stats" style="margin-top: 30px;">';
            echo '<h3>⚠️ 重要提示</h3>';
            echo '<ol>';
            echo '<li>请将上述账号密码信息保存到安全的地方</li>';
            echo '<li>用户可以在 App 设置中查看自己的账号密码</li>';
            echo '<li>建议通知用户修改默认密码</li>';
            echo '<li>迁移完成后可以关闭此页面</li>';
            echo '</ol>';
            echo '</div>';
            
            echo '<p><a href="../index.php" class="button">返回后台</a></p>';
            
        } catch (Exception $e) {
            echo '<p class="error">❌ 错误: ' . htmlspecialchars($e->getMessage()) . '</p>';
            echo '<p class="error">详细信息: ' . htmlspecialchars($e->getTraceAsString()) . '</p>';
        }
        ?>
    </div>
    
    <script>
    function copyCredentials(username, password) {
        const text = `账号: ${username}\n密码: ${password}`;
        navigator.clipboard.writeText(text).then(() => {
            alert('已复制到剪贴板！');
        });
    }
    
    function copyAllCredentials() {
        const table = document.getElementById('credentialsTable');
        const rows = table.querySelectorAll('tbody tr');
        let text = '用户ID\t账号\t密码\n';
        
        rows.forEach(row => {
            const cells = row.querySelectorAll('td');
            text += `${cells[0].textContent}\t${cells[2].textContent}\t${cells[3].textContent}\n`;
        });
        
        navigator.clipboard.writeText(text).then(() => {
            alert('已复制全部账号密码到剪贴板！');
        });
    }
    
    function exportToCSV() {
        const table = document.getElementById('credentialsTable');
        const rows = table.querySelectorAll('tbody tr');
        let csv = '用户ID,设备ID,账号,密码\n';
        
        rows.forEach(row => {
            const cells = row.querySelectorAll('td');
            csv += `"${cells[0].textContent}","${cells[1].textContent}","${cells[2].textContent}","${cells[3].textContent}"\n`;
        });
        
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'user_credentials_' + new Date().getTime() + '.csv';
        link.click();
    }
    </script>
</body>
</html>
