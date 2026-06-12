-- ─────────────────────────────────────────────────────────────────────────────
-- 010_students_year.sql  –  ทำให้ตาราง students "ผูกกับปีการศึกษา" (พ.ศ.)
--   เดิม students มี PK = stuid (1 แถว/คน, ไม่มีปี) → เปลี่ยนเป็น 1 แถว/คน/ปี
--   - students     : เพิ่ม years, ข้อมูลเดิม → 2568, PK เป็น (stuid, years)
--   - studenthit   : เพิ่ม years, ข้อมูลเดิม → 2568, PK เป็น (stuid, years, hit)
--                    (ไม่งั้น Hit-1 ปี 2568 กับ 2569 ของคนเดียวกันจะชนกัน)
--   - promote_log_item : เพิ่ม years (ปีใหม่ที่แถวถูกสร้าง — ใช้ rollback แบบใหม่)
--
--   ปีปัจจุบัน = ACADEMIC_YEAR (config/config.php = 2569). หลังรัน migration นี้
--   ต้องรัน scripts/rollover_students_year.php --apply เพื่อสร้าง roster ปี 2569
--   (เลื่อนชั้นจาก 2568) ไม่งั้นแอป (ที่กรอง years=2569) จะไม่เห็นนักเรียน
--
-- Idempotent: ตรวจ information_schema ก่อนทุก ALTER — รันซ้ำได้
-- Run: "D:/laragon/bin/mysql/mysql-8.0.30-winx64/bin/mysql.exe" -uroot ssraexhi_hittest < database/migrations/010_students_year.sql
-- ⚠️ สำรองก่อน (ALTER PK) — ดู admin_backup.php
-- ─────────────────────────────────────────────────────────────────────────────
SET NAMES utf8mb4;

-- ══ students ════════════════════════════════════════════════════════════════
-- 1) เพิ่มคอลัมน์ years (nullable ก่อน เพื่อ backfill)
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'students' AND COLUMN_NAME = 'years');
SET @ddl := IF(@c = 0, 'ALTER TABLE students ADD COLUMN years INT NULL AFTER class_id', 'DO 0');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

-- 2) ข้อมูลเดิม (years ยังว่าง) → 2568
UPDATE students SET years = 2568 WHERE years IS NULL;

-- 3) บังคับ NOT NULL + ค่า default ปีปัจจุบัน (2569) สำหรับแถวที่สร้างใหม่ภายหลัง
SET @t := (SELECT IS_NULLABLE FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'students' AND COLUMN_NAME = 'years');
SET @ddl := IF(@t = 'YES', 'ALTER TABLE students MODIFY years INT NOT NULL DEFAULT 2569', 'DO 0');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

-- 4) เปลี่ยน PK : stuid → (stuid, years)
SET @pk := (SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'students' AND INDEX_NAME = 'PRIMARY');
SET @ddl := IF(@pk = 'stuid', 'ALTER TABLE students DROP PRIMARY KEY, ADD PRIMARY KEY (stuid, years)', 'DO 0');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

-- 5) ดัชนีช่วยอ่าน roster ตามโรงเรียน/ปี/ชั้น
SET @i := (SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'students' AND INDEX_NAME = 'idx_sc_year');
SET @ddl := IF(@i = 0, 'ALTER TABLE students ADD INDEX idx_sc_year (sc_id, years, class_id)', 'DO 0');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

-- ══ studenthit ══════════════════════════════════════════════════════════════
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'studenthit' AND COLUMN_NAME = 'years');
SET @ddl := IF(@c = 0, 'ALTER TABLE studenthit ADD COLUMN years INT NULL AFTER hit', 'DO 0');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

UPDATE studenthit SET years = 2568 WHERE years IS NULL;

SET @t := (SELECT IS_NULLABLE FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'studenthit' AND COLUMN_NAME = 'years');
SET @ddl := IF(@t = 'YES', 'ALTER TABLE studenthit MODIFY years INT NOT NULL DEFAULT 2569', 'DO 0');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

SET @pk := (SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'studenthit' AND INDEX_NAME = 'PRIMARY');
SET @ddl := IF(@pk = 'stuid,hit', 'ALTER TABLE studenthit DROP PRIMARY KEY, ADD PRIMARY KEY (stuid, years, hit)', 'DO 0');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

-- ══ promote_log_item ════════════════════════════════════════════════════════
-- เพิ่ม years = ปีใหม่ที่แถวถูกสร้าง (rollback แบบใหม่ = ลบแถวปีใหม่)
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'promote_log_item' AND COLUMN_NAME = 'years');
SET @ddl := IF(@c = 0, 'ALTER TABLE promote_log_item ADD COLUMN years INT NULL AFTER stuid', 'DO 0');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;
