<?php
/** index.php — จุดเข้า: ส่งต่อตามบทบาท (นักเรียน / ครู / ยังไม่ login) */
session_start();
if (($_SESSION['role'] ?? '') === 'student' && !empty($_SESSION['stu']['stuid'])) {
    header('Location: my_dashboard.php');           // นักเรียน
} elseif (!empty($_SESSION['sc_id'])) {
    header('Location: menu.php');                   // ครู/ผู้ดูแล
} else {
    header('Location: landing.php');                // ยังไม่ login
}
exit;
