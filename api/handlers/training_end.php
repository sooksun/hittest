<?php
// POST /api/sessions/{sessionId}/end
// $sessionId is set by the router in index.php
// Marks session ended and saves to game_results (idempotent + race-safe)
//
// Hardening: the session row is locked FOR UPDATE inside a transaction, so two
// concurrent / double-clicked end calls are serialized — only the first sees
// ended_at = NULL and writes exactly one game_results row.

$pdo->beginTransaction();

// ── Load + lock session — ownership enforced ─────────────────────────────────
$stmt = $pdo->prepare(
    'SELECT * FROM game_training_sessions
     WHERE id = ? AND stuid = ? AND sc_id = ? LIMIT 1 FOR UPDATE'
);
$stmt->execute([$sessionId, $me['stuid'], $me['sc_id']]);
$session = $stmt->fetch();

if (!$session) {
    $pdo->rollBack();
    audit_log_event($pdo, [
        'sc_id' => $me['sc_id'], 'stuid' => $me['stuid'], 'action' => 'ownership_denied',
        'entity_type' => 'training_session', 'entity_id' => $sessionId, 'status_code' => 404,
    ]);
    json_response(['error' => true, 'message' => 'Session not found'], 404);
}

// ── Already ended → idempotent no-op (no second game_results row) ─────────────
if ($session['ended_at'] !== null) {
    $pdo->commit();
    json_response(['success' => true, 'alreadyEnded' => true]);
}

// ── First end: mark ended + write the single result row, atomically ──────────
$pdo->prepare(
    'UPDATE game_training_sessions SET ended_at = NOW()
     WHERE id = ? AND stuid = ? AND sc_id = ?'
)->execute([$sessionId, $me['stuid'], $me['sc_id']]);

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
    json_encode([
        'correctCount' => $session['correct_count'],
        'totalTasks'   => $session['total_tasks'],
    ], JSON_UNESCAPED_UNICODE),
]);

$pdo->commit();

json_response(['success' => true]);
