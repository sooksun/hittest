<?php
/**
 * admin_media_action.php — backend JSON ของหน้า admin_media.php (ผู้ดูแลระบบเท่านั้น)
 *
 * POST action=
 *   generate_now    {type, wordId, force?, whatToDrawEn?, positive?, negative?, seed?, checkpoint?}
 *                   → สร้างสื่อทันที (synchronous) คืนผล
 *   enqueue_one     {type, wordId, force?}                → เข้าคิว 1 คำ
 *   enqueue_missing {type, grade?, level?, limit?}        → เข้าคิวทุกคำที่ยังไม่มีสื่อ
 *   retry_job       {jobId}                               → รีเซต failed → queued
 *   clear_jobs      {scope: done|failed|all}              → ลบงานออกจากตาราง
 *   jobs            {status?}                             → คืนงานล่าสุด + ตัวนับ (ไว้ refresh)
 *
 * ทุก action ถูก audit + จำกัดเฉพาะ ADMIN_SMIS (admin_auth.php)
 */
require __DIR__ . '/includes/admin_auth.php';
require __DIR__ . '/includes/media_jobs.php';
require __DIR__ . '/includes/media_pipeline.php';

$pdo = db();
$me  = ['smis' => current_smis(), 'sc_id' => current_sc_id()];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'message' => 'POST only'], 405);
}

// รับได้ทั้ง JSON body และ form POST
$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) {
    $body = $_POST;
}
$action = (string)($body['action'] ?? '');
$type   = (($body['type'] ?? '') === 'image') ? 'image' : 'audio';

/** audit helper สั้น ๆ */
$audit = function (string $act, array $meta = [], int $code = 200) use ($pdo, $me) {
    audit_log_event($pdo, [
        'sc_id' => $me['sc_id'], 'stuid' => 'admin:' . $me['smis'], 'role' => 'admin',
        'action' => $act, 'entity_type' => 'media_gen', 'status_code' => $code, 'meta_json' => $meta,
    ]);
};

try {
    switch ($action) {

        case 'generate_now': {
            $wordId = (int)($body['wordId'] ?? 0);
            if ($wordId <= 0) {
                json_response(['ok' => false, 'message' => 'wordId ไม่ถูกต้อง'], 400);
            }
            @set_time_limit(0);   // ภาพอาจนาน
            if ($type === 'audio') {
                $r = pipeline_generate_audio($pdo, $wordId, !empty($body['force']));
            } else {
                $r = pipeline_generate_image($pdo, $wordId, [
                    'force'        => !empty($body['force']),
                    'whatToDrawEn' => trim((string)($body['whatToDrawEn'] ?? '')),
                    'positive'     => trim((string)($body['positive'] ?? '')),
                    'negative'     => trim((string)($body['negative'] ?? '')),
                    'seed'         => isset($body['seed']) && $body['seed'] !== '' ? (int)$body['seed'] : null,
                    'checkpoint'   => trim((string)($body['checkpoint'] ?? '')) ?: null,
                ]);
            }
            $audit('media_generate_now', ['type' => $type, 'wordId' => $wordId, 'ok' => !empty($r['ok']), 'status' => $r['status'] ?? null], !empty($r['ok']) ? 200 : 502);
            json_response(['ok' => !empty($r['ok'])] + $r);
        }

        case 'enqueue_one': {
            $wordId = (int)($body['wordId'] ?? 0);
            if ($wordId <= 0) {
                json_response(['ok' => false, 'message' => 'wordId ไม่ถูกต้อง'], 400);
            }
            $r = media_job_enqueue($pdo, $type, $wordId, !empty($body['force']), $me['smis']);
            $audit('media_enqueue_one', ['type' => $type, 'wordId' => $wordId, 'result' => $r['status']]);
            json_response(['ok' => true] + $r);
        }

        case 'enqueue_missing': {
            $grade = isset($body['grade']) && $body['grade'] !== '' ? max(1, min(6, (int)$body['grade'])) : null;
            $level = isset($body['level']) && $body['level'] !== '' ? max(1, min(3, (int)$body['level'])) : null;
            $limit = max(1, min(2000, (int)($body['limit'] ?? 500)));

            $where = ['word IS NOT NULL', "word <> ''"];
            $params = [];
            if ($type === 'audio') {
                $where[] = "(sound_path IS NULL OR sound_path = '')";
            } else {
                $where[] = "image_status <> 'DONE'";
            }
            if ($grade !== null) { $where[] = 'class_id = ?'; $params[] = $grade; }
            if ($level !== null) { $where[] = 'level = ?';    $params[] = $level; }

            $sql = 'SELECT id FROM wordstest WHERE ' . implode(' AND ', $where) . " ORDER BY id LIMIT {$limit}";
            $st = $pdo->prepare($sql);
            $st->execute($params);
            $ids = $st->fetchAll(PDO::FETCH_COLUMN);

            $queued = $existing = 0;
            foreach ($ids as $id) {
                $r = media_job_enqueue($pdo, $type, (int)$id, false, $me['smis']);
                if ($r['status'] === 'queued') { $queued++; } else { $existing++; }
            }
            $audit('media_enqueue_missing', ['type' => $type, 'grade' => $grade, 'level' => $level, 'queued' => $queued, 'existing' => $existing]);
            json_response(['ok' => true, 'queued' => $queued, 'existing' => $existing, 'scanned' => count($ids)]);
        }

        case 'retry_job': {
            $jobId = (int)($body['jobId'] ?? 0);
            $ok = $jobId > 0 && media_job_retry($pdo, $jobId);
            $audit('media_retry_job', ['jobId' => $jobId, 'ok' => $ok]);
            json_response(['ok' => $ok]);
        }

        case 'clear_jobs': {
            $scope = (string)($body['scope'] ?? 'done');
            if ($scope === 'all') {
                $n = $pdo->exec('DELETE FROM game_media_jobs');
            } elseif (in_array($scope, ['done', 'failed'], true)) {
                $st = $pdo->prepare('DELETE FROM game_media_jobs WHERE status = ?');
                $st->execute([$scope]);
                $n = $st->rowCount();
            } else {
                json_response(['ok' => false, 'message' => 'scope ไม่ถูกต้อง'], 400);
            }
            $audit('media_clear_jobs', ['scope' => $scope, 'deleted' => $n]);
            json_response(['ok' => true, 'deleted' => (int)$n]);
        }

        case 'jobs': {
            $status = (string)($body['status'] ?? '') ?: null;
            json_response([
                'ok'     => true,
                'counts' => media_jobs_counts($pdo),
                'jobs'   => media_jobs_recent($pdo, 60, $status),
            ]);
        }

        case 'process_queue': {
            @set_time_limit(0);                       // งานภาพอาจนาน
            $qtype = in_array(($body['type'] ?? ''), ['audio', 'image'], true) ? (string)$body['type'] : null;
            $limit = max(1, min(10, (int)($body['limit'] ?? 2)));   // batch เล็ก กัน Apache/PHP timeout (เรียกซ้ำจาก JS จนหมดคิว)
            $res    = media_jobs_run_batch($pdo, $qtype, $limit);
            $counts = media_jobs_counts($pdo);
            $audit('media_process_queue', ['type' => $qtype, 'limit' => $limit] + $res);
            json_response(['ok' => true] + $res + [
                'remaining' => $counts['queued'] + $counts['processing'],
                'counts'    => $counts,
            ]);
        }

        default:
            json_response(['ok' => false, 'message' => 'action ไม่รู้จัก'], 400);
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $audit('media_action_error', ['action' => $action, 'error' => $e->getMessage()], 500);
    json_response(['ok' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()], 500);
}
