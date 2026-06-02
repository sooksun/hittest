<?php
/**
 * tests/RetentionTest.php — audit_logs retention + TTS rate-limiter helpers.
 * Run via: php tests/run_tests.php
 */

require_once __DIR__ . '/bootstrap.php';

/** Insert $n audit rows aged $ageSec seconds in the past. */
function seed_audit(PDO $pdo, int $ageSec, string $stuid, string $action, int $n = 1): void
{
    $age  = max(0, $ageSec);
    $stmt = $pdo->prepare(
        "INSERT INTO audit_logs (created_at, stuid, action) VALUES (NOW(3) - INTERVAL {$age} SECOND, ?, ?)"
    );
    for ($i = 0; $i < $n; $i++) {
        $stmt->execute([$stuid, $action]);
    }
}

// ═════════════════════════════════════════════════════════════════════════════
// R1 — prune_audit_logs deletes only rows older than the retention window
// ═════════════════════════════════════════════════════════════════════════════
run_test('R1 — prune keeps recent rows, deletes old ones', function () {
    $pdo = create_test_db();
    seed_audit($pdo, 100 * 86400, 'studentA', 'request', 4);  // 100 days old
    seed_audit($pdo, 10  * 86400, 'studentA', 'request', 3);  // 10 days old

    $deleted = prune_audit_logs($pdo, 90);
    ok($deleted === 4, "prune(90) removed the 4 old rows (got {$deleted})");

    $left = (int)$pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn();
    ok($left === 3, "3 recent rows remain (got {$left})");
});

// ═════════════════════════════════════════════════════════════════════════════
// R2 — prune batches through large backlogs (batch < total)
// ═════════════════════════════════════════════════════════════════════════════
run_test('R2 — prune deletes the whole backlog across batches', function () {
    $pdo = create_test_db();
    seed_audit($pdo, 100 * 86400, 'studentA', 'request', 12);  // 12 old rows

    $deleted = prune_audit_logs($pdo, 90, 5);   // batch size 5 → 5+5+2
    ok($deleted === 12, "all 12 old rows pruned across batches (got {$deleted})");
    ok((int)$pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn() === 0, 'table emptied');
});

// ═════════════════════════════════════════════════════════════════════════════
// R3 — tts_rate_exceeded counts only recent tts_request rows for the user
// ═════════════════════════════════════════════════════════════════════════════
run_test('R3 — TTS rate limiter trips at the configured max', function () {
    $pdo = create_test_db();

    ok(tts_rate_exceeded($pdo, 'studentA', 30, 60) === false, 'no history → not limited');

    seed_audit($pdo, 5, 'studentA', 'tts_request', 29);   // 29 recent calls
    ok(tts_rate_exceeded($pdo, 'studentA', 30, 60) === false, '29 < 30 → still allowed');

    seed_audit($pdo, 5, 'studentA', 'tts_request', 1);    // 30th
    ok(tts_rate_exceeded($pdo, 'studentA', 30, 60) === true, '30 >= 30 → limited');
});

// ═════════════════════════════════════════════════════════════════════════════
// R4 — rate limiter ignores out-of-window and other users' rows
// ═════════════════════════════════════════════════════════════════════════════
run_test('R4 — rate limiter respects the time window and per-user scope', function () {
    $pdo = create_test_db();

    seed_audit($pdo, 120, 'studentA', 'tts_request', 50);  // 50 calls but 120s ago (window 60s)
    ok(tts_rate_exceeded($pdo, 'studentA', 30, 60) === false, 'old calls outside window do not count');

    seed_audit($pdo, 5, 'studentB', 'tts_request', 50);    // another user's recent calls
    ok(tts_rate_exceeded($pdo, 'studentA', 30, 60) === false, "studentB's calls don't limit studentA");
    ok(tts_rate_exceeded($pdo, 'studentB', 30, 60) === true,  'studentB is limited by their own calls');

    // cache-hit style rows (non tts_request) must not count toward the limit
    $pdo->exec('DELETE FROM audit_logs');
    seed_audit($pdo, 5, 'studentA', 'tts_rate_limited', 40);
    ok(tts_rate_exceeded($pdo, 'studentA', 30, 60) === false, 'only action=tts_request is counted');
});

// ═════════════════════════════════════════════════════════════════════════════
// R5 — rate limiter fails OPEN when audit_logs is unavailable
// ═════════════════════════════════════════════════════════════════════════════
run_test('R5 — rate limiter fails open if audit_logs is missing', function () {
    $pdo = create_test_db();
    $pdo->exec('DROP TABLE audit_logs');
    ok(tts_rate_exceeded($pdo, 'studentA', 30, 60) === false, 'missing table → not limited (fail-open)');
});
