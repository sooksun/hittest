<?php
/**
 * admin_users.php — จัดการบัญชีผู้ใช้ (เฉพาะผู้ดูแลระบบ / superadmin)
 *   เพิ่ม / ลบ / แก้ไข username / เปลี่ยนรหัสผ่าน / กำหนด role + ขอบเขต
 *   role (3 ระดับ):
 *     superadmin – ผู้ดูแลระบบทั้งประเทศ (ไม่ผูกเขต/โรงเรียน)
 *     saoadmin   – ผู้ดูแลเขต สพป.       (ผูก area_code = 4 หลักแรกของ SMIS — เห็นทุกโรงเรียนในเขต, อ่านอย่างเดียว)
 *     school     – โรงเรียน              (ผูก sc_id = schools.sc_id)
 *   เก็บในตาราง users (password เป็น hash) — login.php รองรับควบคู่ SMIS โรงเรียนเดิม
 *   บัญชีเขตนำเข้าจาก master_saonew ด้วย scripts/migrate_area_admins.php
 *
 *   POST create · update · set_password · delete   ·   GET (?role=&q=) → หน้าจัดการ
 */
require __DIR__ . '/includes/admin_auth.php';

$pdo = db();

/** แปลง SMIS → sc_id (schools) — คืน null ถ้าว่าง, false ถ้าไม่พบ */
function au_resolve_scid(PDO $pdo, string $smis)
{
    $smis = trim($smis);
    if ($smis === '') {
        return null;
    }
    $st = $pdo->prepare('SELECT sc_id FROM schools WHERE sc_smis = ? LIMIT 1');
    $st->execute([$smis]);
    $id = $st->fetchColumn();
    return $id === false ? false : (string)$id;
}

/* ─────────────────────────── POST: JSON actions ─────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── ค้นรายชื่อโรงเรียน "จากตาราง schools เท่านั้น" (ให้ role=school เลือก) ──
    if ($action === 'search_school') {
        $q = trim((string)($_POST['q'] ?? ''));
        if ($q === '') {
            json_response(['status' => 'ok', 'rows' => []]);
        }
        $st = $pdo->prepare(
            'SELECT sc_smis, sc_name FROM schools
             WHERE sc_smis LIKE ? OR sc_name LIKE ?
             ORDER BY sc_smis LIMIT 20'
        );
        $st->execute([$q . '%', '%' . $q . '%']);
        json_response(['status' => 'ok', 'rows' => $st->fetchAll()]);
    }

    // ── ตรวจฟิลด์ร่วม (create/update) → คืน [username, name, role, scId, area] ──
    $validate = function () use ($pdo) {
        $username = trim((string)($_POST['username'] ?? ''));
        $name     = trim((string)($_POST['name'] ?? ''));
        $role     = (string)($_POST['role'] ?? '');
        $ref      = trim((string)($_POST['scope_ref'] ?? ''));   // SMIS (school) หรือ รหัสเขต (saoadmin)

        if (!preg_match('/^[a-zA-Z0-9._\-]{3,50}$/', $username)) {
            json_response(['status' => 'error', 'message' => 'username ต้องเป็น a-z A-Z 0-9 . _ - ยาว 3–50 ตัว'], 400);
        }
        if (!array_key_exists($role, USER_ROLES)) {
            json_response(['status' => 'error', 'message' => 'role ไม่ถูกต้อง'], 400);
        }
        $scId = null;
        $area = null;
        if ($role === 'school') {
            $scId = au_resolve_scid($pdo, $ref);
            if ($scId === null) {
                json_response(['status' => 'error', 'message' => 'role โรงเรียน ต้องเลือกโรงเรียนจากรายการค้นหา (ตาราง schools)'], 400);
            }
            if ($scId === false) {
                json_response(['status' => 'error', 'message' => 'ไม่พบโรงเรียนรหัส SMIS นี้'], 404);
            }
        } elseif ($role === 'saoadmin') {
            $area = substr(preg_replace('/\D/', '', $ref), 0, 4);   // รับ 4 หลัก หรือตัดจาก SMIS โรงเรียนในเขต
            if (strlen($area) !== 4) {
                json_response(['status' => 'error', 'message' => 'role ผู้ดูแลเขต ต้องระบุรหัสเขต 4 หลัก (หรือ SMIS โรงเรียนในเขต)'], 400);
            }
            $ck = $pdo->prepare('SELECT COUNT(*) FROM schools WHERE sc_smis LIKE ?');
            $ck->execute([$area . '%']);
            if (!(int)$ck->fetchColumn()) {
                json_response(['status' => 'error', 'message' => 'ไม่พบโรงเรียนในเขตรหัส ' . $area], 404);
            }
        }
        return ['username' => $username, 'name' => $name, 'role' => $role, 'scId' => $scId, 'area' => $area];
    };

    // ── เพิ่มผู้ใช้ ──
    if ($action === 'create') {
        $f = $validate();
        $password = (string)($_POST['password'] ?? '');
        if (strlen($password) < 6) {
            json_response(['status' => 'error', 'message' => 'รหัสผ่านอย่างน้อย 6 ตัวอักษร'], 400);
        }
        try {
            $pdo->prepare(
                'INSERT INTO users (username, password_hash, name, role, area_code, sc_id, is_active, created_at, updated_at)
                 VALUES (?,?,?,?,?,?,1,NOW(),NOW())'
            )->execute([$f['username'], password_hash($password, PASSWORD_DEFAULT), $f['name'], $f['role'], $f['area'], $f['scId']]);
        } catch (PDOException $e) {
            $msg = ((int)$e->errorInfo[1] === 1062) ? 'username นี้มีอยู่แล้ว' : 'เพิ่มผู้ใช้ไม่สำเร็จ';
            json_response(['status' => 'error', 'message' => $msg], 409);
        }
        json_response(['status' => 'ok']);
    }

    // ── แก้ไขผู้ใช้ ──
    if ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            json_response(['status' => 'error', 'message' => 'id ไม่ถูกต้อง'], 400);
        }
        $f      = $validate();
        $active = (int)($_POST['is_active'] ?? 1) === 1 ? 1 : 0;
        if ($id === current_user_id() && $active === 0) {
            json_response(['status' => 'error', 'message' => 'ปิดใช้งานบัญชีของตัวเองไม่ได้'], 400);
        }
        try {
            $pdo->prepare(
                'UPDATE users SET username=?, name=?, role=?, area_code=?, sc_id=?, is_active=?, updated_at=NOW() WHERE id=?'
            )->execute([$f['username'], $f['name'], $f['role'], $f['area'], $f['scId'], $active, $id]);
        } catch (PDOException $e) {
            $msg = ((int)$e->errorInfo[1] === 1062) ? 'username นี้มีอยู่แล้ว' : 'บันทึกไม่สำเร็จ';
            json_response(['status' => 'error', 'message' => $msg], 409);
        }
        json_response(['status' => 'ok']);
    }

    // ── เปลี่ยนรหัสผ่าน ──
    if ($action === 'set_password') {
        $id = (int)($_POST['id'] ?? 0);
        $password = (string)($_POST['password'] ?? '');
        if ($id <= 0) {
            json_response(['status' => 'error', 'message' => 'id ไม่ถูกต้อง'], 400);
        }
        if (strlen($password) < 6) {
            json_response(['status' => 'error', 'message' => 'รหัสผ่านอย่างน้อย 6 ตัวอักษร'], 400);
        }
        $pdo->prepare('UPDATE users SET password_hash=?, updated_at=NOW() WHERE id=?')
            ->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
        json_response(['status' => 'ok']);
    }

    // ── ลบผู้ใช้ ──
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id === current_user_id()) {
            json_response(['status' => 'error', 'message' => 'ลบบัญชีของตัวเองไม่ได้'], 400);
        }
        $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$id]);
        json_response(['status' => 'ok']);
    }

    json_response(['status' => 'error', 'message' => 'คำสั่งไม่ถูกต้อง'], 400);
}

/* ─────────────────────────── GET: รายการผู้ใช้ ─────────────────────────── */
$fRole = (string)($_GET['role'] ?? '');
$fQ    = trim((string)($_GET['q'] ?? ''));
$where = [];
$args  = [];
if (array_key_exists($fRole, USER_ROLES)) {
    $where[] = 'u.role = ?';
    $args[]  = $fRole;
}
if ($fQ !== '') {
    $where[] = '(u.username LIKE ? OR u.name LIKE ?)';
    $args[]  = '%' . $fQ . '%';
    $args[]  = '%' . $fQ . '%';
}
$sql = 'SELECT u.id, u.username, u.name, u.role, u.area_code, u.sc_id, u.is_active,
               s.sc_name, s.sc_smis, a.sao AS area_name
        FROM users u
        LEFT JOIN schools s ON s.sc_id = u.sc_id
        LEFT JOIN sao_new a ON a.areacode = CONCAT(u.area_code, \'0000\')';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY FIELD(u.role, "superadmin","saoadmin","school"), u.username';
$st = $pdo->prepare($sql);
$st->execute($args);
$users = $st->fetchAll();

// นับตาม role (แท็บกรอง)
$counts = ['' => 0, 'superadmin' => 0, 'saoadmin' => 0, 'school' => 0];
foreach ($pdo->query('SELECT role, COUNT(*) c FROM users GROUP BY role')->fetchAll() as $r) {
    if (isset($counts[$r['role']])) {
        $counts[$r['role']] = (int)$r['c'];
    }
    $counts[''] += (int)$r['c'];
}

$roleBadge = ['superadmin' => 'ht-badge--special', 'saoadmin' => 'ht-badge--done', 'school' => 'ht-badge--pending'];

$page_title = 'จัดการผู้ใช้';
$active     = 'admin_users';
require __DIR__ . '/includes/header.php';
?>
<style>
.au-results { max-height: 210px; overflow-y: auto; margin-bottom: 4px; }
.au-result-item { padding: 6px 8px; border-radius: 6px; cursor: pointer; font-size: .9rem; }
.au-result-item:hover { background: var(--bg-sky, #eef4ff); }
</style>
<div class="ht-row mb-1" style="gap:14px;align-items:center">
    <h2 class="mb-0">👤 จัดการผู้ใช้</h2>
    <button id="btnAdd" class="ht-btn ht-btn--coral" style="margin-left:auto">➕ เพิ่มผู้ใช้</button>
</div>
<p class="text-muted mb-4">บัญชีในตาราง <code>users</code> · ระดับ: <strong>ผู้ดูแลระบบ</strong> / <strong>ผู้ดูแลเขต (สพป.)</strong> / <strong>โรงเรียน</strong> · บัญชีนี้: <strong><?= htmlspecialchars(current_smis()) ?></strong></p>

<!-- ตัวกรอง role + ค้นหา -->
<div class="d-flex flex-wrap gap-2 mb-3" style="align-items:center">
    <?php
    $tabs = ['' => 'ทั้งหมด', 'superadmin' => USER_ROLES['superadmin'], 'saoadmin' => USER_ROLES['saoadmin'], 'school' => USER_ROLES['school']];
    foreach ($tabs as $rk => $label):
        $on = ($fRole === $rk) || ($rk === '' && !array_key_exists($fRole, USER_ROLES));
        $href = 'admin_users.php' . ($rk !== '' ? '?role=' . urlencode($rk) : '');
    ?>
        <a class="ht-btn ht-btn--sm <?= $on ? 'ht-btn--blue' : 'ht-btn--ghost' ?>" href="<?= $href ?>">
            <?= htmlspecialchars($label) ?> <span class="text-muted">(<?= (int)$counts[$rk] ?>)</span>
        </a>
    <?php endforeach; ?>
    <form method="get" class="d-flex gap-2" style="margin-left:auto">
        <?php if ($fRole !== ''): ?><input type="hidden" name="role" value="<?= htmlspecialchars($fRole) ?>"><?php endif; ?>
        <input class="ht-input ht-input--sm" name="q" placeholder="ค้นหา username/ชื่อ" value="<?= htmlspecialchars($fQ) ?>" style="max-width:220px">
        <button class="ht-btn ht-btn--sm ht-btn--ghost">🔍</button>
    </form>
</div>

<div class="ht-card" style="max-width:1040px">
    <div class="ht-table-wrap">
        <table class="ht-table">
            <thead>
                <tr><th>username</th><th>ชื่อ</th><th>ระดับ</th><th>ขอบเขต</th><th class="text-center">สถานะ</th><th class="text-end">จัดการ</th></tr>
            </thead>
            <tbody>
            <?php if (!$users): ?>
                <tr><td colspan="6" class="text-muted">— ยังไม่มีผู้ใช้ —</td></tr>
            <?php else: foreach ($users as $u):
                $isMe = (int)$u['id'] === current_user_id();
                if ($u['role'] === 'superadmin') {
                    $scope = '<span class="text-muted">ทั้งระบบ</span>';
                } elseif ($u['role'] === 'saoadmin') {
                    $scope = htmlspecialchars(($u['area_name'] ?: 'เขต ' . $u['area_code']) . ' (' . $u['area_code'] . ')');
                } else {
                    $scope = ($u['sc_id'] === null || $u['sc_id'] === '')
                        ? '<span class="text-muted">—</span>'
                        : htmlspecialchars(($u['sc_name'] ?? '—') . ' (' . ($u['sc_smis'] ?? $u['sc_id']) . ')');
                }
            ?>
                <tr>
                    <td class="fw-7"><?= htmlspecialchars($u['username']) ?><?= $isMe ? ' <span class="text-muted" style="font-weight:500">(คุณ)</span>' : '' ?></td>
                    <td><?= htmlspecialchars($u['name'] ?: '—') ?></td>
                    <td><span class="ht-badge <?= $roleBadge[$u['role']] ?? '' ?>"><?= htmlspecialchars(USER_ROLES[$u['role']] ?? $u['role']) ?></span></td>
                    <td><?= $scope ?></td>
                    <td class="text-center">
                        <?= (int)$u['is_active'] === 1
                            ? '<span class="ht-badge ht-badge--done">เปิด</span>'
                            : '<span class="ht-badge ht-badge--missing">ปิด</span>' ?>
                    </td>
                    <td class="text-end" style="white-space:nowrap">
                        <button class="ht-btn ht-btn--ghost ht-btn--sm js-edit"
                            data-id="<?= (int)$u['id'] ?>"
                            data-username="<?= htmlspecialchars($u['username']) ?>"
                            data-name="<?= htmlspecialchars($u['name']) ?>"
                            data-role="<?= htmlspecialchars($u['role']) ?>"
                            data-smis="<?= htmlspecialchars($u['sc_smis'] ?? '') ?>"
                            data-scname="<?= htmlspecialchars($u['sc_name'] ?? '') ?>"
                            data-area="<?= htmlspecialchars($u['area_code'] ?? '') ?>"
                            data-active="<?= (int)$u['is_active'] ?>">✎ แก้ไข</button>
                        <button class="ht-btn ht-btn--ghost ht-btn--sm js-pw" data-id="<?= (int)$u['id'] ?>" data-username="<?= htmlspecialchars($u['username']) ?>">🔑 รหัสผ่าน</button>
                        <?php if (!$isMe): ?>
                        <button class="ht-btn ht-btn--ghost ht-btn--sm js-del" data-id="<?= (int)$u['id'] ?>" data-username="<?= htmlspecialchars($u['username']) ?>">🗑 ลบ</button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
(function () {
    var ROLES = <?= json_encode(USER_ROLES, JSON_UNESCAPED_UNICODE) ?>;
    var BLUE = '#4D96FF', CORAL = '#FF6B6B';

    function post(action, data) {
        var fd = new FormData();
        fd.append('action', action);
        Object.keys(data || {}).forEach(function (k) { fd.append(k, data[k]); });
        return fetch('admin_users.php', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd })
            .then(function (r) { return r.json(); });
    }
    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }

    function roleOptions(sel) {
        return Object.keys(ROLES).map(function (k) {
            return '<option value="' + k + '"' + (k === sel ? ' selected' : '') + '>' + esc(ROLES[k]) + '</option>';
        }).join('');
    }
    // label/placeholder ของช่อง "ขอบเขต" ตาม role (school = ค้นจากตาราง schools)
    function scopeMeta(role) {
        if (role === 'school')   return { show: true, search: true,  label: 'โรงเรียน (ค้นจากตาราง schools)', ph: 'พิมพ์ชื่อ หรือรหัส SMIS โรงเรียน' };
        if (role === 'saoadmin') return { show: true, search: false, label: 'รหัสเขต 4 หลัก (หรือ SMIS ในเขต)', ph: 'เช่น 5703' };
        return { show: false, search: false, label: '', ph: '' };   // superadmin = ทั้งระบบ
    }

    function userFormHtml(d, withPassword) {
        d = d || {};
        return ''
            + '<div style="text-align:left">'
            + '<label class="ht-label">username</label>'
            + '<input id="f_username" class="ht-input mb-2" value="' + esc(d.username) + '" placeholder="เช่น area5703">'
            + (withPassword ? '<label class="ht-label">รหัสผ่าน</label><input id="f_password" type="password" class="ht-input mb-2" placeholder="อย่างน้อย 6 ตัว">' : '')
            + '<label class="ht-label">ชื่อ-สกุล / หน่วยงาน</label>'
            + '<input id="f_name" class="ht-input mb-2" value="' + esc(d.name) + '" placeholder="ชื่อที่แสดง">'
            + '<label class="ht-label">ระดับ (role)</label>'
            + '<select id="f_role" class="ht-input mb-2">' + roleOptions(d.role || 'school') + '</select>'
            + '<div id="scopeWrap"><label class="ht-label" id="scopeLabel"></label>'
            + '<input id="f_scope" class="ht-input mb-1" autocomplete="off" value="' + esc(d.scope) + '">'
            + '<div id="f_results" class="au-results"></div>'
            + '<div id="f_picked" class="text-muted" style="font-size:.85rem"></div>'
            + '<input type="hidden" id="f_scope_val" value="' + esc(d.scopeVal) + '"></div>'
            + (typeof d.active !== 'undefined'
                ? '<label class="ht-label mt-2">สถานะ</label><select id="f_active" class="ht-input"><option value="1"' + (String(d.active) === '1' ? ' selected' : '') + '>เปิดใช้งาน</option><option value="0"' + (String(d.active) === '0' ? ' selected' : '') + '>ปิดใช้งาน</option></select>'
                : '')
            + '</div>';
    }
    // ผูก event หลัง dialog เปิด: อัปเดตช่องขอบเขต + ค้นโรงเรียน (role=school) จากตาราง schools
    function wireScope() {
        var roleEl = document.getElementById('f_role'),
            wrap = document.getElementById('scopeWrap'),
            lbl  = document.getElementById('scopeLabel'),
            inp  = document.getElementById('f_scope'),
            res  = document.getElementById('f_results'),
            pick = document.getElementById('f_picked'),
            hid  = document.getElementById('f_scope_val'),
            timer = null;
        function sync() {
            var m = scopeMeta(roleEl.value);
            wrap.style.display = m.show ? '' : 'none';
            lbl.textContent = m.label;
            inp.placeholder = m.ph;
            res.innerHTML = '';
            pick.textContent = (m.search && hid.value) ? ('✓ เลือก SMIS ' + hid.value) : '';
            if (!m.search) { hid.value = ''; }
        }
        function search() {
            if (roleEl.value !== 'school') { return; }
            var q = inp.value.trim();
            if (!q) { res.innerHTML = ''; return; }
            post('search_school', { q: q }).then(function (d) {
                res.innerHTML = '';
                var rows = d.rows || [];
                if (!rows.length) { res.innerHTML = '<div class="au-result-item text-muted">ไม่พบโรงเรียน</div>'; return; }
                rows.forEach(function (r) {
                    var it = document.createElement('div');
                    it.className = 'au-result-item';
                    it.textContent = r.sc_name + ' (' + r.sc_smis + ')';
                    it.addEventListener('click', function () {
                        hid.value = r.sc_smis; inp.value = r.sc_name;
                        pick.textContent = '✓ เลือก: ' + r.sc_name + ' (' + r.sc_smis + ')';
                        res.innerHTML = '';
                    });
                    res.appendChild(it);
                });
            }).catch(function () { res.innerHTML = ''; });
        }
        roleEl.addEventListener('change', function () { hid.value = ''; inp.value = ''; pick.textContent = ''; sync(); });
        inp.addEventListener('input', function () {
            if (roleEl.value !== 'school') { return; }
            hid.value = '';                          // พิมพ์ใหม่ = ยังไม่ได้เลือก ต้องคลิกจากรายการ
            clearTimeout(timer); timer = setTimeout(search, 300);
        });
        sync();
    }
    function readForm(withPassword, withActive) {
        var role = document.getElementById('f_role').value;
        var scope = role === 'school'
            ? document.getElementById('f_scope_val').value                 // SMIS ที่เลือกจากผลค้นหา (ตาราง schools)
            : (role === 'saoadmin' ? document.getElementById('f_scope').value.trim() : '');
        var v = {
            username: document.getElementById('f_username').value.trim(),
            name: document.getElementById('f_name').value.trim(),
            role: role,
            scope_ref: scope
        };
        if (withPassword) { v.password = document.getElementById('f_password').value; }
        if (withActive) { v.is_active = document.getElementById('f_active').value; }
        return v;
    }
    function done(t) { Swal.fire({ icon: 'success', title: t, confirmButtonColor: BLUE, timer: 1100, showConfirmButton: false }).then(function () { location.reload(); }); }
    function fail(d) { Swal.fire({ icon: 'error', title: 'ผิดพลาด', text: (d && d.message) || '', confirmButtonColor: BLUE }); }

    // เพิ่ม
    document.getElementById('btnAdd').addEventListener('click', function () {
        Swal.fire({
            title: '➕ เพิ่มผู้ใช้', html: userFormHtml({}, true), focusConfirm: false, width: 460, didOpen: wireScope,
            showCancelButton: true, confirmButtonText: 'เพิ่ม', confirmButtonColor: CORAL, cancelButtonText: 'ยกเลิก',
            preConfirm: function () { return readForm(true, false); }
        }).then(function (res) {
            if (!res.isConfirmed) return;
            post('create', res.value).then(function (d) { d.status === 'ok' ? done('เพิ่มผู้ใช้แล้ว') : fail(d); }).catch(function () { fail(); });
        });
    });

    // แก้ไข
    document.querySelectorAll('.js-edit').forEach(function (b) {
        b.addEventListener('click', function () {
            var ds = b.dataset;
            var scope = ds.role === 'saoadmin' ? ds.area : (ds.role === 'school' ? (ds.scname || ds.smis) : '');
            var scopeVal = ds.role === 'school' ? ds.smis : '';
            Swal.fire({
                title: '✎ แก้ไขผู้ใช้',
                html: userFormHtml({ username: ds.username, name: ds.name, role: ds.role, scope: scope, scopeVal: scopeVal, active: ds.active }, false),
                focusConfirm: false, width: 460, didOpen: wireScope,
                showCancelButton: true, confirmButtonText: 'บันทึก', confirmButtonColor: CORAL, cancelButtonText: 'ยกเลิก',
                preConfirm: function () { var v = readForm(false, true); v.id = ds.id; return v; }
            }).then(function (res) {
                if (!res.isConfirmed) return;
                post('update', res.value).then(function (d) { d.status === 'ok' ? done('บันทึกแล้ว') : fail(d); }).catch(function () { fail(); });
            });
        });
    });

    // เปลี่ยนรหัสผ่าน
    document.querySelectorAll('.js-pw').forEach(function (b) {
        b.addEventListener('click', function () {
            var ds = b.dataset;
            Swal.fire({
                title: '🔑 เปลี่ยนรหัสผ่าน', input: 'password', inputLabel: 'รหัสผ่านใหม่ของ ' + ds.username, inputPlaceholder: 'อย่างน้อย 6 ตัว',
                showCancelButton: true, confirmButtonText: 'เปลี่ยน', confirmButtonColor: CORAL, cancelButtonText: 'ยกเลิก',
                inputValidator: function (v) { if (!v || v.length < 6) return 'อย่างน้อย 6 ตัวอักษร'; }
            }).then(function (res) {
                if (!res.isConfirmed) return;
                post('set_password', { id: ds.id, password: res.value }).then(function (d) { d.status === 'ok' ? done('เปลี่ยนรหัสผ่านแล้ว') : fail(d); }).catch(function () { fail(); });
            });
        });
    });

    // ลบ
    document.querySelectorAll('.js-del').forEach(function (b) {
        b.addEventListener('click', function () {
            var ds = b.dataset;
            Swal.fire({
                icon: 'warning', title: 'ลบผู้ใช้?', html: '<b>' + esc(ds.username) + '</b><br>ลบแล้วบัญชีนี้จะ login ไม่ได้',
                showCancelButton: true, confirmButtonText: 'ลบ', confirmButtonColor: CORAL, cancelButtonText: 'ยกเลิก'
            }).then(function (res) {
                if (!res.isConfirmed) return;
                post('delete', { id: ds.id }).then(function (d) { d.status === 'ok' ? done('ลบแล้ว') : fail(d); }).catch(function () { fail(); });
            });
        });
    });
})();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
