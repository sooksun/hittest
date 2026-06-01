<?php
/**
 * dashboard_class.php — Dashboard "ผลพัฒนาการการอ่าน" ระดับชั้น (Phase 2)
 * อ้างอิง docs/dashboard-design.md ข้อ 3.2 — ชั้น = ป.1..ป.6 (รวมทุกห้อง ไม่แยกห้อง)
 *
 * RBAC / anti-IDOR: ทุก query ผูก current_sc_id() (session) เสมอ
 *   - class_id รับจาก URL แต่ validate 1..6 + ข้อมูลถูกกรองด้วย sc_id อยู่แล้ว
 *     → เปลี่ยน class_id ใน URL เห็นได้แค่ชั้นนั้นของโรงเรียนตัวเอง ไม่มีทางข้ามโรงเรียน
 *
 * Filter: รอบ Hit (?hit=1..3) · ประเภทนักเรียน (?status=)
 */
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/dashboard.php';

$scid    = current_sc_id();
$classid = dash_valid_class((int)($_GET['class_id'] ?? 0));
if ($classid === 0) {
    header('Location: dashboard_school.php');     // class_id ไม่ถูกต้อง → กลับหน้าโรงเรียน
    exit;
}
$hit       = dash_valid_hit((int)($_GET['hit'] ?? 1));
$statusF   = (int)($_GET['status'] ?? 0);          // 0 = ทั้งหมด, 1..4 = ตาม STU_STATUS
$className = dash_class_name($classid) ?? ('ป.' . $classid);
$pass      = (int)PASS_SCORE;

$ov       = dash_overview($scid, $hit, $classid);
$students = dash_class_students($scid, $classid);
if ($statusF >= 1 && $statusF <= 4) {
    $students = array_values(array_filter($students, fn($s) => (int)$s['stustatus'] === $statusF));
}

$total   = count($students);
$tested  = 0; $passed = 0; $scoreSum = 0;
foreach ($students as $s) {
    if ((int)$s["hit{$hit}tested"] === 1) {
        $tested++; $scoreSum += (int)$s["hit{$hit}"];
        if ((int)$s["hit{$hit}"] >= $pass) { $passed++; }
    }
}
$avg     = $tested ? round($scoreSum / $tested, 2) : 0.0;
$passPct = $tested ? round($passed / $tested * 100) : 0;
$compPct = $total ? round($tested / $total * 100) : 0;

$hist = dash_score_histogram($students, $hit);

// ---- ตาราง at-risk: เรียงคะแนนรอบที่เลือก น้อย→มาก (ยังไม่สอบ → ท้าย) ----
$rows = [];
foreach ($students as $s) {
    $tcol = (int)$s["hit{$hit}tested"];
    $score = $tcol ? (int)$s["hit{$hit}"] : null;
    // Δ เทียบรอบก่อนหน้า (ถ้ามีและสอบทั้งคู่)
    $delta = null;
    if ($hit > 1 && $tcol && (int)$s["hit" . ($hit - 1) . "tested"] === 1) {
        $delta = $score - (int)$s["hit" . ($hit - 1)];
    }
    $rows[] = [
        'stuid' => $s['stuid'], 'name' => $s['stuname'], 'rooms' => (int)$s['rooms'],
        'status' => (int)$s['stustatus'], 'h1' => $s['hit1tested'] ? (int)$s['hit1'] : null,
        'h2' => $s['hit2tested'] ? (int)$s['hit2'] : null, 'h3' => $s['hit3tested'] ? (int)$s['hit3'] : null,
        'score' => $score, 'delta' => $delta,
    ];
}
usort($rows, function ($a, $b) {
    if ($a['score'] === null) return 1;
    if ($b['score'] === null) return -1;
    return $a['score'] <=> $b['score'];
});

$page_title = 'ผลพัฒนาการ (' . $className . ')';
$active = 'analytics';
require __DIR__ . '/includes/header.php';
?>
<style>.ht-container{max-width:1400px}
.dash-chartbox{background:#fff;border-radius:var(--r-lg);padding:18px 20px;box-shadow:0 2px 10px rgba(0,0,0,.05)}</style>

<div class="ht-row mb-2" style="gap:12px;align-items:center;flex-wrap:wrap">
    <a href="dashboard_school.php" class="ht-btn ht-btn--ghost ht-btn--sm">← ภาพรวมโรงเรียน</a>
    <h2 class="mb-0">📗 <?= htmlspecialchars($className) ?></h2>
    <span class="ht-badge t-blue">🏫 <?= htmlspecialchars($_SESSION['sc_name']) ?></span>
    <a href="teacher_pins.php?class_id=<?= $classid ?>" class="ht-btn ht-btn--ghost ht-btn--sm">🪪 การ์ด login</a>
    <form method="get" class="ht-row" style="gap:8px;margin-left:auto;flex-wrap:wrap">
        <input type="hidden" name="class_id" value="<?= $classid ?>">
        <label class="ht-label mb-0">รอบสอบ</label>
        <select name="hit" class="ht-select" style="width:auto" onchange="this.form.submit()">
            <?php foreach (HITTESTS as $h): ?><option value="<?= $h ?>" <?= $h === $hit ? 'selected' : '' ?>>Hit-<?= $h ?></option><?php endforeach; ?>
        </select>
        <label class="ht-label mb-0">ประเภท</label>
        <select name="status" class="ht-select" style="width:auto" onchange="this.form.submit()">
            <option value="0">ทั้งหมด</option>
            <?php foreach (STU_STATUS as $sid => $sname): ?><option value="<?= $sid ?>" <?= $sid === $statusF ? 'selected' : '' ?>><?= htmlspecialchars($sname) ?></option><?php endforeach; ?>
        </select>
    </form>
</div>
<p class="text-muted mb-4">ปีการศึกษา <?= ACADEMIC_YEAR ?> · เกณฑ์ผ่าน <?= $pass ?>/<?= WORDS_PER_SET ?> (50%) · รวมทุกห้อง · แสดงผลรอบ <strong>Hit-<?= $hit ?></strong></p>

<!-- KPI -->
<div class="row g-4 mb-5">
    <div class="col-6 col-lg-3"><div class="ht-stat t-blue"><div class="ht-stat__icon">👦👧</div><div class="ht-stat__value"><?= $total ?></div><div class="ht-stat__label">นักเรียนในชั้น</div></div></div>
    <div class="col-6 col-lg-3"><div class="ht-stat t-green"><div class="ht-stat__icon">🎯</div><div class="ht-stat__value"><?= $avg ?: '–' ?><?php if ($avg): ?><span style="font-size:.5em;color:var(--ink-soft)"> /<?= WORDS_PER_SET ?></span><?php endif; ?></div><div class="ht-stat__label">คะแนนเฉลี่ย (Hit-<?= $hit ?>)</div></div></div>
    <div class="col-6 col-lg-3"><div class="ht-stat <?= $passPct >= 50 ? 't-purple' : 't-coral' ?>"><div class="ht-stat__icon"><?= $passPct >= 50 ? '✅' : '⚠️' ?></div><div class="ht-stat__value"><?= $tested ? $passPct . '%' : '–' ?></div><div class="ht-stat__label">ผ่านเกณฑ์ (<?= $passed ?>/<?= $tested ?> คน)</div></div></div>
    <div class="col-6 col-lg-3"><div class="ht-stat t-yellow"><div class="ht-stat__icon">📝</div><div class="ht-stat__value"><?= $compPct ?>%</div><div class="ht-stat__label">สอบครบ (<?= $tested ?>/<?= $total ?> คน)</div></div></div>
</div>

<div class="row g-4 mb-5">
    <div class="col-lg-7"><div class="dash-chartbox h-100">
        <h3 class="mb-3">การกระจายคะแนน (Hit-<?= $hit ?>)</h3>
        <canvas id="chartHist" height="160"></canvas>
    </div></div>
    <div class="col-lg-5"><div class="dash-chartbox h-100">
        <h3 class="mb-3">ความคืบหน้าการสอบ</h3>
        <?php if ($total): ?><canvas id="chartDonut" height="220"></canvas>
        <?php else: ?><p class="text-muted" style="padding:30px 0">ยังไม่มีนักเรียนในชั้นนี้</p><?php endif; ?>
    </div></div>
</div>

<div class="dash-chartbox mb-5">
    <h3 class="mb-3">🚨 รายชื่อนักเรียน (เรียงคะแนน Hit-<?= $hit ?> น้อย→มาก — เด็กที่ต้องช่วยอยู่บน)</h3>
    <table class="ht-table" style="width:100%">
        <thead><tr>
            <th>ชื่อ - สกุล</th><th class="text-center">ห้อง</th>
            <th class="text-center">Hit-1</th><th class="text-center">Hit-2</th><th class="text-center">Hit-3</th>
            <th class="text-center">Δ</th><th class="text-center">สถานะ</th><th></th>
        </tr></thead>
        <tbody>
        <?php if (!$rows): ?>
            <tr><td colspan="8" class="text-center text-muted" style="padding:30px">ไม่มีนักเรียนตามเงื่อนไข</td></tr>
        <?php else: foreach ($rows as $r):
            $cur = $r['score'];
            $flag = $cur === null ? '⬜' : ($cur < $pass ? '🔴' : ($cur < 15 ? '🟠' : '🟢')); ?>
            <tr>
                <td class="fw-7"><?= htmlspecialchars($r['name']) ?></td>
                <td class="text-center text-muted"><?= $r['rooms'] ?></td>
                <?php foreach (['h1', 'h2', 'h3'] as $hk): ?>
                    <td class="text-center"><?= $r[$hk] === null ? '<span class="text-muted">–</span>' : $r[$hk] ?></td>
                <?php endforeach; ?>
                <td class="text-center"><?php
                    if ($r['delta'] === null) { echo '<span class="text-muted">–</span>'; }
                    elseif ($r['delta'] > 0) { echo '<span style="color:#1E7B45;font-weight:700">▲ +' . $r['delta'] . '</span>'; }
                    elseif ($r['delta'] < 0) { echo '<span style="color:#B4232B;font-weight:700">▼ ' . $r['delta'] . '</span>'; }
                    else { echo '<span class="text-muted">0</span>'; }
                ?></td>
                <td class="text-center"><?= $flag ?></td>
                <td class="text-end"><a class="ht-btn ht-btn--ghost ht-btn--sm" href="dashboard_student.php?stuid=<?= urlencode($r['stuid']) ?>">ดูรายคน →</a></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
    <p class="text-muted mt-2 mb-0" style="font-size:.85rem">🔴 ต่ำกว่าเกณฑ์ (&lt;<?= $pass ?>) · 🟠 <?= $pass ?>–14 · 🟢 ≥15 · ⬜ ยังไม่สอบ · Δ = เทียบรอบก่อนหน้า</p>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
(function () {
    'use strict';
    var D = <?= json_encode([
        'hist'      => array_values($hist),
        'histLabels'=> array_keys($hist),
        'tested'    => $tested,
        'notTested' => max(0, $total - $tested),
    ], JSON_UNESCAPED_UNICODE) ?>;
    if (typeof Chart === 'undefined') { return; }
    Chart.defaults.font.family = 'Sarabun, sans-serif';

    var hist = document.getElementById('chartHist');
    if (hist) {
        new Chart(hist, {
            type: 'bar',
            data: { labels: D.histLabels, datasets: [{ label: 'จำนวนนักเรียน', data: D.hist,
                backgroundColor: ['#FF6B6B', '#FFD93D', '#6BCB77', '#4D96FF'] }] },
            options: { responsive: true, plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, ticks: { precision: 0 }, title: { display: true, text: 'คน' } } } }
        });
    }
    var donut = document.getElementById('chartDonut');
    if (donut) {
        new Chart(donut, {
            type: 'doughnut',
            data: { labels: ['สอบแล้ว', 'ยังไม่สอบ'], datasets: [{ data: [D.tested, D.notTested],
                backgroundColor: ['#6BCB77', '#E6E8EE'] }] },
            options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
        });
    }
})();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
