<?php
/**
 * student_add.php — เพิ่มนักเรียนรายคน (โรงเรียนที่ login, ปีปัจจุบัน)
 * POST: stuname(required), class_id(1-6), rooms(>=1), stustatus(key STU_STATUS),
 *       sethit1/2/3(1-5, ไม่บังคับ default 1), stuid(ไม่บังคับ — ว่าง = ระบบสร้างรหัส 9-series)
 * คืน {status:ok, stuid:..., generated:bool}
 */
require __DIR__ . '/includes/auth.php';
require_editor();   // บัญชีผู้ชม (viewer) เพิ่มนักเรียนไม่ได้
require_once __DIR__ . '/includes/students_import_lib.php';   // STUID gen helpers

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['status' => 'error', 'message' => 'method not allowed'], 405);
}

$pdo  = db();
$scid = current_sc_id();
$yr   = current_year();

$stuname  = trim((string)($_POST['stuname'] ?? ''));
$class_id = (int)($_POST['class_id'] ?? 0);
$rooms    = (int)($_POST['rooms'] ?? 0);
$status   = (int)($_POST['stustatus'] ?? 1);
$stuidIn  = trim((string)($_POST['stuid'] ?? ''));

if ($stuname === '' || mb_strlen($stuname) > 255) {
    json_response(['status' => 'error', 'message' => 'กรุณากรอกชื่อ - สกุล (ไม่เกิน 255 ตัวอักษร)'], 400);
}
if ($class_id < 1 || $class_id > 6) {
    json_response(['status' => 'error', 'message' => 'ชั้นไม่ถูกต้อง'], 400);
}
if ($rooms < 1) {
    json_response(['status' => 'error', 'message' => 'ห้องไม่ถูกต้อง'], 400);
}
if (!array_key_exists($status, STU_STATUS)) {
    json_response(['status' => 'error', 'message' => 'ประเภทนักเรียนไม่ถูกต้อง'], 400);
}
$sets = [];
foreach ([1, 2, 3] as $n) {
    $sets[$n] = clamp_int($_POST["sethit$n"] ?? 1, 1, 5, 1);
}

// รหัสที่กรอกเอง: ต้องเป็นตัวเลขล้วน และต้องยังไม่มีในปีนี้ (PK = stuid+years unique ทั้งระบบ)
if ($stuidIn !== '') {
    if (!preg_match('/^\d{1,20}$/', $stuidIn)) {
        json_response(['status' => 'error', 'message' => 'รหัสนักเรียนต้องเป็นตัวเลขเท่านั้น'], 400);
    }
    $chk = $pdo->prepare('SELECT 1 FROM students WHERE stuid = ? AND years = ? LIMIT 1');
    $chk->execute([$stuidIn, $yr]);
    if ($chk->fetchColumn()) {
        json_response(['status' => 'error', 'message' => 'รหัสนักเรียนนี้มีอยู่แล้วในปีการศึกษานี้'], 409);
    }
}

$insSql = 'INSERT INTO students
    (stuid, stuname, sc_id, class_id, years, rooms, stustatus,
     hit1, hit1tested, hit2, hit2tested, hit3, hit3tested, sethit1, sethit2, sethit3)
    VALUES (?,?,?,?,?,?,?, 0,0,0,0,0,0, ?,?,?)';

try {
    // กรอกรหัสเอง — เพิ่มตรง ๆ
    if ($stuidIn !== '') {
        $pdo->prepare($insSql)->execute([$stuidIn, $stuname, $scid, $class_id, $yr, $rooms, $status, $sets[1], $sets[2], $sets[3]]);
        json_response(['status' => 'ok', 'stuid' => $stuidIn, 'generated' => false]);
    }

    // เว้นว่าง — ระบบสร้างรหัส 9-series (กันชนข้ามโปรเซสด้วย GET_LOCK; PK เป็นด่านสุดท้าย)
    $pdo->prepare('SELECT GET_LOCK(?, 10)')->execute([STUID_GEN_LOCK]);
    $next = students_max_generated_id($pdo) + 1;
    $ins  = $pdo->prepare($insSql);
    $sid  = null;
    for ($attempt = 0; $attempt < 50; $attempt++) {
        $cand = (string)($next + $attempt);
        try {
            $ins->execute([$cand, $stuname, $scid, $class_id, $yr, $rooms, $status, $sets[1], $sets[2], $sets[3]]);
            $sid = $cand;
            break;
        } catch (\PDOException $e) {
            if ((int)($e->errorInfo[1] ?? 0) === 1062) {
                continue;   // PK ชน → ลองเลขถัดไป
            }
            $pdo->prepare('SELECT RELEASE_LOCK(?)')->execute([STUID_GEN_LOCK]);
            throw $e;
        }
    }
    $pdo->prepare('SELECT RELEASE_LOCK(?)')->execute([STUID_GEN_LOCK]);

    if ($sid === null) {
        json_response(['status' => 'error', 'message' => 'สร้างรหัสนักเรียนไม่สำเร็จ'], 500);
    }
    json_response(['status' => 'ok', 'stuid' => $sid, 'generated' => true]);
} catch (Throwable $e) {
    json_response(['status' => 'error', 'message' => 'เพิ่มนักเรียนไม่สำเร็จ'], 500);
}
