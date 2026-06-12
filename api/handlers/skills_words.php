<?php
// GET /api/skills/words?level=1&limit=8
// Returns {success:true, words:[{id,word,imagePaths,soundPath,spokenForm,level,classId}], count}
// Reads sound/image paths straight from the local wordstest (owned in-app; no readthai join).
$level = max(1, min(3, (int)($_GET['level'] ?? 1)));
$limit = max(1, min(100, (int)($_GET['limit'] ?? 50)));

$stmt = $pdo->prepare(
    'SELECT h.id, h.word, h.level, h.class_id, h.spoken_form,
            h.sound_path, h.image_path
     FROM wordstest h
     WHERE h.level = ? AND h.word IS NOT NULL
     ORDER BY RAND() LIMIT ?'
);
$stmt->execute([$level, $limit]);
$rows = $stmt->fetchAll();

function parse_img(string $raw): ?string {
    if ($raw === '') return null;
    $dec = json_decode($raw, true);
    if (is_array($dec)) {
        return $dec['image_original'] ?? $dec['original'] ?? $dec['img_full'] ?? $dec['hint1'] ?? null;
    }
    return $raw;
}

$words = array_map(function ($r) {
    $imgRaw  = (string)($r['image_path'] ?? '');
    $imgPath = parse_img($imgRaw);

    return [
        'id'         => (int)$r['id'],
        'word'       => (string)$r['word'],
        'imagePaths' => $imgPath ? ['original' => $imgPath] : null,
        'soundPath'  => $r['sound_path'] ?: null,
        'spokenForm' => $r['spoken_form'] ?? null,
        'level'      => (int)$r['level'],
        'classId'    => (int)$r['class_id'],
    ];
}, $rows);

json_response(['success' => true, 'words' => $words, 'count' => count($words)]);
