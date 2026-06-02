<?php
/**
 * game_balloon.php — ลูกโป่งหรรษา (Balloon Spelling Game)
 */
session_start();
require __DIR__ . '/includes/student_auth.php';
require __DIR__ . '/includes/game_assets.php';

$me    = require_student();
$grade = max(1, min(6, (int)($_GET['grade'] ?? (int)($me['class_id'] ?? 1))));
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ลูกโป่งหรรษา — HIT-TEST</title>
    <link rel="icon" href="images/newlogo.png" type="image/png">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
    (function () {
        var p = new URLSearchParams(window.location.search);
        if (!p.get('grade')) p.set('grade', '<?= $grade ?>');
        window.history.replaceState(null, '', '?' + p.toString());
    })();
    window.GAME_CONFIG = <?= json_encode([
        'apiBase'    => '/newhittest',
        'ttsUrl'     => '/newhittest/api/tts.php',
        'homeUrl'    => '/newhittest/my_dashboard.php',
        'studentId'  => $me['stuid'],
        'grade'      => $grade,
        'difficulty' => 1,
    ], JSON_UNESCAPED_UNICODE) ?>;
    </script>
    <?= game_asset_tags('balloon') ?>
</head>
<body style="margin:0;background:#1c1c1c">
    <div id="root"></div>
</body>
</html>
