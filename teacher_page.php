<?php
/**
 * teacher_page.php — หน้าสอบฝั่งครู (หัวใจระบบ) — ธีม Playful
 * logic อยู่ใน assets/js/exam.js (รับค่าผ่าน window.EXAM)
 * คง element id ที่ JS ใช้: currentWord, image, stu_name, class, room, sethit, score, timer, btn1, btn2, btn3
 */
require __DIR__ . '/includes/auth.php';

$stuid    = $_GET['stuid'] ?? '';
$class_id = (int)($_GET['class_id'] ?? 0);
$hittest  = (int)($_GET['hittest'] ?? 0);
$sethit   = (int)($_GET['sethit'] ?? 0);

$student = find_student($stuid);
if (!$student || !valid_hit($hittest)) {
    die('ไม่พบนักเรียน หรือไม่มีสิทธิ์เข้าถึงนักเรียนคนนี้');
}
if (!exam_is_open($hittest)) {
    die('รอบสอบ Hit-' . (int)$hittest . ' ถูกปิดอยู่ — กรุณาเปิดการสอบที่เมนู "จัดการสอบ" ก่อน');
}

$stmt = db()->prepare(
    'SELECT id, word, image_path FROM words
     WHERE class_id = ? AND hittest = ? AND sethit = ? ORDER BY orders'
);
$stmt->execute([$class_id, $hittest, $sethit]);
$words = $stmt->fetchAll();

$slides = array_map(fn($w) => array_merge($student, $w), $words);

$examConfig = [
    'stuid'   => (string)$stuid,
    'hittest' => $hittest,
    'sethit'  => $sethit,
    'years'   => (string)ACADEMIC_YEAR,
    'minutes' => EXAM_MINUTES,
    'slides'  => $slides,
];
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>แบบทดสอบอ่านไทย — HIT-TEST</title>
    <link rel="icon" href="images/newlogo.png" type="image/png">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/theme.css?v=<?= @filemtime(__DIR__ . '/assets/css/theme.css') ?>" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body class="ht-exam">
    <header class="exam-top">
        <div class="exam-top__brand">📖 อ่านไทย</div>
        <div class="exam-top__info">
            <span class="fw-7" id="stu_name"></span>
            · ป.<span id="class"></span> ห้อง <span id="room"></span>
            · ชุดที่ <span id="sethit"></span>
            · ได้ <span class="exam-score" id="score">0</span> คะแนน
        </div>
        <div class="exam-timer" id="timer">05:00</div>
    </header>

    <main class="exam-layout">
        <section class="exam-stage">
            <div class="ht-wordcard">
                <div class="ht-word" id="currentWord"></div>
            </div>

            <div class="exam-illus">
                <img id="image" src="" alt="ภาพประกอบคำ">
            </div>

            <div class="exam-actions">
                <span id="btn1" class="exam-judges">
                    <button class="ht-btn ht-btn--lg ht-btn--green" onclick="changeWord(true)">✅ ถูกต้อง</button>
                    <button class="ht-btn ht-btn--lg ht-btn--coral" onclick="changeWord(false)">❌ ไม่ถูกต้อง</button>
                </span>
                <span id="btn2"><button class="ht-btn ht-btn--lg ht-btn--green" onclick="saveResults()">💾 บันทึกผล</button></span>
                <span id="btn3"><a href="students_list.php?class_id=<?= $class_id ?>" class="ht-btn ht-btn--lg ht-btn--ghost" role="button">← กลับไปที่รายชื่อ</a></span>
                <a href="student_page.php" target="_blank" class="ht-btn ht-btn--lg ht-btn--purple" role="button">🖥️ เปิดหน้าจอนักเรียน</a>
            </div>
        </section>

        <aside class="exam-results" aria-label="ผลการอ่านรายข้อ">
            <div class="exam-results__head">
                <span class="exam-results__title">📋 ผลการอ่าน</span>
                <span class="exam-results__sum" id="resultSum"></span>
            </div>
            <ol class="exam-results__list" id="resultList"></ol>
        </aside>
    </main>

    <script>window.EXAM = <?= json_encode($examConfig, JSON_UNESCAPED_UNICODE) ?>;</script>
    <script src="assets/js/jquery.min.js"></script>
    <script src="assets/js/exam.js?v=<?= @filemtime(__DIR__ . '/assets/js/exam.js') ?>"></script>
</body>
</html>
