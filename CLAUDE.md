# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

**HIT-TEST** (`newhittest`) — a Thai reading-assessment web app for primary grades ป.1–ป.6.
Teachers run a 20-word reading test in three rounds (Hit-1 / Hit-2 / Hit-3), and students
practice with five embedded reading games. Multi-tenant: every school is isolated by `sc_id`.

Plain **PHP 8.1 + MySQL 8**, no framework, served by **Laragon (Apache)** on Windows.
Composer is used only for PhpSpreadsheet (Excel import/export). The global stack defaults
(Next.js/NestJS/Prisma) in `~/.claude/CLAUDE.md` do **not** apply here — this is server-rendered PHP.

## Commands

```bash
# Run the app — Laragon serves it; no build/start step. Open:
#   http://localhost/newhittest/
# Login (teacher/admin): school SMIS code as BOTH user and password, e.g. 57030129 / 57030129

# MySQL CLI used throughout (absolute path on this machine):
MYSQL="D:/laragon/bin/mysql/mysql-8.0.30-winx64/bin/mysql.exe"

# First-time DB load (DB = ssraexhi_hittest @ 127.0.0.1, root, empty password):
"$MYSQL" -uroot -e "CREATE DATABASE IF NOT EXISTS ssraexhi_hittest CHARACTER SET utf8mb4;"
"$MYSQL" -uroot --default-character-set=utf8mb4 ssraexhi_hittest < database/ssraexhi_hittest.sql
"$MYSQL" -uroot --default-character-set=utf8mb4 ssraexhi_hittest < database/seed_students.sql

# Migrations — one idempotent file applies every feature table (safe to re-run):
"$MYSQL" -uroot ssraexhi_hittest < database/migrations/setup_all_features.sql
# Or numbered files in order (001..010). Games need 004 → 005 → 006; story needs 007 (game_stories);
# media generation needs 009; students year-scoping needs 010 (+ the rollover script below).

# Media generation (audio Botnoi + image ComfyUI) — see "Media generation" below:
php scripts/migrate_media_from_readthai.php --apply   # one-time: import existing media into newhittest
php scripts/process_media_jobs.php [--type=audio|image] [--limit=N]   # drain the generation queue (cron)

# Students academic-year rollover (after migration 010) — see "Academic-year-scoped roster" below:
php scripts/rollover_students_year.php --apply        # one-time: create the current-year (2569) roster from 2568

# Tests — custom harness, NO PHPUnit. Needs a separate MySQL DB `newhittest_test`
# and the PDO-MySQL + intl (Normalizer) PHP extensions. It (re)creates its own tables.
"$MYSQL" -uroot -e "CREATE DATABASE IF NOT EXISTS newhittest_test CHARACTER SET utf8mb4;"
php tests/run_tests.php                 # run all suites (Security, Integration, Retention)
php tests/SecurityTest.php              # run a single suite directly (each file bootstraps itself)

# Maintenance — prune old audit rows (cron/Task Scheduler; default keeps AUDIT_RETENTION_DAYS=90):
php scripts/prune_audit_logs.php [days]
```

There is **no JS source in this repo.** The five games are built externally (the
"kingdom-of-words" project at `../readthai/kingdom-of-words`) and only the Vite `dist` is
committed under `assets/games/`. The root `package.json` here has **no dependencies** — it is a
delegator so `pnpm run build:games` works from this directory too (forwards to
`../readthai/kingdom-of-words/apps/web`); its side-effects (`node_modules/`, `pnpm-lock.yaml`)
are gitignored. Any other pnpm command belongs in the kingdom-of-words repo.

## Architecture

### Flat page model
No router/framework. Each user-facing page is a top-level `*.php` script that:
1. `require`s `includes/auth.php` (teacher/admin) **or** calls `require_student()` from
   `includes/student_auth.php` (student) as its first act — this is the auth gate.
2. Renders via `includes/header.php` + `includes/footer.php`.
Config and DB access are centralized: **all constants live in `config/config.php`**
(DB creds, `ACADEMIC_YEAR=2569` พ.ศ., `WORDS_PER_SET=20`, exam minutes, rate limits, status map).
DB handles come from `includes/db.php`: `db()` → PDO (primary, exceptions + assoc + no emulated
prepares) and `dbm()` → mysqli (legacy, used by `teacher_page.php`).

### Two mutually-exclusive auth roles (most important invariant)
A session is **either** teacher/admin **or** student, never both:

| Role | Session shape | Gate | Login |
|------|---------------|------|-------|
| Teacher/admin | `$_SESSION['sc_id']` (+ `sc_smis`, `sc_name`) | `includes/auth.php` (rejects students with 403) | `login.php` — SMIS code, user = pass |
| Student | `$_SESSION['role']='student']` + `$_SESSION['stu']` (`stuid,sc_id,stuname,class_id,rooms`) | `require_student()` in `includes/student_auth.php` | `student_login.php` — by `stuid`, or 6-digit PIN with throttle/backoff |

**User accounts + roles (migration 011).** Besides SMIS-as-password, `login.php` first checks the
`users` table (`username` + bcrypt `password_hash`, `is_active`) via `user_authenticate()`, then falls back
to the SMIS login (unchanged). On match it sets the usual `sc_id`/`sc_smis`/`sc_name` session **plus**
`$_SESSION['user_id'|'user_role'|'user_name'|'area_code']`. Three roles (admin hierarchy):
- `superadmin` — system-wide; `is_admin()` true → system tools.
- `saoadmin` — one สพป **area** (`area_code` = first 4 digits of SMIS); sees every school whose `sc_smis`
  starts with `area_code`, but **read-only** — `require_editor()` (called right after `auth.php` in every
  mutating endpoint: `save_results`, `cancel_result`, `reset_results`, `paper_import`, `student_update`,
  `students_import`, `promote_school`, `promote_rollback`, `exam_window_toggle`) blocks it.
- `school` — one `sc_id` (like an SMIS login).

`superadmin`/`saoadmin` change the **viewed** school via `school_switch.php` (scope-checked: superadmin =
any, saoadmin = within `area_code`); a saoadmin auto-lands on the first school in its area at login. Area-admin
accounts are migrated from legacy `master_saonew` via `scripts/migrate_area_admins.php` (username = areacode,
`area_code` = first 4, password → hash, only areas that have schools). School-level accounts can be
bulk-created from `schools` via `scripts/migrate_school_users.php` (`--area=`/`--active`/`--all`; username =
SMIS, password = SMIS, skips admin SMIS) — optional, since schools also log in via the SMIS fallback. Area
reference = `sao_new` (its `areacode` = `<area_code>0000` for a สพป). Admin-only CRUD UI: `admin_users.php`.

`student_session_set()` wipes `$_SESSION` and regenerates the id, so the two never mix.
Root `index.php` redirects by role: student → `my_dashboard.php`, teacher → `menu.php`, guest → `landing.php`.

### Multi-tenancy by `sc_id`
Every data path scopes to the current school. `find_student()` filters by `current_sc_id()`;
dashboards, PIN management, and all game handlers join on `sc_id`. Treat cross-school access as a bug.

### Exam flow → 4-table write
`students_list.php` → `teacher_page.php` (teacher controls + `assets/js/exam.js`, 20 words, 5-min timer)
with an optional synced `student_page.php` (student screen, `assets/js/student.js` via localStorage) →
`save_results.php`. The actual persistence is **`save_hit_result()` in `includes/functions.php`**, which
writes four tables idempotently (`ON DUPLICATE KEY UPDATE`): `evaluations` (per-word), `studenteval`
(per-round score), `students` (`hit{N}`, `hit{N}tested` flags), `studenthit` (denormalized item1..item20).
`cancel_result.php` reverses one round. The **paper-based** path (`paper.php`, `paper_import.php`,
`paper_export.php`) reuses the same `save_hit_result()` via PhpSpreadsheet Excel I/O.
Exam rounds can be opened/closed per school via `exam_control.php` → `exam_window` table
(checked by `exam_is_open()` / `hit_cell()`).

### Academic-year-scoped roster (migration 010 — preserve these properties)
`students` is **one row per student per academic year** (PK `(stuid, years)`); `studenthit` likewise
(`(stuid, years, hit)`). The app operates on a single **current year** = `current_year()` (returns
`ACADEMIC_YEAR`) — **every** read/write of `students`/`studenthit` filters `years = current_year()`
(see `find_student()`, `students_list.php`, `includes/dashboard.php`, `save_results.php`, etc.). The
one deliberate exception is the cross-school owner check in `includes/students_import_lib.php`, which
reads **all years'** rows of each `stuid`: ownership = the current-year row if it exists, else the
latest year. Excel import (`students_import.php` → `students_import_run()`) is **all-or-nothing** —
duplicate `stuid` within the file, a `stuid` owned by another school, or an invalid row aborts the
whole file with per-row errors (nothing written; ownership re-checked `FOR UPDATE` inside the write
transaction). The `รหัสนักเรียน` column is **optional**: a blank cell makes the system assign a
unique id in the legacy synthetic format — `"9" + 9 running digits` (e.g. `9000000123`), continuing
from `MAX` of the existing `9`-series (`stuid LIKE '9%' AND CHAR_LENGTH=10`), so it never collides
with a real 13-digit national id (those start 0-8). Generation is serialized with
`GET_LOCK('newhittest_stuid_gen')` and skips any `9`-series id typed manually in the same file; the
PK `(stuid, years)` is the final guard. The result returns a `generated` list (stuname→assigned
stuid) so the school can give students their login id. Admins (`is_admin()`) are exempt from the
same-school rule: importing another school's `stuid` "รับย้าย"s the student by moving only the
current-year row's `sc_id` to the viewed school (old years stay with the old school as history;
reported in the result + `moved` list).
**Promotion creates next-year rows** rather than
mutating in place: `includes/promote_lib.php` `promote_school_year()` inserts `toYear` rows at `class+1`
with scores reset (ป.6 graduates get no new row); the old year stays as immutable history.
`promote_rollback_year()` undoes it by **deleting** the new-year rows (guarded — won't delete a student
who already has `toYear` scores). `promote_school.php` / `admin_promote_all.php` / the one-time
`scripts/rollover_students_year.php` all go through that library. Cross-year dashboards
(`dash_year_trend`, `dash_student_trend`) join `studenteval` to the **current-year** `students` row
(`AND s.years = current_year()`) so a student with rows in two years isn't double-counted.

### Games subsystem
PHP wrappers `game_memory.php` / `game_balloon.php` / `game_bubble.php` / `game_hangman.php` /
`game_training.php` each call `game_asset_tags('<entry>')` (`includes/game_assets.php`), which reads
`assets/games/.vite/manifest.json` and emits the hashed `<link>`/`<script>` tags for that entry
(`src/entries/{game}.tsx`). Student-facing history: `my_game_history.php`.
Per-word **audio/image are owned in-app**: handlers read `sound_path`/`image_path` straight from the
local `wordstest` (no `readthai.*` cross-DB join), and files live in the real `media/` dir (gitignored).
See "Media generation" below.

### Game API (`api/`)
`api/.htaccess` rewrites: real files (e.g. `api/tts.php`) are served directly; everything else →
`api/index.php`, a **front controller**. It auth-gates (student or teacher — teachers get a synthetic
`stuid='preview'` identity), then sets `$me` (identity), `$pdo`, `$method`, `$parts` and `require`s a
handler from `api/handlers/`. **Handlers are included into that scope** — they are bare scripts that
read `$me`/`$pdo` and call `json_response()`; dynamic routes also inject `$taskId` / `$sessionId`.
Every request is wrapped by `audit_request_begin()` (a shutdown hook that writes one `audit_logs` row).

### Game-API hardening model (recent work — preserve these properties)
Handlers that mutate server state follow a strict pattern (see `api/handlers/hangman_guess.php`):
- **Ownership**: load the session row `WHERE id=? AND stuid=? AND sc_id=?` with `FOR UPDATE` inside a
  transaction; a miss is audited as `ownership_denied` and returns 404.
- **Idempotency**: replays cannot re-score. Each guess/task is claimed via a UNIQUE key
  (`game_hangman_guesses(session_id,guess_char)`, `game_task_submissions(session_id,task_uuid)`);
  a duplicate (`ER_DUP_ENTRY` 1062) returns the original result instead of scoring again.
- **Server-side scoring**: scores are computed on the server. Client-reported numbers are clamped and
  mismatches audited (e.g. `balloon_submit.php` clamps score to 0–9999, logs `score_clamped`).
- **Audit**: `audit_log_event()` is best-effort and must **never throw** (it swallows all errors).
- **Thai text**: normalize to NFC with `Normalizer::normalize($s, Normalizer::FORM_C)` before comparing
  or storing; `guess_char` is `utf8mb4_bin` so tone/vowel variants stay distinct.

### TTS (Botnoi Voice)
`tts.php` (form pages) and `api/tts.php` (game bundles) proxy the Botnoi API, backed by a `tts_cache`
table keyed on `sha1(text|speaker|speed)`. Both now call the shared helper `includes/botnoi_tts.php`
(`botnoi_request_audio_url()`) so the HTTP/parse logic lives in one place. Real Botnoi calls (cache
misses) are rate-limited per user (`TTS_RATE_MAX` / `TTS_RATE_WINDOW_SEC`, counted from `audit_logs`).
The token lives in the **gitignored** `config/botnoi.php` (`BOTNOI_TOKEN`); absence yields a 500, not a crash.

### Media generation (audio + images) — ported from readthai, owned in-app
Admin-only subsystem (gated by `includes/admin_auth.php` → `ADMIN_SMIS`) that **generates** per-word
audio (Botnoi) and illustrations (ComfyUI), so newhittest no longer borrows readthai's DB/media.
- **Storage**: `media/` is a real dir (was a symlink to readthai). `includes/media_storage.php` builds
  `media/audio/wordstest/{id}/{hash}.mp3` and `media/image/wordstest/{id}/{hash}_original.png`; paths
  stored in `wordstest.sound_path` / `image_path` (TEXT JSON `{image_original:…}`). Games consume only
  the original image. `media/` is gitignored (environment-local); run `migrate_media_from_readthai.php`
  per environment to populate it.
- **Pipeline** (`includes/media_pipeline.php`, ports readthai's tts/image workers): audio = Botnoi POST
  → download mp3 → save → `sound_path`. Image = (override / LLM / admin text) → `pt_build_comfyui_prompt`
  → ComfyUI `/prompt`→poll `/history`→`/view` → save PNG → `image_path` + `image_status` + a
  `game_prompt_history` row. Every external step degrades gracefully (returns a status, never throws).
- **Prompt rules** (`includes/prompt_template.php`): the LLM **always** produces a drawable scene (no
  more AMBIGUOUS) — concrete nouns → closeup single subject, animals → single-animal style, and
  **abstract/concept words → a contextual scene** that conveys meaning (pointing hand, size comparison,
  highlight circle, sequence) e.g. "ราคา" = goods with price tags + a hand pointing, "โต" = three kittens
  small→big with a circle on the biggest. Words a single scene can't convey (time sequence, change,
  multiple examples — e.g. "ฤดู", "เวลา", "สะอาด") may be designed as **one image split into 2–3 large
  side-by-side panels** (`PT_MULTIPANEL_STYLE`, its own negative that does NOT ban busy scenes/multiple
  characters). Never say "comic strip"/"storyboard" in the style — SDXL then draws an 8–12 panel comic
  page with gibberish speech bubbles; the negative bans comic page/dense grid/speech balloons instead.
  Style/negative are routed by the **word** (passing `''` for the scene desc) — routing by the description
  would misclassify contextual scenes that mention objects (e.g. "fruit") as concrete. The one exception
  is `pt_is_multipanel()`, which detects panel keywords (panel / ภาพแบ่ง...ช่อง) in the **description** —
  layout markers, not subjects, so no misclassification risk. `config/word_overrides.json` bypasses the LLM.
- **LLM** (`includes/llm.php`, configurable `LLM_PROVIDER` openai|ollama|none): only the Thai→English
  "what to draw" step. With `none`, image gen still works for override/admin-supplied words.
- **Queue** (no Redis): `game_media_jobs` table + `includes/media_jobs.php` (`media_job_enqueue` is
  idempotent — no duplicate active job; `media_job_claim` uses `FOR UPDATE SKIP LOCKED`). Drained by
  the CLI worker `scripts/process_media_jobs.php` (cron/Task Scheduler). The admin page also runs the
  pipeline **synchronously** for single "generate now" actions.
- **UI**: `admin_media.php` (dashboard: filter, per-word generate, edit-prompt-&-regenerate, jobs panel)
  → `admin_media_action.php` (JSON backend, audited). ComfyUI host/checkpoint + LLM keys live in the
  **gitignored** `config/media_gen.php` (template: `config/media_gen.sample.php`).

## Conventions

- **Test isolation**: tests never call `db()`; they use `connect_test_db()` against `newhittest_test`.
  `json_response()` is wrapped in `function_exists()` so `tests/bootstrap.php` can override it to throw
  `JsonResponseException`, and `MockPhpStream` feeds a mocked `php://input`. When adding a handler,
  keep it includable (no `exit` before `json_response`, no top-level `<?php` assumptions) so the
  harness can drive it.
- **Thai / Buddhist Era**: UI is Thai; years are stored and shown as พ.ศ. (`ACADEMIC_YEAR`).
- **No-store on data pages**: `auth.php` sends `Cache-Control: no-store` to avoid stale bfcache after a save.
- **Gitignored secrets/PII**: `config/botnoi.php`, `config/media_gen.php`, `database/*.sql`, `uploads/*`,
  `media/` (generated audio/images — large, per-environment), `docsref/students/` (student CSVs). Don't commit these.

## Reference

- `README.md` — quick start, original refactor rationale (vs. the older `hittest`).
- `docs/dashboard-design.md` — dashboard/analytics design (Phase 1–2 are code-only, no schema change).
- `database/migrations/README.md` — per-phase migration status and run instructions.
