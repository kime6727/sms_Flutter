<?php
/**
 * 服务配置页面 - 完整重写
 * 支持从HeroSMS同步服务，并管理上架/下架
 */

require_once __DIR__ . '/../../lib/HeroSMS.php';
require_once __DIR__ . '/../../lib/KeyManager.php';

// 处理一键同步 - 强制保存所有数据
if (($_GET['action'] ?? '') === 'sync_all') {
    $heroSmsApiKey = KeyManager::getHeroSmsApiKey();
    if (empty($heroSmsApiKey)) {
        die("ERROR: hero-sms API key is not set in database");
    }
    $heroSMS = new HeroSMS($heroSmsApiKey, HEROSMS_BASE_URL);
    $syncedServices = 0;
    $syncedCountries = 0;
    $syncedPrices = 0;

    // 1. 同步服务列表 - REPLACE方式直接覆盖
    $result = $heroSMS->getServicesList();
    if ($result['success'] && !empty($result['services'])) {
        foreach ($result['services'] as $s) {
            $existing = $db->query("SELECT id, is_published FROM services WHERE hero_service_id = ?", [$s['id']])->fetch();
            if ($existing) {
                // 更新，但保留is_published状态
                $db->query(
                    "UPDATE services SET name = ?, name_en = ?, name_cn = ?, code = ?, icon = ?, active = 1 WHERE hero_service_id = ?",
                    [$s['name'], $s['name'], $s['name'], $s['code'] ?? $s['id'], $s['icon'] ?? '', $s['id']]
                );
            } else {
                $db->insert('services', [
                    'hero_service_id' => $s['id'],
                    'name' => $s['name'],
                    'name_en' => $s['name'],
                    'name_cn' => $s['name'],
                    'code' => $s['code'] ?? $s['id'],
                    'icon' => $s['icon'] ?? '',
                    'active' => 1,
                    'is_published' => 0,
                    'sort_order' => intval($s['id']),
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                $syncedServices++;
            }
        }
    }

    // 2. 同步国家列表 - 直接插入/更新
    $countriesResult = $heroSMS->getCountriesList();
    if ($countriesResult['success'] && !empty($countriesResult['countries'])) {
        foreach ($countriesResult['countries'] as $c) {
            $existing = $db->query("SELECT id FROM countries WHERE hero_country_id = ?", [$c['id']])->fetch();
            if ($existing) {
                $db->query(
                    "UPDATE countries SET name = ?, name_en = ?, name_cn = ?, code = ?, flag = ?, phone_code = ?, active = 1 WHERE hero_country_id = ?",
                    [$c['name'], $c['name'], $c['name'], $c['code'] ?? '', $c['flag'] ?? '🏳️', $c['phone_code'] ?? '', $c['id']]
                );
            } else {
                $db->insert('countries', [
                    'hero_country_id' => $c['id'],
                    'name' => $c['name'],
                    'name_en' => $c['name'],
                    'name_cn' => $c['name'],
                    'code' => $c['code'] ?? '',
                    'flag' => $c['flag'] ?? '🏳️',
                    'phone_code' => $c['phone_code'] ?? '',
                    'active' => 1,
                    'sort_order' => 0,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                $syncedCountries++;
            }
        }
    }

    // 3. 同步服务-国家价格 - 批量更新/插入
    $pricesResult = $heroSMS->getPrices();
    if ($pricesResult['success'] && !empty($pricesResult['prices'])) {
        foreach ($pricesResult['prices'] as $price) {
            $service = $db->query("SELECT id FROM services WHERE hero_service_id = ?", [$price['service_id']])->fetch();
            $country = $db->query("SELECT id FROM countries WHERE hero_country_id = ?", [$price['country_id']])->fetch();

            if ($service && $country) {
                $existing = $db->query(
                    "SELECT id, is_published FROM service_countries WHERE service_id = ? AND country_id = ?",
                    [$service['id'], $country['id']]
                )->fetch();

                if ($existing) {
                    // 更新价格，但保留is_published状态
                    $db->query(
                        "UPDATE service_countries SET price = ?, active = 1 WHERE service_id = ? AND country_id = ?",
                        [$price['price'], $service['id'], $country['id']]
                    );
                } else {
                    $db->insert('service_countries', [
                        'service_id' => $service['id'],
                        'country_id' => $country['id'],
                        'price' => $price['price'],
                        'active' => 1,
                        'is_published' => 0,
                        'is_auto' => 0,
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                    $syncedPrices++;
                }
            }
        }
    }

    $success = "同步完成！新增服务: $syncedServices, 新增国家: $syncedCountries, 新增价格: $syncedPrices (价格已更新)";
}

// 处理批量操作（AJAX/POST）
if (($_POST['action'] ?? '') === 'batch_publish' && !empty($_POST['ids'])) {
    $rawIds = explode(',', $_POST['ids']);
    $ids = array_filter(array_map('intval', $rawIds));
    if (!empty($ids)) {
        $idList = implode(',', $ids);
        $db->query("UPDATE services SET is_published = 1 WHERE id IN ($idList)");
    }
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'count' => count($ids)]);
        exit;
    }
    header('Location: ?page=services&msg=' . urlencode("已上架 " . count($ids) . " 个服务"));
    exit;
}
if (($_POST['action'] ?? '') === 'batch_unpublish' && !empty($_POST['ids'])) {
    $rawIds = explode(',', $_POST['ids']);
    $ids = array_filter(array_map('intval', $rawIds));
    if (!empty($ids)) {
        $idList = implode(',', $ids);
        $db->query("UPDATE services SET is_published = 0 WHERE id IN ($idList)");
    }
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'count' => count($ids)]);
        exit;
    }
    header('Location: ?page=services&msg=' . urlencode("已下架 " . count($ids) . " 个服务"));
    exit;
}

// 处理服务上架/下架
if (($_GET['action'] ?? '') === 'toggle_publish' && ($_GET['id'] ?? null)) {
    $id = intval($_GET['id']);
    $current = $db->query("SELECT is_published FROM services WHERE id = ?", [$id])->fetch();
    if ($current) {
        $newStatus = $current['is_published'] ? 0 : 1;
        $db->query("UPDATE services SET is_published = ? WHERE id = ?", [$newStatus, $id]);
    }
    header('Location: ?page=services');
    exit;
}

// 处理服务名称更新（AJAX）
if (($_POST['action'] ?? '') === 'update_service' && ($_POST['id'] ?? null)) {
    $db->query(
        "UPDATE services SET name_cn = ?, name_en = ?, sort_order = ?, is_pinned = ?, tag = ? WHERE id = ?",
        [$_POST['name_cn'], $_POST['name_en'], intval($_POST['sort_order']), intval($_POST['is_pinned'] ?? 0), intval($_POST['tag'] ?? 0), intval($_POST['id'])]
    );
    // AJAX返回JSON，否则redirect
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    }
    header('Location: ?page=services&msg=' . urlencode("服务信息已更新"));
    exit;
}

// 获取服务列表（包含统计）
$services = $db->query(
    "SELECT s.*,
            (SELECT COUNT(*) FROM service_countries sc WHERE sc.service_id = s.id AND sc.is_published = 1) as published_countries,
            (SELECT COUNT(*) FROM service_countries sc WHERE sc.service_id = s.id) as total_countries
     FROM services s ORDER BY s.is_pinned DESC, s.is_published DESC, s.sort_order ASC"
)->fetchAll();

$msg = $_GET['msg'] ?? '';
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
    <h4 style="margin:0;">⚙️ 服务配置</h4>
    <div style="display:flex;gap:12px;">
        <a href="?page=services&action=sync_all" onclick="return confirm('确定要从HeroSMS同步所有服务和国家数据吗？')" style="background:#10b981;color:white;padding:10px 20px;border-radius:8px;text-decoration:none;font-size:14px;font-weight:500;">🔄 一键同步HeroSMS</a>
    </div>
</div>

<?php if(isset($success)): ?>
<div style="background:#d1fae5;color:#065f46;padding:14px 20px;border-radius:8px;margin-bottom:20px;font-size:14px;">✓ <?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if($msg): ?>
<div style="background:#d1fae5;color:#065f46;padding:14px 20px;border-radius:8px;margin-bottom:20px;font-size:14px;">✓ <?= htmlspecialchars($msg) ?></div>
<?php endif; ?>
<?php if(isset($error)): ?>
<div style="background:#fee2e2;color:#991b1b;padding:14px 20px;border-radius:8px;margin-bottom:20px;font-size:14px;">✗ <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form id="batchForm" method="POST">
    <input type="hidden" name="action" id="batchAction" value="">
    <input type="hidden" name="ids" id="batchIds" value="">
</form>

<div class="card">
    <div style="padding:16px 20px;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
        <div style="display:flex;align-items:center;gap:16px;">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:14px;">
                <input type="checkbox" id="selectAll" onchange="toggleAllServices(this.checked)" style="transform:scale(1.2);">
                全选
            </label>
            <span style="font-weight:600;color:#0f172a;">服务列表</span>
            <span style="margin-left:8px;padding:2px 10px;background:#e0e7ff;color:#4f46e5;border-radius:12px;font-size:13px;font-weight:500;">
                <?= count($services) ?> 个服务
            </span>
            <span id="selectedCount" style="display:none;margin-left:8px;padding:2px 10px;background:#fef3c7;color:#92400e;border-radius:12px;font-size:13px;font-weight:500;">
                已选 <strong id="selectedNum">0</strong> 项
            </span>
        </div>
        <div style="display:flex;gap:8px;align-items:center;">
            <button type="button" onclick="batchAction('batch_publish')" style="background:#6366f1;color:white;border:none;padding:8px 16px;border-radius:8px;cursor:pointer;font-size:13px;font-weight:500;">✅ 批量上架</button>
            <button type="button" onclick="batchAction('batch_unpublish')" style="background:#fef3c7;color:#92400e;border:none;padding:8px 16px;border-radius:8px;cursor:pointer;font-size:13px;font-weight:500;">⏸ 批量下架</button>
        </div>
    </div>
    <table>
        <thead>
            <tr>
                <th style="width:40px;"><input type="checkbox" id="selectAllHeader" onchange="toggleAllServices(this.checked)" style="transform:scale(1.2);"></th>
                <th style="width:60px;">排序</th>
                <th style="width:50px;">图标</th>
                <th>HeroID</th>
                <th>服务名称</th>
                <th>中文名</th>
                <th>英文名</th>
                <th style="text-align:center;">国家数</th>
                <th style="text-align:center;">已上架</th>
                <th style="text-align:center;">置顶</th>
                <th style="text-align:center;">标签</th>
                <th style="text-align:center;">状态</th>
                <th style="text-align:center;">操作</th>
            </tr>
        </thead>
        <tbody>
            <?php if(empty($services)): ?>
            <tr>
                <td colspan="13" style="text-align:center;color:#64748b;padding:60px;">
                    <div style="font-size:48px;margin-bottom:16px;">📦</div>
                    <div style="margin-bottom:12px;">暂无服务数据</div>
                    <div style="font-size:13px;">点击右上角「一键同步HeroSMS」获取服务列表</div>
                </td>
            </tr>
            <?php else: ?>
            <?php foreach($services as $service): ?>
            <tr style="<?= !$service['is_published'] ? 'opacity:0.6;' : '' ?>">
                <td>
                    <input type="checkbox" class="service-checkbox" value="<?= $service['id'] ?>" onchange="updateSelectedCount()" style="transform:scale(1.2);">
                </td>
                <td>
                    <input type="number" class="inline-edit" data-field="sort_order" data-id="<?= $service['id'] ?>" value="<?= $service['sort_order'] ?: 0 ?>" style="width:50px;padding:4px 6px;border:1px solid #e2e8f0;border-radius:4px;font-size:12px;text-align:center;">
                </td>
                <td>
                    <?php 
                    $iconUrl = '';
                    if (!empty($service['icon'])) {
                        $cdnBase = 'https://cdn.hero-sms.com/assets/img/service/';
                        if (strpos($service['icon'], $cdnBase) === 0) {
                            $filename = substr($service['icon'], strlen($cdnBase));
                            $iconUrl = '../../pic/fuwu/' . htmlspecialchars($filename);
                        } elseif (strpos($service['icon'], '/pic/fuwu/') !== false) {
                            $iconUrl = htmlspecialchars($service['icon']);
                        } else {
                            $iconUrl = htmlspecialchars($service['icon']);
                        }
                    }
                    ?>
                    <?php if(!empty($iconUrl)): ?>
                    <img src="<?= $iconUrl ?>" style="width:28px;height:28px;border-radius:6px;object-fit:contain;" onerror="this.style.display='none'">
                    <?php endif; ?>
                </td>
                <td><small style="color:#64748b;background:#f1f5f9;padding:2px 6px;border-radius:4px;"><?= htmlspecialchars($service['hero_service_id']) ?></small></td>
                <td>
                    <div style="font-weight:500;"><?= htmlspecialchars($service['name']) ?></div>
                    <div style="font-size:11px;color:#94a3b8;"><?= htmlspecialchars($service['code'] ?? '') ?></div>
                </td>
                <td>
                    <input type="text" class="inline-edit" data-field="name_cn" data-id="<?= $service['id'] ?>" value="<?= htmlspecialchars($service['name_cn'] ?? '') ?>" style="width:100px;padding:4px 8px;border:1px solid #e2e8f0;border-radius:6px;font-size:13px;">
                </td>
                <td>
                    <input type="text" class="inline-edit" data-field="name_en" data-id="<?= $service['id'] ?>" value="<?= htmlspecialchars($service['name_en'] ?? '') ?>" style="width:100px;padding:4px 8px;border:1px solid #e2e8f0;border-radius:6px;font-size:13px;">
                </td>
                <td style="text-align:center;"><span style="color:#64748b;"><?= $service['total_countries'] ?></span></td>
                <td style="text-align:center;"><span style="color:#10b981;font-weight:600;"><?= $service['published_countries'] ?></span></td>
                <td style="text-align:center;">
                    <input type="checkbox" class="inline-toggle" data-field="is_pinned" data-id="<?= $service['id'] ?>" value="1" <?= $service['is_pinned'] ? 'checked' : '' ?> style="transform:scale(1.2);">
                </td>
                <td style="text-align:center;">
                    <select class="inline-edit" data-field="tag" data-id="<?= $service['id'] ?>" style="padding:4px;border:1px solid #e2e8f0;border-radius:6px;font-size:12px;">
                        <option value="0" <?= $service['tag']==0?'selected':'' ?>>-无-</option>
                        <option value="1" <?= $service['tag']==1?'selected':'' ?> style="color:#ef4444;">🔥 热门</option>
                        <option value="2" <?= $service['tag']==2?'selected':'' ?> style="color:#f59e0b;">⭐ 推荐</option>
                    </select>
                </td>
                <td style="text-align:center;">
                    <?php if($service['is_published']): ?>
                    <span style="background:#d1fae5;color:#065f46;padding:4px 10px;border-radius:12px;font-size:12px;font-weight:600;">已上架</span>
                    <?php else: ?>
                    <span style="background:#f1f5f9;color:#64748b;padding:4px 10px;border-radius:12px;font-size:12px;">未上架</span>
                    <?php endif; ?>
                </td>
                <td style="text-align:center;white-space:nowrap;">
                    <a href="?page=services&action=toggle_publish&id=<?= $service['id'] ?>"
                       style="padding:6px 12px;border-radius:6px;text-decoration:none;font-size:12px;font-weight:500;<?= $service['is_published'] ? 'background:#fef3c7;color:#92400e;' : 'background:#10b981;color:white;' ?>">
                        <?= $service['is_published'] ? '⏸ 下架' : '▶️ 上架' ?>
                    </a>
                    <a href="?page=service_countries&id=<?= $service['id'] ?>"
                       style="margin-left:4px;padding:6px 12px;background:#e0e7ff;color:#4f46e5;border-radius:6px;text-decoration:none;font-size:12px;font-weight:500;">
                        🌍 国家
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div style="margin-top:20px;padding:16px;background:#fffbeb;border:1px solid #fde68a;border-radius:12px;">
    <div style="font-weight:600;color:#92400e;margin-bottom:8px;">💡 使用说明</div>
    <ul style="margin:0;padding-left:20px;color:#78350f;font-size:13px;line-height:1.8;">
        <li>点击「一键同步HeroSMS」获取所有服务、国家和价格数据并保存到本地数据库</li>
        <li>同步后数据保存在本地，之后API优先从本地读取，不再每次调用HeroSMS</li>
        <li>勾选服务后可使用「批量上架/下架」功能</li>
        <li>直接修改行内的中文名、英文名、排序、标签后按回车或点击外部即可保存</li>
        <li>点击服务行的「上架/下架」按钮控制是否对用户展示</li>
        <li>点击「国家」按钮可以管理该服务展示的国家</li>
    </ul>
</div>

<script>
function toggleAllServices(checked) {
    document.querySelectorAll('.service-checkbox').forEach(cb => cb.checked = checked);
    document.getElementById('selectAll').checked = checked;
    document.getElementById('selectAllHeader').checked = checked;
    updateSelectedCount();
}

function updateSelectedCount() {
    const checked = document.querySelectorAll('.service-checkbox:checked');
    const count = checked.length;
    const countEl = document.getElementById('selectedCount');
    const numEl = document.getElementById('selectedNum');
    if (count > 0) {
        countEl.style.display = 'inline';
        numEl.textContent = count;
    } else {
        countEl.style.display = 'none';
    }
}

function batchAction(action) {
    const checked = document.querySelectorAll('.service-checkbox:checked');
    if (checked.length === 0) {
        alert('请先选择要操作的服务');
        return;
    }
    
    const actionText = action === 'batch_publish' ? '上架' : '下架';
    if (!confirm('确定要' + actionText + '选中的 ' + checked.length + ' 个服务吗？')) {
        return;
    }
    
    const ids = Array.from(checked).map(cb => cb.value).join(',');
    
    const formData = new FormData();
    formData.append('action', action);
    formData.append('ids', ids);
    
    const btn = event.target;
    const originalText = btn.textContent;
    btn.textContent = '处理中...';
    btn.disabled = true;
    
    fetch('index.php?page=services', {
        method: 'POST',
        body: formData,
        headers: {'X-Requested-With': 'XMLHttpRequest'}
    })
    .then(r => {
        if (!r.ok) {
            return r.text().then(text => { throw new Error('HTTP ' + r.status + ': ' + text.substring(0, 500)); });
        }
        return r.json();
    })
    .then(data => {
        if (data.success) {
            alert('操作成功，已' + actionText + ' ' + data.count + ' 个服务');
            window.location.reload();
        } else {
            alert('操作失败: ' + (data.error || '未知错误'));
        }
    })
    .catch((err) => {
        alert('请求失败: ' + err.message);
    })
    .finally(() => {
        btn.textContent = originalText;
        btn.disabled = false;
    });
}

// 行内编辑：失去焦点时自动保存
document.querySelectorAll('.inline-edit').forEach(el => {
    el.addEventListener('blur', function() {
        saveServiceField(this.dataset.id, this.dataset.field, this.value);
    });
    el.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.target.blur();
        }
    });
});

// 复选框切换时自动保存
document.querySelectorAll('.inline-toggle').forEach(el => {
    el.addEventListener('change', function() {
        saveServiceField(this.dataset.id, this.dataset.field, this.checked ? 1 : 0);
    });
});

function saveServiceField(id, field, value) {
    const formData = new FormData();
    formData.append('action', 'update_service');
    formData.append('id', id);
    formData.append(field, value);
    formData.append('name_cn', document.querySelector(`.inline-edit[data-id="${id}"][data-field="name_cn"]`)?.value || '');
    formData.append('name_en', document.querySelector(`.inline-edit[data-id="${id}"][data-field="name_en"]`)?.value || '');
    formData.append('sort_order', document.querySelector(`.inline-edit[data-id="${id}"][data-field="sort_order"]`)?.value || 0);
    formData.append('is_pinned', document.querySelector(`.inline-toggle[data-id="${id}"][data-field="is_pinned"]`)?.checked ? 1 : 0);
    formData.append('tag', document.querySelector(`.inline-edit[data-id="${id}"][data-field="tag"]`)?.value || 0);
    
    fetch('', {
        method: 'POST',
        body: formData,
        headers: {'X-Requested-With': 'XMLHttpRequest'}
    }).then(r => r.json()).then(data => {
        if (data.success) {
            // 静默保存成功，不刷新页面
        }
    }).catch(() => {});
}
</script>
