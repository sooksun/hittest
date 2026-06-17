-- ============================================================================
-- Migration 012 — Soft delete นักเรียน (ลบแล้วซ่อน — กู้คืนได้โดยผู้ดูแลระบบ)
--
-- เพิ่มคอลัมน์ deleted_at / deleted_by ให้ตาราง students
--   • deleted_at IS NULL  = นักเรียนปกติ (แสดงผล/ใช้งานได้)
--   • deleted_at มีค่า     = ถูกลบ (ซ่อนจากทุกหน้าโรงเรียน — admin กู้คืนได้)
-- การลบเป็น soft delete: ไม่ลบแถวจริง เก็บประวัติ/ผลสอบไว้ทั้งหมด
--
-- ปลอดภัยกับทุกเครื่อง (idempotent): รันซ้ำได้ ไม่ error ถ้ามีคอลัมน์/ดัชนีอยู่แล้ว
-- รันผ่าน phpMyAdmin: เลือกฐาน → แท็บ SQL → วางทั้งไฟล์ → Go
-- ============================================================================

-- 1) เพิ่มคอลัมน์ deleted_at + deleted_by (เฉพาะถ้ายังไม่มี)
SET @has_col := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'students' AND COLUMN_NAME = 'deleted_at'
);
SET @ddl := IF(@has_col = 0,
  'ALTER TABLE students ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL, ADD COLUMN deleted_by VARCHAR(50) NULL DEFAULT NULL',
  'DO 0');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2) ดัชนีสำหรับกรอง deleted_at IS NULL (เฉพาะถ้ายังไม่มี)
SET @has_idx := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'students' AND INDEX_NAME = 'idx_students_deleted'
);
SET @ddl2 := IF(@has_idx = 0,
  'ALTER TABLE students ADD INDEX idx_students_deleted (sc_id, years, deleted_at)',
  'DO 0');
PREPARE stmt2 FROM @ddl2;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;
