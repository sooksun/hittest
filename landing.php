<?php
/**
 * landing.php — หน้า Landing สาธารณะ (one page) ของระบบ HIT-TEST
 * สังเคราะห์จากเอกสารแนวทางยกระดับการอ่านภาษาไทย รร.พื้นที่ลักษณะพิเศษ (สพฐ. 2567–2570)
 * เชื่อมเข้าระบบเดิมผ่านปุ่ม "เข้าสู่ระบบ" → login.php
 */
session_start();
$css = 'assets/css/theme.css?v=' . (@filemtime(__DIR__ . '/assets/css/theme.css') ?: '0');
$loggedIn = !empty($_SESSION['sc_id']);
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>HIT-TEST — ยกระดับการอ่านภาษาไทย โรงเรียนพื้นที่ลักษณะพิเศษ</title>
    <meta name="description" content="ระบบประเมินความสามารถการอ่านภาษาไทย (HIT) สำหรับนักเรียน ป.1–ป.6 โรงเรียนพื้นที่สูงในถิ่นทุรกันดารและโรงเรียนพื้นที่เกาะ ตามแนวทาง สพฐ.">
    <link rel="icon" href="images/newlogo.png" type="image/png">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= $css ?>" rel="stylesheet">
    <style>
        html { scroll-behavior: smooth; }
        body { background: var(--bg-sky); }

        /* ---- navbar ---- */
        .lp-nav { position: sticky; top: 0; z-index: 50; background: rgba(255,255,255,.86); backdrop-filter: blur(10px); border-bottom: 1px solid var(--line); }
        .lp-nav__inner { max-width: 1180px; margin: 0 auto; padding: 11px 24px; display: flex; align-items: center; gap: 14px; }
        .lp-brand { display: flex; align-items: center; gap: 10px; font-weight: 800; font-size: 1.15rem; color: var(--ink); }
        .lp-brand__logo { width: 42px; height: 42px; border-radius: 14px; display: grid; place-items: center; background: var(--c-blue-soft); font-size: 22px; }
        .lp-nav__links { margin-left: auto; display: flex; align-items: center; gap: 6px; }
        .lp-nav__links a.ln { padding: 8px 14px; border-radius: var(--r-pill); font-weight: 700; color: var(--ink-soft); }
        .lp-nav__links a.ln:hover { background: var(--c-blue-soft); color: var(--c-blue-ink); text-decoration: none; }

        /* ---- hero ---- */
        .lp-hero { position: relative; color: #fff; padding: 100px 24px 116px; text-align: center; overflow: hidden;
            background: #241c3c url('images/imagebackground.jpg') center/cover no-repeat; }
        .lp-hero__video { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; z-index: 1; opacity: 0; transition: opacity .8s ease; }
        .lp-hero__video.is-on { opacity: 1; }
        .lp-hero::after { content: ""; position: absolute; inset: 0; z-index: 2;
            background: linear-gradient(158deg, rgba(24,36,78,.80) 0%, rgba(45,28,92,.74) 60%, rgba(120,40,110,.70) 100%); }
        .lp-hero__inner { max-width: 880px; margin: 0 auto; position: relative; z-index: 3; }
        .lp-eyebrow { display: inline-block; background: rgba(255,255,255,.16); border: 1px solid rgba(255,255,255,.38); padding: 7px 18px; border-radius: var(--r-pill); font-weight: 700; font-size: .9rem; margin-bottom: 22px; }
        .lp-hero h1 { color: #fff; font-size: clamp(2rem, 5vw, 3.3rem); line-height: 1.15; margin-bottom: 18px; }
        .lp-hero p { color: rgba(255,255,255,.94); font-size: clamp(1rem, 2vw, 1.2rem); margin: 0 auto 32px; max-width: 720px; }
        .lp-hero__cta { display: flex; gap: 14px; justify-content: center; flex-wrap: wrap; }
        .lp-btn-light { background: #fff; color: var(--c-blue-ink); border: none; border-radius: var(--r-pill); font-weight: 800; padding: 15px 32px; font-size: 1.05rem; box-shadow: 0 10px 24px rgba(0,0,0,.22); transition: transform .15s var(--ease); }
        .lp-btn-light:hover { transform: translateY(-2px); color: var(--c-blue-ink); text-decoration: none; }
        .lp-btn-outline { background: transparent; color: #fff; border: 2px solid rgba(255,255,255,.7); border-radius: var(--r-pill); font-weight: 700; padding: 13px 28px; font-size: 1.05rem; }
        .lp-btn-outline:hover { background: rgba(255,255,255,.15); color: #fff; text-decoration: none; }

        /* ---- sections ---- */
        .lp-section { max-width: 1180px; margin: 0 auto; padding: 74px 24px; }
        .lp-section h2 { font-size: clamp(1.6rem, 3.6vw, 2.2rem); text-align: center; margin-bottom: 12px; }
        .lp-lead { text-align: center; color: var(--ink-soft); max-width: 720px; margin: 0 auto 46px; font-size: 1.06rem; }

        /* ---- stats band ---- */
        .lp-stats { background: var(--surface); border-top: 1px solid var(--line); border-bottom: 1px solid var(--line); }
        .lp-stats__grid { max-width: 1080px; margin: 0 auto; padding: 46px 24px; display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; }
        .lp-stat { text-align: center; }
        .lp-stat__num { font-size: clamp(2rem, 4vw, 2.7rem); font-weight: 800; line-height: 1; }
        .lp-stat__label { color: var(--ink-soft); margin-top: 10px; font-weight: 600; font-size: .98rem; }

        /* ---- cards ---- */
        .lp-cards { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; }
        .lp-cards--2 { grid-template-columns: repeat(2, 1fr); max-width: 860px; margin: 0 auto; }
        .lp-card { background: var(--surface); border-radius: var(--r-lg); box-shadow: var(--sh-md); padding: 30px; border-top: 5px solid var(--t, var(--c-blue)); height: 100%; }
        .lp-card__icon { font-size: 2.2rem; line-height: 1; margin-bottom: 12px; }
        .lp-card h3 { font-size: 1.2rem; margin-bottom: 8px; }
        .lp-card p { color: var(--ink-soft); margin: 0; }

        /* ---- process steps ---- */
        .lp-steps { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; counter-reset: step; }
        .lp-step { background: var(--surface); border-radius: var(--r-lg); box-shadow: var(--sh-sm); padding: 24px 20px; position: relative; }
        .lp-step::before { counter-increment: step; content: counter(step); position: absolute; top: -16px; left: 20px; width: 36px; height: 36px; border-radius: 50%; background: var(--c-blue); color: #fff; font-weight: 800; display: grid; place-items: center; box-shadow: var(--sh-sm); }
        .lp-step h4 { margin: 10px 0 6px; font-size: 1.05rem; }
        .lp-step p { color: var(--ink-soft); margin: 0; font-size: .94rem; }

        /* ---- objective list ---- */
        .lp-obj { background: var(--surface); border-radius: var(--r-lg); box-shadow: var(--sh-md); padding: 30px; }
        .lp-obj li { margin-bottom: 12px; line-height: 1.6; }
        .lp-goal { background: var(--c-green-soft); color: var(--c-green-ink); border-radius: var(--r-lg); padding: 28px; text-align: center; font-weight: 700; font-size: 1.15rem; }

        /* ---- final cta ---- */
        .lp-cta-wrap { max-width: 1180px; margin: 0 auto; padding: 0 24px 84px; }
        .lp-cta { background: linear-gradient(150deg, var(--c-blue) 0%, var(--c-purple) 100%); color: #fff; text-align: center; border-radius: var(--r-xl); padding: 60px 28px; box-shadow: var(--sh-lg); }
        .lp-cta h2 { color: #fff; font-size: clamp(1.7rem, 4vw, 2.4rem); margin-bottom: 12px; }
        .lp-cta p { color: rgba(255,255,255,.92); font-size: 1.1rem; margin-bottom: 28px; }

        /* ---- footer ---- */
        .lp-footer { background: var(--ink); color: rgba(255,255,255,.78); padding: 38px 24px; text-align: center; font-size: .92rem; line-height: 1.8; }
        .lp-footer strong { color: #fff; }

        @media (max-width: 900px) {
            .lp-stats__grid { grid-template-columns: repeat(2, 1fr); gap: 30px 20px; }
            .lp-cards, .lp-steps { grid-template-columns: 1fr; }
            .lp-cards--2 { grid-template-columns: 1fr; }
            .lp-step::before { top: -14px; }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="lp-nav">
        <div class="lp-nav__inner">
            <span class="lp-brand"><img src="images/newlogo.png" alt="HIT-TEST" style="height:46px;width:46px;object-fit:contain;display:block"> <span style="font-weight:800;color:var(--ink)">HIT-TEST</span></span>
            <div class="lp-nav__links">
                <a class="ln d-none d-md-inline" href="#about">เกี่ยวกับ HIT</a>
                <a class="ln d-none d-md-inline" href="#process">กระบวนการ</a>
                <a class="ln d-none d-md-inline" href="#features">ความสามารถ</a>
                <a class="ht-btn ht-btn--sm" href="<?= $loggedIn ? 'menu.php' : 'login.php' ?>"><?= $loggedIn ? 'ไปที่ระบบ →' : 'เข้าสู่ระบบ →' ?></a>
            </div>
        </div>
    </nav>

    <!-- Hero -->
    <header class="lp-hero">
        <video class="lp-hero__video" muted loop playsinline preload="none" data-src="images/Hittest2026.mp4"></video>
        <div class="lp-hero__inner">
            <span class="lp-eyebrow">สำนักงานคณะกรรมการการศึกษาขั้นพื้นฐาน (สพฐ.)</span>
            <h1>ยกระดับการอ่านภาษาไทย<br>โรงเรียนพื้นที่ลักษณะพิเศษ</h1>
            <p>ระบบประเมินความสามารถการอ่าน <strong>HIT — Highlander and Island School Reading Test</strong>
               สำหรับนักเรียน ป.1–ป.6 โรงเรียนพื้นที่สูงในถิ่นทุรกันดารและโรงเรียนพื้นที่เกาะ
               คัดกรองเป็นรายบุคคล วัดพัฒนาการ 3 รอบต่อปี เพื่อเร่งรัดให้อ่านออก อ่านคล่อง เขียนคล่อง</p>
            <div class="lp-hero__cta">
                <a class="lp-btn-light" href="<?= $loggedIn ? 'menu.php' : 'login.php' ?>"><?= $loggedIn ? 'เข้าใช้งานระบบ →' : '👩‍🏫 ครู / ผู้ดูแล →' ?></a>
                <?php if (!$loggedIn): ?><a class="lp-btn-outline" href="student_login.php">🧒 ฉันเป็นนักเรียน →</a><?php endif; ?>
                <a class="lp-btn-outline" href="#about">ทำความรู้จัก HIT</a>
            </div>
        </div>
    </header>

    <!-- Stats / why -->
    <section class="lp-stats">
        <div class="lp-stats__grid">
            <div class="lp-stat"><div class="lp-stat__num" style="color:var(--c-coral-ink)">67.3%</div><div class="lp-stat__label">รร.พื้นที่พิเศษ มีผล RT ต่ำกว่าค่าเฉลี่ยประเทศ (ปี 2565)</div></div>
            <div class="lp-stat"><div class="lp-stat__num" style="color:var(--c-green-ink)">≥ 3%</div><div class="lp-stat__label">เป้าหมายพัฒนาการอ่านเพิ่มขึ้นต่อปี</div></div>
            <div class="lp-stat"><div class="lp-stat__num" style="color:var(--c-blue-ink)">ป.1–6</div><div class="lp-stat__label">ครอบคลุมทุกระดับชั้นประถมศึกษา</div></div>
            <div class="lp-stat"><div class="lp-stat__num" style="color:var(--c-purple-ink)">3 รอบ</div><div class="lp-stat__label">HIT-1 / HIT-2 / HIT-3 ต่อปีการศึกษา</div></div>
        </div>
    </section>

    <!-- About HIT -->
    <section class="lp-section" id="about">
        <h2>HIT คืออะไร</h2>
        <p class="lp-lead"><strong>HIT (Highlander and Island School Reading Test)</strong> คือการประเมินความสามารถการอ่านภาษาไทยของนักเรียน ป.1–ป.6
           โรงเรียนที่ตั้งในพื้นที่ลักษณะพิเศษ สังกัด สพฐ. — ประเมินด้วยบัญชีคำพื้นฐานภาษาไทย เป็นรายบุคคล
           แนะนำให้คัดกรองอย่างน้อยภาคเรียนละ 1 ครั้ง เพื่อเปรียบเทียบพัฒนาการและช่วยเหลือได้ทันท่วงที</p>
        <div class="lp-cards">
            <div class="lp-card" style="--t:var(--c-blue)"><div class="lp-card__icon">📕</div><h3>HIT-1 · ครั้งที่ 1</h3><p>คัดกรองความสามารถการอ่านต้นภาคเรียน เพื่อวิเคราะห์สภาพปัญหาของนักเรียนรายบุคคล</p></div>
            <div class="lp-card" style="--t:var(--c-green)"><div class="lp-card__icon">📗</div><h3>HIT-2 · ครั้งที่ 2</h3><p>ประเมินซ้ำระหว่างทาง ติดตามพัฒนาการหลังจัดกิจกรรมพัฒนาการอ่าน</p></div>
            <div class="lp-card" style="--t:var(--c-purple)"><div class="lp-card__icon">📘</div><h3>HIT-3 · ครั้งที่ 3</h3><p>ประเมินปลายภาค สรุปพัฒนาการเทียบกับรอบก่อนหน้า วางแผนช่วยเหลือต่อเนื่อง</p></div>
        </div>
    </section>

    <!-- Objectives & goal -->
    <section class="lp-section" style="padding-top:0">
        <h2>วัตถุประสงค์ &amp; เป้าหมาย</h2>
        <p class="lp-lead">ตามแนวทางยกระดับและพัฒนาคุณภาพการอ่านภาษาไทย สำหรับโรงเรียนพื้นที่ลักษณะพิเศษ ปีงบประมาณ 2567–2570</p>
        <div class="row g-4 align-items-stretch">
            <div class="col-lg-7">
                <div class="lp-obj h-100">
                    <h3 class="mb-3">วัตถุประสงค์</h3>
                    <ol class="mb-0" style="padding-left:20px">
                        <li>ส่งเสริมการยกระดับและพัฒนาการอ่านภาษาไทยของนักเรียนในโรงเรียนพื้นที่ลักษณะพิเศษ ให้มีความสามารถในการอ่านตามเกณฑ์ของแต่ละระดับชั้น</li>
                        <li>ส่งเสริมให้ผู้บริหาร ครู และผู้เกี่ยวข้องทุกระดับ เห็นความสำคัญ ตระหนัก และรับผิดชอบในการเร่งรัดให้นักเรียนอ่านออก อ่านคล่อง เขียนคล่อง อย่างยั่งยืน</li>
                    </ol>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="lp-goal h-100 d-flex flex-column justify-content-center">
                    <div style="font-size:2.4rem;line-height:1">🎯</div>
                    <div class="mt-2">นักเรียน ป.1–ป.6 โรงเรียนพื้นที่ลักษณะพิเศษ<br>มีผลการพัฒนาการอ่าน<br><span style="font-size:1.5rem">เพิ่มขึ้นไม่น้อยกว่าร้อยละ 3</span></div>
                </div>
            </div>
        </div>
    </section>

    <!-- Process -->
    <section class="lp-section" id="process" style="padding-top:0">
        <h2>กระบวนการดำเนินงาน 4 ระดับ</h2>
        <p class="lp-lead">ขับเคลื่อนสอดคล้องกันตั้งแต่ระดับนโยบายถึงห้องเรียน โดยใช้ข้อมูลการประเมินรายบุคคลเป็นฐาน</p>
        <div class="lp-steps">
            <div class="lp-step"><h4>สพฐ.</h4><p>กำหนดนโยบาย จุดเน้น เป้าหมาย กรอบงบประมาณ และปฏิทินการดำเนินงาน</p></div>
            <div class="lp-step"><h4>เขตพื้นที่การศึกษา</h4><p>กำหนดเป้าหมายระดับเขต จัดสรรงบ นิเทศ กำกับ ติดตามโรงเรียน</p></div>
            <div class="lp-step"><h4>สถานศึกษา</h4><p>ประเมินการอ่านด้วยเครื่องมือ HIT เลือกนวัตกรรมพัฒนาให้เหมาะกับบริบท</p></div>
            <div class="lp-step"><h4>ห้องเรียน</h4><p>ครูวิเคราะห์ผลรายบุคคล จัดการเรียนรู้เชิงรุก (Active Learning) แล้วประเมินซ้ำ</p></div>
        </div>
    </section>

    <!-- Features of this system -->
    <section class="lp-section" id="features" style="padding-top:0">
        <h2>ความสามารถของระบบ</h2>
        <p class="lp-lead">เครื่องมือดิจิทัลสำหรับครู ใช้ประเมินการอ่านได้ทันที พร้อมสรุปผลและจัดการข้อมูลแยกเป็นรายโรงเรียน</p>
        <div class="lp-cards">
            <div class="lp-card" style="--t:var(--c-blue)"><div class="lp-card__icon">🖥️</div><h3>สอบด้วยคอมพิวเตอร์</h3><p>จอครูควบคุมคำ + จอนักเรียนแสดงคำใหญ่ sync เรียลไทม์ จับเวลา บันทึกผลรายข้อ 20 คำ</p></div>
            <div class="lp-card" style="--t:var(--c-green)"><div class="lp-card__icon">📝</div><h3>สอบด้วยกระดาษ</h3><p>ดาวน์โหลดแบบกรอกคะแนน Excel ทั้งโรงเรียน กรอกแล้วอัปโหลดกลับเข้าระบบอัตโนมัติ</p></div>
            <div class="lp-card" style="--t:var(--c-coral)"><div class="lp-card__icon">📊</div><h3>สรุปผลหลายมิติ</h3><p>Dashboard ภาพรวมโรงเรียน รายชั้น รายบุคคล และผลแยกตามตัวชี้วัดการอ่าน</p></div>
            <div class="lp-card" style="--t:var(--c-purple)"><div class="lp-card__icon">🚦</div><h3>จัดการรอบสอบ</h3><p>เปิด/ปิดการสอบ HIT-1/2/3 ของโรงเรียนได้เอง ควบคุมช่วงเวลาประเมินอย่างยืดหยุ่น</p></div>
            <div class="lp-card" style="--t:var(--c-yellow)"><div class="lp-card__icon">👥</div><h3>จัดการนักเรียน</h3><p>นำเข้ารายชื่อด้วย Excel แก้ไขรายคน และเลื่อนชั้นทั้งโรงเรียนเมื่อขึ้นปีการศึกษาใหม่</p></div>
            <div class="lp-card" style="--t:var(--c-blue)"><div class="lp-card__icon">🔒</div><h3>แยกข้อมูลรายโรงเรียน</h3><p>เข้าระบบด้วยรหัส SMIS โรงเรียน เห็นและจัดการได้เฉพาะข้อมูลของโรงเรียนตนเอง</p></div>
        </div>
    </section>

    <!-- Final CTA -->
    <div class="lp-cta-wrap">
        <div class="lp-cta">
            <h2>พร้อมเริ่มประเมินการอ่านแล้วหรือยัง?</h2>
            <p>เข้าสู่ระบบด้วยรหัส SMIS ของโรงเรียน เพื่อเริ่มคัดกรองและพัฒนาการอ่านของนักเรียน</p>
            <a class="lp-btn-light" href="<?= $loggedIn ? 'menu.php' : 'login.php' ?>"><?= $loggedIn ? 'เข้าใช้งานระบบ →' : 'เข้าสู่ระบบ →' ?></a>
        </div>
    </div>

    <!-- Footer -->
    <footer class="lp-footer">
        <div><strong>HIT-TEST · ระบบประเมินการอ่านภาษาไทย</strong></div>
        <div>โครงการยกระดับและพัฒนาคุณภาพการอ่านภาษาไทย สำหรับโรงเรียนที่ตั้งในพื้นที่ลักษณะพิเศษ
             (โรงเรียนพื้นที่สูงในถิ่นทุรกันดารและโรงเรียนพื้นที่เกาะ)</div>
        <div>สำนักงานคณะกรรมการการศึกษาขั้นพื้นฐาน · ปีงบประมาณ 2567–2570</div>
    </footer>

    <script>
    /* hero พื้นหลัง: แสดงรูปก่อน แล้วอัปเกรดเป็นวิดีโอเมื่อเน็ตเร็วพอ (เน็ตช้า/พัง → คงรูป imagebackground.jpg) */
    (function () {
        var v = document.querySelector('.lp-hero__video');
        if (!v || !v.dataset.src) return;
        var TIMEOUT_MS = 4000;
        function slowConnection() {
            var c = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
            if (!c) return false;                         // เบราว์เซอร์ไม่รองรับ → ลองโหลด (มี timeout กันไว้)
            if (c.saveData) return true;                  // โหมดประหยัดเน็ต
            if (c.effectiveType && /(slow-2g|2g|3g)/.test(c.effectiveType)) return true;
            if (typeof c.downlink === 'number' && c.downlink > 0 && c.downlink < 1.5) return true; // < 1.5 Mbps
            return false;
        }
        if (slowConnection()) return;

        var settled = false;
        var timer = setTimeout(function () {
            if (settled) return;
            settled = true;
            v.removeAttribute('src'); v.load();           // ช้าเกินไป → ยกเลิกโหลดวิดีโอ คงรูปไว้
        }, TIMEOUT_MS);
        v.addEventListener('canplaythrough', function () {
            if (settled) return;
            settled = true; clearTimeout(timer);
            v.classList.add('is-on');
            v.play().catch(function () {});
        }, { once: true });
        v.addEventListener('error', function () {
            if (settled) return;
            settled = true; clearTimeout(timer);
        }, { once: true });
        v.src = v.dataset.src;
        v.load();
    })();
    </script>
</body>
</html>
