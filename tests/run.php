<?php
/**
 * tests/run.php — full suite: unit + integration.
 * Usage: php tests/run.php          (exit 0 = green)
 *        php tests/run.php unit     (unit only)
 *        php tests/run.php int      (integration only)
 *        php tests/run.php deploy   (deploy.sh against a fake server)
 *        php tests/run.php ci       (the pipeline's scripts in ci/)
 */

declare(strict_types=1);

$which = $argv[1] ?? 'all';
$fail = 0;

$run = function (string $script) use (&$fail) {
    // the public clone has no tests/ci.php (it tests scripts private to the Gitea repository)
    if (!is_file(__DIR__ . '/' . $script)) { echo "\n{$script}: not in this checkout, skipped\n"; return; }
    passthru(PHP_BINARY . ' ' . escapeshellarg(__DIR__ . '/' . $script), $rc);
    if ($rc !== 0) $fail = 1;
};

echo "########## bfrackowiak.pl test suite ##########\n";
if ($which === 'all' || $which === 'unit') $run('unit.php');
if ($which === 'all' || $which === 'int')  $run('integration.php');
if ($which === 'all' || $which === 'deploy') $run('deploy.php');
if ($which === 'all' || $which === 'ci')     $run('ci.php');

echo $fail === 0 ? "\n########## SUITE GREEN ##########\n" : "\n########## SUITE FAILED ##########\n";
exit($fail);
