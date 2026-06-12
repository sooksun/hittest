<?php
/**
 * includes/promote_lib.php — ตรรกะเลื่อนชั้นแบบ "ผูกปี" (สร้างแถวปีใหม่ ไม่แก้แถวปีเก่า)
 *
 * โมเดลใหม่ (หลัง migration 010):
 *   - students เป็น 1 แถว/คน/ปี (PK = stuid, years)
 *   - เลื่อนชั้น = "สร้างแถวปีใหม่" จากปีก่อนหน้า (class+1, คะแนน/ธงสอบ=0) — แถวปีเก่าคงเดิมเป็นประวัติ
 *   - ป.6 = จบ ไม่สร้างแถวปีใหม่
 *   - ยกเลิก (rollback) = ลบแถวปีใหม่ที่สร้าง (กันลบถ้าเริ่มสอบปีใหม่แล้ว)
 *
 * ใช้ร่วม: promote_school.php (โรงเรียนเดียว), admin_promote_all.php (ทั้งระบบ),
 *          scripts/rollover_students_year.php (รัน 2568→2569 ครั้งแรก), tests
 *
 * ทุกฟังก์ชันทำงาน "ภายในทรานแซกชันที่ผู้เรียกเปิดไว้แล้ว" (ไม่ begin/commit เอง)
 */

/** คอลัมน์ identity ของ students ที่คัดลอกไปแถวปีใหม่ (ไม่รวมคะแนน/ปี/วันที่) */
const PROMOTE_COPY_COLS = ['stuid', 'stuname', 'sc_id', 'studentId', 'genderName', 'rooms', 'stustatus',
    'sethit1', 'sethit2', 'sethit3'];

/**
 * เลื่อนชั้น 1 โรงเรียน จาก $fromYear → $toYear
 *   - ป.1–5 (stustatus<>4) ปี fromYear → สร้างแถวปี toYear: class+1, hit1-3=0, hit*tested=0, copy identity+sethit
 *   - ป.6 (stustatus<>4) → จบ (นับไว้ใน log แต่ไม่สร้างแถว)
 *   - ข้ามคนที่มีแถวปี toYear อยู่แล้ว (idempotent ด้วย INSERT IGNORE บน PK (stuid,years))
 * คืน ['promoted'=>int, 'graduated'=>int, 'logId'=>int]
 */
function promote_school_year(PDO $pdo, string $scId, int $fromYear, int $toYear, ?string $smis = null, ?string $scName = null): array
{
    // รายชื่อที่จะเลื่อน (ยังไม่มีแถวปีใหม่) — เก็บไว้ลง log_item เพื่อ rollback
    $promStmt = $pdo->prepare(
        'SELECT stuid, class_id, stustatus FROM students src
         WHERE src.sc_id = ? AND src.years = ? AND src.class_id BETWEEN 1 AND 5 AND src.stustatus <> 4
           AND NOT EXISTS (SELECT 1 FROM students d WHERE d.stuid = src.stuid AND d.years = ?)'
    );
    $promStmt->execute([$scId, $fromYear, $toYear]);
    $promRows = $promStmt->fetchAll();

    // ป.6 ที่จบ (เพื่อนับ/ลง log — ไม่สร้างแถวปีใหม่)
    $gradStmt = $pdo->prepare(
        'SELECT stuid, class_id, stustatus FROM students
         WHERE sc_id = ? AND years = ? AND class_id = 6 AND stustatus <> 4'
    );
    $gradStmt->execute([$scId, $fromYear]);
    $gradRows = $gradStmt->fetchAll();

    // สร้างแถวปีใหม่ (bulk) — INSERT IGNORE กันชน PK (stuid,toYear) ถ้ารันซ้ำ
    $ins = $pdo->prepare(
        'INSERT IGNORE INTO students
            (stuid, stuname, sc_id, studentId, genderName, class_id, rooms, stustatus,
             hit1, hit1tested, hit2, hit2tested, hit3, hit3tested, sethit1, sethit2, sethit3, years, updatedDate)
         SELECT stuid, stuname, sc_id, studentId, genderName, class_id + 1, rooms, stustatus,
                0, 0, 0, 0, 0, 0, sethit1, sethit2, sethit3, ?, NOW()
         FROM students
         WHERE sc_id = ? AND years = ? AND class_id BETWEEN 1 AND 5 AND stustatus <> 4'
    );
    $ins->execute([$toYear, $scId, $fromYear]);
    $promoted  = $ins->rowCount();          // จำนวนแถวที่สร้างจริง (IGNORE ข้ามที่มีแล้ว)
    $graduated = count($gradRows);

    // บันทึกประวัติ (ระดับ event + รายคน) — years = ปีใหม่ที่สร้าง
    $pdo->prepare(
        'INSERT INTO promote_log (sc_id, sc_smis, sc_name, promoted, graduated, created_at)
         VALUES (?,?,?,?,?,NOW())'
    )->execute([$scId, $smis, $scName, $promoted, $graduated]);
    $logId = (int)$pdo->lastInsertId();

    $item = $pdo->prepare(
        'INSERT INTO promote_log_item (log_id, stuid, years, prev_class_id, prev_stustatus, action)
         VALUES (?,?,?,?,?,?)'
    );
    foreach ($promRows as $r) {
        $item->execute([$logId, $r['stuid'], $toYear, (int)$r['class_id'], (int)$r['stustatus'], 'promote']);
    }
    foreach ($gradRows as $r) {
        $item->execute([$logId, $r['stuid'], $toYear, (int)$r['class_id'], (int)$r['stustatus'], 'graduate']);
    }

    return ['promoted' => $promoted, 'graduated' => $graduated, 'logId' => $logId];
}

/**
 * ยกเลิกการเลื่อนชั้น "ครั้งล่าสุด" ของโรงเรียน (โมเดลใหม่ = ลบแถวปีใหม่ที่สร้าง)
 *   - ลบเฉพาะแถวปี toYear ที่ "ยังไม่เริ่มสอบ" (hit*tested ทั้งหมด = 0) — กันลบทับข้อมูลจริง
 *   - log แบบเก่า (ก่อนผูกปี, years = NULL) → ยกเลิกอัตโนมัติไม่ได้ (คืน error ให้ผู้เรียกจัดการ)
 * ต้องอยู่ในทรานแซกชันที่เปิดไว้แล้ว
 * คืน ['ok'=>bool, 'restored'=>int, 'skipped'=>int, 'message'=>?string, 'log'=>?array]
 */
function promote_rollback_year(PDO $pdo, string $scId): array
{
    $logStmt = $pdo->prepare(
        'SELECT id, promoted, graduated FROM promote_log
         WHERE sc_id = ? AND rolled_back_at IS NULL ORDER BY id DESC LIMIT 1 FOR UPDATE'
    );
    $logStmt->execute([$scId]);
    $log = $logStmt->fetch();
    if (!$log) {
        return ['ok' => false, 'restored' => 0, 'skipped' => 0, 'message' => 'ไม่มีรายการเลื่อนชั้นให้ยกเลิก', 'log' => null];
    }
    $logId = (int)$log['id'];

    $items = $pdo->prepare("SELECT stuid, years FROM promote_log_item WHERE log_id = ? AND action = 'promote'");
    $items->execute([$logId]);
    $rows = $items->fetchAll();
    if (!$rows) {
        return ['ok' => false, 'restored' => 0, 'skipped' => 0,
                'message' => 'รายการนี้ไม่มีข้อมูลรายคน จึงยกเลิกอัตโนมัติไม่ได้', 'log' => $log];
    }
    if ($rows[0]['years'] === null) {
        return ['ok' => false, 'restored' => 0, 'skipped' => 0,
                'message' => 'รายการเลื่อนชั้นแบบเดิม (ก่อนระบบผูกปี) ยกเลิกอัตโนมัติไม่ได้', 'log' => $log];
    }

    // ลบแถวปีใหม่เฉพาะที่ยังไม่เริ่มสอบ
    $del = $pdo->prepare(
        'DELETE FROM students WHERE stuid = ? AND years = ? AND sc_id = ?
           AND hit1tested = 0 AND hit2tested = 0 AND hit3tested = 0'
    );
    $restored = 0;
    foreach ($rows as $r) {
        $del->execute([$r['stuid'], (int)$r['years'], $scId]);
        $restored += $del->rowCount();
    }
    $skipped = count($rows) - $restored;

    $pdo->prepare('UPDATE promote_log SET rolled_back_at = NOW() WHERE id = ?')->execute([$logId]);

    return ['ok' => true, 'restored' => $restored, 'skipped' => $skipped, 'message' => null, 'log' => $log];
}
