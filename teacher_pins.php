<?php
/**
 * teacher_pins.php — Phase 3: ครู/ผู้ดูแล พิมพ์ "การ์ดเข้าระบบนักเรียน" ทั้งชั้น
 * นักเรียน login ด้วย "รหัสนักเรียน (stuid)" เป็นทั้ง username/password (ดู student_login.php)
 * การ์ดแต่ละใบ = ชื่อ + รหัสนักเรียน + QR (สแกนแล้ว prefill หน้า login)
 *
 * RBAC: auth.php (school admin) — แสดงเฉพาะนักเรียนในโรงเรียนของ session (scope ด้วย sc_id)
 * หมายเหตุ: ระบบ PIN (student_pin/student_pin_verify) ยังอยู่เป็น "ทางเลือก" แต่หน้านี้ใช้ stuid
 */
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/dashboard.php';     // dash_valid_class / dash_class_students / dash_class_name

$scid    = current_sc_id();
$classid = dash_valid_class((int)($_GET['class_id'] ?? 1)) ?: 1;
$className = dash_class_name($classid) ?? ('ป.' . $classid);
$students  = dash_class_students($scid, $classid);

$page_title = 'การ์ดเข้าระบบนักเรียน';
$active = 'pins';
require __DIR__ . '/includes/header.php';
?>
<style>.ht-container{max-width:1200px}
.pin-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:14px}
.pin-card{border:1.5px dashed #c9cede;border-radius:14px;padding:14px;text-align:center;background:#fff}
.pin-card h4{font-size:1rem;margin:0 0 2px}
.pin-card .pin-no{font-size:1.5rem;font-weight:800;letter-spacing:.12em;color:var(--c-blue-ink);margin:6px 0}
.pin-card .qr{display:flex;justify-content:center;margin:8px 0}
.pin-card small{color:var(--ink-soft)}
@media print {
    .no-print, .ht-nav, nav, form, .ht-btn { display:none !important; }
    body { background:#fff; }
    .pin-grid{grid-template-columns:repeat(3,1fr)}
    .pin-card{break-inside:avoid}
}
</style>

<div class="ht-row mb-3 no-print" style="gap:12px;align-items:center;flex-wrap:wrap">
    <h2 class="mb-0">🪪 การ์ดเข้าระบบนักเรียน</h2>
    <span class="ht-badge t-blue">🏫 <?= htmlspecialchars($_SESSION['sc_name']) ?></span>
    <form method="get" class="ht-row" style="gap:8px">
        <label class="ht-label mb-0">ชั้น</label>
        <select name="class_id" class="ht-select" style="width:auto" onchange="this.form.submit()">
            <?php for ($c = 1; $c <= 6; $c++): ?><option value="<?= $c ?>" <?= $c === $classid ? 'selected' : '' ?>>ป.<?= $c ?></option><?php endfor; ?>
        </select>
    </form>
    <button class="ht-btn ht-btn--ghost" type="button" onclick="window.print()" style="margin-left:auto">🖨️ พิมพ์การ์ดทั้งชั้น</button>
</div>

<div class="ht-card mb-4 no-print" style="border-left:6px solid var(--c-blue)">
    <strong>ป.<?= $classid ?></strong> · นักเรียน <?= count($students) ?> คน
    <div class="text-muted" style="font-size:.9rem">นักเรียนเข้าระบบที่ <code>student_login.php</code> โดยกรอก <strong>รหัสนักเรียน</strong> ช่องเดียว — หรือ<strong>สแกน QR บนการ์ดแล้วกดปุ่มเข้าระบบได้เลย</strong> (ไม่ต้องพิมพ์)</div>
</div>

<?php if (!$students): ?>
    <div class="ht-card">ยังไม่มีนักเรียนในชั้นนี้</div>
<?php else: ?>
<div class="pin-grid">
    <?php foreach ($students as $s): $sid = (string)$s['stuid']; ?>
        <div class="pin-card">
            <h4><?= htmlspecialchars($s['stuname']) ?></h4>
            <small><?= htmlspecialchars($className) ?> · ห้อง <?= (int)$s['rooms'] ?></small>
            <div class="pin-no"><?= htmlspecialchars($sid) ?></div>
            <div class="qr" data-stuid="<?= htmlspecialchars($sid, ENT_QUOTES) ?>"></div>
            <small>สแกน QR แล้วกด "เข้าสู่ระบบ"</small>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script>
(function () {
    'use strict';
    if (typeof QRCode === 'undefined') { return; }
    // QR = deep link ไปหน้า login พร้อม prefill รหัสนักเรียน
    var base = location.origin + location.pathname.replace(/teacher_pins\.php$/, 'student_login.php');
    document.querySelectorAll('.qr').forEach(function (el) {
        var url = base + '?u=' + encodeURIComponent(el.dataset.stuid);
        new QRCode(el, { text: url, width: 96, height: 96, correctLevel: QRCode.CorrectLevel.M });
    });
})();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
