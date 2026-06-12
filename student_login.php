<?php
/**
 * student_login.php — Phase 3: หน้าเข้าระบบสำหรับนักเรียน
 * ใช้ "รหัสนักเรียน (stuid)" เป็นทั้ง username + password (pattern เดียวกับ login ครูที่ใช้ sc_smis)
 * stuid เป็น PK ของ students → ระบุตัว + โรงเรียนได้ในตัว · มี throttle กัน brute-force
 *
 * หมายเหตุความปลอดภัย: stuid ไม่ใช่ความลับ → posture เท่ากับ login ครู (read-only ของเด็กคนเดียว)
 *   ถ้าต้องการความปลอดภัยสูงขึ้น สลับไปใช้ PIN ได้ (ดู teacher_pins.php + student_pin_verify())
 */
session_start();
require __DIR__ . '/includes/student_auth.php';

// login เป็นนักเรียนอยู่แล้ว → ไปแดชบอร์ด
if (($_SESSION['role'] ?? '') === 'student' && !empty($_SESSION['stu']['stuid'])) {
    header('Location: my_dashboard.php');
    exit;
}

$error = '';
$ip    = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stuid = trim($_POST['stuid'] ?? '');       // รหัสนักเรียน (ช่องเดียว)
    $keys  = ["ip:$ip", "id:$stuid:$ip"];

    $gate = throttle_check($keys);
    if ($gate['blocked']) {
        $error = $gate['message'];
    } elseif ($stuid === '') {
        throttle_fail($keys);
        $error = 'กรุณากรอกรหัสนักเรียน';
    } else {
        $stu = student_login_by_id($stuid);
        if ($stu) {
            throttle_reset($keys);
            student_session_set($stu);
            header('Location: my_dashboard.php');
            exit;
        }
        throttle_fail($keys);
        $error = 'ไม่พบรหัสนักเรียนนี้';
    }
}

// prefill จาก QR (?u=รหัสนักเรียน) หรือค่าที่เพิ่งกรอก
$prefill = trim($_POST['stuid'] ?? $_GET['u'] ?? '');
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>นักเรียนเข้าระบบ — HIT-TEST</title>
    <link rel="icon" href="images/newlogo.png" type="image/png">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/theme.css?v=<?= @filemtime(__DIR__ . '/assets/css/theme.css') ?: '1' ?>" rel="stylesheet">
    <style>
        body { min-height: 100vh; display: grid; place-items: center; padding: 24px;
               background: linear-gradient(160deg, #EAF2FF, #F3ECFF); }
        .stu-shell { width: min(420px, 100%); background: #fff; border-radius: var(--r-xl);
                     box-shadow: var(--sh-lg); padding: 40px 34px; }
        .stu-brand { display:flex; align-items:center; gap:10px; font-weight:800; font-size:1.1rem; color:var(--c-blue-ink); margin-bottom:20px }
        .stu-brand__logo { width:42px;height:42px;border-radius:12px;display:grid;place-items:center;background:var(--c-blue-soft);font-size:24px }
        .stu-alert { background: var(--c-coral-soft); color: var(--c-coral-ink); font-weight:700; padding:12px 16px; border-radius: var(--r-md); margin-bottom:16px }
        .stu-hint { margin-top:18px; color:var(--ink-soft); font-size:.9rem }
        .stu-hint code { background: var(--c-yellow-soft); color: var(--c-yellow-ink); padding:2px 8px; border-radius:6px; font-weight:700 }
    </style>
</head>
<body>
    <div class="stu-shell">
        <div class="stu-brand" style="flex-direction:column;align-items:center;gap:6px">
            <img src="images/newlogo.png" alt="HIT-TEST" style="width:124px;height:124px;object-fit:contain">
            <span style="font-size:.95rem;color:var(--ink-soft);font-weight:700">🧒 สำหรับนักเรียน</span>
        </div>
        <h1 style="font-size:1.7rem;margin-bottom:4px">สวัสดี! 👋</h1>
        <p class="text-muted" style="margin-bottom:24px">เข้าระบบด้วย <strong>รหัสนักเรียน</strong> ของหนู</p>

        <?php if ($error): ?><div class="stu-alert">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>

        <form method="post" class="ht-stack" style="gap:16px" autocomplete="off">
            <div class="ht-field">
                <label class="ht-label">รหัสนักเรียน</label>
                <input class="ht-input stu-pin" name="stuid" inputmode="numeric" placeholder="เช่น 9000000001" required
                       value="<?= htmlspecialchars($prefill) ?>"<?= $prefill === '' ? ' autofocus' : '' ?>>
            </div>
            <button class="ht-btn ht-btn--lg ht-btn--block" type="submit"<?= $prefill !== '' ? ' autofocus' : '' ?>>เข้าสู่ระบบ 🚀</button>
        </form>

        <p class="stu-hint mb-0">💡 ใช้ <code>รหัสนักเรียน</code> ของหนูเข้าระบบ — หรือสแกน QR จากคุณครูแล้วกดปุ่มได้เลย</p>
        <p class="stu-hint mt-2 mb-0">เป็นครู/ผู้ดูแล? <a href="login.php" style="color:var(--c-blue-ink);font-weight:700">เข้าระบบครู →</a></p>
    </div>
</body>
</html>
