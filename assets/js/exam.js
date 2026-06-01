/**
 * assets/js/exam.js — ตรรกะหน้าสอบฝั่งครู (teacher_page.php)
 * รับค่าจาก window.EXAM { stuid, hittest, sethit, years, minutes, slides[] }
 * sync คำไปจอนักเรียน (student_page.php) ผ่าน localStorage (keys: slides, currentSlideIndex, results)
 */
(function () {
    'use strict';
    var cfg = window.EXAM || {};
    var slides = cfg.slides || [];
    var currentIndex = 0;
    var results = [];
    var score = 0;
    var interval = null;

    // ส่งชุดคำให้จอนักเรียนผ่าน localStorage
    localStorage.setItem('slides', JSON.stringify(slides));

    function el(id) { return document.getElementById(id); }

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    // เรนเดอร์แผงผลการอ่านรายข้อ (ขวา): เผยคำเมื่อถึง/ตอบแล้ว + ติ๊ก ✓/✗ ตามที่ครูกด
    function renderResults() {
        var list = el('resultList');
        if (!list) { return; }
        var html = '';
        for (var i = 0; i < slides.length; i++) {
            var answered  = i < results.length;
            var isCurrent = (i === currentIndex && currentIndex < slides.length);
            var cls = 'exr'
                + (isCurrent ? ' is-current' : '')
                + (answered ? (results[i].correct ? ' is-correct' : ' is-wrong') : '');
            var word = (answered || isCurrent) ? escapeHtml(slides[i].word) : '';
            var mark = answered ? (results[i].correct ? '✓' : '✗') : '';
            html += '<li class="' + cls + '">'
                  +   '<span class="exr__no">' + (i + 1) + '</span>'
                  +   '<span class="exr__word">' + word + '</span>'
                  +   '<span class="exr__mark">' + mark + '</span>'
                  + '</li>';
        }
        list.innerHTML = html;

        var sum = el('resultSum');
        if (sum) { sum.innerText = 'ถูก ' + score + ' · ทำแล้ว ' + results.length + '/' + slides.length; }

        var cur = list.querySelector('.is-current');
        if (cur && cur.scrollIntoView) { cur.scrollIntoView({ block: 'nearest' }); }
    }

    function updateSlideDisplay() {
        var s = slides[currentIndex];
        el('currentWord').innerText = (currentIndex + 1) + '. ' + s.word;
        el('image').src = s.image_path;
        el('stu_name').innerText = s.stuname;
        el('class').innerText = s.class_id;
        el('room').innerText = s.rooms;
        el('sethit').innerText = cfg.sethit;
        localStorage.setItem('currentSlideIndex', currentIndex); // trig storage event → จอนักเรียนอัปเดต
    }

    // กดถูก/ผิด: เก็บผล, นับคะแนน, ไปคำถัดไป
    window.changeWord = function (correct) {
        results.push({
            stuid: cfg.stuid,
            hittest: cfg.hittest,
            years: cfg.years,
            word_id: slides[currentIndex].id,
            correct: correct
        });
        localStorage.setItem('results', JSON.stringify(results));

        currentIndex++;
        if (correct) { score++; }
        el('score').innerText = score;

        if (currentIndex < slides.length) {
            updateSlideDisplay();
        } else {
            $('#btn1').hide();
            clearInterval(interval);
            $('#btn2').show();
            Swal.fire({
                title: 'อ่านคำครบแล้ว!',
                text: 'นักเรียนอ่านครบ ' + slides.length + ' คำแล้ว!',
                icon: 'success'
            });
        }
        renderResults();
    };

    // บันทึกผล: POST ผลทั้งหมดไป save_results.php
    window.saveResults = function () {
        $('#btn2').hide();
        $('#btn3').show();
        localStorage.setItem('currentSlideIndex', 100); // บอกจอนักเรียนว่าจบการสอบ

        fetch('save_results.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(results)
        })
            .then(function (r) { return r.json(); })
            .then(function () {
                Swal.fire({ title: 'บันทึกสำเร็จ!', text: 'คุณทำการบันทึกผลการอ่านแล้ว!', icon: 'success' });
            })
            .catch(function () {
                Swal.fire({ title: 'ผิดพลาด', text: 'บันทึกผลไม่สำเร็จ', icon: 'error' });
            });
    };

    // จับเวลานับถอยหลัง
    function startTimer() {
        var time = (cfg.minutes || 5) * 60;
        interval = setInterval(function () {
            var m = Math.floor(time / 60);
            var s = time % 60;
            el('timer').textContent = (m < 10 ? '0' + m : m) + ':' + (s < 10 ? '0' + s : s);
            if (time <= 0) {
                clearInterval(interval);
                Swal.fire({ icon: 'info', title: 'หมดเวลา', text: 'ท่านใช้เวลาในการสอบหมดแล้ว!' });
            } else {
                time--;
            }
        }, 1000);
    }

    window.addEventListener('load', function () {
        $('#btn2').hide();
        $('#btn3').hide();
        updateSlideDisplay();
        renderResults();
        startTimer();
    });
})();
