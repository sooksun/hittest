<?php
// GET /api/balloon/all-words?gradeLevel=1&limitPerLevel=10
// Returns {ok:true, words:{1:[],2:[],3:[]}, totalWords}
// Reads sound/image paths straight from the local wordstest (owned in-app; no readthai join).
$gradeLevel = max(1, min(6, (int)($_GET['gradeLevel'] ?? 1)));
$limit      = max(1, min(50, (int)($_GET['limitPerLevel'] ?? 10)));

$words = [];
$total = 0;

for ($lvl = 1; $lvl <= 3; $lvl++) {
    $stmt = $pdo->prepare(
        'SELECT h.id, h.word, h.level, h.image_path, h.sound_path
         FROM wordstest h
         WHERE h.class_id = ? AND h.level = ? AND h.word IS NOT NULL
           AND CHAR_LENGTH(h.word) >= 2 AND CHAR_LENGTH(h.word) <= 5
         ORDER BY RAND() LIMIT ?'
    );
    $stmt->execute([$gradeLevel, $lvl, $limit]);

    $words[$lvl] = array_map(function ($r) {
        $imgRaw = (string)($r['image_path'] ?? '');
        $imgPath = null;
        if ($imgRaw !== '') {
            $dec = json_decode($imgRaw, true);
            $imgPath = is_array($dec)
                ? ($dec['image_original'] ?? $dec['original'] ?? $dec['img_full'] ?? null)
                : $imgRaw;
        }
        return [
            'id'        => (int)$r['id'],
            'word'      => (string)$r['word'],
            'imagePath' => $imgPath,
            'soundPath' => $r['sound_path'] ?: null,
            'level'     => (int)$r['level'],
        ];
    }, $stmt->fetchAll());

    $total += count($words[$lvl]);
}

json_response(['ok' => true, 'words' => $words, 'totalWords' => $total]);
