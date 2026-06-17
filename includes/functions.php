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

/** SMIS (username) ที่กำลัง login */
function current_smis(): string
{
    return (string)($_SESSION['sc_smis'] ?? '');
}

/** id ของบัญชีผู้ใช้ (ตาราง users) ที่ login — 0 ถ้า login ด้วย SMIS โรงเรียน */
function current_user_id(): int
{
    return (int)($_SESSION['user_id'] ?? 0);
}

/** role ของบัญชีผู้ใช้ที่ login (superadmin|saoadmin|school) — '' ถ้า login ด้วย SMIS โรงเรียน */
function current_user_role(): string
{
    return (string)($_SESSION['user_role'] ?? '');
}

/** เขตพื้นที่ (4 หลักแรกของ SMIS) ของผู้ใช้ที่ login — '' ถ้าไม่ผูกเขต */
function current_area_code(): string
{
    return (string)($_SESSION['area_code'] ?? '');
}

/** ผู้ดูแลระบบทั้งประเทศ (role superadmin) */
function is_superadmin(): bool
{
    return current_user_role() === 'superadmin';
}

/** ผู้ดูแลเขต สพป. (role saoadmin) — เห็นทุกโรงเรียนในเขตตน แต่อ่านอย่างเดียว */
function is_saoadmin(): bool
{
    return current_user_role() === 'saoadmin';
}

/**
 * ปีการศึกษาที่ระบบทำงานอยู่ (พ.ศ.) — ใช้ scope ตาราง students/studenthit ที่ผูกปี (migration 010)
 * ตอนนี้คืน ACADEMIC_YEAR ตรง ๆ (ปีปัจจุบันปีเดียว) — แยกเป็นฟังก์ชันไว้เป็นจุดเดียว
 * เผื่ออนาคตอยากให้เลือกปีได้ (เช่นอ่านจาก query string / app_settings)
 */
function current_year(): int
{
    return ACADEMIC_YEAR;
}

/* ---------- ค่าตั้งระบบแบบ key-value (ตาราง app_settings) — สำหรับหน้า admin_config.php ---------- */

/** อ่านค่าตั้งระบบทั้งหมด (cache ต่อ request; ทนกรณีตารางยังไม่ถูกสร้าง) */
function app_settings_all(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = [];
    try {
        foreach (db()->query('SELECT skey, sval FROM app_settings') as $r) {
            $cache[$r['skey']] = $r['sval'];
        }
    } catch (Throwable $e) {
        // ตารางยังไม่ถูกสร้าง → ใช้ค่า default ทั้งหมด (config bootstrap ยังทำงานได้)
    }
    return $cache;
}

/** อ่านค่าตั้งระบบรายตัว */
function app_setting(string $key, $default = null)
{
    $all = app_settings_all();
    return array_key_exists($key, $all) ? $all[$key] : $default;
}

/** บันทึกค่าตั้งระบบ (สร้างตารางให้อัตโนมัติถ้ายังไม่มี) */
function app_setting_set(string $key, string $value): void
{
    $pdo = db();
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS app_settings ('
        . ' skey VARCHAR(64) NOT NULL PRIMARY KEY, sval TEXT NULL, updated_at DATETIME NOT NULL,'
        . ' KEY idx_updated (updated_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
    $stmt = $pdo->prepare(
        'INSERT INTO app_settings (skey, sval, updated_at) VALUES (?, ?, NOW())'
        . ' ON DUPLICATE KEY UPDATE sval = VALUES(sval), updated_at = NOW()'
    );
    $stmt->execute([$key, $value]);
}

/** เมนู "เลื่อนชั้นทั้งโรงเรียน" เปิดให้โรงเรียนใช้ไหม — ผู้ดูแลระบบเปิด/ปิดได้ที่หน้าตั้งค่าระบบ
 *  ค่าเริ่มต้น = ปิด ('0') · ผู้ดูแลระบบ (is_admin) เห็น/ใช้ได้เสมอไม่ว่าค่านี้จะเป็นอะไร */
function promote_menu_enabled(): bool
{
    return (string)app_setting('promote_enabled', '0') === '1';
}

/** รายชื่อผู้ดูแล "ส่วนเพิ่ม" ที่เก็บในฐานข้อมูล (app_settings.admin_smis) — ไม่รวมที่ตายตัวใน config */
function admin_smis_db_list(): array
{
    $json = app_setting('admin_smis');
    if (is_string($json) && $json !== '' && is_array($d = json_decode($json, true))) {
        return array_values(array_unique(array_filter(array_map('strval', $d), 'strlen')));
    }
    return [];
}

/** บันทึกรายชื่อผู้ดูแลส่วนเพิ่ม (DB) — normalize + กรองเฉพาะรูปแบบ SMIS (ตัวเลขไม่เกิน 8 หลัก) */
function admin_smis_db_set(array $list): void
{
    $clean = [];
    foreach ($list as $s) {
        $s = trim((string)$s);
        if ($s !== '' && preg_match('/^\d{1,8}$/', $s)) {
            $clean[$s] = true;
        }
    }
    app_setting_set('admin_smis', json_encode(array_keys($clean), JSON_UNESCAPED_UNICODE));
}

/** รายชื่อ SMIS ผู้ดูแล = bootstrap จาก config (ADMIN_SMIS) + ที่เพิ่มผ่านหน้าจัดการผู้ดูแล (DB) */
function admin_smis_list(): array
{
    $boot = defined('ADMIN_SMIS') ? (array)ADMIN_SMIS : [];
    return array_values(array_unique(array_filter(array_map('strval', array_merge($boot, admin_smis_db_list())), 'strlen')));
}

/** เป็นผู้ดูแลระบบไหม — บัญชี users role=superadmin หรือ SMIS อยู่ในรายชื่อผู้ดูแล (config + DB) */
function is_admin(): bool
{
    if (is_superadmin()) {
        return true;
    }
    $smis = current_smis();
    return $smis !== '' && in_array($smis, admin_smis_list(), true);
}

/** บังคับสิทธิ์ผู้ดูแลระบบ — เรียกบนสุดของหน้า/endpoint ระดับระบบ (หลัง auth.php) */
function require_admin(): void
{
    if (is_admin()) {
        return;
    }
    $isAjax = (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest')
           || str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json');
    if ($isAjax) {
        json_response(['status' => 'error', 'message' => 'ต้องเป็นผู้ดูแลระบบเท่านั้น'], 403);
    }
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    $smis = htmlspecialchars(current_smis());
    echo '<meta charset="utf-8"><div style="font-family:sans-serif;max-width:560px;margin:60px auto;text-align:center">'
       . '<h2>⛔ เฉพาะผู้ดูแลระบบ</h2>'
       . '<p>บัญชีนี้ (SMIS <b>' . $smis . '</b>) ไม่มีสิทธิ์ใช้เครื่องมือผู้ดูแลระบบ</p>'
       . '<p style="color:#888;font-size:.9rem">ให้สิทธิ์โดยเพิ่ม SMIS นี้ใน <code>ADMIN_SMIS</code> ที่ <code>config/config.php</code></p>'
       . '<a href="menu.php">← กลับหน้าหลัก</a></div>';
    exit;
}

/**
 * กันบัญชี "ผู้ดูแลเขต" (saoadmin — ดูอย่างเดียว) ไม่ให้แก้ไขข้อมูลโรงเรียน
 * เรียกบนสุดของ endpoint ที่เขียน/ลบข้อมูล (หลัง auth.php) — บัญชีอื่น (superadmin/school/SMIS) ผ่านได้
 */
function require_editor(): void
{
    if (!is_saoadmin()) {
        return;
    }
    $isAjax = (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest')
           || str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json');
    if ($isAjax) {
        json_response(['status' => 'error', 'message' => 'บัญชีผู้ดูแลเขตดูได้อย่างเดียว แก้ไขข้อมูลไม่ได้'], 403);
    }
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    echo '<meta charset="utf-8"><div style="font-family:sans-serif;max-width:520px;margin:60px auto;text-align:center">'
       . '<h2>👁️ บัญชีผู้ดูแลเขต (ดูอย่างเดียว)</h2>'
       . '<p>บัญชีนี้ดูข้อมูลทุกโรงเรียนในเขตได้ แต่ไม่มีสิทธิ์แก้ไขข้อมูล</p>'
       . '<a href="menu.php">← กลับหน้าหลัก</a></div>';
    exit;
}

/**
 * ตรวจ username/password กับตาราง users — คืนแถว user ถ้าผ่าน (active + รหัสตรง), ไม่งั้น null
 * ทนกรณีตาราง users ยังไม่ถูกสร้าง (คืน null → login.php ไป fallback SMIS โรงเรียน)
 */
function user_authenticate(string $username, string $password, ?PDO $pdo = null): ?array
{
    if ($username === '' || $password === '') {
        return null;
    }
    try {
        $st = ($pdo ?? db())->prepare('SELECT * FROM users WHERE username = ? AND is_active = 1 LIMIT 1');
        $st->execute([$username]);
        $u = $st->fetch();
    } catch (Throwable $e) {
        return null;
    }
    return ($u && password_verify($password, (string)$u['password_hash'])) ? $u : null;
}

/** ดึงข้อมูลนักเรียน "ปีปัจจุบัน" จำกัดเฉพาะโรงเรียนที่ login — คืน null ถ้าไม่พบ/ไม่มีสิทธิ์/ถูกลบ (soft delete) */
function find_student(string $stuid): ?array
{
    $stmt = db()->prepare('SELECT * FROM students WHERE stuid = ? AND sc_id = ? AND years = ? AND deleted_at IS NULL');
    $stmt->execute([$stuid, current_sc_id(), current_year()]);
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
        // db() อยู่นอก try ของ audit_log_event — ถ้า DB ล่มตอน shutdown ต้องเงียบ ไม่โยน exception
        try {
            $pdo = db();
        } catch (Throwable $e) {
            return;
        }
        audit_log_event($pdo, [
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
 * กัน CSV/Excel formula injection ของ 1 เซลล์ ก่อนเขียนผ่าน fputcsv()
 * ค่าที่ขึ้นต้นด้วย = + - @ หรือ tab/CR ถูก Excel/LibreOffice ตีความเป็นสูตร — ใส่ ' นำหน้า
 * ให้กลายเป็นข้อความล้วน (เช็คไบต์แรกพอ: อักขระอันตรายเป็น ASCII ส่วนตัวอักษรไทยขึ้นต้น
 * ด้วยไบต์ ≥ 0x80 จึงไม่ชนกัน). ใช้คู่กับ array_map('csv_safe_cell', $row) ในทุกหน้า export CSV
 */
function csv_safe_cell($v): string
{
    $s = (string)$v;
    return ($s !== '' && strpbrk($s[0], "=+-@\t\r") !== false) ? "'" . $s : $s;
}

/**
 * เทียบคำที่นักเรียน "อ่านออกเสียง" (STT transcript) กับคำเป้าหมาย แบบยืดหยุ่นฝั่ง server
 * ใช้กับงานออกเสียง S001 — ไม่เชื่อ speechConfidence ของ client แต่ตรวจ "ข้อความ" ที่ถอดได้เอง:
 *  • normalize NFC (ไทยมี combining marks ได้หลายรูปแบบ ต้องเทียบบนรูปแบบเดียว)
 *  • ไม่สนวรรณยุกต์ ่ ้ ๊ ๋ (U+0E48..U+0E4B) — STT ไทยมักถอดวรรณยุกต์เพี้ยนแม้ออกเสียงถูก
 *  • รองรับ STT ที่พ่วงคำอื่นมา เช่น "ม้า ค่ะ" → แตกเป็นคำ ๆ แล้วเทียบทีละคำ
 * คืน true เมื่อเสียงที่อ่านตรงกับเป้าหมาย (ตรรกะเดียวกับ assets/js/practice-reader.js ฝั่ง client)
 */
function thai_speech_match(string $said, string $expected): bool
{
    $nfc = static fn (string $s): string =>
        class_exists('Normalizer') ? (Normalizer::normalize($s, Normalizer::FORM_C) ?: $s) : $s;
    // ตัดช่องว่างทั้งหมด + lower (อักษรไทยไม่มี case — เผื่อ STT ถอดเป็นอักษรละติน)
    $norm = static fn (string $s): string => mb_strtolower((string)preg_replace('/\s+/u', '', $nfc($s)));
    // ตัดวรรณยุกต์ U+0E48..U+0E4B
    $stripTone = static fn (string $s): string => (string)preg_replace('/[\x{0E48}-\x{0E4B}]/u', '', $s);

    $target = $norm($expected);
    if ($target === '') { return false; }
    $targetTone = $stripTone($target);

    // ผู้สมัคร: ทั้งสตริง + แต่ละคำที่ STT ถอด (เผื่อมีคำพ่วง เช่น "ม้า ค่ะ")
    $cands = array_merge([$said], preg_split('/\s+/u', trim($said)) ?: []);
    foreach ($cands as $cand) {
        $c = $norm($cand);
        if ($c === '') { continue; }
        if ($c === $target || $stripTone($c) === $targetTone) {
            return true;
        }
    }
    return false;
}

/**
 * Clamp คะแนนที่ client ส่งมาให้อยู่ในช่วง 0..$max แล้ว audit ถ้าถูกตัด (กันคะแนนปลอม)
 * ใช้ร่วมกันใน balloon/bubble/memory submit (เกมที่ client เป็นคนคิดคะแนน) — เลี่ยงโค้ดซ้ำ
 */
function game_clamp_score(PDO $pdo, array $me, string $game, int $rawScore, int $max = 9999): int
{
    $score = max(0, min($max, $rawScore));
    if ($score !== $rawScore) {
        audit_log_event($pdo, [
            'sc_id' => $me['sc_id'], 'stuid' => $me['stuid'], 'action' => 'score_clamped',
            'entity_type' => $game, 'meta_json' => ['raw' => $rawScore, 'clamped' => $score],
        ]);
    }
    return $score;
}

/**
 * เขียน 1 แถวลง game_results (โครงสร้างคอลัมน์เดียวกันทุกเกม) แล้วคืน attemptId
 * ใช้ร่วมกันใน balloon/bubble/memory/hangman/training — class_id ใช้ของผู้เล่นถ้ามี ไม่งั้น fallback เป็น grade
 * ทำงานในทรานแซกชันปัจจุบันของ $pdo ถ้ามี (hangman/training เรียกตอนยัง commit ไม่เสร็จ)
 */
function game_result_save(PDO $pdo, array $me, string $game, int $grade, int $difficulty, int $score, ?array $stats = null): int
{
    $pdo->prepare(
        'INSERT INTO game_results
            (sc_id, stuid, stuname, class_id, game, grade, difficulty, score, stats_json, created_at)
         VALUES (?,?,?,?,?,?,?,?,?,NOW())'
    )->execute([
        $me['sc_id'],
        $me['stuid'],
        $me['stuname'] ?? '',
        (int)($me['class_id'] ?? $grade),
        $game,
        $grade,
        $difficulty,
        $score,
        $stats !== null ? json_encode($stats, JSON_UNESCAPED_UNICODE) : null,
    ]);
    return (int)$pdo->lastInsertId();
}

/**
 * สร้าง WHERE + params สำหรับหน้า "เรื่องที่นักเรียนแต่ง" (scope ด้วย sc_id เสมอ — multi-tenant)
 * ใช้ร่วมกันระหว่าง student_stories.php และ student_stories_export.php ให้ตัวกรองตรงกันเป๊ะ (กัน drift)
 * คืน ['where','params','classId','game','q','stuid'] — ค่าที่ validate แล้วสำหรับเอาไปแสดง UI ด้วย
 */
function story_filter_where(array $get, string $scid): array
{
    $where  = ['sc_id = ?'];
    $params = [$scid];

    $c       = (int)($get['class_id'] ?? 0);
    $classId = ($c >= 1 && $c <= 6) ? $c : 0;             // 0 = ทุกชั้น
    if ($classId) { $where[] = 'class_id = ?'; $params[] = $classId; }

    $game = (string)($get['game'] ?? '');
    if (in_array($game, ['memory', 'balloon', 'bubble', 'hangman', 'training'], true)) {
        $where[] = 'game = ?'; $params[] = $game;
    } else {
        $game = '';                                       // ไม่รู้จัก/ว่าง → ไม่กรองเกม
    }

    $q = trim((string)($get['q'] ?? ''));
    if ($q !== '') { $where[] = '(stuname LIKE ? OR stuid LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }

    $stuid = trim((string)($get['stuid'] ?? ''));
    if ($stuid !== '') { $where[] = 'stuid = ?'; $params[] = $stuid; }

    return ['where' => implode(' AND ', $where), 'params' => $params,
            'classId' => $classId, 'game' => $game, 'q' => $q, 'stuid' => $stuid];
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

    // 3) students (คะแนน + ธงสอบแล้ว ตามรอบ) — เฉพาะแถวปีของรอบสอบนี้
    $pdo->prepare("UPDATE students SET `hit{$hittest}` = ?, `hit{$hittest}tested` = 1 WHERE stuid = ? AND years = ?")
        ->execute([$score, $stuid, $years]);

    // 4) studenthit (denormalize item1..item20) — PK = (stuid, years, hit)
    $itemCols = array_map(fn($i) => "item$i", range(1, WORDS_PER_SET));
    $cols = array_merge(['stuid', 'hit', 'years', 'stuname', 'sc_id', 'class_id', 'rooms', 'stustatus', 'hitscore', 'sethit'], $itemCols);
    $ph   = implode(',', array_fill(0, count($cols), '?'));
    $upd  = implode(', ', array_map(
        fn($c) => "`$c`=VALUES(`$c`)",
        array_merge(['stuname', 'sc_id', 'class_id', 'rooms', 'stustatus', 'hitscore', 'sethit'], $itemCols)
    ));
    $sql = 'INSERT INTO studenthit (' . implode(',', $cols) . ', updatedDate) VALUES (' . $ph . ', NOW())'
         . ' ON DUPLICATE KEY UPDATE ' . $upd . ', updatedDate = NOW()';
    $params = [$stuid, $hittest, $years, $student['stuname'], $student['sc_id'], $student['class_id'],
               $student['rooms'], $student['stustatus'], $score, $sethit];
    for ($i = 1; $i <= WORDS_PER_SET; $i++) {
        $params[] = $cells[$i];
    }
    $pdo->prepare($sql)->execute($params);

    return $score;
}
