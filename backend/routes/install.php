<?php
/**
 * 安装路由 - WordPress 风格自安装端点
 *
 * 端点：
 *   GET  /install           - 安装状态 + 触发安装页面
 *   GET  /install/status    - JSON 状态查询
 *   POST /install/run       - 触发运行安装（返回 JSON 结果）
 */

// 注意：index.php 在 require 这个文件之前会先 $db = new Database(...)
// 所以这里可以直接用 $db
global $db;

$installer = new Installer();

// ============================
// 1. GET /install/status
// ============================
if ($path === '/install/status' && $method === 'GET') {
    $dbReady = $installer->checkDatabase($db);
    echo json_encode([
        'success' => true,
        'installed' => $dbReady,
        'installing' => $installer->isInstalling(),
        'marker_file' => file_exists($installer->getMarkerFile()),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ============================
// 2. POST /install/run
// ============================
if ($path === '/install/run' && $method === 'POST') {
    header('Content-Type: application/json; charset=utf-8');

    if ($installer->checkDatabase($db)) {
        echo json_encode([
            'success' => true,
            'status' => 'already_installed',
            'message' => '数据库已初始化，无需重复安装',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($installer->isInstalling()) {
        http_response_code(503);
        echo json_encode([
            'success' => false,
            'status' => 'installing',
            'message' => '其他进程正在安装中，请稍候',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        $result = $installer->install($db);
        $installer->cleanup();

        http_response_code($result['verified'] ? 200 : 500);
        echo json_encode([
            'success' => $result['verified'],
            'status' => $result['status'],
            'data' => $result,
            'message' => $result['verified']
                ? "数据库初始化成功！执行 {$result['executed']} 条语句，耗时 {$result['duration_ms']}ms"
                : '安装完成但验证失败，请查看 errors 字段',
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'status' => 'failed',
            'error' => $e->getMessage(),
        ], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// ============================
// 3. GET /install - 友好 HTML 安装页
// ============================
if ($path === '/install' && $method === 'GET') {
    $installed = $installer->checkDatabase($db);
    $installing = $installer->isInstalling();

    // 标记文件存在但表不存在 → 自动恢复
    if (!$installed && !$installing) {
        try {
            $result = $installer->install($db);
            $installer->cleanup();
            $installed = $result['verified'];
            if (!$installed) {
                $errCount = count($result['errors'] ?? []);
                $firstErr = $errCount > 0 ? $result['errors'][0]['error'] : '未知错误';
                $installError = "{$firstErr}（共 {$errCount} 条 SQL 失败，执行了 {$result['executed']} 条，跳过 {$result['skipped']} 条）";
            }
        } catch (Exception $e) {
            $installError = $e->getMessage();
        }
    }

    header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SMS 接码平台 - 安装向导</title>
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
  .card { background: #fff; border-radius: 12px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); max-width: 600px; width: 100%; overflow: hidden; }
  .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; padding: 40px 30px; text-align: center; }
  .header h1 { font-size: 28px; margin-bottom: 8px; }
  .header p { opacity: 0.9; font-size: 14px; }
  .body { padding: 30px; }
  .status { padding: 20px; border-radius: 8px; margin-bottom: 20px; display: flex; align-items: center; gap: 12px; }
  .status.ok { background: #d4edda; color: #155724; border-left: 4px solid #28a745; }
  .status.warn { background: #fff3cd; color: #856404; border-left: 4px solid #ffc107; }
  .status.err { background: #f8d7da; color: #721c24; border-left: 4px solid #dc3545; }
  .icon { font-size: 32px; }
  .info-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #eee; font-size: 14px; }
  .info-row:last-child { border: none; }
  .info-label { color: #666; }
  .info-value { font-family: monospace; font-weight: 600; }
  .btn { display: inline-block; padding: 12px 30px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; text-decoration: none; border-radius: 6px; font-weight: 600; margin-top: 20px; border: none; cursor: pointer; font-size: 15px; }
  .btn:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(102,126,234,0.4); }
  pre { background: #f4f4f4; padding: 12px; border-radius: 6px; overflow-x: auto; font-size: 12px; }
</style>
</head>
<body>
<div class="card">
  <div class="header">
    <h1>📱 SMS 接码平台</h1>
    <p>安装向导</p>
  </div>
  <div class="body">
    <?php if ($installed): ?>
      <div class="status ok">
        <div class="icon">✅</div>
        <div>
          <strong>数据库已就绪</strong><br>
          <small>所有表已创建，初始数据已导入</small>
        </div>
      </div>

      <div class="info-row"><span class="info-label">数据库 Host</span><span class="info-value"><?= htmlspecialchars(DB_HOST) ?></span></div>
      <div class="info-row"><span class="info-label">数据库 Name</span><span class="info-value"><?= htmlspecialchars(DB_NAME) ?></span></div>
      <div class="info-row"><span class="info-label">PHP 版本</span><span class="info-value"><?= PHP_VERSION ?></span></div>
      <div class="info-row"><span class="info-label">安装时间</span><span class="info-value"><?= file_exists($installer->getMarkerFile()) ? htmlspecialchars(json_decode(file_get_contents($installer->getMarkerFile()), true)['installed_at'] ?? 'unknown') : '-' ?></span></div>

      <a href="/health" class="btn">🎉 进入系统</a>
      <a href="/banners" class="btn" style="background:#6c757d;margin-left:10px;">查看 Banners API</a>

    <?php elseif ($installing): ?>
      <div class="status warn">
        <div class="icon">⏳</div>
        <div>
          <strong>正在安装中</strong><br>
          <small>其他进程正在初始化数据库，请稍候...</small>
        </div>
      </div>
      <script>setTimeout(() => location.reload(), 3000);</script>

    <?php else: ?>
      <div class="status err">
        <div class="icon">❌</div>
        <div>
          <strong>安装失败</strong><br>
          <small><?= htmlspecialchars($installError ?? '未知错误') ?></small>
        </div>
      </div>

      <h3 style="margin: 20px 0 12px; font-size: 16px;">数据库连接信息</h3>
      <div class="info-row"><span class="info-label">Host</span><span class="info-value"><?= htmlspecialchars(DB_HOST) ?></span></div>
      <div class="info-row"><span class="info-label">Port</span><span class="info-value"><?= htmlspecialchars(DB_PORT) ?></span></div>
      <div class="info-row"><span class="info-label">Database</span><span class="info-value"><?= htmlspecialchars(DB_NAME) ?></span></div>
      <div class="info-row"><span class="info-label">User</span><span class="info-value"><?= htmlspecialchars(DB_USER) ?></span></div>

      <p style="margin-top: 20px; color: #666; font-size: 14px;">
        💡 请检查 dokploy Environment 中的数据库配置是否正确。<br>
        修复后<a href="javascript:location.reload()">刷新此页</a>重试。
      </p>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
    <?php
    exit;
}
