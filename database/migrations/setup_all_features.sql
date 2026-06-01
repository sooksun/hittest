-- ============================================================================
-- setup_all_features.sql — ติดตั้งตารางเสริมทั้งหมดที่ฟีเจอร์ใช้ (รันทีเดียวจบ)
-- ปลอดภัย 100%: ทุกคำสั่งเป็น IF NOT EXISTS / idempotent → รันซ้ำได้ ไม่แตะข้อมูลเดิม
-- ใช้กับ production ที่ไม่แน่ใจว่ารัน migration ไหนไปแล้วบ้าง
-- วิธีรัน (phpMyAdmin): เลือกฐาน ssraexhi_hittest → แท็บ SQL → วางทั้งไฟล์ → Go
-- ครอบคลุม: exam_window, reset_log, tts_cache, promote_log(+rolled_back_at),
--           promote_log_item, student_pin, login_throttle
-- หมายเหตุ: ตารางหลัก (students, schools, words, ฯลฯ) มาจากฐานข้อมูลหลักอยู่แล้ว ไม่อยู่ในไฟล์นี้
-- ============================================================================
SET NAMES utf8mb4;

-- ---- เปิด/ปิดการสอบรายรอบ ต่อโรงเรียน ----
CREATE TABLE IF NOT EXISTS exam_window (
  sc_id      VARCHAR(15) NOT NULL,
  hittest    TINYINT     NOT NULL,
  is_open    TINYINT     NOT NULL DEFAULT 1,
  updated_by VARCHAR(8)  DEFAULT NULL,
  updated_at DATETIME    DEFAULT NULL,
  PRIMARY KEY (sc_id, hittest)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---- ประวัติการรีเซตผลการสอบ ----
CREATE TABLE IF NOT EXISTS reset_log (
  id                  INT AUTO_INCREMENT PRIMARY KEY,
  sc_id               VARCHAR(15)  NOT NULL,
  sc_smis             VARCHAR(8)   DEFAULT NULL,
  sc_name             VARCHAR(255) DEFAULT NULL,
  scope               VARCHAR(20)  NOT NULL,
  class_id            TINYINT      DEFAULT NULL,
  rooms               INT          DEFAULT NULL,
  hittest             TINYINT      DEFAULT NULL,
  students_affected   INT          DEFAULT 0,
  eval_deleted        INT          DEFAULT 0,
  studenthit_deleted  INT          DEFAULT 0,
  studenteval_deleted INT          DEFAULT 0,
  created_at          DATETIME     NOT NULL,
  KEY idx_sc (sc_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---- แคชเสียงอ่าน Botnoi ----
CREATE TABLE IF NOT EXISTS tts_cache (
  cache_key  CHAR(40)     PRIMARY KEY,
  text       VARCHAR(255) NOT NULL,
  audio_url  TEXT         NOT NULL,
  created_at DATETIME     NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---- ประวัติการเลื่อนชั้น (+ ธงยกเลิก) ----
CREATE TABLE IF NOT EXISTS promote_log (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  sc_id          VARCHAR(15)  NOT NULL,
  sc_smis        VARCHAR(8)   DEFAULT NULL,
  sc_name        VARCHAR(255) DEFAULT NULL,
  promoted       INT          DEFAULT 0,
  graduated      INT          DEFAULT 0,
  created_at     DATETIME     NOT NULL,
  rolled_back_at DATETIME     NULL DEFAULT NULL,
  KEY idx_sc (sc_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- เพิ่มคอลัมน์ rolled_back_at เฉพาะกรณี promote_log มีอยู่ก่อนแล้วแต่ยังไม่มีคอลัมน์ (idempotent)
SET @has_col := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'promote_log' AND COLUMN_NAME = 'rolled_back_at');
SET @ddl := IF(@has_col = 0,
  'ALTER TABLE promote_log ADD COLUMN rolled_back_at DATETIME NULL DEFAULT NULL AFTER created_at', 'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---- รายการนักเรียนต่อการเลื่อนชั้น (ใช้ย้อนกลับ) ----
CREATE TABLE IF NOT EXISTS promote_log_item (
  id             BIGINT AUTO_INCREMENT PRIMARY KEY,
  log_id         INT          NOT NULL,
  stuid          VARCHAR(50)  NOT NULL,
  prev_class_id  INT          NULL,
  prev_stustatus INT          NULL,
  action         VARCHAR(10)  NOT NULL,
  KEY idx_log (log_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---- PIN นักเรียน (ทางเลือกสำรอง — ปัจจุบัน login ใช้รหัสนักเรียนตรง ๆ) ----
CREATE TABLE IF NOT EXISTS student_pin (
  sc_id      VARCHAR(15)  NOT NULL,
  stuid      VARCHAR(50)  NOT NULL,
  pin_hash   VARCHAR(255) NOT NULL,
  pin_plain  VARCHAR(6)   NULL,
  is_active  TINYINT(1)   NOT NULL DEFAULT 1,
  created_by VARCHAR(8)   NULL,
  created_at DATETIME     NOT NULL,
  PRIMARY KEY (sc_id, stuid),
  UNIQUE KEY uniq_school_pin (sc_id, pin_plain)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---- ตัวนับ/ล็อก กัน brute-force ที่หน้า student_login ----
CREATE TABLE IF NOT EXISTS login_throttle (
  scope_key    VARCHAR(100) NOT NULL,
  fail_count   INT          NOT NULL DEFAULT 0,
  locked_until DATETIME     NULL,
  updated_at   DATETIME     NOT NULL,
  PRIMARY KEY (scope_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---- บันทึกผลการฝึกอ่านของนักเรียน (my_practice.php) ----
CREATE TABLE IF NOT EXISTS practice_log (
  id         BIGINT AUTO_INCREMENT PRIMARY KEY,
  sc_id      VARCHAR(15)  NOT NULL,
  stuid      VARCHAR(50)  NOT NULL,
  stuname    VARCHAR(255) DEFAULT NULL,
  class_id   INT          NOT NULL,
  hittest    INT          NOT NULL,
  sethit     INT          NOT NULL,
  word_id    INT          DEFAULT NULL,
  word       VARCHAR(255) NOT NULL,
  attempts   INT          NOT NULL DEFAULT 0,
  correct    TINYINT(1)   NOT NULL DEFAULT 0,
  created_at DATETIME     NOT NULL,
  KEY idx_stu (stuid, created_at),
  KEY idx_sc (sc_id, created_at),
  KEY idx_word (word_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
