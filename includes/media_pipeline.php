<?php
/**
 * includes/media_pipeline.php — ออร์เคสตเรชันสร้างสื่อ (พอร์ตจาก readthai tts.worker + image.worker)
 *
 * ใช้ร่วมกัน 2 ทาง:
 *   - scripts/process_media_jobs.php (worker) — ดึงงานจาก queue แล้วเรียกฟังก์ชันที่นี่
 *   - admin_media_action.php ("สร้างเดี๋ยวนี้") — เรียกตรงแบบ synchronous
 *
 * หน้าที่: เรียก Botnoi/ComfyUI/LLM → เก็บไฟล์ (media_storage) → อัปเดต wordstest (+ game_prompt_history)
 * ฟังก์ชันคืน array สรุปผล ไม่โยน exception ออกไป (จับ Throwable ภายใน) เพื่อให้ worker mark สถานะได้เสมอ
 */

require_once __DIR__ . '/media_storage.php';
require_once __DIR__ . '/botnoi_tts.php';
require_once __DIR__ . '/prompt_template.php';
require_once __DIR__ . '/llm.php';
require_once __DIR__ . '/comfyui.php';

/** โหลดคำจาก wordstest — คืน null ถ้าไม่พบ */
function pipeline_load_word(PDO $pdo, int $wordId): ?array
{
    $st = $pdo->prepare('SELECT id, word, class_id, sound_path, image_path, image_status FROM wordstest WHERE id = ?');
    $st->execute([$wordId]);
    return $st->fetch() ?: null;
}

/**
 * สร้างเสียงอ่าน 1 คำ → เก็บไฟล์ mp3 + อัปเดต wordstest.sound_path
 * คืน ['ok'=>bool, 'soundPath'=>?string, 'skipped'=>bool, 'error'=>?string]
 */
function pipeline_generate_audio(PDO $pdo, int $wordId, bool $force = false): array
{
    $word = pipeline_load_word($pdo, $wordId);
    if (!$word || trim((string)$word['word']) === '') {
        return ['ok' => false, 'soundPath' => null, 'skipped' => false, 'error' => 'ไม่พบคำหรือคำว่าง'];
    }
    $text = trim((string)$word['word']);

    // มีไฟล์อยู่แล้วและไม่บังคับสร้างใหม่ → ข้าม
    if (!$force && !empty($word['sound_path'])) {
        $full = media_public_to_fullpath((string)$word['sound_path']);
        if ($full && is_file($full)) {
            return ['ok' => true, 'soundPath' => (string)$word['sound_path'], 'skipped' => true, 'error' => null];
        }
    }

    try {
        $r = botnoi_generate_mp3($text);
        if (!$r['ok'] || $r['bytes'] === null) {
            return ['ok' => false, 'soundPath' => null, 'skipped' => false, 'error' => $r['error'] ?? 'Botnoi ล้มเหลว'];
        }
        $hash = media_hash($wordId, $text, 'v1');
        media_save_bytes(media_audio_fullpath($wordId, $hash), $r['bytes']);
        $rel = media_audio_relpath($wordId, $hash);

        $pdo->prepare('UPDATE wordstest SET sound_path = ?, sound_generated_at = NOW() WHERE id = ?')
            ->execute([$rel, $wordId]);

        return ['ok' => true, 'soundPath' => $rel, 'skipped' => false, 'error' => null];
    } catch (Throwable $e) {
        return ['ok' => false, 'soundPath' => null, 'skipped' => false, 'error' => $e->getMessage()];
    }
}

/**
 * สร้างภาพ 1 คำ → เก็บไฟล์ png + อัปเดต wordstest.image_* + game_prompt_history
 * $opts: force(bool), whatToDrawEn(string ที่แอดมินพิมพ์), positive/negative(prompt ที่แก้เอง),
 *        seed(int), checkpoint(string)
 * คืน ['ok'=>bool, 'status'=>'DONE'|'FAILED'|'AMBIGUOUS'|'MANUAL', 'imagePath'=>?string,
 *      'promptId'=>?string, 'seed'=>?int, 'error'=>?string]
 */
function pipeline_generate_image(PDO $pdo, int $wordId, array $opts = []): array
{
    $word = pipeline_load_word($pdo, $wordId);
    if (!$word || trim((string)$word['word']) === '') {
        return ['ok' => false, 'status' => 'FAILED', 'imagePath' => null, 'error' => 'ไม่พบคำหรือคำว่าง'];
    }
    $text = trim((string)$word['word']);

    // มีภาพอยู่แล้วและไม่บังคับ → ข้าม
    if (empty($opts['force']) && ($word['image_status'] ?? '') === 'DONE' && !empty($word['image_path'])) {
        return ['ok' => true, 'status' => 'DONE', 'imagePath' => (string)$word['image_path'], 'skipped' => true, 'error' => null];
    }

    pipeline_set_image_status($pdo, $wordId, 'GENERATING', null);

    try {
        // ── 1) หา prompt ──────────────────────────────────────────────────────
        $positive = trim((string)($opts['positive'] ?? ''));
        $negative = trim((string)($opts['negative'] ?? ''));
        $whatEn   = trim((string)($opts['whatToDrawEn'] ?? ''));
        $whatTh   = null;

        if ($positive === '' || $negative === '') {
            // ยังไม่มี prompt สำเร็จรูป → หา whatToDrawEn (override / LLM / ที่แอดมินพิมพ์)
            if ($whatEn === '') {
                $llm = llm_what_to_draw($text);
                if ($llm['status'] === 'ambiguous') {
                    pipeline_set_image_status($pdo, $wordId, 'AMBIGUOUS', $llm['reason'] ?? 'วาดเป็นภาพเดียวไม่ได้');
                    return ['ok' => false, 'status' => 'AMBIGUOUS', 'imagePath' => null, 'error' => $llm['reason'] ?? 'AMBIGUOUS'];
                }
                if ($llm['status'] === 'manual') {
                    pipeline_set_image_status($pdo, $wordId, 'EMPTY', $llm['reason'] ?? 'ต้องพิมพ์คำอธิบายภาพเอง');
                    return ['ok' => false, 'status' => 'MANUAL', 'imagePath' => null, 'error' => $llm['reason'] ?? 'ต้องพิมพ์คำอธิบายภาพเอง'];
                }
                if ($llm['status'] !== 'ok' || empty($llm['whatToDraw'])) {
                    pipeline_set_image_status($pdo, $wordId, 'FAILED', $llm['reason'] ?? 'LLM ล้มเหลว');
                    return ['ok' => false, 'status' => 'FAILED', 'imagePath' => null, 'error' => $llm['reason'] ?? 'LLM ล้มเหลว'];
                }
                $whatEn = (string)$llm['whatToDraw'];
                $whatTh = $llm['whatToDrawTh'] ?? null;
            }
            $built    = pt_build_comfyui_prompt($whatEn, $text);
            $positive = $built['positive'];
            $negative = $built['negative'];
        }

        // ── 2) เรียก ComfyUI ──────────────────────────────────────────────────
        $gen = comfyui_generate_image($positive, $negative, [
            'wordId'     => $wordId,
            'seed'       => $opts['seed']       ?? null,
            'checkpoint' => $opts['checkpoint'] ?? null,
        ]);
        if (!$gen['ok'] || empty($gen['bytes'])) {
            pipeline_set_image_status($pdo, $wordId, 'FAILED', $gen['error'] ?? 'ComfyUI ล้มเหลว');
            return ['ok' => false, 'status' => 'FAILED', 'imagePath' => null, 'error' => $gen['error'] ?? 'ComfyUI ล้มเหลว', 'promptId' => $gen['promptId'] ?? null];
        }

        // ── 3) เก็บไฟล์ + อัปเดต DB ────────────────────────────────────────────
        $hash = media_hash($wordId, $text . '|' . $gen['seed'], 'v2');
        media_save_bytes(media_image_fullpath($wordId, $hash, 'original'), $gen['bytes']);
        $rel  = media_image_relpath($wordId, $hash, 'original');
        $json = json_encode(['image_original' => $rel], JSON_UNESCAPED_UNICODE);

        $checkpoint = $opts['checkpoint'] ?? (defined('COMFYUI_CHECKPOINT') ? COMFYUI_CHECKPOINT : null);

        $pdo->prepare(
            'UPDATE wordstest SET image_path = ?, image_status = "DONE", what_to_draw = ?, image_reason = NULL, image_generated_at = NOW() WHERE id = ?'
        )->execute([$json, $whatEn !== '' ? $whatEn : null, $wordId]);

        pipeline_record_prompt($pdo, $wordId, $text, $positive, $negative, $whatTh, $whatEn, $checkpoint, (int)$gen['seed'], $rel);

        return ['ok' => true, 'status' => 'DONE', 'imagePath' => $rel, 'promptId' => $gen['promptId'] ?? null, 'seed' => (int)$gen['seed'], 'error' => null];
    } catch (Throwable $e) {
        pipeline_set_image_status($pdo, $wordId, 'FAILED', $e->getMessage());
        return ['ok' => false, 'status' => 'FAILED', 'imagePath' => null, 'error' => $e->getMessage()];
    }
}

/** ตั้งสถานะภาพของคำ (best-effort) */
function pipeline_set_image_status(PDO $pdo, int $wordId, string $status, ?string $reason): void
{
    try {
        $pdo->prepare('UPDATE wordstest SET image_status = ?, image_reason = ? WHERE id = ?')
            ->execute([$status, $reason !== null ? mb_substr($reason, 0, 255) : null, $wordId]);
    } catch (Throwable $e) { /* ไม่สำคัญพอจะล้ม pipeline */ }
}

/** บันทึกประวัติ prompt (ปิด is_active ของเดิม แล้วเพิ่มอันใหม่เป็น active) */
function pipeline_record_prompt(PDO $pdo, int $wordId, string $word, string $pos, string $neg, ?string $whatTh, string $whatEn, ?string $checkpoint, int $seed, string $previewRel): void
{
    try {
        $pdo->prepare('UPDATE game_prompt_history SET is_active = 0 WHERE word_id = ?')->execute([$wordId]);
        $pdo->prepare(
            'INSERT INTO game_prompt_history
                (word_id, word, positive_prompt, negative_prompt, what_to_draw_th, what_to_draw_en,
                 checkpoint_name, seed, preview_image, status, is_active, created_at, updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,1,NOW(3),NOW(3))'
        )->execute([
            $wordId, mb_substr($word, 0, 255), $pos, $neg,
            $whatTh, $whatEn !== '' ? $whatEn : null,
            $checkpoint, $seed, $previewRel, 'generated',
        ]);
    } catch (Throwable $e) { /* ประวัติ prompt พลาดได้ ไม่ทำให้ภาพที่สร้างแล้วเสีย */ }
}
