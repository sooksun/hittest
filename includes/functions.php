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
if (!function_exists('json_response')) {
    function json_response(array $data, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

/**
 * เขียน 1 แถวลง audit_logs — ใช้ทั้ง request middleware และ security event ในแต่ละ handler
 * ห้าม throw: ถ้า audit ล้มเหลวต้องไม่ทำให้ request พัง (best-effort)
 */
function audit_log_event(PDO $pdo, array $fields): void
{
    static $cols = [
        'sc_id', 'stuid', 'role', 'method', 'path', 'action',
        'entity_type', 'entity_id', 'status_code', 'ip', 'user_agent',
        'duration_ms', 'meta_json',
    ];
    try {
        $row = [];
        foreach ($cols as $c) {
            $v = $fields[$c] ?? null;
            if ($c === 'meta_json' && is_array($v)) {
                $v = json_encode($v, JSON_UNESCAPED_UNICODE);
            }
            if ($c === 'user_agent' && is_string($v)) {
                $v = mb_substr($v, 0, 255);
            }
            $row[] = $v;
        }
        $ph = implode(',', array_fill(0, count($cols), '?'));
        $pdo->prepare('INSERT INTO audit_logs (' . implode(',', $cols) . ') VALUES (' . $ph . ')')
            ->execute($row);
    } catch (Throwable $e) {
        // audit must never break the request
    }
}

/**
 * true ถ้า $stuid ยิงคำขอ TTS (action='tts_request') เกิน $max ครั้งใน $windowSec วินาทีล่าสุด
 * นับจาก audit_logs. Fail-open: ถ้าตรวจไม่ได้ (เช่นตารางหาย) คืน false เพื่อไม่บล็อกผู้ใช้
 */
function tts_rate_exceeded(PDO $pdo, string $stuid, int $max, int $windowSec): bool
{
    $windowSec = max(1, $windowSec);
    try {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM audit_logs
             WHERE stuid = ? AND action = 'tts_request'
               AND created_at > (NOW(3) - INTERVAL {$windowSec} SECOND)"
        );
        $stmt->execute([$stuid]);
        return (int)$stmt->fetchColumn() >= $max;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * ลบ audit_logs ที่เก่ากว่า $days วัน แบบเป็น batch (กัน lock ตารางนานตอนตารางใหญ่)
 * คืนจำนวนแถวที่ลบทั้งหมด — $days/$batch เป็น int ที่ระบบคุมเอง จึง interpolate ปลอดภัย
 */
function prune_audit_logs(PDO $pdo, int $days, int $batch = 5000): int
{
    $days  = max(1, $days);
    $batch = max(1, min(50000, $batch));
    $sql   = "DELETE FROM audit_logs WHERE created_at < (NOW() - INTERVAL {$days} DAY) LIMIT {$batch}";
    $total = 0;
    do {
        $n = (int)$pdo->exec($sql);
        $total += $n;
    } while ($n === $batch);
    return $total;
}

/**
 * Request-level audit middleware. เรียกครั้งเดียวจาก api/index.php ก่อน dispatch.
 * ลงทะเบียน shutdown function เพื่อบันทึก method/path/identity/status/duration
 * หลัง handler ส่ง response (json_response() เรียก exit → shutdown ยังทำงาน).
 */
function audit_request_begin(array $ctx): void
{
    $start = microtime(true);
    register_shutdown_function(function () use ($ctx, $start) {
        audit_log_event(db(), [
            'sc_id'       => $ctx['sc_id']  ?? null,
            'stuid'       => $ctx['stuid']  ?? null,
            'role'        => $ctx['role']   ?? null,
            'method'      => $ctx['method'] ?? null,
            'path'        => $ctx['path']   ?? null,
            'action'      => 'request',
            'status_code' => http_response_code() ?: null,
            'ip'          => $_SERVER['REMOTE_ADDR']     ?? null,
            'user_agent'  => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'duration_ms' => (int)round((microtime(true) - $start) * 1000),
        ]);
    });
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
