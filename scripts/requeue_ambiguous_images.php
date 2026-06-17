<?php
/**
 * scripts/requeue_ambiguous_images.php — กู้ภาพคำที่เคยเป็น AMBIGUOUS (ข้อมูลเก่า) ให้สร้างใหม่
 *
 * พื้นหลัง: 425 คำถูกตีตรา image_status='AMBIGUOUS' (what_to_draw=NULL) สมัย prompt template เก่า
 * ก่อนเปลี่ยนมาวาด "ฉากบริบท" เสมอ. คำเหล่านี้ส่วนใหญ่มี override ใน config/word_overrides.json แล้ว
 * (กลุ่ม นามธรรม_/วัฒนธรรม_/รูปธรรม_) ที่เหลือสร้างผ่าน LLM ได้ตามปกติ.
 *
 * สคริปต์นี้ enqueue งานภาพแบบ force ให้คำ AMBIGUOUS (และ FAILED ถ้าใส่ --include-failed)
 * ยกเว้นคำไวยากรณ์ที่วาดไม่ได้จริง (D_FUNCTION 21 คำ — ปล่อยไม่มีรูป) แล้วให้ worker drain
 *
 *   php scripts/requeue_ambiguous_images.php --dry-run            # ดูว่าจะ enqueue กี่คำ ไม่เขียนจริง
 *   php scripts/requeue_ambiguous_images.php                       # enqueue อย่างเดียว (drain ด้วย process_media_jobs.php)
 *   php scripts/requeue_ambiguous_images.php --run                 # enqueue แล้ว drain คิวทันที (ต้องมี ComfyUI พร้อม)
 *   php scripts/requeue_ambiguous_images.php --run --limit=10      # ทดสอบ 10 คำแรก
 *   php scripts/requeue_ambiguous_images.php --include-failed --run # รวมคำ FAILED ด้วย
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only\n"); }
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/media_jobs.php';

// คำไวยากรณ์/อนุภาคที่ไม่มีภาพแทนได้จริง — ปล่อยไม่มีรูป (หน้า practice รองรับ null อยู่แล้ว)
// (ค่ะ จึง ได้ เผื่อ อะไร ไว้ นี้ ประเดี๋ยว คล้องจอง แพศยา อนึ่ง วรรค ฤา อเนก สารพัด ไซร้ พยางค์ ปราศจาก ภาวะ สรรพนาม วรรณยุกต์)
const SKIP_FUNCTION_IDS = [20,30,34,36,47,53,56,1931,1999,2089,2112,2215,2260,2261,2291,2367,2372,2456,2511,2571,2651];

$dry           = in_array('--dry-run', $argv, true);
$run           = in_array('--run', $argv, true);
$includeFailed = in_array('--include-failed', $argv, true);
$limit = 0;
foreach ($argv as $a) { if (preg_match('/^--limit=(\d+)$/', $a, $m)) { $limit = (int)$m[1]; } }

$pdo = db();
$statuses = $includeFailed ? "'AMBIGUOUS','FAILED'" : "'AMBIGUOUS'";
$skipList = implode(',', SKIP_FUNCTION_IDS);
$sql = "SELECT id, word FROM wordstest
        WHERE image_status IN ($statuses)
          AND word IS NOT NULL AND word <> ''
          AND id NOT IN ($skipList)
        ORDER BY class_id, id" . ($limit > 0 ? " LIMIT {$limit}" : "");
$rows = $pdo->query($sql)->fetchAll();

fwrite(STDOUT, sprintf(
    "พบ %d คำ (status: %s, ยกเว้น D_FUNCTION %d คำ)%s\n",
    count($rows), $includeFailed ? 'AMBIGUOUS+FAILED' : 'AMBIGUOUS',
    count(SKIP_FUNCTION_IDS), $dry ? ' [DRY-RUN]' : ''
));

if ($dry) {
    foreach ($rows as $r) { fwrite(STDOUT, "  would enqueue #{$r['id']} {$r['word']}\n"); }
    exit(0);
}

$enq = 0;
foreach ($rows as $r) {
    media_job_enqueue($pdo, 'image', (int)$r['id'], true, 'requeue_ambiguous');
    $enq++;
}
fwrite(STDOUT, "enqueue ภาพแบบ force แล้ว: {$enq} งาน\n");

if (!$run) {
    fwrite(STDOUT, "ยังไม่ drain — รัน: php scripts/process_media_jobs.php --type=image\n");
    exit(0);
}

fwrite(STDOUT, "เริ่ม drain คิว (ComfyUI ต้องพร้อม)...\n");
$t0 = microtime(true);
$tot = ['done' => 0, 'skip' => 0, 'fail' => 0, 'processed' => 0];
do {
    $res = media_jobs_run_batch($pdo, 'image', 50, function (string $msg, bool $err) {
        fwrite($err ? STDERR : STDOUT, '  ' . $msg . "\n");
    });
    foreach ($tot as $k => $_) { $tot[$k] += $res[$k]; }
} while ($res['processed'] > 0);

fwrite(STDOUT, sprintf(
    "เสร็จ: done=%d skip=%d fail=%d (%.0fs)\n",
    $tot['done'], $tot['skip'], $tot['fail'], microtime(true) - $t0
));
