<?php
/**
 * student_update.php — แก้ไขข้อมูลนักเรียนรายคน (จำกัดเฉพาะโรงเรียนที่ login)
 * POST: stuid, stuname, class_id(1-6), rooms(>=1), stustatus(key ใน STU_STATUS), sethit1/2/3(1-5)
 * ไม่อนุญาตให้แก้ stuid / sc_id (PK + ขอบเขตโรงเรียน)
 */
require __DIR__ . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['status' => 'error', 'message' => 'method not allowed'], 405);
}

$scid  = current_sc_id();
$stuid = (string)($_POST['stuid'] ?? '');

// ตรวจสิทธิ์: นักเรียนต้องอยู่ในโรงเรียนที่ login
if (!find_student($stuid)) {
    json_response(['status' => 'error', 'message' => 'ไม่พบนักเรียน หรือไม่มีสิทธิ์'], 403);
}

$stuname  = trim((string)($_POST['stuname'] ?? ''));
$class_id = (int)($_POST['class_id'] ?? 0);
$rooms    = (int)($_POST['rooms'] ?? 0);
$status   = (int)($_POST['stustatus'] ?? 0);

if ($stuname === '' || mb_strlen($stuname) > 255) {
    json_response(['status' => 'error', 'message' => 'กรุณากรอกชื่อ - สกุล (ไม่เกิน 255 ตัวอักษร)'], 400);
}
if ($class_id < 1 || $class_id > 6) {
    json_response(['status' => 'error', 'message' => 'ชั้นไม่ถูกต้อง'], 400);
}
if ($rooms < 1) {
    json_response(['status' => 'error', 'message' => 'ห้องไม่ถูกต้อง'], 400);
}
if (!array_key_exists($status, STU_STATUS)) {
    json_response(['status' => 'error', 'message' => 'ประเภทนักเรียนไม่ถูกต้อง'], 400);
}
$sets = [];
foreach ([1, 2, 3] as $n) {
    $v = (int)($_POST["sethit$n"] ?? 0);
    if ($v < 1 || $v > 5) {
        json_response(['status' => 'error', 'message' => "ชุดคำ Hit-$n ต้องเป็น 1–5"], 400);
    }
    $sets[$n] = $v;
}

try {
    db()->prepare(
        'UPDATE students
            SET stuname = ?, class_id = ?, rooms = ?, stustatus = ?,
                sethit1 = ?, sethit2 = ?, sethit3 = ?
          WHERE stuid = ? AND sc_id = ?'
    )->execute([$stuname, $class_id, $rooms, $status, $sets[1], $sets[2], $sets[3], $stuid, $scid]);
} catch (Throwable $e) {
    json_response(['status' => 'error', 'message' => 'บันทึกไม่สำเร็จ'], 500);
}

json_response(['status' => 'ok']);
