<?php
/**
 * 国家配置页面 - 完整重写
 * 包含：从 HeroSMS 同步国家、国家基础信息编辑、上下架
 */

require_once __DIR__ . '/../../lib/HeroSMS.php';
require_once __DIR__ . '/../../lib/KeyManager.php';

// ==================== 自愈：确保 countries 表关键字段存在 ====================
try {
    $countryCols = array_column($db->query("SHOW COLUMNS FROM countries")->fetchAll(), 'Field');
    $countryIndexes = array_column($db->query("SHOW INDEX FROM countries")->fetchAll(), 'Key_name');
    if (!in_array('uk_hero_country_id', $countryIndexes)) {
        $db->query("ALTER TABLE countries ADD UNIQUE KEY `uk_hero_country_id` (`hero_country_id`)");
    }
} catch (Exception $e) {
    $schemaError = 'countries 表自愈失败: ' . $e->getMessage();
}

// ==================== 同步国家 ====================
if (($_GET['action'] ?? '') === 'sync_countries') {
    $apiKey = KeyManager::getHeroSmsApiKey();
    if (empty($apiKey)) {
        $error = "HeroSMS API key 未配置，请先在「系统设置」中配置";
    } else {
        $heroSMS = new HeroSMS($apiKey, HEROSMS_BASE_URL);
        $result = $heroSMS->getCountries();
        $synced = 0;
        $updated = 0;
        $skipped = 0;
        $rawResponse = null;
        $rawFormat = 'unknown';

        if (!empty($result['success']) && !empty($result['countries'])) {
            $rawResponse = $result['countries'];
            $countries = $result['countries'];

            // 检测格式：是「map（id => info）」还是「list of items」
            $firstKey = array_key_first($countries);
            $firstVal = is_array($countries) ? reset($countries) : null;
            if (is_array($firstVal) && isset($firstVal['id'])) {
                // 列表形式：[{id,eng,chn,rus}, ...]
                $rawFormat = 'list';
            } elseif (is_array($firstVal) && (isset($firstVal['eng']) || isset($firstVal['chn']) || isset($firstVal['rus']))) {
                // map 形式：{ "1": {eng,chn,rus}, "2": {...} }
                $rawFormat = 'map';
            } elseif (is_string($firstKey) && is_array($firstVal)) {
                // map 形式：{ "ru": {eng,chn,rus}, ... } 用 key 作为 hero_id
                $rawFormat = 'map_keyed';
            } else {
                $rawFormat = 'unknown';
            }

            foreach ($countries as $id => $info) {
                if (!is_array($info)) { $skipped++; continue; }

                // 提取字段，兼容多种格式
                $heroCid = '';
                if ($rawFormat === 'list') {
                    $heroCid = (string)($info['id'] ?? '');
                } elseif ($rawFormat === 'map_keyed') {
                    $heroCid = (string)$id;
                } else {
                    $heroCid = (string)($info['id'] ?? $id);
                }
                if ($heroCid === '') { $skipped++; continue; }

                $eng = $info['eng'] ?? $info['en'] ?? $info['name_en'] ?? '';
                $chn = $info['chn'] ?? $info['cn'] ?? $info['name_cn'] ?? '';
                $rus = $info['rus'] ?? $info['ru'] ?? '';

                // 取一个合理的主名称
                $displayName = $chn ?: $eng ?: $rus ?: ('Country#' . $heroCid);

                $code = '';
                $phoneCode = '';
                $flag = '';
                if (!empty($info['iso'])) $code = strtolower($info['iso']);
                elseif (!empty($info['code'])) $code = strtolower($info['code']);
                if (!empty($info['phone'])) $phoneCode = $info['phone'];
                elseif (!empty($info['phone_code'])) $phoneCode = $info['phone_code'];
                if (!empty($info['img'])) $flag = $info['img'];
                elseif (!empty($info['flag'])) $flag = $info['flag'];

                try {
                    $existing = $db->query("SELECT id FROM countries WHERE hero_country_id = ?", [$heroCid])->fetch();
                    if ($existing) {
                        $db->query(
                            "UPDATE countries SET name = ?, name_en = ?, name_cn = ?, code = ?, phone_code = ?, flag = ?, updated_at = NOW() WHERE id = ?",
                            [$displayName, $eng, $chn, $code, $phoneCode, $flag, $existing['id']]
                        );
                        $updated++;
                    } else {
                        $db->insert('countries', [
                            'hero_country_id' => $heroCid,
                            'name' => $displayName,
                            'name_en' => $eng,
                            'name_cn' => $chn,
                            'code' => $code,
                            'phone_code' => $phoneCode,
                            'flag' => $flag,
                            'active' => 1,
                            'sort_order' => 0,
                            'created_at' => date('Y-m-d H:i:s'),
                            'updated_at' => date('Y-m-d H:i:s'),
                        ]);
                        $synced++;
                    }
                } catch (Exception $e) {
                    $skipped++;
                }
            }

            $success = "国家同步完成：新增 {$synced}、更新 {$updated}、跳过 {$skipped}（API 格式：{$rawFormat}）";
        } else {
            $error = "从 HeroSMS 获取国家失败：" . ($result['error'] ?? '返回数据为空');
        }
    }
    // 重定向避免重复提交
    $_SESSION['flash_success'] = $success ?? null;
    $_SESSION['flash_error'] = $error ?? null;
    $_SESSION['flash_raw_format'] = $rawFormat ?? null;
    $_SESSION['flash_raw_count'] = is_array($rawResponse) ? count($rawResponse) : 0;
    header('Location: ?page=countries');
    exit;
}

// 读取 flash 消息
if (!empty($_SESSION['flash_success'])) { $success = $_SESSION['flash_success']; unset($_SESSION['flash_success']); }
if (!empty($_SESSION['flash_error'])) { $error = $_SESSION['flash_error']; unset($_SESSION['flash_error']); }

// 切换启用状态
if (($_GET['action'] ?? '') === 'toggle' && ($_GET['id'] ?? null)) {
    $id = intval($_GET['id']);
    $current = $db->query("SELECT active FROM countries WHERE id = ?", [$id])->fetch();
    if ($current) {
        $newStatus = $current['active'] ? 0 : 1;
        try {
            $db->query("UPDATE countries SET active = ? WHERE id = ?", [$newStatus, $id]);
        } catch (Throwable $e) {
            error_log('[countries.php toggle] ' . $e->getMessage());
            $_SESSION['flash_error'] = '切换状态失败：' . $e->getMessage();
        }
    }
    header('Location: ?page=countries');
    exit;
}

// 更新国家信息
if (($_POST['action'] ?? '') === 'update_name' && ($_POST['id'] ?? null)) {
    try {
        $db->query(
            "UPDATE countries SET name_cn = ?, name_en = ?, flag = ?, phone_code = ? WHERE id = ?",
            [$_POST['name_cn'], $_POST['name_en'], $_POST['flag'], $_POST['phone_code'], intval($_POST['id'])]
        );
        $_SESSION['flash_success'] = '国家信息已更新';
    } catch (Throwable $e) {
        error_log('[countries.php update_name] ' . $e->getMessage());
        $_SESSION['flash_error'] = '更新失败：' . $e->getMessage();
    }
    header('Location: ?page=countries');
    exit;
}

$countries = $db->query("SELECT * FROM countries ORDER BY id")->fetchAll();

$totalCountries = count($countries);
$activeCountries = count(array_filter($countries, fn($c) => $c['active']));
$withServices = $db->query(
    "SELECT COUNT(DISTINCT country_id) as cnt FROM service_countries WHERE is_active = 1"
)->fetch()['cnt'] ?? 0;
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
    <h4 style="margin:0;">🌍 国家基础库</h4>
    <a href="?page=countries&action=sync_countries"
       onclick="return confirm('确定要从 HeroSMS 同步国家列表吗？这将覆盖现有的国家名称/旗帜信息。');"
       style="background:linear-gradient(135deg,#10b981 0%,#059669 100%);color:white;padding:10px 20px;border-radius:8px;text-decoration:none;font-weight:600;font-size:14px;display:inline-flex;align-items:center;gap:6px;">
        🔄 同步 HeroSMS 国家
    </a>
</div>

<?php if(isset($success)): ?>
<div style="background:#d1fae5;color:#065f46;padding:14px 20px;border-radius:8px;margin-bottom:20px;font-size:14px;">✓ <?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if(isset($error)): ?>
<div style="background:#fee2e2;color:#991b1b;padding:14px 20px;border-radius:8px;margin-bottom:20px;font-size:14px;">✗ <?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if(!empty($schemaError)): ?>
<div style="background:#fef3c7;color:#92400e;padding:14px 20px;border-radius:8px;margin-bottom:20px;font-size:14px;">⚠️ <?= htmlspecialchars($schemaError) ?></div>
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
                    <div style="font-size:13px;margin-bottom:20px;">点击右上角「🔄 同步 HeroSMS 国家」按钮拉取数据</div>
                    <a href="?page=countries&action=sync_countries" style="background:linear-gradient(135deg,#10b981 0%,#059669 100%);color:white;padding:10px 20px;border-radius:8px;text-decoration:none;font-weight:600;display:inline-block;">🔄 立即同步</a>
                </td>
            </tr>
            <?php else: ?>
            <?php foreach($countries as $country):
                $flagHeroId = $country['hero_country_id'] ?? '';
                $flagCdn  = $flagHeroId !== '' ? "https://cdn.hero-sms.com/assets/img/country/{$flagHeroId}.svg" : '';
                $flagPath = $flagHeroId !== '' ? "../../pic/country/{$flagHeroId}.svg" : '';
                $flagInitial = strtoupper(substr($country['code'] ?? $flagHeroId, 0, 2));
            ?>
            <tr style="<?= !$country['active'] ? 'opacity:0.5;' : '' ?>">
                <td style="color:#64748b;"><?= $country['id'] ?></td>
                <td><small style="color:#64748b;background:#f1f5f9;padding:2px 6px;border-radius:4px;font-size:11px;"><?= htmlspecialchars($country['hero_country_id']) ?></small></td>
                <td>
                    <img src="<?= htmlspecialchars($flagCdn) ?>"
                         onerror="if(!this.dataset.fb){this.dataset.fb='1';this.src='<?= htmlspecialchars($flagPath) ?>';}else{this.onerror=null;this.outerHTML='<div style=\'width:28px;height:20px;border-radius:4px;background:linear-gradient(135deg,#0ea5e9,#6366f1);color:#fff;display:flex;align-items:center;justify-content:center;font-size:9px;font-weight:700;\'><?= htmlspecialchars($flagInitial) ?></div>';}"
                         style="width:28px;height:20px;border-radius:4px;object-fit:contain;background:#f8fafc;"
                         loading="lazy"
                         title="<?= htmlspecialchars($country['name_en'] ?? $country['name'] ?? '') ?>">
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
        <li>点击「🔄 同步 HeroSMS 国家」可拉取/更新最新国家列表</li>
        <li>国家与服务的关联配置在「服务配置」页面，点击对应服务的「🌍 国家」按钮</li>
        <li>每个服务可以配置不同的国家展示组合</li>
    </ul>
</div>
