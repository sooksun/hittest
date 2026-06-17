<?php
/** students_list.php — รายชื่อนักเรียน + สถานะ Hit-1/2/3 (ธีม Playful) */
require __DIR__ . '/includes/auth.php';

$class_id = (int)($_GET['class_id'] ?? 1);
if ($class_id < 1 || $class_id > 6) {
    $class_id = 1;
}

$sc = current_sc_id();
$yr = current_year();

// ห้องที่มีอยู่จริงของชั้นที่เลือก (ไว้สร้างตัวกรองห้อง)
$roomStmt = db()->prepare('SELECT DISTINCT rooms FROM students WHERE sc_id = ? AND years = ? AND class_id = ? AND rooms IS NOT NULL AND deleted_at IS NULL AND stustatus <> 4 ORDER BY rooms');
$roomStmt->execute([$sc, $yr, $class_id]);
$roomList = array_map('intval', $roomStmt->fetchAll(PDO::FETCH_COLUMN));

// ห้องที่เลือก (ว่าง = ทุกห้อง) — ถ้าเลือกห้องที่ไม่มีในชั้นนี้ (เช่นเพิ่งสลับชั้น) ให้ถือเป็นทุกห้อง
$room = (isset($_GET['rooms']) && $_GET['rooms'] !== '') ? (int)$_GET['rooms'] : null;
if ($room !== null && !in_array($room, $roomList, true)) {
    $room = null;
}

// ซ่อนนักเรียนที่ "ลบ" (deleted_at) และ "ย้ายออก" (stustatus = 4) ออกจากรายชื่อสอบ
$sql    = 'SELECT * FROM students WHERE sc_id = ? AND years = ? AND class_id = ? AND deleted_at IS NULL AND stustatus <> 4';
$params = [$sc, $yr, $class_id];
if ($room !== null) {
    $sql .= ' AND rooms = ?';
    $params[] = $room;
}
$sql .= ' ORDER BY rooms, stuname';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();

$page_title = 'สอบอ่านไทย';
$active = 'exam';
require __DIR__ . '/includes/header.php';
?>
<style>/* หน้ารายชื่อ: ขยายกว้างพิเศษ (ค่าปกติ .ht-container = 1180px) */
.ht-container { max-width: 1400px; }</style>
<div class="ht-row mb-4" style="gap:16px">
    <h2 class="mb-0">📖 สอบอ่านไทย</h2>
    <form method="get" class="ht-row" style="gap:8px">
        <label class="ht-label mb-0">เลือกชั้น</label>
        <select name="class_id" class="ht-select" style="width:auto" onchange="this.form.submit()">
            <?php for ($c = 1; $c <= 6; $c++): ?>
                <option value="<?= $c ?>" <?= $c === $class_id ? 'selected' : '' ?>>ป.<?= $c ?></option>
            <?php endfor; ?>
        </select>
        <label class="ht-label mb-0">ห้อง</label>
        <select name="rooms" class="ht-select" style="width:auto" onchange="this.form.submit()">
            <option value="">ทุกห้อง</option>
            <?php foreach ($roomList as $r): ?>
                <option value="<?= $r ?>" <?= $room === $r ? 'selected' : '' ?>>ห้อง <?= $r ?></option>
            <?php endforeach; ?>
        </select>
    </form>
    <span class="ht-badge t-blue">พบ <?= count($students) ?> คน</span>
    <button type="button" class="ht-btn ht-btn--sm" id="btnAddStudent"
        data-class="<?= $class_id ?>" data-room="<?= $room ?? '' ?>" style="margin-left:auto">➕ เพิ่มนักเรียน</button>
</div>

<div class="ht-table-wrap ht-table-wrap--cards">
    <table class="ht-table ht-table--cards">
        <thead>
            <tr>
                <th class="num">#</th><th>ชื่อ - สกุล</th><th class="num">ห้อง</th>
                <th class="text-center">Hit-1</th><th class="text-center">Hit-2</th><th class="text-center">Hit-3</th>
                <th>ประเภท</th>
                <th class="text-center">จัดการ</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$students): ?>
            <tr><td colspan="8" class="text-center text-muted" style="padding:40px">ยังไม่มีนักเรียนในชั้นนี้</td></tr>
        <?php else: foreach ($students as $i => $s):
            $special = (int)$s['stustatus'] === 2; ?>
            <tr>
                <td class="num text-muted cardhide" data-label="#"><?= $i + 1 ?></td>
                <td class="fw-7 cardhead"><?= htmlspecialchars($s['stuname']) ?></td>
                <td class="num text-center" data-label="ห้อง"><?= (int)$s['rooms'] ?></td>
                <td class="text-center" data-label="Hit-1"><?= hit_cell($s, 1, $class_id) ?></td>
                <td class="text-center" data-label="Hit-2"><?= hit_cell($s, 2, $class_id) ?></td>
                <td class="text-center" data-label="Hit-3"><?= hit_cell($s, 3, $class_id) ?></td>
                <td data-label="ประเภท">
                    <span class="ht-badge <?= $special ? 'ht-badge--special' : 'ht-badge--missing' ?>">
                        <?= htmlspecialchars(status_name((int)$s['stustatus'])) ?>
                    </span>
                </td>
                <td class="text-center" data-label="จัดการ">
                    <button type="button" class="ht-btn ht-btn--ghost ht-btn--sm js-edit"
                        data-stuid="<?= htmlspecialchars($s['stuid'], ENT_QUOTES) ?>"
                        data-stuname="<?= htmlspecialchars((string)$s['stuname'], ENT_QUOTES) ?>"
                        data-class="<?= (int)$s['class_id'] ?>"
                        data-rooms="<?= (int)$s['rooms'] ?>"
                        data-status="<?= (int)$s['stustatus'] ?>"
                        data-set1="<?= max(1, min(5, (int)$s['sethit1'] ?: 1)) ?>"
                        data-set2="<?= max(1, min(5, (int)$s['sethit2'] ?: 1)) ?>"
                        data-set3="<?= max(1, min(5, (int)$s['sethit3'] ?: 1)) ?>">✏️ แก้ไข</button>
                    <?php if ((int)$s['stustatus'] !== 4): ?>
                    <button type="button" class="ht-btn ht-btn--ghost ht-btn--sm js-moveout"
                        data-stuid="<?= htmlspecialchars($s['stuid'], ENT_QUOTES) ?>"
                        data-stuname="<?= htmlspecialchars((string)$s['stuname'], ENT_QUOTES) ?>">📤 ย้ายออก</button>
                    <?php endif; ?>
                    <button type="button" class="ht-btn ht-btn--ghost ht-btn--sm js-delete" style="color:var(--c-coral-ink)"
                        data-stuid="<?= htmlspecialchars($s['stuid'], ENT_QUOTES) ?>"
                        data-stuname="<?= htmlspecialchars((string)$s['stuname'], ENT_QUOTES) ?>">🗑️ ลบ</button>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<!-- โมดัลแก้ไขข้อมูลนักเรียน -->
<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border:none;border-radius:var(--r-lg);overflow:hidden">
      <div class="modal-header" style="border-bottom:1px solid #eee">
        <h5 class="modal-title fw-8 mb-0">✏️ แก้ไขข้อมูลนักเรียน</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button>
      </div>
      <div class="modal-body">
        <form id="editForm">
          <input type="hidden" name="stuid" id="e_stuid">
          <div class="ht-field mb-3">
            <label class="ht-label">รหัสนักเรียน</label>
            <input class="ht-input" id="e_stuid_show" disabled style="background:#F4F5F8">
          </div>
          <div class="ht-field mb-3">
            <label class="ht-label">ชื่อ - สกุล</label>
            <input class="ht-input" name="stuname" id="e_stuname" maxlength="255" required>
          </div>
          <div class="row g-3 mb-3">
            <div class="col-6"><div class="ht-field">
              <label class="ht-label">ชั้น</label>
              <select class="ht-select" name="class_id" id="e_class">
                <?php for ($c = 1; $c <= 6; $c++): ?><option value="<?= $c ?>">ป.<?= $c ?></option><?php endfor; ?>
              </select>
            </div></div>
            <div class="col-6"><div class="ht-field">
              <label class="ht-label">ห้อง</label>
              <input type="number" min="1" step="1" class="ht-input" name="rooms" id="e_rooms" required>
            </div></div>
          </div>
          <div class="ht-field mb-3">
            <label class="ht-label">ประเภท</label>
            <select class="ht-select" name="stustatus" id="e_status">
              <?php foreach (STU_STATUS as $sid => $sname): ?><option value="<?= $sid ?>"><?= htmlspecialchars($sname) ?></option><?php endforeach; ?>
            </select>
          </div>
          <label class="ht-label mb-2">ชุดคำที่ใช้แต่ละรอบ (1–5)</label>
          <div class="row g-3">
            <?php foreach ([1, 2, 3] as $hn): ?>
            <div class="col-4"><div class="ht-field">
              <label class="ht-label" style="font-weight:600;color:var(--ink-soft)">Hit-<?= $hn ?></label>
              <select class="ht-select" name="sethit<?= $hn ?>" id="e_set<?= $hn ?>">
                <?php for ($v = 1; $v <= 5; $v++): ?><option value="<?= $v ?>">ชุด <?= $v ?></option><?php endfor; ?>
              </select>
            </div></div>
            <?php endforeach; ?>
          </div>
        </form>
      </div>
      <div class="modal-footer" style="border-top:1px solid #eee">
        <button type="button" class="ht-btn ht-btn--ghost ht-btn--sm" data-bs-dismiss="modal">ยกเลิก</button>
        <button type="button" class="ht-btn ht-btn--sm" id="e_save">💾 บันทึก</button>
      </div>
    </div>
  </div>
</div>

<!-- โมดัลเพิ่มนักเรียนรายคน -->
<div class="modal fade" id="addModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border:none;border-radius:var(--r-lg);overflow:hidden">
      <div class="modal-header" style="border-bottom:1px solid #eee">
        <h5 class="modal-title fw-8 mb-0">➕ เพิ่มนักเรียน</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button>
      </div>
      <div class="modal-body">
        <form id="addForm">
          <div class="ht-field mb-3">
            <label class="ht-label">ชื่อ - สกุล</label>
            <input class="ht-input" name="stuname" id="a_stuname" maxlength="255" required>
          </div>
          <div class="ht-field mb-3">
            <label class="ht-label">รหัสนักเรียน <span class="text-muted" style="font-weight:400">(เว้นว่าง = ระบบกำหนดให้)</span></label>
            <input class="ht-input" name="stuid" id="a_stuid" inputmode="numeric" pattern="\d*" placeholder="เลขบัตรประชาชน 13 หลัก หรือเว้นว่าง">
          </div>
          <div class="row g-3 mb-3">
            <div class="col-6"><div class="ht-field">
              <label class="ht-label">ชั้น</label>
              <select class="ht-select" name="class_id" id="a_class">
                <?php for ($c = 1; $c <= 6; $c++): ?><option value="<?= $c ?>">ป.<?= $c ?></option><?php endfor; ?>
              </select>
            </div></div>
            <div class="col-6"><div class="ht-field">
              <label class="ht-label">ห้อง</label>
              <input type="number" min="1" step="1" class="ht-input" name="rooms" id="a_rooms" value="1" required>
            </div></div>
          </div>
          <div class="ht-field mb-3">
            <label class="ht-label">ประเภท</label>
            <select class="ht-select" name="stustatus" id="a_status">
              <?php foreach (STU_STATUS as $sid => $sname): ?><option value="<?= $sid ?>"><?= htmlspecialchars($sname) ?></option><?php endforeach; ?>
            </select>
          </div>
          <label class="ht-label mb-2">ชุดคำที่ใช้แต่ละรอบ (1–5)</label>
          <div class="row g-3">
            <?php foreach ([1, 2, 3] as $hn): ?>
            <div class="col-4"><div class="ht-field">
              <label class="ht-label" style="font-weight:600;color:var(--ink-soft)">Hit-<?= $hn ?></label>
              <select class="ht-select" name="sethit<?= $hn ?>" id="a_set<?= $hn ?>">
                <?php for ($v = 1; $v <= 5; $v++): ?><option value="<?= $v ?>">ชุด <?= $v ?></option><?php endfor; ?>
              </select>
            </div></div>
            <?php endforeach; ?>
          </div>
        </form>
      </div>
      <div class="modal-footer" style="border-top:1px solid #eee">
        <button type="button" class="ht-btn ht-btn--ghost ht-btn--sm" data-bs-dismiss="modal">ยกเลิก</button>
        <button type="button" class="ht-btn ht-btn--sm" id="a_save">➕ เพิ่มนักเรียน</button>
      </div>
    </div>
  </div>
</div>

<script>
/* ---- ยกเลิกการสอบรายคน ---- */
document.querySelectorAll('.js-cancel').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var stuid = this.dataset.stuid, hit = this.dataset.hit;
        Swal.fire({
            title: 'ยกเลิกการสอบ Hit-' + hit + '?',
            text: 'ผลการสอบรอบนี้จะถูกลบและคืนสถานะเป็น "ยังไม่สอบ"',
            icon: 'warning', showCancelButton: true,
            confirmButtonText: 'ยกเลิกการสอบ', cancelButtonText: 'ไม่', confirmButtonColor: '#FF6B6B'
        }).then(function (r) {
            if (!r.isConfirmed) return;
            fetch('cancel_result.php?stuid=' + encodeURIComponent(stuid) + '&hittest=' + hit)
                .then(function (x) { return x.json(); })
                .then(function (d) {
                    if (d.status === 'ok') {
                        Swal.fire({ title: 'สำเร็จ', text: 'ยกเลิกการสอบแล้ว', icon: 'success', confirmButtonColor: '#4D96FF' })
                            .then(function () { location.reload(); });
                    } else {
                        Swal.fire({ title: 'ผิดพลาด', text: d.message || '', icon: 'error', confirmButtonColor: '#4D96FF' });
                    }
                });
        });
    });
});

/* ---- แก้ไขข้อมูลนักเรียนรายคน ---- */
function editModalEl() { return document.getElementById('editModal'); }
document.querySelectorAll('.js-edit').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var d = this.dataset;
        document.getElementById('e_stuid').value      = d.stuid;
        document.getElementById('e_stuid_show').value = d.stuid;
        document.getElementById('e_stuname').value    = d.stuname;
        document.getElementById('e_class').value      = d.class;
        document.getElementById('e_rooms').value      = d.rooms;
        document.getElementById('e_status').value     = d.status;
        document.getElementById('e_set1').value       = d.set1;
        document.getElementById('e_set2').value       = d.set2;
        document.getElementById('e_set3').value       = d.set3;
        bootstrap.Modal.getOrCreateInstance(editModalEl()).show();
    });
});
var saveBtn = document.getElementById('e_save');
if (saveBtn) {
    saveBtn.addEventListener('click', function () {
        var form = document.getElementById('editForm');
        if (!form.reportValidity()) return;
        saveBtn.disabled = true;
        fetch('student_update.php', {
            method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: new URLSearchParams(new FormData(form))
        })
            .then(function (x) { return x.json(); })
            .then(function (d) {
                if (d.status === 'ok') {
                    bootstrap.Modal.getOrCreateInstance(editModalEl()).hide();
                    Swal.fire({ icon: 'success', title: 'บันทึกแล้ว', confirmButtonColor: '#4D96FF' }).then(function () { location.reload(); });
                } else {
                    Swal.fire({ icon: 'error', title: 'ผิดพลาด', text: d.message || '', confirmButtonColor: '#4D96FF' });
                }
            })
            .catch(function () { Swal.fire({ icon: 'error', title: 'ผิดพลาด', confirmButtonColor: '#4D96FF' }); })
            .finally(function () { saveBtn.disabled = false; });
    });
}

/* ---- เพิ่มนักเรียนรายคน ---- */
var btnAdd = document.getElementById('btnAddStudent');
if (btnAdd) {
    btnAdd.addEventListener('click', function () {
        document.getElementById('addForm').reset();
        document.getElementById('a_class').value  = this.dataset.class || '1';
        document.getElementById('a_rooms').value  = this.dataset.room || '1';
        document.getElementById('a_status').value = '1';
        document.getElementById('a_set1').value = '1';
        document.getElementById('a_set2').value = '1';
        document.getElementById('a_set3').value = '1';
        bootstrap.Modal.getOrCreateInstance(document.getElementById('addModal')).show();
    });
}
var aSave = document.getElementById('a_save');
if (aSave) {
    aSave.addEventListener('click', function () {
        var form = document.getElementById('addForm');
        if (!form.reportValidity()) return;
        aSave.disabled = true;
        fetch('student_add.php', {
            method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: new URLSearchParams(new FormData(form))
        })
            .then(function (x) { return x.json(); })
            .then(function (d) {
                if (d.status === 'ok') {
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('addModal')).hide();
                    Swal.fire({
                        icon: 'success', title: 'เพิ่มนักเรียนแล้ว', confirmButtonColor: '#4D96FF',
                        html: d.generated ? ('ระบบกำหนดรหัสนักเรียนให้: <b>' + d.stuid + '</b><br><span class="text-muted">ใช้รหัสนี้ให้นักเรียน login</span>') : ('รหัสนักเรียน: <b>' + d.stuid + '</b>')
                    }).then(function () { location.reload(); });
                } else {
                    Swal.fire({ icon: 'error', title: 'ผิดพลาด', text: d.message || '', confirmButtonColor: '#4D96FF' });
                }
            })
            .catch(function () { Swal.fire({ icon: 'error', title: 'ผิดพลาด', confirmButtonColor: '#4D96FF' }); })
            .finally(function () { aSave.disabled = false; });
    });
}

/* ---- ย้ายออกรายคน (สถานะ = ย้ายออก) ---- */
document.querySelectorAll('.js-moveout').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var stuid = this.dataset.stuid, stuname = this.dataset.stuname || '';
        Swal.fire({
            title: 'ย้ายออก?',
            html: 'ทำเครื่องหมายว่า <b>' + stuname + '</b> ย้ายออกจากโรงเรียนนี้<br><span class="text-muted">เก็บประวัติและผลสอบเดิมไว้ · ไม่ต้องระบุโรงเรียนปลายทาง</span>',
            icon: 'warning', showCancelButton: true,
            confirmButtonText: 'ย้ายออก', cancelButtonText: 'ยกเลิก', confirmButtonColor: '#FF6B6B'
        }).then(function (r) {
            if (!r.isConfirmed) return;
            fetch('student_moveout.php', {
                method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: new URLSearchParams({ stuid: stuid })
            })
                .then(function (x) { return x.json(); })
                .then(function (d) {
                    if (d.status === 'ok') {
                        Swal.fire({ icon: 'success', title: 'ย้ายออกแล้ว', confirmButtonColor: '#4D96FF' })
                            .then(function () { location.reload(); });
                    } else {
                        Swal.fire({ icon: 'error', title: 'ผิดพลาด', text: d.message || '', confirmButtonColor: '#4D96FF' });
                    }
                })
                .catch(function () { Swal.fire({ icon: 'error', title: 'ผิดพลาด', confirmButtonColor: '#4D96FF' }); });
        });
    });
});

/* ---- ลบนักเรียนรายคน (soft delete — ซ่อนจากระบบ กู้คืนได้โดยผู้ดูแลระบบ) ---- */
document.querySelectorAll('.js-delete').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var stuid = this.dataset.stuid, stuname = this.dataset.stuname || '';
        Swal.fire({
            title: 'ลบนักเรียนคนนี้?',
            html: 'ลบ <b>' + stuname + '</b> ออกจากรายชื่อ<br><span class="text-muted">เป็นการลบแบบซ่อน (เก็บข้อมูล/ผลสอบไว้) — หากต้องการกู้คืน ติดต่อผู้ดูแลระบบ</span>',
            icon: 'warning', showCancelButton: true,
            confirmButtonText: 'ลบ', cancelButtonText: 'ยกเลิก', confirmButtonColor: '#FF6B6B'
        }).then(function (r) {
            if (!r.isConfirmed) return;
            fetch('student_delete.php', {
                method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: new URLSearchParams({ stuid: stuid })
            })
                .then(function (x) { return x.json(); })
                .then(function (d) {
                    if (d.status === 'ok') {
                        Swal.fire({ icon: 'success', title: 'ลบแล้ว', confirmButtonColor: '#4D96FF' })
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
