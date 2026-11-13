<?php
require_once __DIR__ . '/../shared/api.php';
api_require_roles(['admin']);

try {
    $db = api_db();
    $action = $_GET['action'] ?? 'overview';
    if ($action === 'overview') {
        $counts = [];
        foreach ([
            'students' => 'SELECT COUNT(*) FROM students',
            'companies' => 'SELECT COUNT(*) FROM companies WHERE status = "active"',
            'placements' => 'SELECT COUNT(*) FROM ojt_placements WHERE status IN ("approved", "ongoing")',
            'pending_requirements' => 'SELECT COUNT(*) FROM requirements WHERE status = "pending"',
            'pending_users' => 'SELECT COUNT(*) FROM users WHERE status = "pending"',
        ] as $key => $query) {
            $counts[$key] = (int) $db->query($query)->fetchColumn();
        }
        api_json(['success' => true, 'data' => $counts]);
    }
    if ($action === 'activity') {
        $stmt = $db->query(
            'SELECT a.id, a.action, a.description, a.created_at, u.username
             FROM activity_logs a LEFT JOIN users u ON u.id = a.user_id
             ORDER BY a.created_at DESC LIMIT 50'
        );
        api_json(['success' => true, 'data' => $stmt->fetchAll()]);
    }
    if ($action === 'settings') {
        $stmt = $db->query('SELECT setting_key, setting_value, updated_at FROM system_settings ORDER BY setting_key');
        api_json(['success' => true, 'data' => $stmt->fetchAll()]);
    }
    if ($action === 'save-setting' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        api_require_csrf();
        $input = api_input();
        $key = trim($input['key'] ?? '');
        if (!preg_match('/^[a-z][a-z0-9_.-]{1,99}$/', $key)) {
            api_json(['success' => false, 'message' => 'Setting key is invalid.'], 422);
        }
        $stmt = $db->prepare(
            'INSERT INTO system_settings (setting_key, setting_value, updated_by)
             VALUES (:key, :value, :user_id)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by)'
        );
        $stmt->execute(['key' => $key, 'value' => (string) ($input['value'] ?? ''), 'user_id' => $_SESSION['user_id']]);
        api_log($db, 'setting.updated', 'Updated system setting ' . $key);
        api_json(['success' => true, 'message' => 'Setting saved.']);
    }
    api_json(['success' => false, 'message' => 'Unknown action.'], 404);
} catch (Throwable $e) {
    error_log('System controller failed: ' . $e->getMessage());
    api_json(['success' => false, 'message' => 'Unable to load system information.'], 500);
}