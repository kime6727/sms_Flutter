<?php
/**
 * 服务-国家关联配置页面
 * 管理每个服务展示哪些国家
 */

$serviceId = intval($_GET['id'] ?? 0);
$service = $db->query("SELECT * FROM services WHERE id = ?", [$serviceId])->fetch();

if (!$service) {
    header('Location: ?page=services');
    exit;
}

// 处理全部上架
if (($_GET['action'] ?? '') === 'publish_all') {
    $db->query(
        "UPDATE service_countries SET is_published = 1, is_auto = 0 WHERE service_id = ?",
        [$serviceId]
    );
    $success = '所有国家已设置为展示状态';
}

// 处理自动选择最便宜的12国
if (($_GET['action'] ?? '') === 'auto_select') {
    // 先取消该服务所有国家的自动标记
    $db->query("UPDATE service_countries SET is_auto = 0, is_published = 0 WHERE service_id = ?", [$serviceId]);

    // 选择最便宜的12个，标记为自动+已发布
    $db->query(
        "UPDATE service_countries SET is_auto = 1, is_published = 1
         WHERE id IN (
             SELECT id FROM (
                 SELECT id FROM service_countries
                 WHERE service_id = ? AND active = 1
                 ORDER BY price ASC
                 LIMIT 12
             ) AS tmp
         )",
        [$serviceId]
    );

    $success = '已自动选择该服务最便宜的12个国家';
}

// 处理单个国家切换发布状态
if (($_GET['action'] ?? '') === 'toggle_country' && ($_GET['country_id'] ?? null)) {
    $countryId = intval($_GET['country_id']);
    $current = $db->query(
        "SELECT is_published FROM service_countries WHERE service_id = ? AND country_id = ?",
        [$serviceId, $countryId]
    )->fetch();
    if ($current) {
        $newStatus = $current['is_published'] ? 0 : 1;
        $db->query(
            "UPDATE service_countries SET is_published = ?, is_auto = 0 WHERE service_id = ? AND country_id = ?",
            [$newStatus, $serviceId, $countryId]
        );
    }
    header('Location: ?page=service_countries&id=' . $serviceId);
    exit;
}

// 处理手动添加国家
if (($_POST['action'] ?? '') === 'add_country') {
    $countryId = intval($_POST['country_id']);
    $price = floatval($_POST['price']);

    $existing = $db->query(
        "SELECT id FROM service_countries WHERE service_id = ? AND country_id = ?",
        [$serviceId, $countryId]
    )->fetch();

    if ($existing) {
        $db->query(
            "UPDATE service_countries SET price = ?, is_published = 1, is_auto = 0 WHERE id = ?",
            [$price, $existing['id']]
        );
    } else {
        $db->insert('service_countries', [
            'service_id' => $serviceId,
            'country_id' => $countryId,
            'price' => $price,
            'active' => 1,
            'is_published' => 1,
            'is_auto' => 0
        ]);
    }
    $success = '国家已添加';
}

// 获取该服务所有国家及其价格
$countries = $db->query(
    "SELECT sc.*, c.name as country_name, c.name_cn, c.flag, c.phone_code,
            c.hero_country_id
     FROM service_countries sc
     JOIN countries c ON c.id = sc.country_id
     WHERE sc.service_id = ?
     ORDER BY sc.is_published DESC, sc.price ASC",
    [$serviceId]
)->fetchAll();

// 获取可添加的国家列表（尚未关联的）
$availableCountries = $db->query(
    "SELECT c.* FROM countries c
     WHERE c.id NOT IN (
         SELECT country_id FROM service_countries WHERE service_id = ?
     )
     ORDER BY c.name_cn"
, [$serviceId])->fetchAll();

$publishedCount = count(array_filter($countries, fn($c) => $c['is_published']));
?>

<div style="margin-bottom:24px;">
    <a href="?page=services" style="color:#6366f1;text-decoration:none;font-size:14px;">← 返回服务列表</a>
</div>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
    <h4 style="margin:0;">
        🌍 <?= htmlspecialchars($service['name']) ?> - 国家配置
    </h4>
    <div style="display:flex;gap:12px;">
        <a href="?page=service_countries&id=<?= $serviceId ?>&action=publish_all"
           onclick="return confirm('确定要将该服务所有国家都设为展示状态吗？')"
           style="background:#10b981;color:white;padding:10px 20px;border-radius:8px;text-decoration:none;font-size:14px;font-weight:500;">
            ✅ 全部上架
        </a>
        <a href="?page=service_countries&id=<?= $serviceId ?>&action=auto_select"
           onclick="return confirm('确定要自动选择最便宜的12个国家吗？当前选择会被覆盖。')"
           style="background:#6366f1;color:white;padding:10px 20px;border-radius:8px;text-decoration:none;font-size:14px;font-weight:500;">
            ⚡ 自动选择12国
        </a>
    </div>
</div>

<?php if(isset($success)): ?>
<div style="background:#d1fae5;color:#065f46;padding:14px 20px;border-radius:8px;margin-bottom:20px;font-size:14px;">✓ <?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px;">
    <div class="card" style="padding:20px;">
        <div style="font-size:14px;color:#64748b;margin-bottom:4px;">已展示国家</div>
        <div style="font-size:32px;font-weight:700;color:#10b981;"><?= $publishedCount ?></div>
        <div style="font-size:13px;color:#94a3b8;">个</div>
    </div>
    <div class="card" style="padding:20px;">
        <div style="font-size:14px;color:#64748b;margin-bottom:4px;">价格区间</div>
        <div style="font-size:32px;font-weight:700;color:#6366f1;">
            <?php
            $prices = array_column($countries, 'price');
            if ($prices) {
                echo '$' . min($prices) . ' - $' . max($prices);
            } else {
                echo '-';
            }
            ?>
        </div>
        <div style="font-size:13px;color:#94a3b8;">每条短信</div>
    </div>
</div>

<div class="card" style="margin-bottom:20px;">
    <div style="padding:16px 20px;border-bottom:1px solid #e2e8f0;">
        <div style="display:flex;justify-content:space-between;align-items:center;">
            <span style="font-weight:600;color:#0f172a;">国家列表</span>
            <span style="font-size:13px;color:#64748b;">
                已选 <strong style="color:#10b981;"><?= $publishedCount ?></strong> /
                共 <strong><?= count($countries) ?></strong> 个
            </span>
        </div>
    </div>
    <table>
        <thead>
            <tr>
                <th>国家</th>
                <th>HeroID</th>
                <th>旗帜</th>
                <th>区号</th>
                <th style="text-align:right;">价格</th>
                <th style="text-align:center;">来源</th>
                <th style="text-align:center;">状态</th>
                <th style="text-align:center;">操作</th>
            </tr>
        </thead>
        <tbody>
            <?php if(empty($countries)): ?>
            <tr>
                <td colspan="8" style="text-align:center;color:#64748b;padding:60px;">
                    <div style="font-size:48px;margin-bottom:16px;">🌍</div>
                    <div style="margin-bottom:12px;">暂无国家数据</div>
                    <div style="font-size:13px;">请先在服务配置中执行「一键同步HeroSMS」</div>
                </td>
            </tr>
            <?php else: ?>
            <?php foreach($countries as $c): ?>
            <tr style="<?= !$c['is_published'] ? 'opacity:0.5;' : '' ?>">
                <td>
                    <div style="font-weight:500;"><?= htmlspecialchars($c['name_cn'] ?: $c['country_name']) ?></div>
                    <div style="font-size:11px;color:#94a3b8;"><?= htmlspecialchars($c['name']) ?></div>
                </td>
                <td><small style="color:#64748b;"><?= htmlspecialchars($c['hero_country_id']) ?></small></td>
                <td style="font-size:20px;"><?= $c['flag'] ?: '🏳️' ?></td>
                <td><?= htmlspecialchars($c['phone_code']) ?></td>
                <td style="text-align:right;font-weight:600;color:#10b981;">$<?= number_format($c['price'], 2) ?></td>
                <td style="text-align:center;">
                    <?php if($c['is_auto']): ?>
                    <span style="background:#dbeafe;color:#1e40af;padding:2px 8px;border-radius:10px;font-size:11px;">自动</span>
                    <?php else: ?>
                    <span style="background:#f1f5f9;color:#64748b;padding:2px 8px;border-radius:10px;font-size:11px;">手动</span>
                    <?php endif; ?>
                </td>
                <td style="text-align:center;">
                    <?php if($c['is_published']): ?>
                    <span style="background:#d1fae5;color:#065f46;padding:4px 10px;border-radius:12px;font-size:12px;font-weight:600;">展示中</span>
                    <?php else: ?>
                    <span style="background:#f1f5f9;color:#64748b;padding:4px 10px;border-radius:12px;font-size:12px;">已隐藏</span>
                    <?php endif; ?>
                </td>
                <td style="text-align:center;">
                    <a href="?page=service_countries&id=<?= $serviceId ?>&action=toggle_country&country_id=<?= $c['country_id'] ?>"
                       style="padding:6px 14px;border-radius:6px;text-decoration:none;font-size:12px;font-weight:500;<?= $c['is_published'] ? 'background:#fef3c7;color:#92400e;' : 'background:#10b981;color:white;' ?>">
                        <?= $c['is_published'] ? '⏸ 隐藏' : '▶️ 展示' ?>
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if(!empty($availableCountries)): ?>
<div class="card">
    <div style="padding:16px 20px;border-bottom:1px solid #e2e8f0;">
        <span style="font-weight:600;color:#0f172a;">➕ 添加国家</span>
    </div>
    <div style="padding:20px;">
        <form method="POST" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
            <input type="hidden" name="action" value="add_country">
            <div style="flex:1;min-width:200px;">
                <label style="display:block;margin-bottom:6px;color:#64748b;font-size:13px;">选择国家</label>
                <select name="country_id" style="width:100%;padding:10px 14px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;">
                    <option value="">-- 选择国家 --</option>
                    <?php foreach($availableCountries as $ac): ?>
                    <option value="<?= $ac['id'] ?>"><?= $ac['flag'] ?> <?= htmlspecialchars($ac['name_cn'] ?: $ac['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="width:150px;">
                <label style="display:block;margin-bottom:6px;color:#64748b;font-size:13px;">价格 ($)</label>
                <input type="number" name="price" step="0.01" min="0" value="0.1" style="width:100%;padding:10px 14px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;">
            </div>
            <button type="submit" style="background:#10b981;color:white;border:none;padding:10px 20px;border-radius:8px;cursor:pointer;font-size:14px;font-weight:500;white-space:nowrap;">➕ 添加</button>
        </form>
    </div>
</div>
<?php endif; ?>

<div style="margin-top:20px;padding:16px;background:#fffbeb;border:1px solid #fde68a;border-radius:12px;">
    <div style="font-weight:600;color:#92400e;margin-bottom:8px;">💡 使用说明</div>
    <ul style="margin:0;padding-left:20px;color:#78350f;font-size:13px;line-height:1.8;">
        <li>点击「全部上架」可以将该服务所有关联国家都设为展示状态</li>
        <li>「自动选择12国」会自动挑选该服务最便宜的12个国家并标记为展示</li>
        <li>手动点击「展示/隐藏」会覆盖自动选择，手动操作会标记为手动来源</li>
        <li>用户端只会看到「展示中」状态的国家号码</li>
        <li>可以根据业务需求手动调整每个服务展示的国家</li>
    </ul>
</div>