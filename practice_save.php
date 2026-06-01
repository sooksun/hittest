<?php
/**
 * practice_save.php — บันทึกผลการฝึกอ่าน 1 คำ (เรียกจาก my_practice.php)
 * ความปลอดภัย: ตัวตน (sc_id/stuid/stuname) เอาจาก session นักเรียนเท่านั้น — ไม่เชื่อค่าจาก client
 *   เฉพาะ role=student ที่ login แล้ว · คืน JSON
 */
session_start();
require __DIR__ . '/includes/student_auth.php';

header('Content-Type: application/json; charset=utf-8');

if (($_SESSION['role'] ?? '') !== 'student' || empty($_SESSION['stu']['stuid'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'unauthorized']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'method not allowed']);
    exit;
}

$me = $_SESSION['stu'];
session_write_close();   // อ่านตัวตนจาก session เสร็จแล้ว → ปล่อย lock กันบล็อก AJAX อื่น (tts/save) บน session เดียวกัน

// ค่าชุดคำ/ผล (จาก client) — validate ช่วงค่า
$class_id = (int)($_POST['class_id'] ?? 0);
$hittest  = (int)($_POST['hittest'] ?? 0);
$sethit   = (int)($_POST['sethit'] ?? 0);
$word     = trim((string)($_POST['word'] ?? ''));
$word_id  = ($_POST['word_id'] ?? '') !== '' ? (int)$_POST['word_id'] : null;
$attempts = max(0, (int)($_POST['attempts'] ?? 0));
$correct  = !empty($_POST['correct']) ? 1 : 0;

if ($class_id < 1 || $class_id > 6 || !in_array($hittest, HITTESTS, true)
    || $sethit < 1 || $sethit > 5 || $word === '') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'bad params']);
    exit;
}

try {
    db()->prepare(
        'INSERT INTO practice_log
            (sc_id, stuid, stuname, class_id, hittest, sethit, word_id, word, attempts, correct, created_at)
         VALUES (?,?,?,?,?,?,?,?,?,?,NOW())'
    )->execute([
        (string)$me['sc_id'], (string)$me['stuid'], (string)$me['stuname'],
        $class_id, $hittest, $sethit, $word_id, $word, $attempts, $correct,
    ]);
    echo json_encode(['status' => 'ok']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'save failed']);
}
