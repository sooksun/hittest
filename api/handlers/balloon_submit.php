<?php
// POST /api/balloon/submit  {score, levelsCompleted, difficulty, hearts, gradeLevel}
// Returns {ok:true, attemptId, message}
$body           = json_decode(file_get_contents('php://input'), true) ?: [];
$score          = (int)($body['score']          ?? 0);
$levelsCompleted= (int)($body['levelsCompleted']?? 0);
$difficulty     = (int)($body['difficulty']     ?? 1);
$hearts         = (int)($body['hearts']         ?? 0);
$gradeLevel     = (int)($body['gradeLevel']     ?? $me['class_id'] ?? 1);

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
