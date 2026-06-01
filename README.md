# HIT-TEST (newhittest) — ระบบสอบการอ่านภาษาไทย

เวอร์ชัน refactor ของระบบประเมินการอ่าน ป.1–ป.6 รันบน **Laragon (PHP 8.1 + MySQL 8)**
แยกโครงสร้างชัดเจน (config / includes / assets) แยก JS ออกจาก PHP รองรับ **3 รอบ: Hit-1 / Hit-2 / Hit-3**

## เข้าใช้งาน
- URL: <http://localhost/newhittest/>
- ล็อกอินด้วยรหัส SMIS โรงเรียน (user = pass) เช่น **บ้านพญาไพร** `57030129` / `57030129`
- ฐานข้อมูล: `ssraexhi_hittest` @ `127.0.0.1` (root / ไม่มีรหัสผ่าน)

## โครงสร้างโปรเจกต์
```
newhittest/
├── config/
│   └── config.php          # ค่าคงที่: DB, ปีการศึกษา(2569), จำนวนคำ(20), เวลา(5น.), สถานะนักเรียน
├── includes/
│   ├── db.php              # db() PDO + dbm() mysqli (อ่านค่าจาก config)
│   ├── functions.php       # helpers: find_student, hit_cell, valid_hit, json_response
│   ├── auth.php            # session guard (โหลด functions/db/config ให้พร้อม)
│   ├── header.php          # navbar + เปิด HTML
│   └── footer.php          # ปิด HTML + scripts
├── assets/
│   ├── css/                # bootstrap + theme (app.css = dark theme หน้าสอบ)
│   ├── js/
│   │   ├── exam.js         # ตรรกะหน้าสอบครู (changeWord, saveResults, timer)
│   │   ├── student.js      # จอนักเรียน (sync localStorage)
│   │   └── jquery / bootstrap
│   ├── fonts/  images/      # ฟอนต์ + รูป (logo, avatar, bg-themes)
├── images/  sounds/         # คลังรูป/เสียงประกอบคำ
├── database/
│   ├── ssraexhi_hittest.sql # โครงสร้าง + ข้อมูลอ้างอิง (words, schools, ตัวชี้วัด)
│   └── seed_students.sql    # นักเรียนทดสอบ 10 คน (บ้านพญาไพร)
├── index.php               # ส่งต่อ login/menu
├── login.php  logout.php
├── menu.php                # Dashboard
├── students_list.php       # รายชื่อ + เข้าทดสอบ/ดูผล/ยกเลิก
├── teacher_page.php        # หน้าสอบฝั่งครู (หัวใจ) → exam.js
├── student_page.php        # จอฝั่งนักเรียน → student.js
├── save_results.php        # บันทึกผล (หัวใจ) — เขียน 4 ตาราง รองรับ 3 รอบ
├── cancel_result.php       # ยกเลิกการสอบ 1 รอบ
└── evaluations_view.php    # ผลรายบุคคลแยกตัวชี้วัด
```

## จุดที่ refactor จากเวอร์ชันเดิม (`hittest`)
| เดิม | ใหม่ (newhittest) |
|------|-------------------|
| DB creds hardcode ในแต่ละไฟล์ | รวมที่ `config/config.php` ที่เดียว (constants) |
| connection ซ้ำในทุกไฟล์ | `includes/db.php` (`db()`/`dbm()`) |
| JS ฝังในหน้า PHP ~120 บรรทัด | แยกเป็น `assets/js/exam.js` + `student.js` (รับค่าผ่าน `window.EXAM`) |
| logic ซ้ำ (hit cell, status) | รวมใน `includes/functions.php` |
| ปนกับไฟล์ PHPRunner 235 ไฟล์ | เหลือเฉพาะไฟล์ที่ใช้จริง + assets ที่จำเป็น |
| pace.js ค้างบังจอ | ตัดออก |

## การสอบ (computer-based)
1. เลือกชั้น → กด **เข้าทดสอบ** รอบที่ต้องการ (`teacher_page.php`)
2. ครูแสดงคำ 20 คำ กด **ถูก/ผิด** (จับเวลา 5 นาที) — กด **เปิดหน้าจอนักเรียน** เพื่อ sync คำไปจอเด็ก
3. ครบ 20 คำ → **บันทึกผล** → `save_results.php` เขียน evaluations / studenteval / students / studenthit
4. **ดูผล N** → ผลแยกตัวชี้วัด ; **❌** → ยกเลิกการสอบ (คืนสถานะยังไม่สอบ)

## ติดตั้งฐานข้อมูลใหม่ (ถ้าจำเป็น)
```bash
MYSQL="D:/laragon/bin/mysql/mysql-8.0.30-winx64/bin/mysql.exe"
"$MYSQL" -uroot -e "CREATE DATABASE IF NOT EXISTS ssraexhi_hittest CHARACTER SET utf8mb4;"
"$MYSQL" -uroot --default-character-set=utf8mb4 ssraexhi_hittest < database/ssraexhi_hittest.sql
"$MYSQL" -uroot --default-character-set=utf8mb4 ssraexhi_hittest < database/seed_students.sql
```
