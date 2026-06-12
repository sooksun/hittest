<?php
/**
 * scripts/migrate_media_from_readthai.php — ย้ายสื่อเดิมจาก readthai เข้ามาเป็นของ newhittest
 *
 *   php scripts/migrate_media_from_readthai.php            # dry-run: รายงานอย่างเดียว ไม่แตะอะไร
 *   php scripts/migrate_media_from_readthai.php --apply     # ทำจริง: copy path เข้า wordstest + copy ไฟล์
 *
 * ทำ 2 อย่าง:
 *   1) คัดลอกคอลัมน์ path (sound_path, image_path, what_to_draw, image_status, …) จาก readthai.wordstest
 *      → ssraexhi_hittest.wordstest (id ตรงกัน 1:1) — idempotent
 *   2) คัดลอก "เฉพาะไฟล์ที่ถูกอ้างถึงใน DB" (ไม่ใช่ทั้ง 739MB) จากโฟลเดอร์ media ของ readthai
 *      → โฟลเดอร์จริง media_local/ ของ newhittest
 *
 * ขั้นสุดท้าย (สลับ symlink → โฟลเดอร์จริง) สคริปต์จะ "พิมพ์คำสั่งให้" ทำเอง — ไม่แตะ filesystem ส่วนนั้น
 * เพราะ media ปัจจุบันเป็น junction/symlink ของ Windows (ลบผิดเสี่ยง) ทำมือแล้วตรวจได้ปลอดภัยกว่า
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}
require_once dirname(__DIR__) . '/includes/functions.php';

$apply  = in_array('--apply', $argv, true);
$appDir = dirname(__DIR__);
$pdo    = db();

fwrite(STDOUT, "== migrate_media_from_readthai " . ($apply ? "(APPLY)" : "(DRY-RUN)") . " ==\n");

/* ── 0) หาโฟลเดอร์ media ต้นทางของ readthai ─────────────────────────────────────── */
$mediaPath = $appDir . '/media';
$src = @readlink($mediaPath) ?: (is_dir($mediaPath) ? realpath($mediaPath) : null);
// ถ้า media ถูกสลับเป็นโฟลเดอร์จริงของ newhittest แล้ว ให้ fallback ไป path ของ readthai โดยตรง
if ($src === null || strpos($src, 'readthai') === false) {
    $fallback = 'D:/laragon/www/readthai/kingdom-of-words/apps/api/media';
    if (is_dir($fallback)) {
        $src = $fallback;
    }
}
$src = $src ? str_replace('\\', '/', $src) : null;
fwrite(STDOUT, "source media : " . ($src ?: '(ไม่พบ — ข้ามการ copy ไฟล์)') . "\n");
$dest = $appDir . '/media_local';
fwrite(STDOUT, "dest media   : {$dest}\n");

/* ── 1) คัดลอกคอลัมน์ path จาก readthai.wordstest → wordstest (id ตรงกัน) ────────── */
$dbCopied = 0;
try {
    if ($apply) {
        $sql = 'UPDATE wordstest h JOIN readthai.wordstest r ON r.id = h.id
                SET h.sound_path         = r.sound_path,
                    h.image_path         = r.image_path,
                    h.what_to_draw       = r.what_to_draw,
                    h.image_status       = r.image_status,
                    h.image_reason       = r.image_reason,
                    h.image_generated_at = r.image_generated_at
                WHERE r.sound_path IS NOT NULL OR r.image_status = "DONE"';
        $dbCopied = (int)$pdo->exec($sql);
        fwrite(STDOUT, "DB: อัปเดต path เข้า wordstest {$dbCopied} แถว\n");
    } else {
        $n = (int)$pdo->query('SELECT COUNT(*) FROM wordstest h JOIN readthai.wordstest r ON r.id = h.id
                               WHERE r.sound_path IS NOT NULL OR r.image_status = "DONE"')->fetchColumn();
        fwrite(STDOUT, "DB: จะอัปเดต path ~{$n} แถว (dry-run)\n");
    }
} catch (Throwable $e) {
    fwrite(STDERR, "DB copy ข้าม: {$e->getMessage()} (ฐาน readthai อาจไม่มีบนเครื่องนี้)\n");
}

/* ── 2) รวมรายการไฟล์ที่ถูกอ้างถึง แล้ว copy เฉพาะไฟล์เหล่านั้น ──────────────────── */
$refs = [];   // relative paths เริ่มด้วย media/...
try {
    $rows = $pdo->query("SELECT sound_path, image_path FROM readthai.wordstest
                         WHERE sound_path IS NOT NULL OR image_status = 'DONE'");
    foreach ($rows as $r) {
        foreach (media_collect_refs($r['sound_path'], $r['image_path']) as $rel) {
            $refs[$rel] = true;
        }
    }
} catch (Throwable $e) {
    fwrite(STDERR, "อ่านรายการไฟล์จาก readthai ไม่ได้: {$e->getMessage()}\n");
}
$refs = array_keys($refs);
fwrite(STDOUT, "ไฟล์ที่อ้างถึงใน DB: " . count($refs) . " ไฟล์\n");

$copied = $skipped = $missing = 0;
$bytes  = 0;
if ($src) {
    foreach ($refs as $rel) {
        $from = $src . '/' . $rel;            // rel เช่น audio/wordstest/1/xxx.mp3 (เทียบกับโฟลเดอร์ media)
        $to   = $appDir . '/media_local/' . $rel;
        if (!is_file($from)) {
            $missing++;
            continue;
        }
        $bytes += (int)@filesize($from);
        if (is_file($to) && @filesize($to) === @filesize($from)) {
            $skipped++;
            continue;
        }
        if ($apply) {
            $dir = dirname($to);
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            if (@copy($from, $to)) {
                $copied++;
            } else {
                $missing++;
            }
        } else {
            $copied++;   // dry-run: นับว่าจะ copy
        }
    }
}
fwrite(STDOUT, sprintf("ไฟล์: copy %d, ข้าม(มีแล้ว) %d, หาย %d, รวม ~%.1f MB\n",
    $copied, $skipped, $missing, $bytes / 1048576));

/* ── 3) คำสั่งสลับ symlink → โฟลเดอร์จริง (ทำมือ ตรวจได้) ───────────────────────── */
if ($apply) {
    fwrite(STDOUT, "\n== ขั้นสุดท้าย: สลับ media (junction) → โฟลเดอร์จริง — รันคำสั่งนี้เองแล้วตรวจ ==\n");
    fwrite(STDOUT, "  cmd //c rmdir \"" . str_replace('/', '\\', $mediaPath) . "\"   # ลบ junction (ไม่กระทบไฟล์ต้นทาง)\n");
    fwrite(STDOUT, "  mv media_local media                                          # ใช้โฟลเดอร์จริงแทน\n");
    fwrite(STDOUT, "ตรวจ: ls -la media | head ; ดูว่าเป็น drwx (ไม่ใช่ lrwx) และมีไฟล์ครบ\n");
} else {
    fwrite(STDOUT, "\n(ใส่ --apply เพื่อทำจริง)\n");
}
exit(0);

/* ── helper: รวม path ที่อ้างถึงจาก sound_path + image_path(JSON) → rel เริ่มด้วย media/ ── */
function media_collect_refs(?string $soundPath, ?string $imagePath): array
{
    $out = [];
    $add = static function (?string $p) use (&$out) {
        $p = trim((string)$p);
        if ($p !== '' && strpos($p, '/media/') !== false) {
            // path ใน DB = "/media/audio/..." → คืน rel เทียบกับโฟลเดอร์ media = "audio/..."
            $out[] = ltrim(substr($p, strpos($p, '/media/') + 7), '/');
        }
    };
    $add($soundPath);
    if ($imagePath) {
        $dec = json_decode($imagePath, true);
        if (is_array($dec)) {
            foreach ($dec as $v) {
                if (is_string($v)) {
                    $add($v);
                }
            }
        } else {
            $add($imagePath);
        }
    }
    return $out;
}
