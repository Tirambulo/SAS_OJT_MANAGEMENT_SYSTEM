<?php
require_once __DIR__ . '/../shared/api.php';
api_require_roles(['student']);

try {
    $db = api_db();
    $student = api_user_profile($db, 'students');
    if (!$student) {
        api_json(['success' => false, 'message' => 'Student profile is missing.'], 403);
    }
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $db->prepare(
            'SELECT e.*, CONCAT_WS(" ", u.first_name, u.last_name) AS evaluator_name
             FROM evaluations e
             JOIN ojt_placements p ON p.id = e.placement_id
             JOIN users u ON u.id = e.evaluator_id
             WHERE p.student_id = :student_id ORDER BY e.created_at DESC'
        );
        $stmt->execute(['student_id' => $student['id']]);
        api_json(['success' => true, 'data' => $stmt->fetchAll()]);
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        api_json(['success' => false, 'message' => 'Method not allowed.'], 405);
    }
    api_require_csrf();
    $input = api_input();
    $ratings = [];
    foreach (['performance_rating', 'communication_rating', 'punctuality_rating', 'work_quality_rating'] as $field) {
        $rating = filter_var($input[$field] ?? null, FILTER_VALIDATE_FLOAT);
        if ($rating === false || $rating < 1 || $rating > 5) {
            api_json(['success' => false, 'message' => 'All scores must be between 1 and 5.'], 422);
        }
        $ratings[$field] = $rating;
    }
    $stmt = $db->prepare(
        'SELECT id FROM ojt_placements WHERE student_id = :student_id
         AND status IN ("ongoing", "completed") ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute(['student_id' => $student['id']]);
    $placementId = $stmt->fetchColumn();
    if (!$placementId) {
        api_json(['success' => false, 'message' => 'You need an active or completed placement before submitting feedback.'], 422);
    }
    $stmt = $db->prepare(
        'INSERT INTO evaluations (placement_id, evaluator_id, evaluator_role, period, performance_rating,
         communication_rating, punctuality_rating, work_quality_rating, overall_score, comments)
         VALUES (:placement_id, :evaluator_id, "student", "Company feedback", :performance,
         :communication, :punctuality, :quality, :score, :comments)'
    );
    $stmt->execute([
        'placement_id' => $placementId,
        'evaluator_id' => $_SESSION['user_id'],
        'performance' => $ratings['performance_rating'],
        'communication' => $ratings['communication_rating'],
        'punctuality' => $ratings['punctuality_rating'],
        'quality' => $ratings['work_quality_rating'],
        'score' => array_sum($ratings) / count($ratings),
        'comments' => trim($input['comments'] ?? '') ?: null,
    ]);
    api_json(['success' => true, 'message' => 'Company feedback submitted.'], 201);
} catch (Throwable $e) {
    error_log('Student evaluation controller failed: ' . $e->getMessage());
    api_json(['success' => false, 'message' => 'Unable to process the evaluation.'], 500);
}