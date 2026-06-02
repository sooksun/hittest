-- ============================================================================
-- Migration 006 — Phase 2 hardening
--   • game_hangman_guesses     : normalized per-guess rows, UNIQUE(session_id, guess_char)
--   • game_task_submissions    : normalized per-task rows,  UNIQUE(session_id, task_uuid)
--   • audit_logs               : security/request audit trail
--   • drops the transient JSON columns added in 005 (guessed_chars, submitted_task_ids)
--
-- Idempotent: re-running is safe. Down script: 006_phase2_hardening_rollback.sql
-- Run via phpMyAdmin: select ssraexhi_hittest → SQL tab → paste whole file → Go
--
-- NOTE: `char` is a reserved word in MySQL, so the column is named `guess_char`.
--       The UNIQUE(session_id, guess_char) key enforces the intended one-row-per
--       (session, character) guarantee from the spec.
-- ============================================================================

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
