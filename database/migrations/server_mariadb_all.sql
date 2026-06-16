-- ============================================================================
-- server_mariadb_all.sql — นำ schema บน SERVER (MariaDB 10.4) ให้ตรงกับโค้ดใหม่
-- สร้างจาก: 004_games + 006_phase2_hardening + 008_app_settings + setup_all_features
-- แก้ให้ MariaDB ใช้ได้: utf8mb4_general_ci -> utf8mb4_general_ci (ตรงกับตารางเดิมบน server)
-- ปลอดภัย/idempotent: CREATE TABLE IF NOT EXISTS + ตรวจ information_schema ก่อน ALTER
-- วิธีรัน (phpMyAdmin): เลือกฐาน ssrainfo_hittest -> แท็บ SQL -> วางทั้งไฟล์ -> Go
-- ⚠️ สำรอง DB ก่อน (มีการ DROP/ADD PRIMARY KEY ที่ students/studenthit)
-- ============================================================================
SET NAMES utf8mb4;

-- ===== [004] ตารางเกมหลัก (ต้องมาก่อน 006 เพราะ FK อ้างถึง) =====
-- ── 1. Generic game results (balloon, bubble, memory scores) ─────────────────
CREATE TABLE IF NOT EXISTS game_results (
  id          BIGINT AUTO_INCREMENT PRIMARY KEY,
  sc_id       VARCHAR(15)   NOT NULL,
  stuid       VARCHAR(50)   NOT NULL,
  stuname     VARCHAR(255)  NULL,
  class_id    INT           NOT NULL DEFAULT 1,
  game        VARCHAR(30)   NOT NULL,         -- 'memory','balloon','bubble','hangman','training'
  grade       INT           NOT NULL DEFAULT 1,
  difficulty  INT           NOT NULL DEFAULT 1,
  score       INT           NOT NULL DEFAULT 0,
  stats_json  JSON          NULL,             -- game-specific extra stats
  created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_stu  (stuid, created_at),
  KEY idx_sc   (sc_id, created_at),
  KEY idx_game (game, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── 2. Hangman sessions (server-side state per game round) ───────────────────
CREATE TABLE IF NOT EXISTS game_hangman_sessions (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  sc_id           VARCHAR(15)   NULL,
  stuid           VARCHAR(50)   NULL,
  grade_level     INT           NOT NULL,
  difficulty      INT           NOT NULL,
  word_id         INT           NOT NULL,
  word            VARCHAR(255)  NOT NULL,
  masked_word     JSON          NOT NULL,     -- string[] with revealed chars and "_"
  remaining_lives INT           NOT NULL DEFAULT 5,
  score           INT           NOT NULL DEFAULT 0,
  completed       TINYINT(1)    NOT NULL DEFAULT 0,
  started_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ended_at        DATETIME      NULL,
  KEY idx_stu (stuid, started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── 3. Training sessions (stores generated task list + progress) ─────────────
CREATE TABLE IF NOT EXISTS game_training_sessions (
  id            VARCHAR(36)  PRIMARY KEY,    -- UUID v4
  sc_id         VARCHAR(15)  NOT NULL,
  stuid         VARCHAR(50)  NOT NULL,
  grade_level   INT          NOT NULL,
  difficulty    INT          NOT NULL,
  tasks_json    JSON         NOT NULL,       -- serialised Task[] generated at start
  total_tasks   INT          NOT NULL,
  correct_count INT          NOT NULL DEFAULT 0,
  total_score   INT          NOT NULL DEFAULT 0,
  started_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ended_at      DATETIME     NULL,
  KEY idx_stu (stuid, started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── 1. Hangman guesses (replaces game_hangman_sessions.guessed_chars JSON) ────
CREATE TABLE IF NOT EXISTS game_hangman_guesses (
  id          BIGINT AUTO_INCREMENT PRIMARY KEY,
  session_id  INT          NOT NULL,
  guess_char  VARCHAR(16)  CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,  -- NFC-normalised; binary so tone/vowel variants stay distinct
  guess_type  VARCHAR(16)  NULL,
  is_correct  TINYINT(1)   NOT NULL DEFAULT 0,
  created_at  DATETIME(3)  NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  UNIQUE KEY uniq_session_char (session_id, guess_char),
  KEY idx_session (session_id),
  CONSTRAINT fk_guess_session FOREIGN KEY (session_id)
      REFERENCES game_hangman_sessions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── 2. Training task submissions (replaces submitted_task_ids JSON) ───────────
CREATE TABLE IF NOT EXISTS game_task_submissions (
  id           BIGINT AUTO_INCREMENT PRIMARY KEY,
  session_id   VARCHAR(36)  NOT NULL,
  task_uuid    VARCHAR(36)  NOT NULL,
  is_correct   TINYINT(1)   NOT NULL DEFAULT 0,
  score        INT          NOT NULL DEFAULT 0,
  base_score   INT          NOT NULL DEFAULT 0,
  speed_bonus  INT          NOT NULL DEFAULT 0,
  elapsed_ms   INT          NOT NULL DEFAULT 0,   -- server-computed, never client-supplied
  submitted_at DATETIME(3)  NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  UNIQUE KEY uniq_session_task (session_id, task_uuid),
  KEY idx_session (session_id),
  CONSTRAINT fk_submission_session FOREIGN KEY (session_id)
      REFERENCES game_training_sessions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── 3. Audit log (request trail + security events) ───────────────────────────
CREATE TABLE IF NOT EXISTS audit_logs (
  id          BIGINT AUTO_INCREMENT PRIMARY KEY,
  created_at  DATETIME(3)  NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  sc_id       VARCHAR(15)  NULL,
  stuid       VARCHAR(50)  NULL,
  role        VARCHAR(20)  NULL,            -- 'student' | 'teacher' | 'guest'
  method      VARCHAR(10)  NULL,
  path        VARCHAR(255) NULL,
  action      VARCHAR(50)  NULL,            -- 'request','ownership_denied','duplicate_guess',...
  entity_type VARCHAR(30)  NULL,
  entity_id   VARCHAR(64)  NULL,
  status_code INT          NULL,
  ip          VARCHAR(45)  NULL,
  user_agent  VARCHAR(255) NULL,
  duration_ms INT          NULL,
  meta_json   JSON         NULL,
  KEY idx_created (created_at),
  KEY idx_stu    (stuid, created_at),
  KEY idx_action (action, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── 4. Drop the transient JSON columns from 005 (idempotent) ──────────────────
SET @has_gc := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'game_hangman_sessions' AND COLUMN_NAME = 'guessed_chars');
SET @ddl := IF(@has_gc > 0, 'ALTER TABLE game_hangman_sessions DROP COLUMN guessed_chars', 'DO 0');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

SET @has_st := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'game_training_sessions' AND COLUMN_NAME = 'submitted_task_ids');
SET @ddl := IF(@has_st > 0, 'ALTER TABLE game_training_sessions DROP COLUMN submitted_task_ids', 'DO 0');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

-- ===== [008] app_settings =====
CREATE TABLE IF NOT EXISTS app_settings (
  skey       VARCHAR(64)  NOT NULL PRIMARY KEY,   -- ชื่อค่า เช่น 'admin_smis'
  sval       TEXT         NULL,                   -- ค่า (string หรือ JSON)
  updated_at DATETIME     NOT NULL,
  KEY idx_updated (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===== [setup_all_features] exam_window..users + 009 media + 010 years + 011 users =====
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

-- ---- เรื่องที่นักเรียนแต่งท้ายเกม (story_submit.php / student_stories.php) = migration 007 ----
CREATE TABLE IF NOT EXISTS game_stories (
  id          BIGINT AUTO_INCREMENT PRIMARY KEY,
  sc_id       VARCHAR(15)  NOT NULL,
  stuid       VARCHAR(50)  NOT NULL,
  stuname     VARCHAR(255) NULL,
  class_id    INT          NOT NULL DEFAULT 1,
  game        VARCHAR(30)  NOT NULL,            -- เกมต้นทาง: memory/balloon/bubble/hangman/training
  grade       INT          NOT NULL DEFAULT 1,
  difficulty  INT          NOT NULL DEFAULT 1,
  words_json  JSON         NULL,
  story_text  TEXT         NOT NULL,
  char_count  INT          NOT NULL DEFAULT 0,
  used_all    TINYINT(1)   NOT NULL DEFAULT 0,
  stats_json  JSON         NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_stu  (stuid, created_at),
  KEY idx_sc   (sc_id, created_at),
  KEY idx_game (game, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---- ระบบสร้าง/จัดการสื่อในตัว (Botnoi TTS + ComfyUI) = migration 009 ----
-- ขยาย wordstest (ตารางหลัก) ให้เก็บ metadata ของภาพ — idempotent ตรวจ information_schema ก่อน ALTER
SET @t := (SELECT DATA_TYPE FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'wordstest' AND COLUMN_NAME = 'image_path');
SET @ddl := IF(@t IS NOT NULL AND @t <> 'text',
  'ALTER TABLE wordstest MODIFY image_path TEXT NULL', 'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_col := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'wordstest' AND COLUMN_NAME = 'what_to_draw');
SET @ddl := IF(@has_col = 0,
  'ALTER TABLE wordstest ADD COLUMN what_to_draw TEXT NULL AFTER spoken_form', 'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_col := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'wordstest' AND COLUMN_NAME = 'image_status');
SET @ddl := IF(@has_col = 0,
  "ALTER TABLE wordstest ADD COLUMN image_status ENUM('EMPTY','QUEUED','GENERATING','DONE','FAILED','AMBIGUOUS') NOT NULL DEFAULT 'EMPTY' AFTER what_to_draw",
  'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_col := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'wordstest' AND COLUMN_NAME = 'image_reason');
SET @ddl := IF(@has_col = 0,
  'ALTER TABLE wordstest ADD COLUMN image_reason VARCHAR(255) NULL AFTER image_status', 'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_col := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'wordstest' AND COLUMN_NAME = 'image_generated_at');
SET @ddl := IF(@has_col = 0,
  'ALTER TABLE wordstest ADD COLUMN image_generated_at DATETIME NULL AFTER image_reason', 'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_col := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'wordstest' AND COLUMN_NAME = 'sound_generated_at');
SET @ddl := IF(@has_col = 0,
  'ALTER TABLE wordstest ADD COLUMN sound_generated_at DATETIME NULL AFTER sound_path', 'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_idx := (SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'wordstest' AND INDEX_NAME = 'idx_image_status');
SET @ddl := IF(@has_idx = 0,
  'ALTER TABLE wordstest ADD INDEX idx_image_status (image_status)', 'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- คิวงานสร้างสื่อ (แทน BullMQ) — ขับด้วย scripts/process_media_jobs.php
CREATE TABLE IF NOT EXISTS game_media_jobs (
  id           BIGINT       NOT NULL AUTO_INCREMENT PRIMARY KEY,
  type         ENUM('audio','image')                       NOT NULL,
  word_id      INT          NOT NULL,
  status       ENUM('queued','processing','done','failed') NOT NULL DEFAULT 'queued',
  attempts     INT          NOT NULL DEFAULT 0,
  forced       TINYINT(1)   NOT NULL DEFAULT 0,
  last_error   TEXT         NULL,
  meta_json    JSON         NULL,
  requested_by VARCHAR(64)  NULL,
  created_at   DATETIME(3)  NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  updated_at   DATETIME(3)  NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  KEY idx_status_type (status, type),
  KEY idx_word (word_id, type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ประวัติ prompt ของภาพ (แก้/regenerate ได้)
CREATE TABLE IF NOT EXISTS game_prompt_history (
  id              INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
  word_id         INT          NOT NULL,
  word            VARCHAR(255) NOT NULL,
  positive_prompt TEXT         NOT NULL,
  negative_prompt TEXT         NOT NULL,
  what_to_draw_th TEXT         NULL,
  what_to_draw_en TEXT         NULL,
  checkpoint_name VARCHAR(255) NULL,
  seed            BIGINT       NULL,
  preview_image   TEXT         NULL,
  status          VARCHAR(50)  NOT NULL DEFAULT 'generated',
  is_active       TINYINT(1)   NOT NULL DEFAULT 1,
  notes           TEXT         NULL,
  created_at      DATETIME(3)  NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  updated_at      DATETIME(3)  NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  KEY idx_word_active (word_id, is_active),
  KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---- ผูก students/studenthit กับปีการศึกษา (พ.ศ.) = migration 010 ----
-- เดิม PK=stuid (1 แถว/คน ไม่มีปี) → 1 แถว/คน/ปี · ข้อมูลเดิม → 2568 · idempotent ตรวจ information_schema
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'students' AND COLUMN_NAME = 'years');
SET @ddl := IF(@c = 0, 'ALTER TABLE students ADD COLUMN years INT NULL AFTER class_id', 'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;
UPDATE students SET years = 2568 WHERE years IS NULL;
SET @t := (SELECT IS_NULLABLE FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'students' AND COLUMN_NAME = 'years');
SET @ddl := IF(@t = 'YES', 'ALTER TABLE students MODIFY years INT NOT NULL DEFAULT 2569', 'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @pk := (SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'students' AND INDEX_NAME = 'PRIMARY');
SET @ddl := IF(@pk = 'stuid', 'ALTER TABLE students DROP PRIMARY KEY, ADD PRIMARY KEY (stuid, years)', 'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @i := (SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'students' AND INDEX_NAME = 'idx_sc_year');
SET @ddl := IF(@i = 0, 'ALTER TABLE students ADD INDEX idx_sc_year (sc_id, years, class_id)', 'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'studenthit' AND COLUMN_NAME = 'years');
SET @ddl := IF(@c = 0, 'ALTER TABLE studenthit ADD COLUMN years INT NULL AFTER hit', 'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;
UPDATE studenthit SET years = 2568 WHERE years IS NULL;
SET @t := (SELECT IS_NULLABLE FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'studenthit' AND COLUMN_NAME = 'years');
SET @ddl := IF(@t = 'YES', 'ALTER TABLE studenthit MODIFY years INT NOT NULL DEFAULT 2569', 'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @pk := (SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'studenthit' AND INDEX_NAME = 'PRIMARY');
SET @ddl := IF(@pk = 'stuid,hit', 'ALTER TABLE studenthit DROP PRIMARY KEY, ADD PRIMARY KEY (stuid, years, hit)', 'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'promote_log_item' AND COLUMN_NAME = 'years');
SET @ddl := IF(@c = 0, 'ALTER TABLE promote_log_item ADD COLUMN years INT NULL AFTER stuid', 'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── 011: ตาราง users (บัญชีผู้ใช้จริง + role) สำหรับหน้า admin_users.php ──────────
--   login.php รองรับ username/password (hash) ของตารางนี้ ควบคู่กับ SMIS โรงเรียนเดิม
CREATE TABLE IF NOT EXISTS users (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(50)  NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  name          VARCHAR(150) NOT NULL DEFAULT '',
  role          ENUM('superadmin','saoadmin','school') NOT NULL DEFAULT 'school',
  area_code     VARCHAR(10)  NULL,
  sc_id         VARCHAR(15)  NULL,
  is_active     TINYINT(1)   NOT NULL DEFAULT 1,
  created_at    DATETIME     NOT NULL,
  updated_at    DATETIME     NOT NULL,
  UNIQUE KEY uq_username (username),
  KEY idx_role (role),
  KEY idx_area (area_code),
  KEY idx_sc (sc_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
