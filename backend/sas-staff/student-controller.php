<?php
require_once __DIR__ . '/../shared/api.php';
api_require_roles(['admin', 'sas_staff', 'coordinator']);

try {
    $db = api_db();
    $search = trim($_GET['search'] ?? '');
    $status = trim($_GET['status'] ?? '');
    $sql = 'SELECT s.id, s.student_number, s.course, s.year_level, s.section, s.ojt_status,
                   s.hours_required, s.hours_rendered, u.id AS user_id, u.email, u.first_name,
                   u.last_name, u.status AS account_status, c.department AS coordinator_department
            FROM students s JOIN users u ON u.id = s.user_id
            LEFT JOIN coordinators c ON c.id = s.coordinator_id
    WHERE (:search = "" OR s.student_number LIKE :student_term
           OR u.first_name LIKE :first_term OR u.last_name LIKE :last_term)
              AND (:status_filter = "" OR s.ojt_status = :ojt_status)
            ORDER BY u.last_name, u.first_name LIMIT 500';
    $stmt = $db->prepare($sql);
    $term = '%' . $search . '%';
    $stmt->execute([
        'search' => $search,
        'student_term' => $term,
        'first_term' => $term,
        'last_term' => $term,
        'status_filter' => $status,
        'ojt_status' => $status,
    ]);
    api_json(['success' => true, 'data' => $stmt->fetchAll()]);
} catch (Throwable $e) {
    error_log('Student records controller failed: ' . $e->getMessage());
    api_json(['success' => false, 'message' => 'Unable to load student records.'], 500);
}