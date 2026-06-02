<?php
// GET /api/sessions/{sessionId}/summary
// $sessionId is set by the router in index.php
// Returns SessionSummary {sessionId,totalScore,accuracy,correctCount,totalCount,xpEarned,weakWords,durationSeconds}

// ── Load session — ownership enforced ────────────────────────────────────────
$stmt = $pdo->prepare(
    'SELECT * FROM game_training_sessions
     WHERE id = ? AND stuid = ? AND sc_id = ? LIMIT 1'
);
$stmt->execute([$sessionId, $me['stuid'], $me['sc_id']]);
$session = $stmt->fetch();

if (!$session) {
    json_response(['error' => true, 'message' => 'Session not found'], 404);
}

$total    = (int)$session['total_tasks'];
$correct  = (int)$session['correct_count'];
$score    = (int)$session['total_score'];
$accuracy = $total > 0 ? round($correct / $total, 2) : 0.0;

// Guard against NULL or malformed timestamps
$startedRaw = $session['started_at'] ?? '';
$endedRaw   = $session['ended_at']   ?? '';
$started  = $startedRaw ? (new DateTime($startedRaw)) : new DateTime();
$ended    = $endedRaw   ? (new DateTime($endedRaw))   : new DateTime();
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
