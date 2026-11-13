<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}

require_once __DIR__ . '/../backend/config/database.php';

$email = trim(fgets(STDIN) ?: '');
$password = rtrim(fgets(STDIN) ?: '', "\r\n");
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 10) {
    fwrite(STDERR, "Provide an administrator email and a password of at least 10 characters.\n");
    exit(2);
}

try {
    $db = (new Database())->getConnection();
    $stmt = $db->prepare('UPDATE users SET password = :password WHERE email = :email AND role = "admin"');
    $stmt->execute([
        'password' => password_hash($password, PASSWORD_DEFAULT),
        'email' => $email,
    ]);
    if ($stmt->rowCount() !== 1) {
        fwrite(STDERR, "No matching administrator account was updated.\n");
        exit(1);
    }
    fwrite(STDOUT, "Administrator password updated. Sign in with the new password.\n");
} catch (Throwable $e) {
    error_log('Administrator password reset failed: ' . $e->getMessage());
    fwrite(STDERR, "Password reset failed. Check the database connection.\n");
    exit(1);
}
