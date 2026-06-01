<?php
/**
 * exam_window_toggle.php — เปิด/ปิดการสอบรายรอบ (เฉพาะโรงเรียนที่ login)
 * POST: hittest (1-3), is_open (0|1)
 * scope ด้วย session sc_id เท่านั้น (ไม่รับ sc_id จาก client)
 */
require __DIR__ . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['status' => 'error', 'message' => 'method not allowed'], 405);
}

$scid    = current_sc_id();
$hittest = (int)($_POST['hittest'] ?? 0);
$isOpen  = (int)($_POST['is_open'] ?? -1);

if (!valid_hit($hittest)) {
    json_response(['status' => 'error', 'message' => 'รอบสอบไม่ถูกต้อง'], 400);
}
if ($isOpen !== 0 && $isOpen !== 1) {
    json_response(['status' => 'error', 'message' => 'สถานะไม่ถูกต้อง'], 400);
}

try {
    db()->prepare(
        'INSERT INTO exam_window (sc_id, hittest, is_open, updated_by, updated_at)
         VALUES (?, ?, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE is_open = VALUES(is_open), updated_by = VALUES(updated_by), updated_at = NOW()'
    )->execute([$scid, $hittest, $isOpen, $_SESSION['sc_smis'] ?? null]);
} catch (Throwable $e) {
    json_response(['status' => 'error', 'message' => 'บันทึกสถานะไม่สำเร็จ'], 500);
}

json_response(['status' => 'ok', 'hittest' => $hittest, 'is_open' => $isOpen]);
