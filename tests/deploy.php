<?php
/**
 * tests/deploy.php — deploy.sh against a fake server on this machine.
 *
 * tests/fixtures/fake-ssh stands in for ssh: it runs the "remote" command
 * locally, with HOME pointed at a temp directory that plays the server's home.
 * rsync, the two-way guard and the remote helpers therefore run for real, with
 * no network and nothing outside the temp directory touched.
 *
 * Each sandbox is a copy of this repo ("the laptop") plus a server home whose
 * docroot is seeded with a built copy of the site and a runtime database the
 * deploy must never overwrite.
 */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$ROOT = dirname(__DIR__);
$DP_TMP = sys_get_temp_dir() . '/bfrackowiak-deploy-' . getmypid();
const FAKE_SSH = __DIR__ . '/fixtures/fake-ssh';

register_shutdown_function(function () use ($DP_TMP) {
    exec('chmod -R u+w ' . escapeshellarg($DP_TMP) . ' 2>/dev/null; rm -rf ' . escapeshellarg($DP_TMP));
});

/** shell out, return [rc, combined output] */
function dp_sh(string $cmd): array
{
    exec($cmd . ' 2>&1', $out, $rc);
    return [$rc, implode("\n", $out)];
}

/**
 * A laptop copy of the repo and a fake server home with a seeded docroot.
 * @return array{local:string, rhome:string, docroot:string}
 */
function dp_sandbox(string $name): array
{
    global $ROOT, $DP_TMP;
    $base = "{$DP_TMP}/{$name}";
    $local = "{$base}/local";
    $rhome = "{$base}/rhome";
    $docroot = "{$rhome}/domains/bfrackowiak.pl/public_html";
    @mkdir($local, 0755, true);
    @mkdir($docroot, 0755, true);

    [$rc, $out] = dp_sh('rsync -a --exclude=.git/ --exclude=_remote-backup/ --exclude=_remote-logs/ --exclude=.superpowers/ '
        . '--exclude=.claude/ --exclude=blog/ --exclude=content/comments/ --exclude=content/analytics.db* '
        . '--exclude=content/newsletter.db* --exclude=content/scores.db* --exclude=content/geoip.dat '
        . '--exclude=content/.sync-state --exclude=build.txt '
        . escapeshellarg($ROOT . '/') . ' ' . escapeshellarg($local . '/'));
    if ($rc !== 0) throw new RuntimeException("sandbox copy failed: {$out}");

    // never the real secrets or the real server: example config, fake target
    copy("{$local}/config.example.php", "{$local}/config.php");
    file_put_contents("{$local}/deploy.env", implode("\n", [
        'SSH_USER="tester"',
        'SSH_HOST="fake"',
        'SSH_PORT="22"',
        "REMOTE_DIR=\"{$docroot}\"",
        'GEOIP_MAX_AGE_DAYS="9999"',
        '',
    ]));
    @mkdir("{$local}/content/comments", 0755, true);
    file_put_contents("{$local}/content/analytics.db", 'LOCAL');

    // the server starts with a built site, as if deployed before
    [$rc, $out] = dp_sh('cd ' . escapeshellarg($local) . ' && php tools/build.php');
    if ($rc !== 0) throw new RuntimeException("sandbox build failed: {$out}");
    [$rc, $out] = dp_sh('rsync -a --exclude=content/analytics.db --exclude=deploy.env ' . escapeshellarg($local . '/') . ' ' . escapeshellarg($docroot . '/'));
    if ($rc !== 0) throw new RuntimeException("server seed failed: {$out}");
    file_put_contents("{$docroot}/content/analytics.db", 'REMOTE');

    return ['local' => $local, 'rhome' => $rhome, 'docroot' => $docroot];
}

/**
 * Run deploy.sh in a sandbox against its fake server.
 * @return array{rc:int, out:string}
 */
function dp_run(array $s, array $args, array $env = []): array
{
    $cmd = $args[0] ?? 'push';
    $isPush = !in_array($cmd, ['pull', 'data', 'status', 'help', 'smoke', 'install-remote', 'rollback', 'backup'], true);
    if ($isPush) $args[] = '--no-geoip';

    $envs = array_merge([
        'PATH'              => getenv('PATH'),
        'HOME'              => getenv('HOME'),
        'LANG'              => 'C.UTF-8',
        'DEPLOY_SSH'        => FAKE_SSH,
        'DEPLOY_SKIP_TESTS' => '1',
        'DEPLOY_SMOKE'      => '0',
        'FAKE_REMOTE_HOME'  => $s['rhome'],
    ], $env);

    // a broken harness must fail, not hang: no stdin, and a hard ceiling per run
    $line = 'timeout 120 bash deploy.sh ' . implode(' ', array_map('escapeshellarg', $args)) . ' 2>&1';
    $p = proc_open($line, [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w']], $pipes, $s['local'], $envs);
    $out = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $rc = proc_close($p);
    return ['rc' => $rc, 'out' => (string) $out];
}

/** every file under a directory with size and mtime, for before/after comparisons */
function dp_tree(string $dir): string
{
    [, $out] = dp_sh('cd ' . escapeshellarg($dir) . ' && find . -type f -printf "%P %s %T@\n" | sort');
    return $out;
}

/* ---------------- baseline: what deploy.sh does today ---------------- */
section('deploy baseline');

$s = dp_sandbox('baseline');
$before = dp_tree($s['docroot']);
$r = dp_run($s, ['-n']);
t_eq(0, $r['rc'], 'dry-run succeeds');
t_eq($before, dp_tree($s['docroot']), 'dry-run leaves the server untouched');
t_eq([], glob($s['local'] . '/_remote-backup/*.tar.gz') ?: [], 'dry-run writes no backup');

file_put_contents($s['local'] . '/robots.txt', "# changed locally\n", FILE_APPEND);
$r = dp_run($s, ['push']);
t_eq(0, $r['rc'], 'push to fake remote succeeds');
if ($r['rc'] !== 0) echo $r['out'], "\n";
t_contains('# changed locally', (string) @file_get_contents($s['docroot'] . '/robots.txt'), 'local change arrived on the server');
t_assert(is_file($s['docroot'] . '/blog/index.php'), 'built site is on the server');
t_eq('REMOTE', file_get_contents($s['docroot'] . '/content/analytics.db'), 'runtime db never overwritten');
t_eq(1, count(glob($s['local'] . '/_remote-backup/*.tar.gz') ?: []), 'push archived the server first');
t_eq(0, dp_run($s, ['status'])['rc'], 'status in step after push');

exit(t_summary());
