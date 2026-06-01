<?php
/**
 * includes/auth.php — เริ่ม session + ป้องกันหน้าที่ต้อง login
 * include บนสุดของทุกหน้าที่ต้องเข้าระบบ (ยกเว้น login.php)
 * โหลด functions/db/config ให้พร้อมใช้ในตัว
 *
 * session: sc_id (รหัสโรงเรียน 10 หลัก), sc_smis (username), sc_name (ชื่อโรงเรียน)
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// กัน bfcache/หน้าค้าง — หน้าที่ขับด้วยข้อมูล (คะแนน/สถานะสอบ) ต้องโหลดสดเสมอ
// ป้องกันกรณีกด back หลังบันทึกผลแล้วรายชื่อยังโชว์ค่าเก่า
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once __DIR__ . '/functions.php';

// กันสิทธิ์ข้ามบทบาท: ถ้า login เป็น "นักเรียน" อยู่ ห้ามเข้าหน้าครู/ผู้ดูแล → 403
if (($_SESSION['role'] ?? '') === 'student') {
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    echo '<meta charset="utf-8"><div style="font-family:sans-serif;max-width:520px;margin:60px auto;text-align:center">'
       . '<h2>⛔ หน้านี้สำหรับครู/ผู้ดูแลเท่านั้น</h2>'
       . '<p>บัญชีนักเรียนเข้าได้เฉพาะแดชบอร์ดของตนเอง</p>'
       . '<a href="my_dashboard.php">→ ไปแดชบอร์ดของฉัน</a></div>';
    exit;
}

if (empty($_SESSION['sc_id'])) {
    $isAjax = (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest')
           || str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json');
    if ($isAjax) {
        json_response(['status' => 'error', 'message' => 'ยังไม่ได้เข้าสู่ระบบ'], 401);
    }
    header('Location: login.php');
    exit;
}
