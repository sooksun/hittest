<?php
/**
 * scripts/migrate_school_users.php — สร้างบัญชี "ระดับโรงเรียน" (role=school) จากตาราง schools → users
 *   username = sc_smis · password = sc_smis (hash; คงรูปแบบ SMIS=SMIS เดิม) · sc_id = schools.sc_id
 *   ข้าม: โรงเรียนที่เป็นผู้ดูแล (SMIS อยู่ใน admin_smis_list — กันทับสิทธิ์) และ username ที่มีอยู่แล้ว
 *
 *   ต้องระบุขอบเขต:
 *     php scripts/migrate_school_users.php --area=5703            # dry-run (เขตเดียว = 4 หลักแรกของ SMIS)
 *     php scripts/migrate_school_users.php --area=5703 --apply     # ทำจริง
 *     php scripts/migrate_school_users.php --active --apply        # เฉพาะโรงเรียนที่มีนักเรียน
 *     php scripts/migrate_school_users.php --all --apply           # ทุกโรงเรียน (เยอะมาก — ระวัง)
 *
 * Idempotent: INSERT IGNORE ตาม username (รันซ้ำไม่สร้างซ้ำ/ไม่ทับรหัสที่แก้แล้ว)
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}
require_once dirname(__DIR__) . '/includes/functions.php';

$apply  = in_array('--apply', $argv, true);
$all    = in_array('--all', $argv, true);
$active = in_array('--active', $argv, true);
$area   = '';
foreach ($argv as $a) {
    if (strncmp($a, '--area=', 7) === 0) {
        $area = preg_replace('/\D/', '', substr($a, 7));
    }
}
if (!$all && !$active && $area === '') {
    fwrite(STDERR, "ต้องระบุขอบเขต: --area=XXXX | --active | --all\n");
    exit(1);
}

$pdo  = db();
$cond = [];
$args = [];
if ($area !== '') {
    $cond[] = 'sc_smis LIKE ?';
    $args[] = $area . '%';
}
if ($active) {
    $cond[] = 'sc_id IN (SELECT DISTINCT sc_id FROM students)';
}
$sql = 'SELECT sc_id, sc_smis, sc_name FROM schools'
     . ($cond ? ' WHERE ' . implode(' AND ', $cond) : '')
     . ' ORDER BY sc_smis';
$st = $pdo->prepare($sql);
$st->execute($args);
$rows = $st->fetchAll();

$scope = $area !== '' ? "area={$area}" : ($active ? 'active' : 'ALL');
fwrite(STDOUT, '== migrate school users (schools → users role=school) ' . ($apply ? '(APPLY)' : '(DRY-RUN)') . " scope={$scope} ==\n");
fwrite(STDOUT, 'schools ในขอบเขต: ' . count($rows) . "\n");

$admins = array_flip(admin_smis_list());
$exists = $pdo->prepare('SELECT 1 FROM users WHERE username = ? LIMIT 1');
$ins    = $pdo->prepare(
    'INSERT IGNORE INTO users (username, password_hash, name, role, area_code, sc_id, is_active, created_at, updated_at)
     VALUES (?,?,?,?,NULL,?,1,NOW(),NOW())'
);

$inserted = 0;
$skipAdmin = 0;
$skipExist = 0;
$skipBad = 0;

foreach ($rows as $r) {
    $smis = trim((string)$r['sc_smis']);
    if ($smis === '' || (string)$r['sc_id'] === '') {
        $skipBad++;
        continue;
    }
    if (isset($admins[$smis])) {                 // โรงเรียนที่เป็นผู้ดูแล — login ผ่าน SMIS อยู่แล้ว ไม่สร้างเป็น role=school
        $skipAdmin++;
        continue;
    }
    $exists->execute([$smis]);
    if ($exists->fetchColumn()) {
        $skipExist++;
        continue;
    }
    if ($apply) {
        $ins->execute([$smis, password_hash($smis, PASSWORD_DEFAULT), (string)$r['sc_name'], 'school', (string)$r['sc_id']]);
        $inserted += $ins->rowCount();
    } else {
        $inserted++;
    }
}

fwrite(STDOUT, "\n-- สรุป --\n");
fwrite(STDOUT, ($apply ? 'สร้าง' : 'จะสร้าง') . ": {$inserted}\n");
fwrite(STDOUT, "ข้าม (เป็นผู้ดูแล): {$skipAdmin}\n");
fwrite(STDOUT, "ข้าม (มี user อยู่แล้ว): {$skipExist}\n");
fwrite(STDOUT, "ข้าม (ข้อมูลไม่ครบ): {$skipBad}\n");
fwrite(STDOUT, "หมายเหตุ: password เริ่มต้น = รหัส SMIS (ควรแนะนำให้โรงเรียนเปลี่ยนรหัส)\n");
if (!$apply) {
    fwrite(STDOUT, "\n(ยังไม่ได้ทำจริง — ใส่ --apply เพื่อบันทึก)\n");
}
