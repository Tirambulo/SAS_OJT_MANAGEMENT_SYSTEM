<?php
require_once __DIR__ . '/../shared/api.php';
api_require_roles(['admin']);

try {
    $db = api_db();
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $search = trim($_GET['search'] ?? '');
        $stmt = $db->prepare(
            'SELECT id, username, email, role, first_name, last_name, contact_number, status, created_at
             FROM users
             WHERE (:search = "" OR username LIKE :username_term OR email LIKE :email_term
                    OR first_name LIKE :first_term OR last_name LIKE :last_term)
             ORDER BY created_at DESC LIMIT 500'
        );
        $term = '%' . $search . '%';
        $stmt->execute([
            'search' => $search,
            'username_term' => $term,
            'email_term' => $term,
            'first_term' => $term,
            'last_term' => $term,
        ]);
        api_json(['success' => true, 'data' => $stmt->fetchAll()]);
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        api_json(['success' => false, 'message' => 'Method not allowed.'], 405);
    }
    api_require_csrf();
    $input = api_input();
    $action = $input['action'] ?? '';
    $id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);

    if ($action === 'status' && $id) {
        $status = $input['status'] ?? '';
        if (!in_array($status, ['active', 'inactive', 'pending'], true) || $id === (int) $_SESSION['user_id']) {
            api_json(['success' => false, 'message' => 'Invalid status change.'], 422);
        }
        $stmt = $db->prepare('UPDATE users SET status = :status WHERE id = :id');
        $stmt->execute(['status' => $status, 'id' => $id]);
        api_log($db, 'user.status_changed', 'Changed account status for user ' . $id);
        api_json(['success' => true, 'message' => 'Account status updated.']);
    }

    if ($action === 'create') {
        $firstName = trim($input['first_name'] ?? '');
        $lastName = trim($input['last_name'] ?? '');
        $email = filter_var(trim($input['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $username = trim($input['username'] ?? '');
        $role = $input['role'] ?? '';
        $password = $input['password'] ?? '';
        $allowedRoles = ['admin', 'sas_staff', 'coordinator', 'supervisor', 'company', 'student'];
        if (!$firstName || !$lastName || !$email || !$username || !in_array($role, $allowedRoles, true) || strlen($password) < 10) {
            api_json(['success' => false, 'message' => 'Provide valid account details and a password of at least 10 characters.'], 422);
        }
        if (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $username)) {
            api_json(['success' => false, 'message' => 'Username must be 3–50 characters and use letters, numbers, dots, underscores, or hyphens.'], 422);
        }
        if ($role === 'student' && (!trim($input['student_number'] ?? '') || !trim($input['course'] ?? ''))) {
            api_json(['success' => false, 'message' => 'Student number and course are required for student accounts.'], 422);
        }
        if ($role === 'coordinator' && (!trim($input['employee_id'] ?? '') || !trim($input['department'] ?? ''))) {
            api_json(['success' => false, 'message' => 'Coordinator employee ID and department are required.'], 422);
        }
        if (in_array($role, ['company', 'supervisor'], true) &&
            (!filter_var($input['company_id'] ?? null, FILTER_VALIDATE_INT) || !trim($input['position'] ?? ''))) {
            api_json(['success' => false, 'message' => 'Company ID and supervisor position are required.'], 422);
        }
        $db->beginTransaction();
        $stmt = $db->prepare(
            'INSERT INTO users (username, email, password, role, first_name, last_name, status)
             VALUES (:username, :email, :password, :role, :first_name, :last_name, "active")'
        );
        $stmt->execute([
            'username' => $username,
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'role' => $role,
            'first_name' => $firstName,
            'last_name' => $lastName,
        ]);
        $newUserId = (int) $db->lastInsertId();
        if ($role === 'student') {
            $stmt = $db->prepare(
                'INSERT INTO students (user_id, student_number, course, year_level, section)
                 VALUES (:user_id, :student_number, :course, :year_level, :section)'
            );
            $stmt->execute([
                'user_id' => $newUserId,
                'student_number' => trim($input['student_number'] ?? ''),
                'course' => trim($input['course'] ?? ''),
                'year_level' => max(1, min(8, (int) ($input['year_level'] ?? 4))),
                'section' => trim($input['section'] ?? '') ?: null,
            ]);
        } elseif ($role === 'coordinator') {
            $stmt = $db->prepare(
                'INSERT INTO coordinators (user_id, employee_id, department, college)
                 VALUES (:user_id, :employee_id, :department, :college)'
            );
            $stmt->execute([
                'user_id' => $newUserId,
                'employee_id' => trim($input['employee_id']),
                'department' => trim($input['department']),
                'college' => trim($input['college'] ?? '') ?: null,
            ]);
        } elseif (in_array($role, ['company', 'supervisor'], true)) {
            $companyId = filter_var($input['company_id'] ?? null, FILTER_VALIDATE_INT);
            $stmt = $db->prepare(
                'INSERT INTO supervisors (user_id, company_id, position, department)
                 VALUES (:user_id, :company_id, :position, :department)'
            );
            $stmt->execute([
                'user_id' => $newUserId,
                'company_id' => $companyId,
                'position' => trim($input['position']),
                'department' => trim($input['department'] ?? '') ?: null,
            ]);
        }
        $db->commit();
        api_log($db, 'user.created', 'Created ' . $role . ' account ' . $username);
        api_json(['success' => true, 'message' => 'Account created.', 'id' => $newUserId], 201);
    }

    if ($action === 'reset-password' && $id) {
        $password = $input['password'] ?? '';
        if (strlen($password) < 10) {
            api_json(['success' => false, 'message' => 'Password must be at least 10 characters.'], 422);
        }
        $stmt = $db->prepare('UPDATE users SET password = :password WHERE id = :id');
        $stmt->execute(['password' => password_hash($password, PASSWORD_DEFAULT), 'id' => $id]);
        api_log($db, 'user.password_reset', 'Reset password for user ' . $id);
        api_json(['success' => true, 'message' => 'Password reset.']);
    }
    api_json(['success' => false, 'message' => 'Unknown action.'], 404);
} catch (PDOException $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    if ($e->getCode() === '23000') {
        api_json(['success' => false, 'message' => 'That username or email address is already in use.'], 409);
    }
    error_log('User controller failed: ' . $e->getMessage());
    api_json(['success' => false, 'message' => 'Unable to process the account request.'], 500);
} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log('User controller failed: ' . $e->getMessage());
    api_json(['success' => false, 'message' => 'Unable to process the account request.'], 500);
}