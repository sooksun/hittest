<?php
// POST /api/tasks/{taskId}/submit
// $taskId is set by the router in index.php
// {sessionId, selectedChoiceId?, typedText?, speechConfidence?}
// Returns TaskResult {taskId,isCorrect,score,baseScore,speedBonus,criticalBonus,feedback,xpEarned,elapsedMs}
//
// Hardening:
//  • elapsed time is computed SERVER-SIDE (now − previous submission, or session
//    start) — client-supplied timeSpentMs is ignored entirely.
//  • each task claimed via UNIQUE(session_id, task_uuid) → no double-scoring;
//    a duplicate submit replays the original result (HTTP 200, duplicate:true).
//  • whole read-modify-write runs in a transaction with the session row locked.

$body      = json_decode(file_get_contents('php://input'), true) ?: [];
$sessionId = (string)($body['sessionId'] ?? '');
$choiceId  = (string)($body['selectedChoiceId'] ?? '');
$typed     = trim((string)($body['typedText'] ?? ''));
// speechConfidence is client-reported and MUST NOT drive pass/fail. It is read
// (and intentionally ignored) so the API contract stays stable; correctness for
// S001 uses the typed transcript compared server-side instead.
$speechConf = (float)($body['speechConfidence'] ?? 0.0);
unset($speechConf); // not trusted — drop it so it can never leak into scoring

if (!$sessionId || !$taskId) {
    json_response(['error' => true, 'message' => 'Missing sessionId or taskId'], 400);
}

$pdo->beginTransaction();

// ── Load + lock session — ownership enforced ─────────────────────────────────
$stmt = $pdo->prepare(
    'SELECT id, tasks_json, total_tasks, started_at
     FROM game_training_sessions
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

// ── Find task in session ──────────────────────────────────────────────────────
$tasks = json_decode($session['tasks_json'], true) ?: [];
$task  = null;
foreach ($tasks as $t) {
    if (($t['taskId'] ?? '') === $taskId) { $task = $t; break; }
}
if (!$task) {
    $pdo->rollBack();
    json_response(['error' => true, 'message' => 'Task not found'], 404);
}

// ── Server-side elapsed time (anti-cheat) ────────────────────────────────────
// วัดฝั่ง server เท่านั้น: ตั้งแต่ submit ของ task ก่อนหน้า (หรือ session start ถ้าเป็น task แรก)
// จนถึงตอนนี้ — ไม่เชื่อ timeSpentMs จาก client (จะโกงให้เร็วเพื่อรีด speed bonus ได้).
// ค่านี้เป็น "ขอบบน" ของเวลาคิดจริง (รวมเวลาดูผล task ก่อนหน้าเล็กน้อย) จึงให้โบนัสอย่าง
// อนุรักษ์นิยม — โกงให้ได้โบนัสเกินจริงไม่ได้. รวมเป็น query เดียว (เดิมยิง 2 ครั้ง).
$elapsedStmt = $pdo->prepare(
    'SELECT GREATEST(0, TIMESTAMPDIFF(MICROSECOND,
        COALESCE((SELECT MAX(submitted_at) FROM game_task_submissions WHERE session_id = ?), ?),
        NOW(3)))'
);
$elapsedStmt->execute([$sessionId, $session['started_at']]);
$elapsedMs = (int)floor(((int)$elapsedStmt->fetchColumn()) / 1000);

// ── Evaluate correctness ──────────────────────────────────────────────────────
$isCorrect = false;
$tmpl = $task['templateKey'] ?? '';

if (in_array($tmpl, ['W001_FILL_MISSING_CHAR', 'W003_TYPE_WORD'])) {
    $expected  = $task['config']['expectedText'] ?? $task['config']['expectedWord'] ?? $task['wordText'];
    $isCorrect = mb_strtolower(trim($typed)) === mb_strtolower(trim($expected));
} elseif ($tmpl === 'S001_RECORD_PRONUNCIATION') {
    // ไม่เชื่อ speechConfidence ของ client — ตรวจ "ข้อความ" ที่ STT ถอดได้ฝั่ง server แบบยืดหยุ่น
    // (NFC + ไม่สนวรรณยุกต์ + รองรับคำพ่วง) เพื่อให้ออกเสียงถูกได้คะแนนจริง ไม่ใช่ต้องสะกดเป๊ะ
    $expected  = (string)($task['config']['expectedText'] ?? $task['wordText'] ?? '');
    $isCorrect = thai_speech_match($typed, $expected);
} else {
    // Choice-based (L001, R001, ...)
    foreach ($task['choices'] as $ch) {
        if (($ch['id'] ?? '') === $choiceId && !empty($ch['isCorrect'])) {
            $isCorrect = true;
            break;
        }
    }
}

// ── Score (speed bonus from SERVER elapsed) ───────────────────────────────────
$baseScore  = $isCorrect ? 100 : 0;
$timerMs    = (int)($task['timerMs'] ?? 15000);
$speedBonus = 0;
if ($isCorrect && $timerMs > 0 && $elapsedMs < $timerMs) {
    $speedBonus = (int)min(50, round(50 * (1 - $elapsedMs / $timerMs)));
}
$total    = $baseScore + $speedBonus;
$xp       = $isCorrect ? 10 : 0;
$feedback = $isCorrect ? 'ถูกต้อง! เก่งมาก' : 'ลองใหม่นะ';

// ── Claim this task (UNIQUE constraint rejects duplicates) ────────────────────
try {
    $pdo->prepare(
        'INSERT INTO game_task_submissions
            (session_id, task_uuid, is_correct, score, base_score, speed_bonus, elapsed_ms)
         VALUES (?,?,?,?,?,?,?)'
    )->execute([$sessionId, $taskId, $isCorrect ? 1 : 0, $total, $baseScore, $speedBonus, $elapsedMs]);
} catch (PDOException $e) {
    if (($e->errorInfo[1] ?? 0) === 1062) {           // ER_DUP_ENTRY → already submitted
        // Idempotent replay: a retry / double-click returns the ORIGINAL result
        // (HTTP 200, same TaskResult shape) instead of a 4xx the game client would
        // surface as a hard error. Scores are NOT re-counted — the row already exists.
        $pdo->rollBack();
        $prevStmt = $pdo->prepare(
            'SELECT is_correct, score, base_score, speed_bonus, elapsed_ms
             FROM game_task_submissions WHERE session_id = ? AND task_uuid = ? LIMIT 1'
        );
        $prevStmt->execute([$sessionId, $taskId]);
        $prev = $prevStmt->fetch() ?: [];
        $wasCorrect = (bool)($prev['is_correct'] ?? false);

        audit_log_event($pdo, [
            'sc_id' => $me['sc_id'], 'stuid' => $me['stuid'], 'action' => 'duplicate_submit',
            'entity_type' => 'training_task', 'entity_id' => $taskId, 'status_code' => 200,
            'meta_json' => ['sessionId' => $sessionId],
        ]);
        json_response([
            'taskId'        => $taskId,
            'isCorrect'     => $wasCorrect,
            'score'         => (int)($prev['score'] ?? 0),
            'baseScore'     => (int)($prev['base_score'] ?? 0),
            'speedBonus'    => (int)($prev['speed_bonus'] ?? 0),
            'criticalBonus' => 0,
            'feedback'      => $wasCorrect ? 'ถูกต้อง! เก่งมาก' : 'ลองใหม่นะ',
            'xpEarned'      => $wasCorrect ? 10 : 0,
            'elapsedMs'     => (int)($prev['elapsed_ms'] ?? 0),
            'duplicate'     => true,
        ]);
    }
    $pdo->rollBack();
    throw $e;
}

// ── Update session aggregates ─────────────────────────────────────────────────
$pdo->prepare(
    'UPDATE game_training_sessions
     SET correct_count = correct_count + ?, total_score = total_score + ?
     WHERE id = ? AND stuid = ? AND sc_id = ?'
)->execute([$isCorrect ? 1 : 0, $total, $sessionId, $me['stuid'], $me['sc_id']]);

$pdo->commit();

json_response([
    'taskId'        => $taskId,
    'isCorrect'     => $isCorrect,
    'score'         => $total,
    'baseScore'     => $baseScore,
    'speedBonus'    => $speedBonus,
    'criticalBonus' => 0,
    'feedback'      => $feedback,
    'xpEarned'      => $xp,
    'elapsedMs'     => $elapsedMs,
]);
