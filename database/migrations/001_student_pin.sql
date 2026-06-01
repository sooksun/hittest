-- ============================================================================
-- Migration 001 — Phase 3: Student PIN login + brute-force throttle
-- อ้างอิง docs/dashboard-design.md ข้อ 4.5
-- รัน: ดู database/migrations/README.md
-- ============================================================================

-- PIN ของนักเรียน (ครู generate + พิมพ์แจก) — unique ต่อโรงเรียน (ไม่ใช่ทั้งประเทศ)
CREATE TABLE IF NOT EXISTS student_pin (
  sc_id      VARCHAR(15)  NOT NULL,                 -- รหัสโรงเรียน 10 หลัก (= students.sc_id)
  stuid      VARCHAR(50)  NOT NULL,                 -- รหัสนักเรียน (= students.stuid)
  pin_hash   VARCHAR(255) NOT NULL,                 -- password_hash(PIN) — ใช้ตรวจตอน login
  pin_plain  VARCHAR(6)   NULL,                     -- PIN ตัวจริง (ให้ครูพิมพ์แจกซ้ำได้ — ดูหมายเหตุความปลอดภัย)
  is_active  TINYINT(1)   NOT NULL DEFAULT 1,
  created_by VARCHAR(8)   NULL,                     -- sc_smis ของครู/ผู้สร้าง
  created_at DATETIME     NOT NULL,
  PRIMARY KEY (sc_id, stuid),
  UNIQUE KEY uniq_school_pin (sc_id, pin_plain)     -- กัน PIN ซ้ำในโรงเรียนเดียวกัน
                                                    -- (ใช้ pin_plain เพราะ pin_hash ใส่ salt → ค่าต่างทุกครั้ง บังคับ unique ไม่ได้)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
-- ใช้ utf8mb4_0900_ai_ci ให้ตรงกับ students.stuid/sc_id (เลี่ยง "Illegal mix of collations" ตอน JOIN)

-- ตัวนับ/ล็อก สำหรับกัน brute-force ที่หน้า student_login.php
-- scope_key รูปแบบ:  ip:<ip>  |  sc:<sc_id>:<ip>  |  sc:<sc_id>
CREATE TABLE IF NOT EXISTS login_throttle (
  scope_key    VARCHAR(100) NOT NULL,
  fail_count   INT          NOT NULL DEFAULT 0,
  locked_until DATETIME     NULL,
  updated_at   DATETIME     NOT NULL,
  PRIMARY KEY (scope_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
-- ใช้ utf8mb4_0900_ai_ci ให้ตรงกับ students.stuid/sc_id (เลี่ยง "Illegal mix of collations" ตอน JOIN)

-- หมายเหตุความปลอดภัย (เลือก option ข ของ doc 4.5.2):
--  • เก็บ pin_plain เพื่อให้ครู "พิมพ์ PIN แจกซ้ำ" ได้ — เป็น trade-off ที่ยอมรับได้เพราะ
--    PIN ปลดล็อกได้แค่ "แดชบอร์ดผลการอ่านของเด็กคนนั้น (read-only) ในโรงเรียนเดียว" ความเสี่ยงต่ำ
--  • การเข้าถึง pin_plain จำกัดเฉพาะครูในโรงเรียนนั้น (teacher_pins.php scope ด้วย sc_id ของ session)
--  • การ "ตรวจ PIN ตอน login" ยังใช้ password_verify() กับ pin_hash เสมอ (ไม่เทียบ plaintext ตรง ๆ)
--  • ถ้าต้องการความปลอดภัยสูงสุดในอนาคต → เปลี่ยนเป็น option ก (เก็บ hash อย่างเดียว + โชว์ PIN ครั้งเดียวตอนสร้าง)
