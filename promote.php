<?php
/** promote.php — เลื่อนชั้นทั้งโรงเรียน (ป.1-5 → +1, ป.6 → ย้ายออก, เก็บผลสอบเดิม) */
require __DIR__ . '/includes/auth.php';

$scid = current_sc_id();

// การกระจายชั้นปัจจุบัน (นับเฉพาะที่ยังไม่ย้ายออก)
$byClass = db()->prepare('SELECT class_id, COUNT(*) total, SUM(stustatus = 4) movedout
    FROM students WHERE sc_id = ? GROUP BY class_id');
$byClass->execute([$scid]);
$active2 = [];
foreach ($byClass->fetchAll() as $r) {
    $active2[(int)$r['class_id']] = (int)$r['total'] - (int)$r['movedout'];
}

$promoteN = 0;
$graduateN = 0;
for ($c = 1; $c <= 5; $c++) {
    $promoteN += $active2[$c] ?? 0;
}
$graduateN = $active2[6] ?? 0;

// การเลื่อนชั้นครั้งล่าสุดที่ "ยังยกเลิกได้" (ยังไม่ถูกย้อน + มีรายละเอียดรายคน)
$lastStmt = db()->prepare(
    'SELECT pl.id, pl.promoted, pl.graduated, pl.created_at,
            (SELECT COUNT(*) FROM promote_log_item i WHERE i.log_id = pl.id) AS item_cnt
     FROM promote_log pl
     WHERE pl.sc_id = ? AND pl.rolled_back_at IS NULL
     ORDER BY pl.id DESC LIMIT 1'
);
$lastStmt->execute([$scid]);
$lastPromote = $lastStmt->fetch();
$canRollback = $lastPromote && (int)$lastPromote['item_cnt'] > 0;

/** แสดงวันที่เป็น พ.ศ. (เก็บเป็น ค.ศ.) */
function thai_dt(?string $dt): string
{
    if (!$dt) { return '-'; }
    $ts = strtotime($dt);
    return date('d/m/', $ts) . (date('Y', $ts) + 543) . date(' H:i', $ts);
}

$page_title = 'เลื่อนชั้นทั้งโรงเรียน';
$active = 'promote';
require __DIR__ . '/includes/header.php';
?>
<h2 class="mb-1">⬆️ เลื่อนชั้นทั้งโรงเรียน</h2>
<p class="text-muted mb-4">โรงเรียน <strong><?= htmlspecialchars($_SESSION['sc_name']) ?></strong></p>

<div class="ht-card" style="max-width:720px; border-left:6px solid var(--c-blue)">
    <h3 class="mb-3">สรุปก่อนเลื่อนชั้น</h3>
    <div class="ht-table-wrap mb-3">
        <table class="ht-table">
            <thead><tr><th>ชั้นปัจจุบัน</th><th class="num text-center">นักเรียน (ใช้งาน)</th><th>หลังเลื่อนชั้น</th></tr></thead>
            <tbody>
            <?php for ($c = 1; $c <= 6; $c++): ?>
                <tr>
                    <td>ป.<?= $c ?></td>
                    <td class="num text-center"><?= $active2[$c] ?? 0 ?></td>
                    <td class="text-muted"><?= $c < 6 ? ('→ ป.' . ($c + 1)) : '→ จบ (ย้ายออก)' ?></td>
                </tr>
            <?php endfor; ?>
            </tbody>
        </table>
    </div>
    <div class="ht-badge ht-badge--pending mb-3">เลื่อนขึ้น <?= $promoteN ?> คน · จบ→ย้ายออก <?= $graduateN ?> คน · เก็บผลสอบเดิมไว้</div>
    <p class="text-muted mb-3" style="font-size:.92rem">ป.1–5 เลื่อนขึ้น 1 ชั้น · ป.6 เปลี่ยนเป็น "ย้ายออก" (เก็บประวัติ + ผลสอบ) · ข้ามผู้ที่ย้ายออกแล้ว · <strong>ผลสอบเดิมไม่ถูกลบ</strong></p>
    <button id="btnPromote" class="ht-btn ht-btn--coral"<?= ($promoteN + $graduateN) === 0 ? ' disabled' : '' ?>>⬆️ เลื่อนชั้นทั้งโรงเรียน</button>
</div>

<?php if ($canRollback): ?>
<div class="ht-card mt-4" style="max-width:720px; border-left:6px solid var(--c-yellow)">
    <h3 class="mb-2">↩️ ยกเลิกการเลื่อนชั้น (เลิกทำครั้งล่าสุด)</h3>
    <p class="text-muted mb-3" style="font-size:.92rem">
        เลื่อนชั้นครั้งล่าสุดเมื่อ <strong><?= htmlspecialchars(thai_dt($lastPromote['created_at'])) ?></strong> ·
        เลื่อนขึ้น <strong><?= (int)$lastPromote['promoted'] ?></strong> คน ·
        จบ→ย้ายออก <strong><?= (int)$lastPromote['graduated'] ?></strong> คน<br>
        การยกเลิกจะคืนชั้นและสถานะของนักเรียนกลับเป็นค่าก่อนเลื่อน (ผลสอบไม่ถูกแตะต้อง)
    </p>
    <button id="btnRollback" class="ht-btn ht-btn--yellow">↩️ ยกเลิกการเลื่อนชั้นครั้งล่าสุด</button>
</div>
<?php endif; ?>

<script>
var SMIS = '<?= htmlspecialchars($_SESSION['sc_smis'], ENT_QUOTES) ?>';
var btn = document.getElementById('btnPromote');
if (btn) {
    btn.addEventListener('click', function () {
        Swal.fire({
            icon: 'warning', title: 'เลื่อนชั้นทั้งโรงเรียน?',
            html: 'ป.1–ป.5 เลื่อนขึ้น 1 ชั้น · <b>ป.6 จบ → ย้ายออก</b><br>ผลสอบเดิมจะถูกเก็บไว้ (ไม่ลบ)<br><br>พิมพ์รหัส <b>' + SMIS + '</b> เพื่อยืนยัน',
            input: 'text', inputPlaceholder: 'รหัส SMIS',
            showCancelButton: true, confirmButtonText: 'เลื่อนชั้น', confirmButtonColor: '#4D96FF', cancelButtonText: 'ยกเลิก',
            preConfirm: function (v) {
                if (String(v).trim() !== SMIS) { Swal.showValidationMessage('รหัสไม่ถูกต้อง'); return false; }
                return true;
            }
        }).then(function (r) {
            if (!r.isConfirmed) return;
            fetch('promote_school.php', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (x) { return x.json(); })
                .then(function (d) {
                    if (d.status === 'ok') {
                        Swal.fire({
                            icon: 'success', title: 'เลื่อนชั้นสำเร็จ', confirmButtonColor: '#4D96FF',
                            html: 'เลื่อนขึ้นชั้น <b>' + d.promoted + '</b> คน · จบการศึกษา (ป.6 → ย้ายออก) <b>' + d.graduated + '</b> คน'
                        }).then(function () { location.reload(); });
                    } else {
                        Swal.fire({ icon: 'error', title: 'ผิดพลาด', text: d.message || '', confirmButtonColor: '#4D96FF' });
                    }
                })
                .catch(function () { Swal.fire({ icon: 'error', title: 'ผิดพลาด', confirmButtonColor: '#4D96FF' }); });
        });
    });
}

var btnUndo = document.getElementById('btnRollback');
if (btnUndo) {
    btnUndo.addEventListener('click', function () {
        Swal.fire({
            icon: 'warning', title: 'ยกเลิกการเลื่อนชั้นครั้งล่าสุด?',
            html: 'นักเรียนจะถูกคืนกลับชั้น/สถานะก่อนเลื่อน<br>(ผลสอบเดิมไม่ถูกแตะต้อง)<br><br>พิมพ์รหัส <b>' + SMIS + '</b> เพื่อยืนยัน',
            input: 'text', inputPlaceholder: 'รหัส SMIS',
            showCancelButton: true, confirmButtonText: 'ยกเลิกการเลื่อนชั้น', confirmButtonColor: '#FF6B6B', cancelButtonText: 'ปิด',
            preConfirm: function (v) {
                if (String(v).trim() !== SMIS) { Swal.showValidationMessage('รหัสไม่ถูกต้อง'); return false; }
                return true;
            }
        }).then(function (r) {
            if (!r.isConfirmed) return;
            fetch('promote_rollback.php', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (x) { return x.json(); })
                .then(function (d) {
                    if (d.status === 'ok') {
                        Swal.fire({
                            icon: 'success', title: 'ยกเลิกการเลื่อนชั้นแล้ว', confirmButtonColor: '#4D96FF',
                            html: 'คืนสถานะนักเรียน <b>' + d.restored + '</b> คนกลับเป็นก่อนเลื่อน'
                        }).then(function () { location.reload(); });
                    } else {
                        Swal.fire({ icon: 'error', title: 'ยกเลิกไม่สำเร็จ', text: d.message || '', confirmButtonColor: '#4D96FF' });
                    }
                })
                .catch(function () { Swal.fire({ icon: 'error', title: 'ผิดพลาด', confirmButtonColor: '#4D96FF' }); });
        });
    });
}
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
