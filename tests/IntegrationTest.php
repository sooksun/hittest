<?php
/**
 * tests/IntegrationTest.php — drives the REAL handler files end-to-end against
 * a MySQL test DB, focusing on concurrent submissions and double-click attacks.
 *
 * Run via: php tests/run_tests.php
 */

require_once __DIR__ . '/bootstrap.php';

$H  = dirname(__DIR__) . '/api/handlers/';
$ME = ['stuid' => 'studentA', 'sc_id' => 'school1', 'stuname' => 'A', 'class_id' => 1, 'rooms' => 1];

// ── Fixtures ──────────────────────────────────────────────────────────────────

function seed_hangman(PDO $pdo, array $o = []): int
{
    $pdo->prepare(
        'INSERT INTO game_hangman_sessions
            (sc_id, stuid, grade_level, difficulty, word_id, word, masked_word, remaining_lives, score, completed)
         VALUES (?,?,?,?,?,?,?,?,?,?)'
    )->execute([
        $o['sc_id']     ?? 'school1',
        $o['stuid']     ?? 'studentA',
        1, 1,
        $o['word_id']   ?? 1,
        $o['word']      ?? 'กา',
        $o['masked']    ?? '["_","_"]',
        $o['lives']     ?? 5,
        $o['score']     ?? 0,
        $o['completed'] ?? 0,
    ]);
    return (int)$pdo->lastInsertId();
}

function seed_training(PDO $pdo, array $o = []): string
{
    $tasks = $o['tasks'] ?? [[
        'taskId'      => 'task-1',
        'templateKey' => 'L001_LISTEN_PICK_WORD',
        'wordText'    => 'กา',
        'choices'     => [
            ['id' => 'c-ok',    'text' => 'กา', 'isCorrect' => true],
            ['id' => 'c-wrong', 'text' => 'ขา', 'isCorrect' => false],
        ],
        'timerMs' => 15000,
        'config'  => null,
    ]];
    $id = $o['id'] ?? ('sess-' . bin2hex(random_bytes(6)));
    $pdo->prepare(
        'INSERT INTO game_training_sessions
            (id, sc_id, stuid, grade_level, difficulty, tasks_json, total_tasks, correct_count, total_score)
         VALUES (?,?,?,?,?,?,?,?,?)'
    )->execute([
        $id,
        $o['sc_id'] ?? 'school1',
        $o['stuid'] ?? 'studentA',
        1, 1,
        json_encode($tasks),
        count($tasks),
        $o['correct_count'] ?? 0,
        $o['total_score']   ?? 0,
    ]);
    return $id;
}

function count_rows(PDO $pdo, string $table, string $where, array $args): int
{
    $st = $pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE {$where}");
    $st->execute($args);
    return (int)$st->fetchColumn();
}

// ═════════════════════════════════════════════════════════════════════════════
// IT1 — Double-click: same correct hangman guess scores only once
// ═════════════════════════════════════════════════════════════════════════════
run_test('IT1 — Hangman double-click (correct char) scores once', function () use ($H, $ME) {
    $pdo = create_test_db();
    $sid = seed_hangman($pdo, ['word' => 'กา', 'masked' => '["_","_"]', 'lives' => 5]);

    $r1 = call_handler($H . 'hangman_guess.php', ['pdo' => $pdo, 'me' => $ME],
        json_encode(['sessionId' => $sid, 'guess' => 'ก', 'guessType' => 'consonant']));
    ok($r1->data['correct'] === true,        '1st guess correct');
    ok($r1->data['score']   === 20,          '1st guess scores 20 (10 + lives*2)');

    $r2 = call_handler($H . 'hangman_guess.php', ['pdo' => $pdo, 'me' => $ME],
        json_encode(['sessionId' => $sid, 'guess' => 'ก', 'guessType' => 'consonant']));
    ok(($r2->data['duplicate'] ?? false) === true, '2nd identical guess flagged duplicate');
    ok($r2->data['correct'] === true,               'replay reports prior correctness (was correct)');
    ok($r2->data['score'] === 20,                   'score still 20 after replay (no double-score)');

    $guesses = count_rows($pdo, 'game_hangman_guesses', 'session_id = ?', [$sid]);
    ok($guesses === 1, "exactly 1 guess row persisted (got {$guesses})");

    $score = (int)$pdo->query("SELECT score FROM game_hangman_sessions WHERE id={$sid}")->fetchColumn();
    ok($score === 20, "session score persisted as 20 (got {$score})");
});

// ═════════════════════════════════════════════════════════════════════════════
// IT2 — Double-click: same WRONG hangman guess deducts a life only once
// ═════════════════════════════════════════════════════════════════════════════
run_test('IT2 — Hangman double-click (wrong char) costs one life only', function () use ($H, $ME) {
    $pdo = create_test_db();
    $sid = seed_hangman($pdo, ['word' => 'กา', 'masked' => '["_","_"]', 'lives' => 5]);

    $r1 = call_handler($H . 'hangman_guess.php', ['pdo' => $pdo, 'me' => $ME],
        json_encode(['sessionId' => $sid, 'guess' => 'ม', 'guessType' => 'consonant']));
    ok($r1->data['correct'] === false,          'wrong guess marked incorrect');
    ok($r1->data['remainingLives'] === 4,       'life dropped 5 → 4');

    $r2 = call_handler($H . 'hangman_guess.php', ['pdo' => $pdo, 'me' => $ME],
        json_encode(['sessionId' => $sid, 'guess' => 'ม', 'guessType' => 'consonant']));
    ok(($r2->data['duplicate'] ?? false) === true, 'replayed wrong guess flagged duplicate');
    ok($r2->data['correct'] === false,             'replay reports prior correctness (was wrong)');
    ok($r2->data['remainingLives'] === 4,          'life still 4 (not double-deducted)');

    $lives = (int)$pdo->query("SELECT remaining_lives FROM game_hangman_sessions WHERE id={$sid}")->fetchColumn();
    ok($lives === 4, "session lives persisted as 4 (got {$lives})");
});

// ═════════════════════════════════════════════════════════════════════════════
// IT3 — Cross-user cannot touch another student's hangman session
// ═════════════════════════════════════════════════════════════════════════════
run_test('IT3 — Ownership: studentB cannot guess studentA session', function () use ($H) {
    $pdo = create_test_db();
    $sid = seed_hangman($pdo, ['stuid' => 'studentA', 'word' => 'กา', 'masked' => '["_","_"]']);

    $attacker = ['stuid' => 'studentB', 'sc_id' => 'school1', 'stuname' => 'B', 'class_id' => 1, 'rooms' => 1];
    $r = call_handler($H . 'hangman_guess.php', ['pdo' => $pdo, 'me' => $attacker],
        json_encode(['sessionId' => $sid, 'guess' => 'ก', 'guessType' => 'consonant']));

    ok($r->httpCode === 404, 'attacker gets 404');
    $guesses = count_rows($pdo, 'game_hangman_guesses', 'session_id = ?', [$sid]);
    ok($guesses === 0, 'no guess row created for the victim session');
    $denied = count_rows($pdo, 'audit_logs', 'action = ?', ['ownership_denied']);
    ok($denied === 1, 'ownership_denied written to audit_logs');
});

// ═════════════════════════════════════════════════════════════════════════════
// IT4 — Double-click: training task submit replays original result (idempotent)
//        HTTP 200 + duplicate:true, scored once — the deployed game client's API
//        wrapper throws on any 4xx, so a replay must NOT be an error status.
// ═════════════════════════════════════════════════════════════════════════════
run_test('IT4 — Training task double-submit is idempotent (200 replay, scored once)', function () use ($H, $ME) {
    $pdo = create_test_db();
    $sid = seed_training($pdo);

    $r1 = call_handler($H . 'training_task_submit.php',
        ['pdo' => $pdo, 'me' => $ME, 'taskId' => 'task-1'],
        json_encode(['sessionId' => $sid, 'selectedChoiceId' => 'c-ok']));
    ok($r1->data['isCorrect'] === true,  '1st submit correct');
    ok($r1->data['score'] >= 100,        '1st submit scored ≥ 100');
    $firstScore = $r1->data['score'];

    $r2 = call_handler($H . 'training_task_submit.php',
        ['pdo' => $pdo, 'me' => $ME, 'taskId' => 'task-1'],
        json_encode(['sessionId' => $sid, 'selectedChoiceId' => 'c-ok']));
    ok($r2->httpCode === 200,                       '2nd submit returns 200 (not a client-breaking 4xx)');
    ok(($r2->data['duplicate'] ?? false) === true,  '2nd submit flagged duplicate');
    ok($r2->data['score'] === (int)$firstScore,     'replay returns the ORIGINAL score');
    ok($r2->data['isCorrect'] === true,             'replay returns the original isCorrect');

    $subs = count_rows($pdo, 'game_task_submissions', 'session_id = ?', [$sid]);
    ok($subs === 1, "exactly 1 submission row (got {$subs})");

    $row = $pdo->query("SELECT correct_count, total_score FROM game_training_sessions WHERE id='{$sid}'")->fetch();
    ok((int)$row['correct_count'] === 1,            'correct_count == 1 (not 2)');
    ok((int)$row['total_score'] === (int)$firstScore, 'total_score counted once');

    $dup = count_rows($pdo, 'audit_logs', 'action = ?', ['duplicate_submit']);
    ok($dup === 1, 'duplicate_submit written to audit_logs');
});

// ═════════════════════════════════════════════════════════════════════════════
// IT5 — Server-side timing: client timeSpentMs is ignored
// ═════════════════════════════════════════════════════════════════════════════
run_test('IT5 — Speed bonus uses SERVER elapsed, not client timeSpentMs', function () use ($H, $ME) {
    // (a) Just-started session + client lies "slow" (timeSpentMs huge) → bonus still high
    $pdo = create_test_db();
    $sid = seed_training($pdo);
    $r = call_handler($H . 'training_task_submit.php',
        ['pdo' => $pdo, 'me' => $ME, 'taskId' => 'task-1'],
        json_encode(['sessionId' => $sid, 'selectedChoiceId' => 'c-ok', 'timeSpentMs' => 9999999]));
    ok($r->data['speedBonus'] > 0, 'client-claimed slow time ignored → server sees fast → bonus > 0');
    ok(isset($r->data['elapsedMs']), 'response exposes server-computed elapsedMs');

    // (b) Session started 60s ago + client lies "instant" (timeSpentMs 0) → bonus 0
    $pdo2 = create_test_db();
    $sid2 = seed_training($pdo2);
    $pdo2->exec("UPDATE game_training_sessions SET started_at = NOW() - INTERVAL 60 SECOND WHERE id='{$sid2}'");
    $r2 = call_handler($H . 'training_task_submit.php',
        ['pdo' => $pdo2, 'me' => $ME, 'taskId' => 'task-1'],
        json_encode(['sessionId' => $sid2, 'selectedChoiceId' => 'c-ok', 'timeSpentMs' => 0]));
    ok($r2->data['speedBonus'] === 0, 'client-claimed instant ignored → server sees 60s → bonus 0');
    ok($r2->data['elapsedMs'] >= 59000, "server elapsedMs ≈ 60000 (got {$r2->data['elapsedMs']})");
});

// ═════════════════════════════════════════════════════════════════════════════
// IT6 — Double-click: training end writes exactly one game_results row
// ═════════════════════════════════════════════════════════════════════════════
run_test('IT6 — Training end double-click → single result row', function () use ($H, $ME) {
    $pdo = create_test_db();
    $sid = seed_training($pdo, ['total_score' => 150, 'correct_count' => 1]);

    $r1 = call_handler($H . 'training_end.php',
        ['pdo' => $pdo, 'me' => $ME, 'sessionId' => $sid], '{}');
    ok($r1->data['success'] === true,                  '1st end succeeds');
    ok(!isset($r1->data['alreadyEnded']),              '1st end is the real end');

    $r2 = call_handler($H . 'training_end.php',
        ['pdo' => $pdo, 'me' => $ME, 'sessionId' => $sid], '{}');
    ok(($r2->data['alreadyEnded'] ?? false) === true,  '2nd end is idempotent no-op');

    $results = count_rows($pdo, 'game_results', 'game = ? AND stuid = ?', ['training', 'studentA']);
    ok($results === 1, "exactly 1 training game_results row (got {$results})");
});

// ═════════════════════════════════════════════════════════════════════════════
// IT7 — Concurrent race: UNIQUE(session_id,guess_char) is the durable guard
// ═════════════════════════════════════════════════════════════════════════════
run_test('IT7 — Concurrent identical guess: DB UNIQUE rejects the loser', function () use ($H) {
    $pdo = create_test_db();
    $sid = seed_hangman($pdo, ['word' => 'กา', 'masked' => '["_","_"]']);

    // Two independent connections simulate two parallel requests slipping past app logic.
    $c1 = connect_test_db();
    $c2 = connect_test_db();

    $insert = fn(PDO $c) => $c->prepare(
        'INSERT INTO game_hangman_guesses (session_id, guess_char, guess_type, is_correct) VALUES (?,?,?,1)'
    )->execute([$sid, 'ก', 'consonant']);

    $ok1 = false; $ok2 = false; $dupErrno = 0;
    try { $insert($c1); $ok1 = true; } catch (PDOException $e) { $dupErrno = $e->errorInfo[1]; }
    try { $insert($c2); $ok2 = true; } catch (PDOException $e) { $dupErrno = $e->errorInfo[1]; }

    ok($ok1 !== $ok2, 'exactly one of the two inserts succeeds');
    ok($dupErrno === 1062, "loser fails with ER_DUP_ENTRY 1062 (got {$dupErrno})");

    $rows = count_rows($pdo, 'game_hangman_guesses', 'session_id = ? AND guess_char = ?', [$sid, 'ก']);
    ok($rows === 1, "exactly 1 row survives the race (got {$rows})");
});

// ═════════════════════════════════════════════════════════════════════════════
// IT8 — FOR UPDATE row lock serializes concurrent writers
// ═════════════════════════════════════════════════════════════════════════════
run_test('IT8 — Session row lock blocks a concurrent writer (FOR UPDATE)', function () {
    $pdo = create_test_db();
    $sid = seed_hangman($pdo, ['word' => 'กา']);

    $c1 = connect_test_db();
    $c2 = connect_test_db();

    // c1 acquires the row lock and holds it
    $c1->beginTransaction();
    $c1->prepare('SELECT * FROM game_hangman_sessions WHERE id = ? FOR UPDATE')->execute([$sid]);

    // c2 wants the same row; with a 1s wait timeout it must fail rather than proceed
    $c2->exec('SET SESSION innodb_lock_wait_timeout = 1');
    $c2->beginTransaction();
    $blocked = false; $errno = 0;
    try {
        $c2->prepare('SELECT * FROM game_hangman_sessions WHERE id = ? FOR UPDATE')->execute([$sid]);
    } catch (PDOException $e) {
        $blocked = true; $errno = $e->errorInfo[1];
    }
    if ($c2->inTransaction()) $c2->rollBack();
    $c1->commit();

    ok($blocked === true,  'concurrent writer was blocked while the lock was held');
    ok($errno === 1205,    "blocked with lock-wait-timeout 1205 (got {$errno})");
});

// ═════════════════════════════════════════════════════════════════════════════
// IT9 — S001 pronunciation is scored SERVER-SIDE on the transcript (not client
//        speechConfidence): a correct read scores even when STT mangles the tone
//        / appends a word, while a wrong word fails despite high client confidence.
// ═════════════════════════════════════════════════════════════════════════════
run_test('IT9 — S001 pronunciation: tone-variant transcript scores, wrong word fails', function () use ($H, $ME) {
    $s001 = [
        'taskId'      => 'task-s1',
        'templateKey' => 'S001_RECORD_PRONUNCIATION',
        'wordText'    => 'กบ',
        'choices'     => [],
        'timerMs'     => 15000,
        'config'      => ['expectedText' => 'กบ'],
    ];

    // Correct read, but Chrome STT returned a stray tone mark + a polite word
    $pdo = create_test_db();
    $sid = seed_training($pdo, ['tasks' => [$s001]]);
    $r = call_handler($H . 'training_task_submit.php',
        ['pdo' => $pdo, 'me' => $ME, 'taskId' => 'task-s1'],
        json_encode(['sessionId' => $sid, 'typedText' => 'ก่บ ค่ะ']));
    ok($r->data['isCorrect'] === true, 'tone-variant + appended word is accepted server-side');
    ok($r->data['score'] >= 100,       'a correct read earns the base score');

    // Wrong word must fail even if the client claims near-perfect confidence
    $pdo2 = create_test_db();
    $sid2 = seed_training($pdo2, ['tasks' => [$s001]]);
    $r2 = call_handler($H . 'training_task_submit.php',
        ['pdo' => $pdo2, 'me' => $ME, 'taskId' => 'task-s1'],
        json_encode(['sessionId' => $sid2, 'typedText' => 'หมา', 'speechConfidence' => 0.99]));
    ok($r2->data['isCorrect'] === false, 'wrong word rejected despite high client speechConfidence');
    ok($r2->data['score'] === 0,         'no score for a wrong read');
});

// ═════════════════════════════════════════════════════════════════════════════
// IT10 — Replaying the guess that COMPLETED the round is idempotent (200 replay,
//         not 400). The game client's API wrapper throws on any 4xx, so a retry /
//         double-tap of the winning guess must return the original final result.
// ═════════════════════════════════════════════════════════════════════════════
run_test('IT10 — Hangman replay of the winning guess returns 200 (not 400), scored once', function () use ($H, $ME) {
    $pdo = create_test_db();
    $sid = seed_hangman($pdo, ['word' => 'กา', 'masked' => '["_","_"]', 'lives' => 5]);

    // Reveal the first letter, then the winning letter (round completes)
    call_handler($H . 'hangman_guess.php', ['pdo' => $pdo, 'me' => $ME],
        json_encode(['sessionId' => $sid, 'guess' => 'ก', 'guessType' => 'consonant']));
    $win = call_handler($H . 'hangman_guess.php', ['pdo' => $pdo, 'me' => $ME],
        json_encode(['sessionId' => $sid, 'guess' => 'า', 'guessType' => 'vowel']));
    ok($win->data['won'] === true,       'winning guess completes the word');
    ok($win->data['completed'] === true, 'session marked completed');
    $finalScore = (int)$win->data['score'];

    // Network retry / double-tap of that SAME winning guess on the finished round
    $replay = call_handler($H . 'hangman_guess.php', ['pdo' => $pdo, 'me' => $ME],
        json_encode(['sessionId' => $sid, 'guess' => 'า', 'guessType' => 'vowel']));
    ok($replay->httpCode === 200,                      'replay is HTTP 200, not a 4xx the client would throw');
    ok(($replay->data['duplicate'] ?? false) === true, 'replay flagged duplicate');
    ok($replay->data['won'] === true,                  'replay still reports won');
    ok($replay->data['completed'] === true,            'replay still reports completed');
    ok($replay->data['word'] === 'กา',                 'replay reveals the answer word');
    ok($replay->data['score'] === $finalScore,         'replay returns the original score (no re-score)');

    // No double insert / no double result row
    $rows = count_rows($pdo, 'game_hangman_guesses', 'session_id = ? AND guess_char = ?', [$sid, 'า']);
    ok($rows === 1, "exactly 1 guess row for the winning char (got {$rows})");
    $results = count_rows($pdo, 'game_results', 'game = ? AND stuid = ?', ['hangman', 'studentA']);
    ok($results === 1, "exactly 1 hangman result row (got {$results})");

    // A genuinely NEW guess on the finished round still gets 400 (not a replay)
    $newGuess = call_handler($H . 'hangman_guess.php', ['pdo' => $pdo, 'me' => $ME],
        json_encode(['sessionId' => $sid, 'guess' => 'ม', 'guessType' => 'consonant']));
    ok($newGuess->httpCode === 400, 'a never-made guess on a finished round still returns 400');
});

// ═════════════════════════════════════════════════════════════════════════════
// IT11 — balloon_submit drives the shared game_clamp_score() + game_result_save()
//         helpers: a legit score is stored as-is; a fabricated score is clamped
//         to 9999 and the clamp is audited.
// ═════════════════════════════════════════════════════════════════════════════
run_test('IT11 — balloon_submit: shared clamp + result-save helpers', function () use ($H, $ME) {
    $pdo = create_test_db();

    // Legit score saves through game_result_save (class_id from $me, grade from body)
    $r1 = call_handler($H . 'balloon_submit.php', ['pdo' => $pdo, 'me' => $ME],
        json_encode(['score' => 1500, 'levelsCompleted' => 3, 'difficulty' => 2, 'hearts' => 4, 'gradeLevel' => 2]));
    ok(($r1->data['ok'] ?? false) === true,    'ok=true');
    ok(($r1->data['attemptId'] ?? 0) > 0,      'returns attemptId');
    $row = $pdo->query("SELECT * FROM game_results WHERE game='balloon' ORDER BY id DESC LIMIT 1")->fetch();
    ok((int)$row['score'] === 1500,            'legit score 1500 stored as-is');
    ok((int)$row['grade'] === 2,               'grade from body');
    ok((int)$row['class_id'] === 1,            'class_id from $me (studentA = class 1)');

    // Fabricated huge score → clamped to 9999 + score_clamped audit
    $r2 = call_handler($H . 'balloon_submit.php', ['pdo' => $pdo, 'me' => $ME],
        json_encode(['score' => 999999, 'difficulty' => 1, 'gradeLevel' => 1]));
    $clampedScore = (int)$pdo->query("SELECT score FROM game_results WHERE game='balloon' ORDER BY id DESC LIMIT 1")->fetchColumn();
    ok($clampedScore === 9999, "fabricated 999999 clamped to 9999 (got {$clampedScore})");
    $clamped = count_rows($pdo, 'audit_logs', 'action = ?', ['score_clamped']);
    ok($clamped === 1, 'score_clamped audited exactly once');

    ok(count_rows($pdo, 'game_results', 'game = ?', ['balloon']) === 2, 'two balloon rows total');
});
