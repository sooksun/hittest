<?php
/**
 * dashboard_student.php — Dashboard "ผลพัฒนาการการอ่าน" รายบุคคล (Phase 2)
 * อ้างอิง docs/dashboard-design.md ข้อ 3.1
 *
 * RBAC / anti-IDOR (สำคัญ): ดึงนักเรียนผ่าน dash_find_student() ซึ่งผูก
 *   `WHERE stuid=? AND sc_id=current_sc_id()` → ถ้า stuid ไม่ใช่เด็กในโรงเรียนของ session
 *   จะได้ null แล้วตอบ 403 ทันที (เปลี่ยน stuid ใน URL เป็นเด็กโรงเรียนอื่นไม่ได้)
 *
 * Filter: รอบ Hit (?hit=) สำหรับกราฟเทียบค่าเฉลี่ย + หมวดคำที่อ่อน
 */
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/dashboard.php';

$scid = current_sc_id();
$stuid = (string)($_GET['stuid'] ?? '');
$stu = $stuid !== '' ? dash_find_student($stuid) : null;

if (!$stu) {
    // anti-IDOR: ไม่พบ หรือไม่ใช่เด็กในโรงเรียนของ session
    http_response_code(403);
    $page_title = 'ไม่พบนักเรียน';
    $active = 'analytics';
    require __DIR__ . '/includes/header.php';
    echo '<div class="ht-card" style="border-left:6px solid var(--c-coral);max-width:560px">'
       . '<h3 class="mb-2">⛔ ไม่พบนักเรียน หรือไม่มีสิทธิ์เข้าถึง</h3>'
       . '<p class="text-muted mb-3">นักเรียนรายนี้ไม่ได้อยู่ในโรงเรียนของคุณ — ดูได้เฉพาะนักเรียนในโรงเรียนตนเองเท่านั้น</p>'
       . '<a href="dashboard_school.php" class="ht-btn ht-btn--sm">← กลับภาพรวมโรงเรียน</a></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$classid   = (int)$stu['class_id'];
$className = dash_class_name($classid) ?? ('ป.' . $classid);
$hit       = dash_valid_hit((int)($_GET['hit'] ?? 1));
$pass      = (int)PASS_SCORE;

// คะแนนรายรอบ (snapshot)
$hits = [];
foreach (HITTESTS as $h) {
    $hits[$h] = [
        'tested' => (int)$stu["hit{$h}tested"] === 1,
        'score'  => (int)$stu["hit{$h}"],
    ];
}
// Δ ของรอบที่เลือก (เทียบรอบก่อนหน้า)
$delta = null;
if ($hit > 1 && $hits[$hit]['tested'] && $hits[$hit - 1]['tested']) {
    $delta = $hits[$hit]['score'] - $hits[$hit - 1]['score'];
}

// เทียบค่าเฉลี่ย: ฉัน vs ชั้น vs โรงเรียน (รอบที่เลือก)
$clsOv = dash_overview($scid, $hit, $classid);
$schOv = dash_overview($scid, $hit);
$clsAvg = (int)$clsOv['tested'] ? round((int)$clsOv['score_sum'] / (int)$clsOv['tested'], 2) : 0;
$schAvg = (int)$schOv['tested'] ? round((int)$schOv['score_sum'] / (int)$schOv['tested'], 2) : 0;
$myScore = $hits[$hit]['tested'] ? $hits[$hit]['score'] : null;

// เทรนด์ + หมวดอ่อน
$trend = dash_student_trend($scid, $stuid);
$weak  = dash_student_weak_categories($stuid, ACADEMIC_YEAR, $hit);

$trendLabels = array_map(fn($t) => 'Hit-' . $t['hittest'] . ' (' . $t['years'] . ')', $trend);
$trendData   = array_map(fn($t) => (int)$t['score'], $trend);

$page_title = 'ผลพัฒนาการ — ' . $stu['stuname'];
$active = 'analytics';
require __DIR__ . '/includes/header.php';
?>
<style>.ht-container{max-width:1200px}
.dash-chartbox{background:#fff;border-radius:var(--r-lg);padding:18px 20px;box-shadow:0 2px 10px rgba(0,0,0,.05)}</style>

<div class="ht-row mb-2" style="gap:12px;align-items:center;flex-wrap:wrap">
    <a href="dashboard_class.php?class_id=<?= $classid ?>" class="ht-btn ht-btn--ghost ht-btn--sm">← <?= htmlspecialchars($className) ?></a>
    <h2 class="mb-0">👦 <?= htmlspecialchars($stu['stuname']) ?></h2>
    <span class="ht-badge t-blue"><?= htmlspecialchars($className) ?> · ห้อง <?= (int)$stu['rooms'] ?></span>
    <span class="ht-badge <?= (int)$stu['stustatus'] === 2 ? 'ht-badge--special' : 'ht-badge--missing' ?>"><?= htmlspecialchars(status_name((int)$stu['stustatus'])) ?></span>
    <form method="get" class="ht-row" style="gap:8px;margin-left:auto">
        <input type="hidden" name="stuid" value="<?= htmlspecialchars($stuid, ENT_QUOTES) ?>">
        <label class="ht-label mb-0">รอบสอบ</label>
        <select name="hit" class="ht-select" style="width:auto" onchange="this.form.submit()">
            <?php foreach (HITTESTS as $h): ?><option value="<?= $h ?>" <?= $h === $hit ? 'selected' : '' ?>>Hit-<?= $h ?></option><?php endforeach; ?>
        </select>
    </form>
</div>
<p class="text-muted mb-4">รหัสนักเรียน <?= htmlspecialchars($stu['stuid']) ?> · ปีการศึกษา <?= ACADEMIC_YEAR ?> · เกณฑ์ผ่าน <?= $pass ?>/<?= WORDS_PER_SET ?></p>

<!-- คะแนนรายรอบ + พัฒนาการ -->
<div class="row g-4 mb-5">
    <?php foreach (HITTESTS as $h): $d = $hits[$h]; ?>
        <div class="col-6 col-lg-3">
            <div class="ht-stat <?= $d['tested'] ? ($d['score'] >= $pass ? 't-green' : 't-coral') : 't-blue' ?>">
                <div class="ht-stat__icon"><?= ['', '📕', '📗', '📘'][$h] ?></div>
                <div class="ht-stat__value"><?= $d['tested'] ? $d['score'] . '<span style="font-size:.5em;color:var(--ink-soft)">/' . WORDS_PER_SET . '</span>' : '–' ?></div>
                <div class="ht-stat__label">Hit-<?= $h ?> <?= $d['tested'] ? ($d['score'] >= $pass ? '· ✅ ผ่าน' : '· ⚠️ ไม่ผ่าน') : '· ยังไม่สอบ' ?></div>
            </div>
        </div>
    <?php endforeach; ?>
    <div class="col-6 col-lg-3">
        <div class="ht-stat t-purple">
            <div class="ht-stat__icon">📈</div>
            <div class="ht-stat__value"><?php
                if ($delta === null) { echo '–'; }
                elseif ($delta > 0) { echo '+' . $delta; }
                else { echo $delta; }
            ?></div>
            <div class="ht-stat__label">พัฒนาการ Hit-<?= $hit ?> (เทียบรอบก่อน)</div>
        </div>
    </div>
</div>

<div class="row g-4 mb-5">
    <!-- Line trend -->
    <div class="col-lg-7"><div class="dash-chartbox h-100">
        <h3 class="mb-3">พัฒนาการรายรอบ/รายปี</h3>
        <?php if ($trend): ?><canvas id="chartTrend" height="200"></canvas>
        <?php else: ?><p class="text-muted" style="padding:30px 0">ยังไม่มีประวัติคะแนนย้อนหลัง (studenteval)</p><?php endif; ?>
    </div></div>
    <!-- เทียบค่าเฉลี่ย -->
    <div class="col-lg-5"><div class="dash-chartbox h-100">
        <h3 class="mb-3">เทียบกับค่าเฉลี่ย (Hit-<?= $hit ?>)</h3>
        <?php if ($myScore !== null): ?><canvas id="chartCompare" height="200"></canvas>
        <?php else: ?><p class="text-muted" style="padding:30px 0">นักเรียนยังไม่ได้สอบรอบนี้</p><?php endif; ?>
    </div></div>
</div>

<!-- หมวดคำที่ต้องฝึกเพิ่ม -->
<div class="dash-chartbox mb-5">
    <h3 class="mb-3">หมวดคำที่ต้องฝึกเพิ่ม (Hit-<?= $hit ?>)</h3>
    <?php if ($weak): ?>
        <table class="ht-table" style="width:100%">
            <thead><tr><th>หมวดคำ</th><th class="text-center">อ่านถูก</th><th>% ถูก</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($weak as $w): $p2 = (float)$w['pct']; ?>
                <tr>
                    <td class="fw-7"><?= htmlspecialchars($w['category']) ?></td>
                    <td class="text-center text-muted"><?= (int)$w['correct'] ?>/<?= (int)$w['total'] ?></td>
                    <td style="min-width:160px">
                        <div class="ht-progress"><div style="height:100%;width:<?= $p2 ?>%;background:<?= $p2 < 50 ? '#FF6B6B' : ($p2 < 70 ? '#FFD93D' : '#6BCB77') ?>;border-radius:var(--r-pill)"></div></div>
                        <span style="font-size:.85rem;font-weight:700"><?= $p2 ?>%</span>
                    </td>
                    <td class="text-end"><a class="ht-btn ht-btn--ghost ht-btn--sm" href="practice.php?class_id=<?= $classid ?>&hittest=<?= $hit ?>&sethit=1">🗣️ ฝึกอ่าน</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p class="text-muted mb-2">ยังไม่มีข้อมูลผลรายคำ (per-word) ของนักเรียนรายนี้ในรอบ/ปีนี้</p>
        <p class="text-muted mb-3" style="font-size:.9rem">ℹ️ ส่วนนี้จะแสดงเมื่อระบบเริ่มเก็บผลรายคำ (`evaluations`) ครบ — mapping หมวดคำพร้อมแล้ว (design doc ข้อ 1.5/Phase 4)</p>
        <a class="ht-btn ht-btn--sm" href="practice.php?class_id=<?= $classid ?>&hittest=<?= $hit ?>&sethit=1">🗣️ ไปฝึกอ่านชุดของชั้นนี้</a>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
(function () {
    'use strict';
    var D = <?= json_encode([
        'trendLabels' => $trendLabels,
        'trendData'   => $trendData,
        'cmp'         => [$myScore, $clsAvg, $schAvg],
        'full'        => WORDS_PER_SET,
        'pass'        => $pass,
    ], JSON_UNESCAPED_UNICODE) ?>;
    if (typeof Chart === 'undefined') { return; }
    Chart.defaults.font.family = 'Sarabun, sans-serif';

    var t = document.getElementById('chartTrend');
    if (t && D.trendData.length) {
        new Chart(t, {
            type: 'line',
            data: { labels: D.trendLabels, datasets: [{ label: 'คะแนน', data: D.trendData,
                borderColor: '#9D7BFF', backgroundColor: 'rgba(157,123,255,.15)', fill: true, tension: .3, pointRadius: 5 }] },
            options: { responsive: true, plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, max: D.full } } }
        });
    }
    var c = document.getElementById('chartCompare');
    if (c) {
        new Chart(c, {
            type: 'bar',
            data: { labels: ['ฉัน', 'ค่าเฉลี่ยชั้น', 'ค่าเฉลี่ยโรงเรียน'], datasets: [{ label: 'คะแนน (Hit)',
                data: D.cmp, backgroundColor: ['#4D96FF', '#6BCB77', '#FFD93D'] }] },
            options: { indexAxis: 'y', responsive: true, plugins: { legend: { display: false } },
                scales: { x: { beginAtZero: true, max: D.full } } }
        });
    }
})();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
