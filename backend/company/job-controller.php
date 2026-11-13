<?php
require_once __DIR__ . '/../shared/api.php';
api_require_roles(['supervisor', 'company']);

try {
    $db = api_db();
    $supervisor = api_user_profile($db, 'supervisors');
    if (!$supervisor) {
        api_json(['success' => false, 'message' => 'Company supervisor profile is missing.'], 403);
    }
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $db->prepare('SELECT * FROM job_descriptions WHERE company_id = :company_id ORDER BY updated_at DESC');
        $stmt->execute(['company_id' => $supervisor['company_id']]);
        api_json(['success' => true, 'data' => $stmt->fetchAll()]);
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        api_json(['success' => false, 'message' => 'Method not allowed.'], 405);
    }
    api_require_csrf();
    $input = api_input();
    $action = $input['action'] ?? 'create';
    if ($action === 'status') {
        $id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
        $status = $input['status'] ?? '';
        if (!$id || !in_array($status, ['active', 'closed'], true)) {
            api_json(['success' => false, 'message' => 'Invalid job status.'], 422);
        }
        $stmt = $db->prepare('UPDATE job_descriptions SET status = :status WHERE id = :id AND company_id = :company_id');
        $stmt->execute(['status' => $status, 'id' => $id, 'company_id' => $supervisor['company_id']]);
        api_json(['success' => true, 'message' => 'Job status updated.']);
    }
    $title = trim($input['title'] ?? '');
    $description = trim($input['description'] ?? '');
    if (!$title || !$description) {
        api_json(['success' => false, 'message' => 'Job title and description are required.'], 422);
    }
    $stmt = $db->prepare(
        'INSERT INTO job_descriptions (company_id, supervisor_id, title, description, qualifications, location)
         VALUES (:company_id, :supervisor_id, :title, :description, :qualifications, :location)'
    );
    $stmt->execute([
        'company_id' => $supervisor['company_id'],
        'supervisor_id' => $supervisor['id'],
        'title' => $title,
        'description' => $description,
        'qualifications' => trim($input['qualifications'] ?? '') ?: null,
        'location' => trim($input['location'] ?? '') ?: null,
    ]);
    api_log($db, 'job.created', 'Published job description ' . $title);
    api_json(['success' => true, 'message' => 'Job description published.'], 201);
} catch (Throwable $e) {
    error_log('Job controller failed: ' . $e->getMessage());
    api_json(['success' => false, 'message' => 'Unable to process the job description.'], 500);
}
