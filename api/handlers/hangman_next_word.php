<?php
// GET /api/hangman/next-word?grade=1&difficulty=1&excludeWordIds=[1,2,3]
// Returns {error:false,sessionId,wordId,maskedWord[],hints[],remainingLives,imagePath,soundPath}
// LEFT JOINs readthai.wordstest for real sound/image paths.

$grade      = max(1, min(6, (int)($_GET['grade']      ?? 1)));
$difficulty = max(1, min(3, (int)($_GET['difficulty'] ?? 1)));
$stuid      = $me['stuid']; // always the authenticated user — never from client input

$excludeIds = [];
if (!empty($_GET['excludeWordIds'])) {
    $decoded = json_decode($_GET['excludeWordIds'], true);
    if (is_array($decoded)) $excludeIds = array_map('intval', $decoded);
}

// ── Select a word with real media paths ──────────────────────────────────────
$base = 'SELECT h.id, h.word, h.level, h.class_id, h.spoken_form,
                r.sound_path, r.image_path
         FROM wordstest h
         LEFT JOIN readthai.wordstest r ON r.id = h.id
         WHERE h.class_id = ? AND h.level = ? AND h.word IS NOT NULL';

if (count($excludeIds) > 0) {
    $ph   = implode(',', array_fill(0, count($excludeIds), '?'));
    $stmt = $pdo->prepare($base . " AND h.id NOT IN ({$ph}) ORDER BY RAND() LIMIT 1");
    $stmt->execute(array_merge([$grade, $difficulty], $excludeIds));
} else {
    $stmt = $pdo->prepare($base . ' ORDER BY RAND() LIMIT 1');
    $stmt->execute([$grade, $difficulty]);
}
$word = $stmt->fetch();

if (!$word) {
    // Fallback: any word for grade
    $stmt = $pdo->prepare(
        'SELECT h.id, h.word, h.level, h.class_id, h.spoken_form, r.sound_path, r.image_path
         FROM wordstest h LEFT JOIN readthai.wordstest r ON r.id = h.id
         WHERE h.class_id = ? AND h.word IS NOT NULL ORDER BY RAND() LIMIT 1'
    );
    $stmt->execute([$grade]);
    $word = $stmt->fetch();
}
if (!$word) {
    json_response(['error' => true, 'message' => 'No words found'], 404);
}

// ── Masked word (all underscores) ────────────────────────────────────────────
$components = preg_split('//u', $word['word'], -1, PREG_SPLIT_NO_EMPTY);
$maskedWord = array_fill(0, count($components), '_');

// ── Lives by difficulty ──────────────────────────────────────────────────────
$lives = [1 => 7, 2 => 5, 3 => 3][$difficulty] ?? 5;

// ── Hints ────────────────────────────────────────────────────────────────────
$hints = [];
if (!empty($word['spoken_form'])) $hints[] = 'คำอ่าน: ' . $word['spoken_form'];
$hints[] = 'คำนี้มี ' . count($components) . ' ตัวอักษร';
$hints[] = 'ระดับความยาก: ' . ([1 => 'ง่าย', 2 => 'ปานกลาง', 3 => 'ยาก'][$word['level']] ?? 'ไม่ทราบ');
$hints[] = 'ระดับชั้น: ป.' . $word['class_id'];
$consonants = 'กขฃคฅฆงจฉชซฌญฎฏฐฑฒณดตถทธนบปผฝพฟภมยรลวศษสหฬอฮ';
if (mb_strlen($word['word']) > 0) {
    $first = mb_substr($word['word'], 0, 1);
    if (mb_strpos($consonants, $first) !== false) $hints[] = 'เสียงต้น: ' . $first;
}

// ── Parse image_path from readthai JSON ─────────────────────────────────────
$imgRaw = (string)($word['image_path'] ?? '');
$imgPath = null;
if ($imgRaw !== '') {
    $dec = json_decode($imgRaw, true);
    $imgPath = is_array($dec)
        ? ($dec['image_original'] ?? $dec['original'] ?? $dec['img_full'] ?? null)
        : $imgRaw;
}
// HangmanGame prepends API_BASE_URL ('/newhittest') when path starts with '/'
// so returning the raw /media/image/... path is correct.

// ── Create session ────────────────────────────────────────────────────────────
$pdo->prepare(
    'INSERT INTO game_hangman_sessions
        (sc_id, stuid, grade_level, difficulty, word_id, word, masked_word, remaining_lives, score, started_at)
     VALUES (?,?,?,?,?,?,?,?,0,NOW())'
)->execute([
    $me['sc_id'],
    $stuid,
    $grade,
    $difficulty,
    (int)$word['id'],
    $word['word'],
    json_encode($maskedWord, JSON_UNESCAPED_UNICODE),
    $lives,
]);

json_response([
    'error'          => false,
    'sessionId'      => (int)$pdo->lastInsertId(),
    'wordId'         => (int)$word['id'],
    'maskedWord'     => $maskedWord,
    'hints'          => $hints,
    'remainingLives' => $lives,
    'imagePath'      => $imgPath,
    'soundPath'      => $word['sound_path'] ?: null,
]);
