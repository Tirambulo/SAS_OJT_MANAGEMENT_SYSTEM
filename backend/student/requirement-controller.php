<?php
require_once __DIR__ . '/../shared/api.php';
api_require_roles(['student']);

try {
    $db = api_db();
    $student = api_user_profile($db, 'students');
    if (!$student) {
        api_json(['success' => false, 'message' => 'Student profile is missing.'], 403);
    }
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'download') {
        $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
        $stmt = $db->prepare('SELECT file_path, file_name FROM requirements WHERE id = :id AND student_id = :student_id');
        $stmt->execute(['id' => $id, 'student_id' => $student['id']]);
        $file = $stmt->fetch();
        $path = $file ? realpath(__DIR__ . '/../../' . $file['file_path']) : false;
        $root = realpath(__DIR__ . '/../../uploads/requirements');
        if (!$path || !$root || strpos($path, $root . DIRECTORY_SEPARATOR) !== 0 || !is_file($path)) {
            api_json(['success' => false, 'message' => 'File not found.'], 404);
        }
        header('Content-Type: application/octet-stream');
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', basename($file['file_name'])) . '"');
        readfile($path);
        exit();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $db->prepare(
            'SELECT rt.id AS requirement_type_id, rt.title, rt.description, rt.is_mandatory,
                    r.id AS submission_id, r.id AS id, r.file_name, r.status, r.feedback,
                    r.submitted_at, r.reviewed_at
             FROM requirement_types rt
             LEFT JOIN requirements r ON r.requirement_type_id = rt.id AND r.student_id = :student_id
             ORDER BY rt.id'
        );
        $stmt->execute(['student_id' => $student['id']]);
        api_json(['success' => true, 'data' => $stmt->fetchAll()]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        api_json(['success' => false, 'message' => 'Method not allowed.'], 405);
    }
    api_require_csrf();
    $typeId = filter_var($_POST['requirement_type_id'] ?? null, FILTER_VALIDATE_INT);
    $upload = $_FILES['file'] ?? null;
    if (!$typeId || !$upload || $upload['error'] !== UPLOAD_ERR_OK || $upload['size'] < 1 || $upload['size'] > 5 * 1024 * 1024) {
        api_json(['success' => false, 'message' => 'Choose a requirement and a file no larger than 5 MB.'], 422);
    }
    $stmt = $db->prepare('SELECT id, title FROM requirement_types WHERE id = :id');
    $stmt->execute(['id' => $typeId]);
    $type = $stmt->fetch();
    if (!$type) {
        api_json(['success' => false, 'message' => 'Requirement type not found.'], 404);
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);
    $allowed = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
    ];
    if (!isset($allowed[$mime])) {
        api_json(['success' => false, 'message' => 'Only PDF, JPEG, PNG, DOC, or DOCX files are accepted.'], 415);
    }
    $directory = __DIR__ . '/../../uploads/requirements';
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
        api_json(['success' => false, 'message' => 'Upload storage is unavailable. Contact the administrator.'], 500);
    }
    $storedName = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($upload['tmp_name'], $directory . DIRECTORY_SEPARATOR . $storedName)) {
        api_json(['success' => false, 'message' => 'Unable to store the uploaded file.'], 500);
    }
    $relativePath = 'uploads/requirements/' . $storedName;
    $stmt = $db->prepare(
        'INSERT INTO requirements (student_id, requirement_type_id, title, file_path, file_name, file_size, status)
         VALUES (:student_id, :type_id, :title, :path, :file_name, :size, "pending")'
    );
    $stmt->execute([
        'student_id' => $student['id'],
        'type_id' => $typeId,
        'title' => $type['title'],
        'path' => $relativePath,
        'file_name' => basename($upload['name']),
        'size' => $upload['size'],
    ]);
    api_json(['success' => true, 'message' => 'Requirement uploaded for review.'], 201);
} catch (Throwable $e) {
    error_log('Requirement controller failed: ' . $e->getMessage());
    api_json(['success' => false, 'message' => 'Unable to process the requirement request.'], 500);
}