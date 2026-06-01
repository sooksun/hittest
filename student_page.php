<?php
/**
 * student_page.php — จอฝั่งนักเรียน (passive display) — ธีม Playful
 * แสดง "คำ" ใหญ่เต็มจอ sync จาก teacher_page ผ่าน localStorage (assets/js/student.js)
 * คง element id: currentWord, image, stu_name, class, room
 */
require __DIR__ . '/includes/auth.php';
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>หน้าจอนักเรียน — แบบทดสอบอ่านไทย</title>
    <link rel="icon" href="images/logohittest.png" type="image/png">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/theme.css?v=<?= @filemtime(__DIR__ . '/assets/css/theme.css') ?>" rel="stylesheet">
</head>

<body class="ht-exam student-screen">
    <header class="exam-top">
        <div class="exam-top__brand">📖 อ่านไทย</div>
        <div class="exam-top__info">
            <span class="fw-7" id="stu_name"></span> · ป.<span id="class"></span> ห้อง <span id="room"></span>
        </div>
    </header>

    <main class="exam-main">
        <div class="ht-wordcard">
            <div class="ht-word" id="currentWord">กำลังโหลด…</div>
        </div>
        <div class="exam-illus">
            <img id="image" src="" alt="ภาพประกอบคำ">
        </div>
    </main>

    <script src="assets/js/student.js?v=<?= @filemtime(__DIR__ . '/assets/js/student.js') ?>"></script>
</body>
</html>
