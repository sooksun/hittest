<?php
/** logout.php — ออกจากระบบ (ครู → login.php, นักเรียน → student_login.php) */
session_start();
$wasStudent = (($_SESSION['role'] ?? '') === 'student');
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();
header('Location: ' . ($wasStudent ? 'student_login.php' : 'login.php'));
exit;
