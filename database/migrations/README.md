# Database Migrations — Dashboard ผลพัฒนาการ

## ⭐ ติดตั้งบน production ทีเดียวจบ — รัน `setup_all_features.sql`

ถ้าไม่แน่ใจว่า production รัน migration ไหนไปแล้วบ้าง ให้รันไฟล์เดียวนี้ (idempotent ปลอดภัย รันซ้ำได้):

```bash
"D:/laragon/bin/mysql/mysql-8.0.30-winx64/bin/mysql.exe" -u root ssraexhi_hittest < database/migrations/setup_all_features.sql
```

หรือ phpMyAdmin → ฐาน `ssraexhi_hittest` → แท็บ SQL → วางทั้งไฟล์ `setup_all_features.sql` → Go

ครอบคลุมทุกตารางที่ฟีเจอร์ใหม่ต้องใช้: `exam_window`, `reset_log`, `tts_cache`, `promote_log`(+`rolled_back_at`), `promote_log_item`, `student_pin`, `login_throttle` — ทุกคำสั่ง `IF NOT EXISTS` จึงไม่แตะข้อมูลเดิม

---

Migration SQL รายไฟล์ (สำหรับ track เป็น step ๆ) อยู่ในโฟลเดอร์นี้ รันตามลำดับเลขนำหน้าไฟล์

## วิธีรัน (local — laragon)

```bash
"D:/laragon/bin/mysql/mysql-8.0.30-winx64/bin/mysql.exe" -u root ssraexhi_hittest < database/migrations/<ไฟล์>.sql
```

หรือผ่าน phpMyAdmin → เลือกฐาน `ssraexhi_hittest` → แท็บ Import → เลือกไฟล์ .sql

## สถานะตาม Phase

| Phase | ฟีเจอร์ | ต้องมี migration? |
|---|---|---|
| **1** | Dashboard ระดับโรงเรียน (`dashboard_school.php`) | ❌ **ไม่ต้อง** — ใช้ตารางเดิม (`students`, `studenteval`, `class`) + ค่าคงที่ `PASS_SCORE` ใน `config/config.php` เท่านั้น |
| **2** | Dashboard รายชั้น + รายบุคคล (`dashboard_class.php`, `dashboard_student.php`) | ❌ **ไม่ต้อง** — ใช้ตารางเดิม + helper ใน `includes/dashboard.php` (ยังใช้ school admin login เดิมเป็น role) |
| **3** | Student login (PIN) + แดชบอร์ดนักเรียน (`student_login.php`, `my_dashboard.php`, `teacher_pins.php`) | ✅ **`001_student_pin.sql`** — สร้าง `student_pin` + `login_throttle` |
| 4 | per-word weakness | (ตรวจ path การเขียน `evaluations` — อาจไม่ต้องแก้ schema) |
| 5 | Dashboard ระดับเขต (`area_admin`) | ✅ เพิ่ม `schools.area_code`, `user_admin.area_code` |

## Phase 3 — รันคำสั่งนี้ (ผู้ใช้ต้องรันก่อนใช้งาน student login)

```bash
"D:/laragon/bin/mysql/mysql-8.0.30-winx64/bin/mysql.exe" -u root ssraexhi_hittest < database/migrations/001_student_pin.sql
```

## 002 — ยกเลิกการเลื่อนชั้น (undo promote) — รันก่อนใช้ปุ่ม "ยกเลิกการเลื่อนชั้น"

```bash
"D:/laragon/bin/mysql/mysql-8.0.30-winx64/bin/mysql.exe" -u root ssraexhi_hittest < database/migrations/002_promote_rollback.sql
```

เพิ่มคอลัมน์ `promote_log.rolled_back_at` + ตาราง `promote_log_item` (เก็บชั้น/สถานะก่อนเลื่อนรายคน เพื่อย้อนกลับแม่นยำ)

**Idempotent + self-contained** — รันซ้ำได้ ไม่พัง และ**สร้างตาราง `promote_log` ให้เองถ้ายังไม่มี** (เช่น production ที่ไม่เคยรัน migration เดิม) จึงไม่ต้องรัน `migration_promote_log.sql` ก่อน · เพิ่มคอลัมน์ `rolled_back_at` เฉพาะตอนที่ขาด (ใช้ information_schema + PREPARE)

ใช้ `CREATE TABLE IF NOT EXISTS` — รันซ้ำได้ปลอดภัย · collation = `utf8mb4_0900_ai_ci` ให้ตรงกับ `students` (เลี่ยง error ตอน JOIN)

> **Phase 1–2 ไม่มีไฟล์ migration** โดยตั้งใจ — การเปลี่ยนแปลงเป็นโค้ด PHP + ค่าคงที่ ไม่แตะ schema
> ดูรายละเอียดการออกแบบเต็มได้ที่ [`docs/dashboard-design.md`](../../docs/dashboard-design.md)
