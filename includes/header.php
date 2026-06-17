<?php
/** includes/header.php — ส่วนหัวร่วม (Bootstrap 5.3.8 + ธีม Playful) — ต้อง include auth.php มาก่อน */
$active     = $active     ?? '';
$page_title = $page_title ?? APP_NAME;
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($page_title) ?> — HIT-TEST</title>
    <link rel="icon" href="images/newlogo.png" type="image/png">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/theme.css?v=<?= @filemtime(__DIR__ . '/../assets/css/theme.css') ?: '1' ?>" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="ht-app">
<button class="ht-burger" id="htBurger" aria-label="เปิด/ปิดเมนู" type="button">☰</button>
<div class="ht-backdrop" id="htBackdrop"></div>
<aside class="ht-sidebar" id="htSidebar">
    <a class="ht-brand" href="menu.php" style="display:block;text-align:center;margin:2px 4px 14px">
        <img src="images/newlogo.png" alt="HIT-TEST" style="width:124px;height:124px;object-fit:contain;display:inline-block">
    </a>
    <a class="ht-side-link<?= $active === 'dashboard' ? ' active' : '' ?>" href="menu.php">🏠 Dashboard</a>
    <a class="ht-side-link<?= $active === 'exam' ? ' active' : '' ?>" href="students_list.php">📖 สอบอ่านไทย</a>
    <a class="ht-side-link<?= $active === 'paper' ? ' active' : '' ?>" href="paper.php">📝 สอบด้วยกระดาษ</a>
    <a class="ht-side-link<?= $active === 'practice' ? ' active' : '' ?>" href="practice.php">🗣️ ฝึกอ่าน</a>
    <a class="ht-side-link<?= $active === 'analytics' ? ' active' : '' ?>" href="dashboard_school.php">📊 ผลพัฒนาการ</a>
    <a class="ht-side-link<?= $active === 'stories' ? ' active' : '' ?>" href="student_stories.php">✍️ เรื่องที่นักเรียนแต่ง</a>
<?php if (is_admin() || is_saoadmin()): ?>
    <a class="ht-side-link<?= $active === 'area_results' ? ' active' : '' ?>" href="area_results.php">🏫 ติดตามผลสอบ (เขต)</a>
<?php endif; ?>

<?php if (!is_saoadmin()): ?>
    <div class="ht-side-label">ตั้งค่า</div>
    <a class="ht-side-link<?= $active === 'settings' ? ' active' : '' ?>" href="settings.php">🗑️ รีเซตผลการสอบ</a>
    <a class="ht-side-link<?= $active === 'examctl' ? ' active' : '' ?>" href="exam_control.php">🚦 จัดการสอบ</a>
    <a class="ht-side-link<?= $active === 'pins' ? ' active' : '' ?>" href="teacher_pins.php">🪪 การ์ด login นักเรียน</a>
    <?php if (is_admin() || promote_menu_enabled()): ?>
    <a class="ht-side-link<?= $active === 'promote' ? ' active' : '' ?>" href="promote.php">⬆️ เลื่อนชั้นทั้งโรงเรียน<?= !promote_menu_enabled() ? ' 🔒' : '' ?></a>
    <?php endif; ?>
    <a class="ht-side-link<?= $active === 'stu_import' ? ' active' : '' ?>" href="students_import.php">📥 นำเข้ารายชื่อนักเรียน</a>
<?php endif; ?>

<?php if (is_admin()): ?>
    <div class="ht-side-label">ผู้ดูแลระบบ</div>
    <a class="ht-side-link<?= $active === 'admin_users' ? ' active' : '' ?>" href="admin_users.php">👤 จัดการผู้ใช้</a>
    <a class="ht-side-link<?= $active === 'admin_trash' ? ' active' : '' ?>" href="admin_students_trash.php">🗑️ ถังขยะนักเรียน</a>
    <a class="ht-side-link<?= $active === 'admin_config' ? ' active' : '' ?>" href="admin_config.php">⚙️ ตั้งค่าระบบ</a>
    <a class="ht-side-link<?= $active === 'admin_media' ? ' active' : '' ?>" href="admin_media.php">🎨 สร้างสื่อ (เสียง/ภาพ)</a>
    <a class="ht-side-link<?= $active === 'admin_backup' ? ' active' : '' ?>" href="admin_backup.php">💾 สำรอง/กู้คืน DB</a>
    <a class="ht-side-link<?= $active === 'admin_promote' ? ' active' : '' ?>" href="admin_promote_all.php">⏫ เลื่อนชั้นทั้งระบบ</a>
<?php endif; ?>

    <div class="ht-side-spacer"></div>
    <div class="ht-side-school"><span class="ht-badge ht-badge--special">🏫 <?= htmlspecialchars($_SESSION['sc_name'] ?? '') ?></span></div>
<?php if (is_admin() || is_saoadmin()): ?>
    <a class="ht-side-link" href="school_switch.php">🔄 เปลี่ยนโรงเรียนที่ดู</a>
<?php endif; ?>
    <a class="ht-side-link" href="logout.php" style="color:var(--c-coral-ink)">🚪 ออกจากระบบ</a>
</aside>
<script>
(function () {
    var b = document.getElementById('htBurger'),
        s = document.getElementById('htSidebar'),
        d = document.getElementById('htBackdrop');
    if (!b || !s) { return; }
    function toggle(open) { s.classList.toggle('open', open); d.classList.toggle('open', open); }
    b.addEventListener('click', function () { toggle(!s.classList.contains('open')); });
    d.addEventListener('click', function () { toggle(false); });
    // ปิดเมนูเมื่อคลิกลิงก์ (มือถือ)
    s.querySelectorAll('.ht-side-link').forEach(function (a) { a.addEventListener('click', function () { toggle(false); }); });
})();
</script>
<main class="ht-container">
