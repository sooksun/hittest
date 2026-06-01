<?php
/**
 * my_dashboard.php — Phase 3: แดชบอร์ดของนักเรียน (ดูพัฒนาการตัวเองเท่านั้น)
 * อ้างอิง docs/dashboard-design.md ข้อ 3.1 + 4.5.6
 *
 * Strict scope: ใช้ stuid/sc_id จาก session นักเรียนเท่านั้น — ไม่รับจาก $_GET
 *   ถ้ามี ?stuid= และไม่ตรงกับ session → 403 (กันสอดส่องเพื่อน)
 *   หน้านี้สำหรับ role=student เท่านั้น (require_student) — ครูใช้ dashboard_student.php
 */
session_start();
require __DIR__ . '/includes/student_auth.php';
require __DIR__ . '/includes/dashboard.php';

$me = require_student();              // role=student เท่านั้น ไม่ใช่ → เด้งไป student_login

// anti-snooping: ถ้าพยายามใส่ stuid คนอื่นใน URL → 403
if (isset($_GET['stuid']) && (string)$_GET['stuid'] !== (string)$me['stuid']) {
    http_response_code(403);
    exit('<meta charset="utf-8"><div style="font-family:sans-serif;text-align:center;margin-top:60px">'
        . '<h2>⛔ ดูได้เฉพาะผลของตัวเองเท่านั้น</h2><a href="my_dashboard.php">→ กลับแดชบอร์ดของฉัน</a></div>');
}

$scid  = (string)$me['sc_id'];
$stuid = (string)$me['stuid'];
$stu   = dash_student_snapshot($scid, $stuid);
if (!$stu) {
    http_response_code(404);
    exit('<meta charset="utf-8"><div style="font-family:sans-serif;text-align:center;margin-top:60px"><h2>ไม่พบข้อมูลนักเรียน</h2></div>');
}

$classid   = (int)$stu['class_id'];
$className = dash_class_name($classid) ?? ('ป.' . $classid);
$hit       = dash_valid_hit((int)($_GET['hit'] ?? 1));
$pass      = (int)PASS_SCORE;

$hits = [];
foreach (HITTESTS as $h) {
    $hits[$h] = ['tested' => (int)$stu["hit{$h}tested"] === 1, 'score' => (int)$stu["hit{$h}"]];
}
$delta = null;
if ($hit > 1 && $hits[$hit]['tested'] && $hits[$hit - 1]['tested']) {
    $delta = $hits[$hit]['score'] - $hits[$hit - 1]['score'];
}

// เทียบค่าเฉลี่ย ชั้น/โรงเรียน (รอบที่เลือก)
$clsOv  = dash_overview($scid, $hit, $classid);
$schOv  = dash_overview($scid, $hit);
$clsAvg = (int)$clsOv['tested'] ? round((int)$clsOv['score_sum'] / (int)$clsOv['tested'], 2) : 0;
$schAvg = (int)$schOv['tested'] ? round((int)$schOv['score_sum'] / (int)$schOv['tested'], 2) : 0;
$myScore = $hits[$hit]['tested'] ? $hits[$hit]['score'] : null;

$trend = dash_student_trend($scid, $stuid);
$trendLabels = array_map(fn($t) => 'Hit-' . $t['hittest'] . ' (' . $t['years'] . ')', $trend);
$trendData   = array_map(fn($t) => (int)$t['score'], $trend);
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>แดชบอร์ดของฉัน — HIT-TEST</title>
    <link rel="icon" href="images/logohittest.png" type="image/png">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/theme.css?v=<?= @filemtime(__DIR__ . '/assets/css/theme.css') ?: '1' ?>" rel="stylesheet">
    <style>
        body { background: linear-gradient(160deg,#EAF2FF,#F7F3FF); min-height:100vh }
        .stu-top { background:#fff; box-shadow:var(--sh-sm,0 2px 10px rgba(0,0,0,.06)); }
        .stu-top__in { max-width:1100px; margin:0 auto; padding:14px 22px; display:flex; align-items:center; gap:12px; flex-wrap:wrap }
        .stu-wrap { max-width:1100px; margin:0 auto; padding:26px 22px }
        .dash-chartbox { background:#fff; border-radius:var(--r-lg); padding:18px 20px; box-shadow:0 2px 10px rgba(0,0,0,.05) }
    </style>
</head>
<body>
<div class="stu-top"><div class="stu-top__in">
    <img src="images/logohittest.png" alt="HIT-TEST" style="height:40px;width:40px;object-fit:contain;display:block">
    <strong style="font-size:1.05rem">🧒 <?= htmlspecialchars($stu['stuname']) ?></strong>
    <span class="ht-badge t-blue"><?= htmlspecialchars($className) ?></span>
    <div class="ht-row" style="gap:8px;margin-left:auto;flex-wrap:wrap;align-items:center">
        <form method="get" class="ht-row" style="gap:8px;margin:0">
            <label class="ht-label mb-0">รอบสอบ</label>
            <select name="hit" class="ht-select" style="width:auto" onchange="this.form.submit()">
                <?php foreach (HITTESTS as $h): ?><option value="<?= $h ?>" <?= $h === $hit ? 'selected' : '' ?>>Hit-<?= $h ?></option><?php endforeach; ?>
            </select>
        </form>
        <a href="my_practice.php?class_id=<?= $classid ?>&hittest=<?= $hit ?>&sethit=1" class="ht-btn ht-btn--green ht-btn--sm">▶️ ไปฝึกอ่าน</a>
        <a href="logout.php" class="ht-btn ht-btn--ghost ht-btn--sm" style="color:var(--c-coral-ink)">ออกจากระบบ</a>
    </div>
</div></div>

<div class="stu-wrap">
    <h1 style="font-size:1.7rem" class="mb-1">พัฒนาการการอ่านของฉัน 🌟</h1>
    <p class="text-muted mb-4">ปีการศึกษา <?= ACADEMIC_YEAR ?> · เกณฑ์ผ่าน <?= $pass ?>/<?= WORDS_PER_SET ?> คะแนน</p>

    <!-- คะแนนรายรอบ -->
    <div class="row g-4 mb-4">
        <?php foreach (HITTESTS as $h): $d = $hits[$h]; ?>
            <div class="col-6 col-lg-3">
                <div class="ht-stat <?= $d['tested'] ? ($d['score'] >= $pass ? 't-green' : 't-coral') : 't-blue' ?>">
                    <div class="ht-stat__icon"><?= ['', '📕', '📗', '📘'][$h] ?></div>
                    <div class="ht-stat__value"><?= $d['tested'] ? $d['score'] . '<span style="font-size:.5em;color:var(--ink-soft)">/' . WORDS_PER_SET . '</span>' : '–' ?></div>
                    <div class="ht-stat__label">Hit-<?= $h ?> <?= $d['tested'] ? ($d['score'] >= $pass ? '· ✅ ผ่าน' : '· สู้ ๆ นะ') : '· ยังไม่สอบ' ?></div>
                </div>
            </div>
        <?php endforeach; ?>
        <div class="col-6 col-lg-3">
            <div class="ht-stat t-purple">
                <div class="ht-stat__icon">📈</div>
                <div class="ht-stat__value"><?= $delta === null ? '–' : ($delta > 0 ? '+' . $delta : $delta) ?></div>
                <div class="ht-stat__label">พัฒนาการ Hit-<?= $hit ?></div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-7"><div class="dash-chartbox h-100">
            <h3 class="mb-3">พัฒนาการของฉัน</h3>
            <?php if ($trend): ?><canvas id="chartTrend" height="200"></canvas>
            <?php else: ?><p class="text-muted" style="padding:30px 0">ยังไม่มีประวัติคะแนน — สอบรอบแรกแล้วจะเห็นกราฟที่นี่!</p><?php endif; ?>
        </div></div>
        <div class="col-lg-5"><div class="dash-chartbox h-100">
            <h3 class="mb-3">เทียบกับเพื่อน ๆ (Hit-<?= $hit ?>)</h3>
            <?php if ($myScore !== null): ?><canvas id="chartCompare" height="200"></canvas>
            <?php else: ?><p class="text-muted" style="padding:30px 0">ยังไม่ได้สอบรอบนี้</p><?php endif; ?>
        </div></div>
    </div>

    <div class="dash-chartbox">
        <h3 class="mb-2">อยากเก่งขึ้น? มาฝึกอ่านกัน 🗣️</h3>
        <p class="text-muted mb-3">ฝึกอ่านคำในชุดของ <?= htmlspecialchars($className) ?> แล้วกลับมาดูคะแนนใหม่ได้เลย</p>
        <a class="ht-btn ht-btn--lg" href="my_practice.php?class_id=<?= $classid ?>&hittest=<?= $hit ?>&sethit=1">▶️ ไปฝึกอ่าน</a>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
(function () {
    'use strict';
    var D = <?= json_encode([
        'trendLabels' => $trendLabels, 'trendData' => $trendData,
        'cmp' => [$myScore, $clsAvg, $schAvg], 'full' => WORDS_PER_SET,
    ], JSON_UNESCAPED_UNICODE) ?>;
    if (typeof Chart === 'undefined') { return; }
    Chart.defaults.font.family = 'Sarabun, sans-serif';
    var t = document.getElementById('chartTrend');
    if (t && D.trendData.length) {
        new Chart(t, { type: 'line',
            data: { labels: D.trendLabels, datasets: [{ label: 'คะแนน', data: D.trendData,
                borderColor: '#9D7BFF', backgroundColor: 'rgba(157,123,255,.15)', fill: true, tension: .3, pointRadius: 5 }] },
            options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, max: D.full } } } });
    }
    var c = document.getElementById('chartCompare');
    if (c) {
        new Chart(c, { type: 'bar',
            data: { labels: ['ฉัน', 'เฉลี่ยชั้น', 'เฉลี่ยโรงเรียน'], datasets: [{ data: D.cmp,
                backgroundColor: ['#4D96FF', '#6BCB77', '#FFD93D'] }] },
            options: { indexAxis: 'y', responsive: true, plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, max: D.full } } } });
    }
})();
</script>
</body>
</html>
