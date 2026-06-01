-- ============================================================================
-- Migration 003 — บันทึกผลการฝึกอ่านของนักเรียน (my_practice.php)
-- 1 แถว = 1 คำที่ฝึก (เก็บจำนวนครั้งที่อ่านกว่าจะถูก + ถูก/ข้าม)
-- idempotent: CREATE TABLE IF NOT EXISTS
-- ============================================================================
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS practice_log (
  id         BIGINT AUTO_INCREMENT PRIMARY KEY,
  sc_id      VARCHAR(15)  NOT NULL,            -- โรงเรียน (จาก session นักเรียน)
  stuid      VARCHAR(50)  NOT NULL,            -- รหัสนักเรียน (จาก session)
  stuname    VARCHAR(255) DEFAULT NULL,        -- ชื่อนักเรียน (จาก session)
  class_id   INT          NOT NULL,            -- ชุดคำที่ฝึก: ชั้น
  hittest    INT          NOT NULL,            --             รอบ
  sethit     INT          NOT NULL,            --             ชุด
  word_id    INT          DEFAULT NULL,        -- อ้าง words.id (ไว้วิเคราะห์หมวดคำภายหลัง)
  word       VARCHAR(255) NOT NULL,            -- คำที่ฝึก
  attempts   INT          NOT NULL DEFAULT 0,  -- จำนวนครั้งที่อ่านกว่าจะถูก
  correct    TINYINT(1)   NOT NULL DEFAULT 0,  -- 1 = อ่านถูก, 0 = ข้าม/ยังไม่ถูก
  created_at DATETIME     NOT NULL,
  KEY idx_stu (stuid, created_at),
  KEY idx_sc (sc_id, created_at),
  KEY idx_word (word_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
