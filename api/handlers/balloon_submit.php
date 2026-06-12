<?php
// POST /api/balloon/submit  {score, levelsCompleted, difficulty, hearts, gradeLevel}
// Returns {ok:true, attemptId, message}
$body            = json_decode(file_get_contents('php://input'), true) ?: [];
$score           = game_clamp_score($pdo, $me, 'balloon', (int)($body['score'] ?? 0));
$levelsCompleted = max(0, min(10, (int)($body['levelsCompleted'] ?? 0)));
$difficulty      = max(1, min(3,  (int)($body['difficulty']      ?? 1)));
$hearts          = max(0, min(5,  (int)($body['hearts']          ?? 0)));
$gradeLevel      = max(1, min(6,  (int)($body['gradeLevel']      ?? $me['class_id'] ?? 1)));

$attemptId = game_result_save($pdo, $me, 'balloon', $gradeLevel, $difficulty, $score,
    ['levelsCompleted' => $levelsCompleted, 'hearts' => $hearts]);

json_response(['ok' => true, 'attemptId' => $attemptId, 'message' => 'Game result saved successfully']);
