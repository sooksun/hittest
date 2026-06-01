<?php
/** evaluations_view.php — ผลการสอบรายบุคคล แยกตามตัวชี้วัด (ธีม Playful) */
require __DIR__ . '/includes/auth.php';

$stuid   = (string)($_GET['stuid'] ?? '');
$hittest = (int)($_GET['hittest'] ?? 0);

$student = find_student($stuid);
if (!$student || !valid_hit($hittest)) {
    die('ไม่พบข้อมูลนักเรียน หรือรอบไม่ถูกต้อง');
}

$rows = db()->prepare(
    'SELECT wc.category, COUNT(*) total, COALESCE(SUM(e.correct),0) correct
     FROM evaluations e
     JOIN words w          ON w.id = e.word_id
     JOIN word_category wc ON wc.catid = w.indicator
     WHERE e.stuid = ? AND e.hittest = ?
     GROUP BY wc.catid, wc.category
     ORDER BY wc.catid'
);
$rows->execute([$stuid, $hittest]);
$data = $rows->fetchAll();

$totalWords   = array_sum(array_column($data, 'total'));
$totalCorrect = array_sum(array_column($data, 'correct'));
$totalPct     = $totalWords ? round($totalCorrect * 100 / $totalWords, 2) : 0;
$set = (int)($student["sethit{$hittest}"] ?? 0);

$page_title = 'ผลการสอบรายบุคคล';
$active = 'exam';
require __DIR__ . '/includes/header.php';
?>
<a href="students_list.php?class_id=<?= (int)$student['class_id'] ?>" class="ht-btn ht-btn--ghost ht-btn--sm mb-4">← กลับไปรายชื่อ</a>

<div class="ht-card t-blue mb-4" style="border-left:6px solid var(--c-blue)">
    <h2 class="mb-1">ผลสอบ Hit-<?= $hittest ?> · <?= htmlspecialchars($student['stuname']) ?></h2>
    <p class="text-muted mb-0">ชั้น ป.<?= (int)$student['class_id'] ?> ห้อง <?= (int)$student['rooms'] ?> · แบบทดสอบชุดที่ <?= $set ?>
        &nbsp;—&nbsp; รวม <span class="fw-8" style="color:var(--c-blue-ink)"><?= $totalCorrect ?>/<?= $totalWords ?></span> คะแนน (<?= $totalPct ?>%)</p>
</div>

<?php if (!$data): ?>
    <div class="ht-card t-yellow" style="border-left:6px solid var(--c-yellow)">ยังไม่มีผลการสอบรอบนี้</div>
<?php else: ?>
<div class="ht-table-wrap">
    <table class="ht-table">
        <thead>
            <tr><th>ตัวชี้วัด</th><th class="num text-center">คำทั้งหมด</th><th class="num text-center">อ่านถูก</th><th style="width:34%">คิดเป็นร้อยละ</th></tr>
        </thead>
        <tbody>
        <?php foreach ($data as $d):
            $pct  = $d['total'] ? round($d['correct'] * 100 / $d['total'], 2) : 0;
            $tone = $pct >= 100 ? 't-green' : ($pct >= 50 ? 't-yellow' : 't-coral');
        ?>
            <tr>
                <td class="fw-7"><?= htmlspecialchars($d['category']) ?></td>
                <td class="num text-center"><?= (int)$d['total'] ?></td>
                <td class="num text-center"><?= (int)$d['correct'] ?></td>
                <td>
                    <div class="d-flex align-items-center gap-3">
                        <div class="ht-progress <?= $tone ?> flex-grow-1"><span style="width:<?= $pct ?>%"></span></div>
                        <span class="ht-pct text-end"><?= $pct ?>%</span>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr class="fw-8" style="background:var(--bg-sky)">
                <td>รวม</td>
                <td class="num text-center"><?= $totalWords ?></td>
                <td class="num text-center"><?= $totalCorrect ?></td>
                <td>
                    <div class="d-flex align-items-center gap-3">
                        <div class="ht-progress ht-progress--lg t-blue flex-grow-1"><span style="width:<?= $totalPct ?>%"></span></div>
                        <span class="ht-pct text-end"><?= $totalPct ?>%</span>
                    </div>
                </td>
            </tr>
        </tfoot>
    </table>
</div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
