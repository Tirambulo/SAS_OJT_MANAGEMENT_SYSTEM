<?php
require_once __DIR__ . '/../shared/api.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_json(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
$password = $_POST['password'] ?? '';
if (!$email || $password === '') {
    api_json(['success' => false, 'message' => 'Enter a valid email address and password.'], 422);
}

try {
    $db = api_db();
    $stmt = $db->prepare('SELECT id, email, password, role, first_name, last_name, status FROM users WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        api_json(['success' => false, 'message' => 'Invalid email or password.'], 401);
    }
    if ($user['status'] !== 'active') {
        api_json(['success' => false, 'message' => 'This account is not active. Contact your system administrator.'], 403);
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['name'] = trim($user['first_name'] . ' ' . $user['last_name']);
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    $routes = [
        'admin' => 'administrator',
        'sas_staff' => 'sas-staff',
        'coordinator' => 'coordinator',
        'supervisor' => 'company',
        'company' => 'company',
        'student' => 'student',
    ];
    $section = $routes[$user['role']] ?? null;
    if ($section === null) {
        session_destroy();
        api_json(['success' => false, 'message' => 'This account has an unsupported role.'], 403);
    }

    api_json([
        'success' => true,
        'csrfToken' => $_SESSION['csrf_token'],
        'redirect' => BASE_URL . 'dashboards/' . $section . '/dashboard.html',
    ]);
} catch (Throwable $e) {
    error_log('Login failed: ' . $e->getMessage());
    api_json(['success' => false, 'message' => 'Unable to sign in right now. Please try again later.'], 500);
}