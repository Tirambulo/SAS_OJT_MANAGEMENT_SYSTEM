<?php
require_once __DIR__ . '/api.php';
check_session();

try {
    $db = api_db();
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $db->prepare(
            'SELECT id, title, message, type, link, is_read, created_at
             FROM notifications WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 100'
        );
        $stmt->execute(['user_id' => $_SESSION['user_id']]);
        api_json(['success' => true, 'data' => $stmt->fetchAll()]);
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        api_json(['success' => false, 'message' => 'Method not allowed.'], 405);
    }
    api_require_csrf();
    $input = api_input();
    if (($input['action'] ?? '') === 'read') {
        $id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$id) {
            api_json(['success' => false, 'message' => 'Notification is invalid.'], 422);
        }
        $stmt = $db->prepare('UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :user_id');
        $stmt->execute(['id' => $id, 'user_id' => $_SESSION['user_id']]);
        api_json(['success' => true]);
    }
    if (($input['action'] ?? '') === 'broadcast') {
        api_require_roles(['admin', 'sas_staff', 'coordinator']);
        $title = trim($input['title'] ?? '');
        $message = trim($input['message'] ?? '');
        if (!$title || !$message) {
            api_json(['success' => false, 'message' => 'Title and message are required.'], 422);
        }
        $role = trim($input['role'] ?? '');
        $query = 'INSERT INTO notifications (user_id, title, message, type)
                  SELECT id, :title, :message, "info" FROM users WHERE status = "active"';
        if ($role !== '') {
            $query .= ' AND role = :role';
        }
        $stmt = $db->prepare($query);
        $params = ['title' => $title, 'message' => $message];
        if ($role !== '') {
            $params['role'] = $role;
        }
        $stmt->execute($params);
        api_log($db, 'notification.broadcast', 'Sent a system notification');
        api_json(['success' => true, 'message' => 'Notification sent to ' . $stmt->rowCount() . ' accounts.']);
    }
    api_json(['success' => false, 'message' => 'Unknown action.'], 404);
} catch (Throwable $e) {
    error_log('Notification controller failed: ' . $e->getMessage());
    api_json(['success' => false, 'message' => 'Unable to process the notification request.'], 500);
}