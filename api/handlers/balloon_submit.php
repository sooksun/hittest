<?php
// POST /api/balloon/submit  {score, levelsCompleted, difficulty, hearts, gradeLevel}
// Returns {ok:true, attemptId, message}
$body           = json_decode(file_get_contents('php://input'), true) ?: [];
$rawScore       = (int)($body['score'] ?? 0);
$score          = max(0, min(9999, $rawScore));
$levelsCompleted= max(0, min(10,   (int)($body['levelsCompleted']?? 0)));
$difficulty     = max(1, min(3,    (int)($body['difficulty']     ?? 1)));
$hearts         = max(0, min(5,    (int)($body['hearts']         ?? 0)));
$gradeLevel     = max(1, min(6,    (int)($body['gradeLevel']     ?? $me['class_id'] ?? 1)));

if ($rawScore !== $score) {
    audit_log_event($pdo, [
        'sc_id' => $me['sc_id'], 'stuid' => $me['stuid'], 'action' => 'score_clamped',
        'entity_type' => 'balloon', 'meta_json' => ['raw' => $rawScore, 'clamped' => $score],
    ]);
}

$stmt = $pdo->prepare(
    'INSERT INTO game_results
        (sc_id, stuid, stuname, class_id, game, grade, difficulty, score, stats_json, created_at)
     VALUES (?,?,?,?,?,?,?,?,?,NOW())'
);
$stmt->execute([
    $me['sc_id'],
    $me['stuid'],
    $me['stuname'] ?? '',
    (int)($me['class_id'] ?? $gradeLevel),
    'balloon',
    $gradeLevel,
    $difficulty,
    $score,
    json_encode(['levelsCompleted' => $levelsCompleted, 'hearts' => $hearts], JSON_UNESCAPED_UNICODE),
]);

json_response(['ok' => true, 'attemptId' => (int)$pdo->lastInsertId(), 'message' => 'Game result saved successfully']);
