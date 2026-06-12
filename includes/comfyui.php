<?php
/**
 * includes/comfyui.php — เรียก ComfyUI API สร้างภาพ (พอร์ตจาก readthai ComfyUIService)
 *
 * ลำดับงาน: ประกอบ workflow graph → POST /prompt (รับ prompt_id) → poll /history/{id}
 *           จนได้ชื่อไฟล์ → GET /view?filename=... ได้ไบต์ PNG
 * host/พารามิเตอร์อยู่ที่ config/media_gen.php (COMFYUI_API_URL, COMFYUI_CHECKPOINT, …)
 */

/** โหลด config/media_gen.php ถ้ายังไม่มี COMFYUI_API_URL — คืน true ถ้าตั้งค่าครบ */
function comfyui_ready(): bool
{
    if (!defined('COMFYUI_API_URL')) {
        $secret = dirname(__DIR__) . '/config/media_gen.php';
        if (is_file($secret)) {
            require_once $secret;
        }
    }
    return defined('COMFYUI_API_URL') && COMFYUI_API_URL !== '';
}

/** base URL ของ ComfyUI (ตัด / ท้าย) */
function comfyui_base(): string
{
    return rtrim(defined('COMFYUI_API_URL') ? (string)COMFYUI_API_URL : '', '/');
}

/** workflow text2image มาตรฐาน (ฝังไว้ในโค้ด — โครงเดียวกับ readthai_text2image.json) */
function comfyui_workflow(string $positive, string $negative, array $o): array
{
    return [
        '3'  => ['class_type' => 'CheckpointLoaderSimple', '_meta' => ['title' => 'CheckpointLoader'],
                 'inputs' => ['ckpt_name' => $o['checkpoint']]],
        '5'  => ['class_type' => 'EmptyLatentImage', '_meta' => ['title' => 'EmptyLatentImage'],
                 'inputs' => ['width' => $o['width'], 'height' => $o['height'], 'batch_size' => 1]],
        '6'  => ['class_type' => 'CLIPTextEncode', '_meta' => ['title' => 'PROMPT'],
                 'inputs' => ['text' => $positive, 'clip' => ['3', 1]]],
        '7'  => ['class_type' => 'CLIPTextEncode', '_meta' => ['title' => 'NEGATIVE'],
                 'inputs' => ['text' => $negative, 'clip' => ['3', 1]]],
        '10' => ['class_type' => 'KSampler', '_meta' => ['title' => 'KSampler'],
                 'inputs' => [
                     'seed' => $o['seed'], 'steps' => $o['steps'], 'cfg' => $o['cfg'],
                     'sampler_name' => $o['sampler'], 'scheduler' => $o['scheduler'], 'denoise' => 1,
                     'model' => ['3', 0], 'positive' => ['6', 0], 'negative' => ['7', 0], 'latent_image' => ['5', 0],
                 ]],
        '8'  => ['class_type' => 'VAEDecode', '_meta' => ['title' => 'VAEDecode'],
                 'inputs' => ['samples' => ['10', 0], 'vae' => ['3', 2]]],
        '9'  => ['class_type' => 'SaveImage', '_meta' => ['title' => 'SaveImage'],
                 'inputs' => ['filename_prefix' => $o['prefix'], 'images' => ['8', 0]]],
    ];
}

/**
 * สร้างภาพ 1 ใบ (ทำงานแบบ synchronous: รอจนเสร็จหรือ timeout)
 * คืน ['ok'=>bool, 'bytes'=>?string, 'promptId'=>?string, 'filename'=>?string, 'seed'=>int, 'error'=>?string]
 */
function comfyui_generate_image(string $positive, string $negative, array $opts = []): array
{
    if (!comfyui_ready()) {
        return ['ok' => false, 'error' => 'ยังไม่ได้ตั้งค่า ComfyUI (config/media_gen.php)'] + comfyui_blank();
    }
    $seed = isset($opts['seed']) ? (int)$opts['seed'] : random_int(0, 2147483647);
    $o = [
        'checkpoint' => $opts['checkpoint'] ?? (defined('COMFYUI_CHECKPOINT') ? COMFYUI_CHECKPOINT : 'dreamshaper_8.safetensors'),
        'width'      => (int)($opts['width']  ?? (defined('COMFYUI_WIDTH')  ? COMFYUI_WIDTH  : 1024)),
        'height'     => (int)($opts['height'] ?? (defined('COMFYUI_HEIGHT') ? COMFYUI_HEIGHT : 1024)),
        'steps'      => (int)($opts['steps']  ?? (defined('COMFYUI_STEPS')  ? COMFYUI_STEPS  : 20)),
        'cfg'        => (float)($opts['cfg']  ?? (defined('COMFYUI_CFG')    ? COMFYUI_CFG    : 7.0)),
        'sampler'    => $opts['sampler']   ?? (defined('COMFYUI_SAMPLER')   ? COMFYUI_SAMPLER   : 'euler'),
        'scheduler'  => $opts['scheduler'] ?? (defined('COMFYUI_SCHEDULER') ? COMFYUI_SCHEDULER : 'normal'),
        'seed'       => $seed,
        'prefix'     => $opts['prefix'] ?? ('newhittest_' . (int)($opts['wordId'] ?? 0)),
    ];

    $workflow = comfyui_workflow($positive, $negative, $o);

    // 1) queue
    [$code, $body] = comfyui_post_json(comfyui_base() . '/prompt', ['prompt' => $workflow], 30);
    if ($code !== 200) {
        return ['ok' => false, 'error' => "ComfyUI /prompt ตอบ HTTP {$code}", 'seed' => $seed] + comfyui_blank();
    }
    $promptId = (string)(json_decode($body ?: '{}', true)['prompt_id'] ?? '');
    if ($promptId === '') {
        return ['ok' => false, 'error' => 'ComfyUI ไม่คืน prompt_id', 'seed' => $seed] + comfyui_blank();
    }

    // 2) poll history
    $filename = comfyui_wait_for_image($promptId);
    if ($filename === null) {
        return ['ok' => false, 'error' => 'หมดเวลารอภาพจาก ComfyUI', 'promptId' => $promptId, 'seed' => $seed] + comfyui_blank();
    }

    // 3) download
    [$dcode, $bytes] = comfyui_get_binary(comfyui_base() . '/view?filename=' . rawurlencode($filename), 60);
    if ($dcode !== 200 || $bytes === '' || strlen($bytes) < 500) {
        return ['ok' => false, 'error' => "ดาวน์โหลดภาพไม่สำเร็จ (HTTP {$dcode})", 'promptId' => $promptId, 'filename' => $filename, 'seed' => $seed] + comfyui_blank();
    }
    return ['ok' => true, 'bytes' => $bytes, 'promptId' => $promptId, 'filename' => $filename, 'seed' => $seed, 'error' => null];
}

/** poll /history/{id} ทุก COMFYUI_POLL_SEC จน timeout — คืนชื่อไฟล์ภาพแรก หรือ null */
function comfyui_wait_for_image(string $promptId): ?string
{
    $poll    = max(1, defined('COMFYUI_POLL_SEC') ? (int)COMFYUI_POLL_SEC : 2);
    $timeout = max(10, defined('COMFYUI_TIMEOUT_SEC') ? (int)COMFYUI_TIMEOUT_SEC : 300);
    $deadline = time() + $timeout;

    while (time() < $deadline) {
        [$code, $body] = comfyui_get(comfyui_base() . '/history/' . rawurlencode($promptId), 15);
        if ($code === 200) {
            $hist = json_decode($body ?: '{}', true);
            $outputs = $hist[$promptId]['outputs'] ?? null;
            if (is_array($outputs)) {
                foreach ($outputs as $node) {
                    if (!empty($node['images'][0]['filename'])) {
                        return (string)$node['images'][0]['filename'];
                    }
                }
            }
        }
        sleep($poll);
    }
    return null;
}

/** ตรวจว่า ComfyUI ออนไลน์ไหม (GET /system_stats) */
function comfyui_health(): bool
{
    if (!comfyui_ready()) {
        return false;
    }
    [$code] = comfyui_get(comfyui_base() . '/system_stats', 6);
    return $code === 200;
}

/* ---------- HTTP helpers (curl) ---------- */

function comfyui_blank(): array
{
    return ['bytes' => null, 'promptId' => null, 'filename' => null, 'seed' => 0];
}

function comfyui_post_json(string $url, array $payload, int $timeout): array
{
    $json = json_encode($payload);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => $json,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
    ]);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$code, $body !== false ? $body : ''];
}

function comfyui_get(string $url, int $timeout): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
    ]);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$code, $body !== false ? $body : ''];
}

function comfyui_get_binary(string $url, int $timeout): array
{
    return comfyui_get($url, $timeout);   // curl คืน raw bytes อยู่แล้ว
}
