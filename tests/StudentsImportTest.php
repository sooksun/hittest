<?php
/**
 * tests/StudentsImportTest.php — นำเข้ารายชื่อนักเรียน (includes/students_import_lib.php)
 *   - เว้นช่องรหัสว่าง → ระบบ gen รหัส "9"+เลขรัน 9 หลัก (unique, ต่อจากเลขสูงสุด)
 *   - กรอกรหัสเอง → all-or-nothing: ซ้ำในไฟล์ / ของโรงเรียนอื่น / ข้อมูลไม่ครบ → ยกเลิกทั้งไฟล์
 *   - รหัสเดิมของโรงเรียนตัวเอง = อัปเดต (ไม่แตะคะแนน) · นำเข้าซ้ำ idempotent
 *   - admin รับย้ายข้ามโรงเรียนได้ (ย้าย sc_id เฉพาะแถวปีปัจจุบัน) — non-admin ถูกบล็อก
 *   - เจ้าของ stuid ตัดสินจากแถวปีปัจจุบันก่อน ไม่งั้นปีล่าสุด
 */
require_once dirname(__DIR__) . '/includes/students_import_lib.php';

const SIT_Y   = 2569;   // ปีปัจจุบันที่ใช้ทดสอบ
const SIT_OLD = 2568;

/** แถวข้อมูลตามคอลัมน์ template: A=รหัส B=ชื่อ C=ชั้น D=ห้อง E=ประเภท F-H=ชุดคำ */
function sit_row(string $stuid, string $name = 'เด็กชายทดสอบ ระบบ', $class = 1, $rooms = 1, $status = 1, $s1 = 1, $s2 = 1, $s3 = 1): array
{
    return [$stuid, $name, $class, $rooms, $status, $s1, $s2, $s3];
}

/** seed นักเรียน 1 แถว (stuid, years) */
function sit_seed(PDO $pdo, string $stuid, string $sc, int $years, array $o = []): void
{
    $pdo->prepare(
        'INSERT INTO students (stuid, stuname, sc_id, class_id, years, rooms, stustatus, hit1, hit1tested)
         VALUES (?,?,?,?,?,?,?,?,?)'
    )->execute([
        $stuid, $o['stuname'] ?? ('เดิม-' . $stuid), $sc, $o['class_id'] ?? 1, $years, 1,
        $o['stustatus'] ?? 1, $o['hit1'] ?? 0, $o['hit1tested'] ?? 0,
    ]);
}

run_test('SIT1 — นำเข้าไฟล์ปกติ: เพิ่มใหม่ทั้งหมด + clamp ค่าเกินช่วง', function () {
    $pdo = create_test_db();
    $r = students_import_run($pdo, 'SC1', SIT_Y, [
        sit_row('s1', 'เด็กหญิงหนึ่ง ทดสอบ', 2, 3, 2, 4, 5, 1),
        ['', null, null, null, null, null, null, null],            // แถวว่าง — ข้ามเฉย ๆ
        sit_row('s2', 'เด็กชายสอง ทดสอบ', 6, 0, 99, 9, 0, 2),      // rooms/status/set เกินช่วง → default
    ], false);

    ok($r['ok'] === true, 'นำเข้าสำเร็จ');
    ok($r['rows_seen'] === 2 && $r['imported'] === 2 && $r['updated'] === 0, 'นับ 2 แถว เพิ่มใหม่ 2');
    $s1 = $pdo->query("SELECT * FROM students WHERE stuid='s1' AND years=" . SIT_Y)->fetch();
    ok($s1 && $s1['sc_id'] === 'SC1' && (int)$s1['class_id'] === 2 && (int)$s1['rooms'] === 3, 's1 ลงโรงเรียน/ชั้น/ห้องถูก');
    ok((int)$s1['sethit1'] === 4 && (int)$s1['sethit2'] === 5, 's1 ชุดคำตามไฟล์');
    $s2 = $pdo->query("SELECT * FROM students WHERE stuid='s2' AND years=" . SIT_Y)->fetch();
    ok((int)$s2['rooms'] === 1 && (int)$s2['stustatus'] === 1 && (int)$s2['sethit1'] === 1, 's2 ค่าเกินช่วงถูก clamp เป็น default');
});

run_test('SIT2 — รหัสซ้ำในไฟล์เดียวกัน: ยกเลิกทั้งไฟล์ (แถวดีก็ไม่เข้า) + บอกแถวที่ซ้ำ', function () {
    $pdo = create_test_db();
    $r = students_import_run($pdo, 'SC1', SIT_Y, [
        sit_row('dup1'),               // แถว 4
        sit_row('okrow'),              // แถว 5 — ถูกต้อง แต่ต้องไม่ถูกนำเข้า
        sit_row('dup1', 'ชื่ออื่น'),   // แถว 6 — ซ้ำกับแถว 4
    ], false);

    ok($r['ok'] === false, 'ถูกยกเลิก');
    ok(count($r['errors']) === 1 && str_contains($r['errors'][0], 'dup1')
        && str_contains($r['errors'][0], 'แถวที่ 6') && str_contains($r['errors'][0], 'แถวที่ 4'), 'error ระบุรหัสและเลขแถวทั้งคู่');
    ok((int)$pdo->query('SELECT COUNT(*) FROM students')->fetchColumn() === 0, 'ไม่มีแถวใดถูกบันทึกเลย (รวมแถวที่ถูกต้อง)');
});

run_test('SIT3 — รหัสเป็นของโรงเรียนอื่น (non-admin): ยกเลิกทั้งไฟล์ ข้อมูลเดิมไม่ถูกแตะ', function () {
    $pdo = create_test_db();
    sit_seed($pdo, 'x1', 'SC2', SIT_Y, ['stuname' => 'ของโรงเรียนสอง', 'hit1' => 12]);
    $r = students_import_run($pdo, 'SC1', SIT_Y, [
        sit_row('new1'),
        sit_row('x1', 'พยายามแย่งมา'),
    ], false);

    ok($r['ok'] === false, 'ถูกยกเลิก');
    ok(count($r['errors']) === 1 && str_contains($r['errors'][0], 'x1') && str_contains($r['errors'][0], 'โรงเรียนอื่น'), 'error ระบุรหัสที่ชนโรงเรียนอื่น');
    ok((int)$pdo->query("SELECT COUNT(*) FROM students WHERE stuid='new1'")->fetchColumn() === 0, 'แถวที่ถูกต้องก็ไม่ถูกนำเข้า (all-or-nothing)');
    $x = $pdo->query("SELECT stuname, sc_id, hit1 FROM students WHERE stuid='x1'")->fetch();
    ok($x['stuname'] === 'ของโรงเรียนสอง' && $x['sc_id'] === 'SC2' && (int)$x['hit1'] === 12, 'ข้อมูลของโรงเรียนอื่นไม่ถูกแตะ');
});

run_test('SIT4 — รหัสเดิมของโรงเรียนตัวเอง: อัปเดตข้อมูล ไม่แตะคะแนน', function () {
    $pdo = create_test_db();
    sit_seed($pdo, 'u1', 'SC1', SIT_Y, ['stuname' => 'ชื่อเก่า', 'class_id' => 1, 'hit1' => 15, 'hit1tested' => 1]);
    $r = students_import_run($pdo, 'SC1', SIT_Y, [
        sit_row('u1', 'ชื่อใหม่ แก้แล้ว', 2),
        sit_row('n1'),
    ], false);

    ok($r['ok'] === true && $r['imported'] === 1 && $r['updated'] === 1, 'เพิ่ม 1 อัปเดต 1');
    $u = $pdo->query("SELECT stuname, class_id, hit1, hit1tested, sc_id FROM students WHERE stuid='u1' AND years=" . SIT_Y)->fetch();
    ok($u['stuname'] === 'ชื่อใหม่ แก้แล้ว' && (int)$u['class_id'] === 2, 'ชื่อ/ชั้นถูกอัปเดต');
    ok((int)$u['hit1'] === 15 && (int)$u['hit1tested'] === 1, 'คะแนน/ธงสอบไม่ถูกแตะ');
    ok($u['sc_id'] === 'SC1', 'sc_id คงเดิม');
});

run_test('SIT5 — admin รับย้ายข้ามโรงเรียน: sc_id ย้ายเฉพาะปีปัจจุบัน คะแนนติดตัว ปีเก่าคงเดิม', function () {
    $pdo = create_test_db();
    sit_seed($pdo, 'm1', 'SC2', SIT_OLD, ['stuname' => 'ประวัติปีเก่า']);
    sit_seed($pdo, 'm1', 'SC2', SIT_Y, ['stuname' => 'ก่อนย้าย', 'hit1' => 7, 'hit1tested' => 1]);

    $r = students_import_run($pdo, 'SC1', SIT_Y, [sit_row('m1', 'หลังย้ายมา', 3)], true);

    ok($r['ok'] === true, 'admin นำเข้าสำเร็จ');
    ok($r['moved'] === ['m1' => 'SC2'], 'รายงานรับย้าย m1 จาก SC2');
    $cur = $pdo->query("SELECT sc_id, stuname, class_id, hit1, hit1tested FROM students WHERE stuid='m1' AND years=" . SIT_Y)->fetch();
    ok($cur['sc_id'] === 'SC1' && $cur['stuname'] === 'หลังย้ายมา' && (int)$cur['class_id'] === 3, 'แถวปีปัจจุบันย้ายมา SC1');
    ok((int)$cur['hit1'] === 7 && (int)$cur['hit1tested'] === 1, 'คะแนนปีนี้ติดตัวมา (ไม่รีเซ็ต)');
    $old = $pdo->query("SELECT sc_id FROM students WHERE stuid='m1' AND years=" . SIT_OLD)->fetch();
    ok($old['sc_id'] === 'SC2', 'แถวปีเก่ายังเป็นของ SC2 (ประวัติ)');
});

run_test('SIT6 — เจ้าของจากปีเก่า (ไม่มีแถวปีปัจจุบัน): non-admin ถูกบล็อก / admin นำเข้าได้', function () {
    $pdo = create_test_db();
    sit_seed($pdo, 'h1', 'SC2', SIT_OLD);

    $r1 = students_import_run($pdo, 'SC1', SIT_Y, [sit_row('h1')], false);
    ok($r1['ok'] === false && str_contains($r1['errors'][0] ?? '', 'h1'), 'non-admin: ประวัติปีเก่าของโรงเรียนอื่นก็บล็อก');

    $r2 = students_import_run($pdo, 'SC1', SIT_Y, [sit_row('h1')], true);
    ok($r2['ok'] === true && $r2['moved'] === ['h1' => 'SC2'] && $r2['imported'] === 1, 'admin: สร้างแถวปีปัจจุบันที่ SC1 + รายงานรับย้าย');
    ok($pdo->query("SELECT sc_id FROM students WHERE stuid='h1' AND years=" . SIT_OLD)->fetchColumn() === 'SC2', 'แถวปีเก่าไม่ถูกแตะ');
});

run_test('SIT7 — เจ้าของตัดสินจากแถวปีปัจจุบันก่อนปีเก่า', function () {
    $pdo = create_test_db();
    sit_seed($pdo, 'p1', 'SC1', SIT_OLD);          // ปีเก่าเคยเป็นของ SC1
    sit_seed($pdo, 'p1', 'SC2', SIT_Y);            // ปีปัจจุบันถูกย้ายไป SC2 แล้ว

    $r = students_import_run($pdo, 'SC1', SIT_Y, [sit_row('p1')], false);
    ok($r['ok'] === false, 'SC1 นำเข้าไม่ได้แล้ว — เจ้าของปัจจุบันคือ SC2');

    $r2 = students_import_run($pdo, 'SC2', SIT_Y, [sit_row('p1')], false);
    ok($r2['ok'] === true && $r2['updated'] === 1, 'SC2 (เจ้าของปัจจุบัน) อัปเดตได้ปกติ');
});

run_test('SIT8 — ข้อมูลไม่ครบ (ไม่มีชื่อ / ชั้นผิด): ยกเลิกทั้งไฟล์', function () {
    $pdo = create_test_db();
    $r = students_import_run($pdo, 'SC1', SIT_Y, [
        sit_row('b1', ''),             // ไม่มีชื่อ
        sit_row('b2', 'ชื่อปกติ', 7),  // ชั้นเกิน 6
        sit_row('b3'),                 // ถูกต้อง
    ], false);
    ok($r['ok'] === false && count($r['errors']) === 2, 'รายงานครบทั้ง 2 ปัญหา');
    ok((int)$pdo->query('SELECT COUNT(*) FROM students')->fetchColumn() === 0, 'ไม่มีแถวใดถูกบันทึก');
});

run_test('SIT9 — นำเข้าไฟล์เดิมซ้ำ: idempotent ไม่เกิดแถวซ้ำ', function () {
    $pdo = create_test_db();
    $rows = [sit_row('i1'), sit_row('i2')];
    $r1 = students_import_run($pdo, 'SC1', SIT_Y, $rows, false);
    $r2 = students_import_run($pdo, 'SC1', SIT_Y, $rows, false);
    ok($r1['ok'] && $r1['imported'] === 2, 'รอบแรกเพิ่ม 2');
    ok($r2['ok'] && $r2['imported'] === 0 && $r2['updated'] === 2, 'รอบสองอัปเดต 2 (ไม่เพิ่มใหม่)');
    ok((int)$pdo->query('SELECT COUNT(*) FROM students')->fetchColumn() === 2, 'ยังมี 2 แถว');
});

run_test('SIT10 — ไฟล์ว่าง (ไม่มีข้อมูลสักแถว): แจ้ง error ไม่แตะ DB', function () {
    $pdo = create_test_db();
    $r = students_import_run($pdo, 'SC1', SIT_Y, [['', '', null, null, null, null, null, null]], false);
    ok($r['ok'] === false && str_contains($r['errors'][0] ?? '', 'ไม่พบข้อมูล'), 'แจ้งไม่พบข้อมูลในไฟล์');
});

run_test('SIT11 — เว้นช่องรหัสว่าง: ระบบ gen รหัส 9-series ให้ นับเป็นเพิ่มใหม่', function () {
    $pdo = create_test_db();
    $r = students_import_run($pdo, 'SC1', SIT_Y, [sit_row('', 'เด็กชายไม่มีรหัส ทดสอบ', 1)], false);
    ok($r['ok'] === true && $r['imported'] === 1 && $r['updated'] === 0, 'เพิ่มใหม่ 1');
    ok(count($r['generated']) === 1, 'มี generated 1 รายการ');
    $gid = $r['generated'][0]['stuid'];
    ok(preg_match('/^9[0-9]{9}$/', $gid) === 1, "รหัสเป็นรูปแบบ 9 + 9 หลัก ({$gid})");
    ok($gid === '9000000001', 'เริ่มที่ 9000000001 เมื่อยังไม่มีชุด 9');
    ok($r['generated'][0]['stuname'] === 'เด็กชายไม่มีรหัส ทดสอบ', 'generated ผูกชื่อถูกคน');
    $row = $pdo->query("SELECT sc_id, years, class_id FROM students WHERE stuid='{$gid}'")->fetch();
    ok($row && $row['sc_id'] === 'SC1' && (int)$row['years'] === SIT_Y && (int)$row['class_id'] === 1, 'แถวถูกบันทึกครบ');
});

run_test('SIT12 — gen ต่อจากเลขสูงสุดของชุด 9 ที่มีอยู่', function () {
    $pdo = create_test_db();
    sit_seed($pdo, '9000000005', 'SC1', SIT_Y);          // ชุด 9 สูงสุดปัจจุบัน = ...005
    sit_seed($pdo, '9000000003', 'SC2', SIT_OLD);        // ของโรงเรียนอื่นก็นับ (unique ทั้งระบบ)
    $r = students_import_run($pdo, 'SC1', SIT_Y, [sit_row('', 'คนใหม่ ต่อเลข')], false);
    ok($r['ok'] === true && $r['generated'][0]['stuid'] === '9000000006', 'ได้ 9000000006 (max+1)');
});

run_test('SIT13 — หลายแถวเว้นว่าง: ได้รหัสไม่ซ้ำ เรียงต่อกัน', function () {
    $pdo = create_test_db();
    $r = students_import_run($pdo, 'SC1', SIT_Y, [
        sit_row('', 'คนหนึ่ง ทดสอบ'),
        sit_row('', 'คนสอง ทดสอบ'),
        sit_row('', 'คนสาม ทดสอบ'),
    ], false);
    ok($r['ok'] === true && $r['imported'] === 3, 'เพิ่มใหม่ 3');
    $ids = array_column($r['generated'], 'stuid');
    ok(count(array_unique($ids)) === 3, 'รหัสทั้ง 3 ไม่ซ้ำกัน');
    ok($ids === ['9000000001', '9000000002', '9000000003'], 'เรียงต่อกัน 001-003');
    ok((int)$pdo->query('SELECT COUNT(*) FROM students')->fetchColumn() === 3, 'มี 3 แถวใน DB');
});

run_test('SIT14 — ผสม กรอกเอง + เว้นว่าง ในไฟล์เดียว', function () {
    $pdo = create_test_db();
    $r = students_import_run($pdo, 'SC1', SIT_Y, [
        sit_row('1100700000001', 'กรอกเลขบัตรเอง'),
        sit_row('', 'ให้ระบบกำหนด'),
    ], false);
    ok($r['ok'] === true && $r['imported'] === 2, 'เพิ่มใหม่ 2 (กรอกเอง + gen)');
    ok(count($r['generated']) === 1 && $r['generated'][0]['stuname'] === 'ให้ระบบกำหนด', 'generated เฉพาะแถวที่เว้นว่าง');
    ok($pdo->query("SELECT stuname FROM students WHERE stuid='1100700000001'")->fetchColumn() === 'กรอกเลขบัตรเอง', 'รหัสที่กรอกเองถูกใช้ตามนั้น');
});

run_test('SIT15 — แถวมีชื่อแต่ไม่มีรหัส = gen (ไม่ใช่ error) · แถวว่างเปล่า = ข้าม', function () {
    $pdo = create_test_db();
    $r = students_import_run($pdo, 'SC1', SIT_Y, [
        ['', '', null, null, null, null, null, null],   // ว่างเปล่า → ข้าม
        sit_row('', 'มีแต่ชื่อ ไม่มีรหัส'),             // ชื่อมี รหัสไม่มี → gen
    ], false);
    ok($r['ok'] === true && $r['rows_seen'] === 1 && $r['imported'] === 1, 'นับแถวมีชื่อ 1 (ข้ามแถวว่าง)');
    ok(count($r['generated']) === 1, 'gen ให้ 1');
});

run_test('SIT16 — gen ข้ามรหัส 9-series ที่ผู้ใช้กรอกเองในไฟล์เดียวกัน', function () {
    $pdo = create_test_db();
    $r = students_import_run($pdo, 'SC1', SIT_Y, [
        sit_row('9000000001', 'กรอก 9000000001 เอง'),   // ชน candidate ตัวแรกพอดี
        sit_row('', 'ให้ระบบกำหนด ต้องข้าม 001'),
    ], false);
    ok($r['ok'] === true && $r['imported'] === 2, 'เพิ่มใหม่ 2');
    ok($r['generated'][0]['stuid'] === '9000000002', 'ระบบข้าม 001 (ชนของที่กรอกเอง) ไปใช้ 002');
    ok((int)$pdo->query('SELECT COUNT(DISTINCT stuid) FROM students')->fetchColumn() === 2, 'รหัสไม่ชนกัน');
});

run_test('SIT17 — admin: เว้นว่างก็ gen ได้ (ไม่นับเป็นรับย้าย)', function () {
    $pdo = create_test_db();
    $r = students_import_run($pdo, 'SC1', SIT_Y, [sit_row('', 'นักเรียนใหม่ admin')], true);
    ok($r['ok'] === true && $r['imported'] === 1, 'เพิ่มใหม่ 1');
    ok(count($r['generated']) === 1 && $r['moved'] === [], 'gen 1, ไม่มีรับย้าย');
});
