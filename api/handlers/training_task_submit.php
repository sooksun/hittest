<?php
// POST /api/tasks/{taskId}/submit
// $taskId is set by the router in index.php
// {sessionId, selectedChoiceId?, typedText?, speechConfidence?, timeSpentMs}
// Returns TaskResult {taskId,isCorrect,score,baseScore,speedBonus,criticalBonus,feedback,xpEarned}

$body      = json_decode(file_get_contents('php://input'), true) ?: [];
$sessionId = (string)($body['sessionId'] ?? '');
$choiceId  = (string)($body['selectedChoiceId'] ?? '');
$typed     = trim((string)($body['typedText'] ?? ''));
$speechConf= (float)($body['speechConfidence'] ?? 0.0);
$timeMs    = max(0, (int)($body['timeSpentMs'] ?? 0));

if (!$sessionId || !$taskId) {
    json_response(['error' => true, 'message' => 'Missing sessionId or taskId'], 400);
}

// Load session
$stmt = $pdo->prepare('SELECT tasks_json, total_tasks FROM game_training_sessions WHERE id = ? LIMIT 1');
$stmt->execute([$sessionId]);
$session = $stmt->fetch();
if (!$session) {
    json_response(['error' => true, 'message' => 'Session not found'], 404);
}

$tasks = json_decode($session['tasks_json'], true) ?: [];
$task  = null;
foreach ($tasks as $t) {
    if (($t['taskId'] ?? '') === $taskId) { $task = $t; break; }
}
if (!$task) {
    json_response(['error' => true, 'message' => 'Task not found'], 404);
}

// ── Evaluate correctness ──────────────────────────────────────────────────────
$isCorrect = false;
$tmpl = $task['templateKey'] ?? '';

if (in_array($tmpl, ['W001_FILL_MISSING_CHAR', 'W003_TYPE_WORD'])) {
    $expected  = $task['config']['expectedText'] ?? $task['config']['expectedWord'] ?? $task['wordText'];
    $isCorrect = mb_strtolower(trim($typed)) === mb_strtolower(trim($expected));
} elseif ($tmpl === 'S001_RECORD_PRONUNCIATION') {
    $expected  = mb_strtolower(trim($task['config']['expectedText'] ?? $task['wordText']));
    $isCorrect = $speechConf >= 0.7 || mb_strtolower($typed) === $expected;
} else {
    // Choice-based (L001, R001, R002...)
    foreach ($task['choices'] as $ch) {
        if (($ch['id'] ?? '') === $choiceId && !empty($ch['isCorrect'])) {
            $isCorrect = true;
            break;
        }
    }
}

// ── Score ─────────────────────────────────────────────────────────────────────
$baseScore  = $isCorrect ? 100 : 0;
$timerMs    = (int)($task['timerMs'] ?? 15000);
$speedBonus = 0;
if ($isCorrect && $timerMs > 0 && $timeMs < 3000) {
    $speedBonus = (int)min(50, round(50 * (1 - $timeMs / $timerMs)));
}
$total   = $baseScore + $speedBonus;
$xp      = $isCorrect ? 10 : 0;
$feedback = $isCorrect ? 'ถูกต้อง! เก่งมาก' : 'ลองใหม่นะ';

// Update session aggregates
$pdo->prepare(
    'UPDATE game_training_sessions
     SET correct_count = correct_count + ?, total_score = total_score + ?
     WHERE id = ?'
)->execute([$isCorrect ? 1 : 0, $total, $sessionId]);

json_response([
    'taskId'        => $taskId,
    'isCorrect'     => $isCorrect,
    'score'         => $total,
    'baseScore'     => $baseScore,
    'speedBonus'    => $speedBonus,
    'criticalBonus' => 0,
    'feedback'      => $feedback,
    'xpEarned'      => $xp,
]);
