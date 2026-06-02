<?php
// POST /api/sessions/{sessionId}/end
// $sessionId is set by the router in index.php
// Marks session ended and saves to game_results

$stmt = $pdo->prepare('SELECT * FROM game_training_sessions WHERE id = ? LIMIT 1');
$stmt->execute([$sessionId]);
$session = $stmt->fetch();

if (!$session) {
    json_response(['error' => true, 'message' => 'Session not found'], 404);
}

// Mark ended
$pdo->prepare('UPDATE game_training_sessions SET ended_at = NOW() WHERE id = ? AND ended_at IS NULL')
    ->execute([$sessionId]);

// Save to game_results
$pdo->prepare(
    'INSERT INTO game_results
        (sc_id, stuid, stuname, class_id, game, grade, difficulty, score, stats_json, created_at)
     VALUES (?,?,?,?,?,?,?,?,?,NOW())'
)->execute([
    $me['sc_id'],
    $me['stuid'],
    $me['stuname'] ?? '',
    (int)($me['class_id'] ?? $session['grade_level']),
    'training',
    (int)$session['grade_level'],
    (int)$session['difficulty'],
    (int)$session['total_score'],
    json_encode(['correctCount' => $session['correct_count'], 'totalTasks' => $session['total_tasks']], JSON_UNESCAPED_UNICODE),
]);

json_response(['success' => true]);
