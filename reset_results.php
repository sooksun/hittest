<?php
/**
 * reset_results.php — รีเซตผลการสอบ (รายห้อง / รายโรงเรียน, ทุกรอบ / เฉพาะรอบ)
 * ลบเฉพาะข้อมูลของโรงเรียนที่ login (scoped sc_id) + บันทึกประวัติลง reset_log
 *
 * POST: mode = room | school , hittest = 0(ทุกรอบ)|1|2|3 , (room: class_id, rooms)
 */
require __DIR__ . '/includes/auth.php';
require_editor();   // บัญชีผู้ชม (viewer) รีเซตผลไม่ได้

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['status' => 'error', 'message' => 'method not allowed'], 405);
}

$scid    = current_sc_id();
$year    = current_year();                          // รีเซตเฉพาะปีปัจจุบัน (ปีเก่าเป็นประวัติ ไม่แตะ)
$mode    = $_POST['mode'] ?? '';
$hittest = (int)($_POST['hittest'] ?? 0);          // 0 = ทุกรอบ
if ($hittest !== 0 && !valid_hit($hittest)) {
    json_response(['status' => 'error', 'message' => 'รอบไม่ถูกต้อง'], 400);
}

$class_id = null;
$rooms    = null;
if ($mode === 'room') {
    $class_id = (int)($_POST['class_id'] ?? 0);
    $rooms    = (int)($_POST['rooms'] ?? 0);
    if ($class_id < 1 || $class_id > 6) {
        json_response(['status' => 'error', 'message' => 'ชั้นไม่ถูกต้อง'], 400);
    }
    $where  = 'sc_id = ? AND years = ? AND class_id = ? AND rooms = ?';
    $params = [$scid, $year, $class_id, $rooms];
    $label  = "ป.$class_id ห้อง $rooms";
} elseif ($mode === 'school') {
    $where  = 'sc_id = ? AND years = ?';
    $params = [$scid, $year];
    $label  = 'ทั้งโรงเรียน';
} else {
    json_response(['status' => 'error', 'message' => 'ขอบเขตไม่ถูกต้อง'], 400);
}

$roundLabel = $hittest ? "Hit-$hittest" : 'ทุกรอบ';

$pdo = db();
try {
    $pdo->beginTransaction();

    // จำนวนนักเรียนในขอบเขต
    $cs = $pdo->prepare("SELECT COUNT(*) FROM students WHERE $where");
    $cs->execute($params);
    $nStu = (int)$cs->fetchColumn();

    $sub = "SELECT stuid FROM students WHERE $where";

    if ($hittest) {
        // ---- เฉพาะรอบที่เลือก (ปีปัจจุบัน) ----
        $p = array_merge($params, [$hittest, $year]);   // sub(students)+hittest+ปีของตารางคะแนน
        $de = $pdo->prepare("DELETE FROM evaluations WHERE stuid IN ($sub) AND hittest = ? AND years = ?"); $de->execute($p);
        $dh = $pdo->prepare("DELETE FROM studenthit  WHERE stuid IN ($sub) AND hit = ? AND years = ?");     $dh->execute($p);
        $ds = $pdo->prepare("DELETE FROM studenteval WHERE stuid IN ($sub) AND hittest = ? AND years = ?"); $ds->execute($p);
        $pdo->prepare("UPDATE students SET `hit{$hittest}` = 0, `hit{$hittest}tested` = 0 WHERE $where")->execute($params);
    } else {
        // ---- ทุกรอบ (ปีปัจจุบัน) ----
        $pAll = array_merge($params, [$year]);
        $de = $pdo->prepare("DELETE FROM evaluations WHERE stuid IN ($sub) AND years = ?"); $de->execute($pAll);
        $dh = $pdo->prepare("DELETE FROM studenthit  WHERE stuid IN ($sub) AND years = ?"); $dh->execute($pAll);
        $ds = $pdo->prepare("DELETE FROM studenteval WHERE stuid IN ($sub) AND years = ?"); $ds->execute($pAll);
        $pdo->prepare("UPDATE students
            SET hit1 = 0, hit2 = 0, hit3 = 0, hit1tested = 0, hit2tested = 0, hit3tested = 0
            WHERE $where")->execute($params);
    }
    $nEval = $de->rowCount();
    $nHit  = $dh->rowCount();
    $nSe   = $ds->rowCount();

    // บันทึกประวัติการรีเซต
    $pdo->prepare(
        'INSERT INTO reset_log
         (sc_id, sc_smis, sc_name, scope, class_id, rooms, hittest,
          students_affected, eval_deleted, studenthit_deleted, studenteval_deleted, created_at)
         VALUES (?,?,?,?,?,?,?,?,?,?,?, NOW())'
    )->execute([
        $scid, $_SESSION['sc_smis'] ?? null, $_SESSION['sc_name'] ?? null,
        $mode, $class_id, $rooms, ($hittest ?: null),
        $nStu, $nEval, $nHit, $nSe,
    ]);

    $pdo->commit();
    json_response([
        'status'      => 'ok',
        'label'       => $label,
        'round'       => $roundLabel,
        'students'    => $nStu,
        'evaluations' => $nEval,
        'studenthit'  => $nHit,
        'studenteval' => $nSe,
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    json_response(['status' => 'error', 'message' => 'รีเซตไม่สำเร็จ'], 500);
}
