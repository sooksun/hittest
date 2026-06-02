<?php
// POST /api/bubble/submit  {score, wordsCompleted, lives, difficulty, gradeLevel, won}
// Returns {ok:true, attemptId}
$body           = json_decode(file_get_contents('php://input'), true) ?: [];
$rawScore       = (int)($body['score'] ?? 0);
$score          = max(0, min(9999,  $rawScore));
$wordsCompleted = max(0, min(200,   (int)($body['wordsCompleted'] ?? 0)));
$lives          = max(0, min(5,     (int)($body['lives']          ?? 0)));
$difficulty     = max(1, min(3,     (int)($body['difficulty']     ?? 1)));
$gradeLevel     = max(1, min(6,     (int)($body['gradeLevel']     ?? $me['class_id'] ?? 1)));
$won            = !empty($body['won']);

if ($rawScore !== $score) {
    audit_log_event($pdo, [
        'sc_id' => $me['sc_id'], 'stuid' => $me['stuid'], 'action' => 'score_clamped',
        'entity_type' => 'bubble', 'meta_json' => ['raw' => $rawScore, 'clamped' => $score],
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
    'bubble',
    $gradeLevel,
    $difficulty,
    $score,
    json_encode(['wordsCompleted' => $wordsCompleted, 'lives' => $lives, 'won' => $won], JSON_UNESCAPED_UNICODE),
]);

json_response(['ok' => true, 'attemptId' => (int)$pdo->lastInsertId()]);
