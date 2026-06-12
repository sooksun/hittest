<?php
/**
 * paper_import.php — นำเข้า Excel คะแนนสอบกระดาษ (ทั้งโรงเรียน)
 * อ่านไฟล์ที่ download จาก paper_export.php (กรอกข้อ1..20 แล้ว)
 * เขียนผลลง evaluations + studenthit + students + studenteval ผ่าน save_hit_result()
 */
require __DIR__ . '/includes/auth.php';
require_editor();   // บัญชีผู้ชม (viewer) นำเข้าผลไม่ได้
require __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

/** ตีความค่าช่องคะแนน -> 1 (ถูก) / 0 (ผิด) */
function cell_correct($v): int
{
    if ($v === null) {
        return 0;
    }
    $s = mb_strtolower(trim((string)$v));
    return in_array($s, ['1', '1.0', '✓', '✔', '/', 'y', 'yes', 'true', 'ถูก'], true) ? 1 : 0;
}

$errors  = [];
$imported = 0;
$skipped  = 0;
$rowsSeen = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['file']['tmp_name']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
    try {
        $sheet = IOFactory::load($_FILES['file']['tmp_name'])->getActiveSheet();
        $highestRow = $sheet->getHighestDataRow();
        // อ่าน A4 .. AB{highest} (28 คอลัมน์) เป็น array 0-index
        $rows = $sheet->rangeToArray("A4:AB{$highestRow}", null, true, false, false);

        $pdo = db();
        $year = current_year();   // ผูกผลนำเข้ากับปีการศึกษาปัจจุบัน (จุดเดียวกับทั้งระบบ)

        foreach ($rows as $row) {
            $stuid = trim((string)($row[1] ?? ''));   // คอลัมน์ B
            if ($stuid === '') {
                continue;                              // แถวว่าง
            }
            $rowsSeen++;
            $hittest = (int)($row[5] ?? 0);            // คอลัมน์ F (รอบ)

            if (!valid_hit($hittest)) {
                $errors[] = "รหัส $stuid: รอบ (Hit) ไม่ถูกต้อง";
                $skipped++;
                continue;
            }

            if (!exam_is_open($hittest)) {
                $errors[] = "รหัส $stuid: รอบ Hit-$hittest ถูกปิดอยู่ — ไม่นำเข้า";
                $skipped++;
                continue;
            }

            $student = find_student($stuid);           // จำกัดเฉพาะโรงเรียนที่ login
            if (!$student) {
                $errors[] = "รหัส $stuid: ไม่พบนักเรียนในโรงเรียนนี้";
                $skipped++;
                continue;
            }

            // ข้อ1..20 = คอลัมน์ H..AA (index 7..26)
            $items = [];
            for ($i = 1; $i <= WORDS_PER_SET; $i++) {
                $items[$i] = cell_correct($row[6 + $i] ?? null);
            }

            try {
                $pdo->beginTransaction();
                save_hit_result($pdo, $student, $hittest, $year, $items);
                $pdo->commit();
                $imported++;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $errors[] = "รหัส $stuid: บันทึกไม่สำเร็จ";
                $skipped++;
            }
        }
    } catch (Throwable $e) {
        $errors[] = 'อ่านไฟล์ไม่สำเร็จ: ' . $e->getMessage();
    }
} else {
    header('Location: paper.php');
    exit;
}

$page_title = 'นำเข้าผลสอบกระดาษ';
$active = 'paper';
require __DIR__ . '/includes/header.php';
?>
<a href="paper.php" class="ht-btn ht-btn--ghost ht-btn--sm mb-4">← กลับหน้าสอบด้วยกระดาษ</a>

<div class="ht-card t-green mb-4" style="border-left:6px solid var(--c-green)">
    <h2 class="mb-1">ผลการนำเข้าคะแนนสอบกระดาษ</h2>
    <p class="mb-0 text-muted">
        พบ <?= $rowsSeen ?> แถว ·
        <span class="fw-8" style="color:var(--c-green-ink)">นำเข้าสำเร็จ <?= $imported ?> คน</span>
        <?php if ($skipped): ?> · <span class="fw-8" style="color:var(--c-coral-ink)">ข้าม <?= $skipped ?> คน</span><?php endif; ?>
    </p>
</div>

<?php if ($errors): ?>
    <div class="ht-card t-coral" style="border-left:6px solid var(--c-coral)">
        <div class="fw-7 mb-2">รายการที่ข้าม / ผิดพลาด (<?= count($errors) ?>)</div>
        <ul class="mb-0" style="padding-left:20px">
            <?php foreach (array_slice($errors, 0, 50) as $e): ?>
                <li><?= htmlspecialchars($e) ?></li>
            <?php endforeach; ?>
            <?php if (count($errors) > 50): ?><li>… และอีก <?= count($errors) - 50 ?> รายการ</li><?php endif; ?>
        </ul>
    </div>
<?php else: ?>
    <div class="ht-badge ht-badge--done" style="font-size:1rem">✓ นำเข้าครบทุกคน ไม่มีข้อผิดพลาด</div>
<?php endif; ?>

<div class="mt-4">
    <a href="students_list.php?class_id=<?= (int)($student['class_id'] ?? 1) ?>" class="ht-btn">ดูรายชื่อ + ผลสอบ →</a>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
