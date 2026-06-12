<?php
/**
 * tests/MediaPipelineTest.php — พฤติกรรม pipeline ที่ "ไม่แตะเครือข่าย" (ตรวจ logic ตัดสินใจ)
 *   - audio: ถ้ามีไฟล์เสียงอยู่แล้ว + ไม่ force → ข้าม (skipped) โดยไม่เรียก Botnoi
 *   - image: คำไม่มี override + LLM ปิด → คืน MANUAL โดยไม่เรียก ComfyUI + ตั้งสถานะให้ถูก
 * (การเรียก Botnoi/ComfyUI จริงตรวจแบบ end-to-end ด้วยมือแล้ว — ที่นี่คุมเฉพาะ branch ในตัว)
 */
require_once dirname(__DIR__) . '/includes/media_pipeline.php';

run_test('MediaPipeline(audio): skips when file already exists (no network)', function () {
    $pdo = create_test_db();
    $hash = 'abc123def456';
    $rel  = media_audio_relpath(5, $hash);
    // มีไฟล์จริงอยู่แล้ว + DB ชี้ไปที่ไฟล์นั้น
    media_save_bytes(media_audio_fullpath(5, $hash), 'fake-mp3-bytes');
    $pdo->prepare("INSERT INTO wordstest (id,word,class_id,level,sound_path) VALUES (5,'ก',1,1,?)")->execute([$rel]);

    $r = pipeline_generate_audio($pdo, 5, false);
    ok($r['ok'] === true && !empty($r['skipped']), 'existing audio + no force → ok + skipped');
    ok($r['soundPath'] === $rel, 'returns the existing sound_path unchanged');
    @unlink(media_audio_fullpath(5, $hash));
});

run_test('MediaPipeline(audio): missing word → graceful failure', function () {
    $pdo = create_test_db();
    $r = pipeline_generate_audio($pdo, 999, false);   // ไม่มีคำนี้
    ok($r['ok'] === false && $r['error'] !== null, 'unknown word → ok=false with error (no throw)');
});

run_test('MediaPipeline(image): non-override word + LLM off → MANUAL (no ComfyUI call)', function () {
    if (function_exists('llm_provider') && llm_provider() !== 'none') {
        ok(true, 'SKIPPED: LLM_PROVIDER != none in config/media_gen.php');
        return;
    }
    $pdo = create_test_db();
    $pdo->exec("INSERT INTO wordstest (id,word,class_id,level) VALUES (42,'ทดสอบคำ',1,1)");

    $t0 = microtime(true);
    $r  = pipeline_generate_image($pdo, 42, []);
    $elapsed = microtime(true) - $t0;

    ok($r['status'] === 'MANUAL' && $r['ok'] === false, 'no override + LLM off → MANUAL');
    ok($elapsed < 3.0, 'returns fast (did not attempt ComfyUI)');
    $row = $pdo->query('SELECT image_status,image_reason FROM wordstest WHERE id=42')->fetch();
    ok($row['image_status'] === 'EMPTY' && $row['image_reason'] !== null, 'wordstest status reset to EMPTY with reason');
});

run_test('MediaPipeline(image): missing word → graceful failure', function () {
    $pdo = create_test_db();
    $r = pipeline_generate_image($pdo, 999, []);
    ok($r['ok'] === false && $r['status'] === 'FAILED', 'unknown word → FAILED (no throw)');
});
