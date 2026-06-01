<?php
/**
 * includes/student_auth.php — Phase 3: ระบบ login นักเรียนด้วย PIN + throttle + จัดการ PIN
 *
 * Session ของนักเรียนแยกจากครู/ผู้ดูแลโดยเด็ดขาด:
 *   - student:  $_SESSION['role']='student', $_SESSION['stu']=[stuid,sc_id,stuname,class_id,rooms]
 *   - admin:    $_SESSION['sc_id'] (ของเดิม) — **ไม่เซ็ตให้ student**
 * จึงไม่ปนสิทธิ์กัน (admin auth.php เช็ค sc_id, student เช็ค role+stu)
 */
require_once __DIR__ . '/functions.php';   // db()

const STU_PIN_LENGTH      = 6;             // ความยาว PIN (doc แนะนำ 6 หลัก)
const STU_THROTTLE_MAX    = 5;             // ผิดเกินกี่ครั้ง (ต่อ sc+ip) → ล็อก
const STU_THROTTLE_LOCK   = 15;            // ล็อกกี่นาที
const STU_BACKOFF_CAP     = 30;            // เพดาน exponential backoff (วินาที)

/* ----------------------------- Session helpers ----------------------------- */

/** บังคับว่าต้อง login เป็นนักเรียน — ไม่ใช่ → ส่งไปหน้า login นักเรียน */
function require_student(): array
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    if (($_SESSION['role'] ?? '') !== 'student' || empty($_SESSION['stu']['stuid'])) {
        header('Location: student_login.php');
        exit;
    }
    return $_SESSION['stu'];
}

/** เซ็ต session นักเรียน (ล้าง session เดิมก่อน กันปนสิทธิ์ครู) */
function student_session_set(array $stu): void
{
    $_SESSION = [];
    session_regenerate_id(true);
    $_SESSION['role'] = 'student';
    $_SESSION['stu']  = [
        'stuid'    => (string)$stu['stuid'],
        'sc_id'    => (string)$stu['sc_id'],
        'stuname'  => (string)$stu['stuname'],
        'class_id' => (int)$stu['class_id'],
        'rooms'    => (int)($stu['rooms'] ?? 0),
    ];
}

/* ------------------------------- Throttle ---------------------------------- */
/**
 * ตรวจว่าถูกล็อก/ต้องรอหรือไม่ — รับ array ของ scope_key
 * คืน ['blocked'=>bool, 'wait'=>วินาทีที่ต้องรอ, 'message'=>ข้อความ]
 */
function throttle_check(array $keys): array
{
    if (!$keys) {
        return ['blocked' => false, 'wait' => 0, 'message' => ''];
    }
    $in  = implode(',', array_fill(0, count($keys), '?'));
    $st  = db()->prepare("SELECT scope_key, fail_count, locked_until, updated_at
                          FROM login_throttle WHERE scope_key IN ($in)");
    $st->execute($keys);
    $now = time();
    foreach ($st->fetchAll() as $r) {
        // 1) ล็อกแข็ง (เกินจำนวนครั้ง)
        if ($r['locked_until'] !== null) {
            $until = strtotime($r['locked_until']);
            if ($until > $now) {
                return ['blocked' => true, 'wait' => $until - $now,
                        'message' => 'พยายามผิดหลายครั้งเกินไป — ลองใหม่ในอีก ' . ceil(($until - $now) / 60) . ' นาที'];
            }
        }
        // 2) exponential backoff (หน่วงเวลาแต่ละครั้งที่ผิด)
        $fail = (int)$r['fail_count'];
        if ($fail > 0) {
            $need = min(2 ** $fail, STU_BACKOFF_CAP);
            $since = $now - strtotime($r['updated_at']);
            if ($since < $need) {
                return ['blocked' => true, 'wait' => $need - $since,
                        'message' => 'กรุณารออีก ' . ($need - $since) . ' วินาทีก่อนลองใหม่'];
            }
        }
    }
    return ['blocked' => false, 'wait' => 0, 'message' => ''];
}

/** บันทึกความล้มเหลว — เพิ่ม fail_count ทุก key; ถ้าถึงเพดาน → ตั้ง locked_until */
function throttle_fail(array $keys): void
{
    $sql = 'INSERT INTO login_throttle (scope_key, fail_count, locked_until, updated_at)
            VALUES (?, 1, NULL, NOW())
            ON DUPLICATE KEY UPDATE
                fail_count   = fail_count + 1,
                locked_until = IF(fail_count + 1 >= ' . (int)STU_THROTTLE_MAX . ',
                                  DATE_ADD(NOW(), INTERVAL ' . (int)STU_THROTTLE_LOCK . ' MINUTE), locked_until),
                updated_at   = NOW()';
    $st = db()->prepare($sql);
    foreach ($keys as $k) {
        $st->execute([$k]);
    }
}

/** ล้างตัวนับเมื่อ login สำเร็จ */
function throttle_reset(array $keys): void
{
    if (!$keys) {
        return;
    }
    $in = implode(',', array_fill(0, count($keys), '?'));
    db()->prepare("DELETE FROM login_throttle WHERE scope_key IN ($in)")->execute($keys);
}

/** สร้างชุด scope_key สำหรับ throttle (ชั้น IP / school+IP / school-wide) */
function throttle_keys(string $scId, string $ip): array
{
    return ["ip:$ip", "sc:$scId:$ip", "sc:$scId"];
}

/* --------------------------- PIN: ตรวจ + สร้าง ------------------------------ */

/** หา sc_id (10 หลัก) จากรหัส SMIS ที่นักเรียนกรอก — null ถ้าไม่พบ */
function school_id_from_smis(string $smis): ?string
{
    $st = db()->prepare('SELECT sc_id FROM schools WHERE sc_smis = ?');
    $st->execute([$smis]);
    $r = $st->fetch();
    return $r ? (string)$r['sc_id'] : null;
}

/**
 * ตรวจ PIN — คืนข้อมูลนักเรียนถ้าถูก, null ถ้าผิด
 * lookup ด้วย pin_plain (มี unique index) แล้วยืนยันด้วย password_verify(pin_hash)
 */
function student_pin_verify(string $scId, string $pin): ?array
{
    $st = db()->prepare(
        'SELECT sp.stuid, sp.pin_hash, s.stuname, s.class_id, s.rooms
         FROM student_pin sp
         JOIN students s ON s.stuid = sp.stuid AND s.sc_id = sp.sc_id
         WHERE sp.sc_id = ? AND sp.pin_plain = ? AND sp.is_active = 1'
    );
    $st->execute([$scId, $pin]);
    $r = $st->fetch();
    if (!$r || !password_verify($pin, $r['pin_hash'])) {
        return null;
    }
    return ['stuid' => $r['stuid'], 'sc_id' => $scId, 'stuname' => $r['stuname'],
            'class_id' => (int)$r['class_id'], 'rooms' => (int)$r['rooms']];
}

/**
 * เข้าระบบด้วย "รหัสนักเรียน (stuid)" — ใช้ stuid เป็นทั้ง username/password
 * stuid เป็น PK ของ students (unique ทั้งระบบ) → ระบุตัว + โรงเรียนได้ในตัว ไม่ต้องเลือกโรงเรียน
 * คืนข้อมูลนักเรียนถ้าพบ, null ถ้าไม่พบ
 */
function student_login_by_id(string $stuid): ?array
{
    $st = db()->prepare('SELECT stuid, stuname, sc_id, class_id, rooms FROM students WHERE stuid = ?');
    $st->execute([$stuid]);
    $r = $st->fetch();
    if (!$r) {
        return null;
    }
    return ['stuid' => (string)$r['stuid'], 'sc_id' => (string)$r['sc_id'], 'stuname' => (string)$r['stuname'],
            'class_id' => (int)$r['class_id'], 'rooms' => (int)$r['rooms']];
}

/** สุ่ม PIN ตัวเลข 6 หลักแบบ secure */
function generate_pin(): string
{
    $min = 10 ** (STU_PIN_LENGTH - 1);          // 100000
    $max = (10 ** STU_PIN_LENGTH) - 1;          // 999999
    return (string)random_int($min, $max);
}

/** set ของ PIN ที่ใช้อยู่ในโรงเรียน (กันชนตอน generate) */
function active_pins_in_school(string $scId): array
{
    $st = db()->prepare('SELECT pin_plain FROM student_pin WHERE sc_id = ? AND pin_plain IS NOT NULL');
    $st->execute([$scId]);
    return array_column($st->fetchAll(), 'pin_plain');
}

/**
 * สร้าง/แทนที่ PIN ของนักเรียน 1 คน (unique ภายในโรงเรียน)
 * $existing = set ของ PIN ที่มีอยู่ (ส่งเข้ามาเพื่อ generate ทีละหลายคนโดยไม่ query ซ้ำ)
 * คืน PIN ใหม่ (plaintext) เพื่อนำไปแสดง/พิมพ์
 */
function upsert_student_pin(string $scId, string $stuid, array &$existing, ?string $createdBy): string
{
    do {
        $pin = generate_pin();
    } while (in_array($pin, $existing, true));
    $existing[] = $pin;

    db()->prepare(
        'INSERT INTO student_pin (sc_id, stuid, pin_hash, pin_plain, is_active, created_by, created_at)
         VALUES (?,?,?,?,1,?,NOW())
         ON DUPLICATE KEY UPDATE pin_hash=VALUES(pin_hash), pin_plain=VALUES(pin_plain),
                                 is_active=1, created_by=VALUES(created_by), created_at=NOW()'
    )->execute([$scId, $stuid, password_hash($pin, PASSWORD_DEFAULT), $pin, $createdBy]);

    return $pin;
}

/** ดึง PIN ปัจจุบันของนักเรียนทั้งชั้น (สำหรับหน้า teacher_pins) — map stuid => pin_plain */
function class_pins(string $scId, int $classId): array
{
    $st = db()->prepare(
        'SELECT sp.stuid, sp.pin_plain
         FROM student_pin sp
         JOIN students s ON s.stuid = sp.stuid AND s.sc_id = sp.sc_id
         WHERE sp.sc_id = ? AND s.class_id = ? AND sp.is_active = 1'
    );
    $st->execute([$scId, $classId]);
    $map = [];
    foreach ($st->fetchAll() as $r) {
        $map[(string)$r['stuid']] = $r['pin_plain'];
    }
    return $map;
}
