<?php
/**
 * save_results.php — บันทึกผลการสอบอ่าน (รับ JSON array จาก exam.js)
 * รองรับครบ 3 รอบ (hit1/hit2/hit3) — เลือกคอลัมน์/ชุดตามรอบจริง
 *
 * เขียน 4 ตาราง:
 *   1) evaluations  ผลรายคำ (PK: stuid,hittest,years,autoid)
 *   2) studenteval  คะแนนรวมต่อรอบ (PK: stuid,hittest,years)
 *   3) students     hit{N} = คะแนน, hit{N}tested = 1
 *   4) studenthit   สรุป item1..item20 (PK: stuid,hit)
 */
require __DIR__ . '/includes/auth.php';
require_editor();   // บัญชีผู้ชม (viewer) บันทึกผลไม่ได้

$results = json_decode(file_get_contents('php://input'), true);
if (!is_array($results) || count($results) === 0) {
    json_response(['status' => 'error', 'message' => 'ไม่มีข้อมูลผลการสอบ'], 400);
}

$stuid   = (string)($results[0]['stuid'] ?? '');
$hittest = (int)($results[0]['hittest'] ?? 0);
$years   = current_year();   // ปีการศึกษาจากเซิร์ฟเวอร์เสมอ — ไม่เชื่อค่า years ที่ client ส่งมา (กันเขียนทับผลปีอื่น)
if (!valid_hit($hittest)) {
    json_response(['status' => 'error', 'message' => 'รอบสอบไม่ถูกต้อง (hittest)'], 400);
}

// ตรวจสิทธิ์: นักเรียนต้องอยู่ในโรงเรียนที่ login
$student = find_student($stuid);
if (!$student) {
    json_response(['status' => 'error', 'message' => 'ไม่พบนักเรียน หรือไม่มีสิทธิ์'], 403);
}
if (!exam_is_open($hittest)) {
    json_response(['status' => 'error', 'message' => 'รอบสอบนี้ถูกปิดอยู่ ไม่สามารถบันทึกผลได้'], 403);
}

$pdo = db();

// เขียน 4 ตารางในทรานแซกชันเดียว — ถ้าพังกลางคัน rollback ทั้งหมด (กันผลไม่ครบข้ามตาราง)
$pdo->beginTransaction();
try {
    /* ---- 1) evaluations: ผลรายคำ + รวมคะแนน + เก็บผลรายข้อ ---- */
    $score  = 0;
    $autoid = 0;
    $items  = array_fill(1, WORDS_PER_SET, 0);
    $stmtEval = $pdo->prepare(
        'INSERT INTO evaluations (stuid, hittest, years, autoid, word_id, correct)
         VALUES (:stuid, :hittest, :years, :autoid, :word_id, :correct)
         ON DUPLICATE KEY UPDATE word_id = VALUES(word_id), correct = VALUES(correct)'
    );
    foreach ($results as $r) {
        $autoid++;
        $correct = !empty($r['correct']) ? 1 : 0;
        if ($correct) {
            $score++;
        }
        if ($autoid <= WORDS_PER_SET) {
            $items[$autoid] = $correct;
        }
        $stmtEval->execute([
            ':stuid'   => $stuid,
            ':hittest' => $hittest,
            ':years'   => $years,
            ':autoid'  => $autoid,
            ':word_id' => (int)($r['word_id'] ?? 0),
            ':correct' => $correct,
        ]);
    }

    /* ---- 2) studenteval: คะแนนรวมต่อรอบ ---- */
    $pdo->prepare(
        'INSERT INTO studenteval (stuid, hittest, years, score)
         VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE score = VALUES(score)'
    )->execute([$stuid, $hittest, $years, $score]);

    /* ---- 3) students: คะแนน + ธงสอบแล้ว ตามรอบจริง (เฉพาะแถวปีของรอบสอบนี้) ---- */
    $pdo->prepare("UPDATE students SET `hit{$hittest}` = ?, `hit{$hittest}tested` = 1 WHERE stuid = ? AND years = ?")
        ->execute([$score, $stuid, $years]);

    /* ---- 4) studenthit: สรุป denormalize item1..item20 ตามรอบจริง (PK = stuid, years, hit) ---- */
    $sethit  = (int)($student["sethit{$hittest}"] ?? 0);
    $itemCols = array_map(fn($i) => "item$i", range(1, WORDS_PER_SET));
    $cols  = array_merge(['stuid', 'hit', 'years', 'stuname', 'sc_id', 'class_id', 'rooms', 'stustatus', 'hitscore', 'sethit'], $itemCols);
    $place = implode(', ', array_fill(0, count($cols), '?'));
    $update = implode(', ', array_map(
        fn($c) => "`$c` = VALUES(`$c`)",
        array_merge(['stuname', 'sc_id', 'class_id', 'rooms', 'stustatus', 'hitscore', 'sethit'], $itemCols)
    ));
    $sql = 'INSERT INTO studenthit (' . implode(', ', $cols) . ', updatedDate) '
         . "VALUES ($place, NOW()) ON DUPLICATE KEY UPDATE $update, updatedDate = NOW()";

    $params = [
        $stuid, $hittest, $years, $student['stuname'], $student['sc_id'],
        $student['class_id'], $student['rooms'], $student['stustatus'], $score, $sethit,
    ];
    for ($i = 1; $i <= WORDS_PER_SET; $i++) {
        $params[] = $items[$i];
    }
    $pdo->prepare($sql)->execute($params);

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    json_response(['status' => 'error', 'message' => 'บันทึกผลไม่สำเร็จ'], 500);
}

json_response(['status' => 'ok', 'score' => $score, 'hittest' => $hittest]);
