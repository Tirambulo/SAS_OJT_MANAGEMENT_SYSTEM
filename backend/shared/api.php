<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../authentication/session.php';

function api_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit();
}

function api_db(): PDO
{
    static $db;
    if (!$db) {
        $db = (new Database())->getConnection();
    }
    return $db;
}

function api_require_roles(array $roles): void
{
    check_session();
    if (!in_array($_SESSION['role'] ?? '', $roles, true)) {
        api_json(['success' => false, 'message' => 'You do not have permission to perform this action.'], 403);
    }
}

function api_require_csrf(): void
{
    $provided = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $expected = $_SESSION['csrf_token'] ?? '';
    if (!$provided || !$expected || !hash_equals($expected, $provided)) {
        api_json(['success' => false, 'message' => 'Your session expired. Refresh the page and try again.'], 419);
    }
}

function api_input(): array
{
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') !== false) {
        $decoded = json_decode(file_get_contents('php://input'), true);
        return is_array($decoded) ? $decoded : [];
    }
    return $_POST;
}

function api_valid_date(string $value): bool
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    $errors = DateTimeImmutable::getLastErrors();
    return $date !== false
        && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
        && $date->format('Y-m-d') === $value;
}

function api_log(PDO $db, string $action, ?string $description = null): void
{
    $stmt = $db->prepare(
        'INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent)
         VALUES (:user_id, :action, :description, :ip, :agent)'
    );
    $stmt->execute([
        'user_id' => $_SESSION['user_id'] ?? null,
        'action' => $action,
        'description' => $description,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        'agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
    ]);
}

function api_user_profile(PDO $db, string $table): ?array
{
    $allowed = ['students', 'coordinators', 'supervisors'];
    if (!in_array($table, $allowed, true)) {
        throw new InvalidArgumentException('Unsupported profile table.');
    }
    $stmt = $db->prepare("SELECT * FROM {$table} WHERE user_id = :user_id LIMIT 1");
    $stmt->execute(['user_id' => $_SESSION['user_id']]);
    $profile = $stmt->fetch();
    return $profile ?: null;
}

function api_user_name(PDO $db, int $userId): string
{
    $stmt = $db->prepare("SELECT CONCAT_WS(' ', first_name, middle_name, last_name) FROM users WHERE id = :id");
    $stmt->execute(['id' => $userId]);
    return (string) $stmt->fetchColumn();
}
