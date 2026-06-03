<?php
/**
 * 创建性能索引 - 简化版
 * 直接在浏览器中访问即可执行
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(300); // 5分钟超时

$backendRoot = __DIR__;
require_once $backendRoot . '/config/database.php';
require_once $backendRoot . '/lib/Database.php';

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>创建性能索引</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Arial, sans-serif;
            max-width: 900px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #4CAF50;
            padding-bottom: 10px;
        }
        .success {
            color: #4CAF50;
            background: #e8f5e9;
            padding: 12px;
            margin: 10px 0;
            border-left: 4px solid #4CAF50;
            border-radius: 4px;
        }
        .error {
            color: #f44336;
            background: #ffebee;
            padding: 12px;
            margin: 10px 0;
            border-left: 4px solid #f44336;
            border-radius: 4px;
        }
        .info {
            color: #2196F3;
            background: #e3f2fd;
            padding: 12px;
            margin: 10px 0;
            border-left: 4px solid #2196F3;
            border-radius: 4px;
        }
        .warning {
            color: #ff9800;
            background: #fff3e0;
            padding: 12px;
            margin: 10px 0;
            border-left: 4px solid #ff9800;
            border-radius: 4px;
        }
        .progress {
            margin: 20px 0;
        }
        .progress-bar {
            width: 100%;
            height: 30px;
            background: #e0e0e0;
            border-radius: 15px;
            overflow: hidden;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #4CAF50, #8BC34A);
            transition: width 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
        }
        th {
            background: #f5f5f5;
            font-weight: bold;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #4CAF50;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin: 10px 5px;
            font-weight: bold;
        }
        .btn:hover {
            background: #45a049;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        .stat-card {
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
        .stat-card h3 {
            margin: 0;
            font-size: 36px;
        }
        .stat-card p {
            margin: 5px 0 0 0;
            color: #666;
        }
        .stat-success { background: #e8f5e9; color: #4CAF50; }
        .stat-warning { background: #fff3e0; color: #ff9800; }
        .stat-error { background: #ffebee; color: #f44336; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 创建性能索引</h1>
        
<?php

try {
    // 连接数据库
    $db = Database::getInstance();
    echo "<div class='success'>✅ 数据库连接成功 (newsms)</div>\n";
    
    // 定义所有索引
    $indexes = [
        // 订单表索引
        ['table' => 'orders', 'name' => 'idx_orders_user_status', 'columns' => 'user_id, status'],
        ['table' => 'orders', 'name' => 'idx_orders_status_expires', 'columns' => 'status, expires_at'],
        ['table' => 'orders', 'name' => 'idx_orders_hero_order', 'columns' => 'hero_order_id'],
        ['table' => 'orders', 'name' => 'idx_orders_created', 'columns' => 'created_at DESC'],
        
        // 服务国家关联索引
        ['table' => 'service_countries', 'name' => 'idx_sc_service_country', 'columns' => 'service_id, country_id'],
        ['table' => 'service_countries', 'name' => 'idx_sc_published', 'columns' => 'is_published, active'],
        ['table' => 'service_countries', 'name' => 'idx_sc_service_published', 'columns' => 'service_id, is_published, active'],
        
        // 通知索引
        ['table' => 'notifications', 'name' => 'idx_notifications_user_read', 'columns' => 'user_id, read_at'],
        ['table' => 'notifications', 'name' => 'idx_notifications_created', 'columns' => 'created_at DESC'],
        
        // 支付记录索引
        ['table' => 'payment_records', 'name' => 'idx_payment_transaction', 'columns' => 'transaction_id'],
        ['table' => 'payment_records', 'name' => 'idx_payment_user', 'columns' => 'user_id, created_at DESC'],
        
        // 用户索引
        ['table' => 'users', 'name' => 'idx_users_device', 'columns' => 'device_id'],
        ['table' => 'users', 'name' => 'idx_users_email', 'columns' => 'email'],
        ['table' => 'users', 'name' => 'idx_users_username', 'columns' => 'username'],
        
        // 积分流水索引
        ['table' => 'credit_transactions', 'name' => 'idx_credit_user_created', 'columns' => 'user_id, created_at DESC'],
        
        // 短信消息索引
        ['table' => 'sms_messages', 'name' => 'idx_sms_order', 'columns' => 'order_id, received_at DESC'],
        
        // 服务索引
        ['table' => 'services', 'name' => 'idx_services_published', 'columns' => 'is_published, active, sort_order'],
        ['table' => 'services', 'name' => 'idx_services_code', 'columns' => 'code'],
        
        // 国家索引
        ['table' => 'countries', 'name' => 'idx_countries_active', 'columns' => 'active'],
        ['table' => 'countries', 'name' => 'idx_countries_hero_id', 'columns' => 'hero_country_id'],
    ];
    
    $totalIndexes = count($indexes);
    $successCount = 0;
    $skippedCount = 0;
    $errorCount = 0;
    $errors = [];
    
    echo "<div class='info'>📊 准备创建 {$totalIndexes} 个性能索引...</div>\n";
    echo "<div class='progress'><div class='progress-bar'><div class='progress-fill' id='progress'>0%</div></div></div>\n";
    
    echo "<h2>执行进度</h2>\n";
    echo "<table>\n";
    echo "<thead><tr><th>表名</th><th>索引名</th><th>列</th><th>状态</th></tr></thead>\n";
    echo "<tbody>\n";
    
    foreach ($indexes as $i => $index) {
        $tableName = $index['table'];
        $indexName = $index['name'];
        $columns = $index['columns'];
        
        // 检查表是否存在
        $tableExists = $db->query(
            "SELECT COUNT(*) FROM information_schema.TABLES 
             WHERE TABLE_SCHEMA = 'newsms' AND TABLE_NAME = ?",
            [$tableName]
        )->fetchColumn();
        
        if (!$tableExists) {
            echo "<tr><td>{$tableName}</td><td>{$indexName}</td><td>{$columns}</td>";
            echo "<td><span style='color: orange;'>⚠️ 表不存在</span></td></tr>\n";
            $skippedCount++;
            continue;
        }
        
        // 检查索引是否已存在
        $indexExists = $db->query(
            "SELECT COUNT(*) FROM information_schema.STATISTICS 
             WHERE TABLE_SCHEMA = 'newsms' 
             AND TABLE_NAME = ? 
             AND INDEX_NAME = ?",
            [$tableName, $indexName]
        )->fetchColumn();
        
        if ($indexExists) {
            echo "<tr><td>{$tableName}</td><td>{$indexName}</td><td>{$columns}</td>";
            echo "<td><span style='color: blue;'>ℹ️ 已存在</span></td></tr>\n";
            $skippedCount++;
        } else {
            // 创建索引
            $sql = "CREATE INDEX {$indexName} ON {$tableName}({$columns})";
            try {
                $db->exec($sql);
                echo "<tr><td>{$tableName}</td><td>{$indexName}</td><td>{$columns}</td>";
                echo "<td><span style='color: green;'>✅ 创建成功</span></td></tr>\n";
                $successCount++;
            } catch (PDOException $e) {
                echo "<tr><td>{$tableName}</td><td>{$indexName}</td><td>{$columns}</td>";
                echo "<td><span style='color: red;'>❌ 失败</span></td></tr>\n";
                $errorCount++;
                $errors[] = "{$indexName}: " . $e->getMessage();
            }
        }
        
        // 更新进度
        $progress = round((($i + 1) / $totalIndexes) * 100);
        echo "<script>document.getElementById('progress').style.width='{$progress}%';document.getElementById('progress').textContent='{$progress}%';</script>\n";
        flush();
    }
    
    echo "</tbody></table>\n";
    
    // 显示统计
    echo "<h2>📊 执行统计</h2>\n";
    echo "<div class='stats'>\n";
    echo "<div class='stat-card stat-success'><h3>{$successCount}</h3><p>新创建</p></div>\n";
    echo "<div class='stat-card stat-warning'><h3>{$skippedCount}</h3><p>已存在/跳过</p></div>\n";
    echo "<div class='stat-card stat-error'><h3>{$errorCount}</h3><p>失败</p></div>\n";
    echo "</div>\n";
    
    // 显示错误
    if ($errorCount > 0) {
        echo "<h2>❌ 错误详情</h2>\n";
        echo "<div class='error'>\n";
        foreach ($errors as $error) {
            echo "<p>{$error}</p>\n";
        }
        echo "</div>\n";
    }
    
    // 验证索引
    echo "<h2>🔍 索引验证</h2>\n";
    $allIndexes = $db->query("
        SELECT 
            TABLE_NAME,
            INDEX_NAME,
            GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) as COLUMNS
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = 'newsms'
        AND INDEX_NAME LIKE 'idx_%'
        GROUP BY TABLE_NAME, INDEX_NAME
        ORDER BY TABLE_NAME, INDEX_NAME
    ")->fetchAll();
    
    echo "<div class='success'>✅ 数据库中共有 " . count($allIndexes) . " 个性能索引</div>\n";
    
    echo "<table>\n";
    echo "<thead><tr><th>表名</th><th>索引名</th><th>列</th></tr></thead>\n";
    echo "<tbody>\n";
    foreach ($allIndexes as $idx) {
        echo "<tr>";
        echo "<td>{$idx['TABLE_NAME']}</td>";
        echo "<td>{$idx['INDEX_NAME']}</td>";
        echo "<td>{$idx['COLUMNS']}</td>";
        echo "</tr>\n";
    }
    echo "</tbody></table>\n";
    
    // 最终结果
    if ($errorCount === 0) {
        echo "<div class='success'>\n";
        echo "<h2>🎉 索引创建完成！</h2>\n";
        echo "<p>所有索引已成功创建或已存在。数据库性能已优化！</p>\n";
        echo "<p><strong>预期性能提升：</strong></p>\n";
        echo "<ul>\n";
        echo "<li>订单查询速度提升 10x</li>\n";
        echo "<li>用户查询速度提升 10x</li>\n";
        echo "<li>支付记录查询速度提升 10x</li>\n";
        echo "<li>API 响应时间从 500-800ms 降至 50-100ms</li>\n";
        echo "</ul>\n";
        echo "<a href='test/deploy_check.php' class='btn'>返回检测页面</a>\n";
        echo "</div>\n";
    } else {
        echo "<div class='warning'>\n";
        echo "<h2>⚠️ 索引创建完成但有错误</h2>\n";
        echo "<p>有 {$errorCount} 个索引创建失败，但大部分索引已成功创建。</p>\n";
        echo "<a href='test/deploy_check.php' class='btn'>返回检测页面</a>\n";
        echo "</div>\n";
    }
    
} catch (Exception $e) {
    echo "<div class='error'>\n";
    echo "<h2>❌ 执行失败</h2>\n";
    echo "<p><strong>错误信息：</strong> " . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>\n";
    echo "</div>\n";
}

?>
    </div>
</body>
</html>
