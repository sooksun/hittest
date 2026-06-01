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
    <link rel="icon" href="images/logohittest.png" type="image/png">
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
    <a href="my_dashboard.php"><img src="images/logohittest.png" alt="HIT-TEST" style="height:38px;width:38px;object-fit:contain;display:block"></a>
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
    <script>
    (function () {
        'use strict';
        var WORDS = (window.PRACTICE && window.PRACTICE.words) || [];
        var SET   = (window.PRACTICE && window.PRACTICE.set) || {};
        for (var i = WORDS.length - 1; i > 0; i--) {     // สลับลำดับ (Fisher–Yates)
            var j = Math.floor(Math.random() * (i + 1));
            var t = WORDS[i]; WORDS[i] = WORDS[j]; WORDS[j] = t;
        }
        var idx = 0, score = 0, total = WORDS.length, attempts = 0, solved = false;

        var el = function (id) { return document.getElementById(id); };
        var norm = function (s) { return String(s == null ? '' : s).replace(/\s+/g, '').trim(); };

        // ---- บันทึกผลรายคำลงเซิร์ฟเวอร์ (ตัวตนมาจาก session ฝั่ง server) ----
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

        // ---- เสียงดริ๊ง ----
        var chime = new Audio('sounds/correct.mp3'); chime.preload = 'auto';
        var audioUnlocked = false;
        // ปลดล็อกเสียงด้วย user-gesture แรก (มือถือบล็อก autoplay เสียงที่ไม่ได้เกิดจากการแตะ)
        function unlockAudio() {
            if (audioUnlocked) { return; }
            audioUnlocked = true;
            try {
                chime.muted = true;
                var p = chime.play();
                if (p && p.then) { p.then(function () { chime.pause(); chime.currentTime = 0; chime.muted = false; })
                                    .catch(function () { chime.muted = false; }); }
                else { chime.pause(); chime.currentTime = 0; chime.muted = false; }
            } catch (e) { chime.muted = false; }
        }
        function playChime() { try { chime.currentTime = 0; chime.play(); } catch (e) {} }

        // ---- ฟังคำ (Botnoi ผ่าน tts.php, สำรองด้วย TTS เบราว์เซอร์) ----
        var ttsAudio = null, lastSpeakText = '', lastSpeakAt = 0;
        function speakFallback(text) {
            if (wantListen || !('speechSynthesis' in window)) { return; }
            window.speechSynthesis.cancel();
            var u = new SpeechSynthesisUtterance(text); u.lang = 'th-TH'; u.rate = 0.9;
            window.speechSynthesis.speak(u);
        }
        function speak(text) {
            var t = Date.now();
            if (text === lastSpeakText && (t - lastSpeakAt) < 800) { return; }
            lastSpeakText = text; lastSpeakAt = t;
            stopListen(); stopAudio();
            fetch('tts.php', { method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: 'text=' + encodeURIComponent(text) })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (wantListen) { return; }
                    if (d.status === 'ok' && d.audio_url) {
                        ttsAudio = new Audio(d.audio_url);
                        var pr = ttsAudio.play();
                        if (pr && pr.then) { pr.catch(function () { lastSpeakText = ''; speakFallback(text); }); }
                    } else { speakFallback(text); }
                })
                .catch(function () { speakFallback(text); });
        }

        // ---- Speech Recognition ----
        var SR = window.SpeechRecognition || window.webkitSpeechRecognition;
        var recog = null, listening = false;
        var wantListen = false, gotResult = false, retried = false, lastErr = '', micWarmed = false;
        if (SR) {
            recog = new SR();
            recog.lang = 'th-TH'; recog.interimResults = true; recog.continuous = true; recog.maxAlternatives = 5;
            recog.onstart = function () {
                listening = true;
                el('pMic').innerHTML = '🛑 หยุด (กำลังฟัง…)';
                el('pMic').classList.replace('ht-btn--green', 'ht-btn--coral');
            };
            recog.onerror = function (e) { lastErr = (e && e.error) || ''; };
            recog.onend = function () {
                listening = false;
                el('pMic').innerHTML = '🎤 อ่าน';
                el('pMic').classList.replace('ht-btn--coral', 'ht-btn--green');
                if (wantListen && !gotResult && !retried && (lastErr === '' || lastErr === 'no-speech' || lastErr === 'aborted')) {
                    retried = true;
                    setTimeout(function () { if (wantListen && !listening) { try { recog.start(); } catch (e) {} } }, 220);
                    return;
                }
                if (wantListen && !gotResult) {
                    el('pHeard').textContent =
                        lastErr === 'not-allowed' ? '⚠️ กรุณาอนุญาตไมโครโฟน'
                        : lastErr === 'audio-capture' ? '⚠️ ไม่พบไมโครโฟน'
                        : lastErr === 'network' ? '⚠️ เชื่อมต่ออินเทอร์เน็ตไม่ได้'
                        : 'ไม่ได้ยินเสียง — กด 🎤 อ่าน อีกครั้ง';
                }
                wantListen = false;
            };
            recog.onresult = function (e) {
                var interim = '', finals = [], top = '';
                for (var k = e.resultIndex; k < e.results.length; k++) {
                    var r = e.results[k];
                    if (r.isFinal) { gotResult = true; if (!top) { top = r[0].transcript; }
                        for (var a = 0; a < r.length; a++) { finals.push(r[a].transcript); } }
                    else { interim += r[0].transcript; }
                }
                el('pHeard').textContent = (top || interim) ? ('ได้ยิน: ' + (top || interim)) : '';
                if (finals.length) { check(finals); }
            };
        } else {
            el('pUnsupported').classList.remove('d-none');
            el('pMic').disabled = true;
        }

        // ---- เทียบเสียง (รองรับคำพ้องเสียง) ----
        var FINAL_SND = {
            'ก':'ก','ข':'ก','ค':'ก','ฆ':'ก','ง':'ง',
            'จ':'ด','ช':'ด','ซ':'ด','ฌ':'ด','ฎ':'ด','ฏ':'ด','ฐ':'ด','ฑ':'ด','ฒ':'ด','ด':'ด','ต':'ด','ถ':'ด','ท':'ด','ธ':'ด','ศ':'ด','ษ':'ด','ส':'ด',
            'ญ':'น','ณ':'น','น':'น','ร':'น','ล':'น','ฬ':'น','ม':'ม','ย':'ย','ว':'ว',
            'บ':'บ','ป':'บ','ผ':'บ','ฝ':'บ','พ':'บ','ฟ':'บ','ภ':'บ'
        };
        function isCons(c) { return c >= 'ก' && c <= 'ฮ'; }
        function isVowel(c) { return (c >= 'ะ' && c <= 'ฺ') || (c >= 'เ' && c <= 'ๅ'); }
        function soundKey(s) {
            s = norm(s).replace(/[ก-ฮ]์/g, '').replace(/์/g, '');
            var ch = s.split(''), first = -1, last = -1;
            for (var i = 0; i < ch.length; i++) { if (isCons(ch[i])) { if (first < 0) { first = i; } last = i; } }
            if (last > first) {
                var vowelAfter = false;
                for (var j = last + 1; j < ch.length; j++) { if (isVowel(ch[j])) { vowelAfter = true; break; } }
                if (!vowelAfter) {
                    var fin = last;
                    if (ch[last] === 'ร' && last - 1 > first && isCons(ch[last - 1])) {
                        var vBefore = false;
                        for (var k = 0; k < last - 1; k++) { if (isVowel(ch[k])) { vBefore = true; break; } }
                        if (vBefore) { ch.splice(last, 1); fin = last - 1; }
                    }
                    if (FINAL_SND[ch[fin]]) { ch[fin] = FINAL_SND[ch[fin]]; }
                }
            }
            return ch.join('');
        }
        function isCorrect(said, cur) {
            var targets = [cur.word];
            String(cur.spoken || '').split(',').forEach(function (x) { x = x.trim(); if (x) { targets.push(x); } });
            for (var t = 0; t < targets.length; t++) {
                if (norm(said) === norm(targets[t])) { return true; }
                if (soundKey(said) === soundKey(targets[t])) { return true; }
            }
            return false;
        }
        function check(saidList) {
            var cur = WORDS[idx]; if (!cur) { return; }
            if (typeof saidList === 'string') { saidList = [saidList]; }
            attempts++;
            var matched = false;
            for (var i = 0; i < saidList.length; i++) { if (isCorrect(saidList[i], cur)) { matched = true; break; } }
            if (matched) {
                score++; el('pScore').textContent = score;
                solved = true;
                addResult(cur.word, attempts);
                savePractice(cur, attempts, 1);      // บันทึก: อ่านถูก
                stopListen(); playChime();
                Swal.fire({ icon: 'success', title: 'อ่านถูกต้อง! 🎉',
                    html: 'คำว่า <b>"' + cur.word + '"</b>', timer: 1500, showConfirmButton: false })
                    .then(function () { next(); });
            } else {
                stopListen();
                el('pHeard').textContent = 'ได้ยิน: ' + (saidList[0] || '');
                Swal.fire({ icon: 'error', title: 'ยังไม่ถูก ลองอีกครั้ง',
                    html: 'คำว่า <b>"' + cur.word + '"</b>', confirmButtonText: 'ลองอีกครั้ง', confirmButtonColor: '#FF6B6B' });
            }
        }

        function render() {
            var cur = WORDS[idx];
            el('pWord').textContent = cur ? cur.word : '—';
            el('pHeard').textContent = '';
            attempts = 0; solved = false;
            el('pBar').style.width = (total ? Math.round(idx / total * 100) : 0) + '%';
        }
        function addResult(word, tries) {
            el('pResultsWrap').style.display = '';
            var color = tries === 1 ? '#1E7B45' : (tries <= 3 ? '#9A6B00' : '#B4232B');
            var th = document.createElement('th'); th.textContent = word; th.style.textAlign = 'center'; th.style.whiteSpace = 'nowrap';
            el('pResHead').appendChild(th);
            var td = document.createElement('td'); td.textContent = tries; td.style.textAlign = 'center'; td.style.fontWeight = '800'; td.style.color = color;
            el('pResRow').appendChild(td);
        }
        function next() {
            // ถ้าข้ามคำโดยยังไม่ถูก → บันทึกเป็น "ข้าม" (correct=0)
            var cur = WORDS[idx];
            if (cur && !solved) { savePractice(cur, attempts, 0); }
            stopListen();
            idx++;
            if (idx >= total) { finish(); return; }
            render();
        }
        function finish() {
            el('pBar').style.width = '100%';
            Swal.fire({ icon: 'success', title: 'ฝึกครบ ' + total + ' คำแล้ว! 🎉',
                html: 'อ่านถูก <b>' + score + '</b> / ' + total + ' คำ<br><span style="color:var(--ink-soft)">บันทึกผลให้คุณครูแล้ว</span>',
                showCancelButton: true, confirmButtonText: 'ฝึกชุดนี้อีกครั้ง', cancelButtonText: 'เลือกชุดใหม่', confirmButtonColor: '#4D96FF'
            }).then(function (r) { if (r.isConfirmed) { location.reload(); } else { location.href = 'my_practice.php'; } });
        }

        el('pSpeak').addEventListener('click', function () { unlockAudio(); var c = WORDS[idx]; if (c) { speak(c.word); } });
        function stopAudio() {
            if (ttsAudio) { try { ttsAudio.pause(); } catch (e) {} ttsAudio = null; }
            if ('speechSynthesis' in window) { try { window.speechSynthesis.cancel(); } catch (e) {} }
        }
        function stopListen() { wantListen = false; if (recog && listening) { try { recog.stop(); } catch (e) {} } }
        function startListen() {
            if (!recog || listening) { return; }
            stopAudio(); el('pHeard').textContent = '';
            wantListen = true; gotResult = false; retried = false; lastErr = '';
            var go = function () {
                try { recog.start(); }
                catch (e) { try { recog.stop(); } catch (_) {} setTimeout(function () { try { recog.start(); } catch (__) {} }, 300); }
            };
            if (!micWarmed && navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
                navigator.mediaDevices.getUserMedia({ audio: true })
                    .then(function (st) { micWarmed = true; st.getTracks().forEach(function (t) { t.stop(); }); go(); })
                    .catch(function () { go(); });
            } else { go(); }
        }
        el('pMic').addEventListener('click', function () { unlockAudio(); if (!recog) { return; } if (listening) { stopListen(); } else { startListen(); } });
        el('pNext').addEventListener('click', next);

        if (total === 0) { el('pWord').textContent = 'ไม่มีคำในชุดนี้'; } else { render(); }
    })();
    </script>
<?php endif; ?>
</main>
</body>
</html>
