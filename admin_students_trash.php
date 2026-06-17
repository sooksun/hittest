<?php
/**
 * admin_students_trash.php — ถังขยะนักเรียนที่ถูกลบ (soft delete) — กู้คืนได้เฉพาะผู้ดูแลระบบ
 *   • แสดงนักเรียนทุกโรงเรียนที่ deleted_at มีค่า (ลบแบบซ่อน) พร้อมชื่อโรงเรียน/ปี/เวลาที่ลบ
 *   • POST action=restore (stuid, years) → คืนค่า deleted_at=NULL → นักเรียนกลับมาแสดงในระบบ
 *   gate: admin_auth (require_admin) — เฉพาะผู้ดูแลระบบ
 */
require __DIR__ . '/includes/admin_auth.php';

// POST: กู้คืนนักเรียน 1 คน
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'restore') {
    $stuid = (string)($_POST['stuid'] ?? '');
    $years = (int)($_POST['years'] ?? 0);
    if ($stuid === '' || $years < 1) {
        json_response(['status' => 'error', 'message' => 'ข้อมูลไม่ครบ'], 400);
    }
    try {
        $st = db()->prepare('UPDATE students SET deleted_at = NULL, deleted_by = NULL WHERE stuid = ? AND years = ? AND deleted_at IS NOT NULL');
        $st->execute([$stuid, $years]);
        if ($st->rowCount() === 0) {
            json_response(['status' => 'error', 'message' => 'ไม่พบนักเรียนที่ถูกลบ (อาจกู้คืนไปแล้ว)'], 404);
        }
    } catch (Throwable $e) {
        json_response(['status' => 'error', 'message' => 'กู้คืนไม่สำเร็จ'], 500);
    }
    json_response(['status' => 'ok']);
}

$q = trim((string)($_GET['q'] ?? ''));
$sql = 'SELECT s.stuid, s.stuname, s.class_id, s.rooms, s.years, s.deleted_at, s.deleted_by,
               sc.sc_name, sc.sc_smis
        FROM students s
        LEFT JOIN schools sc ON sc.sc_id = s.sc_id
        WHERE s.deleted_at IS NOT NULL';
$params = [];
if ($q !== '') {
    $sql .= ' AND (s.stuid LIKE ? OR s.stuname LIKE ? OR sc.sc_name LIKE ? OR sc.sc_smis LIKE ?)';
    $like = '%' . $q . '%';
    $params = [$like, $like, $like, $like];
}
$sql .= ' ORDER BY s.deleted_at DESC LIMIT 500';
$st = db()->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

function thai_dt_trash(?string $dt): string
{
    if (!$dt) { return '-'; }
    $ts = strtotime($dt);
    return date('d/m/', $ts) . (date('Y', $ts) + 543) . date(' H:i', $ts);
}

$page_title = 'ถังขยะนักเรียน';
$active = 'admin_trash';
require __DIR__ . '/includes/header.php';
?>
<style>.ht-container { max-width: 1200px; }</style>
<div class="ht-row mb-3" style="gap:16px; align-items:center">
    <h2 class="mb-0">🗑️ ถังขยะนักเรียน (ลบแบบซ่อน)</h2>
    <form method="get" class="ht-row" style="gap:8px; margin-left:auto">
        <input class="ht-input" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="ค้นหา ชื่อ/รหัส/โรงเรียน" style="width:280px">
        <button class="ht-btn ht-btn--sm" type="submit">🔍 ค้นหา</button>
    </form>
</div>
<p class="text-muted mb-4">นักเรียนที่โรงเรียนลบออก (ยังเก็บข้อมูล/ผลสอบไว้) — กดกู้คืนเพื่อให้กลับมาแสดงในระบบ · แสดงล่าสุด 500 รายการ</p>

<div class="ht-table-wrap">
    <table class="ht-table">
        <thead>
            <tr>
                <th>ชื่อ - สกุล</th><th>รหัสนักเรียน</th><th>โรงเรียน</th>
                <th class="text-center">ชั้น</th><th class="text-center">ปี (พ.ศ.)</th>
                <th>ลบเมื่อ</th><th>ลบโดย</th><th class="text-center">จัดการ</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$rows): ?>
            <tr><td colspan="8" class="text-center text-muted" style="padding:40px">— ไม่มีนักเรียนที่ถูกลบ —</td></tr>
        <?php else: foreach ($rows as $r): ?>
            <tr>
                <td class="fw-7"><?= htmlspecialchars((string)$r['stuname']) ?></td>
                <td class="num"><?= htmlspecialchars((string)$r['stuid']) ?></td>
                <td><?= htmlspecialchars((string)($r['sc_name'] ?? '-')) ?> <span class="text-muted">(<?= htmlspecialchars((string)($r['sc_smis'] ?? '')) ?>)</span></td>
                <td class="text-center">ป.<?= (int)$r['class_id'] ?></td>
                <td class="text-center"><?= (int)$r['years'] ?></td>
                <td><?= htmlspecialchars(thai_dt_trash($r['deleted_at'])) ?></td>
                <td class="text-muted"><?= htmlspecialchars((string)($r['deleted_by'] ?? '-')) ?></td>
                <td class="text-center">
                    <button type="button" class="ht-btn ht-btn--sm js-restore"
                        data-stuid="<?= htmlspecialchars((string)$r['stuid'], ENT_QUOTES) ?>"
                        data-years="<?= (int)$r['years'] ?>"
                        data-stuname="<?= htmlspecialchars((string)$r['stuname'], ENT_QUOTES) ?>">♻️ กู้คืน</button>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<script>
document.querySelectorAll('.js-restore').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var stuid = this.dataset.stuid, years = this.dataset.years, stuname = this.dataset.stuname || '';
        Swal.fire({
            title: 'กู้คืนนักเรียนคนนี้?',
            html: 'คืน <b>' + stuname + '</b> กลับเข้าระบบ (ปี ' + years + ')',
            icon: 'question', showCancelButton: true,
            confirmButtonText: 'กู้คืน', cancelButtonText: 'ยกเลิก', confirmButtonColor: '#4D96FF'
        }).then(function (r) {
            if (!r.isConfirmed) return;
            fetch('admin_students_trash.php', {
                method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: new URLSearchParams({ action: 'restore', stuid: stuid, years: years })
            })
                .then(function (x) { return x.json(); })
                .then(function (d) {
                    if (d.status === 'ok') {
                        Swal.fire({ icon: 'success', title: 'กู้คืนแล้ว', confirmButtonColor: '#4D96FF' })
                            .then(function () { location.reload(); });
                    } else {
                        Swal.fire({ icon: 'error', title: 'ผิดพลาด', text: d.message || '', confirmButtonColor: '#4D96FF' });
                    }
                })
                .catch(function () { Swal.fire({ icon: 'error', title: 'ผิดพลาด', confirmButtonColor: '#4D96FF' }); });
        });
    });
});
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
