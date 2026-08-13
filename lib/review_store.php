<?php
/**
 * lib/review_store.php — the single owner of content/review/<slug>.json.
 *
 * These are the author's own margin notes on a draft, not reader comments, so they live apart from
 * content/comments/ and never render publicly. They exist to be pulled down and handed to an agent:
 * the round trip is write here, `deploy.sh --pull`, fix locally, push back with each note resolved.
 *
 * Anchoring is the whole problem. A note pinned to "paragraph 4, character 120" is wrong the moment
 * a sentence above it changes, and every one of these notes exists precisely because text is about
 * to change. So a note stores what the W3C annotation model calls a TextQuoteSelector: the quoted
 * text plus a little of what surrounded it. Re-anchoring searches for that pattern, degrading from
 * "same block, same neighbours" to "anywhere, same neighbours" to "anywhere, same quote", and marks
 * the note orphaned rather than guessing when the sentence it referred to is gone.
 */

declare(strict_types=1);

require_once __DIR__ . '/review_blocks.php';

const RV_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT;
const RV_SLUG_RE = '/^[a-z0-9-]{3,80}$/';
/** Characters of context kept either side of the quote. Enough to disambiguate a repeated phrase. */
const RV_CONTEXT = 40;

function rv_dir(): string
{
    return CONTENT_DIR . '/review';
}

function rv_path(string $slug): string
{
    return rv_dir() . '/' . $slug . '.json';
}

/** The stored review for one post, normalised ('comments' always a list). */
function rv_load(string $slug): array
{
    $empty = ['slug' => $slug, 'updated' => null, 'comments' => []];
    if (!preg_match(RV_SLUG_RE, $slug)) return $empty;
    $f = rv_path($slug);
    if (!is_file($f)) return $empty;
    $j = json_decode((string) file_get_contents($f), true);
    if (!is_array($j)) return $empty;
    $j['slug'] = $slug;
    $j['comments'] = array_values(array_filter($j['comments'] ?? [], 'is_array'));
    return $j;
}

/** Every stored review: slug => review. */
function rv_all(): array
{
    $out = [];
    foreach (glob(rv_dir() . '/*.json') ?: [] as $f) {
        $slug = basename($f, '.json');
        $r = rv_load($slug);
        if ($r['comments']) $out[$slug] = $r;
    }
    return $out;
}

/**
 * Atomically rewrite one review. $fn receives the comment list and returns the new one, or null to
 * abort. Same discipline as c_store_mutate: a file that no longer parses is left alone rather than
 * clobbered, and the replacement is renamed into place under the lock.
 */
function rv_mutate(string $slug, callable $fn): bool
{
    if (!preg_match(RV_SLUG_RE, $slug)) return false;
    if (!is_dir(rv_dir()) && !@mkdir(rv_dir(), 0755, true) && !is_dir(rv_dir())) return false;
    $path = rv_path($slug);
    $existed = is_file($path);
    $fh = fopen($path, 'c+');
    if (!$fh || !flock($fh, LOCK_EX)) { if ($fh) fclose($fh); return false; }

    $raw = stream_get_contents($fh);
    $doc = ['slug' => $slug, 'comments' => []];
    if ($raw !== false && trim($raw) !== '') {
        $j = json_decode($raw, true);
        if (!is_array($j)) { flock($fh, LOCK_UN); fclose($fh); return false; }
        $doc = $j + $doc;
        $doc['comments'] = array_values(array_filter($j['comments'] ?? [], 'is_array'));
    }

    $new = $fn($doc['comments']);
    $written = false;
    if (is_array($new)) {
        $doc['slug'] = $slug;
        $doc['comments'] = array_values($new);
        $doc['updated'] = date('c');
        $tmp = $path . '.tmp';
        if (file_put_contents($tmp, json_encode($doc, RV_FLAGS)) !== false && rename($tmp, $path)) $written = true;
        else @unlink($tmp);
    }

    flock($fh, LOCK_UN);
    fclose($fh);
    if (!$written && !$existed) @unlink($path);
    return $written;
}

/** A short sortable id: time-ordered so the export reads in the order notes were written. */
function rv_id(): string
{
    return 'c' . base_convert((string) time(), 10, 36) . bin2hex(random_bytes(3));
}

/**
 * Add one note. $sel is what the browser selected: block id, quote, and the surrounding text.
 * Returns the new note, or null when the selection is unusable.
 */
function rv_add(string $slug, array $sel, string $body): ?array
{
    $quote = trim((string) ($sel['quote'] ?? ''));
    $body = trim($body);
    if ($quote === '' || $body === '') return null;

    $note = [
        'id' => rv_id(),
        'created' => date('c'),
        'status' => 'open',
        'block' => (string) ($sel['block'] ?? ''),
        'section' => (string) ($sel['section'] ?? ''),
        'quote' => mb_substr($quote, 0, 600),
        'prefix' => mb_substr((string) ($sel['prefix'] ?? ''), -RV_CONTEXT),
        'suffix' => mb_substr((string) ($sel['suffix'] ?? ''), 0, RV_CONTEXT),
        'body' => mb_substr($body, 0, 4000),
        'resolution' => null,
    ];
    return rv_mutate($slug, fn(array $list) => [...$list, $note]) ? $note : null;
}

/** Patch one note in place (body, status, resolution). Returns whether anything was written. */
function rv_update(string $slug, string $id, array $patch): bool
{
    return rv_mutate($slug, function (array $list) use ($id, $patch) {
        $hit = false;
        foreach ($list as &$c) {
            if (($c['id'] ?? '') !== $id) continue;
            $hit = true;
            if (isset($patch['body'])) $c['body'] = mb_substr(trim((string) $patch['body']), 0, 4000);
            if (isset($patch['status']) && in_array($patch['status'], ['open', 'resolved'], true)) {
                $c['status'] = $patch['status'];
                if ($patch['status'] === 'open') $c['resolution'] = null;
            }
            if (array_key_exists('resolution', $patch)) $c['resolution'] = $patch['resolution'];
        }
        unset($c);
        return $hit ? $list : null;
    });
}

function rv_delete(string $slug, string $id): bool
{
    return rv_mutate($slug, function (array $list) use ($id) {
        $new = array_values(array_filter($list, fn($c) => ($c['id'] ?? '') !== $id));
        return count($new) === count($list) ? null : $new;
    });
}

/**
 * Locate every note in the post as it stands now.
 *
 * Each note gains `anchor`: ['block' => id, 'start' => int, 'end' => int] in the block's plain text,
 * or null when the text it quoted is gone. Nothing is written back: where a note currently sits is a
 * function of the file, and storing it would just be another thing that can go stale.
 */
function rv_reanchor(array $comments, array $parsed): array
{
    $plain = [];
    foreach ($parsed['blocks'] as $b) $plain[$b['id']] = rb_plain($b['md']);

    foreach ($comments as &$c) {
        $c['anchor'] = rv_locate($c, $plain);
    }
    return $comments;
}

/** The best position for one note, or null. Tried narrowest-first so a repeated phrase resolves. */
function rv_locate(array $c, array $plain): ?array
{
    $quote = (string) ($c['quote'] ?? '');
    if ($quote === '') return null;
    $prefix = (string) ($c['prefix'] ?? '');
    $suffix = (string) ($c['suffix'] ?? '');
    $home = (string) ($c['block'] ?? '');

    // 1. the block it was written against, with its neighbours intact
    // 2. the same block, quote alone
    // 3. any block, with neighbours
    // 4. any block, quote alone
    $order = [];
    if (isset($plain[$home])) $order[] = [$home, true];
    if (isset($plain[$home])) $order[] = [$home, false];
    foreach ($plain as $id => $_) { if ($id !== $home) $order[] = [$id, true]; }
    foreach ($plain as $id => $_) { if ($id !== $home) $order[] = [$id, false]; }

    foreach ($order as [$id, $withContext]) {
        $hay = $plain[$id];
        $needle = $withContext ? $prefix . $quote . $suffix : $quote;
        if ($needle === '') continue;
        $at = mb_strpos($hay, $needle);
        if ($at === false) continue;
        $start = $withContext ? $at + mb_strlen($prefix) : $at;
        return ['block' => $id, 'start' => $start, 'end' => $start + mb_strlen($quote)];
    }
    return null;
}

/** open / resolved / orphan counts, for the admin list. */
function rv_counts(string $slug, ?array $parsed = null): array
{
    $c = rv_load($slug)['comments'];
    $out = ['open' => 0, 'resolved' => 0, 'orphan' => 0, 'total' => count($c)];
    if ($parsed !== null) $c = rv_reanchor($c, $parsed);
    foreach ($c as $x) {
        if ($parsed !== null && ($x['anchor'] ?? null) === null) { $out['orphan']++; continue; }
        $out[($x['status'] ?? 'open') === 'resolved' ? 'resolved' : 'open']++;
    }
    return $out;
}
