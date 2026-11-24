<?php
/**
 * lib/blog.php — the content model.
 * Loading and parsing posts, URLs, tags, series, hero-image metadata, and the
 * plain-text renderings (per-post markdown mirrors, llms-full.txt).
 */

declare(strict_types=1);

require_once __DIR__ . '/scores.php';

/**
 * Where an essay is republished. One row per platform: the meta line that
 * declares it, the label readers see, the letter in the badge, and the host the
 * URL has to live on. Adding a platform is a row here, not another branch in
 * the parser, the badge, the schema and the markdown mirror.
 */
const SYNDICATION = [
    'Medium' => ['label' => 'Medium', 'badge' => 'M', 'host' => 'medium.com'],
    'DevTo'  => ['label' => 'DEV',    'badge' => 'D', 'host' => 'dev.to'],
];

/**
 * Where a post was *shared*, as opposed to republished.
 *
 * Deliberately not a SYNDICATION entry. Those become `sameAs` in the article schema and an "also
 * published on" badge, which asserts the article exists at that address. A LinkedIn post links to
 * the article; it is not a copy of it, and claiming otherwise in structured data is a lie search
 * engines are entitled to believe. So this is recorded, never rendered publicly, and exists so the
 * admin can answer "where has this one been" without opening drafts/ — which never ships.
 */
const SHARED_ON = [
    'LinkedIn' => ['label' => 'LinkedIn', 'badge' => 'in', 'host' => 'linkedin.com'],
];

/**
 * A syndication URL is only accepted when it is https and on that platform's
 * own host. It ends up in an href and in sameAs, so "whatever the author
 * typed" is not good enough: this refuses javascript: URLs outright and
 * catches the likelier mistake of a DevTo line pointing at Medium.
 */
function syndication_url_ok(string $url, string $host): bool
{
    $parts = parse_url($url);
    if (($parts['scheme'] ?? '') !== 'https' || !isset($parts['host'])) return false;
    return $parts['host'] === $host || str_ends_with($parts['host'], '.' . $host);
}

/**
 * Where a post gets hand-carried, and the utm_source that identifies the click
 * on the way back. Every one of these hosts strips or hides the referrer —
 * Medium's interstitial, dev.to's rel="noreferrer", LinkedIn's lnkd.in wrapper,
 * a mail client sending none at all — so an untagged link is indistinguishable
 * from somebody typing the address. The tag is the only way that traffic is
 * ever attributable; an_utm_group() maps it back to a real acquisition group.
 */
const SHARE_TARGETS = [
    'medium'     => ['label' => 'Medium',     'medium' => 'syndication'],
    'devto'      => ['label' => 'DEV',        'medium' => 'syndication'],
    'linkedin'   => ['label' => 'LinkedIn',   'medium' => 'social'],
    'newsletter' => ['label' => 'Newsletter', 'medium' => 'email'],
];

/** Posts that belong to a named series: slug => [series name, part]. One series per post. */
const SERIES = [
    'from-signals-to-blips'                   => ['Tools That Scale', 1],
    'probabilistic-metrics'                   => ['Tools That Scale', 2],
    'prompt-driven-architecture'              => ['Tools That Scale', 3],
    'the-bottleneck-moved'                    => ['Reviewability', 1],
    'we-deleted-the-wrong-half-of-agile'      => ['Reviewability', 2],
    'every-approval-is-a-missing-rule'        => ['The agentic operating model', 1],
    'the-model-grades-its-own-homework'       => ['The agentic operating model', 2],
    'four-levels-three-cliffs'                => ['The agentic operating model', 3],
    'adoption-is-the-deliverable'             => ['Adoption', 1],
    'nobody-resisted'                         => ['Adoption', 2],
    'the-model-is-the-easy-part'              => ['Invent the least', 1],
    'a-feature-is-not-a-service'              => ['Invent the least', 2],
    'decisions-have-an-architecture-too'      => ['Decisions', 1],
    'the-migration-that-never-starts'         => ['Decisions', 2],
    'design-the-stopping-point'               => ['Decisions', 3],
    'diagrams-are-conversations-not-documentation' => ['Diagrams', 1],
    'the-diagram-that-cannot-lie'             => ['Diagrams', 2],
    'turn-the-sequence-diagram-on-its-side'   => ['Diagrams', 3],
    'the-graph-database-we-didnt-buy'         => ['From edges to facts', 1],
    'one-row-per-edge'                        => ['From edges to facts', 2],
    'the-name-is-an-observation'              => ['From edges to facts', 3],
    'rejected-in-the-morning'                 => ['From edges to facts', 4],
    'owned-elsewhere'                         => ['From edges to facts', 5],
];

/** How many of the newest essays the homepage lists before linking the full archive. */
const HOME_LATEST_COUNT = 10;

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

/** Copy-ready post URLs, one per SHARE_TARGETS destination, tagged for attribution. */
function share_urls(string $slug): array
{
    $out = [];
    foreach (SHARE_TARGETS as $source => $spec) {
        $out[$source] = [
            'label' => $spec['label'],
            'url'   => post_url($slug) . '?' . http_build_query([
                'utm_source'   => $source,
                'utm_medium'   => $spec['medium'],
                'utm_campaign' => $slug,
            ]),
        ];
    }
    return $out;
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
    $body = [];
    $inFence = false;

    /* Every `**Key:** value` line the format understands: the capture pattern
       for the value, and where the raw value lands. Adding a meta key is one
       row here rather than another near-identical branch in the loop below. */
    $meta = ['Status' => null, 'Publish' => null, 'Updated' => null,
             'Scores' => null, 'Scored' => null, 'Tags' => null, 'Related' => null];
    foreach (array_keys(SYNDICATION) as $platform) $meta[$platform] = null;
    foreach (array_keys(SHARED_ON) as $platform) $meta[$platform] = null;
    $metaPattern = ['Status' => '[a-zA-Z]+'];
    foreach (array_keys(SYNDICATION) as $platform) $metaPattern[$platform] = 'https?:\/\/\S+';
    foreach (array_keys(SHARED_ON) as $platform) $metaPattern[$platform] = 'https?:\/\/\S+';

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
        if (preg_match('/^\*\*([A-Za-z]+):\*\*\s*(.+?)\s*$/', trim($line), $mm) && array_key_exists($mm[1], $meta)) {
            $value = $mm[2];
            $pattern = $metaPattern[$mm[1]] ?? null;
            // a key with a stricter value shape only counts when the value matches it
            if ($pattern !== null && !preg_match('/^' . $pattern . '$/', $value)) {
                if ($title !== '') $body[] = $line;
                continue;
            }
            $meta[$mm[1]] = $value;
            $dropTrailingRule();
            continue;
        }
        if ($title !== '') $body[] = $line;
    }

    $syndication = [];
    foreach (SYNDICATION as $platform => $spec) {
        $url = $meta[$platform];
        if ($url === null) continue;
        if (!syndication_url_ok($url, $spec['host'])) {
            blog_warn("{$name}.md: **{$platform}:** must be an https URL on {$spec['host']} — ignored");
            continue;
        }
        $syndication[$platform] = $url;
    }

    $shared = [];
    foreach (SHARED_ON as $platform => $spec) {
        $url = $meta[$platform];
        if ($url === null) continue;
        if (!syndication_url_ok($url, $spec['host'])) {
            blog_warn("{$name}.md: **{$platform}:** must be an https URL on {$spec['host']} — ignored");
            continue;
        }
        $shared[$platform] = $url;
    }

    $publishRaw = $meta['Publish'];
    $updatedRaw = $meta['Updated'];
    $scoresRaw  = $meta['Scores'] ?? '';
    $scoredRaw  = $meta['Scored'] ?? '';
    $draft      = strtolower((string) $meta['Status']) === 'draft';
    $tags       = $meta['Tags'] === null ? [] : array_map('trim', explode(',', $meta['Tags']));
    // optional **Related:** slug, slug — the author's own picks for "keep reading";
    // tags only fill the block when this line is absent
    $related    = $meta['Related'] === null ? []
                : array_values(array_filter(array_map('trim', explode(',', $meta['Related'])), fn($s) => $s !== ''));

    if ($inFence) {
        blog_warn("{$name}.md: unclosed code fence — any metadata after it (Status/Publish/Tags) is treated as fenced text and ignored");
    }

    // render once: the word count and the page were both re-running the whole
    // markdown pass over the same lines
    $html = md_to_html($body);
    $plain = trim(preg_replace('/\s+/', ' ', strip_tags($html)));
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
        'related'     => $related,
        'syndication' => $syndication,
        'shared' => $shared,
        'status'      => $status,
        'publish_ts'  => $publishTs,
        'publish_iso' => (new DateTimeImmutable('@' . $publishTs))->setTimezone(site_tz())->format('c'),
        'updated_ts'  => $updatedTs,
        'updated_iso' => $updatedTs !== null ? (new DateTimeImmutable('@' . $updatedTs))->setTimezone(site_tz())->format('c') : null,
        'scores'      => $scores,
        'scored'      => sc_parse_scored($scoredRaw),
        'words'       => $words,
        'minutes'     => max(1, (int) ceil($words / 215)),
        'html'        => $html,
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
    $bySlug = [];
    foreach (glob(CONTENT_DIR . '/posts/*.md') as $file) {
        $post = parse_post_file($file);
        if ($post === null) {
            blog_warn('skip (bad name): ' . basename($file, '.md'));
            continue;
        }
        $slug = $post['slug'];
        if (isset($bySlug[$slug])) {
            // One slug, two source files. Rescheduling renames a post, and the
            // deploy is additive, so the old filename can survive on the server.
            // Both files parse to the same slug: without this the post builds
            // twice and appears twice in the pipeline. Keep whichever was
            // written last, which is the freshly synced one, and say out loud
            // that the other has to go.
            [$keptFile, $keptPost] = $bySlug[$slug];
            $newer = filemtime($file) >= filemtime($keptFile);
            $bySlug[$slug] = $newer ? [$file, $post] : [$keptFile, $keptPost];
            blog_warn("duplicate slug '{$slug}': " . basename($keptFile) . ' and ' . basename($file)
                . ' — using ' . basename($newer ? $file : $keptFile) . ', delete the other');
            continue;
        }
        $bySlug[$slug] = [$file, $post];
    }

    $posts = [];
    foreach ($bySlug as [, $post]) {
        if (!$withUnpublished && $post['status'] !== 'published') continue;
        $posts[] = $post;
    }
    usort($posts, fn($a, $b) => $b['publish_ts'] <=> $a['publish_ts']);
    return $posts;
}

/**
 * slug => [filenames] for every slug backed by more than one source file.
 * Filename-only, so it stays cheap enough for the build and the admin page to
 * call on every run. A non-empty result always means manual cleanup is due.
 */
function post_duplicate_sources(): array
{
    $bySlug = [];
    foreach (glob(CONTENT_DIR . '/posts/*.md') as $file) {
        if (preg_match('/^\d{4}-\d{2}-\d{2}-([a-z0-9-]{3,80})$/', basename($file, '.md'), $m)) {
            $bySlug[$m[1]][] = basename($file);
        }
    }
    return array_filter($bySlug, static fn(array $files): bool => count($files) > 1);
}

/** Source .md path for a slug, or null. */
/**
 * The unguessable token that lets one person read an unpublished essay.
 *
 * Derived, not stored: HMAC over the slug with a config secret, so there is no
 * table of live links to keep, and a link for a post is always the same link.
 * The trade is that revocation is all-or-nothing — rotate `preview_secret` and
 * every share link dies at once. For handing a draft to two people before it
 * goes out, that is the right shape; if per-link expiry is ever needed, this is
 * where it goes, as a signed timestamp inside the same HMAC.
 *
 * Returns null when no secret is configured, and every caller must read that as
 * "sharing is off" rather than falling back to something predictable. A default
 * secret would make every draft on every install readable by anyone who knows
 * the scheme.
 */
function preview_share_token(string $slug): ?string
{
    $secret = (string) cfg('preview_secret', '');
    if ($secret === '' || str_starts_with($secret, 'CHANGE-ME')) return null;
    if (!preg_match('/^[a-z0-9-]{3,80}$/', $slug)) return null;
    return substr(hash_hmac('sha256', 'preview:' . $slug, $secret), 0, 32);
}

/** The full link to hand to somebody. Null when sharing is not configured. */
function preview_share_url(string $slug): ?string
{
    $token = preview_share_token($slug);
    return $token === null ? null : cfg('site_url') . '/preview.php?slug=' . rawurlencode($slug) . '&k=' . $token;
}

function post_source_file(string $slug): ?string
{
    // callers validate, but never let a malformed slug reach the glob
    if (!preg_match('/^[a-z0-9-]{3,80}$/', $slug)) return null;
    // the glob is a suffix match ("service" also hits "…-not-a-service.md"),
    // so verify the parsed slug before trusting a file
    $found = null;
    foreach (glob(CONTENT_DIR . '/posts/*-' . $slug . '.md') as $file) {
        $post = parse_post_file($file);
        if ($post === null || $post['slug'] !== $slug) continue;
        // same tie-break as load_posts: when a rename left two files behind,
        // every writer must land on the file the reader is actually using
        if ($found === null || filemtime($file) > filemtime($found)) $found = $file;
    }
    return $found;
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

/**
 * Up to $n other posts to keep reading. The author's **Related:** picks come first,
 * in the order written, skipping slugs that are not in the pool; without picks it
 * falls back to the posts sharing the most tags, recency breaking ties. The tag
 * fallback is coarse on purpose (25 of 33 essays share one tag), which is why the
 * manual line exists at all.
 */
function related_posts(array $post, array $pool, int $n = 3): array
{
    if (!empty($post['related'])) {
        $by = array_column($pool, null, 'slug');
        $picked = [];
        foreach ($post['related'] as $slug) {
            if ($slug !== $post['slug'] && isset($by[$slug])) $picked[] = $by[$slug];
        }
        if ($picked) return array_slice($picked, 0, $n);
    }
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
    /* A hero is either a licensed photograph (.jpg) or a figure rendered from a model (.png).
       The extension decides, and png wins, because a rendered figure is transparent and one
       file then serves both themes — where a photograph never can, which is the whole reason
       body figures ship as light/dark pairs. */
    $ext = null;
    foreach (['png', 'jpg'] as $e) {
        if (is_file(APP_ROOT . "/assets/img/posts/{$slug}.{$e}")) { $ext = $e; break; }
    }
    if ($ext === null) return null;
    $file = APP_ROOT . "/assets/img/posts/{$slug}.{$ext}";
    $c = $credits[$slug] ?? [];
    [$w, $h] = getimagesize($file) ?: [null, null];
    $thumbFile = APP_ROOT . "/assets/img/posts/{$slug}.thumb.{$ext}";
    $thumb = is_file($thumbFile) ? "/assets/img/posts/{$slug}.thumb.{$ext}" : "/assets/img/posts/{$slug}.{$ext}";
    [$tw, $th] = is_file($thumbFile) ? (getimagesize($thumbFile) ?: [null, null]) : [$w, $h];
    /* The social card, when the hero cannot be one. A transparent figure unfurls as a black
       slab on one client and a white one on the next, and a hero is whatever shape it is
       rather than 1200×630 — so `<slug>.og.jpg` overrides it where a post ships one. */
    $cardFile = APP_ROOT . "/assets/img/posts/{$slug}.og.jpg";
    $card = is_file($cardFile) ? "/assets/img/posts/{$slug}.og.jpg" : "/assets/img/posts/{$slug}.{$ext}";
    [$cw, $ch] = is_file($cardFile) ? (getimagesize($cardFile) ?: [$w, $h]) : [$w, $h];
    return [
        'src'     => "/assets/img/posts/{$slug}.{$ext}",
        'card'    => $card,
        'cw'      => $cw,
        'ch'      => $ch,
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

/**
 * The figures inside a post body: [src, w, h, caption] each, in document order.
 * Reads the markdown rather than the rendered HTML so it sees exactly the
 * standalone image lines that md_to_html turns into <figure> elements, and the
 * alt text that doubles as the caption.
 */
function post_body_images(array $p): array
{
    if (!preg_match_all('/^!\[([^\]]*)\]\((\S+)\)\s*$/m', (string) ($p['md'] ?? ''), $m, PREG_SET_ORDER)) return [];
    $out = [];
    foreach ($m as [, $caption, $src]) {
        if (!str_starts_with($src, '/') || str_starts_with($src, '//')) continue;   // our own assets only
        $size = @getimagesize(APP_ROOT . $src);
        $out[] = [
            'src'     => $src,
            'w'       => $size ? (int) $size[0] : null,
            'h'       => $size ? (int) $size[1] : null,
            'caption' => $caption,
        ];
    }
    return $out;
}

/* ---------------- plain-text renderings ---------------- */

/** raw-markdown mirror of a post — friendly to AI agents and plain-text readers */
function render_md(array $p): string
{
    $tags = implode(', ', array_map('tag_slug', $p['tags']));
    $also = '';
    foreach ($p['syndication'] as $platform => $url) {
        $also .= '- Also on ' . SYNDICATION[$platform]['label'] . ": {$url}\n";
    }
    return "# {$p['title']}\n\n"
        . "*{$p['subtitle']}*\n\n"
        . '- Author: ' . cfg('author') . "\n"
        . '- Published: ' . date('Y-m-d', $p['publish_ts']) . "\n"
        . '- Canonical: ' . post_url($p['slug']) . "\n"
        . $also
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
         . 'Canonical HTML lives at ' . cfg('site_url') . '/ and each essay also has a markdown mirror at its URL + index.md. '
         . "This file concatenates all essays for LLM ingestion.\n\n"
         // essays only — the project page is not an essay, so it is pointed at rather than inlined
         . 'Not included here: Stemma, the author\'s architecture modelling tool, documented at '
         . cfg('site_url') . '/stemma/ (plain text: ' . cfg('site_url') . "/stemma/index.md).\n\n";
    foreach ($posts as $p) {
        $out .= "\n\n============================================================\n\n" . render_md($p);
    }
    return $out;
}

/* ---------------- series, reading paths, figure thumbnails ---------------- */

/** Every slug of the series $slug belongs to, in part order; [] outside a series. */
function series_slugs(string $slug): array
{
    if (!isset(SERIES[$slug])) return [];
    $name = SERIES[$slug][0];
    $parts = [];
    foreach (SERIES as $s => [$n, $i]) if ($n === $name) $parts[$i] = $s;
    ksort($parts);
    return array_values($parts);
}

/**
 * Re-point site links at /blog/<slug>/ to a route an admin can open. A post that is not
 * live has no page under /blog/, so inside a draft every link to a sibling draft 404s
 * until both are published, and a series read in preview is a chain of dead ends.
 * $to maps a slug to the replacement href; $only, when given, limits the swap to those
 * slugs (a set: slug => true), so links to published essays keep pointing at the site.
 * Admin surfaces only: a share-link guest must never be handed a path to a second draft.
 */
function admin_post_links(string $html, callable $to, ?array $only = null): string
{
    return (string) preg_replace_callback('#href="/blog/([a-z0-9-]+)/"#', function (array $m) use ($to, $only): string {
        if ($only !== null && !isset($only[$m[1]])) return $m[0];
        return 'href="' . e($to($m[1])) . '"';
    }, $html);
}

/**
 * The three reading paths ("doors"): one editorial list, rendered on the start
 * page and on the homepage so the two never drift apart.
 */
function reading_paths(): array
{
    return [
        [
            'k'     => 'Architecture & orgs',
            'art'   => 'architecture',
            'title' => 'People problems, dressed as technical ones',
            'desc'  => 'Ownership gaps, decision rights, and the org chart hiding inside your architecture. For architects and tech leads who keep fixing the same "technical" issue that never was one.',
            'reads' => ['nobody-owns-the-sum', 'decisions-have-an-architecture-too', 'the-anti-corruption-layer-is-for-people-too'],
        ],
        [
            'k'      => 'The AI transformation',
            'art'    => 'ai',
            'title'  => 'Running AI adoption at real scale',
            'desc'   => 'Fifteen squads, several hundred developers, one transformation: the tech radar that drives it, the metrics that survive contact with reality, and the architecture practice that grew out of both.',
            'series' => 'The “Tools That Scale” series',
            'reads'  => ['from-signals-to-blips', 'probabilistic-metrics', 'prompt-driven-architecture'],
        ],
        [
            'k'     => 'Built in the open',
            'art'   => 'open',
            'title' => 'One person, three products, AI agents',
            'desc'  => 'Field reports from shipping my own products solo, with AI doing the typing: what actually changes, what breaks, and the receipts to prove it.',
            'reads' => ['a-company-of-one-staffed-by-agents', 'the-diagram-that-cannot-lie', 'edge-cases-written-by-parliament'],
        ],
    ];
}
