<?php
/**
 * paper_pdf.php — ดาวน์โหลดไฟล์ PDF ชุดแบบทดสอบ (สอบด้วยกระดาษ)
 * พารามิเตอร์: class_id (1-6), hit (1-3), hitset (>=1)
 *
 * ไฟล์จริงอยู่ใน /pdf_files (ถูก Deny ไม่ให้เข้าตรงผ่าน .htaccess) จึงต้องดาวน์โหลด
 * ผ่านไฟล์นี้ที่ผ่านการ login ครู/ผู้ดูแลก่อน (auth.php) — กันนักเรียน/คนนอกเดา URL
 * ดึงข้อสอบ.
 */
require __DIR__ . '/includes/auth.php';

$class_id = (int)($_GET['class_id'] ?? 0);
$hit      = (int)($_GET['hit'] ?? 0);
$hitset   = (int)($_GET['hitset'] ?? 0);

if ($class_id < 1 || $class_id > 6 || !valid_hit($hit) || $hitset < 1) {
    http_response_code(400);
    exit('พารามิเตอร์ไม่ถูกต้อง (class_id / hit / hitset)');
}

// ผูกกับสถานะเปิด/ปิดสอบ: รอบที่ปิดอยู่ ห้ามดาวน์โหลดข้อสอบ (เหมือน paper_export.php)
if (!exam_is_open($hit)) {
    http_response_code(403);
    exit('รอบสอบ Hit-' . $hit . ' ถูกปิดอยู่ — ดาวน์โหลดข้อสอบไม่ได้ (เปิดการสอบที่เมนู "จัดการสอบ" ก่อน)');
}

// ชุดข้อสอบเป็นชุดกลาง (hittestSet ไม่มี sc_id) — ดึงตาม class/hit/set
// ชื่อตารางตาม schema คือ hittestSet (S ตัวใหญ่) — MySQL บน Linux case-sensitive จึงต้องตรงเป๊ะ ห้ามเปลี่ยนเป็นพิมพ์เล็ก
$stmt = db()->prepare(
    'SELECT hittestdoc FROM hittestSet WHERE class_id = ? AND hit = ? AND hitset = ? LIMIT 1'
);
$stmt->execute([$class_id, $hit, $hitset]);
$doc = $stmt->fetchColumn();

if ($doc === false || $doc === '') {
    http_response_code(404);
    exit('ไม่พบชุดแบบทดสอบที่ระบุ');
}

// แก้ path ให้ปลอดภัย: บังคับให้อยู่ใน /pdf_files เท่านั้น (กัน path traversal)
$docBase  = realpath(__DIR__ . '/pdf_files');
$fullPath = $docBase . DIRECTORY_SEPARATOR . basename($doc);   // basename ตัด ../ ทิ้ง
$real     = realpath($fullPath);

if ($docBase === false || $real === false
    || !str_starts_with($real, $docBase . DIRECTORY_SEPARATOR)
    || !is_file($real)) {
    http_response_code(404);
    exit('ไม่พบไฟล์เอกสารในระบบ');
}

// ชื่อไฟล์ที่ผู้ใช้เห็นตอนเซฟ — ไทยผ่าน RFC 5987 + ASCII fallback
$dlName = "ข้อสอบ-ป.{$class_id}-Hit{$hit}-ชุด{$hitset}.pdf";
$ascii  = "hittest_p{$class_id}_hit{$hit}_set{$hitset}.pdf";

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $ascii . '"; '
     . "filename*=UTF-8''" . rawurlencode($dlName));
header('Content-Length: ' . filesize($real));
header('Cache-Control: private, max-age=0, must-revalidate');
header('X-Content-Type-Options: nosniff');

readfile($real);
exit;
