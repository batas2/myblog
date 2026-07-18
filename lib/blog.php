<?php
/**
 * lib/blog.php — the content model.
 * Loading and parsing posts, URLs, tags, series, hero-image metadata, and the
 * plain-text renderings (per-post markdown mirrors, llms-full.txt).
 */

declare(strict_types=1);

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

function nice_date(string $d): string { return date('M j, Y', strtotime($d)); }
function log_date(string $d): string { return date('Y.m.d', strtotime($d)); }

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
    if (!preg_match('/^(\d{4}-\d{2}-\d{2})-(.+)$/', $name, $m)) {
        return null;
    }
    [, $date, $slug] = $m;

    $lines = preg_split('/\r?\n/', (string) file_get_contents($file));
    $title = $subtitle = '';
    $medium = $publishRaw = null;
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
            && preg_match('/^\*([^*].*)\*\s*$/', trim($line), $mm)) {
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
        if (preg_match('/^\*\*Tags:\*\*\s*(.+)$/', trim($line), $mm)) {
            $tags = array_map('trim', explode(',', $mm[1]));
            $dropTrailingRule();
            continue;
        }
        if ($title !== '') $body[] = $line;
    }

    $plain = trim(preg_replace('/\s+/', ' ', strip_tags(md_to_html($body))));
    $words = str_word_count($plain);

    $publishTs = post_publish_ts($date, $publishRaw);
    $status = $draft ? 'draft' : ($publishTs > time() ? 'scheduled' : 'published');

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

/** True when the slug belongs to a currently published post (comments gate). */
function post_is_public(string $slug): bool
{
    // the glob is a suffix match ("service" also hits "…-not-a-service.md"),
    // so verify the parsed slug before trusting a file
    foreach (glob(CONTENT_DIR . '/posts/*-' . $slug . '.md') as $file) {
        $post = parse_post_file($file);
        if ($post !== null && $post['slug'] === $slug) {
            return $post['status'] === 'published';
        }
    }
    return false;
}

/** tag name => list of posts (posts stay in reverse-chron order) */
function collect_tags(array $posts): array
{
    $tags = [];
    foreach ($posts as $p) {
        foreach ($p['tags'] as $t) $tags[$t][] = $p;
    }
    uasort($tags, fn($a, $b) => count($b) <=> count($a));
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
        . "- Published: {$p['date']}\n"
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
