<?php
/**
 * includes/media_storage.php — ที่เก็บไฟล์สื่อ (เสียง mp3 + ภาพ png) ของ newhittest
 *
 * โครงสร้างพาธ (ตรงกับของเดิมที่ย้ายมาจาก readthai เป๊ะ ๆ เพื่อให้ migrate ไฟล์ได้ตรง):
 *   เสียง: <app>/media/audio/wordstest/{wordId}/{hash}.mp3
 *   ภาพ : <app>/media/image/wordstest/{wordId}/{hash}_original.png
 *
 * พาธที่เก็บลง DB (sound_path / image_path) เป็น public path ขึ้นต้น "/media/..." (ไม่มี /newhittest)
 * ฝั่งเกม (React) จะ prepend API base ('/newhittest') ให้เองเมื่อพาธขึ้นต้นด้วย '/'
 */

if (!defined('MEDIA_ROOT')) {
    // โฟลเดอร์จริงบนดิสก์ (เดิมเป็น symlink ไป readthai → migrate เป็นไดเรกทอรีจริงของ newhittest)
    define('MEDIA_ROOT', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'media');
}

/** แฮชสั้น 12 ตัวอักษรhexสำหรับตั้งชื่อไฟล์ — เสถียรต่อ (wordId, ข้อความ, เวอร์ชัน) */
function media_hash(int $wordId, string $text, string $ver = 'v1'): string
{
    return substr(sha1($wordId . '|' . $text . '|' . $ver), 0, 12);
}

/* ---------- เสียง ---------- */

/** public path เก็บลง DB เช่น "/media/audio/wordstest/1/abc123.mp3" */
function media_audio_relpath(int $wordId, string $hash): string
{
    return "/media/audio/wordstest/{$wordId}/{$hash}.mp3";
}

/** พาธไฟล์จริงบนดิสก์ */
function media_audio_fullpath(int $wordId, string $hash): string
{
    return MEDIA_ROOT . "/audio/wordstest/{$wordId}/{$hash}.mp3";
}

/* ---------- ภาพ ---------- */

/** public path เก็บลง DB เช่น "/media/image/wordstest/1/abc123_original.png" */
function media_image_relpath(int $wordId, string $hash, string $variant = 'original'): string
{
    return "/media/image/wordstest/{$wordId}/{$hash}_{$variant}.png";
}

/** พาธไฟล์จริงบนดิสก์ */
function media_image_fullpath(int $wordId, string $hash, string $variant = 'original'): string
{
    return MEDIA_ROOT . "/image/wordstest/{$wordId}/{$hash}_{$variant}.png";
}

/** แปลง public path ("/media/...") → พาธไฟล์จริงบนดิสก์ (ใช้ตอนลบ/ตรวจไฟล์เดิม) */
function media_public_to_fullpath(string $publicPath): ?string
{
    $p = ltrim($publicPath, '/');
    if (strncmp($p, 'media/', 6) !== 0) {
        return null;                       // ไม่ใช่พาธสื่อของเรา — กัน path traversal
    }
    $rel = substr($p, 6);                  // ตัด "media/" นำหน้า
    if (strpos($rel, '..') !== false) {
        return null;
    }
    return MEDIA_ROOT . '/' . $rel;
}

/**
 * เขียนไบต์ลงไฟล์แบบสร้างโฟลเดอร์ให้ + เขียนผ่านไฟล์ชั่วคราวแล้ว rename (กันไฟล์ครึ่ง ๆ)
 * คืน true เมื่อสำเร็จ — โยน RuntimeException เมื่อสร้างโฟลเดอร์/เขียนไม่ได้ (ให้ pipeline จับ)
 */
function media_save_bytes(string $fullpath, string $bytes): bool
{
    $dir = dirname($fullpath);
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException("สร้างโฟลเดอร์ไม่ได้: {$dir}");
    }
    $tmp = $fullpath . '.tmp' . getmypid();
    if (@file_put_contents($tmp, $bytes) === false) {
        throw new RuntimeException("เขียนไฟล์ไม่ได้: {$tmp}");
    }
    // Windows: rename ทับไฟล์เดิมไม่ได้ — ลบเป้าหมายก่อน
    if (is_file($fullpath)) {
        @unlink($fullpath);
    }
    if (!@rename($tmp, $fullpath)) {
        @unlink($tmp);
        throw new RuntimeException("ย้ายไฟล์ไม่ได้: {$fullpath}");
    }
    return true;
}
