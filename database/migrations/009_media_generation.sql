-- ─────────────────────────────────────────────────────────────────────────────
-- 009_media_generation.sql  –  ระบบสร้าง/จัดการสื่อในตัว (Botnoi TTS + ComfyUI image)
--   พอร์ตจากระบบ readthai (NestJS) มาเป็น PHP ฝั่ง newhittest เพื่อให้ "เป็นเจ้าของ" สื่อเอง
--   - ขยายตาราง wordstest ให้เก็บ metadata ของภาพ (เดิมไป LEFT JOIN readthai.wordstest)
--   - game_media_jobs : คิวงานสร้างสื่อ (แทน BullMQ/Redis) ขับด้วย scripts/process_media_jobs.php
--   - game_prompt_history : เก็บประวัติ prompt ของภาพ (แก้/regenerate/เวอร์ชัน)
--
-- Idempotent: ตรวจ information_schema ก่อนทุก ALTER + CREATE TABLE IF NOT EXISTS — รันซ้ำได้
-- Run: "D:/laragon/bin/mysql/mysql-8.0.30-winx64/bin/mysql.exe" -uroot ssraexhi_hittest < database/migrations/009_media_generation.sql
-- ─────────────────────────────────────────────────────────────────────────────
SET NAMES utf8mb4;

-- ── 1) ขยาย wordstest ให้เก็บสถานะ/คำบรรยายภาพในตัว ─────────────────────────────
-- image_path : VARCHAR(255) → TEXT (เก็บ JSON ของภาพหลายแบบเหมือน readthai)
SET @t := (SELECT DATA_TYPE FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'wordstest' AND COLUMN_NAME = 'image_path');
SET @ddl := IF(@t IS NOT NULL AND @t <> 'text',
  'ALTER TABLE wordstest MODIFY image_path TEXT NULL', 'DO 0');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

-- what_to_draw : คำบรรยายฉากที่จะวาด (ไทย/อังกฤษ) ที่ LLM/override สร้าง
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'wordstest' AND COLUMN_NAME = 'what_to_draw');
SET @ddl := IF(@c = 0,
  'ALTER TABLE wordstest ADD COLUMN what_to_draw TEXT NULL AFTER spoken_form', 'DO 0');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

-- image_status : สถานะการสร้างภาพ
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'wordstest' AND COLUMN_NAME = 'image_status');
SET @ddl := IF(@c = 0,
  "ALTER TABLE wordstest ADD COLUMN image_status ENUM('EMPTY','QUEUED','GENERATING','DONE','FAILED','AMBIGUOUS') NOT NULL DEFAULT 'EMPTY' AFTER what_to_draw",
  'DO 0');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

-- image_reason : เหตุผล (error / ทำไมเป็น AMBIGUOUS)
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'wordstest' AND COLUMN_NAME = 'image_reason');
SET @ddl := IF(@c = 0,
  'ALTER TABLE wordstest ADD COLUMN image_reason VARCHAR(255) NULL AFTER image_status', 'DO 0');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

-- image_generated_at / sound_generated_at : เวลาที่สร้างเสร็จ
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'wordstest' AND COLUMN_NAME = 'image_generated_at');
SET @ddl := IF(@c = 0,
  'ALTER TABLE wordstest ADD COLUMN image_generated_at DATETIME NULL AFTER image_reason', 'DO 0');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'wordstest' AND COLUMN_NAME = 'sound_generated_at');
SET @ddl := IF(@c = 0,
  'ALTER TABLE wordstest ADD COLUMN sound_generated_at DATETIME NULL AFTER sound_path', 'DO 0');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

-- ดัชนีช่วยกรองในหน้า admin (เลือกคำที่ยังไม่มีภาพ/เสียง)
SET @c := (SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'wordstest' AND INDEX_NAME = 'idx_image_status');
SET @ddl := IF(@c = 0,
  'ALTER TABLE wordstest ADD INDEX idx_image_status (image_status)', 'DO 0');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

-- ── 2) game_media_jobs : คิวงานสร้างสื่อ (แทน BullMQ) ───────────────────────────
--   หนึ่งงาน = สร้าง audio หรือ image ของ word หนึ่งคำ. worker ดึง queued → processing → done/failed.
--   `forced`=1 = สั่งสร้างใหม่แม้มีของเดิม (ตั้งชื่อ forced เลี่ยง reserved word FORCE)
CREATE TABLE IF NOT EXISTS game_media_jobs (
  id          BIGINT       NOT NULL AUTO_INCREMENT PRIMARY KEY,
  type        ENUM('audio','image')                       NOT NULL,
  word_id     INT          NOT NULL,
  status      ENUM('queued','processing','done','failed') NOT NULL DEFAULT 'queued',
  attempts    INT          NOT NULL DEFAULT 0,
  forced      TINYINT(1)   NOT NULL DEFAULT 0,
  last_error  TEXT         NULL,
  meta_json   JSON         NULL,
  requested_by VARCHAR(64) NULL,                           -- SMIS ผู้สั่ง (audit เบา ๆ)
  created_at  DATETIME(3)  NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  updated_at  DATETIME(3)  NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  KEY idx_status_type (status, type),
  KEY idx_word (word_id, type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── 3) game_prompt_history : ประวัติ prompt ของภาพ (แก้/regenerate ได้) ──────────
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
  preview_image   TEXT         NULL,                       -- public path ของภาพที่ออกมา
  status          VARCHAR(50)  NOT NULL DEFAULT 'generated',
  is_active       TINYINT(1)   NOT NULL DEFAULT 1,
  notes           TEXT         NULL,
  created_at      DATETIME(3)  NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  updated_at      DATETIME(3)  NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  KEY idx_word_active (word_id, is_active),
  KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
