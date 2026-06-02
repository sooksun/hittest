<?php
// GET /api/sessions/{sessionId}/summary
// $sessionId is set by the router in index.php
// Returns SessionSummary {sessionId,totalScore,accuracy,correctCount,totalCount,xpEarned,weakWords,durationSeconds}

$stmt = $pdo->prepare('SELECT * FROM game_training_sessions WHERE id = ? LIMIT 1');
$stmt->execute([$sessionId]);
$session = $stmt->fetch();

if (!$session) {
    json_response(['error' => true, 'message' => 'Session not found'], 404);
}

$total   = (int)$session['total_tasks'];
$correct = (int)$session['correct_count'];
$score   = (int)$session['total_score'];
$accuracy = $total > 0 ? round($correct / $total, 2) : 0.0;

$started = new DateTime($session['started_at']);
$ended   = $session['ended_at'] ? new DateTime($session['ended_at']) : new DateTime();
$duration = max(0, (int)$ended->getTimestamp() - (int)$started->getTimestamp());

json_response([
    'sessionId'       => $sessionId,
    'totalScore'      => $score,
    'accuracy'        => $accuracy,
    'correctCount'    => $correct,
    'totalCount'      => $total,
    'xpEarned'        => $correct * 10,
    'weakWords'       => [],
    'durationSeconds' => $duration,
]);
