<?php
require_once __DIR__ . '/api.php';
api_require_roles(['admin', 'sas_staff', 'coordinator']);

try {
    $db = api_db();
    $profile = $_SESSION['role'] === 'coordinator' ? api_user_profile($db, 'coordinators') : null;
    if ($_SESSION['role'] === 'coordinator' && !$profile) {
        api_json(['success' => false, 'message' => 'Coordinator profile is missing.'], 403);
    }
    $coordinatorId = $profile['id'] ?? null;
    $sql = 'SELECT s.student_number, CONCAT_WS(" ", u.first_name, u.last_name) AS student_name,
                   s.course, s.year_level, s.ojt_status, s.hours_required, s.hours_rendered,
                   c.name AS company_name, p.status AS placement_status, p.start_date, p.end_date
            FROM students s
            JOIN users u ON u.id = s.user_id
            LEFT JOIN ojt_placements p ON p.student_id = s.id AND p.status != "terminated"
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
    $rows = $stmt->fetchAll();

    if (($_GET['format'] ?? '') === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="ojt-student-report.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Student number', 'Student name', 'Course', 'Year', 'OJT status', 'Required hours', 'Rendered hours', 'Company', 'Placement status', 'Start date', 'End date']);
        foreach ($rows as $row) {
            fputcsv($output, array_values($row));
        }
        fclose($output);
        exit();
    }
    api_json(['success' => true, 'data' => $rows, 'count' => count($rows)]);
} catch (Throwable $e) {
    error_log('Report controller failed: ' . $e->getMessage());
    api_json(['success' => false, 'message' => 'Unable to generate the report.'], 500);
}