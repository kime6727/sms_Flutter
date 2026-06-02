<?php
/**
 * 数据库迁移执行脚本
 * 用于执行 add_performance_indexes.sql
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

$backendRoot = dirname(__DIR__);
require_once $backendRoot . '/config/database.php';
require_once $backendRoot . '/lib/Database.php';

echo "<!DOCTYPE html>\n";
echo "<html><head><meta charset='UTF-8'><title>数据库迁移</title>";
echo "<style>
body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
h1 { color: #333; }
.success { color: green; padding: 10px; background: #e8f5e9; border-left: 4px solid green; margin: 10px 0; }
.error { color: red; padding: 10px; background: #ffebee; border-left: 4px solid red; margin: 10px 0; }
.info { color: blue; padding: 10px; background: #e3f2fd; border-left: 4px solid blue; margin: 10px 0; }
.sql { background: #f5f5f5; padding: 10px; margin: 10px 0; border-radius: 4px; font-family: monospace; }
table { width: 100%; border-collapse: collapse; margin: 20px 0; }
th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
th { background: #f5f5f5; }
</style></head><body>";

echo "<h1>🔧 数据库迁移执行</h1>\n";

try {
    // 连接数据库
    $db = Database::getInstance();
    echo "<div class='success'>✅ 数据库连接成功</div>\n";
    
    // 读取 SQL 文件
    $sqlFile = __DIR__ . '/add_performance_indexes.sql';
    if (!file_exists($sqlFile)) {
        throw new Exception("SQL 文件不存在: {$sqlFile}");
    }
    
    $sql = file_get_contents($sqlFile);
    echo "<div class='info'>📄 已读取 SQL 文件: add_performance_indexes.sql</div>\n";
    
    // 分割 SQL 语句
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        function($stmt) {
            return !empty($stmt) && 
                   strpos($stmt, '--') !== 0 && 
                   strpos(strtoupper($stmt), 'SELECT') !== 0;
        }
    );
    
    echo "<div class='info'>📊 共 " . count($statements) . " 条 SQL 语句待执行</div>\n";
    
    // 执行每条语句
    $successCount = 0;
    $errorCount = 0;
    $skippedCount = 0;
    
    echo "<h2>执行结果</h2>\n";
    
    foreach ($statements as $i => $statement) {
        $statement = trim($statement);
        if (empty($statement)) continue;
        
        // 提取索引名称
        preg_match('/CREATE INDEX.*?(\w+)\s+ON/i', $statement, $matches);
        $indexName = $matches[1] ?? "语句 " . ($i + 1);
        
        try {
            $db->exec($statement);
            echo "<div class='success'>✅ {$indexName} - 创建成功</div>\n";
            $successCount++;
        } catch (PDOException $e) {
            // 检查是否是索引已存在的错误
            if (strpos($e->getMessage(), 'Duplicate key name') !== false || 
                strpos($e->getMessage(), 'already exists') !== false) {
                echo "<div class='info'>ℹ️ {$indexName} - 已存在，跳过</div>\n";
                $skippedCount++;
            } else {
                echo "<div class='error'>❌ {$indexName} - 失败: {$e->getMessage()}</div>\n";
                $errorCount++;
            }
        }
        
        flush();
    }
    
    // 显示统计
    echo "<h2>📊 执行统计</h2>\n";
    echo "<table>";
    echo "<tr><th>状态</th><th>数量</th></tr>";
    echo "<tr><td>✅ 成功创建</td><td>{$successCount}</td></tr>";
    echo "<tr><td>ℹ️ 已存在（跳过）</td><td>{$skippedCount}</td></tr>";
    echo "<tr><td>❌ 失败</td><td>{$errorCount}</td></tr>";
    echo "<tr><th>总计</th><th>" . ($successCount + $skippedCount + $errorCount) . "</th></tr>";
    echo "</table>";
    
    // 验证索引
    echo "<h2>🔍 索引验证</h2>\n";
    $indexes = $db->query("
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
    
    if ($indexes) {
        echo "<div class='success'>✅ 找到 " . count($indexes) . " 个性能索引</div>\n";
        echo "<table>";
        echo "<tr><th>表名</th><th>索引名</th><th>列</th></tr>";
        foreach ($indexes as $idx) {
            echo "<tr>";
            echo "<td>{$idx['TABLE_NAME']}</td>";
            echo "<td>{$idx['INDEX_NAME']}</td>";
            echo "<td>{$idx['COLUMNS']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<div class='error'>❌ 未找到任何性能索引</div>\n";
    }
    
    // 最终结果
    if ($errorCount === 0) {
        echo "<div class='success'>";
        echo "<h2>🎉 迁移完成！</h2>";
        echo "<p>所有索引已成功创建或已存在。</p>";
        echo "<p><a href='../test/deploy_check.php'>返回检测页面</a></p>";
        echo "</div>";
    } else {
        echo "<div class='error'>";
        echo "<h2>⚠️ 迁移完成但有错误</h2>";
        echo "<p>有 {$errorCount} 个索引创建失败，请检查错误信息。</p>";
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<h2>❌ 迁移失败</h2>";
    echo "<p>错误: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    echo "</div>";
}

echo "</body></html>";
