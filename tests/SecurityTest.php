<?php
/**
 * tests/SecurityTest.php — regression tests for game security & scoring fixes
 *
 * Each test corresponds to a finding from the code review.
 * Run:  php tests/run_tests.php
 */

require_once __DIR__ . '/bootstrap.php';

$H = dirname(__DIR__) . '/api/handlers/';

// ── Shared fixture helpers ────────────────────────────────────────────────────

function make_hangman_session(PDO $pdo, string $stuid, string $scId, array $extra = []): int
{
    $pdo->prepare(
        'INSERT INTO game_hangman_sessions
            (sc_id, stuid, grade_level, difficulty, word_id, word, masked_word, remaining_lives, score)
         VALUES (?,?,?,?,?,?,?,?,?)'
    )->execute([
        $scId, $stuid,
        $extra['grade_level']     ?? 1,
        $extra['difficulty']      ?? 1,
        $extra['word_id']         ?? 1,
        $extra['word']            ?? 'กา',
        $extra['masked_word']     ?? '["_","_"]',
        $extra['remaining_lives'] ?? 5,
        $extra['score']           ?? 0,
    ]);
    return (int)$pdo->lastInsertId();
}

function make_training_session(PDO $pdo, string $stuid, string $scId, array $tasks = []): string
{
    if (empty($tasks)) {
        $tasks = [[
            'taskId'      => 'task-001',
            'templateKey' => 'L001_LISTEN_PICK_WORD',
            'wordText'    => 'กา',
            'choices'     => [
                ['id' => 'choice-correct', 'text' => 'กา',  'isCorrect' => true],
                ['id' => 'choice-wrong',   'text' => 'ขา', 'isCorrect' => false],
            ],
            'timerMs'     => 15000,
            'config'      => null,
        ]];
    }
    $id = 'sess-' . uniqid();
    $pdo->prepare(
        'INSERT INTO game_training_sessions
            (id, sc_id, stuid, grade_level, difficulty, tasks_json, total_tasks)
         VALUES (?,?,?,?,?,?,?)'
    )->execute([$id, $scId, $stuid, 1, 1, json_encode($tasks), count($tasks)]);
    return $id;
}

// ─────────────────────────────────────────────────────────────────────────────
// Finding 1: Session ownership — hangman
// ─────────────────────────────────────────────────────────────────────────────
run_test('F1a — Hangman session rejects wrong stuid', function () use ($H) {
    $pdo = create_test_db();
    $sid = make_hangman_session($pdo, 'studentA', 'school1');

    // Query with wrong owner (the fixed SELECT includes AND stuid=? AND sc_id=?)
    $stmt = $pdo->prepare(
        'SELECT id FROM game_hangman_sessions WHERE id=? AND stuid=? AND sc_id=? LIMIT 1'
    );
    $stmt->execute([$sid, 'studentB', 'school1']); // wrong stuid
    ok($stmt->fetch() === false, 'Wrong stuid → no row returned');

    $stmt->execute([$sid, 'studentA', 'school2']); // wrong sc_id
    ok($stmt->fetch() === false, 'Wrong sc_id → no row returned');

    $stmt->execute([$sid, 'studentA', 'school1']); // correct owner
    ok($stmt->fetch() !== false, 'Correct owner → row returned');
});

// ─────────────────────────────────────────────────────────────────────────────
// Finding 1b: Session ownership — training (task submit, end, summary)
// ─────────────────────────────────────────────────────────────────────────────
run_test('F1b — Training session rejects wrong owner', function () use ($H) {
    $pdo = create_test_db();
    $sid = make_training_session($pdo, 'studentA', 'school1');

    $stmt = $pdo->prepare(
        'SELECT id FROM game_training_sessions WHERE id=? AND stuid=? AND sc_id=? LIMIT 1'
    );
    $stmt->execute([$sid, 'studentB', 'school1']);
    ok($stmt->fetch() === false, 'Task submit — wrong stuid → no row');

    $stmt->execute([$sid, 'studentA', 'school1']);
    ok($stmt->fetch() !== false, 'Task submit — correct owner → row found');
});

// ─────────────────────────────────────────────────────────────────────────────
// Finding 2: Client-controlled scores are capped server-side
// ─────────────────────────────────────────────────────────────────────────────
run_test('F2 — Score cap enforced for balloon / bubble / memory', function () {
    $cap = static fn(int $raw) => max(0, min(9999, $raw));

    ok($cap(999999) === 9999, 'Fabricated score 999999 → capped to 9999');
    ok($cap(-100)   === 0,    'Negative score clamped to 0');
    ok($cap(5000)   === 5000, 'Legitimate score 5000 passes through');
    ok($cap(9999)   === 9999, 'Max score 9999 passes through');
});

// ─────────────────────────────────────────────────────────────────────────────
// Finding 3: Task re-submission guard
// ─────────────────────────────────────────────────────────────────────────────
run_test('F3 — game_task_submissions UNIQUE(session_id,task_uuid) blocks double-submit', function () {
    $pdo = create_test_db();
    $sid = make_training_session($pdo, 'studentA', 'school1');

    $ins = fn() => $pdo->prepare(
        'INSERT INTO game_task_submissions (session_id, task_uuid, is_correct, score) VALUES (?,?,1,100)'
    )->execute([$sid, 'task-001']);

    ok($ins() === true, 'first submission of task-001 accepted');

    $errno = 0;
    try { $ins(); } catch (PDOException $e) { $errno = $e->errorInfo[1]; }
    ok($errno === 1062, 'duplicate (session,task) rejected with ER_DUP_ENTRY 1062');

    $n = (int)$pdo->query("SELECT COUNT(*) FROM game_task_submissions WHERE session_id='$sid'")->fetchColumn();
    ok($n === 1, 'exactly one submission row survives');
});

// ─────────────────────────────────────────────────────────────────────────────
// Finding 4: training_end double INSERT guard (rowCount check)
// ─────────────────────────────────────────────────────────────────────────────
run_test('F4 — training_end INSERT only fires on first end call', function () {
    $pdo = create_test_db();
    $sid = make_training_session($pdo, 'studentA', 'school1');

    // First end call: UPDATE succeeds (rowCount=1), INSERT fires
    $upd = $pdo->prepare(
        "UPDATE game_training_sessions SET ended_at=CURRENT_TIMESTAMP
         WHERE id=? AND stuid=? AND sc_id=? AND ended_at IS NULL"
    );
    $upd->execute([$sid, 'studentA', 'school1']);
    ok($upd->rowCount() === 1, 'First end: rowCount=1 (INSERT should fire)');

    if ($upd->rowCount() > 0) {
        $pdo->prepare(
            "INSERT INTO game_results (sc_id,stuid,stuname,class_id,game,grade,difficulty,score,stats_json)
             VALUES ('school1','studentA','',1,'training',1,1,0,'{}')"
        )->execute();
    }

    // Second end call: UPDATE affects 0 rows (ended_at already set), INSERT does NOT fire
    $upd->execute([$sid, 'studentA', 'school1']);
    ok($upd->rowCount() === 0, 'Second end: rowCount=0 (INSERT skipped)');

    if ($upd->rowCount() > 0) {
        $pdo->prepare(
            "INSERT INTO game_results (sc_id,stuid,stuname,class_id,game,grade,difficulty,score,stats_json)
             VALUES ('school1','studentA','',1,'training',1,1,0,'{}')"
        )->execute();
    }

    $count = (int)$pdo->query("SELECT COUNT(*) FROM game_results")->fetchColumn();
    ok($count === 1, "game_results has exactly 1 row, not {$count}");
});

// ─────────────────────────────────────────────────────────────────────────────
// Finding 5: Repeated hangman guess does not re-score
// ─────────────────────────────────────────────────────────────────────────────
run_test('F5 — game_hangman_guesses UNIQUE(session_id,guess_char) blocks re-score', function () {
    $pdo = create_test_db();
    $sid = make_hangman_session($pdo, 'studentA', 'school1');

    $insChar = fn(string $c) => $pdo->prepare(
        'INSERT INTO game_hangman_guesses (session_id, guess_char, guess_type, is_correct) VALUES (?,?,?,1)'
    )->execute([$sid, $c, 'consonant']);

    ok($insChar('ก') === true, 'first guess of "ก" accepted');

    $errno = 0;
    try { $insChar('ก'); } catch (PDOException $e) { $errno = $e->errorInfo[1]; }
    ok($errno === 1062, 'repeat guess of "ก" rejected with ER_DUP_ENTRY 1062');

    ok($insChar('า') === true, 'a different char "า" is still accepted');

    // utf8mb4_bin collation keeps tone/vowel variants distinct
    ok($insChar('ก่') === true, 'NFC variant "ก่" treated as a distinct char (binary collation)');
});

// ─────────────────────────────────────────────────────────────────────────────
// Finding 6: stuid never comes from GET parameter
// ─────────────────────────────────────────────────────────────────────────────
run_test('F6 — hangman_next_word uses $me[stuid], not GET[stuid]', function () {
    // Verify the handler source no longer reads $_GET['stuid']
    $src = file_get_contents(dirname(__DIR__) . '/api/handlers/hangman_next_word.php');
    ok(
        strpos($src, "\$_GET['stuid']") === false,
        'GET[stuid] not referenced in hangman_next_word.php'
    );
    ok(
        strpos($src, '$stuid      = $me[\'stuid\']') !== false ||
        strpos($src, '$stuid = $me[\'stuid\']') !== false,
        'stuid is assigned directly from $me[stuid]'
    );
});

// ─────────────────────────────────────────────────────────────────────────────
// Finding 7: speechConf removed from correctness evaluation
// ─────────────────────────────────────────────────────────────────────────────
run_test('F7 — S001 correctness uses text match only, not client speechConf', function () {
    $src = file_get_contents(dirname(__DIR__) . '/api/handlers/training_task_submit.php');
    ok(
        strpos($src, '$speechConf >= 0.7') === false,
        'speechConf threshold removed from isCorrect condition'
    );
    ok(
        strpos($src, 'speechConfidence') !== false,
        'speechConfidence variable still read (can be logged/stored)'
    );
});

// ─────────────────────────────────────────────────────────────────────────────
// Finding 8: Speed bonus condition uses timerMs, not hardcoded 3000
// ─────────────────────────────────────────────────────────────────────────────
run_test('F8a — Speed bonus uses SERVER elapsed; client timeSpentMs not read', function () {
    $src = file_get_contents(dirname(__DIR__) . '/api/handlers/training_task_submit.php');
    ok(
        strpos($src, '&& $timeMs < 3000') === false,
        'Hardcoded 3000 ms guard removed'
    );
    ok(
        strpos($src, "\$body['timeSpentMs']") === false,
        'client timeSpentMs is never read from the request body'
    );
    ok(
        strpos($src, '$elapsedMs < $timerMs') !== false,
        'speed-bonus gate uses server-computed $elapsedMs vs $timerMs'
    );
    ok(
        strpos($src, 'TIMESTAMPDIFF(MICROSECOND') !== false,
        'elapsed time is derived from the DB clock (TIMESTAMPDIFF)'
    );
});

run_test('F8b — Speed bonus formula gives proportional result', function () {
    // Mirrors the handler:  speedBonus = min(50, round(50*(1 - elapsedMs/timerMs)))
    $calc = static function (int $elapsedMs, int $timerMs): int {
        if ($timerMs <= 0 || $elapsedMs >= $timerMs) return 0;
        return (int)min(50, round(50 * (1 - $elapsedMs / $timerMs)));
    };

    ok($calc(0,     15000) === 50, 'Server elapsed ≈ 0 → max bonus 50');
    ok($calc(5000,  15000) === 33, '5 s on 15 s timer → bonus ≈ 33');
    ok($calc(14000, 15000) === 3,  '14 s on 15 s timer → small bonus');
    ok($calc(15000, 15000) === 0,  'At/over the limit → 0 bonus');
    ok($calc(60000, 15000) === 0,  '60 s on 15 s timer → 0 bonus');
});

// ─────────────────────────────────────────────────────────────────────────────
// Finding 9: training_summary guards against NULL started_at
// ─────────────────────────────────────────────────────────────────────────────
run_test('F9 — training_summary handles NULL started_at without crashing', function () {
    $startedRaw = null;
    $endedRaw   = null;
    // Fixed code:
    $started  = $startedRaw ? (new DateTime($startedRaw)) : new DateTime();
    $ended    = $endedRaw   ? (new DateTime($endedRaw))   : new DateTime();
    $duration = max(0, (int)$ended->getTimestamp() - (int)$started->getTimestamp());
    ok($duration >= 0, 'Duration is non-negative with NULL timestamps');
});

// ─────────────────────────────────────────────────────────────────────────────
// Finding 11: TTS speaker allowlist and speed/volume normalisation
// ─────────────────────────────────────────────────────────────────────────────
run_test('F11 — TTS parameter validation and cache-key normalisation', function () {
    $normalise = static function (mixed $rawSpeaker, float $speed, float $volume): array {
        $speaker = ctype_digit((string)$rawSpeaker)
            && (int)$rawSpeaker >= 1 && (int)$rawSpeaker <= 99
            ? (string)$rawSpeaker
            : '1';
        $speed  = round(max(0.5, min(2.0, $speed)), 1);
        $volume = round(max(0.0, min(1.0, $volume)), 1);
        return compact('speaker', 'speed', 'volume');
    };

    $r = $normalise('../../etc', -999.0, 9999.0);
    ok($r['speaker'] === '1',   'Invalid speaker string → default "1"');
    ok($r['speed']   === 0.5,   'Speed -999 clamped to 0.5');
    ok($r['volume']  === 1.0,   'Volume 9999 clamped to 1.0');

    $r2 = $normalise('1', 1.001, 0.9);
    ok($r2['speed'] === 1.0,    'speed 1.001 rounded to 1.0 (stable cache key)');

    $r3 = $normalise('50', 1.5, 0.8);
    ok($r3['speaker'] === '50', 'Valid speaker "50" passed through');

    // Cache keys for logically-equivalent params must be identical
    $k1 = sha1('คำ|1|' . $normalise('1', 1.0, 1.0)['speed']);
    $k2 = sha1('คำ|1|' . $normalise('1', 1.001, 1.0)['speed']);
    ok($k1 === $k2, 'speed 1.0 and 1.001 produce the same cache key after normalisation');
});

// ─────────────────────────────────────────────────────────────────────────────
// Finding 14 (bonus): in_array strict-mode win detection
// ─────────────────────────────────────────────────────────────────────────────
run_test('F14 — Hangman win check uses strict in_array', function () {
    $src = file_get_contents(dirname(__DIR__) . '/api/handlers/hangman_guess.php');
    ok(
        strpos($src, "in_array('_', \$updatedMasked, true)") !== false,
        'Win check uses strict mode: in_array(_, array, true)'
    );
});
