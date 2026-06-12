<?php
// POST /api/memory/submit  {score, moves, timeElapsed, difficulty, gradeLevel}
// Returns {ok:true, attemptId}
$body        = json_decode(file_get_contents('php://input'), true) ?: [];
$score       = game_clamp_score($pdo, $me, 'memory', (int)($body['score'] ?? 0));
$moves       = max(0, min(9999, (int)($body['moves']       ?? 0)));
$timeElapsed = max(0, min(3600, (int)($body['timeElapsed'] ?? 0)));
$difficulty  = max(1, min(3,    (int)($body['difficulty']  ?? 1)));
$gradeLevel  = max(1, min(6,    (int)($body['gradeLevel']  ?? $me['class_id'] ?? 1)));

$attemptId = game_result_save($pdo, $me, 'memory', $gradeLevel, $difficulty, $score,
    ['moves' => $moves, 'timeElapsed' => $timeElapsed]);

json_response(['ok' => true, 'attemptId' => $attemptId]);
