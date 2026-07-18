<?php
/**
 * lib/markdown.php — minimal markdown renderer for the post corpus.
 * Supports: h2, paragraphs, ul/ol, blockquotes, hr, code fences,
 * inline bold/italic/links/code, with HTML escaping throughout.
 */

declare(strict_types=1);

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
    $s = preg_replace('/!\[([^\]]*)\]\(([^)\s]+)\)/', '<img src="$2" alt="$1" loading="lazy" style="max-width:100%;height:auto">', $s);
    // links
    $s = preg_replace('/\[([^\]]+)\]\(([^)\s]+)\)/', '<a href="$2">$1</a>', $s);
    // bold, then italic
    $s = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $s);
    $s = preg_replace('/(?<![\w*])\*([^*\n]+)\*(?![\w*])/', '<em>$1</em>', $s);

    return preg_replace_callback('/\x1A(\d+)\x1A/', fn($m) => $codes[(int) $m[1]], $s);
}

function md_to_html(array $lines): string
{
    $html = '';
    $para = [];
    $list = null;      // 'ul' | 'ol' | null
    $quote = [];
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

    foreach ($lines as $line) {
        if (preg_match('/^```/', $line)) {
            $flushPara(); $flushList(); $flushQuote();
            $html .= $inCode ? "</code></pre>\n" : '<pre><code>';
            $inCode = !$inCode;
            continue;
        }
        if ($inCode) {
            $html .= htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "\n";
            continue;
        }

        $t = rtrim($line);

        if ($t === '') { $flushPara(); $flushList(); $flushQuote(); continue; }
        if (preg_match('/^!\[([^\]]*)\]\(([^)\s]+)\)\s*$/', $t, $m)) {
            $flushPara(); $flushList(); $flushQuote();
            $src = htmlspecialchars($m[2], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $cap = md_inline($m[1]);
            $alt = htmlspecialchars($m[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $html .= '<figure class="post-figure"><img src="' . $src . '" alt="' . $alt . '" loading="lazy" style="max-width:100%;height:auto">'
                   . ($m[1] !== '' ? '<figcaption style="font-size:.85em;color:#666;margin-top:.4em">' . $cap . '</figcaption>' : '')
                   . "</figure>\n";
            continue;
        }
        if (preg_match('/^#{2}\s+(.*)$/', $t, $m)) {
            $flushPara(); $flushList(); $flushQuote();
            $html .= '<h2>' . md_inline($m[1]) . "</h2>\n";
            continue;
        }
        if (preg_match('/^>\s?(.*)$/', $t, $m)) {
            $flushPara(); $flushList();
            $quote[] = $m[1];
            continue;
        }
        if (preg_match('/^- (.*)$/', $t, $m)) {
            $flushPara(); $flushQuote();
            if ($list !== 'ul') { $flushList(); $html .= "<ul>\n"; $list = 'ul'; }
            $html .= '<li>' . md_inline($m[1]) . "</li>\n";
            continue;
        }
        if (preg_match('/^\d+\.\s+(.*)$/', $t, $m)) {
            $flushPara(); $flushQuote();
            if ($list !== 'ol') { $flushList(); $html .= "<ol>\n"; $list = 'ol'; }
            $html .= '<li>' . md_inline($m[1]) . "</li>\n";
            continue;
        }
        if (preg_match('/^(---+|\*\*\*+)$/', $t)) {
            $flushPara(); $flushList(); $flushQuote();
            $html .= "<hr>\n";
            continue;
        }
        $para[] = $t;
    }
    $flushPara(); $flushList(); $flushQuote();
    return $html;
}
