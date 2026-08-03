<?php
/**
 * admin_exam_control.php — เครื่องมือผู้ดูแล: เปิด/ปิดการสอบ "ทุกโรงเรียน" พร้อมกัน
 *   เก็บคำสั่งใน app_settings.exam_global_h{N} (open|closed|auto) — ทับค่ารายโรงเรียน
 *   ('auto' = ไม่บังคับ ปล่อยให้แต่ละโรงเรียนกำหนดเอง — พฤติกรรมเดิม)
 *
 *   POST action=set_global → บันทึกคำสั่งรายรอบ → JSON
 *   GET                    → หน้าจัดการ (สรุปทั้งระบบ)
 */
require __DIR__ . '/includes/admin_auth.php';

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'set_global') {
    $hit   = (int)($_POST['hittest'] ?? 0);
    $state = (string)($_POST['state'] ?? '');
    if (!valid_hit($hit)) {
        json_response(['status' => 'error', 'message' => 'รอบสอบไม่ถูกต้อง'], 400);
    }
    if (!in_array($state, ['open', 'closed', 'auto'], true)) {
        json_response(['status' => 'error', 'message' => 'สถานะไม่ถูกต้อง'], 400);
    }
    try {
        app_setting_set('exam_global_h' . $hit, $state);
    } catch (Throwable $e) {
        json_response(['status' => 'error', 'message' => 'บันทึกสถานะไม่สำเร็จ'], 500);
    }
    json_response(['status' => 'ok', 'hittest' => $hit, 'state' => $state]);
}

/* ---------- สรุปทั้งระบบ (ทุกโรงเรียน ปีปัจจุบัน) ---------- */
$stat = $pdo->prepare(
    'SELECT COUNT(*) total, COUNT(DISTINCT sc_id) schools,
            COALESCE(SUM(hit1tested),0) h1, COALESCE(SUM(hit2tested),0) h2, COALESCE(SUM(hit3tested),0) h3
     FROM students WHERE years = ? AND deleted_at IS NULL AND stustatus <> 4'
);
$stat->execute([current_year()]);
$stat = $stat->fetch() ?: ['total' => 0, 'schools' => 0, 'h1' => 0, 'h2' => 0, 'h3' => 0];
$total       = (int)$stat['total'];
$schoolCnt   = (int)$stat['schools'];
$testedByHit = [1 => (int)$stat['h1'], 2 => (int)$stat['h2'], 3 => (int)$stat['h3']];

// จำนวนโรงเรียนที่ตั้งค่า exam_window เอง (ไว้เตือนว่า 'auto' จะใช้ค่าของแต่ละโรงเรียน)
$customCnt = 0;
try {
    $customCnt = (int)$pdo->query('SELECT COUNT(DISTINCT sc_id) FROM exam_window WHERE is_open = 0')->fetchColumn();
} catch (Throwable $e) {
}

$states = exam_global_states();
$icons  = [1 => '📕', 2 => '📗', 3 => '📘'];

$labels = [
    'open'   => '🟢 บังคับเปิดทุกโรงเรียน',
    'closed' => '🔴 บังคับปิดทุกโรงเรียน',
    'auto'   => '⚪ ตามที่โรงเรียนตั้งเอง',
];

$page_title = 'จัดการสอบทุกโรงเรียน';
$active     = 'admin_examctl';
require __DIR__ . '/includes/header.php';
?>
<h2 class="mb-1">🚦 จัดการการเปิด/ปิดการสอบ — ทุกโรงเรียน</h2>
<p class="text-muted mb-4">คำสั่งนี้ <strong>ทับค่าของทุกโรงเรียน</strong> ในฐานข้อมูล <strong><?= htmlspecialchars(DB_NAME) ?></strong>
   (<?= number_format($schoolCnt) ?> โรงเรียน · <?= number_format($total) ?> คน) —
   <strong>บังคับเปิด/ปิด</strong> โรงเรียนแก้เองไม่ได้ · <strong>ตามที่โรงเรียนตั้งเอง</strong> คืนสิทธิ์ให้โรงเรียนกำหนด</p>

<div class="row g-4" style="max-width:1100px">
<?php foreach ([1, 2, 3] as $h):
    $st = $states[$h] ?? 'auto';
?>
    <div class="col-md-4">
        <div class="ht-card h-100 exam-gwin" data-hit="<?= $h ?>" data-state="<?= htmlspecialchars($st) ?>">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <h3 class="mb-0"><?= $icons[$h] ?> Hit-<?= $h ?></h3>
                <span class="ht-badge js-gstate <?= $st === 'open' ? 'ht-badge--done' : ($st === 'closed' ? 'ht-badge--missing' : 'ht-badge--pending') ?>">
                    <?= $labels[$st] ?>
                </span>
            </div>
            <p class="text-muted mb-3">สอบแล้ว <b style="color:var(--c-green-ink)"><?= number_format($testedByHit[$h]) ?></b> / <?= number_format($total) ?> คน (ทุกโรงเรียน)</p>
            <div class="d-grid gap-2">
                <button class="ht-btn ht-btn--sm js-gset" data-state="open"   data-hit="<?= $h ?>">🟢 บังคับเปิดทุกโรงเรียน</button>
                <button class="ht-btn ht-btn--sm ht-btn--coral js-gset" data-state="closed" data-hit="<?= $h ?>">🔴 บังคับปิดทุกโรงเรียน</button>
                <button class="ht-btn ht-btn--sm ht-btn--ghost js-gset" data-state="auto"   data-hit="<?= $h ?>">⚪ ตามที่โรงเรียนตั้งเอง</button>
            </div>
        </div>
    </div>
<?php endforeach; ?>
</div>

<?php if ($customCnt > 0): ?>
<p class="text-muted mt-3" style="max-width:1100px;font-size:.9rem">
    ℹ️ มี <strong><?= number_format($customCnt) ?></strong> โรงเรียนที่ตั้งค่าปิดบางรอบเอง —
    จะมีผลเฉพาะรอบที่เลือก "⚪ ตามที่โรงเรียนตั้งเอง" เท่านั้น
</p>
<?php endif; ?>

<script>
function applyGState(card, state) {
    card.dataset.state = state;
    var badge = card.querySelector('.js-gstate');
    var map = {
        open:   { txt: '🟢 บังคับเปิดทุกโรงเรียน', cls: 'ht-badge--done' },
        closed: { txt: '🔴 บังคับปิดทุกโรงเรียน', cls: 'ht-badge--missing' },
        auto:   { txt: '⚪ ตามที่โรงเรียนตั้งเอง', cls: 'ht-badge--pending' }
    };
    var m = map[state] || map.auto;
    badge.textContent = m.txt;
    badge.className   = 'ht-badge js-gstate ' + m.cls;
}
document.querySelectorAll('.js-gset').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var card  = btn.closest('.exam-gwin');
        var hit   = btn.dataset.hit;
        var state = btn.dataset.state;
        if (card.dataset.state === state) { return; }   // ไม่มีอะไรเปลี่ยน

        var titles = { open: 'บังคับเปิด', closed: 'บังคับปิด', auto: 'คืนสิทธิ์ให้โรงเรียน' };
        Swal.fire({
            icon: 'question',
            title: titles[state] + ' Hit-' + hit + ' ทุกโรงเรียน?',
            text: state === 'auto' ? 'แต่ละโรงเรียนจะกลับไปใช้ค่าที่ตั้งเอง' : 'ทับค่าของทุกโรงเรียน โรงเรียนจะแก้รอบนี้เองไม่ได้',
            showCancelButton: true, confirmButtonText: 'ยืนยัน', cancelButtonText: 'ยกเลิก',
            confirmButtonColor: state === 'closed' ? '#FF6B6B' : '#4D96FF'
        }).then(function (r) {
            if (!r.isConfirmed) { return; }
            var cardBtns = card.querySelectorAll('.js-gset');
            cardBtns.forEach(function (b) { b.disabled = true; });
            fetch('admin_exam_control.php', {
                method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: new URLSearchParams({ action: 'set_global', hittest: hit, state: state })
            })
                .then(function (x) { return x.json(); })
                .then(function (d) {
                    if (d.status === 'ok') {
                        applyGState(card, d.state);
                        Swal.fire({ toast: true, position: 'top-end', timer: 1800, showConfirmButton: false,
                            icon: 'success', title: 'บันทึกคำสั่ง Hit-' + hit + ' แล้ว' });
                    } else {
                        Swal.fire({ icon: 'error', title: 'ผิดพลาด', text: d.message || '', confirmButtonColor: '#4D96FF' });
                    }
                })
                .catch(function () { Swal.fire({ icon: 'error', title: 'ผิดพลาด', confirmButtonColor: '#4D96FF' }); })
                .finally(function () { cardBtns.forEach(function (b) { b.disabled = false; }); });
        });
    });
});
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
