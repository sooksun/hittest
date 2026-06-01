<?php
/**
 * practice.php — ฝึกอ่านคำพื้นฐาน (ตรวจคำอ่านอัตโนมัติด้วย Web Speech API)
 * อ้างอิงต้นแบบ autocheck2.html — อ่านคำจากตาราง words (คลังคำพื้นฐานระดับชาติ)
 * เลือก ชั้น/รอบ/ชุด → ฝึกอ่าน 20 คำ : ฟังคำ (TTS) + อ่านออกเสียง (STT) ตรวจกับ spoken_form
 * หมายเหตุ: Web Speech API ใช้ได้บน Chrome ผ่าน https หรือ localhost เท่านั้น
 */
require __DIR__ . '/includes/auth.php';

$class_id = (int)($_GET['class_id'] ?? 0);
$hittest  = (int)($_GET['hittest'] ?? 0);
$sethit   = (int)($_GET['sethit'] ?? 0);

$started = ($class_id >= 1 && $class_id <= 6) && valid_hit($hittest) && ($sethit >= 1 && $sethit <= 5);

$words = [];
if ($started) {
    $stmt = db()->prepare('SELECT word, spoken_form FROM words
        WHERE class_id = ? AND hittest = ? AND sethit = ? ORDER BY orders');
    $stmt->execute([$class_id, $hittest, $sethit]);
    $words = array_map(fn($w) => ['word' => $w['word'], 'spoken' => $w['spoken_form']], $stmt->fetchAll());
}

$page_title = 'ฝึกอ่าน';
$active = 'practice';
require __DIR__ . '/includes/header.php';
?>
<h2 class="mb-1">🗣️ ฝึกอ่านคำพื้นฐาน</h2>
<p class="text-muted mb-4">อ่านคำที่ปรากฏด้วยตัวเอง แล้วกด 🎤 อ่าน เพื่อให้ระบบตรวจและแจ้งผลทันที (กด 🔊 ฟังคำ ได้หากต้องการฟังเสียงตัวอย่าง)</p>

<?php if (!$started): ?>
    <!-- เลือกชุดคำที่จะฝึก -->
    <div class="ht-card" style="max-width:560px; border-left:6px solid var(--c-blue)">
        <h3 class="mb-3">เลือกชุดคำที่ต้องการฝึก</h3>
        <form method="get" class="row g-3 align-items-end">
            <div class="col-sm-4">
                <div class="ht-field">
                    <label class="ht-label">ชั้น</label>
                    <select name="class_id" class="ht-select">
                        <?php for ($c = 1; $c <= 6; $c++): ?><option value="<?= $c ?>" <?= $c === ($class_id ?: 1) ? 'selected' : '' ?>>ป.<?= $c ?></option><?php endfor; ?>
                    </select>
                </div>
            </div>
            <div class="col-sm-4">
                <div class="ht-field">
                    <label class="ht-label">รอบ</label>
                    <select name="hittest" class="ht-select">
                        <?php for ($h = 1; $h <= 3; $h++): ?><option value="<?= $h ?>">Hit-<?= $h ?></option><?php endfor; ?>
                    </select>
                </div>
            </div>
            <div class="col-sm-4">
                <div class="ht-field">
                    <label class="ht-label">ชุดคำ</label>
                    <select name="sethit" class="ht-select">
                        <?php for ($s = 1; $s <= 5; $s++): ?><option value="<?= $s ?>">ชุด <?= $s ?></option><?php endfor; ?>
                    </select>
                </div>
            </div>
            <div class="col-12">
                <button class="ht-btn ht-btn--lg mt-2" type="submit">▶️ เริ่มฝึกอ่าน (20 คำ)</button>
            </div>
        </form>
        <p class="text-muted mt-3 mb-0" style="font-size:.9rem">💡 ใช้บน Google Chrome และอนุญาตให้เข้าถึงไมโครโฟน · รองรับเฉพาะ https หรือ localhost</p>
    </div>
<?php else: ?>
    <!-- สนามฝึกอ่าน -->
    <div class="ht-row mb-3" style="gap:12px">
        <span class="ht-badge t-blue">ป.<?= $class_id ?> · Hit-<?= $hittest ?> · ชุด <?= $sethit ?></span>
        <span class="ht-badge ht-badge--done">อ่านถูก <span id="pScore">0</span> / <?= count($words) ?></span>
        <a href="practice.php" class="ht-btn ht-btn--ghost ht-btn--sm" style="margin-left:auto">เปลี่ยนชุด</a>
    </div>

    <div id="pUnsupported" class="ht-card mb-3 d-none" style="border-left:6px solid var(--c-coral)">
        <strong>เบราว์เซอร์นี้ไม่รองรับการฟังเสียง (Web Speech API)</strong>
        <p class="mb-0 text-muted">กรุณาใช้ Google Chrome ผ่าน localhost หรือ https — ยังฝึกอ่านได้โดยกด "🔊 ฟังคำ" และ "ต่อไป" เอง</p>
    </div>

    <div class="ht-wordcard mb-3">
        <div class="ht-word" id="pWord">—</div>
        <div id="pHeard" class="mt-2" style="font-size:1.3rem;min-height:1.6em;color:var(--ink-soft)"></div>
    </div>

    <div class="d-flex flex-wrap gap-3 justify-content-center mb-4">
        <button id="pSpeak" class="ht-btn ht-btn--lg ht-btn--ghost">🔊 ฟังคำ</button>
        <button id="pMic"   class="ht-btn ht-btn--lg ht-btn--green">🎤 อ่าน</button>
        <button id="pNext"  class="ht-btn ht-btn--lg ht-btn--purple">ต่อไป →</button>
    </div>

    <div class="ht-progress" style="background:var(--c-blue-soft);border-radius:var(--r-pill);height:14px;overflow:hidden;max-width:680px;margin:0 auto">
        <div id="pBar" style="height:100%;width:0;background:var(--c-blue);transition:width .3s ease"></div>
    </div>

    <!-- ผลการฝึกอ่าน: โผล่ทีละคำเมื่ออ่านถูก · ตัวเลข = จำนวนครั้งที่อ่านกว่าจะถูก -->
    <div id="pResultsWrap" class="mt-5" style="display:none">
        <h3 class="mb-2">📋 ผลการฝึกอ่าน
            <span class="text-muted" style="font-size:.9rem;font-weight:500">— ตัวเลข = จำนวนครั้งที่อ่านกว่าจะถูก (1 = ถูกตั้งแต่ครั้งแรก)</span>
        </h3>
        <div class="ht-table-wrap" style="overflow-x:auto">
            <table class="ht-table" style="min-width:max-content">
                <thead><tr id="pResHead"><th>คำ</th></tr></thead>
                <tbody><tr id="pResRow"><td class="fw-7">ครั้งที่อ่าน</td></tr></tbody>
            </table>
        </div>
    </div>

    <script>window.PRACTICE = <?= json_encode(['words' => $words], JSON_UNESCAPED_UNICODE) ?>;</script>
    <script>
    (function () {
        'use strict';
        var WORDS = (window.PRACTICE && window.PRACTICE.words) || [];
        // สลับลำดับคำ (Fisher–Yates) เพื่อให้ฝึกได้หลากหลายรอบ
        for (var i = WORDS.length - 1; i > 0; i--) {
            var j = Math.floor(Math.random() * (i + 1));
            var t = WORDS[i]; WORDS[i] = WORDS[j]; WORDS[j] = t;
        }
        var idx = 0, score = 0, total = WORDS.length, attempts = 0;

        var el = function (id) { return document.getElementById(id); };
        var norm = function (s) { return String(s == null ? '' : s).replace(/\s+/g, '').trim(); };

        // ---- เสียงดริ๊ง (chime) เมื่ออ่านถูก ----
        var chime = new Audio('sounds/correct.mp3');
        chime.preload = 'auto';
        function playChime() { try { chime.currentTime = 0; chime.play(); } catch (e) {} }

        // ---- ฟังคำ: ใช้เสียงจาก Botnoi (ผ่าน tts.php) เป็นหลัก สำรองด้วย TTS ของเบราว์เซอร์ ----
        var ttsAudio = null, audioReady = false, lastSpeakText = '', lastSpeakAt = 0;
        function speakFallback(text) {
            if (wantListen) { return; }                 // อย่าเล่นทับขณะตั้งใจฟังไมค์
            if (!('speechSynthesis' in window)) { return; }
            window.speechSynthesis.cancel();
            var u = new SpeechSynthesisUtterance(text);
            u.lang = 'th-TH';
            u.rate = 0.9;
            window.speechSynthesis.speak(u);
        }
        function speak(text) {
            var t = Date.now();
            if (text === lastSpeakText && (t - lastSpeakAt) < 800) { return; }   // กันสั่งเล่นซ้ำถี่ ๆ (เช่น auto + แตะ)
            lastSpeakText = text; lastSpeakAt = t;
            stopListen();   // หยุดไมค์ก่อนเล่นเสียง กันไมค์รับเสียงระบบ
            stopAudio();    // หยุดเสียงเก่า (Botnoi + speechSynthesis ที่ค้าง)
            fetch('tts.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: 'text=' + encodeURIComponent(text)
            })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (wantListen) { return; }                 // ผู้ใช้กดอ่าน(ไมค์)ระหว่างโหลดเสียง → ไม่เล่นทับ
                    if (d.status === 'ok' && d.audio_url) {
                        ttsAudio = new Audio(d.audio_url);
                        var pr = ttsAudio.play();
                        if (pr && pr.then) {
                            pr.then(function () { audioReady = true; })
                              .catch(function () { lastSpeakText = ''; speakFallback(text); });   // โดนบล็อก autoplay → เคลียร์ debounce ให้เล่นได้ตอนแตะ
                        }
                    } else {
                        speakFallback(text);
                    }
                })
                .catch(function () { speakFallback(text); });
        }

        // ---- Speech Recognition: อ่านแล้วตรวจ ----
        var SR = window.SpeechRecognition || window.webkitSpeechRecognition;
        var recog = null, listening = false;
        var wantListen = false, gotResult = false, retried = false, lastErr = '', micWarmed = false;
        if (SR) {
            recog = new SR();
            recog.lang = 'th-TH';
            recog.interimResults = true;
            recog.continuous = true;     // ฟังต่อเนื่อง กันจบเร็วเกินไป/พลาดเสียง
            recog.maxAlternatives = 5;   // เก็บหลายคำเดา → ถ้าตัวใดตรงก็ผ่าน (ช่วยคำพ้องเสียง)
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
                // จบแบบไม่ได้ผลทั้งที่ยังตั้งใจฟัง → เริ่มฟังใหม่อัตโนมัติ 1 ครั้ง (กันเคสไม่ได้ยิน 2-3% + คำแรก)
                if (wantListen && !gotResult && !retried &&
                    (lastErr === '' || lastErr === 'no-speech' || lastErr === 'aborted')) {
                    retried = true;
                    setTimeout(function () { if (wantListen && !listening) { try { recog.start(); } catch (e) {} } }, 220);
                    return;
                }
                if (wantListen && !gotResult) {
                    el('pHeard').textContent =
                        lastErr === 'not-allowed' ? '⚠️ กรุณาอนุญาตไมโครโฟนในเบราว์เซอร์'
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
                    if (r.isFinal) {
                        gotResult = true;
                        if (!top) { top = r[0].transcript; }
                        for (var a = 0; a < r.length; a++) { finals.push(r[a].transcript); }  // ทุกคำเดา
                    } else {
                        interim += r[0].transcript;
                    }
                }
                el('pHeard').textContent = (top || interim) ? ('ได้ยิน: ' + (top || interim)) : '';
                if (finals.length) { check(finals); }
            };
        } else {
            el('pUnsupported').classList.remove('d-none');
            el('pMic').disabled = true;
        }

        // ---- เทียบ "เสียง" รองรับคำพ้องเสียง (เขียนต่าง เสียงเหมือน) ----
        // เสียงตัวสะกดตามมาตรา: กก/กง/กด/กน/กม/กบ/เกย/เกอว
        var FINAL_SND = {
            'ก':'ก','ข':'ก','ค':'ก','ฆ':'ก',
            'ง':'ง',
            'จ':'ด','ช':'ด','ซ':'ด','ฌ':'ด','ฎ':'ด','ฏ':'ด','ฐ':'ด','ฑ':'ด','ฒ':'ด','ด':'ด','ต':'ด','ถ':'ด','ท':'ด','ธ':'ด','ศ':'ด','ษ':'ด','ส':'ด',
            'ญ':'น','ณ':'น','น':'น','ร':'น','ล':'น','ฬ':'น',
            'ม':'ม','ย':'ย','ว':'ว',
            'บ':'บ','ป':'บ','ผ':'บ','ฝ':'บ','พ':'บ','ฟ':'บ','ภ':'บ'
        };
        function isCons(c) { return c >= 'ก' && c <= 'ฮ'; }
        function isVowel(c) { return (c >= 'ะ' && c <= 'ฺ') || (c >= 'เ' && c <= 'ๅ'); }
        // กุญแจเสียง: ตัดการันต์ + แปลงตัวสะกดท้ายเป็นเสียงมาตรา (คงเสียงต้น/วรรณยุกต์)
        function soundKey(s) {
            s = norm(s).replace(/[ก-ฮ]์/g, '').replace(/์/g, '');   // ตัดการันต์ (ตัวไม่ออกเสียง)
            var ch = s.split(''), first = -1, last = -1;
            for (var i = 0; i < ch.length; i++) { if (isCons(ch[i])) { if (first < 0) { first = i; } last = i; } }
            if (last > first) {
                var vowelAfter = false;
                for (var j = last + 1; j < ch.length; j++) { if (isVowel(ch[j])) { vowelAfter = true; break; } }
                if (!vowelAfter) {
                    var fin = last;
                    // "ร" ควบท้ายที่ไม่ออกเสียง (บาตร/มิตร/จักร) → ตัวสะกดจริงคือตัวก่อนหน้า (เฉพาะเมื่อมีสระนำคลัสเตอร์)
                    if (ch[last] === 'ร' && last - 1 > first && isCons(ch[last - 1])) {
                        var vBefore = false;
                        for (var k = 0; k < last - 1; k++) { if (isVowel(ch[k])) { vBefore = true; break; } }
                        if (vBefore) { ch.splice(last, 1); fin = last - 1; }
                    }
                    if (FINAL_SND[ch[fin]]) { ch[fin] = FINAL_SND[ch[fin]]; }   // ตัวสะกด → เสียงมาตรา
                }
            }
            return ch.join('');
        }
        function isCorrect(said, cur) {
            var targets = [cur.word];
            String(cur.spoken || '').split(',').forEach(function (x) { x = x.trim(); if (x) { targets.push(x); } });
            for (var t = 0; t < targets.length; t++) {
                if (norm(said) === norm(targets[t])) { return true; }            // ตรงตัวสะกด
                if (soundKey(said) === soundKey(targets[t])) { return true; }     // เสียงเหมือน (พ้องเสียง)
            }
            return false;
        }
        function check(saidList) {
            var cur = WORDS[idx]; if (!cur) { return; }
            if (typeof saidList === 'string') { saidList = [saidList]; }
            attempts++;        // นับครั้งที่อ่าน (ตรวจ) สำหรับคำนี้
            var matched = false;
            for (var i = 0; i < saidList.length; i++) { if (isCorrect(saidList[i], cur)) { matched = true; break; } }
            if (matched) {
                score++; el('pScore').textContent = score;
                addResult(cur.word, attempts);   // บันทึกผล: คำนี้อ่านถูกหลังพยายาม N ครั้ง
                stopListen();
                playChime();   // เสียงดริ๊งใส ๆ เมื่ออ่านถูก
                Swal.fire({ icon: 'success', title: 'อ่านถูกต้อง! 🎉',
                    html: 'คำว่า <b>"' + cur.word + '"</b>', timer: 1500, showConfirmButton: false })
                    .then(function () { next(); });
            } else {
                stopListen();   // หยุดฟัง แล้วให้ผู้ใช้กด 🎤 อ่าน ใหม่เอง
                el('pHeard').textContent = 'ได้ยิน: ' + (saidList[0] || '');
                Swal.fire({ icon: 'error', title: 'ยังไม่ถูก ลองอีกครั้ง',
                    html: 'คำว่า <b>"' + cur.word + '"</b>',
                    confirmButtonText: 'ลองอีกครั้ง', confirmButtonColor: '#FF6B6B' });
            }
        }

        function render() {
            var cur = WORDS[idx];
            el('pWord').textContent = cur ? cur.word : '—';
            el('pHeard').textContent = '';
            attempts = 0;        // เริ่มนับใหม่สำหรับคำถัดไป
            el('pBar').style.width = (total ? Math.round(idx / total * 100) : 0) + '%';
        }

        // เพิ่มผลลงตาราง "ผลการฝึกอ่าน" (โผล่ทีละคำเมื่ออ่านถูก)
        function addResult(word, tries) {
            el('pResultsWrap').style.display = '';
            var color = tries === 1 ? '#1E7B45' : (tries <= 3 ? '#9A6B00' : '#B4232B');   // เขียว/เหลือง/แดง
            var th = document.createElement('th');
            th.textContent = word; th.style.textAlign = 'center'; th.style.whiteSpace = 'nowrap';
            el('pResHead').appendChild(th);
            var td = document.createElement('td');
            td.textContent = tries; td.style.textAlign = 'center';
            td.style.fontWeight = '800'; td.style.color = color;
            el('pResRow').appendChild(td);
        }

        function next() {
            stopListen();
            idx++;
            if (idx >= total) { finish(); return; }
            render();   // แสดงคำเฉย ๆ ไม่อ่านให้ฟัง — ผู้ใช้กด 🔊 ฟังคำ เองถ้าต้องการ
        }

        function finish() {
            el('pBar').style.width = '100%';
            Swal.fire({
                icon: 'success', title: 'ฝึกครบ ' + total + ' คำแล้ว!',
                html: 'อ่านถูก <b>' + score + '</b> / ' + total + ' คำ',
                showCancelButton: true, confirmButtonText: 'ฝึกชุดนี้อีกครั้ง', cancelButtonText: 'เลือกชุดใหม่',
                confirmButtonColor: '#4D96FF'
            }).then(function (r) {
                if (r.isConfirmed) { location.reload(); }
                else { location.href = 'practice.php'; }
            });
        }

        el('pSpeak').addEventListener('click', function () { var c = WORDS[idx]; if (c) { speak(c.word); } });
        function stopAudio() {
            if (ttsAudio) { try { ttsAudio.pause(); } catch (e) {} ttsAudio = null; }
            if ('speechSynthesis' in window) { try { window.speechSynthesis.cancel(); } catch (e) {} }
        }
        function stopListen() {
            wantListen = false;
            if (recog && listening) { try { recog.stop(); } catch (e) {} }
        }
        function startListen() {
            if (!recog || listening) { return; }
            stopAudio();                       // กันไมค์รับเสียง TTS
            el('pHeard').textContent = '';
            wantListen = true; gotResult = false; retried = false; lastErr = '';
            var go = function () {
                try { recog.start(); }
                catch (e) { try { recog.stop(); } catch (_) {} setTimeout(function () { try { recog.start(); } catch (__) {} }, 300); }
            };
            // ครั้งแรก: ขอ/อุ่นไมค์ให้พร้อมก่อนแล้วค่อยเริ่มฟัง → แก้ปัญหา "คำแรกไม่ได้ยิน"
            if (!micWarmed && navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
                navigator.mediaDevices.getUserMedia({ audio: true })
                    .then(function (st) { micWarmed = true; st.getTracks().forEach(function (t) { t.stop(); }); go(); })
                    .catch(function () { go(); });
            } else { go(); }
        }
        el('pMic').addEventListener('click', function () {
            if (!recog) { return; }
            if (listening) { stopListen(); } else { startListen(); }
        });
        el('pNext').addEventListener('click', next);

        // เริ่ม — แสดงคำแรกเฉย ๆ ไม่อ่านออกเสียงให้ก่อน (ผู้ใช้อ่านเองแล้วกด 🎤 อ่าน)
        if (total === 0) {
            el('pWord').textContent = 'ไม่มีคำในชุดนี้';
        } else {
            render();
        }
    })();
    </script>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
