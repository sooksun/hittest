<?php
/**
 * includes/prompt_template.php — พอร์ตจาก readthai PromptTemplateService (เดิม TypeScript)
 *
 * แปลง "คำอธิบายภาพอังกฤษ" (whatToDrawEn) → positive/negative prompt สำหรับ ComfyUI
 * เป็นตรรกะ deterministic ล้วน (ไม่พึ่ง AI): ตรวจประเภทคำ (สัตว์/สิ่งของ/การกระทำ) แล้วเลือก
 * style + negative ให้เหมาะ + รองรับ word_overrides.json (ล็อกความหมายคำเสี่ยง 100 คำ)
 */

// ── สไตล์/Negative มาตรฐาน (การ์ตูนแฟลชการ์ดเด็ก: ชัด จำง่าย ไม่ใช่ 3D เคลย์/บลอบ) ──
// สไตล์เหมือนจริง (realvisxl) — ภาพประกอบหนังสือเด็กแบบ digital painting นุ่ม ๆ
const PT_STYLE_STORYBOOK = "warm friendly children's book illustration, soft digital painting, gentle natural lighting, single clear main subject, simple clean background, wholesome, cute, high quality, detailed, clear and recognizable";

// คง anatomy negatives แรง ๆ (มือ/นิ้ว/เท้า/หน้า) — เอา anti-realism ออกเพราะตอนนี้อยากได้เหมือนจริง
const PT_NEGATIVE_STORYBOOK = "text, letters, numbers, logo, watermark, horror, dark, scary, creepy, complex background, cluttered, busy scene, crowd, ugly, distorted, blurry, low quality, noise, grain, claymation, clay figure, blob creature, mochi, 3d render, cgi, bad anatomy, deformed, disfigured, mutated hands, bad hands, malformed hands, extra fingers, missing fingers, fused fingers, too many fingers, extra limbs, missing limbs, extra arms, deformed face, distorted face, asymmetrical eyes, ugly face, malformed feet, extra feet, extra characters";

const PT_ANIMAL_NEGATIVE = "extra legs, extra limbs, extra feet, extra hooves, duplicated legs, merged legs, multiple bodies, deformed anatomy, incorrect anatomy, mutated limbs, malformed animal, fused body, overlapping bodies, conjoined animals, body merging, blended anatomy, hybrid animal, incorrect species, wrong number of legs, anatomy errors, limb duplication";

const PT_SINGLE_SUBJECT_NEG = "multiple animals, herd, group of animals, pack of animals, baby animals, two animals, multiple subjects, crowd, duplicate, twin, many objects, several items";

const PT_CLOSEUP_STYLE = "close-up shot, centered composition, single isolated subject, clean simple background, soft gradient background, no distractions, studio lighting, product photography style, sharp focus on subject, minimalist composition";

const PT_CLOSEUP_NEGATIVE = "busy background, cluttered scene, multiple objects, crowded composition, other subjects, distracting elements, complex scene, landscape, environment, people, characters, hands holding object";

// สไตล์ "ฉากบริบท" — สำหรับคำนามธรรม/แนวคิด/การกระทำ: เล่าความหมายด้วยบริบท (ชี้/เปรียบเทียบ/ไฮไลต์) มีหลายองค์ประกอบได้ แต่จุดเน้นเดียวชัด
const PT_CONTEXT_STYLE = "clear conceptual illustration for a children's picture dictionary that explains the meaning, simple uncluttered composition with one obvious focal point, use a pointing hand, a highlight circle, an arrow, or a size comparison to emphasize the key idea, friendly cute children's book style, soft digital painting, clean simple background, easy for a child to understand";

// สไตล์ "หลายภาพในรูปเดียว" — ภาพเดียวแบ่ง 2-3 ช่องใหญ่เรียงแถวเดียว (ลำดับเหตุการณ์/ก่อน-หลัง/หลายตัวอย่าง)
// ระวัง: ห้ามใช้คำว่า comic strip / storyboard — SDXL จะวาดเป็นหน้าการ์ตูน grid 8-12 ช่อง + speech bubble
const PT_MULTIPANEL_STYLE = "educational illustration for a children's picture dictionary, one wide image split into two or three large panels arranged side by side in a single horizontal row, clean thin borders between panels, each large panel fills the full height and contains one simple clear subject, the panels together explain the meaning of the word, flat clean background inside every panel, friendly cute children's book style, soft digital painting, easy for a child to understand";

// negative สำหรับ multi-panel: คุมคุณภาพ/anatomy เหมือนเดิม แต่ "ไม่" แบน busy scene/หลายตัวละคร (หลายช่องย่อมมีหลายองค์ประกอบ)
// + กันหน้าการ์ตูน/grid ช่องเยอะ/ช่องซ้ำ/speech bubble แรง ๆ
const PT_MULTIPANEL_NEGATIVE = "comic book page, manga page, comic strip, storyboard, many small panels, more than three panels, dense panel grid, multiple rows of panels, tiny panels, repeated identical panels, photo collage, speech bubbles, speech balloon, captions, text, letters, numbers, logo, watermark, horror, dark, scary, creepy, ugly, distorted, blurry, low quality, noise, grain, claymation, clay figure, blob creature, 3d render, cgi, bad anatomy, deformed, disfigured, mutated hands, bad hands, extra fingers, missing fingers, extra limbs, deformed face, distorted face, merged panels, overlapping panels, chaotic layout";

/** โหลด + แบน word_overrides.json (โครงสร้าง: หมวด → คำ → {whatToDrawEn,category,extraNegative}) */
function pt_load_overrides(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = [];
    $path = dirname(__DIR__) . '/config/word_overrides.json';
    if (is_file($path)) {
        $json = json_decode((string)file_get_contents($path), true);
        if (is_array($json)) {
            foreach ($json as $cat => $words) {
                if (is_string($cat) && $cat !== '' && $cat[0] === '_') {
                    continue;                       // ข้าม metadata (_comment/_version/…)
                }
                if (is_array($words)) {
                    foreach ($words as $w => $ov) {
                        if (is_array($ov)) {
                            $cache[$w] = $ov;
                        }
                    }
                }
            }
        }
    }
    return $cache;
}

/** ดึง override ของคำ (trim ก่อน) — คืน null ถ้าไม่มี */
function pt_get_override(string $word): ?array
{
    $all = pt_load_overrides();
    $w = trim($word);
    return $all[$w] ?? null;
}

/** คีย์เวิร์ดสัตว์ (ไทย+อังกฤษ) ใช้ตัดสินว่าเป็นภาพสัตว์ */
function pt_is_animal(string $whatToDrawEn, string $word): bool
{
    static $kw = [
        'buffalo','water buffalo','cow','bull','cattle','gaur','banteng','elephant','tiger','lion',
        'bear','deer','cat','dog','bird','fish','frog','snake','turtle','rabbit','mouse','chicken',
        'duck','goose','horse','pig','goat','sheep','monkey','animal','creature','crocodile','wolf','fox',
        'กระทิง','กระบือ','ควาย','วัว','ช้าง','เสือ','สิงโต','หมี','กวาง','แมว','หมา','สุนัข','นก',
        'ปลา','กบ','งู','เต่า','กระต่าย','หนู','ไก่','เป็ด','ห่าน','ม้า','หมู','แพะ','แกะ','ลิง',
    ];
    $hay = mb_strtolower($whatToDrawEn . ' ' . $word);
    foreach ($kw as $k) {
        if (mb_strpos($hay, mb_strtolower($k)) !== false) {
            return true;
        }
    }
    return false;
}

/**
 * ตรวจว่าคำบรรยายเป็นแบบ "หลายภาพในรูปเดียว" (multi-panel) หรือไม่
 * นี่คือ routing เดียวที่ดูจาก "คำบรรยาย" (ไม่ใช่คำศัพท์) — คีย์เวิร์ด panel เป็น marker ของ layout
 * ไม่ใช่ subject จึงไม่มีปัญหา misclassify แบบ fruit/cat (ดูคอมเมนต์ใน pt_build_comfyui_prompt)
 */
function pt_is_multipanel(string $whatToDraw): bool
{
    static $kw = [
        'panel', 'panels', 'storyboard', 'comic strip', 'triptych',
        'divided into', 'split into', 'before and after', 'grid of',
        'ภาพแบ่ง', 'แบ่งช่อง', 'หลายช่อง', 'ช่องแรก', 'ช่องที่', 'ช่องกลาง', 'ช่องสุดท้าย',
        '2 ช่อง', '3 ช่อง', '4 ช่อง', 'สองช่อง', 'สามช่อง', 'สี่ช่อง',
    ];
    $hay = mb_strtolower($whatToDraw);
    foreach ($kw as $k) {
        if (mb_strpos($hay, mb_strtolower($k)) !== false) {
            return true;
        }
    }
    return false;
}

/** คีย์เวิร์ดคำนามที่จับต้องได้ (สิ่งของ/อาหาร/พืช/ยานพาหนะ) → ใช้สไตล์ซูมใกล้ */
function pt_is_concrete(string $whatToDrawEn, string $word): bool
{
    static $kw = [
        'table','chair','book','pencil','pen','bag','ball','door','window','clock','phone','cup','glass',
        'plate','bowl','spoon','fork','knife','umbrella','shoe','hat','shirt','pants','dress','key','box',
        'bottle','mirror','chopsticks','cloth','pillow','blanket','bed','sofa','cabinet','lamp',
        'apple','banana','orange','mango','rice','egg','bread','cake','cookie','fruit','vegetable','meat',
        'water','milk','candy','ice cream','flower','tree','leaf','grass','rose','lotus','jasmine','sunflower',
        'car','bus','train','plane','boat','bicycle','motorcycle','airplane','ship','bike',
        'โต๊ะ','เก้าอี้','หนังสือ','ดินสอ','ปากกา','กระเป๋า','ลูกบอล','ประตู','หน้าต่าง','นาฬิกา','โทรศัพท์',
        'ถ้วย','แก้ว','จาน','ชาม','ช้อน','ส้อม','มีด','ร่ม','รองเท้า','หมวก','เสื้อ','กางเกง','กระโปรง',
        'กุญแจ','กล่อง','ขวด','กระจก','ตะเกียบ','ผ้า','หมอน','ผ้าห่ม','เตียง','โซฟา','ตู้','โคมไฟ',
        'แอปเปิ้ล','กล้วย','ส้ม','มะม่วง','ข้าว','ไข่','ขนมปัง','เค้ก','คุกกี้','ผลไม้','ผัก','เนื้อ','ปลา',
        'น้ำ','นม','ขนม','ไอศกรีม','ดอกไม้','ต้นไม้','ใบไม้','หญ้า','กุหลาบ','บัว','มะลิ','ดอกทานตะวัน',
        'รถยนต์','รถเมล์','รถไฟ','เครื่องบิน','เรือ','จักรยาน','มอเตอร์ไซค์',
    ];
    $hay = mb_strtolower($whatToDrawEn . ' ' . $word);
    foreach ($kw as $k) {
        if (mb_strpos($hay, mb_strtolower($k)) !== false) {
            return true;
        }
    }
    return false;
}

/**
 * สร้าง prompt ภาษาไทยให้ LLM แปลงคำ → "ฉากที่วาดได้จริง"
 * ถ้าคำมี override → คืน "OVERRIDE: ..." (ผู้เรียกจะ bypass LLM)
 */
function pt_build_what_to_draw_prompt(string $word): string
{
    $ov = pt_get_override($word);
    if ($ov && !empty($ov['whatToDrawEn'])) {
        return 'OVERRIDE: "' . $ov['whatToDrawEn'] . '"';
    }
    return "คุณคือผู้ช่วยออกแบบภาพประกอบ \"พจนานุกรมภาพ\" สำหรับเด็กประถม\n\n"
        . "คำศัพท์: \"{$word}\"\n\n"
        . "งาน: ออกแบบ \"ภาพ 1 ภาพ\" ที่ช่วยให้เด็กเข้าใจ \"ความหมาย\" ของคำนี้ "
        . "ทุกคำต้องวาดได้เสมอ แม้เป็นคำนามธรรม โดยใช้บริบท/การเปรียบเทียบ/การชี้ "
        . "หรือถ้าฉากเดียวสื่อไม่พอ ให้แบ่งภาพเป็น 2-3 ช่องใหญ่เรียงซ้ายไปขวาในรูปเดียว — ห้ามปฏิเสธว่าวาดไม่ได้\n\n"
        . "แนวทางตามประเภทคำ:\n"
        . "- คำนามจับต้องได้ (สิ่งของ/อาหาร/พืช/ยานพาหนะ): วาดสิ่งนั้นชัด ๆ ซูมใกล้ พื้นหลังเรียบ\n"
        . "- สัตว์: วาดสัตว์ 1 ตัว เต็มตัว เห็นขาครบ\n"
        . "- คำกริยา/การกระทำ: เด็กกำลังทำกริยานั้นอย่างชัดเจน\n"
        . "- คำนามธรรม/คุณศัพท์/แนวคิด: วาด \"ฉากบริบท\" ที่สื่อความหมาย เช่น มือหรือเด็กชี้ไปที่สิ่งนั้น, "
        . "เปรียบเทียบเล็ก-ใหญ่/น้อย-มากแล้ววงกลมเน้นตัวที่ตรงกับคำ, ลำดับก่อน-หลัง, หรือสีหน้า-ท่าทางที่สื่ออารมณ์\n"
        . "- คำที่ฉากเดียวสื่อไม่พอ (ลำดับเวลา/การเปลี่ยนแปลง/เหตุ-ผล/หลายตัวอย่างของแนวคิดเดียว): "
        . "ออกแบบเป็น \"ภาพแบ่ง 2-3 ช่องใหญ่เรียงซ้ายไปขวาในรูปเดียว\" แล้วบอกชัดว่าแต่ละช่องมีอะไร "
        . "(ใช้เมื่อจำเป็นจริงเท่านั้น — ถ้าฉากเดียวสื่อได้ ให้ใช้ฉากเดียว)\n\n"
        . "ตัวอย่าง:\n"
        . "- \"ราคา\" → สินค้าวางบนโต๊ะมีป้ายราคาติดอยู่ มือเด็กชี้ไปที่ป้ายราคา บนพื้นหลังสีอ่อน\n"
        . "- \"โต\" → ลูกแมว 3 ตัวเรียงจากเล็กไปใหญ่ มีวงกลมเน้นที่แมวตัวใหญ่ที่สุด บนพื้นหลังสีพาสเทล\n"
        . "- \"ฤดู\" → ภาพแบ่ง 3 ช่องในรูปเดียว ช่องแรกเด็กกางร่มกลางสายฝน ช่องกลางเด็กใส่หมวกกลางแดดจ้า "
        . "ช่องสุดท้ายเด็กใส่เสื้อกันหนาวมีใบไม้ร่วง บนพื้นหลังสีฟ้าอ่อน\n"
        . "- \"สะอาด\" → ภาพแบ่ง 2 ช่องในรูปเดียว ช่องแรกมือเปื้อนโคลน ช่องที่สองมือสะอาดมีฟองสบู่และประกายวิบวับ "
        . "มีวงกลมเน้นช่องที่สอง บนพื้นหลังสีขาว\n\n"
        . "กฎการตอบ:\n"
        . "1. ตอบเป็นภาษาไทย \"1 ประโยคเดียว\" ล้วน ๆ — ห้ามมีหัวข้อ ห้าม markdown ห้ามเสนอหลายตัวเลือก ห้ามอธิบายเพิ่ม\n"
        . "2. ฉากเดียว: มีจุดเน้นเดียวชัดเจน ภาพไม่รก / แบบแบ่งช่อง: ไม่เกิน 3 ช่อง เรียงซ้ายไปขวา แต่ละช่องเรียบง่ายมีเรื่องเดียว\n"
        . "3. ลงท้ายด้วย \"บนพื้นหลังสี...\" เสมอ\n"
        . "4. ต้องวาดได้เสมอ — ห้ามตอบ AMBIGUOUS หรือปฏิเสธ\n\n"
        . "ตอบเพียง 1 ประโยคภาษาไทย:";
}

/**
 * สร้าง positive/negative prompt สุดท้ายสำหรับ ComfyUI จาก whatToDrawEn (อังกฤษ)
 * คืน ['positive'=>..., 'negative'=>..., 'whatToDraw'=>..., 'wasOverridden'=>bool]
 */
function pt_build_comfyui_prompt(string $whatToDrawEn, string $word): array
{
    $ov = pt_get_override($word);
    $final = $whatToDrawEn;
    $wasOverridden = false;
    if ($ov && !empty($ov['whatToDrawEn'])) {
        $final = (string)$ov['whatToDrawEn'];
        $wasOverridden = true;
    }

    $style    = PT_STYLE_STORYBOOK;
    $negative = PT_NEGATIVE_STORYBOOK;

    // จัดประเภทจาก "คำศัพท์" เป็นหลัก (ส่ง '' แทนคำบรรยาย) — ฉากบริบทมักพูดถึงวัตถุ (fruit/cat/table)
    // ถ้า detect จากคำบรรยายจะเข้าใจผิดว่าเป็น concrete แล้วบังคับ closeup เดี่ยว ทับฉากบริบทที่ตั้งใจ
    // ยกเว้น multi-panel: ตรวจจาก "คำบรรยาย" (คีย์เวิร์ด panel/แบ่งช่อง เป็น marker ของ layout ไม่ใช่ subject)
    $isAnimal   = (($ov['category'] ?? '') === 'animal')   || pt_is_animal('', $word);
    $isConcrete = (($ov['category'] ?? '') === 'concrete') || pt_is_concrete('', $word);

    if (pt_is_multipanel($final)) {
        // หลายภาพในรูปเดียว (2-4 ช่อง): อธิบายคำผ่านลำดับ/ก่อน-หลัง/หลายตัวอย่าง
        // negative เฉพาะทาง — ไม่แบน busy scene/หลายตัวละคร และกัน speech bubble/ช่องซ้อนเละ
        $positive = "{$final}, " . PT_MULTIPANEL_STYLE . ", educational illustration, child-friendly, high quality, clean render";
        $negative = PT_MULTIPANEL_NEGATIVE;
    } elseif ($isConcrete && !$isAnimal) {
        // คำนามจับต้องได้: ซูมใกล้ พื้นหลังเรียบ
        $positive = "{$final}, " . PT_CLOSEUP_STYLE . ", {$style}, educational illustration, child-friendly, high quality, clean render, no harsh details";
        $negative = "{$negative}, " . PT_CLOSEUP_NEGATIVE;
    } elseif ($isAnimal) {
        // สัตว์: 1 ตัว + คุม anatomy + ตัวเดียว
        $positive = "{$final}, {$style}, single animal only, centered composition, clean simple background, soft gradient background, accurate animal anatomy, correct number of legs, four legs visible, educational illustration, child-friendly, high quality, clean render";
        $negative = "{$negative}, " . PT_SINGLE_SUBJECT_NEG . ", " . PT_ANIMAL_NEGATIVE . ", " . PT_CLOSEUP_NEGATIVE;
    } else {
        // คำกริยา/นามธรรม/แนวคิด: วาด "ฉากบริบท" สื่อความหมาย (จุดเน้นเดียว ใช้ชี้/เปรียบเทียบ/ไฮไลต์)
        // ไม่ใส่ PT_SINGLE_SUBJECT_NEG เพื่อให้มีหลายองค์ประกอบเปรียบเทียบ/บริบทได้ (เช่น แมว 3 ตัว, มือชี้ป้ายราคา)
        $positive = "{$final}, " . PT_CONTEXT_STYLE . ", educational illustration, child-friendly, easy to understand, high quality, clean render";
    }

    if (!empty($ov['extraNegative'])) {
        $negative .= ', ' . $ov['extraNegative'];
    }

    // เก็บกวาดช่องว่าง/ขึ้นบรรทัด
    $clean = static fn (string $s): string => trim((string)preg_replace('/\s+/u', ' ', str_replace("\n", ' ', $s)));

    return [
        'positive'      => $clean($positive),
        'negative'      => $clean($negative),
        'whatToDraw'    => $final,
        'wasOverridden' => $wasOverridden,
    ];
}
