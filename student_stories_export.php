<?php
/**
 * student_stories_export.php — ส่งออก "เรื่องที่นักเรียนแต่ง" เป็น CSV (เปิดใน Excel ได้)
 * ใช้ตัวกรองชุดเดียวกับ student_stories.php — scope ด้วย sc_id ของ session เท่านั้น
 *
 * RBAC: auth.php (school admin). UTF-8 BOM กันภาษาไทยเพี้ยนใน Excel
 */
require __DIR__ . '/includes/auth.php';

$scid = current_sc_id();
$pdo  = db();

$gameNames = [
    'memory'   => 'สลับคำ จำให้แม่น',
    'balloon'  => 'ลูกโป่งหรรษา',
    'bubble'   => 'ยิงแม่น แขวนคำ',
    'hangman'  => 'ไทยคำ จำแม่น',
    'training' => 'ฝึกอ่าน',
];

// ---- Filters (ใช้ helper กลางตัวเดียวกับ student_stories.php → ตัวกรองตรงกันเป๊ะ ไม่ drift) ----
$f        = story_filter_where($_GET, $scid);
$whereSql = $f['where'];
$params   = $f['params'];

$stmt = $pdo->prepare(
    "SELECT stuid, stuname, class_id, game, grade, difficulty,
            words_json, story_text, char_count, used_all, created_at
     FROM game_stories WHERE $whereSql
     ORDER BY class_id, stuname, created_at DESC"
);
$stmt->execute($params);

// ---- CSV output ----
$fname = 'student_stories_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $fname . '"');
header('Cache-Control: no-store');

echo "\xEF\xBB\xBF"; // UTF-8 BOM → Excel แสดงภาษาไทยถูก
$out = fopen('php://output', 'w');

// ทุกเซลล์ส่งผ่าน csv_safe_cell() (includes/functions.php) กัน CSV/Excel formula injection
fputcsv($out, ['วันที่', 'ชื่อนักเรียน', 'รหัสนักเรียน', 'ชั้น', 'เกม', 'ใช้คำครบ', 'จำนวนตัวอักษร', 'คำที่ให้', 'เรื่องที่แต่ง']);

while ($r = $stmt->fetch()) {
    $dateObj = new DateTime($r['created_at']);
    $dateStr = $dateObj->format('d/m/') . ((int)$dateObj->format('Y') + 543) . ' ' . $dateObj->format('H:i');

    $wj    = json_decode($r['words_json'] ?? '{}', true) ?: [];
    $words = is_array($wj['words'] ?? null) ? implode(', ', $wj['words']) : '';

    fputcsv($out, array_map('csv_safe_cell', [
        $dateStr,
        $r['stuname'] ?: $r['stuid'],
        $r['stuid'],
        'ป.' . (int)$r['class_id'],
        $gameNames[$r['game']] ?? $r['game'],
        ((int)$r['used_all'] === 1) ? 'ครบ' : 'ไม่ครบ',
        (int)$r['char_count'],
        $words,
        $r['story_text'],
    ]));
}

fclose($out);
exit;
