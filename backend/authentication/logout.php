<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/session.php';
$baseUrl = BASE_URL;
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();
header('Location: ' . $baseUrl . 'index.html');
exit();
?>