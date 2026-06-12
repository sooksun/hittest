<?php
/**
 * students_import_tpl.php — ดาวน์โหลด Excel template สำหรับนำเข้ารายชื่อนักเรียนใหม่
 * คอลัมน์: รหัสนักเรียน, ชื่อ-สกุล, ชั้น(1-6), ห้อง, ประเภท(1-4), ชุด Hit-1/2/3 (1-5)
 * กรอกข้อมูลตั้งแต่แถวที่ 4 แล้วอัปโหลดผ่าน students_import.php
 */
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

$headers = ['รหัสนักเรียน (เว้นว่าง=ระบบกำหนด)', 'ชื่อ-สกุล', 'ชั้น (1-6)', 'ห้อง', 'ประเภท (1-4)', 'ชุด Hit-1 (1-5)', 'ชุด Hit-2 (1-5)', 'ชุด Hit-3 (1-5)'];
$lastColIdx = count($headers);                                  // 8
$lastCol    = Coordinate::stringFromColumnIndex($lastColIdx);   // H

$ss = new Spreadsheet();
$ss->getProperties()->setCreator('HIT-TEST')->setTitle('นำเข้านักเรียนใหม่');
$sheet = $ss->getActiveSheet();
$sheet->setTitle('นำเข้านักเรียน');

$sheet->setCellValue('A1', 'แบบนำเข้ารายชื่อนักเรียนใหม่ — โรงเรียน ' . $_SESSION['sc_name']);
$sheet->setCellValue('A2', 'กรอกข้อมูลตั้งแต่แถวที่ 4 · รหัสนักเรียน: เว้นว่างได้ ระบบจะกำหนดให้อัตโนมัติ (ถ้ากรอกเองต้องไม่ซ้ำ) · ประเภท: 1=เด็กปกติ 2=เด็กพิเศษ 3=ขาดสอบ 4=ย้ายออก (เว้นว่าง=1) · ชุดคำ 1-5 (เว้นว่าง=1) · ตัวอย่าง: (เว้นว่าง) | เด็กชายตัวอย่าง | 1 | 1 | 1 | 1 | 1 | 1');
$sheet->mergeCells("A1:{$lastCol}1");
$sheet->mergeCells("A2:{$lastCol}2");
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
$sheet->getStyle('A2')->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF8A6D3B'));
$sheet->getStyle('A1:A2')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

$headRow = 3;
foreach ($headers as $c => $title) {
    $sheet->setCellValue([$c + 1, $headRow], $title);
}
$sheet->getStyle("A{$headRow}:{$lastCol}{$headRow}")->applyFromArray([
    'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF4D96FF']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
]);

$sheet->getColumnDimension('A')->setWidth(26);
$sheet->getColumnDimension('B')->setWidth(28);
foreach (['C', 'D', 'E', 'F', 'G', 'H'] as $cc) {
    $sheet->getColumnDimension($cc)->setWidth(13);
}
// ตีกรอบหัวตาราง + 30 แถวว่างให้กรอก
$sheet->getStyle("A{$headRow}:{$lastCol}" . ($headRow + 30))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
$sheet->getStyle("C4:H" . ($headRow + 30))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->freezePane('A4');

$filename = 'hittest_students_import_template.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
(new Xlsx($ss))->save('php://output');
exit;
