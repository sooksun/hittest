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

require_once __DIR__ . '/includes/botnoi_tts.php';   // botnoi_ready(), botnoi_request_audio_url()
if (!botnoi_ready()) {
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

$key = sha1($text . '|' . $speaker . '|' . $speed . '|' . $volume);
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

// 2) เรียก Botnoi Voice API (ผ่าน helper ร่วม includes/botnoi_tts.php)
$r = botnoi_request_audio_url($text, $speaker, $speed, $volume);

if ($r['ok']) {
    try {
        $pdo->prepare('INSERT INTO tts_cache (cache_key, text, audio_url, created_at) VALUES (?,?,?,NOW())
                       ON DUPLICATE KEY UPDATE audio_url = VALUES(audio_url)')
            ->execute([$key, mb_substr($text, 0, 255), $r['audioUrl']]);
    } catch (Throwable $e) {
        // แคชล้มเหลวไม่เป็นไร ส่ง url กลับได้
    }
    json_response(['status' => 'ok', 'audio_url' => $r['audioUrl'], 'cached' => false]);
}

json_response(['status' => 'error', 'message' => $r['error'] ?? 'สร้างเสียงไม่สำเร็จ', 'http' => $r['httpCode']], 502);
