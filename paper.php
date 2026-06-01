<?php
/** paper.php — สอบด้วยกระดาษ: ดาวน์โหลดแบบกรอกคะแนน → กรอก → อัปโหลดทั้งโรงเรียน */
require __DIR__ . '/includes/auth.php';

$class_id = (int)($_GET['class_id'] ?? 1);
if ($class_id < 1 || $class_id > 6) {
    $class_id = 1;
}
$cnt = db()->prepare('SELECT COUNT(*) c FROM students WHERE sc_id = ? AND class_id = ?');
$cnt->execute([current_sc_id(), $class_id]);
$cnt = (int)$cnt->fetch()['c'];

$win = exam_windows();   // สถานะเปิด/ปิดรายรอบของโรงเรียนนี้

$page_title = 'สอบด้วยกระดาษ';
$active = 'paper';
require __DIR__ . '/includes/header.php';
?>
<h2 class="mb-1">📝 สอบด้วยกระดาษ</h2>
<p class="text-muted mb-4">ขั้นตอน: <strong>1)</strong> ตรวจรายชื่อ → <strong>2)</strong> ดาวน์โหลด Excel กรอกคะแนน → <strong>3)</strong> กรอกผลสอบทั้งโรงเรียน → <strong>4)</strong> อัปโหลดกลับเข้าระบบ</p>

<div class="row g-4">
    <!-- ขั้นที่ 1–2: ตรวจรายชื่อ + ดาวน์โหลด -->
    <div class="col-lg-6">
        <div class="ht-card" style="height:100%">
            <span class="ht-badge t-blue mb-3">ขั้นที่ 1 – 2</span>
            <h3 class="mt-2">ตรวจรายชื่อ &amp; ดาวน์โหลดแบบกรอกคะแนน</h3>

            <div class="ht-field mt-3">
                <label class="ht-label">เลือกชั้น</label>
                <select class="ht-select" onchange="location='paper.php?class_id='+this.value">
                    <?php for ($c = 1; $c <= 6; $c++): ?>
                        <option value="<?= $c ?>" <?= $c === $class_id ? 'selected' : '' ?>>ป.<?= $c ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <p class="mt-3 mb-2">
                ป.<?= $class_id ?> มีนักเรียน <strong><?= $cnt ?></strong> คน
                · <a href="students_list.php?class_id=<?= $class_id ?>">ตรวจรายชื่อ</a>
            </p>

            <div class="ht-field mt-2">
                <label class="ht-label">เลือกรอบสอบ</label>
                <select id="hitSel" class="ht-select">
                    <?php foreach ([1, 2, 3] as $h): $o = $win[$h] ?? true; ?>
                        <option value="<?= $h ?>"<?= $o ? '' : ' disabled' ?>>Hit-<?= $h ?><?= $o ? '' : ' — ปิดสอบ' ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (in_array(false, $win, true)): ?>
                    <p class="mb-0 mt-1" style="font-size:.86rem;color:var(--c-coral-ink)">🔒 รอบที่ปิดสอบ ดาวน์โหลด/นำเข้าไม่ได้ — เปิดได้ที่เมนู "จัดการสอบ"</p>
                <?php endif; ?>
            </div>

            <button class="ht-btn ht-btn--lg mt-4" onclick="downloadTpl()">⬇️ ดาวน์โหลด Excel (ป.<?= $class_id ?>)</button>
            <p class="text-muted mt-3 mb-0" style="font-size:.92rem">ไฟล์มีรายชื่อนักเรียน + ช่อง ข้อ1–ข้อ20 ให้กรอกคะแนนรายข้อรายคน</p>
        </div>
    </div>

    <!-- ขั้นที่ 3–4: กรอก + อัปโหลด -->
    <div class="col-lg-6">
        <div class="ht-card" style="height:100%">
            <span class="ht-badge t-green mb-3">ขั้นที่ 3 – 4</span>
            <h3 class="mt-2">กรอกคะแนน &amp; อัปโหลดทั้งโรงเรียน</h3>

            <ol class="mt-3" style="padding-left:20px; line-height:2">
                <li>เปิดไฟล์ Excel ที่ดาวน์โหลด</li>
                <li>กรอกช่อง <strong>ข้อ1–ข้อ20</strong> : <strong>1</strong> = อ่านถูก, <strong>0</strong> = อ่านผิด</li>
                <li>บันทึกไฟล์ (.xlsx) แล้วอัปโหลดกลับด้านล่าง</li>
            </ol>

            <form method="post" action="paper_import.php" enctype="multipart/form-data" class="mt-3"
                  onsubmit="return this.file.files.length>0">
                <div class="ht-field">
                    <label class="ht-label">ไฟล์ Excel คะแนนสอบ (.xlsx)</label>
                    <input class="ht-input" type="file" name="file" accept=".xlsx,.xls" required>
                </div>
                <button class="ht-btn ht-btn--lg ht-btn--green mt-4" type="submit">⬆️ อัปโหลดคะแนนเข้าระบบ</button>
            </form>
            <p class="text-muted mt-3 mb-0" style="font-size:.92rem">ระบบจะบันทึกลงผลรายข้อ (evaluations) และสรุปคะแนน (studenthit) ให้อัตโนมัติ</p>
        </div>
    </div>
</div>

<script>
function downloadTpl() {
    var cls = <?= $class_id ?>, hit = document.getElementById('hitSel').value;
    window.location = 'paper_export.php?class_id=' + cls + '&hittest=' + hit;
}
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
