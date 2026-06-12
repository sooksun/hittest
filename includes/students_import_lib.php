<?php
/**
 * includes/students_import_lib.php — ตรรกะนำเข้ารายชื่อนักเรียนจาก Excel (all-or-nothing)
 *
 * กติกา (ตรวจทั้งไฟล์ก่อน แล้วค่อยเขียนจริง — พบปัญหาแม้รายการเดียว = ยกเลิกทั้งไฟล์):
 *   1. ช่อง "รหัสนักเรียน" เว้นว่างได้ → ระบบกำหนดรหัสให้อัตโนมัติ เป็นรูปแบบเดิมของระบบ
 *      = "9" + เลขรัน 9 หลัก (เช่น 9000000123) ต่อจากเลขสูงสุดของชุด 9xxxxxxxxx ที่มีอยู่
 *      จึง unique ทั้งระบบเสมอ และไม่ชนกับเลขบัตรประชาชนจริง (ขึ้นต้น 0-8)
 *   2. ถ้ากรอกรหัสมาเอง (เช่นเลขบัตร ปชช.) → เคารพรหัสนั้น แต่ต้อง:
 *      - ไม่ซ้ำกันเองในไฟล์
 *      - ไม่ชนกับนักเรียนของ "โรงเรียนอื่น" (รหัสเดิมของโรงเรียนตัวเอง = อัปเดต ไม่แตะคะแนน)
 *        ยกเว้น admin (is_admin) "รับย้าย" ได้ — ย้าย sc_id เฉพาะแถวปีปัจจุบัน
 *
 * เจ้าของ stuid ตัดสินจากแถว "ปีปัจจุบัน" ก่อน ถ้าไม่มีจึงใช้แถวปีล่าสุด
 * การ gen รหัสถูกกันชนข้ามโปรเซสด้วย GET_LOCK (และ PK (stuid,years) เป็นด่านสุดท้าย)
 *
 * ใช้ร่วม: students_import.php (หน้าอัปโหลด), tests/StudentsImportTest.php
 * ฟังก์ชันรับ PDO + ปี + สิทธิ์เป็นพารามิเตอร์ — ไม่เรียก db()/session เอง (เทสต์ขับตรงได้)
 */

const STUID_GEN_PREFIX = '9';   // รหัสที่ระบบสร้าง = 9 + เลขรัน 9 หลัก (รวม 10 หลัก)
const STUID_GEN_BASE   = 9000000000;   // เลขฐาน: รหัสแรกที่ gen = 9000000001
const STUID_GEN_LOCK   = 'newhittest_stuid_gen';

/** แปลงค่าเป็น int ในช่วง [min,max] ไม่งั้นใช้ค่า default */
function clamp_int($v, int $min, int $max, int $default): int
{
    $n = (int)$v;
    return ($n < $min || $n > $max) ? $default : $n;
}

/**
 * ค่าเลขรันสูงสุดของชุดรหัสที่ระบบสร้าง (9xxxxxxxxx, ยาว 10 หลัก) ใน students ทั้งตาราง
 * (ข้ามทุกโรงเรียน/ทุกปี — stuid unique ทั้งระบบ) คืน int; STUID_GEN_BASE ถ้ายังไม่มี
 */
function students_max_generated_id(PDO $pdo): int
{
    $max = $pdo->query(
        "SELECT MAX(CAST(stuid AS UNSIGNED)) FROM students
         WHERE stuid LIKE '" . STUID_GEN_PREFIX . "%' AND CHAR_LENGTH(stuid) = 10"
    )->fetchColumn();
    $max = (int)$max;
    return $max > STUID_GEN_BASE ? $max : STUID_GEN_BASE;
}

/**
 * ตรวจสอบ + นำเข้าแถวข้อมูลจาก Excel (คอลัมน์ A=stuid … H=sethit3) แบบ all-or-nothing
 *
 * @param array $rows       แถวดิบจาก rangeToArray โดยข้อมูลเริ่มที่แถว Excel ที่ $firstRowNo
 * @param bool  $isAdmin    ผู้นำเข้าเป็นผู้ดูแลระบบ (ได้สิทธิ์รับย้ายข้ามโรงเรียน)
 * @return array{ok:bool, errors:string[], rows_seen:int, imported:int, updated:int,
 *               generated:array<int,array{stuid:string,stuname:string}>, moved:array<string,string>}
 *         generated = รายชื่อที่ระบบกำหนดรหัสให้ (เพื่อแจ้งโรงเรียนไว้ให้นักเรียน login)
 *         moved     = stuid => sc_id โรงเรียนเดิม (เฉพาะกรณี admin รับย้าย)
 */
function students_import_run(PDO $pdo, string $scid, int $year, array $rows, bool $isAdmin, int $firstRowNo = 4): array
{
    $errors   = [];
    $entries  = [];   // [{rowNo, stuid:?string (null=ให้ระบบ gen), data:[]}]
    $seenAt   = [];   // รหัสที่กรอกเอง => เลขแถว Excel แรกที่พบ (จับซ้ำในไฟล์)
    $rowsSeen = 0;

    foreach ($rows as $i => $row) {
        $rowNo   = $firstRowNo + $i;
        $stuid   = trim((string)($row[0] ?? ''));
        $stuname = trim((string)($row[1] ?? ''));

        if ($stuid === '' && $stuname === '') {
            continue;                                   // แถวว่างจริง — ข้ามเงียบ
        }
        $rowsSeen++;
        $class = (int)($row[2] ?? 0);

        if ($stuname === '' || mb_strlen($stuname) > 255) {
            $errors[] = "แถวที่ {$rowNo}: ไม่มีชื่อ-สกุล หรือยาวเกิน 255 ตัวอักษร";
            continue;
        }
        if ($class < 1 || $class > 6) {
            $errors[] = "แถวที่ {$rowNo} ({$stuname}): ชั้นไม่ถูกต้อง (ต้อง 1-6)";
            continue;
        }
        if ($stuid !== '') {                            // กรอกรหัสมาเอง — ตรวจซ้ำในไฟล์
            if (mb_strlen($stuid) > 50) {
                $errors[] = "แถวที่ {$rowNo}: รหัสนักเรียนยาวเกิน 50 ตัวอักษร";
                continue;
            }
            if (isset($seenAt[$stuid])) {
                $errors[] = "แถวที่ {$rowNo}: รหัส {$stuid} ซ้ำกับแถวที่ {$seenAt[$stuid]} ในไฟล์เดียวกัน";
                continue;
            }
            $seenAt[$stuid] = $rowNo;
        }

        $entries[] = [
            'rowNo' => $rowNo,
            'stuid' => $stuid !== '' ? $stuid : null,    // null = ให้ระบบกำหนดรหัสให้
            'data'  => [
                'stuname'  => $stuname,
                'class_id' => $class,
                'rooms'    => clamp_int($row[3] ?? 1, 1, 9999, 1),
                'status'   => clamp_int($row[4] ?? 1, 1, 4, 1),   // STU_STATUS keys = 1..4
                'set1'     => clamp_int($row[5] ?? 1, 1, 5, 1),
                'set2'     => clamp_int($row[6] ?? 1, 1, 5, 1),
                'set3'     => clamp_int($row[7] ?? 1, 1, 5, 1),
            ],
        ];
    }

    if ($rowsSeen === 0) {
        $errors[] = 'ไม่พบข้อมูลในไฟล์ (เริ่มกรอกที่แถวที่ 4 ของ template)';
    }

    $result = ['ok' => false, 'errors' => $errors, 'rows_seen' => $rowsSeen,
               'imported' => 0, 'updated' => 0, 'generated' => [], 'moved' => []];
    if ($errors || !$entries) {
        return $result;                                 // ผิดตั้งแต่ในไฟล์ — ไม่แตะ DB
    }

    // ── ตรวจเจ้าของ + gen รหัส + เขียนจริง ในทรานแซกชันเดียว ──
    // GET_LOCK กันสองโปรเซส gen เลขรันชนกัน (PK (stuid,years) เป็นด่านสุดท้าย)
    $gotLock = false;
    try {
        $lk = $pdo->prepare('SELECT GET_LOCK(?, 10)');
        $lk->execute([STUID_GEN_LOCK]);
        $gotLock = ((int)$lk->fetchColumn() === 1);

        $pdo->beginTransaction();

        // เจ้าของของรหัสที่กรอกเอง (ล็อกแถว FOR UPDATE กัน race)
        $provided = [];
        foreach ($entries as $e) {
            if ($e['stuid'] !== null) {
                $provided[] = $e['stuid'];
            }
        }
        $moved = [];
        if ($provided) {
            $ph  = implode(',', array_fill(0, count($provided), '?'));
            $own = $pdo->prepare("SELECT stuid, years, sc_id FROM students WHERE stuid IN ({$ph}) FOR UPDATE");
            $own->execute($provided);

            // เจ้าของปัจจุบันต่อ stuid: แถวปี $year ชนะ ไม่งั้นแถวปีล่าสุด
            $owner = [];
            foreach ($own->fetchAll() as $r) {
                $sid   = (string)$r['stuid'];
                $isCur = (int)$r['years'] === $year;
                if (!isset($owner[$sid])
                    || ($isCur && !$owner[$sid]['cur'])
                    || (!$owner[$sid]['cur'] && (int)$r['years'] > $owner[$sid]['years'])) {
                    $owner[$sid] = ['sc_id' => (string)$r['sc_id'], 'years' => (int)$r['years'], 'cur' => $isCur];
                }
            }
            foreach ($owner as $sid => $o) {
                if ($o['sc_id'] === $scid) {
                    continue;                           // ของโรงเรียนตัวเอง = อัปเดตได้
                }
                if ($isAdmin) {
                    $moved[$sid] = $o['sc_id'];          // admin รับย้ายเข้าโรงเรียนที่กำลังดู
                } else {
                    $errors[] = "แถวที่ {$seenAt[$sid]}: รหัส {$sid} เป็นนักเรียนของโรงเรียนอื่น — "
                              . "นำเข้าไม่ได้ (เว้นช่องรหัสว่างไว้ ระบบจะกำหนดรหัสใหม่ให้)";
                }
            }
            if ($errors) {
                $pdo->rollBack();
                $result['errors'] = $errors;
                return $result;
            }
        }

        // gen รหัสให้แถวที่เว้นว่าง — ต่อจากเลขสูงสุด ข้ามเลขที่กรอกเองในไฟล์เดียวกัน
        $next  = students_max_generated_id($pdo);
        $genFor = [];   // index ใน $entries => รหัสที่ gen
        foreach ($entries as $idx => $e) {
            if ($e['stuid'] !== null) {
                continue;
            }
            do {
                $next++;
                $cand = (string)$next;
            } while (isset($seenAt[$cand]));            // กันชนรหัสที่กรอกเองในไฟล์
            $genFor[$idx] = $cand;
            $seenAt[$cand] = $e['rowNo'];               // กันชนกันเองในชุด gen
        }

        // upsert — เฉพาะ admin จึงย้าย sc_id ตามไฟล์ (กรณีรับย้ายข้ามโรงเรียน)
        $up = $pdo->prepare('INSERT INTO students
            (stuid, stuname, sc_id, class_id, rooms, stustatus, years,
             hit1, hit1tested, hit2, hit2tested, hit3, hit3tested, sethit1, sethit2, sethit3)
            VALUES (?,?,?,?,?,?,?, 0,0,0,0,0,0, ?,?,?)
            ON DUPLICATE KEY UPDATE stuname=VALUES(stuname), class_id=VALUES(class_id), rooms=VALUES(rooms),
                stustatus=VALUES(stustatus), sethit1=VALUES(sethit1), sethit2=VALUES(sethit2), sethit3=VALUES(sethit3)'
            . ($isAdmin ? ', sc_id=VALUES(sc_id)' : ''));

        $imported  = 0;
        $updated   = 0;
        $generated = [];
        foreach ($entries as $idx => $e) {
            $sid = $e['stuid'] ?? $genFor[$idx];
            $d   = $e['data'];
            $up->execute([$sid, $d['stuname'], $scid, $d['class_id'], $d['rooms'],
                          $d['status'], $year, $d['set1'], $d['set2'], $d['set3']]);
            // rowCount: 1 = เพิ่มใหม่, 2 = อัปเดต(มีการเปลี่ยน), 0 = ไม่เปลี่ยน
            if ($up->rowCount() === 1) {
                $imported++;
            } else {
                $updated++;
            }
            if ($e['stuid'] === null) {
                $generated[] = ['stuid' => $sid, 'stuname' => $d['stuname']];
            }
        }
        $pdo->commit();
        return ['ok' => true, 'errors' => [], 'rows_seen' => $rowsSeen,
                'imported' => $imported, 'updated' => $updated,
                'generated' => $generated, 'moved' => $moved];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $result['errors'] = ['บันทึกไม่สำเร็จ — ยกเลิกการนำเข้าทั้งหมด ไม่มีข้อมูลถูกบันทึก'];
        return $result;
    } finally {
        if ($gotLock) {
            $pdo->prepare('SELECT RELEASE_LOCK(?)')->execute([STUID_GEN_LOCK]);
        }
    }
}
