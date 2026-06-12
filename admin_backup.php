<?php
/**
 * admin_backup.php — เครื่องมือผู้ดูแล: สำรอง/กู้คืนฐานข้อมูล
 *   GET  ?download=<file>  → ดาวน์โหลดไฟล์ backup
 *   POST action=backup     → สร้าง backup (ทั้ง DB หรือเฉพาะ evaluations) → JSON
 *   POST action=restore    → กู้คืนจากไฟล์ในรายการ หรือไฟล์อัปโหลด (ยืนยันด้วยชื่อ DB) → JSON
 *   POST action=delete     → ลบไฟล์ backup → JSON
 *   GET  (อื่น ๆ)          → หน้าจัดการ
 */
require __DIR__ . '/includes/admin_auth.php';
require __DIR__ . '/includes/db_backup.php';

/* ---------- ดาวน์โหลด ---------- */
if (isset($_GET['download'])) {
    $p = dbk_safe_path((string)$_GET['download']);
    if (!$p) {
        http_response_code(404);
        exit('ไม่พบไฟล์');
    }
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($p) . '"');
    header('Content-Length: ' . filesize($p));
    header('Cache-Control: no-store');
    readfile($p);
    exit;
}

/* ---------- การกระทำ (POST) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'backup') {
        $tables = (($_POST['scope'] ?? 'all') === 'evaluations') ? ['evaluations'] : [];
        $r = dbk_backup($tables);
        if ($r['ok']) {
            json_response([
                'status' => 'ok',
                'name'   => basename($r['file']),
                'size'   => dbk_human((int)$r['bytes']),
                'method' => $r['method'],
                'rows'   => $r['rows'] ?? null,
            ]);
        }
        json_response(['status' => 'error', 'message' => $r['error'] ?? 'สำรองข้อมูลไม่สำเร็จ'], 500);
    }

    if ($action === 'delete') {
        $p = dbk_safe_path((string)($_POST['name'] ?? ''));
        if ($p && @unlink($p)) {
            json_response(['status' => 'ok']);
        }
        json_response(['status' => 'error', 'message' => 'ลบไฟล์ไม่สำเร็จ'], 400);
    }

    if ($action === 'restore') {
        // ยืนยันด้วยการพิมพ์ชื่อฐานข้อมูล (กันกดพลาด — restore มี DROP TABLE)
        if (($_POST['confirm'] ?? '') !== DB_NAME) {
            json_response(['status' => 'error', 'message' => 'พิมพ์ชื่อฐานข้อมูลยืนยันไม่ถูกต้อง'], 400);
        }
        if (!empty($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
            $r = dbk_restore($_FILES['file']['tmp_name']);          // จากไฟล์อัปโหลด
        } else {
            $p = dbk_safe_path((string)($_POST['name'] ?? ''));
            if (!$p) {
                json_response(['status' => 'error', 'message' => 'ไม่พบไฟล์ที่จะกู้คืน'], 400);
            }
            $r = dbk_restore($p);                                   // จากไฟล์ในรายการ
        }
        if ($r['ok']) {
            json_response(['status' => 'ok', 'method' => $r['method'], 'statements' => $r['statements'] ?? null]);
        }
        json_response(['status' => 'error', 'message' => $r['error'] ?? 'กู้คืนไม่สำเร็จ'], 500);
    }

    json_response(['status' => 'error', 'message' => 'คำสั่งไม่ถูกต้อง'], 400);
}

/* ---------- แสดงผล ---------- */
$backups = dbk_list();
$useExec = dbk_exec_enabled() && dbk_bin('mysqldump');

$page_title = 'สำรอง/กู้คืนฐานข้อมูล';
$active     = 'admin_backup';
require __DIR__ . '/includes/header.php';
?>
<h2 class="mb-1">💾 สำรอง / กู้คืนฐานข้อมูล</h2>
<p class="text-muted mb-4">ฐานข้อมูล <strong><?= htmlspecialchars(DB_NAME) ?></strong> ·
   วิธีที่ใช้: <strong><?= $useExec ? 'mysqldump (เร็ว เหมาะกับตารางใหญ่)' : 'PHP ล้วน (host ปิด exec)' ?></strong></p>

<div class="ht-card mb-4" style="max-width:820px; border-left:6px solid var(--c-blue)">
    <h3 class="mb-3">สร้างไฟล์สำรอง</h3>
    <div class="d-flex flex-wrap gap-2">
        <button class="ht-btn ht-btn--coral js-backup" data-scope="all">⬇️ สำรองทั้งฐานข้อมูล</button>
        <button class="ht-btn ht-btn--ghost js-backup" data-scope="evaluations">⬇️ สำรองเฉพาะ evaluations (6M+)</button>
    </div>
    <p class="text-muted mt-3 mb-0" style="font-size:.9rem">
        ไฟล์ถูกบีบอัด <code>.sql.gz</code> เก็บในโฟลเดอร์ <code>backups/</code> (กันเข้าถึงผ่านเว็บ) ·
        ตารางใหญ่มากแนะนำรันผ่าน CLI: <code>php includes/db_backup.php backup evaluations</code>
    </p>
</div>

<div class="ht-card mb-4" style="max-width:820px">
    <h3 class="mb-3">ไฟล์สำรองที่มี (<?= count($backups) ?>)</h3>
    <?php if (!$backups): ?>
        <p class="text-muted mb-0">ยังไม่มีไฟล์สำรอง</p>
    <?php else: ?>
    <div class="ht-table-wrap">
        <table class="ht-table">
            <thead><tr><th>ไฟล์</th><th class="num text-end">ขนาด</th><th>เมื่อ</th><th class="text-center">จัดการ</th></tr></thead>
            <tbody>
            <?php foreach ($backups as $b): $n = htmlspecialchars($b['name'], ENT_QUOTES); ?>
                <tr>
                    <td style="word-break:break-all"><?= $n ?></td>
                    <td class="num text-end"><?= dbk_human((int)$b['bytes']) ?></td>
                    <td class="text-muted"><?= date('d/m/', $b['mtime']) . (date('Y', $b['mtime']) + 543) . date(' H:i', $b['mtime']) ?></td>
                    <td class="text-center" style="white-space:nowrap">
                        <a class="ht-btn ht-btn--ghost ht-btn--sm" href="admin_backup.php?download=<?= urlencode($b['name']) ?>">ดาวน์โหลด</a>
                        <button class="ht-btn ht-btn--yellow ht-btn--sm js-restore" data-name="<?= $n ?>">กู้คืน</button>
                        <button class="ht-btn ht-btn--ghost ht-btn--sm js-delete" data-name="<?= $n ?>">ลบ</button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<div class="ht-card" style="max-width:820px; border-left:6px solid var(--c-yellow)">
    <h3 class="mb-2">↩️ กู้คืนจากไฟล์อัปโหลด</h3>
    <p class="text-muted mb-3" style="font-size:.9rem">
        ⚠️ การกู้คืนจะ <strong>เขียนทับตารางเดิม</strong> (ไฟล์มี DROP TABLE) — แนะนำสำรองปัจจุบันไว้ก่อน
    </p>
    <form id="formUpload" enctype="multipart/form-data">
        <input type="file" name="file" accept=".sql,.gz" class="form-control mb-2" style="max-width:480px" required>
        <button type="submit" class="ht-btn ht-btn--yellow">↩️ กู้คืนจากไฟล์นี้</button>
    </form>
</div>

<script>
var DBNAME = <?= json_encode(DB_NAME) ?>;

function post(fd) {
    return fetch('admin_backup.php', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd })
        .then(function (r) { return r.json(); });
}

document.querySelectorAll('.js-backup').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var scope = btn.getAttribute('data-scope');
        Swal.fire({ title: 'กำลังสำรองข้อมูล…', html: 'อาจใช้เวลาสักครู่สำหรับตารางใหญ่', allowOutsideClick: false, didOpen: function () { Swal.showLoading(); } });
        var fd = new FormData(); fd.append('action', 'backup'); fd.append('scope', scope);
        post(fd).then(function (d) {
            if (d.status === 'ok') {
                Swal.fire({ icon: 'success', title: 'สำรองสำเร็จ', confirmButtonColor: '#4D96FF',
                    html: '<b>' + d.name + '</b><br>ขนาด ' + d.size + (d.rows ? '<br>' + Number(d.rows).toLocaleString() + ' แถว' : '') + '<br>วิธี: ' + d.method
                }).then(function () { location.reload(); });
            } else { Swal.fire({ icon: 'error', title: 'ผิดพลาด', text: d.message || '', confirmButtonColor: '#4D96FF' }); }
        }).catch(function () { Swal.fire({ icon: 'error', title: 'ผิดพลาด', confirmButtonColor: '#4D96FF' }); });
    });
});

document.querySelectorAll('.js-delete').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var name = btn.getAttribute('data-name');
        Swal.fire({ icon: 'warning', title: 'ลบไฟล์สำรอง?', html: '<b>' + name + '</b>', showCancelButton: true,
            confirmButtonText: 'ลบ', confirmButtonColor: '#FF6B6B', cancelButtonText: 'ยกเลิก' }).then(function (r) {
            if (!r.isConfirmed) return;
            var fd = new FormData(); fd.append('action', 'delete'); fd.append('name', name);
            post(fd).then(function (d) {
                if (d.status === 'ok') { location.reload(); }
                else { Swal.fire({ icon: 'error', title: 'ลบไม่สำเร็จ', text: d.message || '', confirmButtonColor: '#4D96FF' }); }
            });
        });
    });
});

function confirmRestore(html, sendFd) {
    Swal.fire({
        icon: 'warning', title: 'ยืนยันการกู้คืน', confirmButtonColor: '#FF6B6B', cancelButtonText: 'ยกเลิก',
        html: html + '<br><br>การกู้คืนจะ <b>เขียนทับข้อมูลปัจจุบัน</b><br>พิมพ์ชื่อฐานข้อมูล <b>' + DBNAME + '</b> เพื่อยืนยัน',
        input: 'text', inputPlaceholder: 'ชื่อฐานข้อมูล', showCancelButton: true, confirmButtonText: 'กู้คืน',
        preConfirm: function (v) { if (String(v).trim() !== DBNAME) { Swal.showValidationMessage('ชื่อไม่ถูกต้อง'); return false; } return true; }
    }).then(function (r) {
        if (!r.isConfirmed) return;
        Swal.fire({ title: 'กำลังกู้คืน…', allowOutsideClick: false, didOpen: function () { Swal.showLoading(); } });
        sendFd(String(r.value).trim()).then(function (d) {
            if (d.status === 'ok') {
                Swal.fire({ icon: 'success', title: 'กู้คืนสำเร็จ', confirmButtonColor: '#4D96FF',
                    html: 'วิธี: ' + d.method + (d.statements ? '<br>' + Number(d.statements).toLocaleString() + ' คำสั่ง' : '') });
            } else { Swal.fire({ icon: 'error', title: 'กู้คืนไม่สำเร็จ', text: d.message || '', confirmButtonColor: '#4D96FF' }); }
        }).catch(function () { Swal.fire({ icon: 'error', title: 'ผิดพลาด', confirmButtonColor: '#4D96FF' }); });
    });
}

document.querySelectorAll('.js-restore').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var name = btn.getAttribute('data-name');
        confirmRestore('กู้คืนจาก <b>' + name + '</b>', function (confirm) {
            var fd = new FormData(); fd.append('action', 'restore'); fd.append('name', name); fd.append('confirm', confirm);
            return post(fd);
        });
    });
});

document.getElementById('formUpload').addEventListener('submit', function (e) {
    e.preventDefault();
    var input = this.querySelector('input[type=file]');
    if (!input.files.length) return;
    confirmRestore('กู้คืนจากไฟล์ <b>' + input.files[0].name + '</b>', function (confirm) {
        var fd = new FormData(); fd.append('action', 'restore'); fd.append('file', input.files[0]); fd.append('confirm', confirm);
        return post(fd);
    });
});
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
