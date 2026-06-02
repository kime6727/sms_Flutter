<?php
/**
 * 国家配置页面 - 纯国家基础库
 * 注意：国家-服务关联在「服务配置」->「国家」中管理
 */

if (($_GET['action'] ?? '') === 'toggle' && ($_GET['id'] ?? null)) {
    $id = intval($_GET['id']);
    $current = $db->query("SELECT active FROM countries WHERE id = ?", [$id])->fetch();
    if ($current) {
        $newStatus = $current['active'] ? 0 : 1;
        $db->query("UPDATE countries SET active = ? WHERE id = ?", [$newStatus, $id]);
    }
    header('Location: ?page=countries');
    exit;
}

if (($_POST['action'] ?? '') === 'update_name' && ($_POST['id'] ?? null)) {
    $db->query(
        "UPDATE countries SET name_cn = ?, name_en = ?, flag = ?, phone_code = ? WHERE id = ?",
        [$_POST['name_cn'], $_POST['name_en'], $_POST['flag'], $_POST['phone_code'], intval($_POST['id'])]
    );
    $success = '国家信息已更新';
}

$countries = $db->query("SELECT * FROM countries ORDER BY id")->fetchAll();

$totalCountries = count($countries);
$activeCountries = count(array_filter($countries, fn($c) => $c['active']));
$withServices = $db->query(
    "SELECT COUNT(DISTINCT country_id) as cnt FROM service_countries WHERE active = 1"
)->fetch()['cnt'] ?? 0;
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
    <h4 style="margin:0;">🌍 国家基础库</h4>
</div>

<?php if(isset($success)): ?>
<div style="background:#d1fae5;color:#065f46;padding:14px 20px;border-radius:8px;margin-bottom:20px;font-size:14px;">✓ <?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:24px;">
    <div class="card" style="padding:20px;text-align:center;">
        <div style="font-size:14px;color:#64748b;margin-bottom:4px;">国家总数</div>
        <div style="font-size:28px;font-weight:700;color:#0f172a;"><?= $totalCountries ?></div>
    </div>
    <div class="card" style="padding:20px;text-align:center;">
        <div style="font-size:14px;color:#64748b;margin-bottom:4px;">启用状态</div>
        <div style="font-size:28px;font-weight:700;color:#10b981;"><?= $activeCountries ?></div>
    </div>
    <div class="card" style="padding:20px;text-align:center;">
        <div style="font-size:14px;color:#64748b;margin-bottom:4px;">已配置服务</div>
        <div style="font-size:28px;font-weight:700;color:#6366f1;"><?= $withServices ?></div>
    </div>
</div>

<div class="card">
    <div style="padding:16px 20px;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;">
        <span style="font-weight:600;color:#0f172a;">国家列表</span>
        <span style="font-size:13px;color:#64748b;">共 <?= $totalCountries ?> 个国家</span>
    </div>
    <table>
        <thead>
            <tr>
                <th style="width:60px;">ID</th>
                <th style="width:80px;">HeroID</th>
                <th style="width:60px;">旗帜</th>
                <th>国家名称</th>
                <th>中文名</th>
                <th>英文名</th>
                <th style="width:60px;">Code</th>
                <th style="width:80px;">区号</th>
                <th style="width:70px;text-align:center;">状态</th>
                <th style="width:120px;">操作</th>
            </tr>
        </thead>
        <tbody>
            <?php if(empty($countries)): ?>
            <tr>
                <td colspan="10" style="text-align:center;color:#64748b;padding:60px;">
                    <div style="font-size:48px;margin-bottom:16px;">🌍</div>
                    <div style="margin-bottom:12px;">暂无国家数据</div>
                    <div style="font-size:13px;">请在「服务配置」中执行「一键同步HeroSMS」</div>
                </td>
            </tr>
            <?php else: ?>
            <?php foreach($countries as $country): ?>
            <tr style="<?= !$country['active'] ? 'opacity:0.5;' : '' ?>">
                <td style="color:#64748b;"><?= $country['id'] ?></td>
                <td><small style="color:#64748b;background:#f1f5f9;padding:2px 6px;border-radius:4px;font-size:11px;"><?= htmlspecialchars($country['hero_country_id']) ?></small></td>
                <td>
                    <?php 
                    $flagUrl = '';
                    if (!empty($country['flag'])) {
                        $cdnBase = 'https://cdn.hero-sms.com/assets/img/country/';
                        if (strpos($country['flag'], $cdnBase) === 0) {
                            $filename = substr($country['flag'], strlen($cdnBase));
                            $flagUrl = '../../pic/country/' . htmlspecialchars($filename);
                        } elseif (strpos($country['flag'], '/pic/country/') !== false) {
                            $flagUrl = htmlspecialchars($country['flag']);
                        } else {
                            // 如果是emoji或其他格式，保持原样
                            $flagUrl = '';
                        }
                    }
                    ?>
                    <?php if(!empty($flagUrl)): ?>
                    <img src="<?= $flagUrl ?>" style="width:28px;height:20px;border-radius:4px;object-fit:contain;" onerror="this.parentElement.textContent='🏳️'">
                    <?php else: ?>
                    🏳️
                    <?php endif; ?>
                </td>
                <td>
                    <div style="font-weight:500;"><?= htmlspecialchars($country['name']) ?></div>
                </td>
                <td>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="action" value="update_name">
                        <input type="hidden" name="id" value="<?= $country['id'] ?>">
                        <input type="text" name="name_cn" value="<?= htmlspecialchars($country['name_cn'] ?? '') ?>" style="width:90px;padding:4px 8px;border:1px solid #e2e8f0;border-radius:6px;font-size:13px;" placeholder="中文名">
                </td>
                <td>
                        <input type="text" name="name_en" value="<?= htmlspecialchars($country['name_en'] ?? '') ?>" style="width:90px;padding:4px 8px;border:1px solid #e2e8f0;border-radius:6px;font-size:13px;" placeholder="英文名">
                </td>
                <td style="color:#64748b;"><?= htmlspecialchars($country['code'] ?? '-') ?></td>
                <td><?= htmlspecialchars($country['phone_code'] ?? '-') ?></td>
                <td style="text-align:center;">
                    <span style="padding:4px 10px;border-radius:12px;font-size:12px;font-weight:600;<?= $country['active'] ? 'background:#d1fae5;color:#065f46;' : 'background:#f1f5f9;color:#64748b;' ?>">
                        <?= $country['active']?'启用':'禁用' ?>
                    </span>
                </td>
                <td style="white-space:nowrap;">
                    <button type="submit" style="background:#6366f1;color:white;border:none;padding:5px 10px;border-radius:6px;cursor:pointer;font-size:12px;margin-right:4px;">✓</button>
                    </form>
                    <a href="?page=countries&action=toggle&id=<?= $country['id'] ?>"
                       style="padding:5px 10px;border-radius:6px;text-decoration:none;font-size:12px;font-weight:500;<?= $country['active'] ? 'background:#fef3c7;color:#92400e;' : 'background:#10b981;color:white;' ?>">
                        <?= $country['active']?'禁用':'启用' ?>
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div style="margin-top:20px;padding:16px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:12px;">
    <div style="font-weight:600;color:#1e40af;margin-bottom:8px;">💡 说明</div>
    <ul style="margin:0;padding-left:20px;color:#1e40af;font-size:13px;line-height:1.8;">
        <li>这里是国家基础库，存储从HeroSMS同步的国家数据</li>
        <li>国家与服务的关联配置在「服务配置」页面，点击对应服务的「🌍 国家」按钮</li>
        <li>每个服务可以配置不同的国家展示组合</li>
    </ul>
</div>