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
    if (($_POST['confirm'] ?? '') !== DB_NAME) {
        json_response(['status' => 'error', 'message' => 'พิมพ์ชื่อฐานข้อมูลยืนยันไม่ถูกต้อง'], 400);
    }
    try {
        @set_time_limit(0);
        $pdo->beginTransaction();

        $schools = $pdo->prepare(
            'SELECT DISTINCT sc_id FROM students WHERE years = ? AND class_id BETWEEN 1 AND 6 AND stustatus <> 4'
        );
        $schools->execute([$fromYear]);
        $schoolIds = $schools->fetchAll(PDO::FETCH_COLUMN);

        $info = $pdo->prepare('SELECT sc_smis, sc_name FROM schools WHERE sc_id = ? LIMIT 1');
        $totP = 0;
        $totG = 0;
        foreach ($schoolIds as $sc) {
            $info->execute([$sc]);
            $sch = $info->fetch() ?: ['sc_smis' => null, 'sc_name' => null];
            $r = promote_school_year($pdo, (string)$sc, $fromYear, $toYear, $sch['sc_smis'], $sch['sc_name']);
            $totP += $r['promoted'];
            $totG += $r['graduated'];
        }

        $pdo->commit();
        json_response(['status' => 'ok', 'schools' => count($schoolIds), 'promoted' => $totP, 'graduated' => $totG]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        json_response(['status' => 'error', 'message' => 'เลื่อนชั้นทั้งระบบไม่สำเร็จ'], 500);
    }
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
        preConfirm: function (v) { if (String(v).trim() !== DBNAME) { Swal.showValidationMessage('ชื่อไม่ถูกต้อง'); return false; } return true; }
    }).then(function (r) {
        if (!r.isConfirmed) return;
        Swal.fire({ title: 'กำลังเลื่อนชั้น…', allowOutsideClick: false, didOpen: function () { Swal.showLoading(); } });
        var fd = new FormData(); fd.append('action', 'promote_all'); fd.append('confirm', String(r.value).trim());
        fetch('admin_promote_all.php', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd })
            .then(function (x) { return x.json(); })
            .then(function (d) {
                if (d.status === 'ok') {
                    Swal.fire({ icon: 'success', title: 'เลื่อนชั้นทั้งระบบสำเร็จ', confirmButtonColor: '#4D96FF',
                        html: '<b>' + d.schools + '</b> โรงเรียน · เลื่อนขึ้น <b>' + d.promoted + '</b> คน · จบการศึกษา <b>' + d.graduated + '</b> คน'
                    }).then(function () { location.reload(); });
                } else { Swal.fire({ icon: 'error', title: 'ผิดพลาด', text: d.message || '', confirmButtonColor: '#4D96FF' }); }
            })
            .catch(function () { Swal.fire({ icon: 'error', title: 'ผิดพลาด', confirmButtonColor: '#4D96FF' }); });
    });
});
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
