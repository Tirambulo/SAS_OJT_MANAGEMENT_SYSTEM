<?php
require_once __DIR__ . '/../config/config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
    ]);
    session_start();
}

function check_session(): void
{
    if (empty($_SESSION['user_id'])) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Please sign in to continue.']);
        exit();
    }

    try {
        $db = function_exists('api_db') ? api_db() : (new Database())->getConnection();
        $stmt = $db->prepare('SELECT role, status FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $_SESSION['user_id']]);
        $user = $stmt->fetch();
        if (!$user || $user['status'] !== 'active') {
            $_SESSION = [];
            session_destroy();
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Your account is no longer active. Please contact the administrator.']);
            exit();
        }
        $_SESSION['role'] = $user['role'];
    } catch (Throwable $e) {
        error_log('Session account check failed: ' . $e->getMessage());
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(503);
        echo json_encode(['success' => false, 'message' => 'Unable to verify your account right now.']);
        exit();
    }
}

function require_role($roles): void
{
    check_session();
    $roles = is_array($roles) ? $roles : [$roles];
    if (!in_array($_SESSION['role'] ?? '', $roles, true)) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You do not have permission to perform this action.']);
        exit();
    }
}