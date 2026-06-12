-- ─────────────────────────────────────────────────────────────────────────────
-- 007_game_stories.sql  –  เก็บ "ภารกิจนักเล่าเรื่อง" (storyteller) ท้ายเกมทุกเกม
--   นักเรียนแต่งเรื่องจาก 8 คำที่สุ่มจากคำในเกม → บันทึกให้ครูอ่านย้อนหลังได้
--   ใช้ร่วมทั้ง 5 เกม (memory/balloon/bubble/hangman/training) ผ่าน /api/story/submit
--
-- Idempotent: CREATE TABLE IF NOT EXISTS — รันซ้ำได้ปลอดภัย ไม่แตะข้อมูลเดิม
-- Run: "D:/laragon/bin/mysql/mysql-8.0.30-winx64/bin/mysql.exe" -u root ssraexhi_hittest < database/migrations/007_game_stories.sql
-- ─────────────────────────────────────────────────────────────────────────────
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS game_stories (
  id          BIGINT AUTO_INCREMENT PRIMARY KEY,
  sc_id       VARCHAR(15)  NOT NULL,
  stuid       VARCHAR(50)  NOT NULL,
  stuname     VARCHAR(255) NULL,
  class_id    INT          NOT NULL DEFAULT 1,
  game        VARCHAR(30)  NOT NULL,            -- เกมต้นทาง: memory/balloon/bubble/hangman/training
  grade       INT          NOT NULL DEFAULT 1,
  difficulty  INT          NOT NULL DEFAULT 1,
  words_json  JSON         NULL,                -- 8 คำที่ให้แต่งเรื่อง
  story_text  TEXT         NOT NULL,            -- เรื่องที่นักเรียนแต่ง
  char_count  INT          NOT NULL DEFAULT 0,
  used_all    TINYINT(1)   NOT NULL DEFAULT 0,  -- ใช้คำครบทุกคำหรือไม่
  stats_json  JSON         NULL,                -- สถิติเกมตอนจบ (เช่น score/accuracy)
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_stu  (stuid, created_at),
  KEY idx_sc   (sc_id, created_at),
  KEY idx_game (game, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
