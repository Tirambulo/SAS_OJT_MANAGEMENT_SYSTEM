<?php
require_once __DIR__ . '/../shared/api.php';
api_require_roles(['admin', 'sas_staff']);

try {
    $db = api_db();
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if (($_GET['action'] ?? '') === 'download') {
            $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
            $stmt = $db->prepare('SELECT file_path, file_name FROM requirements WHERE id = :id');
            $stmt->execute(['id' => $id]);
            $file = $stmt->fetch();
            $path = $file ? realpath(__DIR__ . '/../../' . $file['file_path']) : false;
            $root = realpath(__DIR__ . '/../../uploads/requirements');
            if (!$path || !$root || strpos($path, $root . DIRECTORY_SEPARATOR) !== 0 || !is_file($path)) {
                api_json(['success' => false, 'message' => 'File not found.'], 404);
            }
            $downloadName = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($file['file_name']));
            header('Content-Type: application/octet-stream');
            header('Content-Length: ' . filesize($path));
            header('Content-Disposition: attachment; filename="' . $downloadName . '"');
            readfile($path);
            exit();
        }
        $status = $_GET['status'] ?? '';
        $sql = 'SELECT r.*, rt.title AS requirement_type, s.student_number,
                       CONCAT_WS(" ", u.first_name, u.last_name) AS student_name
                FROM requirements r
                JOIN students s ON s.id = r.student_id
                JOIN users u ON u.id = s.user_id
                LEFT JOIN requirement_types rt ON rt.id = r.requirement_type_id';
        if (in_array($status, ['pending', 'approved', 'rejected'], true)) {
            $sql .= ' WHERE r.status = :status';
        }
        $sql .= ' ORDER BY r.submitted_at DESC LIMIT 500';
        $stmt = $db->prepare($sql);
        $stmt->execute(in_array($status, ['pending', 'approved', 'rejected'], true) ? ['status' => $status] : []);
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
        'UPDATE requirements SET status = :status, feedback = :feedback, reviewed_at = CURRENT_TIMESTAMP
         WHERE id = :id AND status = "pending"'
    );
    $stmt->execute(['status' => $status, 'feedback' => trim($input['feedback'] ?? '') ?: null, 'id' => $id]);
    if (!$stmt->rowCount()) {
        api_json(['success' => false, 'message' => 'Pending requirement not found.'], 404);
    }
    api_log($db, 'requirement.reviewed', 'Reviewed requirement ' . $id . ' as ' . $status);
    $stmt = $db->prepare(
        'INSERT INTO notifications (user_id, title, message, type)
         SELECT s.user_id, "Requirement reviewed", CONCAT("Your ", r.title, " was ", r.status, "."),
                IF(r.status = "approved", "success", "warning")
         FROM requirements r JOIN students s ON s.id = r.student_id WHERE r.id = :id'
    );
    $stmt->execute(['id' => $id]);
    api_json(['success' => true, 'message' => 'Requirement review saved.']);
} catch (Throwable $e) {
    error_log('Verification controller failed: ' . $e->getMessage());
    api_json(['success' => false, 'message' => 'Unable to process the requirement review.'], 500);
}