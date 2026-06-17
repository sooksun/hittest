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
/*
// ---- ฐานข้อมูล (localhost) ----
const DB_HOST    = 'localhost';
const DB_NAME    = 'ssrainfo_hittest';
const DB_USER    = 'ssrainfo_hittest';
const DB_PASS    = 'l6-lyo9N';
const DB_CHARSET = 'utf8mb4';
*/

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

// ---- สร้าง/จัดการสื่อในตัว (เสียง Botnoi + ภาพ ComfyUI) — หน้า admin_media.php ----
// โทเคน Botnoi อยู่ที่ config/botnoi.php · host ComfyUI/คีย์ LLM อยู่ที่ config/media_gen.php (gitignored)
const MEDIA_GEN_WORKER_BATCH = 25; // จำนวนงานสูงสุดต่อรอบของ scripts/process_media_jobs.php (กันรันยาว)

// ---- ผู้ดูแลระบบ (เครื่องมืออันตรายระดับระบบ: backup/restore DB, เลื่อนชั้นทั้งระบบ) ----
// ทุกโรงเรียน login ด้วย SMIS ได้ → ต้องจำกัดเฉพาะ SMIS ในรายชื่อนี้เท่านั้น
// ⚠️ ใส่ SMIS ของผู้ดูแลจริงบน production. รายชื่อว่าง = ไม่มีใครเข้าเครื่องมือผู้ดูแลได้ (fail-safe)
const ADMIN_SMIS = ['57030129'];

// (ไม่บังคับ) path ของ mysqldump/mysql ถ้าไม่อยู่ใน PATH ของ server — ปลดคอมเมนต์ถ้าจำเป็น
// const MYSQLDUMP_BIN = '/usr/bin/mysqldump';
// const MYSQL_BIN     = '/usr/bin/mysql';
// (ไม่บังคับ) โฟลเดอร์เก็บ backup — ค่าเริ่มต้น = <app>/backups (มี .htaccess กันเว็บ); ย้ายออกนอก web root ได้ถ้าทำได้
// const BACKUP_DIR = '/home/USER/backups/hittest';

// ---- บัญชีผู้ใช้ (ตาราง users) — role ตามลำดับชั้นการบริหาร (หน้า admin_users.php) ----
// superadmin = ผู้ดูแลระบบทั้งประเทศ · saoadmin = ผู้ดูแลเขต สพป. (ดูทุกโรงเรียนในเขต) · school = โรงเรียนเดียว
const USER_ROLES = ['superadmin' => 'ผู้ดูแลระบบ', 'saoadmin' => 'ผู้ดูแลเขต (สพป.)', 'school' => 'โรงเรียน'];

// ---- mapping ----
const STU_STATUS = [
    1 => 'เด็กปกติ',
    2 => 'เด็กพิเศษ',
    3 => 'ขาดสอบ',
    4 => 'ย้ายออก',
];

date_default_timezone_set('Asia/Bangkok');
mb_internal_encoding('UTF-8');
