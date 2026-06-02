<?php
/**
 * includes/dashboard.php — ชั้น query กลางของ Dashboard "ผลพัฒนาการ"
 *
 * RBAC (สำคัญ): ทุกฟังก์ชันรับ $scid (รหัสโรงเรียนจาก session) และผูก
 * `WHERE sc_id = ?` ไว้ตายตัวเสมอ — school_admin เห็นได้เฉพาะโรงเรียนตัวเอง
 * ห้าม caller ส่ง sc_id จาก $_GET เข้ามา (ให้ส่ง current_sc_id() เท่านั้น)
 *
 * แหล่งข้อมูล (อ้างอิง docs/dashboard-design.md ข้อ 2.2):
 *  - เมตริกปีปัจจุบัน (KPI/รายชั้น/heatmap/at-risk) → ตาราง students (snapshot hit1/2/3)
 *    เพราะผูก sc_id ได้ตรง ๆ (scope ปลอดภัย) และเป็นคะแนนชุดเดียวกับที่ save_hit_result() เขียน
 *  - เทรนด์ข้ามปี → studenteval (32k แถว) join students เพื่อ scope ด้วย sc_id
 *    (studenteval.stuid เป็น bigint, students.stuid เป็น varchar → ต้อง CAST ตอน join)
 */
require_once __DIR__ . '/functions.php';     // db() + find_student() (RBAC: scope ด้วย sc_id)

/** ตรวจรอบ Hit ให้อยู่ใน 1..3 (กันค่าหลุดมาจาก query string) */
function dash_valid_hit(int $hit): int
{
    return in_array($hit, HITTESTS, true) ? $hit : 1;
}

/**
 * KPI ภาพรวมสำหรับรอบที่เลือก (ปีปัจจุบัน, จาก snapshot students)
 * scope: ทั้งโรงเรียน หรือเฉพาะชั้น (ถ้าส่ง $classid)
 * คืน: total, tested, score_sum, passed  (เกณฑ์ผ่าน = PASS_SCORE)
 */
function dash_overview(string $scid, int $hit, ?int $classid = null): array
{
    $hit  = dash_valid_hit($hit);
    $col  = "hit{$hit}";          // 1..3 ผ่าน validate แล้ว — ปลอดภัยที่จะแทรกชื่อคอลัมน์
    $tcol = "hit{$hit}tested";
    $p    = (int)PASS_SCORE;
    $sql  = "SELECT
                COUNT(*)                                                       AS total,
                COALESCE(SUM($tcol),0)                                         AS tested,
                COALESCE(SUM(CASE WHEN $tcol=1 THEN $col END),0)               AS score_sum,
                COALESCE(SUM(CASE WHEN $tcol=1 AND $col>=$p THEN 1 ELSE 0 END),0) AS passed
             FROM students
             WHERE sc_id = ?";
    $params = [$scid];
    if ($classid !== null) {                 // scope แคบลงระดับชั้น (ยังผูก sc_id อยู่)
        $sql .= " AND class_id = ?";
        $params[] = $classid;
    }
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->fetch() ?: ['total' => 0, 'tested' => 0, 'score_sum' => 0, 'passed' => 0];
}

/**
 * สรุปรายชั้น × รอบ (จำนวน/เฉลี่ย/ผ่าน) ของโรงเรียน — ใช้ทำ grouped bar + heatmap + ตาราง rank
 * คืน 1 แถวต่อชั้น (ป.1–ป.6) แม้ไม่มีนักเรียนก็คืนแถว (LEFT JOIN class)
 */
function dash_by_class(string $scid): array
{
    $p   = (int)PASS_SCORE;
    $sql = "SELECT c.class_id, c.classname,
                COUNT(s.stuid) AS n,
                COALESCE(SUM(s.hit1tested),0) AS h1t, ROUND(AVG(CASE WHEN s.hit1tested=1 THEN s.hit1 END),2) AS h1avg, COALESCE(SUM(CASE WHEN s.hit1tested=1 AND s.hit1>=$p THEN 1 ELSE 0 END),0) AS h1pass,
                COALESCE(SUM(s.hit2tested),0) AS h2t, ROUND(AVG(CASE WHEN s.hit2tested=1 THEN s.hit2 END),2) AS h2avg, COALESCE(SUM(CASE WHEN s.hit2tested=1 AND s.hit2>=$p THEN 1 ELSE 0 END),0) AS h2pass,
                COALESCE(SUM(s.hit3tested),0) AS h3t, ROUND(AVG(CASE WHEN s.hit3tested=1 THEN s.hit3 END),2) AS h3avg, COALESCE(SUM(CASE WHEN s.hit3tested=1 AND s.hit3>=$p THEN 1 ELSE 0 END),0) AS h3pass
             FROM class c
             LEFT JOIN students s ON s.class_id = c.class_id AND s.sc_id = ?
             GROUP BY c.class_id, c.classname
             ORDER BY c.class_id";
    $st = db()->prepare($sql);
    $st->execute([$scid]);
    return $st->fetchAll();
}

/**
 * เทรนด์คะแนนเฉลี่ยข้ามปี (พ.ศ.) ของนักเรียนปัจจุบันในโรงเรียน — จาก studenteval
 * scope ด้วย sc_id ผ่าน join students (CAST stuid)
 * คืน: [ ['years'=>2567,'avg_score'=>14.3,'n'=>..], ... ] เรียงตามปี
 */
function dash_year_trend(string $scid): array
{
    $sql = "SELECT se.years AS years, ROUND(AVG(se.score),2) AS avg_score, COUNT(*) AS n
            FROM studenteval se
            JOIN students s ON CAST(s.stuid AS UNSIGNED) = se.stuid
            WHERE s.sc_id = ?
            GROUP BY se.years
            ORDER BY se.years";
    $st = db()->prepare($sql);
    $st->execute([$scid]);
    return $st->fetchAll();
}

/** %ผ่านของชั้น ในรอบที่เลือก (ปลอดหารศูนย์) — ใช้จัดอันดับชั้นที่ต้องเร่ง */
function dash_class_pass_pct(array $row, int $hit): ?float
{
    $hit = dash_valid_hit($hit);
    $tested = (int)$row["h{$hit}t"];
    if ($tested === 0) {
        return null;                 // ยังไม่มีใครสอบรอบนี้ → ไม่จัดอันดับ
    }
    return round((int)$row["h{$hit}pass"] / $tested * 100, 1);
}

/* ==================== Phase 2: ระดับชั้น + รายบุคคล ==================== */

/** ตรวจ class_id ให้อยู่ใน 1..6 (กันค่าหลุดจาก URL); คืน 0 ถ้าไม่ถูกต้อง */
function dash_valid_class(int $classid): int
{
    return ($classid >= 1 && $classid <= 6) ? $classid : 0;
}

/** ชื่อชั้นจาก class_id (เช่น "ป.1") — null ถ้าไม่พบ */
function dash_class_name(int $classid): ?string
{
    $st = db()->prepare('SELECT classname FROM class WHERE class_id = ?');
    $st->execute([$classid]);
    $r = $st->fetch();
    return $r ? $r['classname'] : null;
}

/**
 * รายชื่อนักเรียนในชั้นของโรงเรียน (snapshot) — ใช้ทำ histogram/at-risk/drill
 * RBAC: ผูก sc_id เสมอ → คืนเฉพาะนักเรียนในโรงเรียนของ session
 */
function dash_class_students(string $scid, int $classid): array
{
    $st = db()->prepare(
        'SELECT stuid, stuname, rooms, stustatus,
                hit1, hit1tested, hit2, hit2tested, hit3, hit3tested
         FROM students
         WHERE sc_id = ? AND class_id = ?
         ORDER BY stuname'
    );
    $st->execute([$scid, $classid]);
    return $st->fetchAll();
}

/** การกระจายคะแนน (histogram) ของรายชื่อที่ส่งมา สำหรับรอบที่เลือก — 4 ช่วง */
function dash_score_histogram(array $students, int $hit): array
{
    $hit = dash_valid_hit($hit);
    $buckets = ['0-5' => 0, '6-10' => 0, '11-15' => 0, '16-20' => 0];
    foreach ($students as $s) {
        if ((int)$s["hit{$hit}tested"] !== 1) {
            continue;
        }
        $v = (int)$s["hit{$hit}"];
        if ($v <= 5)        { $buckets['0-5']++; }
        elseif ($v <= 10)   { $buckets['6-10']++; }
        elseif ($v <= 15)   { $buckets['11-15']++; }
        else                { $buckets['16-20']++; }
    }
    return $buckets;
}

/**
 * ดึงนักเรียน 1 คน — RBAC/anti-IDOR: ผูก sc_id ของ session → คืน null ถ้าไม่ใช่เด็กในโรงเรียนตน
 * (ห่อ find_student() ที่มีอยู่ใน functions.php เพื่อความชัดเจนของ intent)
 */
function dash_find_student(string $stuid): ?array
{
    return find_student($stuid);     // find_student มี WHERE stuid=? AND sc_id=current_sc_id()
}

/** snapshot นักเรียน 1 คน scope ด้วย sc_id (ใช้ในหน้านักเรียน my_dashboard ที่ไม่มี admin session) */
function dash_student_snapshot(string $scid, string $stuid): ?array
{
    $st = db()->prepare('SELECT * FROM students WHERE stuid = ? AND sc_id = ?');
    $st->execute([$stuid, $scid]);
    return $st->fetch() ?: null;
}

/**
 * เทรนด์คะแนนรายบุคคลข้ามปี/รอบ จาก studenteval
 * defense-in-depth: join students + ผูก sc_id ด้วย (นอกเหนือจากที่ caller verify มาแล้ว)
 * คืน: [ ['years'=>, 'hittest'=>, 'score'=>], ... ] เรียงตามปีแล้วรอบ
 */
function dash_student_trend(string $scid, string $stuid): array
{
    $st = db()->prepare(
        'SELECT se.years AS years, se.hittest AS hittest, se.score AS score
         FROM studenteval se
         JOIN students s ON CAST(s.stuid AS UNSIGNED) = se.stuid
         WHERE s.sc_id = ? AND s.stuid = ?
         ORDER BY se.years, se.hittest'
    );
    $st->execute([$scid, $stuid]);
    return $st->fetchAll();
}

/**
 * ประวัติการเล่นเกมล่าสุดของนักเรียน จาก game_results
 * RBAC: ผูก stuid + sc_id ผ่าน join students — คืนเฉพาะข้อมูลตัวเอง
 * คืน: [ {id, game, score, grade, difficulty, stats_json, created_at}, ... ]
 */
function dash_game_history(string $stuid, int $limit = 15): array
{
    $st = db()->prepare(
        'SELECT id, game, score, grade, difficulty, stats_json, created_at
         FROM game_results
         WHERE stuid = ?
         ORDER BY created_at DESC
         LIMIT ?'
    );
    $st->execute([$stuid, $limit]);
    return $st->fetchAll();
}

/**
 * สรุปสถิติเกมรวม (เล่นทั้งหมดกี่ครั้ง, คะแนนสูงสุด/เฉลี่ยแต่ละเกม)
 */
function dash_game_summary(string $stuid): array
{
    $st = db()->prepare(
        'SELECT game,
                COUNT(*) AS plays,
                MAX(score) AS best,
                ROUND(AVG(score), 0) AS avg_score
         FROM game_results
         WHERE stuid = ?
         GROUP BY game'
    );
    $st->execute([$stuid]);
    $rows = $st->fetchAll();
    $map  = [];
    foreach ($rows as $r) {
        $map[$r['game']] = $r;
    }
    return $map;
}

/**
 * หมวดคำที่นักเรียนอ่อน (จาก per-word evaluations) — รอบ/ปีที่เลือก
 * mapping: words.indicator = word_category.catid (ยืนยันแล้ว — design doc ข้อ 1.5)
 * หมายเหตุ: evaluations แทบว่างในระบบจริงตอนนี้ → มักคืน [] (หน้า UI จะแจ้งผู้ใช้)
 */
function dash_student_weak_categories(string $stuid, int $years, int $hit): array
{
    $hit = dash_valid_hit($hit);
    $st = db()->prepare(
        'SELECT wc.catid AS catid, wc.category AS category,
                SUM(e.correct) AS correct, COUNT(*) AS total,
                ROUND(SUM(e.correct)/COUNT(*)*100, 1) AS pct
         FROM evaluations e
         JOIN words w          ON w.id = e.word_id
         JOIN word_category wc ON wc.catid = w.indicator
         WHERE e.stuid = ? AND e.years = ? AND e.hittest = ?
         GROUP BY wc.catid, wc.category
         ORDER BY pct ASC, total DESC'
    );
    $st->execute([$stuid, $years, $hit]);
    return $st->fetchAll();
}
