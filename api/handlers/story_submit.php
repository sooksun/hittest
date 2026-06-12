<?php
// POST /api/story/submit  {game, words[], wordIds[], storyText, difficulty, grade, stats}
// Returns {success:true, attemptId, feedback:{praise}}
//
// บันทึก "ภารกิจนักเล่าเรื่อง" ท้ายเกม — ตรวจ + บันทึกฝั่ง server (ไม่มี AI)
// ใช้ร่วมกันทั้ง 5 เกม; $me/$pdo มาจาก api/index.php
$body = json_decode(file_get_contents('php://input'), true) ?: [];

// เกมต้นทาง — จำกัดให้อยู่ใน whitelist กัน enum แปลกปลอม
$game    = strtolower(preg_replace('/[^a-zA-Z_]/', '', (string)($body['game'] ?? 'story')));
$allowed = ['memory', 'balloon', 'bubble', 'hangman', 'training', 'story'];
if (!in_array($game, $allowed, true)) {
    $game = 'story';
}

$storyText = trim((string)($body['storyText'] ?? ''));
$len       = mb_strlen($storyText);
if ($len === 0) {
    json_response(['success' => false, 'message' => 'กรุณาเขียนเรื่องราวก่อนส่งนะ'], 400);
}

// คำที่ให้แต่ง (string[]) + id (int[]) — clamp ความยาว/จำนวนกันข้อมูลผิดปกติ
$words = [];
foreach ((array)($body['words'] ?? []) as $w) {
    $w = trim((string)$w);
    if ($w !== '') {
        $words[] = mb_substr($w, 0, 64);
    }
}
$words   = array_slice($words, 0, 20);
$wordIds = array_slice(array_map('intval', (array)($body['wordIds'] ?? [])), 0, 20);

$grade      = max(1, min(6, (int)($body['grade']      ?? $me['class_id'] ?? 1)));
$difficulty = max(1, min(3, (int)($body['difficulty'] ?? 1)));

// ตรวจฝั่ง server ว่าใช้คำครบทุกคำไหม (ไม่ไว้ใจ client):
//  • ไม่มีคำให้แต่งเลย → ไม่ถือว่า "ครบ" (กัน vacuous used_all=1 เมื่อ words ว่าง)
//  • เทียบบนข้อความเต็ม "ก่อน" ตัด 5000 ตัว (กันคำที่อยู่ท้าย ๆ หาย)
//  • normalize NFC ทั้งสองฝั่ง (ไทยเรียง combining marks ได้หลายรูปแบบ)
$nfcStory = Normalizer::normalize($storyText, Normalizer::FORM_C) ?: $storyText;
$usedAll  = $words !== [];
foreach ($words as $w) {
    $nfcWord = Normalizer::normalize($w, Normalizer::FORM_C) ?: $w;
    if (mb_strpos($nfcStory, $nfcWord) === false) {
        $usedAll = false;
        break;
    }
}

// ตัดข้อความยาวผิดปกติ "หลัง" ตรวจ used_all แล้ว — เก็บลง DB ไม่เกิน 5000 ตัว
if ($len > 5000) {
    $storyText = mb_substr($storyText, 0, 5000);
    $len       = 5000;
}

$stats = $body['stats'] ?? null;
if (!is_array($stats)) {
    $stats = null;
}

try {
    $stmt = $pdo->prepare(
        'INSERT INTO game_stories
            (sc_id, stuid, stuname, class_id, game, grade, difficulty,
             words_json, story_text, char_count, used_all, stats_json, created_at)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW())'
    );
    $stmt->execute([
        $me['sc_id'],
        $me['stuid'],
        $me['stuname'] ?? '',
        (int)($me['class_id'] ?? $grade),
        $game,
        $grade,
        $difficulty,
        json_encode(['words' => $words, 'wordIds' => $wordIds], JSON_UNESCAPED_UNICODE),
        $storyText,
        $len,
        $usedAll ? 1 : 0,
        $stats !== null ? json_encode($stats, JSON_UNESCAPED_UNICODE) : null,
    ]);
    $attemptId = (int)$pdo->lastInsertId();
} catch (Throwable $e) {
    json_response(['success' => false, 'message' => 'ไม่สามารถบันทึกได้ กรุณาลองใหม่'], 500);
}

audit_log_event($pdo, [
    'sc_id' => $me['sc_id'], 'stuid' => $me['stuid'], 'action' => 'story_saved',
    'entity_type' => $game, 'entity_id' => (string)$attemptId,
    'meta_json' => ['chars' => $len, 'usedAll' => $usedAll, 'words' => count($words)],
]);

// ฟีดแบ็กแบบง่าย ไม่ใช้ AI — ให้กำลังใจตามว่าใช้คำครบไหม
$praise = $usedAll
    ? 'ยอดเยี่ยมมาก! แต่งเรื่องได้ครบทุกคำและน่าสนใจสุด ๆ 🎉'
    : 'เก่งมาก! เรื่องราวน่าสนใจเลย ครั้งหน้าลองใส่คำให้ครบทุกคำนะ';

json_response([
    'success'   => true,
    'attemptId' => $attemptId,
    'feedback'  => ['praise' => $praise],
]);
