<?php
/**
 * student_moveout.php — ตั้งสถานะนักเรียนเป็น "ย้ายออก" (stustatus = 4) รายคน
 *   ไม่ต้องระบุโรงเรียนปลายทาง — แค่ทำเครื่องหมายว่าย้ายออกจากโรงเรียนนี้ (เก็บประวัติ/ผลสอบไว้)
 * POST: stuid   (ต้องอยู่ในโรงเรียนที่ login + ปีปัจจุบัน)
 */
require __DIR__ . '/includes/auth.php';
require_editor();   // บัญชีผู้ชม (viewer) ทำไม่ได้

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['status' => 'error', 'message' => 'method not allowed'], 405);
}

$scid  = current_sc_id();
$stuid = (string)($_POST['stuid'] ?? '');

// ตรวจสิทธิ์: นักเรียนต้องอยู่ในโรงเรียนที่ login (ปีปัจจุบัน)
if (!find_student($stuid)) {
    json_response(['status' => 'error', 'message' => 'ไม่พบนักเรียน หรือไม่มีสิทธิ์'], 403);
}

try {
    db()->prepare('UPDATE students SET stustatus = 4 WHERE stuid = ? AND sc_id = ? AND years = ?')
        ->execute([$stuid, $scid, current_year()]);
} catch (Throwable $e) {
    json_response(['status' => 'error', 'message' => 'ย้ายออกไม่สำเร็จ'], 500);
}

json_response(['status' => 'ok']);
