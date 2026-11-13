<?php
require_once __DIR__ . '/../shared/api.php';
api_require_roles(['supervisor', 'company', 'coordinator', 'admin']);

try {
    $db = api_db();
    if (in_array($_SESSION['role'], ['coordinator', 'admin'], true)) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            api_json(['success' => false, 'message' => 'Method not allowed.'], 405);
        }
        api_require_csrf();
        $input = api_input();
        $id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
        $decision = $input['supervisor_approval'] ?? '';
        if (!$id || !in_array($decision, ['approved', 'rejected'], true)) {
            api_json(['success' => false, 'message' => 'Choose a valid attendance decision.'], 422);
        }
        $db->beginTransaction();
        $sql = 'SELECT a.id, a.student_id, a.total_hours
                FROM attendance a JOIN ojt_placements p ON p.id = a.placement_id
                WHERE a.id = :id';
        $params = ['id' => $id];
        if ($_SESSION['role'] === 'coordinator') {
            $coordinator = api_user_profile($db, 'coordinators');
            if (!$coordinator) {
                $db->rollBack();
                api_json(['success' => false, 'message' => 'Coordinator profile is missing.'], 403);
            }
            $sql .= ' AND p.coordinator_id = :coordinator_id';
            $params['coordinator_id'] = $coordinator['id'];
        }
        $sql .= ' FOR UPDATE';
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $record = $stmt->fetch();
        if (!$record) {
            $db->rollBack();
            api_json(['success' => false, 'message' => 'Attendance record not found.'], 404);
        }
        $stmt = $db->prepare('UPDATE attendance SET supervisor_approval = :decision WHERE id = :id');
        $stmt->execute(['decision' => $decision, 'id' => $id]);
        $stmt = $db->prepare(
            'UPDATE students SET hours_rendered = (
                 SELECT COALESCE(SUM(total_hours), 0) FROM attendance
                 WHERE student_id = :student_filter AND supervisor_approval = "approved"
             ) WHERE id = :student_id'
        );
        $stmt->execute(['student_filter' => $record['student_id'], 'student_id' => $record['student_id']]);
        $db->commit();
        api_log($db, 'attendance.reviewed', 'Reviewed attendance ' . $id . ' as ' . $decision);
        api_json(['success' => true, 'message' => 'Attendance review saved.']);
    }
    $supervisor = api_user_profile($db, 'supervisors');
    if (!$supervisor) {
        api_json(['success' => false, 'message' => 'Your account is not linked to a company supervisor profile. Contact the administrator.'], 403);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $db->prepare(
            'SELECT a.*, CONCAT_WS(" ", u.first_name, u.last_name) AS student_name,
                    s.student_number, c.name AS company_name
             FROM attendance a
             JOIN students s ON s.id = a.student_id
             JOIN users u ON u.id = s.user_id
             JOIN ojt_placements p ON p.id = a.placement_id
             JOIN companies c ON c.id = p.company_id
             WHERE p.supervisor_id = :supervisor_id
             ORDER BY a.date DESC, a.id DESC LIMIT 500'
        );
        $stmt->execute(['supervisor_id' => $supervisor['id']]);
        api_json(['success' => true, 'data' => $stmt->fetchAll()]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        api_json(['success' => false, 'message' => 'Method not allowed.'], 405);
    }
    api_require_csrf();
    $input = api_input();
    $studentId = filter_var($input['student_id'] ?? null, FILTER_VALIDATE_INT);
    $date = $input['date'] ?? '';
    $timeIn = $input['time_in'] ?? '';
    $timeOut = $input['time_out'] ?? '';
    if (!$studentId || !api_valid_date($date) || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $timeIn) ||
        !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $timeOut) || $timeOut <= $timeIn) {
        api_json(['success' => false, 'message' => 'Choose a student, date, and valid start/end times.'], 422);
    }
    $stmt = $db->prepare(
        'SELECT p.id FROM ojt_placements p
         WHERE p.student_id = :student_id AND p.supervisor_id = :supervisor_id
           AND p.status IN ("approved", "ongoing") LIMIT 1'
    );
    $stmt->execute(['student_id' => $studentId, 'supervisor_id' => $supervisor['id']]);
    $placementId = $stmt->fetchColumn();
    if (!$placementId) {
        api_json(['success' => false, 'message' => 'That student is not assigned to your company.'], 403);
    }

    $start = strtotime($timeIn);
    $end = strtotime($timeOut);
    $hours = round(($end - $start) / 3600, 2);
    if ($hours <= 0 || $hours > 24) {
        api_json(['success' => false, 'message' => 'The recorded work duration is invalid.'], 422);
    }
    $status = in_array($input['status'] ?? '', ['present', 'late', 'absent', 'half_day'], true)
        ? $input['status']
        : 'present';
    $db->beginTransaction();
    $stmt = $db->prepare(
        'INSERT INTO attendance (student_id, placement_id, date, time_in, time_out, total_hours, status, supervisor_approval, remarks)
         VALUES (:student_id, :placement_id, :date, :time_in, :time_out, :hours, :status, "pending", :remarks)
         ON DUPLICATE KEY UPDATE placement_id = VALUES(placement_id), time_in = VALUES(time_in),
           time_out = VALUES(time_out), total_hours = VALUES(total_hours), status = VALUES(status),
           supervisor_approval = "pending", remarks = VALUES(remarks)'
    );
    $stmt->execute([
        'student_id' => $studentId,
        'placement_id' => $placementId,
        'date' => $date,
        'time_in' => $timeIn,
        'time_out' => $timeOut,
        'hours' => $hours,
        'status' => $status,
        'remarks' => trim($input['remarks'] ?? '') ?: null,
    ]);
    $stmt = $db->prepare('SELECT COALESCE(SUM(total_hours), 0) FROM attendance WHERE student_id = :student_id AND supervisor_approval = "approved"');
    $stmt->execute(['student_id' => $studentId]);
    $hoursRendered = $stmt->fetchColumn();
    $stmt = $db->prepare('UPDATE students SET hours_rendered = :hours WHERE id = :student_id');
    $stmt->execute(['hours' => $hoursRendered, 'student_id' => $studentId]);
    $db->commit();
    api_log($db, 'attendance.recorded', 'Recorded attendance for student ' . $studentId . ' on ' . $date);
    api_json(['success' => true, 'message' => 'Attendance saved for coordinator review.']);
} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Attendance controller failed: ' . $e->getMessage());
    api_json(['success' => false, 'message' => 'Unable to process the attendance request.'], 500);
}