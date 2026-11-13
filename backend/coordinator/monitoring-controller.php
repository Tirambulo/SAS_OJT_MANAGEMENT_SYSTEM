<?php
require_once __DIR__ . '/../shared/api.php';
api_require_roles(['coordinator', 'admin']);

try {
    $db = api_db();
    if (($_GET['action'] ?? '') === 'attendance') {
        $profile = $_SESSION['role'] === 'coordinator' ? api_user_profile($db, 'coordinators') : null;
        if ($_SESSION['role'] === 'coordinator' && !$profile) {
            api_json(['success' => false, 'message' => 'Coordinator profile is missing.'], 403);
        }
        $sql = 'SELECT a.*, s.student_number, CONCAT_WS(" ", u.first_name, u.last_name) AS student_name,
                       c.name AS company_name
                FROM attendance a JOIN ojt_placements p ON p.id = a.placement_id
                JOIN students s ON s.id = a.student_id JOIN users u ON u.id = s.user_id
                JOIN companies c ON c.id = p.company_id
                WHERE a.supervisor_approval = "pending"';
        $params = [];
        if ($profile) {
            $sql .= ' AND p.coordinator_id = :coordinator_id';
            $params['coordinator_id'] = $profile['id'];
        }
        $sql .= ' ORDER BY a.date DESC';
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        api_json(['success' => true, 'data' => $stmt->fetchAll()]);
    }
    $profile = $_SESSION['role'] === 'coordinator' ? api_user_profile($db, 'coordinators') : null;
    if ($_SESSION['role'] === 'coordinator' && !$profile) {
        api_json(['success' => false, 'message' => 'Coordinator profile is missing.'], 403);
    }
    $coordinatorId = $profile['id'] ?? null;
    $sql = 'SELECT s.id, s.student_number, s.course, s.year_level, s.ojt_status,
                   s.hours_required, s.hours_rendered, u.first_name, u.last_name,
                   p.id AS placement_id, p.status AS placement_status, p.start_date, p.end_date,
                   c.name AS company_name
            FROM students s JOIN users u ON u.id = s.user_id
            LEFT JOIN ojt_placements p ON p.student_id = s.id AND p.status NOT IN ("terminated")
            LEFT JOIN companies c ON c.id = p.company_id';
    if ($coordinatorId !== null) {
        $sql .= ' WHERE s.coordinator_id = :student_coordinator_id OR p.coordinator_id = :placement_coordinator_id';
    }
    $sql .= ' ORDER BY u.last_name, u.first_name';
    $stmt = $db->prepare($sql);
    $stmt->execute($coordinatorId === null ? [] : [
        'student_coordinator_id' => $coordinatorId,
        'placement_coordinator_id' => $coordinatorId,
    ]);
    api_json(['success' => true, 'data' => $stmt->fetchAll()]);
} catch (Throwable $e) {
    error_log('Monitoring controller failed: ' . $e->getMessage());
    api_json(['success' => false, 'message' => 'Unable to load OJT monitoring information.'], 500);
}