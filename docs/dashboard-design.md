# Dashboard Design — "ผลการพัฒนาภาษาไทยของนักเรียน"

> เอกสารออกแบบ (design doc) — **ยังไม่ลง code จริง** ใช้เพื่อรีวิว/คอมเมนต์ก่อนเริ่ม implement
> ระบบ: HIT-TEST (PHP 8.1 + MySQL 8 + Bootstrap 5 + SweetAlert2) · DB: `ssraexhi_hittest`
> ขอบเขต: Dashboard 4 ระดับ — รายบุคคล / รายชั้น / รายโรงเรียน / ระดับเขตพื้นที่ (สพป./สพม.) พร้อม RBAC
>
> **อัปเดต (รอบ 2):** บรรจุการตัดสินใจสุดท้ายจากผู้ใช้ — นักเรียน login ได้ (PIN), เกณฑ์ผ่าน 50%, admin ดูเฉพาะเขตของตน, ยืนยัน mapping หมวดคำแล้ว, drill ลึกแค่ระดับชั้น (ไม่มีห้อง)

---

## 0. สรุปผู้บริหาร (อ่านก่อน)

ระบบ HIT-TEST วัด "การอ่านคำพื้นฐานภาษาไทย" ของนักเรียน ป.1–ป.6 ด้วยการสอบ 3 รอบต่อปี (Hit-1/2/3) รอบละ 20 คำ คะแนนเต็ม 20

**การตัดสินใจสุดท้าย (ยึดตามนี้):**
1. **นักเรียน login ได้** หลายคน — ใช้ **PIN 4–6 หลัก** ครูเป็นคน generate + พิมพ์แจก (ดูข้อ 4.5)
2. **เกณฑ์ผ่าน = 50%** → `score ≥ 10` จาก 20 (`PASS_SCORE = 10`)
3. **admin = ระดับเขตพื้นที่ (สพป./สพม.)** — เห็นเฉพาะโรงเรียนในเขตตน **ไม่ใช่ทั้งประเทศ** → ต้องเพิ่ม `area_code` ผูกโรงเรียน↔เขต (ดูข้อ 4.2, 5)
4. **mapping หมวดคำ = ยืนยันแล้ว** → `words.indicator` **คือ** `word_category.catid` (1:1) (ดูข้อ 1.5)
5. **drill ลึกสุดแค่ระดับชั้น** (ป.1…ป.6) — **ไม่ทำระดับห้อง** (1/1, 1/2)

**ข้อมูลที่ใช้ได้จริง ณ ตอนนี้** (สำรวจจาก DB จริง):
- ✅ **คะแนนรวมรายรอบ** มีครบและเป็นชุดข้อมูลหลัก — `studenteval` มี **32,483 แถว** ครอบคลุมปี พ.ศ. 2567–2569 มี `testtime` → ทำ **trend ตามเวลา/รอบ/ปี** ได้
- ✅ **ทะเบียนโรงเรียน 31,022 แห่ง** (`schools`) มีภูมิศาสตร์ครบ (ตำบล/อำเภอ/จังหวัด/อปท./กลุ่มโรงเรียน) — **แต่ยังไม่มี field "เขตพื้นที่ (สพป./สพม.)"** ต้องเพิ่ม (ดูข้อ 5)
- ✅ **mapping หมวดคำพร้อมใช้** — `words.indicator = word_category.catid` (ดูข้อ 1.5) ⇒ พอ per-word ถูกเก็บ จะทำ "หมวดที่อ่อน" ได้ทันที
- ✅ **สถิติ precomputed** — `statalltest` (% ต่อโรงเรียน) + `statscore` (% ต่อหมวดคำ)
- ⚠️ **คะแนนรายคำ (per-word)** แทบไม่มีในระบบจริง — `evaluations` มีแค่ **20 แถว / 1 คน**, `studenthit` มี **1 แถว** → **weakness รายคำ/รายหมวดยังทำไม่ได้** จนกว่าจะเริ่มเก็บ (ดูข้อ 5)
- ⚠️ **ยังไม่มี role model + student login ใน DB** — ปัจจุบัน login = "1 บัญชี = 1 โรงเรียน" (`schools.sc_smis`) → ต้องเพิ่ม role (student/teacher/school_admin/area_admin) + PIN login (ดูข้อ 4)

**คำแนะนำลำดับงาน:** เริ่มจากระดับที่ "ข้อมูลพร้อม + auth พร้อม" ที่สุดก่อน = **ระดับโรงเรียน** (ข้อ 7 Phase 1) แล้วค่อยขยายลง (ชั้น→บุคคล) และขึ้น (เขตพื้นที่)

---

## 1. Data Inventory (สำรวจจาก DB จริง)

### 1.1 ตารางที่เกี่ยวข้องโดยตรง

| ตาราง | แถว (local) | บทบาท | คอลัมน์สำคัญ |
|---|---:|---|---|
| `students` | 10* | ทะเบียน + **สแน็ปช็อตคะแนนปัจจุบัน** | `stuid`(PK,varchar50), `stuname`, `sc_id`(varchar15), `class_id`, `rooms`, `stustatus`, `hit1/2/3`, `hit1/2/3tested`, `sethit1/2/3`, `updatedDate` |
| `studenteval` | **32,483** | **ประวัติคะแนนรวมรายรอบ (แหล่งหลักของ trend)** | `stuid`(bigint), `hittest`, `years`(พ.ศ.), `score`(0–20), `testtime`(timestamp) — PK(stuid,hittest,years) |
| `evaluations` | 20 ⚠️ | ผลรายคำ (correct/incorrect ต่อข้อ) | `stuid`,`hittest`,`years`,`autoid`(ลำดับข้อ1–20), `word_id`, `correct`(0/1) — PK(stuid,hittest,years,autoid) |
| `studenthit` | 1 ⚠️ | denormalize item1..item20 ต่อคน/รอบ | `stuid`,`hit`,`hitscore`,`sethit`,`item1..item20`,`sc_id`,`class_id`,`rooms`,`stustatus`,`updatedDate` |
| `words` | 1,800 | คลังคำ (6 ชั้น × 3 รอบ × 5 ชุด × 20) | `id`,`orders`,`word`,`level`(1–3),`class_id`,`indicator`(1–17),`hittest`,`sethit`,`spoken_form` |
| `word_category` | 28 | หมวดคำ (มาตราตัวสะกด/คำควบ ฯลฯ) | `catid`,`category` |

\* `students`/`evaluations`/`studenthit` มีน้อยใน local เพราะเป็นชุด seed ของโรงเรียนเดียว — ในระบบจริงจะมากตามจำนวนนักเรียน

### 1.2 ตารางโรงเรียน / ภูมิศาสตร์ / สิทธิ์

| ตาราง | แถว | บทบาท | คอลัมน์สำคัญ |
|---|---:|---|---|
| `schools` | **31,022** | ทะเบียนโรงเรียนทั้งประเทศ + ภูมิศาสตร์ | `sc_id`(10 หลัก), `sc_smis`(8 หลัก = username), `sc_name`, `sao_code`, `districts`(ตำบล), `amphures`(อำเภอ), `provinces`, `school_group`, `num_boy/girl/sum_stu` |
| `school` | 1,635 | ตารางโรงเรียนรุ่นเก่า (มี credential + คะแนนรวม) | `sc_id`,`username`,`password`,`sc_names`,`sao_id`,`sum_score`,`status` |
| `class` | 6 | ชื่อชั้น ป.1–ป.6 | `class_id`,`classname` |
| `levels` | 3 | ระดับคำ: ง่าย/ปานกลาง/ยาก | `levid`,`levels` |
| `exam_window` | 2 | เปิด/ปิดรอบสอบต่อโรงเรียน | `sc_id`,`hittest`,`is_open`,`updated_by`,`updated_at` |
| `user_admin` | 270 | บัญชี admin (ระดับเขต/ชาติ — **ยังไม่เชื่อมกับ auth.php**) | `id`,`name`,`user`,`password`,`email` |
| `hittestuggroups/members/rights` | – | ระบบสิทธิ์กลุ่มรุ่นเก่า (PHPRunner) — **legacy, ไม่ใช้งาน** | `GroupID`,`UserName`,`TableName`,`AccessMask` |
| `statalltest` | 1,370 | **precomputed:** % สำเร็จต่อโรงเรียน | `sc_id`,`scname`,`percents`,`finished`,`rec_date` |
| `statscore` | 57 | **precomputed:** % ถูกต่อหมวดคำ (ระดับชาติ) | `hittest`,`id`,`catid`,`category`,`all_item`,`scores`,`percents` |
| `sao_new`,`province`,`master_*` | – | lookup ภูมิศาสตร์ (อปท./จังหวัด/พิกัด) | `areacode`,`province`,`lat`,`lng` |

### 1.3 ความสัมพันธ์หลัก (ER ย่อ)

```
schools (sc_id 10หลัก) ──1:N── students (sc_id) ──1:N── studenteval (stuid, ต่อ hittest/years)
   │  provinces/amphures/                 │ class_id          │ score 0..20 + testtime
   │  districts/school_group              │ rooms             └─ (history → trend)
   │  (roll-up ภูมิศาสตร์)                 │ stustatus
   │                                       └─1:N── evaluations (per-word, ⚠️ ว่างในจริง)
   │
words (class_id,hittest,sethit,orders) ── word_id ──> evaluations.word_id
words.indicator ═══ = ═══> word_category.catid   ✅ ยืนยันแล้ว 1:1 (ดูข้อ 1.5)
```

### 1.4 ตัวอย่างข้อมูลจริง

**`studenteval`** (การกระจายตามปี/รอบ — ยืนยันว่า trend ทำได้):

| years | hittest | n | avg score | ช่วงเวลา (testtime) |
|---:|---:|---:|---:|---|
| 2567 | 1 | 7,329 | 14.38 | 2025-06 → 2025-07 |
| 2568 | 2 | 12,625 | 13.41 | 2025-08 → 2026-03 |
| 2568 | 3 | 11,943 | 14.40 | 2025-08 → 2026-03 |
| 2569 | 1 | 1 | 17.00 | 2026-06 (ปีปัจจุบัน เพิ่งเริ่ม) |

**`statscore`** (% ถูกต่อหมวด — ใช้โชว์ "หมวดที่อ่อน" ได้ทันที):

| hittest | catid | category | all_item | scores | % |
|---:|---:|---|---:|---:|---:|
| 1 | 1 | สระเดี่ยวเสียงยาว | 84,579 | 71,036 | 83.99 |
| 1 | 3 | สะกดตรงมาตรา | 108,978 | 70,412 | 64.61 |
| 1 | 5 | สระลดรูป | 43,947 | 25,482 | 57.98 |

**`statalltest`** (% ต่อโรงเรียน — ใช้จัดอันดับ/heatmap): `sc_id, scname, percents, finished`

### 1.5 Mapping หมวดคำ — **ยืนยันจาก DB จริงแล้ว** ✅

> ตอบข้อ 4 ของรอบที่แล้ว: **`words.indicator` = `word_category.catid` แบบ 1:1** (ตรวจด้วยการ join จริง ตรงทุกแถว)

- `word_category` นิยามไว้ **28 หมวด** (`catid` 1–28) แต่คลังคำปัจจุบัน (`words` 1,800 คำ) ใช้จริง **เฉพาะหมวด 1–17** (18–28 ยังไม่มีคำ) — นี่คือเหตุที่รอบก่อนเห็น "1–17 vs 28" ไม่ใช่เพราะ mapping ผิด
- `statscore.catid` ก็อ้างชุด catid เดียวกัน → ตัวเลข aggregate ระดับเขต/หมวด สอดคล้องกันได้
- **Query หมวดที่อ่อน (พร้อมใช้เมื่อ `evaluations` มีข้อมูล):**
  ```sql
  SELECT wc.catid, wc.category,
         SUM(e.correct) AS correct, COUNT(*) AS total,
         ROUND(SUM(e.correct)/COUNT(*)*100, 1) AS pct
  FROM evaluations e
  JOIN words w           ON w.id = e.word_id
  JOIN word_category wc  ON wc.catid = w.indicator
  WHERE e.stuid = :stuid AND e.years = :years AND e.hittest = :hit
  GROUP BY wc.catid, wc.category
  ORDER BY pct ASC;          -- หมวดที่ % ต่ำสุด = จุดอ่อน
  ```

**หมวดคำที่ใช้จริง (catid → category, พร้อมจำนวนคำในคลัง):**

| catid | หมวด | #คำ | catid | หมวด | #คำ |
|---:|---|---:|---:|---|---:|
| 1 | สระเดี่ยวเสียงยาว | 304 | 10 | คำที่ประวิสรรชนีย์ | 119 |
| 2 | สระเดี่ยวเสียงสั้น | 210 | 11 | คำที่ไม่ประวิสรรชนีย์ | 67 |
| 3 | สะกดตรงมาตรา | 184 | 12 | คำที่มี รร | 43 |
| 4 | สระเปลี่ยนรูป | 101 | 13 | คำที่การันต์ | 79 |
| 5 | สระลดรูป | 35 | 14 | คำที่มี บัน บรร | 12 |
| 6 | สระประสม | 111 | 15 | คำพิเศษ (ฑ ฤ ฤๅ) | 12 |
| 7 | สะกดไม่ตรงมาตรา | 187 | 16 | คำที่มาจากต่างประเทศ | 59 |
| 8 | คำควบกล้ำ | 158 | 17 | สำนวนไทย | 2 |
| 9 | อักษรนำ | 117 | | *(18–28 นิยามไว้แต่ยังไม่มีคำ)* | – |

---

## 2. KPI & Metrics ที่เสนอ (เลือกเฉพาะที่ data ทำได้จริง)

หมายเหตุสัญลักษณ์: ✅ = ทำได้ทันทีด้วยข้อมูลปัจจุบัน · ⚠️ = ต้องเก็บข้อมูลเพิ่มก่อน (ดูข้อ 5)

### 2.1 Metric แกนกลาง (คำนวณจาก `score`/20)

| Metric | สูตร | ระดับที่ใช้ได้ | สถานะ |
|---|---|---|---|
| **% อ่านถูก** | `score / 20 × 100` | ทุกระดับ | ✅ |
| **คะแนนเฉลี่ย** | `AVG(score)` | ชั้น/โรงเรียน/ประเทศ | ✅ |
| **อัตราผ่านเกณฑ์** | `% นักเรียนที่ score ≥ 10` (**เกณฑ์ 50% = 10/20**, `PASS_SCORE=10`) | ชั้น/โรงเรียน/เขต | ✅ |
| **พัฒนาการรายรอบ (Δ)** | `score(Hit-n) − score(Hit-(n-1))` | บุคคล/ชั้น/โรงเรียน | ✅ |
| **พัฒนาการข้ามปี** | เทียบ `years` 2567→2568→2569 จาก `studenteval` | ทุกระดับ | ✅ |
| **อัตราการสอบครบ (completion)** | `hit{n}tested` / จำนวนนักเรียน | ชั้น/โรงเรียน/เขต | ✅ |
| **การกระจายคะแนน (histogram)** | นับนักเรียนต่อช่วงคะแนน 0–5/6–10/11–15/16–20 | ชั้น/โรงเรียน/เขต | ✅ |
| **เทียบกับค่าเฉลี่ย** | คะแนนคน/ชั้น เทียบ avg ชั้น/โรงเรียน/เขต | ทุกระดับ | ✅ |
| **เด็กต้องช่วยเร่งด่วน (at-risk)** | `score < 10` (ต่ำกว่าเกณฑ์) หรือ `Δ ≤ 0` (ไม่พัฒนา) หรือ `ยังไม่สอบ` | บุคคล/ชั้น/โรงเรียน | ✅ |
| **หมวดคำที่อ่อน (precomputed เขต/รวม)** | จาก `statscore.percents` เรียงน้อย→มาก | เขต | ✅ (precomputed) |
| **หมวดคำที่อ่อน (รายคน/ชั้น/โรงเรียน)** | join `evaluations.word_id → words.indicator = word_category.catid` (ข้อ 1.5) | บุคคล/ชั้น/โรงเรียน | ⚠️ mapping พร้อม รอ per-word |
| **คำที่ผิดบ่อย (top wrong words)** | นับ `evaluations.correct=0` group by `word_id` | ทุกระดับ | ⚠️ รอ per-word |
| **เวลาที่ใช้ฝึก/ความถี่** | – | – | ⚠️ (ไม่มี log การฝึก practice.php) |

> **เกณฑ์ผ่าน 50%:** กำหนด `const PASS_SCORE = 10;` ใน `config.php` แล้วอ้างทุกที่ — เปลี่ยนที่เดียวคุมทั้งระบบ ("at-risk" = ต่ำกว่า 10)

### 2.2 หมายเหตุเชิงข้อมูล (caveats สำหรับ implement)

- `studenteval.stuid` เป็น **bigint** แต่ `students.stuid` เป็น **varchar(50)** → ตอน join ต้อง `CAST` (เช่น `CAST(students.stuid AS UNSIGNED) = studenteval.stuid`) — ทดสอบให้ดี
- `evaluations` ไม่มี timestamp ของตัวเอง (trend รายคำต้องอิง `studenteval.testtime` หรือเพิ่มคอลัมน์)
- **"ชั้น" = `class_id` (ป.1–ป.6) เท่านั้น** — ตามการตัดสินใจ **ไม่ทำ dashboard ระดับห้อง (`rooms`)**; รายชั้นรวมทุกห้องเข้าด้วยกัน (คอลัมน์ `rooms` ยังเก็บไว้ใน DB ได้ แต่ไม่ใช้เป็นมิติ drill-down)
- `years` เก็บเป็น **พ.ศ.** อยู่แล้ว (2567/2568/2569) — ตรงกับ convention พ.ศ. ของโปรเจกต์ ไม่ต้อง convert

---

## 3. โครงสร้างหน้า / Wireframe (4 ระดับ)

Chart library ที่เสนอ: **Chart.js v4 (CDN)** — เบา, ไม่ต้อง build, เข้ากับ Bootstrap/SweetAlert2 ที่มีอยู่ (ดูข้อ 6)

### 3.1 ระดับ "รายบุคคล" — `dashboard_student.php` (นักเรียน login เห็นของตัวเอง / ครูเปิดดูรายคน)
ผู้ใช้: **นักเรียน (login ด้วย PIN)** / ผู้ปกครอง / ครู · เป้าหมาย: เห็นพัฒนาการตัวเอง

```
┌───────────────────────────────────────────────────────────────┐
│ 👦 เด็กชายทดสอบ หนึ่ง · ป.1 · บ้าน...   [ปี: 2569 ▾]           │
├──────────────┬──────────────┬──────────────┬──────────────────┤
│ Hit-1: 17/20 │ Hit-2: –     │ Hit-3: –     │ พัฒนาการ ▲ +0    │  ← stat cards
│ (85%) ✅ผ่าน │ ยังไม่สอบ    │ ยังไม่สอบ    │ เทียบรอบก่อน     │
├──────────────┴──────────────┴──────────────┴──────────────────┤
│  พัฒนาการรายรอบ/รายปี (Line)        │  เทียบกับค่าเฉลี่ย (Bar) │
│  20┤        ●───●                   │  ฉัน    ████████ 85%    │
│  15┤   ●────╯                       │  ชั้น   ██████   72%    │
│  10┤  ╯  ┄┄┄ เกณฑ์ผ่าน 50% ┄┄┄      │  ร.ร.  █████    68%    │
│   0└──┬────┬────┬──                 │  เขต   ██████   71%    │
│    H1'67 H1'68 H1'69                │  (Hit-1, ปี 2569)       │
├──────────────────────────────────────────────────────────────┤
│  หมวดคำที่ต้องฝึกเพิ่ม (Horizontal bar)      ⚠️ รอ per-word    │
│  สระลดรูป      ████░░ 58%   [🗣️ ไปฝึกอ่านชุดนี้]  (mapping     │
│  คำควบกล้ำ     █████░ 64%                          พร้อมแล้ว)   │
└──────────────────────────────────────────────────────────────┘
```
- **Charts:** Line (trend รายรอบ/ปี + เส้นเกณฑ์ 50% ✅), Bar เทียบค่าเฉลี่ย ชั้น/ร.ร./เขต (✅), Horizontal bar หมวดอ่อน (⚠️ รอ per-word — query พร้อมแล้วในข้อ 1.5)
- **Filter:** ปีการศึกษา (พ.ศ.)
- **Drill-down:** ปุ่ม "ไปฝึกอ่าน" → ลิงก์ `practice.php?class_id=&hittest=&sethit=` ของหมวด/ชุดที่อ่อน
- **ทำได้ทันที:** stat cards + line trend + bar เทียบเฉลี่ย; ส่วนหมวดอ่อนรอ per-word
- **RBAC:** นักเรียนเห็น `stuid` ตัวเองเท่านั้น (`WHERE stuid = session.stuid`); ครู/school_admin เปิดดูได้เฉพาะเด็กในโรงเรียนตน

### 3.2 ระดับ "รายชั้น" — `dashboard_class.php?class_id=` (ครู/หัวหน้าชั้น)
ขอบเขตข้อมูล: เฉพาะโรงเรียนตัวเอง (`sc_id = session`) · **ชั้น = ป.1…ป.6 (รวมทุกห้อง ไม่แยกห้อง)**

```
┌───────────────────────────────────────────────────────────────┐
│ 🏫 ป.1 · บ้าน...   [รอบ: Hit-1 ▾][ปี: 2569 ▾]                  │
├───────────────┬───────────────┬───────────────┬───────────────┤
│ นักเรียน 24   │ สอบแล้ว 20    │ เฉลี่ย 14.4   │ ผ่านเกณฑ์ 70% │  ← KPI (ผ่าน=score≥10)
├───────────────┴───────────────┴───────────────┴───────────────┤
│ การกระจายคะแนน (Bar histogram)   │ ความคืบหน้าการสอบ (Donut)  │
│  คน                              │      ┌────┐                 │
│  10┤        ███                  │      │ 83%│ สอบแล้ว 20      │
│   5┤  ██   ███  ███              │      └────┘ ยังไม่สอบ 4     │
│   0└─0-5─6-10─11-15─16-20        │ (เส้นแบ่งผ่านที่ 10)        │
├──────────────────────────────────────────────────────────────┤
│ 🚨 เด็กต้องช่วยเร่งด่วน (ตาราง เรียงคะแนนน้อย→มาก)            │
│ ชื่อ            Hit-1  Hit-2  Hit-3  Δ    สถานะ   [ดูรายคน →] │
│ ด.ช. ก          2/20   –      –     –    🔴      → drill       │
│ ด.ญ. ข          8/20   7/20   –     -1   🟠      → drill       │
└──────────────────────────────────────────────────────────────┘
```
- **Charts:** Bar histogram (✅), Donut completion (✅), ตาราง at-risk (✅ `score<10`)
- **Filter:** รอบ (Hit-1/2/3), ปี, สถานะนักเรียน (`stustatus`) — **ไม่มี filter ห้อง**
- **Drill-down:** คลิกนักเรียน → 3.1 รายบุคคล (drill ลึกสุดที่นี่ — ไม่มีระดับห้อง)
- **ทำได้ทันที:** ทั้งหน้า ✅ (ใช้ `students` + `studenteval`)

### 3.3 ระดับ "รายโรงเรียน" — `dashboard_school.php` (school_admin)
ขอบเขต: `WHERE sc_id = session.sc_id` (โรงเรียนตัวเองเท่านั้น)

```
┌───────────────────────────────────────────────────────────────┐
│ 🏫 บ้าน... · ภาพรวมทั้งโรงเรียน      [รอบ ▾][ปี ▾]             │
├──────────┬──────────┬──────────┬──────────┬───────────────────┤
│ นร. 240  │ เฉลี่ย   │ ผ่าน 68% │ สอบครบ   │ เทียบเขต ▼-3%     │ ← KPI (ผ่าน=≥10)
│          │ 13.6/20  │          │ 81%      │                   │
├──────────┴──────────┴──────────┴──────────┴───────────────────┤
│ เฉลี่ยแต่ละชั้น (Grouped bar: Hit-1/2/3)  │ เทรนด์ข้ามปี (Line)│
│  ป.1 ███▌                                 │ 2567 ●             │
│  ป.2 ████                                 │ 2568   ●──●        │
│  ป.3 ███                                  │ 2569      ●        │
│  ...                                       │                    │
├──────────────────────────────────────────────────────────────┤
│ Heatmap ชั้น × รอบ (% ผ่าน)   │ 🚨 ชั้นที่ต้องเร่ง (rank ระดับชั้น)│
│        Hit-1 Hit-2 Hit-3      │ ป.3  ██  52%   [→ รายชั้น]      │
│  ป.1   🟩    🟨    ⬜          │ ป.5  ██▌ 58%                   │
│  ป.2   🟨    🟧    ⬜          │                                │
└──────────────────────────────────────────────────────────────┘
```
- **Charts:** Grouped bar (ชั้น×รอบ ✅), Line ข้ามปี (✅), Heatmap ชั้น×รอบ (✅ ใช้ matrix หรือ table-CSS), ตาราง rank **ระดับชั้น** (✅)
- **Filter:** รอบ, ปี, ชั้น
- **Drill-down:** คลิกชั้น → 3.2 รายชั้น (ไม่มีระดับห้อง)
- **ทำได้ทันที:** ทั้งหน้า ✅ · "เทียบเขต" ใช้ค่าเฉลี่ยเขตของตน (ไม่โชว์รายชื่อโรงเรียนอื่น)

### 3.4 ระดับ "เขตพื้นที่ (สพป./สพม.)" — `dashboard_area.php` (area_admin)
ขอบเขต: **เฉพาะโรงเรียนในเขตของตน** `WHERE sc_id IN (โรงเรียนที่ area_code = session.area_code)` — **ไม่ใช่ทั้งประเทศ**

```
┌───────────────────────────────────────────────────────────────┐
│ 🗂️ สพป.เชียงราย เขต 3   [ปี ▾][รอบ ▾][อำเภอ ▾][กลุ่ม ร.ร. ▾]  │
├──────────┬──────────┬──────────┬──────────┬───────────────────┤
│ ร.ร.ในเขต│ นร. รวม  │ เฉลี่ย   │ ผ่าน 71% │ สอบครบ 64%        │ ← KPI
│ 142      │ ...      │ 14.1/20  │          │                   │
├──────────┴──────────┴──────────┴──────────┴───────────────────┤
│ % เฉลี่ยรายอำเภอ (Bar)        │ หมวดคำที่อ่อนในเขต (Bar)      │
│  อ.แม่ฟ้าหลวง ████ 73%        │ สระลดรูป   ████░ 58%          │
│  อ.แม่จัน    ███▌ 69%         │ คำควบกล้ำ  █████ 64%          │
│  ...                          │ สะกดไม่ตรงมาตรา █████▌68%     │
├──────────────────────────────────────────────────────────────┤
│ อันดับโรงเรียนในเขต (ตาราง: filter อำเภอ/กลุ่ม)               │
│ # โรงเรียน        อำเภอ       %ผ่าน   จำนวนนร.  [→ รายโรงเรียน]│
│ 1 บ้าน...         แม่จัน      98.5%    ...        (ในเขตเท่านั้น)│
│ การกระจาย % โรงเรียนในเขต (Histogram)                         │
└──────────────────────────────────────────────────────────────┘
```
- **Charts:** Bar รายอำเภอ (✅ `schools.amphures` + agg, กรองในเขต), Bar หมวดอ่อนในเขต (✅ — agg `evaluations`×`words.indicator` เมื่อมี per-word / ระหว่างนี้ใช้ `statscore` เป็น proxy), ตาราง+histogram โรงเรียนในเขต (✅)
- **Filter:** ปี, รอบ, อำเภอ (`amphures`), กลุ่มโรงเรียน (`school_group`) — **ทุก filter ถูกครอบด้วย `area_code` ของตนเสมอ**
- **Drill-down:** อำเภอ → โรงเรียน (ในเขต) → 3.3 รายโรงเรียน
- **ทำได้ทันที:** ✅ เมื่อมี `area_code` ผูกโรงเรียน (ดูข้อ 5); agg สดจาก `studenteval`×`schools` ของเขต (หลักร้อย ร.ร.) เร็วพอ — ระดับทั้งประเทศค่อยใช้ precompute (`statalltest`)
- **หมายเหตุขอบเขต:** ตามการตัดสินใจ ระบบ **ไม่มี view "ทั้งประเทศ"** สำหรับ admin ทั่วไป — แต่ละ area_admin เห็นเฉพาะเขตตน (ถ้าต้องการ super-admin ระดับชาติในอนาคต ค่อยเพิ่ม role พิเศษ)

---

## 4. RBAC Design (สำคัญที่สุด)

### 4.1 สถานะปัจจุบัน (เป็นข้อจำกัด)
- `auth.php` เก็บ session แค่ `sc_id`, `sc_smis`, `sc_name` — **ไม่มี concept ของ role**
- login = "1 บัญชี = 1 โรงเรียน" (`schools.sc_smis`, password = sc_smis)
- ไม่มี login ของ "นักเรียน" และ "ครู" แยก
- `user_admin` (270 บัญชี) = admin แต่ **ยังไม่ถูกใช้ใน auth.php** และ **ยังไม่มี field เขตพื้นที่**
- ⇒ ต้องเพิ่ม **role + scope (รวม `area_code`)** เข้า session model

### 4.2 Role model ที่เสนอ (4 roles ตามการตัดสินใจ)

| role | ดูได้ระดับ | scope filter อัตโนมัติ (เพิ่มในทุก query) | ที่มา identity |
|---|---|---|---|
| `student` | รายบุคคล (ตัวเองเท่านั้น) | `WHERE stuid = :session_stuid` | **(ใหม่) login ด้วย PIN** (ข้อ 4.5) |
| `teacher` | รายชั้น (ในโรงเรียนตน) | `WHERE sc_id = :session_sc_id AND class_id IN (:assigned)` | (ใหม่) ผูกครู↔ชั้น |
| `school_admin` | ทั้งโรงเรียน (ของตน) | `WHERE sc_id = :session_sc_id` | = login ปัจจุบัน (`schools`) |
| `area_admin` | **เฉพาะโรงเรียนในเขตตน (สพป./สพม.)** | `WHERE sc_id IN (SELECT sc_id FROM schools WHERE area_code = :session_area)` | `user_admin` + `area_code` |

> **กติกาเหล็ก 1:** `school_admin`/`teacher`/`student` **ห้ามเห็นข้ามโรงเรียน** — ทุก query ต้องมี `sc_id = session.sc_id` ผูกอัตโนมัติ ห้ามรับ `sc_id` จาก query string มาเชื่อ
>
> **กติกาเหล็ก 2:** `area_admin` **ห้ามเห็นข้ามเขต** — ทุก query ครอบด้วยชุด `sc_id` ที่อยู่ใน `area_code` ของตน; ไม่มี role ใดเห็น "ทั้งประเทศ" (ตามการตัดสินใจข้อ 3)
>
> **กติกาเหล็ก 3 (scope แคบลงเรื่อย ๆ):** `student ⊂ teacher(ชั้น) ⊂ school_admin(ร.ร.) ⊂ area_admin(เขต)` — สิทธิ์เป็น subset เสมอ ไม่มีทางลัดข้ามชั้น

### 4.3 Matrix: role × ระดับ dashboard

| | รายบุคคล | รายชั้น | รายโรงเรียน | ระดับเขต (สพป./สพม.) |
|---|:---:|:---:|:---:|:---:|
| student | ✅ (ตัวเอง) | ❌ | ❌ | ❌ |
| teacher | ✅ (เด็กในชั้นตน) | ✅ (ชั้นที่สอน) | ❌ (หรือ read-only ภาพรวม ร.ร.ตน) | ❌ |
| school_admin | ✅ (ทุกคนใน ร.ร.) | ✅ (ทุกชั้นใน ร.ร.) | ✅ (ร.ร.ตน) | ❌ |
| area_admin | ✅ (ทุกคนในเขต) | ✅ (ทุกชั้นในเขต) | ✅ (ทุก ร.ร.ในเขต) | ✅ (เฉพาะเขตตน) |

> **ไม่มี view ทั้งประเทศ** — `area_admin` ถูกจำกัดที่ `area_code` ของตนเสมอ (รวมระดับรายบุคคล/ชั้น/ร.ร. ที่อยู่ในเขต)

### 4.4 จุดที่ต้อง enforce (2 ชั้น — ห้ามพึ่ง UI อย่างเดียว)

**A. Query layer (บังคับเสมอ — กันรั่วจริง)**
- เพิ่ม helper กลาง เช่น `scope_where(): array` ที่คืน SQL fragment + params ตาม role ใน session
  - `student` → `['stuid = ?', [$_SESSION['stuid']]]`
  - `teacher` → `['sc_id = ? AND class_id IN (...)', [...]]`
  - `school_admin` → `['sc_id = ?', [$_SESSION['sc_id']]]`
  - `area_admin` → `['sc_id IN (SELECT sc_id FROM schools WHERE area_code = ?)', [$_SESSION['area_code']]]`
- **ทุก** dashboard query ต้องเรียก helper นี้ — ห้าม hard-code `sc_id`/`area_code` จาก `$_GET`
- ตรวจ ownership ตอนเข้าหน้า `dashboard_student.php?stuid=X`: ดึงนักเรียนผ่าน `find_student()` ที่มี `AND sc_id = current_sc_id()` อยู่แล้ว (มีใน `functions.php`) → คนนอกโรงเรียนจะได้ null → 403. สำหรับ student role ตรวจเพิ่มว่า `X == session.stuid`

**B. UI layer (ซ่อนเมนู/ปุ่มที่ไม่มีสิทธิ์ — UX เฉย ๆ)**
- เมนู nav แสดงเฉพาะระดับที่ role เข้าได้ (เช่น school_admin ไม่เห็นเมนู "ระดับเขต"; student เห็นแค่หน้าตัวเอง + ฝึกอ่าน)
- filter dropdown "อำเภอ/กลุ่มโรงเรียน" โผล่เฉพาะ `area_admin`
- drill-down link ข้ามโรงเรียนสร้างเฉพาะ `area_admin` (และยังคงอยู่ในเขตตน)

**C. ข้อควรระวัง**
- AJAX endpoint ของ dashboard ต้องผ่าน `auth.php` + ตรวจ scope เหมือนกัน (กันยิง API ตรง)
- `statscore`/`statalltest` เป็นสถิติ "รวม" — ในหน้า school_admin/area_admin ใช้เป็น **ค่าเทียบ (benchmark)** ได้ แต่ **ห้ามโชว์รายชื่อโรงเรียนนอกเขต**; ถ้าจะโชว์อันดับ ต้องกรอง `area_code` ก่อน
- **student role + PIN**: ต้องผ่าน rate-limit ที่ login (ดูข้อ 4.5) — เป็นจุดเปราะที่สุดของระบบ

### 4.5 Student Authentication — login ด้วย PIN (ออกแบบใหม่)

> **⚠️ อัปเดตตอน implement (Phase 3):** ผู้ใช้เลือกให้ **นักเรียน login ด้วย `stuid` (รหัสนักเรียน) เป็นทั้ง username + password** (pattern เดียวกับ login ครูที่ใช้ sc_smis) แทนการ generate PIN — เพราะ `students.stuid` เป็น PK (unique ทั้งระบบ) ระบุตัว+โรงเรียนได้ในตัว ไม่ต้องสร้าง/พิมพ์ PIN
> - **ที่ implement จริง:** `student_login.php` ตรวจ `stuid` กับตาราง `students` + คง throttle (ข้อ 4.5.5) · `teacher_pins.php` กลายเป็นหน้า "พิมพ์การ์ดเข้าระบบ" (รหัสนักเรียน + QR prefill)
> - **Trade-off:** stuid ไม่ใช่ความลับ → posture เท่ากับ login ครู (รับได้เพราะ read-only ของเด็กคนเดียว) · ตาราง `student_pin` + `student_pin_verify()` ยังคงไว้เป็น **ทางเลือกที่ปลอดภัยกว่า** หากต้องการสลับกลับไปใช้ PIN
> - เนื้อหา PIN ด้านล่างเก็บไว้เป็น reference ของทางเลือกนั้น

นักเรียนชั้น ป.1–ป.6 (อ่านยังไม่คล่อง) → ใช้ **PIN ตัวเลข 4–6 หลัก** แทน username/password ปกติ ครูเป็นผู้ออก PIN + พิมพ์แจก

**4.5.1 รูปแบบ login**
- หน้า login นักเรียนแยก เช่น `student_login.php` — กรอก 2 ช่อง: **รหัสโรงเรียน (หรือเลือกจากลิสต์)** + **PIN**
- PIN **unique ต่อโรงเรียน** (ไม่ unique ทั้งประเทศ) → ลด collision และ PIN สั้นพอจำง่าย
  - คู่ที่ระบุตัวตน = `(sc_id, pin)` → 1 นักเรียน
- ไม่ใช้ชื่อ/รหัสนักเรียนจริงเป็นรหัสผ่าน (เด็กพิมพ์ยาก + เดาง่าย)

**4.5.2 ตารางที่ต้องเพิ่ม**
```sql
CREATE TABLE student_pin (
  sc_id       VARCHAR(15) NOT NULL,
  stuid       VARCHAR(50) NOT NULL,
  pin_hash    VARCHAR(255) NOT NULL,     -- เก็บ hash (password_hash) ไม่เก็บ plaintext
  pin_plain   VARCHAR(6)  NULL,          -- (ทางเลือก) เก็บ plaintext ชั่วคราวเพื่อ "พิมพ์แจก" — ถ้าทำต้องลบหลังพิมพ์/หมดอายุ
  is_active   TINYINT(1)  NOT NULL DEFAULT 1,
  created_by  VARCHAR(8)  NULL,          -- ครู/โรงเรียนที่ generate
  created_at  DATETIME    NOT NULL,
  PRIMARY KEY (sc_id, stuid),
  UNIQUE KEY uniq_school_pin (sc_id, pin_hash)   -- unique ต่อโรงเรียน
);
```
> ⚖️ **trade-off plaintext:** เพื่อ "พิมพ์ PIN แจก" ครูต้องเห็น PIN ได้ — ทางเลือก:
> (ก) เก็บ `pin_hash` อย่างเดียว + ตอน generate โชว์/พิมพ์ทันทีครั้งเดียว (ปลอดภัยสุด) หรือ
> (ข) เก็บ `pin_plain` ไว้ให้พิมพ์ซ้ำได้ แต่จำกัดสิทธิ์ดูเฉพาะครูในโรงเรียนนั้น + เข้ารหัส at-rest
> — แนะนำ (ก) สำหรับความปลอดภัย; ถ้าครูต้องพิมพ์ซ้ำบ่อยค่อยพิจารณา (ข)

**4.5.3 การ generate PIN (ครูทำ)**
- หน้า `student_pin_admin.php` (เฉพาะ teacher/school_admin) → เลือกชั้น → ปุ่ม "สร้าง PIN ทั้งห้อง/ชั้น"
- อัลกอริทึม: สุ่มเลข 4–6 หลัก, ตรวจชนกับ PIN ที่ active ใน `sc_id` เดียวกัน, retry ถ้าชน
- รองรับ "regenerate" รายคน (กรณี PIN หาย/รั่ว) — PIN เก่า inactive ทันที

**4.5.4 หน้า "พิมพ์ PIN นักเรียนทั้งชั้น" (ครู)**
- `student_pin_print.php?class_id=` → layout การ์ดตัดได้ (ใช้ CSS `@media print`)
- แต่ละการ์ด: ชื่อนักเรียน + ชั้น + **PIN ตัวใหญ่** + **QR code** (encode `sc_id` + PIN หรือ deep link `student_login.php?sc=...&pin=...`)
  - QR ใช้ lib ฝั่ง client เบา ๆ เช่น `qrcodejs` (CDN) หรือ generate ฝั่ง PHP — ไม่ต้องเพิ่ม framework
  - เด็กเล็กสแกน QR แทนพิมพ์ได้ → ลดปัญหาพิมพ์ผิด

```
┌─────────────────┐ ┌─────────────────┐
│ ด.ช. ก  ป.1     │ │ ด.ญ. ข  ป.1     │
│  PIN: 4823      │ │  PIN: 7156      │   ← การ์ดตัดแจก (พิมพ์)
│  [▦ QR]         │ │  [▦ QR]         │
└─────────────────┘ └─────────────────┘
```

**4.5.5 Throttle / rate-limit (สำคัญ — PIN 4 หลักเดาง่าย)**
- PIN 4 หลัก = 10,000 ความเป็นไปได้ → ต้องกัน brute force หลายชั้น:
  1. **นับครั้งผิดต่อ (sc_id + IP)** — เกิน N ครั้ง (เช่น 5) ใน 15 นาที → lock ชั่วคราว / ขึ้น CAPTCHA
  2. **หน่วงเวลา (exponential backoff)** — ผิดครั้งที่ k → รอ `min(2^k, 30)` วินาที
  3. **ล็อกรายบัญชี** — PIN เดียวผิดติดกันหลายครั้ง → แจ้งครูให้ regenerate
  4. **บังคับเลือก/ระบุโรงเรียนก่อน** → จำกัด search space เหลือเฉพาะ PIN ในโรงเรียนนั้น (ไม่ใช่ทั้งประเทศ) แต่ก็ยังต้อง throttle
- ตารางช่วย: `login_throttle(scope_key, fail_count, locked_until, updated_at)` — `scope_key = sc_id|ip`
- แนะนำ PIN **6 หลัก** ถ้าเป็นไปได้ (1,000,000 ความเป็นไปได้) — ปลอดภัยกว่ามากโดยเด็กยังจำ/สแกน QR ได้
- บันทึก audit log การ login สำเร็จ/ล้มเหลว (ไว้สอบสวนกรณีรั่ว)

**4.5.6 ขอบเขตสิทธิ์ของนักเรียน (กันใช้ PIN ทำเกินหน้าที่)**
- session ของ student เก็บ `role=student`, `sc_id`, `stuid` เท่านั้น
- เข้าได้แค่: หน้า `dashboard_student.php` (ของตัวเอง) + `practice.php` (ฝึกอ่าน)
- **ห้าม** เข้า dashboard ชั้น/ร.ร./เขต และห้ามแก้ไขข้อมูลใด ๆ (read-only + ฝึกอ่าน)

---

## 5. ข้อมูลที่ยังขาด (ต้องเก็บเพิ่มก่อนทำบาง metric)

| # | สิ่งที่ขาด | กระทบ metric | ข้อเสนอ |
|---|---|---|---|
| 1 | **per-word results ในระบบจริง** (`evaluations`/`studenthit` แทบว่าง) | หมวดคำที่อ่อนรายคน/ชั้น/ร.ร., คำที่ผิดบ่อย | ให้หน้าสอบ (teacher_page/paper_import) เขียน `evaluations` ครบทุกครั้ง (โค้ด `save_hit_result()` เขียนอยู่แล้ว — ตรวจว่าถูกเรียกจริงทุก path) |
| 2 | **role/scope model** | RBAC 4 ระดับทั้งหมด | เพิ่มตาราง `app_user(user_id, role, sc_id, area_code)` + `teacher_class(user_id, class_id)` และแก้ `auth.php` ให้เซ็ต `$_SESSION['role']`,`['scope']` (ไม่ต้องมีมิติ `rooms` ตามการตัดสินใจ) |
| 3 | **🆕 `area_code` ผูกโรงเรียน↔เขต (สพป./สพม.)** | dashboard ระดับเขต + scope ของ `area_admin` | เพิ่ม field `area_code`/`district_id` ใน `schools` (หรือ `school`) + ผูก `user_admin.area_code`; ต้องหา/นำเข้าข้อมูล mapping โรงเรียน→เขต (อาจ derive จาก `sao_code`/`provinces`/`amphures` ที่มี หรือ import ทะเบียน สพฐ.) |
| 4 | **🆕 student login (PIN)** | dashboard รายบุคคล (เด็ก login เอง หลายคน) | สร้าง `student_pin` + `login_throttle` + หน้า generate/print PIN (ดูข้อ 4.5) |
| 5 | ~~mapping คำ → หมวด~~ ✅ **แก้แล้ว** | หมวดอ่อนรายคน | **ยืนยันแล้ว: `words.indicator = word_category.catid` (1:1)** — ไม่ต้องเพิ่ม FK; query พร้อมในข้อ 1.5 |
| 6 | **timestamp ของ `evaluations`** | trend รายคำตามเวลา | ใช้ `studenteval.testtime` แทนได้ในระดับรอบ; ถ้าต้องการละเอียดให้เพิ่มคอลัมน์ |
| 7 | **stuid type mismatch** | join `students`(varchar) × `studenteval`(bigint) | ทำ `CAST`/normalize หรือ migrate ให้ type ตรงกัน (ป้องกัน join พลาด + index ไม่ทำงาน) |
| 8 | **precompute/cache ระดับเขต** | dashboard เขต (agg หลายร้อย ร.ร. × หลายหมื่น rows) | agg สดต่อเขตเร็วพอ; ถ้าช้าใช้ `statalltest`/`statscore` + cron refresh หรือสร้าง summary table (ปี,รอบ,ชั้น,ร.ร.) |
| 9 | **log การฝึก (practice.php)** | metric "ความถี่/เวลาที่ฝึก" | (อนาคต) ตาราง `practice_log(stuid, word_id, correct, ts)` — ตอนนี้ไม่มี |
| 10 | ~~เกณฑ์ผ่าน~~ ✅ **ตัดสินแล้ว = 50%** | อัตราผ่านเกณฑ์ | กำหนด `const PASS_SCORE = 10;` ใน `config.php` (50% ของ 20) แล้วอ้างทุกที่ |

---

## 6. Tech Stack ที่เสนอ (ใช้ของเดิม ไม่เพิ่ม framework ใหญ่)

| ชั้น | เลือกใช้ | เหตุผล |
|---|---|---|
| Backend | **PHP 8.1 + PDO (MySQL)** เดิม | ใช้ `includes/db.php`, `functions.php`, `auth.php` ที่มีอยู่ |
| Pattern | หน้า `.php` ต่อ dashboard + endpoint AJAX คืน JSON | ตรงกับ pattern เดิม (`save_results.php`, `tts.php`) |
| CSS/UI | **Bootstrap 5.3.8 + theme.css** เดิม | มี grid/utility/การ์ด `.ht-card`,`.ht-badge` พร้อม |
| Charts | **Chart.js v4 (CDN)** — *เพิ่มใหม่* | เบา ~70KB, ไม่ต้อง build; รองรับ bar/line/donut; heatmap ใช้ table-CSS หรือ `chartjs-chart-matrix` (ถ้าจำเป็น) |
| Map (ระดับเขต) | เริ่มด้วย **Bar รายอำเภอในเขต** ก่อน; choropleth ค่อยเพิ่มทีหลัง (มี `lat/lng` ใน `province`/`schools`) | ลด complexity Phase แรก |
| QR code (พิมพ์ PIN) | **qrcodejs (CDN)** หรือ generate ฝั่ง PHP — *เพิ่มใหม่ เฉพาะหน้า print PIN* | เด็กเล็กสแกนแทนพิมพ์ (ดูข้อ 4.5.4) |
| Popup/feedback | **SweetAlert2 v11** เดิม | โหลดใน `header.php` อยู่แล้ว |
| Export (อนาคต) | CSV ด้วย PHP เดิม / ไม่ต้องเพิ่ม lib | reuse pattern `paper_export.php` |

> เพิ่มแค่ Chart.js (ทุกหน้า dashboard) + qrcodejs (เฉพาะหน้า print PIN) ผ่าน CDN — ไม่มี npm/build step ใหม่

---

## 7. Phase Plan (แบ่งเล็ก ส่งมอบเป็นชิ้น)

ลำดับตาม **ความพร้อมของข้อมูล + auth** (ไม่ใช่ตามหมายเลขระดับ) เพื่อให้เห็นผลเร็ว:

### Phase 0 — เตรียมฐาน (1 งานเล็ก)
- เพิ่ม Chart.js CDN, ตั้ง `const PASS_SCORE = 10;` ใน `config.php`, ทำ helper `scope_where()` + `dashboard_query.php` (ฟังก์ชัน agg กลาง), แก้ `auth.php` ใส่ `$_SESSION['role']` (เริ่มจาก map ทุก login เดิม = `school_admin`)

### Phase 1 — Dashboard รายโรงเรียน (`school_admin`) ⭐ เริ่มที่นี่
- ข้อมูลพร้อม 100% (`students` + `studenteval`), auth พร้อม (= login ปัจจุบัน), scope ง่าย (`sc_id = session`)
- ส่งมอบ: KPI cards (รวมผ่าน≥10), grouped bar ชั้น×รอบ, line ข้ามปี, ตาราง rank **ระดับชั้น**, at-risk
- ได้คุณค่าทันทีต่อผู้ใช้ปัจจุบัน

### Phase 2 — Dashboard รายชั้น + drill-down (`teacher` view)
- ต่อยอดจาก Phase 1: histogram, donut completion, ตาราง at-risk รายคน (เรียงคะแนน)
- เพิ่ม role `teacher` + ผูกครู↔ชั้น (ตาราง `teacher_class`) — หรือให้ school_admin ใช้ filter ชั้นไปก่อน
- **ไม่มีระดับห้อง** — รายชั้นรวมทุกห้อง

### Phase 3 — Dashboard รายบุคคล + Student login (PIN)
- stat cards + line trend (+ เส้นเกณฑ์ 50%) + bar เทียบค่าเฉลี่ย (ทำได้ทันที)
- (รอ per-word) ส่วน "หมวดคำที่ต้องฝึก" + ปุ่มลิงก์ `practice.php`
- **Student auth (ข้อ 4.5):** ตาราง `student_pin` + `login_throttle`, หน้า generate + print PIN (QR), `student_login.php` พร้อม rate-limit
- ครูเปิดดูแทนได้ด้วย (drill จาก Phase 2)

### Phase 4 — เก็บ per-word ให้ครบ (data enablement)
- ตรวจ/แก้ทุก path การสอบให้เขียน `evaluations` ครบ (mapping `words.indicator = word_category.catid` พร้อมแล้ว — ข้อ 1.5)
- ปลดล็อก metric หมวดอ่อน/คำผิดบ่อย ทุกระดับ (เติมกลับเข้า Phase 1–3)

### Phase 5 — Dashboard ระดับเขต (`area_admin`)
- **ต้องมาก่อน:** เพิ่ม `area_code` ใน `schools` + นำเข้า mapping โรงเรียน→เขต (สพป./สพม.) + ผูก `user_admin.area_code` (ข้อ 5 #3)
- เชื่อม `user_admin` เข้า auth + role `area_admin` (scope = เฉพาะเขตตน)
- bar รายอำเภอในเขต, หมวดอ่อนในเขต, อันดับโรงเรียนในเขต; agg สดต่อเขต (ถ้าช้าใช้ precompute)
- (ทีหลัง) choropleth map ระดับเขต

---

## 8. สรุปการตัดสินใจที่ยืนยันแล้ว (ปิดคำถามรอบก่อน)

| # | คำถามเดิม | ✅ การตัดสินใจ | ผลต่อ design |
|---|---|---|---|
| 1 | login นักเรียน? | **มี — นักเรียน login ได้หลายคน ด้วย PIN 4–6 หลัก** ครู generate + พิมพ์แจก | เพิ่มข้อ 4.5 (student auth), Phase 3 |
| 2 | เกณฑ์ผ่าน? | **50% = `score ≥ 10`** (`PASS_SCORE=10`) | KPI/at-risk ทุกระดับ, เส้นเกณฑ์ในกราฟ |
| 3 | ขอบเขต admin? | **ระดับเขต (สพป./สพม.) เห็นเฉพาะเขตตน — ไม่มี view ทั้งประเทศ** | role `area_admin` + `area_code`, ข้อ 3.4/4.2 |
| 4 | mapping หมวดคำ? | **`words.indicator = word_category.catid` (1:1) — ยืนยันจาก DB** | ข้อ 1.5, ปลด ⚠️ mapping |
| 5 | drill ถึงห้อง? | **ไม่ — ลึกสุดที่ระดับชั้น (ป.1…ป.6)** | ตัดมิติ `rooms` ออกจากทุก wireframe/filter |

**ยังต้องเก็บข้อมูลเพิ่มก่อน implement บางส่วน** (ดูข้อ 5): per-word (`evaluations`), `area_code` ผูกเขต, ตาราง student PIN — ที่เหลือ (รายโรงเรียน/ชั้น/บุคคลแบบคะแนนรวม) ทำได้ทันที

---

*เอกสารนี้อิงข้อมูลจริงจาก DB `ssraexhi_hittest` ณ วันที่จัดทำ — ตัวเลขแถว/ค่าเฉลี่ยเป็นค่าจาก local snapshot อาจต่างจาก production*
