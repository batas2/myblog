<?php
/**
 * lib/comment_store.php — the single owner of comment JSON files.
 * Every reader (thread render, admin dashboard) and writer (visitor POST,
 * admin delete) goes through here, so path rules, JSON flags, and the
 * flock discipline can never drift apart.
 */

declare(strict_types=1);

const C_STORE_FLAGS = JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT;
const C_STORE_SLUG_RE = '/^[a-z0-9-]{3,80}$/';

function c_store_dir(): string
{
    return CONTENT_DIR . '/comments';
}

function c_store_path(string $slug): string
{
    return c_store_dir() . '/' . $slug . '.json';
}

/** Comments for one post, oldest first ([] when none). */
function c_store_load(string $slug): array
{
    if (!preg_match(C_STORE_SLUG_RE, $slug)) return [];
    $f = c_store_path($slug);
    if (!is_file($f)) return [];
    $j = json_decode((string) file_get_contents($f), true);
    return is_array($j) ? $j : [];
}

/** Every stored thread: slug => comment list. */
function c_store_all(): array
{
    $out = [];
    foreach (glob(c_store_dir() . '/*.json') ?: [] as $f) {
        $list = json_decode((string) file_get_contents($f), true);
        if (is_array($list)) $out[basename($f, '.json')] = $list;
    }
    return $out;
}

/**
 * Atomically rewrite one thread: $fn receives the current list and returns
 * the new one, or null to abort without writing. Returns whether a write
 * happened. All mutations (append, moderate) run under the same LOCK_EX.
 *
 * Crash-safety: a thread that exists but no longer parses as JSON aborts the
 * mutation (the damaged bytes stay on disk for forensics — appending "just
 * the new comment" would silently erase the whole history). The new list is
 * written to a temp file and rename()d over the original while the lock is
 * held, so a crash mid-write can never leave a truncated thread behind.
 */
function c_store_mutate(string $slug, callable $fn): bool
{
    if (!preg_match(C_STORE_SLUG_RE, $slug)) return false;
    if (!is_dir(c_store_dir())) @mkdir(c_store_dir(), 0755, true);
    $path = c_store_path($slug);
    $existed = is_file($path);
    $fh = fopen($path, 'c+');
    if (!$fh || !flock($fh, LOCK_EX)) { if ($fh) fclose($fh); return false; }

    $raw = stream_get_contents($fh);
    $list = [];
    if ($raw !== false && trim($raw) !== '') {
        $list = json_decode($raw, true);
        if (!is_array($list)) {                    // corrupt thread: refuse to clobber it
            flock($fh, LOCK_UN);
            fclose($fh);
            return false;
        }
    }

    $new = $fn($list);
    $written = false;
    if (is_array($new)) {
        $tmp = $path . '.tmp';
        if (file_put_contents($tmp, json_encode($new, C_STORE_FLAGS)) !== false && rename($tmp, $path)) {
            $written = true;
        } else {
            @unlink($tmp);
        }
    }

    flock($fh, LOCK_UN);
    fclose($fh);
    if (!$written && !$existed) @unlink($path);    // an aborted mutate must not leave an empty thread file
    return $written;
}
