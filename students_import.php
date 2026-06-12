<?php
/**
 * students_import.php — นำเข้ารายชื่อนักเรียนใหม่จาก Excel (เฉพาะโรงเรียนที่ login)
 *  - ดาวน์โหลด template (students_import_tpl.php) → กรอก → อัปโหลดที่หน้านี้
 *  - ตรวจทั้งไฟล์ก่อนบันทึก (includes/students_import_lib.php): รหัสซ้ำในไฟล์ /
 *    รหัสของโรงเรียนอื่น / ข้อมูลไม่ครบ = ยกเลิกการนำเข้าทั้งหมด พร้อมรายการให้แก้ไข
 *  - รหัสใหม่ = เพิ่ม · รหัสเดิมของโรงเรียนนี้ = อัปเดต · admin รับย้ายข้ามโรงเรียนได้
 */
require __DIR__ . '/includes/auth.php';
require_editor();   // บัญชีผู้ชม (viewer) นำเข้ารายชื่อไม่ได้
require __DIR__ . '/includes/students_import_lib.php';
require __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$scid      = current_sc_id();
$didImport = false;
$res       = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['file']['tmp_name']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
    $didImport = true;
    try {
        $sheet = IOFactory::load($_FILES['file']['tmp_name'])->getActiveSheet();
        $rows  = $sheet->rangeToArray('A4:H' . $sheet->getHighestDataRow(), null, true, false, false);
        $res   = students_import_run(db(), $scid, current_year(), $rows, is_admin());
    } catch (Throwable $e) {
        $res = ['ok' => false, 'errors' => ['อ่านไฟล์ไม่สำเร็จ: ' . $e->getMessage()],
                'rows_seen' => 0, 'imported' => 0, 'updated' => 0, 'generated' => [], 'moved' => []];
    }
}

$page_title = 'นำเข้ารายชื่อนักเรียนใหม่';
$active = 'stu_import';
require __DIR__ . '/includes/header.php';
?>
<h2 class="mb-1">📥 นำเข้ารายชื่อนักเรียนใหม่</h2>
<p class="text-muted mb-4">โรงเรียน <strong><?= htmlspecialchars($_SESSION['sc_name']) ?></strong> — เพิ่ม/อัปเดตได้เฉพาะโรงเรียนของท่าน</p>

<?php if ($didImport): ?>
    <?php if ($res['ok']): ?>
        <div class="ht-card mb-4" style="border-left:6px solid var(--c-green); max-width:980px">
            <h3 class="mb-1">✅ นำเข้าสำเร็จ</h3>
            <p class="mb-0 text-muted">พบ <?= $res['rows_seen'] ?> แถว ·
                <span class="fw-8" style="color:var(--c-green-ink)">เพิ่มใหม่ <?= $res['imported'] ?></span> ·
                <span class="fw-8" style="color:var(--c-blue-ink)">อัปเดต <?= $res['updated'] ?></span> คน
                <?php if ($res['generated']): ?> · <span class="fw-8" style="color:var(--c-violet-ink, #6C4DFF)">ระบบกำหนดรหัสให้ <?= count($res['generated']) ?></span><?php endif; ?>
                <?php if ($res['moved']): ?> · <span class="fw-8" style="color:var(--c-coral-ink)">รับย้ายจากโรงเรียนอื่น <?= count($res['moved']) ?></span><?php endif; ?>
            </p>
            <?php if ($res['moved']): ?>
                <p class="text-muted mt-2 mb-0" style="font-size:.9rem">
                    รับย้ายด้วยสิทธิ์ผู้ดูแลระบบ: รหัส <?= htmlspecialchars(implode(', ', array_keys($res['moved']))) ?>
                </p>
            <?php endif; ?>
        </div>
        <?php if ($res['generated']): ?>
            <div class="ht-card mb-4" style="border-left:6px solid #6C4DFF; max-width:980px">
                <div class="fw-7 mb-1">🆔 รหัสที่ระบบกำหนดให้ (<?= count($res['generated']) ?>)</div>
                <p class="text-muted mb-2" style="font-size:.9rem">นักเรียนใช้ "รหัสนักเรียน" นี้เข้าสู่ระบบ — โปรดบันทึก/แจ้งให้นักเรียนทราบ</p>
                <div class="ht-table-wrap">
                    <table class="ht-table"><thead><tr><th>ชื่อ-สกุล</th><th>รหัสนักเรียน</th></tr></thead><tbody>
                        <?php foreach ($res['generated'] as $g): ?>
                            <tr><td><?= htmlspecialchars($g['stuname']) ?></td><td class="num fw-7"><?= htmlspecialchars($g['stuid']) ?></td></tr>
                        <?php endforeach; ?>
                    </tbody></table>
                </div>
            </div>
        <?php endif; ?>
        <a href="students_list.php" class="ht-btn mb-4">ดูรายชื่อนักเรียน →</a>
    <?php else: ?>
        <div class="ht-card mb-4" style="border-left:6px solid var(--c-coral); max-width:980px">
            <h3 class="mb-1">❌ ยกเลิกการนำเข้าทั้งหมด — ไม่มีข้อมูลถูกบันทึก</h3>
            <p class="text-muted">พบปัญหา <?= count($res['errors']) ?> รายการ กรุณาแก้ไขไฟล์ตามรายการด้านล่าง แล้วอัปโหลดใหม่อีกครั้ง</p>
            <ul class="mb-0" style="padding-left:20px">
                <?php foreach (array_slice($res['errors'], 0, 50) as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
                <?php if (count($res['errors']) > 50): ?><li>… และอีก <?= count($res['errors']) - 50 ?> รายการ</li><?php endif; ?>
            </ul>
        </div>
    <?php endif; ?>
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
                <li>กรอกข้อมูลตั้งแต่แถวที่ 4 · <strong>เว้นช่องรหัสนักเรียนว่างได้</strong> ระบบจะกำหนดให้อัตโนมัติ</li>
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
            <p class="text-muted mt-3 mb-0" style="font-size:.9rem">
                เว้นช่องรหัสว่าง = ระบบกำหนดรหัสให้อัตโนมัติ (unique เสมอ) ·
                รหัสที่มีในโรงเรียนนี้แล้วจะถูก<strong>อัปเดต</strong> ·
                หากกรอกรหัสเองแล้วซ้ำในไฟล์ หรือเป็นรหัสของโรงเรียนอื่น ระบบจะ<strong>ยกเลิกการนำเข้าทั้งไฟล์</strong>ให้แก้ไขก่อน
            </p>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
