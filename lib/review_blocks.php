<?php
/**
 * lib/review_blocks.php — the addressable view of a post's markdown.
 *
 * The review editor lets one paragraph be rewritten at a time. That is only worth building if the
 * write is surgical: rewriting the whole file from a WYSIWYG document is what produces a diff full
 * of re-wrapped lines and re-quoted punctuation nobody asked for. So every rendered block keeps the
 * range of *source* lines it came from, and a save splices exactly that range. A one-sentence fix
 * lands as a one-line diff and the rest of the file stays byte-for-byte identical.
 *
 * The block grammar here mirrors md_to_html() in lib/markdown.php, and the meta stripping mirrors
 * parse_post_file() in lib/blog.php. Those two are the authority; if either grows a construct, it
 * has to be added here or the editor will silently treat it as a paragraph.
 */

declare(strict_types=1);

/** Block kinds a human may retype in place. Everything else renders read-only. */
const RB_EDITABLE = ['p', 'h2', 'quote', 'ul', 'ol'];

/**
 * Split a post file's raw lines into addressable blocks.
 *
 * Returns `['blocks' => [...], 'lines' => $lines]`, each block:
 *   id        stable within one parse: b0, b1, ...
 *   type      p | h2 | quote | ul | ol | code | figure | hr | table
 *   from,to   inclusive indices into $lines (the raw file, not the body)
 *   md        the source text of the block, newline-joined
 *   editable  whether the editor may rewrite it
 *
 * Lines that parse_post_file() strips (the H1, the italic subtitle, `**Key:** value` meta) belong
 * to no block, so the editor cannot reach them and the meta block stays the publishing surface.
 */
function rb_blocks(array $lines): array
{
    $meta = ['Status', 'Publish', 'Updated', 'Scores', 'Scored', 'Tags', 'Medium', 'DevTo'];
    $blocks = [];
    $open = null;                 // ['type'=>, 'from'=>, 'lines'=>[]]
    $title = false;
    $subtitle = false;
    $bodyStarted = false;
    $inFence = false;
    $fenceFrom = -1;
    $n = 0;

    $flush = function () use (&$open, &$blocks, &$n): void {
        if ($open === null) return;
        $blocks[] = [
            'id' => 'b' . $n++,
            'type' => $open['type'],
            'from' => $open['from'],
            'to' => $open['from'] + count($open['lines']) - 1,
            'md' => implode("\n", $open['lines']),
            'editable' => in_array($open['type'], RB_EDITABLE, true),
        ];
        $open = null;
    };
    /** Append to an open block of the same kind, or start a fresh one. */
    $push = function (string $type, int $i, string $line) use (&$open, $flush): void {
        if ($open !== null && $open['type'] !== $type) $flush();
        if ($open === null) $open = ['type' => $type, 'from' => $i, 'lines' => []];
        $open['lines'][] = $line;
    };

    foreach ($lines as $i => $line) {
        $t = rtrim($line);

        // A fence is one block from ``` to ```, and nothing inside it is interpreted.
        if (preg_match('/^```/', $t)) {
            if ($inFence) {
                $open['lines'][] = $line;
                $flush();
                $inFence = false;
            } else {
                $flush();
                $inFence = true;
                $fenceFrom = $i;
                $open = ['type' => 'code', 'from' => $fenceFrom, 'lines' => [$line]];
            }
            continue;
        }
        if ($inFence) { $open['lines'][] = $line; continue; }

        // --- the three line kinds that are not body at all
        if (!$title && preg_match('/^#\s+/', $t)) { $title = true; continue; }
        if ($title && !$subtitle && !$bodyStarted && preg_match('/^\*([^*]+)\*\s*$/', trim($t))) {
            $subtitle = true;
            continue;
        }
        if (preg_match('/^\*\*([A-Za-z]+):\*\*\s*(.+?)\s*$/', trim($t), $m) && in_array($m[1], $meta, true)) {
            $flush();
            continue;
        }
        if (!$title) continue;   // anything before the H1 is not body either

        if ($t === '') { $flush(); continue; }
        $bodyStarted = true;

        if (preg_match('/^!\[([^\]]*)\]\(([^)\s]+)\)\s*$/', $t)) { $flush(); $push('figure', $i, $t); $flush(); continue; }
        if (preg_match('/^#{2}\s+/', $t))                        { $flush(); $push('h2', $i, $t); $flush(); continue; }
        if (preg_match('/^(---+|\*\*\*+)$/', $t))                { $flush(); $push('hr', $i, $t); $flush(); continue; }
        if (str_starts_with($t, '|'))                            { $push('table', $i, $t); continue; }
        if (preg_match('/^>\s?/', $t))                           { $push('quote', $i, $t); continue; }
        if (preg_match('/^- /', $t))                             { $push('ul', $i, $t); continue; }
        if (preg_match('/^\d+\.\s+/', $t))                       { $push('ol', $i, $t); continue; }
        $push('p', $i, $t);
    }
    $flush();

    return ['blocks' => $blocks, 'lines' => $lines];
}

/** The blocks of a post file on disk, or null when it cannot be read. */
function rb_load(string $file): ?array
{
    if (!is_file($file)) return null;
    return rb_blocks(preg_split('/\r?\n/', (string) file_get_contents($file)));
}

/** A block by id, or null. */
function rb_find(array $parsed, string $id): ?array
{
    foreach ($parsed['blocks'] as $b) if ($b['id'] === $id) return $b;
    return null;
}

/**
 * Replace one block's source lines with $md, atomically.
 *
 * $expectHash guards against a mid-air collision: the browser sends the hash of the markdown it was
 * editing, and a mismatch means the file moved underneath it (a rebuild, a git pull, a second tab),
 * so the write is refused rather than silently clobbering. Returns an error string, or '' on success.
 */
function rb_write_block(string $file, string $id, string $md, string $expectHash): string
{
    $parsed = rb_load($file);
    if ($parsed === null) return 'post not found';
    $b = rb_find($parsed, $id);
    if ($b === null) return 'block not found';
    if (!$b['editable']) return 'block is not editable';
    if ($expectHash !== '' && !hash_equals(rb_hash($b['md']), $expectHash)) return 'stale';

    $md = rtrim(str_replace(["\r\n", "\r"], "\n", $md));
    if ($md === '') return 'refusing to empty a block';
    // A paragraph is one source line. Letting an edit smuggle in a blank line would split the block
    // in two and leave every id after it pointing somewhere else.
    if ($b['type'] !== 'ul' && $b['type'] !== 'ol' && $b['type'] !== 'quote' && str_contains($md, "\n")) {
        $md = preg_replace('/\s*\n\s*/', ' ', $md);
    }

    $lines = $parsed['lines'];
    array_splice($lines, $b['from'], $b['to'] - $b['from'] + 1, explode("\n", $md));

    $tmp = $file . '.tmp';
    if (file_put_contents($tmp, implode("\n", $lines)) === false) return 'write failed';
    return rename($tmp, $file) ? '' : 'rename failed';
}

/** Short content hash of a block's source, for the stale check. */
function rb_hash(string $md): string
{
    return substr(hash('sha256', $md), 0, 16);
}

/**
 * The plain text of a block as the reader sees it, which is what a comment anchor quotes.
 * Markers are removed, inline markup is unwrapped, and link text survives without its target.
 */
function rb_plain(string $md): string
{
    $s = preg_replace('/^(#{2,}\s+|>\s?|- |\d+\.\s+)/m', '', $md);
    $s = preg_replace('/!\[([^\]]*)\]\([^)\s]+\)/', '$1', (string) $s);
    $s = preg_replace('/\[([^\]]+)\]\([^)\s]+\)/', '$1', (string) $s);
    $s = preg_replace('/\*\*(.+?)\*\*/s', '$1', (string) $s);
    $s = preg_replace('/(?<![\w*])\*([^*\n]+)\*(?![\w*])/', '$1', (string) $s);
    $s = preg_replace('/`([^`]+)`/', '$1', (string) $s);
    return trim(preg_replace('/[ \t]*\n[ \t]*/', ' ', (string) $s) ?? '');
}
