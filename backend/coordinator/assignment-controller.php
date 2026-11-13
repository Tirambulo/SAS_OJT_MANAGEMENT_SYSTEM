<?php
require_once __DIR__ . '/../shared/api.php';
api_require_roles(['coordinator', 'admin']);

try {
    $db = api_db();
    $coordinatorId = null;
    if ($_SESSION['role'] === 'coordinator') {
        $profile = api_user_profile($db, 'coordinators');
        if (!$profile) {
            api_json(['success' => false, 'message' => 'Coordinator profile is missing.'], 403);
        }
        $coordinatorId = (int) $profile['id'];
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = $_GET['action'] ?? 'placements';
        if ($action === 'options') {
            $students = $db->query(
                'SELECT s.id, s.student_number, s.course, s.year_level,
                        CONCAT_WS(" ", u.first_name, u.last_name) AS name
                 FROM students s JOIN users u ON u.id = s.user_id
                 WHERE s.ojt_status IN ("not_started", "ongoing") ORDER BY u.last_name, u.first_name'
            );
            $companies = $db->query('SELECT id, name FROM companies WHERE status = "active" ORDER BY name');
            $supervisors = $db->query(
                'SELECT sp.id, sp.company_id,
                        CONCAT_WS(" - ", c.name, CONCAT_WS(" ", u.first_name, u.last_name)) AS name
                 FROM supervisors sp
                 JOIN users u ON u.id = sp.user_id
                 JOIN companies c ON c.id = sp.company_id
                 WHERE u.status = "active" AND c.status = "active"
                 ORDER BY c.name, u.last_name, u.first_name'
            );
            api_json([
                'success' => true,
                'students' => $students->fetchAll(),
                'companies' => $companies->fetchAll(),
                'supervisors' => $supervisors->fetchAll(),
            ]);
        }
        $sql = 'SELECT p.*, s.student_number, CONCAT_WS(" ", su.first_name, su.last_name) AS student_name,
                       c.name AS company_name
                FROM ojt_placements p
                JOIN students s ON s.id = p.student_id
                JOIN users su ON su.id = s.user_id
                JOIN companies c ON c.id = p.company_id';
        if ($coordinatorId !== null) {
            $sql .= ' WHERE p.coordinator_id = :coordinator_id';
        }
        $sql .= ' ORDER BY p.created_at DESC LIMIT 500';
        $stmt = $db->prepare($sql);
        $stmt->execute($coordinatorId === null ? [] : ['coordinator_id' => $coordinatorId]);
        api_json(['success' => true, 'data' => $stmt->fetchAll()]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        api_json(['success' => false, 'message' => 'Method not allowed.'], 405);
    }
    api_require_csrf();
    $input = api_input();
    $action = $input['action'] ?? 'assign';
    if ($action === 'assign') {
        $studentId = filter_var($input['student_id'] ?? null, FILTER_VALIDATE_INT);
        $companyId = filter_var($input['company_id'] ?? null, FILTER_VALIDATE_INT);
        $supervisorId = filter_var($input['supervisor_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$studentId || !$companyId || !$supervisorId) {
            api_json(['success' => false, 'message' => 'Choose a student, company, and supervisor.'], 422);
        }
        $startDate = trim($input['start_date'] ?? '');
        $endDate = trim($input['end_date'] ?? '');
        if (($startDate !== '' && !api_valid_date($startDate)) || ($endDate !== '' && !api_valid_date($endDate)) ||
            ($startDate !== '' && $endDate !== '' && $endDate < $startDate)) {
            api_json(['success' => false, 'message' => 'Enter valid placement dates with the end date on or after the start date.'], 422);
        }
        $db->beginTransaction();
        $stmt = $db->prepare('SELECT id FROM students WHERE id = :id AND ojt_status = "not_started" FOR UPDATE');
        $stmt->execute(['id' => $studentId]);
        if (!$stmt->fetchColumn()) {
            $db->rollBack();
            api_json(['success' => false, 'message' => 'Student is already placed or unavailable.'], 409);
        }
        $stmt = $db->prepare('SELECT id FROM companies WHERE id = :id AND status = "active"');
        $stmt->execute(['id' => $companyId]);
        if (!$stmt->fetchColumn()) {
            $db->rollBack();
            api_json(['success' => false, 'message' => 'Company is not active.'], 422);
        }
        $stmt = $db->prepare(
            'SELECT sp.id FROM supervisors sp JOIN users u ON u.id = sp.user_id
             WHERE sp.id = :supervisor_id AND sp.company_id = :company_id AND u.status = "active"'
        );
        $stmt->execute(['supervisor_id' => $supervisorId, 'company_id' => $companyId]);
        if (!$stmt->fetchColumn()) {
            $db->rollBack();
            api_json(['success' => false, 'message' => 'Choose an active supervisor for the selected company.'], 422);
        }
        $stmt = $db->prepare(
            'INSERT INTO ojt_placements
                (student_id, company_id, supervisor_id, coordinator_id, start_date, end_date, required_hours, status)
             VALUES
                (:student_id, :company_id, :supervisor_id, :coordinator_id, :start_date, :end_date, :required_hours, "pending")'
        );
        $stmt->execute([
            'student_id' => $studentId,
            'company_id' => $companyId,
            'supervisor_id' => $supervisorId,
            'coordinator_id' => $coordinatorId,
            'start_date' => $startDate ?: null,
            'end_date' => $endDate ?: null,
            'required_hours' => max(1, min(5000, (int) ($input['required_hours'] ?? 480))),
        ]);
        $placementId = (int) $db->lastInsertId();
        $stmt = $db->prepare('UPDATE students SET coordinator_id = COALESCE(:coordinator_id, coordinator_id) WHERE id = :id');
        $stmt->execute(['coordinator_id' => $coordinatorId, 'id' => $studentId]);
        $stmt = $db->prepare('INSERT INTO notifications (user_id, title, message, type) SELECT user_id, "OJT placement created", "A coordinator created an OJT placement for you.", "info" FROM students WHERE id = :student_id');
        $stmt->execute(['student_id' => $studentId]);
        $db->commit();
        api_log($db, 'placement.created', 'Created placement ' . $placementId);
        api_json(['success' => true, 'message' => 'Placement created for review.', 'id' => $placementId], 201);
    }
    if ($action === 'status') {
        $placementId = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
        $status = $input['status'] ?? '';
        if (!$placementId || !in_array($status, ['pending', 'approved', 'ongoing', 'completed', 'terminated'], true)) {
            api_json(['success' => false, 'message' => 'Invalid placement update.'], 422);
        }
        $sql = 'UPDATE ojt_placements SET status = :status WHERE id = :id';
        $params = ['status' => $status, 'id' => $placementId];
        if ($coordinatorId !== null) {
            $sql .= ' AND coordinator_id = :coordinator_id';
            $params['coordinator_id'] = $coordinatorId;
        }
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        if (!$stmt->rowCount()) {
            api_json(['success' => false, 'message' => 'Placement not found.'], 404);
        }
        api_log($db, 'placement.status_changed', 'Changed placement ' . $placementId . ' to ' . $status);
        api_json(['success' => true, 'message' => 'Placement status updated.']);
    }
    api_json(['success' => false, 'message' => 'Unknown action.'], 404);
} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Assignment controller failed: ' . $e->getMessage());
    api_json(['success' => false, 'message' => 'Unable to process the placement request.'], 500);
}