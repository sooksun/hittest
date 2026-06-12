<?php
/**
 * tests/MediaJobTest.php — คิวงานสร้างสื่อ + ตัวแต่ง prompt + ที่เก็บไฟล์ (logic ล้วน ไม่แตะเครือข่าย)
 *   - game_media_jobs: enqueue idempotency, claim transition, complete/fail/retry, counts
 *   - prompt_template : override + การจัดประเภทคำ (สัตว์/คำนาม) + กฎ AMBIGUOUS
 *   - media_storage   : hash เสถียร + พาธ + กัน path traversal + เขียนไฟล์
 */
require_once dirname(__DIR__) . '/includes/media_jobs.php';
require_once dirname(__DIR__) . '/includes/media_storage.php';
require_once dirname(__DIR__) . '/includes/prompt_template.php';

run_test('MediaJobTest: enqueue idempotency', function () {
    $pdo = create_test_db();
    $pdo->exec("INSERT INTO wordstest (id,word,class_id,level) VALUES (5,'ทดสอบ',1,1)");

    $a = media_job_enqueue($pdo, 'audio', 5, false, 'tester');
    ok($a['status'] === 'queued', 'first enqueue → queued');

    $b = media_job_enqueue($pdo, 'audio', 5, false, 'tester');
    ok($b['status'] === 'exists' && $b['jobId'] === $a['jobId'], 'duplicate (active job) → exists, same id');

    $c = media_job_enqueue($pdo, 'audio', 5, true, 'tester');
    ok($c['status'] === 'queued' && $c['jobId'] !== $a['jobId'], 'force → new queued job');

    $d = media_job_enqueue($pdo, 'image', 5, false, 'tester');
    ok($d['status'] === 'queued', 'image type is independent of audio');
});

run_test('MediaJobTest: claim → processing transition (atomic)', function () {
    $pdo = create_test_db();
    $pdo->exec("INSERT INTO wordstest (id,word,class_id,level) VALUES (5,'ก',1,1),(6,'ข',1,1)");
    media_job_enqueue($pdo, 'audio', 5, false);
    media_job_enqueue($pdo, 'audio', 6, false);

    $j1 = media_job_claim($pdo, 'audio');
    ok($j1 !== null && (int)$j1['word_id'] === 5, 'claim returns oldest queued (word 5)');
    ok((int)$j1['attempts'] === 1, 'attempts incremented to 1');
    $st = $pdo->query('SELECT status FROM game_media_jobs WHERE id=' . (int)$j1['id'])->fetchColumn();
    ok($st === 'processing', 'claimed job marked processing');

    $j2 = media_job_claim($pdo, 'audio');
    ok($j2 !== null && (int)$j2['word_id'] === 6, 'second claim returns word 6');

    $j3 = media_job_claim($pdo, 'audio');
    ok($j3 === null, 'queue drained → null');
});

run_test('MediaJobTest: type filter on claim', function () {
    $pdo = create_test_db();
    $pdo->exec("INSERT INTO wordstest (id,word,class_id,level) VALUES (5,'ก',1,1)");
    media_job_enqueue($pdo, 'image', 5, false);
    ok(media_job_claim($pdo, 'audio') === null, 'claim(audio) ignores image jobs');
    ok(media_job_claim($pdo, 'image') !== null, 'claim(image) picks image job');
});

run_test('MediaJobTest: complete / fail / retry', function () {
    $pdo = create_test_db();
    $pdo->exec("INSERT INTO wordstest (id,word,class_id,level) VALUES (5,'ก',1,1)");

    media_job_enqueue($pdo, 'audio', 5, false);
    $j = media_job_claim($pdo, 'audio');
    media_job_complete($pdo, (int)$j['id']);
    ok($pdo->query('SELECT status FROM game_media_jobs WHERE id=' . (int)$j['id'])->fetchColumn() === 'done', 'complete → done');
    ok(media_job_retry($pdo, (int)$j['id']) === false, 'retry on done job → false (no-op)');

    $j2id = media_job_enqueue($pdo, 'audio', 5, true)['jobId'];
    $j2 = media_job_claim($pdo, 'audio');
    media_job_fail($pdo, (int)$j2['id'], 'boom');
    $row = $pdo->query('SELECT status,last_error FROM game_media_jobs WHERE id=' . (int)$j2['id'])->fetch();
    ok($row['status'] === 'failed' && $row['last_error'] === 'boom', 'fail → failed + stored error');
    ok(media_job_retry($pdo, (int)$j2['id']) === true, 'retry failed → true');
    ok($pdo->query('SELECT status FROM game_media_jobs WHERE id=' . (int)$j2['id'])->fetchColumn() === 'queued', 'retry resets to queued');
});

run_test('MediaJobTest: counts reflect state', function () {
    $pdo = create_test_db();
    $pdo->exec("INSERT INTO wordstest (id,word,class_id,level) VALUES (5,'ก',1,1)");
    media_job_enqueue($pdo, 'audio', 5, false);
    media_job_enqueue($pdo, 'image', 5, false);
    media_job_fail($pdo, (int)media_job_claim($pdo, 'audio')['id'], 'x');
    $c = media_jobs_counts($pdo);
    ok($c['queued'] === 1 && $c['failed'] === 1, 'counts: 1 queued + 1 failed');
});

run_test('PromptTemplate: overrides + word classification', function () {
    $ov = pt_get_override('ช้าง');
    ok($ov !== null && ($ov['category'] ?? '') === 'animal', 'ช้าง override loaded as animal');

    $p = pt_build_comfyui_prompt('', 'ช้าง');
    ok($p['wasOverridden'] === true, 'override applied to prompt');
    ok(strpos($p['negative'], 'extra legs') !== false, 'animal negative includes anatomy guard');
    ok(strpos($p['negative'], 'mammoth') !== false, 'override extraNegative merged into negative');

    $t = pt_build_comfyui_prompt('a wooden brown table on cream background', 'โต๊ะ');
    ok(strpos($t['positive'], 'close-up') !== false, 'concrete noun → close-up style');

    $wtp = pt_build_what_to_draw_prompt('วิ่ง');
    ok(strpos($wtp, 'AMBIGUOUS') !== false, 'what_to_draw prompt carries AMBIGUOUS rule');
    $ovp = pt_build_what_to_draw_prompt('ช้าง');
    ok(strpos($ovp, 'OVERRIDE') === 0, 'override word short-circuits LLM prompt');
});

run_test('MediaStorage: hash, paths, traversal guard, write', function () {
    $h1 = media_hash(7, 'ม้า', 'v1');
    ok($h1 === media_hash(7, 'ม้า', 'v1') && strlen($h1) === 12, 'hash deterministic, 12 hex chars');
    ok(media_hash(7, 'ม้า', 'v2') !== $h1, 'version string changes the hash');
    ok(media_audio_relpath(7, $h1) === "/media/audio/wordstest/7/{$h1}.mp3", 'audio relpath shape');
    ok(media_image_relpath(7, $h1) === "/media/image/wordstest/7/{$h1}_original.png", 'image relpath shape');

    ok(media_public_to_fullpath('/etc/passwd') === null, 'traversal: non-/media path rejected');
    ok(media_public_to_fullpath('/media/../secret') === null, 'traversal: .. rejected');
    ok(media_public_to_fullpath('/media/audio/wordstest/7/x.mp3') !== null, 'valid /media path accepted');

    // เขียนไฟล์จริงลง temp MEDIA_ROOT (สร้างโฟลเดอร์ให้เอง)
    $full = media_audio_fullpath(99, 'deadbeefcafe');
    media_save_bytes($full, 'ID3test-bytes');
    ok(is_file($full) && file_get_contents($full) === 'ID3test-bytes', 'media_save_bytes writes file + makes dirs');
    @unlink($full);
});
