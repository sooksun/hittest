#!/usr/bin/env php
<?php
/**
 * Run the game security regression tests.
 * Usage:  php tests/run_tests.php
 * Requires PHP 8.0+ with SQLite extension (php_pdo_sqlite).
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/SecurityTest.php';
require_once __DIR__ . '/IntegrationTest.php';
require_once __DIR__ . '/RetentionTest.php';
require_once __DIR__ . '/StoryTest.php';
require_once __DIR__ . '/MediaJobTest.php';
require_once __DIR__ . '/MediaPipelineTest.php';
require_once __DIR__ . '/PromoteYearTest.php';
require_once __DIR__ . '/AuthUserTest.php';
require_once __DIR__ . '/StudentsImportTest.php';

// ── Report ────────────────────────────────────────────────────────────────────
$passed  = $GLOBALS['_test_passed'];
$failed  = $GLOBALS['_test_failed'];
$total   = $passed + $failed;
$failures = $GLOBALS['_test_failures'];

echo "\n" . str_repeat('─', 60) . "\n";
echo "Results: {$passed}/{$total} passed";
if ($failed > 0) {
    echo ", {$failed} FAILED\n";
    foreach ($failures as $f) {
        echo "  ✗ {$f}\n";
    }
} else {
    echo " ✓\n";
}
echo str_repeat('─', 60) . "\n";

exit($failed > 0 ? 1 : 0);
