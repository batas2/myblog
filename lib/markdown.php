<?php
/**
 * lib/markdown.php — minimal markdown renderer for the post corpus.
 * Supports: h2, paragraphs, ul/ol, blockquotes, hr, code fences, pipe tables,
 * inline bold/italic/links/code, with HTML escaping throughout.
 */

declare(strict_types=1);

/**
 * ` width="W" height="H"` for a site-relative image, or '' when the file is not
 * on disk. Writing the intrinsic size into the tag is what stops the article
 * reflowing as each figure loads: the browser reserves the box up front instead
 * of shoving the text down when the bytes arrive. Readers feel that as the page
 * jumping under them, and Core Web Vitals counts it against the page.
 *
 * Cached per src because parse_post_file renders each body twice, once for the
 * word count and once for the page.
 */
function md_img_dimensions(string $src): string
{
    static $cache = [];
    if (isset($cache[$src])) return $cache[$src];

    $attr = '';
    // only our own assets: an absolute path under APP_ROOT, never a remote URL
    if (defined('APP_ROOT') && str_starts_with($src, '/') && !str_starts_with($src, '//')) {
        $size = @getimagesize(APP_ROOT . $src);
        if ($size && $size[0] > 0 && $size[1] > 0) $attr = ' width="' . (int) $size[0] . '" height="' . (int) $size[1] . '"';
    }
    return $cache[$src] = $attr;
}

/**
 * The `<img>` for a figure, wrapped in a `<picture>` when a dark twin exists on disk.
 *
 * Diagrams exported from draw.io are transparent PNGs and read on either background, but the ones
 * captured out of Stemma are screenshots of an application, so they carry its canvas with them: a
 * light capture is a glaring white slab for a dark-mode reader, and a dark one is a hole in the
 * page for everybody else. So a post writes the light file, and if `<name>-dark.<ext>` sits beside
 * it the browser is told to prefer that one. No new markdown syntax, and the naming is the one
 * already used by /assets/img/stemma/.
 *
 * The `<img>` stays the light file so anything that does not understand `<picture>` (feed readers,
 * the markdown mirrors, an unfurler) still gets a real image.
 */
function md_img_tag(string $rawSrc, string $escSrc, string $alt): string
{
    $img = '<img src="' . $escSrc . '" alt="' . $alt . '"' . md_img_dimensions($rawSrc)
         . ' loading="lazy" style="max-width:100%;height:auto">';

    if (!preg_match('/^(.*)-light(\.[a-z0-9]+)$/i', $rawSrc, $m)) return $img;
    $darkSrc = $m[1] . '-dark' . $m[2];
    if (!defined('APP_ROOT') || !is_file(APP_ROOT . $darkSrc)) return $img;

    return '<picture><source media="(prefers-color-scheme: dark)" srcset="'
         . htmlspecialchars($darkSrc, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">' . $img . '</picture>';
}

/* ============================== markdown ============================== */

function md_inline(string $s): string
{
    $s = htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    // protect inline code from further formatting
    $codes = [];
    $s = preg_replace_callback('/`([^`]+)`/', function ($m) use (&$codes) {
        $codes[] = '<code>' . $m[1] . '</code>';
        return "\x1A" . (count($codes) - 1) . "\x1A";
    }, $s);

    // images (before links so ![alt](src) is not parsed as a link)
    $s = preg_replace_callback('/!\[([^\]]*)\]\(([^)\s]+)\)/', fn(array $m): string =>
        '<img src="' . $m[2] . '" alt="' . $m[1] . '"' . md_img_dimensions($m[2])
        . ' loading="lazy" style="max-width:100%;height:auto">', $s);
    // links
    $s = preg_replace('/\[([^\]]+)\]\(([^)\s]+)\)/', '<a href="$2">$1</a>', $s);
    // bold, then italic
    $s = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $s);
    $s = preg_replace('/(?<![\w*])\*([^*\n]+)\*(?![\w*])/', '<em>$1</em>', $s);

    return preg_replace_callback('/\x1A(\d+)\x1A/', fn($m) => $codes[(int) $m[1]], $s);
}

/**
 * The cells of one pipe-table row.
 *
 * The outer pipes are optional in GFM, so they are stripped before splitting: keeping them would
 * invent an empty cell at each end. `\|` is a literal pipe inside a cell, so the split only breaks
 * on unescaped ones and the escape is unwrapped afterwards, before md_inline sees it.
 */
function md_table_cells(string $row): array
{
    $row = trim($row);
    if (str_starts_with($row, '|')) $row = substr($row, 1);
    if (str_ends_with($row, '|') && !str_ends_with($row, '\\|')) $row = substr($row, 0, -1);

    return array_map(
        fn(string $c): string => str_replace('\\|', '|', trim($c)),
        preg_split('/(?<!\\\\)\|/', $row)
    );
}

/** `---` / `:---` / `---:` / `:---:` in every cell: the row that turns the line above into a header. */
function md_is_table_delimiter(string $row): bool
{
    return (bool) preg_match('/^\s*\|?(\s*:?-+:?\s*\|)*\s*:?-+:?\s*\|?\s*$/', $row);
}

function md_to_html(array $lines): string
{
    $html = '';
    $para = [];
    $list = null;      // 'ul' | 'ol' | null
    $quote = [];
    $table = [];
    $inCode = false;

    $flushPara = function () use (&$para, &$html) {
        if ($para) {
            $html .= '<p>' . md_inline(trim(implode(' ', $para))) . "</p>\n";
            $para = [];
        }
    };
    $flushList = function () use (&$list, &$html) {
        if ($list) { $html .= "</$list>\n"; $list = null; }
    };
    $flushQuote = function () use (&$quote, &$html) {
        if ($quote) {
            $html .= "<blockquote><p>" . md_inline(trim(implode(' ', $quote))) . "</p></blockquote>\n";
            $quote = [];
        }
    };
    /**
     * A run of pipe-prefixed lines becomes a table only if the second one is a delimiter. Anything
     * else was never a table, so it goes back out as prose: a body line that happens to start with
     * a pipe should render oddly, not disappear.
     *
     * The header row fixes the column count. Short rows get empty cells and long ones are truncated,
     * which is what GFM does and what keeps a miscounted row from breaking the grid.
     */
    $flushTable = function () use (&$table, &$html, &$para) {
        if (!$table) return;
        $rows = $table;
        $table = [];

        if (count($rows) < 2 || !md_is_table_delimiter($rows[1])) {
            foreach ($rows as $r) $para[] = $r;
            return;
        }

        $align = array_map(function (string $c): string {
            $left = str_starts_with($c, ':');
            $right = str_ends_with($c, ':');
            if ($left && $right) return ' style="text-align:center"';
            return $right ? ' style="text-align:right"' : '';
        }, md_table_cells($rows[1]));

        $head = md_table_cells($rows[0]);
        $cols = count($head);

        $html .= "<div class=\"table-wrap\">\n<table>\n<thead>\n<tr>";
        foreach ($head as $i => $c) $html .= '<th' . ($align[$i] ?? '') . '>' . md_inline($c) . '</th>';
        $html .= "</tr>\n</thead>\n<tbody>\n";
        foreach (array_slice($rows, 2) as $row) {
            $cells = md_table_cells($row);
            $html .= '<tr>';
            for ($i = 0; $i < $cols; $i++) $html .= '<td' . ($align[$i] ?? '') . '>' . md_inline($cells[$i] ?? '') . '</td>';
            $html .= "</tr>\n";
        }
        $html .= "</tbody>\n</table>\n</div>\n";
    };

    foreach ($lines as $line) {
        if (preg_match('/^```/', $line)) {
            $flushTable(); $flushPara(); $flushList(); $flushQuote();
            $html .= $inCode ? "</code></pre>\n" : '<pre><code>';
            $inCode = !$inCode;
            continue;
        }
        if ($inCode) {
            $html .= htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "\n";
            continue;
        }

        $t = rtrim($line);

        // A table row, collected until the run ends: whether these lines are a table at all is
        // decided by the second one, which has not been read yet.
        if (str_starts_with($t, '|')) {
            $flushPara(); $flushList(); $flushQuote();
            $table[] = $t;
            continue;
        }

        if ($t === '') { $flushTable(); $flushPara(); $flushList(); $flushQuote(); continue; }
        if (preg_match('/^!\[([^\]]*)\]\(([^)\s]+)\)\s*$/', $t, $m)) {
            $flushTable(); $flushPara(); $flushList(); $flushQuote();
            $src = htmlspecialchars($m[2], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $cap = md_inline($m[1]);
            $alt = htmlspecialchars($m[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $html .= '<figure class="post-figure">' . md_img_tag($m[2], $src, $alt)
                   . ($m[1] !== '' ? '<figcaption style="font-size:.85em;color:var(--muted);margin-top:.4em">' . $cap . '</figcaption>' : '')
                   . "</figure>\n";
            continue;
        }
        if (preg_match('/^#{2}\s+(.*)$/', $t, $m)) {
            $flushTable(); $flushPara(); $flushList(); $flushQuote();
            $html .= '<h2>' . md_inline($m[1]) . "</h2>\n";
            continue;
        }
        if (preg_match('/^>\s?(.*)$/', $t, $m)) {
            $flushTable(); $flushPara(); $flushList();
            $quote[] = $m[1];
            continue;
        }
        if (preg_match('/^- (.*)$/', $t, $m)) {
            $flushTable(); $flushPara(); $flushQuote();
            if ($list !== 'ul') { $flushList(); $html .= "<ul>\n"; $list = 'ul'; }
            $html .= '<li>' . md_inline($m[1]) . "</li>\n";
            continue;
        }
        if (preg_match('/^\d+\.\s+(.*)$/', $t, $m)) {
            $flushTable(); $flushPara(); $flushQuote();
            if ($list !== 'ol') { $flushList(); $html .= "<ol>\n"; $list = 'ol'; }
            $html .= '<li>' . md_inline($m[1]) . "</li>\n";
            continue;
        }
        if (preg_match('/^(---+|\*\*\*+)$/', $t)) {
            $flushTable(); $flushPara(); $flushList(); $flushQuote();
            $html .= "<hr>\n";
            continue;
        }
        $flushTable();
        $para[] = $t;
    }
    if ($inCode) $html .= "</code></pre>\n";   // unclosed fence must not swallow the rest of the page
    $flushTable(); $flushPara(); $flushList(); $flushQuote();
    return $html;
}
