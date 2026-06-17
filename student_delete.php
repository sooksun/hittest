<?php
/**
 * student_delete.php — ลบนักเรียนแบบ soft delete (ตั้ง deleted_at) รายคน
 *   ไม่ลบแถวจริง — ซ่อนจากทุกหน้าของโรงเรียน เก็บประวัติ/ผลสอบไว้ครบ
 *   กู้คืนได้เฉพาะผู้ดูแลระบบ (admin_students_trash.php)
 * POST: stuid   (ต้องอยู่ในโรงเรียนที่ login + ปีปัจจุบัน + ยังไม่ถูกลบ)
 */
require __DIR__ . '/includes/auth.php';
require_editor();   // บัญชีผู้ชม (viewer/เขตพื้นที่) ลบไม่ได้

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['status' => 'error', 'message' => 'method not allowed'], 405);
}

$scid  = current_sc_id();
$stuid = (string)($_POST['stuid'] ?? '');

// ตรวจสิทธิ์: นักเรียนต้องอยู่ในโรงเรียนที่ login (ปีปัจจุบัน) และยังไม่ถูกลบ — find_student กรอง deleted_at ให้แล้ว
if (!find_student($stuid)) {
    json_response(['status' => 'error', 'message' => 'ไม่พบนักเรียน หรือไม่มีสิทธิ์'], 403);
}

$by = (string)($_SESSION['user_name'] ?? $_SESSION['sc_smis'] ?? '');

try {
    db()->prepare('UPDATE students SET deleted_at = NOW(), deleted_by = ? WHERE stuid = ? AND sc_id = ? AND years = ? AND deleted_at IS NULL')
        ->execute([$by, $stuid, $scid, current_year()]);
} catch (Throwable $e) {
    json_response(['status' => 'error', 'message' => 'ลบไม่สำเร็จ'], 500);
}

json_response(['status' => 'ok']);
