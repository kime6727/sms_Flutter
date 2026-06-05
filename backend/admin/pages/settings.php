<?php
/**
 * 系统设置页面
 */

if (($_POST['action'] ?? '') === 'update') {
    foreach ($_POST['settings'] as $key => $value) {
        $existing = $db->query("SELECT `key` FROM system_settings WHERE `key` = ?", [$key])->fetch();
        if ($existing) {
            $db->query("UPDATE system_settings SET `value` = ? WHERE `key` = ?", [$value, $key]);
        } else {
            $db->insert('system_settings', ['key' => $key, 'value' => $value, 'type' => 'string']);
        }
    }
    $success = '设置已更新';
}

if (($_POST['action'] ?? '') === 'change_password') {
    $currentPassword = $_POST['current_password'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];

    $admin = $db->query("SELECT password FROM admins WHERE id = ?", [$_SESSION['admin_id']])->fetch();

    if (!password_verify($currentPassword, $admin['password'])) {
        $error = '当前密码错误';
    } elseif ($newPassword !== $confirmPassword) {
        $error = '两次密码输入不一致';
    } elseif (strlen($newPassword) < 6) {
        $error = '密码长度至少6位';
    } else {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $db->query("UPDATE admins SET password = ? WHERE id = ?", [$hashedPassword, $_SESSION['admin_id']]);
        $success = '密码已修改';
    }
}

$settings = $db->query("SELECT * FROM system_settings")->fetchAll();
$settingsMap = [];
foreach ($settings as $s) {
    $settingsMap[$s['key']] = $s['value'];
}
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
    <h4 style="margin:0;">🔧 系统设置</h4>
</div>

<?php if(isset($success)): ?>
<div style="background:#d1fae5;color:#065f46;padding:12px 16px;border-radius:8px;margin-bottom:16px;"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if(isset($error)): ?>
<div style="background:#fee2e2;color:#991b1b;padding:12px 16px;border-radius:8px;margin-bottom:16px;"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="card" style="margin-bottom:20px;">
    <div style="padding:20px;border-bottom:1px solid #e2e8f0;">
        <h5 style="margin:0;">⚙️ 基础设置</h5>
    </div>
    <div style="padding:20px;">
        <form method="POST" style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <input type="hidden" name="action" value="update">
            <div>
                <label style="display:block;margin-bottom:6px;color:#64748b;font-size:14px;">网站名称</label>
                <input type="text" name="settings[site_name]" value="<?= htmlspecialchars($settingsMap['site_name'] ?? 'SMS 接码平台') ?>" style="width:100%;padding:10px 14px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;">
            </div>
            <div>
                <label style="display:block;margin-bottom:6px;color:#64748b;font-size:14px;">联系邮箱</label>
                <input type="email" name="settings[contact_email]" value="<?= htmlspecialchars($settingsMap['contact_email'] ?? '') ?>" style="width:100%;padding:10px 14px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;">
            </div>
            <div>
                <label style="display:block;margin-bottom:6px;color:#64748b;font-size:14px;">待使用订单过期时间(小时)</label>
                <input type="number" name="settings[pending_order_expire_hours]" value="<?= $settingsMap['pending_order_expire_hours'] ?? '72' ?>" min="1" max="720" style="width:100%;padding:10px 14px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;">
                <small style="color:#94a3b8;">待使用(pending)订单超过此时长将自动变为已过期(expired)。批量订单共用此时间,购买成功后开始计时。<b style="color:#dc2626;">积分不予退还</b>。</small>
            </div>
            <div>
                <label style="display:block;margin-bottom:6px;color:#64748b;font-size:14px;">订单超时时间(分钟)</label>
                <input type="number" name="settings[order_timeout]" value="<?= $settingsMap['order_timeout'] ?? 20 ?>" min="1" max="60" style="width:100%;padding:10px 14px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;">
                <small style="color:#94a3b8;">激活后等待短信的超时时长。超时后订单自动过期,<b style="color:#dc2626;">号码资源已被占用,积分不予退还</b>。</small>
            </div>
            <div>
                <label style="display:block;margin-bottom:6px;color:#64748b;font-size:14px;">HeroSMS API Key</label>
                <input type="text" name="settings[hero_sms_api_key]" value="<?= htmlspecialchars($settingsMap['hero_sms_api_key'] ?? '') ?>" style="width:100%;padding:10px 14px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;">
                <small style="color:#94a3b8;">Hero-SMS 服务密钥，用于同步服务、国家和价格数据</small>
            </div>
            <div>
                <label style="display:block;margin-bottom:6px;color:#64748b;font-size:14px;">APP API Key</label>
                <input type="text" name="settings[api_key]" value="<?= htmlspecialchars($settingsMap['api_key'] ?? '') ?>" style="width:100%;padding:10px 14px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;">
                <small style="color:#94a3b8;">用于APP客户端访问API的密钥</small>
            </div>
            <div style="grid-column:1/-1;"><hr style="border:0;border-top:1px solid #e2e8f0;margin:16px 0;"></div>
            <div>
                <label style="display:block;margin-bottom:6px;color:#64748b;font-size:14px;">注册送积分-最小值</label>
                <input type="number" name="settings[register_bonus_min]" value="<?= $settingsMap['register_bonus_min'] ?? '5' ?>" style="width:100%;padding:10px 14px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;">
                <small style="color:#94a3b8;">新用户注册时随机赠送积分的最小值</small>
            </div>
            <div>
                <label style="display:block;margin-bottom:6px;color:#64748b;font-size:14px;">注册送积分-最大值</label>
                <input type="number" name="settings[register_bonus_max]" value="<?= $settingsMap['register_bonus_max'] ?? '20' ?>" style="width:100%;padding:10px 14px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;">
                <small style="color:#94a3b8;">新用户注册时随机赠送积分的最大值</small>
            </div>
            <div>
                <label style="display:block;margin-bottom:6px;color:#64748b;font-size:14px;">首充双倍积分</label>
                <select name="settings[first_topup_double]" style="width:100%;padding:10px 14px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;">
                    <option value="1" <?= ($settingsMap['first_topup_double'] ?? '1') == '1' ? 'selected' : '' ?>>开启</option>
                    <option value="0" <?= ($settingsMap['first_topup_double'] ?? '1') == '0' ? 'selected' : '' ?>>关闭</option>
                </select>
                <small style="color:#94a3b8;">首次充值是否赠送双倍积分</small>
            </div>
            <div>
                <label style="display:block;margin-bottom:6px;color:#64748b;font-size:14px;">首充优惠倒计时(小时)</label>
                <input type="number" name="settings[first_topup_countdown_hours]" value="<?= $settingsMap['first_topup_countdown_hours'] ?? '24' ?>" style="width:100%;padding:10px 14px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;">
                <small style="color:#94a3b8;">新用户注册后首充优惠的有效时长</small>
            </div>
            <div style="grid-column:1/-1;">
                <button type="submit" class="btn btn-primary" style="margin-top:8px;">💾 保存设置</button>
            </div>
        </form>
    </div>
</div>

<div class="card" style="margin-bottom:20px;">
    <div style="padding:20px;border-bottom:1px solid #e2e8f0;">
        <h5 style="margin:0;">🔒 账户安全</h5>
    </div>
    <div style="padding:20px;">
        <form method="POST" style="max-width:400px;">
            <input type="hidden" name="action" value="change_password">
            <div style="margin-bottom:16px;">
                <label style="display:block;margin-bottom:6px;color:#64748b;font-size:14px;">当前密码</label>
                <input type="password" name="current_password" required style="width:100%;padding:10px 14px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;">
            </div>
            <div style="margin-bottom:16px;">
                <label style="display:block;margin-bottom:6px;color:#64748b;font-size:14px;">新密码</label>
                <input type="password" name="new_password" required minlength="6" style="width:100%;padding:10px 14px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;">
            </div>
            <div style="margin-bottom:16px;">
                <label style="display:block;margin-bottom:6px;color:#64748b;font-size:14px;">确认新密码</label>
                <input type="password" name="confirm_password" required style="width:100%;padding:10px 14px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;">
            </div>
            <button type="submit" style="background:#f59e0b;color:white;border:none;padding:10px 20px;border-radius:8px;cursor:pointer;font-size:14px;">🔑 修改密码</button>
        </form>
    </div>
</div>

<div class="card">
    <div style="padding:20px;border-bottom:1px solid #e2e8f0;">
        <h5 style="margin:0;">ℹ️ 系统信息</h5>
    </div>
    <div style="padding:20px;display:grid;grid-template-columns:repeat(3,1fr);gap:20px;">
        <div>
            <p style="margin:0 0 4px 0;color:#64748b;font-size:13px;">PHP 版本</p>
            <p style="margin:0;font-weight:600;"><?= PHP_VERSION ?></p>
        </div>
        <div>
            <p style="margin:0 0 4px 0;color:#64748b;font-size:13px;">MySQL 版本</p>
            <p style="margin:0;font-weight:600;"><?= $db->query("SELECT VERSION()")->fetchColumn() ?></p>
        </div>
        <div>
            <p style="margin:0 0 4px 0;color:#64748b;font-size:13px;">当前时间</p>
            <p style="margin:0;font-weight:600;"><?= date('Y-m-d H:i:s') ?></p>
        </div>
    </div>
</div>