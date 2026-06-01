<?php
/**
 * config/config.php — ค่าคงที่ของระบบ HIT-TEST
 * แก้การเชื่อมต่อ/ค่าระบบที่ไฟล์นี้ไฟล์เดียว
 */

// ---- ฐานข้อมูล (localhost) ----
const DB_HOST    = '127.0.0.1';
const DB_NAME    = 'ssraexhi_hittest';
const DB_USER    = 'root';
const DB_PASS    = '';
const DB_CHARSET = 'utf8mb4';

// ---- แอปพลิเคชัน ----
const APP_NAME      = '@HIT-TEST';
const ACADEMIC_YEAR = 2569;        // ปีการศึกษาปัจจุบัน (พ.ศ.) — บันทึกลง evaluations.years / studenteval.years
const WORDS_PER_SET = 20;          // จำนวนคำต่อชุด
const EXAM_MINUTES  = 5;           // เวลาสอบ (นาที)
const HITTESTS      = [1, 2, 3];   // รอบสอบ (Hit-1/2/3)
const PASS_SCORE    = 10;          // เกณฑ์ผ่าน = 50% ของ 20 (ใช้ใน dashboard ผลพัฒนาการ)

// ---- mapping ----
const STU_STATUS = [
    1 => 'เด็กปกติ',
    2 => 'เด็กพิเศษ',
    3 => 'ขาดสอบ',
    4 => 'ย้ายออก',
];

date_default_timezone_set('Asia/Bangkok');
mb_internal_encoding('UTF-8');
