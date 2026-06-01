<?php
/**
 * tts.php — พร็อกซีเรียกเสียงอ่านจาก Botnoi Voice API
 *   - เก็บ token ฝั่ง server (config/botnoi.php, gitignored) ไม่เปิดเผยฝั่ง client
 *   - cache ผล (audio_url) ลงตาราง tts_cache กันเรียกซ้ำ/กัน point หมด
 * POST: text  →  { status:'ok', audio_url, cached:bool }
 */
// อนุญาตทั้งครู (sc_id) และนักเรียน (role=student) — tts เป็น proxy อ่านออกเสียงเฉย ๆ
session_start();
require_once __DIR__ . '/includes/functions.php';   // db() + json_response()
$loggedIn = !empty($_SESSION['sc_id']) || (($_SESSION['role'] ?? '') === 'student');
if (!$loggedIn) {
    json_response(['status' => 'error', 'message' => 'ยังไม่ได้เข้าสู่ระบบ'], 401);
}
// ปล่อย session lock ทันที — กันค้าง 20 วิ ระหว่างเรียก Botnoi แล้วบล็อก AJAX อื่นบน session เดียวกัน
session_write_close();

$secret = __DIR__ . '/config/botnoi.php';
if (is_file($secret)) {
    require_once $secret;
}
if (!defined('BOTNOI_TOKEN') || BOTNOI_TOKEN === '') {
    json_response(['status' => 'error', 'message' => 'ยังไม่ได้ตั้งค่า Botnoi token (config/botnoi.php)'], 500);
}

$text = trim((string)($_POST['text'] ?? ''));
if ($text === '') {
    json_response(['status' => 'error', 'message' => 'ไม่มีข้อความ'], 400);
}
if (mb_strlen($text) > 255) {
    json_response(['status' => 'error', 'message' => 'ข้อความยาวเกินไป'], 400);
}

$speaker = defined('BOTNOI_SPEAKER') ? (string)BOTNOI_SPEAKER : '1';
$speed   = defined('BOTNOI_SPEED')   ? (float)BOTNOI_SPEED   : 1;
$volume  = defined('BOTNOI_VOLUME')  ? (float)BOTNOI_VOLUME  : 1;

$key = sha1($text . '|' . $speaker . '|' . $speed);
$pdo = db();

// 1) มีในแคชแล้วหรือยัง
try {
    $c = $pdo->prepare('SELECT audio_url FROM tts_cache WHERE cache_key = ?');
    $c->execute([$key]);
    $cachedUrl = $c->fetchColumn();
    if ($cachedUrl) {
        json_response(['status' => 'ok', 'audio_url' => $cachedUrl, 'cached' => true]);
    }
} catch (Throwable $e) {
    // ไม่มีตาราง cache ก็ข้ามไปเรียก API ได้
}

// 2) เรียก Botnoi Voice API
$payload = json_encode([
    'text'       => $text,
    'speaker'    => $speaker,
    'volume'     => $volume,
    'speed'      => $speed,
    'type_media' => 'mp3',
    'save_file'  => 'true',
    'language'   => 'th',
    'page'       => 'user',
]);   // ไม่ใช้ JSON_UNESCAPED_UNICODE → Thai เป็น \uXXXX ปลอดภัยกับการขนส่ง

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
        CURLOPT_TIMEOUT        => 20,
    ]);
    $res = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
} else {
    $ctx = stream_context_create(['http' => [
        'method'        => 'POST',
        'header'        => "Content-Type: application/json\r\nBotnoi-Token: " . BOTNOI_TOKEN . "\r\n",
        'content'       => $payload,
        'timeout'       => 20,
        'ignore_errors' => true,
    ]]);
    $res = @file_get_contents($endpoint, false, $ctx);
    if (!empty($http_response_header[0]) && preg_match('#\s(\d{3})\s#', $http_response_header[0], $m)) {
        $httpCode = (int)$m[1];
    } elseif ($res !== false) {
        $httpCode = 200;
    }
}

if ($res === false || $res === null) {
    json_response(['status' => 'error', 'message' => 'เชื่อมต่อ Botnoi ไม่สำเร็จ'], 502);
}

$data = json_decode($res, true);
$audioUrl = is_array($data) ? ($data['audio_url'] ?? '') : '';

if ($httpCode === 200 && $audioUrl !== '') {
    try {
        $pdo->prepare('INSERT INTO tts_cache (cache_key, text, audio_url, created_at) VALUES (?,?,?,NOW())
                       ON DUPLICATE KEY UPDATE audio_url = VALUES(audio_url)')
            ->execute([$key, mb_substr($text, 0, 255), $audioUrl]);
    } catch (Throwable $e) {
        // แคชล้มเหลวไม่เป็นไร ส่ง url กลับได้
    }
    json_response(['status' => 'ok', 'audio_url' => $audioUrl, 'cached' => false]);
}

$msg = (is_array($data) && !empty($data['detail']) && is_string($data['detail'])) ? $data['detail'] : 'สร้างเสียงไม่สำเร็จ';
json_response(['status' => 'error', 'message' => $msg, 'http' => $httpCode], 502);
