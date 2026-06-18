<?php
/**
 * login.php — เข้าสู่ระบบด้วยรหัส SMIS ของโรงเรียน (ธีม Playful, การ์ดเดี่ยวบนพื้นหลังวิดีโอ/รูป)
 * ของเดิม: username = password = รหัสโรงเรียน (เช่น 57030129)
 */
session_start();
require __DIR__ . '/includes/functions.php';

if (!empty($_SESSION['sc_id']) || !empty($_SESSION['user_id'])) {
    header('Location: menu.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $smis = trim($_POST['username'] ?? '');
    $pass = trim($_POST['password'] ?? '');

    // 1) บัญชีผู้ใช้ (ตาราง users) — username/password (hash) + role
    $user = user_authenticate($smis, $pass);
    if ($user) {
        session_regenerate_id(true);
        $area = (string)($user['area_code'] ?? '');
        $scid = (string)($user['sc_id'] ?? '');
        // saoadmin (ผูกเขต ไม่ผูกโรงเรียน) → เลือกโรงเรียนแรก "ที่มีข้อมูลสอบ" ในเขตเป็นบริบทเริ่มต้น
        // (ถ้าทั้งเขตยังไม่มีใครสอบ → fallback โรงเรียนแรกตาม SMIS) — สลับได้ภายหลัง
        if ($scid === '' && $area !== '') {
            $f = db()->prepare(
                'SELECT sc.sc_id FROM schools sc
                 WHERE sc.sc_smis LIKE ?
                   AND EXISTS (SELECT 1 FROM students s
                              WHERE s.sc_id = sc.sc_id AND s.years = ? AND s.deleted_at IS NULL
                                AND (s.hit1tested = 1 OR s.hit2tested = 1 OR s.hit3tested = 1))
                 ORDER BY sc.sc_smis LIMIT 1'
            );
            $f->execute([$area . '%', current_year()]);
            $scid = (string)($f->fetchColumn() ?: '');
            if ($scid === '') {   // ทั้งเขตยังไม่มีใครสอบ → โรงเรียนแรกตาม SMIS
                $f = db()->prepare('SELECT sc_id FROM schools WHERE sc_smis LIKE ? ORDER BY sc_smis LIMIT 1');
                $f->execute([$area . '%']);
                $scid = (string)($f->fetchColumn() ?: '');
            }
        }
        // resolve บริบทโรงเรียน (ชื่อ/SMIS) จาก sc_id ถ้ามี
        $scSmis = (string)$user['username'];
        $scName = USER_ROLES[$user['role']] ?? 'ผู้ใช้';
        if ($scid !== '') {
            $s = db()->prepare('SELECT sc_id, sc_smis, sc_name FROM schools WHERE sc_id = ? LIMIT 1');
            $s->execute([$scid]);
            if ($sc = $s->fetch()) {
                $scid   = (string)$sc['sc_id'];
                $scSmis = (string)$sc['sc_smis'];
                $scName = (string)$sc['sc_name'];
            }
        }
        $_SESSION['sc_id']     = $scid;
        $_SESSION['sc_smis']   = $scSmis;
        $_SESSION['sc_name']   = $scName;
        $_SESSION['user_id']   = (int)$user['id'];
        $_SESSION['user_role'] = (string)$user['role'];
        $_SESSION['user_name'] = (string)$user['name'];
        $_SESSION['area_code'] = $area;
        header('Location: menu.php');
        exit;
    }

    // 2) เดิม: โรงเรียน login ด้วย SMIS (user = pass = SMIS)
    $stmt = db()->prepare('SELECT sc_id, sc_smis, sc_name FROM schools WHERE sc_smis = ?');
    $stmt->execute([$smis]);
    $school = $stmt->fetch();

    if ($school && $pass !== '' && $pass === $smis) {
        session_regenerate_id(true);
        $_SESSION['sc_id']   = (string)$school['sc_id'];
        $_SESSION['sc_smis'] = $school['sc_smis'];
        $_SESSION['sc_name'] = $school['sc_name'];
        header('Location: menu.php');
        exit;
    }
    $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
}
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>เข้าระบบ — HIT-TEST</title>
    <link rel="icon" href="images/newlogo.png" type="image/png">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/theme.css?v=<?= @filemtime(__DIR__ . '/assets/css/theme.css') ?: '1' ?>" rel="stylesheet">
    <style>
        body { min-height: 100vh; display: grid; place-items: center; padding: 24px; background: transparent; }
        /* วิดีโอพื้นหลังเต็มจอ + เลเยอร์ทับให้การ์ดเด่น */
        .login-bg-img { position: fixed; inset: 0; width: 100%; height: 100%; object-fit: cover; z-index: -3; }
        .login-bg { position: fixed; inset: 0; width: 100%; height: 100%; object-fit: cover; z-index: -2; opacity: 0; transition: opacity .6s ease; }
        .login-bg.is-on { opacity: 1; }
        .login-bg-overlay { position: fixed; inset: 0; z-index: -1; background: linear-gradient(160deg, rgba(18,28,58,.42), rgba(42,22,72,.32)); }
        .login-shell {
            position: relative; z-index: 1;
            width: min(440px, 100%);
            background: rgba(255,255,255,.10);
            backdrop-filter: blur(22px) saturate(140%);
            -webkit-backdrop-filter: blur(22px) saturate(140%);
            border: 1px solid rgba(255,255,255,.45);
            border-radius: var(--r-xl); box-shadow: var(--sh-lg);
            overflow: hidden;
        }
        .login-brand { display: flex; align-items: center; gap: 10px; font-weight: 800; font-size: 1.15rem; color: var(--c-blue-ink); margin-bottom: 18px; }
        .login-brand__logo { width: 40px; height: 40px; border-radius: 12px; display: grid; place-items: center; background: var(--c-blue-soft); font-size: 22px; }
        .login-form { padding: 44px 40px; display: flex; flex-direction: column; justify-content: center; background: rgba(255,255,255,.62); }
        .login-form h1 { font-size: 1.9rem; margin-bottom: 4px; }
        .login-form .sub { color: var(--ink-soft); margin-bottom: 28px; }
        .login-alert { background: var(--c-coral-soft); color: var(--c-coral-ink); font-weight: 700; padding: 12px 16px; border-radius: var(--r-md); margin-bottom: 18px; }
        .login-hint { margin-top: 22px; color: var(--ink-soft); font-size: .92rem; }
        .login-hint code { background: var(--c-yellow-soft); color: var(--c-yellow-ink); padding: 2px 8px; border-radius: 6px; font-weight: 700; }
        @media (max-width: 480px) {
            body { padding: 12px; }
            .login-form { padding: 30px 22px; }
            .login-form h1 { font-size: 1.55rem; }
            .login-form .sub { margin-bottom: 20px; }
        }
    </style>
</head>
<body>
    <img class="login-bg-img" src="images/imagebackground.jpg" alt="">
    <video class="login-bg" muted loop playsinline preload="none" data-src="images/Hittest2026.mp4"></video>
    <div class="login-bg-overlay"></div>
    <div class="login-shell">
        <section class="login-form">
            <div class="login-brand" style="justify-content:center">
                <img src="images/newlogo.png" alt="HIT-TEST" style="width:130px;height:130px;object-fit:contain">
            </div>
            <h1>เข้าสู่ระบบ</h1>
            <p class="sub">เข้าสู่ระบบบัญชีของคุณเพื่อเริ่มการประเมิน</p>

            <?php if ($error): ?>
                <div class="login-alert">⚠️ <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form class="ht-stack" style="gap:18px" method="post" autocomplete="off">
                <div class="ht-field">
                    <label class="ht-label">ชื่อผู้ใช้ <span class="text-muted" style="font-weight:500">(รหัส SMIS โรงเรียน)</span></label>
                    <input class="ht-input" name="username" placeholder="รหัส SMIS โรงเรียน" required autofocus
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                </div>
                <div class="ht-field">
                    <label class="ht-label">รหัสผ่าน</label>
                    <input class="ht-input" name="password" type="password" placeholder="••••••••" required>
                </div>
                <button class="ht-btn ht-btn--lg ht-btn--block" type="submit">เข้าสู่ระบบ</button>
            </form>

            <p class="login-hint mb-0"><a href="landing.php" style="color:var(--ink-soft);text-decoration:none">← กลับหน้าหลัก</a></p>
        </section>
    </div>

    <script>
    /* พื้นหลัง login: แสดงรูปก่อนเสมอ แล้วอัปเกรดเป็นวิดีโอเฉพาะเมื่อเน็ตเร็วพอ */
    (function () {
        var v = document.querySelector('.login-bg');
        if (!v || !v.dataset.src) return;
        var TIMEOUT_MS = 4000;

        // เน็ตช้าชัดเจน? (Network Information API) — ถ้าใช่ คงรูปไว้ ไม่โหลดวิดีโอเลย
        function slowConnection() {
            var c = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
            if (!c) return false;                         // เบราว์เซอร์ไม่รองรับ → ลองโหลด (มี timeout กันไว้)
            if (c.saveData) return true;                  // เปิดโหมดประหยัดเน็ต
            if (c.effectiveType && /(slow-2g|2g|3g)/.test(c.effectiveType)) return true;
            if (typeof c.downlink === 'number' && c.downlink > 0 && c.downlink < 1.5) return true; // < 1.5 Mbps
            return false;
        }
        if (slowConnection()) return;

        // เน็ตพอใช้/ไม่ทราบ → ลองโหลดวิดีโอ; พร้อมเล่นทันใน TIMEOUT → fade ทับรูป, ไม่ทัน/พัง → คงรูป
        var settled = false;
        var timer = setTimeout(function () {
            if (settled) return;
            settled = true;
            v.removeAttribute('src'); v.load();           // ช้าเกินไป → ยกเลิกการโหลดวิดีโอ
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

        v.src = v.dataset.src;                            // เริ่มโหลดวิดีโอ
        v.load();
    })();
    </script>
</body>
</html>
