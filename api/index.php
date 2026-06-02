<?php
/**
 * api/index.php — Front controller for game API endpoints
 * Routes extension-less URLs and dynamic-segment paths to handler files.
 * All handlers share $me (student data), $pdo (PDO), $method, $parts.
 */
session_start();
require_once dirname(__DIR__) . '/includes/functions.php';  // db(), json_response(), audit_*

$method = $_SERVER['REQUEST_METHOD'];

// ── Path parsing ─────────────────────────────────────────────────────────────
// REQUEST_URI example: /newhittest/api/balloon/all-words?gradeLevel=1
$uriPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$base    = '/newhittest/api/';
$path    = ltrim(substr($uriPath, strlen($base)), '/');
$parts   = explode('/', $path);

// ── Identity ─────────────────────────────────────────────────────────────────
$isStudent = ($_SESSION['role'] ?? '') === 'student' && !empty($_SESSION['stu']['stuid']);
$isTeacher = !empty($_SESSION['sc_id']);
$role      = $isStudent ? 'student' : ($isTeacher ? 'teacher' : 'guest');

// ── Audit middleware (registered before the auth gate so 401s are logged too) ─
audit_request_begin([
    'role'   => $role,
    'method' => $method,
    'path'   => $path,
    'stuid'  => $isStudent ? ($_SESSION['stu']['stuid'] ?? null) : null,
    'sc_id'  => (string)($_SESSION['sc_id'] ?? '') ?: null,
]);

// ── Auth ─────────────────────────────────────────────────────────────────────
if (!$isStudent && !$isTeacher) {
    json_response(['error' => true, 'message' => 'ยังไม่ได้เข้าสู่ระบบ'], 401);
}

// $me carries student identity used by handlers that save results
$me = $isStudent
    ? $_SESSION['stu']
    : ['stuid' => 'preview', 'sc_id' => (string)$_SESSION['sc_id'], 'stuname' => 'ครู', 'class_id' => 1, 'rooms' => 1];

$pdo = db();

// ── Dispatch ─────────────────────────────────────────────────────────────────
$h = __DIR__ . '/handlers/';

if ($path === 'words' && $method === 'GET') {
    require $h . 'words.php';

} elseif ($path === 'skills/words' && $method === 'GET') {
    require $h . 'skills_words.php';

} elseif ($path === 'balloon/all-words' && $method === 'GET') {
    require $h . 'balloon_all_words.php';

} elseif ($path === 'balloon/submit' && $method === 'POST') {
    require $h . 'balloon_submit.php';

} elseif ($path === 'memory/submit' && $method === 'POST') {
    require $h . 'memory_submit.php';

} elseif ($path === 'bubble/submit' && $method === 'POST') {
    require $h . 'bubble_submit.php';

} elseif ($path === 'hangman/next-word' && $method === 'GET') {
    require $h . 'hangman_next_word.php';

} elseif ($path === 'hangman/guess' && $method === 'POST') {
    require $h . 'hangman_guess.php';

} elseif ($path === 'sessions/training/start' && $method === 'POST') {
    require $h . 'training_start.php';

} elseif (count($parts) === 3 && $parts[0] === 'tasks' && $parts[2] === 'submit' && $method === 'POST') {
    $taskId = $parts[1];
    require $h . 'training_task_submit.php';

} elseif (count($parts) === 3 && $parts[0] === 'sessions' && $parts[2] === 'summary' && $method === 'GET') {
    $sessionId = $parts[1];
    require $h . 'training_summary.php';

} elseif (count($parts) === 3 && $parts[0] === 'sessions' && $parts[2] === 'end' && $method === 'POST') {
    $sessionId = $parts[1];
    require $h . 'training_end.php';

} else {
    json_response(['error' => true, 'message' => "Unknown route: {$method} {$path}"], 404);
}
