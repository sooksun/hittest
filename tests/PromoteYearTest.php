<?php
/**
 * tests/PromoteYearTest.php — เลื่อนชั้นแบบผูกปี (includes/promote_lib.php) + PK ผูกปี
 *   - promote_school_year: สร้างแถวปีใหม่ (class+1, คะแนน=0), ป.6 จบไม่สร้างแถว, แถวปีเก่าไม่ถูกแตะ
 *   - idempotent (INSERT IGNORE)
 *   - promote_rollback_year: ลบแถวปีใหม่ (กันลบถ้าเริ่มสอบแล้ว)
 *   - studenthit PK (stuid, years, hit) — 2568 กับ 2569 ไม่ชนกัน
 */
require_once dirname(__DIR__) . '/includes/promote_lib.php';

/** seed นักเรียน 1 คนในปีหนึ่ง */
function pyt_seed(PDO $pdo, string $stuid, string $sc, int $class, int $years, array $o = []): void
{
    $pdo->prepare(
        'INSERT INTO students (stuid, stuname, sc_id, class_id, years, rooms, stustatus,
                               hit1, hit1tested, sethit1, sethit2, sethit3)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
    )->execute([
        $stuid, 'name-' . $stuid, $sc, $class, $years, 1,
        $o['stustatus'] ?? 1, $o['hit1'] ?? 0, $o['hit1tested'] ?? 0,
        $o['sethit'] ?? 2, $o['sethit'] ?? 3, $o['sethit'] ?? 4,
    ]);
}

run_test('PromoteYear: creates next-year rows, resets scores, ป.6 graduates', function () {
    $pdo = create_test_db();
    $sc = 'SC1';
    pyt_seed($pdo, 'a', $sc, 2, 2568, ['hit1' => 15, 'hit1tested' => 1, 'sethit' => 3]);
    pyt_seed($pdo, 'b', $sc, 3, 2568, ['hit1' => 10, 'hit1tested' => 1]);
    pyt_seed($pdo, 'g', $sc, 6, 2568);                       // ป.6 → จบ
    pyt_seed($pdo, 'm', $sc, 4, 2568, ['stustatus' => 4]);   // ย้ายออก → ข้าม

    $pdo->beginTransaction();
    $r = promote_school_year($pdo, $sc, 2568, 2569, 'smis', 'name');
    $pdo->commit();

    ok($r['promoted'] === 2, 'promoted = 2 (ป.2, ป.3 ที่ active)');
    ok($r['graduated'] === 1, 'graduated = 1 (ป.6 active)');

    $a69 = $pdo->query("SELECT class_id,hit1,hit1tested,sethit1 FROM students WHERE stuid='a' AND years=2569")->fetch();
    ok($a69 && (int)$a69['class_id'] === 3, 'a เลื่อนเป็น ป.3 ในปี 2569');
    ok((int)$a69['hit1'] === 0 && (int)$a69['hit1tested'] === 0, 'คะแนน/ธงสอบรีเซ็ตเป็น 0');
    ok((int)$a69['sethit1'] === 3, 'sethit คัดลอกมา');
    ok((int)$pdo->query("SELECT COUNT(*) FROM students WHERE stuid='g' AND years=2569")->fetchColumn() === 0, 'ป.6 จบ — ไม่มีแถว 2569');
    ok((int)$pdo->query("SELECT COUNT(*) FROM students WHERE stuid='m' AND years=2569")->fetchColumn() === 0, 'ย้ายออก — ข้าม');

    $a68 = $pdo->query("SELECT class_id,hit1 FROM students WHERE stuid='a' AND years=2568")->fetch();
    ok((int)$a68['class_id'] === 2 && (int)$a68['hit1'] === 15, 'แถวปี 2568 ไม่ถูกแตะ (ประวัติ)');

    $items = (int)$pdo->query("SELECT COUNT(*) FROM promote_log_item WHERE years=2569")->fetchColumn();
    ok($items === 3, 'promote_log_item บันทึก 3 คน (2 promote + 1 graduate) ปี 2569');
});

run_test('PromoteYear: idempotent (re-run = no duplicate)', function () {
    $pdo = create_test_db();
    pyt_seed($pdo, 'a', 'SC1', 2, 2568);
    $pdo->beginTransaction(); promote_school_year($pdo, 'SC1', 2568, 2569); $pdo->commit();
    $pdo->beginTransaction(); $r2 = promote_school_year($pdo, 'SC1', 2568, 2569); $pdo->commit();
    ok($r2['promoted'] === 0, 'รันซ้ำ promote 0');
    ok((int)$pdo->query("SELECT COUNT(*) FROM students WHERE stuid='a' AND years=2569")->fetchColumn() === 1, 'มีแถว 2569 เพียง 1');
});

run_test('PromoteYear: rollback deletes new rows, guards tested', function () {
    $pdo = create_test_db();
    pyt_seed($pdo, 'a', 'SC1', 2, 2568);
    pyt_seed($pdo, 'b', 'SC1', 3, 2568);
    $pdo->beginTransaction(); promote_school_year($pdo, 'SC1', 2568, 2569); $pdo->commit();
    // b เริ่มสอบปี 2569 แล้ว
    $pdo->prepare("UPDATE students SET hit1tested=1 WHERE stuid='b' AND years=2569")->execute();

    $pdo->beginTransaction();
    $rb = promote_rollback_year($pdo, 'SC1');
    $pdo->commit();

    ok($rb['ok'] === true, 'rollback ok');
    ok($rb['restored'] === 1 && $rb['skipped'] === 1, 'ลบ 1 (a) ข้าม 1 (b ที่สอบแล้ว)');
    ok((int)$pdo->query("SELECT COUNT(*) FROM students WHERE stuid='a' AND years=2569")->fetchColumn() === 0, 'แถว 2569 ของ a ถูกลบ');
    ok((int)$pdo->query("SELECT COUNT(*) FROM students WHERE stuid='b' AND years=2569")->fetchColumn() === 1, 'แถว 2569 ของ b ยังอยู่ (สอบแล้ว)');
    ok((int)$pdo->query("SELECT COUNT(*) FROM students WHERE years=2568")->fetchColumn() === 2, 'แถวปี 2568 ครบ ไม่ถูกแตะ');
    ok($pdo->query("SELECT rolled_back_at FROM promote_log ORDER BY id DESC LIMIT 1")->fetchColumn() !== null, 'log ถูกทำเครื่องหมาย rolled_back');
});

run_test('PromoteYear: rollback with nothing to undo', function () {
    $pdo = create_test_db();
    $pdo->beginTransaction();
    $rb = promote_rollback_year($pdo, 'SC1');
    $pdo->commit();
    ok($rb['ok'] === false && mb_strpos((string)$rb['message'], 'ไม่มี') !== false, 'ไม่มี log → ok=false + ข้อความ');
});

run_test('PromoteYear: studenthit PK (stuid,years,hit) ไม่ชนข้ามปี', function () {
    $pdo = create_test_db();
    $ins = $pdo->prepare('INSERT INTO studenthit (stuid,hit,years,hitscore) VALUES (?,?,?,?)');
    $ins->execute(['a', 1, 2568, 15]);
    $ins->execute(['a', 1, 2569, 8]);     // stuid+hit เดิม ปีต่าง → ต้องอยู่ร่วมกันได้
    ok((int)$pdo->query("SELECT COUNT(*) FROM studenthit WHERE stuid='a' AND hit=1")->fetchColumn() === 2, 'Hit-1 ทั้งสองปีอยู่ร่วมกัน');
    ok((int)$pdo->query("SELECT hitscore FROM studenthit WHERE stuid='a' AND hit=1 AND years=2569")->fetchColumn() === 8, 'แถวปี 2569 แยกจากปี 2568');
});
