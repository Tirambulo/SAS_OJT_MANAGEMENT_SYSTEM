<?php
require_once __DIR__ . '/../shared/api.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_json(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$firstName = trim($_POST['first_name'] ?? '');
$lastName = trim($_POST['last_name'] ?? '');
$username = trim($_POST['username'] ?? '');
$email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
$password = $_POST['password'] ?? '';
$studentNumber = trim($_POST['student_number'] ?? '');
$course = trim($_POST['course'] ?? '');
$yearLevel = filter_var($_POST['year_level'] ?? null, FILTER_VALIDATE_INT);
$section = trim($_POST['section'] ?? '');

if (!$firstName || !$lastName || !$username || !$email || strlen($password) < 10 || !$studentNumber || !$course || !$yearLevel || $yearLevel < 1 || $yearLevel > 8) {
    api_json(['success' => false, 'message' => 'Complete all required fields. Passwords must be at least 10 characters and year level must be between 1 and 8.'], 422);
}
if (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $username)) {
    api_json(['success' => false, 'message' => 'Username must be 3–50 characters and use letters, numbers, dots, underscores, or hyphens.'], 422);
}

try {
    $db = api_db();
    $db->beginTransaction();
    $stmt = $db->prepare(
        'INSERT INTO users (username, email, password, role, first_name, last_name, status)
         VALUES (:username, :email, :password, "student", :first_name, :last_name, "active")'
    );
    $stmt->execute([
        'username' => $username,
        'email' => $email,
        'password' => password_hash($password, PASSWORD_DEFAULT),
        'first_name' => $firstName,
        'last_name' => $lastName,
    ]);
    $userId = (int) $db->lastInsertId();
    $stmt = $db->prepare(
        'INSERT INTO students (user_id, student_number, course, year_level, section)
         VALUES (:user_id, :student_number, :course, :year_level, :section)'
    );
    $stmt->execute([
        'user_id' => $userId,
        'student_number' => $studentNumber,
        'course' => $course,
        'year_level' => $yearLevel,
        'section' => $section ?: null,
    ]);
    $db->commit();
    api_json(['success' => true, 'message' => 'Registration complete. You can now sign in.']);
} catch (PDOException $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    if ($e->getCode() === '23000') {
        api_json(['success' => false, 'message' => 'That username, email, or student number is already registered.'], 409);
    }
    error_log('Registration failed: ' . $e->getMessage());
    api_json(['success' => false, 'message' => 'Unable to complete registration. Please try again later.'], 500);
} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Registration failed: ' . $e->getMessage());
    api_json(['success' => false, 'message' => 'Unable to complete registration. Please try again later.'], 500);
}