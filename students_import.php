<?php
/**
 * students_import.php — นำเข้ารายชื่อนักเรียนใหม่จาก Excel (เฉพาะโรงเรียนที่ login)
 *  - ดาวน์โหลด template (students_import_tpl.php) → กรอก → อัปโหลดที่หน้านี้
 *  - รหัสใหม่ = เพิ่ม · รหัสเดิมของโรงเรียนนี้ = อัปเดต · รหัสของโรงเรียนอื่น = ข้าม (กันแย่งข้อมูล)
 */
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$scid     = current_sc_id();
$imported = 0;
$updated  = 0;
$skipped  = 0;
$rowsSeen = 0;
$errors   = [];
$didImport = false;

/** แปลงค่าเป็น int ในช่วง [min,max] ไม่งั้นใช้ค่า default */
function clamp_int($v, int $min, int $max, int $default): int
{
    $n = (int)$v;
    return ($n < $min || $n > $max) ? $default : $n;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['file']['tmp_name']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
    $didImport = true;
    try {
        $sheet      = IOFactory::load($_FILES['file']['tmp_name'])->getActiveSheet();
        $highestRow = $sheet->getHighestDataRow();
        $rows       = $sheet->rangeToArray("A4:H{$highestRow}", null, true, false, false);

        $pdo  = db();
        $find = $pdo->prepare('SELECT sc_id FROM students WHERE stuid = ?');
        $ins  = $pdo->prepare('INSERT INTO students
            (stuid, stuname, sc_id, class_id, rooms, stustatus,
             hit1, hit1tested, hit2, hit2tested, hit3, hit3tested, sethit1, sethit2, sethit3)
            VALUES (?,?,?,?,?,?, 0,0,0,0,0,0, ?,?,?)');
        $upd  = $pdo->prepare('UPDATE students
            SET stuname = ?, class_id = ?, rooms = ?, stustatus = ?, sethit1 = ?, sethit2 = ?, sethit3 = ?
            WHERE stuid = ? AND sc_id = ?');

        foreach ($rows as $row) {
            $stuid = trim((string)($row[0] ?? ''));
            if ($stuid === '') {
                continue;                                  // แถวว่าง
            }
            $rowsSeen++;
            $stuname  = trim((string)($row[1] ?? ''));
            $class_id = (int)($row[2] ?? 0);
            $rooms    = clamp_int($row[3] ?? 1, 1, 9999, 1);
            $status   = clamp_int($row[4] ?? 1, 1, 4, 1);   // STU_STATUS keys = 1..4
            $set1     = clamp_int($row[5] ?? 1, 1, 5, 1);
            $set2     = clamp_int($row[6] ?? 1, 1, 5, 1);
            $set3     = clamp_int($row[7] ?? 1, 1, 5, 1);

            if ($stuname === '') {
                $errors[] = "รหัส $stuid: ไม่มีชื่อ-สกุล"; $skipped++; continue;
            }
            if ($class_id < 1 || $class_id > 6) {
                $errors[] = "รหัส $stuid: ชั้นไม่ถูกต้อง (ต้อง 1-6)"; $skipped++; continue;
            }

            try {
                $find->execute([$stuid]);
                $ex = $find->fetch();
                if ($ex) {
                    if ((string)$ex['sc_id'] !== (string)$scid) {
                        $errors[] = "รหัส $stuid: มีอยู่ในโรงเรียนอื่นแล้ว — ข้าม";
                        $skipped++;
                        continue;
                    }
                    $upd->execute([$stuname, $class_id, $rooms, $status, $set1, $set2, $set3, $stuid, $scid]);
                    $updated++;
                } else {
                    $ins->execute([$stuid, $stuname, $scid, $class_id, $rooms, $status, $set1, $set2, $set3]);
                    $imported++;
                }
            } catch (Throwable $e) {
                $errors[] = "รหัส $stuid: บันทึกไม่สำเร็จ";
                $skipped++;
            }
        }
    } catch (Throwable $e) {
        $errors[] = 'อ่านไฟล์ไม่สำเร็จ: ' . $e->getMessage();
    }
}

$page_title = 'นำเข้ารายชื่อนักเรียนใหม่';
$active = 'stu_import';
require __DIR__ . '/includes/header.php';
?>
<h2 class="mb-1">📥 นำเข้ารายชื่อนักเรียนใหม่</h2>
<p class="text-muted mb-4">โรงเรียน <strong><?= htmlspecialchars($_SESSION['sc_name']) ?></strong> — เพิ่ม/อัปเดตได้เฉพาะโรงเรียนของท่าน</p>

<?php if ($didImport): ?>
    <div class="ht-card mb-4" style="border-left:6px solid var(--c-green); max-width:980px">
        <h3 class="mb-1">ผลการนำเข้า</h3>
        <p class="mb-0 text-muted">พบ <?= $rowsSeen ?> แถว ·
            <span class="fw-8" style="color:var(--c-green-ink)">เพิ่มใหม่ <?= $imported ?></span> ·
            <span class="fw-8" style="color:var(--c-blue-ink)">อัปเดต <?= $updated ?></span>
            <?php if ($skipped): ?> · <span class="fw-8" style="color:var(--c-coral-ink)">ข้าม <?= $skipped ?></span><?php endif; ?> คน
        </p>
    </div>
    <?php if ($errors): ?>
        <div class="ht-card mb-4" style="border-left:6px solid var(--c-coral); max-width:980px">
            <div class="fw-7 mb-2">รายการที่ข้าม / ผิดพลาด (<?= count($errors) ?>)</div>
            <ul class="mb-0" style="padding-left:20px">
                <?php foreach (array_slice($errors, 0, 50) as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
                <?php if (count($errors) > 50): ?><li>… และอีก <?= count($errors) - 50 ?> รายการ</li><?php endif; ?>
            </ul>
        </div>
    <?php endif; ?>
    <a href="students_list.php" class="ht-btn mb-4">ดูรายชื่อนักเรียน →</a>
<?php endif; ?>

<div class="row g-4" style="max-width:980px">
    <div class="col-lg-6">
        <div class="ht-card" style="height:100%">
            <span class="ht-badge t-blue mb-3">ขั้นที่ 1</span>
            <h3 class="mt-2">ดาวน์โหลดแบบฟอร์ม Excel</h3>
            <p class="text-muted mt-2">หัวคอลัมน์: รหัสนักเรียน · ชื่อ-สกุล · ชั้น · ห้อง · ประเภท · ชุด Hit-1/2/3</p>
            <a href="students_import_tpl.php" class="ht-btn ht-btn--lg mt-3">⬇️ ดาวน์โหลด Template</a>
            <p class="text-muted mt-3 mb-0" style="font-size:.9rem">ประเภท: 1=ปกติ 2=พิเศษ 3=ขาดสอบ 4=ย้ายออก (เว้นว่าง=1) · ชุดคำ 1–5 (เว้นว่าง=1)</p>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="ht-card" style="height:100%">
            <span class="ht-badge t-green mb-3">ขั้นที่ 2</span>
            <h3 class="mt-2">อัปโหลดไฟล์ที่กรอกแล้ว</h3>
            <ol class="mt-3" style="padding-left:20px; line-height:2">
                <li>กรอกข้อมูลตั้งแต่แถวที่ 4 (รหัสนักเรียนห้ามซ้ำ)</li>
                <li>บันทึกเป็น .xlsx แล้วอัปโหลดด้านล่าง</li>
            </ol>
            <form method="post" action="students_import.php" enctype="multipart/form-data" class="mt-3"
                  onsubmit="return this.file.files.length>0">
                <div class="ht-field">
                    <label class="ht-label">ไฟล์ Excel รายชื่อ (.xlsx)</label>
                    <input class="ht-input" type="file" name="file" accept=".xlsx,.xls" required>
                </div>
                <button class="ht-btn ht-btn--lg ht-btn--green mt-4" type="submit">⬆️ นำเข้ารายชื่อ</button>
            </form>
            <p class="text-muted mt-3 mb-0" style="font-size:.9rem">รหัสที่มีในโรงเรียนนี้แล้วจะถูก<strong>อัปเดต</strong> · รหัสของโรงเรียนอื่นจะถูกข้าม</p>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
