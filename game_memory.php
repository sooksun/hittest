<?php
/**
 * game_memory.php — สลับคำ จำให้แม่น (Memory Matching)
 * Embeds the React memory game bundle; serves student session auth.
 */
session_start();
require __DIR__ . '/includes/student_auth.php';
require __DIR__ . '/includes/game_assets.php';

$me         = require_student();
$difficulty = max(1, min(3, (int)($_GET['difficulty'] ?? 1)));
$grade      = max(1, min(6, (int)($_GET['grade'] ?? (int)($me['class_id'] ?? 1))));
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>สลับคำ จำให้แม่น — HIT-TEST</title>
    <link rel="icon" href="images/newlogo.png" type="image/png">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
    (function () {
        var p = new URLSearchParams(window.location.search);
        if (!p.get('difficulty')) p.set('difficulty', '<?= $difficulty ?>');
        window.history.replaceState(null, '', '?' + p.toString());
    })();
    window.GAME_CONFIG = <?= json_encode([
        'apiBase'    => '/newhittest',
        'ttsUrl'     => '/newhittest/api/tts.php',
        'homeUrl'    => '/newhittest/my_dashboard.php',
        'studentId'  => $me['stuid'],
        'grade'      => $grade,
        'difficulty' => $difficulty,
    ], JSON_UNESCAPED_UNICODE) ?>;
    </script>
    <?= game_asset_tags('memory') ?>
</head>
<body style="margin:0;background:#0f172a">
    <div id="root"></div>
</body>
</html>
