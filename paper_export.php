<?php
/**
 * paper_export.php — ดาวน์โหลด Excel รายชื่อนักเรียน + ช่องกรอกคะแนน (สอบด้วยกระดาษ)
 * พารามิเตอร์: class_id (1-6), hittest (1-3)
 * คอลัมน์: ลำดับ, รหัสนักเรียน, ชื่อ-สกุล, ชั้น, ห้อง, รอบ, ชุด, ข้อ1..ข้อ20, รวมคะแนน
 *   กรอก ข้อ1..ข้อ20 = 1 (อ่านถูก) / 0 (อ่านผิด) แล้ว Upload กลับผ่าน paper_import.php
 */
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

$class_id = (int)($_GET['class_id'] ?? 0);
$hittest  = (int)($_GET['hittest'] ?? 0);
if ($class_id < 1 || $class_id > 6 || !valid_hit($hittest)) {
    die('พารามิเตอร์ไม่ถูกต้อง (class_id / hittest)');
}
if (!exam_is_open($hittest)) {
    die('รอบสอบ Hit-' . $hittest . ' ถูกปิดอยู่ — ดาวน์โหลดแบบกรอกคะแนนไม่ได้ (เปิดการสอบที่เมนู "จัดการสอบ" ก่อน)');
}

$stmt = db()->prepare('SELECT * FROM students WHERE sc_id = ? AND years = ? AND class_id = ? AND deleted_at IS NULL AND stustatus <> 4 ORDER BY rooms, stuname');
$stmt->execute([current_sc_id(), current_year(), $class_id]);
$students = $stmt->fetchAll();

$headers = ['ลำดับ', 'รหัสนักเรียน', 'ชื่อ-สกุล', 'ชั้น', 'ห้อง', 'รอบ', 'ชุด'];
for ($i = 1; $i <= WORDS_PER_SET; $i++) {
    $headers[] = 'ข้อ' . $i;
}
$headers[] = 'รวมคะแนน';
$lastColIdx = count($headers);                       // 28
$lastCol    = Coordinate::stringFromColumnIndex($lastColIdx);

$ss = new Spreadsheet();
$ss->getProperties()->setCreator('HIT-TEST')->setTitle("ป.$class_id Hit-$hittest");
$sheet = $ss->getActiveSheet();
$sheet->setTitle("ป.$class_id Hit-$hittest");

// แถวคำอธิบาย
$sheet->setCellValue('A1', "แบบกรอกคะแนนสอบอ่าน (กระดาษ) — ป.$class_id · รอบ Hit-$hittest · โรงเรียน " . $_SESSION['sc_name']);
$sheet->setCellValue('A2', 'วิธีกรอก: ช่อง ข้อ1–ข้อ20 ใส่ 1 = อ่านถูก, 0 = อ่านผิด (เว้นว่าง = 0) แล้ว Upload กลับเข้าระบบ — ห้ามแก้คอลัมน์ "รหัสนักเรียน" และ "รอบ"');
$sheet->mergeCells("A1:{$lastCol}1");
$sheet->mergeCells("A2:{$lastCol}2");
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
$sheet->getStyle('A2')->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF8A6D3B'));
$sheet->getStyle('A1:A2')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

// แถวหัวตาราง (row 3)
$headRow = 3;
foreach ($headers as $c => $title) {
    $sheet->setCellValue([$c + 1, $headRow], $title);
}
$sheet->getStyle("A{$headRow}:{$lastCol}{$headRow}")->applyFromArray([
    'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF4D96FF']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
]);

// แถวข้อมูลนักเรียน (เริ่ม row 4)
$r = $headRow + 1;
foreach ($students as $i => $s) {
    $sheet->setCellValue([1, $r], $i + 1);
    $sheet->setCellValueExplicit([2, $r], (string)$s['stuid'], DataType::TYPE_STRING);
    $sheet->setCellValue([3, $r], $s['stuname']);
    $sheet->setCellValue([4, $r], 'ป.' . (int)$s['class_id']);
    $sheet->setCellValue([5, $r], (int)$s['rooms']);
    $sheet->setCellValue([6, $r], $hittest);
    $sheet->setCellValue([7, $r], (int)($s["sethit{$hittest}"] ?? 0));
    // ข้อ1..20 เว้นว่าง ; รวมคะแนน เว้นว่าง (ระบบคำนวณตอน import)
    $r++;
}
$lastRow = $r - 1;

// จัดรูปแบบ: ความกว้างคอลัมน์ + กึ่งกลางช่องคะแนน + เส้นตาราง
$sheet->getColumnDimension('A')->setWidth(7);
$sheet->getColumnDimension('B')->setWidth(16);
$sheet->getColumnDimension('C')->setWidth(28);
foreach (['D', 'E', 'F', 'G'] as $cc) {
    $sheet->getColumnDimension($cc)->setWidth(7);
}
for ($c = 8; $c <= $lastColIdx; $c++) {
    $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setWidth(7);
}
if ($lastRow >= 4) {
    $sheet->getStyle("A3:{$lastCol}{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $sheet->getStyle("H4:{$lastCol}{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle("A4:A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle("D4:G{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
}
$sheet->freezePane('D4');   // ตรึงหัวตาราง + คอลัมน์ระบุตัวตน

// ส่งออกเป็นไฟล์
$filename = "hittest_paper_p{$class_id}_hit{$hittest}.xlsx";
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($ss);
$writer->save('php://output');
exit;
