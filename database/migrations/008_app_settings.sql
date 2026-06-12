-- ─────────────────────────────────────────────────────────────────────────────
-- 008_app_settings.sql  –  ตารางค่าตั้งระบบแบบ key-value (สำหรับหน้า admin_config.php)
--   เก็บค่าที่แก้ได้ผ่านหน้าเว็บ (เช่น รายชื่อ SMIS ผู้ดูแลเพิ่มเติม) โดยไม่ต้องแก้ config.php
--   ค่าคงที่หลัก (ACADEMIC_YEAR ฯลฯ) ยังอยู่ใน config/config.php ตามเดิม (หน้าเว็บโชว์อ่านอย่างเดียว)
--
-- Idempotent: CREATE TABLE IF NOT EXISTS — รันซ้ำได้ปลอดภัย
--   (PHP สร้างตารางนี้ให้อัตโนมัติตอนบันทึกค่าครั้งแรกด้วย — migration นี้ไว้ทำล่วงหน้า)
-- Run: "D:/laragon/bin/mysql/mysql-8.0.30-winx64/bin/mysql.exe" -u root ssrainfo_hittest < database/migrations/008_app_settings.sql
-- ─────────────────────────────────────────────────────────────────────────────
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS app_settings (
  skey       VARCHAR(64)  NOT NULL PRIMARY KEY,   -- ชื่อค่า เช่น 'admin_smis'
  sval       TEXT         NULL,                   -- ค่า (string หรือ JSON)
  updated_at DATETIME     NOT NULL,
  KEY idx_updated (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
