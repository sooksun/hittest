<?php
/**
 * scripts/migrate_area_admins.php — นำเข้าบัญชี "ผู้ดูแลเขต" (saoadmin) จาก legacy master_saonew → users
 *   master_saonew (246 บัญชีสำนักงานเขต: id=areacode 8 หลัก, code=ชื่อเขต, user, password[plaintext])
 *   → users: username = areacode (unique, เสถียร), name = ชื่อเขต, role = saoadmin,
 *            area_code = 4 หลักแรกของ areacode (= prefix SMIS ของโรงเรียนในเขต), password → hash
 *   นำเข้าเฉพาะ "เขตที่มีโรงเรียนจริงในระบบ" (มี schools.sc_smis ขึ้นต้นด้วย area_code) — เลี่ยงบัญชีลอย
 *
 *   php scripts/migrate_area_admins.php            # dry-run: รายงานอย่างเดียว
 *   php scripts/migrate_area_admins.php --apply     # ทำจริง
 *
 * Idempotent: INSERT IGNORE ตาม username (areacode) — รันซ้ำไม่สร้างซ้ำ/ไม่ทับรหัสที่แก้แล้ว
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}
require_once dirname(__DIR__) . '/includes/functions.php';

$apply = in_array('--apply', $argv, true);
$pdo   = db();

fwrite(STDOUT, '== migrate area admins (master_saonew → users) ' . ($apply ? '(APPLY)' : '(DRY-RUN)') . " ==\n");

$rows = $pdo->query('SELECT id, code, code_name, user, password FROM master_saonew')->fetchAll();
fwrite(STDOUT, 'master_saonew: ' . count($rows) . " rows\n");

$hasSchool = $pdo->prepare('SELECT 1 FROM schools WHERE sc_smis LIKE ? LIMIT 1');
$exists    = $pdo->prepare('SELECT 1 FROM users WHERE username = ? LIMIT 1');
$ins       = $pdo->prepare(
    'INSERT IGNORE INTO users (username, password_hash, name, role, area_code, sc_id, is_active, created_at, updated_at)
     VALUES (?,?,?,?,?,NULL,1,NOW(),NOW())'
);

$inserted = 0;
$skipExist = 0;
$skipNoSchool = 0;
$skipBad = 0;
$noPass = 0;

foreach ($rows as $r) {
    $areacode = trim((string)$r['id']);
    $area4    = substr(preg_replace('/\D/', '', $areacode), 0, 4);
    if (strlen($area4) !== 4 || $areacode === '') {
        $skipBad++;
        continue;
    }
    // เฉพาะเขตที่มีโรงเรียนจริงในระบบ
    $hasSchool->execute([$area4 . '%']);
    if (!$hasSchool->fetchColumn()) {
        $skipNoSchool++;
        continue;
    }
    $exists->execute([$areacode]);
    if ($exists->fetchColumn()) {
        $skipExist++;
        continue;
    }
    $name = trim((string)($r['code'] ?: $r['code_name'] ?: ('เขต ' . $area4)));
    $pass = (string)$r['password'];
    if ($pass === '') {
        $noPass++;                       // ไม่มีรหัสเดิม → ตั้ง hash จากค่าสุ่ม (ต้องรีเซตรหัสก่อนใช้)
        $pass = bin2hex(random_bytes(8));
    }
    if ($apply) {
        $ins->execute([$areacode, password_hash($pass, PASSWORD_DEFAULT), $name, 'saoadmin', $area4]);
        $inserted += $ins->rowCount();
    } else {
        $inserted++;
        fwrite(STDOUT, "  + {$areacode}  area={$area4}  {$name}\n");
    }
}

fwrite(STDOUT, "\n-- สรุป --\n");
fwrite(STDOUT, ($apply ? 'นำเข้า' : 'จะนำเข้า') . ": {$inserted}\n");
fwrite(STDOUT, "ข้าม (มีอยู่แล้ว): {$skipExist}\n");
fwrite(STDOUT, "ข้าม (ไม่มีโรงเรียนในเขต): {$skipNoSchool}\n");
fwrite(STDOUT, "ข้าม (areacode ไม่ถูกต้อง): {$skipBad}\n");
fwrite(STDOUT, "บัญชีที่ไม่มีรหัสเดิม (ต้องรีเซตก่อนใช้): {$noPass}\n");
if (!$apply) {
    fwrite(STDOUT, "\n(ยังไม่ได้ทำจริง — ใส่ --apply เพื่อบันทึก)\n");
}
