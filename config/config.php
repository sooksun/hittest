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

// ---- เกม / ความปลอดภัย ----
const AUDIT_RETENTION_DAYS = 90;   // เก็บ audit_logs กี่วัน (scripts/prune_audit_logs.php ลบที่เก่ากว่านี้)
const TTS_RATE_MAX         = 30;   // จำนวนครั้งเรียก Botnoi (cache miss) สูงสุดต่อผู้ใช้
const TTS_RATE_WINDOW_SEC  = 60;   // ภายในกี่วินาที (sliding window ของ TTS_RATE_MAX)

// ---- mapping ----
const STU_STATUS = [
    1 => 'เด็กปกติ',
    2 => 'เด็กพิเศษ',
    3 => 'ขาดสอบ',
    4 => 'ย้ายออก',
];

date_default_timezone_set('Asia/Bangkok');
mb_internal_encoding('UTF-8');
