<?php
/**
 * scripts/resize_word_images.php — ย่อภาพประกอบคำใน media/image/wordstest ลงเหลือ <= MAX px
 *   - downscale อย่างเดียว (รูปที่เล็กกว่าหรือเท่ากับ MAX อยู่แล้วจะถูกข้าม)
 *   - เขียนทับไฟล์เดิม (in place) เพื่อลดขนาดโฟลเดอร์ก่อน upload
 *   - รักษาสัดส่วนภาพ + ช่อง alpha
 *
 * Usage:
 *   php scripts/resize_word_images.php                 # dry-run (รายงานอย่างเดียว ไม่แก้ไฟล์)
 *   php scripts/resize_word_images.php --apply         # ย่อจริง (เขียนทับ)
 *   php scripts/resize_word_images.php --apply --max=900
 *   php scripts/resize_word_images.php --apply --dir=media/image/wordstest/1   # เฉพาะโฟลเดอร์เดียว (เทสต์)
 */
$opts   = getopt('', ['apply', 'max::', 'dir::']);
$apply  = isset($opts['apply']);
$max    = (int)($opts['max'] ?? 900);
$root   = $opts['dir'] ?? (__DIR__ . '/../media/image/wordstest');

if ($max < 1) { fwrite(STDERR, "max must be >= 1\n"); exit(1); }
if (!extension_loaded('gd')) { fwrite(STDERR, "GD extension required\n"); exit(1); }

$root  = rtrim($root, '/\\');
$files = glob($root . '/*/*_original.png');                 // <root>/<id>/<hash>_original.png
if (!$files) { $files = glob($root . '/*_original.png'); }   // <root> เป็นโฟลเดอร์ id เดียว
if (!$files && is_file($root)) { $files = [$root]; }         // ชี้ไฟล์เดียว
$total = count($files);
echo ($apply ? "APPLY" : "DRY-RUN") . " · max={$max}px · files={$total} · root={$root}\n";

$resized = $skipped = $failed = 0;
$bytesBefore = $bytesAfter = 0;
$i = 0;
foreach ($files as $f) {
    $i++;
    $sz = @getimagesize($f);
    if (!$sz) { $failed++; fwrite(STDERR, "  ! bad image: $f\n"); continue; }
    [$w, $h] = $sz;
    $before = filesize($f);
    $bytesBefore += $before;

    $long = max($w, $h);
    if ($long <= $max) { $skipped++; $bytesAfter += $before; continue; }

    $scale = $max / $long;
    $nw = max(1, (int)round($w * $scale));
    $nh = max(1, (int)round($h * $scale));

    if (!$apply) {
        $resized++;
        $bytesAfter += (int)($before * $scale * $scale); // ประมาณการ
        continue;
    }

    $src = @imagecreatefrompng($f);
    if (!$src) { $failed++; fwrite(STDERR, "  ! load fail: $f\n"); continue; }
    $dst = imagecreatetruecolor($nw, $nh);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

    $tmp = $f . '.tmp';
    $ok = imagepng($dst, $tmp, 9);
    imagedestroy($src);
    imagedestroy($dst);

    if ($ok && filesize($tmp) > 0 && rename($tmp, $f)) {
        $resized++;
        $bytesAfter += filesize($f);
    } else {
        @unlink($tmp);
        $failed++;
        $bytesAfter += $before;
        fwrite(STDERR, "  ! write fail: $f\n");
    }
    if ($i % 200 === 0) echo "  ...$i/$total\n";
}

$mb = fn($b) => number_format($b / 1048576, 1) . ' MB';
echo "----\n";
echo "resized: $resized · skipped(<= $max): $skipped · failed: $failed\n";
echo "size: " . $mb($bytesBefore) . " -> " . $mb($bytesAfter)
   . ($apply ? "" : " (estimated)") . "\n";
if (!$apply) echo "** dry-run — ใส่ --apply เพื่อย่อจริง **\n";
