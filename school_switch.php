<?php
/**
 * school_switch.php — สลับ "โรงเรียนที่กำลังดู" สำหรับ superadmin / saoadmin
 *   superadmin : เลือกได้ทุกโรงเรียน
 *   saoadmin   : เฉพาะโรงเรียนในเขตตน (area_code = 4 หลักแรกของ SMIS)
 *   role=school / login ด้วย SMIS : ล็อกโรงเรียนตนเอง — เข้าหน้านี้ไม่ได้
 *
 *   เปลี่ยนเฉพาะบริบท sc_id/sc_smis/sc_name ใน session (คง user_id/role/area_code)
 *   POST action=search → ค้นโรงเรียนในขอบเขต · action=switch → ตั้งโรงเรียนที่ดู
 *   GET → หน้าเลือกโรงเรียน
 */
require __DIR__ . '/includes/auth.php';

// ผู้มีขอบเขตหลายโรงเรียน: superadmin/bootstrap SMIS admin (ทุกโรงเรียน) หรือ saoadmin (เขตตน)
$canSwitch = is_admin() || is_saoadmin();
$area      = current_area_code();

/* ─── gate: เฉพาะผู้มีขอบเขตหลายโรงเรียน ─── */
if (!$canSwitch) {
    $isAjax = (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest');
    if ($isAjax) {
        json_response(['status' => 'error', 'message' => 'บัญชีนี้ผูกกับโรงเรียนเดียว สลับไม่ได้'], 403);
    }
    header('Location: menu.php');
    exit;
}

$pdo = db();

/* ─────────────────────────── POST: JSON actions ─────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── ค้นโรงเรียนในขอบเขต ──
    if ($action === 'search') {
        $q = trim((string)($_POST['q'] ?? ''));
        $where  = [];
        $args   = [];
        if (is_saoadmin()) {
            if ($area === '') {
                json_response(['status' => 'ok', 'rows' => []]);
            }
            $where[] = 'sc_smis LIKE ?';
            $args[]  = $area . '%';
            // เฉพาะโรงเรียนที่ "สอบ hittest จริง" ในปีปัจจุบัน —
            // มีนักเรียน (ยังไม่ถูกลบ) ที่ถูกประเมินอย่างน้อย 1 รอบ
            $where[] = 'EXISTS (SELECT 1 FROM students s
                               WHERE s.sc_id = schools.sc_id AND s.years = ? AND s.deleted_at IS NULL
                                 AND (s.hit1tested = 1 OR s.hit2tested = 1 OR s.hit3tested = 1))';
            $args[]  = current_year();
        }
        if ($q !== '') {
            $where[] = '(sc_smis LIKE ? OR sc_name LIKE ?)';
            $args[]  = $q . '%';
            $args[]  = '%' . $q . '%';
        } elseif (!is_saoadmin()) {
            json_response(['status' => 'ok', 'rows' => [], 'hint' => 'พิมพ์เพื่อค้นหา']);  // ผู้ดูแลระบบต้องค้นก่อน (31k)
        }
        $sql = 'SELECT sc_id, sc_smis, sc_name FROM schools';
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY sc_smis LIMIT 50';
        $st = $pdo->prepare($sql);
        $st->execute($args);
        json_response(['status' => 'ok', 'rows' => $st->fetchAll()]);
    }

    // ── สลับไปโรงเรียนที่เลือก ──
    if ($action === 'switch') {
        $scId = trim((string)($_POST['sc_id'] ?? ''));
        $st = $pdo->prepare('SELECT sc_id, sc_smis, sc_name FROM schools WHERE sc_id = ? LIMIT 1');
        $st->execute([$scId]);
        $sc = $st->fetch();
        if (!$sc) {
            json_response(['status' => 'error', 'message' => 'ไม่พบโรงเรียน'], 404);
        }
        // saoadmin: ต้องอยู่ในเขตตน
        if (is_saoadmin() && ($area === '' || strncmp((string)$sc['sc_smis'], $area, strlen($area)) !== 0)) {
            json_response(['status' => 'error', 'message' => 'โรงเรียนนี้อยู่นอกเขตของคุณ'], 403);
        }
        $_SESSION['sc_id']   = (string)$sc['sc_id'];
        $_SESSION['sc_smis'] = (string)$sc['sc_smis'];
        $_SESSION['sc_name'] = (string)$sc['sc_name'];
        json_response(['status' => 'ok', 'sc_name' => $sc['sc_name']]);
    }

    json_response(['status' => 'error', 'message' => 'คำสั่งไม่ถูกต้อง'], 400);
}

/* ─────────────────────────── GET: หน้าเลือกโรงเรียน ─────────────────────────── */
$areaName = '';
if (is_saoadmin() && $area !== '') {
    $an = $pdo->prepare('SELECT sao FROM sao_new WHERE areacode = CONCAT(?, \'0000\') LIMIT 1');
    $an->execute([$area]);
    $areaName = (string)($an->fetchColumn() ?: ('เขต ' . $area));
}

$page_title = 'เปลี่ยนโรงเรียนที่ดู';
$active     = '';
require __DIR__ . '/includes/header.php';
?>
<h2 class="mb-1">🔄 เปลี่ยนโรงเรียนที่กำลังดู</h2>
<p class="text-muted mb-4">
    <?php if (!is_saoadmin()): ?>
        ผู้ดูแลระบบ — เลือกดูได้ทุกโรงเรียน
    <?php else: ?>
        ผู้ดูแลเขต <strong><?= htmlspecialchars($areaName) ?></strong> — เลือกได้เฉพาะโรงเรียนในเขต (อ่านอย่างเดียว)
    <?php endif; ?>
    · กำลังดู: <strong><?= htmlspecialchars($_SESSION['sc_name'] ?? '—') ?></strong>
</p>

<div class="ht-card" style="max-width:760px">
    <div class="d-flex gap-2 mb-3" style="max-width:480px">
        <input id="q" class="ht-input" placeholder="<?= !is_saoadmin() ? 'พิมพ์รหัส SMIS หรือชื่อโรงเรียน' : 'ค้นหาในเขต (เว้นว่าง = ทั้งเขต)' ?>" autocomplete="off">
        <button id="btnSearch" class="ht-btn ht-btn--blue" type="button">🔍 ค้นหา</button>
    </div>
    <div id="result"><p class="text-muted">กำลังโหลด…</p></div>
</div>

<script>
(function () {
    var BLUE = '#4D96FF';
    var SUPER = <?= !is_saoadmin() ? 'true' : 'false' ?>;
    var q = document.getElementById('q'), box = document.getElementById('result');

    function post(action, data) {
        var fd = new FormData(); fd.append('action', action);
        Object.keys(data || {}).forEach(function (k) { fd.append(k, data[k]); });
        return fetch('school_switch.php', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd }).then(function (r) { return r.json(); });
    }
    function render(rows) {
        box.textContent = '';
        if (!rows.length) {
            var p = document.createElement('p'); p.className = 'text-muted'; p.style.padding = '8px 0';
            p.textContent = SUPER ? 'พิมพ์เพื่อค้นหาโรงเรียน' : 'ไม่พบโรงเรียน'; box.appendChild(p); return;
        }
        var wrap = document.createElement('div'); wrap.className = 'ht-table-wrap';
        var tbl = document.createElement('table'); tbl.className = 'ht-table'; var tb = document.createElement('tbody');
        rows.forEach(function (r) {
            var tr = document.createElement('tr');
            var a = document.createElement('td'); a.className = 'fw-7'; a.textContent = r.sc_smis;
            var b = document.createElement('td'); b.textContent = r.sc_name;
            var c = document.createElement('td'); c.className = 'text-end';
            var btn = document.createElement('button'); btn.className = 'ht-btn ht-btn--sm'; btn.textContent = 'ดูโรงเรียนนี้ →';
            btn.addEventListener('click', function () {
                post('switch', { sc_id: r.sc_id }).then(function (d) {
                    if (d.status === 'ok') { window.location = 'menu.php'; }
                    else { Swal.fire({ icon: 'error', title: 'ผิดพลาด', text: d.message || '', confirmButtonColor: BLUE }); }
                });
            });
            c.appendChild(btn); tr.appendChild(a); tr.appendChild(b); tr.appendChild(c); tb.appendChild(tr);
        });
        tbl.appendChild(tb); wrap.appendChild(tbl); box.appendChild(wrap);
    }
    function search() {
        box.innerHTML = '<p class="text-muted" style="padding:8px 0">กำลังค้นหา…</p>';
        post('search', { q: q.value.trim() }).then(function (d) { render(d.rows || []); }).catch(function () { box.innerHTML = '<p class="text-muted">ค้นหาไม่สำเร็จ</p>'; });
    }
    document.getElementById('btnSearch').addEventListener('click', search);
    q.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); search(); } });
    if (SUPER) { box.innerHTML = '<p class="text-muted" style="padding:8px 0">พิมพ์เพื่อค้นหาโรงเรียน</p>'; }
    else { search(); }   // saoadmin: โหลดโรงเรียนในเขตทันที
})();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
