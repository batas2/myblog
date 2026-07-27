<?php
/**
 * lib/blog.php — the content model.
 * Loading and parsing posts, URLs, tags, series, hero-image metadata, and the
 * plain-text renderings (per-post markdown mirrors, llms-full.txt).
 */

declare(strict_types=1);

require_once __DIR__ . '/scores.php';

/** Posts that belong to a named series: slug => [series name, part]. */
const SERIES = [
    'from-signals-to-blips'      => ['Tools That Scale', 1],
    'probabilistic-metrics'      => ['Tools That Scale', 2],
    'prompt-driven-architecture' => ['Tools That Scale', 3],
];

/* ---------------- urls & labels ---------------- */

/** Site-relative post path: /blog/<slug>/ */
function post_path(string $slug): string
{
    return '/blog/' . $slug . '/';
}

/** Absolute post URL. */
function post_url(string $slug): string
{
    return cfg('site_url') . post_path($slug);
}

/** kebab-case tag slug used in URLs and hashtags */
function tag_slug(string $tag): string
{
    $s = strtolower(trim($tag));
    $s = str_replace('&', 'and', $s);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    return trim($s, '-');
}

/** Site-relative tag archive path: /blog/tag/<slug>/ */
function tag_path(string $tag): string
{
    return '/blog/tag/' . tag_slug($tag) . '/';
}

/** clickable #hashtag link to the tag archive */
function tag_link(string $tag): string
{
    return '<a class="tag" href="' . tag_path($tag) . '" title="' . e($tag) . '">#' . tag_slug($tag) . '</a>';
}

/** " · Series Name, part N" or "" when the post is not part of a series */
function series_suffix(string $slug): string
{
    if (!isset(SERIES[$slug])) return '';
    [$name, $part] = SERIES[$slug];
    return ' · ' . e($name) . ', part ' . $part;
}


/* display dates come from the publish moment, not the filename — a post
   published early or late shows the day it actually went live */
function nice_ts(int $ts): string { return date('M j, Y', $ts); }
function log_ts(int $ts): string { return date('Y.m.d', $ts); }

/* ---------------- loading & parsing ---------------- */

/**
 * Publish moment of a post as a unix timestamp, in the site timezone.
 * $explicit is the optional `**Publish:** YYYY-MM-DD HH:MM` value; without it
 * the post goes live on its filename date at 09:00.
 */
function post_publish_ts(string $date, ?string $explicit = null): int
{
    $tz = site_tz();
    if ($explicit !== null && $explicit !== '') {
        try {
            return (new DateTimeImmutable($explicit, $tz))->getTimestamp();
        } catch (Exception) {
            // fall through to the filename date
        }
    }
    return (new DateTimeImmutable($date . ' 09:00', $tz))->getTimestamp();
}

/**
 * Parse one content/posts/YYYY-MM-DD-slug.md file into the post array,
 * or null when the filename doesn't match the convention.
 *
 * File format: `# Title`, first standalone *italic* line = subtitle,
 * optional `**Medium:** <url>`, `**Status:** draft`, `**Publish:** YYYY-MM-DD HH:MM`
 * lines, trailing `**Tags:** a, b, c` line.
 *
 * Status is derived: 'draft' when declared, 'scheduled' while the publish
 * moment is in the future, 'published' otherwise.
 */
function parse_post_file(string $file): ?array
{
    $name = basename($file, '.md');
    // the filename slug must satisfy the same contract every runtime consumer
    // enforces (comments, admin, store) — a looser slug would build a page
    // that can never be commented on or managed
    if (!preg_match('/^(\d{4}-\d{2}-\d{2})-([a-z0-9-]{3,80})$/', $name, $m)) {
        if (preg_match('/^\d{4}-\d{2}-\d{2}-/', $name)) {
            blog_warn("skipped {$name}.md: slug must match [a-z0-9-]{3,80}");
        }
        return null;
    }
    [, $date, $slug] = $m;

    $lines = preg_split('/\r?\n/', (string) file_get_contents($file));
    $title = $subtitle = '';
    $medium = $publishRaw = $updatedRaw = null;
    $scoresRaw = $scoredRaw = '';
    $draft = false;
    $tags = [];
    $body = [];
    $inFence = false;

    // meta lines are stripped from the body together with any hr that only separated them
    $dropTrailingRule = function () use (&$body): void {
        while ($body && in_array(trim(end($body)), ['', '---'], true)) array_pop($body);
    };

    foreach ($lines as $line) {
        // meta markers inside code fences are literal examples, never metadata
        if (preg_match('/^```/', $line)) {
            $inFence = !$inFence;
            if ($title !== '') $body[] = $line;
            continue;
        }
        if ($inFence) {
            if ($title !== '') $body[] = $line;
            continue;
        }
        if ($title === '' && preg_match('/^#\s+(.*)$/', $line, $mm)) { $title = trim($mm[1]); continue; }
        $bodyStarted = (bool) array_filter($body, fn($l) => trim($l) !== '');
        if ($title !== '' && $subtitle === '' && !$bodyStarted
            && preg_match('/^\*([^*]+)\*\s*$/', trim($line), $mm)) {
            $subtitle = trim($mm[1]);
            continue;
        }
        if (preg_match('/^\*\*Medium:\*\*\s*(\S+)\s*$/', trim($line), $mm)) {
            $medium = $mm[1];
            $dropTrailingRule();
            continue;
        }
        if (preg_match('/^\*\*Status:\*\*\s*([a-zA-Z]+)\s*$/', trim($line), $mm)) {
            $draft = strtolower($mm[1]) === 'draft';
            $dropTrailingRule();
            continue;
        }
        if (preg_match('/^\*\*Publish:\*\*\s*(.+?)\s*$/', trim($line), $mm)) {
            $publishRaw = $mm[1];
            $dropTrailingRule();
            continue;
        }
        if (preg_match('/^\*\*Updated:\*\*\s*(.+?)\s*$/', trim($line), $mm)) {
            $updatedRaw = $mm[1];
            $dropTrailingRule();
            continue;
        }
        if (preg_match('/^\*\*Scores:\*\*\s*(.+?)\s*$/', trim($line), $mm)) {
            $scoresRaw = $mm[1];
            $dropTrailingRule();
            continue;
        }
        if (preg_match('/^\*\*Scored:\*\*\s*(.+?)\s*$/', trim($line), $mm)) {
            $scoredRaw = $mm[1];
            $dropTrailingRule();
            continue;
        }
        if (preg_match('/^\*\*Tags:\*\*\s*(.+)$/', trim($line), $mm)) {
            $tags = array_map('trim', explode(',', $mm[1]));
            $dropTrailingRule();
            continue;
        }
        if ($title !== '') $body[] = $line;
    }

    if ($inFence) {
        blog_warn("{$name}.md: unclosed code fence — any metadata after it (Status/Publish/Tags) is treated as fenced text and ignored");
    }

    $plain = trim(preg_replace('/\s+/', ' ', strip_tags(md_to_html($body))));
    $words = str_word_count($plain);

    $publishTs = post_publish_ts($date, $publishRaw);
    $status = $draft ? 'draft' : ($publishTs > time() ? 'scheduled' : 'published');

    // optional **Updated:** meta — a real revision signal (schema dateModified,
    // article:modified_time, sitemap lastmod). Unparsable or before publish → ignored.
    $updatedTs = null;
    if ($updatedRaw !== null) {
        try {
            $updatedTs = (new DateTimeImmutable($updatedRaw, site_tz()))->getTimestamp();
            if ($updatedTs < $publishTs) $updatedTs = null;
        } catch (Exception) {
            blog_warn("{$name}.md: unparsable **Updated:** '{$updatedRaw}' — ignored");
        }
    }

    // editorial scores (docs/SCORING-RUBRIC.md). A partial set is a typo, not a
    // measurement, so it is reported rather than quietly averaged over.
    $scores = sc_parse_scores($scoresRaw);
    if ($scoresRaw !== '' && !sc_complete($scores)) {
        blog_warn("{$name}.md: incomplete **Scores:** line — " . count($scores) . ' of ' . count(SC_AXES) . ' axes parsed');
    }

    return [
        'slug'        => $slug,
        'date'        => $date,
        'title'       => $title,
        'subtitle'    => $subtitle,
        'tags'        => $tags,
        'medium'      => $medium,
        'status'      => $status,
        'publish_ts'  => $publishTs,
        'publish_iso' => (new DateTimeImmutable('@' . $publishTs))->setTimezone(site_tz())->format('c'),
        'updated_ts'  => $updatedTs,
        'updated_iso' => $updatedTs !== null ? (new DateTimeImmutable('@' . $updatedTs))->setTimezone(site_tz())->format('c') : null,
        'scores'      => $scores,
        'scored'      => sc_parse_scored($scoredRaw),
        'words'       => $words,
        'minutes'     => max(1, (int) ceil($words / 215)),
        'html'        => md_to_html($body),
        'md'          => trim(implode("\n", $body)),
    ];
}

/** Warn on stderr under CLI, error_log otherwise (web SAPI has no STDERR). */
function blog_warn(string $msg): void
{
    if (defined('STDERR')) {
        fwrite(STDERR, $msg . "\n");
    } else {
        error_log($msg);
    }
}

/**
 * Posts from content/posts/, newest first.
 * By default only published posts (the public site); $withUnpublished adds
 * drafts and scheduled posts (build reporting, admin).
 */
function load_posts(bool $withUnpublished = false): array
{
    $posts = [];
    foreach (glob(CONTENT_DIR . '/posts/*.md') as $file) {
        $post = parse_post_file($file);
        if ($post === null) {
            blog_warn('skip (bad name): ' . basename($file, '.md'));
            continue;
        }
        if (!$withUnpublished && $post['status'] !== 'published') continue;
        $posts[] = $post;
    }
    usort($posts, fn($a, $b) => $b['publish_ts'] <=> $a['publish_ts']);
    return $posts;
}

/** Source .md path for a slug, or null. */
function post_source_file(string $slug): ?string
{
    // callers validate, but never let a malformed slug reach the glob
    if (!preg_match('/^[a-z0-9-]{3,80}$/', $slug)) return null;
    // the glob is a suffix match ("service" also hits "…-not-a-service.md"),
    // so verify the parsed slug before trusting a file
    foreach (glob(CONTENT_DIR . '/posts/*-' . $slug . '.md') as $file) {
        $post = parse_post_file($file);
        if ($post !== null && $post['slug'] === $slug) return $file;
    }
    return null;
}

/** True when the slug belongs to a currently published post (comments gate). */
function post_is_public(string $slug): bool
{
    $file = post_source_file($slug);
    if ($file === null) return false;
    $post = parse_post_file($file);
    return $post !== null && $post['status'] === 'published';
}

/**
 * Replace a block of meta lines in a post source, atomically.
 *
 * Lines matching $dropRe are removed first (never inside code fences, where an
 * identical-looking line is a literal example), then $newLines are inserted just
 * above the trailing **Tags:** line, or appended at EOF when there is none.
 * Everything else in the file stays byte-identical.
 *
 * Shared by the publishing pipeline (Status/Publish) and the scoring pass
 * (Scores/Scored) so both kinds of meta are written by one tested routine.
 */
function post_meta_write(string $file, string $dropRe, array $newLines): bool
{
    if (!is_file($file) || !$newLines) return false;

    $lines = preg_split('/\r?\n/', (string) file_get_contents($file));
    $kept = [];
    $inFence = false;
    $justDropped = false;
    foreach ($lines as $line) {
        if (preg_match('/^```/', $line)) $inFence = !$inFence;
        if (!$inFence && preg_match($dropRe, trim($line))) { $justDropped = true; continue; }
        // a removed meta block leaves its separating blank line behind; swallow it,
        // or every rewrite of this file would insert one more blank than it removed
        if ($justDropped && !$inFence && trim($line) === '') { $justDropped = false; continue; }
        $justDropped = false;
        $kept[] = $line;
    }

    // insert above the trailing Tags line when there is one, else append
    $at = null;
    $inFence = false;
    foreach ($kept as $i => $line) {
        if (preg_match('/^```/', $line)) $inFence = !$inFence;
        if (!$inFence && preg_match('/^\*\*Tags:\*\*/', trim($line))) $at = $i;
    }
    if ($at !== null) {
        array_splice($kept, $at, 0, [...$newLines, '']);
    } else {
        while ($kept && trim(end($kept)) === '') array_pop($kept);
        $kept[] = '';
        foreach ($newLines as $l) $kept[] = $l;
    }

    // exactly one trailing newline, no matter how often this runs: the file is
    // rewritten on every status change and every scoring pass, and without this
    // each write would leave one more blank line behind than the last
    while ($kept && trim(end($kept)) === '') array_pop($kept);

    $tmp = $file . '.tmp';
    if (file_put_contents($tmp, implode("\n", $kept) . "\n") === false) return false;
    return rename($tmp, $file);
}

/**
 * Rewrite a post file's publication meta in place (atomically):
 *   'publish'  → live now  (**Publish:** <now>, any draft flag removed)
 *   'draft'    → hidden    (**Status:** draft appended)
 *   'schedule' → goes live at $when ('YYYY-MM-DD HH:MM', site timezone)
 */
function post_write_status(string $file, string $action, string $when = ''): bool
{
    if ($action === 'schedule') {
        try {
            $when = (new DateTimeImmutable($when, site_tz()))->format('Y-m-d H:i');
        } catch (Exception) {
            return false;
        }
    } elseif ($action === 'publish') {
        $when = date('Y-m-d H:i');
    } elseif ($action !== 'draft') {
        return false;
    }

    return post_meta_write(
        $file,
        '/^\*\*(Status|Publish):\*\*/',
        [$action === 'draft' ? '**Status:** draft' : "**Publish:** {$when}"]
    );
}

/** Up to $n other posts sharing the most tags with $post; recency breaks ties. */
function related_posts(array $post, array $pool, int $n = 3): array
{
    $scored = [];
    foreach ($pool as $p) {
        if ($p['slug'] === $post['slug']) continue;
        $shared = count(array_intersect($post['tags'], $p['tags']));
        if ($shared === 0) continue;
        $scored[] = [$shared, $p['publish_ts'], $p];
    }
    usort($scored, fn($a, $b) => [$b[0], $b[1]] <=> [$a[0], $a[1]]);
    return array_column(array_slice($scored, 0, $n), 2);
}

/**
 * tag name => list of posts (posts stay in reverse-chron order).
 * Grouped by tag_slug so case/punctuation variants ("AI" vs "ai") merge into
 * one archive instead of silently overwriting each other's page; the
 * first-seen spelling becomes the display name.
 */
function collect_tags(array $posts): array
{
    $bySlug = [];
    $names = [];
    foreach ($posts as $p) {
        foreach ($p['tags'] as $t) {
            $k = tag_slug($t);
            $names[$k] ??= $t;
            $group = $bySlug[$k] ?? [];
            // a post tagged with two spellings of one tag lands in the group once
            if (!$group || end($group)['slug'] !== $p['slug']) $bySlug[$k][] = $p;
        }
    }
    uasort($bySlug, fn($a, $b) => count($b) <=> count($a));
    $tags = [];
    foreach ($bySlug as $k => $list) $tags[$names[$k]] = $list;
    return $tags;
}

/* ---------------- hero images ---------------- */

/** Image metadata for a post's hero, or null when no image exists. */
function hero_meta(string $slug): ?array
{
    static $credits = null;
    if ($credits === null) {
        $f = APP_ROOT . '/assets/img/posts/credits.json';
        $credits = is_file($f) ? (json_decode((string) file_get_contents($f), true) ?: []) : [];
    }
    $file = APP_ROOT . "/assets/img/posts/{$slug}.jpg";
    if (!is_file($file)) return null;
    $c = $credits[$slug] ?? [];
    [$w, $h] = getimagesize($file) ?: [null, null];
    $thumbFile = APP_ROOT . "/assets/img/posts/{$slug}.thumb.jpg";
    $thumb = is_file($thumbFile) ? "/assets/img/posts/{$slug}.thumb.jpg" : "/assets/img/posts/{$slug}.jpg";
    [$tw, $th] = is_file($thumbFile) ? (getimagesize($thumbFile) ?: [null, null]) : [$w, $h];
    return [
        'src'     => "/assets/img/posts/{$slug}.jpg",
        'w'       => $w,
        'h'       => $h,
        'thumb'   => $thumb,
        'tw'      => $tw,
        'th'      => $th,
        'creator' => $c['creator'] ?? '',
        'license' => strtoupper($c['license'] ?? ''),
        'source'  => $c['source'] ?? '',
        'caption' => $c['title'] ?? '',
    ];
}

/* ---------------- plain-text renderings ---------------- */

/** raw-markdown mirror of a post — friendly to AI agents and plain-text readers */
function render_md(array $p): string
{
    $tags = implode(', ', array_map('tag_slug', $p['tags']));
    $medium = $p['medium'] ? "- Also on Medium: {$p['medium']}\n" : '';
    return "# {$p['title']}\n\n"
        . "*{$p['subtitle']}*\n\n"
        . '- Author: ' . cfg('author') . "\n"
        . '- Published: ' . date('Y-m-d', $p['publish_ts']) . "\n"
        . '- Canonical: ' . post_url($p['slug']) . "\n"
        . $medium
        . "- Tags: {$tags}\n\n---\n\n"
        . $p['md'] . "\n";
}

/**
 * Refresh the "Selected posts:" list inside the hand-maintained llms.txt.
 * Only the list block is rewritten (all posts, newest first); the prose
 * around it stays as written. Returns false when the file or block is missing.
 */
function update_llms_selected(string $file, array $posts): bool
{
    if (!is_file($file)) return false;
    $txt = (string) file_get_contents($file);
    $list = implode("\n", array_map(
        static fn(array $p): string => "- {$p['title']}: " . post_url($p['slug']),
        $posts
    ));
    // the list block may sit at EOF without a trailing newline — match both,
    // otherwise a partial match would splice the new list next to stale lines
    $new = preg_replace_callback(
        '/(Selected posts:\n\n)((?:- [^\n]+(?:\n|$))+)/',
        static fn(array $m): string => $m[1] . $list . "\n",
        $txt,
        1,
        $found
    );
    if (!$found) return false;
    if ($new !== $txt) file_put_contents($file, $new);
    return true;
}

function render_llms_full(array $posts): string
{
    $out = '# Field Notes by ' . cfg('author') . ": full content\n\n"
         . '> Weekly essays on software architecture, the people around it, and the corporate machine they form together. '
         . 'Canonical HTML lives at ' . cfg('site_url') . '/blog/ and each essay also has a markdown mirror at its URL + index.md. '
         . "This file concatenates all essays for LLM ingestion.\n\n";
    foreach ($posts as $p) {
        $out .= "\n\n============================================================\n\n" . render_md($p);
    }
    return $out;
}
