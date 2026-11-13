<?php
require_once __DIR__ . '/../shared/api.php';
api_require_roles(['student', 'supervisor', 'company']);

try {
    $db = api_db();
    if (in_array($_SESSION['role'], ['supervisor', 'company'], true)) {
        $supervisor = api_user_profile($db, 'supervisors');
        if (!$supervisor) {
            api_json(['success' => false, 'message' => 'Company supervisor profile is missing.'], 403);
        }
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $stmt = $db->prepare(
                'SELECT j.*, s.student_number, CONCAT_WS(" ", u.first_name, u.last_name) AS student_name
                 FROM journals j JOIN ojt_placements p ON p.id = j.placement_id
                 JOIN students s ON s.id = j.student_id JOIN users u ON u.id = s.user_id
                 WHERE p.supervisor_id = :supervisor_id ORDER BY j.journal_date DESC'
            );
            $stmt->execute(['supervisor_id' => $supervisor['id']]);
            api_json(['success' => true, 'data' => $stmt->fetchAll()]);
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            api_json(['success' => false, 'message' => 'Method not allowed.'], 405);
        }
        api_require_csrf();
        $input = api_input();
        $id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
        $status = $input['status'] ?? '';
        if (!$id || !in_array($status, ['approved', 'rejected'], true)) {
            api_json(['success' => false, 'message' => 'Choose a valid review decision.'], 422);
        }
        $stmt = $db->prepare(
            'UPDATE journals j JOIN ojt_placements p ON p.id = j.placement_id
             SET j.status = :status, j.supervisor_feedback = :feedback,
                 j.reviewed_by = :reviewed_by, j.reviewed_at = CURRENT_TIMESTAMP
             WHERE j.id = :id AND p.supervisor_id = :supervisor_id AND j.status = "pending"'
        );
        $stmt->execute([
            'status' => $status,
            'feedback' => trim($input['feedback'] ?? '') ?: null,
            'reviewed_by' => $_SESSION['user_id'],
            'id' => $id,
            'supervisor_id' => $supervisor['id'],
        ]);
        if (!$stmt->rowCount()) {
            api_json(['success' => false, 'message' => 'Pending journal not found.'], 404);
        }
        api_json(['success' => true, 'message' => 'Journal review saved.']);
    }

    $student = api_user_profile($db, 'students');
    if (!$student) {
        api_json(['success' => false, 'message' => 'Student profile is missing.'], 403);
    }
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $db->prepare(
            'SELECT j.*, p.status AS placement_status FROM journals j
             JOIN ojt_placements p ON p.id = j.placement_id
             WHERE j.student_id = :student_id ORDER BY j.journal_date DESC'
        );
        $stmt->execute(['student_id' => $student['id']]);
        api_json(['success' => true, 'data' => $stmt->fetchAll()]);
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        api_json(['success' => false, 'message' => 'Method not allowed.'], 405);
    }
    api_require_csrf();
    $input = api_input();
    $title = trim($input['title'] ?? '');
    $activities = trim($input['activities'] ?? '');
    $journalDate = $input['journal_date'] ?? '';
    $hours = filter_var($input['hours'] ?? 0, FILTER_VALIDATE_FLOAT);
    $stmt = $db->prepare(
        'SELECT id FROM ojt_placements WHERE student_id = :student_id AND status IN ("approved", "ongoing") ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute(['student_id' => $student['id']]);
    $placementId = $stmt->fetchColumn();
    if (!$placementId || !$title || strlen($title) > 150 || !$activities ||
        !api_valid_date($journalDate) || $hours === false || $hours < 0 || $hours > 24) {
        api_json(['success' => false, 'message' => 'An active placement, date, title, activities, and valid hours are required.'], 422);
    }
    $stmt = $db->prepare(
        'INSERT INTO journals (student_id, placement_id, journal_date, title, activities, learnings, hours)
         VALUES (:student_id, :placement_id, :date, :title, :activities, :learnings, :hours)
         ON DUPLICATE KEY UPDATE title = VALUES(title), activities = VALUES(activities),
           learnings = VALUES(learnings), hours = VALUES(hours), status = "pending",
           supervisor_feedback = NULL, reviewed_by = NULL, reviewed_at = NULL'
    );
    $stmt->execute([
        'student_id' => $student['id'],
        'placement_id' => $placementId,
        'date' => $journalDate,
        'title' => $title,
        'activities' => $activities,
        'learnings' => trim($input['learnings'] ?? '') ?: null,
        'hours' => $hours,
    ]);
    api_json(['success' => true, 'message' => 'Daily journal submitted.']);
} catch (Throwable $e) {
    error_log('Journal controller failed: ' . $e->getMessage());
    api_json(['success' => false, 'message' => 'Unable to process the journal request.'], 500);
}