<?php
require_once __DIR__ . '/../shared/api.php';
check_session();

try {
    $db = api_db();
    $stmt = $db->prepare('SELECT id, username, email, role, first_name, last_name, middle_name, contact_number, status FROM users WHERE id = :id');
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $user = $stmt->fetch();
    if (!$user || $user['status'] !== 'active') {
        $_SESSION = [];
        session_destroy();
        api_json(['success' => false, 'message' => 'Your account is not active.'], 403);
    }
    api_json(['success' => true, 'user' => $user, 'csrfToken' => $_SESSION['csrf_token'] ?? null]);
} catch (Throwable $e) {
    error_log('Session profile lookup failed: ' . $e->getMessage());
    api_json(['success' => false, 'message' => 'Unable to load your account.'], 500);
}
