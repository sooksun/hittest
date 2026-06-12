<?php
/**
 * includes/media_jobs.php — คิวงานสร้างสื่อ (ตาราง game_media_jobs) แทน BullMQ/Redis
 *
 * worker (scripts/process_media_jobs.php) ดึงงาน queued → processing → done/failed
 * enqueue เป็น idempotent: ไม่สร้างงานซ้ำถ้ามีงาน active (queued/processing) ของ (type,wordId) อยู่แล้ว
 */

/**
 * เพิ่มงานเข้า queue — คืน ['status'=>'queued'|'exists', 'jobId'=>?int]
 * @param bool $force สร้างใหม่แม้มีของเดิม (ส่งต่อให้ pipeline regenerate)
 */
function media_job_enqueue(PDO $pdo, string $type, int $wordId, bool $force = false, ?string $by = null): array
{
    $type = ($type === 'image') ? 'image' : 'audio';

    if (!$force) {
        // มีงาน active อยู่แล้วไหม (กันคิวซ้ำ)
        $q = $pdo->prepare(
            "SELECT id FROM game_media_jobs
             WHERE type = ? AND word_id = ? AND status IN ('queued','processing') LIMIT 1"
        );
        $q->execute([$type, $wordId]);
        $existing = $q->fetchColumn();
        if ($existing) {
            return ['status' => 'exists', 'jobId' => (int)$existing];
        }
    }

    $ins = $pdo->prepare(
        'INSERT INTO game_media_jobs (type, word_id, status, forced, requested_by, created_at, updated_at)
         VALUES (?,?,?,?,?,NOW(3),NOW(3))'
    );
    $ins->execute([$type, $wordId, 'queued', $force ? 1 : 0, $by !== null ? mb_substr($by, 0, 64) : null]);
    return ['status' => 'queued', 'jobId' => (int)$pdo->lastInsertId()];
}

/**
 * ดึงงาน queued 1 งานมาเป็น processing แบบ atomic (FOR UPDATE SKIP LOCKED)
 * คืนแถวงาน [id,type,word_id,forced,attempts] หรือ null ถ้าคิวว่าง
 */
function media_job_claim(PDO $pdo, ?string $type = null): ?array
{
    $pdo->beginTransaction();
    try {
        $sql = "SELECT id, type, word_id, forced, attempts FROM game_media_jobs
                WHERE status = 'queued'";
        $params = [];
        if ($type === 'audio' || $type === 'image') {
            $sql .= ' AND type = ?';
            $params[] = $type;
        }
        $sql .= ' ORDER BY id ASC LIMIT 1 FOR UPDATE SKIP LOCKED';

        $sel = $pdo->prepare($sql);
        $sel->execute($params);
        $job = $sel->fetch();

        if (!$job) {
            $pdo->commit();
            return null;
        }
        $pdo->prepare(
            "UPDATE game_media_jobs SET status='processing', attempts = attempts + 1, updated_at = NOW(3) WHERE id = ?"
        )->execute([(int)$job['id']]);
        $pdo->commit();

        $job['attempts'] = (int)$job['attempts'] + 1;
        return $job;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/** ทำงานสำเร็จ */
function media_job_complete(PDO $pdo, int $jobId): void
{
    $pdo->prepare("UPDATE game_media_jobs SET status='done', last_error=NULL, updated_at=NOW(3) WHERE id=?")
        ->execute([$jobId]);
}

/** ทำงานล้มเหลว (เก็บข้อความ error) */
function media_job_fail(PDO $pdo, int $jobId, string $error): void
{
    $pdo->prepare("UPDATE game_media_jobs SET status='failed', last_error=?, updated_at=NOW(3) WHERE id=?")
        ->execute([mb_substr($error, 0, 1000), $jobId]);
}

/** รีเซตงาน failed → queued เพื่อรันใหม่ — คืน true ถ้ามีงานถูกแก้ */
function media_job_retry(PDO $pdo, int $jobId): bool
{
    $st = $pdo->prepare("UPDATE game_media_jobs SET status='queued', last_error=NULL, updated_at=NOW(3) WHERE id=? AND status='failed'");
    $st->execute([$jobId]);
    return $st->rowCount() > 0;
}

/**
 * ประมวลผลงานในคิวสูงสุด $limit งาน — ใช้ร่วมกัน: CLI worker + ปุ่ม "ดำเนินการคิว" ในหน้า admin
 *   claim → pipeline → complete/fail ต่อ 1 งาน · concurrency-safe ด้วย SKIP LOCKED (รันพร้อม CLI ได้)
 * @param ?string  $type audio|image หรือ null = ทั้งสอง
 * @param ?callable $log  fn(string $msg, bool $isError) — optional (CLI ใช้พิมพ์ progress)
 * @return array ['done'=>int,'skip'=>int,'fail'=>int,'processed'=>int]
 */
function media_jobs_run_batch(PDO $pdo, ?string $type, int $limit, ?callable $log = null): array
{
    require_once __DIR__ . '/media_pipeline.php';   // pipeline_generate_audio/image
    $done = $fail = $skip = 0;
    for ($i = 0; $i < $limit; $i++) {
        $job = media_job_claim($pdo, $type);
        if ($job === null) {
            break;                                  // คิวว่าง
        }
        $jobId  = (int)$job['id'];
        $wordId = (int)$job['word_id'];
        $forced = (int)$job['forced'] === 1;
        try {
            $r = $job['type'] === 'audio'
                ? pipeline_generate_audio($pdo, $wordId, $forced)
                : pipeline_generate_image($pdo, $wordId, ['force' => $forced]);
            if (!empty($r['ok'])) {
                media_job_complete($pdo, $jobId);
                if (!empty($r['skipped'])) { $skip++; } else { $done++; }
                if ($log) { $log(sprintf('#%d %s word=%d -> %s', $jobId, $job['type'], $wordId, !empty($r['skipped']) ? 'skip(มีอยู่แล้ว)' : 'done'), false); }
            } else {
                media_job_fail($pdo, $jobId, (string)($r['error'] ?? 'ไม่ทราบสาเหตุ'));
                $fail++;
                if ($log) { $log(sprintf('#%d %s word=%d -> FAIL: %s', $jobId, $job['type'], $wordId, (string)($r['error'] ?? '')), true); }
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            media_job_fail($pdo, $jobId, $e->getMessage());
            $fail++;
            if ($log) { $log(sprintf('#%d %s word=%d -> EXCEPTION: %s', $jobId, $job['type'], $wordId, $e->getMessage()), true); }
        }
    }
    return ['done' => $done, 'skip' => $skip, 'fail' => $fail, 'processed' => $done + $skip + $fail];
}

/** นับงานตามสถานะ (ไว้โชว์ในหน้า admin) */
function media_jobs_counts(PDO $pdo): array
{
    $out = ['queued' => 0, 'processing' => 0, 'done' => 0, 'failed' => 0];
    foreach ($pdo->query("SELECT status, COUNT(*) c FROM game_media_jobs GROUP BY status") as $r) {
        $out[$r['status']] = (int)$r['c'];
    }
    return $out;
}

/** งานล่าสุด (ไว้โชว์/retry ในหน้า admin) */
function media_jobs_recent(PDO $pdo, int $limit = 50, ?string $status = null): array
{
    $limit = max(1, min(500, $limit));
    if (in_array($status, ['queued', 'processing', 'done', 'failed'], true)) {
        $st = $pdo->prepare(
            "SELECT j.*, w.word FROM game_media_jobs j LEFT JOIN wordstest w ON w.id = j.word_id
             WHERE j.status = ? ORDER BY j.id DESC LIMIT {$limit}"
        );
        $st->execute([$status]);
    } else {
        $st = $pdo->query(
            "SELECT j.*, w.word FROM game_media_jobs j LEFT JOIN wordstest w ON w.id = j.word_id
             ORDER BY j.id DESC LIMIT {$limit}"
        );
    }
    return $st->fetchAll();
}
