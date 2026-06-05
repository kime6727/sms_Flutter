<?php
/**
 * Banner管理页面
 */

// 处理删除Banner
if (($_POST['action'] ?? '') === 'delete_banner') {
    $bannerId = $_POST['banner_id'] ?? null;
    
    if ($bannerId) {
        $db->query("DELETE FROM banners WHERE id = ?", [$bannerId]);
        header("Location: ?page=banners&msg=" . urlencode("Banner删除成功"));
        exit;
    }
}

// 处理切换启用状态
if (($_POST['action'] ?? '') === 'toggle_banner') {
    $bannerId = $_POST['banner_id'] ?? null;
    
    if ($bannerId) {
        $banner = $db->query("SELECT is_enabled FROM banners WHERE id = ?", [$bannerId])->fetch();
        if ($banner) {
            $newStatus = $banner['is_enabled'] ? 0 : 1;
            $db->query("UPDATE banners SET is_enabled = ? WHERE id = ?", [$newStatus, $bannerId]);
            
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'is_enabled' => $newStatus]);
            exit;
        }
    }
}

// 处理添加/编辑Banner
if (($_POST['action'] ?? '') === 'save_banner') {
    $bannerId = $_POST['banner_id'] ?? null;
    $name = trim($_POST['name'] ?? '');
    $imageUrl = trim($_POST['image_url'] ?? '');
    $linkUrl = trim($_POST['link_url'] ?? '');
    $sortOrder = intval($_POST['sort_order'] ?? 0);
    $isEnabled = isset($_POST['is_enabled']) ? 1 : 0;
    
    // 验证必填字段
    if (empty($name) || empty($imageUrl) || empty($linkUrl)) {
        header("Location: ?page=banners&error=" . urlencode("请填写所有必填字段"));
        exit;
    }
    
    // 处理图片上传
    if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../../uploads/banners/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $fileExt = strtolower(pathinfo($_FILES['image_file']['name'], PATHINFO_EXTENSION));
        $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (!in_array($fileExt, $allowedExts)) {
            header("Location: ?page=banners&error=" . urlencode("只支持JPG、PNG、GIF、WEBP格式的图片"));
            exit;
        }
        
        $fileName = 'banner_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $fileExt;
        $filePath = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['image_file']['tmp_name'], $filePath)) {
            $imageUrl = '/uploads/banners/' . $fileName;
        }
    }
    
    if ($bannerId) {
        // 编辑
        $db->query(
            "UPDATE banners SET `name` = ?, `image_url` = ?, `link_url` = ?, `sort_order` = ?, `is_enabled` = ?, `updated_at` = NOW() WHERE id = ?",
            [$name, $imageUrl, $linkUrl, $sortOrder, $isEnabled, $bannerId]
        );
        $message = "Banner更新成功";
    } else {
        // 添加
        $db->insert('banners', [
            'name' => $name,
            'image_url' => $imageUrl,
            'link_url' => $linkUrl,
            'sort_order' => $sortOrder,
            'is_enabled' => $isEnabled,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        $message = "Banner添加成功";
    }
    
    header("Location: ?page=banners&msg=" . urlencode($message));
    exit;
}

// 获取所有Banner
$banners = $db->query("SELECT * FROM banners ORDER BY sort_order ASC, created_at DESC")->fetchAll();

// 获取编辑的Banner
$editBanner = null;
if (!empty($_GET['edit'])) {
    $editBanner = $db->query("SELECT * FROM banners WHERE id = ?", [$_GET['edit']])->fetch();
}

// 显示模式：列表或表单
$showForm = !empty($_GET['edit']) || ($_GET['action'] ?? '') === 'add';
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
    <h4 style="margin:0;">🎨 Banner管理</h4>
    <?php if (!$showForm): ?>
    <a href="?page=banners&action=add" style="background:#6366f1;color:white;padding:10px 20px;border-radius:8px;text-decoration:none;font-size:14px;font-weight:500;">+ 添加Banner</a>
    <?php endif; ?>
</div>

<?php if(isset($_GET['msg'])): ?>
<div style="background:#d1fae5;color:#065f46;padding:14px 20px;border-radius:8px;margin-bottom:20px;font-size:14px;">✓ <?= htmlspecialchars($_GET['msg']) ?></div>
<?php endif; ?>
<?php if(isset($_GET['error'])): ?>
<div style="background:#fee2e2;color:#991b1b;padding:14px 20px;border-radius:8px;margin-bottom:20px;font-size:14px;">✗ <?= htmlspecialchars($_GET['error']) ?></div>
<?php endif; ?>

<?php if ($showForm): ?>
<!-- 添加/编辑表单 -->
<div class="card">
    <div style="padding:20px;border-bottom:1px solid #e2e8f0;">
        <h5 style="margin:0;"><?= $editBanner ? '编辑Banner' : '添加Banner' ?></h5>
    </div>
    <div style="padding:20px;">
        <form method="POST" enctype="multipart/form-data" style="max-width:600px;">
            <input type="hidden" name="action" value="save_banner">
            <input type="hidden" name="banner_id" value="<?= $editBanner['id'] ?? '' ?>">
            
            <div style="margin-bottom:16px;">
                <label style="display:block;margin-bottom:6px;color:#64748b;font-size:14px;">Banner名称 <span style="color:#ef4444;">*</span></label>
                <input type="text" name="name" value="<?= htmlspecialchars($editBanner['name'] ?? '') ?>" required style="width:100%;padding:10px 14px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;">
            </div>
            
            <div style="margin-bottom:16px;">
                <label style="display:block;margin-bottom:6px;color:#64748b;font-size:14px;">上传图片</label>
                <input type="file" name="image_file" accept="image/*" style="width:100%;padding:10px 14px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;">
                <small style="color:#94a3b8;">支持JPG、PNG、GIF、WEBP格式，建议尺寸：750x300px</small>
            </div>
            
            <div style="margin-bottom:16px;">
                <label style="display:block;margin-bottom:6px;color:#64748b;font-size:14px;">或填写图片URL <span style="color:#ef4444;">*</span></label>
                <input type="url" name="image_url" value="<?= htmlspecialchars($editBanner['image_url'] ?? '') ?>" placeholder="https://example.com/banner.jpg" style="width:100%;padding:10px 14px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;">
                <small style="color:#94a3b8;">如果上传了图片，此字段会被覆盖</small>
            </div>
            
            <div style="margin-bottom:16px;">
                <label style="display:block;margin-bottom:6px;color:#64748b;font-size:14px;">跳转URL <span style="color:#ef4444;">*</span></label>
                <input type="url" name="link_url" value="<?= htmlspecialchars($editBanner['link_url'] ?? '') ?>" required placeholder="https://example.com" style="width:100%;padding:10px 14px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;">
            </div>
            
            <div style="margin-bottom:16px;">
                <label style="display:block;margin-bottom:6px;color:#64748b;font-size:14px;">显示顺序</label>
                <input type="number" name="sort_order" value="<?= $editBanner['sort_order'] ?? 0 ?>" min="0" style="width:100%;padding:10px 14px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;">
                <small style="color:#94a3b8;">数字越小越靠前</small>
            </div>
            
            <div style="margin-bottom:20px;">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                    <input type="checkbox" name="is_enabled" <?= ($editBanner['is_enabled'] ?? 1) ? 'checked' : '' ?> style="transform:scale(1.2);">
                    <span style="color:#64748b;font-size:14px;">启用</span>
                </label>
            </div>
            
            <div style="display:flex;gap:12px;">
                <button type="submit" style="background:#6366f1;color:white;border:none;padding:10px 24px;border-radius:8px;cursor:pointer;font-size:14px;font-weight:500;">💾 保存</button>
                <a href="?page=banners" style="background:#f1f5f9;color:#64748b;padding:10px 24px;border-radius:8px;text-decoration:none;font-size:14px;font-weight:500;">取消</a>
            </div>
        </form>
    </div>
</div>
<?php else: ?>
<!-- Banner列表 -->
<div class="card">
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>预览</th>
                    <th>名称</th>
                    <th>跳转URL</th>
                    <th>排序</th>
                    <th>状态</th>
                    <th>创建时间</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($banners)): ?>
                <tr>
                    <td colspan="8" style="text-align:center;color:#64748b;padding:60px;">
                        <div style="font-size:48px;margin-bottom:16px;">🎨</div>
                        <div style="margin-bottom:12px;">暂无Banner</div>
                        <div style="font-size:13px;">点击「添加Banner」创建第一个Banner</div>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($banners as $banner): ?>
                <tr>
                    <td><?= $banner['id'] ?></td>
                    <td>
                        <img src="<?= htmlspecialchars($banner['image_url']) ?>" 
                             alt="<?= htmlspecialchars($banner['name']) ?>" 
                             style="max-width:120px;max-height:60px;object-fit:cover;border-radius:4px;">
                    </td>
                    <td><?= htmlspecialchars($banner['name']) ?></td>
                    <td>
                        <small style="color:#64748b;word-break:break-all;">
                            <?= htmlspecialchars($banner['link_url']) ?>
                        </small>
                    </td>
                    <td><?= $banner['sort_order'] ?></td>
                    <td>
                        <form method="POST" style="display:inline;" class="toggle-form">
                            <input type="hidden" name="action" value="toggle_banner">
                            <input type="hidden" name="banner_id" value="<?= $banner['id'] ?>">
                            <button type="submit" class="toggle-btn" style="padding:4px 12px;border-radius:20px;border:none;cursor:pointer;font-size:12px;font-weight:600;<?= $banner['is_enabled'] ? 'background:#d1fae5;color:#065f46;' : 'background:#f3f4f6;color:#6b7280;' ?>">
                                <?= $banner['is_enabled'] ? '启用' : '禁用' ?>
                            </button>
                        </form>
                    </td>
                    <td><?= $banner['created_at'] ?></td>
                    <td>
                        <a href="?page=banners&edit=<?= $banner['id'] ?>" style="background:#dbeafe;color:#1e40af;padding:4px 10px;border-radius:6px;text-decoration:none;font-size:12px;font-weight:500;">编辑</a>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('确定要删除这个Banner吗？');">
                            <input type="hidden" name="action" value="delete_banner">
                            <input type="hidden" name="banner_id" value="<?= $banner['id'] ?>">
                            <button type="submit" style="background:#fee2e2;color:#991b1b;padding:4px 10px;border-radius:6px;border:none;cursor:pointer;font-size:12px;font-weight:500;">删除</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.querySelectorAll('.toggle-form').forEach(function(form) {
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        var formData = new FormData(form);
        var button = form.querySelector('.toggle-btn');

        fetch('', {
            method: 'POST',
            body: formData,
            headers: {'X-Requested-With': 'XMLHttpRequest'}
        })
        .then(function(response) { return response.json(); })
        .then(function(response) {
            if (response.success) {
                if (response.is_enabled) {
                    button.style.background = '#d1fae5';
                    button.style.color = '#065f46';
                    button.textContent = '启用';
                } else {
                    button.style.background = '#f3f4f6';
                    button.style.color = '#6b7280';
                    button.textContent = '禁用';
                }
            } else {
                alert('操作失败：' + (response.error || '未知错误'));
                window.location.reload();
            }
        }).catch(function(err) {
            alert('请求失败：' + err.message);
            window.location.reload();
        });
    });
});
</script>
<?php endif; ?>
