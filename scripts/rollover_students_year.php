<?php
/**
 * scripts/rollover_students_year.php — รันเลื่อนชั้น "ครั้งแรก" หลัง migration 010
 *   สร้าง roster ปีปัจจุบัน (ACADEMIC_YEAR, 2569) จากแถวปีก่อนหน้า (2568) "ทุกโรงเรียน"
 *   เพื่อให้แอป (ที่กรอง years = ACADEMIC_YEAR) ไม่ว่างเปล่าหลัง migration
 *
 *   php scripts/rollover_students_year.php            # dry-run: รายงานอย่างเดียว
 *   php scripts/rollover_students_year.php --apply     # ทำจริง (เลื่อน 2568→2569 ทุกโรงเรียน)
 *
 * Idempotent: ข้ามโรงเรียนที่มี roster ปีใหม่อยู่แล้ว + INSERT IGNORE กันชนรายคน (รันซ้ำได้)
 * เลื่อนทีละโรงเรียน (commit ต่อโรงเรียน) — ทนทาน/รันต่อได้ถ้าค้างกลางคัน
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/promote_lib.php';

$apply    = in_array('--apply', $argv, true);
$pdo      = db();
$toYear   = current_year();
$fromYear = $toYear - 1;

fwrite(STDOUT, "== rollover students {$fromYear} → {$toYear} " . ($apply ? '(APPLY)' : '(DRY-RUN)') . " ==\n");

// โรงเรียนที่มี roster ปีก่อนหน้า แต่ยังไม่มี roster ปีใหม่
$schStmt = $pdo->prepare(
    'SELECT DISTINCT s.sc_id FROM students s
     WHERE s.years = ? AND s.class_id BETWEEN 1 AND 6 AND s.stustatus <> 4
       AND NOT EXISTS (SELECT 1 FROM students t WHERE t.sc_id = s.sc_id AND t.years = ?)'
);
$schStmt->execute([$fromYear, $toYear]);
$schools = $schStmt->fetchAll(PDO::FETCH_COLUMN);

fwrite(STDOUT, "โรงเรียนที่ต้องเลื่อน: " . count($schools) . "\n");
if (!$schools) {
    fwrite(STDOUT, "ไม่มีโรงเรียนที่ค้างเลื่อน (อาจรันไปแล้ว)\n");
    exit(0);
}

$info     = $pdo->prepare('SELECT sc_smis, sc_name FROM schools WHERE sc_id = ? LIMIT 1');
$cntStmt  = $pdo->prepare(
    'SELECT SUM(class_id BETWEEN 1 AND 5) prom, SUM(class_id = 6) grad
     FROM students WHERE sc_id = ? AND years = ? AND stustatus <> 4'
);
$totP = $totG = $totSchools = 0;

foreach ($schools as $sc) {
    if ($apply) {
        try {
            $pdo->beginTransaction();
            $info->execute([$sc]);
            $sch = $info->fetch() ?: ['sc_smis' => null, 'sc_name' => null];
            $r = promote_school_year($pdo, (string)$sc, $fromYear, $toYear, $sch['sc_smis'], $sch['sc_name']);
            $pdo->commit();
            $totP += $r['promoted'];
            $totG += $r['graduated'];
            $totSchools++;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            fwrite(STDERR, "school {$sc} FAILED: {$e->getMessage()}\n");
        }
    } else {
        $cntStmt->execute([$sc, $fromYear]);
        $c = $cntStmt->fetch();
        $totP += (int)$c['prom'];
        $totG += (int)$c['grad'];
        $totSchools++;
    }
}

fwrite(STDOUT, sprintf("%s: %d โรงเรียน · เลื่อนขึ้น %d คน · จบ %d คน\n",
    $apply ? 'เสร็จ' : 'จะทำ', $totSchools, $totP, $totG));
if (!$apply) {
    fwrite(STDOUT, "(ใส่ --apply เพื่อทำจริง)\n");
}
exit(0);
