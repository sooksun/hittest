<?php
/**
 * practice.php — ฝึกอ่านคำพื้นฐาน (ตรวจคำอ่านอัตโนมัติด้วย Web Speech API)
 * อ้างอิงต้นแบบ autocheck2.html — อ่านคำจากตาราง words (คลังคำพื้นฐานระดับชาติ)
 * เลือก ชั้น/รอบ/ชุด → ฝึกอ่าน 20 คำ : ฟังคำ (TTS) + อ่านออกเสียง (STT) ตรวจกับ spoken_form
 * หมายเหตุ: Web Speech API ใช้ได้บน Chrome ผ่าน https หรือ localhost เท่านั้น
 * ตรรกะฝึกอ่าน (เสียง/ไมค์/เทียบเสียง) อยู่ใน assets/js/practice-reader.js (ใช้ร่วมกับ my_practice.php)
 */
require __DIR__ . '/includes/auth.php';

$class_id = (int)($_GET['class_id'] ?? 0);
$hittest  = (int)($_GET['hittest'] ?? 0);
$sethit   = (int)($_GET['sethit'] ?? 0);

$started = ($class_id >= 1 && $class_id <= 6) && valid_hit($hittest) && ($sethit >= 1 && $sethit <= 5);

$words = [];
if ($started) {
    $stmt = db()->prepare('SELECT word, spoken_form FROM words
        WHERE class_id = ? AND hittest = ? AND sethit = ? ORDER BY orders');
    $stmt->execute([$class_id, $hittest, $sethit]);
    $words = array_map(fn($w) => ['word' => $w['word'], 'spoken' => $w['spoken_form']], $stmt->fetchAll());
}

$page_title = 'ฝึกอ่าน';
$active = 'practice';
require __DIR__ . '/includes/header.php';
?>
<h2 class="mb-1">🗣️ ฝึกอ่านคำพื้นฐาน</h2>
<p class="text-muted mb-4">อ่านคำที่ปรากฏด้วยตัวเอง แล้วกด 🎤 อ่าน เพื่อให้ระบบตรวจและแจ้งผลทันที (กด 🔊 ฟังคำ ได้หากต้องการฟังเสียงตัวอย่าง)</p>

<?php if (!$started): ?>
    <!-- เลือกชุดคำที่จะฝึก -->
    <div class="ht-card" style="max-width:560px; border-left:6px solid var(--c-blue)">
        <h3 class="mb-3">เลือกชุดคำที่ต้องการฝึก</h3>
        <form method="get" class="row g-3 align-items-end">
            <div class="col-sm-4">
                <div class="ht-field">
                    <label class="ht-label">ชั้น</label>
                    <select name="class_id" class="ht-select">
                        <?php for ($c = 1; $c <= 6; $c++): ?><option value="<?= $c ?>" <?= $c === ($class_id ?: 1) ? 'selected' : '' ?>>ป.<?= $c ?></option><?php endfor; ?>
                    </select>
                </div>
            </div>
            <div class="col-sm-4">
                <div class="ht-field">
                    <label class="ht-label">รอบ</label>
                    <select name="hittest" class="ht-select">
                        <?php for ($h = 1; $h <= 3; $h++): ?><option value="<?= $h ?>">Hit-<?= $h ?></option><?php endfor; ?>
                    </select>
                </div>
            </div>
            <div class="col-sm-4">
                <div class="ht-field">
                    <label class="ht-label">ชุดคำ</label>
                    <select name="sethit" class="ht-select">
                        <?php for ($s = 1; $s <= 5; $s++): ?><option value="<?= $s ?>">ชุด <?= $s ?></option><?php endfor; ?>
                    </select>
                </div>
            </div>
            <div class="col-12">
                <button class="ht-btn ht-btn--lg mt-2" type="submit">▶️ เริ่มฝึกอ่าน (20 คำ)</button>
            </div>
        </form>
        <p class="text-muted mt-3 mb-0" style="font-size:.9rem">💡 ใช้บน Google Chrome และอนุญาตให้เข้าถึงไมโครโฟน · รองรับเฉพาะ https หรือ localhost</p>
    </div>
<?php else: ?>
    <!-- สนามฝึกอ่าน -->
    <div class="ht-row mb-3" style="gap:12px">
        <span class="ht-badge t-blue">ป.<?= $class_id ?> · Hit-<?= $hittest ?> · ชุด <?= $sethit ?></span>
        <span class="ht-badge ht-badge--done">อ่านถูก <span id="pScore">0</span> / <?= count($words) ?></span>
        <a href="practice.php" class="ht-btn ht-btn--ghost ht-btn--sm" style="margin-left:auto">เปลี่ยนชุด</a>
    </div>

    <div id="pUnsupported" class="ht-card mb-3 d-none" style="border-left:6px solid var(--c-coral)">
        <strong>เบราว์เซอร์นี้ไม่รองรับการฟังเสียง (Web Speech API)</strong>
        <p class="mb-0 text-muted">กรุณาใช้ Google Chrome ผ่าน localhost หรือ https — ยังฝึกอ่านได้โดยกด "🔊 ฟังคำ" และ "ต่อไป" เอง</p>
    </div>

    <div class="ht-wordcard mb-3">
        <div class="ht-word" id="pWord">—</div>
        <div id="pHeard" class="mt-2" style="font-size:1.3rem;min-height:1.6em;color:var(--ink-soft)"></div>
    </div>

    <div class="d-flex flex-wrap gap-3 justify-content-center mb-4">
        <button id="pSpeak" class="ht-btn ht-btn--lg ht-btn--ghost">🔊 ฟังคำ</button>
        <button id="pMic"   class="ht-btn ht-btn--lg ht-btn--green">🎤 อ่าน</button>
        <button id="pNext"  class="ht-btn ht-btn--lg ht-btn--purple">ต่อไป →</button>
    </div>

    <div class="ht-progress" style="background:var(--c-blue-soft);border-radius:var(--r-pill);height:14px;overflow:hidden;max-width:680px;margin:0 auto">
        <div id="pBar" style="height:100%;width:0;background:var(--c-blue);transition:width .3s ease"></div>
    </div>

    <!-- ผลการฝึกอ่าน: โผล่ทีละคำเมื่ออ่านถูก · ตัวเลข = จำนวนครั้งที่อ่านกว่าจะถูก -->
    <div id="pResultsWrap" class="mt-5" style="display:none">
        <h3 class="mb-2">📋 ผลการฝึกอ่าน
            <span class="text-muted" style="font-size:.9rem;font-weight:500">— ตัวเลข = จำนวนครั้งที่อ่านกว่าจะถูก (1 = ถูกตั้งแต่ครั้งแรก)</span>
        </h3>
        <div class="ht-table-wrap" style="overflow-x:auto">
            <table class="ht-table" style="min-width:max-content">
                <thead><tr id="pResHead"><th>คำ</th></tr></thead>
                <tbody><tr id="pResRow"><td class="fw-7">ครั้งที่อ่าน</td></tr></tbody>
            </table>
        </div>
    </div>

    <script>window.PRACTICE = <?= json_encode(['words' => $words], JSON_UNESCAPED_UNICODE) ?>;</script>
    <script src="assets/js/practice-reader.js?v=<?= @filemtime(__DIR__ . '/assets/js/practice-reader.js') ?: '1' ?>"></script>
    <script>
        PracticeReader.init({
            words:   (window.PRACTICE && window.PRACTICE.words) || [],
            ttsUrl:  'tts.php',
            exitUrl: 'practice.php'
        });
    </script>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
