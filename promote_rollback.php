<?php
/**
 * promote_rollback.php — ยกเลิก (undo) การเลื่อนชั้นทั้งโรงเรียน "ครั้งล่าสุด"
 *   - คืนค่าชั้น/สถานะของนักเรียนแต่ละคนกลับเป็นค่าก่อนเลื่อน (จาก promote_log_item)
 *   - ทำได้เฉพาะ log ล่าสุดของโรงเรียนที่ยังไม่ถูกยกเลิก (rolled_back_at IS NULL)
 *   - RBAC: scope ด้วย sc_id ของ session ทุกจุด (ย้อนได้เฉพาะโรงเรียนตน)
 *   - ผลสอบเดิมไม่ถูกแตะต้องอยู่แล้ว (การเลื่อนชั้นไม่ลบผลสอบ)
 */
require __DIR__ . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['status' => 'error', 'message' => 'method not allowed'], 405);
}

$scid = current_sc_id();
$pdo  = db();
try {
    $pdo->beginTransaction();

    // หา log เลื่อนชั้นล่าสุดที่ยังไม่ถูกยกเลิก (ล็อกแถวกัน race ด้วย FOR UPDATE)
    $logStmt = $pdo->prepare('SELECT id, promoted, graduated FROM promote_log
                              WHERE sc_id = ? AND rolled_back_at IS NULL
                              ORDER BY id DESC LIMIT 1 FOR UPDATE');
    $logStmt->execute([$scid]);
    $log = $logStmt->fetch();

    if (!$log) {
        $pdo->rollBack();
        json_response(['status' => 'error', 'message' => 'ไม่มีรายการเลื่อนชั้นให้ยกเลิก'], 404);
    }

    $logId = (int)$log['id'];
    $items = $pdo->prepare('SELECT stuid, prev_class_id, prev_stustatus FROM promote_log_item WHERE log_id = ?');
    $items->execute([$logId]);
    $rows = $items->fetchAll();

    if (!$rows) {
        // log เก่าก่อน migration (ไม่มีรายละเอียดรายคน) → ย้อนกลับไม่ได้
        $pdo->rollBack();
        json_response(['status' => 'error', 'message' => 'รายการนี้ไม่มีข้อมูลรายคน จึงยกเลิกอัตโนมัติไม่ได้'], 409);
    }

    // คืนค่าชั้น/สถานะรายคน — ผูก sc_id เสมอ (กันแก้ข้ามโรงเรียน)
    $restore = $pdo->prepare('UPDATE students SET class_id = ?, stustatus = ?
                              WHERE stuid = ? AND sc_id = ?');
    $n = 0;
    foreach ($rows as $r) {
        $restore->execute([(int)$r['prev_class_id'], (int)$r['prev_stustatus'], $r['stuid'], $scid]);
        $n += $restore->rowCount();
    }

    // ทำเครื่องหมายว่า log นี้ถูกยกเลิกแล้ว (กันย้อนซ้ำ)
    $pdo->prepare('UPDATE promote_log SET rolled_back_at = NOW() WHERE id = ?')->execute([$logId]);

    $pdo->commit();
    json_response([
        'status'   => 'ok',
        'restored' => $n,
        'promoted' => (int)$log['promoted'],
        'graduated' => (int)$log['graduated'],
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    json_response(['status' => 'error', 'message' => 'ยกเลิกการเลื่อนชั้นไม่สำเร็จ'], 500);
}
