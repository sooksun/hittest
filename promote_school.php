<?php
/**
 * promote_school.php — เลื่อนชั้นนักเรียนทั้งโรงเรียน (เฉพาะโรงเรียนที่ login)
 *   โมเดลผูกปี: สร้าง "แถวปีใหม่" (ACADEMIC_YEAR) จากแถวปีก่อนหน้า
 *   - ป.1–ป.5 : สร้างแถวปีใหม่ class+1, คะแนน/ธงสอบ = 0 (ปีใหม่สอบใหม่)
 *   - ป.6      : จบ ไม่สร้างแถวปีใหม่
 *   - แถวปีเก่าไม่ถูกแก้ (เป็นประวัติ) · ผลสอบเดิมไม่ถูกแตะต้อง
 * บันทึกประวัติลง promote_log + promote_log_item (ดู includes/promote_lib.php)
 */
require __DIR__ . '/includes/auth.php';
require_editor();   // บัญชีผู้ชม (viewer) เลื่อนชั้นไม่ได้
require __DIR__ . '/includes/promote_lib.php';

// เมนูเลื่อนชั้นถูกปิดโดยผู้ดูแลระบบ → บล็อก (ผู้ดูแลระบบยังทำได้)
if (!is_admin() && !promote_menu_enabled()) {
    json_response(['status' => 'error', 'message' => 'เมนูเลื่อนชั้นถูกปิดโดยผู้ดูแลระบบ'], 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['status' => 'error', 'message' => 'method not allowed'], 405);
}

$scid     = current_sc_id();
$toYear   = current_year();          // ปีปัจจุบัน (2569)
$fromYear = $toYear - 1;             // เลื่อนจากปีก่อนหน้า (2568)
$pdo      = db();
try {
    $pdo->beginTransaction();
    $r = promote_school_year($pdo, $scid, $fromYear, $toYear,
        $_SESSION['sc_smis'] ?? null, $_SESSION['sc_name'] ?? null);
    $pdo->commit();
    json_response(['status' => 'ok', 'promoted' => $r['promoted'], 'graduated' => $r['graduated']]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    json_response(['status' => 'error', 'message' => 'เลื่อนชั้นไม่สำเร็จ'], 500);
}
