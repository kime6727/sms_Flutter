<?php
/**
 * 服务配置页面 - 完整重写
 * 支持从 HeroSMS 同步服务、本地上下架管理
 */

require_once __DIR__ . '/../../lib/HeroSMS.php';
require_once __DIR__ . '/../../lib/KeyManager.php';

// ==================== 自愈：确保 services 表关键字段存在 ====================
try {
    $svcCols = array_column($db->query("SHOW COLUMNS FROM services")->fetchAll(), 'Field');
    if (!in_array('is_pinned', $svcCols)) {
        $db->query("ALTER TABLE services ADD COLUMN `is_pinned` tinyint DEFAULT '0' COMMENT '是否置顶显示' AFTER `sort_order`");
    }
    if (!in_array('tag', $svcCols)) {
        $db->query("ALTER TABLE services ADD COLUMN `tag` tinyint DEFAULT '0' COMMENT '标签: 0=无 1=热门 2=推荐' AFTER `is_pinned`");
    }
    if (!in_array('name_en', $svcCols)) {
        $db->query("ALTER TABLE services ADD COLUMN `name_en` varchar(255) DEFAULT NULL AFTER `name`");
    }
    if (!in_array('name_cn', $svcCols)) {
        $db->query("ALTER TABLE services ADD COLUMN `name_cn` varchar(255) DEFAULT NULL AFTER `name_en`");
    }
    // 索引自愈
    $svcIndexes = array_column($db->query("SHOW INDEX FROM services")->fetchAll(), 'Key_name');
    if (!in_array('idx_pinned', $svcIndexes)) {
        $db->query("ALTER TABLE services ADD KEY `idx_pinned` (`is_pinned`)");
    }
    if (!in_array('uk_hero_service_id', $svcIndexes)) {
        $db->query("ALTER TABLE services ADD UNIQUE KEY `uk_hero_service_id` (`hero_service_id`)");
    }
} catch (Exception $e) {
    $schemaError = 'services 表结构自愈失败: ' . $e->getMessage();
}

// ==================== 同步服务列表 ====================
if (($_GET['action'] ?? '') === 'sync_all') {
    $heroSmsApiKey = KeyManager::getHeroSmsApiKey();
    if (empty($heroSmsApiKey)) {
        $error = "HeroSMS API key 未配置";
    } else {
        $heroSMS = new HeroSMS($heroSmsApiKey, HEROSMS_BASE_URL);
        $syncedServices = 0;
        $updatedServices = 0;

        // getServicesList 返回结构: [{code, name}, ...]
        $result = $heroSMS->getServicesList();
        if ($result['success'] && !empty($result['services'])) {
            foreach ($result['services'] as $s) {
                if (!is_array($s) || empty($s['code'])) continue;
                $code = (string)$s['code'];
                $name = $s['name'] ?? $code;
                $existing = $db->query("SELECT id, is_published FROM services WHERE hero_service_id = ?", [$code])->fetch();
                if ($existing) {
                    // 更新（保留 is_published 状态）
                    $db->query(
                        "UPDATE services SET name = ?, name_en = ?, name_cn = ?, code = ?, is_active = 1, updated_at = NOW() WHERE hero_service_id = ?",
                        [$name, $name, $name, $code, $code]
                    );
                    $updatedServices++;
                } else {
                    // 首次插入，默认上架
                    $db->insert('services', [
                        'hero_service_id' => $code,
                        'name' => $name,
                        'name_en' => $name,
                        'name_cn' => $name,
                        'code' => $code,
                        'is_published' => 1,
                        'is_active' => 1,
                        'sort_order' => 100,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                    $syncedServices++;
                }
            }
        }

        // 同步国家（用于 service_countries）
        $syncedCountries = 0;
        $updatedCountries = 0;
        $countriesResult = $heroSMS->getCountries();
        if (!empty($countriesResult['countries'])) {
            foreach ($countriesResult['countries'] as $id => $info) {
                if (!is_array($info)) continue;
                $heroCid = (string)($info['id'] ?? $id);
                $eng = $info['eng'] ?? '';
                $chn = $info['chn'] ?? '';
                $rus = $info['rus'] ?? '';
                $displayName = $chn ?: $eng ?: $rus ?: ('Country_' . $id);
                $code = strtolower(substr($eng, 0, 10));
                $existing = $db->query("SELECT id FROM countries WHERE hero_country_id = ?", [$heroCid])->fetch();
                if ($existing) {
                    $db->query(
                        "UPDATE countries SET name = ?, name_en = ?, name_cn = ?, code = ?, active = 1 WHERE hero_country_id = ?",
                        [$displayName, $eng, $chn, $code, $heroCid]
                    );
                    $updatedCountries++;
                } else {
                    $db->insert('countries', [
                        'hero_country_id' => $heroCid,
                        'name' => $displayName,
                        'name_en' => $eng,
                        'name_cn' => $chn,
                        'code' => $code,
                        'active' => 1,
                        'sort_order' => 0,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                    $syncedCountries++;
                }
            }
        }

        // 同步价格（service_countries）
        $syncedPrices = 0;
        $pricesResult = $heroSMS->getPrices();
        if (!empty($pricesResult['success']) && !empty($pricesResult['prices'])) {
            foreach ($pricesResult['prices'] as $countryHeroId => $serviceMap) {
                if (!is_array($serviceMap)) continue;
                foreach ($serviceMap as $serviceCode => $priceData) {
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

                    $svc = $db->query("SELECT id FROM services WHERE hero_service_id = ?", [(string)$serviceCode])->fetch();
                    $cty = $db->query("SELECT id FROM countries WHERE hero_country_id = ?", [(string)$countryHeroId])->fetch();
                    if (!$svc || !$cty) continue;

                    $exist = $db->query("SELECT id FROM service_countries WHERE service_id = ? AND country_id = ?", [$svc['id'], $cty['id']])->fetch();
                    if ($exist) {
                        $db->query("UPDATE service_countries SET price = ?, stock = ?, is_active = 1, updated_at = NOW() WHERE id = ?", [$cost, $count, $exist['id']]);
                    } else {
                        $db->insert('service_countries', [
                            'service_id' => $svc['id'],
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

        $success = "同步完成：新增服务 {$syncedServices}、更新 {$updatedServices}；新增国家 {$syncedCountries}、更新 {$updatedCountries}；新增价格 {$syncedPrices}";
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

    switch ($action) {
        case 'batch_publish':
            try {
                $db->query("UPDATE services SET is_published = 1 WHERE id IN ($idList)");
                echo json_encode(['success' => true, 'count' => count($ids), 'msg' => '已上架']);
            } catch (Throwable $e) {
                echo json_encode(['success' => false, 'error' => '上架失败：' . $e->getMessage()]);
            }
            exit;
        case 'batch_unpublish':
            try {
                $db->query("UPDATE services SET is_published = 0 WHERE id IN ($idList)");
                echo json_encode(['success' => true, 'count' => count($ids), 'msg' => '已下架']);
            } catch (Throwable $e) {
                echo json_encode(['success' => false, 'error' => '下架失败：' . $e->getMessage()]);
            }
            exit;
        case 'batch_pin':
            try {
                $db->query("UPDATE services SET is_pinned = 1 WHERE id IN ($idList)");
                echo json_encode(['success' => true, 'count' => count($ids), 'msg' => '已置顶']);
            } catch (Throwable $e) {
                echo json_encode(['success' => false, 'error' => '置顶失败：' . $e->getMessage()]);
            }
            exit;
        case 'batch_unpin':
            try {
                $db->query("UPDATE services SET is_pinned = 0 WHERE id IN ($idList)");
                echo json_encode(['success' => true, 'count' => count($ids), 'msg' => '已取消置顶']);
            } catch (Throwable $e) {
                echo json_encode(['success' => false, 'error' => '取消置顶失败：' . $e->getMessage()]);
            }
            exit;
        case 'batch_set_tag':
            $tag = intval($_POST['tag'] ?? 0);
            try {
                $db->query("UPDATE services SET tag = ? WHERE id IN ($idList)", [$tag]);
                echo json_encode(['success' => true, 'count' => count($ids), 'msg' => "已设标签={$tag}"]);
            } catch (Throwable $e) {
                echo json_encode(['success' => false, 'error' => '标签设置失败：' . $e->getMessage()]);
            }
            exit;
        case 'batch_set_coefficient':
            $coef = floatval($_POST['coefficient'] ?? 0);
            try {
                $db->query("UPDATE system_settings SET value = ? WHERE `key` = 'default_coefficient_after'", [(string)$coef]);
                echo json_encode(['success' => true, 'msg' => "全局系数已设为 {$coef}"]);
            } catch (Throwable $e) {
                echo json_encode(['success' => false, 'error' => '系数更新失败：' . $e->getMessage()]);
            }
            exit;
        case 'toggle_publish':
            $id = intval($_POST['id']);
            try {
                $cur = $db->query("SELECT is_published FROM services WHERE id = ?", [$id])->fetch();
                if ($cur) {
                    $new = $cur['is_published'] ? 0 : 1;
                    $db->query("UPDATE services SET is_published = ? WHERE id = ?", [$new, $id]);
                    echo json_encode(['success' => true, 'is_published' => $new]);
                } else {
                    echo json_encode(['success' => false, 'error' => '服务不存在']);
                }
            } catch (Throwable $e) {
                echo json_encode(['success' => false, 'error' => '操作失败：' . $e->getMessage()]);
            }
            exit;
        case 'update_service':
            $id = intval($_POST['id']);
            $name_cn = $_POST['name_cn'] ?? '';
            $name_en = $_POST['name_en'] ?? '';
            $sort_order = intval($_POST['sort_order'] ?? 0);
            $is_pinned = intval($_POST['is_pinned'] ?? 0);
            $tag = intval($_POST['tag'] ?? 0);
            try {
                $db->query(
                    "UPDATE services SET name_cn = ?, name_en = ?, sort_order = ?, is_pinned = ?, tag = ? WHERE id = ?",
                    [$name_cn, $name_en, $sort_order, $is_pinned, $tag, $id]
                );
                echo json_encode(['success' => true]);
            } catch (Throwable $e) {
                echo json_encode(['success' => false, 'error' => '更新失败：' . $e->getMessage()]);
            }
            exit;
        case 'delete_service':
            $id = intval($_POST['id']);
            try {
                $db->query("DELETE FROM service_countries WHERE service_id = ?", [$id]);
                $db->query("DELETE FROM services WHERE id = ?", [$id]);
                echo json_encode(['success' => true]);
            } catch (Throwable $e) {
                echo json_encode(['success' => false, 'error' => '删除失败：' . $e->getMessage()]);
            }
            exit;
        default:
            echo json_encode(['success' => false, 'error' => '未知操作: ' . $action]);
    }
    exit;
}

// ==================== 获取数据 ====================
// 筛选参数
$search = trim($_GET['q'] ?? '');
$filter = $_GET['filter'] ?? 'all'; // all / published / unpublished / pinned / hot / recommended
$tagFilter = intval($_GET['tag'] ?? -1);

// 分页参数
$page = max(1, intval($_GET['p'] ?? 1));
$limit = max(10, min(200, intval($_GET['limit'] ?? 20)));  // 默认 20/页, 范围 10-200
$offset = ($page - 1) * $limit;

$where = ['1=1'];
$params = [];
if ($search !== '') {
    $where[] = '(s.name LIKE ? OR s.name_cn LIKE ? OR s.name_en LIKE ? OR s.hero_service_id LIKE ? OR s.code LIKE ?)';
    $s = "%$search%";
    $params = array_merge($params, [$s, $s, $s, $s, $s]);
}
if ($filter === 'published') $where[] = 's.is_published = 1';
if ($filter === 'unpublished') $where[] = 's.is_published = 0';
if ($filter === 'pinned') $where[] = 's.is_pinned = 1';
if ($tagFilter >= 0) $where[] = 's.tag = ' . $tagFilter;

$whereSql = implode(' AND ', $where);

try {
    // 统计总数 (给分页用)
    $totalCount = intval($db->query("SELECT COUNT(*) FROM services s WHERE $whereSql", $params)->fetchColumn());

    // 总页数 + 防越界（必须先 clamp page，再算 offset）
    $totalPages = max(1, (int)ceil($totalCount / $limit));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $limit;

    // 分页查数据
    $services = $db->query(
        "SELECT s.*,
                (SELECT COUNT(*) FROM service_countries sc WHERE sc.service_id = s.id AND sc.is_published = 1) as published_countries,
                (SELECT COUNT(*) FROM service_countries sc WHERE sc.service_id = s.id) as total_countries
         FROM services s WHERE $whereSql
         ORDER BY s.is_pinned DESC, s.is_published DESC, s.tag DESC, s.sort_order ASC, s.id ASC
         LIMIT $limit OFFSET $offset",
        $params
    )->fetchAll();
    $queryError = null;
} catch (Exception $e) {
    $services = [];
    $totalCount = 0;
    $totalPages = 1;
    $page = 1;
    $offset = 0;
    $queryError = $e->getMessage();
}

// 统计(基于全表+过滤) - 全表计数更准确反映"已上架总数"
$total = $totalCount;
$totalPublished = intval($db->query("SELECT COUNT(*) FROM services s WHERE s.is_published = 1")->fetchColumn());
$totalPinned = intval($db->query("SELECT COUNT(*) FROM services s WHERE s.is_pinned = 1")->fetchColumn());
$totalTagHot = intval($db->query("SELECT COUNT(*) FROM services s WHERE s.tag = 1")->fetchColumn());
$totalTagRec = intval($db->query("SELECT COUNT(*) FROM services s WHERE s.tag = 2")->fetchColumn());

$msg = $_GET['msg'] ?? '';
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px;">
    <h4 style="margin:0;">⚙️ 服务配置</h4>
    <div style="display:flex;gap:12px;">
        <a href="?page=services&action=sync_all" onclick="return confirm('确定要从 HeroSMS 同步所有服务/国家/价格数据吗？\n\n- 已有服务会更新名称（保留上架/置顶状态）\n- 新增服务默认自动上架\n- 同步过程可能需要 1-2 分钟')" style="background:#10b981;color:white;padding:10px 20px;border-radius:8px;text-decoration:none;font-size:14px;font-weight:500;">🔄 同步 HeroSMS</a>
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
    <div style="margin-top:10px;font-size:13px;">请检查 services 表是否存在，以及 <code>is_pinned</code> / <code>tag</code> / <code>name_en</code> / <code>name_cn</code> 字段是否齐全。可在 SQL 编辑器执行：</div>
    <pre style="background:#1e293b;color:#e2e8f0;padding:12px;border-radius:6px;margin-top:8px;font-size:12px;overflow-x:auto;">ALTER TABLE services
  ADD COLUMN is_pinned tinyint DEFAULT 0 AFTER sort_order,
  ADD COLUMN tag tinyint DEFAULT 0 AFTER is_pinned,
  ADD COLUMN name_en varchar(255) DEFAULT NULL AFTER name,
  ADD COLUMN name_cn varchar(255) DEFAULT NULL AFTER name_en;</pre>
</div>
<?php endif; ?>
<?php if($msg): ?>
<div style="background:#d1fae5;color:#065f46;padding:14px 20px;border-radius:8px;margin-bottom:20px;font-size:14px;">✓ <?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:repeat(5,1fr);gap:12px;margin-bottom:16px;">
    <div class="card" style="padding:14px;text-align:center;">
        <div style="font-size:12px;color:#64748b;">总数</div>
        <div style="font-size:24px;font-weight:700;color:#0f172a;"><?= $total ?></div>
    </div>
    <div class="card" style="padding:14px;text-align:center;">
        <div style="font-size:12px;color:#64748b;">已上架</div>
        <div style="font-size:24px;font-weight:700;color:#10b981;"><?= $totalPublished ?></div>
    </div>
    <div class="card" style="padding:14px;text-align:center;">
        <div style="font-size:12px;color:#64748b;">置顶</div>
        <div style="font-size:24px;font-weight:700;color:#f59e0b;"><?= $totalPinned ?></div>
    </div>
    <div class="card" style="padding:14px;text-align:center;">
        <div style="font-size:12px;color:#ef4444;">🔥 热门</div>
        <div style="font-size:24px;font-weight:700;color:#ef4444;"><?= $totalTagHot ?></div>
    </div>
    <div class="card" style="padding:14px;text-align:center;">
        <div style="font-size:12px;color:#f59e0b;">⭐ 推荐</div>
        <div style="font-size:24px;font-weight:700;color:#f59e0b;"><?= $totalTagRec ?></div>
    </div>
</div>

<div class="card" style="padding:12px 16px;margin-bottom:12px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
    <input type="text" id="searchInput" placeholder="🔍 搜索服务名/HeroID/code..." value="<?= htmlspecialchars($search) ?>" style="flex:1;min-width:200px;padding:8px 12px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;">
    <select id="filterSelect" onchange="applyFilter()" style="padding:8px 12px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;">
        <option value="all" <?= $filter==='all'?'selected':'' ?>>全部状态</option>
        <option value="published" <?= $filter==='published'?'selected':'' ?>>已上架</option>
        <option value="unpublished" <?= $filter==='unpublished'?'selected':'' ?>>未上架</option>
        <option value="pinned" <?= $filter==='pinned'?'selected':'' ?>>已置顶</option>
    </select>
    <select id="tagSelect" onchange="applyFilter()" style="padding:8px 12px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;">
        <option value="-1" <?= $tagFilter===-1?'selected':'' ?>>全部标签</option>
        <option value="0" <?= $tagFilter===0?'selected':'' ?>>-无标签-</option>
        <option value="1" <?= $tagFilter===1?'selected':'' ?>>🔥 热门</option>
        <option value="2" <?= $tagFilter===2?'selected':'' ?>>⭐ 推荐</option>
    </select>
</div>

<div class="card">
    <div style="padding:16px 20px;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
        <div style="display:flex;align-items:center;gap:16px;">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:14px;">
                <input type="checkbox" id="selectAll" onchange="toggleAll(this.checked)" style="transform:scale(1.2);">
                全选
            </label>
            <span style="font-weight:600;color:#0f172a;">服务列表</span>
            <span id="selectedCount" style="display:none;margin-left:8px;padding:2px 10px;background:#fef3c7;color:#92400e;border-radius:12px;font-size:13px;font-weight:500;">
                已选 <strong id="selectedNum">0</strong> 项
            </span>
        </div>
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
            <button type="button" onclick="batchAction('batch_publish')" style="background:#10b981;color:white;border:none;padding:8px 14px;border-radius:8px;cursor:pointer;font-size:13px;font-weight:500;">✅ 批量上架</button>
            <button type="button" onclick="batchAction('batch_unpublish')" style="background:#94a3b8;color:white;border:none;padding:8px 14px;border-radius:8px;cursor:pointer;font-size:13px;font-weight:500;">⏸ 批量下架</button>
            <button type="button" onclick="batchAction('batch_pin')" style="background:#f59e0b;color:white;border:none;padding:8px 14px;border-radius:8px;cursor:pointer;font-size:13px;font-weight:500;">📌 置顶</button>
            <button type="button" onclick="batchAction('batch_unpin')" style="background:#cbd5e1;color:#475569;border:none;padding:8px 14px;border-radius:8px;cursor:pointer;font-size:13px;font-weight:500;">取消置顶</button>
            <button type="button" onclick="setTagToSelected(1)" style="background:#ef4444;color:white;border:none;padding:8px 14px;border-radius:8px;cursor:pointer;font-size:13px;font-weight:500;">🔥 标热门</button>
            <button type="button" onclick="setTagToSelected(2)" style="background:#f59e0b;color:white;border:none;padding:8px 14px;border-radius:8px;cursor:pointer;font-size:13px;font-weight:500;">⭐ 标推荐</button>
            <button type="button" onclick="setTagToSelected(0)" style="background:#e2e8f0;color:#475569;border:none;padding:8px 14px;border-radius:8px;cursor:pointer;font-size:13px;font-weight:500;">清标签</button>
        </div>
    </div>
    <table>
        <thead>
            <tr>
                <th style="width:40px;"><input type="checkbox" id="selectAllHeader" onchange="toggleAll(this.checked)" style="transform:scale(1.2);"></th>
                <th style="width:50px;">ID</th>
                <th style="width:60px;">排序</th>
                <th>HeroID/code</th>
                <th>名称(英)</th>
                <th>名称(中)</th>
                <th style="text-align:center;">国家</th>
                <th style="text-align:center;">置顶</th>
                <th style="text-align:center;">标签</th>
                <th style="text-align:center;">状态</th>
                <th style="text-align:center;">操作</th>
            </tr>
        </thead>
        <tbody>
            <?php if(empty($services)): ?>
            <tr><td colspan="11" style="text-align:center;color:#64748b;padding:60px;">
                <div style="font-size:48px;margin-bottom:16px;">📦</div>
                <div style="margin-bottom:12px;">暂无服务数据</div>
                <div style="font-size:13px;">点击右上角「同步 HeroSMS」获取服务列表</div>
            </td></tr>
            <?php else: foreach($services as $s): ?>
            <?php
            // HeroSMS 服务图标：远端 https://cdn.hero-sms.com/assets/img/service/{id}0.webp
            // 本地 pic/fuwu/{id}0.webp，三级降级：CDN → 本地 → 文字
            $svcId = $s['hero_service_id'] ?? '';
            $svcIconCdn  = $svcId ? "https://cdn.hero-sms.com/assets/img/service/{$svcId}0.webp" : '';
            $svcIconPath = $svcId ? "../../pic/fuwu/{$svcId}0.webp" : '';
            $svcInitial  = strtoupper(substr($s['name_en'] ?? $s['name'] ?? $svcId, 0, 2));
            ?>
            <tr style="<?= !$s['is_published'] ? 'opacity:0.5;' : '' ?><?= $s['is_pinned'] ? 'background:#fffbeb;' : '' ?>" data-id="<?= $s['id'] ?>">
                <td><input type="checkbox" class="row-cb" value="<?= $s['id'] ?>" onchange="updateCount()"></td>
                <td style="color:#64748b;font-size:12px;"><?= $s['id'] ?></td>
                <td><input type="number" class="field-input" data-field="sort_order" data-id="<?= $s['id'] ?>" value="<?= $s['sort_order'] ?: 100 ?>" style="width:50px;padding:4px 6px;border:1px solid #e2e8f0;border-radius:4px;font-size:12px;text-align:center;"></td>
                <td>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <img src="<?= htmlspecialchars($svcIconCdn) ?>"
                             onerror="if(!this.dataset.fallback){this.dataset.fallback='1';this.src='<?= htmlspecialchars($svcIconPath) ?>';}else{this.onerror=null;this.outerHTML='<div style=\'width:32px;height:32px;border-radius:6px;background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;flex-shrink:0;\'><?= htmlspecialchars($svcInitial) ?></div>';}"
                             style="width:32px;height:32px;border-radius:6px;object-fit:contain;background:#f8fafc;flex-shrink:0;"
                             loading="lazy"
                             title="<?= htmlspecialchars($s['name_en'] ?? $svcId) ?>">
                        <small style="color:#64748b;background:#f1f5f9;padding:2px 6px;border-radius:4px;font-family:monospace;"><?= htmlspecialchars($svcId) ?></small>
                    </div>
                </td>
                <td><input type="text" class="field-input" data-field="name_en" data-id="<?= $s['id'] ?>" value="<?= htmlspecialchars($s['name_en'] ?? $s['name'] ?? '') ?>" style="width:140px;padding:4px 8px;border:1px solid #e2e8f0;border-radius:6px;font-size:13px;"></td>
                <td><input type="text" class="field-input" data-field="name_cn" data-id="<?= $s['id'] ?>" value="<?= htmlspecialchars($s['name_cn'] ?? '') ?>" style="width:120px;padding:4px 8px;border:1px solid #e2e8f0;border-radius:6px;font-size:13px;" placeholder="中文名"></td>
                <td style="text-align:center;">
                    <span style="color:#10b981;font-weight:600;"><?= $s['published_countries'] ?></span>
                    <span style="color:#cbd5e1;">/</span>
                    <span style="color:#64748b;"><?= $s['total_countries'] ?></span>
                </td>
                <td style="text-align:center;">
                    <input type="checkbox" class="field-toggle" data-field="is_pinned" data-id="<?= $s['id'] ?>" <?= $s['is_pinned'] ? 'checked' : '' ?> style="transform:scale(1.2);">
                </td>
                <td style="text-align:center;">
                    <select class="field-input" data-field="tag" data-id="<?= $s['id'] ?>" style="padding:4px;border:1px solid #e2e8f0;border-radius:6px;font-size:12px;">
                        <option value="0" <?= $s['tag']==0?'selected':'' ?>>-</option>
                        <option value="1" <?= $s['tag']==1?'selected':'' ?> style="color:#ef4444;">🔥</option>
                        <option value="2" <?= $s['tag']==2?'selected':'' ?> style="color:#f59e0b;">⭐</option>
                    </select>
                </td>
                <td style="text-align:center;">
                    <?php if($s['is_published']): ?>
                        <span style="background:#d1fae5;color:#065f46;padding:3px 10px;border-radius:12px;font-size:11px;font-weight:600;">已上架</span>
                    <?php else: ?>
                        <span style="background:#f1f5f9;color:#64748b;padding:3px 10px;border-radius:12px;font-size:11px;">已下架</span>
                    <?php endif; ?>
                </td>
                <td style="text-align:center;white-space:nowrap;">
                    <a href="javascript:void(0)" onclick="togglePublish(<?= $s['id'] ?>)"
                       style="padding:4px 10px;border-radius:6px;text-decoration:none;font-size:12px;font-weight:500;<?= $s['is_published'] ? 'background:#fef3c7;color:#92400e;' : 'background:#10b981;color:white;' ?>">
                        <?= $s['is_published'] ? '⏸ 下架' : '▶ 上架' ?>
                    </a>
                    <a href="?page=service_countries&service_id=<?= $s['id'] ?>"
                       style="margin-left:2px;padding:4px 10px;background:#e0e7ff;color:#4f46e5;border-radius:6px;text-decoration:none;font-size:12px;font-weight:500;">
                        🌍 国家
                    </a>
                </td>
            </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>

    <?php
    // 分页 UI
    $baseParams = ['page' => 'services'];
    foreach (['q', 'filter', 'tag'] as $k) {
        if (isset($_GET[$k]) && $_GET[$k] !== '' && !($k === 'tag' && $_GET[$k] === '-1') && !($k === 'filter' && $_GET[$k] === 'all')) {
            $baseParams[$k] = $_GET[$k];
        }
    }
    $pageUrl = function($p) use ($baseParams, $limit) {
        $params = $baseParams;
        $params['p'] = $p;
        $params['limit'] = $limit;
        return '?' . http_build_query($params);
    };
    // 页码范围: 当前页 ± 2
    $pageStart = max(1, $page - 2);
    $pageEnd = min($totalPages, $page + 2);
    ?>
    <div style="padding:14px 20px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;border-top:1px solid #e2e8f0;">
        <div style="font-size:13px;color:#64748b;">
            共 <strong style="color:#0f172a;"><?= $totalCount ?></strong> 条
            <span style="margin-left:8px;">第 <strong style="color:#0f172a;"><?= $page ?></strong> / <?= $totalPages ?> 页</span>
        </div>
        <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
            <label style="font-size:13px;color:#64748b;">每页</label>
            <select onchange="location.href=this.value" style="padding:4px 8px;border:1px solid #e2e8f0;border-radius:6px;font-size:13px;">
                <?php foreach ([10, 20, 50, 100] as $opt): ?>
                <option value="<?= $pageUrl(1) ?>&limit=<?= $opt ?>" <?= $limit == $opt ? 'selected' : '' ?>><?= $opt ?></option>
                <?php endforeach; ?>
            </select>

            <?php if ($page > 1): ?>
            <a href="<?= $pageUrl(1) ?>" style="padding:4px 10px;background:#f1f5f9;color:#475569;border-radius:6px;text-decoration:none;font-size:13px;">« 首页</a>
            <a href="<?= $pageUrl($page - 1) ?>" style="padding:4px 10px;background:#f1f5f9;color:#475569;border-radius:6px;text-decoration:none;font-size:13px;">‹ 上一页</a>
            <?php endif; ?>

            <?php for ($p = $pageStart; $p <= $pageEnd; $p++): ?>
            <a href="<?= $pageUrl($p) ?>" style="padding:4px 10px;border-radius:6px;text-decoration:none;font-size:13px;<?= $p == $page ? 'background:#6366f1;color:white;font-weight:600;' : 'background:#f8fafc;color:#475569;' ?>"><?= $p ?></a>
            <?php endfor; ?>

            <?php if ($page < $totalPages): ?>
            <a href="<?= $pageUrl($page + 1) ?>" style="padding:4px 10px;background:#f1f5f9;color:#475569;border-radius:6px;text-decoration:none;font-size:13px;">下一页 ›</a>
            <a href="<?= $pageUrl($totalPages) ?>" style="padding:4px 10px;background:#f1f5f9;color:#475569;border-radius:6px;text-decoration:none;font-size:13px;">末页 »</a>
            <?php endif; ?>

            <span style="font-size:12px;color:#94a3b8;margin-left:4px;">跳到</span>
            <input type="number" min="1" max="<?= $totalPages ?>" value="<?= $page ?>" onchange="var v=parseInt(this.value||1,10);if(v>=1&&v<=<?= $totalPages ?>){var url=location.href;if(/[?&]p=/.test(url)){url=url.replace(/([?&])p=[^&]*/,'$1p='+v);}else{url+=(url.indexOf('?')>=0?'&':'?')+'p='+v;}location.href=url;}" style="width:50px;padding:4px 6px;border:1px solid #e2e8f0;border-radius:6px;font-size:13px;text-align:center;">
        </div>
    </div>
</div>

<div style="margin-top:20px;padding:16px;background:#fffbeb;border:1px solid #fde68a;border-radius:12px;">
    <div style="font-weight:600;color:#92400e;margin-bottom:8px;">💡 使用说明</div>
    <ul style="margin:0;padding-left:20px;color:#78350f;font-size:13px;line-height:1.8;">
        <li>「同步 HeroSMS」从上游 API 拉取所有服务/国家/价格数据保存到本地（首次需要运行）</li>
        <li>服务支持<strong>上架/下架</strong>：下架后客户端不展示，可保留价格数据备用</li>
        <li><strong>置顶</strong>的服务会优先展示在客户端首页</li>
        <li>标签<strong>🔥 热门</strong> / <strong>⭐ 推荐</strong>用于客户端做运营标记</li>
        <li>行内修改「排序/英文名/中文名/置顶/标签」失焦后自动保存</li>
        <li>点击「🌍 国家」管理该服务在哪些国家可见及其价格系数</li>
    </ul>
</div>

<script>
function toggleAll(checked) {
    document.querySelectorAll('.row-cb').forEach(cb => cb.checked = checked);
    document.getElementById('selectAll').checked = checked;
    document.getElementById('selectAllHeader').checked = checked;
    updateCount();
}
function updateCount() {
    const checked = document.querySelectorAll('.row-cb:checked');
    const el = document.getElementById('selectedCount');
    document.getElementById('selectedNum').textContent = checked.length;
    el.style.display = checked.length > 0 ? 'inline' : 'none';
}
function getSelectedIds() {
    return Array.from(document.querySelectorAll('.row-cb:checked')).map(cb => cb.value).join(',');
}
function batchAction(action) {
    const ids = getSelectedIds();
    if (!ids) { alert('请先勾选服务'); return; }
    if (!confirm('确定要执行批量操作吗？')) return;
    sendAjax({action, ids});
}
function setTagToSelected(tag) {
    const ids = getSelectedIds();
    if (!ids) { alert('请先勾选服务'); return; }
    sendAjax({action: 'batch_set_tag', ids, tag});
}
function togglePublish(id) {
    sendAjax({action: 'toggle_publish', id, ids: id});
}
function sendAjax(payload) {
    payload.ajax = 1;
    return fetch('?page=services', {
        method: 'POST',
        body: new URLSearchParams(payload),
        headers: {'Content-Type': 'application/x-www-form-urlencoded'}
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('操作失败: ' + (data.error || '未知'));
        }
    })
    .catch(err => alert('请求失败: ' + err.message));
}
function saveServiceField(id, field, value) {
    const formData = new URLSearchParams();
    formData.append('ajax', '1');
    formData.append('action', 'update_service');
    formData.append('id', id);
    formData.append(field, value);
    // 其他字段也带上
    formData.append('name_cn', document.querySelector(`.field-input[data-id="${id}"][data-field="name_cn"]`)?.value || '');
    formData.append('name_en', document.querySelector(`.field-input[data-id="${id}"][data-field="name_en"]`)?.value || '');
    formData.append('sort_order', document.querySelector(`.field-input[data-id="${id}"][data-field="sort_order"]`)?.value || 100);
    formData.append('is_pinned', document.querySelector(`.field-toggle[data-id="${id}"][data-field="is_pinned"]`)?.checked ? 1 : 0);
    formData.append('tag', document.querySelector(`.field-input[data-id="${id}"][data-field="tag"]`)?.value || 0);
    fetch('?page=services', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(d => {
            if (!d.success) alert('保存失败: ' + (d.error || ''));
        }).catch(() => {});
}
document.querySelectorAll('.field-input').forEach(el => {
    el.addEventListener('change', function() {
        saveServiceField(this.dataset.id, this.dataset.field, this.value);
    });
    el.addEventListener('blur', function() {
        saveServiceField(this.dataset.id, this.dataset.field, this.value);
    });
    el.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') e.target.blur();
    });
});
document.querySelectorAll('.field-toggle').forEach(el => {
    el.addEventListener('change', function() {
        saveServiceField(this.dataset.id, this.dataset.field, this.checked ? 1 : 0);
    });
});

// 搜索/筛选
let searchTimer = null;
document.getElementById('searchInput').addEventListener('input', function() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(applyFilter, 300);
});
function applyFilter() {
    const q = document.getElementById('searchInput').value.trim();
    const filter = document.getElementById('filterSelect').value;
    const tag = document.getElementById('tagSelect').value;
    const url = new URL(location.href);
    if (q) url.searchParams.set('q', q); else url.searchParams.delete('q');
    url.searchParams.set('filter', filter);
    url.searchParams.set('tag', tag);
    location.href = url.toString();
}
</script>
