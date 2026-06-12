<?php
// GET /api/words?gradeLevel=1&difficulty=1&limit=50
// Returns {error:false, words:[{id,word,level,classId,spokenForm,soundPath}], count}
// Reads sound_path straight from the local wordstest (owned in-app; no readthai join).
$gradeLevel = max(1, min(6, (int)($_GET['gradeLevel'] ?? 1)));
$limit      = max(1, min(200, (int)($_GET['limit'] ?? 50)));
$difficulty = isset($_GET['difficulty']) && $_GET['difficulty'] !== ''
    ? max(1, min(3, (int)$_GET['difficulty']))
    : null;

$base = 'SELECT h.id, h.word, h.level, h.class_id, h.spoken_form, h.sound_path
         FROM wordstest h
         WHERE h.class_id = ? AND h.word IS NOT NULL';

if ($difficulty !== null) {
    $stmt = $pdo->prepare($base . ' AND h.level = ? ORDER BY RAND() LIMIT ?');
    $stmt->execute([$gradeLevel, $difficulty, $limit]);
} else {
    $stmt = $pdo->prepare($base . ' ORDER BY RAND() LIMIT ?');
    $stmt->execute([$gradeLevel, $limit]);
}
$rows = $stmt->fetchAll();

$words = array_map(function ($r) {
    return [
        'id'         => (int)$r['id'],
        'word'       => (string)$r['word'],
        'level'      => $r['level'] !== null ? (int)$r['level'] : null,
        'classId'    => $r['class_id'] !== null ? (int)$r['class_id'] : null,
        'spokenForm' => $r['spoken_form'] ?? null,
        'soundPath'  => $r['sound_path'] ?: null,
    ];
}, $rows);

json_response(['error' => false, 'words' => $words, 'count' => count($words)]);
