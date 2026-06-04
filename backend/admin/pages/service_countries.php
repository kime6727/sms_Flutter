<?php
/**
 * 服务-国家配置页面
 * 单个服务在哪些国家可见、价格系数、上下架
 */

require_once __DIR__ . '/../../lib/HeroSMS.php';
require_once __DIR__ . '/../../lib/KeyManager.php';

$serviceId = intval($_GET['service_id'] ?? $_GET['id'] ?? 0);
$service = null;
if ($serviceId) {
    try {
        $service = $db->query("SELECT * FROM services WHERE id = ?", [$serviceId])->fetch();
    } catch (Exception $e) {
        echo '<div style="padding:40px;text-align:center;color:#dc2626;">查询服务失败: ' . htmlspecialchars($e->getMessage()) . '</div>';
        return;
    }
}

if (!$service) {
    echo '<div style="padding:40px;text-align:center;color:#64748b;">请从「服务配置」页面选择一个服务</div>';
    return;
}

// ==================== 同步该服务的国家价格 ====================
if (($_GET['action'] ?? '') === 'sync_service_countries') {
    $apiKey = KeyManager::getHeroSmsApiKey();
    if (empty($apiKey)) {
        $error = "HeroSMS API key 未配置";
    } else {
        $heroSMS = new HeroSMS($apiKey, HEROSMS_BASE_URL);
        // 调用 getPrices 不带 service 参数以获取全部
        $pricesResp = $heroSMS->getPrices();
        $syncedPrices = 0;
        $updatedPrices = 0;
        if (!empty($pricesResp['success']) && !empty($pricesResp['prices'])) {
            foreach ($pricesResp['prices'] as $countryHeroId => $serviceMap) {
                if (!is_array($serviceMap)) continue;
                foreach ($serviceMap as $serviceCode => $priceData) {
                    if ((string)$serviceCode !== (string)$service['hero_service_id']) continue;
                    if (!is_array($priceData) || empty($priceData)) continue;
                    $cost = 0;
                    $count = 0;
                    if (isset($priceData['cost'])) {
                        $cost = floatval($priceData['cost']);
                        $count = intval($priceData['count'] ?? 0);
                    } else {
                        $costKey = array_key_first($priceData);
                        if ($costKey !== null) {
                            $cost = floatval($costKey);
                            $count = intval($priceData[$costKey] ?? 0);
                        }
                    }
                    if ($cost <= 0) continue;
                    $cty = $db->query("SELECT id FROM countries WHERE hero_country_id = ?", [(string)$countryHeroId])->fetch();
                    if (!$cty) continue;
                    $exist = $db->query("SELECT id FROM service_countries WHERE service_id = ? AND country_id = ?", [$serviceId, $cty['id']])->fetch();
                    if ($exist) {
                        $db->query("UPDATE service_countries SET price = ?, stock = ?, is_active = 1, updated_at = NOW() WHERE id = ?", [$cost, $count, $exist['id']]);
                        $updatedPrices++;
                    } else {
                        $db->insert('service_countries', [
                            'service_id' => $serviceId,
                            'country_id' => $cty['id'],
                            'price' => $cost,
                            'stock' => $count,
                            'is_published' => 1,
                            'is_active' => 1,
                            'created_at' => date('Y-m-d H:i:s'),
                            'updated_at' => date('Y-m-d H:i:s'),
                        ]);
                        $syncedPrices++;
                    }
                }
            }
        }
        $success = "同步完成：新增 {$syncedPrices} 条、更新 {$updatedPrices} 条价格";
    }
}

// ==================== 批量操作 ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['ajax'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    $rawIds = explode(',', $_POST['ids'] ?? '');
    $ids = array_filter(array_map('intval', $rawIds));

    if (empty($ids)) {
        echo json_encode(['success' => false, 'error' => '未选择项目']);
        exit;
    }
    $idList = implode(',', $ids);
    $sid = intval($_POST['service_id'] ?? $serviceId);

    switch ($action) {
        case 'batch_publish':
            $db->query("UPDATE service_countries SET is_published = 1 WHERE service_id = ? AND id IN ($idList)", [$sid]);
            echo json_encode(['success' => true, 'count' => count($ids)]);
            break;
        case 'batch_unpublish':
            $db->query("UPDATE service_countries SET is_published = 0 WHERE service_id = ? AND id IN ($idList)", [$sid]);
            echo json_encode(['success' => true, 'count' => count($ids)]);
            break;
        case 'toggle_sc':
            $id = intval($_POST['id']);
            $cur = $db->query("SELECT is_published FROM service_countries WHERE id = ?", [$id])->fetch();
            if ($cur) {
                $new = $cur['is_published'] ? 0 : 1;
                $db->query("UPDATE service_countries SET is_published = ? WHERE id = ?", [$new, $id]);
                echo json_encode(['success' => true, 'is_published' => $new]);
            } else {
                echo json_encode(['success' => false]);
            }
            break;
        case 'update_sc':
            $id = intval($_POST['id']);
            $customPrice = $_POST['custom_price'] !== '' ? floatval($_POST['custom_price']) : null;
            $coefficient = floatval($_POST['coefficient'] ?? 1);
            $is_published = intval($_POST['is_published'] ?? 1);
            $db->query(
                "UPDATE service_countries SET custom_price = ?, coefficient = ?, is_published = ? WHERE id = ?",
                [$customPrice, $coefficient, $is_published, $id]
            );
            echo json_encode(['success' => true]);
            break;
        case 'add_country':
            $cid = intval($_POST['country_id']);
            $db->query("INSERT IGNORE INTO service_countries (service_id, country_id, price, is_published, is_active, created_at, updated_at) VALUES (?, ?, 0, 1, 1, NOW(), NOW())", [$sid, $cid]);
            echo json_encode(['success' => true]);
            break;
        case 'remove_sc':
            $id = intval($_POST['id']);
            $db->query("DELETE FROM service_countries WHERE id = ?", [$id]);
            echo json_encode(['success' => true]);
            break;
        case 'publish_all':
            $db->query("UPDATE service_countries SET is_published = 1 WHERE service_id = ?", [$sid]);
            echo json_encode(['success' => true]);
            break;
        case 'unpublish_all':
            $db->query("UPDATE service_countries SET is_published = 0 WHERE service_id = ?", [$sid]);
            echo json_encode(['success' => true]);
            break;
        default:
            echo json_encode(['success' => false, 'error' => '未知操作']);
    }
    exit;
}

// ==================== 准备数据 ====================
// 升级 service_countries 表结构（如果需要）
try {
    $cols = $db->query("SHOW COLUMNS FROM service_countries")->fetchAll();
    $colNames = array_column($cols, 'Field');
    if (!in_array('custom_price', $colNames)) {
        $db->query("ALTER TABLE service_countries ADD COLUMN `custom_price` decimal(10,4) DEFAULT NULL COMMENT '自定义价格(覆盖 HeroSMS 返回的成本价)' AFTER `price`");
    }
    if (!in_array('coefficient', $colNames)) {
        $db->query("ALTER TABLE service_countries ADD COLUMN `coefficient` decimal(5,2) NOT NULL DEFAULT '1.00' COMMENT '价格倍数' AFTER `custom_price`");
    }
} catch (Exception $e) {
    $schemaError = 'service_countries 表结构自愈失败: ' . $e->getMessage();
}

$search = trim($_GET['q'] ?? '');
$filter = $_GET['filter'] ?? 'all';

$where = ['sc.service_id = ?'];
$params = [$serviceId];
if ($search !== '') {
    $where[] = '(c.name LIKE ? OR c.name_cn LIKE ? OR c.name_en LIKE ? OR c.hero_country_id LIKE ?)';
    $s = "%$search%";
    $params = array_merge($params, [$s, $s, $s, $s]);
}
if ($filter === 'published') $where[] = 'sc.is_published = 1';
if ($filter === 'unpublished') $where[] = 'sc.is_published = 0';
if ($filter === 'in_stock') $where[] = 'sc.stock > 0';
if ($filter === 'no_price') $where[] = '(sc.price IS NULL OR sc.price = 0)';

$whereSql = implode(' AND ', $where);

try {
    $rows = $db->query(
        "SELECT sc.*, c.name, c.name_en, c.name_cn, c.hero_country_id, c.code as country_code, c.flag
         FROM service_countries sc
         JOIN countries c ON sc.country_id = c.id
         WHERE $whereSql
         ORDER BY sc.is_published DESC, sc.stock DESC, c.sort_order ASC, c.id ASC",
        $params
    )->fetchAll();
    $queryError = null;
} catch (Exception $e) {
    $rows = [];
    $queryError = $e->getMessage();
}

$total = count($rows);
$published = count(array_filter($rows, fn($r) => $r['is_published']));
$inStock = count(array_filter($rows, fn($r) => $r['stock'] > 0));

// 用于「新增国家」下拉
try {
    $linkedCountryIds = array_column($rows, 'country_id');
    $availableCountries = $db->query("SELECT * FROM countries ORDER BY sort_order, id")->fetchAll();
    $availableCountries = array_filter($availableCountries, fn($c) => !in_array($c['id'], $linkedCountryIds));
} catch (Exception $e) {
    $availableCountries = [];
}
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px;">
    <div>
        <a href="?page=services" style="color:#6366f1;text-decoration:none;font-size:14px;">← 返回服务列表</a>
        <h4 style="margin:8px 0 0 0;">🌍 <?= htmlspecialchars($service['name_cn'] ?: $service['name_en'] ?: $service['name']) ?> - 国家配置</h4>
        <div style="font-size:13px;color:#64748b;margin-top:4px;">
            HeroID: <code><?= htmlspecialchars($service['hero_service_id']) ?></code>
            <?php if($service['is_published']): ?>
                · <span style="color:#10b981;">✓ 已上架</span>
            <?php else: ?>
                · <span style="color:#ef4444;">✗ 已下架</span>
            <?php endif; ?>
        </div>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <a href="?page=service_countries&service_id=<?= $serviceId ?>&action=sync_service_countries" onclick="return confirm('从 HeroSMS 同步该服务的所有国家价格？')" style="background:#10b981;color:white;padding:10px 20px;border-radius:8px;text-decoration:none;font-size:14px;font-weight:500;">🔄 同步价格</a>
        <a href="javascript:void(0)" onclick="batchAction('publish_all')" style="background:#6366f1;color:white;padding:10px 20px;border-radius:8px;text-decoration:none;font-size:14px;font-weight:500;">📦 全部上架</a>
        <a href="javascript:void(0)" onclick="batchAction('unpublish_all')" style="background:#94a3b8;color:white;padding:10px 20px;border-radius:8px;text-decoration:none;font-size:14px;font-weight:500;">📭 全部下架</a>
    </div>
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
<?php if(!empty($queryError)): ?>
<div style="background:#fee2e2;color:#991b1b;padding:14px 20px;border-radius:8px;margin-bottom:20px;font-size:14px;">
    <div style="font-weight:600;margin-bottom:6px;">✗ 数据查询失败</div>
    <div style="font-family:monospace;font-size:12px;background:#fff5f5;padding:8px 12px;border-radius:6px;margin-top:6px;"><?= htmlspecialchars($queryError) ?></div>
    <div style="margin-top:10px;font-size:13px;">请检查 service_countries 表结构。可在 SQL 编辑器执行：</div>
    <pre style="background:#1e293b;color:#e2e8f0;padding:12px;border-radius:6px;margin-top:8px;font-size:12px;overflow-x:auto;">ALTER TABLE service_countries
  ADD COLUMN custom_price decimal(10,4) DEFAULT NULL AFTER price,
  ADD COLUMN coefficient decimal(5,2) NOT NULL DEFAULT 1.00 AFTER custom_price;</pre>
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:16px;">
    <div class="card" style="padding:14px;text-align:center;">
        <div style="font-size:12px;color:#64748b;">国家总数</div>
        <div style="font-size:24px;font-weight:700;color:#0f172a;"><?= $total ?></div>
    </div>
    <div class="card" style="padding:14px;text-align:center;">
        <div style="font-size:12px;color:#64748b;">已上架</div>
        <div style="font-size:24px;font-weight:700;color:#10b981;"><?= $published ?></div>
    </div>
    <div class="card" style="padding:14px;text-align:center;">
        <div style="font-size:12px;color:#64748b;">有库存</div>
        <div style="font-size:24px;font-weight:700;color:#3b82f6;"><?= $inStock ?></div>
    </div>
    <div class="card" style="padding:14px;text-align:center;">
        <div style="font-size:12px;color:#64748b;">可新增</div>
        <div style="font-size:24px;font-weight:700;color:#8b5cf6;"><?= count($availableCountries) ?></div>
    </div>
</div>

<div class="card" style="padding:12px 16px;margin-bottom:12px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
    <input type="text" id="searchInput" placeholder="🔍 搜索国家..." value="<?= htmlspecialchars($search) ?>" style="flex:1;min-width:200px;padding:8px 12px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;">
    <select id="filterSelect" onchange="applyFilter()" style="padding:8px 12px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;">
        <option value="all" <?= $filter==='all'?'selected':'' ?>>全部</option>
        <option value="published" <?= $filter==='published'?'selected':'' ?>>已上架</option>
        <option value="unpublished" <?= $filter==='unpublished'?'selected':'' ?>>未上架</option>
        <option value="in_stock" <?= $filter==='in_stock'?'selected':'' ?>>有库存</option>
        <option value="no_price" <?= $filter==='no_price'?'selected':'' ?>>无价格</option>
    </select>
    <select id="addCountrySelect" style="padding:8px 12px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;max-width:300px;">
        <option value="">+ 添加国家...</option>
        <?php foreach($availableCountries as $ac): ?>
        <option value="<?= $ac['id'] ?>"><?= htmlspecialchars($ac['name_cn'] ?: $ac['name_en'] ?: $ac['name']) ?> (ID:<?= $ac['id'] ?>)</option>
        <?php endforeach; ?>
    </select>
    <button type="button" onclick="addCountry()" style="background:#8b5cf6;color:white;border:none;padding:8px 16px;border-radius:8px;cursor:pointer;font-size:13px;font-weight:500;">➕ 添加</button>
</div>

<div class="card">
    <div style="padding:16px 20px;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;">
        <div style="display:flex;align-items:center;gap:16px;">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:14px;">
                <input type="checkbox" id="selectAll" onchange="toggleAll(this.checked)" style="transform:scale(1.2);">
                全选
            </label>
            <span style="font-weight:600;color:#0f172a;">国家列表</span>
        </div>
        <div style="display:flex;gap:8px;">
            <button type="button" onclick="batchAction('batch_publish')" style="background:#10b981;color:white;border:none;padding:8px 14px;border-radius:8px;cursor:pointer;font-size:13px;">✅ 批量上架</button>
            <button type="button" onclick="batchAction('batch_unpublish')" style="background:#94a3b8;color:white;border:none;padding:8px 14px;border-radius:8px;cursor:pointer;font-size:13px;">⏸ 批量下架</button>
        </div>
    </div>
    <table>
        <thead>
            <tr>
                <th style="width:40px;"><input type="checkbox" onchange="toggleAll(this.checked)"></th>
                <th>国家</th>
                <th>HeroID</th>
                <th style="text-align:right;">HeroSMS价格(USD)</th>
                <th style="text-align:right;">自定义价格(USD)</th>
                <th style="text-align:center;">系数</th>
                <th style="text-align:center;">积分单价</th>
                <th style="text-align:center;">库存</th>
                <th style="text-align:center;">状态</th>
                <th style="text-align:center;">操作</th>
            </tr>
        </thead>
        <tbody>
            <?php if(empty($rows)): ?>
            <tr><td colspan="10" style="text-align:center;color:#64748b;padding:60px;">
                <div style="font-size:48px;margin-bottom:16px;">🌍</div>
                <div style="margin-bottom:12px;">该服务尚未配置国家</div>
                <div style="font-size:13px;">点击右上角「同步价格」从 HeroSMS 拉取</div>
            </td></tr>
            <?php else: foreach($rows as $r):
                $unitPoints = bcmul((string)$r['price'], (string)($r['coefficient'] ?: 1), 4);
                $unitPointsDisplay = bcmul($unitPoints, '100', 0);
            ?>
            <tr style="<?= !$r['is_published'] ? 'opacity:0.5;' : '' ?>" data-id="<?= $r['id'] ?>">
                <td><input type="checkbox" class="row-cb" value="<?= $r['id'] ?>"></td>
                <td>
                    <div style="font-weight:500;"><?= htmlspecialchars($r['name_cn'] ?: $r['name_en'] ?: $r['name']) ?></div>
                    <div style="font-size:11px;color:#94a3b8;"><?= htmlspecialchars($r['name_en'] ?? '') ?></div>
                </td>
                <td><code style="background:#f1f5f9;padding:2px 6px;border-radius:4px;font-size:11px;"><?= htmlspecialchars($r['hero_country_id']) ?></code></td>
                <td style="text-align:right;font-family:monospace;">
                    <?= $r['price'] > 0 ? '$' . number_format($r['price'], 4) : '<span style="color:#94a3b8;">未设置</span>' ?>
                </td>
                <td style="text-align:right;">
                    <input type="number" step="0.0001" class="field-input" data-field="custom_price" data-id="<?= $r['id'] ?>" value="<?= $r['custom_price'] ?? '' ?>" placeholder="留空用上游" style="width:90px;padding:4px 6px;border:1px solid #e2e8f0;border-radius:4px;font-size:12px;text-align:right;">
                </td>
                <td style="text-align:center;">
                    <input type="number" step="0.1" min="0.1" class="field-input" data-field="coefficient" data-id="<?= $r['id'] ?>" value="<?= $r['coefficient'] ?: 1 ?>" style="width:60px;padding:4px 6px;border:1px solid #e2e8f0;border-radius:4px;font-size:12px;text-align:center;">
                </td>
                <td style="text-align:center;font-family:monospace;color:#10b981;font-weight:600;">
                    <?= $unitPointsDisplay ?> 积分
                </td>
                <td style="text-align:center;">
                    <?php if($r['stock'] > 0): ?>
                        <span style="color:#10b981;font-weight:600;"><?= $r['stock'] ?></span>
                    <?php else: ?>
                        <span style="color:#ef4444;">0</span>
                    <?php endif; ?>
                </td>
                <td style="text-align:center;">
                    <?php if($r['is_published']): ?>
                        <span style="background:#d1fae5;color:#065f46;padding:3px 10px;border-radius:12px;font-size:11px;font-weight:600;">已上架</span>
                    <?php else: ?>
                        <span style="background:#f1f5f9;color:#64748b;padding:3px 10px;border-radius:12px;font-size:11px;">已下架</span>
                    <?php endif; ?>
                </td>
                <td style="text-align:center;white-space:nowrap;">
                    <a href="javascript:void(0)" onclick="togglePublish(<?= $r['id'] ?>)" style="padding:4px 10px;border-radius:6px;text-decoration:none;font-size:12px;font-weight:500;<?= $r['is_published'] ? 'background:#fef3c7;color:#92400e;' : 'background:#10b981;color:white;' ?>">
                        <?= $r['is_published'] ? '⏸ 下架' : '▶ 上架' ?>
                    </a>
                    <a href="javascript:void(0)" onclick="removeSC(<?= $r['id'] ?>)" style="margin-left:2px;padding:4px 10px;background:#fee2e2;color:#991b1b;border-radius:6px;text-decoration:none;font-size:12px;font-weight:500;" title="移除关联">✕</a>
                </td>
            </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<div style="margin-top:20px;padding:16px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:12px;">
    <div style="font-weight:600;color:#1e40af;margin-bottom:8px;">💡 价格说明</div>
    <ul style="margin:0;padding-left:20px;color:#1e40af;font-size:13px;line-height:1.8;">
        <li><strong>HeroSMS价格</strong>：上游 API 返回的成本价（美元），只读</li>
        <li><strong>自定义价格</strong>：运营可手动覆盖（留空则用上游）</li>
        <li><strong>系数</strong>：在自定义价或上游价基础上乘以的倍数（默认 1.0）</li>
        <li><strong>积分单价</strong> = (价格 USD × 系数) × 100 = 用户需支付的积分</li>
        <li>修改后失焦自动保存；<strong>上架/下架</strong> 状态独立控制客户端是否展示</li>
    </ul>
</div>

<script>
const SERVICE_ID = <?= $serviceId ?>;
function toggleAll(checked) {
    document.querySelectorAll('.row-cb').forEach(cb => cb.checked = checked);
    document.getElementById('selectAll').checked = checked;
}
function getSelectedIds() {
    return Array.from(document.querySelectorAll('.row-cb:checked')).map(cb => cb.value).join(',');
}
function batchAction(action) {
    if (action === 'publish_all' || action === 'unpublish_all') {
        if (!confirm('确定要' + (action === 'publish_all' ? '全部上架' : '全部下架') + '吗？')) return;
        sendAjax({action, service_id: SERVICE_ID});
    } else {
        const ids = getSelectedIds();
        if (!ids) { alert('请先选择国家'); return; }
        if (!confirm('确定要执行批量操作吗？')) return;
        sendAjax({action, ids, service_id: SERVICE_ID});
    }
}
function togglePublish(id) {
    sendAjax({action: 'toggle_sc', id, service_id: SERVICE_ID});
}
function removeSC(id) {
    if (!confirm('确定要移除该国家关联吗？')) return;
    sendAjax({action: 'remove_sc', id, service_id: SERVICE_ID});
}
function addCountry() {
    const cid = document.getElementById('addCountrySelect').value;
    if (!cid) { alert('请先选择国家'); return; }
    sendAjax({action: 'add_country', country_id: cid, service_id: SERVICE_ID});
}
function sendAjax(payload) {
    payload.ajax = 1;
    return fetch('?page=service_countries&service_id=' + SERVICE_ID, {
        method: 'POST',
        body: new URLSearchParams(payload),
        headers: {'Content-Type': 'application/x-www-form-urlencoded'}
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) location.reload();
        else alert('失败: ' + (data.error || ''));
    })
    .catch(err => alert('请求失败: ' + err.message));
}
function saveField(id, field, value) {
    const fd = new URLSearchParams();
    fd.append('ajax', '1');
    fd.append('action', 'update_sc');
    fd.append('id', id);
    fd.append('service_id', SERVICE_ID);
    fd.append('custom_price', document.querySelector(`.field-input[data-id="${id}"][data-field="custom_price"]`)?.value || '');
    fd.append('coefficient', document.querySelector(`.field-input[data-id="${id}"][data-field="coefficient"]`)?.value || 1);
    fetch('?page=service_countries&service_id=' + SERVICE_ID, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => { if (!d.success) alert('保存失败'); else location.reload(); })
        .catch(() => {});
}
document.querySelectorAll('.field-input').forEach(el => {
    el.addEventListener('change', function() { saveField(this.dataset.id, this.dataset.field, this.value); });
    el.addEventListener('blur', function() { saveField(this.dataset.id, this.dataset.field, this.value); });
});
let searchTimer = null;
document.getElementById('searchInput').addEventListener('input', function() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(applyFilter, 300);
});
function applyFilter() {
    const q = document.getElementById('searchInput').value.trim();
    const filter = document.getElementById('filterSelect').value;
    const url = new URL(location.href);
    if (q) url.searchParams.set('q', q); else url.searchParams.delete('q');
    url.searchParams.set('filter', filter);
    location.href = url.toString();
}
</script>
