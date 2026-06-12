<?php
/**
 * student_stories.php — ครู/ผู้ดูแล อ่าน "เรื่องที่นักเรียนแต่ง" (ภารกิจนักเล่าเรื่อง ท้ายเกม)
 * อ่านจากตาราง game_stories — scope ด้วย sc_id ของ session เท่านั้น (multi-tenant)
 *
 * RBAC: auth.php (school admin). Filter: ชั้น / เกม / ค้นหาชื่อ-รหัส + แบ่งหน้า
 */
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/dashboard.php';   // dash_valid_class / dash_class_name

$scid = current_sc_id();
$pdo  = db();

// ---- Filters ----
$gameNames = [
    'memory'   => ['icon' => '🧠', 'name' => 'สลับคำ จำให้แม่น'],
    'balloon'  => ['icon' => '🎈', 'name' => 'ลูกโป่งหรรษา'],
    'bubble'   => ['icon' => '💎', 'name' => 'ยิงแม่น แขวนคำ'],
    'hangman'  => ['icon' => '📝', 'name' => 'ไทยคำ จำแม่น'],
    'training' => ['icon' => '⚡', 'name' => 'ฝึกอ่าน'],
];
$diffNames = [1 => 'ง่าย', 2 => 'ปานกลาง', 3 => 'ยาก'];

// ตัวกรอง + WHERE จาก helper กลาง (ใช้ร่วมกับ student_stories_export.php → ตัวกรองตรงกันเป๊ะ ไม่ drift)
$f         = story_filter_where($_GET, $scid);
$whereSql  = $f['where'];
$params    = $f['params'];
$classId   = $f['classId'];   // 0 = ทุกชั้น
$game      = $f['game'];
$q         = $f['q'];
$stuFilter = $f['stuid'];     // ดูเรื่องเป็นรายคน

$page  = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;

// query string รวมตัวกรองปัจจุบัน (ส่งต่อให้ปุ่ม CSV)
$filterQS = http_build_query(array_filter([
    'class_id' => $classId ?: '',
    'game'     => $game,
    'q'        => $q,
    'stuid'    => $stuFilter,
]));

// ---- Aggregate (รวม + ใช้คำครบ) ----
$agg = $pdo->prepare("SELECT COUNT(*) AS c, COALESCE(SUM(used_all),0) AS used FROM game_stories WHERE $whereSql");
$agg->execute($params);
$aggRow    = $agg->fetch();
$total     = (int)($aggRow['c'] ?? 0);
$usedCount = (int)($aggRow['used'] ?? 0);
$totalPages = max(1, (int)ceil($total / $limit));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $limit;

// ---- Page rows ----
$stmt = $pdo->prepare(
    "SELECT id, stuid, stuname, class_id, game, grade, difficulty,
            words_json, story_text, char_count, used_all, stats_json, created_at
     FROM game_stories WHERE $whereSql
     ORDER BY created_at DESC LIMIT ? OFFSET ?"
);
$stmt->execute([...$params, $limit, $offset]);
$rows = $stmt->fetchAll();

// ชื่อชั้นทั้งหมดครั้งเดียว (กัน N+1 — เดิมเรียก dash_class_name() ต่อแถวในลูปแสดงผล)
$classMap = [];
foreach ($pdo->query('SELECT class_id, classname FROM class')->fetchAll() as $cRow) {
    $classMap[(int)$cRow['class_id']] = $cRow['classname'];
}

// ชื่อนักเรียน (สำหรับแบนเนอร์ตอนดูรายคน)
$stuName = $stuFilter !== '' ? (string)($rows[0]['stuname'] ?? $stuFilter) : '';

$page_title = 'เรื่องที่นักเรียนแต่ง';
$active = 'stories';
require __DIR__ . '/includes/header.php';
?>
<style>
.ht-container{max-width:1100px}
.story-stat{display:inline-flex;align-items:center;gap:6px;background:#fff;border:1px solid #eef;border-radius:12px;padding:8px 14px;font-size:.9rem;box-shadow:0 1px 4px rgba(0,0,0,.04)}
.story-card{background:#fff;border-radius:14px;padding:16px 18px;box-shadow:0 2px 8px rgba(0,0,0,.06);margin-bottom:14px}
.story-card__head{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:10px}
.story-card__name{font-weight:700;font-size:1.02rem}
.game-badge{display:inline-flex;align-items:center;gap:6px;padding:3px 11px;border-radius:20px;font-size:.8rem;font-weight:600}
.badge-memory{background:#EEF2FF;color:#4338CA}
.badge-balloon{background:#FFF7ED;color:#C2410C}
.badge-bubble{background:#F0FDF4;color:#15803D}
.badge-hangman{background:#FDF4FF;color:#7E22CE}
.badge-training{background:#FFFBEB;color:#B45309}
.used-yes{background:#D1FAE5;color:#065F46}
.used-no{background:#FEF3C7;color:#92400E}
.mini-badge{display:inline-block;padding:2px 9px;border-radius:10px;font-size:.74rem;font-weight:600}
.story-words{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:10px}
.word-chip{background:#F1F5FF;color:#334155;border:1px solid #e2e8f0;border-radius:14px;padding:2px 11px;font-size:.82rem;font-weight:500}
.story-text{background:#F8FAFC;border:1px solid #eef2f7;border-radius:10px;padding:12px 14px;white-space:pre-wrap;line-height:1.7;color:#1e293b;font-size:.98rem}
.story-meta{font-size:.78rem;color:#94a3b8;margin-left:auto}
a.story-card__name{color:#1e293b;text-decoration:none}
a.story-card__name:hover{color:#4D96FF;text-decoration:underline}
@media print {
    .ht-sidebar,.ht-burger,.ht-backdrop,.no-print{display:none !important}
    body.ht-app{display:block;background:#fff !important}
    .ht-container{max-width:100% !important;padding:0 !important}
    .story-card{box-shadow:none;border:1px solid #cbd5e1;break-inside:avoid;page-break-inside:avoid}
    .story-text{background:#fff}
    a.story-card__name{color:#1e293b !important;text-decoration:none !important}
}
</style>

<div class="ht-row mb-3" style="gap:12px;align-items:center;flex-wrap:wrap">
    <h2 class="mb-0">✍️ เรื่องที่นักเรียนแต่ง</h2>
    <span class="ht-badge t-blue">🏫 <?= htmlspecialchars($_SESSION['sc_name'] ?? '') ?></span>
    <div class="ht-row no-print" style="gap:8px;margin-left:auto">
        <a href="student_stories_export.php<?= $filterQS ? '?' . htmlspecialchars($filterQS) : '' ?>" class="ht-btn ht-btn--ghost ht-btn--sm" title="ดาวน์โหลดเป็น CSV (เปิดใน Excel)">⬇️ CSV</a>
        <button type="button" class="ht-btn ht-btn--ghost ht-btn--sm" onclick="window.print()">🖨️ พิมพ์</button>
    </div>
</div>
<p class="text-muted mb-3" style="margin-top:-6px">เรื่องราวที่นักเรียนแต่งใน "ภารกิจนักเล่าเรื่อง" ท้ายเกม (จาก 8 คำที่สุ่มมาในเกม)</p>

<?php if ($stuFilter !== ''): ?>
<div class="ht-card mb-3" style="border-left:6px solid var(--c-blue);display:flex;align-items:center;gap:10px;flex-wrap:wrap">
    <span>👦 กำลังดูเรื่องของ <strong><?= htmlspecialchars($stuName) ?></strong> · <?= number_format($total) ?> เรื่อง</span>
    <a href="student_stories.php" class="ht-btn ht-btn--ghost ht-btn--sm no-print" style="margin-left:auto">← ดูนักเรียนทั้งหมด</a>
</div>
<?php endif; ?>

<!-- Filters -->
<form method="get" class="ht-card mb-3 no-print" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap">
    <?php if ($stuFilter !== ''): ?><input type="hidden" name="stuid" value="<?= htmlspecialchars($stuFilter) ?>"><?php endif; ?>
    <div>
        <label class="ht-label mb-1">ชั้น</label><br>
        <select name="class_id" class="ht-select" style="width:auto" onchange="this.form.submit()">
            <option value="">ทุกชั้น</option>
            <?php for ($c = 1; $c <= 6; $c++): ?>
                <option value="<?= $c ?>" <?= $c === $classId ? 'selected' : '' ?>>ป.<?= $c ?></option>
            <?php endfor; ?>
        </select>
    </div>
    <div>
        <label class="ht-label mb-1">เกม</label><br>
        <select name="game" class="ht-select" style="width:auto" onchange="this.form.submit()">
            <option value="">ทุกเกม</option>
            <?php foreach ($gameNames as $key => $g): ?>
                <option value="<?= $key ?>" <?= $game === $key ? 'selected' : '' ?>><?= $g['icon'] ?> <?= $g['name'] ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div style="flex:1;min-width:180px">
        <label class="ht-label mb-1">ค้นหา (ชื่อ / รหัสนักเรียน)</label><br>
        <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" class="ht-input" placeholder="พิมพ์ชื่อหรือรหัส…" style="width:100%">
    </div>
    <button class="ht-btn" type="submit">🔍 ค้นหา</button>
    <?php if ($classId || $game || $q !== ''): ?>
        <a href="student_stories.php" class="ht-btn ht-btn--ghost">ล้างตัวกรอง</a>
    <?php endif; ?>
</form>

<!-- Summary -->
<div class="ht-row mb-3" style="gap:10px;flex-wrap:wrap">
    <span class="story-stat">📚 ทั้งหมด <strong><?= number_format($total) ?></strong> เรื่อง</span>
    <span class="story-stat">✅ ใช้คำครบ <strong><?= number_format($usedCount) ?></strong> เรื่อง</span>
</div>

<?php if (empty($rows)): ?>
    <div class="ht-card text-center" style="padding:40px 20px;color:#94a3b8">
        <div style="font-size:3rem;margin-bottom:10px">✍️</div>
        <p class="mb-0">ยังไม่มีเรื่องที่นักเรียนแต่ง<?= ($classId || $game || $q !== '' || $stuFilter !== '') ? 'ตามตัวกรองนี้' : '' ?></p>
    </div>
<?php else: ?>
    <?php foreach ($rows as $r):
        $g       = $gameNames[$r['game']] ?? ['icon' => '🎮', 'name' => $r['game']];
        $wj      = json_decode($r['words_json'] ?? '{}', true) ?: [];
        $words   = is_array($wj['words'] ?? null) ? $wj['words'] : [];
        $className = $classMap[(int)$r['class_id']] ?? ('ป.' . (int)$r['class_id']);
        $dateObj = new DateTime($r['created_at']);
        $thYear  = (int)$dateObj->format('Y') + 543;
        $dateStr = $dateObj->format('d/m/') . $thYear . ' ' . $dateObj->format('H:i') . ' น.';
        $usedAll = (int)$r['used_all'] === 1;
    ?>
    <div class="story-card">
        <div class="story-card__head">
            <a class="story-card__name" href="student_stories.php?stuid=<?= urlencode($r['stuid']) ?>" title="ดูเรื่องทั้งหมดของนักเรียนคนนี้">🧒 <?= htmlspecialchars($r['stuname'] ?: $r['stuid']) ?></a>
            <span class="ht-badge t-blue"><?= htmlspecialchars($className) ?></span>
            <span class="game-badge badge-<?= htmlspecialchars($r['game']) ?>"><?= $g['icon'] ?> <?= htmlspecialchars($g['name']) ?></span>
            <span class="mini-badge <?= $usedAll ? 'used-yes' : 'used-no' ?>"><?= $usedAll ? '✓ ใช้คำครบ' : '◯ ใช้ไม่ครบ' ?></span>
            <span class="story-meta"><?= htmlspecialchars($dateStr) ?> · <?= number_format((int)$r['char_count']) ?> ตัวอักษร</span>
        </div>
        <?php if ($words): ?>
        <div class="story-words">
            <?php foreach ($words as $w): ?>
                <span class="word-chip"><?= htmlspecialchars((string)$w) ?></span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <div class="story-text"><?= htmlspecialchars($r['story_text']) ?></div>
    </div>
    <?php endforeach; ?>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="d-flex justify-content-center align-items-center gap-2 py-3">
        <?php if ($page > 1): ?>
            <a href="?<?= htmlspecialchars(http_build_query(array_merge($_GET, ['page' => $page - 1]))) ?>" class="ht-btn ht-btn--ghost ht-btn--sm">← ก่อน</a>
        <?php endif; ?>
        <span class="text-muted" style="font-size:.85rem">หน้า <?= $page ?> / <?= $totalPages ?></span>
        <?php if ($page < $totalPages): ?>
            <a href="?<?= htmlspecialchars(http_build_query(array_merge($_GET, ['page' => $page + 1]))) ?>" class="ht-btn ht-btn--ghost ht-btn--sm">ถัดไป →</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
