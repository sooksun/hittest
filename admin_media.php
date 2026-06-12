<?php
/**
 * admin_media.php — หน้าจัดการ "สร้างสื่อในตัว" (เสียง Botnoi + ภาพ ComfyUI) — ผู้ดูแลระบบ
 *   • ดูสถานะสื่อรายคำ + ฟิลเตอร์ (ชั้น/ระดับ/สถานะ/ค้นหา)
 *   • สร้างเสียง/ภาพทันที, แก้ prompt แล้วสร้างภาพใหม่
 *   • เข้าคิวทุกคำที่ยังไม่มีสื่อ (worker = scripts/process_media_jobs.php)
 * การกระทำทั้งหมดยิงไป admin_media_action.php (POST JSON)
 */
require __DIR__ . '/includes/admin_auth.php';
require __DIR__ . '/includes/media_jobs.php';

$pdo = db();

// โหลด config (ไม่บังคับ) เพื่อโชว์สถานะการตั้งค่า
foreach (['config/botnoi.php', 'config/media_gen.php'] as $cf) {
    $p = __DIR__ . '/' . $cf;
    if (is_file($p)) { require_once $p; }
}
$botnoiOk    = defined('BOTNOI_TOKEN') && BOTNOI_TOKEN !== '';
$llmProvider = defined('LLM_PROVIDER') ? LLM_PROVIDER : 'none';
$comfyHost   = defined('COMFYUI_API_URL') ? COMFYUI_API_URL : '(ยังไม่ตั้งค่า)';
$checkpoint  = defined('COMFYUI_CHECKPOINT') ? COMFYUI_CHECKPOINT : '';

// ── ฟิลเตอร์ ─────────────────────────────────────────────────────────────────
$grade  = isset($_GET['grade'])  && $_GET['grade']  !== '' ? max(1, min(6, (int)$_GET['grade']))  : 0;
$level  = isset($_GET['level'])  && $_GET['level']  !== '' ? max(1, min(3, (int)$_GET['level']))  : 0;
$status = (string)($_GET['status'] ?? 'all');
$q      = trim((string)($_GET['q'] ?? ''));
$page   = max(1, (int)($_GET['page'] ?? 1));
$per    = 50;

$where = ['word IS NOT NULL', "word <> ''"];
$params = [];
if ($grade) { $where[] = 'class_id = ?'; $params[] = $grade; }
if ($level) { $where[] = 'level = ?';    $params[] = $level; }
switch ($status) {
    case 'no_audio':      $where[] = "(sound_path IS NULL OR sound_path = '')"; break;
    case 'has_audio':     $where[] = "(sound_path IS NOT NULL AND sound_path <> '')"; break;
    case 'no_image':      $where[] = "image_status <> 'DONE'"; break;
    case 'img_done':      $where[] = "image_status = 'DONE'"; break;
    case 'img_failed':    $where[] = "image_status = 'FAILED'"; break;
    case 'img_ambiguous': $where[] = "image_status = 'AMBIGUOUS'"; break;
}
if ($q !== '') { $where[] = '(word LIKE ? OR spoken_form LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }
$whereSql = implode(' AND ', $where);

// นับทั้งหมดตามฟิลเตอร์ (สำหรับ pagination)
$cst = $pdo->prepare("SELECT COUNT(*) FROM wordstest WHERE {$whereSql}");
$cst->execute($params);
$totalRows = (int)$cst->fetchColumn();
$pages = max(1, (int)ceil($totalRows / $per));
$page  = min($page, $pages);
$offset = ($page - 1) * $per;

// รายการคำ
$lst = $pdo->prepare(
    "SELECT id, word, spoken_form, class_id, level, sound_path, image_path, image_status, image_reason
     FROM wordstest WHERE {$whereSql} ORDER BY class_id, level, id LIMIT {$per} OFFSET {$offset}"
);
$lst->execute($params);
$rows = $lst->fetchAll();

// สถิติรวม (ทั้งฐาน — ไม่ตามฟิลเตอร์)
$stat = $pdo->query(
    "SELECT
        COUNT(*) total,
        SUM(sound_path IS NOT NULL AND sound_path <> '') has_audio,
        SUM(image_status = 'DONE') img_done,
        SUM(image_status = 'FAILED') img_failed,
        SUM(image_status = 'AMBIGUOUS') img_ambig
     FROM wordstest WHERE word IS NOT NULL AND word <> ''"
)->fetch();
$jobCounts = media_jobs_counts($pdo);

/** helper: image_original จาก JSON */
$imgOriginal = static function (?string $raw): ?string {
    if (!$raw) { return null; }
    $d = json_decode($raw, true);
    return is_array($d) ? ($d['image_original'] ?? $d['original'] ?? $d['img_full'] ?? null) : $raw;
};
/** path สำหรับใช้เป็น src แบบ relative (ตัด / นำหน้า → อิงจาก /newhittest/) */
$rel = static fn (?string $p): string => $p ? ltrim($p, '/') : '';

$active     = 'admin_media';
$page_title = 'สร้างสื่อ (เสียง/ภาพ)';
require __DIR__ . '/includes/header.php';
?>
<h1 class="h4 mb-1">🎨 สร้างสื่อในตัว — เสียง (Botnoi) + ภาพ (ComfyUI)</h1>
<p class="text-muted small mb-3">สร้าง/จัดการไฟล์เสียงอ่านและภาพประกอบของคำศัพท์ ใช้กับเกมฝึกอ่านทั้ง 5 เกม</p>

<!-- สถานะการตั้งค่า + สถิติ -->
<div class="row g-2 mb-3">
  <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body py-2">
    <div class="small text-muted">เสียง (มี/ทั้งหมด)</div>
    <div class="fs-5 fw-bold"><?= number_format((int)$stat['has_audio']) ?> / <?= number_format((int)$stat['total']) ?></div>
  </div></div></div>
  <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body py-2">
    <div class="small text-muted">ภาพสำเร็จ (DONE)</div>
    <div class="fs-5 fw-bold"><?= number_format((int)$stat['img_done']) ?>
      <span class="small text-muted">/ <?= number_format((int)$stat['total']) ?></span></div>
  </div></div></div>
  <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body py-2">
    <div class="small text-muted">คิวงาน (queued/processing)</div>
    <div class="fs-5 fw-bold" id="jobActive"><?= $jobCounts['queued'] + $jobCounts['processing'] ?></div>
  </div></div></div>
  <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body py-2">
    <div class="small text-muted">งานล้มเหลว</div>
    <div class="fs-5 fw-bold text-danger" id="jobFailed"><?= $jobCounts['failed'] ?></div>
  </div></div></div>
</div>

<div class="alert alert-light border small d-flex flex-wrap gap-3 align-items-center">
  <span>Botnoi: <?= $botnoiOk ? '<span class="text-success fw-bold">พร้อม</span>' : '<span class="text-danger fw-bold">ยังไม่ตั้ง token</span>' ?></span>
  <span>ComfyUI: <code><?= htmlspecialchars($comfyHost) ?></code><?= $checkpoint ? ' · <code>' . htmlspecialchars($checkpoint) . '</code>' : '' ?></span>
  <span>LLM: <code><?= htmlspecialchars($llmProvider) ?></code><?= $llmProvider === 'none' ? ' <span class="text-muted">(ภาพ: ใช้คำที่พิมพ์เอง/override)</span>' : '' ?></span>
</div>

<!-- ฟิลเตอร์ -->
<form class="row g-2 align-items-end mb-3" method="get">
  <div class="col-6 col-md-2">
    <label class="form-label small mb-1">ชั้น ป.</label>
    <select name="grade" class="form-select form-select-sm">
      <option value="">ทุกชั้น</option>
      <?php for ($g = 1; $g <= 6; $g++): ?>
        <option value="<?= $g ?>"<?= $grade === $g ? ' selected' : '' ?>>ป.<?= $g ?></option>
      <?php endfor; ?>
    </select>
  </div>
  <div class="col-6 col-md-2">
    <label class="form-label small mb-1">ระดับ</label>
    <select name="level" class="form-select form-select-sm">
      <option value="">ทุกระดับ</option>
      <?php foreach ([1 => 'ง่าย', 2 => 'ปานกลาง', 3 => 'ยาก'] as $lv => $nm): ?>
        <option value="<?= $lv ?>"<?= $level === $lv ? ' selected' : '' ?>><?= $nm ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-6 col-md-3">
    <label class="form-label small mb-1">สถานะ</label>
    <select name="status" class="form-select form-select-sm">
      <?php
      $stOpts = ['all' => 'ทั้งหมด', 'no_audio' => 'ยังไม่มีเสียง', 'has_audio' => 'มีเสียงแล้ว',
                 'no_image' => 'ยังไม่มีภาพ', 'img_done' => 'มีภาพแล้ว', 'img_failed' => 'ภาพล้มเหลว',
                 'img_ambiguous' => 'ภาพ: วาดไม่ได้'];
      foreach ($stOpts as $k => $nm): ?>
        <option value="<?= $k ?>"<?= $status === $k ? ' selected' : '' ?>><?= $nm ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-6 col-md-3">
    <label class="form-label small mb-1">ค้นหาคำ</label>
    <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" class="form-control form-control-sm" placeholder="คำ / คำอ่าน">
  </div>
  <div class="col-12 col-md-2 d-grid">
    <button class="btn btn-sm btn-primary">กรอง</button>
  </div>
</form>

<!-- bulk -->
<div class="d-flex flex-wrap gap-2 mb-2">
  <button class="btn btn-sm btn-outline-success" id="bulkAudio">▶ เข้าคิวเสียงที่ยังไม่มี (ตามฟิลเตอร์)</button>
  <button class="btn btn-sm btn-outline-primary" id="bulkImage">🖼️ เข้าคิวภาพที่ยังไม่มี (ตามฟิลเตอร์)</button>
  <button class="btn btn-sm btn-success" id="runQueue">⚙️ ดำเนินการคิวเดี๋ยวนี้</button>
  <button class="btn btn-sm btn-outline-secondary ms-auto" id="refreshJobs">↻ รีเฟรชสถานะคิว</button>
</div>
<p class="text-muted small mb-2" id="runStatus">กด <b>“ดำเนินการคิวเดี๋ยวนี้”</b> เพื่อประมวลผลในเว็บ (ทำทีละชุดจนหมดคิว · คลิกซ้ำเพื่อหยุด) — หรือรัน worker เอง: <code>php scripts/process_media_jobs.php</code> (ตั้ง Task Scheduler ก็ได้)</p>

<!-- ตารางคำ -->
<div class="table-responsive">
  <table class="table table-sm align-middle">
    <thead><tr>
      <th>#</th><th>คำ</th><th>ป./ระดับ</th><th>เสียง</th><th>ภาพ</th><th class="text-end">จัดการ</th>
    </tr></thead>
    <tbody>
    <?php foreach ($rows as $r):
        $img = $imgOriginal($r['image_path']); ?>
      <tr data-id="<?= (int)$r['id'] ?>" data-word="<?= htmlspecialchars($r['word'], ENT_QUOTES) ?>">
        <td class="text-muted small"><?= (int)$r['id'] ?></td>
        <td><span class="fw-bold"><?= htmlspecialchars($r['word']) ?></span>
            <?php if ($r['spoken_form']): ?><div class="small text-muted"><?= htmlspecialchars($r['spoken_form']) ?></div><?php endif; ?></td>
        <td class="small">ป.<?= (int)$r['class_id'] ?> · <?= [1=>'ง่าย',2=>'ปานกลาง',3=>'ยาก'][$r['level']] ?? '-' ?></td>
        <td>
          <?php if ($r['sound_path']): ?>
            <button class="btn btn-sm btn-light js-play" data-src="<?= htmlspecialchars($rel($r['sound_path'])) ?>" title="เล่นเสียง">🔊 เล่น</button>
          <?php else: ?><span class="badge bg-secondary-subtle text-secondary">ไม่มี</span><?php endif; ?>
        </td>
        <td>
          <?php if ($img && $r['image_status'] === 'DONE'): ?>
            <a href="<?= htmlspecialchars($rel($img)) ?>" target="_blank">
              <img src="<?= htmlspecialchars($rel($img)) ?>" alt="" style="width:44px;height:44px;object-fit:cover;border-radius:6px;border:1px solid #ddd"></a>
          <?php else:
            $badge = ['EMPTY'=>'bg-secondary-subtle text-secondary','FAILED'=>'bg-danger-subtle text-danger',
                      'AMBIGUOUS'=>'bg-warning-subtle text-warning','GENERATING'=>'bg-info-subtle text-info',
                      'QUEUED'=>'bg-info-subtle text-info'][$r['image_status']] ?? 'bg-secondary-subtle text-secondary'; ?>
            <span class="badge <?= $badge ?>" title="<?= htmlspecialchars((string)$r['image_reason']) ?>"><?= htmlspecialchars($r['image_status']) ?></span>
          <?php endif; ?>
        </td>
        <td class="text-end text-nowrap">
          <button class="btn btn-sm btn-outline-success js-gen-audio" title="สร้างเสียงเดี๋ยวนี้">🔊+</button>
          <button class="btn btn-sm btn-outline-primary js-gen-image" title="สร้าง/แก้ภาพ">🖼️+</button>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="6" class="text-center text-muted py-4">ไม่พบคำตามเงื่อนไข</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<!-- pagination -->
<?php if ($pages > 1):
    $qs = function ($p) use ($grade, $level, $status, $q) {
        return 'admin_media.php?' . http_build_query(['grade'=>$grade ?: '', 'level'=>$level ?: '', 'status'=>$status, 'q'=>$q, 'page'=>$p]);
    }; ?>
<nav class="d-flex justify-content-between align-items-center">
  <span class="small text-muted">ทั้งหมด <?= number_format($totalRows) ?> คำ · หน้า <?= $page ?>/<?= $pages ?></span>
  <div class="btn-group btn-group-sm">
    <a class="btn btn-outline-secondary<?= $page <= 1 ? ' disabled' : '' ?>" href="<?= $qs(max(1,$page-1)) ?>">‹ ก่อน</a>
    <a class="btn btn-outline-secondary<?= $page >= $pages ? ' disabled' : '' ?>" href="<?= $qs(min($pages,$page+1)) ?>">ถัดไป ›</a>
  </div>
</nav>
<?php endif; ?>

<!-- งานล่าสุด -->
<h2 class="h6 mt-4">งานในคิวล่าสุด
  <button class="btn btn-sm btn-link" id="clearDone">ล้างที่เสร็จแล้ว</button>
  <button class="btn btn-sm btn-link text-danger" id="clearFailed">ล้างที่ล้มเหลว</button>
</h2>
<div class="table-responsive"><table class="table table-sm small" id="jobsTable">
  <thead><tr><th>#</th><th>ชนิด</th><th>คำ</th><th>สถานะ</th><th>ครั้ง</th><th>ข้อผิดพลาด</th><th></th></tr></thead>
  <tbody><tr><td colspan="7" class="text-muted">กด “รีเฟรชสถานะคิว”</td></tr></tbody>
</table></div>

<!-- modal: สร้าง/แก้ภาพ -->
<div class="modal fade" id="imgModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title">สร้างภาพ: <span id="imWord"></span></h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <p class="small text-muted">ใส่คำอธิบายภาพเป็น<strong>ภาษาอังกฤษ</strong> (เช่น <em>a single red apple on white background</em>) ระบบจะแต่ง prompt ให้เอง — ถ้าคำมี override อยู่แล้วเว้นว่างได้</p>
    <div class="mb-2">
      <label class="form-label small mb-1">คำอธิบายภาพ (อังกฤษ)</label>
      <input type="text" id="imWhat" class="form-control form-control-sm" placeholder="a single ... on ... background">
    </div>
    <details class="mb-2"><summary class="small text-muted">ขั้นสูง: กำหนด prompt / seed เอง</summary>
      <div class="mt-2">
        <label class="form-label small mb-1">positive prompt (ถ้าใส่จะข้ามตัวแต่งอัตโนมัติ)</label>
        <textarea id="imPos" class="form-control form-control-sm" rows="2"></textarea>
        <label class="form-label small mb-1 mt-2">negative prompt</label>
        <textarea id="imNeg" class="form-control form-control-sm" rows="2"></textarea>
        <div class="row g-2 mt-1">
          <div class="col"><label class="form-label small mb-1">seed</label><input type="number" id="imSeed" class="form-control form-control-sm" placeholder="สุ่ม"></div>
          <div class="col"><label class="form-label small mb-1">checkpoint</label><input type="text" id="imCkpt" class="form-control form-control-sm" placeholder="<?= htmlspecialchars($checkpoint) ?>"></div>
        </div>
      </div>
    </details>
    <div class="form-check"><input class="form-check-input" type="checkbox" id="imForce" checked>
      <label class="form-check-label small" for="imForce">บังคับสร้างใหม่แม้มีภาพเดิม</label></div>
    <div id="imResult" class="mt-3 text-center"></div>
  </div>
  <div class="modal-footer">
    <button class="btn btn-sm btn-secondary" data-bs-dismiss="modal">ปิด</button>
    <button class="btn btn-sm btn-primary" id="imGo">🖼️ สร้างภาพเดี๋ยวนี้</button>
  </div>
</div></div></div>

<script>
const ACTION = 'admin_media_action.php';
async function post(payload) {
  const res = await fetch(ACTION, {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload)});
  return res.json();
}
// เล่นเสียง
document.querySelectorAll('.js-play').forEach(b => b.addEventListener('click', () => {
  new Audio(b.dataset.src).play().catch(()=>{});
}));
// สร้างเสียงทันที
document.querySelectorAll('.js-gen-audio').forEach(b => b.addEventListener('click', async () => {
  const tr = b.closest('tr'); const id = +tr.dataset.id;
  b.disabled = true; b.textContent = '⏳';
  const r = await post({action:'generate_now', type:'audio', wordId:id, force:true});
  b.disabled = false; b.textContent = '🔊+';
  if (r.ok) { Swal.fire({icon:'success', title:'สร้างเสียงแล้ว', timer:1200, showConfirmButton:false}); setTimeout(()=>location.reload(), 900); }
  else { Swal.fire({icon:'error', title:'ไม่สำเร็จ', text:r.error||r.message||''}); }
}));
// modal ภาพ — สร้าง instance แบบ lazy ตอนคลิก (bootstrap JS โหลดจาก footer ทีหลัง inline script นี้)
let imWordId = 0;
document.querySelectorAll('.js-gen-image').forEach(b => b.addEventListener('click', () => {
  const tr = b.closest('tr'); imWordId = +tr.dataset.id;
  document.getElementById('imWord').textContent = tr.dataset.word + ' (#' + imWordId + ')';
  ['imWhat','imPos','imNeg','imSeed','imCkpt'].forEach(id => document.getElementById(id).value='');
  document.getElementById('imResult').innerHTML='';
  bootstrap.Modal.getOrCreateInstance(document.getElementById('imgModal')).show();
}));
document.getElementById('imGo').addEventListener('click', async () => {
  const btn = document.getElementById('imGo'); btn.disabled = true; btn.textContent = '⏳ กำลังสร้าง (อาจนาน 1–2 นาที)...';
  const r = await post({action:'generate_now', type:'image', wordId:imWordId,
    force: document.getElementById('imForce').checked,
    whatToDrawEn: document.getElementById('imWhat').value,
    positive: document.getElementById('imPos').value,
    negative: document.getElementById('imNeg').value,
    seed: document.getElementById('imSeed').value,
    checkpoint: document.getElementById('imCkpt').value});
  btn.disabled = false; btn.textContent = '🖼️ สร้างภาพเดี๋ยวนี้';
  const box = document.getElementById('imResult');
  if (r.ok && r.imagePath) {
    box.innerHTML = '<div class="text-success mb-2">สำเร็จ (seed '+(r.seed||'')+')</div><img src="'+r.imagePath.replace(/^\//,'')+'?t='+Date.now()+'" style="max-width:100%;border-radius:8px">';
  } else {
    box.innerHTML = '<div class="alert alert-warning small mb-0">'+(r.status||'')+': '+(r.error||r.message||'ไม่สำเร็จ')+'</div>';
  }
});
// bulk
async function bulk(type, btn) {
  btn.disabled = true;
  const params = new URLSearchParams(location.search);
  const r = await post({action:'enqueue_missing', type, grade: params.get('grade')||'', level: params.get('level')||'', limit: 1000});
  btn.disabled = false;
  Swal.fire({icon:'success', title:'เข้าคิวแล้ว', html:'ใหม่ '+r.queued+' งาน · มีอยู่แล้ว '+r.existing+'<br><small>รัน <code>php scripts/process_media_jobs.php</code></small>'});
  loadJobs();
}
document.getElementById('bulkAudio').addEventListener('click', e => bulk('audio', e.target));
document.getElementById('bulkImage').addEventListener('click', e => bulk('image', e.target));
// ดำเนินการคิวในเว็บ (วนทีละชุดจนหมด) — คลิกซ้ำเพื่อหยุด
let queueRunning = false;
document.getElementById('runQueue').addEventListener('click', async function () {
  const btn = this, status = document.getElementById('runStatus');
  if (queueRunning) { queueRunning = false; return; }            // คลิกซ้ำ = หยุดหลังชุดปัจจุบัน
  queueRunning = true; btn.textContent = '⏸ หยุด'; btn.classList.remove('btn-success'); btn.classList.add('btn-warning');
  let done = 0, skip = 0, fail = 0;
  try {
    while (queueRunning) {
      const r = await post({action:'process_queue', limit: 2});
      if (!r.ok) { status.textContent = 'ผิดพลาด: ' + (r.message || ''); break; }
      done += r.done; skip += r.skip; fail += r.fail;
      status.innerHTML = 'กำลังดำเนินการ… สำเร็จ <b class="text-success">' + done + '</b> · ข้าม ' + skip +
        ' · ล้มเหลว <b class="text-danger">' + fail + '</b> · เหลือในคิว <b>' + r.remaining + '</b>';
      loadJobs();
      if (r.processed === 0 || r.remaining === 0) break;          // คิวหมด
    }
  } catch (e) { status.textContent = 'หยุด: ' + e; }
  queueRunning = false; btn.textContent = '⚙️ ดำเนินการคิวเดี๋ยวนี้'; btn.classList.remove('btn-warning'); btn.classList.add('btn-success');
  loadJobs();
  Swal.fire({icon:'success', title:'จบการดำเนินการคิว', html:'สำเร็จ ' + done + ' · ข้าม ' + skip + ' · ล้มเหลว ' + fail, timer:2600, showConfirmButton:false});
});
// jobs panel
async function loadJobs() {
  const r = await post({action:'jobs'});
  if (!r.ok) return;
  document.getElementById('jobActive').textContent = r.counts.queued + r.counts.processing;
  document.getElementById('jobFailed').textContent = r.counts.failed;
  const tb = document.querySelector('#jobsTable tbody');
  if (!r.jobs.length) { tb.innerHTML = '<tr><td colspan="7" class="text-muted">ไม่มีงาน</td></tr>'; return; }
  const badge = {queued:'secondary', processing:'info', done:'success', failed:'danger'};
  tb.innerHTML = r.jobs.map(j => '<tr><td>'+j.id+'</td><td>'+j.type+'</td><td>'+(j.word||'')+'</td>'+
    '<td><span class="badge bg-'+(badge[j.status]||'secondary')+'">'+j.status+'</span></td>'+
    '<td>'+j.attempts+'</td><td class="text-danger small">'+(j.last_error?String(j.last_error).slice(0,60):'')+'</td>'+
    '<td>'+(j.status==='failed'?'<button class="btn btn-sm btn-link js-retry" data-id="'+j.id+'">ลองใหม่</button>':'')+'</td></tr>').join('');
  tb.querySelectorAll('.js-retry').forEach(b => b.addEventListener('click', async () => {
    await post({action:'retry_job', jobId:+b.dataset.id}); loadJobs();
  }));
}
document.getElementById('refreshJobs').addEventListener('click', loadJobs);
document.getElementById('clearDone').addEventListener('click', async () => { await post({action:'clear_jobs', scope:'done'}); loadJobs(); });
document.getElementById('clearFailed').addEventListener('click', async () => { await post({action:'clear_jobs', scope:'failed'}); loadJobs(); });
loadJobs();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
