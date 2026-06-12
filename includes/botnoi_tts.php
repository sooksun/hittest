<?php
/**
 * includes/botnoi_tts.php — เรียก Botnoi Voice API (สังเคราะห์เสียงอ่านไทย)
 *
 * ใช้ร่วมกัน 3 ที่:
 *   - tts.php / api/tts.php   → ต้องการแค่ audio_url (แคชไว้)            → botnoi_request_audio_url()
 *   - includes/media_pipeline → ต้องการ "ไฟล์ mp3" เพื่อเก็บเป็นของเรา → botnoi_generate_mp3()
 *
 * โทเคนอยู่ที่ config/botnoi.php (BOTNOI_TOKEN) — โหลดให้อัตโนมัติถ้ายังไม่ได้นิยาม
 */

/** โหลด config/botnoi.php ถ้ายังไม่มี BOTNOI_TOKEN — คืน true ถ้ามีโทเคนพร้อมใช้ */
function botnoi_ready(): bool
{
    if (!defined('BOTNOI_TOKEN')) {
        $secret = dirname(__DIR__) . '/config/botnoi.php';
        if (is_file($secret)) {
            require_once $secret;
        }
    }
    return defined('BOTNOI_TOKEN') && BOTNOI_TOKEN !== '';
}

/** normalize speaker/speed/volume ให้คงที่ (ใช้ทำคีย์แคชด้วย) */
function botnoi_norm_params($speaker = null, $speed = null, $volume = null): array
{
    $rawSpeaker = (string)($speaker ?? (defined('BOTNOI_SPEAKER') ? BOTNOI_SPEAKER : '1'));
    $sp = (ctype_digit($rawSpeaker) && (int)$rawSpeaker >= 1 && (int)$rawSpeaker <= 99) ? $rawSpeaker : '1';
    $spd = round(max(0.5, min(2.0, (float)($speed  ?? (defined('BOTNOI_SPEED')  ? BOTNOI_SPEED  : 1.0)))), 1);
    $vol = round(max(0.0, min(1.0, (float)($volume ?? (defined('BOTNOI_VOLUME') ? BOTNOI_VOLUME : 1.0)))), 1);
    return [$sp, $spd, $vol];
}

/**
 * POST ไป Botnoi → คืน audio_url (ไม่ดาวน์โหลดไฟล์)
 * คืน ['ok'=>bool, 'audioUrl'=>string, 'httpCode'=>int, 'error'=>?string]
 */
function botnoi_request_audio_url(string $text, $speaker = null, $speed = null, $volume = null): array
{
    if (!botnoi_ready()) {
        return ['ok' => false, 'audioUrl' => '', 'httpCode' => 0, 'error' => 'ยังไม่ได้ตั้งค่า Botnoi token'];
    }
    [$sp, $spd, $vol] = botnoi_norm_params($speaker, $speed, $volume);

    $payload = json_encode([
        'text' => $text, 'speaker' => $sp, 'volume' => $vol, 'speed' => $spd,
        'type_media' => 'mp3', 'save_file' => 'true', 'language' => 'th', 'page' => 'user',
    ]);   // ไม่ใส่ JSON_UNESCAPED_UNICODE → ไทยเป็น \uXXXX ปลอดภัยกับการขนส่ง
    $endpoint = 'https://api-voice.botnoi.ai/openapi/v1/generate_audio';

    $res = null;
    $httpCode = 0;
    if (function_exists('curl_init')) {
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Botnoi-Token: ' . BOTNOI_TOKEN],
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
        ]);
        $res      = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    } else {
        $ctx = stream_context_create(['http' => [
            'method'        => 'POST',
            'header'        => "Content-Type: application/json\r\nBotnoi-Token: " . BOTNOI_TOKEN . "\r\n",
            'content'       => $payload,
            'timeout'       => 30,
            'ignore_errors' => true,
        ]]);
        $res = @file_get_contents($endpoint, false, $ctx);
        if (!empty($http_response_header[0]) && preg_match('#\s(\d{3})\s#', $http_response_header[0], $m)) {
            $httpCode = (int)$m[1];
        } elseif ($res !== false) {
            $httpCode = 200;
        }
    }

    $data     = json_decode($res ?: '{}', true);
    $audioUrl = is_array($data) ? (string)($data['audio_url'] ?? '') : '';

    if ($httpCode === 200 && $audioUrl !== '') {
        return ['ok' => true, 'audioUrl' => $audioUrl, 'httpCode' => 200, 'error' => null];
    }
    $msg = (is_array($data) && !empty($data['detail']) && is_string($data['detail'])) ? $data['detail'] : 'สร้างเสียงไม่สำเร็จ';
    return ['ok' => false, 'audioUrl' => '', 'httpCode' => $httpCode, 'error' => $msg];
}

/**
 * สังเคราะห์เสียง + ดาวน์โหลดไฟล์ mp3 มาเป็นไบต์ (สำหรับเก็บเป็นของเรา)
 * คืน ['ok'=>bool, 'bytes'=>?string, 'audioUrl'=>string, 'error'=>?string]
 */
function botnoi_generate_mp3(string $text, $speaker = null, $speed = null, $volume = null): array
{
    $r = botnoi_request_audio_url($text, $speaker, $speed, $volume);
    if (!$r['ok']) {
        return ['ok' => false, 'bytes' => null, 'audioUrl' => '', 'error' => $r['error']];
    }
    $bytes = botnoi_download($r['audioUrl']);
    if ($bytes === null || strlen($bytes) < 200) {     // ไฟล์เล็กผิดปกติ = ดาวน์โหลดพลาด
        return ['ok' => false, 'bytes' => null, 'audioUrl' => $r['audioUrl'], 'error' => 'ดาวน์โหลดไฟล์เสียงไม่สำเร็จ'];
    }
    return ['ok' => true, 'bytes' => $bytes, 'audioUrl' => $r['audioUrl'], 'error' => null];
}

/** ดาวน์โหลดไบต์จาก URL (mp3 จาก S3 ของ Botnoi) — คืน null ถ้าพลาด */
function botnoi_download(string $url): ?string
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 60,
        ]);
        $bytes = curl_exec($ch);
        $code  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ($bytes !== false && $code === 200) ? $bytes : null;
    }
    $bytes = @file_get_contents($url);
    return $bytes !== false ? $bytes : null;
}
