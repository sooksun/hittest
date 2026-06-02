<?php
/**
 * api/tts.php — JSON TTS adapter for game bundles
 *
 * Game (botnoi.ts) sends:  POST JSON {text, speaker?, speed?, volume?}
 * This file responds:       JSON {audioUrl: "https://..."}
 *
 * Reuses the Botnoi token + tts_cache table from the root tts.php,
 * but translates the request/response format.
 */
session_start();
require_once dirname(__DIR__) . '/includes/functions.php';

$isStudent = ($_SESSION['role'] ?? '') === 'student' && !empty($_SESSION['stu']['stuid']);
$isTeacher = !empty($_SESSION['sc_id']);
if (!$isStudent && !$isTeacher) {
    json_response(['error' => true, 'message' => 'ยังไม่ได้เข้าสู่ระบบ'], 401);
}

// Capture caller identity for rate-limiting + audit before releasing the session
$actorId = $isStudent
    ? (string)($_SESSION['stu']['stuid'] ?? '')
    : ('teacher:' . (string)($_SESSION['sc_id'] ?? ''));
$actorSc = (string)($_SESSION['sc_id'] ?? ($_SESSION['stu']['sc_id'] ?? ''));

session_write_close();

// ── Config ────────────────────────────────────────────────────────────────────
$secret = dirname(__DIR__) . '/config/botnoi.php';
if (is_file($secret)) require_once $secret;
if (!defined('BOTNOI_TOKEN') || BOTNOI_TOKEN === '') {
    json_response(['error' => true, 'message' => 'ยังไม่ได้ตั้งค่า Botnoi token'], 500);
}

// ── Parse JSON body ───────────────────────────────────────────────────────────
$body    = json_decode(file_get_contents('php://input'), true) ?: [];
$text    = trim((string)($body['text'] ?? ''));

// Validate and normalise speaker, speed, volume before forwarding to Botnoi.
// Normalisation also ensures cache-key stability (e.g. 1.0 === 1.00).
$rawSpeaker = (string)($body['speaker'] ?? (defined('BOTNOI_SPEAKER') ? BOTNOI_SPEAKER : '1'));
$speaker    = ctype_digit($rawSpeaker) && (int)$rawSpeaker >= 1 && (int)$rawSpeaker <= 99
    ? $rawSpeaker
    : '1';
$speed  = round(max(0.5, min(2.0, (float)($body['speed']  ?? (defined('BOTNOI_SPEED') ? BOTNOI_SPEED : 1.0)))), 1);
$volume = round(max(0.0, min(1.0, (float)($body['volume'] ?? 1.0))), 1);

if ($text === '') {
    json_response(['error' => true, 'message' => 'No text'], 400);
}
if (mb_strlen($text) > 255) {
    json_response(['error' => true, 'message' => 'Text too long'], 400);
}

// ── Cache lookup ──────────────────────────────────────────────────────────────
$key = sha1($text . '|' . $speaker . '|' . $speed);
$pdo = db();
try {
    $c = $pdo->prepare('SELECT audio_url FROM tts_cache WHERE cache_key = ?');
    $c->execute([$key]);
    $cached = $c->fetchColumn();
    if ($cached) {
        json_response(['audioUrl' => $cached]);
    }
} catch (Throwable $e) { /* table may not exist yet */ }

// ── Rate limit (only on cache MISS → an actual paid Botnoi call) ─────────────
$rateMax = defined('TTS_RATE_MAX')        ? TTS_RATE_MAX        : 30;
$rateWin = defined('TTS_RATE_WINDOW_SEC') ? TTS_RATE_WINDOW_SEC : 60;
if ($actorId !== '' && tts_rate_exceeded($pdo, $actorId, $rateMax, $rateWin)) {
    audit_log_event($pdo, [
        'sc_id' => $actorSc, 'stuid' => $actorId, 'action' => 'tts_rate_limited',
        'entity_type' => 'tts', 'status_code' => 429, 'meta_json' => ['textLen' => mb_strlen($text)],
    ]);
    json_response(['error' => true, 'message' => 'เรียกใช้เสียงถี่เกินไป กรุณารอสักครู่แล้วลองใหม่'], 429);
}
// Count this attempt toward the window (the audio service tolerates a failed call)
audit_log_event($pdo, [
    'sc_id' => $actorSc, 'stuid' => $actorId, 'action' => 'tts_request',
    'entity_type' => 'tts', 'meta_json' => ['speaker' => $speaker, 'textLen' => mb_strlen($text)],
]);

// ── Botnoi API call ───────────────────────────────────────────────────────────
$payload  = json_encode(['text' => $text, 'speaker' => $speaker, 'volume' => $volume,
    'speed' => $speed, 'type_media' => 'mp3', 'save_file' => 'true', 'language' => 'th', 'page' => 'user']);
$endpoint = 'https://api-voice.botnoi.ai/openapi/v1/generate_audio';
$res      = null;
$httpCode = 0;

if (function_exists('curl_init')) {
    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Botnoi-Token: ' . BOTNOI_TOKEN],
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
    ]);
    $res      = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
}

$data     = json_decode($res ?: '{}', true);
$audioUrl = is_array($data) ? ($data['audio_url'] ?? '') : '';

if ($httpCode === 200 && $audioUrl !== '') {
    try {
        $pdo->prepare('INSERT INTO tts_cache (cache_key, text, audio_url, created_at) VALUES (?,?,?,NOW())
                       ON DUPLICATE KEY UPDATE audio_url = VALUES(audio_url)')
            ->execute([$key, mb_substr($text, 0, 255), $audioUrl]);
    } catch (Throwable $e) { /* ignore */ }
    json_response(['audioUrl' => $audioUrl]);
}

$msg = is_array($data) && !empty($data['detail']) ? $data['detail'] : 'สร้างเสียงไม่สำเร็จ';
json_response(['error' => true, 'message' => $msg], 502);
