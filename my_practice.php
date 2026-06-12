<?php
/**
 * my_practice.php — ฝึกอ่านสำหรับ "นักเรียนที่ login" + บันทึกผลลง practice_log
 * ฟีเจอร์/การทำงานเหมือน practice.php (no-login) แต่รู้ว่าเป็นใคร และเก็บสถิติการอ่าน
 * ดีไซน์ Mobile-first · 2 หน้า: (1) เลือกชุดคำ  (2) ฝึกอ่านคำพื้นฐาน
 *
 * RBAC: เฉพาะ role=student (require_student) · บันทึกผูก stuid จาก session เท่านั้น
 */
session_start();
require __DIR__ . '/includes/student_auth.php';

$me = require_student();
$scid = (string)$me['sc_id'];

$class_id = (int)($_GET['class_id'] ?? 0);
$hittest  = (int)($_GET['hittest'] ?? 0);
$sethit   = (int)($_GET['sethit'] ?? 0);
$started  = ($class_id >= 1 && $class_id <= 6) && in_array($hittest, HITTESTS, true) && ($sethit >= 1 && $sethit <= 5);
$selClass = $class_id ?: ((int)$me['class_id'] ?: 1);     // ดีฟอลต์ = ชั้นของนักเรียน

$words = [];
if ($started) {
    $stmt = db()->prepare('SELECT id, word, spoken_form FROM words
        WHERE class_id = ? AND hittest = ? AND sethit = ? ORDER BY orders');
    $stmt->execute([$class_id, $hittest, $sethit]);
    $words = array_map(fn($w) => ['id' => (int)$w['id'], 'word' => $w['word'], 'spoken' => $w['spoken_form']], $stmt->fetchAll());
}
$cssver = @filemtime(__DIR__ . '/assets/css/theme.css') ?: '1';
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title>ฝึกอ่าน — HIT-TEST</title>
    <link rel="icon" href="images/newlogo.png" type="image/png">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/theme.css?v=<?= $cssver ?>" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { background: linear-gradient(160deg,#EAF2FF,#F7F3FF); min-height:100vh; margin:0; }
        .mp-top { background:#fff; box-shadow:0 2px 10px rgba(0,0,0,.06); position:sticky; top:0; z-index:20; }
        .mp-top__in { max-width:640px; margin:0 auto; padding:10px 16px; display:flex; align-items:center; gap:10px; }
        .mp-top__in strong { font-size:1rem; }
        .mp-wrap { max-width:640px; margin:0 auto; padding:18px 16px 40px; }

        /* การ์ดคำ — ใหญ่ อ่านง่ายบนมือถือ */
        .mp-card { background:#fff; border:4px solid #fff; border-radius:24px; box-shadow:var(--sh-lg);
                   padding:32px 18px; text-align:center; position:relative; overflow:hidden; }
        .mp-word { font-size:clamp(2.6rem, 14vw, 4.2rem); font-weight:800; line-height:1.15; color:var(--ink); word-break:break-word; }
        .mp-heard { font-size:1.15rem; min-height:1.5em; color:var(--ink-soft); margin-top:8px; }

        /* ปุ่ม — Mobile first: เต็มแถว/ใหญ่ แตะง่าย */
        .mp-actions { display:flex; flex-direction:column; gap:12px; margin:18px 0; }
        .mp-actions .ht-btn { width:100%; min-height:58px; font-size:1.15rem; display:flex; align-items:center; justify-content:center; gap:8px; }
        @media (min-width:480px) {
            .mp-actions { flex-direction:row; flex-wrap:wrap; }
            .mp-actions .ht-btn { flex:1 1 30%; }
        }

        .mp-bar { background:var(--c-blue-soft); border-radius:var(--r-pill); height:14px; overflow:hidden; }
        .mp-bar > div { height:100%; width:0; background:var(--c-blue); transition:width .3s ease; }

        .mp-results { margin-top:28px; }
        .mp-results .ht-table-wrap { overflow-x:auto; }
        .mp-results table { min-width:max-content; }

        /* หน้าเลือกชุด — selects ใหญ่ */
        .mp-pick .ht-select { min-height:52px; font-size:1.05rem; }
        .mp-pick label { font-weight:700; }
    </style>
</head>
<body>
<div class="mp-top"><div class="mp-top__in">
    <a href="my_dashboard.php"><img src="images/newlogo.png" alt="HIT-TEST" style="height:38px;width:38px;object-fit:contain;display:block"></a>
    <strong>🧒 <?= htmlspecialchars($me['stuname']) ?></strong>
    <a href="my_dashboard.php" class="ht-btn ht-btn--ghost ht-btn--sm" style="margin-left:auto">← แดชบอร์ด</a>
</div></div>

<main class="mp-wrap">
<?php if (!$started): ?>
    <!-- ===== หน้า 1: เลือกชุดคำที่ต้องการฝึก ===== -->
    <h1 style="font-size:1.5rem" class="mb-1">🗣️ ฝึกอ่านคำพื้นฐาน</h1>
    <p class="text-muted mb-4">เลือกชุดคำที่อยากฝึก แล้วเริ่มได้เลย — ระบบจะฟังและบันทึกผลให้</p>

    <div class="ht-card mp-pick" style="border-left:6px solid var(--c-blue)">
        <h3 class="mb-3">เลือกชุดคำ</h3>
        <form method="get" class="ht-stack" style="gap:16px">
            <div class="ht-field">
                <label class="ht-label">ชั้น</label>
                <select name="class_id" class="ht-select">
                    <?php for ($c = 1; $c <= 6; $c++): ?><option value="<?= $c ?>" <?= $c === $selClass ? 'selected' : '' ?>>ป.<?= $c ?></option><?php endfor; ?>
                </select>
            </div>
            <div class="ht-field">
                <label class="ht-label">รอบ</label>
                <select name="hittest" class="ht-select">
                    <?php foreach (HITTESTS as $h): ?><option value="<?= $h ?>">Hit-<?= $h ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="ht-field">
                <label class="ht-label">ชุดคำ</label>
                <select name="sethit" class="ht-select">
                    <?php for ($s = 1; $s <= 5; $s++): ?><option value="<?= $s ?>">ชุด <?= $s ?></option><?php endfor; ?>
                </select>
            </div>
            <button class="ht-btn ht-btn--lg ht-btn--block" type="submit" style="min-height:56px;font-size:1.15rem">▶️ เริ่มฝึกอ่าน (20 คำ)</button>
        </form>
        <p class="text-muted mt-3 mb-0" style="font-size:.9rem">💡 ใช้บน Google Chrome และอนุญาตไมโครโฟน · รองรับ https หรือ localhost</p>
    </div>
<?php else: ?>
    <!-- ===== หน้า 2: ฝึกอ่านคำพื้นฐาน ===== -->
    <div class="ht-row mb-3" style="gap:8px">
        <span class="ht-badge t-blue">ป.<?= $class_id ?> · Hit-<?= $hittest ?> · ชุด <?= $sethit ?></span>
        <span class="ht-badge ht-badge--done">อ่านถูก <span id="pScore">0</span> / <?= count($words) ?></span>
        <a href="my_practice.php" class="ht-btn ht-btn--ghost ht-btn--sm" style="margin-left:auto">เปลี่ยนชุด</a>
    </div>

    <div id="pUnsupported" class="ht-card mb-3 d-none" style="border-left:6px solid var(--c-coral)">
        <strong>เบราว์เซอร์นี้ไม่รองรับการฟังเสียง</strong>
        <p class="mb-0 text-muted">ใช้ Google Chrome ผ่าน localhost หรือ https — ยังกด "🔊 ฟังคำ" และ "ต่อไป" ได้</p>
    </div>

    <div class="mp-card mb-2">
        <div class="mp-word" id="pWord">—</div>
        <div class="mp-heard" id="pHeard"></div>
    </div>

    <div class="mp-actions">
        <button id="pSpeak" class="ht-btn ht-btn--ghost">🔊 ฟังคำ</button>
        <button id="pMic"   class="ht-btn ht-btn--green">🎤 อ่าน</button>
        <button id="pNext"  class="ht-btn ht-btn--purple">ต่อไป →</button>
    </div>

    <div class="mp-bar"><div id="pBar"></div></div>

    <div id="pResultsWrap" class="mp-results" style="display:none">
        <h3 class="mb-2">📋 ผลการฝึกอ่าน
            <span class="text-muted" style="font-size:.85rem;font-weight:500">— ตัวเลข = จำนวนครั้งที่อ่านกว่าจะถูก</span>
        </h3>
        <div class="ht-table-wrap">
            <table class="ht-table">
                <thead><tr id="pResHead"><th>คำ</th></tr></thead>
                <tbody><tr id="pResRow"><td class="fw-7">ครั้งที่อ่าน</td></tr></tbody>
            </table>
        </div>
    </div>

    <script>window.PRACTICE = <?= json_encode([
        'words' => $words,
        'set'   => ['class_id' => $class_id, 'hittest' => $hittest, 'sethit' => $sethit],
    ], JSON_UNESCAPED_UNICODE) ?>;</script>
    <script src="assets/js/practice-reader.js?v=<?= @filemtime(__DIR__ . '/assets/js/practice-reader.js') ?: '1' ?>"></script>
    <script>
    (function () {
        'use strict';
        var P = window.PRACTICE || {}, SET = P.set || {};
        // บันทึกผลรายคำลงเซิร์ฟเวอร์ (ตัวตนมาจาก session ฝั่ง server) — เรียกตอนอ่านถูก (1) และตอนข้ามทั้งที่ยังไม่ถูก (0)
        function savePractice(w, tries, correct) {
            if (!w) { return; }
            fetch('practice_save.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: 'class_id=' + SET.class_id + '&hittest=' + SET.hittest + '&sethit=' + SET.sethit +
                      '&word=' + encodeURIComponent(w.word) + '&word_id=' + (w.id || '') +
                      '&attempts=' + tries + '&correct=' + (correct ? 1 : 0)
            }).catch(function () {});
        }
        PracticeReader.init({
            words:    P.words || [],
            ttsUrl:   'tts.php',
            exitUrl:  'my_practice.php',
            onResult: savePractice
        });
    })();
    </script>
<?php endif; ?>
</main>
</body>
</html>
