<?php
require_once __DIR__ . '/../shared/api.php';
api_require_roles(['supervisor', 'company']);

try {
    $db = api_db();
    $supervisor = api_user_profile($db, 'supervisors');
    if (!$supervisor) {
        api_json(['success' => false, 'message' => 'Company supervisor profile is missing.'], 403);
    }
    $companyId = (int) $supervisor['company_id'];
    $action = $_GET['action'] ?? 'dashboard';

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if ($action === 'profile') {
            $stmt = $db->prepare('SELECT * FROM companies WHERE id = :id');
            $stmt->execute(['id' => $companyId]);
            api_json(['success' => true, 'data' => $stmt->fetch()]);
        }
        if ($action === 'assigned' || $action === 'hours') {
            $stmt = $db->prepare(
                'SELECT s.id AS student_id, s.student_number, s.course, s.year_level,
                        s.hours_required, s.hours_rendered, s.ojt_status,
                        CONCAT_WS(" ", u.first_name, u.last_name) AS student_name,
                        p.id AS placement_id, p.status AS placement_status, p.start_date, p.end_date
                 FROM ojt_placements p JOIN students s ON s.id = p.student_id
                 JOIN users u ON u.id = s.user_id
                 WHERE p.supervisor_id = :supervisor_id ORDER BY u.last_name, u.first_name'
            );
            $stmt->execute(['supervisor_id' => $supervisor['id']]);
            api_json(['success' => true, 'data' => $stmt->fetchAll()]);
        }
        $stmt = $db->prepare(
            'SELECT COUNT(*) AS students,
                    SUM(p.status = "ongoing") AS ongoing,
                    (SELECT COUNT(*) FROM attendance a JOIN ojt_placements p2 ON p2.id = a.placement_id
                     WHERE p2.supervisor_id = :attendance_supervisor_id AND a.supervisor_approval = "pending") AS pending_attendance
             FROM ojt_placements p WHERE p.supervisor_id = :placement_supervisor_id'
        );
        $stmt->execute([
            'attendance_supervisor_id' => $supervisor['id'],
            'placement_supervisor_id' => $supervisor['id'],
        ]);
        api_json(['success' => true, 'data' => $stmt->fetch()]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $action !== 'profile') {
        api_json(['success' => false, 'message' => 'Method not allowed.'], 405);
    }
    api_require_csrf();
    $input = api_input();
    $name = trim($input['name'] ?? '');
    $address = trim($input['address'] ?? '');
    $contactEmail = trim($input['contact_email'] ?? '');
    $website = trim($input['website'] ?? '');
    if (!$name || !$address) {
        api_json(['success' => false, 'message' => 'Company name and address are required.'], 422);
    }
    if (($contactEmail !== '' && !filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) ||
        ($website !== '' && !filter_var($website, FILTER_VALIDATE_URL))) {
        api_json(['success' => false, 'message' => 'Enter a valid contact email and website URL.'], 422);
    }
    $stmt = $db->prepare(
        'UPDATE companies SET name = :name, industry = :industry, address = :address,
         contact_person = :contact_person, contact_email = :contact_email, contact_phone = :contact_phone,
         website = :website WHERE id = :id'
    );
    $stmt->execute([
        'name' => $name,
        'industry' => trim($input['industry'] ?? '') ?: null,
        'address' => $address,
        'contact_person' => trim($input['contact_person'] ?? '') ?: null,
        'contact_email' => $contactEmail ?: null,
        'contact_phone' => trim($input['contact_phone'] ?? '') ?: null,
        'website' => $website ?: null,
        'id' => $companyId,
    ]);
    api_json(['success' => true, 'message' => 'Company profile updated.']);
} catch (Throwable $e) {
    error_log('Company portal failed: ' . $e->getMessage());
    api_json(['success' => false, 'message' => 'Unable to process the company request.'], 500);
}
