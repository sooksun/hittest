<?php
/**
 * scripts/prune_audit_logs.php — ลบ audit_logs ที่เก่ากว่า AUDIT_RETENTION_DAYS
 *
 * รันมือ:        php scripts/prune_audit_logs.php
 * Linux cron:    0 3 * * *  php /path/to/newhittest/scripts/prune_audit_logs.php >> /var/log/ht_audit_prune.log 2>&1
 * Windows Task Scheduler: action = php.exe, args = D:\...\scripts\prune_audit_logs.php (daily)
 *
 * Optional override: php scripts/prune_audit_logs.php 30   (เก็บ 30 วัน)
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}

require_once dirname(__DIR__) . '/includes/functions.php';

$days = isset($argv[1]) && ctype_digit($argv[1])
    ? max(1, (int)$argv[1])
    : (defined('AUDIT_RETENTION_DAYS') ? AUDIT_RETENTION_DAYS : 90);

try {
    $deleted = prune_audit_logs(db(), $days);
    fwrite(STDOUT, sprintf("[%s] pruned %d audit_logs row(s) older than %d day(s)\n",
        date('Y-m-d H:i:s'), $deleted, $days));
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, sprintf("[%s] prune FAILED: %s\n", date('Y-m-d H:i:s'), $e->getMessage()));
    exit(1);
}
