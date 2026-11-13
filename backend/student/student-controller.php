<?php
require_once __DIR__ . '/../shared/api.php';
api_require_roles(['student']);

try {
    $db = api_db();
    $profile = api_user_profile($db, 'students');
    if (!$profile) {
        api_json(['success' => false, 'message' => 'Student profile is missing. Contact the administrator.'], 403);
    }
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'attendance') {
        $stmt = $db->prepare(
            'SELECT a.date, a.time_in, a.time_out, a.total_hours, a.status, a.supervisor_approval, a.remarks
             FROM attendance a WHERE a.student_id = :student_id ORDER BY a.date DESC'
        );
        $stmt->execute(['student_id' => $profile['id']]);
        api_json(['success' => true, 'data' => $stmt->fetchAll()]);
    }
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $db->prepare(
            'SELECT s.*, u.first_name, u.last_name, u.email, u.contact_number,
                    p.status AS placement_status, p.start_date, p.end_date, p.required_hours,
                    c.name AS company_name, c.address AS company_address,
                    su.first_name AS supervisor_first_name, su.last_name AS supervisor_last_name
             FROM students s
             JOIN users u ON u.id = s.user_id
             LEFT JOIN ojt_placements p ON p.student_id = s.id AND p.status NOT IN ("terminated")
             LEFT JOIN companies c ON c.id = p.company_id
             LEFT JOIN supervisors sp ON sp.id = p.supervisor_id
             LEFT JOIN users su ON su.id = sp.user_id
             WHERE s.id = :student_id ORDER BY p.created_at DESC LIMIT 1'
        );
        $stmt->execute(['student_id' => $profile['id']]);
        $student = $stmt->fetch();
        $stmt = $db->prepare('SELECT COUNT(*) FROM requirements WHERE student_id = :id AND status = "approved"');
        $stmt->execute(['id' => $profile['id']]);
        $student['approved_requirements'] = (int) $stmt->fetchColumn();
        $stmt = $db->prepare('SELECT COUNT(*) FROM requirements WHERE student_id = :id');
        $stmt->execute(['id' => $profile['id']]);
        $student['submitted_requirements'] = (int) $stmt->fetchColumn();
        api_json(['success' => true, 'data' => $student]);
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        api_json(['success' => false, 'message' => 'Method not allowed.'], 405);
    }
    api_require_csrf();
    $input = api_input();
    $firstName = trim($input['first_name'] ?? '');
    $lastName = trim($input['last_name'] ?? '');
    $phone = trim($input['contact_number'] ?? '');
    $email = filter_var(trim($input['email'] ?? ''), FILTER_VALIDATE_EMAIL);
    if (!$firstName || !$lastName || !$email) {
        api_json(['success' => false, 'message' => 'First name, last name, and a valid email are required.'], 422);
    }
    $stmt = $db->prepare(
        'UPDATE users SET first_name = :first_name, last_name = :last_name,
         contact_number = :contact_number, email = :email WHERE id = :id'
    );
    $stmt->execute([
        'first_name' => $firstName,
        'last_name' => $lastName,
        'contact_number' => $phone ?: null,
        'email' => $email,
        'id' => $_SESSION['user_id'],
    ]);
    $_SESSION['name'] = $firstName . ' ' . $lastName;
    $_SESSION['email'] = $email;
    api_json(['success' => true, 'message' => 'Profile updated.']);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        api_json(['success' => false, 'message' => 'Email address is already in use.'], 409);
    }
    error_log('Student profile controller failed: ' . $e->getMessage());
    api_json(['success' => false, 'message' => 'Unable to save profile changes.'], 500);
} catch (Throwable $e) {
    error_log('Student profile controller failed: ' . $e->getMessage());
    api_json(['success' => false, 'message' => 'Unable to save profile changes.'], 500);
}