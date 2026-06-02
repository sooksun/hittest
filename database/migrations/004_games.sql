-- ─────────────────────────────────────────────────────────────────────────────
-- 004_games.sql  –  Tables for the 5 embedded reading-practice games
-- ─────────────────────────────────────────────────────────────────────────────

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
