<?php
/**
 * admin_promote_all.php — เครื่องมือผู้ดูแล: เลื่อนชั้นนักเรียน "ทั้งระบบ" (ทุกโรงเรียน)
 *   โมเดลผูกปี: สร้าง "แถวปีใหม่" (ACADEMIC_YEAR) จากแถวปีก่อนหน้า ทุกโรงเรียน
 *   - ป.1–ป.5 : สร้างแถวปีใหม่ class+1, คะแนน/ธงสอบ = 0 · ป.6 : จบ ไม่สร้างแถว
 *   - แถวปีเก่าไม่ถูกแก้ · บันทึก promote_log แยกรายโรงเรียน (ยกเลิกรายโรงเรียนได้ที่ promote.php)
 *
 *   POST action=promote_all (ยืนยันด้วยชื่อ DB) → ทำในทรานแซกชันเดียว (all-or-nothing) → JSON
 *   GET                                          → หน้าพรีวิว
 */
require __DIR__ . '/includes/admin_auth.php';
require __DIR__ . '/includes/promote_lib.php';

$pdo = db();
$toYear   = current_year();          // ปีปัจจุบัน (2569)
$fromYear = $toYear - 1;             // เลื่อนจากปีก่อนหน้า (2568)

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'promote_all') {
    // ยอมรับเว้นวรรคแทน "_" (บางแป้นพิมพ์กด _ ไม่ได้) — ปรับทั้งฝั่ง server เองด้วย
    $confirmIn = preg_replace('/\s+/', '_', trim((string)($_POST['confirm'] ?? '')));
    if ($confirmIn !== DB_NAME) {
        json_response(['status' => 'error', 'message' => 'พิมพ์ชื่อฐานข้อมูลยืนยันไม่ถูกต้อง'], 400);
    }
    // ── fail-protection สำหรับสเกลใหญ่ (หลายแสนคน) ──────────────────────────────
    //   • commit "ทีละโรงเรียน" (ไม่ใช่ transaction เดียวทั้งระบบ) → ล้มกลางคันไม่เสียทั้งยวง
    //   • ทำเป็น batch ตาม time budget แล้วคืนค่าให้ client เรียกต่อ → กัน proxy/LSAPI timeout
    //   • idempotent: ข้ามโรงเรียนที่มี roster ปีใหม่แล้ว (NOT EXISTS) → กดซ้ำ/resume ได้
    //   • โรงเรียนที่ error ถูกส่งกลับใน failed[] → client ส่งเป็น skip รอบถัดไป (ไม่ retry วนไม่จบ)
    @set_time_limit(0);
    @ignore_user_abort(true);

    $budget = 20;            // วินาที/คำขอ — เผื่อให้จบก่อน proxy ตัด (เริ่มนับหลังทำอย่างน้อย 1 โรงเรียน)
    $start  = time();

    // โรงเรียนที่ล้มเหลวสะสมจากรอบก่อน (client ส่งกลับมา) — ข้ามไม่หยิบมาทำซ้ำ
    $skip = array_values(array_filter(
        array_map('strval', explode(',', (string)($_POST['skip'] ?? ''))),
        static fn ($s) => $s !== '' && ctype_digit($s)
    ));
    $skipSql = $skip ? (' AND s.sc_id NOT IN (' . implode(',', array_fill(0, count($skip), '?')) . ')') : '';

    // เลือกเฉพาะโรงเรียนที่ "มี roster ปีเก่า แต่ยังไม่มี roster ปีใหม่" (resumable) และไม่อยู่ใน skip
    $sel = $pdo->prepare(
        'SELECT DISTINCT s.sc_id FROM students s
         WHERE s.years = ? AND s.class_id BETWEEN 1 AND 6 AND s.stustatus <> 4
           AND NOT EXISTS (SELECT 1 FROM students d WHERE d.sc_id = s.sc_id AND d.years = ?)'
        . $skipSql . ' ORDER BY s.sc_id'
    );
    $sel->execute(array_merge([$fromYear, $toYear], $skip));
    $schoolIds = $sel->fetchAll(PDO::FETCH_COLUMN);

    $info = $pdo->prepare('SELECT sc_smis, sc_name FROM schools WHERE sc_id = ? LIMIT 1');
    $processed = 0;
    $totP = 0;
    $totG = 0;
    $failed = [];
    foreach ($schoolIds as $sc) {
        try {
            $info->execute([$sc]);
            $sch = $info->fetch() ?: ['sc_smis' => null, 'sc_name' => null];
            $pdo->beginTransaction();
            $r = promote_school_year($pdo, (string)$sc, $fromYear, $toYear, $sch['sc_smis'], $sch['sc_name']);
            $pdo->commit();                          // จบเป็นรายโรงเรียน → ผลที่สำเร็จคงอยู่แม้รอบถัดไปล้ม
            $totP += $r['promoted'];
            $totG += $r['graduated'];
            $processed++;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();                    // โรงเรียนนี้กลับสภาพเดิม ไม่กระทบโรงเรียนอื่น
            }
            $failed[] = (string)$sc;                 // client จะส่งกลับมาใน skip รอบถัดไป
        }
        if (time() - $start >= $budget) {
            break;                                   // คืนค่าให้ client เรียกต่อ (resume แบบไม่เริ่มใหม่)
        }
    }

    $remaining = max(0, count($schoolIds) - $processed - count($failed));
    json_response([
        'status'    => 'ok',
        'processed' => $processed,
        'promoted'  => $totP,
        'graduated' => $totG,
        'failed'    => $failed,
        'remaining' => $remaining,
        'done'      => ($remaining === 0),
    ]);
}

/* ---------- พรีวิว (อิงแถวปีก่อนหน้า = ปีที่จะถูกเลื่อน) ---------- */
$dist = [];
$distStmt = $pdo->prepare('SELECT class_id, COUNT(*) total, SUM(stustatus <> 4) active FROM students WHERE years = ? GROUP BY class_id ORDER BY class_id');
$distStmt->execute([$fromYear]);
foreach ($distStmt as $r) {
    $dist[(int)$r['class_id']] = ['total' => (int)$r['total'], 'active' => (int)$r['active']];
}
$scStmt = $pdo->prepare('SELECT COUNT(DISTINCT sc_id) FROM students WHERE years = ? AND class_id BETWEEN 1 AND 6 AND stustatus <> 4');
$scStmt->execute([$fromYear]);
$schoolCnt = (int)$scStmt->fetchColumn();
$pStmt = $pdo->prepare('SELECT COUNT(*) FROM students WHERE years = ? AND class_id BETWEEN 1 AND 5 AND stustatus <> 4');
$pStmt->execute([$fromYear]);
$toProm = (int)$pStmt->fetchColumn();
$gStmt = $pdo->prepare('SELECT COUNT(*) FROM students WHERE years = ? AND class_id = 6 AND stustatus <> 4');
$gStmt->execute([$fromYear]);
$toGrad = (int)$gStmt->fetchColumn();

$page_title = 'เลื่อนชั้นทั้งระบบ';
$active     = 'admin_promote';
require __DIR__ . '/includes/header.php';
?>
<h2 class="mb-1">⏫ เลื่อนชั้นนักเรียนทั้งระบบ</h2>
<p class="text-muted mb-4">ทุกโรงเรียนในฐานข้อมูล <strong><?= htmlspecialchars(DB_NAME) ?></strong></p>

<div class="ht-card mb-4" style="max-width:760px; border-left:6px solid var(--c-coral)">
    <p class="mb-2">⚠️ <strong>คำเตือน:</strong> คำสั่งนี้กระทบ <strong>ทุกโรงเรียน</strong> พร้อมกัน
       (<?= number_format($schoolCnt) ?> โรงเรียน) — ควร <a href="admin_backup.php">สำรองฐานข้อมูลก่อน</a></p>
</div>

<div class="ht-card" style="max-width:760px; border-left:6px solid var(--c-blue)">
    <h3 class="mb-3">สรุปก่อนเลื่อนชั้น (ทั้งระบบ)</h3>
    <div class="ht-table-wrap mb-3">
        <table class="ht-table">
            <thead><tr><th>ชั้นปัจจุบัน</th><th class="num text-center">นักเรียน (ใช้งาน)</th><th>หลังเลื่อนชั้น</th></tr></thead>
            <tbody>
            <?php for ($c = 1; $c <= 6; $c++): ?>
                <tr>
                    <td>ป.<?= $c ?></td>
                    <td class="num text-center"><?= number_format($dist[$c]['active'] ?? 0) ?></td>
                    <td class="text-muted"><?= $c < 6 ? ('→ ป.' . ($c + 1)) : '→ จบ (ย้ายออก)' ?></td>
                </tr>
            <?php endfor; ?>
            </tbody>
        </table>
    </div>
    <div class="ht-badge ht-badge--pending mb-3">
        เลื่อนขึ้น <?= number_format($toProm) ?> คน · จบ→ย้ายออก <?= number_format($toGrad) ?> คน ·
        <?= number_format($schoolCnt) ?> โรงเรียน · เก็บผลสอบเดิมไว้
    </div>
    <p class="text-muted mb-3" style="font-size:.92rem">
        ป.1–5 เลื่อนขึ้น 1 ชั้น · ป.6 เปลี่ยนเป็น "ย้ายออก" (เก็บประวัติ + ผลสอบ) · ข้ามผู้ที่ย้ายออกแล้ว ·
        <strong>ผลสอบเดิมไม่ถูกลบ</strong> · บันทึกประวัติแยกรายโรงเรียน (ยกเลิกรายโรงเรียนได้ที่เมนูเลื่อนชั้น)
    </p>
    <button id="btnPromoteAll" class="ht-btn ht-btn--coral"<?= ($toProm + $toGrad) === 0 ? ' disabled' : '' ?>>⏫ เลื่อนชั้นทั้งระบบ</button>
</div>

<script>
var DBNAME = <?= json_encode(DB_NAME) ?>;
document.getElementById('btnPromoteAll').addEventListener('click', function () {
    Swal.fire({
        icon: 'warning', title: 'เลื่อนชั้นทั้งระบบ?',
        html: 'กระทบ <b>ทุกโรงเรียน</b><br>ป.1–ป.5 เลื่อนขึ้น 1 ชั้น · <b>ป.6 จบ → ย้ายออก</b><br>ผลสอบเดิมจะถูกเก็บไว้ (ไม่ลบ)<br><br>พิมพ์ชื่อฐานข้อมูล <b>' + DBNAME + '</b> เพื่อยืนยัน',
        input: 'text', inputPlaceholder: 'ชื่อฐานข้อมูล',
        showCancelButton: true, confirmButtonText: 'เลื่อนชั้นทั้งระบบ', confirmButtonColor: '#FF6B6B', cancelButtonText: 'ยกเลิก',
        // ยอมรับเว้นวรรคแทน "_" (บางแป้นพิมพ์กด _ ไม่ได้) — แปลงช่องว่างเป็น _ ก่อนเทียบ
        preConfirm: function (v) { if (String(v).trim().replace(/\s+/g, '_') !== DBNAME) { Swal.showValidationMessage('ชื่อไม่ถูกต้อง'); return false; } return true; }
    }).then(function (r) {
        if (!r.isConfirmed) return;
        var confirmVal = String(r.value).trim().replace(/\s+/g, '_');
        var totals = { schools: 0, promoted: 0, graduated: 0 };
        var skip = [];          // โรงเรียนที่ล้มเหลวสะสม → ส่งให้ server ข้ามรอบถัดไป (กัน retry ไม่จบ)

        Swal.fire({
            title: 'กำลังเลื่อนชั้น…',
            html: '<div id="promoProg" style="font-size:.95rem">เริ่ม…</div>',
            allowOutsideClick: false, allowEscapeKey: false,
            didOpen: function () { Swal.showLoading(); }
        });

        // เรียกทีละ batch จนกว่าจะ done — ผลแต่ละโรงเรียนถูก commit แล้ว จึง resume ได้แม้หลุดกลางคัน
        function step() {
            var fd = new FormData();
            fd.append('action', 'promote_all');
            fd.append('confirm', confirmVal);
            fd.append('skip', skip.join(','));
            return fetch('admin_promote_all.php', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd })
                .then(function (x) { return x.json(); })
                .then(function (d) {
                    if (d.status !== 'ok') { throw new Error(d.message || 'ผิดพลาด'); }
                    totals.schools   += d.processed;
                    totals.promoted  += d.promoted;
                    totals.graduated += d.graduated;
                    if (d.failed && d.failed.length) { skip = skip.concat(d.failed); }
                    var pe = document.getElementById('promoProg');
                    if (pe) {
                        pe.innerHTML =
                            'เลื่อนแล้ว <b>' + totals.schools + '</b> โรงเรียน · ขึ้น <b>' + totals.promoted + '</b> คน · จบ <b>' + totals.graduated + '</b> คน'
                            + (skip.length ? '<br><span style="color:#c0392b">ล้มเหลว ' + skip.length + ' โรงเรียน (ข้ามไว้)</span>' : '')
                            + (d.remaining ? '<br>เหลืออีก ~' + d.remaining + ' โรงเรียน…' : '');
                    }
                    if (!d.done) { return step(); }   // ยังไม่ครบ → เรียก batch ถัดไป
                });
        }

        step().then(function () {
            Swal.fire({
                icon: skip.length ? 'warning' : 'success',
                title: skip.length ? 'เลื่อนชั้นเสร็จ (บางโรงเรียนล้มเหลว)' : 'เลื่อนชั้นทั้งระบบสำเร็จ',
                confirmButtonColor: '#4D96FF',
                html: '<b>' + totals.schools + '</b> โรงเรียน · เลื่อนขึ้น <b>' + totals.promoted + '</b> คน · จบการศึกษา <b>' + totals.graduated + '</b> คน'
                    + (skip.length ? '<br><br><span style="color:#c0392b">ล้มเหลว ' + skip.length + ' โรงเรียน: ' + skip.join(', ')
                        + '<br>กด “เลื่อนชั้นทั้งระบบ” ซ้ำเพื่อลองใหม่ หรือเลื่อนทีละโรงเรียนที่เมนูเลื่อนชั้น</span>' : '')
            }).then(function () { location.reload(); });
        }).catch(function (e) {
            Swal.fire({
                icon: 'error', title: 'หยุดกลางคัน', confirmButtonColor: '#4D96FF',
                html: ((e && e.message) ? e.message + '<br><br>' : '')
                    + 'ผลที่เลื่อนสำเร็จก่อนหน้านี้ถูกบันทึกแล้ว (' + totals.schools + ' โรงเรียน)<br>กด “เลื่อนชั้นทั้งระบบ” ซ้ำเพื่อทำต่อจากเดิมได้'
            });
        });
    });
});
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
