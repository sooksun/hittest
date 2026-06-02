-- ============================================================================
-- Rollback for Migration 006 — Phase 2 hardening
-- Restores the 005 JSON columns and drops the normalized tables.
--
-- WARNING: dropping the tables discards all per-guess / per-submission /
--          audit history. Take a backup first if those rows matter.
-- Idempotent: re-running is safe.
-- ============================================================================

-- ── 1. Drop the normalized tables (children first; FKs cascade) ───────────────
DROP TABLE IF EXISTS game_hangman_guesses;
DROP TABLE IF EXISTS game_task_submissions;
DROP TABLE IF EXISTS audit_logs;

-- ── 2. Restore the 005 JSON columns (idempotent add) ──────────────────────────
SET @has_gc := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'game_hangman_sessions' AND COLUMN_NAME = 'guessed_chars');
SET @ddl := IF(@has_gc = 0,
  'ALTER TABLE game_hangman_sessions ADD COLUMN guessed_chars JSON NULL',
  'DO 0');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

SET @has_st := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'game_training_sessions' AND COLUMN_NAME = 'submitted_task_ids');
SET @ddl := IF(@has_st = 0,
  'ALTER TABLE game_training_sessions ADD COLUMN submitted_task_ids JSON NULL',
  'DO 0');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;
