<?php
/**
 * dashboard_school.php — Dashboard "ผลพัฒนาการการอ่าน" ระดับโรงเรียน (Phase 1)
 * อ้างอิง docs/dashboard-design.md ข้อ 3.3
 *
 * RBAC: school_admin — เห็นเฉพาะโรงเรียนของตน (scope ด้วย current_sc_id() ทุก query)
 *   ผ่านชั้น query กลางใน includes/dashboard.php — หน้านี้ไม่รับ sc_id จาก $_GET เลย
 *
 * Filter: รอบ Hit (1–3) ผ่าน ?hit=  · ปีที่แสดงเมตริก = ปีการศึกษาปัจจุบัน (ACADEMIC_YEAR)
 *   เทรนด์ข้ามปีดึงทุกปีจาก studenteval
 */
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/dashboard.php';

$scid = current_sc_id();
$hit  = dash_valid_hit((int)($_GET['hit'] ?? 1));
$pass = (int)PASS_SCORE;

$ov      = dash_overview($scid, $hit);
$classes = dash_by_class($scid);
$trend   = dash_year_trend($scid);

$total   = (int)$ov['total'];
$tested  = (int)$ov['tested'];
$passed  = (int)$ov['passed'];
$avg     = $tested ? round((int)$ov['score_sum'] / $tested, 2) : 0.0;
$passPct = $tested ? round($passed / $tested * 100) : 0;
$compPct = $total ? round($tested / $total * 100) : 0;

// ---- เตรียมข้อมูลกราฟ (ส่งเป็น JSON ให้ Chart.js) ----
$chartLabels = array_map(fn($c) => $c['classname'], $classes);
$avgByHit = [1 => [], 2 => [], 3 => []];
foreach ($classes as $c) {
    foreach ([1, 2, 3] as $hn) {
        $avgByHit[$hn][] = $c["h{$hn}avg"] !== null ? (float)$c["h{$hn}avg"] : null;
    }
}
$trendLabels = array_map(fn($t) => 'ปี ' . $t['years'], $trend);
$trendData   = array_map(fn($t) => (float)$t['avg_score'], $trend);

// ---- จัดอันดับชั้นที่ต้องเร่ง (pass% ของรอบที่เลือก น้อย→มาก) ----
$ranked = [];
foreach ($classes as $c) {
    $pct = dash_class_pass_pct($c, $hit);
    $ranked[] = ['class_id' => (int)$c['class_id'], 'name' => $c['classname'],
                 'n' => (int)$c['n'], 'tested' => (int)$c["h{$hit}t"], 'pct' => $pct];
}
usort($ranked, function ($a, $b) {
    if ($a['pct'] === null) return 1;        // ยังไม่สอบ → ท้ายตาราง
    if ($b['pct'] === null) return -1;
    return $a['pct'] <=> $b['pct'];
});

/** สีพื้นเซลล์ heatmap ตาม %ผ่าน */
function heat_color(?float $pct): string
{
    if ($pct === null) return 'background:#F1F2F6;color:#9aa0ad';     // ยังไม่มีข้อมูล
    if ($pct < 50)     return 'background:#FFE0E0;color:#B4232B';     // ต่ำกว่าเกณฑ์
    if ($pct < 70)     return 'background:#FFF3D6;color:#9A6B00';     // กลาง
    return 'background:#DDF5E5;color:#1E7B45';                        // ดี
}

$page_title = 'ผลพัฒนาการ (โรงเรียน)';
$active = 'analytics';
require __DIR__ . '/includes/header.php';
?>
<style>.ht-container{max-width:1400px}
.dash-heat td,.dash-heat th{text-align:center;padding:10px 8px;border-radius:10px}
.dash-heat td{font-weight:800}
.dash-chartbox{background:#fff;border-radius:var(--r-lg);padding:18px 20px;box-shadow:var(--sh-sm,0 2px 10px rgba(0,0,0,.05))}
</style>

<div class="ht-row mb-2" style="gap:16px;align-items:center">
    <h2 class="mb-0">📊 ผลพัฒนาการการอ่าน</h2>
    <span class="ht-badge t-blue">🏫 <?= htmlspecialchars($_SESSION['sc_name']) ?></span>
    <form method="get" class="ht-row" style="gap:8px;margin-left:auto">
        <label class="ht-label mb-0">รอบสอบ</label>
        <select name="hit" class="ht-select" style="width:auto" onchange="this.form.submit()">
            <?php foreach (HITTESTS as $h): ?>
                <option value="<?= $h ?>" <?= $h === $hit ? 'selected' : '' ?>>Hit-<?= $h ?></option>
            <?php endforeach; ?>
        </select>
    </form>
</div>
<p class="text-muted mb-4">ปีการศึกษา <?= ACADEMIC_YEAR ?> · เกณฑ์ผ่าน <?= $pass ?>/<?= WORDS_PER_SET ?> (50%) · แสดงผลรอบ <strong>Hit-<?= $hit ?></strong></p>

<!-- KPI cards -->
<div class="row g-4 mb-5">
    <div class="col-6 col-lg-3">
        <div class="ht-stat t-blue">
            <div class="ht-stat__icon">👦👧</div>
            <div class="ht-stat__value"><?= $total ?></div>
            <div class="ht-stat__label">นักเรียนทั้งหมด</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="ht-stat t-green">
            <div class="ht-stat__icon">🎯</div>
            <div class="ht-stat__value"><?= $avg ?: '–' ?><?php if ($avg): ?><span style="font-size:.5em;color:var(--ink-soft)"> /<?= WORDS_PER_SET ?></span><?php endif; ?></div>
            <div class="ht-stat__label">คะแนนเฉลี่ย (Hit-<?= $hit ?>)</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="ht-stat <?= $passPct >= 50 ? 't-purple' : 't-coral' ?>">
            <div class="ht-stat__icon"><?= $passPct >= 50 ? '✅' : '⚠️' ?></div>
            <div class="ht-stat__value"><?= $tested ? $passPct . '%' : '–' ?></div>
            <div class="ht-stat__label">อัตราผ่านเกณฑ์ (<?= $passed ?>/<?= $tested ?> คน)</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="ht-stat t-yellow">
            <div class="ht-stat__icon">📝</div>
            <div class="ht-stat__value"><?= $compPct ?>%</div>
            <div class="ht-stat__label">สอบครบ (<?= $tested ?>/<?= $total ?> คน)</div>
        </div>
    </div>
</div>

<div class="row g-4 mb-5">
    <!-- Grouped bar: เฉลี่ยรายชั้น × รอบ -->
    <div class="col-lg-7">
        <div class="dash-chartbox h-100">
            <h3 class="mb-3">คะแนนเฉลี่ยแต่ละชั้น (เทียบ Hit-1/2/3)</h3>
            <canvas id="chartByClass" height="150"></canvas>
        </div>
    </div>
    <!-- Line: เทรนด์ข้ามปี -->
    <div class="col-lg-5">
        <div class="dash-chartbox h-100">
            <h3 class="mb-3">พัฒนาการเฉลี่ยข้ามปี</h3>
            <?php if ($trend): ?>
                <canvas id="chartTrend" height="220"></canvas>
            <?php else: ?>
                <p class="text-muted" style="padding:30px 0">ยังไม่มีข้อมูลย้อนหลังของนักเรียนชุดปัจจุบัน (studenteval)</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="row g-4 mb-5">
    <!-- Heatmap ชั้น × รอบ (%ผ่าน) -->
    <div class="col-lg-7">
        <div class="dash-chartbox">
            <h3 class="mb-3">อัตราผ่านเกณฑ์ รายชั้น × รอบ (%)</h3>
            <table class="dash-heat" style="width:100%;border-collapse:separate;border-spacing:6px">
                <thead>
                    <tr>
                        <th style="text-align:left">ชั้น</th>
                        <th>Hit-1</th><th>Hit-2</th><th>Hit-3</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($classes as $c): ?>
                    <tr>
                        <td style="text-align:left;font-weight:700;background:transparent;color:var(--ink)"><?= htmlspecialchars($c['classname']) ?></td>
                        <?php foreach ([1, 2, 3] as $hn): $pct = dash_class_pass_pct($c, $hn); ?>
                            <td style="<?= heat_color($pct) ?>"><?= $pct === null ? '–' : $pct . '%' ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <p class="text-muted mt-2 mb-0" style="font-size:.85rem">🟩 ≥70% · 🟨 50–69% · 🟥 &lt;50% · ⬜ ยังไม่สอบ</p>
        </div>
    </div>
    <!-- ชั้นที่ต้องเร่ง (rank ตามรอบที่เลือก) -->
    <div class="col-lg-5">
        <div class="dash-chartbox">
            <h3 class="mb-3">🚨 ชั้นที่ต้องเร่ง (Hit-<?= $hit ?>)</h3>
            <table class="ht-table" style="width:100%">
                <thead><tr><th>ชั้น</th><th class="text-center">สอบแล้ว</th><th class="text-center">%ผ่าน</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($ranked as $r): ?>
                    <tr>
                        <td class="fw-7"><?= htmlspecialchars($r['name']) ?></td>
                        <td class="text-center text-muted"><?= $r['tested'] ?>/<?= $r['n'] ?></td>
                        <td class="text-center">
                            <?php if ($r['pct'] === null): ?>
                                <span class="ht-badge ht-badge--missing">ยังไม่สอบ</span>
                            <?php else: ?>
                                <span class="fw-8" style="color:<?= $r['pct'] < 50 ? '#B4232B' : ($r['pct'] < 70 ? '#9A6B00' : '#1E7B45') ?>"><?= $r['pct'] ?>%</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end"><a class="ht-btn ht-btn--ghost ht-btn--sm" href="dashboard_class.php?class_id=<?= $r['class_id'] ?>">ดูรายชั้น →</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <p class="text-muted mt-2 mb-0" style="font-size:.85rem">💡 ระดับชั้น (ป.1–ป.6) — ไม่แยกห้อง · กด "ดูรายชั้น" เพื่อ drill ลงรายชั้น → รายคน</p>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
(function () {
    'use strict';
    var DATA = <?= json_encode([
        'labels'      => $chartLabels,
        'avgByHit'    => $avgByHit,
        'trendLabels' => $trendLabels,
        'trendData'   => $trendData,
        'pass'        => $pass,
        'full'        => WORDS_PER_SET,
    ], JSON_UNESCAPED_UNICODE) ?>;

    if (typeof Chart === 'undefined') { return; }
    Chart.defaults.font.family = 'Sarabun, sans-serif';

    var byClass = document.getElementById('chartByClass');
    if (byClass) {
        new Chart(byClass, {
            type: 'bar',
            data: {
                labels: DATA.labels,
                datasets: [
                    { label: 'Hit-1', data: DATA.avgByHit[1], backgroundColor: '#4D96FF' },
                    { label: 'Hit-2', data: DATA.avgByHit[2], backgroundColor: '#6BCB77' },
                    { label: 'Hit-3', data: DATA.avgByHit[3], backgroundColor: '#9D7BFF' }
                ]
            },
            options: {
                responsive: true,
                scales: { y: { beginAtZero: true, max: DATA.full, title: { display: true, text: 'คะแนนเฉลี่ย (เต็ม ' + DATA.full + ')' } } },
                plugins: { legend: { position: 'bottom' } }
            }
        });
    }

    var trend = document.getElementById('chartTrend');
    if (trend && DATA.trendData.length) {
        new Chart(trend, {
            type: 'line',
            data: {
                labels: DATA.trendLabels,
                datasets: [{
                    label: 'คะแนนเฉลี่ย', data: DATA.trendData,
                    borderColor: '#4D96FF', backgroundColor: 'rgba(77,150,255,.15)',
                    fill: true, tension: .3, pointRadius: 5
                }]
            },
            options: {
                responsive: true,
                scales: { y: { beginAtZero: true, max: DATA.full } },
                plugins: { legend: { display: false } }
            }
        });
    }
})();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
