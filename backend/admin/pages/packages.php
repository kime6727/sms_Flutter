<?php
/**
 * 充值套餐管理页面
 */

$message = '';
$error = '';

// 自愈:补齐 payment_configs 缺失字段(display_price / is_recommended)
// 兜底:把所有 NOT NULL 但没有默认值的字段加上 DEFAULT,避免 INSERT 时漏传崩溃
// 缓存文件 v2:更智能的兜底,覆盖更多 NOT NULL 字段
$schemaFix = __DIR__ . '/../_schema_fix_payment_configs_v3.json';
$needFix = true;
if (file_exists($schemaFix) && (time() - filemtime($schemaFix)) < 86400) {
    $needFix = false;
}
if ($needFix) {
    try {
        $colRows = $db->query("SHOW COLUMNS FROM payment_configs")->fetchAll();
        $colNames = array_map(function($r) { return $r['Field'] ?? $r[0] ?? ''; }, $colRows);

        // 1) 缺字段就加
        if (!in_array('display_price', $colNames, true)) {
            $db->query("ALTER TABLE `payment_configs` ADD COLUMN `display_price` DECIMAL(10,2) DEFAULT '0.00' COMMENT '参考价格(USD)' AFTER `credits`");
        }
        if (!in_array('is_recommended', $colNames, true)) {
            $db->query("ALTER TABLE `payment_configs` ADD COLUMN `is_recommended` TINYINT(1) DEFAULT '0' COMMENT '是否推荐' AFTER `description`");
            try { $db->query("ALTER TABLE `payment_configs` ADD KEY `idx_is_recommended` (`is_recommended`)"); } catch (Throwable $e2) {}
        }

        // 2) 兜底:任何 NOT NULL 且无默认值的字段,自动 MODIFY 加默认 ''
        //    (name / price / product_id / credits 都属于高风险列)
        $colType = [];
        foreach ($colRows as $r) {
            $field = $r['Field'] ?? $r[0] ?? '';
            $type = $r['Type'] ?? $r[1] ?? '';
            $nullMark = strtoupper($r['Null'] ?? $r[2] ?? 'YES');
            $defaultVal = $r['Default'] ?? $r[4] ?? null;
            $colType[$field] = ['type' => $type, 'null' => $nullMark, 'default' => $defaultVal];
        }

        foreach ($colType as $field => $info) {
            // MySQL SHOW COLUMNS 把"无默认值"显示为 NULL 或字符串 "NULL" 视 server 而定
            $hasDefault = ($info['default'] !== null && $info['default'] !== 'NULL' && $info['default'] !== '');
            if ($info['null'] === 'NO' && !$hasDefault) {
                $typeLower = strtolower($info['type']);
                if (strpos($typeLower, 'int') !== false || strpos($typeLower, 'decimal') !== false || strpos($typeLower, 'float') !== false || strpos($typeLower, 'double') !== false) {
                    $default = "'0'";
                } else {
                    $default = "''";
                }
                try {
                    $db->query("ALTER TABLE `payment_configs` MODIFY COLUMN `{$field}` {$info['type']} NOT NULL DEFAULT {$default}");
                    error_log("[packages.php schema fix] MODIFY {$field} DEFAULT {$default}");
                } catch (Throwable $e3) {
                    error_log("[packages.php schema fix] MODIFY {$field} failed: " . $e3->getMessage());
                }
            }
        }

        // 关键: id 字段必须是 AUTO_INCREMENT (没有它, INSERT 显式 id=0 与已有 id=0 冲突)
        $idInfo = $db->query("SHOW COLUMNS FROM payment_configs WHERE Field = 'id'")->fetch();
        if ($idInfo && stripos($idInfo['Extra'] ?? '', 'auto_increment') === false) {
            try {
                // id 已经是 PRIMARY KEY, 不能再加 PRIMARY KEY
                $db->query("ALTER TABLE `payment_configs` MODIFY COLUMN `id` INT(11) NOT NULL AUTO_INCREMENT");
                error_log("[packages.php schema fix] MODIFY id AUTO_INCREMENT");
            } catch (Throwable $e3) {
                error_log("[packages.php schema fix] MODIFY id AUTO_INCREMENT failed: " . $e3->getMessage());
            }
        }

        @file_put_contents($schemaFix, json_encode(['fixed_at' => date('c')]));
    } catch (Throwable $e) {
        error_log('[packages.php schema fix] ' . $e->getMessage());
    }
}

// 处理POST请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $productId = trim($_POST['product_id'] ?? '');
        $configName = trim($_POST['config_name'] ?? '');
        $credits = intval($_POST['credits'] ?? 0);
        $displayPrice = floatval($_POST['display_price'] ?? 0);
        $price = floatval($_POST['price'] ?? $displayPrice);
        $description = trim($_POST['description'] ?? '');
        $isRecommended = isset($_POST['is_recommended']) ? 1 : 0;
        $active = isset($_POST['active']) ? 1 : 0;

        // product_id 是必填 (UI 不显示, 但 admin 提交要有,否则自动生成)
        if (!$productId) {
            $productId = 'simu_' . time() . '_' . substr(md5(uniqid()), 0, 6);
        }
        if (!$configName || $credits <= 0) {
            $error = '请填写必填字段（商品名称、积分）';
        } else {
            try {
                $exists = $db->query("SELECT id FROM payment_configs WHERE product_id = ?", [$productId])->fetch();
                if ($exists) {
                    $error = '产品ID已存在';
                } else {
                    $db->query(
                        "INSERT INTO payment_configs (product_id, name, config_name, price, credits, display_price, description, is_recommended, active, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                        [$productId, $configName, $configName, $price, $credits, $displayPrice, $description, $isRecommended, $active, date('Y-m-d H:i:s')]
                    );
                    $message = '套餐已创建：' . $configName;
                }
            } catch (Throwable $e) {
                $error = '创建失败：' . $e->getMessage();
                error_log('[packages.php create] ' . $e->getMessage());
            }
        }
    } elseif ($action === 'update') {
        $id = intval($_POST['id'] ?? 0);
        $configName = trim($_POST['config_name'] ?? '');
        $credits = intval($_POST['credits'] ?? 0);
        $displayPrice = floatval($_POST['display_price'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $isRecommended = isset($_POST['is_recommended']) ? 1 : 0;
        $active = isset($_POST['active']) ? 1 : 0;

        if (!$configName || $credits <= 0) {
            $error = '请填写必填字段';
        } else {
            try {
                $db->query(
                    "UPDATE payment_configs SET config_name = ?, credits = ?, display_price = ?, description = ?, is_recommended = ?, active = ? WHERE id = ?",
                    [$configName, $credits, $displayPrice, $description, $isRecommended, $active, $id]
                );
                $message = '套餐已更新';
            } catch (Throwable $e) {
                $error = '更新失败：' . $e->getMessage();
                error_log('[packages.php update] ' . $e->getMessage());
            }
        }
    } elseif ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        try {
            $db->query("DELETE FROM payment_configs WHERE id = ?", [$id]);
            $message = '套餐已删除';
        } catch (Throwable $e) {
            $error = '删除失败：' . $e->getMessage();
        }
    } elseif ($action === 'toggle_recommended') {
        $id = intval($_POST['id'] ?? 0);
        try {
            $db->query("UPDATE payment_configs SET is_recommended = NOT is_recommended WHERE id = ?", [$id]);
            $message = '推荐状态已切换';
        } catch (Throwable $e) {
            $error = '操作失败：' . $e->getMessage();
        }
    }
}

// 发生错误时，POST 后不直接 exit，而是重定向回 GET 页面，让 UI 看到 $error / $message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($message || $error)) {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_error'] = $error;
    header('Location: index.php?page=packages');
    exit;
}

// 获取所有套餐
$packages = $db->query("SELECT * FROM payment_configs ORDER BY credits ASC")->fetchAll();

// 从 session 取 flash
if (!empty($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
}
if (!empty($_SESSION['flash_error'])) {
    $error = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
    <h4 style="margin:0;">💎 充值套餐管理</h4>
    <button class="btn btn-primary" onclick="showCreateModal()">+ 添加套餐</button>
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
    <div style="background: #f8fafc; padding: 16px; border-radius: 8px; margin-bottom: 20px;">
        <p style="color: #64748b; font-size: 14px; margin: 0;">
            <strong>说明：</strong>在这里配置 AppStore 消耗型内购项目对应的充值套餐。<br>
            用户在 AppStore 购买成功后，系统会自动给用户充值对应数量的积分。<br>
            用户实际支付的价格由 Apple 的定价策略决定，这里只配置给用户多少积分。
        </p>
    </div>
    
    <div class="table-responsive">
    <table>
        <thead>
            <tr>
                <th>商品名称</th>
                <th>AppStore 产品ID</th>
                <th>充值积分</th>
                <th>参考价格</th>
                <th>推荐</th>
                <th>状态</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            <?php if(empty($packages)): ?>
            <tr>
                <td colspan="7" style="text-align:center;color:#64748b;padding:40px;">暂无套餐配置，请点击"添加套餐"创建</td>
            </tr>
            <?php else: ?>
            <?php foreach($packages as $pkg): ?>
            <tr>
                <td>
                    <div style="font-weight: 600;"><?= htmlspecialchars($pkg['config_name']) ?></div>
                    <?php if($pkg['description']): ?>
                    <div style="font-size: 12px; color: #64748b;"><?= htmlspecialchars($pkg['description']) ?></div>
                    <?php endif; ?>
                </td>
                <td>
                    <code style="background:#f1f5f9;padding:4px 8px;border-radius:4px;font-size:12px;"><?= htmlspecialchars($pkg['product_id']) ?></code>
                </td>
                <td>
                    <strong style="color:#6366f1;font-size:18px;"><?= number_format($pkg['credits']) ?></strong>
                    <span style="color:#64748b;font-size:12px;">积分</span>
                </td>
                <td>
                    <span style="color:#64748b;">$<?= number_format($pkg['display_price'] ?? 0, 2) ?></span>
                </td>
                <td>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="action" value="toggle_recommended">
                        <input type="hidden" name="id" value="<?= $pkg['id'] ?>">
                        <?php if($pkg['is_recommended']): ?>
                        <button type="submit" class="badge badge-warning" style="border:none;cursor:pointer;">⭐ 推荐</button>
                        <?php else: ?>
                        <button type="submit" class="badge badge-secondary" style="border:none;cursor:pointer;">未推荐</button>
                        <?php endif; ?>
                    </form>
                </td>
                <td>
                    <?php if($pkg['active']): ?>
                    <span class="badge badge-success">启用</span>
                    <?php else: ?>
                    <span class="badge badge-danger">禁用</span>
                    <?php endif; ?>
                </td>
                <td>
                    <button class="btn btn-primary btn-sm" onclick="showEditModal(<?= $pkg['id'] ?>, '<?= htmlspecialchars(addslashes($pkg['config_name'])) ?>', '<?= htmlspecialchars(addslashes($pkg['product_id'])) ?>', <?= $pkg['credits'] ?>, <?= $pkg['display_price'] ?? 0 ?>, '<?= htmlspecialchars(addslashes($pkg['description'] ?? '')) ?>', <?= $pkg['is_recommended'] ? 'true' : 'false' ?>, <?= $pkg['active'] ? 'true' : 'false' ?>)">编辑</button>
                    <button class="btn btn-sm" style="background:#fee2e2;color:#991b1b;" onclick="confirmDelete(<?= $pkg['id'] ?>, '<?= htmlspecialchars(addslashes($pkg['config_name'])) ?>')">删除</button>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    </div>
</div>

<!-- 创建套餐弹窗 -->
<div id="createModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div style="background: white; border-radius: 16px; padding: 24px; width: 500px; max-width: 90%; max-height: 90vh; overflow-y: auto;">
        <h5 style="margin: 0 0 20px 0;">💎 添加充值套餐</h5>
        <form method="POST">
            <input type="hidden" name="action" value="create">
            <div style="margin-bottom: 16px;">
                <label style="display: block; font-size: 13px; color: #64748b; margin-bottom: 6px;">AppStore 产品ID <span style="color:#ef4444;">*</span></label>
                <input type="text" name="product_id" required placeholder="例如: com.smsreceiver.points.100"
                       style="padding: 10px 14px; border: 1px solid #e2e8f0; border-radius: 8px; width: 100%; font-size: 14px;">
                <small style="color:#94a3b8;">必须与 AppStore Connect 中配置的消耗型产品ID完全一致</small>
            </div>
            <div style="margin-bottom: 16px;">
                <label style="display: block; font-size: 13px; color: #64748b; margin-bottom: 6px;">商品名称 <span style="color:#ef4444;">*</span></label>
                <input type="text" name="config_name" required placeholder="例如: 100积分"
                       style="padding: 10px 14px; border: 1px solid #e2e8f0; border-radius: 8px; width: 100%; font-size: 14px;">
            </div>
            <div style="display: flex; gap: 16px; margin-bottom: 16px;">
                <div style="flex: 1;">
                    <label style="display: block; font-size: 13px; color: #64748b; margin-bottom: 6px;">充值积分 <span style="color:#ef4444;">*</span></label>
                    <input type="number" name="credits" required min="1" placeholder="100"
                           style="padding: 10px 14px; border: 1px solid #e2e8f0; border-radius: 8px; width: 100%; font-size: 16px;">
                    <small style="color:#94a3b8;">购买成功后用户获得的积分数量</small>
                </div>
                <div style="flex: 1;">
                    <label style="display: block; font-size: 13px; color: #64748b; margin-bottom: 6px;">参考价格 (USD)</label>
                    <input type="number" name="display_price" step="0.01" min="0" placeholder="0.99"
                           style="padding: 10px 14px; border: 1px solid #e2e8f0; border-radius: 8px; width: 100%; font-size: 14px;">
                    <small style="color:#94a3b8;">仅供参考，实际价格由 Apple 决定</small>
                </div>
            </div>
            <div style="margin-bottom: 16px;">
                <label style="display: block; font-size: 13px; color: #64748b; margin-bottom: 6px;">描述</label>
                <textarea name="description" rows="2" placeholder="套餐描述（可选）"
                          style="padding: 10px 14px; border: 1px solid #e2e8f0; border-radius: 8px; width: 100%; font-size: 14px; resize: vertical;"></textarea>
            </div>
            <div style="display: flex; gap: 20px; margin-bottom: 20px;">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" name="is_recommended"> 设为推荐套餐
                </label>
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" name="active" checked> 启用
                </label>
            </div>
            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                <button type="button" onclick="closeCreateModal()" class="btn" style="background: #e2e8f0; color: #64748b;">取消</button>
                <button type="submit" class="btn btn-primary">创建</button>
            </div>
        </form>
    </div>
</div>

<!-- 编辑套餐弹窗 -->
<div id="editModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div style="background: white; border-radius: 16px; padding: 24px; width: 500px; max-width: 90%; max-height: 90vh; overflow-y: auto;">
        <h5 style="margin: 0 0 20px 0;">💎 编辑充值套餐</h5>
        <form method="POST">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="editId">
            <div style="margin-bottom: 16px;">
                <label style="display: block; font-size: 13px; color: #64748b; margin-bottom: 6px;">AppStore 产品ID</label>
                <input type="text" id="editProductId" disabled
                       style="padding: 10px 14px; border: 1px solid #e2e8f0; border-radius: 8px; width: 100%; font-size: 14px; background: #f8fafc; color: #94a3b8;">
                <small style="color:#94a3b8;">产品ID不可修改</small>
            </div>
            <div style="margin-bottom: 16px;">
                <label style="display: block; font-size: 13px; color: #64748b; margin-bottom: 6px;">商品名称 <span style="color:#ef4444;">*</span></label>
                <input type="text" name="config_name" id="editConfigName" required
                       style="padding: 10px 14px; border: 1px solid #e2e8f0; border-radius: 8px; width: 100%; font-size: 14px;">
            </div>
            <div style="display: flex; gap: 16px; margin-bottom: 16px;">
                <div style="flex: 1;">
                    <label style="display: block; font-size: 13px; color: #64748b; margin-bottom: 6px;">充值积分 <span style="color:#ef4444;">*</span></label>
                    <input type="number" name="credits" id="editCredits" required min="1"
                           style="padding: 10px 14px; border: 1px solid #e2e8f0; border-radius: 8px; width: 100%; font-size: 16px;">
                </div>
                <div style="flex: 1;">
                    <label style="display: block; font-size: 13px; color: #64748b; margin-bottom: 6px;">参考价格 (USD)</label>
                    <input type="number" name="display_price" id="editDisplayPrice" step="0.01" min="0"
                           style="padding: 10px 14px; border: 1px solid #e2e8f0; border-radius: 8px; width: 100%; font-size: 14px;">
                </div>
            </div>
            <div style="margin-bottom: 16px;">
                <label style="display: block; font-size: 13px; color: #64748b; margin-bottom: 6px;">描述</label>
                <textarea name="description" id="editDescription" rows="2"
                          style="padding: 10px 14px; border: 1px solid #e2e8f0; border-radius: 8px; width: 100%; font-size: 14px; resize: vertical;"></textarea>
            </div>
            <div style="display: flex; gap: 20px; margin-bottom: 20px;">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" name="is_recommended" id="editIsRecommended"> 设为推荐套餐
                </label>
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" name="active" id="editActive"> 启用
                </label>
            </div>
            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                <button type="button" onclick="closeEditModal()" class="btn" style="background: #e2e8f0; color: #64748b;">取消</button>
                <button type="submit" class="btn btn-primary">保存</button>
            </div>
        </form>
    </div>
</div>

<!-- 删除确认弹窗 -->
<div id="deleteModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div style="background: white; border-radius: 16px; padding: 24px; width: 400px; max-width: 90%; text-align: center;">
        <h5 style="margin: 0 0 16px 0;">⚠️ 确认删除</h5>
        <p style="color: #64748b; margin-bottom: 24px;">确定要删除套餐 <strong id="deletePackageName"></strong> 吗？</p>
        <form method="POST">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" id="deleteId">
            <div style="display: flex; gap: 12px; justify-content: center;">
                <button type="button" onclick="closeDeleteModal()" class="btn" style="background: #e2e8f0; color: #64748b;">取消</button>
                <button type="submit" class="btn" style="background: #ef4444; color: white;">确认删除</button>
            </div>
        </form>
    </div>
</div>

<script>
function showCreateModal() {
    document.getElementById('createModal').style.display = 'flex';
}

function closeCreateModal() {
    document.getElementById('createModal').style.display = 'none';
}

function showEditModal(id, name, productId, credits, price, description, isRecommended, active) {
    document.getElementById('editId').value = id;
    document.getElementById('editConfigName').value = name;
    document.getElementById('editProductId').value = productId;
    document.getElementById('editCredits').value = credits;
    document.getElementById('editDisplayPrice').value = price;
    document.getElementById('editDescription').value = description;
    document.getElementById('editIsRecommended').checked = isRecommended;
    document.getElementById('editActive').checked = active;
    document.getElementById('editModal').style.display = 'flex';
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

function confirmDelete(id, name) {
    document.getElementById('deleteId').value = id;
    document.getElementById('deletePackageName').textContent = name;
    document.getElementById('deleteModal').style.display = 'flex';
}

function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
}

// 点击遮罩层关闭弹窗
['createModal', 'editModal', 'deleteModal'].forEach(function(id) {
    document.getElementById(id).addEventListener('click', function(e) {
        if (e.target === this) {
            this.style.display = 'none';
        }
    });
});
</script>
