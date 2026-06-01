<?php
/** cancel_result.php — ยกเลิกการสอบ 1 รอบ (ลบผล + คืนสถานะยังไม่สอบ) */
require __DIR__ . '/includes/auth.php';

$stuid   = (string)($_REQUEST['stuid'] ?? '');
$hittest = (int)($_REQUEST['hittest'] ?? 0);
if (!valid_hit($hittest) || $stuid === '') {
    json_response(['status' => 'error', 'message' => 'พารามิเตอร์ไม่ถูกต้อง'], 400);
}

// ตรวจสิทธิ์: นักเรียนต้องอยู่ในโรงเรียนที่ login
if (!find_student($stuid)) {
    json_response(['status' => 'error', 'message' => 'ไม่พบนักเรียน หรือไม่มีสิทธิ์'], 403);
}

$pdo = db();
try {
    $pdo->beginTransaction();
    $pdo->prepare('DELETE FROM evaluations WHERE stuid = ? AND hittest = ?')->execute([$stuid, $hittest]);
    $pdo->prepare('DELETE FROM studenteval WHERE stuid = ? AND hittest = ?')->execute([$stuid, $hittest]);
    $pdo->prepare('DELETE FROM studenthit  WHERE stuid = ? AND hit = ?')->execute([$stuid, $hittest]);
    $pdo->prepare("UPDATE students SET `hit{$hittest}` = 0, `hit{$hittest}tested` = 0 WHERE stuid = ?")->execute([$stuid]);
    $pdo->commit();
} catch (PDOException $e) {
    $pdo->rollBack();
    json_response(['status' => 'error', 'message' => 'ลบไม่สำเร็จ'], 500);
}

json_response(['status' => 'ok']);
