<?php
/** settings.php — ตั้งค่า: รีเซตผลการสอบ รายห้อง/รายโรงเรียน, ทุกรอบ/เฉพาะรอบ + ประวัติการรีเซต */
require __DIR__ . '/includes/auth.php';

$scid = current_sc_id();
$class_id = (int)($_GET['class_id'] ?? 1);
if ($class_id < 1 || $class_id > 6) {
    $class_id = 1;
}

// ห้อง + จำนวนต่อห้องของชั้นที่เลือก
$rs = db()->prepare('SELECT rooms, COUNT(*) c FROM students WHERE sc_id = ? AND class_id = ? GROUP BY rooms ORDER BY rooms');
$rs->execute([$scid, $class_id]);
$rooms = $rs->fetchAll();
$roomCount = [];
foreach ($rooms as $r) {
    $roomCount[(int)$r['rooms']] = (int)$r['c'];
}

// สรุปทั้งโรงเรียน
$tot = db()->prepare('SELECT COUNT(*) total, COALESCE(SUM(hit1tested+hit2tested+hit3tested),0) tested FROM students WHERE sc_id = ?');
$tot->execute([$scid]);
$tot = $tot->fetch();

// ประวัติการรีเซตล่าสุด
$logs = db()->prepare('SELECT * FROM reset_log WHERE sc_id = ? ORDER BY id DESC LIMIT 20');
$logs->execute([$scid]);
$logs = $logs->fetchAll();

/** datetime -> d/m/พ.ศ. H:i */
function fmt_be(?string $dt): string
{
    if (!$dt) return '-';
    $t = strtotime($dt);
    return date('d/m/', $t) . (date('Y', $t) + 543) . date(' H:i', $t);
}

$page_title = 'ตั้งค่า';
$active = 'settings';
require __DIR__ . '/includes/header.php';
?>
<h2 class="mb-1">⚙️ ตั้งค่า</h2>
<p class="text-muted mb-4">จัดการข้อมูลของโรงเรียน <strong><?= htmlspecialchars($_SESSION['sc_name']) ?></strong></p>

<div class="ht-card" style="border-left:6px solid var(--c-coral); max-width:820px">
    <h3 class="mb-1">🗑️ รีเซตผลการสอบ</h3>
    <div class="ht-badge ht-badge--pending mb-3">⚠️ ลบถาวร — กู้คืนไม่ได้</div>
    <p class="text-muted mb-3">ลบผลการสอบและคืนสถานะนักเรียนเป็น "ยังไม่สอบ"
        — ลบจาก evaluations, studenthit, studenteval และตั้งคะแนนใน students เป็น 0</p>

    <div class="ht-field" style="max-width:280px">
        <label class="ht-label">เลือกรอบที่จะรีเซต</label>
        <select id="hitSel" class="ht-select">
            <option value="0">ทุกรอบ (Hit-1/2/3)</option>
            <option value="1">เฉพาะ Hit-1</option>
            <option value="2">เฉพาะ Hit-2</option>
            <option value="3">เฉพาะ Hit-3</option>
        </select>
    </div>

    <!-- รีเซตรายห้อง -->
    <div class="mt-4 p-3" style="background:var(--bg-sky); border-radius:var(--r-md)">
        <h4 class="mb-3">รีเซตรายห้อง</h4>
        <div class="row g-3 align-items-end">
            <div class="col-sm-4">
                <div class="ht-field">
                    <label class="ht-label">ชั้น</label>
                    <select class="ht-select" onchange="location='settings.php?class_id='+this.value">
                        <?php for ($c = 1; $c <= 6; $c++): ?>
                            <option value="<?= $c ?>" <?= $c === $class_id ? 'selected' : '' ?>>ป.<?= $c ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>
            <div class="col-sm-4">
                <div class="ht-field">
                    <label class="ht-label">ห้อง</label>
                    <select id="roomSel" class="ht-select" onchange="updRoomCnt()">
                        <?php if (!$rooms): ?>
                            <option value="">— ไม่มีนักเรียน —</option>
                        <?php else: foreach ($rooms as $r): ?>
                            <option value="<?= (int)$r['rooms'] ?>">ห้อง <?= (int)$r['rooms'] ?></option>
                        <?php endforeach; endif; ?>
                    </select>
                </div>
            </div>
            <div class="col-sm-4">
                <button class="ht-btn ht-btn--coral ht-btn--block" onclick="resetRoom()" <?= $rooms ? '' : 'disabled' ?>>รีเซตห้องนี้</button>
            </div>
        </div>
        <p class="mt-2 mb-0 text-muted" id="roomInfo"></p>
    </div>

    <!-- รีเซตทั้งโรงเรียน -->
    <div class="mt-3 p-3" style="background:var(--c-coral-soft); border-radius:var(--r-md)">
        <h4 class="mb-2">รีเซตทั้งโรงเรียน</h4>
        <p class="mb-3">นักเรียนทั้งหมด <strong><?= (int)$tot['total'] ?></strong> คน · มีผลสอบบันทึกไว้ <strong><?= (int)$tot['tested'] ?></strong> รายการ (รวมทุกรอบ)</p>
        <button class="ht-btn ht-btn--coral" onclick="resetSchool()">รีเซตผลสอบทั้งโรงเรียน</button>
    </div>
</div>

<!-- ประวัติการรีเซต -->
<div class="mt-5" style="max-width:820px">
    <h3 class="mb-3">🕘 ประวัติการรีเซต <span class="text-muted" style="font-size:1rem;font-weight:500">(ล่าสุด <?= count($logs) ?> รายการ)</span></h3>
    <div class="ht-table-wrap">
        <table class="ht-table">
            <thead><tr><th>เวลา</th><th>โดย</th><th>ขอบเขต</th><th>รอบ</th><th class="num text-center">นักเรียน</th><th class="num text-center">ลบ (รายคำ/สรุป)</th></tr></thead>
            <tbody>
            <?php if (!$logs): ?>
                <tr><td colspan="6" class="text-center text-muted" style="padding:30px">ยังไม่มีประวัติการรีเซต</td></tr>
            <?php else: foreach ($logs as $lg):
                $scope = $lg['scope'] === 'room' ? ('ป.' . (int)$lg['class_id'] . ' ห้อง ' . (int)$lg['rooms']) : 'ทั้งโรงเรียน';
                $round = $lg['hittest'] === null ? 'ทุกรอบ' : ('Hit-' . (int)$lg['hittest']);
            ?>
                <tr>
                    <td class="num"><?= fmt_be($lg['created_at']) ?></td>
                    <td><?= htmlspecialchars($lg['sc_smis'] ?? '') ?></td>
                    <td><span class="ht-badge <?= $lg['scope'] === 'school' ? 'ht-badge--special' : 't-blue' ?>"><?= htmlspecialchars($scope) ?></span></td>
                    <td><?= htmlspecialchars($round) ?></td>
                    <td class="num text-center"><?= (int)$lg['students_affected'] ?></td>
                    <td class="num text-center"><?= (int)$lg['eval_deleted'] ?> / <?= (int)$lg['studenthit_deleted'] ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
var ROOMCNT = <?= json_encode($roomCount, JSON_UNESCAPED_UNICODE) ?>;
var SMIS  = '<?= htmlspecialchars($_SESSION['sc_smis'], ENT_QUOTES) ?>';
var CLASS = <?= $class_id ?>;

function roundLabel() {
    var v = document.getElementById('hitSel').value;
    return v === '0' ? 'ทุกรอบ' : ('Hit-' + v);
}
function updRoomCnt() {
    var sel = document.getElementById('roomSel'), v = sel ? sel.value : '';
    document.getElementById('roomInfo').innerText = v ? ('ป.' + CLASS + ' ห้อง ' + v + ' มีนักเรียน ' + (ROOMCNT[v] || 0) + ' คน') : '';
}
updRoomCnt();

function runReset(extra, cfg) {
    var hit = document.getElementById('hitSel').value;
    Swal.fire(Object.assign({
        icon: 'warning', showCancelButton: true,
        confirmButtonText: 'รีเซต', confirmButtonColor: '#FF6B6B', cancelButtonText: 'ยกเลิก'
    }, cfg)).then(function (r) {
        if (!r.isConfirmed) return;
        var body = new URLSearchParams(Object.assign({ hittest: hit }, extra));
        fetch('reset_results.php', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: body })
            .then(function (x) { return x.json(); })
            .then(function (d) {
                if (d.status === 'ok') {
                    Swal.fire({
                        icon: 'success', title: 'รีเซตสำเร็จ', confirmButtonColor: '#4D96FF',
                        html: '<b>' + d.label + ' · ' + d.round + '</b> — รีเซต ' + d.students + ' คน<br>'
                            + 'ลบผลรายคำ ' + d.evaluations + ' · studenthit ' + d.studenthit + ' · สรุป ' + d.studenteval + ' แถว'
                    }).then(function () { location.reload(); });
                } else {
                    Swal.fire({ icon: 'error', title: 'ผิดพลาด', text: d.message || '', confirmButtonColor: '#4D96FF' });
                }
            })
            .catch(function () { Swal.fire({ icon: 'error', title: 'ผิดพลาด', confirmButtonColor: '#4D96FF' }); });
    });
}

function resetRoom() {
    var room = document.getElementById('roomSel').value;
    if (!room) return;
    runReset({ mode: 'room', class_id: CLASS, rooms: room }, {
        title: 'รีเซต ป.' + CLASS + ' ห้อง ' + room + ' (' + roundLabel() + ')?',
        html: 'ลบผลการสอบ <b>' + roundLabel() + '</b> ของนักเรียน ' + (ROOMCNT[room] || 0) + ' คนในห้องนี้ — <b>ลบถาวร</b>'
    });
}

function resetSchool() {
    runReset({ mode: 'school' }, {
        title: 'รีเซตทั้งโรงเรียน (' + roundLabel() + ')?',
        html: 'ลบผลสอบ <b>' + roundLabel() + '</b> ทั้งโรงเรียนถาวร<br>พิมพ์รหัส <b>' + SMIS + '</b> เพื่อยืนยัน',
        input: 'text', inputPlaceholder: 'รหัส SMIS',
        preConfirm: function (v) {
            if (String(v).trim() !== SMIS) { Swal.showValidationMessage('รหัสไม่ถูกต้อง'); return false; }
            return true;
        }
    });
}
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
