/**
 * assets/js/student.js — จอฝั่งนักเรียน (student_page.php)
 * อ่านคำปัจจุบันจาก localStorage แล้วอัปเดตเมื่อครูเปลี่ยนคำ (storage event)
 */
(function () {
    'use strict';

    function el(id) { return document.getElementById(id); }

    function render() {
        var slides = JSON.parse(localStorage.getItem('slides') || '[]');
        var idx = parseInt(localStorage.getItem('currentSlideIndex'), 10) || 0;

        // ครูกดบันทึกผล → ตั้ง index = 100 = จบการสอบ
        if (idx === 100) {
            el('currentWord').innerText = 'เสร็จสิ้นการสอบอ่าน';
            el('image').src = 'images/cert_endexam.png';
            return;
        }

        var s = slides[idx];
        if (!s) { return; }
        el('currentWord').innerText = s.word;
        el('image').src = s.image_path;
        el('stu_name').innerText = s.stuname;
        el('class').innerText = s.class_id;
        el('room').innerText = s.rooms;
    }

    // อัปเดตเรียลไทม์เมื่อ teacher_page เปลี่ยน currentSlideIndex (คนละ tab/หน้าจอ)
    window.addEventListener('storage', function (e) {
        if (e.key === 'currentSlideIndex') { render(); }
    });

    window.addEventListener('load', render);
})();
