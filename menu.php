<?php
/** menu.php — Dashboard ภาพรวมของโรงเรียนที่ login (ธีม Playful) */
require __DIR__ . '/includes/auth.php';

$scid = current_sc_id();

$yr = current_year();

$total = db()->prepare('SELECT COUNT(*) c FROM students WHERE sc_id = ? AND years = ? AND deleted_at IS NULL AND stustatus <> 4');
$total->execute([$scid, $yr]);
$total = (int)$total->fetch()['c'];

$h = db()->prepare('SELECT
        COALESCE(SUM(hit1tested),0) h1,
        COALESCE(SUM(hit2tested),0) h2,
        COALESCE(SUM(hit3tested),0) h3
    FROM students WHERE sc_id = ? AND years = ? AND deleted_at IS NULL AND stustatus <> 4');
$h->execute([$scid, $yr]);
$h = $h->fetch();

$byClass = db()->prepare('SELECT c.class_id, c.classname,
        COUNT(s.stuid) cnt,
        COALESCE(SUM(s.hit1tested),0) h1,
        COALESCE(SUM(s.hit2tested),0) h2,
        COALESCE(SUM(s.hit3tested),0) h3
    FROM class c
    LEFT JOIN students s ON s.class_id = c.class_id AND s.sc_id = ? AND s.years = ? AND s.deleted_at IS NULL AND s.stustatus <> 4
    GROUP BY c.class_id, c.classname ORDER BY c.class_id');
$byClass->execute([$scid, $yr]);
$classes = $byClass->fetchAll();

$stats = [
    ['👦👧', 'นักเรียนทั้งหมด', $total,        't-blue'],
    ['📕', 'สอบ Hit-1 แล้ว',  (int)$h['h1'], 't-green'],
    ['📗', 'สอบ Hit-2 แล้ว',  (int)$h['h2'], 't-coral'],
    ['📘', 'สอบ Hit-3 แล้ว',  (int)$h['h3'], 't-purple'],
];

/** แสดง "เข้าสอบแล้ว / ยังไม่สอบ" ของรอบหนึ่ง */
function hit_progress(int $tested, int $total): string
{
    if ($total === 0) {
        return '<span class="text-muted">—</span>';
    }
    $remain = max(0, $total - $tested);
    return '<span class="fw-8" style="color:var(--c-green-ink)">' . $tested . '</span>'
         . ' <span class="text-muted">/ ' . $remain . '</span>';
}

$page_title = 'Dashboard';
$active = 'dashboard';
require __DIR__ . '/includes/header.php';
?>
<style>/* หน้า Dashboard: ขยายกว้างพิเศษ (ค่าปกติ .ht-container = 1180px) */
.ht-container { max-width: 1400px; }</style>
<div class="ht-row mb-1" style="gap:14px;align-items:center">
    <h2 class="mb-0"><?= htmlspecialchars($_SESSION['sc_name']) ?></h2>
    <a class="ht-btn ht-btn--sm" href="dashboard_school.php" style="margin-left:auto">📊 ดูผลพัฒนาการการอ่าน →</a>
</div>
<p class="text-muted mb-4">รหัส SMIS <?= htmlspecialchars($_SESSION['sc_smis']) ?> · ภาพรวมการประเมินการอ่าน</p>

<div class="row g-4 mb-5">
    <?php foreach ($stats as [$icon, $label, $value, $tone]): ?>
        <div class="col-6 col-lg-3">
            <div class="ht-stat <?= $tone ?>">
                <div class="ht-stat__icon"><?= $icon ?></div>
                <div class="ht-stat__value"><?= $value ?></div>
                <div class="ht-stat__label"><?= $label ?></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="d-flex align-items-center flex-wrap gap-3 mb-3">
    <h3 class="mb-0">📚 จำนวนนักเรียนแยกตามชั้น</h3>
    <span class="text-muted" style="font-size:.9rem">แต่ละรอบ = <span class="fw-7" style="color:var(--c-green-ink)">เข้าสอบแล้ว</span> / <span class="text-muted">ยังไม่สอบ</span></span>
</div>
<div class="ht-table-wrap ht-table-wrap--cards">
    <table class="ht-table ht-table--cards">
        <thead>
            <tr>
                <th>ชั้น</th><th class="num text-center">นักเรียน</th>
                <th class="text-center">Hit-1</th><th class="text-center">Hit-2</th><th class="text-center">Hit-3</th>
                <th class="text-end">จัดการ</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($classes as $c): $cnt = (int)$c['cnt']; ?>
            <tr>
                <td class="fw-7 cardhead"><?= htmlspecialchars($c['classname']) ?></td>
                <td class="num text-center" data-label="นักเรียน"><?= $cnt ?> คน</td>
                <td class="text-center" data-label="Hit-1"><?= hit_progress((int)$c['h1'], $cnt) ?></td>
                <td class="text-center" data-label="Hit-2"><?= hit_progress((int)$c['h2'], $cnt) ?></td>
                <td class="text-center" data-label="Hit-3"><?= hit_progress((int)$c['h3'], $cnt) ?></td>
                <td class="text-end" data-label="จัดการ">
                    <a class="ht-btn ht-btn--sm" href="students_list.php?class_id=<?= (int)$c['class_id'] ?>">เข้าสอบอ่านไทย →</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
