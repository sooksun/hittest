<?php
/**
 * scripts/rerender_class_images.php <class_id> [--limit=N]
 *   สร้างภาพใหม่ของทั้งชั้น โดย "ใช้คำบรรยายอังกฤษเดิม" (ที่ LLM เคยให้ไว้แล้ว) — re-render เฉพาะภาพ
 *   ใช้หลังเปลี่ยน checkpoint/สไตล์ prompt: แก้เฉพาะการ render ไม่เรียก LLM ใหม่
 *   (กันคำดี ๆ พลิกเป็น AMBIGUOUS + เร็วกว่าเพราะข้ามขั้น LLM)
 *
 *   php scripts/rerender_class_images.php 1            # ทั้ง ป.1
 *   php scripts/rerender_class_images.php 1 --limit=5  # ทดสอบ 5 คำ
 *
 * คำที่ไม่มีคำบรรยายเดิม (เคย AMBIGUOUS) จะถูกข้าม
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only\n"); }
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/media_pipeline.php';

$class = (int)($argv[1] ?? 0);
if ($class < 1 || $class > 6) { exit("usage: php scripts/rerender_class_images.php <class_id 1-6> [--limit=N]\n"); }
$limit = 0;
foreach ($argv as $a) { if (preg_match('/^--limit=(\d+)$/', $a, $m)) { $limit = (int)$m[1]; } }

$pdo = db();
// คำที่มี "คำบรรยายอังกฤษ" เดิมให้ใช้ซ้ำ (จาก wordstest.what_to_draw หรือ game_prompt_history)
$sql = "SELECT w.id, w.word,
               COALESCE(NULLIF(w.what_to_draw,''), gph.what_to_draw_en) AS en
        FROM wordstest w
        LEFT JOIN game_prompt_history gph ON gph.word_id = w.id AND gph.is_active = 1
        WHERE w.class_id = ? AND w.word IS NOT NULL AND w.word <> ''
        HAVING en IS NOT NULL AND en <> ''
        ORDER BY w.id" . ($limit > 0 ? " LIMIT {$limit}" : "");
$st = $pdo->prepare($sql);
$st->execute([$class]);
$rows = $st->fetchAll();

fwrite(STDOUT, "re-render ป.{$class}: " . count($rows) . " คำ (ใช้คำบรรยายเดิม, checkpoint=" . (defined('COMFYUI_CHECKPOINT') ? COMFYUI_CHECKPOINT : '?') . ")\n");
$done = $fail = 0;
$t0 = microtime(true);
foreach ($rows as $r) {
    $res = pipeline_generate_image($pdo, (int)$r['id'], ['force' => true, 'whatToDrawEn' => $r['en']]);
    if (($res['status'] ?? '') === 'DONE') { $done++; } else { $fail++; }
    fwrite(STDOUT, sprintf("[%s] #%d %s -> %s\n", date('H:i:s'), (int)$r['id'], $r['word'], $res['status'] ?? 'ERR'));
}
fwrite(STDOUT, sprintf("เสร็จ: done=%d fail=%d (%.0fs)\n", $done, $fail, microtime(true) - $t0));
