<?php
/** exam_control.php — จัดการเปิด/ปิดการสอบ Hit-1/2/3 (เฉพาะโรงเรียนที่ login) */
require __DIR__ . '/includes/auth.php';

$scid = current_sc_id();

// สถานะหน้าต่างสอบของโรงเรียนนี้ + เวลาอัปเดต (ไม่มีแถว = เปิดตามค่าเริ่มต้น)
$rows = db()->prepare('SELECT hittest, is_open, updated_by, updated_at FROM exam_window WHERE sc_id = ?');
$rows->execute([$scid]);
$win = [];
foreach ($rows->fetchAll() as $r) {
    $win[(int)$r['hittest']] = $r;
}

// จำนวนนักเรียน + สอบแล้วต่อรอบ (ของโรงเรียนนี้)
$stat = db()->prepare('SELECT COUNT(*) total,
        COALESCE(SUM(hit1tested),0) h1, COALESCE(SUM(hit2tested),0) h2, COALESCE(SUM(hit3tested),0) h3
    FROM students WHERE sc_id = ? AND years = ? AND deleted_at IS NULL AND stustatus <> 4');
$stat->execute([$scid, current_year()]);
$stat = $stat->fetch();
$total = (int)$stat['total'];
$testedByHit = [1 => (int)$stat['h1'], 2 => (int)$stat['h2'], 3 => (int)$stat['h3']];

function fmt_be2(?string $dt): string
{
    if (!$dt) {
        return '';
    }
    $t = strtotime($dt);
    return 'อัปเดต ' . date('d/m/', $t) . (date('Y', $t) + 543) . date(' H:i น.', $t);
}

$icons = [1 => '📕', 2 => '📗', 3 => '📘'];

// คำสั่งบังคับจากผู้ดูแลส่วนกลาง (ทับค่าโรงเรียน) — ถ้า != 'auto' โรงเรียนแก้รอบนั้นเองไม่ได้
$globalStates = exam_global_states();

$page_title = 'จัดการสอบ';
$active = 'examctl';
require __DIR__ . '/includes/header.php';
?>
<h2 class="mb-1">🚦 จัดการการเปิด/ปิดการสอบ</h2>
<p class="text-muted mb-4">โรงเรียน <strong><?= htmlspecialchars($_SESSION['sc_name']) ?></strong>
   — เปิด/ปิดได้เฉพาะโรงเรียนของท่าน เมื่อ "ปิด" ครูจะกด "เข้าทดสอบ" รอบนั้นไม่ได้ (ผลที่บันทึกแล้วยังดูได้ปกติ)</p>

<div class="row g-4" style="max-width:980px">
<?php foreach ([1, 2, 3] as $h):
    $schoolOpen = isset($win[$h]) ? ((int)$win[$h]['is_open'] === 1) : true;   // ค่าเริ่มต้น = เปิด
    $gstate     = $globalStates[$h] ?? 'auto';
    $forced     = $gstate !== 'auto';
    $isOpen     = $forced ? ($gstate === 'open') : $schoolOpen;   // สถานะที่มีผลจริง
    $upd        = isset($win[$h]) ? fmt_be2($win[$h]['updated_at']) : '';
?>
    <div class="col-md-4">
        <div class="ht-card exam-win h-100" data-hit="<?= $h ?>" data-open="<?= $isOpen ? 1 : 0 ?>">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <h3 class="mb-0"><?= $icons[$h] ?> Hit-<?= $h ?></h3>
                <span class="ht-badge js-status <?= $isOpen ? 'ht-badge--done' : 'ht-badge--missing' ?>">
                    <?= $isOpen ? '🟢 เปิดสอบอยู่' : '🔴 ปิดสอบอยู่' ?>
                </span>
            </div>
            <p class="text-muted mb-1">สอบแล้ว <b style="color:var(--c-green-ink)"><?= $testedByHit[$h] ?></b> / <?= $total ?> คน</p>
            <p class="text-muted mb-3" style="font-size:.85rem;min-height:1.2em"><?= htmlspecialchars($upd) ?></p>
            <?php if ($forced): ?>
                <div class="ht-badge ht-badge--special mb-2" style="white-space:normal">🔧 ส่วนกลาง<?= $gstate === 'open' ? 'บังคับเปิด' : 'บังคับปิด' ?> รอบนี้ — ปรับเองไม่ได้</div>
                <?php if (is_admin()): /* ผู้ดูแลระบบแก้คำสั่งส่วนกลางได้ — พาไปหน้านั้นแทนปุ่มตาย */ ?>
                <a class="ht-btn ht-btn--sm ht-btn--block" href="admin_exam_control.php">🔧 ไปแก้คำสั่งส่วนกลาง</a>
                <?php else: ?>
                <button class="ht-btn ht-btn--sm ht-btn--block" disabled>🔒 ควบคุมโดยส่วนกลาง</button>
                <?php endif; ?>
            <?php else: ?>
                <button class="ht-btn ht-btn--sm ht-btn--block js-toggle <?= $isOpen ? 'ht-btn--coral' : '' ?>" data-hit="<?= $h ?>">
                    <?= $isOpen ? 'ปิดการสอบ' : 'เปิดการสอบ' ?>
                </button>
            <?php endif; ?>
        </div>
    </div>
<?php endforeach; ?>
</div>

<script>
function applyState(card, open) {
    card.dataset.open = open ? '1' : '0';
    var badge = card.querySelector('.js-status');
    var btn   = card.querySelector('.js-toggle');
    badge.textContent = open ? '🟢 เปิดสอบอยู่' : '🔴 ปิดสอบอยู่';
    badge.className   = 'ht-badge js-status ' + (open ? 'ht-badge--done' : 'ht-badge--missing');
    btn.textContent   = open ? 'ปิดการสอบ' : 'เปิดการสอบ';
    btn.className     = 'ht-btn ht-btn--sm ht-btn--block js-toggle' + (open ? ' ht-btn--coral' : '');
}
document.querySelectorAll('.js-toggle').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var card   = btn.closest('.exam-win');
        var hit    = card.dataset.hit;
        var target = card.dataset.open === '1' ? 0 : 1;   // สลับสถานะ
        btn.disabled = true;
        fetch('exam_window_toggle.php', {
            method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: new URLSearchParams({ hittest: hit, is_open: target })
        })
            .then(function (x) { return x.json(); })
            .then(function (d) {
                if (d.status === 'ok') {
                    applyState(card, d.is_open === 1);
                    Swal.fire({ toast: true, position: 'top-end', timer: 1800, showConfirmButton: false,
                        icon: 'success', title: (d.is_open ? 'เปิด' : 'ปิด') + 'การสอบ Hit-' + hit + ' แล้ว' });
                } else {
                    Swal.fire({ icon: 'error', title: 'ผิดพลาด', text: d.message || '', confirmButtonColor: '#4D96FF' });
                }
            })
            .catch(function () { Swal.fire({ icon: 'error', title: 'ผิดพลาด', confirmButtonColor: '#4D96FF' }); })
            .finally(function () { btn.disabled = false; });
    });
});
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
