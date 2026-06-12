<?php
// POST /api/bubble/submit  {score, wordsCompleted, lives, difficulty, gradeLevel, won}
// Returns {ok:true, attemptId}
$body           = json_decode(file_get_contents('php://input'), true) ?: [];
$score          = game_clamp_score($pdo, $me, 'bubble', (int)($body['score'] ?? 0));
$wordsCompleted = max(0, min(200, (int)($body['wordsCompleted'] ?? 0)));
$lives          = max(0, min(5,   (int)($body['lives']          ?? 0)));
$difficulty     = max(1, min(3,   (int)($body['difficulty']     ?? 1)));
$gradeLevel     = max(1, min(6,   (int)($body['gradeLevel']     ?? $me['class_id'] ?? 1)));
$won            = !empty($body['won']);

$attemptId = game_result_save($pdo, $me, 'bubble', $gradeLevel, $difficulty, $score,
    ['wordsCompleted' => $wordsCompleted, 'lives' => $lives, 'won' => $won]);

json_response(['ok' => true, 'attemptId' => $attemptId]);
