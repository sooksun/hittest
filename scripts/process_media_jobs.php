<?php
/**
 * scripts/process_media_jobs.php — worker ดึงงานสร้างสื่อจาก queue (game_media_jobs) มาประมวลผล
 *
 *   php scripts/process_media_jobs.php                 # ทำงาน queued สูงสุด MEDIA_GEN_WORKER_BATCH งาน
 *   php scripts/process_media_jobs.php --type=audio    # เฉพาะเสียง
 *   php scripts/process_media_jobs.php --type=image    # เฉพาะภาพ (ช้า — ต้องเข้าถึง ComfyUI ได้)
 *   php scripts/process_media_jobs.php --limit=100     # กำหนดจำนวนงานต่อรอบ
 *
 * Linux cron:    * * * * *  php /path/to/newhittest/scripts/process_media_jobs.php --type=audio >> /var/log/ht_media.log 2>&1
 * Windows Task Scheduler: action = php.exe, args = D:\...\scripts\process_media_jobs.php
 *
 * lockfile กันรันซ้อน (ถ้ารอบก่อนยังไม่จบ จะข้าม)
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/media_jobs.php';
require_once dirname(__DIR__) . '/includes/media_pipeline.php';

// ── flags ──────────────────────────────────────────────────────────────────────
$type   = null;
$limit  = defined('MEDIA_GEN_WORKER_BATCH') ? (int)MEDIA_GEN_WORKER_BATCH : 25;
$workerId = '';                          // --id=N → รัน worker ขนานได้ (lock คนละไฟล์; claim ปลอดภัยด้วย SKIP LOCKED)
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--type=(audio|image)$/', $a, $m)) {
        $type = $m[1];
    } elseif (preg_match('/^--limit=(\d+)$/', $a, $m)) {
        $limit = max(1, min(5000, (int)$m[1]));
    } elseif (preg_match('/^--id=(\w+)$/', $a, $m)) {
        $workerId = $m[1];
    }
}

// ── lock กันรันซ้อน (ต่อ worker id — หลายตัวรันพร้อมกันได้ถ้า id ต่างกัน) ──────────
$lockPath = sys_get_temp_dir() . '/newhittest_media_worker' . ($workerId !== '' ? "_{$workerId}" : '') . '.lock';
$lock = fopen($lockPath, 'c');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
    fwrite(STDOUT, "[" . date('H:i:s') . "] worker อื่นกำลังทำงานอยู่ — ข้ามรอบนี้\n");
    exit(0);
}

@set_time_limit(0);                  // งานภาพอาจนาน
$pdo = db();
$started = microtime(true);

// ใช้ตัวประมวลผลร่วม (เดียวกับปุ่ม "ดำเนินการคิว" ในหน้า admin) — พิมพ์ progress ทาง STDOUT/STDERR
$res = media_jobs_run_batch($pdo, $type, $limit, function (string $msg, bool $isError) {
    fwrite($isError ? STDERR : STDOUT, '[' . date('H:i:s') . "] $msg\n");
});

fwrite(STDOUT, sprintf("[%s] เสร็จ: done=%d skip=%d fail=%d (%.1fs)\n",
    date('H:i:s'), $res['done'], $res['skip'], $res['fail'], microtime(true) - $started));

flock($lock, LOCK_UN);
fclose($lock);
exit(0);
