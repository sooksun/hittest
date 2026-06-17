/*!
 * practice-reader.js — ฝึกอ่านคำพื้นฐาน (Web Speech API)
 * ใช้ร่วมกันระหว่าง practice.php (ครู/พรีวิว) และ my_practice.php (นักเรียน)
 *
 * แก้ 3 ปัญหาที่พบบ่อย:
 *   1) เสียงดิ๊งตอนตอบถูกไม่ดัง  → resume AudioContext ให้เสร็จ "ก่อน" เล่น (กันเคส
 *      context ถูก suspend หลังใช้ไมค์ แล้วเล่นเสียงไม่ออก)
 *   2) ไมค์ไม่ตอบสนอง ต้องกดซ้ำ  → สร้าง recognizer ใหม่ทุกครั้งที่เริ่มฟัง + watchdog
 *      ตรวจว่าเริ่มจริงไหม ถ้าไม่เริ่มภายใน ~0.9s สร้างใหม่ให้อัตโนมัติ (กัน state ค้างของ
 *      Chrome SpeechRecognition เมื่อ start/stop ถี่ ๆ)
 *   3) อ่านถูกแต่ถอดเสียง/สะกดไม่ตรง → เทียบแบบไม่สนวรรณยุกต์ + เทียบทีละคำ (token)
 *      เพิ่มจากเดิมที่เทียบเฉพาะตัวสะกดมาตรา
 *
 * ใช้งาน:
 *   PracticeReader.init({
 *     words:   [{word, spoken, id?}, ...],   // จำเป็น
 *     ttsUrl:  'tts.php',                     // ดีฟอลต์ tts.php
 *     exitUrl: 'practice.php',                // ปุ่ม "เลือกชุดใหม่"
 *     onResult: function(word, attempts, correct){...}  // (ออปชัน) บันทึกผลรายคำ
 *   });
 * ต้องมี DOM id: pWord pHeard pMic pSpeak pNext pScore pBar pResultsWrap pResHead pResRow pUnsupported
 * และมี SweetAlert2 (Swal) โหลดไว้แล้ว
 */
(function () {
    'use strict';

    function init(opts) {
        opts = opts || {};
        var WORDS    = (opts.words || []).slice();
        var TTS_URL  = opts.ttsUrl  || 'tts.php';
        var EXIT_URL = opts.exitUrl || 'practice.php';
        var onResult = typeof opts.onResult === 'function' ? opts.onResult : null;

        // สลับลำดับคำ (Fisher–Yates)
        for (var i = WORDS.length - 1; i > 0; i--) {
            var j = Math.floor(Math.random() * (i + 1));
            var t = WORDS[i]; WORDS[i] = WORDS[j]; WORDS[j] = t;
        }
        var idx = 0, score = 0, total = WORDS.length, attempts = 0, solved = false;

        var el   = function (id) { return document.getElementById(id); };
        var norm = function (s) { return String(s == null ? '' : s).replace(/\s+/g, '').trim(); };
        // ตัดวรรณยุกต์ ่ ้ ๊ ๋ (U+0E48..U+0E4B) — STT ไทยมักสะกดวรรณยุกต์เพี้ยนแม้ออกเสียงถูก
        var stripTone = function (s) { return String(s).replace(/[่-๋]/g, ''); };

        // ─────────────────────────────────────────────────────────────────────
        // (1) เสียงดิ๊ง — Web Audio API + resume ให้เสร็จก่อนเล่น
        // ─────────────────────────────────────────────────────────────────────
        var AudioCtx = window.AudioContext || window.webkitAudioContext;
        var audioCtx = null, chimeBuffer = null, chimeLoading = false;

        function ensureAudioCtx() {
            if (!AudioCtx) { return null; }
            if (!audioCtx) { try { audioCtx = new AudioCtx(); } catch (e) { return null; } }
            return audioCtx;
        }
        function loadChime() {
            if (chimeBuffer || chimeLoading) { return; }
            var ctx = ensureAudioCtx(); if (!ctx) { return; }
            chimeLoading = true;
            fetch('sounds/correct.mp3')
                .then(function (r) { return r.arrayBuffer(); })
                .then(function (buf) { return ctx.decodeAudioData(buf); })
                .then(function (decoded) { chimeBuffer = decoded; })
                .catch(function () { chimeBuffer = null; })   // ใช้ตัวสำรอง (oscillator)
                .then(function () { chimeLoading = false; });
        }
        // "ดิ๊ง" สังเคราะห์ (สองโน้ตไล่ขึ้น) — ได้ยินแม้ไม่มีไฟล์เสียง
        function synthChime(ctx) {
            var now = ctx.currentTime;
            [[988, 0], [1319, 0.12]].forEach(function (p) {
                var osc = ctx.createOscillator(), gain = ctx.createGain();
                osc.type = 'sine';
                osc.frequency.setValueAtTime(p[0], now + p[1]);
                gain.gain.setValueAtTime(0.0001, now + p[1]);
                gain.gain.exponentialRampToValueAtTime(0.4, now + p[1] + 0.02);
                gain.gain.exponentialRampToValueAtTime(0.0001, now + p[1] + 0.28);
                osc.connect(gain).connect(ctx.destination);
                osc.start(now + p[1]); osc.stop(now + p[1] + 0.3);
            });
        }
        function playChime() {
            var ctx = ensureAudioCtx();
            if (!ctx) {   // ไม่มี Web Audio → fallback <audio>
                try { var a = new Audio('sounds/correct.mp3'); var pr = a.play(); if (pr && pr.catch) { pr.catch(function () {}); } } catch (e) {}
                return;
            }
            var doPlay = function () {
                if (chimeBuffer) {
                    try {
                        var src = ctx.createBufferSource();
                        src.buffer = chimeBuffer; src.connect(ctx.destination); src.start(0);
                        return;
                    } catch (e) {}
                }
                synthChime(ctx);   // ยังโหลดไฟล์ไม่ทัน → เสียงสังเคราะห์
                loadChime();       // โหลดไว้ใช้รอบถัดไป
            };
            // สำคัญ: ถ้า context ถูก suspend (พบบ่อยหลังใช้ไมค์) ต้อง resume ให้เสร็จก่อนเล่น
            if (ctx.state === 'suspended') { ctx.resume().then(doPlay).catch(doPlay); }
            else { doPlay(); }
        }
        // ปลดล็อกเสียงตั้งแต่ผู้ใช้แตะหน้าจอครั้งแรก (นโยบาย autoplay)
        function unlockAudio() {
            var ctx = ensureAudioCtx();
            if (ctx && ctx.state === 'suspended') { ctx.resume().catch(function () {}); }
            loadChime();
        }
        document.addEventListener('pointerdown', unlockAudio, { once: true });

        // ─────────────────────────────────────────────────────────────────────
        // ฟังคำ (Botnoi ผ่าน tts.php, สำรองด้วย TTS ของเบราว์เซอร์)
        // ─────────────────────────────────────────────────────────────────────
        var ttsAudio = null, lastSpeakText = '', lastSpeakAt = 0;
        function speakFallback(text) {
            if (wantListen || !('speechSynthesis' in window)) { return; }
            window.speechSynthesis.cancel();
            var u = new SpeechSynthesisUtterance(text);
            u.lang = 'th-TH'; u.rate = 0.9;
            window.speechSynthesis.speak(u);
        }
        // audioUrl (ไม่บังคับ) = path ไฟล์ MP3 ที่สร้างไว้แล้ว; ถ้าไม่มีจึงโทร TTS API
        function speak(text, audioUrl) {
            var t = Date.now();
            if (text === lastSpeakText && (t - lastSpeakAt) < 800) { return; }   // กันเล่นซ้ำถี่
            lastSpeakText = text; lastSpeakAt = t;
            stopListen(); stopAudio();
            if (audioUrl) {
                ttsAudio = new Audio(audioUrl);
                var pr = ttsAudio.play();
                if (pr && pr.then) { pr.catch(function () { lastSpeakText = ''; speakFallback(text); }); }
                return;
            }
            fetch(TTS_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: 'text=' + encodeURIComponent(text)
            })
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
        function stopAudio() {
            if (ttsAudio) { try { ttsAudio.pause(); } catch (e) {} ttsAudio = null; }
            if ('speechSynthesis' in window) { try { window.speechSynthesis.cancel(); } catch (e) {} }
        }

        // ─────────────────────────────────────────────────────────────────────
        // (3) เทียบเสียง — รองรับคำพ้องเสียง + ไม่สนวรรณยุกต์ + เทียบทีละคำ
        // ─────────────────────────────────────────────────────────────────────
        var FINAL_SND = {
            'ก':'ก','ข':'ก','ค':'ก','ฆ':'ก','ง':'ง',
            'จ':'ด','ช':'ด','ซ':'ด','ฌ':'ด','ฎ':'ด','ฏ':'ด','ฐ':'ด','ฑ':'ด','ฒ':'ด','ด':'ด','ต':'ด','ถ':'ด','ท':'ด','ธ':'ด','ศ':'ด','ษ':'ด','ส':'ด',
            'ญ':'น','ณ':'น','น':'น','ร':'น','ล':'น','ฬ':'น','ม':'ม','ย':'ย','ว':'ว',
            'บ':'บ','ป':'บ','ผ':'บ','ฝ':'บ','พ':'บ','ฟ':'บ','ภ':'บ'
        };
        function isCons(c) { return c >= 'ก' && c <= 'ฮ'; }
        function isVowel(c) { return (c >= 'ะ' && c <= 'ฺ') || (c >= 'เ' && c <= 'ๅ'); }
        function soundKey(s) {
            s = norm(s).replace(/[ก-ฮ]์/g, '').replace(/์/g, '');   // ตัดการันต์
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
        // คำ ๆ เดียว vs เป้าหมายเดียว — ผ่านได้หลายชั้น (เข้มไปหลวม)
        function matchOne(said, target) {
            var sN = norm(said),     gN = norm(target);
            if (sN === gN) { return true; }                                  // ตรงตัวสะกด
            if (stripTone(sN) === stripTone(gN)) { return true; }            // ตรงแต่วรรณยุกต์ต่าง
            var sK = soundKey(said), gK = soundKey(target);
            if (sK === gK) { return true; }                                  // เสียงเหมือน (ตัวสะกดมาตรา)
            if (stripTone(sK) === stripTone(gK)) { return true; }            // เสียงเหมือน + ไม่สนวรรณยุกต์
            return false;
        }
        function isCorrect(said, cur) {
            var targets = [cur.word];
            String(cur.spoken || '').split(',').forEach(function (x) { x = x.trim(); if (x) { targets.push(x); } });
            // ขยาย said เป็นผู้สมัคร: ทั้งประโยค + ทีละคำ (STT อาจได้ "ม้า ค่ะ" / "อ่า ม้า")
            var cands = [said];
            String(said).split(/\s+/).forEach(function (tok) { tok = tok.trim(); if (tok) { cands.push(tok); } });
            for (var c = 0; c < cands.length; c++) {
                for (var t = 0; t < targets.length; t++) {
                    if (matchOne(cands[c], targets[t])) { return true; }
                }
            }
            return false;
        }

        // ─────────────────────────────────────────────────────────────────────
        // (2) Speech Recognition — สร้างใหม่ทุกครั้ง + watchdog กันค้าง
        // ─────────────────────────────────────────────────────────────────────
        var SR = window.SpeechRecognition || window.webkitSpeechRecognition;
        var recog = null, listening = false, starting = false, startWatch = null;
        var wantListen = false, gotResult = false, retried = false, lastErr = '', micWarmed = false;

        function micUI(on) {
            var b = el('pMic'); if (!b) { return; }
            if (on) { b.innerHTML = '🛑 หยุด (กำลังฟัง…)'; b.classList.replace('ht-btn--green', 'ht-btn--coral'); }
            else    { b.innerHTML = '🎤 อ่าน';            b.classList.replace('ht-btn--coral', 'ht-btn--green'); }
        }
        function errMsg(e) {
            return e === 'not-allowed'   ? '⚠️ กรุณาอนุญาตไมโครโฟนในเบราว์เซอร์'
                 : e === 'audio-capture' ? '⚠️ ไม่พบไมโครโฟน'
                 : e === 'network'       ? '⚠️ เชื่อมต่ออินเทอร์เน็ตไม่ได้'
                 : 'ไม่ได้ยินเสียง — กด 🎤 อ่าน อีกครั้ง';
        }
        function buildRecog() {
            var r = new SR();
            r.lang = 'th-TH'; r.interimResults = true; r.continuous = true; r.maxAlternatives = 5;
            r.onstart  = function () { starting = false; listening = true; clearTimeout(startWatch); micUI(true); };
            r.onerror  = function (e) { lastErr = (e && e.error) || ''; };
            r.onend    = function () {
                listening = false; starting = false; clearTimeout(startWatch); micUI(false);
                // จบแบบไม่ได้ผลทั้งที่ยังตั้งใจฟัง → เริ่มฟังใหม่ 1 ครั้ง (กันเคสไม่ได้ยิน + คำแรก)
                if (wantListen && !gotResult && !retried &&
                    (lastErr === '' || lastErr === 'no-speech' || lastErr === 'aborted')) {
                    retried = true;
                    setTimeout(function () { if (wantListen && !listening && !starting) { spawn(); } }, 220);
                    return;
                }
                if (wantListen && !gotResult) { el('pHeard').textContent = errMsg(lastErr); }
                wantListen = false;
            };
            r.onresult = function (e) {
                var interim = '', finals = [], top = '';
                for (var k = e.resultIndex; k < e.results.length; k++) {
                    var res = e.results[k];
                    if (res.isFinal) {
                        gotResult = true;
                        if (!top) { top = res[0].transcript; }
                        for (var a = 0; a < res.length; a++) { finals.push(res[a].transcript); }
                    } else { interim += res[0].transcript; }
                }
                el('pHeard').textContent = (top || interim) ? ('ได้ยิน: ' + (top || interim)) : '';
                if (finals.length) { check(finals); }
            };
            return r;
        }
        // สร้าง recognizer ใหม่แล้วเริ่ม + ตั้ง watchdog: ถ้าไม่ onstart ใน 0.9s ให้สร้างใหม่
        function spawn() {
            try {
                recog = buildRecog();
                starting = true;
                recog.start();
                clearTimeout(startWatch);
                startWatch = setTimeout(function () {
                    if (starting && !listening) {            // start() เงียบ ไม่เริ่มจริง
                        starting = false;
                        try { recog.abort(); } catch (e) {}
                        if (wantListen && !retried) { retried = true; spawn(); }
                        else if (wantListen) { el('pHeard').textContent = errMsg(lastErr); wantListen = false; }
                    }
                }, 900);
            } catch (e) {
                // start() โยน (เช่น InvalidStateError) → ทิ้งตัวเก่า แล้วลองใหม่สั้น ๆ
                starting = false;
                try { recog.abort(); } catch (_) {}
                setTimeout(function () { if (wantListen && !listening && !starting) { spawn(); } }, 250);
            }
        }
        function stopListen() {
            wantListen = false; clearTimeout(startWatch);
            if (recog && (listening || starting)) {
                try { recog.stop(); } catch (e) { try { recog.abort(); } catch (_) {} }
            }
        }
        function startListen() {
            if (!SR || listening || starting) { return; }
            unlockAudio();                     // ปลดล็อกเสียงไว้ใช้ตอนตอบถูก
            stopAudio();
            el('pHeard').textContent = '';
            wantListen = true; gotResult = false; retried = false; lastErr = '';
            // ครั้งแรก: อุ่นไมค์ให้พร้อมก่อน แก้ "คำแรกไม่ได้ยิน"
            if (!micWarmed && navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
                navigator.mediaDevices.getUserMedia({ audio: true })
                    .then(function (st) { micWarmed = true; st.getTracks().forEach(function (tk) { tk.stop(); }); spawn(); })
                    .catch(function () { spawn(); });
            } else { spawn(); }
        }

        // ─────────────────────────────────────────────────────────────────────
        // Flow
        // ─────────────────────────────────────────────────────────────────────
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
                if (onResult) { onResult(cur, attempts, 1); }
                stopListen();
                playChime();
                Swal.fire({ icon: 'success', title: 'อ่านถูกต้อง! 🎉',
                    html: 'คำว่า <b>"' + cur.word + '"</b>', timer: 1500, showConfirmButton: false })
                    .then(function () { next(); });
            } else {
                stopListen();
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
            attempts = 0; solved = false;
            el('pBar').style.width = (total ? Math.round(idx / total * 100) : 0) + '%';
            var imgEl = el('pWordImg');
            if (imgEl) {
                if (cur && cur.image) {
                    imgEl.src = cur.image;
                    imgEl.style.display = 'block';
                } else {
                    imgEl.src = '';
                    imgEl.style.display = 'none';
                }
            }
        }
        function addResult(word, tries) {
            el('pResultsWrap').style.display = '';
            var color = tries === 1 ? '#1E7B45' : (tries <= 3 ? '#9A6B00' : '#B4232B');
            var th = document.createElement('th');
            th.textContent = word; th.style.textAlign = 'center'; th.style.whiteSpace = 'nowrap';
            el('pResHead').appendChild(th);
            var td = document.createElement('td');
            td.textContent = tries; td.style.textAlign = 'center'; td.style.fontWeight = '800'; td.style.color = color;
            el('pResRow').appendChild(td);
        }
        function next() {
            var cur = WORDS[idx];
            if (cur && !solved && onResult) { onResult(cur, attempts, 0); }   // ข้ามทั้งที่ยังไม่ถูก → บันทึกเป็นข้าม
            stopListen();
            idx++;
            if (idx >= total) { finish(); return; }
            render();
        }
        function finish() {
            el('pBar').style.width = '100%';
            Swal.fire({
                icon: 'success', title: 'ฝึกครบ ' + total + ' คำแล้ว!',
                html: 'อ่านถูก <b>' + score + '</b> / ' + total + ' คำ'
                    + (onResult ? '<br><span style="color:var(--ink-soft)">บันทึกผลให้คุณครูแล้ว</span>' : ''),
                showCancelButton: true, confirmButtonText: 'ฝึกชุดนี้อีกครั้ง', cancelButtonText: 'เลือกชุดใหม่',
                confirmButtonColor: '#4D96FF'
            }).then(function (r) {
                if (r.isConfirmed) { location.reload(); } else { location.href = EXIT_URL; }
            });
        }

        // ─────────────────────────────────────────────────────────────────────
        // Wire up
        // ─────────────────────────────────────────────────────────────────────
        if (!SR) {
            if (el('pUnsupported')) { el('pUnsupported').classList.remove('d-none'); }
            if (el('pMic')) { el('pMic').disabled = true; }
        }
        el('pSpeak').addEventListener('click', function () { unlockAudio(); var c = WORDS[idx]; if (c) { speak(c.word, c.audio || null); } });
        el('pMic').addEventListener('click', function () {
            unlockAudio();
            if (!SR) { return; }
            if (listening || starting) { stopListen(); } else { startListen(); }
        });
        el('pNext').addEventListener('click', next);

        if (total === 0) { el('pWord').textContent = 'ไม่มีคำในชุดนี้'; } else { render(); }
    }

    window.PracticeReader = { init: init };
})();
