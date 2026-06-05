<?php
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (($_POST['action'] ?? '') === 'update_default') {
            $before = floatval($_POST['default_coefficient_before'] ?? 3);
            $after = floatval($_POST['default_coefficient_after'] ?? 2);
            $db->query("INSERT INTO system_settings (`key`, `value`) VALUES ('default_coefficient_before', ?) ON DUPLICATE KEY UPDATE `value` = ?", [$before, $before]);
            $db->query("INSERT INTO system_settings (`key`, `value`) VALUES ('default_coefficient_after', ?) ON DUPLICATE KEY UPDATE `value` = ?", [$after, $after]);
            $message = '默认系数已更新';
        } elseif (($_POST['action'] ?? '') === 'update_service') {
            $serviceId = intval($_POST['service_id'] ?? 0);
            $coefBefore = ($_POST['coefficient_before'] ?? '') !== '' ? floatval($_POST['coefficient_before']) : null;
            $coefAfter = ($_POST['coefficient_after'] ?? '') !== '' ? floatval($_POST['coefficient_after']) : null;

            $existing = $db->query("SELECT id FROM service_coefficients WHERE service_id = ?", [$serviceId])->fetch();

            if ($coefBefore === null && $coefAfter === null) {
                if ($existing) {
                    $db->query("DELETE FROM service_coefficients WHERE service_id = ?", [$serviceId]);
                }
            } else {
                if ($existing) {
                    $db->query("UPDATE service_coefficients SET coefficient_before = ?, coefficient_after = ?, updated_at = NOW() WHERE service_id = ?", [$coefBefore, $coefAfter, $serviceId]);
                } else {
                    $db->query("INSERT INTO service_coefficients (service_id, coefficient_before, coefficient_after) VALUES (?, ?, ?)", [$serviceId, $coefBefore, $coefAfter]);
                }
            }
            $message = '服务系数已更新';
        }
    } catch (Throwable $e) {
        $error = '保存失败：' . $e->getMessage();
        error_log('[coefficients.php] ' . $e->getMessage());
    }
    // POST 结束后 PRG 重定向,避免 F5 重复提交
    if ($message || $error) {
        $_SESSION['flash_message'] = $message;
        $_SESSION['flash_error'] = $error;
        header('Location: index.php?page=coefficients');
        exit;
    }
}

$defaultBefore = floatval($db->query("SELECT value FROM system_settings WHERE `key` = 'default_coefficient_before'")->fetchColumn() ?: '2');
$defaultAfter = floatval($db->query("SELECT value FROM system_settings WHERE `key` = 'default_coefficient_after'")->fetchColumn() ?: '4');

// 从 session 读取 flash
if (!empty($_SESSION['flash_message'])) { $message = $_SESSION['flash_message']; unset($_SESSION['flash_message']); }
if (!empty($_SESSION['flash_error'])) { $error = $_SESSION['flash_error']; unset($_SESSION['flash_error']); }

$services = $db->query("
    SELECT s.id, s.name, s.code, sc.coefficient_before, sc.coefficient_after
    FROM services s
    LEFT JOIN service_coefficients sc ON s.id = sc.service_id
    WHERE s.is_published = 1
    ORDER BY s.sort_order
")->fetchAll();
?>

<div class="top-bar">
    <h4>📊 价格系数管理</h4>
</div>

<?php if ($message): ?>
<div class="alert alert-success" style="background: #d1fae5; color: #065f46; padding: 16px; border-radius: 8px; margin-bottom: 24px;">
    ✅ <?= htmlspecialchars($message) ?>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-danger" style="background: #fee2e2; color: #991b1b; padding: 16px; border-radius: 8px; margin-bottom: 24px;">
    ❌ <?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h5>🔧 默认系数设置</h5>
    </div>
    <div style="background: #f8fafc; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
        <p style="color: #64748b; font-size: 14px; margin-bottom: 16px;">
            <strong>说明：</strong>默认系数应用于所有服务。未自定义系数的服务将使用此处设置的默认值。
        </p>
        <form method="POST" style="display: flex; gap: 20px; align-items: flex-end; flex-wrap: wrap;">
            <input type="hidden" name="action" value="update_default">
            <div>
                <label style="display: block; font-size: 13px; color: #64748b; margin-bottom: 6px;">充值前系数（用户未充值时使用，价格较低）</label>
                <input type="number" name="default_coefficient_before" value="<?= $defaultBefore ?>" 
                       step="0.1" min="0.1" max="100" required
                       style="padding: 10px 14px; border: 1px solid #e2e8f0; border-radius: 8px; width: 120px; font-size: 16px;">
            </div>
            <div>
                <label style="display: block; font-size: 13px; color: #64748b; margin-bottom: 6px;">充值后系数（用户充值后使用，价格较高）</label>
                <input type="number" name="default_coefficient_after" value="<?= $defaultAfter ?>" 
                       step="0.1" min="0.1" max="100" required
                       style="padding: 10px 14px; border: 1px solid #e2e8f0; border-radius: 8px; width: 120px; font-size: 16px;">
            </div>
            <button type="submit" class="btn btn-primary">保存默认系数</button>
        </form>
        <div style="margin-top: 16px; padding: 12px; background: white; border-radius: 6px; border-left: 4px solid #6366f1;">
            <strong>计算公式：</strong> 用户价格(积分) = API成本价(分) × 系数
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5>📱 服务系数配置</h5>
    </div>
    <table>
        <thead>
            <tr>
                <th>服务</th>
                <th>充值前系数</th>
                <th>充值后系数</th>
                <th>状态</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($services as $service): ?>
            <tr>
                <td>
                    <div style="font-weight: 600;"><?= htmlspecialchars($service['name']) ?></div>
                    <div style="font-size: 12px; color: #64748b;"><?= htmlspecialchars($service['code']) ?></div>
                </td>
                <td>
                    <?php if ($service['coefficient_before'] !== null): ?>
                    <span class="badge badge-info"><?= floatval($service['coefficient_before']) ?></span>
                    <?php else: ?>
                    <span class="badge badge-secondary">默认 <?= $defaultBefore ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($service['coefficient_after'] !== null): ?>
                    <span class="badge badge-info"><?= floatval($service['coefficient_after']) ?></span>
                    <?php else: ?>
                    <span class="badge badge-secondary">默认 <?= $defaultAfter ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($service['coefficient_before'] !== null || $service['coefficient_after'] !== null): ?>
                    <span class="badge badge-warning">已自定义</span>
                    <?php else: ?>
                    <span class="badge badge-success">使用默认</span>
                    <?php endif; ?>
                </td>
                <td>
                    <button class="btn btn-primary btn-sm" onclick="editService(<?= $service['id'] ?>, '<?= htmlspecialchars($service['name']) ?>', <?= $service['coefficient_before'] !== null ? floatval($service['coefficient_before']) : 'null' ?>, <?= $service['coefficient_after'] !== null ? floatval($service['coefficient_after']) : 'null' ?>)">
                        编辑
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div id="editModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div style="background: white; border-radius: 16px; padding: 24px; width: 400px; max-width: 90%;">
        <h5 style="margin: 0 0 20px 0;">编辑服务系数 - <span id="editServiceName"></span></h5>
        <form method="POST">
            <input type="hidden" name="action" value="update_service">
            <input type="hidden" name="service_id" id="editServiceId">
            <div style="margin-bottom: 16px;">
                <label style="display: block; font-size: 13px; color: #64748b; margin-bottom: 6px;">充值前系数（留空使用默认 <?= $defaultBefore ?>）</label>
                <input type="number" name="coefficient_before" id="editCoefBefore" step="0.1" min="0.1" max="100"
                       style="padding: 10px 14px; border: 1px solid #e2e8f0; border-radius: 8px; width: 100%; font-size: 16px;">
            </div>
            <div style="margin-bottom: 20px;">
                <label style="display: block; font-size: 13px; color: #64748b; margin-bottom: 6px;">充值后系数（留空使用默认 <?= $defaultAfter ?>）</label>
                <input type="number" name="coefficient_after" id="editCoefAfter" step="0.1" min="0.1" max="100"
                       style="padding: 10px 14px; border: 1px solid #e2e8f0; border-radius: 8px; width: 100%; font-size: 16px;">
            </div>
            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                <button type="button" onclick="closeModal()" class="btn" style="background: #e2e8f0; color: #64748b;">取消</button>
                <button type="submit" class="btn btn-primary">保存</button>
            </div>
        </form>
    </div>
</div>

<script>
function editService(id, name, coefBefore, coefAfter) {
    document.getElementById('editServiceId').value = id;
    document.getElementById('editServiceName').textContent = name;
    document.getElementById('editCoefBefore').value = coefBefore !== null ? coefBefore : '';
    document.getElementById('editCoefAfter').value = coefAfter !== null ? coefAfter : '';
    document.getElementById('editModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('editModal').style.display = 'none';
}

document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});
</script>
