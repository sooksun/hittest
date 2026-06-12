<?php
/**
 * includes/llm.php — ต่อ LLM แปลงคำไทย → คำอธิบายภาพอังกฤษ (ขั้นก่อนส่ง ComfyUI)
 *
 * รองรับ 3 โหมด (ตั้งที่ config/media_gen.php → LLM_PROVIDER):
 *   'openai' : OpenAI หรือ endpoint เข้ากันได้ (Open WebUI /api/chat/completions) — ใช้ OPENAI_BASE_URL
 *   'ollama' : Ollama native /api/generate (Open WebUI อยู่ใต้ /ollama + ต้องมี OLLAMA_API_KEY)
 *   'none'   : ปิด → ใช้ word_overrides.json + คำที่แอดมินพิมพ์เองเท่านั้น (คืน status 'manual')
 *
 * ทุกฟังก์ชันทนความล้มเหลว: ถ้า LLM ใช้ไม่ได้ จะ fallback แทนที่จะโยน exception
 */

require_once __DIR__ . '/prompt_template.php';

/** โหลด config/media_gen.php ครั้งเดียว แล้วคืนชื่อ provider */
function llm_provider(): string
{
    if (!defined('LLM_PROVIDER')) {
        $secret = dirname(__DIR__) . '/config/media_gen.php';
        if (is_file($secret)) {
            require_once $secret;
        }
    }
    return defined('LLM_PROVIDER') ? strtolower((string)LLM_PROVIDER) : 'none';
}

/** dictionary ไทย→อังกฤษ ช่วยให้ชื่อสัตว์/สิ่งของถูกต้องตอนแปล (พอร์ตจาก openai-llm.service.ts) */
function llm_thai_dict(): array
{
    static $d = [
        'กระทิง' => 'gaur (wild buffalo)', 'ควาย' => 'water buffalo', 'วัว' => 'cow', 'ช้าง' => 'elephant',
        'เสือ' => 'tiger', 'สิงโต' => 'lion', 'ลิง' => 'monkey', 'หมี' => 'bear', 'กวาง' => 'deer',
        'แมว' => 'cat', 'หมา' => 'dog', 'สุนัข' => 'dog', 'นก' => 'bird', 'ปลา' => 'fish', 'กบ' => 'frog',
        'งู' => 'snake', 'เต่า' => 'turtle', 'กระต่าย' => 'rabbit', 'หนู' => 'mouse', 'ไก่' => 'chicken',
        'เป็ด' => 'duck', 'ห่าน' => 'goose', 'ม้า' => 'horse', 'หมู' => 'pig', 'แพะ' => 'goat', 'แกะ' => 'sheep',
        'สะพาน' => 'wooden bridge', 'กระจก' => 'mirror', 'โต๊ะ' => 'table', 'เก้าอี้' => 'chair',
        'หนังสือ' => 'book', 'ดินสอ' => 'pencil', 'ปากกา' => 'pen', 'กระดาษ' => 'paper',
        'ประตู' => 'door', 'หน้าต่าง' => 'window',
    ];
    return $d;
}

/**
 * แปลงคำ → "ฉากที่วาดได้" (อังกฤษพร้อมส่ง ComfyUI)
 * คืน ['status'=>'ok'|'ambiguous'|'manual'|'failed', 'whatToDraw'=>?string, 'reason'=>?string, 'lang'=>'en'|'th']
 *   - มี override → ok ทันที (เป็นอังกฤษอยู่แล้ว)
 *   - provider none/ล้มเหลว → manual (ให้แอดมินพิมพ์เอง)
 */
function llm_what_to_draw(string $word): array
{
    $ov = pt_get_override($word);
    if ($ov && !empty($ov['whatToDrawEn'])) {
        return ['status' => 'ok', 'whatToDraw' => (string)$ov['whatToDrawEn'], 'reason' => 'override', 'lang' => 'en'];
    }

    $provider = llm_provider();
    if ($provider === 'none') {
        return ['status' => 'manual', 'whatToDraw' => null, 'reason' => 'LLM ปิดอยู่ (LLM_PROVIDER=none)', 'lang' => 'th'];
    }

    $prompt = pt_build_what_to_draw_prompt($word);
    $system = "You design ONE illustration that explains the meaning of a Thai vocabulary word for a children's picture dictionary. It can be a single scene, or one image split into 2-3 large side-by-side panels (sequence, before/after, comparison) when a single scene is not enough. Even abstract words must be drawable using context, comparison, pointing/highlight cues, or panels. Reply with ONLY one plain Thai sentence describing the visible image (for panels, state what is in each panel) — no markdown, no headings, no options, no explanation. Never refuse.";

    // หมายเหตุ: โมเดลแบบ reasoning (เช่น gemma) ใช้ token ช่วง "คิด" ก่อนตอบ → ให้ budget สูงพอ
    $th = ($provider === 'ollama')
        ? llm_ollama_generate($prompt, 0.7, 1024)
        : llm_openai_chat($system, $prompt, 0.7, 400);

    if ($th === null) {
        return ['status' => 'failed', 'whatToDraw' => null, 'reason' => 'เรียก LLM ไม่สำเร็จ', 'lang' => 'th'];
    }
    $th = trim($th);
    if ($th === '' || mb_strlen($th) < 5) {
        return ['status' => 'failed', 'whatToDraw' => null, 'reason' => 'ผลลัพธ์ว่าง/สั้นเกินไป', 'lang' => 'th'];
    }
    // แนวคิดใหม่: วาด "ฉากบริบท" เสมอ — ไม่ข้ามคำนามธรรมอีก
    // กันเฉพาะกรณีโมเดลฝืนตอบว่า AMBIGUOUS ล้วน ๆ → ถือว่า failed (ลองใหม่ได้) ไม่ส่งคำนี้ไป ComfyUI
    if (strcasecmp($th, 'AMBIGUOUS') === 0) {
        return ['status' => 'failed', 'whatToDraw' => null, 'reason' => 'LLM ปฏิเสธการวาด (ลองใหม่)', 'lang' => 'th'];
    }

    // ได้ภาษาไทย → แปลเป็นอังกฤษต่อ
    $en = llm_translate_to_english($th);
    return ['status' => 'ok', 'whatToDraw' => $en, 'reason' => null, 'lang' => 'en', 'whatToDrawTh' => $th];
}

/** แปล "ฉากภาษาไทย" → อังกฤษ (ใช้ dict ช่วย) — provider none/ล้มเหลว → คืนข้อความเดิม */
function llm_translate_to_english(string $thai): string
{
    $provider = llm_provider();
    if ($provider === 'none') {
        return $thai;
    }
    $hints = [];
    foreach (llm_thai_dict() as $t => $e) {
        if (mb_strpos($thai, $t) !== false) {
            $hints[] = "{$t} = {$e}";
        }
    }
    $dictSection = $hints ? "\n\nUse these exact translations:\n" . implode("\n", $hints) : '';
    $system = "Translate Thai scene descriptions to English for children's book illustrations. "
        . "Focus on visual elements (people, objects, colors, actions). Keep under 70 words. "
        . "If the Thai describes an image divided into panels (ภาพแบ่ง...ช่อง), keep that structure explicitly: "
        . "'one wide image split into N side-by-side panels: panel 1 ..., panel 2 ...'. "
        . "Use accurate animal/object names from the dictionary if provided.{$dictSection}\n"
        . "Respond with ONLY the English translation, no explanation.";

    $out = ($provider === 'ollama')
        ? llm_ollama_generate($system . "\n\nThai: " . $thai, 0.3, 1024)
        : llm_openai_chat($system, $thai, 0.3, 300);

    if ($out === null || trim($out) === '') {
        return $thai;                          // fallback: ใช้ไทยตรง ๆ
    }
    // ตัด quote/ขึ้นบรรทัด
    $out = trim($out);
    $out = (string)preg_replace('/^["\']|["\']$/u', '', $out);
    return trim(str_replace("\n", ', ', $out));
}

/* ---------- ตัวเรียก provider ---------- */

/** OpenAI / OpenAI-compatible chat completions → คืน content (string) หรือ null */
function llm_openai_chat(string $system, string $user, float $temp, int $maxTokens): ?string
{
    $key   = defined('OPENAI_API_KEY')   ? (string)OPENAI_API_KEY   : '';
    $model = defined('OPENAI_LLM_MODEL') ? (string)OPENAI_LLM_MODEL : 'gpt-4o-mini';
    $base  = defined('OPENAI_BASE_URL')  ? rtrim((string)OPENAI_BASE_URL, '/') : 'https://api.openai.com/v1';
    if ($key === '') {
        return null;
    }
    $payload = [
        'model'       => $model,
        'messages'    => [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user',   'content' => $user],
        ],
        'temperature' => $temp,
        'max_tokens'  => $maxTokens,
    ];
    [$code, $body] = llm_http_post_json($base . '/chat/completions', $payload, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $key,
    ], 60);
    if ($code !== 200) {
        return null;
    }
    $data = json_decode($body ?: '{}', true);
    $txt  = $data['choices'][0]['message']['content'] ?? null;
    return is_string($txt) ? $txt : null;
}

/** Ollama native /api/generate → คืน response (string) หรือ null */
function llm_ollama_generate(string $prompt, float $temp, int $maxTokens): ?string
{
    $base  = defined('OLLAMA_API_URL') ? rtrim((string)OLLAMA_API_URL, '/') : 'http://localhost:11434';
    $model = defined('OLLAMA_MODEL')   ? (string)OLLAMA_MODEL : 'gemma3:12b';
    $key   = defined('OLLAMA_API_KEY') ? (string)OLLAMA_API_KEY : '';
    // ทนได้ทั้งกรณีตั้ง URL เป็น base (…/ollama) หรือเป็น endpoint เต็ม (…/api/generate)
    $endpoint = (substr($base, -13) === '/api/generate') ? $base : $base . '/api/generate';
    $headers = ['Content-Type: application/json'];
    if ($key !== '') {
        $headers[] = 'Authorization: Bearer ' . $key;
    }
    $payload = [
        'model'   => $model,
        'prompt'  => $prompt,
        'stream'  => false,
        'options' => ['temperature' => $temp, 'num_predict' => $maxTokens],
    ];
    [$code, $body] = llm_http_post_json($endpoint, $payload, $headers, 90);
    if ($code !== 200) {
        return null;
    }
    $data = json_decode($body ?: '{}', true);
    $txt  = $data['response'] ?? null;
    return is_string($txt) ? $txt : null;
}

/** POST JSON ทั่วไป (curl) → [httpCode, body] */
function llm_http_post_json(string $url, array $payload, array $headers, int $timeout): array
{
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_POSTFIELDS     => $json,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
        ]);
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return [$code, $body !== false ? $body : ''];
    }
    $ctx = stream_context_create(['http' => [
        'method'        => 'POST',
        'header'        => implode("\r\n", $headers),
        'content'       => $json,
        'timeout'       => $timeout,
        'ignore_errors' => true,
    ]]);
    $body = @file_get_contents($url, false, $ctx);
    $code = 0;
    if (!empty($http_response_header[0]) && preg_match('#\s(\d{3})\s#', $http_response_header[0], $m)) {
        $code = (int)$m[1];
    }
    return [$code, $body !== false ? $body : ''];
}
