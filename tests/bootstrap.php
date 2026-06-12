<?php
/**
 * tests/bootstrap.php — lightweight test harness (no external dependencies)
 *
 * Run:  php tests/run_tests.php
 *
 * Uses an isolated MySQL test database (newhittest_test); tables are recreated
 * fresh on every create_test_db() call so the production DB is never touched.
 *
 * Requirements: PHP 8.0+, PDO MySQL + intl (Normalizer) extensions.
 */

error_reporting(E_ALL & ~E_DEPRECATED);

define('TEST_MODE', true);
// NOTE: HITTESTS / WORDS_PER_SET / STU_STATUS are defined by config/config.php
// (pulled in via functions.php below) — do NOT redefine them here or PHP fatals
// on a duplicate constant. No test ever calls db(), so the production DB_NAME in
// config.php is never connected; handler tests use connect_test_db() instead.

const TEST_DSN  = 'mysql:host=127.0.0.1;dbname=newhittest_test;charset=utf8mb4';
const TEST_USER = 'root';
const TEST_PASS = '';

// Media file ops in tests target a throwaway temp dir (never the real /media/).
// Defined before includes/media_storage.php loads so it wins the define() guard.
if (!defined('MEDIA_ROOT')) {
    define('MEDIA_ROOT', sys_get_temp_dir() . '/newhittest_test_media');
}

// ── json_response override ──────────────────────────────────────────────────
// Defined BEFORE functions.php (whose definition is wrapped in function_exists)
// so handlers can be included without calling exit().

class JsonResponseException extends RuntimeException
{
    public function __construct(
        public readonly array $data,
        public readonly int   $httpCode
    ) {
        parent::__construct(json_encode($data));
    }
}

function json_response(array $data, int $code = 200): void
{
    throw new JsonResponseException($data, $code);
}

// Pull in the real audit_log_event() / helpers (functions.php skips json_response
// because we already defined it). functions.php requires db.php → config.php,
// which may connect to the real DB lazily; we never call db() in handler tests.
require_once dirname(__DIR__) . '/includes/functions.php';

// ── MySQL test DB ───────────────────────────────────────────────────────────

function connect_test_db(): PDO
{
    return new PDO(TEST_DSN, TEST_USER, TEST_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,   // match production db()
    ]);
}

/** Fresh connection AND a clean schema (mirrors 004 + 006 final state). */
function create_test_db(): PDO
{
    $pdo = connect_test_db();

    // Children first (FKs), then parents, then independent tables
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach ([
        'game_hangman_guesses', 'game_task_submissions', 'audit_logs',
        'game_results', 'game_hangman_sessions', 'game_training_sessions',
        'game_stories', 'game_media_jobs', 'game_prompt_history', 'wordstest',
        'students', 'studenthit', 'promote_log', 'promote_log_item', 'users',
    ] as $t) {
        $pdo->exec("DROP TABLE IF EXISTS {$t}");
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

    $pdo->exec("
        CREATE TABLE game_results (
            id         BIGINT AUTO_INCREMENT PRIMARY KEY,
            sc_id      VARCHAR(15) NOT NULL,
            stuid      VARCHAR(50) NOT NULL,
            stuname    VARCHAR(255),
            class_id   INT NOT NULL DEFAULT 1,
            game       VARCHAR(30) NOT NULL,
            grade      INT NOT NULL DEFAULT 1,
            difficulty INT NOT NULL DEFAULT 1,
            score      INT NOT NULL DEFAULT 0,
            stats_json JSON,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_stu (stuid, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE game_hangman_sessions (
            id              INT AUTO_INCREMENT PRIMARY KEY,
            sc_id           VARCHAR(15),
            stuid           VARCHAR(50),
            grade_level     INT NOT NULL,
            difficulty      INT NOT NULL,
            word_id         INT NOT NULL,
            word            VARCHAR(255) NOT NULL,
            masked_word     JSON NOT NULL,
            remaining_lives INT NOT NULL DEFAULT 5,
            score           INT NOT NULL DEFAULT 0,
            completed       TINYINT(1) NOT NULL DEFAULT 0,
            started_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            ended_at        DATETIME,
            KEY idx_stu (stuid, started_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE game_training_sessions (
            id            VARCHAR(36) PRIMARY KEY,
            sc_id         VARCHAR(15) NOT NULL,
            stuid         VARCHAR(50) NOT NULL,
            grade_level   INT NOT NULL,
            difficulty    INT NOT NULL,
            tasks_json    JSON NOT NULL,
            total_tasks   INT NOT NULL,
            correct_count INT NOT NULL DEFAULT 0,
            total_score   INT NOT NULL DEFAULT 0,
            started_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            ended_at      DATETIME,
            KEY idx_stu (stuid, started_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE game_hangman_guesses (
            id          BIGINT AUTO_INCREMENT PRIMARY KEY,
            session_id  INT NOT NULL,
            guess_char  VARCHAR(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
            guess_type  VARCHAR(16),
            is_correct  TINYINT(1) NOT NULL DEFAULT 0,
            created_at  DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
            UNIQUE KEY uniq_session_char (session_id, guess_char),
            KEY idx_session (session_id),
            CONSTRAINT fk_guess_session FOREIGN KEY (session_id)
                REFERENCES game_hangman_sessions (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE game_task_submissions (
            id           BIGINT AUTO_INCREMENT PRIMARY KEY,
            session_id   VARCHAR(36) NOT NULL,
            task_uuid    VARCHAR(36) NOT NULL,
            is_correct   TINYINT(1) NOT NULL DEFAULT 0,
            score        INT NOT NULL DEFAULT 0,
            base_score   INT NOT NULL DEFAULT 0,
            speed_bonus  INT NOT NULL DEFAULT 0,
            elapsed_ms   INT NOT NULL DEFAULT 0,
            submitted_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
            UNIQUE KEY uniq_session_task (session_id, task_uuid),
            KEY idx_session (session_id),
            CONSTRAINT fk_submission_session FOREIGN KEY (session_id)
                REFERENCES game_training_sessions (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE audit_logs (
            id          BIGINT AUTO_INCREMENT PRIMARY KEY,
            created_at  DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
            sc_id       VARCHAR(15),
            stuid       VARCHAR(50),
            role        VARCHAR(20),
            method      VARCHAR(10),
            path        VARCHAR(255),
            action      VARCHAR(50),
            entity_type VARCHAR(30),
            entity_id   VARCHAR(64),
            status_code INT,
            ip          VARCHAR(45),
            user_agent  VARCHAR(255),
            duration_ms INT,
            meta_json   JSON,
            KEY idx_action (action, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE game_stories (
            id          BIGINT AUTO_INCREMENT PRIMARY KEY,
            sc_id       VARCHAR(15) NOT NULL,
            stuid       VARCHAR(50) NOT NULL,
            stuname     VARCHAR(255),
            class_id    INT NOT NULL DEFAULT 1,
            game        VARCHAR(30) NOT NULL,
            grade       INT NOT NULL DEFAULT 1,
            difficulty  INT NOT NULL DEFAULT 1,
            words_json  JSON,
            story_text  TEXT NOT NULL,
            char_count  INT NOT NULL DEFAULT 0,
            used_all    TINYINT(1) NOT NULL DEFAULT 0,
            stats_json  JSON,
            created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_stu (stuid, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // ── media generation (migration 009) ──────────────────────────────────────
    $pdo->exec("
        CREATE TABLE wordstest (
            id                 INT AUTO_INCREMENT PRIMARY KEY,
            word               VARCHAR(255),
            spoken_form        VARCHAR(255),
            class_id           INT,
            level              INT,
            sound_path         VARCHAR(255),
            sound_generated_at DATETIME NULL,
            what_to_draw       TEXT NULL,
            image_status       ENUM('EMPTY','QUEUED','GENERATING','DONE','FAILED','AMBIGUOUS') NOT NULL DEFAULT 'EMPTY',
            image_reason       VARCHAR(255) NULL,
            image_generated_at DATETIME NULL,
            image_path         TEXT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        CREATE TABLE game_media_jobs (
            id           BIGINT AUTO_INCREMENT PRIMARY KEY,
            type         ENUM('audio','image') NOT NULL,
            word_id      INT NOT NULL,
            status       ENUM('queued','processing','done','failed') NOT NULL DEFAULT 'queued',
            attempts     INT NOT NULL DEFAULT 0,
            forced       TINYINT(1) NOT NULL DEFAULT 0,
            last_error   TEXT NULL,
            meta_json    JSON NULL,
            requested_by VARCHAR(64) NULL,
            created_at   DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
            updated_at   DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
            KEY idx_status_type (status, type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        CREATE TABLE game_prompt_history (
            id              INT AUTO_INCREMENT PRIMARY KEY,
            word_id         INT NOT NULL,
            word            VARCHAR(255) NOT NULL,
            positive_prompt TEXT NOT NULL,
            negative_prompt TEXT NOT NULL,
            what_to_draw_th TEXT NULL,
            what_to_draw_en TEXT NULL,
            checkpoint_name VARCHAR(255) NULL,
            seed            BIGINT NULL,
            preview_image   TEXT NULL,
            status          VARCHAR(50) NOT NULL DEFAULT 'generated',
            is_active       TINYINT(1) NOT NULL DEFAULT 1,
            notes           TEXT NULL,
            created_at      DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
            updated_at      DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
            KEY idx_word_active (word_id, is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // ── students roster ผูกปี (migration 010) — PK (stuid, years) ──────────────
    $pdo->exec("
        CREATE TABLE students (
            stuid       VARCHAR(50)  NOT NULL,
            stuname     VARCHAR(255),
            sc_id       VARCHAR(15),
            studentId   VARCHAR(255),
            genderName  VARCHAR(255),
            class_id    INT,
            years       INT          NOT NULL DEFAULT 2569,
            rooms       INT,
            stustatus   INT          NOT NULL DEFAULT 1,
            hit1 INT NOT NULL DEFAULT 0, hit1tested INT NOT NULL DEFAULT 0,
            hit2 INT NOT NULL DEFAULT 0, hit2tested INT NOT NULL DEFAULT 0,
            hit3 INT NOT NULL DEFAULT 0, hit3tested INT NOT NULL DEFAULT 0,
            sethit1 INT DEFAULT 1, sethit2 INT DEFAULT 1, sethit3 INT DEFAULT 1,
            updatedDate TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (stuid, years)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        CREATE TABLE studenthit (
            stuid     VARCHAR(50) NOT NULL,
            hit       INT NOT NULL DEFAULT 1,
            years     INT NOT NULL DEFAULT 2569,
            stuname   VARCHAR(255), sc_id VARCHAR(15), class_id INT, rooms INT,
            stustatus INT NOT NULL DEFAULT 1, hitscore INT NOT NULL DEFAULT 0, sethit INT,
            item1 INT,item2 INT,item3 INT,item4 INT,item5 INT,item6 INT,item7 INT,item8 INT,item9 INT,item10 INT,
            item11 INT,item12 INT,item13 INT,item14 INT,item15 INT,item16 INT,item17 INT,item18 INT,item19 INT,item20 INT,
            updatedDate DATETIME,
            PRIMARY KEY (stuid, years, hit)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        CREATE TABLE promote_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sc_id VARCHAR(15) NOT NULL, sc_smis VARCHAR(8), sc_name VARCHAR(255),
            promoted INT DEFAULT 0, graduated INT DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, rolled_back_at DATETIME NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        CREATE TABLE promote_log_item (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            log_id INT NOT NULL, stuid VARCHAR(50) NOT NULL, years INT NULL,
            prev_class_id INT, prev_stustatus INT, action VARCHAR(10) NOT NULL,
            KEY idx_log (log_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    // ── users (migration 011) — บัญชีผู้ใช้จริง + role ─────────────────────────
    $pdo->exec("
        CREATE TABLE users (
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
            UNIQUE KEY uq_username (username)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    return $pdo;
}

// ── Handler driver (drives the REAL handler files) ──────────────────────────

/**
 * php://input replacement so handlers can read a mocked request body.
 */
class MockPhpStream
{
    public static string $inputContent = '{}';
    private int $pos = 0;

    public function stream_open(string $path, string $mode, int $options, ?string &$opened_path): bool
    {
        $this->pos = 0;
        return true;
    }
    public function stream_read(int $count): string
    {
        $chunk = substr(self::$inputContent, $this->pos, $count);
        $this->pos += strlen($chunk);
        return $chunk;
    }
    public function stream_write(string $data): int { return strlen($data); }
    public function stream_eof(): bool { return $this->pos >= strlen(self::$inputContent); }
    public function stream_stat(): array { return []; }
    public function stream_seek(int $offset, int $whence): bool { return false; }
    public function stream_tell(): int { return $this->pos; }
}

/**
 * Execute a real handler file with injected scope + mocked php://input.
 * $scope keys (pdo, me, taskId, sessionId, ...) become local variables visible
 * to the required handler. Returns the JsonResponseException it raised.
 */
function call_handler(string $handlerFile, array $scope, string $inputJson = '{}'): JsonResponseException
{
    MockPhpStream::$inputContent = $inputJson;
    stream_wrapper_unregister('php');
    stream_wrapper_register('php', 'MockPhpStream');

    extract($scope, EXTR_OVERWRITE);

    try {
        require $handlerFile;
        throw new RuntimeException('Handler did not call json_response()');
    } catch (JsonResponseException $e) {
        return $e;
    } finally {
        stream_wrapper_restore('php');
        // Safety: never leak an open transaction into the next test
        if (isset($scope['pdo']) && $scope['pdo'] instanceof PDO && $scope['pdo']->inTransaction()) {
            $scope['pdo']->rollBack();
        }
    }
}

// ── Assertion helpers ───────────────────────────────────────────────────────

$GLOBALS['_test_passed']   = 0;
$GLOBALS['_test_failed']   = 0;
$GLOBALS['_test_failures'] = [];

function ok(bool $condition, string $label): void
{
    if ($condition) {
        $GLOBALS['_test_passed']++;
        echo "  ✓ {$label}\n";
    } else {
        $GLOBALS['_test_failed']++;
        $GLOBALS['_test_failures'][] = $label;
        echo "  ✗ {$label}\n";
    }
}

function run_test(string $name, callable $fn): void
{
    echo "\n{$name}\n";
    try {
        $fn();
    } catch (Throwable $e) {
        $GLOBALS['_test_failed']++;
        $GLOBALS['_test_failures'][] = $name . ': ' . $e->getMessage();
        echo "  ✗ EXCEPTION: {$e->getMessage()}\n";
    }
}
