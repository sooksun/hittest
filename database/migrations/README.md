# Database Migrations — Dashboard ผลพัฒนาการ

## ⭐ ติดตั้งบน production ทีเดียวจบ — รัน `setup_all_features.sql`

ถ้าไม่แน่ใจว่า production รัน migration ไหนไปแล้วบ้าง ให้รันไฟล์เดียวนี้ (idempotent ปลอดภัย รันซ้ำได้):

```bash
"D:/laragon/bin/mysql/mysql-8.0.30-winx64/bin/mysql.exe" -u root ssraexhi_hittest < database/migrations/setup_all_features.sql
```

หรือ phpMyAdmin → ฐาน `ssraexhi_hittest` → แท็บ SQL → วางทั้งไฟล์ `setup_all_features.sql` → Go

ครอบคลุมทุกตารางที่ฟีเจอร์ใหม่ต้องใช้: `exam_window`, `reset_log`, `tts_cache`, `promote_log`(+`rolled_back_at`), `promote_log_item`, `student_pin`, `login_throttle`, `practice_log`, `game_stories`(007 — เรื่องที่นักเรียนแต่งท้ายเกม), คอลัมน์สื่อใน `wordstest` + `game_media_jobs` + `game_prompt_history`(009 — ระบบสร้างสื่อในตัว) — ทุกคำสั่ง `IF NOT EXISTS` / ตรวจ `information_schema` ก่อน `ALTER` จึงไม่แตะข้อมูลเดิม

> หมายเหตุ: ตารางหลักของ "เกม" (`game_results`, `game_hangman_sessions`, `game_training_sessions` + idempotency/audit ของ 005–006) ยังต้องรัน `004 → 005 → 006` แยกตามลำดับ — `setup_all_features.sql` รวมเฉพาะ `game_stories` (007) ที่เป็นตารางแบนไม่มี FK

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

## 009 — ระบบสร้าง/จัดการสื่อในตัว (Botnoi TTS + ComfyUI image) — รันก่อนใช้ `admin_media.php`

```bash
"D:/laragon/bin/mysql/mysql-8.0.30-winx64/bin/mysql.exe" -u root ssraexhi_hittest < database/migrations/009_media_generation.sql
```

ขยาย `wordstest` (คอลัมน์ `what_to_draw`, `image_status`, `image_reason`, `image_generated_at`, `sound_generated_at` + `image_path`→`TEXT`) ให้ newhittest "เป็นเจ้าของ" สื่อเอง แทนการ `LEFT JOIN readthai.wordstest` ข้าม DB · เพิ่มตาราง `game_media_jobs` (คิวงาน แทน BullMQ) และ `game_prompt_history` (ประวัติ prompt ภาพ)

**Idempotent** — ตรวจ `information_schema` ก่อน `ALTER` ทุกคอลัมน์ + `CREATE TABLE IF NOT EXISTS` รันซ้ำได้

หลังรัน migration ให้ย้ายสื่อเดิม (1554 คำ) จาก readthai เข้ามาเป็นของ newhittest ด้วย:

```bash
php scripts/migrate_media_from_readthai.php        # คัดลอกไฟล์ที่อ้างถึง + คัดลอก path เข้า wordstest
```

## 010 — ผูก students/studenthit กับปีการศึกษา (พ.ศ.) — สำรอง DB ก่อน (ALTER PK)

```bash
"D:/laragon/bin/mysql/mysql-8.0.30-winx64/bin/mysql.exe" -u root ssraexhi_hittest < database/migrations/010_students_year.sql
php scripts/rollover_students_year.php --apply      # สร้าง roster ปีปัจจุบัน (2569) จาก 2568 ทุกโรงเรียน
```

เดิม `students` เป็น 1 แถว/คน (PK=`stuid`, ไม่มีปี) → **1 แถว/คน/ปี** (PK=`stuid,years`) · ข้อมูลเดิม → **2568** · `studenthit` ก็เพิ่ม `years` (PK=`stuid,years,hit`) · `promote_log_item` เพิ่ม `years`

**ต้องรัน 2 ขั้นต่อกัน**: migration ตั้งข้อมูลเดิมเป็น 2568 → แอป (กรอง `years=ACADEMIC_YEAR=2569`) จะว่างจนกว่าจะรัน `rollover_students_year.php --apply` (เลื่อนชั้น 2568→2569 สร้าง roster ปีปัจจุบัน) · **Idempotent** ทั้งคู่ (ตรวจ information_schema + INSERT IGNORE)

**Idempotent + self-contained** — รันซ้ำได้ ไม่พัง และ**สร้างตาราง `promote_log` ให้เองถ้ายังไม่มี** (เช่น production ที่ไม่เคยรัน migration เดิม) จึงไม่ต้องรัน `migration_promote_log.sql` ก่อน · เพิ่มคอลัมน์ `rolled_back_at` เฉพาะตอนที่ขาด (ใช้ information_schema + PREPARE)

ใช้ `CREATE TABLE IF NOT EXISTS` — รันซ้ำได้ปลอดภัย · collation = `utf8mb4_0900_ai_ci` ให้ตรงกับ `students` (เลี่ยง error ตอน JOIN)

## 011 — ตาราง `users` (บัญชีผู้ใช้จริง + role ตามลำดับชั้น) — สำหรับหน้า `admin_users.php`

```bash
"D:/laragon/bin/mysql/mysql-8.0.30-winx64/bin/mysql.exe" -uroot ssraexhi_hittest < database/migrations/011_users.sql
php scripts/migrate_area_admins.php            # dry-run ดูจำนวน
php scripts/migrate_area_admins.php --apply     # นำเข้าบัญชีผู้ดูแลเขต (saoadmin) จาก master_saonew
php scripts/migrate_school_users.php --area=5703 --apply   # สร้าง user ระดับโรงเรียนจาก schools (ระบุเขต/--active/--all)
```

สร้างตาราง `users` (username + `password_hash`/bcrypt + `role` **superadmin·saoadmin·school** + `area_code` + `sc_id` + `is_active`) · `login.php` ลองตารางนี้ก่อน (username/password) แล้ว **fallback** ไป SMIS โรงเรียนเดิม — **ไม่กระทบ login เดิม**

- **superadmin** — ผู้ดูแลระบบทั้งประเทศ (เครื่องมือระบบ) · **saoadmin** — ผู้ดูแลเขต สพป. (`area_code` = 4 หลักแรกของ SMIS, เห็นทุกโรงเรียนในเขต, **อ่านอย่างเดียว** ผ่าน `require_editor()`) · **school** — โรงเรียนเดียว (`sc_id`)
- super/saoadmin สลับโรงเรียนที่ดูผ่าน `school_switch.php` (ตรวจขอบเขต) · บัญชีเขต migrate จาก `master_saonew` ด้วย `scripts/migrate_area_admins.php` (username = areacode, นำเข้าเฉพาะเขตที่มีโรงเรียน — local มี 173 เขต) · เขตอ้างอิงจาก `sao_new`
- บัญชีระดับโรงเรียนสร้างจากตาราง `schools` ด้วย `scripts/migrate_school_users.php` (username=SMIS, password=SMIS hash, role=school; ข้ามโรงเรียนที่เป็นผู้ดูแล) — ไม่บังคับ เพราะโรงเรียน login ด้วย SMIS ได้อยู่แล้ว (fallback) แต่สร้างไว้เพื่อจัดการ/เปลี่ยนรหัสในหน้า admin
- จัดการบัญชีที่ `admin_users.php` (เฉพาะ superadmin) · **Idempotent** (`CREATE TABLE IF NOT EXISTS` + INSERT IGNORE) — ตาราง `users` รวมใน `setup_all_features.sql` แล้ว (การ migrate บัญชีเขตต้องรันสคริปต์แยก)

> **Phase 1–2 ไม่มีไฟล์ migration** โดยตั้งใจ — การเปลี่ยนแปลงเป็นโค้ด PHP + ค่าคงที่ ไม่แตะ schema
> ดูรายละเอียดการออกแบบเต็มได้ที่ [`docs/dashboard-design.md`](../../docs/dashboard-design.md)

## 012 — Soft delete นักเรียน (`deleted_at` / `deleted_by`) — สำหรับปุ่ม "🗑️ ลบ" ของโรงเรียน

```bash
"D:/laragon/bin/mysql/mysql-8.0.30-winx64/bin/mysql.exe" -uroot ssraexhi_hittest < database/migrations/012_student_soft_delete.sql
```

เพิ่มคอลัมน์ `deleted_at DATETIME NULL` + `deleted_by VARCHAR(50) NULL` ให้ `students` (รวมใน `setup_all_features.sql` / `server_mariadb_all.sql` แล้ว) · **Idempotent** (information_schema + PREPARE)

- โรงเรียน (role โรงเรียน) ลบนักเรียนแบบ **soft delete** ได้ที่ `students_list.php` → `student_delete.php` (ตั้ง `deleted_at=NOW()`) — ไม่ลบแถวจริง เก็บประวัติ/ผลสอบไว้
- **Invariant สำคัญ**: ทุกการอ่าน `students` ฝั่งผู้ใช้ต้องกรอง `deleted_at IS NULL` (ดู `find_student()` เป็น chokepoint หลัก + `students_list`/`menu`/`dashboard`/`area_results`/`paper`/`settings`/`exam_control`/`promote_lib`/`student_auth`) — เพิ่ม query ใหม่ที่อ่านนักเรียนเมื่อใด ต้องใส่เงื่อนไขนี้ด้วย
- กู้คืนได้เฉพาะผู้ดูแลระบบ ที่ `admin_students_trash.php` (ถังขยะ → ♻️ กู้คืน → `deleted_at=NULL`)

## เมนู "เลื่อนชั้นทั้งโรงเรียน" เปิด/ปิดได้ (ไม่ใช่ migration — เก็บใน `app_settings`)

ผู้ดูแลระบบเปิด/ปิดเมนูเลื่อนชั้นสำหรับโรงเรียนได้ที่ `admin_config.php` (เก็บ `app_settings.promote_enabled` = `'1'`/`'0'`, ค่าเริ่มต้น = ปิด) · helper `promote_menu_enabled()` · `header.php`/`promote.php`/`promote_school.php`/`promote_rollback.php` เช็ค `is_admin() || promote_menu_enabled()` (ผู้ดูแลระบบใช้ได้เสมอ)
