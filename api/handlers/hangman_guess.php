<?php
// POST /api/hangman/guess  {sessionId, wordId, guess, guessType}
// Returns {error:false,correct,maskedWord[],remainingLives,completed,won,lost,score,word}
$body      = json_decode(file_get_contents('php://input'), true) ?: [];
$sessionId = (int)($body['sessionId'] ?? 0);
$guess     = (string)($body['guess']    ?? '');
$guessType = (string)($body['guessType']?? 'consonant');

if (!$sessionId || $guess === '') {
    json_response(['error' => true, 'message' => 'Missing sessionId or guess'], 400);
}

// ── Load session ──────────────────────────────────────────────────────────────
$stmt = $pdo->prepare('SELECT * FROM game_hangman_sessions WHERE id = ? LIMIT 1');
$stmt->execute([$sessionId]);
$session = $stmt->fetch();

if (!$session) {
    json_response(['error' => true, 'message' => 'Session not found'], 404);
}
if ($session['completed']) {
    json_response(['error' => true, 'message' => 'Session already completed'], 400);
}

$currentMasked = json_decode($session['masked_word'], true);
$wordText      = $session['word'];
$components    = preg_split('//u', $wordText, -1, PREG_SPLIT_NO_EMPTY);

// ── Check guess ───────────────────────────────────────────────────────────────
$normalGuess = Normalizer::normalize($guess, Normalizer::FORM_C);
$correct     = false;
$revealed    = [];

if ($guessType === 'word') {
    $normalWord = Normalizer::normalize($wordText, Normalizer::FORM_C);
    if ($normalWord === $normalGuess) {
        $correct  = true;
        $revealed = array_keys($components);
    }
} else {
    foreach ($components as $i => $char) {
        if (Normalizer::normalize($char, Normalizer::FORM_C) === $normalGuess) {
            $correct = true;
            $revealed[] = $i;
        }
    }
}

// ── Update masked word ────────────────────────────────────────────────────────
$updatedMasked = $currentMasked;
foreach ($revealed as $pos) {
    if (isset($components[$pos])) {
        $updatedMasked[$pos] = $components[$pos];
    }
}

// ── Score delta ───────────────────────────────────────────────────────────────
$baseScores = ['consonant' => 10, 'vowel' => 15, 'tone' => 20, 'word' => 50];
$scoreDelta = 0;
if ($correct) {
    $scoreDelta = ($baseScores[$guessType] ?? 10) + (int)floor($session['remaining_lives'] * 2);
}
$newScore = (int)$session['score'] + $scoreDelta;

// ── Lives ─────────────────────────────────────────────────────────────────────
$newLives = (int)$session['remaining_lives'];
if (!$correct) {
    $newLives = max(0, $newLives - 1);
}

// ── Win / lose ────────────────────────────────────────────────────────────────
$isWon     = !in_array('_', $updatedMasked);
$isLost    = $newLives === 0 && !$isWon;
$completed = $isWon || $isLost;
$actualWord = $completed ? $wordText : null;

// ── Persist ───────────────────────────────────────────────────────────────────
$pdo->prepare(
    'UPDATE game_hangman_sessions
     SET masked_word=?, remaining_lives=?, score=?, completed=?, ended_at=?
     WHERE id=?'
)->execute([
    json_encode($updatedMasked, JSON_UNESCAPED_UNICODE),
    $newLives,
    $newScore,
    $completed ? 1 : 0,
    $completed ? date('Y-m-d H:i:s') : null,
    $sessionId,
]);

// Save to game_results on completion
if ($completed) {
    $pdo->prepare(
        'INSERT INTO game_results (sc_id, stuid, stuname, class_id, game, grade, difficulty, score, stats_json, created_at)
         VALUES (?,?,?,?,?,?,?,?,?,NOW())'
    )->execute([
        $me['sc_id'], $me['stuid'], $me['stuname'] ?? '',
        (int)($me['class_id'] ?? $session['grade_level']),
        'hangman',
        $session['grade_level'],
        $session['difficulty'],
        $newScore,
        json_encode(['won' => $isWon, 'word' => $wordText], JSON_UNESCAPED_UNICODE),
    ]);
}

json_response([
    'error'          => false,
    'correct'        => $correct,
    'maskedWord'     => $updatedMasked,
    'remainingLives' => $newLives,
    'completed'      => $completed,
    'won'            => $isWon,
    'lost'           => $isLost,
    'score'          => $newScore,
    'word'           => $actualWord,
]);
