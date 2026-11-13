<?php
require_once __DIR__ . '/../shared/api.php';
api_require_roles(['supervisor', 'company', 'admin', 'coordinator']);

try {
    $db = api_db();
    $where = '';
    $params = [];
    if (in_array($_SESSION['role'], ['supervisor', 'company'], true)) {
        $supervisor = api_user_profile($db, 'supervisors');
        if (!$supervisor) {
            api_json(['success' => false, 'message' => 'Company supervisor profile is missing.'], 403);
        }
        $where = 'WHERE p.supervisor_id = :supervisor_id';
        $params['supervisor_id'] = $supervisor['id'];
    } elseif ($_SESSION['role'] === 'coordinator') {
        $coordinator = api_user_profile($db, 'coordinators');
        if (!$coordinator) {
            api_json(['success' => false, 'message' => 'Coordinator profile is missing.'], 403);
        }
        $where = 'WHERE p.coordinator_id = :coordinator_id';
        $params['coordinator_id'] = $coordinator['id'];
    }
    $stmt = $db->prepare(
        'SELECT e.id, e.period, e.overall_score, e.comments, e.created_at,
                e.evaluator_role, CONCAT_WS(" ", u.first_name, u.last_name) AS student_name,
                c.name AS company_name
         FROM evaluations e
         JOIN ojt_placements p ON p.id = e.placement_id
         JOIN students s ON s.id = p.student_id
         JOIN users u ON u.id = s.user_id
         JOIN companies c ON c.id = p.company_id ' . $where . '
         ORDER BY e.created_at DESC LIMIT 500'
    );
    $stmt->execute($params);
    $evaluations = $stmt->fetchAll();
    $scores = array_map(static function (array $row): float {
        return (float) $row['overall_score'];
    }, $evaluations);
    api_json([
        'success' => true,
        'data' => $evaluations,
        'summary' => [
            'count' => count($scores),
            'average' => $scores ? round(array_sum($scores) / count($scores), 2) : 0,
        ],
    ]);
} catch (Throwable $e) {
    error_log('Performance controller failed: ' . $e->getMessage());
    api_json(['success' => false, 'message' => 'Unable to load evaluation results.'], 500);
}