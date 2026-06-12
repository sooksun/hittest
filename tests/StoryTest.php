<?php
/**
 * tests/StoryTest.php — "ภารกิจนักเล่าเรื่อง" (story submit) handler tests
 *
 * ครอบคลุม endpoint ใหม่ POST /api/story/submit (api/handlers/story_submit.php)
 * ที่ทุกเกมเรียกใช้ตอนผ่านด่าน
 * Run:  php tests/run_tests.php
 */

require_once __DIR__ . '/bootstrap.php';

$H = dirname(__DIR__) . '/api/handlers/';

function story_me(): array
{
    return ['stuid' => 'S1', 'sc_id' => '57030129', 'stuname' => 'เด็ก ก', 'class_id' => 1, 'rooms' => 1];
}

// ─────────────────────────────────────────────────────────────────────────────
// 1) บันทึกเรื่องที่ใช้คำครบ → success + used_all = 1
// ─────────────────────────────────────────────────────────────────────────────
run_test('Story 1 — saves a valid story and detects used_all', function () use ($H) {
    $pdo  = create_test_db();
    $body = json_encode([
        'game'       => 'memory',
        'words'      => ['ถ้ำ', 'แมงดา', 'แยม'],
        'wordIds'    => [11, 22, 33],
        'storyText'  => 'ใน ถ้ำ มี แมงดา กิน แยม อย่างมีความสุข',
        'grade'      => 2,
        'difficulty' => 1,
        'stats'      => ['moves' => 5],
    ], JSON_UNESCAPED_UNICODE);

    $e = call_handler($H . 'story_submit.php', ['pdo' => $pdo, 'me' => story_me()], $body);
    ok($e->httpCode === 200, 'returns 200');
    ok(($e->data['success'] ?? false) === true, 'success=true');
    ok(($e->data['attemptId'] ?? 0) > 0, 'returns attemptId');

    $row = $pdo->query('SELECT * FROM game_stories ORDER BY id DESC LIMIT 1')->fetch();
    ok($row['game'] === 'memory',      'game saved');
    ok((int)$row['used_all'] === 1,    'used_all=1 (all words present)');
    ok((int)$row['grade'] === 2,       'grade saved');
    ok($row['stuid'] === 'S1',         'stuid saved');
    ok((int)$row['char_count'] > 0,    'char_count recorded');
});

// ─────────────────────────────────────────────────────────────────────────────
// 2) เรื่องว่าง → ปฏิเสธ 400 ไม่บันทึก
// ─────────────────────────────────────────────────────────────────────────────
run_test('Story 2 — empty story is rejected (400)', function () use ($H) {
    $pdo  = create_test_db();
    $body = json_encode(['game' => 'balloon', 'words' => ['ก'], 'storyText' => '   '], JSON_UNESCAPED_UNICODE);

    $e = call_handler($H . 'story_submit.php', ['pdo' => $pdo, 'me' => story_me()], $body);
    ok($e->httpCode === 400, 'returns 400');
    ok(($e->data['success'] ?? null) === false, 'success=false');
    ok((int)$pdo->query('SELECT COUNT(*) FROM game_stories')->fetchColumn() === 0, 'nothing saved');
});

// ─────────────────────────────────────────────────────────────────────────────
// 3) ใช้คำไม่ครบ → ยังบันทึกได้ แต่ used_all = 0
// ─────────────────────────────────────────────────────────────────────────────
run_test('Story 3 — missing a word still saves but used_all=0', function () use ($H) {
    $pdo  = create_test_db();
    $body = json_encode([
        'game'      => 'bubble',
        'words'     => ['ถ้ำ', 'แมงดา', 'แยม'],
        'storyText' => 'ใน ถ้ำ มี แมงดา ตัวใหญ่ เดินเล่นอย่างสบายใจ',  // ไม่มี "แยม"
        'grade'     => 1,
    ], JSON_UNESCAPED_UNICODE);

    $e = call_handler($H . 'story_submit.php', ['pdo' => $pdo, 'me' => story_me()], $body);
    ok(($e->data['success'] ?? false) === true, 'success=true (saved)');
    $row = $pdo->query('SELECT used_all FROM game_stories ORDER BY id DESC LIMIT 1')->fetch();
    ok((int)$row['used_all'] === 0, 'used_all=0 (a word missing)');
});

// ─────────────────────────────────────────────────────────────────────────────
// 4) game แปลกปลอม → sanitize เป็น "story" (กัน enum หลุด)
// ─────────────────────────────────────────────────────────────────────────────
run_test('Story 4 — unknown game falls back to "story"', function () use ($H) {
    $pdo  = create_test_db();
    $body = json_encode([
        'game'      => 'h@ck!!',
        'words'     => ['ก'],
        'storyText' => 'นี่คือเรื่องราวสั้น ๆ ที่มีคำว่า ก อยู่ในประโยคนี้',
    ], JSON_UNESCAPED_UNICODE);

    $e = call_handler($H . 'story_submit.php', ['pdo' => $pdo, 'me' => story_me()], $body);
    ok(($e->data['success'] ?? false) === true, 'success=true');
    ok($pdo->query('SELECT game FROM game_stories ORDER BY id DESC LIMIT 1')->fetchColumn() === 'story',
        'unknown game sanitized to "story"');
});

// ─────────────────────────────────────────────────────────────────────────────
// 5) words ว่าง → used_all=0 (กัน vacuous true) แต่ยังบันทึกเรื่องได้
// ─────────────────────────────────────────────────────────────────────────────
run_test('Story 5 — empty words[] is NOT counted as used_all', function () use ($H) {
    $pdo  = create_test_db();
    $body = json_encode([
        'game'      => 'memory',
        'words'     => [],
        'storyText' => 'เรื่องสั้น ๆ ที่ไม่มีคำบังคับให้ใช้เลย',
    ], JSON_UNESCAPED_UNICODE);

    $e = call_handler($H . 'story_submit.php', ['pdo' => $pdo, 'me' => story_me()], $body);
    ok(($e->data['success'] ?? false) === true, 'success=true (saved)');
    $row = $pdo->query('SELECT used_all FROM game_stories ORDER BY id DESC LIMIT 1')->fetch();
    ok((int)$row['used_all'] === 0, 'used_all=0 when no words assigned (no vacuous true)');
});

// ─────────────────────────────────────────────────────────────────────────────
// 6) used_all เทียบแบบ NFC → คำที่เรียง combining marks ต่างกันแต่เทียบเท่ากันถือว่าใช้แล้ว
// ─────────────────────────────────────────────────────────────────────────────
run_test('Story 6 — used_all matches across NFC combining-mark order', function () use ($H) {
    $pdo = create_test_db();
    // คำให้ไว้เรียง ก+วรรณยุกต์+สระอุ ; ในเรื่องพิมพ์ ก+สระอุ+วรรณยุกต์ (NFC แล้วเท่ากัน)
    $word  = "ก\u{0E48}\u{0E38}";
    $story = "วันนี้มี ก\u{0E38}\u{0E48} อยู่ในเรื่องของหนู";
    $body  = json_encode(['game' => 'memory', 'words' => [$word], 'storyText' => $story], JSON_UNESCAPED_UNICODE);

    $e = call_handler($H . 'story_submit.php', ['pdo' => $pdo, 'me' => story_me()], $body);
    ok(($e->data['success'] ?? false) === true, 'success=true');
    $row = $pdo->query('SELECT used_all FROM game_stories ORDER BY id DESC LIMIT 1')->fetch();
    ok((int)$row['used_all'] === 1, 'used_all=1: NFC normalization makes reordered marks match');
});
