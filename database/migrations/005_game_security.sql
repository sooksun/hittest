-- ─────────────────────────────────────────────────────────────────────────────
-- 005_game_security.sql  –  Columns needed for security & idempotency fixes
-- Run once after 004_games.sql is applied.
-- ─────────────────────────────────────────────────────────────────────────────

-- Track which characters the player has already guessed (prevents re-scoring)
ALTER TABLE game_hangman_sessions
    ADD COLUMN guessed_chars JSON NULL
        COMMENT 'NFC-normalised guess strings already processed; prevents re-scoring duplicates';

-- Track which task IDs in a training session have already been submitted
ALTER TABLE game_training_sessions
    ADD COLUMN submitted_task_ids JSON NULL
        COMMENT 'UUID strings of tasks already submitted; prevents double-score on retry';
