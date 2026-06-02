<?php
/**
 * 通知相关路由
 */

// 获取通知列表
if ($path === '/notifications' && $method === 'GET') {
    $userId = getSecureUserId();

    $page = intval($_GET['page'] ?? 1);
    $limit = intval($_GET['limit'] ?? 20);
    $offset = ($page - 1) * $limit;
    
    if (!$userId) {
        apiBadRequest('user_id 参数缺失');
    }
    
    $notifications = $db->query(
        "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?",
        [$userId, $limit, $offset]
    )->fetchAll();
    
    $result = array_map(function($n) {
        return [
            'id' => $n['id'],
            'title' => $n['title'],
            'content' => $n['body'] ?? '',
            'type' => $n['type'],
            'is_read' => $n['status'] === 'read',
            'created_at' => $n['created_at']
        ];
    }, $notifications);
    
    echo json_encode(['success' => true, 'data' => $result]);
    exit;
}

// 标记通知为已读
if (preg_match('/^\/notifications\/(.+)\/read$/', $path, $matches) && $method === 'POST') {
    $notificationId = $matches[1];
    $userId = getCurrentUserIdFromToken();
    
    if (!$userId) {
        apiUnauthorized('请先登录');
    }
    
    $notification = $db->query(
        "SELECT * FROM notifications WHERE id = ? AND user_id = ?",
        [$notificationId, $userId]
    )->fetch();
    
    if (!$notification) {
        apiNotFound('通知不存在');
    }
    
    $db->query(
        "UPDATE notifications SET status = 'read', read_at = NOW() WHERE id = ?",
        [$notificationId]
    );
    
    echo json_encode(['success' => true, 'message' => '已标记为已读']);
    exit;
}

// 标记所有通知为已读
if ($path === '/notifications/read-all' && $method === 'POST') {
    $userId = getCurrentUserIdFromToken();
    
    if (!$userId) {
        apiUnauthorized('请先登录');
    }
    
    $db->query(
        "UPDATE notifications SET status = 'read', read_at = NOW() WHERE user_id = ? AND status != 'read'",
        [$userId]
    );
    
    echo json_encode(['success' => true, 'message' => '所有通知已标记为已读']);
    exit;
}
