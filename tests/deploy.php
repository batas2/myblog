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

// deploy.sh runs the test suite before it deploys. Run from here, that suite would
// include this file again, in a fresh sandbox, forever. dp_run() sets the marker
// (and DEPLOY_SKIP_TESTS); a suite that finds it stops here.
if (getenv('BFX_DEPLOY_TEST_NESTED') === '1') {
    echo "tests/deploy.php: refusing to run inside a deploy started by tests/deploy.php\n";
    exit(0);
}

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
 * $helper: install ci/remote/* into the server's ~/bin, as a set-up server has.
 * @return array{local:string, rhome:string, docroot:string}
 */
function dp_sandbox(string $name, bool $helper = true): array
{
    global $ROOT, $DP_TMP;
    $base = "{$DP_TMP}/{$name}";
    $local = "{$base}/local";
    $rhome = "{$base}/rhome";
    $docroot = "{$rhome}/domains/bfrackowiak.pl/public_html";
    @mkdir($local, 0755, true);
    @mkdir($docroot, 0755, true);

    // '/.git' without a slash: in a git worktree .git is a file pointing at the real repository,
    // and a sandbox that keeps it runs every git command against that repository
    [$rc, $out] = dp_sh('rsync -a --exclude=/.git --exclude=_remote-backup/ --exclude=_remote-logs/ --exclude=.superpowers/ '
        . '--exclude=.claude/ --exclude=blog/ --exclude=content/comments/ --exclude=content/analytics.db* '
        . '--exclude=content/newsletter.db* --exclude=content/scores.db* --exclude=content/geoip.dat '
        . '--exclude=content/.sync-state --exclude=build.txt '
        . escapeshellarg($ROOT . '/') . ' ' . escapeshellarg($local . '/'));
    if ($rc !== 0) throw new RuntimeException("sandbox copy failed: {$out}");
    if (file_exists("{$local}/.git")) throw new RuntimeException('sandbox still linked to the real repository');

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
    [$rc, $out] = dp_sh('rsync -a --exclude=content/analytics.db --exclude=deploy.env --exclude=/ci/ --exclude=/.githooks/ '
        . '--exclude=/.gitea/ --exclude=/content/outlines/ ' . escapeshellarg($local . '/') . ' ' . escapeshellarg($docroot . '/'));
    if ($rc !== 0) throw new RuntimeException("server seed failed: {$out}");
    file_put_contents("{$docroot}/content/analytics.db", 'REMOTE');
    if ($helper) dp_install_helper($rhome);

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
        'BFX_DEPLOY_TEST_NESTED' => '1',
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

/** copy ci/remote/* into a server home's ~/bin, the way install-remote does */
function dp_install_helper(string $rhome): void
{
    global $ROOT;
    @mkdir("{$rhome}/bin", 0755, true);
    foreach (glob("{$ROOT}/ci/remote/*") ?: [] as $f) {
        copy($f, "{$rhome}/bin/" . basename($f));
        chmod("{$rhome}/bin/" . basename($f), 0755);
    }
}

/**
 * Run ci/remote/blog-remote directly with a docroot of our choosing.
 * @return array{rc:int, out:string}
 */
function br_run(string $docroot, array $args, string $stdin = ''): array
{
    global $ROOT;
    $line = escapeshellarg("{$ROOT}/ci/remote/blog-remote") . ' ' . implode(' ', array_map('escapeshellarg', $args)) . ' 2>&1';
    $p = proc_open($line, [0 => ['pipe', 'r'], 1 => ['pipe', 'w']], $pipes, null,
        ['PATH' => getenv('PATH'), 'HOME' => getenv('HOME'), 'BLOG_DOCROOT' => $docroot]);
    fwrite($pipes[0], $stdin);
    fclose($pipes[0]);
    $out = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    return ['rc' => proc_close($p), 'out' => (string) $out];
}

/** a WAL-mode sqlite database with a hits table of $rows rows, written but not checkpointed */
function dp_wal_db(string $path, int $rows): PDO
{
    $db = new PDO('sqlite:' . $path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $db->exec('PRAGMA journal_mode=WAL');
    $db->exec('PRAGMA wal_autocheckpoint=0');
    $db->exec('CREATE TABLE hits (day TEXT, excl INTEGER DEFAULT 0)');
    for ($i = 0; $i < $rows; $i++) $db->exec("INSERT INTO hits (day) VALUES ('2026-11-2{$i}')");
    return $db;   // keep it open: the rows stay in the -wal file
}

/**
 * A second machine deploying to the same server: a copy of $s's working tree with
 * no sync marker of its own, the way a CI checkout starts.
 */
function dp_clone(array $s, string $name): array
{
    global $DP_TMP;
    $local = "{$DP_TMP}/{$name}/local";
    @mkdir($local, 0755, true);
    [$rc, $out] = dp_sh('rsync -a --exclude=/.git --exclude=content/.sync-state --exclude=_remote-backup/ --exclude=_remote-logs/ '
        . escapeshellarg($s['local'] . '/') . ' ' . escapeshellarg($local . '/'));
    if ($rc !== 0) throw new RuntimeException("clone failed: {$out}");
    return ['local' => $local, 'rhome' => $s['rhome'], 'docroot' => $s['docroot']];
}

/** the hash list deploy.sh records for a tree, as blog-remote prints it (header dropped) */
function dp_hashes(string $dir): string
{
    $r = br_run($dir, ['hashes']);
    return implode("\n", array_slice(explode("\n", $r['out']), 1));
}

/** on an unexpected failure, show the tail of what deploy.sh said */
function dp_show(array $r, int $expect = 0): void
{
    if ($r['rc'] === $expect) return;
    $lines = explode("\n", trim($r['out']));
    echo '      │ ' . implode("\n      │ ", array_slice($lines, -12)) . "\n";
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
dp_show($r);
t_contains('# changed locally', (string) @file_get_contents($s['docroot'] . '/robots.txt'), 'local change arrived on the server');
t_assert(is_file($s['docroot'] . '/blog/index.php'), 'built site is on the server');
t_eq('REMOTE', file_get_contents($s['docroot'] . '/content/analytics.db'), 'runtime db never overwritten');
t_eq(1, count(glob($s['local'] . '/_remote-backup/*.tar.gz') ?: []), 'push archived the server first');
t_eq(0, dp_run($s, ['status'])['rc'], 'status in step after push');

/* ---------------- blog-remote: the one program deploy.sh runs on the server ---------------- */
section('blog-remote');

$d = "{$DP_TMP}/br/public_html";
@mkdir("{$d}/content/posts", 0755, true);
@mkdir("{$d}/content/review", 0755, true);
file_put_contents("{$d}/content/posts/2026-01-01-a.md", "# A\n");
file_put_contents("{$d}/content/posts/2026-01-02-b.md", "# B\n");
file_put_contents("{$d}/content/review/b.json", '{"notes":[]}');
file_put_contents("{$d}/content/posts/ignored.txt", "not a post");

$r = br_run($d, ['version']);
t_eq("blog-remote 1 {$d}", trim($r['out']), 'version names itself and its docroot');

$r = br_run($d, ['hashes']);
t_eq(0, $r['rc'], 'hashes succeeds');
$lines = explode("\n", trim($r['out']));
t_eq("# blog-remote 1 {$d}", $lines[0], 'hashes starts with the header');
t_eq([
    md5_file("{$d}/content/posts/2026-01-01-a.md") . '  content/posts/2026-01-01-a.md',
    md5_file("{$d}/content/posts/2026-01-02-b.md") . '  content/posts/2026-01-02-b.md',
    md5_file("{$d}/content/review/b.json") . '  content/review/b.json',
], array_slice($lines, 1), 'one md5 line per post and review note, sorted, nothing else');

$r = br_run("{$DP_TMP}/br/nope", ['hashes']);
t_assert($r['rc'] !== 0, 'hashes fails when the docroot is missing');

$state = "abc  content/posts/2026-01-01-a.md\ndef  content/review/b.json\n";
$r = br_run($d, ['sync-state-write'], $state);
t_eq("ok", trim($r['out']), 'sync-state-write acknowledges');
$r = br_run($d, ['sync-state-read']);
t_eq("# blog-remote 1 {$d}\n" . $state, $r['out'], 'sync-state-read returns what was written, after the header');
t_eq([], glob("{$d}/content/.sync-state.*") ?: [], 'no temp file left behind');

@mkdir("{$d}/content/.logpull.1", 0755, true);
t_eq(2, br_run($d, ['cleanup', '/etc'])['rc'], 'cleanup refuses /etc');
t_eq(2, br_run($d, ['cleanup', "{$d}/content/posts"])['rc'], 'cleanup refuses the posts directory');
t_eq(2, br_run($d, ['cleanup', "{$d}/content/.logpull.1/../posts"])['rc'], 'cleanup refuses a traversal');
t_assert(is_dir("{$d}/content/posts") && is_file("{$d}/content/posts/2026-01-01-a.md"), 'refused cleanups deleted nothing');
t_eq(0, br_run($d, ['cleanup', "{$d}/content/.logpull.1"])['rc'], 'cleanup accepts a snapshot directory');
t_assert(!is_dir("{$d}/content/.logpull.1"), 'snapshot directory removed');

$live = dp_wal_db("{$d}/content/analytics.db", 3);
$r = br_run($d, ['snapshot']);
t_eq(0, $r['rc'], 'snapshot succeeds');
preg_match('/^dir (\S+)$/m', $r['out'], $m);
$snapDir = $m[1] ?? '';
t_assert(str_starts_with($snapDir, "{$d}/content/.logpull."), 'snapshot reports its directory inside content/');
t_contains('ok analytics.db', $r['out'], 'snapshot reports the analytics copy');
if (is_file($snapDir . '/analytics.db')) {
    $copy = new PDO('sqlite:' . $snapDir . '/analytics.db');
    t_eq(3, (int) $copy->query('SELECT COUNT(*) FROM hits')->fetchColumn(), 'copy holds the rows still in the WAL');
    t_eq('ok', $copy->query('PRAGMA integrity_check')->fetchColumn(), 'copy passes integrity_check');
    $copy = null;
} else {
    t_assert(false, 'snapshot wrote analytics.db');
}
$live = null;

t_eq(1, br_run($d, ['rm', '-rf', '/'])['rc'], 'unknown subcommand refused');

/* ---------------- deploy.sh talks to the server only through the helper ---------------- */
section('deploy via helper');

// a non-post change: with no sync marker yet, a changed post would (rightly) read as a server edit
$s = dp_sandbox('nohelper', false);
file_put_contents($s['local'] . '/robots.txt', "# changed locally\n", FILE_APPEND);
$remoteRobots = $s['docroot'] . '/robots.txt';
$beforeRobots = md5_file($remoteRobots);
$r = dp_run($s, ['push'], ['DEPLOY_CI' => '1']);
t_eq(1, $r['rc'], 'missing helper fails loudly');
t_contains('remote helper missing', $r['out'], 'the message names the helper');
t_eq($beforeRobots, md5_file($remoteRobots), 'nothing was pushed');

@mkdir($s['rhome'] . '/bin', 0755, true);
file_put_contents($s['rhome'] . '/bin/blog-remote', "#!/usr/bin/env bash\necho '# blog-remote 0 {$s['docroot']}'\n");
chmod($s['rhome'] . '/bin/blog-remote', 0755);
$r = dp_run($s, ['push'], ['DEPLOY_CI' => '1']);
t_eq(1, $r['rc'], 'outdated helper fails loudly');
t_contains('remote helper missing', $r['out'], 'the same message for an old helper');
t_eq($beforeRobots, md5_file($remoteRobots), 'still nothing pushed');

$r = dp_run($s, ['push']);
t_eq(0, $r['rc'], 'a laptop push installs the helper and deploys');
dp_show($r);
t_assert(is_file($s['rhome'] . '/bin/blog-remote') && str_contains(file_get_contents($s['rhome'] . '/bin/blog-remote'), 'BLOG_DOCROOT'), 'the real helper replaced the old one');
t_eq(md5_file($s['local'] . '/robots.txt'), md5_file($remoteRobots), 'the change arrived');

$s = dp_sandbox('data');
unlink($s['docroot'] . '/content/analytics.db');
$live = dp_wal_db($s['docroot'] . '/content/analytics.db', 3);
$r = dp_run($s, ['data']);
t_eq(0, $r['rc'], 'data succeeds');
dp_show($r);
$pulled = glob($s['local'] . '/_remote-logs/*/content/analytics.db') ?: [];
t_eq(1, count($pulled), 'data pulled the analytics snapshot');
if ($pulled) t_eq(3, (int) (new PDO('sqlite:' . $pulled[0]))->query('SELECT COUNT(*) FROM hits')->fetchColumn(), 'snapshot carries every row');
t_eq([], glob($s['docroot'] . '/content/.logpull.*') ?: [], 'no snapshot directory left on the server');
$live = null;

/* ---------------- the sync marker lives on the server ---------------- */
section('shared guard');

$s = dp_sandbox('guard');
$posts = glob($s['local'] . '/content/posts/*.md');
$postA = basename($posts[0]);
$postB = basename($posts[1]);
$remoteA = "{$s['docroot']}/content/posts/{$postA}";
$state = "{$s['docroot']}/content/.sync-state";

$r = dp_run($s, ['push']);
t_eq(0, $r['rc'], 'first push succeeds');
dp_show($r);
t_assert(is_file($state), 'push records state on server');
t_eq(dp_hashes($s['local']), (string) @file_get_contents($state), 'recorded state is the local post list');
t_assert(!is_file($s['local'] . '/content/.sync-state'), 'no local marker written');

// someone fixes a typo on the site through review.php
file_put_contents($remoteA, "\nedited on the site\n", FILE_APPEND);
$edited = md5_file($remoteA);

$r = dp_run($s, ['status']);
t_eq(3, $r['rc'], 'status exits 3 when server ahead');
$r = dp_run($s, ['push']);
t_eq(3, $r['rc'], 'server edit blocks older push');
t_contains('changed on the server', $r['out'], 'the refusal says why');
t_eq($edited, md5_file($remoteA), 'the edit on the server survived');
t_assert(!is_dir("{$s['docroot']}/content/.deploylock"), 'lock released after failed deploy');

$ci = dp_clone($s, 'guard-ci');
$r = dp_run($ci, ['push'], ['DEPLOY_CI' => '1']);
t_eq(3, $r['rc'], 'a CI clone without the edit is refused too');
copy($remoteA, "{$ci['local']}/content/posts/{$postA}");
$r = dp_run($ci, ['push'], ['DEPLOY_CI' => '1']);
t_eq(0, $r['rc'], 'CI clone with the server edit deploys');
dp_show($r);
t_eq($edited, md5_file($remoteA), 'the edit is still on the server');

$stateBefore = md5_file($state);
$r = dp_run($s, ['pull']);
t_eq(0, $r['rc'], 'pull succeeds');
t_eq($stateBefore, md5_file($state), 'pull does not write state');
t_eq($edited, md5_file("{$s['local']}/content/posts/{$postA}"), 'pull brought the edit home');
$r = dp_run($s, ['push']);
t_eq(0, $r['rc'], 'pull then push passes');
dp_show($r);

// a server deployed by the old deploy.sh: no marker there, the laptop holds one
unlink($state);
file_put_contents("{$s['local']}/content/.sync-state", dp_hashes($s['local']));
file_put_contents("{$s['local']}/content/posts/{$postB}", "\nedited on the laptop\n", FILE_APPEND);
$r = dp_run($s, ['push']);
t_eq(0, $r['rc'], 'migration uses local marker');
dp_show($r);
t_contains('using local sync marker (migration)', $r['out'], 'migration says so');
t_assert(is_file($state), 'migration leaves a marker on the server');
t_eq(md5_file("{$s['local']}/content/posts/{$postB}"), md5_file("{$s['docroot']}/content/posts/{$postB}"), 'the laptop edit went up');

t_eq(0, br_run($s['docroot'], ['lock', 'other'])['rc'], 'another deploy takes the lock');
file_put_contents($s['local'] . '/robots.txt', "# while locked\n", FILE_APPEND);
$robots = md5_file("{$s['docroot']}/robots.txt");
$r = dp_run($s, ['push']);
t_eq(4, $r['rc'], 'concurrent deploy exits 4');
t_eq($robots, md5_file("{$s['docroot']}/robots.txt"), 'a refused deploy changes nothing');
t_assert(is_dir("{$s['docroot']}/content/.deploylock"), 'and leaves the other deploy its lock');
t_eq(0, br_run($s['docroot'], ['unlock'])['rc'], 'the other deploy unlocks');
$r = dp_run($s, ['push']);
t_eq(0, $r['rc'], 'push passes after unlock');
dp_show($r);

// a lock left behind by a crashed deploy expires
br_run($s['docroot'], ['lock', 'crashed']);
file_put_contents("{$s['docroot']}/content/.deploylock/owner", 'crashed ' . (time() - 1000));
$r = dp_run($s, ['push']);
t_eq(0, $r['rc'], 'a lock older than 15 minutes is taken over');
t_assert(!is_dir("{$s['docroot']}/content/.deploylock"), 'and released afterwards');

/* ---------------- blog-gate: the forced command of the CI key ---------------- */
section('gate');

$g = "{$DP_TMP}/gate";
$groot = "{$g}/home/domains/bfrackowiak.pl";
@mkdir("{$groot}/public_html/content/posts", 0755, true);
$rec = "{$g}/recorded";
$recorder = "{$g}/recorder";
file_put_contents($recorder, "#!/usr/bin/env bash\nprintf '%s|%s\\n' \"\${SSH_ORIGINAL_COMMAND:-}\" \"\$*\" >> " . escapeshellarg($rec) . "\n");
chmod($recorder, 0755);

/** run blog-gate with $cmd as SSH_ORIGINAL_COMMAND; returns [rc, what the recorder saw, gate log] */
$gate = function (string $cmd) use ($ROOT, $g, $groot, $rec, $recorder): array {
    @unlink($rec);
    @unlink("{$g}/gate.log");
    $p = proc_open(escapeshellarg("{$ROOT}/ci/remote/blog-gate") . ' 2>&1', [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w']], $pipes, $g, [
        'PATH' => getenv('PATH'), 'HOME' => "{$g}/home", 'SSH_ORIGINAL_COMMAND' => $cmd,
        'BLOG_GATE_ROOT' => $groot, 'BLOG_GATE_RRSYNC' => $recorder, 'BLOG_REMOTE_BIN' => $recorder,
        'BLOG_GATE_LOG' => "{$g}/gate.log",
    ]);
    $out = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $rc = proc_close($p);
    return [$rc, trim((string) @file_get_contents($rec)), trim((string) @file_get_contents("{$g}/gate.log")), $out];
};

[$rc, $seen] = $gate("rsync --server -logDtprze.iLsfxCIvu . {$groot}/public_html/");
t_eq(0, $rc, 'rsync into the docroot passes');
t_eq("rsync --server -logDtprze.iLsfxCIvu . public_html/|{$groot}", $seen, 'absolute path rewritten relative to the root, rrsync pinned to the root');
[$rc, $seen] = $gate("rsync --server --sender -logDtprze.iLsfx . {$groot}/public_html/content/posts/");
t_eq("rsync --server --sender -logDtprze.iLsfx . public_html/content/posts/|{$groot}", $seen, 'reading posts passes');
[$rc, $seen] = $gate("rsync --server -logDtprze.iLsfxCIvu --delete . {$groot}/public_html/content/posts/");
t_eq(0, $rc, 'the posts mirror (--delete) passes');

foreach ([
    "rsync --server . {$g}/home/.ssh/"                         => 'rsync outside the root',
    "rsync --server . {$groot}/public_html/../../.ssh/"        => 'rsync with a traversal',
    "rsync --server . /etc/"                                   => 'rsync to /etc',
    'blog-remote hashes; rm -rf ~'                             => 'a command chained after the helper',
    'blog-remote $(id)'                                        => 'a substitution',
    'blog-remote hashes | sh'                                  => 'a pipe',
    'bash'                                                     => 'a shell',
    ''                                                         => 'an interactive login',
    'scp -t /tmp'                                              => 'scp',
    "blog-remote cleanup {$groot}/public_html/content/../x"    => 'a traversal in a helper argument',
    'blog-remote hashes extra args'                            => 'more than one helper argument',
    "rsync --server . {$groot}/public_html/\nbash"             => 'a newline',
] as $cmd => $what) {
    [$rc, $seen, $log, $out] = $gate($cmd);
    t_eq(1, $rc, "refused: {$what}");
    t_eq('', $seen, "nothing ran: {$what}");
    t_contains('blog-gate: command not allowed', $out, "says so: {$what}");
    t_assert($log !== '', "logged: {$what}");
}

[$rc, $seen] = $gate('~/bin/blog-remote hashes');
t_eq('|hashes', $seen, 'the helper runs with its subcommand');
[$rc, $seen] = $gate("blog-remote cleanup {$groot}/public_html/content/.logpull.1");
t_eq("|cleanup {$groot}/public_html/content/.logpull.1", $seen, 'the helper runs with its one argument');
[$rc, $seen] = $gate('~/bin/blog-remote lock ci');
t_eq('|lock ci', $seen, 'lock passes');

/* ---------------- a whole deploy through the gate ---------------- */
section('deploy through the gate');

$rrsync = trim((string) shell_exec('command -v rrsync 2>/dev/null'));
if ($rrsync === '') {
    echo "  skipped: rrsync missing\n";
} else {
    $s = dp_sandbox('gated');
    $env = ['DEPLOY_CI' => '1', 'FAKE_FORCED_COMMAND' => "{$ROOT}/ci/remote/blog-gate", 'BLOG_GATE_RRSYNC' => $rrsync,
            'DEPLOY_BACKUP_DIR' => "{$DP_TMP}/gated/backups"];
    file_put_contents($s['local'] . '/robots.txt', "# through the gate\n", FILE_APPEND);
    $r = dp_run($s, ['push'], $env);
    t_eq(0, $r['rc'], 'push through restricted key');
    dp_show($r);
    t_contains('# through the gate', (string) @file_get_contents($s['docroot'] . '/robots.txt'), 'the change arrived');
    t_eq('', trim((string) @file_get_contents($s['rhome'] . '/blog-gate.log')), 'the gate refused nothing');
    t_eq(1, count(glob("{$DP_TMP}/gated/backups/*.tar.gz") ?: []), 'CI backup goes to DEPLOY_BACKUP_DIR');
    t_eq([], glob($s['local'] . '/_remote-backup/*.tar.gz') ?: [], 'and not into the checkout');
    t_not_contains("\033[", $r['out'], 'no colour codes in CI output');
    t_assert(is_file($s['docroot'] . '/content/.sync-state'), 'the marker was written through the gate');
    t_assert(!is_dir($s['docroot'] . '/content/.deploylock'), 'the lock was released through the gate');

    $r = dp_run($s, ['status'], $env);
    t_eq(0, $r['rc'], 'status through the gate');
    // a real database: in CI mode a snapshot that fails fails the run
    unlink($s['docroot'] . '/content/analytics.db');
    $gatedDb = dp_wal_db($s['docroot'] . '/content/analytics.db', 1);
    $r = dp_run($s, ['data'], $env);
    t_eq(0, $r['rc'], 'data through the gate');
    $gatedDb = null;
    dp_show($r);
    $r = dp_run($s, ['install-remote'], $env);
    t_assert($r['rc'] !== 0, 'the CI key cannot install into ~/bin');
}

/* ---------------- build marker, warnings, where backups go, what never ships ---------------- */
section('push extras');

$s = dp_sandbox('extras');
$r = dp_run($s, ['push'], ['DEPLOY_SHA' => 'abc123']);
t_eq(0, $r['rc'], 'push with a given sha');
dp_show($r);
$build = (string) @file_get_contents($s['docroot'] . '/build.txt');
t_contains("sha=abc123\n", $build, 'build.txt carries the sha');
t_contains("by=laptop\n", $build, 'build.txt says who deployed');
t_assert((bool) preg_match('/^deployed=\d{4}-\d\d-\d\dT\d\d:\d\d:\d\dZ$/m', $build), 'build.txt carries the UTC time');

foreach (['.gitea/workflows/x.yml', 'ci/gate-patterns.txt', 'content/outlines/a.md', '_remote-logs/20261123/access.log', '.superpowers/sdd/x/brief.md'] as $f) {
    @mkdir(dirname($s['local'] . '/' . $f), 0755, true);
    file_put_contents($s['local'] . '/' . $f, "private\n");
}
$r = dp_run($s, ['push']);
t_eq(0, $r['rc'], 'push with private dirs present');
foreach (['.gitea', 'ci', '.githooks', 'content/outlines', '_remote-logs', '.superpowers'] as $d) {
    t_assert(!file_exists($s['docroot'] . '/' . $d), "private dirs never shipped: {$d}");
}
// a deploy from a git worktree: .git is a file there, and must stay home as well
file_put_contents($s['local'] . '/.git', "gitdir: /nowhere\n");
$r = dp_run($s, ['push']);
t_assert(!file_exists($s['docroot'] . '/.git'), 'a .git file never ships');
unlink($s['local'] . '/.git');

// a git checkout: warnings about what the next CI deploy would undo
if (file_exists($s['local'] . '/.git')) throw new RuntimeException('refusing to run git in a sandbox linked to the real repository');
// GIT_CEILING_DIRECTORIES: never let a git command climb out of the sandbox looking for a repository
$git = 'cd ' . escapeshellarg($s['local']) . ' && GIT_CEILING_DIRECTORIES=' . escapeshellarg(dirname($s['local'])) . ' git -c user.email=t@example.com -c user.name=t ';
dp_sh($git . 'init -q -b main && ' . $git . 'add -A && ' . $git . 'commit -qm base');
dp_sh('git init -q --bare ' . escapeshellarg("{$DP_TMP}/extras/origin.git"));
dp_sh($git . 'remote add origin ' . escapeshellarg("{$DP_TMP}/extras/origin.git") . ' && ' . $git . 'push -q origin main');
$r = dp_run($s, ['push']);
t_eq(0, $r['rc'], 'clean pushed checkout deploys');
t_not_contains('uncommitted changes', $r['out'], 'no warning for a clean tree');
t_not_contains('HEAD is not in main on origin', $r['out'], 'no warning when HEAD is in origin/main');
t_assert((bool) preg_match('/^sha=[0-9a-f]{40}$/m', (string) file_get_contents($s['docroot'] . '/build.txt')), 'without DEPLOY_SHA the marker carries HEAD');

@mkdir($s['local'] . '/drafts', 0755, true);
file_put_contents($s['local'] . '/drafts/konspekt-x.md', "an outline in progress\n");
$r = dp_run($s, ['push']);
t_not_contains('uncommitted changes', $r['out'], 'work in progress that never ships (drafts/) raises no warning');
t_assert((bool) preg_match('/^sha=[0-9a-f]{40}$/m', (string) file_get_contents($s['docroot'] . '/build.txt')), 'and does not mark the deploy dirty');

file_put_contents($s['local'] . '/robots.txt', "# dirty\n", FILE_APPEND);
$r = dp_run($s, ['push']);
t_eq(0, $r['rc'], 'dirty tree still deploys');
t_contains('uncommitted changes: the next CI deploy will replace this deploy', $r['out'], 'dirty tree warns');
t_assert((bool) preg_match('/^sha=[0-9a-f]{40}-dirty$/m', (string) file_get_contents($s['docroot'] . '/build.txt')), 'a dirty deploy is marked dirty');

dp_sh($git . 'commit -qam local-only');
$r = dp_run($s, ['push']);
t_contains('HEAD is not in main on origin: the next CI deploy will replace this deploy', $r['out'], 'unpushed HEAD warns');
$r = dp_run($s, ['push'], ['DEPLOY_CI' => '1']);
t_not_contains('the next CI deploy will replace', $r['out'], 'CI does not warn about itself');

// where the archive goes
$home = "{$DP_TMP}/extras/home-backups";
@mkdir($home, 0755, true);
file_put_contents($s['local'] . '/deploy.env', "HOME_BACKUP_TARGET=\"{$home}/\"\n", FILE_APPEND);
array_map('unlink', glob($s['local'] . '/_remote-backup/*.tar.gz') ?: []);
$r = dp_run($s, ['push']);
t_eq(0, $r['rc'], 'push with a home backup target');
t_eq(1, count(glob("{$home}/*.tar.gz") ?: []), 'backup goes home');
t_eq([], glob($s['local'] . '/_remote-backup/*.tar.gz') ?: [], 'and leaves no local copy');

file_put_contents($s['local'] . '/deploy.env', str_replace("{$home}/", 'nobody@unreachable.invalid:backups/', (string) file_get_contents($s['local'] . '/deploy.env')));
$r = dp_run($s, ['push']);
t_eq(0, $r['rc'], 'an unreachable home does not stop the deploy');
t_eq(1, count(glob($s['local'] . '/_remote-backup/*.tar.gz') ?: []), 'backup stays local when home is down');
t_contains('kept here', $r['out'], 'and says where it is');

/* ---------------- smoke checks from deploy.sh ---------------- */
section('deploy smoke');

$s = dp_sandbox('smoke');
$port = 8800 + (getmypid() % 90);
$srv = proc_open(['php', '-S', "127.0.0.1:{$port}", '-t', $s['docroot']], [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pp);
register_shutdown_function(fn() => @proc_terminate($srv));
for ($i = 0; $i < 50 && !@fsockopen('127.0.0.1', $port); $i++) usleep(100000);
$url = "http://127.0.0.1:{$port}";

$r = dp_run($s, ['smoke', $url, '--local']);
t_eq(0, $r['rc'], 'deploy.sh smoke passes against a healthy copy');
dp_show($r);
t_contains('smoke: all blocking checks passed', $r['out'], 'and says so');

$r = dp_run($s, ['push'], ['DEPLOY_SMOKE' => '1', 'SMOKE_URL' => $url, 'SMOKE_LOCAL' => '1', 'DEPLOY_SHA' => 'feed123']);
t_eq(0, $r['rc'], 'push ends with smoke checks of what it deployed');
dp_show($r);
t_assert((bool) preg_match('/PASS build_sha\s+live feed123, expected feed123/', $r['out']), 'the smoke checks compare the live commit');

$r = dp_run($s, ['push'], ['DEPLOY_SMOKE' => '1', 'SMOKE_URL' => 'http://127.0.0.1:1', 'SMOKE_LOCAL' => '1']);
t_eq(1, $r['rc'], 'a push whose smoke checks fail exits 1');
t_contains('deployed, but the smoke checks failed', $r['out'], 'and says what happened');
t_assert((bool) preg_match('#to undo it: \./deploy\.sh rollback \S+/_remote-backup/\d{8}-\d{6}\.tar\.gz#', $r['out']), 'and how to undo it');

$r = dp_run($s, ['status', '--sha=x']);
t_eq(1, $r['rc'], '--sha belongs to smoke only');
@proc_terminate($srv);

/* ---------------- CI compares by content, not by date ---------------- */
section('ci checksum');

$s = dp_sandbox('checksum');
dp_run($s, ['push']);
$ci = dp_clone($s, 'checksum-ci');
dp_sh('find ' . escapeshellarg($ci['local']) . ' -type f -exec touch {} +');   // a fresh checkout: every mtime is new
$r = dp_run($ci, ['push'], ['DEPLOY_CI' => '1']);
t_eq(0, $r['rc'], 'CI push from a fresh checkout');
dp_show($r);
preg_match('/Synced \((\d+) files transferred/', $r['out'], $m);
t_assert(isset($m[1]) && (int) $m[1] <= 3, 'a fresh checkout sends only what changed (got ' . ($m[1] ?? '?') . ' files)');

/* ---------------- rollback: put a pre-deploy archive back ---------------- */
section('rollback');

$s = dp_sandbox('rollback');
$posts = glob($s['local'] . '/content/posts/*.md');
$postA = basename($posts[0]);
$localA = "{$s['local']}/content/posts/{$postA}";
$remoteA = "{$s['docroot']}/content/posts/{$postA}";
$origA = (string) file_get_contents($localA);
$backups = "{$s['local']}/_remote-backup";
$remoteDb = "{$s['docroot']}/content/analytics.db";

$r = dp_run($s, ['push']);   // the first push records the sync marker
t_eq(0, $r['rc'], 'first push');
file_put_contents($localA, $origA . "\nrollback-marker v1\n");
$r = dp_run($s, ['push']);
t_eq(0, $r['rc'], 'push v1');
dp_show($r);
array_map('unlink', glob("{$backups}/*.tar.gz") ?: []);
file_put_contents($localA, $origA . "\nrollback-marker v2\n");
$r = dp_run($s, ['push']);
t_eq(0, $r['rc'], 'push v2');
dp_show($r);
t_contains('rollback-marker v2', (string) @file_get_contents($remoteA), 'v2 is live');
$archives = glob("{$backups}/*.tar.gz") ?: [];
t_eq(1, count($archives), 'push v2 archived the server as it was at v1');
$archive = $archives[0] ?? '/missing.tar.gz';

// the site keeps living after the archive: runtime data written since must survive the rollback
file_put_contents($remoteDb, 'REMOTE-NEWER');
@mkdir("{$s['docroot']}/content/comments", 0755, true);
file_put_contents("{$s['docroot']}/content/comments/after-archive.json", '[]');
sleep(1);   // the pre-rollback backup must not share the archive's timestamp

$r = dp_run($s, ['rollback']);
t_eq(2, $r['rc'], 'rollback without an archive is a usage error');
t_contains('rollback <archive.tar.gz>', $r['out'], 'and says how to call it');
$r = dp_run($s, ['rollback', "{$backups}/nope.tar.gz"]);
t_eq(2, $r['rc'], 'rollback of a missing file is a usage error');
$r = dp_run($s, ['push', $archive]);
t_eq(1, $r['rc'], 'an archive given to push is refused');
t_contains('applies to rollback', $r['out'], 'and named as such');

$r = dp_run($s, ['rollback', '-n', $archive]);
t_eq(0, $r['rc'], 'rollback dry run');
dp_show($r);
t_contains('rollback-marker v2', (string) @file_get_contents($remoteA), 'dry run leaves the server alone');
t_eq(1, count(glob("{$backups}/*.tar.gz") ?: []), 'dry run writes no backup');

[, $lockOut] = dp_sh('BLOG_DOCROOT=' . escapeshellarg($s['docroot']) . ' ' . escapeshellarg("{$ROOT}/ci/remote/blog-remote") . ' lock ci');
$r = dp_run($s, ['rollback', $archive]);
t_eq(4, $r['rc'], 'rollback waits for a deploy holding the lock');
t_contains('rollback-marker v2', (string) @file_get_contents($remoteA), 'and touches nothing');
dp_sh('BLOG_DOCROOT=' . escapeshellarg($s['docroot']) . ' ' . escapeshellarg("{$ROOT}/ci/remote/blog-remote") . ' unlock');

$r = dp_run($s, ['rollback', $archive]);
t_eq(0, $r['rc'], 'rollback succeeds');
dp_show($r);
$liveA = (string) @file_get_contents($remoteA);
t_contains('rollback-marker v1', $liveA, 'the post is back at v1');
t_not_contains('rollback-marker v2', $liveA, 'and v2 is gone');
t_eq('REMOTE-NEWER', (string) @file_get_contents($remoteDb), 'runtime data written after the archive stays');
t_assert(is_file("{$s['docroot']}/content/comments/after-archive.json"), 'a comment written after the archive stays');
t_assert(!is_dir("{$s['docroot']}/content/.deploylock"), 'the lock is released (the one inside the archive is not restored)');
t_eq(2, count(glob("{$backups}/*.tar.gz") ?: []), 'the server was backed up before the rollback');
t_contains('Rolled back', $r['out'], 'the summary says what happened');
$unpacked = "{$DP_TMP}/rollback-unpacked";
@mkdir($unpacked, 0755, true);
dp_sh('tar -xzf ' . escapeshellarg($archive) . ' -C ' . escapeshellarg($unpacked));
$top = glob("{$unpacked}/*", GLOB_ONLYDIR) ?: [''];
$marker = (string) @file_get_contents("{$s['docroot']}/content/.sync-state");
t_assert(str_contains($marker, "content/posts/{$postA}"), 'the sync marker lists the posts');
t_eq(dp_hashes($top[0]), $marker, 'the sync marker records the archive');
$r = dp_run($s, ['status']);
t_eq(0, $r['rc'], 'after a rollback the server is not ahead: the next push simply ships again');

$tiny = "{$DP_TMP}/rollback-tiny";
@mkdir("{$tiny}/20260101-000000/content", 0755, true);
foreach (['index.php', 'robots.txt', 'content/.htaccess'] as $f) file_put_contents("{$tiny}/20260101-000000/{$f}", "x\n");
dp_sh('tar -czf ' . escapeshellarg("{$tiny}.tar.gz") . ' -C ' . escapeshellarg($tiny) . ' 20260101-000000');
$r = dp_run($s, ['rollback', "{$tiny}.tar.gz"]);
t_eq(1, $r['rc'], 'an archive with 3 files is refused');
t_contains('looks empty', $r['out'], 'as one that looks empty');
t_contains('rollback-marker v1', (string) @file_get_contents($remoteA), 'and the server is untouched');

/* ---------------- nightly: data in CI mode, DEPLOY_LOGS_DIR, backup ---------------- */
section('nightly');

$s = dp_sandbox('nightly');   // the server's analytics.db is the text 'REMOTE': its snapshot fails
$logs = "{$DP_TMP}/nightly-data";
$r = dp_run($s, ['data'], ['DEPLOY_LOGS_DIR' => $logs]);
t_eq(0, $r['rc'], 'a failed snapshot from the laptop: a warning and rc 0, as before');
t_contains('analytics.db', $r['out'], 'the warning names the database');
$r = dp_run($s, ['data'], ['DEPLOY_CI' => '1', 'DEPLOY_LOGS_DIR' => $logs]);
t_eq(1, $r['rc'], 'data fails in CI mode on snapshot failure');
t_contains('analytics.db', $r['out'], 'naming the database');
t_eq([], glob($s['docroot'] . '/content/.logpull.*') ?: [], 'and leaves no snapshot directory on the server');

unlink($s['docroot'] . '/content/analytics.db');
$live = dp_wal_db($s['docroot'] . '/content/analytics.db', 2);
$r = dp_run($s, ['data'], ['DEPLOY_CI' => '1', 'DEPLOY_LOGS_DIR' => $logs]);
t_eq(0, $r['rc'], 'data in CI mode with healthy databases');
dp_show($r);
t_eq(1, count(glob("{$logs}/*/content/analytics.db") ?: []), 'data honours DEPLOY_LOGS_DIR');
t_eq([], glob($s['local'] . '/_remote-logs/*') ?: [], 'and writes nothing into the checkout');

$before = dp_tree($s['docroot']);
$weekly = "{$DP_TMP}/nightly-weekly";
$r = dp_run($s, ['backup'], ['DEPLOY_CI' => '1', 'DEPLOY_BACKUP_DIR' => $weekly]);
t_eq(0, $r['rc'], 'backup succeeds');
dp_show($r);
$arch = glob("{$weekly}/*.tar.gz") ?: [];
t_eq(1, count($arch), 'backup creates an archive');
[, $listing] = dp_sh('tar -tzf ' . escapeshellarg($arch[0] ?? '/nope') . ' | grep -c "/index.php$"');
t_assert((int) $listing > 0, 'of the whole docroot');
t_eq($before, dp_tree($s['docroot']), 'backup creates an archive without deploying: the server is unchanged');
t_assert(!is_dir($s['docroot'] . '/content/.deploylock'), 'and the lock is released');
dp_sh('BLOG_DOCROOT=' . escapeshellarg($s['docroot']) . ' ' . escapeshellarg("{$ROOT}/ci/remote/blog-remote") . ' lock laptop');
$r = dp_run($s, ['backup'], ['DEPLOY_CI' => '1', 'DEPLOY_BACKUP_DIR' => $weekly]);
t_eq(4, $r['rc'], 'backup waits for a deploy holding the lock');
dp_sh('BLOG_DOCROOT=' . escapeshellarg($s['docroot']) . ' ' . escapeshellarg("{$ROOT}/ci/remote/blog-remote") . ' unlock');
$live = null;

exit(t_summary());
