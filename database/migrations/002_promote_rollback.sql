-- ============================================================================
-- Migration 002 — รองรับ "ยกเลิกการเลื่อนชั้นทั้งโรงเรียน" (undo last promote)
-- เก็บสภาพก่อนเลื่อนของนักเรียนแต่ละคน เพื่อย้อนกลับได้แม่นยำ (ไม่เดาจากจำนวน)
--
-- ปลอดภัยกับทุกเครื่อง (idempotent):
--   • ถ้ายังไม่มี promote_log (เช่น production ที่ไม่เคยรัน migration เดิม) → สร้างให้พร้อม rolled_back_at
--   • ถ้ามี promote_log อยู่แล้วแต่ยังไม่มีคอลัมน์ rolled_back_at → เพิ่มให้ (ไม่ error ถ้ามีอยู่แล้ว)
--   • รันซ้ำได้ ไม่พัง
-- รันผ่าน phpMyAdmin: เลือกฐาน ssraexhi_hittest → แท็บ SQL → วางทั้งไฟล์ → Go
-- ============================================================================

-- 1) ตารางบันทึกประวัติการเลื่อนชั้น (สร้างให้ครบถ้ายังไม่มี — รวมคอลัมน์ rolled_back_at)
CREATE TABLE IF NOT EXISTS promote_log (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  sc_id          VARCHAR(15)  NOT NULL,
  sc_smis        VARCHAR(8)   DEFAULT NULL,
  sc_name        VARCHAR(255) DEFAULT NULL,
  promoted       INT          DEFAULT 0,           -- จำนวนที่เลื่อนชั้น (ป.1-5 → +1)
  graduated      INT          DEFAULT 0,           -- จำนวน ป.6 ที่จบ → ย้ายออก (stustatus=4)
  created_at     DATETIME     NOT NULL,
  rolled_back_at DATETIME     NULL DEFAULT NULL,   -- เวลาที่ถูกยกเลิก (NULL = ยังไม่ยกเลิก)
  KEY idx_sc (sc_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2) เพิ่มคอลัมน์ rolled_back_at เฉพาะกรณีตารางมีอยู่ก่อนแล้วแต่ยังไม่มีคอลัมน์นี้ (idempotent)
SET @has_col := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'promote_log' AND COLUMN_NAME = 'rolled_back_at'
);
SET @ddl := IF(@has_col = 0,
  'ALTER TABLE promote_log ADD COLUMN rolled_back_at DATETIME NULL DEFAULT NULL AFTER created_at',
  'DO 0');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3) รายการนักเรียนที่ได้รับผลจากการเลื่อนชั้นแต่ละครั้ง (ใช้ย้อนกลับ)
CREATE TABLE IF NOT EXISTS promote_log_item (
  id             BIGINT AUTO_INCREMENT PRIMARY KEY,
  log_id         INT          NOT NULL,            -- อ้างถึง promote_log.id
  stuid          VARCHAR(50)  NOT NULL,
  prev_class_id  INT          NULL,                -- ชั้นก่อนเลื่อน (ไว้คืนค่า)
  prev_stustatus INT          NULL,                -- สถานะก่อนเลื่อน (ไว้คืนค่า)
  action         VARCHAR(10)  NOT NULL,            -- 'promote' (ป.1-5 +1) | 'graduate' (ป.6 → ย้ายออก)
  KEY idx_log (log_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- หมายเหตุ: log การเลื่อนชั้นที่ทำ "ก่อน" migration นี้จะไม่มี item → ย้อนกลับไม่ได้ (ย้อนได้เฉพาะครั้งใหม่)
