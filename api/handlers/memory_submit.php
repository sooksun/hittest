<?php
// POST /api/memory/submit  {score, moves, timeElapsed, difficulty, gradeLevel}
// Returns {ok:true, attemptId}
$body        = json_decode(file_get_contents('php://input'), true) ?: [];
$score       = (int)($body['score']       ?? 0);
$moves       = (int)($body['moves']       ?? 0);
$timeElapsed = (int)($body['timeElapsed'] ?? 0);
$difficulty  = max(1, min(3, (int)($body['difficulty'] ?? 1)));
$gradeLevel  = max(1, min(6, (int)($body['gradeLevel'] ?? $me['class_id'] ?? 1)));

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
    'memory',
    $gradeLevel,
    $difficulty,
    $score,
    json_encode(['moves' => $moves, 'timeElapsed' => $timeElapsed], JSON_UNESCAPED_UNICODE),
]);

json_response(['ok' => true, 'attemptId' => (int)$pdo->lastInsertId()]);
