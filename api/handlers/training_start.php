<?php
// POST /api/sessions/training/start  {gradeLevel, difficulty, limit}
// Returns {sessionId, tasks:[Task], totalTasks}
// LEFT JOINs readthai.wordstest for real sound/image paths.

$body       = json_decode(file_get_contents('php://input'), true) ?: [];
$gradeLevel = max(1, min(6, (int)($body['gradeLevel'] ?? $body['grade'] ?? 1)));
$difficulty = max(1, min(3, (int)($body['difficulty'] ?? 1)));
$limit      = max(1, min(20, (int)($body['limit'] ?? 10)));

// ── Helpers ───────────────────────────────────────────────────────────────────
function ts_uuid(): string {
    $d = random_bytes(16);
    $d[6] = chr(ord($d[6]) & 0x0f | 0x40);
    $d[8] = chr(ord($d[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($d), 4));
}
function ts_shuffle(array $a): array { shuffle($a); return $a; }

/**
 * Parse readthai image_path JSON → extract 'image_original' path → prepend /newhittest.
 * Task components use <img src={task.imagePath}> directly, so full path is required.
 */
function ts_img(?string $raw): ?string {
    if (!$raw || $raw === '') return null;
    $dec = json_decode($raw, true);
    $path = is_array($dec)
        ? ($dec['image_original'] ?? $dec['original'] ?? $dec['img_full'] ?? null)
        : $raw;
    if (!$path) return null;
    // Prepend /newhittest so the direct <img src> resolves correctly via the media junction.
    return '/newhittest' . (str_starts_with($path, '/') ? '' : '/') . $path;
}

function ts_distractors(PDO $pdo, int $wordId, int $classId): array {
    $st = $pdo->prepare(
        'SELECT h.id, h.word, h.spoken_form
         FROM wordstest h
         WHERE h.class_id = ? AND h.id != ? AND h.word IS NOT NULL
         ORDER BY RAND() LIMIT 3'
    );
    $st->execute([$classId, $wordId]);
    return $st->fetchAll();
}

// ── Select words ──────────────────────────────────────────────────────────────
$stmt = $pdo->prepare(
    'SELECT h.id, h.word, h.level, h.class_id, h.spoken_form,
            r.sound_path, r.image_path
     FROM wordstest h
     LEFT JOIN readthai.wordstest r ON r.id = h.id
     WHERE h.class_id = ? AND h.level = ? AND h.word IS NOT NULL
     ORDER BY RAND() LIMIT ?'
);
$stmt->execute([$gradeLevel, $difficulty, $limit]);
$words = $stmt->fetchAll();

if (empty($words)) {
    $stmt = $pdo->prepare(
        'SELECT h.id, h.word, h.level, h.class_id, h.spoken_form, r.sound_path, r.image_path
         FROM wordstest h LEFT JOIN readthai.wordstest r ON r.id = h.id
         WHERE h.class_id = ? AND h.word IS NOT NULL ORDER BY RAND() LIMIT ?'
    );
    $stmt->execute([$gradeLevel, $limit]);
    $words = $stmt->fetchAll();
}
if (empty($words)) {
    json_response(['error' => true, 'message' => 'No words available for this grade'], 404);
}

// ── Template pool ─────────────────────────────────────────────────────────────
$templates = ['L001_LISTEN_PICK_WORD', 'R001_READ_PICK_MEANING', 'W001_FILL_MISSING_CHAR'];
if ($gradeLevel >= 3) $templates[] = 'W003_TYPE_WORD';
if ($gradeLevel >= 5) $templates[] = 'S001_RECORD_PRONUNCIATION';
$tmplCount = count($templates);
$timerMap  = ['L001_LISTEN_PICK_WORD' => 15000, 'R001_READ_PICK_MEANING' => 20000,
    'W001_FILL_MISSING_CHAR' => 20000, 'W003_TYPE_WORD' => 30000, 'S001_RECORD_PRONUNCIATION' => 30000];
$thaiPool  = preg_split('//u', 'กขคงจฉชซญดตถทธนบปผฝพฟภมยรลวศษสหอฮ', -1, PREG_SPLIT_NO_EMPTY);

$tasks = [];
foreach ($words as $idx => $w) {
    $tmpl    = $templates[$idx % $tmplCount];
    $taskId  = ts_uuid();
    $wText   = (string)$w['word'];
    $chars   = preg_split('//u', $wText, -1, PREG_SPLIT_NO_EMPTY) ?: ['_'];
    $classId = (int)$w['class_id'];
    $imgPath = ts_img((string)($w['image_path'] ?? ''));

    $task = [
        'taskId'      => $taskId,
        'taskIndex'   => $idx,
        'templateKey' => $tmpl,
        'wordId'      => (int)$w['id'],
        'wordText'    => $wText,
        'prompt'      => '',
        'soundPath'   => $w['sound_path'] ?: null,
        'imagePath'   => $imgPath,
        'choices'     => [],
        'timerMs'     => $timerMap[$tmpl] ?? 15000,
        'config'      => null,
    ];

    switch ($tmpl) {
        case 'L001_LISTEN_PICK_WORD':
            $task['prompt'] = 'ฟังเสียงแล้วเลือกคำที่ถูกต้อง';
            $dist = ts_distractors($pdo, (int)$w['id'], $classId);
            $ch   = [['id' => ts_uuid(), 'text' => $wText, 'isCorrect' => true]];
            foreach ($dist as $d) $ch[] = ['id' => ts_uuid(), 'text' => (string)$d['word'], 'isCorrect' => false];
            $task['choices'] = ts_shuffle($ch);
            break;

        case 'R001_READ_PICK_MEANING':
            $task['prompt'] = "อ่านคำ \"{$wText}\" แล้วเลือกความหมายที่ถูกต้อง";
            $dist    = ts_distractors($pdo, (int)$w['id'], $classId);
            $correct = $w['spoken_form'] ?: $wText;
            $ch      = [['id' => ts_uuid(), 'text' => $correct, 'isCorrect' => true]];
            foreach ($dist as $d) $ch[] = ['id' => ts_uuid(), 'text' => (string)($d['spoken_form'] ?: $d['word']), 'isCorrect' => false];
            $task['choices'] = ts_shuffle($ch);
            break;

        case 'W001_FILL_MISSING_CHAR':
            $mi       = array_rand($chars);
            $mc       = $chars[$mi];
            $masked   = $chars; $masked[$mi] = '_';
            $mStr     = implode('', $masked);
            $task['prompt'] = "เติมตัวอักษรที่หายไป: {$mStr}";
            $dChars = ts_shuffle(array_values(array_filter($thaiPool, fn($c) => $c !== $mc)));
            $ch     = [['id' => ts_uuid(), 'text' => $mc, 'isCorrect' => true]];
            foreach (array_slice($dChars, 0, 3) as $c) $ch[] = ['id' => ts_uuid(), 'text' => $c, 'isCorrect' => false];
            $task['choices'] = ts_shuffle($ch);
            $task['config']  = ['maskedWord' => $mStr, 'missingChar' => $mc, 'expectedText' => $wText];
            break;

        case 'W003_TYPE_WORD':
            $task['prompt'] = 'ฟังเสียงแล้วพิมพ์คำที่ได้ยิน';
            $task['config'] = ['expectedWord' => $wText, 'expectedText' => $wText];
            break;

        case 'S001_RECORD_PRONUNCIATION':
            $task['prompt'] = "อ่านคำนี้ให้ชัดเจน: \"{$wText}\"";
            $task['config'] = ['expectedText' => $wText, 'spokenForm' => $w['spoken_form'] ?: $wText];
            break;
    }

    $tasks[] = $task;
}

// ── Persist session ───────────────────────────────────────────────────────────
$sessionId = ts_uuid();
$pdo->prepare(
    'INSERT INTO game_training_sessions
        (id, sc_id, stuid, grade_level, difficulty, tasks_json, total_tasks)
     VALUES (?,?,?,?,?,?,?)'
)->execute([
    $sessionId,
    $me['sc_id'],
    $me['stuid'],
    $gradeLevel,
    $difficulty,
    json_encode($tasks, JSON_UNESCAPED_UNICODE),
    count($tasks),
]);

json_response(['sessionId' => $sessionId, 'tasks' => $tasks, 'totalTasks' => count($tasks)]);
