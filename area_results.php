<?php
/**
 * area_results.php — ติดตามผลการสอบรายโรงเรียน ระดับเขตพื้นที่ (สพป.)
 *
 *   saoadmin   : ล็อกเขตตนเอง (area_code = 4 หลักแรกของ SMIS)
 *   superadmin : เลือกเขตได้ทุกเขต (?area=XXXX)
 *   role=school: เข้าหน้านี้ไม่ได้ (redirect ไป menu.php)
 *
 * แสดงทุกโรงเรียนในเขต × ความคืบหน้า 3 รอบ (Hit-1/2/3): สอบแล้วกี่คน, เฉลี่ย, ผ่าน (≥ PASS_SCORE)
 * ข้อมูลจาก snapshot ตาราง students ปีปัจจุบัน (current_year()) — แหล่งเดียวกับ dashboard ผลพัฒนาการ
 * ปุ่ม "เข้าดู" สลับโรงเรียนผ่าน school_switch.php (scope-check ฝั่งนั้นอยู่แล้ว)
 */
require __DIR__ . '/includes/auth.php';

// gate: saoadmin (เขตตน) หรือผู้ดูแลระบบ (superadmin/bootstrap SMIS admin) — role=school เข้าไม่ได้
if (!is_admin() && !is_saoadmin()) {
    header('Location: menu.php');
    exit;
}

$pdo = db();

/* ─── เขตที่ดู ─── */
if (is_saoadmin()) {
    $area = current_area_code();                       // ล็อกเขตตน
} else {
    $area = preg_match('/^\d{4}$/', (string)($_GET['area'] ?? '')) ? (string)$_GET['area'] : '';
}

// ผู้ดูแลระบบ (ไม่ใช่ saoadmin): รายการเขตทั้งหมดที่มีโรงเรียน (ไว้ทำ dropdown) + default เขตแรก
$areaList = [];
if (!is_saoadmin()) {
    $areaList = $pdo->query(
        "SELECT LEFT(sc.sc_smis,4) AS a4, COUNT(*) AS nsch, MIN(sn.sao) AS sao_name
         FROM schools sc
         LEFT JOIN sao_new sn ON sn.areacode = CONCAT(LEFT(sc.sc_smis,4), '0000')
         WHERE sc.sc_smis REGEXP '^[0-9]{8}$'
         GROUP BY a4 ORDER BY a4"
    )->fetchAll();
    if ($area === '' && $areaList) {
        $area = (string)$areaList[0]['a4'];
    }
}

// ชื่อเขต
$areaName = '';
if ($area !== '') {
    $an = $pdo->prepare("SELECT sao FROM sao_new WHERE areacode = CONCAT(?, '0000') LIMIT 1");
    $an->execute([$area]);
    $areaName = (string)($an->fetchColumn() ?: ('เขต ' . $area));
}

/* ─── ฟิลเตอร์ชั้น ─── */
$classid = (int)($_GET['class'] ?? 0);
if ($classid < 1 || $classid > 6) {
    $classid = 0;                                      // 0 = ทุกชั้น
}

/* ─── สรุปรายโรงเรียน × 3 รอบ (ปีปัจจุบัน) ─── */
$rows = [];
if ($area !== '') {
    $p = (int)PASS_SCORE;
    $joinClass = $classid ? ' AND s.class_id = ?' : '';
    $sql = "SELECT sc.sc_id, sc.sc_smis, sc.sc_name,
                COUNT(s.stuid) AS n,
                COALESCE(SUM(s.hit1tested),0) AS h1t, ROUND(AVG(CASE WHEN s.hit1tested=1 THEN s.hit1 END),2) AS h1avg, COALESCE(SUM(CASE WHEN s.hit1tested=1 AND s.hit1>=$p THEN 1 ELSE 0 END),0) AS h1pass,
                COALESCE(SUM(s.hit2tested),0) AS h2t, ROUND(AVG(CASE WHEN s.hit2tested=1 THEN s.hit2 END),2) AS h2avg, COALESCE(SUM(CASE WHEN s.hit2tested=1 AND s.hit2>=$p THEN 1 ELSE 0 END),0) AS h2pass,
                COALESCE(SUM(s.hit3tested),0) AS h3t, ROUND(AVG(CASE WHEN s.hit3tested=1 THEN s.hit3 END),2) AS h3avg, COALESCE(SUM(CASE WHEN s.hit3tested=1 AND s.hit3>=$p THEN 1 ELSE 0 END),0) AS h3pass
            FROM schools sc
            LEFT JOIN students s ON s.sc_id = sc.sc_id AND s.years = ?{$joinClass}
            WHERE sc.sc_smis LIKE ?
            GROUP BY sc.sc_id, sc.sc_smis, sc.sc_name
            ORDER BY sc.sc_smis";
    $params = [current_year()];
    if ($classid) {
        $params[] = $classid;
    }
    $params[] = $area . '%';
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll();
}

/* ─── KPI รวมทั้งเขต (รวมจากแถว) ─── */
$tot = ['sch' => count($rows), 'sch_has' => 0, 'n' => 0,
        'h1t' => 0, 'h1pass' => 0, 'h2t' => 0, 'h2pass' => 0, 'h3t' => 0, 'h3pass' => 0];
foreach ($rows as $r) {
    if ((int)$r['n'] > 0) {
        $tot['sch_has']++;
    }
    $tot['n'] += (int)$r['n'];
    foreach ([1, 2, 3] as $h) {
        $tot["h{$h}t"]    += (int)$r["h{$h}t"];
        $tot["h{$h}pass"] += (int)$r["h{$h}pass"];
    }
}
$pct = static fn (int $a, int $b): string => $b > 0 ? number_format($a / $b * 100, 1) : '0.0';

$page_title = 'ติดตามผลสอบรายโรงเรียน';
$active     = 'area_results';
require __DIR__ . '/includes/header.php';

/** เซลล์สรุป 1 รอบของ 1 โรงเรียน */
function ar_hit_cell(array $r, int $h): string
{
    $n = (int)$r['n'];
    if ($n === 0) {
        return '<td class="text-muted text-center">—</td>';
    }
    $t    = (int)$r["h{$h}t"];
    $pass = (int)$r["h{$h}pass"];
    $avg  = $r["h{$h}avg"];
    $tp   = $n > 0 ? round($t / $n * 100) : 0;
    $bar  = $tp >= 100 ? '#6BCB77' : ($tp > 0 ? '#4D96FF' : '#e6e6e6');
    $html  = '<td style="min-width:150px">';
    $html .= '<div class="d-flex justify-content-between" style="font-size:.85rem">'
           . '<span>สอบแล้ว <b>' . $t . '</b>/' . $n . '</span><span class="text-muted">' . $tp . '%</span></div>';
    $html .= '<div style="height:6px;border-radius:4px;background:#eef1f5;overflow:hidden;margin:3px 0 4px">'
           . '<div style="width:' . min(100, $tp) . '%;height:100%;background:' . $bar . '"></div></div>';
    if ($t > 0) {
        $passPct = round($pass / $t * 100);
        $html .= '<div class="text-muted" style="font-size:.78rem">เฉลี่ย <b>' . htmlspecialchars((string)$avg) . '</b>/' . WORDS_PER_SET
               . ' · ผ่าน ' . $pass . ' (' . $passPct . '%)</div>';
    } else {
        $html .= '<div class="text-muted" style="font-size:.78rem">ยังไม่เริ่มสอบ</div>';
    }
    return $html . '</td>';
}
?>
<h2 class="mb-1">🏫 ติดตามผลการสอบรายโรงเรียน</h2>
<p class="text-muted mb-3">
    เขตพื้นที่ <strong><?= htmlspecialchars($areaName ?: '—') ?></strong>
    · ปีการศึกษา <strong><?= current_year() ?></strong>
    · เกณฑ์ผ่าน ≥ <?= (int)PASS_SCORE ?>/<?= (int)WORDS_PER_SET ?> คำ
    <?php if ($classid): ?> · เฉพาะชั้น <strong>ป.<?= $classid ?></strong><?php endif; ?>
</p>

<form method="get" class="d-flex flex-wrap gap-2 align-items-center mb-3">
    <?php if (!is_saoadmin()): ?>
        <select name="area" class="ht-input" style="max-width:340px">
            <?php foreach ($areaList as $a): ?>
                <option value="<?= htmlspecialchars($a['a4']) ?>" <?= $a['a4'] === $area ? 'selected' : '' ?>>
                    <?= htmlspecialchars($a['a4']) ?> — <?= htmlspecialchars($a['sao_name'] ?: 'ไม่ทราบชื่อเขต') ?> (<?= (int)$a['nsch'] ?> รร.)
                </option>
            <?php endforeach; ?>
        </select>
    <?php endif; ?>
    <select name="class" class="ht-input" style="max-width:140px">
        <option value="0">ทุกชั้น</option>
        <?php for ($c = 1; $c <= 6; $c++): ?>
            <option value="<?= $c ?>" <?= $classid === $c ? 'selected' : '' ?>>ป.<?= $c ?></option>
        <?php endfor; ?>
    </select>
    <button class="ht-btn ht-btn--blue" type="submit">แสดงผล</button>
    <input id="q" class="ht-input ms-auto" style="max-width:260px" placeholder="🔍 กรองชื่อ/รหัสโรงเรียน" autocomplete="off">
</form>

<div class="d-flex flex-wrap gap-2 mb-3">
    <?php
    $cards = [
        ['โรงเรียนทั้งหมด', number_format($tot['sch']), 'แห่ง'],
        ['มีรายชื่อนักเรียน', number_format($tot['sch_has']), 'แห่ง'],
        ['นักเรียน' . ($classid ? ' ป.' . $classid : ''), number_format($tot['n']), 'คน'],
        ['สอบแล้ว Hit-1', $pct($tot['h1t'], $tot['n']) . '%', number_format($tot['h1t']) . ' คน · ผ่าน ' . $pct($tot['h1pass'], $tot['h1t']) . '%'],
        ['สอบแล้ว Hit-2', $pct($tot['h2t'], $tot['n']) . '%', number_format($tot['h2t']) . ' คน · ผ่าน ' . $pct($tot['h2pass'], $tot['h2t']) . '%'],
        ['สอบแล้ว Hit-3', $pct($tot['h3t'], $tot['n']) . '%', number_format($tot['h3t']) . ' คน · ผ่าน ' . $pct($tot['h3pass'], $tot['h3t']) . '%'],
    ];
    foreach ($cards as [$label, $value, $sub]): ?>
        <div class="ht-card" style="flex:1 1 150px;min-width:150px;padding:12px 16px">
            <div class="text-muted" style="font-size:.8rem"><?= htmlspecialchars($label) ?></div>
            <div style="font-size:1.4rem;font-weight:800;line-height:1.3"><?= htmlspecialchars($value) ?></div>
            <div class="text-muted" style="font-size:.75rem"><?= htmlspecialchars($sub) ?></div>
        </div>
    <?php endforeach; ?>
</div>

<div class="ht-card">
    <div class="ht-table-wrap">
        <table class="ht-table" id="tbl">
            <thead>
            <tr>
                <th style="cursor:pointer" data-sort="smis">SMIS ⇅</th>
                <th style="cursor:pointer" data-sort="name">โรงเรียน ⇅</th>
                <th class="text-end" style="cursor:pointer" data-sort="n">นักเรียน ⇅</th>
                <th style="cursor:pointer" data-sort="h1">Hit-1 ⇅</th>
                <th style="cursor:pointer" data-sort="h2">Hit-2 ⇅</th>
                <th style="cursor:pointer" data-sort="h3">Hit-3 ⇅</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="7" class="text-muted text-center" style="padding:24px">ไม่พบโรงเรียนในเขตนี้</td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $r): $n = (int)$r['n']; ?>
                <tr data-smis="<?= htmlspecialchars($r['sc_smis']) ?>"
                    data-name="<?= htmlspecialchars($r['sc_name']) ?>"
                    data-n="<?= $n ?>"
                    data-h1="<?= $n ? round((int)$r['h1t'] / $n * 100) : -1 ?>"
                    data-h2="<?= $n ? round((int)$r['h2t'] / $n * 100) : -1 ?>"
                    data-h3="<?= $n ? round((int)$r['h3t'] / $n * 100) : -1 ?>">
                    <td class="fw-7" style="white-space:nowrap"><?= htmlspecialchars($r['sc_smis']) ?></td>
                    <td><?= htmlspecialchars($r['sc_name']) ?></td>
                    <td class="text-end"><?= $n ? number_format($n) : '<span class="text-muted">ยังไม่นำเข้า</span>' ?></td>
                    <?= ar_hit_cell($r, 1) ?>
                    <?= ar_hit_cell($r, 2) ?>
                    <?= ar_hit_cell($r, 3) ?>
                    <td class="text-end">
                        <button class="ht-btn ht-btn--sm js-view" data-scid="<?= htmlspecialchars($r['sc_id']) ?>" type="button">เข้าดู →</button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p class="text-muted mt-2 mb-0" style="font-size:.78rem">
        คลิกหัวตารางเพื่อเรียงลำดับ · "เข้าดู" จะสลับโรงเรียนที่กำลังดูแล้วเปิดหน้าผลพัฒนาการของโรงเรียนนั้น
    </p>
</div>

<script>
(function () {
    var BLUE = '#4D96FF';

    // ── กรองชื่อ/รหัส ──
    var q = document.getElementById('q');
    var tbody = document.querySelector('#tbl tbody');
    q.addEventListener('input', function () {
        var s = q.value.trim().toLowerCase();
        tbody.querySelectorAll('tr[data-smis]').forEach(function (tr) {
            var hit = !s || tr.dataset.smis.indexOf(s) !== -1 || tr.dataset.name.toLowerCase().indexOf(s) !== -1;
            tr.style.display = hit ? '' : 'none';
        });
    });

    // ── เรียงลำดับ (client-side) ──
    var dir = {};
    document.querySelectorAll('#tbl th[data-sort]').forEach(function (th) {
        th.addEventListener('click', function () {
            var k = th.dataset.sort;
            dir[k] = !dir[k];
            var trs = Array.prototype.slice.call(tbody.querySelectorAll('tr[data-smis]'));
            trs.sort(function (a, b) {
                var x = a.dataset[k], y = b.dataset[k];
                if (k === 'smis' || k === 'name') {
                    return dir[k] ? x.localeCompare(y, 'th') : y.localeCompare(x, 'th');
                }
                return dir[k] ? (+x) - (+y) : (+y) - (+x);
            });
            trs.forEach(function (tr) { tbody.appendChild(tr); });
        });
    });

    // ── เข้าดูโรงเรียน: สลับ context ผ่าน school_switch.php แล้วไปหน้าผลพัฒนาการ ──
    document.querySelectorAll('.js-view').forEach(function (btn) {
        btn.addEventListener('click', function () {
            btn.disabled = true;
            var fd = new FormData();
            fd.append('action', 'switch');
            fd.append('sc_id', btn.dataset.scid);
            fetch('school_switch.php', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (d.status === 'ok') { window.location = 'dashboard_school.php'; }
                    else {
                        btn.disabled = false;
                        Swal.fire({ icon: 'error', title: 'ผิดพลาด', text: d.message || '', confirmButtonColor: BLUE });
                    }
                })
                .catch(function () { btn.disabled = false; });
        });
    });
})();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
