<?php
/**
 * my_game_history.php — ประวัติการเล่นเกมทั้งหมดของนักเรียน
 * เฉพาะ role=student เท่านั้น — ดูได้เฉพาะข้อมูลตัวเอง
 */
session_start();
require __DIR__ . '/includes/student_auth.php';
require __DIR__ . '/includes/dashboard.php';

$me = require_student();

$stuid = (string)$me['stuid'];
$scid  = (string)$me['sc_id'];

// Anti-snooping
if (isset($_GET['stuid']) && (string)$_GET['stuid'] !== $stuid) {
    http_response_code(403);
    exit('<meta charset="utf-8"><div style="font-family:sans-serif;text-align:center;margin-top:60px">'
        . '<h2>⛔ ดูได้เฉพาะผลของตัวเองเท่านั้น</h2>'
        . '<a href="my_game_history.php">→ กลับหน้าประวัติของฉัน</a></div>');
}

// Filter by game type
$filterGame = isset($_GET['game']) && in_array($_GET['game'], ['memory','balloon','bubble','hangman','training'])
    ? $_GET['game'] : '';

$page  = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Count total
$pdo = db();
if ($filterGame) {
    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM game_results WHERE stuid = ? AND game = ?');
    $countStmt->execute([$stuid, $filterGame]);
} else {
    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM game_results WHERE stuid = ?');
    $countStmt->execute([$stuid]);
}
$total     = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $limit));
$page       = min($page, $totalPages);

// Fetch page
if ($filterGame) {
    $stmt = $pdo->prepare(
        'SELECT id, game, score, grade, difficulty, stats_json, created_at
         FROM game_results WHERE stuid = ? AND game = ?
         ORDER BY created_at DESC LIMIT ? OFFSET ?'
    );
    $stmt->execute([$stuid, $filterGame, $limit, $offset]);
} else {
    $stmt = $pdo->prepare(
        'SELECT id, game, score, grade, difficulty, stats_json, created_at
         FROM game_results WHERE stuid = ?
         ORDER BY created_at DESC LIMIT ? OFFSET ?'
    );
    $stmt->execute([$stuid, $limit, $offset]);
}
$rows = $stmt->fetchAll();

// Summary stats
$summary = dash_game_summary($stuid);

$gameNames = [
    'memory'   => ['icon' => '🧠', 'name' => 'สลับคำ จำให้แม่น'],
    'balloon'  => ['icon' => '🎈', 'name' => 'ลูกโป่งหรรษา'],
    'bubble'   => ['icon' => '💎', 'name' => 'ยิงแม่น แขวนคำ'],
    'hangman'  => ['icon' => '📝', 'name' => 'ไทยคำ จำแม่น'],
    'training' => ['icon' => '⚡', 'name' => 'ฝึกอ่าน'],
];
$diffNames = [1 => 'ง่าย', 2 => 'ปานกลาง', 3 => 'ยาก'];

// Student name for display
$stuSnap = dash_student_snapshot($scid, $stuid);
$stuname = $stuSnap ? $stuSnap['stuname'] : 'นักเรียน';
$classid = $stuSnap ? (int)$stuSnap['class_id'] : 1;
$className = dash_class_name($classid) ?? ('ป.' . $classid);
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ประวัติการเล่นเกม — HIT-TEST</title>
    <link rel="icon" href="images/newlogo.png" type="image/png">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/theme.css?v=<?= @filemtime(__DIR__ . '/assets/css/theme.css') ?: '1' ?>" rel="stylesheet">
    <style>
        body { background: linear-gradient(160deg,#EAF2FF,#F7F3FF); min-height:100vh }
        .stu-top { background:#fff; box-shadow:0 2px 10px rgba(0,0,0,.06) }
        .stu-top__in { max-width:1100px; margin:0 auto; padding:14px 22px; display:flex; align-items:center; gap:12px; flex-wrap:wrap }
        .stu-wrap { max-width:1100px; margin:0 auto; padding:26px 22px }
        .stat-card { background:#fff; border-radius:12px; padding:16px 18px; box-shadow:0 2px 8px rgba(0,0,0,.06); text-align:center }
        .stat-icon { font-size:2rem; margin-bottom:4px }
        .stat-val  { font-size:1.5rem; font-weight:800; color:#4D96FF }
        .stat-sub  { font-size:.78rem; color:#888 }
        .game-badge { display:inline-flex; align-items:center; gap:6px; padding:4px 12px; border-radius:20px; font-size:.82rem; font-weight:600 }
        .badge-memory   { background:#EEF2FF; color:#4338CA }
        .badge-balloon  { background:#FFF7ED; color:#C2410C }
        .badge-bubble   { background:#F0FDF4; color:#15803D }
        .badge-hangman  { background:#FDF4FF; color:#7E22CE }
        .badge-training { background:#FFFBEB; color:#B45309 }
        .diff-badge { display:inline-block; padding:2px 8px; border-radius:10px; font-size:.72rem; font-weight:600 }
        .diff-1 { background:#D1FAE5; color:#065F46 }
        .diff-2 { background:#FEF3C7; color:#92400E }
        .diff-3 { background:#FEE2E2; color:#991B1B }
        .score-num { font-size:1.1rem; font-weight:700; color:#4D96FF }
        .filter-btn { padding:6px 14px; border:1.5px solid #ddd; border-radius:20px; font-size:.82rem; text-decoration:none; color:#555 }
        .filter-btn.active, .filter-btn:hover { background:#4D96FF; color:#fff; border-color:#4D96FF }
        .history-table th { font-size:.82rem; font-weight:600; color:#888; border:none; padding:8px 12px }
        .history-table td { padding:10px 12px; vertical-align:middle; border-color:#f0f0f0 }
        .history-table tr:hover td { background:#F8FAFF }
    </style>
</head>
<body>

<div class="stu-top"><div class="stu-top__in">
    <img src="images/newlogo.png" alt="HIT-TEST" style="height:40px;width:40px;object-fit:contain">
    <strong style="font-size:1.05rem">🧒 <?= htmlspecialchars($stuname) ?></strong>
    <span class="ht-badge t-blue"><?= htmlspecialchars($className) ?></span>
    <div class="ht-row" style="gap:8px;margin-left:auto;flex-wrap:wrap;align-items:center">
        <a href="my_dashboard.php" class="ht-btn ht-btn--ghost ht-btn--sm">← แดชบอร์ด</a>
        <a href="logout.php" class="ht-btn ht-btn--ghost ht-btn--sm" style="color:var(--c-coral-ink)">ออกจากระบบ</a>
    </div>
</div></div>

<div class="stu-wrap">
    <h1 style="font-size:1.7rem" class="mb-1">🎮 ประวัติการเล่นเกม</h1>
    <p class="text-muted mb-4">บันทึกผลการเล่นเกมฝึกอ่านคำทั้งหมดของฉัน</p>

    <!-- Summary Stats -->
    <div class="row g-3 mb-4">
        <?php foreach ($gameNames as $key => $g):
            $s = $summary[$key] ?? null;
        ?>
        <div class="col-6 col-md-4 col-lg-2">
            <div class="stat-card">
                <div class="stat-icon"><?= $g['icon'] ?></div>
                <div class="stat-val"><?= $s ? number_format((int)$s['plays']) : '0' ?></div>
                <div class="stat-sub"><?= $g['name'] ?></div>
                <?php if ($s): ?>
                <div class="stat-sub mt-1">สูงสุด: <strong><?= number_format((int)$s['best']) ?></strong></div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <div class="col-6 col-md-4 col-lg-2">
            <div class="stat-card">
                <div class="stat-icon">🏆</div>
                <div class="stat-val"><?= number_format(array_sum(array_column($summary, 'plays'))) ?></div>
                <div class="stat-sub">รวมทั้งหมด</div>
            </div>
        </div>
    </div>

    <!-- Filter tabs -->
    <div class="d-flex flex-wrap gap-2 mb-3">
        <a href="my_game_history.php" class="filter-btn <?= $filterGame === '' ? 'active' : '' ?>">ทั้งหมด</a>
        <?php foreach ($gameNames as $key => $g): ?>
        <a href="my_game_history.php?game=<?= $key ?>"
           class="filter-btn <?= $filterGame === $key ? 'active' : '' ?>">
            <?= $g['icon'] ?> <?= $g['name'] ?>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- History table -->
    <div class="bg-white rounded-3 shadow-sm overflow-hidden">
        <?php if (empty($rows)): ?>
            <div class="text-center py-5 text-muted">
                <div style="font-size:3rem;margin-bottom:12px">🎮</div>
                <p>ยังไม่มีประวัติการเล่นเกม<?= $filterGame ? ' (' . ($gameNames[$filterGame]['name'] ?? $filterGame) . ')' : '' ?></p>
                <a href="my_dashboard.php" class="ht-btn mt-2">ไปเล่นเกมเลย!</a>
            </div>
        <?php else: ?>
        <div class="table-responsive">
        <table class="table history-table mb-0">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>เกม</th>
                    <th>ชั้น</th>
                    <th>ความยาก</th>
                    <th>คะแนน</th>
                    <th>รายละเอียด</th>
                    <th>วันที่เล่น</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $i => $r):
                    $g = $gameNames[$r['game']] ?? ['icon' => '🎮', 'name' => $r['game']];
                    $stats = json_decode($r['stats_json'] ?? '{}', true) ?: [];
                    $dateObj = new DateTime($r['created_at']);
                    $thYear = (int)$dateObj->format('Y') + 543;
                    $dateDisplay = $dateObj->format('d/m/') . $thYear . ' ' . $dateObj->format('H:i');
                    $rowNum = $offset + $i + 1;
                ?>
                <tr>
                    <td class="text-muted" style="font-size:.8rem"><?= $rowNum ?></td>
                    <td>
                        <span class="game-badge badge-<?= htmlspecialchars($r['game']) ?>">
                            <?= $g['icon'] ?> <?= $g['name'] ?>
                        </span>
                    </td>
                    <td style="font-size:.85rem">ป.<?= (int)$r['grade'] ?></td>
                    <td>
                        <span class="diff-badge diff-<?= (int)$r['difficulty'] ?>">
                            <?= $diffNames[(int)$r['difficulty']] ?? '-' ?>
                        </span>
                    </td>
                    <td><span class="score-num"><?= number_format((int)$r['score']) ?></span></td>
                    <td style="font-size:.8rem;color:#666">
                        <?php
                        $detail = [];
                        if ($r['game'] === 'memory') {
                            if (isset($stats['moves'])) $detail[] = 'เปิด ' . $stats['moves'] . ' ครั้ง';
                            if (isset($stats['timeElapsed'])) $detail[] = $stats['timeElapsed'] . ' วิ';
                        } elseif ($r['game'] === 'balloon') {
                            if (isset($stats['levelsCompleted'])) $detail[] = 'ผ่าน ' . $stats['levelsCompleted'] . ' ด่าน';
                            if (isset($stats['hearts'])) $detail[] = '❤️ ' . $stats['hearts'];
                        } elseif ($r['game'] === 'bubble') {
                            if (isset($stats['wordsCompleted'])) $detail[] = $stats['wordsCompleted'] . ' คำ';
                            if (isset($stats['won'])) $detail[] = $stats['won'] ? '✅ ชนะ' : '❌ แพ้';
                        } elseif ($r['game'] === 'hangman') {
                            if (isset($stats['word'])) $detail[] = '"' . $stats['word'] . '"';
                            if (isset($stats['won'])) $detail[] = $stats['won'] ? '✅ ทาย​ได้' : '❌ ทายไม่ได้';
                        } elseif ($r['game'] === 'training') {
                            if (isset($stats['correctCount'], $stats['totalTasks'])) {
                                $detail[] = $stats['correctCount'] . '/' . $stats['totalTasks'] . ' ข้อ';
                            }
                        }
                        echo $detail ? implode(' · ', $detail) : '—';
                        ?>
                    </td>
                    <td style="font-size:.8rem;color:#888"><?= $dateDisplay ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="d-flex justify-content-center align-items-center gap-2 py-3 border-top">
            <?php if ($page > 1): ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="ht-btn ht-btn--ghost ht-btn--sm">← ก่อน</a>
            <?php endif; ?>
            <span class="text-muted" style="font-size:.85rem">หน้า <?= $page ?> / <?= $totalPages ?> (<?= number_format($total) ?> รายการ)</span>
            <?php if ($page < $totalPages): ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="ht-btn ht-btn--ghost ht-btn--sm">ถัดไป →</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
