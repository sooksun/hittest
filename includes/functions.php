<?php
/**
 * includes/functions.php — ฟังก์ชันร่วมของระบบ
 */
require_once __DIR__ . '/db.php';

/** รหัสโรงเรียนที่กำลัง login (จาก session) */
function current_sc_id(): string
{
    return (string)($_SESSION['sc_id'] ?? '');
}

/** ดึงข้อมูลนักเรียน จำกัดเฉพาะโรงเรียนที่ login — คืน null ถ้าไม่พบ/ไม่มีสิทธิ์ */
function find_student(string $stuid): ?array
{
    $stmt = db()->prepare('SELECT * FROM students WHERE stuid = ? AND sc_id = ?');
    $stmt->execute([$stuid, current_sc_id()]);
    return $stmt->fetch() ?: null;
}

/** ตรวจว่ารอบสอบถูกต้อง (1–3) */
function valid_hit($hit): bool
{
    return in_array((int)$hit, HITTESTS, true);
}

/** สถานะเปิด/ปิดการสอบของโรงเรียนที่ login — [1=>bool, 2=>bool, 3=>bool] (ค่าเริ่มต้น = เปิด) */
function exam_windows(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $open = [1 => true, 2 => true, 3 => true];
    $stmt = db()->prepare('SELECT hittest, is_open FROM exam_window WHERE sc_id = ?');
    $stmt->execute([current_sc_id()]);
    foreach ($stmt->fetchAll() as $r) {
        $h = (int)$r['hittest'];
        if (isset($open[$h])) {
            $open[$h] = (int)$r['is_open'] === 1;
        }
    }
    return $cache = $open;
}

/** รอบสอบนี้เปิดให้สอบอยู่ไหม (เฉพาะโรงเรียนที่ login) */
function exam_is_open(int $hittest): bool
{
    $w = exam_windows();
    return $w[$hittest] ?? true;
}

/** ชื่อสถานะนักเรียนจากรหัส */
function status_name(int $id): string
{
    return STU_STATUS[$id] ?? '-';
}

/**
 * สร้าง HTML เซลล์รอบ Hit สำหรับตารางรายชื่อ
 *  - สอบแล้ว  -> ป้าย "ดูผล N" + ปุ่มยกเลิก
 *  - ยังไม่สอบ -> ปุ่ม "เข้าทดสอบ (ชุด N)"
 */
function hit_cell(array $stu, int $hit, int $class_id): string
{
    $tested = (int)($stu["hit{$hit}tested"] ?? 0);
    $score  = (int)($stu["hit{$hit}"] ?? 0);
    $set    = (int)($stu["sethit{$hit}"] ?? 0);
    $stuidA = htmlspecialchars($stu['stuid'], ENT_QUOTES);
    $stuidU = urlencode($stu['stuid']);

    if ($tested) {
        return sprintf(
            '<span class="d-inline-flex align-items-center gap-1">'
            . '<a class="ht-badge ht-badge--done" href="evaluations_view.php?stuid=%s&hittest=%d">✓ ดูผล %d</a>'
            . '<button class="ht-cancel js-cancel" title="ยกเลิกการสอบ" data-stuid="%s" data-hit="%d">✕</button>'
            . '</span>',
            $stuidU, $hit, $score, $stuidA, $hit
        );
    }
    if (!exam_is_open($hit)) {
        return '<span class="ht-badge ht-badge--missing" title="รอบนี้ปิดการสอบอยู่ — เปิดได้ที่เมนูจัดการสอบ">🔒 ปิดสอบ</span>';
    }
    return sprintf(
        '<a class="ht-btn ht-btn--ghost ht-btn--sm" href="teacher_page.php?stuid=%s&hittest=%d&class_id=%d&sethit=%d">'
        . 'เข้าทดสอบ <span class="text-muted" style="font-weight:600">· ชุด %d</span></a>',
        $stuidU, $hit, $class_id, $set, $set
    );
}

/** ส่ง JSON response แล้วจบการทำงาน */
function json_response(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * บันทึกผลการสอบ 1 คน 1 รอบ — เขียน evaluations + studenteval + students + studenthit
 * ใช้ร่วมกันได้ทั้งการสอบด้วยคอมพิวเตอร์และการนำเข้า Excel (สอบด้วยกระดาษ)
 * @param array $student แถวจากตาราง students (ต้องมี stuid, stuname, sc_id, class_id, rooms, stustatus, sethit{N})
 * @param array $items   [1..20] => 1 (อ่านถูก) / 0 (อ่านผิด)
 * @return int           คะแนนรวม
 */
function save_hit_result(PDO $pdo, array $student, int $hittest, int $years, array $items): int
{
    $stuid    = (string)$student['stuid'];
    $class_id = (int)$student['class_id'];
    $sethit   = (int)($student["sethit{$hittest}"] ?? 0);

    // ลำดับคำ (orders 1..20) -> word_id ของชุดนี้
    $w = $pdo->prepare('SELECT id FROM words WHERE class_id = ? AND hittest = ? AND sethit = ? ORDER BY orders');
    $w->execute([$class_id, $hittest, $sethit]);
    $wordIds = $w->fetchAll(PDO::FETCH_COLUMN);

    // 1) evaluations (ผลรายคำ) + นับคะแนน
    $score = 0;
    $cells = array_fill(1, WORDS_PER_SET, 0);
    $stmtE = $pdo->prepare(
        'INSERT INTO evaluations (stuid, hittest, years, autoid, word_id, correct)
         VALUES (?,?,?,?,?,?)
         ON DUPLICATE KEY UPDATE word_id = VALUES(word_id), correct = VALUES(correct)'
    );
    for ($i = 1; $i <= WORDS_PER_SET; $i++) {
        $c = !empty($items[$i]) ? 1 : 0;
        $cells[$i] = $c;
        if ($c) {
            $score++;
        }
        $stmtE->execute([$stuid, $hittest, $years, $i, (int)($wordIds[$i - 1] ?? 0), $c]);
    }

    // 2) studenteval (คะแนนรวมต่อรอบ)
    $pdo->prepare('INSERT INTO studenteval (stuid, hittest, years, score) VALUES (?,?,?,?)
                   ON DUPLICATE KEY UPDATE score = VALUES(score)')
        ->execute([$stuid, $hittest, $years, $score]);

    // 3) students (คะแนน + ธงสอบแล้ว ตามรอบ)
    $pdo->prepare("UPDATE students SET `hit{$hittest}` = ?, `hit{$hittest}tested` = 1 WHERE stuid = ?")
        ->execute([$score, $stuid]);

    // 4) studenthit (denormalize item1..item20)
    $itemCols = array_map(fn($i) => "item$i", range(1, WORDS_PER_SET));
    $cols = array_merge(['stuid', 'hit', 'stuname', 'sc_id', 'class_id', 'rooms', 'stustatus', 'hitscore', 'sethit'], $itemCols);
    $ph   = implode(',', array_fill(0, count($cols), '?'));
    $upd  = implode(', ', array_map(
        fn($c) => "`$c`=VALUES(`$c`)",
        array_merge(['stuname', 'sc_id', 'class_id', 'rooms', 'stustatus', 'hitscore', 'sethit'], $itemCols)
    ));
    $sql = 'INSERT INTO studenthit (' . implode(',', $cols) . ', updatedDate) VALUES (' . $ph . ', NOW())'
         . ' ON DUPLICATE KEY UPDATE ' . $upd . ', updatedDate = NOW()';
    $params = [$stuid, $hittest, $student['stuname'], $student['sc_id'], $student['class_id'],
               $student['rooms'], $student['stustatus'], $score, $sethit];
    for ($i = 1; $i <= WORDS_PER_SET; $i++) {
        $params[] = $cells[$i];
    }
    $pdo->prepare($sql)->execute($params);

    return $score;
}
