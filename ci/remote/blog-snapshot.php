<?php
// ci/remote/blog-snapshot.php — run on the server by `blog-remote snapshot`.
// Writes a consistent copy of each runtime database of the docroot in $argv[1]
// into the directory $argv[2] and reports one line per file: ok|fail|skip.
$docroot = $argv[1] ?? '';
$dest = $argv[2] ?? '';
if ($docroot === '' || !is_dir($docroot) || !chdir($docroot)) {
    fwrite(STDERR, "snapshot docroot is not a directory: {$docroot}\n");
    exit(1);
}
if ($dest === '' || !is_dir($dest)) {
    fwrite(STDERR, "snapshot destination is not a directory: {$dest}\n");
    exit(1);
}
$open = static function (string $src): PDO {
    $db = new PDO('sqlite:' . $src, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    // the live site is writing to these; wait rather than fail on a busy lock
    $db->exec('PRAGMA busy_timeout=10000');
    return $db;
};
foreach (['analytics.db', 'newsletter.db', 'scores.db'] as $name) {
    $src = 'content/' . $name;
    if (!is_file($src)) { echo "skip {$name} — not on the server\n"; continue; }
    $out = $dest . '/' . $name;
    $method = '';
    try {
        $db = $open($src);
        $db->exec('VACUUM INTO ' . $db->quote($out));
        $method = 'vacuum';
    } catch (Throwable $e) {
        // VACUUM INTO needs SQLite 3.27+. Older hosts get the next best thing:
        // fold the WAL back into the main file, then copy it. A checkpoint is
        // routine maintenance the database does on its own anyway.
        try {
            $db = $open($src);
            $db->exec('PRAGMA wal_checkpoint(TRUNCATE)');
            $db = null;
            if (!copy($src, $out)) throw new RuntimeException('copy failed');
            $method = 'checkpoint+copy';
        } catch (Throwable $e2) {
            echo "fail {$name} — {$e2->getMessage()}\n";
            continue;
        }
    }
    printf("ok %s %d %s\n", $name, (int) filesize($out), $method);
}
