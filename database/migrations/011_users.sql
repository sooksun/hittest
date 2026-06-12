-- ─────────────────────────────────────────────────────────────────────────────
-- 011_users.sql  –  ตาราง users (บัญชีผู้ใช้จริง + role ตามลำดับชั้นการบริหาร)
--   login.php รองรับ username/password ของตารางนี้ "ควบคู่" กับ SMIS โรงเรียนเดิม
--   (ไม่กระทบ login เดิมของโรงเรียน 31k แห่ง — ลองตาราง users ก่อน ถ้าไม่เจอค่อย fallback SMIS)
--
--   role (3 ระดับ):
--     superadmin – ผู้ดูแลระบบ      (ทั้งประเทศ · เข้าเครื่องมือระดับระบบ · สลับดูได้ทุกโรงเรียน)
--     saoadmin   – ผู้ดูแลเขต สพป.   (เห็น/ดูทุกโรงเรียนใน "เขต" ของตน · อ่านอย่างเดียว)
--     school     – โรงเรียน          (เฉพาะโรงเรียนตน — เหมือน login SMIS เดิม)
--
--   scope:
--     area_code = 4 หลักแรกของ SMIS = "เขตพื้นที่" (เช่น 5703 = สพป.เชียงราย เขต 3 ใน sao_new.areacode 57030000)
--               saoadmin เห็นทุกโรงเรียนที่ LEFT(schools.sc_smis,4) = area_code
--     sc_id     = schools.sc_id (โรงเรียนสังกัด) — ใช้กับ role=school
--   password เก็บเป็น hash (password_hash/PASSWORD_DEFAULT) — ไม่เก็บ plaintext
--   บัญชีเขตนำเข้าจาก master_saonew ด้วย scripts/migrate_area_admins.php
--
-- Idempotent: CREATE TABLE IF NOT EXISTS — รันซ้ำได้
-- Run: "D:/laragon/bin/mysql/mysql-8.0.30-winx64/bin/mysql.exe" -uroot ssraexhi_hittest < database/migrations/011_users.sql
-- ─────────────────────────────────────────────────────────────────────────────
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS users (
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
  UNIQUE KEY uq_username (username),
  KEY idx_role (role),
  KEY idx_area (area_code),
  KEY idx_sc (sc_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
