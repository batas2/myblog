<?php
/**
 * tools/review_export.php — turn stored margin notes into a brief an agent can work from.
 *
 *   php tools/review_export.php                 write drafts/review/<slug>.review.md for every post
 *                                               that has notes, and print what is open
 *   php tools/review_export.php <slug>          just that post
 *   php tools/review_export.php --resolve <slug> <id> "what I changed"
 *                                               close one note, with a line saying what was done
 *   php tools/review_export.php --list          one line per post, no files written
 *
 * The JSON under content/review/ stays the source of truth: it holds the anchors, and it is what
 * travels back to the server so the browser shows the same threads with the same history. The .md
 * written here is a projection for reading, and regenerating it is free.
 */

declare(strict_types=1);

require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/markdown.php';
require_once __DIR__ . '/../lib/blog.php';
require_once __DIR__ . '/../lib/review_store.php';

const RX_OUT = APP_ROOT . '/drafts/review';

$argv = $_SERVER['argv'];
array_shift($argv);
$mode = 'export';
$only = null;

if (($argv[0] ?? '') === '--resolve') {
    [$slug, $id, $note] = [$argv[1] ?? '', $argv[2] ?? '', $argv[3] ?? ''];
    if ($slug === '' || $id === '') {
        fwrite(STDERR, "usage: --resolve <slug> <comment-id> \"what changed\"\n");
        exit(2);
    }
    $ok = rv_update($slug, $id, [
        'status' => 'resolved',
        'resolution' => ['by' => 'claude', 'note' => $note, 'at' => date('c')],
    ]);
    if (!$ok) { fwrite(STDERR, "no such note: {$slug} {$id}\n"); exit(1); }
    echo "resolved {$id}\n";
    rx_write($slug);
    exit(0);
}
if (($argv[0] ?? '') === '--list') $mode = 'list';
elseif (($argv[0] ?? '') !== '' && !str_starts_with($argv[0], '--')) $only = $argv[0];

$reviews = rv_all();
if ($only !== null) $reviews = isset($reviews[$only]) ? [$only => $reviews[$only]] : [];
if (!$reviews) { echo "no review notes stored\n"; exit(0); }

$totalOpen = 0;
foreach ($reviews as $slug => $_) {
    $r = rx_write($slug, $mode === 'list');
    $totalOpen += $r['open'];
    printf("%-46s %2d open  %2d resolved%s\n", $slug, $r['open'], $r['resolved'],
        $r['orphan'] ? "  {$r['orphan']} orphaned" : '');
}
echo $mode === 'list' ? "\n" : "\nwrote " . count($reviews) . " file(s) to drafts/review/\n";
if ($totalOpen) echo "{$totalOpen} note(s) waiting. Read the .review.md next to each post.\n";

/**
 * Write one post's brief. Returns the counts.
 *
 * Every note quotes the sentence it is attached to, so the agent can find the text without needing
 * the anchor arithmetic, and carries its id so it can close the note when it is done.
 */
function rx_write(string $slug, bool $dry = false): array
{
    $file = post_source_file($slug);
    $parsed = $file !== null ? rb_load($file) : ['blocks' => []];
    $post = $file !== null ? parse_post_file($file) : null;
    $comments = rv_reanchor(rv_load($slug)['comments'], $parsed);

    $counts = ['open' => 0, 'resolved' => 0, 'orphan' => 0];
    foreach ($comments as $c) {
        if (($c['anchor'] ?? null) === null) $counts['orphan']++;
        elseif (($c['status'] ?? 'open') === 'resolved') $counts['resolved']++;
        else $counts['open']++;
    }
    if ($dry) return $counts;

    $rel = $file !== null ? ltrim(str_replace(APP_ROOT, '', $file), '/') : '(post file missing)';
    $out = [];
    $out[] = '# Review notes: ' . ($post['title'] ?? $slug);
    $out[] = '';
    $out[] = "Post: `{$rel}`";
    $out[] = "Notes: `content/review/{$slug}.json` (the anchors live there, this file is a projection)";
    $out[] = '';
    $out[] = sprintf('%d open · %d resolved%s', $counts['open'], $counts['resolved'],
        $counts['orphan'] ? " · {$counts['orphan']} orphaned" : '');
    $out[] = '';
    $out[] = 'Each note quotes the sentence it is pinned to. Edit the post, then close the note:';
    $out[] = '';
    $out[] = '```';
    $out[] = "php tools/review_export.php --resolve {$slug} <id> \"what you changed\"";
    $out[] = '```';
    $out[] = '';
    $out[] = 'Leave a note open if you disagree with it, and say why in your reply to the author.';
    $out[] = 'An orphaned note quotes text that no longer exists; read it, then delete or resolve it.';
    $out[] = '';

    $i = 0;
    foreach ($comments as $c) {
        $i++;
        $state = ($c['anchor'] ?? null) === null ? 'ORPHANED' : strtoupper($c['status'] ?? 'open');
        $section = ($c['section'] ?? '') !== '' ? ' · §' . $c['section'] : '';
        $out[] = '---';
        $out[] = '';
        $out[] = "## [{$i}] {$state}{$section}";
        $out[] = '';
        $out[] = '`' . $c['id'] . '`';
        $out[] = '';
        foreach (explode("\n", wordwrap($c['quote'], 92, "\n", false)) as $l) $out[] = '> ' . $l;
        $out[] = '';
        $out[] = trim((string) $c['body']);
        $out[] = '';
        if (!empty($c['resolution']['note'])) {
            $out[] = '✓ ' . ($c['resolution']['by'] ?? 'resolved') . ': ' . $c['resolution']['note'];
            $out[] = '';
        }
    }

    if (!is_dir(RX_OUT) && !@mkdir(RX_OUT, 0755, true) && !is_dir(RX_OUT)) {
        fwrite(STDERR, "cannot create " . RX_OUT . "\n");
        exit(1);
    }
    file_put_contents(RX_OUT . "/{$slug}.review.md", implode("\n", $out) . "\n");
    return $counts;
}
