<?php
require_once __DIR__ . '/../shared/api.php';
api_require_roles(['supervisor', 'company']);

try {
    $db = api_db();
    $supervisor = api_user_profile($db, 'supervisors');
    if (!$supervisor) {
        api_json(['success' => false, 'message' => 'Your account is not linked to a company supervisor profile.'], 403);
    }
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if (($_GET['action'] ?? '') === 'options') {
            $stmt = $db->prepare(
                'SELECT p.id AS placement_id, s.student_number,
                        CONCAT_WS(" ", u.first_name, u.last_name) AS student_name
                 FROM ojt_placements p JOIN students s ON s.id = p.student_id
                 JOIN users u ON u.id = s.user_id
                 WHERE p.supervisor_id = :supervisor_id AND p.status IN ("approved", "ongoing", "completed")
                 ORDER BY u.last_name, u.first_name'
            );
            $stmt->execute(['supervisor_id' => $supervisor['id']]);
            api_json(['success' => true, 'data' => $stmt->fetchAll()]);
        }
        $stmt = $db->prepare(
            'SELECT e.*, CONCAT_WS(" ", u.first_name, u.last_name) AS student_name,
                    s.student_number, p.id AS placement_id
             FROM evaluations e
             JOIN ojt_placements p ON p.id = e.placement_id
             JOIN students s ON s.id = p.student_id
             JOIN users u ON u.id = s.user_id
             WHERE p.supervisor_id = :supervisor_id AND e.evaluator_role = "supervisor"
             ORDER BY e.created_at DESC'
        );
        $stmt->execute(['supervisor_id' => $supervisor['id']]);
        api_json(['success' => true, 'data' => $stmt->fetchAll()]);
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        api_json(['success' => false, 'message' => 'Method not allowed.'], 405);
    }
    api_require_csrf();
    $input = api_input();
    $placementId = filter_var($input['placement_id'] ?? null, FILTER_VALIDATE_INT);
    $ratings = [];
    foreach (['performance_rating', 'communication_rating', 'punctuality_rating', 'work_quality_rating'] as $field) {
        $rating = filter_var($input[$field] ?? null, FILTER_VALIDATE_FLOAT);
        if ($rating === false || $rating < 1 || $rating > 5) {
            api_json(['success' => false, 'message' => 'All evaluation scores must be between 1 and 5.'], 422);
        }
        $ratings[$field] = $rating;
    }
    $stmt = $db->prepare('SELECT id FROM ojt_placements WHERE id = :id AND supervisor_id = :supervisor_id');
    $stmt->execute(['id' => $placementId, 'supervisor_id' => $supervisor['id']]);
    if (!$stmt->fetchColumn()) {
        api_json(['success' => false, 'message' => 'Placement not found for this supervisor.'], 403);
    }
    $score = array_sum($ratings) / count($ratings);
    $stmt = $db->prepare(
        'INSERT INTO evaluations (placement_id, evaluator_id, evaluator_role, period, performance_rating,
          communication_rating, punctuality_rating, work_quality_rating, overall_score, comments)
         VALUES (:placement_id, :evaluator_id, "supervisor", :period, :performance, :communication,
          :punctuality, :quality, :score, :comments)'
    );
    $stmt->execute([
        'placement_id' => $placementId,
        'evaluator_id' => $_SESSION['user_id'],
        'period' => trim($input['period'] ?? '') ?: 'Final',
        'performance' => $ratings['performance_rating'],
        'communication' => $ratings['communication_rating'],
        'punctuality' => $ratings['punctuality_rating'],
        'quality' => $ratings['work_quality_rating'],
        'score' => $score,
        'comments' => trim($input['comments'] ?? '') ?: null,
    ]);
    api_log($db, 'evaluation.created', 'Submitted supervisor evaluation for placement ' . $placementId);
    api_json(['success' => true, 'message' => 'Evaluation submitted.'], 201);
} catch (Throwable $e) {
    error_log('Feedback controller failed: ' . $e->getMessage());
    api_json(['success' => false, 'message' => 'Unable to process the evaluation.'], 500);
}