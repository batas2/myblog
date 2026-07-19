<?php
/**
 * tools/newsletter_send.php — mail newly published essays to the confirmed list.
 *
 * Run it on the server right after the generator, in the same cron:
 *   [star]/15 * * * * cd ~/domains/bfrackowiak.pl/public_html && php tools/build.php && php tools/newsletter_send.php >> content/newsletter.log 2>&1
 *
 * Idempotent: the sends ledger guarantees one email per essay, ever.
 * The first run on an empty ledger seeds all published essays without
 * mailing anything (the archive is never blasted at the list).
 *
 * Flags: --dry-run  print the plan, change nothing, send nothing.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require __DIR__ . '/../lib/config.php';
require __DIR__ . '/../lib/helpers.php';
require __DIR__ . '/../lib/markdown.php';
require __DIR__ . '/../lib/blog.php';
require __DIR__ . '/../lib/newsletter.php';

$dry = in_array('--dry-run', $argv, true);
$posts = load_posts();   // published only

foreach (nl_send_new($posts, $dry) as $line) {
    echo '[' . date('Y-m-d H:i:s') . '] ' . $line . "\n";
}

$c = nl_counts(nl_db());
echo '[' . date('Y-m-d H:i:s') . '] list: ' . $c['confirmed'] . ' confirmed, ' . $c['pending'] . " pending\n";
