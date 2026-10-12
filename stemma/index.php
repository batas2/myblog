<?php
/**
 * stemma/index.php — the project page for Stemma.
 *
 * Built to be lifted. Everything below the site chrome is self-contained: one
 * scoped stylesheet (`.st-` prefix), its own SVG, its own images under
 * /assets/img/stemma/. When Stemma gets its own domain the body moves as a
 * block and only page_head()/site_nav()/site_footer() are replaced.
 *
 * Shape: a spec sheet paced like a product page. Big quiet statements, the
 * product itself as the evidence, and the dense reference material at the
 * bottom where someone evaluating it will go looking for it.
 *
 * Indexed on purpose — unlike /services/, this page is meant to be found.
 */

declare(strict_types=1);

require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/components.php';
require_once __DIR__ . '/../lib/page.php';
require_once __DIR__ . '/../lib/schema.php';

$url  = cfg('site_url') . '/stemma/';
$repo = 'https://github.com/batas2/Stemma';
$film = cfg('site_url') . '/assets/video/stemma-film-dark.mp4';
$shot = cfg('site_url') . '/assets/img/stemma/canvas-dark.webp';

/* The questions an evaluator actually arrives with, answered once. Rendered as a visible
   section further down and mirrored into FAQPage below — the markup describes what is on
   the page, which is the only way it is allowed to be used. */
$faq = [
    'Does the diagram stay in sync with the code?' =>
        'It cannot drift, because it is the same thing. The model is C# in Architecture/*.cs inside your repository, and a canvas edit is a Roslyn DocumentEditor rewrite of that file. There is no export step to forget and no second copy to keep in sync.',
    'Does my whole system have to be written in C#?' =>
        'No. C# is only the notation of the model, not a limit on what you model. The workspace can be a model-only folder with no solution in it, and the systems it describes can be written in anything. You need a .NET runtime, and a model-only workspace opens with no SDK, no restore and no NuGet.',
    'Where is the data stored?' =>
        'In your Git working tree, and nowhere else. There is no runtime database at any layer. The model is C# source, and the presentation (positions, styling, routing, notes) is a stemma.layout.json sidecar committed next to it. While a workspace is open the model is in memory; the files are the permanent storage.',
    'How is this different from draw.io, Lucidchart or an EA tool?' =>
        'A drawing tool gives you a picture that is true on the day you save it. A reverse-engineering tool reads code but cannot edit it. Stemma works in both directions and keeps round-trip fidelity, so a UI edit becomes a diff with only that edit in it. That is what makes the loop safe to use.',
    'What does a change look like in review?' =>
        'A normal Git diff. Renaming one element on the canvas rewrites the declaration and every reference Roslyn can see, and nothing else: no reordering, no reformatting, no lost comment. The reviewer reads it as the change it was.',
    'How do stakeholders see the model without installing anything?' =>
        'Two ways, both in the visual layer. A story book is an ordered sequence of pages for one audience that drives the canvas while you present, stored next to the model so it cannot get stale. Or you export one self-contained HTML report: a single file that opens offline, with no server, no login and no seat, with audience modes and comments attached to elements that you can import back.',
    'Can an agent use this without opening the canvas?' =>
        'Yes, and out of the box: that is the text layer. A workspace is three text files, Architecture/*.cs, Views/*.cs and stemma.layout.json, and all three are documented for someone who will never see the app. An agent writes them, runs stemma validate to catch the string ids nothing else checks, and runs stemma render to get the image with no browser and no mouse. The layout is a constraint (grid, row, column, stack) instead of coordinates, because a model is good at intent and bad at pixels.',
    'What stops a published diagram from going stale?' =>
        'Every render writes a manifest next to the image, with a fingerprint of the model, the view, the layout and the profile. stemma render --check calculates it again and exits non-zero when the committed image no longer matches its source, so the build fails before a reader sees an old picture. It is the same drift guarantee as the diff, outside the app window.',
    'What licence is it under?' =>
        'PolyForm Noncommercial 1.0.0. Free for any noncommercial use: personal use, study, hobby projects, and use by charities, schools, public research bodies and government. Any commercial use needs a commercial licence: kontakt@bfrackowiak.pl. It is source-available, not open source, and that is on purpose.',
];

$extra  = ld_script([
    '@context' => 'https://schema.org',
    '@graph' => [
        [
            '@type' => 'WebPage',
            '@id' => $url . '#page',
            'url' => $url,
            'name' => 'Stemma: the architecture model that is your source code',
            'description' => 'How Stemma works: the C# model, the diff a rename produces, the headless pipeline an agent drives, the views, the story books, and the single-file HTML report.',
            'inLanguage' => 'en',
            'isPartOf' => ['@id' => cfg('site_url') . '/#website'],
            'about' => ['@id' => $url . '#app'],
            'primaryImageOfPage' => ['@type' => 'ImageObject', 'url' => $shot],
            // the changelog counts: a release is the most common reason anything on this
            // page stopped being current, and it lives in its own file
            'dateModified' => date('Y-m-d', (int) max(
                filemtime(__FILE__),
                filemtime(__DIR__ . '/changelog/index.php') ?: 0,
                filemtime(dirname(__DIR__) . '/assets/video/stemma-film-dark.mp4') ?: 0,
            )),
        ],
        [
            '@type' => 'SoftwareApplication',
            '@id' => $url . '#app',
            'name' => 'Stemma',
            'alternateName' => 'Stemma architecture modelling tool',
            'url' => $url,
            'applicationCategory' => 'DeveloperApplication',
            'applicationSubCategory' => 'Software architecture modelling',
            'operatingSystem' => 'Linux, macOS, Windows',
            'softwareRequirements' => '.NET 10 runtime',
            'author' => ['@id' => cfg('site_url') . '/#person'],
            'description' => 'An architecture modelling tool for solution architects in which the C# source on disk is the model database. Canvas edits become Roslyn rewrites of real .cs files, so the diagram cannot drift from the code. The same model is also three text files that an agent can write, validate and render with one command, out of the box.',
            'codeRepository' => $repo,
            'softwareHelp' => $repo,
            'screenshot' => $shot,
            'video' => ['@id' => $url . '#film'],
            'license' => 'https://polyformproject.org/licenses/noncommercial/1.0.0/',
            'releaseNotes' => $url . 'changelog/',
            // Drawn from the specifications the page states below — claims, not marketing.
            'featureList' => [
                'Model stored as C# in Architecture/*.cs inside your Git repository',
                'Round-trip fidelity: a UI edit diffs as only that edit',
                'Canvas modelling of bounded contexts, modules, containers, systems, people, capabilities and use cases',
                'Data flows and dependencies that carry what moves, not only that something moves',
                'Lifecycle and ownership tags, plus risks, questions and assumptions as first-class elements',
                'Concept elements and Relation links for boxes and arrows that assert nothing about a system',
                'Module map, dependency graph and concerns board, plus saved custom views stored as code',
                'Narrated, audience-tagged story books that drive the canvas in present mode',
                'Headless CLI: stemma render turns a model and a view into an image with no browser and no mouse',
                'stemma validate reports the dangling ids, empty views and half-drawn links the compiler cannot see',
                'Layout expressed as a declarative constraint (grid, row, column, stack) solved to coordinates',
                'A render manifest and stemma render --check, so a build fails when a published image stops matching its model',
                'A published JSON Schema for the presentation sidecar, and a protocol reference for authoring a workspace as text',
                'Export to PNG, true vector SVG, draw.io, Mermaid, PDF view-books and a single-file interactive HTML report',
                'Local-only diagnostics: structured logs with no network sink, and redacted shareable bundles',
                'No runtime database: Git is the store and the collaboration model',
            ],
        ],
        [
            // self-hosted, so contentUrl is a real file a crawler can fetch. Two cuts are
            // served — one lit for each theme — and the dark one is the canonical URL here
            // because it is what the thumbnail below shows.
            '@type' => 'VideoObject',
            '@id' => $url . '#film',
            'name' => 'Stemma: the diagram is the code',
            'description' => 'A two-minute look at Stemma: a model built from an empty file on camera (bounded contexts, modules, relationships), the two-line diff a rename produces in the source, the narrated story books that walk each audience through it, and the single self-contained HTML report the whole thing exports to.',
            'contentUrl' => $film,
            'thumbnailUrl' => [$shot, cfg('site_url') . '/assets/img/stemma/og.jpg'],
            'uploadDate' => '2026-08-04',
            'duration' => 'PT1M58S',
            'width' => 1920,
            'height' => 1080,
            'isFamilyFriendly' => true,
            'author' => ['@id' => cfg('site_url') . '/#person'],
        ],
        [
            '@type' => 'FAQPage',
            '@id' => $url . '#faq',
            'mainEntity' => array_map(fn($q, $a) => [
                '@type' => 'Question',
                'name' => $q,
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $a],
            ], array_keys($faq), array_values($faq)),
        ],
    ],
]) . "\n";
$extra .= ld_script(schema_breadcrumbs([
    'Field Notes' => cfg('site_url') . '/',
    'Stemma' => $url,
])) . "\n";
$extra .= '<meta property="og:image:width" content="1200">' . "\n"
        . '<meta property="og:image:height" content="630">' . "\n"
        . '<meta property="og:image:alt" content="Stemma: the diagram is the code. An architecture model held as C# in your repository.">' . "\n"
        . '<meta name="twitter:image:alt" content="Stemma: the diagram is the code. An architecture model held as C# in your repository.">' . "\n";

/* A pasted link should unfurl as the film, not as a still. Slack, Discord, Telegram and X all
   read og:video and will play an mp4 inline; everything else ignores these and falls back to
   the card image, so there is no downside to declaring them. */
$extra .= '<meta property="og:video" content="' . e($film) . '">' . "\n"
        . '<meta property="og:video:secure_url" content="' . e($film) . '">' . "\n"
        . '<meta property="og:video:type" content="video/mp4">' . "\n"
        . '<meta property="og:video:width" content="1920">' . "\n"
        . '<meta property="og:video:height" content="1080">' . "\n"
        . '<meta name="twitter:player:stream" content="' . e($film) . '">' . "\n"
        . '<meta name="twitter:player:stream:content_type" content="video/mp4">' . "\n";

/* Same courtesy the essays extend: a plain-text rendering for agents and answer engines. */
$extra .= '<link rel="alternate" type="text/markdown" href="' . e($url) . 'index.md" title="Markdown source">' . "\n";

$extra .= <<<'CSS'
<style>
/* ============================================================================
   Stemma project page.
   Scoped to .st-*. Inherits the site tokens (Inter / JetBrains Mono, indigo,
   both themes) and adds two local ones: a stage surface for the full-bleed
   chapters, and Stemma's own lifecycle words (current / target / to-be-created)
   used as status — the same words the product puts on your systems.
   ========================================================================== */

.st-page {
  --st-ok: #0f9d6e; --st-tgt: var(--accent); --st-new: #b0790a;
  --st-stage: #f1f2f7;
  --st-shot: min(1480px, 94vw);
  /* The film is cut twice; this is the letterbox colour of whichever cut is playing. */
  --st-film-bg: #fbfbfd;
}
@media (prefers-color-scheme: dark) {
  .st-page { --st-ok: #3ecf9a; --st-new: #e0aa3e; --st-stage: #131419; --st-film-bg: #08090c; }
}

/* ---- chapter rhythm ------------------------------------------------------ */
.st-page .st-sec { padding: 7rem 0; }
.st-stage { background: var(--st-stage); }
.st-page .st-sec.st-tight { padding: 4.5rem 0; }

/* Centred chapter opener: one statement, one clarifying line, nothing else. */
.st-open { max-width: 44rem; margin-inline: auto; text-align: center; }
.st-open .eyebrow { justify-content: center; }
.st-h2 {
  font-family: 'Inter', system-ui, sans-serif; font-weight: 700; color: var(--ink);
  font-size: clamp(1.95rem, 4.4vw, 3.05rem); letter-spacing: -0.035em; line-height: 1.06;
  margin: 1.1rem 0 1.1rem; text-wrap: balance;
}
.st-sub { font-size: clamp(1rem, 1.5vw, 1.2rem); color: var(--muted); line-height: 1.6; text-wrap: pretty; }
.st-sub strong { color: var(--ink); font-weight: 600; }
.st-page p + p { margin-top: 1rem; }
.st-mono { font-family: 'JetBrains Mono', ui-monospace, monospace; }

/* ---- hero ---------------------------------------------------------------- */
.st-hero { padding: 5.5rem 0 0; text-align: center; overflow: hidden; }
.st-hero .eyebrow { justify-content: center; }
.st-h1 {
  font-family: 'Inter', system-ui, sans-serif; font-weight: 700; color: var(--ink);
  font-size: clamp(2.9rem, 8vw, 5.4rem); letter-spacing: -0.05em; line-height: 0.97;
  margin: 1.2rem 0 1.3rem; text-wrap: balance;
}
.st-h1 em { font-style: normal; color: var(--accent-deep); }
.st-hero .st-sub { max-width: 40rem; margin-inline: auto; }
.st-cta { display: flex; flex-wrap: wrap; gap: 0.75rem; justify-content: center; margin-top: 2.2rem; }
.st-hero-shot { width: var(--st-shot); margin: 3.5rem auto 0; }
.st-hero-shot img {
  width: 100%; height: auto; border-radius: 16px; border: 1px solid var(--line);
  box-shadow: 0 30px 70px -30px rgba(20, 22, 45, 0.35);
}
@media (prefers-color-scheme: dark) { .st-hero-shot img { box-shadow: 0 30px 70px -30px rgba(0, 0, 0, 0.8); } }

/* ---- the film ------------------------------------------------------------
   Not a player — a moving hero image. No chrome, no controls, no play button.
   The screenshot underneath is both the poster and what a phone gets instead,
   so there is only ever one image to load. There are two cuts of the film, one
   lit for each theme; the script below fetches whichever one this reader's
   system asks for, and swaps if they change their mind mid-play. */
.st-film { position: relative; border-radius: 16px; overflow: hidden; }
.st-film picture, .st-film video { display: block; width: 100%; }
.st-film video {
  position: absolute; inset: 0; height: 100%; background: var(--st-film-bg);
  opacity: 0; transition: opacity .55s ease;
}
.st-film.rolling video { opacity: 1; }
.st-film.rolling picture { opacity: 0; transition: opacity .55s ease; }
/* One affordance, and only on hover: a way back to the beginning. */
.st-replay {
  position: absolute; right: 16px; bottom: 14px; border: 0; cursor: pointer;
  font-family: 'JetBrains Mono', ui-monospace, monospace; font-size: 0.62rem;
  letter-spacing: 0.12em; text-transform: uppercase; color: rgba(255, 255, 255, 0.8);
  background: rgba(8, 9, 12, 0.55); border-radius: 999px; padding: 0.5em 0.95em;
  backdrop-filter: blur(6px); opacity: 0; transition: opacity .25s, color .2s;
}
.st-film:hover .st-replay, .st-replay:focus-visible { opacity: 1; }
.st-replay:hover { color: #fff; }
.st-replay:focus-visible { outline: 2px solid var(--accent); outline-offset: 3px; }

/* ---- contents rail ------------------------------------------------------
   Lives in the left gutter on a screen wide enough to have one, and nowhere
   else. It marks where you are; it is not the primary way to navigate. */
/* Sits in the gutter, never over the text: 124px of rail plus a 72px gap has to
   fit beside the 1320px column, which needs 1780px of viewport to exist at all. */
.st-toc {
  display: none; position: fixed; left: calc((100vw - var(--max)) / 2 - 196px);
  top: 50%; transform: translateY(-50%); z-index: 40; width: 124px;
}
.st-toc ol { list-style: none; display: flex; flex-direction: column; gap: 0.1rem; }
.st-toc a {
  display: block; padding: 0.34rem 0 0.34rem 0.85rem; position: relative;
  font-size: 0.78rem; line-height: 1.3; color: var(--faint);
  border-left: 1px solid var(--line); transition: color .22s, border-color .22s;
}
.st-toc a:hover { color: var(--body); }
.st-toc a[aria-current="true"] { color: var(--ink); font-weight: 600; border-left-color: var(--accent); }
.st-toc a[aria-current="true"]::before {
  content: ""; position: absolute; left: -3px; top: 50%; margin-top: -2.5px;
  width: 5px; height: 5px; border-radius: 50%; background: var(--accent);
}
.js .st-toc { opacity: 0; pointer-events: none; transition: opacity .4s ease; }
.js .st-toc.on { opacity: 1; pointer-events: auto; }
@media (min-width: 1780px) { .st-toc { display: block; } }

/* ---- the fact strip under the hero --------------------------------------- */
/* 165px, not 190: five facts have to sit on one row down to the 940px breakpoint
   where the borders flip from left to top, or the fifth wraps and wears a left
   border in the middle of nowhere. */

/* ---- source ⇄ concept: code pinned, notes scrolling past ------------------ */
.st-source { display: grid; grid-template-columns: minmax(0, 1.18fr) minmax(0, 0.82fr); gap: 3.8rem; align-items: start; margin-top: 3.5rem; }
/* min-width:0 — without it the grid track sizes to the listing's max-content and
   the panel pushes the page sideways instead of scrolling inside itself. */
.st-pin { position: sticky; top: 84px; min-width: 0; }
.st-notes { display: flex; flex-direction: column; gap: 5rem; padding: 1rem 0 5rem; }
.st-note h3 { font-size: 1.28rem; font-weight: 650; color: var(--ink); letter-spacing: -0.015em; line-height: 1.25; margin-bottom: 0.6rem; }
.st-note p { color: var(--muted); font-size: 0.98rem; line-height: 1.65; max-width: 30rem; }
.st-note .st-tick { font-family: 'JetBrains Mono', ui-monospace, monospace; font-size: 0.6rem; letter-spacing: 0.12em; text-transform: uppercase; color: var(--accent); display: block; margin-bottom: 0.55rem; }

/* ---- code panels --------------------------------------------------------- */
.st-code { background: var(--code-bg); border: 1px solid var(--line); border-radius: 14px; overflow: hidden; }
.st-code-bar { display: flex; flex-wrap: wrap; gap: 0.4rem 1rem; align-items: baseline; padding: 0.7rem 1.1rem; border-bottom: 1px solid var(--line); font-family: 'JetBrains Mono', ui-monospace, monospace; font-size: 0.68rem; letter-spacing: 0.04em; color: var(--muted); }
.st-code-bar b { color: var(--ink); font-weight: 600; }
.st-code-bar .st-r { margin-left: auto; }
.st-code pre { margin: 0; padding: 1.15rem 0; overflow-x: auto; font-family: 'JetBrains Mono', ui-monospace, monospace; font-size: 0.86rem; line-height: 1.85; }
/* Each line is its own block, so the newlines between them in the source must
   collapse — otherwise <pre> renders the whole listing double-spaced. */
.st-code code { display: block; min-width: max-content; white-space: normal; }
.st-code .l { display: block; padding: 0 1.35rem; color: var(--body); white-space: pre; }
.st-code .c { color: var(--faint); }
.st-code .k { color: var(--accent); }
.st-code .s { color: var(--st-ok); }
/* Scroll-linked emphasis: the group the reader is on stays lit, the rest recede.
   Only armed once the script confirms it can drive it, so with no JS every line
   is fully legible. */
.st-lit .l { transition: opacity .35s ease; opacity: 0.42; }
.st-lit .l.on { opacity: 1; }
@media (prefers-reduced-motion: reduce) { .st-lit .l { transition: none; } }

/* ---- the diff ------------------------------------------------------------ */
.st-diff { max-width: 60rem; margin: 2.6rem auto 0; }
.st-diff pre { font-size: 0.76rem; line-height: 1.75; }
.st-diff .l-h { color: var(--faint); }
.st-diff .l-a { background: color-mix(in srgb, var(--st-ok) 13%, transparent); color: var(--ink); }
.st-diff .l-r { background: color-mix(in srgb, #d1435b 13%, transparent); color: var(--ink); }
.st-code-bar .st-plus { color: var(--st-ok); }
.st-code-bar .st-minus { color: #d1435b; }
.st-reads { list-style: none; max-width: 60rem; margin: 1.6rem auto 0; display: grid; gap: 0.9rem; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); }
.st-reads li { border-top: 2px solid var(--accent); padding-top: 0.7rem; font-size: 0.9rem; color: var(--muted); line-height: 1.6; }
.st-reads b { display: block; color: var(--ink); font-weight: 600; font-size: 0.86rem; margin-bottom: 0.2rem; }

/* ---- a listing that spans more than one file -----------------------------
   The agent's workspace is three files and the point is that it is three
   files, so they are shown in one panel with the boundaries drawn rather than
   in three panels the reader has to assemble. */
.st-file {
  color: var(--faint); border-top: 1px solid var(--line); margin-top: 0.55rem;
  padding-top: 0.55rem !important;
}
.st-code .l.st-file:first-child { border-top: 0; margin-top: 0; padding-top: 0 !important; }

/* ---- terminal ------------------------------------------------------------
   A transcript, not a screenshot: it is text, so it is selectable, searchable
   and legible in both themes without a second asset. */
.st-term { max-width: 62rem; margin: 3rem auto 0; }
.st-term pre { font-size: 0.76rem; line-height: 1.8; }
.st-term .l-p { color: var(--accent); }
.st-term .l-c { color: var(--ink); }
.st-term .l-o { color: var(--muted); }
.st-term .l-w { color: var(--st-new); }
.st-term .l-e { color: #d1435b; }
.st-term .l-gap { height: 0.85rem; }
.st-term .l-ok { color: var(--st-ok); }

/* ---- reference tables ---------------------------------------------------- */
/* min() so the track can still shrink below its ideal width on a phone, rather
   than forcing the page wider than the viewport. */
.st-ref { margin-top: 3rem; display: grid; gap: 2.6rem; grid-template-columns: repeat(auto-fit, minmax(min(400px, 100%), 1fr)); }
.st-ref h3 { font-family: 'JetBrains Mono', ui-monospace, monospace; font-size: 0.66rem; letter-spacing: 0.14em; text-transform: uppercase; color: var(--accent); margin-bottom: 0.8rem; }
.st-ref table { width: 100%; border-collapse: collapse; font-size: 0.86rem; }
.st-ref th, .st-ref td { text-align: left; padding: 0.6rem 0.9rem 0.6rem 0; border-bottom: 1px solid var(--line); vertical-align: top; }
.st-ref thead th { font-family: 'JetBrains Mono', ui-monospace, monospace; font-size: 0.6rem; letter-spacing: 0.1em; text-transform: uppercase; color: var(--muted); font-weight: 600; }
.st-ref tbody th { font-family: 'JetBrains Mono', ui-monospace, monospace; font-size: 0.76rem; font-weight: 500; color: var(--ink); white-space: nowrap; padding-right: 1.2rem; }
.st-ref td { color: var(--muted); }
.st-ref tbody tr:last-child th, .st-ref tbody tr:last-child td { border-bottom: 0; }

/* ---- plates -------------------------------------------------------------- */
.st-plate { width: var(--st-shot); margin: 3.2rem auto 0; }
.st-plate img { width: 100%; height: auto; border: 1px solid var(--line); border-radius: 16px; }
.st-plate figcaption { max-width: 46rem; margin: 1.1rem auto 0; text-align: center; font-size: 0.86rem; color: var(--muted); line-height: 1.6; }
.st-plate .st-pl { font-family: 'JetBrains Mono', ui-monospace, monospace; font-size: 0.62rem; letter-spacing: 0.14em; text-transform: uppercase; color: var(--accent); display: block; margin-bottom: 0.35rem; }

/* ---- the lineage figure -------------------------------------------------- */
.st-fig { max-width: 46rem; margin: 3rem auto 0; }
.st-fig svg { width: 100%; height: auto; display: block; }
.st-fig figcaption { font-size: 0.86rem; color: var(--muted); line-height: 1.6; margin-top: 1.4rem; text-align: center; }
.st-fig figcaption i { color: var(--ink); font-style: italic; }
.st-n-box { fill: var(--paper); stroke: var(--line); }
.st-n-src { stroke: var(--accent); }
.st-n-side { stroke-dasharray: 4 3; }
.st-n-t { fill: var(--ink); font-family: 'JetBrains Mono', ui-monospace, monospace; font-size: 11px; }
.st-n-s { fill: var(--muted); font-family: 'Inter', system-ui, sans-serif; font-size: 9.5px; letter-spacing: 0.05em; text-transform: uppercase; }
.st-sig { fill: var(--accent); font-family: 'JetBrains Mono', ui-monospace, monospace; font-size: 10px; font-weight: 700; }
.st-desc { stroke: var(--faint); stroke-width: 1; fill: none; }
.st-blip { fill: var(--accent); }
/* The descent draws itself top-down when the figure comes into view: archetypes,
   then the bus they share, then the witnesses. Pure CSS off a .reveal class, so
   the end state is the drawn line whether or not it ever animated. */
@media (prefers-reduced-motion: no-preference) {
  .js .reveal .st-desc { stroke-dasharray: 460; stroke-dashoffset: 460; }
  .js .reveal.in .st-desc { animation: st-draw .5s ease-out both; }
  .js .reveal.in .st-d1 { animation-delay: .1s; }
  .js .reveal.in .st-d2 { animation-delay: .45s; animation-duration: .7s; }
  .js .reveal.in .st-d3 { animation-delay: 1s; }
}
@keyframes st-draw { to { stroke-dashoffset: 0; } }

/* ---- layer stack --------------------------------------------------------- */
.st-stack { max-width: 52rem; margin: 3rem auto 0; display: flex; flex-direction: column; gap: 0.5rem; }
.st-layer { display: grid; grid-template-columns: 9.5rem 1fr; gap: 1.5rem; align-items: baseline; background: var(--surface); border: 1px solid var(--line); border-radius: 12px; padding: 1rem 1.3rem; }
.st-layer b { font-family: 'JetBrains Mono', ui-monospace, monospace; font-size: 0.74rem; color: var(--ink); font-weight: 600; }
.st-layer span { font-size: 0.9rem; color: var(--muted); line-height: 1.55; }
.st-layer.st-pure { border-color: color-mix(in srgb, var(--accent) 40%, var(--line)); }
.st-arrow { text-align: center; color: var(--faint); font-size: 0.8rem; line-height: 1; }



/* ---- roadmap ------------------------------------------------------------- */
.st-chip { font-family: 'JetBrains Mono', ui-monospace, monospace; font-size: 0.58rem; letter-spacing: 0.09em; text-transform: uppercase; font-weight: 600; border-radius: 999px; padding: 0.3em 0.7em; border: 1px solid currentColor; white-space: nowrap; }
.st-c-ok { color: var(--st-ok); }
.st-c-tgt { color: var(--st-tgt); }
.st-c-new { color: var(--st-new); }
.st-road { display: grid; gap: 1.6rem 2.5rem; grid-template-columns: repeat(auto-fit, minmax(270px, 1fr)); margin-top: 3rem; }
.st-road section { border-top: 2px solid currentColor; padding-top: 1.1rem; }
.st-road h3 { display: flex; align-items: baseline; gap: 0.6rem; font-size: 1.1rem; font-weight: 700; color: var(--ink); margin-bottom: 0.9rem; }
.st-road ul { list-style: none; display: flex; flex-direction: column; gap: 0.7rem; }
.st-road li { font-size: 0.9rem; color: var(--body); line-height: 1.5; padding-left: 1rem; position: relative; }
.st-road li::before { content: ""; position: absolute; left: 0; top: 0.6em; width: 5px; height: 5px; border-radius: 50%; background: currentColor; }
.st-road b { color: var(--ink); font-weight: 600; }

/* ---- questions ----------------------------------------------------------
   The objections an evaluator arrives with, in the order they arrive. Set as
   a reading column rather than an accordion: every answer is short enough to
   read, and hiding them behind a click would hide them from a crawler too. */
.st-faq { max-width: 48rem; margin: 3rem auto 0; }
.st-faq > div { padding: 1.5rem 0; border-bottom: 1px solid var(--line); }
.st-faq > div:last-child { border-bottom: 0; }
.st-faq h3 { font-size: 1.08rem; font-weight: 650; color: var(--ink); letter-spacing: -0.015em; margin-bottom: 0.55rem; text-wrap: balance; }
.st-faq p { font-size: 0.95rem; color: var(--body); line-height: 1.68; text-wrap: pretty; }
.st-faq code { font-family: 'JetBrains Mono', ui-monospace, monospace; font-size: 0.85em; color: var(--ink); }

/* ---- repository ---------------------------------------------------------- */
.st-repo { max-width: 44rem; margin: 2.6rem auto 0; text-align: center; }
.st-plain { font-size: 0.82rem; color: var(--faint); margin-top: 1.6rem; }
.st-plain a { color: var(--muted); text-decoration: underline; text-underline-offset: 2px; }
.st-plain a:hover { color: var(--accent-deep); }
.st-repo-url {
  display: inline-flex; align-items: center; gap: 0.7rem; font-family: 'JetBrains Mono', ui-monospace, monospace;
  font-size: 0.95rem; color: var(--ink); background: var(--surface); border: 1px solid var(--line);
  border-radius: 999px; padding: 0.7rem 1.4rem; transition: border-color .2s, transform .2s;
}
.st-repo-url:hover { color: var(--ink); border-color: var(--accent); transform: translateY(-1px); }
.st-repo-url svg { width: 17px; height: 17px; flex: none; }
.st-repo p { font-size: 0.95rem; color: var(--muted); line-height: 1.65; margin-top: 1.4rem; }

/* ---- two layers ----------------------------------------------------------- */
.st-two { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1.4rem; max-width: 64rem; margin: 2.6rem auto 0; }
.st-two-card { border: 1px solid var(--line); border-radius: 16px; padding: 1.7rem 1.8rem; background: var(--paper); }
.st-two-text { border-color: color-mix(in srgb, var(--accent) 40%, var(--line)); }
.st-two-k { font-family: 'JetBrains Mono', ui-monospace, monospace; font-size: 0.7rem; letter-spacing: 0.12em; text-transform: uppercase; color: var(--accent); font-weight: 600; }
.st-two-card h3 { margin: 0.7rem 0 0.6rem; font-size: 1.15rem; color: var(--ink); }
.st-two-card p { font-size: 0.95rem; color: var(--body); line-height: 1.65; }
.st-two-card ul { margin-top: 1rem; padding-left: 1.1rem; display: flex; flex-direction: column; gap: 0.4rem; font-size: 0.9rem; color: var(--muted); }
@media (max-width: 820px) { .st-two { grid-template-columns: minmax(0, 1fr); } }

/* ---- colophon: the small print, pitched as small print ------------------- */
.st-colophon { border-top: 1px solid var(--line); padding: 2.2rem 0 3rem; }
.st-colophon p { max-width: 44rem; font-size: 0.84rem; line-height: 1.65; color: var(--faint); }

/* ---- responsive ---------------------------------------------------------- */
@media (max-width: 940px) {
  .st-source { grid-template-columns: minmax(0, 1fr); gap: 2rem; }
  .st-pin { position: static; }
  .st-notes { gap: 2.2rem; padding: 0 0 1rem; }
  .st-note p { max-width: none; }
  /* Nothing is pinned any more, so nothing can drive the emphasis. */
  .st-lit .l { opacity: 1; }
}
/* No film on a phone or a tablet. The script checks the same width before it
   attaches a source, so this is belt and braces rather than the mechanism. */
@media (max-width: 820px) {
  .st-film video, .st-film .st-replay { display: none; }
}
@media (max-width: 700px) {
  .st-page .st-sec { padding: 4rem 0; }
  .st-page .st-sec.st-tight { padding: 2.5rem 0; }
  .st-hero { padding-top: 3rem; }
  .st-hero-shot { margin-top: 2.2rem; }
  .st-layer { grid-template-columns: 1fr; gap: 0.3rem; }
  .st-code pre { font-size: 0.66rem; }
  .st-term pre { font-size: 0.6rem; }
  .st-plate img, .st-hero-shot img { border-radius: 10px; }
}
</style>
CSS;

// One description does double duty: Google cuts around 155 characters and the social cards
// show two or three lines, so the differentiator has to survive in the first clause.
echo page_head(
    'Stemma: the architecture model that is your source code',
    'An architecture model that lives in your repo as C# files. People edit it on a canvas, agents edit it as text, and the diagram cannot drift from the model.',
    $url,
    $extra,
    cfg('site_url') . '/assets/img/stemma/og.jpg'
);

echo site_nav('stemma');

$ghIcon = '<svg fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 0C5.374 0 0 5.373 0 12c0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23A11.509 11.509 0 0112 5.803c1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576C20.566 21.797 24 17.3 24 12c0-6.627-5.373-12-12-12z"/></svg>';
?>

<main class="st-page">

  <!-- Contents. Rendered always, shown only where there is a gutter to put it in;
       without script it is still a working list of anchors. -->
  <nav class="st-toc" id="st-toc" aria-label="On this page">
    <ol>
      <li><a href="#layers">Two layers</a></li>
      <li><a href="#source">The source</a></li>
      <li><a href="#edit">The edit</a></li>
      <li><a href="#vocabulary">The vocabulary</a></li>
      <li><a href="#agents">The text layer</a></li>
      <li><a href="#views">The views</a></li>
      <li><a href="#books">Story books</a></li>
      <li><a href="#shape">The shape of it</a></li>
      <li><a href="#roadmap">Roadmap</a></li>
      <li><a href="#questions">Questions</a></li>
      <li><a href="#repository">The repository</a></li>
    </ol>
  </nav>

  <!-- ==================== HERO ==================== -->
  <section class="st-hero" id="st-top">
    <div class="wrap">
      <?= eyebrow('Stemma', true) ?>
      <h1 class="st-h1">The diagram <em>is</em> the code.</h1>
      <p class="st-sub">
        Stemma is my side project: an architecture model that lives in your repository as plain C# files.
        People work with it on a canvas. Agents work with it as text. Both change the same files, so the
        picture and the model never go separate ways.
      </p>
      <p class="st-cta">
        <a class="btn btn-primary" href="#repository">Where to find it <?= arrow_icon() ?></a>
        <a class="btn btn-ghost" href="#layers">How the two layers work</a>
      </p>
    </div>
    <!-- The screenshot is the poster. On a phone that is all it ever is: the video
         element and its controls are display:none there and carry preload="none",
         so nothing of the film is fetched on a connection that didn't ask for it. -->
    <div class="st-hero-shot">
      <div class="st-film" id="st-film">
        <picture>
          <source srcset="/assets/img/stemma/canvas-dark.webp" media="(prefers-color-scheme: dark)">
          <img src="/assets/img/stemma/canvas-light.webp" width="2400" height="1470" fetchpriority="high" decoding="async"
               alt="The Stemma canvas: three bounded contexts as lanes, Ordering, Payments and Fulfilment, with modules inside them, labelled data flows between them, and external systems along the top.">
        </picture>
        <!-- No src attributes: the sources are attached by script, and only on a
             viewport wide enough to show the film. Nothing downloads otherwise —
             and only ever one of the two cuts, the one this reader's system asks for. -->
        <video id="st-video" preload="none" muted playsinline disablepictureinpicture
               width="1920" height="1080" aria-label="The Stemma film, two minutes, silent"
               data-dark-webm="/assets/video/stemma-film-dark.webm" data-dark-mp4="/assets/video/stemma-film-dark.mp4"
               data-light-webm="/assets/video/stemma-film-light.webm" data-light-mp4="/assets/video/stemma-film-light.mp4"></video>
        <button type="button" class="st-replay" id="st-replay" hidden>Replay</button>
      </div>
    </div>
  </section>

  <!-- ==================== TWO LAYERS ====================
       The idea of the tool in one screen: the canvas aligns people with each
       other, the text files align people with agents. Only shipped behaviour is
       claimed here; the STEMMA.md brief is still on the roadmap. -->
  <section class="st-sec st-two-wrap" id="layers">
    <div class="wrap">
      <div class="st-open reveal">
        <?= eyebrow('Two layers, one model') ?>
        <h2 class="st-h2">One for people, one for people and agents.</h2>
        <p class="st-sub">
          The model has two faces, and both are the same files in Git. I built it like this because
          an architecture today has two audiences: the people who decide and build, and the agents
          that write more and more of the code. The second audience is why I think architects should
          write what agents read; the long version is
          <a href="/blog/every-approval-is-a-missing-rule/">Every Approval Is a Missing Rule</a>.
        </p>
      </div>
      <div class="st-two reveal">
        <div class="st-two-card">
          <span class="st-two-k">Visual layer · for people</span>
          <h3>Agreement in the room.</h3>
          <p>The canvas, the views and the story books. Here an architect, a team and the people who pay
            for the work look at the same picture and agree on it. Every edit on the canvas is a small
            Roslyn rewrite of the C# file, so agreeing on the picture means agreeing on the model.</p>
          <ul>
            <li>Module map and dependency graph, plus your own saved views</li>
            <li>Story books that drive the canvas in a review meeting</li>
            <li>A one-file HTML report for people who will not install anything</li>
          </ul>
        </div>
        <div class="st-two-card st-two-text">
          <span class="st-two-k">Text layer · for people and agents</span>
          <h3>Agreement with the agents.</h3>
          <p>The same model as three text files: <span class="st-mono">Architecture.cs</span>, one file per
            view, and a layout sidecar with a JSON Schema. An agent reads and writes them, checks them and
            renders them from a shell, and a human reviews the result as a normal diff. You get this
            out of the box: no plugin, no API key, no export step.</p>
          <ul>
            <li><span class="st-mono">stemma validate</span> catches broken ids the compiler cannot see</li>
            <li><span class="st-mono">stemma render</span> draws any view to PNG or SVG, no browser open</li>
            <li><span class="st-mono">--check</span> fails the build when a committed image is out of date</li>
          </ul>
        </div>
      </div>
    </div>
  </section>

  <!-- ==================== SOURCE ⇄ CONCEPT ==================== -->
  <section class="st-sec" id="source">
    <div class="wrap">
      <div class="st-open reveal">
        <?= eyebrow('The source') ?>
        <h2 class="st-h2">Your model is a file you can read.</h2>
        <p class="st-sub">
          No project format, no XML, no new notation. The model is C# records in
          <span class="st-mono">Architecture/Architecture.cs</span>, and anyone on the team, or any agent, can open
          it in an editor and follow it.
        </p>
      </div>

      <div class="st-source">
        <div class="st-pin">
          <div class="st-code st-lit" id="st-src">
            <div class="st-code-bar"><b>Architecture/Architecture.cs</b><span class="st-r">excerpt</span></div>
<pre><code><span class="l on" data-g="ctx"><span class="c">// Bounded contexts</span></span><span class="l on" data-g="ctx">var ctxOrdering   = <span class="k">new </span>BoundedContext(<span class="s">"ctx_ordering"</span>, <span class="s">"Ordering"</span>);</span><span class="l on" data-g="ctx">var ctxPayments   = <span class="k">new </span>BoundedContext(<span class="s">"ctx_payments"</span>, <span class="s">"Payments"</span>);</span><span class="l on" data-g="ctx">var ctxFulfilment = <span class="k">new </span>BoundedContext(<span class="s">"ctx_fulfilment"</span>, <span class="s">"Fulfilment"</span>);</span><span class="l on" data-g="ctx"> </span><span class="l" data-g="mod"><span class="c">// Modules, each declaring the context it belongs to</span></span><span class="l" data-g="mod">var modCart   = <span class="k">new </span>Module(<span class="s">"mod_cart"</span>, <span class="s">"Cart"</span>, <span class="s">"ctx_ordering"</span>);</span><span class="l" data-g="mod">var modOrder  = <span class="k">new </span>Module(<span class="s">"mod_order"</span>, <span class="s">"Order Lifecycle"</span>, <span class="s">"ctx_ordering"</span>);</span><span class="l" data-g="mod">var modAuth   = <span class="k">new </span>Module(<span class="s">"mod_auth"</span>, <span class="s">"Authorisation"</span>, <span class="s">"ctx_payments"</span>);</span><span class="l" data-g="mod">var modLedger = <span class="k">new </span>Module(<span class="s">"mod_ledger"</span>, <span class="s">"Ledger"</span>, <span class="s">"ctx_payments"</span>);</span><span class="l" data-g="mod"> </span><span class="l" data-g="flow"><span class="c">// A relationship carries what moves, not just that</span></span><span class="l" data-g="flow"><span class="c">// something moves</span></span><span class="l" data-g="flow">var flowOrdered  = <span class="k">new </span>DataFlow(<span class="s">"flow_ordered"</span>,</span><span class="l" data-g="flow">      <span class="s">"mod_order"</span>, <span class="s">"mod_auth"</span>, <span class="s">"OrderPlaced"</span>);</span><span class="l" data-g="flow">var depSettleAuth = <span class="k">new </span>Dependency(<span class="s">"dep_settle_auth"</span>,</span><span class="l" data-g="flow">      <span class="s">"mod_settlement"</span>, <span class="s">"mod_auth"</span>, <span class="s">"uses"</span>);</span><span class="l" data-g="flow"> </span><span class="l" data-g="tag"><span class="c">// Where a thing is in its life, and who answers for it</span></span><span class="l" data-g="tag">var tagLedger = Tag.For(modLedger,</span><span class="l" data-g="tag">    lifecycle: <span class="k">new </span>Lifecycle(Status: <span class="s">"to-be-created"</span>, Phase: <span class="s">"Q4"</span>),</span><span class="l" data-g="tag">    ownership: <span class="k">new </span>Ownership(Squad: <span class="s">"Money"</span>, Domain: <span class="s">"Payments"</span>));</span><span class="l" data-g="tag"> </span><span class="l" data-g="risk"><span class="c">// The parts of a design that have no box</span></span><span class="l" data-g="risk">var riskDualWrite = <span class="k">new </span>Risk(<span class="s">"risk_dualwrite"</span>,</span><span class="l" data-g="risk">    <span class="s">"Order and Ledger are written in two transactions"</span>,</span><span class="l" data-g="risk">    <span class="s">"mod_ledger"</span>);</span><span class="l" data-g="risk">var qRefund = <span class="k">new </span>Question(<span class="s">"q_refund"</span>,</span><span class="l" data-g="risk">    <span class="s">"Who owns a partial refund after delivery?"</span>, <span class="s">"mod_ledger"</span>);</span></code></pre>
          </div>
        </div>

        <div class="st-notes">
          <div class="st-note" data-g="ctx">
            <span class="st-tick">Bounded contexts</span>
            <h3>Contexts are declared, not drawn.</h3>
            <p>A bounded context is a record with an id and a name. On the canvas it is a lane and modules sit
              inside it, but the nesting is a real reference in the file, not a position on a grid. Move a
              module to another lane and one string argument changes.</p>
          </div>
          <div class="st-note" data-g="mod">
            <span class="st-tick">Elements</span>
            <h3>Ids are stable, names are not.</h3>
            <p>Each element has an id that never changes and a display name that can. Flows, dependencies,
              tags and views point at the id, so you can rename a concept when you understand it better and
              nothing breaks.</p>
          </div>
          <div class="st-note" data-g="flow">
            <span class="st-tick">Relationships</span>
            <h3>An arrow says what it carries.</h3>
            <p>A <span class="st-mono">DataFlow</span> names its payload and its direction. A
              <span class="st-mono">Dependency</span> names its kind: uses, calls, reads, publishes or consumes.
              A link is drawn in a view only when both ends are in that view, so a filtered view never
              shows an arrow going nowhere.</p>
          </div>
          <div class="st-note" data-g="tag">
            <span class="st-tick">Lifecycle &amp; ownership</span>
            <h3>Target state lives in the model, not in a deck.</h3>
            <p><span class="st-mono">Lifecycle</span> marks a thing as current, target, to-adapt, to-be-created or
              deprecated, with a phase and validity dates. <span class="st-mono">Ownership</span> adds a squad, a
              domain and <a href="/blog/decisions-have-an-architecture-too/">RAPID roles</a>. Both work on elements <em>and</em> on relationships, and the
              relationships are usually where a migration plan goes wrong.</p>
          </div>
          <div class="st-note" data-g="risk">
            <span class="st-tick">Concerns</span>
            <h3>Risks and questions are model elements.</h3>
            <p>A risk, an open question or an assumption is an element attached to what it is about. It shows
              as a dotted edge to its subject, it is versioned with it, and it appears on the Concerns board.
              Not in a wiki page nobody opens. Why I treat
              <a href="/blog/questions-are-an-architects-sharpest-tool/">open questions as a deliverable</a>
              is a separate essay.</p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ==================== THE EDIT ==================== -->
  <section class="st-sec st-stage" id="edit">
    <div class="wrap">
      <div class="st-open reveal">
        <?= eyebrow('The edit') ?>
        <h2 class="st-h2">One rename. Three lines. Nothing else moved.</h2>
        <p class="st-sub">
          I double-clicked the <span class="st-mono">Ledger</span> box on the canvas, typed a longer name and
          pressed Enter. This is the <span class="st-mono">git diff</span> I got: all three hunks, without the file
          header, and with two long lines cut at the right margin.
        </p>
      </div>

      <div class="st-diff st-code reveal">
        <div class="st-code-bar"><b>git diff</b><span>after renaming one element in the UI</span><span class="st-r">1 file changed · <span class="st-plus">+3</span> <span class="st-minus">−3</span></span></div>
<pre><code><span class="l l-h">@@ -39,7 +39,7 @@ public static class Architecture</span>
<span class="l">         var modAuth = new Module("mod_auth", "Authorisation", "ctx_payments");</span>
<span class="l">         var modSettlement = new Module("mod_settlement", "Settlement", "ctx_payments");</span>
<span class="l l-r">-        var modLedger = new Module("mod_ledger", "Ledger", "ctx_payments");</span>
<span class="l l-a">+        var modLedgerReconciliation = new Module("mod_ledger", "Ledger &amp; Reconciliation", "ctx_payments");</span>
<span class="l"> </span>
<span class="l">         var modAllocation = new Module("mod_allocation", "Stock Allocation", "ctx_fulfilment");</span>
<span class="l">         var modShipping = new Module("mod_shipping", "Shipping", "ctx_fulfilment");</span>
<span class="l l-h">@@ -86,7 +86,7 @@ public static class Architecture</span>
<span class="l">         var tagSettlement = Tag.For(modSettlement, lifecycle: new Lifecycle(Status: "target", …</span>
<span class="l l-r">-        var tagLedger = Tag.For(modLedger, lifecycle: new Lifecycle(Status: "to-be-created", …</span>
<span class="l l-a">+        var tagLedger = Tag.For(modLedgerReconciliation, lifecycle: new Lifecycle(Status: "to-be-created", …</span>
<span class="l">         var tagAllocation = Tag.For(modAllocation, lifecycle: new Lifecycle(Status: "current"), …</span>
<span class="l l-h">@@ -98,7 +98,7 @@ public static class Architecture</span>
<span class="l">             ctxOrdering, ctxPayments, ctxFulfilment,</span>
<span class="l">             modCart, modPricing, modOrder,</span>
<span class="l l-r">-            modAuth, modSettlement, modLedger,</span>
<span class="l l-a">+            modAuth, modSettlement, modLedgerReconciliation,</span>
<span class="l">             modAllocation, modShipping,</span>
</code></pre>
      </div>

      <ul class="st-reads reveal">
        <li><b>The declaration</b>The display name changed. The id stayed, so every other reference still works.</li>
        <li><b>The local variable, twice</b>Roslyn renamed the symbol, so the tag and the list followed it. A find-and-replace would miss one, and the code would still compile.</li>
        <li><b>Nothing else</b>No reordering, no reformatting, no moved blank line, no lost comment. The reviewer sees a rename, because it was a rename.</li>
      </ul>

      <p class="st-kick reveal" style="text-align:center">
        Everything else in the tool depends on this. A suite of small, realistic and deliberately nasty
        fixtures runs every operation and compares the diff byte by byte, and the codebase has one fixed
        rule about it: <strong>when the fidelity suite fails, the implementation is wrong, not the
        test</strong>. A diff that only says what changed is also what makes a change reviewable when
        nobody remembers writing it, which I wrote about in
        <a href="/blog/we-deleted-the-wrong-half-of-agile/">We Deleted the Wrong Half of Agile</a>.
      </p>
    </div>
  </section>

  <!-- ==================== VOCABULARY ==================== -->
  <section class="st-sec" id="vocabulary">
    <div class="wrap">
      <div class="st-open reveal">
        <?= eyebrow('The vocabulary') ?>
        <h2 class="st-h2">Everything you are allowed to say.</h2>
        <p class="st-sub">
          More than a class diagram, less than an IDE. This is the whole language, there is no
          hidden second part.
        </p>
      </div>

      <div class="st-ref reveal">
        <div>
          <h3>Elements</h3>
          <table>
            <thead><tr><th>Record</th><th>Carries</th></tr></thead>
            <tbody>
              <tr><th>Person</th><td>An actor with a role: external, internal or user</td></tr>
              <tr><th>SoftwareSystem</th><td>A system, usually a neighbouring one you don't own</td></tr>
              <tr><th>Container</th><td>A deployable unit inside a system, with a kind: service, worker, cronjob or db</td></tr>
              <tr><th>BoundedContext</th><td>A DDD context; renders as a lane other elements nest inside</td></tr>
              <tr><th>Module</th><td>A unit of cohesion, optionally inside a context</td></tr>
              <tr><th>Capability</th><td>A business capability, optionally inside a context</td></tr>
              <tr><th>UseCase</th><td>A user-visible use case</td></tr>
              <tr><th>Risk</th><td>A design risk, anchored to what it concerns</td></tr>
              <tr><th>Question</th><td>An open design question, anchored the same way</td></tr>
              <tr><th>Assumption</th><td>A standing assumption everything downstream rests on</td></tr>
              <tr><th>Concept</th><td>A named box that asserts <em>nothing</em> about a system, for drawing an argument instead of an architecture</td></tr>
            </tbody>
          </table>
        </div>

        <div>
          <h3>Relationships, metadata, views</h3>
          <table>
            <thead><tr><th>Record</th><th>Carries</th></tr></thead>
            <tbody>
              <tr><th>DataFlow</th><td>From, to, a payload name shown as the edge label, and a direction</td></tr>
              <tr><th>Dependency</th><td>From, to, and a kind: uses, calls, reads, publishes or consumes</td></tr>
              <tr><th>Relation</th><td>From, to, and a free label. An arrow that claims nothing more than its label</td></tr>
              <tr><th>Lifecycle</th><td>Status (current, target, to-adapt, to-be-created, deprecated, or your own word), phase, valid-from, valid-until</td></tr>
              <tr><th>Ownership</th><td>Squad, domain, and RAPID role lists: recommend, agree, perform, input, decide</td></tr>
              <tr><th>Tag.For</th><td>Attaches a lifecycle and/or an ownership to any element <em>or any link</em></td></tr>
              <tr><th>View</th><td>A named subset with a base lens (module map, dependency graph or all), stored as its own <span class="st-mono">.cs</span> file</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </section>

  <!-- ==================== THE AGENT'S HALF ====================
       The second authoring surface. Everything above this point needs a mouse;
       this chapter is the claim that none of it has to. The listing and the
       transcript are both real — the workspace draws a diagram that already
       exists on this site, which is why it was chosen. -->
  <section class="st-sec st-stage" id="agents">
    <div class="wrap">
      <div class="st-open reveal">
        <?= eyebrow('The text layer') ?>
        <h2 class="st-h2">Nobody has to open it.</h2>
        <p class="st-sub">
          This is the text layer up close. A workspace is three text files with a vocabulary that is
          written down, so the canvas is <strong>one</strong> way to edit the model, not the only one. An
          agent with a shell writes the files, checks them and renders the picture. Nothing to install or
          connect for this: it is how the tool works from the first commit. It is the same idea as the
          context repo in <a href="/blog/prompt-driven-architecture/">Prompt-Driven Architecture</a>:
          the text is the asset, and the picture is a build artefact.
        </p>
      </div>

      <div class="st-source">
        <div class="st-pin">
          <div class="st-code st-lit">
            <div class="st-code-bar"><b>One workspace, three files</b><span class="st-r">abbreviated</span></div>
<pre><code><span class="l on st-file" data-g="model"><span class="c">// Architecture/Architecture.cs</span></span><span class="l on" data-g="model">var humanDiff = <span class="k">new </span>Concept(<span class="s">"con_human_diff"</span>, <span class="s">"a colleague's diff"</span>);</span><span class="l on" data-g="model">var ask       = <span class="k">new </span>Concept(<span class="s">"con_ask"</span>, <span class="s">"ask them why"</span>);</span><span class="l on" data-g="model">var answered  = <span class="k">new </span>Concept(<span class="s">"con_answered"</span>, <span class="s">"answered in a sentence"</span>);</span><span class="l on" data-g="model">var agentDiff = <span class="k">new </span>Concept(<span class="s">"con_agent_diff"</span>, <span class="s">"an agent's diff"</span>);</span><span class="l on" data-g="model">var nobody    = <span class="k">new </span>Concept(<span class="s">"con_nobody"</span>, <span class="s">"nobody to ask"</span>);</span><span class="l on" data-g="model">var artifacts = <span class="k">new </span>Concept(<span class="s">"con_artifacts"</span>,</span><span class="l on" data-g="model">      <span class="s">"tests, contracts, decision records"</span>);</span><span class="l st-file" data-g="view"><span class="c">// Views/WhoDoYouAsk.cs</span></span><span class="l" data-g="view"><span class="k">public static </span>View Define() =&gt; <span class="k">new</span>(</span><span class="l" data-g="view">    Id: <span class="s">"view_ask"</span>, Name: <span class="s">"Who do you ask"</span>, BaseView: <span class="s">"all"</span>,</span><span class="l" data-g="view">    ElementIds: <span class="k">new</span>[] { <span class="s">"con_human_diff"</span>, <span class="s">"con_ask"</span>, <span class="s">"con_answered"</span>,</span><span class="l" data-g="view">                        <span class="s">"con_agent_diff"</span>, <span class="s">"con_nobody"</span>,</span><span class="l" data-g="view">                        <span class="s">"con_artifacts"</span> });</span><span class="l st-file" data-g="layout"><span class="c">// stemma.layout.json</span></span><span class="l" data-g="layout"><span class="s">"custom:view_ask"</span>: {</span><span class="l" data-g="layout">  <span class="s">"layout"</span>: {</span><span class="l" data-g="layout">    <span class="s">"kind"</span>: <span class="k">"grid"</span>,</span><span class="l" data-g="layout">    <span class="s">"columns"</span>: [[<span class="s">"con_human_diff"</span>, <span class="s">"con_agent_diff"</span>],</span><span class="l" data-g="layout">                [<span class="s">"con_ask"</span>,        <span class="s">"con_nobody"</span>],</span><span class="l" data-g="layout">                [<span class="s">"con_answered"</span>,   <span class="s">"con_artifacts"</span>]],</span><span class="l" data-g="layout">    <span class="s">"gap"</span>: [60, 40], <span class="s">"align"</span>: <span class="k">"center"</span></span><span class="l" data-g="layout">  },</span><span class="l" data-g="ink">  <span class="s">"shapes"</span>: [</span><span class="l" data-g="ink">    { <span class="s">"id"</span>: <span class="s">"sh_human"</span>, <span class="s">"kind"</span>: <span class="k">"label"</span>, <span class="s">"x"</span>: -140, <span class="s">"y"</span>: 10,</span><span class="l" data-g="ink">      <span class="s">"text"</span>: <span class="k">"human"</span>, <span class="s">"color"</span>: <span class="k">"#767884"</span> }</span><span class="l" data-g="ink">  ]</span><span class="l" data-g="ink">}</span></code></pre>
          </div>
        </div>

        <div class="st-notes">
          <div class="st-note" data-g="model">
            <span class="st-tick">The model</span>
            <h3>A box that asserts nothing.</h3>
            <p>Every other element kind is a claim: <em>this is a bounded context</em>, <em>this is
              deployable</em>. The boxes in an argument claim nothing, and if you force one into a
              <span class="st-mono">Module</span>, you write something false into a file other people will copy.
              <span class="st-mono">Concept</span> and <span class="st-mono">Relation</span> are the way out: a named box and a
              labelled arrow that mean only what they say.</p>
          </div>
          <div class="st-note" data-g="view">
            <span class="st-tick">The view</span>
            <h3>The view is the question.</h3>
            <p>A view names the subset one picture is about. A link is drawn only when <em>both</em> ends are
              in the view. This is the most common reason a diagram looks fine and still misses an arrow, so
              the validator reports this case by name.</p>
          </div>
          <div class="st-note" data-g="layout">
            <span class="st-tick">The layout</span>
            <h3>Describe the arrangement, not the positions.</h3>
            <p>A language model is good at intent and bad at pixels. So the agent writes the intent, three
              columns, sixty apart, centred, and Stemma calculates the coordinates. When you move one box by
              hand, Stemma stores an offset <em>against</em> the constraint instead of replacing it, so the
              arrangement survives the correction and the offset moves with its slot when the grid is solved
              again. <span class="st-mono">row</span>, <span class="st-mono">column</span> and
              <span class="st-mono">stack</span> work the same way.</p>
          </div>
          <div class="st-note" data-g="ink">
            <span class="st-tick">Annotation</span>
            <h3>Ink is not architecture.</h3>
            <p>Swimlane labels, brackets and callouts carry no architectural meaning, so they are free-form
              shapes in the sidecar, never elements. I write this down because agents break this rule first:
              if you want an element only to make the picture look better, what you want is a shape.</p>
          </div>
        </div>
      </div>

      <div class="st-term st-code reveal">
        <div class="st-code-bar"><b>a shell, no browser open</b><span>check it, draw it, then catch it when it gets stale</span><span class="st-r">wrapped to fit</span></div>
<pre><code><span class="l"><span class="l-p">$ </span><span class="l-c">stemma validate --workspace .</span></span>
<span class="l l-o">  info    model/is-a-figure            6 of 6 elements are Concepts, so this is</span>
<span class="l l-o">                                       a figure rather than an architecture,</span>
<span class="l l-o">                                       which is a fine thing to be</span>
<span class="l l-ok">stemma validate: 0 error(s), 0 warning(s)</span>
<span class="l l-gap"> </span>
<span class="l"><span class="l-p">$ </span><span class="l-c">stemma render --workspace . --view view_ask \</span></span>
<span class="l l-c">      --out assets/img/posts/the-bottleneck-moved/who-do-you-ask.png</span>
<span class="l l-o">  who-do-you-ask.png  1180x512  6 nodes</span>
<span class="l l-ok">stemma render: wrote 1 file</span>
<span class="l l-gap"> </span>
<span class="l l-o">  # a fortnight later, after the model moved and the image did not</span>
<span class="l l-gap"> </span>
<span class="l"><span class="l-p">$ </span><span class="l-c">stemma render --check --workspace . --view view_ask \</span></span>
<span class="l l-c">      --out assets/img/posts/the-bottleneck-moved/who-do-you-ask.png</span>
<span class="l l-e">stemma render --check: who-do-you-ask.png is out of date</span>
<span class="l l-e">  elements 6 -&gt; 7, links 5 -&gt; 6, view members 6 -&gt; 7</span>
<span class="l l-w">  # non-zero, so the build stops rather than shipping a stale picture</span>
</code></pre>
      </div>

      <ul class="st-reads reveal">
        <li><b>Deterministic</b>The same input gives the same picture. Every render starts its own short-lived backend, so two renders can run in parallel and neither touches a workspace you have open.</li>
        <li><b>Checkable</b>Every render writes a manifest with a fingerprint of the model, the view, the layout and the profile. <span class="st-mono">--check</span> calculates it again and fails the build when a committed image no longer matches its source.</li>
        <li><b>Written down</b>The sidecar has a published JSON Schema, and every file points to it. The protocol reference in the repo explains the whole chain for someone who never opens the app, with three worked examples that were built and rendered exactly as printed.</li>
      </ul>

      <p class="st-kick reveal" style="margin-top:2.4rem">
        The workspace above draws a diagram that already exists on this blog, the one in
        <a href="/blog/the-bottleneck-moved/">The Bottleneck Moved</a>, first made in draw.io. Getting the
        same picture from a model, with one command and no mouse, transparent and identical on two
        machines, was the acceptance test for this pipeline. The story behind it is less nice. Before,
        the diagrams for a later essay <em>were</em> made from a Stemma model by a 190-line Playwright
        script: it started the app, opened a real browser and dragged every node into place with fake
        mouse events, in three passes, because dragging was the only thing that stayed. It worked, and it
        was also a bug report against my own product.
        <strong><span class="st-mono">stemma render</span> is what deleted it.</strong>
      </p>
    </div>
  </section>

  <!-- ==================== VIEWS ==================== -->
  <section class="st-sec" id="views">
    <div class="wrap">
      <div class="st-open reveal">
        <?= eyebrow('The views') ?>
        <h2 class="st-h2">One model, a different lens for each room.</h2>
        <p class="st-sub">
          A view is a projection, not a copy: a named subset of the same model with its own layout,
          styling and notes. When you switch a view, you change the lens, not the data.
        </p>
      </div>

      <figure class="st-plate reveal">
        <picture>
          <source srcset="/assets/img/stemma/concerns-dark.webp" media="(prefers-color-scheme: dark)">
          <img src="/assets/img/stemma/concerns-light.webp" width="2400" height="1470" loading="lazy" decoding="async"
               alt="The Concerns board in Stemma, listing the model's open question, assumption and two risks, each labelled with the element it is about.">
        </picture>
        <figcaption>
          <span class="st-pl">The Concerns board</span>
          Every question, assumption and risk in the model, each one still pointing at its element.
          Click one and it opens on the map, selected.
        </figcaption>
      </figure>
    </div>
  </section>

  <!-- ==================== STORY BOOKS ==================== -->
  <section class="st-sec" id="books">
    <div class="wrap">
      <div class="st-open reveal">
        <?= eyebrow('Story books') ?>
        <h2 class="st-h2">The review meeting is part of the architecture.</h2>
        <p class="st-sub">
          This is the visual layer at work. Modelling the system is half of the job. The other half is
          forty minutes in front of the people who decide the budget, and usually that is a slide deck,
          rebuilt by hand every quarter, which starts to drift from the model on the same day. And an
          architecture nobody follows did not happen, which is the point of
          <a href="/blog/adoption-is-the-deliverable/">Adoption Is the Deliverable</a>.
        </p>
      </div>

      <div class="st-source" style="margin-top:3rem">
        <div class="st-pin">
          <div class="st-code">
            <div class="st-code-bar"><b>Concepts/view-book.stemma.yaml</b><span class="st-r">excerpt</span></div>
<pre><code><span class="l"><span class="c">books:</span></span><span class="l">  - id: book_steering</span><span class="l">    name: Steering review: extracting Payments</span><span class="l">    audience: <span class="s">leadership</span></span><span class="l">    pages:</span><span class="l">      - viewId: moduleMap</span><span class="l">        title: Where the money moves today</span><span class="l">        narrative: <span class="c">Three contexts. Ordering and Fulfilment are</span></span><span class="l">          <span class="c">already out of the monolith. Payments is the one still</span></span><span class="l">          <span class="c">half in. Settlement is target state for Q3, Ledger does</span></span><span class="l">          <span class="c">not exist yet…</span></span><span class="l"> </span><span class="l">      - viewId: dependencyGraph</span><span class="l">        title: The dependency that sets the order of work</span><span class="l">        narrative: <span class="c">Order Lifecycle still reads the legacy</span></span><span class="l">          <span class="c">monolith. That arrow is why Payments cannot go first…</span></span></code></pre>
          </div>
        </div>

        <div class="st-notes">
          <div class="st-note">
            <span class="st-tick">A page</span>
            <h3>A view, plus what you would actually say.</h3>
            <p>Each page points to a view and has a title and a narrative. Not a caption, but the sentence
              you say when that picture is on the screen, written down so it still works when you are on
              holiday.</p>
          </div>
          <div class="st-note">
            <span class="st-tick">An audience</span>
            <h3>One model, one deck per room.</h3>
            <p>A book has an audience: leadership, engineering, security or operations. The committee that
              funds the work and the team that builds it get a different order, depth and language, from the
              same model, with no second copy to maintain.</p>
          </div>
          <div class="st-note">
            <span class="st-tick">Present mode</span>
            <h3>The canvas follows the story.</h3>
            <p>Open a book and the tool becomes the deck. The canvas switches to each page's view, the
              narrative sits in a strip below it, and prev/next takes the room through it. Drag pages to
              reorder them, edit the narrative in place, and export a multi-page PDF for people who want a
              document.</p>
          </div>
          <div class="st-note">
            <span class="st-tick">Why it matters</span>
            <h3>The deck cannot drift either.</h3>
            <p>A book stores a <em>reference</em> to a view, never a picture of it. When the architecture
              changes, next quarter's walkthrough is drawn again from the current model, so the story you
              told the board and the code you shipped stay one artefact.</p>
          </div>
        </div>
      </div>

      <figure class="st-plate reveal">
        <picture>
          <source srcset="/assets/img/stemma/book-dark.webp" media="(prefers-color-scheme: dark)">
          <img src="/assets/img/stemma/book-light.webp" width="2400" height="1470" loading="lazy" decoding="async"
               alt="Stemma in present mode: a four-page steering review for leadership, with the page strip along the bottom and the narrative for page one below the canvas.">
        </picture>
        <figcaption>
          <span class="st-pl">Present mode</span>
          A four-page steering review for <span class="st-mono">leadership</span>. The strip at the bottom
          is the story, the canvas above it is the model. Nothing was exported to build this.
        </figcaption>
      </figure>
    </div>
  </section>

  <!-- ==================== SHAPE ==================== -->
  <section class="st-sec st-stage" id="shape">
    <div class="wrap">
      <div class="st-open reveal">
        <?= eyebrow('The shape of it') ?>
        <h2 class="st-h2">Four layers, one direction, no second store.</h2>
        <p class="st-sub">
          The engine is pure Roslyn. It knows nothing about the web layer and nothing about any model
          provider, which is why I can test the fidelity guarantee on its own.
        </p>
      </div>

      <div class="st-stack reveal">
        <div class="st-layer"><b>Client</b><span>React 19 · Vite · React&nbsp;Flow · zustand · Tailwind. Renders the active view, sends operations, applies deltas. It holds no authoritative state, a refresh just fetches the snapshot again.</span></div>
        <div class="st-arrow" aria-hidden="true">REST · SignalR ↓</div>
        <div class="st-layer"><b>Web</b><span>ASP.NET Core 10. REST for load, snapshot, layout and views; a SignalR hub for the operation stream. The only place model-provider calls are allowed to live.</span></div>
        <div class="st-arrow" aria-hidden="true">↓</div>
        <div class="st-layer st-pure"><b>Engine</b><span>Pure Roslyn. Workspace, in-memory model, the operation catalogue, <span class="st-mono">DocumentEditor</span> rewrites, the DSL reader and writer, undo, the layout sidecar. No web, no network.</span></div>
        <div class="st-arrow" aria-hidden="true">↓</div>
        <div class="st-layer"><b>Model</b><span>The record vocabulary your workspace references. Nothing but type definitions.</span></div>
        <div class="st-arrow" aria-hidden="true">↓</div>
        <div class="st-layer"><b>Storage</b><span>Your Git working tree. <span class="st-mono">Architecture/*.cs</span>, <span class="st-mono">Views/*.cs</span>, <span class="st-mono">stemma.layout.json</span>. No SQLite, no Postgres, no embedded store.</span></div>
      </div>

      <figure class="st-fig reveal">
        <svg viewBox="0 0 560 252" role="img" aria-labelledby="st-fig-t st-fig-d">
          <title id="st-fig-t">The lineage of a Stemma workspace</title>
          <desc id="st-fig-d">Two committed files at the top, Architecture.cs and stemma.layout.json, with descent lines to four derived views: Module Map, Dependency Graph, Concerns board and the HTML report.</desc>

          <path class="st-desc st-d1" d="M155 82 V132"/>
          <path class="st-desc st-d1" d="M405 82 V132"/>
          <path class="st-desc st-d2" d="M64 132 H496"/>
          <path class="st-desc st-d3" d="M64 132 V176"/>
          <path class="st-desc st-d3" d="M208 132 V176"/>
          <path class="st-desc st-d3" d="M352 132 V176"/>
          <path class="st-desc st-d3" d="M496 132 V176"/>

          <rect class="st-n-box st-n-src" x="60" y="28" width="190" height="54" rx="9" stroke-width="1.5"/>
          <circle class="st-blip" cx="72" cy="41" r="3"/>
          <text class="st-n-s" x="82" y="45">Model · committed</text>
          <text class="st-n-t" x="72" y="66">Architecture.cs</text>
          <text class="st-sig" x="44" y="40">α</text>

          <rect class="st-n-box st-n-side" x="310" y="28" width="190" height="54" rx="9" stroke-width="1.5"/>
          <text class="st-n-s" x="322" y="45">Presentation · committed</text>
          <text class="st-n-t" x="322" y="66">stemma.layout.json</text>
          <text class="st-sig" x="294" y="40">β</text>

          <rect class="st-n-box" x="2" y="176" width="124" height="50" rx="8"/>
          <text class="st-n-s" x="14" y="196">View</text>
          <text class="st-n-t" x="14" y="214">Module Map</text>
          <text class="st-sig" x="112" y="196">A</text>

          <rect class="st-n-box" x="146" y="176" width="124" height="50" rx="8"/>
          <text class="st-n-s" x="158" y="196">View</text>
          <text class="st-n-t" x="158" y="214">Dependencies</text>
          <text class="st-sig" x="256" y="196">B</text>

          <rect class="st-n-box" x="290" y="176" width="124" height="50" rx="8"/>
          <text class="st-n-s" x="302" y="196">Board</text>
          <text class="st-n-t" x="302" y="214">Concerns</text>
          <text class="st-sig" x="400" y="196">C</text>

          <rect class="st-n-box" x="434" y="176" width="124" height="50" rx="8"/>
          <text class="st-n-s" x="446" y="196">Publication</text>
          <text class="st-n-t" x="446" y="214">Report .html</text>
          <text class="st-sig" x="544" y="196">D</text>
        </svg>
        <figcaption>
          A <i>stemma codicum</i> is the family tree philologists draw for a manuscript: every copy traced
          back to one original. The files here have the same shape. Two committed files, every view comes
          from them, and nothing has to be kept in sync by hand.
        </figcaption>
      </figure>
    </div>
  </section>

  <!-- ==================== ROADMAP ==================== -->
  <section class="st-sec" id="roadmap">
    <div class="wrap">
      <div class="st-open reveal">
        <?= eyebrow('Roadmap') ?>
        <h2 class="st-h2">Horizons, not dates.</h2>
        <p class="st-sub">
          A side project I build on evenings and weekends, since 4 May 2026. I don't promise quarters,
          so each item has a status instead, in Stemma's own lifecycle words, the same ones it puts on
          your systems.
        </p>
      </div>

      <div class="st-road reveal">
        <section style="color:var(--st-ok)">
          <h3>Now <span class="st-chip st-c-ok">current</span></h3>
          <ul style="color:var(--body)">
            <li><b>The Roslyn engine</b> and the fidelity suite that gates it</li>
            <li><b>The canonical model</b>: elements, links, lifecycle, ownership, views as code, and the kinds that assert nothing</li>
            <li><b>The canvas</b>: module map, dependency graph, saved views, layout, styling, shapes, notes</li>
            <li><b>The committed sidecar</b>, with a published schema and a protocol reference for writing it by hand</li>
            <li><b>The headless CLI</b>: render and validate, the publication profile, layout as a constraint, the render manifest</li>
            <li><b>Story books</b>: narrated, audience-tagged page sequences, with present mode and PDF export</li>
            <li><b>The single-file HTML report</b>, with audience modes and a comment loop</li>
            <li><b>Model-only workspaces</b> and the from-scratch onboarding path</li>
            <li><b>Local-only diagnostics</b>: structured logs, redacted bundles, no network sink</li>
          </ul>
        </section>
        <section style="color:var(--st-tgt)">
          <h3>Next <span class="st-chip st-c-tgt">target</span></h3>
          <ul style="color:var(--body)">
            <li><b>Fidelity fixtures across the whole operation catalogue</b>: every operation, no exception</li>
            <li><b>A desktop window</b>, so it stops reading as a dev server</li>
            <li><b>One-command install</b> as a global tool</li>
            <li><b>A workspace brief</b>: a <span class="st-mono">STEMMA.md</span> dropped into every new workspace, so the agent that opens it needs no other document</li>
            <li><b>An embedded font in the vector export</b>, so an SVG cannot re-wrap on a machine that lacks the one it was drawn in</li>
            <li><b>Auto-layout from the command line</b>: the four algorithms are pure functions already, and reachable only from the canvas</li>
            <li><b>Clearer view management</b>: faster switching, better handling</li>
            <li><b>An accessibility pass</b>: keyboard, focus, contrast, reduced motion</li>
            <li><b>A curated sample gallery</b> as living documentation</li>
          </ul>
        </section>
        <section style="color:var(--st-new)">
          <h3>Later <span class="st-chip st-c-new">to&#8209;be&#8209;created</span></h3>
          <ul style="color:var(--body)">
            <li><b>More projections</b>: Mermaid and PlantUML output, a live code-preview pane</li>
            <li><b>Git-backed sessions</b>: a session is a branch, a save is a commit</li>
            <li><b>Deeper validation</b>: background compilation, richer rules inline</li>
            <li><b>Canvas search and filter</b>, and deep-linkable views</li>
            <li><b>A repository integration</b> that regenerates the report on merge and comments the architecture change on the pull request</li>
            <li><b>Hosted report links</b>: the one part that could ever be a service</li>
          </ul>
        </section>
      </div>
    </div>
  </section>

  <!-- ==================== QUESTIONS ====================
       The same answers are emitted as FAQPage in the head. If you edit one,
       edit the other — they are the same $faq array, so there is only one. -->
  <section class="st-sec st-stage" id="questions">
    <div class="wrap">
      <div class="st-open reveal">
        <?= eyebrow('Questions') ?>
        <h2 class="st-h2">What people ask first.</h2>
        <p class="st-sub">
          Nine questions people ask first, answered here instead of three emails later.
        </p>
      </div>

      <div class="st-faq reveal">
        <?php foreach ($faq as $q => $a): ?>
        <div>
          <h3><?= e($q) ?></h3>
          <p><?= e($a) ?></p>
        </div>
        <?php endforeach; ?>
      </div>

      <p class="st-kick reveal" style="text-align:center">
        The argument behind all of this is an essay,
        <a href="/blog/the-diagram-that-cannot-lie/">The Diagram That Cannot Lie</a>, about why a diagram
        that <em>can</em> drift always does. And on what diagrams are really for:
        <a href="/blog/diagrams-are-conversations-not-documentation/">Diagrams Are Conversations, Not Documentation</a>.
      </p>
    </div>
  </section>

  <!-- ==================== REPOSITORY ==================== -->
  <section class="st-sec" id="repository">
    <div class="wrap">
      <div class="st-open reveal">
        <?= eyebrow('Where to find it') ?>
        <h2 class="st-h2">The repository.</h2>
      </div>

      <div class="st-repo reveal">
        <a class="st-repo-url" href="<?= e($repo) ?>" rel="noopener"><?= $ghIcon ?>github.com/batas2/Stemma</a>
        <p>
          The source is there, with the engine and the fidelity suite. You need the .NET&nbsp;10 SDK and
          Node&nbsp;24, no database and no container, and
          <span class="st-mono">./run.sh --dev --workspace samples/AuroraRail</span> opens the reference
          model. What is still in progress is on the <a href="#roadmap">roadmap</a>, and what already
          shipped is dated in the <a href="/stemma/changelog/">changelog</a>, so you can check the pace
          yourself.
        </p>
        <p>
          <strong>PolyForm Noncommercial 1.0.0.</strong> Free for personal use, study and hobby projects,
          and for charities, schools, public research and government. It is source-available, not open
          source: any commercial use needs a commercial licence, and that is
          <a href="/about/#contact">a short email</a>, not a procurement process.
        </p>
        <p>
          If you disagree with something on this page, that is more useful to me than agreement, so
          <a href="/about/#contact">write to me</a>. I especially want to hear from architects who lived
          with diagram drift long enough to stop believing in fixes.
        </p>
        <p class="st-plain">
          Reading this with a machine? The whole page is also <a href="/stemma/index.md">plain markdown</a>,
          the changelog too (<a href="/stemma/changelog/index.md">markdown</a>), and the site context file
          is <a href="/llms.txt">llms.txt</a>. The repo has a JSON Schema for the layout sidecar and a
          protocol reference for writing a workspace without the app.
        </p>
      </div>
    </div>
  </section>

  <!-- ==================== NEWSLETTER ==================== -->
  <section class="st-sec st-tight">
    <div class="wrap">
      <?= newsletter_box() ?>
    </div>
  </section>

  <!-- ==================== COLOPHON ====================
       Deliberately the quietest thing on the page. Someone who has read this far
       has earned the context; nobody arriving from a search result needs it in
       the hero. -->
  <div class="st-colophon">
    <div class="wrap">
      <p>
        Stemma is my pet project. I build it alone, on evenings and weekends, the source is open to read,
        and it is not a company. There is no roadmap I owe anybody, no support line and no sales call at
        the end of this page. The commercial licence exists because a licence has to say something, not
        because there is a funnel behind it. I built the tool because I wanted it and nobody else had.
      </p>
    </div>
  </div>

</main>

<?= reveal_script() ?>
<script>
/* The film starts itself, silently, as part of the hero — and when it finishes it
   dissolves back to the screenshot rather than sitting on a dead frame.
   The sources are attached here, not in the markup, so the decision "is this a
   screen that shows the film at all" is made once and nothing downloads on the
   screens where the answer is no.

   Two cuts exist — the same film, lit for a light room and a dark one. Which one
   gets fetched follows the system preference, and if that flips while you are
   watching, the other cut takes over from the same second. */
(function () {
  var film = document.getElementById('st-film');
  var video = document.getElementById('st-video');
  var replay = document.getElementById('st-replay');
  if (!film || !video) return;

  var wide = matchMedia('(min-width: 821px)');
  var calm = matchMedia('(prefers-reduced-motion: reduce)');
  if (!wide.matches || calm.matches) return;   // phones, tablets, and anyone who asked for stillness

  var night = matchMedia('(prefers-color-scheme: dark)');
  var finished = false;

  function attach(cut) {
    while (video.firstChild) video.removeChild(video.firstChild);
    ['webm', 'mp4'].forEach(function (kind) {
      var src = document.createElement('source');
      src.src = video.dataset[cut + kind.charAt(0).toUpperCase() + kind.slice(1)];
      src.type = 'video/' + kind;
      video.appendChild(src);
    });
    video.preload = 'auto';
    video.load();
  }

  function roll() {
    film.classList.add('rolling');
    var p = video.play();
    if (p) p.catch(function () { film.classList.remove('rolling'); });   // autoplay refused: keep the still
  }

  attach(night.matches ? 'dark' : 'light');
  video.addEventListener('canplay', roll, { once: true });

  video.addEventListener('ended', function () {
    finished = true;
    film.classList.remove('rolling');
    video.currentTime = 0;
    replay.hidden = false;
  });

  replay.addEventListener('click', function () { finished = false; replay.hidden = true; roll(); });

  /* Theme changed under us. Re-cut at the same timestamp; a film that restarts
     from the top would read as a glitch, not a courtesy. */
  function recut() {
    var at = video.currentTime;
    var playing = !video.paused && !video.ended;
    attach(night.matches ? 'dark' : 'light');
    video.addEventListener('loadedmetadata', function () {
      video.currentTime = Math.min(at, video.duration || at);
      if (playing) roll();
      else if (finished) { film.classList.remove('rolling'); video.currentTime = 0; }
    }, { once: true });
  }
  if (night.addEventListener) night.addEventListener('change', recut);
  else if (night.addListener) night.addListener(recut);        // Safari < 14
})();
</script>

<script>
/* Contents rail: marks the chapter you are actually reading. The observer band is
   the middle of the viewport, so the mark changes when a chapter takes the screen
   rather than the instant its top edge appears. */
(function () {
  var toc = document.getElementById('st-toc');
  if (!toc || !('IntersectionObserver' in window)) return;

  var links = [].slice.call(toc.querySelectorAll('a'));
  var map = {};
  var sections = links.map(function (a) {
    var id = a.getAttribute('href').slice(1);
    map[id] = a;
    return document.getElementById(id);
  }).filter(Boolean);
  if (!sections.length) return;

  var seen = {};
  function mark() {
    var best = null;
    sections.forEach(function (sec) { if (seen[sec.id]) best = best || sec; });
    links.forEach(function (a) { a.removeAttribute('aria-current'); });
    if (best) map[best.id].setAttribute('aria-current', 'true');
  }
  var io = new IntersectionObserver(function (entries) {
    entries.forEach(function (en) { seen[en.target.id] = en.isIntersecting; });
    mark();
  }, { rootMargin: '-45% 0px -45% 0px' });
  sections.forEach(function (sec) { io.observe(sec); });

  // reveal only once the hero — which is wider than the text column — is behind us
  var top = document.getElementById('st-top');
  if (top) {
    new IntersectionObserver(function (e) {
      toc.classList.toggle('on', !e[0].isIntersecting);
    }, { rootMargin: '-90px 0px 0px 0px' }).observe(top);
  } else {
    toc.classList.add('on');
  }
})();
</script>
<script>
/* Scroll-linked emphasis on a pinned listing: whichever note the reader is on
   lights its lines and dims the rest. Armed only here, so with no script — or on
   a narrow screen, where nothing is pinned — every line stays fully legible.

   Scoped per .st-source block rather than per document: two chapters use this
   pattern now, and a global note selector would have the second one's notes
   driving the first one's panel. */
(function () {
  if (!('IntersectionObserver' in window)) return;
  if (!matchMedia('(min-width: 941px)').matches) return;

  [].forEach.call(document.querySelectorAll('.st-source'), function (block) {
    var panel = block.querySelector('.st-code.st-lit');
    if (!panel) return;                                  // a block with no emphasis to drive
    var lines = panel.querySelectorAll('.l');
    var notes = block.querySelectorAll('.st-note[data-g]');
    if (!lines.length || !notes.length) return;

    function light(group) {
      [].forEach.call(lines, function (l) { l.classList.toggle('on', l.dataset.g === group); });
    }
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) { if (en.isIntersecting) light(en.target.dataset.g); });
    }, { rootMargin: '-45% 0px -45% 0px' });
    [].forEach.call(notes, function (n) { io.observe(n); });
    light(notes[0].dataset.g);
  });
})();
</script>

<?= site_footer() ?>
