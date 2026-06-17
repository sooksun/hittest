<?php
/**
 * promote_rollback.php — ยกเลิก (undo) การเลื่อนชั้นทั้งโรงเรียน "ครั้งล่าสุด"
 *   โมเดลผูกปี: ลบ "แถวปีใหม่" ที่การเลื่อนชั้นครั้งนั้นสร้าง (เฉพาะคนที่ยังไม่เริ่มสอบปีใหม่)
 *   - ทำได้เฉพาะ log ล่าสุดของโรงเรียนที่ยังไม่ถูกยกเลิก (rolled_back_at IS NULL)
 *   - RBAC: scope ด้วย sc_id ของ session (ย้อนได้เฉพาะโรงเรียนตน)
 *   - แถวปีเก่า + ผลสอบเดิมไม่ถูกแตะต้อง
 */
require __DIR__ . '/includes/auth.php';
require_editor();   // บัญชีผู้ชม (viewer) ยกเลิกเลื่อนชั้นไม่ได้
require __DIR__ . '/includes/promote_lib.php';

// เมนูเลื่อนชั้นถูกปิดโดยผู้ดูแลระบบ → บล็อก (ผู้ดูแลระบบยังทำได้)
if (!is_admin() && !promote_menu_enabled()) {
    json_response(['status' => 'error', 'message' => 'เมนูเลื่อนชั้นถูกปิดโดยผู้ดูแลระบบ'], 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['status' => 'error', 'message' => 'method not allowed'], 405);
}

$scid = current_sc_id();
$pdo  = db();
try {
    $pdo->beginTransaction();
    $r = promote_rollback_year($pdo, $scid);
    if (!$r['ok']) {
        $pdo->rollBack();
        json_response(['status' => 'error', 'message' => $r['message'] ?? 'ยกเลิกไม่สำเร็จ'], 409);
    }
    $pdo->commit();
    json_response([
        'status'    => 'ok',
        'restored'  => $r['restored'],
        'skipped'   => $r['skipped'],
        'promoted'  => (int)($r['log']['promoted'] ?? 0),
        'graduated' => (int)($r['log']['graduated'] ?? 0),
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    json_response(['status' => 'error', 'message' => 'ยกเลิกการเลื่อนชั้นไม่สำเร็จ'], 500);
}
