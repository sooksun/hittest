<?php
// POST /api/hangman/guess  {sessionId, guess, guessType}
// Returns {error:false,correct,maskedWord[],remainingLives,completed,won,lost,score,word}
//
// Hardening:
//  • session row locked with FOR UPDATE inside a transaction (race protection)
//  • each guess claimed via UNIQUE(session_id, guess_char) → repeat guesses can
//    never re-score or re-deduct a life (idempotent, even under concurrency)
$body      = json_decode(file_get_contents('php://input'), true) ?: [];
$sessionId = (int)($body['sessionId'] ?? 0);
$guess     = (string)($body['guess']    ?? '');
$guessType = (string)($body['guessType']?? 'consonant');

if (!$sessionId || $guess === '') {
    json_response(['error' => true, 'message' => 'Missing sessionId or guess'], 400);
}

$normalGuess = Normalizer::normalize($guess, Normalizer::FORM_C);

$pdo->beginTransaction();

// ── Load + lock session — ownership enforced ─────────────────────────────────
$stmt = $pdo->prepare(
    'SELECT * FROM game_hangman_sessions
     WHERE id = ? AND stuid = ? AND sc_id = ? LIMIT 1 FOR UPDATE'
);
$stmt->execute([$sessionId, $me['stuid'], $me['sc_id']]);
$session = $stmt->fetch();

if (!$session) {
    $pdo->rollBack();
    audit_log_event($pdo, [
        'sc_id' => $me['sc_id'], 'stuid' => $me['stuid'], 'action' => 'ownership_denied',
        'entity_type' => 'hangman_session', 'entity_id' => (string)$sessionId, 'status_code' => 404,
    ]);
    json_response(['error' => true, 'message' => 'Session not found'], 404);
}
if ($session['completed']) {
    $pdo->rollBack();
    json_response(['error' => true, 'message' => 'Session already completed'], 400);
}

// ── Evaluate correctness (pure in-memory) ─────────────────────────────────────
$currentMasked = json_decode($session['masked_word'], true);
$wordText      = $session['word'];
$components    = preg_split('//u', $wordText, -1, PREG_SPLIT_NO_EMPTY);

$correct  = false;
$revealed = [];

if ($guessType === 'word') {
    if (Normalizer::normalize($wordText, Normalizer::FORM_C) === $normalGuess) {
        $correct  = true;
        $revealed = array_keys($components);
    }
} else {
    foreach ($components as $i => $char) {
        if (Normalizer::normalize($char, Normalizer::FORM_C) === $normalGuess) {
            $correct    = true;
            $revealed[] = $i;
        }
    }
}

// ── Claim this guess (UNIQUE constraint rejects duplicates) ───────────────────
try {
    $pdo->prepare(
        'INSERT INTO game_hangman_guesses (session_id, guess_char, guess_type, is_correct)
         VALUES (?,?,?,?)'
    )->execute([$sessionId, $normalGuess, $guessType, $correct ? 1 : 0]);
} catch (PDOException $e) {
    if (($e->errorInfo[1] ?? 0) === 1062) {           // ER_DUP_ENTRY → already guessed
        // Idempotent replay: return the ORIGINAL correctness of this char so the
        // client doesn't replay a "wrong" animation/sound when nothing changed.
        $pdo->rollBack();
        $prevStmt = $pdo->prepare(
            'SELECT is_correct FROM game_hangman_guesses WHERE session_id = ? AND guess_char = ? LIMIT 1'
        );
        $prevStmt->execute([$sessionId, $normalGuess]);
        $wasCorrect = (bool)$prevStmt->fetchColumn();

        audit_log_event($pdo, [
            'sc_id' => $me['sc_id'], 'stuid' => $me['stuid'], 'action' => 'duplicate_guess',
            'entity_type' => 'hangman_session', 'entity_id' => (string)$sessionId,
            'meta_json' => ['guess' => $normalGuess],
        ]);
        json_response([
            'error'          => false,
            'correct'        => $wasCorrect,
            'duplicate'      => true,
            'maskedWord'     => $currentMasked,
            'remainingLives' => (int)$session['remaining_lives'],
            'completed'      => false,
            'won'            => false,
            'lost'           => false,
            'score'          => (int)$session['score'],
            'word'           => null,
        ]);
    }
    $pdo->rollBack();
    throw $e;
}

// ── Apply reveal / score / lives ──────────────────────────────────────────────
$updatedMasked = $currentMasked;
foreach ($revealed as $pos) {
    if (isset($components[$pos])) {
        $updatedMasked[$pos] = $components[$pos];
    }
}

$baseScores = ['consonant' => 10, 'vowel' => 15, 'tone' => 20, 'word' => 50];
$scoreDelta = $correct ? ($baseScores[$guessType] ?? 10) + (int)floor($session['remaining_lives'] * 2) : 0;
$newScore   = (int)$session['score'] + $scoreDelta;

$newLives = (int)$session['remaining_lives'];
if (!$correct) {
    $newLives = max(0, $newLives - 1);
}

$isWon      = !in_array('_', $updatedMasked, true);
$isLost     = $newLives === 0 && !$isWon;
$completed  = $isWon || $isLost;
$actualWord = $completed ? $wordText : null;

// ── Persist session state ─────────────────────────────────────────────────────
$pdo->prepare(
    'UPDATE game_hangman_sessions
     SET masked_word=?, remaining_lives=?, score=?, completed=?, ended_at=?
     WHERE id=? AND stuid=? AND sc_id=?'
)->execute([
    json_encode($updatedMasked, JSON_UNESCAPED_UNICODE),
    $newLives,
    $newScore,
    $completed ? 1 : 0,
    $completed ? date('Y-m-d H:i:s') : null,
    $sessionId,
    $me['stuid'],
    $me['sc_id'],
]);

// ── Save to game_results on completion (same transaction = atomic) ───────────
if ($completed) {
    $pdo->prepare(
        'INSERT INTO game_results
            (sc_id, stuid, stuname, class_id, game, grade, difficulty, score, stats_json, created_at)
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

$pdo->commit();

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
