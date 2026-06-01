<?php
/**
 * promote_school.php — เลื่อนชั้นนักเรียนทั้งโรงเรียน (เฉพาะโรงเรียนที่ login)
 *   - ป.1–ป.5 : class_id + 1
 *   - ป.6      : stustatus = 4 (ย้ายออก/จบการศึกษา) — เก็บประวัติ + ผลสอบไว้
 *   - ข้ามนักเรียนที่ย้ายออกแล้ว (stustatus = 4)
 *   - เก็บผลสอบเดิมไว้ทั้งหมด (ไม่ลบ evaluations/studenthit/studenteval)
 * บันทึกประวัติลง promote_log
 */
require __DIR__ . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['status' => 'error', 'message' => 'method not allowed'], 405);
}

$scid = current_sc_id();
$pdo  = db();
try {
    $pdo->beginTransaction();

    // 0) เก็บสภาพ "ก่อนเลื่อน" ของนักเรียนที่จะได้รับผล เพื่อใช้ย้อนกลับ (rollback) ได้แม่นยำ
    $gradStmt = $pdo->prepare('SELECT stuid, class_id, stustatus FROM students
                               WHERE sc_id = ? AND class_id = 6 AND stustatus <> 4');
    $gradStmt->execute([$scid]);
    $gradRows = $gradStmt->fetchAll();

    $promStmt = $pdo->prepare('SELECT stuid, class_id, stustatus FROM students
                               WHERE sc_id = ? AND class_id BETWEEN 1 AND 5 AND stustatus <> 4');
    $promStmt->execute([$scid]);
    $promRows = $promStmt->fetchAll();

    // 1) ป.6 จบ → ย้ายออก (ทำก่อน เพื่อไม่ให้ชนกับ ป.5 ที่กำลังจะเลื่อนขึ้นเป็น ป.6)
    $g = $pdo->prepare('UPDATE students SET stustatus = 4
                        WHERE sc_id = ? AND class_id = 6 AND stustatus <> 4');
    $g->execute([$scid]);
    $graduated = $g->rowCount();

    // 2) ป.1–ป.5 เลื่อนขึ้น 1 ชั้น (ข้ามผู้ที่ย้ายออกแล้ว)
    $p = $pdo->prepare('UPDATE students SET class_id = class_id + 1
                        WHERE sc_id = ? AND class_id BETWEEN 1 AND 5 AND stustatus <> 4');
    $p->execute([$scid]);
    $promoted = $p->rowCount();

    // 3) บันทึกประวัติการเลื่อนชั้น
    $pdo->prepare(
        'INSERT INTO promote_log (sc_id, sc_smis, sc_name, promoted, graduated, created_at)
         VALUES (?, ?, ?, ?, ?, NOW())'
    )->execute([$scid, $_SESSION['sc_smis'] ?? null, $_SESSION['sc_name'] ?? null, $promoted, $graduated]);
    $logId = (int)$pdo->lastInsertId();

    // 4) บันทึกรายคน (ก่อนเลื่อน) ไว้สำหรับการยกเลิก
    $item = $pdo->prepare('INSERT INTO promote_log_item (log_id, stuid, prev_class_id, prev_stustatus, action)
                           VALUES (?,?,?,?,?)');
    foreach ($gradRows as $r) {
        $item->execute([$logId, $r['stuid'], (int)$r['class_id'], (int)$r['stustatus'], 'graduate']);
    }
    foreach ($promRows as $r) {
        $item->execute([$logId, $r['stuid'], (int)$r['class_id'], (int)$r['stustatus'], 'promote']);
    }

    $pdo->commit();
    json_response(['status' => 'ok', 'promoted' => $promoted, 'graduated' => $graduated]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    json_response(['status' => 'error', 'message' => 'เลื่อนชั้นไม่สำเร็จ'], 500);
}
