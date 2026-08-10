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
        'It cannot drift, because it is the same artefact. The model is C# in Architecture/*.cs inside your repository, and a canvas edit is a Roslyn DocumentEditor rewrite of that file. There is no export step to forget and no second copy to reconcile.',
    'Does my whole system have to be written in C#?' =>
        'No. C# is the notation the model is written in, not a constraint on what you model. The workspace can be a model-only folder with no solution in it, and the systems it describes can be written in anything. A .NET runtime is the only requirement, and a model-only workspace opens with no SDK, no restore and no NuGet.',
    'Where is the data stored?' =>
        'In your Git working tree, and nowhere else. There is no runtime database at any layer. The model is C# source; presentation — positions, styling, routing, notes — is a stemma.layout.json sidecar committed beside it. While a workspace is open the model lives in memory; permanent storage is the files.',
    'How is this different from draw.io, Lucidchart or an EA tool?' =>
        'Drawing tools produce a picture that is true on the day it is saved. Reverse-engineering tools read code but cannot edit it. Stemma closes the loop in both directions and guarantees round-trip fidelity, so a UI edit arrives as a diff containing only that edit — which is what makes the loop trustworthy enough to use.',
    'What does a change look like in review?' =>
        'A normal Git diff. Renaming one element on the canvas rewrites the declaration and every reference Roslyn can see, and touches nothing else: no reordering, no reformatting, no lost comment. Reviewers read it as the change it was.',
    'How do stakeholders see the model without installing anything?' =>
        'Two ways. A story book is an ordered, audience-tagged sequence of pages that drives the canvas as you present, stored beside the model so it cannot go stale either. Or export a single self-contained HTML report — one file, opens offline with no server, no login and no seat, with audience modes and element-anchored comments that import back.',
    'Can an agent use this without opening the canvas?' =>
        'That is the whole second half of the tool. A workspace is three text files — Architecture/*.cs, Views/*.cs and stemma.layout.json — and all three are documented for a writer who will never see the app. An agent authors them, runs stemma validate to catch the string ids nothing else checks, and runs stemma render to produce the image with no browser open and no mouse. Arrangement is expressed as a constraint (grid, row, column, stack) rather than as coordinates, because a model is good at intent and bad at pixels.',
    'What stops a published diagram from going stale?' =>
        'Every render writes a sibling manifest fingerprinting the model, the view, the layout and the profile it was drawn from. stemma render --check re-computes that fingerprint and exits non-zero when the committed image has stopped matching its source, so a build fails instead of a reader being misled. It is the same drift guarantee as the diff, extended past the app window.',
    'What licence is it under?' =>
        'PolyForm Noncommercial 1.0.0. Free for any noncommercial purpose — personal use, study, hobby projects, and use by charities, educational institutions, public research bodies and government. Commercial use of any kind needs a commercial licence: kontakt@bfrackowiak.pl. It is source-available, not open source, and the distinction is deliberate.',
];

$extra  = ld_script([
    '@context' => 'https://schema.org',
    '@graph' => [
        [
            '@type' => 'WebPage',
            '@id' => $url . '#page',
            'url' => $url,
            'name' => 'Stemma — the architecture model that is your source code',
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
            'description' => 'An architecture modelling tool for solution architects in which the C# source on disk is the model database. Canvas edits become Roslyn rewrites of real .cs files, so the diagram cannot drift from the code — and the same model can be authored as text by an agent and rendered headlessly with one command.',
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
                'Layout expressed as a declarative constraint — grid, row, column, stack — solved to coordinates',
                'A render manifest and stemma render --check, so a build fails when a published image stops matching its model',
                'A published JSON Schema for the presentation sidecar, and a protocol reference for authoring a workspace as text',
                'Export to PNG, true vector SVG, draw.io, Mermaid, PDF view-books and a single-file interactive HTML report',
                'Local-only diagnostics: structured logs with no network sink, and redacted shareable bundles',
                'No runtime database — Git is the store and the collaboration model',
            ],
        ],
        [
            // self-hosted, so contentUrl is a real file a crawler can fetch. Two cuts are
            // served — one lit for each theme — and the dark one is the canonical URL here
            // because it is what the thumbnail below shows.
            '@type' => 'VideoObject',
            '@id' => $url . '#film',
            'name' => 'Stemma — the diagram is the code',
            'description' => 'A two-minute look at Stemma: a model built from an empty file on camera — bounded contexts, modules, relationships — the two-line diff a rename produces in the source, the narrated story books that walk each audience through it, and the single self-contained HTML report the whole thing exports to.',
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
        . '<meta property="og:image:alt" content="Stemma — the diagram is the code. An architecture model held as C# in your repository.">' . "\n"
        . '<meta name="twitter:image:alt" content="Stemma — the diagram is the code. An architecture model held as C# in your repository.">' . "\n";

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
/* Sits in the gutter, never over the text: 124px of rail plus a 24px gap has to
   fit beside the 1320px column, which needs 1680px of viewport to exist at all. */
.st-toc {
  display: none; position: fixed; left: calc((100vw - var(--max)) / 2 - 148px);
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
@media (min-width: 1680px) { .st-toc { display: block; } }

/* ---- the fact strip under the hero --------------------------------------- */
.st-strip { border-top: 1px solid var(--line); border-bottom: 1px solid var(--line); }
/* 165px, not 190: five facts have to sit on one row down to the 940px breakpoint
   where the borders flip from left to top, or the fifth wraps and wears a left
   border in the middle of nowhere. */
.st-strip dl { display: grid; grid-template-columns: repeat(auto-fit, minmax(165px, 1fr)); }
.st-strip dl > div { padding: 1.6rem 1.6rem 1.6rem 1.8rem; border-left: 1px solid var(--line); }
.st-strip dl > div:first-child { padding-left: 0; border-left: 0; }
.st-strip dt { font-family: 'JetBrains Mono', ui-monospace, monospace; font-size: 0.6rem; letter-spacing: 0.12em; text-transform: uppercase; color: var(--muted); margin-bottom: 0.45rem; }
.st-strip dd { font-size: 1rem; font-weight: 600; color: var(--ink); line-height: 1.35; }
.st-strip dd span { display: block; font-weight: 400; font-size: 0.84rem; color: var(--muted); margin-top: 0.15rem; }

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

/* ---- what shipped: the last few releases, in front of the roadmap -------- */
.st-ship { max-width: 50rem; margin: 3rem auto 0; }
.st-ship li { display: grid; grid-template-columns: 8.5rem minmax(0, 1fr); gap: 1.8rem; padding: 1.4rem 0; border-bottom: 1px solid var(--line); }
.st-ship li:last-child { border-bottom: 0; }
.st-ship time { font-family: 'JetBrains Mono', ui-monospace, monospace; font-size: 0.72rem; letter-spacing: 0.04em; color: var(--muted); padding-top: 0.28em; }
.st-ship b { display: block; color: var(--ink); font-weight: 650; font-size: 1.02rem; letter-spacing: -0.015em; margin-bottom: 0.3rem; }
.st-ship span { font-size: 0.92rem; color: var(--muted); line-height: 1.65; }
.st-more { display: block; max-width: 50rem; margin: 2rem auto 0; text-align: center; }

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


/* ---- comparison ---------------------------------------------------------- */
.st-tw { overflow-x: auto; margin-top: 3rem; }
.st-cmp { width: 100%; border-collapse: collapse; font-size: 0.88rem; min-width: 720px; }
.st-cmp th, .st-cmp td { text-align: left; padding: 0.85rem 1rem; border-bottom: 1px solid var(--line); vertical-align: top; }
.st-cmp thead th { font-family: 'JetBrains Mono', ui-monospace, monospace; font-size: 0.6rem; letter-spacing: 0.1em; text-transform: uppercase; color: var(--muted); font-weight: 600; }
.st-cmp thead th.st-us { color: var(--accent); }
.st-cmp tbody th { font-weight: 600; color: var(--ink); width: 20%; }
.st-cmp td { color: var(--muted); }
.st-cmp td.st-us { color: var(--ink); font-weight: 600; background: color-mix(in srgb, var(--accent) 6%, transparent); }
.st-cmp tbody tr:last-child th, .st-cmp tbody tr:last-child td { border-bottom: 0; }

/* ---- tech specs ---------------------------------------------------------- */
.st-specs { max-width: 62rem; margin: 3rem auto 0; }
.st-specs > div { display: grid; grid-template-columns: 11rem 1fr; gap: 1.5rem; padding: 0.95rem 0; border-bottom: 1px solid var(--line); }
.st-specs > div:last-child { border-bottom: 0; }
.st-specs dt { font-family: 'JetBrains Mono', ui-monospace, monospace; font-size: 0.63rem; letter-spacing: 0.1em; text-transform: uppercase; color: var(--muted); padding-top: 0.28em; }
.st-specs dd { font-size: 0.92rem; color: var(--body); line-height: 1.6; }
.st-specs dd code { font-family: 'JetBrains Mono', ui-monospace, monospace; font-size: 0.85em; color: var(--ink); }
.st-specs dd em { font-style: normal; color: var(--muted); }

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
  .st-strip dl > div { padding: 1.1rem 0; border-left: 0; border-top: 1px solid var(--line); }
  .st-strip dl > div:first-child { border-top: 0; }
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
  .st-specs > div { grid-template-columns: 1fr; gap: 0.35rem; }
  .st-ship li { grid-template-columns: 1fr; gap: 0.4rem; }
  .st-ship time { padding-top: 0; }
  .st-code pre { font-size: 0.66rem; }
  .st-term pre { font-size: 0.6rem; }
  .st-plate img, .st-hero-shot img { border-radius: 10px; }
}
</style>
CSS;

// One description does double duty: Google cuts around 155 characters and the social cards
// show two or three lines, so the differentiator has to survive in the first clause.
echo page_head(
    'Stemma — the architecture model that is your source code',
    'Architecture modelling for solution architects: the C# in your repository is the model. Edit the canvas, Roslyn rewrites the file — the diagram cannot drift.',
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
      <li><a href="#source">The source</a></li>
      <li><a href="#edit">The edit</a></li>
      <li><a href="#vocabulary">The vocabulary</a></li>
      <li><a href="#agents">The agent's half</a></li>
      <li><a href="#views">The views</a></li>
      <li><a href="#books">Story books</a></li>
      <li><a href="#shape">The shape of it</a></li>
      <li><a href="#compare">How it differs</a></li>
      <li><a href="#specs">Specifications</a></li>
      <li><a href="#changelog">What shipped</a></li>
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
        An architecture model for solution architects that lives in your repository as C# source:
        edit the canvas, and Roslyn rewrites the file. Or skip the canvas entirely — the same model is
        three text files an agent can write, and one command renders the picture with no browser open
        and no mouse.
      </p>
      <p class="st-cta">
        <a class="btn btn-primary" href="#repository">Where to find it <?= arrow_icon() ?></a>
        <a class="btn btn-ghost" href="#agents">The agent's half</a>
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
               alt="The Stemma canvas: three bounded contexts as lanes — Ordering, Payments, Fulfilment — with modules nested inside them, labelled data flows between them, and external systems along the top.">
        </picture>
        <!-- No src attributes: the sources are attached by script, and only on a
             viewport wide enough to show the film. Nothing downloads otherwise —
             and only ever one of the two cuts, the one this reader's system asks for. -->
        <video id="st-video" preload="none" muted playsinline disablepictureinpicture
               width="1920" height="1080" aria-label="The Stemma film — two minutes, silent"
               data-dark-webm="/assets/video/stemma-film-dark.webm" data-dark-mp4="/assets/video/stemma-film-dark.mp4"
               data-light-webm="/assets/video/stemma-film-light.webm" data-light-mp4="/assets/video/stemma-film-light.mp4"></video>
        <button type="button" class="st-replay" id="st-replay" hidden>Replay</button>
      </div>
    </div>
  </section>

  <!-- ==================== FACT STRIP ==================== -->
  <section class="st-strip">
    <div class="wrap">
      <dl>
        <div><dt>Storage</dt><dd>Your Git working tree<span>No runtime database, at any layer</span></dd></div>
        <div><dt>Engine</dt><dd>Roslyn <span class="st-mono">DocumentEditor</span><span>Targeted rewrites, never string rebuilds</span></dd></div>
        <div><dt>Guarantee</dt><dd>Round-trip fidelity<span>A UI edit diffs as only that edit</span></dd></div>
        <div><dt>Headless</dt><dd><span class="st-mono">stemma render</span><span>Model in, image out. No browser, no mouse</span></dd></div>
        <div><dt>Narrative</dt><dd>Story books<span>The walkthrough that lands it, kept with the model</span></dd></div>
      </dl>
    </div>
  </section>

  <!-- ==================== SOURCE ⇄ CONCEPT ==================== -->
  <section class="st-sec" id="source">
    <div class="wrap">
      <div class="st-open reveal">
        <?= eyebrow('The source') ?>
        <h2 class="st-h2">Your model is a file you can read.</h2>
        <p class="st-sub">
          No project format, no XML, no notation to learn. A model is C# records in
          <span class="st-mono">Architecture/Architecture.cs</span>, and anyone on the team can open it
          in a text editor and follow it.
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
            <p>A bounded context is a record with an id and a name. On the canvas it renders as a lane
              that other elements nest inside — but the nesting is a real reference in the file, not a
              position on a grid. Move a module between lanes and one string argument changes.</p>
          </div>
          <div class="st-note" data-g="mod">
            <span class="st-tick">Elements</span>
            <h3>Ids are stable; names are not.</h3>
            <p>Every element carries an id that never changes and a display name that can. Flows,
              dependencies, tags and views all reference the id, so renaming a concept as your
              understanding improves costs nothing and breaks nothing.</p>
          </div>
          <div class="st-note" data-g="flow">
            <span class="st-tick">Relationships</span>
            <h3>An arrow that says what it carries.</h3>
            <p>A <span class="st-mono">DataFlow</span> names its payload and its direction; a
              <span class="st-mono">Dependency</span> names its kind — uses, calls, reads, publishes,
              consumes. A link renders in a view only when both of its endpoints are present, so a
              filtered view never shows an arrow into nothing.</p>
          </div>
          <div class="st-note" data-g="tag">
            <span class="st-tick">Lifecycle &amp; ownership</span>
            <h3>Target state lives on the model, not in a deck.</h3>
            <p><span class="st-mono">Lifecycle</span> marks a thing current, target, to-adapt,
              to-be-created or deprecated, with a phase and validity dates.
              <span class="st-mono">Ownership</span> attaches a squad, a domain and RAPID roles.
              Both go on elements <em>and</em> on relationships, which is where migration plans
              usually fall apart.</p>
          </div>
          <div class="st-note" data-g="risk">
            <span class="st-tick">Concerns</span>
            <h3>Risks and questions are first-class.</h3>
            <p>A risk, an open question or a standing assumption is a model element anchored to what it
              concerns. It renders as a dotted edge to its subject, versions with it, and turns up on the
              Concerns board — instead of in a wiki page nobody opens.</p>
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
          Double-click the <span class="st-mono">Ledger</span> box on the canvas, type a longer name,
          press Enter. This is what <span class="st-mono">git diff</span> reported — all three hunks,
          with the file header removed and two over-long lines cut at the right margin.
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
        <li><b>The declaration</b>The display name changed. The id did not, so every reference elsewhere still resolves.</li>
        <li><b>The local variable, twice</b>Roslyn renamed the symbol, so the tag and the aggregate list followed it. A find-and-replace would have missed one and still compiled.</li>
        <li><b>And nothing else</b>No reordering, no reformatting, no blank line moved, no comment lost. The reviewer sees a rename, because that is what happened.</li>
      </ul>

      <p class="st-kick reveal" style="text-align:center">
        This is the contract the rest of the tool rests on. A suite of minimal, realistic and deliberately
        pathological fixtures runs every operation and asserts the diff byte for byte, and the codebase has
        one standing instruction about it: <strong>when the fidelity suite fails, the implementation is
        wrong, not the test</strong>.
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
          Deliberately richer than a class diagram, deliberately poorer than an IDE. This is the whole
          language — there is no hidden second half.
        </p>
      </div>

      <div class="st-ref reveal">
        <div>
          <h3>Elements</h3>
          <table>
            <thead><tr><th>Record</th><th>Carries</th></tr></thead>
            <tbody>
              <tr><th>Person</th><td>An actor, with a role — external, internal, user</td></tr>
              <tr><th>SoftwareSystem</th><td>A system, usually a neighbouring one you don't own</td></tr>
              <tr><th>Container</th><td>A deployable unit inside a system, with a kind — service, worker, cronjob, db</td></tr>
              <tr><th>BoundedContext</th><td>A DDD context; renders as a lane other elements nest inside</td></tr>
              <tr><th>Module</th><td>A unit of cohesion, optionally inside a context</td></tr>
              <tr><th>Capability</th><td>A business capability, optionally inside a context</td></tr>
              <tr><th>UseCase</th><td>A user-visible use case</td></tr>
              <tr><th>Risk</th><td>A design risk, anchored to what it concerns</td></tr>
              <tr><th>Question</th><td>An open design question, anchored the same way</td></tr>
              <tr><th>Assumption</th><td>A standing assumption everything downstream rests on</td></tr>
              <tr><th>Concept</th><td>A named box that asserts <em>nothing</em> about a system — for drawing an argument rather than an architecture</td></tr>
            </tbody>
          </table>
        </div>

        <div>
          <h3>Relationships, metadata, views</h3>
          <table>
            <thead><tr><th>Record</th><th>Carries</th></tr></thead>
            <tbody>
              <tr><th>DataFlow</th><td>From, to, a payload name shown as the edge label, and a direction</td></tr>
              <tr><th>Dependency</th><td>From, to, and a kind — uses, calls, reads, publishes, consumes</td></tr>
              <tr><th>Relation</th><td>From, to, and a free label — an arrow that claims nothing beyond what it says</td></tr>
              <tr><th>Lifecycle</th><td>Status (current, target, to-adapt, to-be-created, deprecated, or your own word), phase, valid-from, valid-until</td></tr>
              <tr><th>Ownership</th><td>Squad, domain, and RAPID role lists — recommend, agree, perform, input, decide</td></tr>
              <tr><th>Tag.For</th><td>Attaches a lifecycle and/or an ownership to any element <em>or any link</em></td></tr>
              <tr><th>View</th><td>A named subset with a base lens — module map, dependency graph, or all — stored as its own <span class="st-mono">.cs</span> file</td></tr>
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
        <?= eyebrow('The agent&rsquo;s half') ?>
        <h2 class="st-h2">Nobody has to open it.</h2>
        <p class="st-sub">
          Everything above happens with a mouse in it. But a workspace is three text files in a
          vocabulary that is written down — so the canvas is <strong>an</strong> authoring surface, not
          the only one. An agent with a shell writes the model; one command turns it into a picture.
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
            <p>Every other element kind is a claim — <em>this is a bounded context</em>, <em>this is
              deployable</em>. An argument's boxes claim nothing, and forcing one into a
              <span class="st-mono">Module</span> writes a falsehood into a file other people will copy
              from. <span class="st-mono">Concept</span> and <span class="st-mono">Relation</span> are
              the way out: a named box and a labelled arrow that mean only what they say.</p>
          </div>
          <div class="st-note" data-g="view">
            <span class="st-tick">The view</span>
            <h3>The view is the question.</h3>
            <p>A view names the subset this particular picture is about. A link is drawn only when
              <em>both</em> of its endpoints are in it — the single most common reason a diagram comes
              out looking right and missing an arrow. So the validator reports that case by name,
              rather than leaving it to be noticed.</p>
          </div>
          <div class="st-note" data-g="layout">
            <span class="st-tick">The layout</span>
            <h3>Say what the arrangement is, not where things are.</h3>
            <p>A language model is good at intent and poor at pixels, so it states the intent — three
              columns, sixty apart, centred — and Stemma solves it to coordinates. Nudging one box by
              hand records an offset <em>against</em> the constraint instead of replacing it, so the
              arrangement survives being corrected and the nudge travels with its slot when the grid
              re-solves. <span class="st-mono">row</span>, <span class="st-mono">column</span> and
              <span class="st-mono">stack</span> work the same way.</p>
          </div>
          <div class="st-note" data-g="ink">
            <span class="st-tick">Annotation</span>
            <h3>Ink is not architecture.</h3>
            <p>Swimlane labels, brackets, callouts — anything carrying no architectural meaning — are
              free-form shapes in the sidecar, never elements. Worth stating plainly, because it is the
              rule an agent breaks first: if you want to add an element so that a picture looks better,
              what you want is a shape.</p>
          </div>
        </div>
      </div>

      <div class="st-term st-code reveal">
        <div class="st-code-bar"><b>a shell, no browser open</b><span>check it, draw it, then catch it going stale</span><span class="st-r">wrapped to fit</span></div>
<pre><code><span class="l"><span class="l-p">$ </span><span class="l-c">stemma validate --workspace .</span></span>
<span class="l l-o">  info    model/is-a-figure            6 of 6 elements are Concepts — this is</span>
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
        <li><b>Deterministic</b>Same input, same picture. Each render starts a short-lived backend of its own, so two can run at once and neither disturbs a workspace you have open.</li>
        <li><b>Checkable</b>Every render writes a manifest fingerprinting the model, the view, the layout and the profile it came from. <span class="st-mono">--check</span> re-computes it and fails the build when a committed image has stopped matching its source.</li>
        <li><b>Written down</b>The sidecar has a published JSON Schema and every file written points at it. The protocol reference explains the whole chain to somebody who will never open the app, with three worked examples that were built and rendered exactly as printed.</li>
      </ul>

      <p class="st-kick reveal" style="margin-top:2.4rem">
        The workspace above draws a diagram that already exists on this site — the one in
        <a href="/blog/the-bottleneck-moved/">The Bottleneck Moved</a>, originally made in draw.io.
        Reproducing it from a model, with one command and no mouse, transparent and identical across two
        machines, was the acceptance test for this whole pipeline. Its origin is less flattering: a later
        essay's diagrams <em>were</em> drawn from a Stemma model, by a 190-line Playwright script that
        started the app, opened a real browser and dragged every node into position with synthetic mouse
        gestures — three passes, re-reading an anchor node before each one, because dragging was the only
        thing that stuck. It worked, and it was a bug report against the product.
        <strong><span class="st-mono">stemma render</span> is what deleted it.</strong>
      </p>
    </div>
  </section>

  <!-- ==================== VIEWS ==================== -->
  <section class="st-sec" id="views">
    <div class="wrap">
      <div class="st-open reveal">
        <?= eyebrow('The views') ?>
        <h2 class="st-h2">One model. Whatever lens the room needs.</h2>
        <p class="st-sub">
          A view is a projection, not a copy — a named subset of the same model with its own layout,
          styling and notes. Switching one changes the lens, never the data.
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
          Every question, assumption and risk in the model, each still pointing at the element it belongs
          to. Click one and it opens on the map, selected.
        </figcaption>
      </figure>
    </div>
  </section>

  <!-- ==================== STORY BOOKS ==================== -->
  <section class="st-sec" id="books">
    <div class="wrap">
      <div class="st-open reveal">
        <?= eyebrow('Story books') ?>
        <h2 class="st-h2">An architecture nobody followed is an architecture that didn't happen.</h2>
        <p class="st-sub">
          Modelling the system is half the job. The other half is forty minutes in front of people who
          control the budget — and that walkthrough is normally a slide deck, rebuilt by hand every
          quarter, drifting from the model the moment either one moves.
        </p>
      </div>

      <div class="st-source" style="margin-top:3rem">
        <div class="st-pin">
          <div class="st-code">
            <div class="st-code-bar"><b>Concepts/view-book.stemma.yaml</b><span class="st-r">excerpt</span></div>
<pre><code><span class="l"><span class="c">books:</span></span><span class="l">  - id: book_steering</span><span class="l">    name: Steering review — extracting Payments</span><span class="l">    audience: <span class="s">leadership</span></span><span class="l">    pages:</span><span class="l">      - viewId: moduleMap</span><span class="l">        title: Where the money moves today</span><span class="l">        narrative: <span class="c">Three contexts. Ordering and Fulfilment are</span></span><span class="l">          <span class="c">already out of the monolith. Payments is the one still</span></span><span class="l">          <span class="c">half in — Settlement is target state for Q3, Ledger does</span></span><span class="l">          <span class="c">not exist yet…</span></span><span class="l"> </span><span class="l">      - viewId: dependencyGraph</span><span class="l">        title: The dependency that sets the order of work</span><span class="l">        narrative: <span class="c">Order Lifecycle still reads the legacy</span></span><span class="l">          <span class="c">monolith. That arrow is why Payments cannot go first…</span></span></code></pre>
          </div>
        </div>

        <div class="st-notes">
          <div class="st-note">
            <span class="st-tick">A page</span>
            <h3>A view, plus the thing you would actually say.</h3>
            <p>Each page points at a view of the model and carries a title and a narrative. Not a caption —
              the sentence you say out loud when that picture is on the screen, written down so it survives
              you being on holiday.</p>
          </div>
          <div class="st-note">
            <span class="st-tick">An audience</span>
            <h3>One model, one deck per room.</h3>
            <p>A book is tagged for who it is for — leadership, engineering, security, operations. The
              committee that funds the work and the team that builds it get different sequences, different
              depth and different language, off the same model, with no second copy to keep honest.</p>
          </div>
          <div class="st-note">
            <span class="st-tick">Present mode</span>
            <h3>The canvas follows the story.</h3>
            <p>Open a book and the tool becomes the deck: the canvas switches to each page's view, the
              narrative sits in a strip beneath it, and prev/next walks the room through it. Drag to
              reorder pages, edit the narrative in place, export the whole thing as a multi-page PDF for
              the people who want a document.</p>
          </div>
          <div class="st-note">
            <span class="st-tick">Why it matters</span>
            <h3>The deck cannot drift either.</h3>
            <p>A book stores the <em>reference</em> to a view, never a picture of it. Change the
              architecture and next quarter's walkthrough redraws itself from the current model — so the
              story you told the board and the code you shipped stay the same artifact. That is the part
              no diagramming tool can offer, because it has no model to point at.</p>
          </div>
        </div>
      </div>

      <figure class="st-plate reveal">
        <picture>
          <source srcset="/assets/img/stemma/book-dark.webp" media="(prefers-color-scheme: dark)">
          <img src="/assets/img/stemma/book-light.webp" width="2400" height="1470" loading="lazy" decoding="async"
               alt="Stemma in present mode: a four-page steering-review book tagged for leadership, with the page strip along the bottom and the narrative for page one beneath the canvas.">
        </picture>
        <figcaption>
          <span class="st-pl">Present mode</span>
          A four-page steering review tagged <span class="st-mono">leadership</span>. The strip along the
          bottom is the story; the canvas above it is the model. Nothing was exported to build this.
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
          provider, which is what keeps the fidelity guarantee testable in isolation.
        </p>
      </div>

      <div class="st-stack reveal">
        <div class="st-layer"><b>Client</b><span>React 19 · Vite · React&nbsp;Flow · zustand · Tailwind. Renders the active view, emits operations, applies deltas. Holds no authoritative state — a refresh re-fetches the snapshot.</span></div>
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
          <desc id="st-fig-d">Two committed files at the top — Architecture.cs and stemma.layout.json — with descent lines to four derived views: Module Map, Dependency Graph, Concerns board and the HTML report.</desc>

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
          A <i>stemma codicum</i> is the family tree philologists draw for a manuscript: every surviving
          copy traced back to one archetype. Persistence here has the same shape — two committed files,
          every view descended from them, and not one descendant that has to be kept honest by hand.
        </figcaption>
      </figure>
    </div>
  </section>

  <!-- ==================== HOW IT DIFFERS ==================== -->
  <section class="st-sec" id="compare">
    <div class="wrap">
      <div class="st-open reveal">
        <?= eyebrow('How it differs') ?>
        <h2 class="st-h2">Not a better canvas.</h2>
        <p class="st-sub">
          Three honest categories of tool already exist, and each is good at what it set out to do. This
          is what changes when the model has nowhere to drift to.
        </p>
      </div>

      <div class="st-tw reveal">
        <table class="st-cmp">
          <thead>
            <tr>
              <th scope="col">&nbsp;</th>
              <th scope="col" class="st-us">Stemma</th>
              <th scope="col">Canvas drawing tools</th>
              <th scope="col">Model-as-language tools</th>
              <th scope="col">Code analysers</th>
            </tr>
          </thead>
          <tbody>
            <tr><th scope="row">Source of truth</th><td class="st-us">Your source files</td><td>A separate document</td><td>A separate language beside the code</td><td>Your source files, read-only</td></tr>
            <tr><th scope="row">Redesign in the picture</th><td class="st-us">Yes — the code follows</td><td>Yes, and nothing follows</td><td>Yes, and the code still doesn't follow</td><td>No</td></tr>
            <tr><th scope="row">Drift</th><td class="st-us">Structurally impossible</td><td>Immediate and permanent</td><td>Slower, but there are still two artifacts</td><td>None, at the price of not designing</td></tr>
            <tr><th scope="row">Reviewed in a pull request</th><td class="st-us">As a diff of the architecture</td><td>As an image somebody re-exported</td><td>As a diff of the description</td><td>Not applicable</td></tr>
            <tr><th scope="row">Risk, ownership, questions</th><td class="st-us">First-class, anchored, versioned</td><td>A sticky note, if you're lucky</td><td>Limited</td><td>Absent by construction</td></tr>
            <tr><th scope="row">Telling the story</th><td class="st-us">Narrated books that travel with the model</td><td>A slide deck, rebuilt by hand each time</td><td>Not addressed</td><td>Not addressed</td></tr>
            <tr><th scope="row">Where a coding agent meets it</th><td class="st-us">In the repository it is already editing</td><td>Nowhere near it</td><td>A file beside the code, easy to ignore</td><td>After the fact, as a report</td></tr>
          </tbody>
        </table>
      </div>

      <p class="st-kick reveal" style="margin-top:2.4rem">
        For a team the practical differences are narrow and concrete: an architecture review stops opening
        with twenty minutes of establishing whether the picture is current; a module reaching across a
        context boundary shows up in the diff a reviewer already reads; and a new architect has one file
        and one canvas to read instead of a wiki space to excavate. There is nothing to procure — it runs
        on a laptop, against a repository you already own, and nothing leaves it.
      </p>
    </div>
  </section>

  <!-- ==================== TECH SPECS ==================== -->
  <section class="st-sec st-stage" id="specs">
    <div class="wrap">
      <div class="st-open reveal">
        <?= eyebrow('Specifications') ?>
        <h2 class="st-h2">Technical specifications.</h2>
      </div>

      <dl class="st-specs reveal">
        <div><dt>Engine</dt><dd>.NET 10 · Roslyn. Loads a repository through <code>MSBuildWorkspace</code>, or a model-only folder through an <code>AdhocWorkspace</code>. All edits are <code>DocumentEditor</code> rewrites.</dd></div>
        <div><dt>Host</dt><dd>ASP.NET Core 10 — REST for workspace, layout, views and violations; SignalR for the operation stream and delta broadcast.</dd></div>
        <div><dt>Client</dt><dd>React 19 · Vite · <code>@xyflow/react</code> · zustand · Tailwind.</dd></div>
        <div><dt>Requirements</dt><dd>A .NET 10 runtime. <em>A model-only workspace opens with no SDK installed, no restore and no NuGet — the metadata references come from the assemblies the process is already running on.</em></dd></div>
        <div><dt>Model storage</dt><dd><code>Architecture/*.cs</code> and <code>Views/*.cs</code> in your Git working tree.</dd></div>
        <div><dt>Presentation</dt><dd><code>stemma.layout.json</code>, committed beside the model: positions, node and edge styling, routing, notes, custom properties, free-form shapes, per-view layout choice.</dd></div>
        <div><dt>Runtime database</dt><dd>None. The model lives in memory while a workspace is open; permanent storage is the files.</dd></div>
        <div><dt>Operations</dt><dd>Add, rename and remove elements; re-parent; set attributes; add and remove links; set link attributes; set lifecycle; set ownership; restore — each with its own round-trip fixtures.</dd></div>
        <div><dt>Views</dt><dd>Module map, dependency graph and concerns board built in; any number of saved custom views, each stored as code.</dd></div>
        <div><dt>Layout</dt><dd>Architectural (by type), hierarchical, force-directed and manual — tunable per view, remembered per view. A manual move flips that view to custom. Or a declarative constraint — <code>grid</code>, <code>row</code>, <code>column</code>, <code>stack</code> — solved to coordinates and kept live, with a later drag recorded as an offset against it.</dd></div>
        <div><dt>Canvas</dt><dd>Pan, zoom (0.05×–2.5×), minimap, snap to 20px grid, multi-select, align and distribute, undo and redo, six connection dots per node, draw.io-style edge docking, four routing modes, both-end markers.</dd></div>
        <div><dt>Headless</dt><dd><code>stemma render</code> — a workspace and a view become an image with no browser open and no mouse; profile, palette, theme, format, scale, padding and transparency as flags; a short-lived backend per render, so renders are isolated and can run in parallel. <code>stemma validate</code> — dangling endpoints, missing view members, half-drawn links, duplicate ids, busy views, with <code>--json</code> for a caller acting on the answer.</dd></div>
        <div><dt>Provenance</dt><dd>Every render writes a sibling <code>.manifest.json</code> fingerprinting the model, the view, the layout and the profile. <code>stemma render --check</code> re-computes it and exits non-zero when a committed image no longer matches its source.</dd></div>
        <div><dt>Authoring as text</dt><dd>A published JSON Schema for <code>stemma.layout.json</code>, pointed at from every file written, plus a protocol reference covering the whole chain — vocabulary, view, sidecar, render command — with three worked examples. <em>Written for somebody who will never open the app.</em></dd></div>
        <div><dt>Export</dt><dd>PNG · true vector SVG from the publication profile · draw.io (honouring the view and the layout) · Mermaid · multi-page PDF view-books · a single-file interactive HTML report that opens offline with no network requests.</dd></div>
        <div><dt>Report</dt><dd>Audience modes (builder, stakeholder, reviewer), per-view layer toggles, element search, and element-anchored comments that export as a pack and import back into the workspace.</dd></div>
        <div><dt>Story books</dt><dd>Ordered, audience-tagged sequences of pages; each page references a view and carries a title and a narrative. Present mode drives the canvas from the page, with prev/next, drag-to-reorder, in-place narrative editing and multi-page PDF export. Stored as <code>Concepts/view-book.stemma.yaml</code> beside the model.</dd></div>
        <div><dt>Validation</dt><dd>Dangling references and architecture-rule violations surfaced inline on the canvas and in the status bar, and reported on the command line by <code>stemma validate</code>.</dd></div>
        <div><dt>Diagnostics</dt><dd>A leveled, structured record of what the engine did — JSON lines under <code>~/.stemma/logs/</code>, capped at 20 MB and 14 days without being asked, level changeable while running. Shareable bundles are redacted by default. <em>There is no network sink and none can be configured: a log record contains your architecture.</em></dd></div>
        <div><dt>Collaboration</dt><dd>Git. Branch, tag, blame, merge, revert, pull request. Live sync over SignalR for clients open on the same workspace; external file edits reconcile without clobbering in-flight work.</dd></div>
        <div><dt>Fidelity</dt><dd>Every operation carries minimal, realistic and pathological round-trip fixtures. A failing fixture blocks the change, not the other way round.</dd></div>
        <div><dt>Samples</dt><dd><code>samples/AuroraRail</code> — a fictional rail-ticketing platform: 66 elements, four saved views, three narrative books, exercising every part of the DSL. <code>samples/StemmaArchitecture</code> — Stemma modelling itself.</dd></div>
        <div><dt>Licence</dt><dd>PolyForm Noncommercial 1.0.0 — source-available, <em>not</em> open source. Free for any noncommercial purpose: personal use, study, hobby projects, and use by charities, educational institutions, public research bodies and government. Commercial use of any kind needs a commercial licence.</dd></div>
      </dl>
    </div>
  </section>

  <!-- ==================== WHAT SHIPPED ====================
       The three most recent releases only. The whole record lives at
       /stemma/changelog/, which is the page that grows; this one stays the
       length it is. -->
  <section class="st-sec" id="changelog">
    <div class="wrap">
      <div class="st-open reveal">
        <?= eyebrow('What shipped') ?>
        <h2 class="st-h2">It is still being built.</h2>
        <p class="st-sub">
          The three most recent releases. A project page can claim anything about a side project;
          dated releases are the only version of that claim you can check.
        </p>
      </div>

      <ul class="st-ship reveal">
        <li>
          <time datetime="2026-08-08">8 Aug 2026</time>
          <div>
            <b>The headless half</b>
            <span><span class="st-mono">stemma render</span> and <span class="st-mono">stemma validate</span>,
              a publication profile, layout as a declarative constraint, a render manifest with
              <span class="st-mono">--check</span>, true vector SVG, the
              <span class="st-mono">Concept</span> and <span class="st-mono">Relation</span> kinds, a
              published sidecar schema and the protocol reference — plus local-only diagnostics.</span>
          </div>
        </li>
        <li>
          <time datetime="2026-08-03">3 Aug 2026</time>
          <div>
            <b>In the open</b>
            <span>The repository, PolyForm Noncommercial 1.0.0, and two workspaces worth reading first:
              Aurora Rail, and Stemma's own architecture modelled in Stemma.</span>
          </div>
        </li>
        <li>
          <time datetime="2026-08-01">1 Aug 2026</time>
          <div>
            <b>Stemma, and a workspace with no solution in it</b>
            <span>The rename from Verso, and model-only workspaces: a folder holding
              <span class="st-mono">Architecture/</span> and nothing else opens with no MSBuild, no SDK,
              no restore and no NuGet.</span>
          </div>
        </li>
      </ul>

      <p class="st-more reveal">
        <a class="btn btn-ghost" href="/stemma/changelog/">The whole changelog, back to 4 May <?= arrow_icon() ?></a>
      </p>
    </div>
  </section>

  <!-- ==================== ROADMAP ==================== -->
  <section class="st-sec st-stage" id="roadmap">
    <div class="wrap">
      <div class="st-open reveal">
        <?= eyebrow('Roadmap') ?>
        <h2 class="st-h2">Horizons, not dates.</h2>
        <p class="st-sub">
          A side project built on evenings and weekends since 4 May 2026. Promising quarters would be a
          lie with a nice font, so each item carries an honest position instead. The words are Stemma's
          own lifecycle vocabulary — the same three it puts on your systems.
        </p>
      </div>

      <div class="st-road reveal">
        <section style="color:var(--st-ok)">
          <h3>Now <span class="st-chip st-c-ok">current</span></h3>
          <ul style="color:var(--body)">
            <li><b>The Roslyn engine</b> and the fidelity suite that gates it</li>
            <li><b>The canonical model</b> — elements, links, lifecycle, ownership, views as code, and the kinds that assert nothing</li>
            <li><b>The canvas</b> — module map, dependency graph, saved views, layout, styling, shapes, notes</li>
            <li><b>The committed sidecar</b>, with a published schema and a protocol reference for writing it by hand</li>
            <li><b>The headless CLI</b> — render and validate, the publication profile, layout as a constraint, the render manifest</li>
            <li><b>Story books</b> — narrated, audience-tagged page sequences, with present mode and PDF export</li>
            <li><b>The single-file HTML report</b>, with audience modes and a comment loop</li>
            <li><b>Model-only workspaces</b> and the from-scratch onboarding path</li>
            <li><b>Local-only diagnostics</b> — structured logs, redacted bundles, no network sink</li>
          </ul>
        </section>
        <section style="color:var(--st-tgt)">
          <h3>Next <span class="st-chip st-c-tgt">target</span></h3>
          <ul style="color:var(--body)">
            <li><b>Fidelity fixtures across the whole operation catalogue</b> — every operation, no exception</li>
            <li><b>A desktop window</b>, so it stops reading as a dev server</li>
            <li><b>One-command install</b> as a global tool</li>
            <li><b>A workspace brief</b> — a <span class="st-mono">STEMMA.md</span> dropped into every new workspace, so the agent that opens it needs no other document</li>
            <li><b>An embedded font in the vector export</b>, so an SVG cannot re-wrap on a machine that lacks the one it was drawn in</li>
            <li><b>Auto-layout from the command line</b> — the four algorithms are pure functions already, and reachable only from the canvas</li>
            <li><b>Clearer view management</b> — faster switching, better handling</li>
            <li><b>An accessibility pass</b> — keyboard, focus, contrast, reduced motion</li>
            <li><b>A curated sample gallery</b> as living documentation</li>
          </ul>
        </section>
        <section style="color:var(--st-new)">
          <h3>Later <span class="st-chip st-c-new">to&#8209;be&#8209;created</span></h3>
          <ul style="color:var(--body)">
            <li><b>More projections</b> — Mermaid and PlantUML output, a live code-preview pane</li>
            <li><b>Git-backed sessions</b> — a session is a branch, a save is a commit</li>
            <li><b>Deeper validation</b> — background compilation, richer rules inline</li>
            <li><b>Canvas search and filter</b>, and deep-linkable views</li>
            <li><b>A repository integration</b> that regenerates the report on merge and comments the architecture change on the pull request</li>
            <li><b>Hosted report links</b> — the one part that could ever be a service</li>
          </ul>
        </section>
      </div>
    </div>
  </section>

  <!-- ==================== QUESTIONS ====================
       The same answers are emitted as FAQPage in the head. If you edit one,
       edit the other — they are the same $faq array, so there is only one. -->
  <section class="st-sec" id="questions">
    <div class="wrap">
      <div class="st-open reveal">
        <?= eyebrow('Questions') ?>
        <h2 class="st-h2">What people ask first.</h2>
        <p class="st-sub">
          Nine objections, answered where you can find them rather than three emails in.
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
        The argument behind all of it is an essay:
        <a href="/blog/the-diagram-that-cannot-lie/">The Diagram That Cannot Lie</a> — why a diagram that
        <em>can</em> drift always does. On what diagrams are actually for, there is
        <a href="/blog/diagrams-are-conversations-not-documentation/">Diagrams Are Conversations, Not Documentation</a>.
      </p>
    </div>
  </section>

  <!-- ==================== REPOSITORY ==================== -->
  <section class="st-sec st-stage" id="repository">
    <div class="wrap">
      <div class="st-open reveal">
        <?= eyebrow('Where to find it') ?>
        <h2 class="st-h2">The repository.</h2>
      </div>

      <div class="st-repo reveal">
        <a class="st-repo-url" href="<?= e($repo) ?>" rel="noopener"><?= $ghIcon ?>github.com/batas2/Stemma</a>
        <p>
          The source is there, engine included, with the fidelity suite that gates it. It needs the
          .NET&nbsp;10 SDK and Node&nbsp;24 — no database, no container, nothing to procure —
          and <span class="st-mono">./run.sh --dev --workspace samples/AuroraRail</span> opens the
          reference model. What is still in flight is stated on the
          <a href="#roadmap">roadmap</a> and what has already landed is dated in the
          <a href="/stemma/changelog/">changelog</a>, so you can judge the pace yourself.
        </p>
        <p>
          <strong>PolyForm Noncommercial 1.0.0.</strong> Free for personal use, study, hobby projects,
          and for charities, educational institutions, public research bodies and government. Source-available,
          not open source: commercial use of any kind needs a commercial licence, which is
          <a href="/about/#contact">a short email</a> rather than a procurement process.
        </p>
        <p>
          If you want to argue with any of the above — which is more useful to me than agreement —
          <a href="/about/#contact">write to me</a>. Architects who have lived with the drift problem long
          enough to be cynical about it are exactly the readers I want.
        </p>
        <p class="st-plain">
          Reading this with a machine? The whole page is also
          <a href="/stemma/index.md">plain markdown</a>, the changelog is
          <a href="/stemma/changelog/index.md">plain markdown</a>, and the site's context file is
          <a href="/llms.txt">llms.txt</a>. The repository carries a JSON Schema for the presentation
          sidecar and a protocol reference for writing a workspace without opening the app.
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
        Stemma is a pet project. It is built by one person on evenings and weekends, the source is
        readable by anyone, and it is not a company — there is no roadmap I owe anybody, no support line
        and no sales call at the end of this page. A commercial licence exists because the licence had to
        say something and that is the honest thing for it to say, not because there is a funnel behind it.
        The tool exists because I wanted it and nobody had built it.
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
