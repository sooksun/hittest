<?php
/**
 * admin_config.php — หน้าตั้งค่า/ดูค่าระบบ (ผู้ดูแลระบบ)
 *   • แก้ได้: รายชื่อ SMIS ผู้ดูแล (เก็บใน DB app_settings → ไม่ต้องแก้ config.php/SSH)
 *   • อ่านอย่างเดียว: ค่าคงที่หลัก (config.php), สถานะ DB, ระบบสำรองข้อมูล, สภาพแวดล้อม PHP
 *
 *   POST action=save_admins → บันทึกรายชื่อผู้ดูแลเพิ่มเติม → JSON
 *   GET                     → หน้าตั้งค่า
 */
require __DIR__ . '/includes/admin_auth.php';
require __DIR__ . '/includes/db_backup.php';   // ใช้ dbk_* สำหรับสถานะระบบสำรองข้อมูล

$pdo = db();

/* ---------- รวบรวมข้อมูลแสดงผล ---------- */
$bootAdmins = defined('ADMIN_SMIS') ? (array)ADMIN_SMIS : [];
$dbAdmins   = [];
$j = app_setting('admin_smis');
if (is_string($j) && $j !== '' && is_array($d = json_decode($j, true))) {
    $dbAdmins = array_values(array_filter(array_diff($d, $bootAdmins), 'strlen'));
}

// ค่าคงที่หลัก (อ่านอย่างเดียว — แก้ที่ config/config.php)
$consts = [
    'ปีการศึกษา (พ.ศ.)'        => ACADEMIC_YEAR,
    'จำนวนคำต่อชุด'            => WORDS_PER_SET,
    'เวลาสอบ (นาที)'          => EXAM_MINUTES,
    'เกณฑ์ผ่าน (คะแนน)'       => PASS_SCORE,
    'รอบสอบ'                  => implode(', ', array_map(fn($h) => "Hit-$h", HITTESTS)),
    'เก็บ audit_logs (วัน)'   => AUDIT_RETENTION_DAYS,
    'TTS เรียกสูงสุด/ผู้ใช้'   => TTS_RATE_MAX . ' ครั้ง / ' . TTS_RATE_WINDOW_SEC . ' วินาที',
];

// สถานะฐานข้อมูล
$dbStat = ['tables' => '-', 'size' => '-', 'eval' => '-'];
try {
    $dbStat['tables'] = (int)$pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()')->fetchColumn();
    $bytes = (int)$pdo->query('SELECT IFNULL(SUM(data_length + index_length),0) FROM information_schema.tables WHERE table_schema = DATABASE()')->fetchColumn();
    $dbStat['size'] = dbk_human($bytes);
    $er = $pdo->query("SELECT TABLE_ROWS FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'evaluations'")->fetchColumn();
    $dbStat['eval'] = ($er === false || $er === null) ? '—' : '≈ ' . number_format((int)$er);
} catch (Throwable $e) {
}

// สถานะระบบสำรองข้อมูล
$backups   = dbk_list();
$bkBytes   = array_sum(array_column($backups, 'bytes'));
$bkDir     = dbk_dir();
$useExec   = dbk_exec_enabled();
$mysqldump = dbk_bin('mysqldump');
$mysqlBin  = dbk_bin('mysql');

// สภาพแวดล้อม PHP
$phpInfo = [
    'PHP version'         => PHP_VERSION,
    'memory_limit'        => ini_get('memory_limit'),
    'max_execution_time'  => ini_get('max_execution_time') . ' s',
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size'       => ini_get('post_max_size'),
    'zlib (gzip)'         => extension_loaded('zlib') ? '✓' : '✗',
    'pdo_mysql'           => extension_loaded('pdo_mysql') ? '✓' : '✗',
    'intl (Normalizer)'   => extension_loaded('intl') ? '✓' : '✗',
];

function yn(bool $b): string { return $b ? '<span class="ht-badge ht-badge--done">✓ ใช้ได้</span>' : '<span class="ht-badge ht-badge--missing">✗ ไม่มี</span>'; }

$page_title = 'ตั้งค่าระบบ';
$active     = 'admin_config';
require __DIR__ . '/includes/header.php';
?>
<h2 class="mb-1">⚙️ ตั้งค่าระบบ</h2>
<p class="text-muted mb-4">ฐานข้อมูล <strong><?= htmlspecialchars(DB_NAME) ?></strong> · บัญชีนี้: <strong><?= htmlspecialchars(current_smis()) ?></strong></p>

<!-- ===== ผู้ดูแลระบบ (อ่านอย่างเดียว — จัดการที่ admin_users.php) ===== -->
<div class="ht-card mb-4" style="max-width:820px; border-left:6px solid var(--c-coral)">
    <h3 class="mb-2">👑 ผู้ดูแลระบบ (login ด้วย SMIS)</h3>
    <p class="text-muted mb-2" style="font-size:.9rem">โรงเรียนที่ login ด้วย SMIS เหล่านี้จะเข้าเครื่องมือผู้ดูแลได้ (ตั้งใน <code>config.php</code> เป็นบัญชีตั้งต้น) · บัญชีผู้ใช้แบบมี role จัดการที่หน้า <strong>จัดการผู้ใช้</strong></p>

    <div class="mb-2">
        <div class="text-muted mb-1" style="font-size:.85rem">จาก <code>config.php</code> (ตายตัว):</div>
        <?php if ($bootAdmins): foreach ($bootAdmins as $a): ?>
            <span class="ht-badge ht-badge--special">🔒 <?= htmlspecialchars($a) ?></span>
        <?php endforeach; else: ?>
            <span class="text-muted">— ไม่ได้ตั้งใน config —</span>
        <?php endif; ?>
    </div>
    <div class="mb-3">
        <div class="text-muted mb-1" style="font-size:.85rem">เพิ่มในฐานข้อมูล:</div>
        <?php if ($dbAdmins): foreach ($dbAdmins as $a): ?>
            <span class="ht-badge ht-badge--done"><?= htmlspecialchars($a) ?></span>
        <?php endforeach; else: ?>
            <span class="text-muted">— ยังไม่มี —</span>
        <?php endif; ?>
    </div>
    <a class="ht-btn ht-btn--coral ht-btn--sm" href="admin_users.php">👤 จัดการบัญชีผู้ใช้ (role) →</a>
</div>

<!-- ===== อ่านอย่างเดียว: ค่าระบบ ===== -->
<div class="ht-card mb-4" style="max-width:820px; border-left:6px solid var(--c-blue)">
    <h3 class="mb-2">🧩 ค่าคงที่ของระบบ <span class="text-muted" style="font-size:.85rem;font-weight:500">(อ่านอย่างเดียว — แก้ที่ <code>config/config.php</code>)</span></h3>
    <div class="ht-table-wrap">
        <table class="ht-table">
            <tbody>
            <?php foreach ($consts as $k => $v): ?>
                <tr><td><?= htmlspecialchars($k) ?></td><td class="num text-end"><strong><?= htmlspecialchars((string)$v) ?></strong></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ===== สถานะฐานข้อมูล ===== -->
<div class="ht-card mb-4" style="max-width:820px; border-left:6px solid var(--c-green)">
    <h3 class="mb-2">🗄️ ฐานข้อมูล</h3>
    <div class="ht-table-wrap">
        <table class="ht-table">
            <tbody>
                <tr><td>Host</td><td class="text-end"><?= htmlspecialchars(DB_HOST) ?></td></tr>
                <tr><td>Database</td><td class="text-end"><?= htmlspecialchars(DB_NAME) ?></td></tr>
                <tr><td>User</td><td class="text-end"><?= htmlspecialchars(DB_USER) ?> <span class="text-muted">· รหัสผ่าน <?= DB_PASS === '' ? '(ว่าง)' : '••••••••' ?></span></td></tr>
                <tr><td>Charset</td><td class="text-end"><?= htmlspecialchars(DB_CHARSET) ?></td></tr>
                <tr><td>จำนวนตาราง</td><td class="num text-end"><?= htmlspecialchars((string)$dbStat['tables']) ?></td></tr>
                <tr><td>ขนาดฐานข้อมูล</td><td class="num text-end"><?= htmlspecialchars((string)$dbStat['size']) ?></td></tr>
                <tr><td>แถวในตาราง evaluations</td><td class="num text-end"><?= htmlspecialchars((string)$dbStat['eval']) ?></td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- ===== ระบบสำรองข้อมูล ===== -->
<div class="ht-card mb-4" style="max-width:820px; border-left:6px solid var(--c-yellow)">
    <h3 class="mb-2">💾 ระบบสำรองข้อมูล</h3>
    <div class="ht-table-wrap">
        <table class="ht-table">
            <tbody>
                <tr><td>วิธีสำรอง</td><td class="text-end"><?= $useExec && $mysqldump ? '<span class="ht-badge ht-badge--done">mysqldump (เร็ว)</span>' : '<span class="ht-badge ht-badge--pending">PHP ล้วน (exec ปิด)</span>' ?></td></tr>
                <tr><td>exec/proc_open</td><td class="text-end"><?= yn($useExec) ?></td></tr>
                <tr><td>mysqldump</td><td class="text-end" style="word-break:break-all"><?= $mysqldump ? htmlspecialchars($mysqldump) : yn(false) ?></td></tr>
                <tr><td>mysql</td><td class="text-end" style="word-break:break-all"><?= $mysqlBin ? htmlspecialchars($mysqlBin) : yn(false) ?></td></tr>
                <tr><td>โฟลเดอร์ backup</td><td class="text-end" style="word-break:break-all"><?= htmlspecialchars($bkDir) ?> <?= is_writable($bkDir) ? '<span class="ht-badge ht-badge--done">เขียนได้</span>' : '<span class="ht-badge ht-badge--missing">เขียนไม่ได้</span>' ?></td></tr>
                <tr><td>ไฟล์สำรองที่มี</td><td class="num text-end"><?= count($backups) ?> ไฟล์ · <?= dbk_human((int)$bkBytes) ?></td></tr>
            </tbody>
        </table>
    </div>
    <a class="ht-btn ht-btn--ghost ht-btn--sm mt-2" href="admin_backup.php">→ ไปหน้าสำรอง/กู้คืน</a>
</div>

<!-- ===== สภาพแวดล้อม PHP ===== -->
<div class="ht-card" style="max-width:820px">
    <h3 class="mb-2">🐘 สภาพแวดล้อม PHP</h3>
    <div class="ht-table-wrap">
        <table class="ht-table">
            <tbody>
            <?php foreach ($phpInfo as $k => $v): ?>
                <tr><td><?= htmlspecialchars($k) ?></td><td class="text-end"><strong><?= htmlspecialchars((string)$v) ?></strong></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
