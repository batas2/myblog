<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/components.php';
require_once __DIR__ . '/../lib/page.php';

$calendly = (string) cfg('calendly_url', '');

$ld = json_encode([
    '@context' => 'https://schema.org',
    '@graph' => [
        [
            '@type' => 'WebPage',
            '@id' => 'https://bfrackowiak.pl/services/#page',
            'url' => 'https://bfrackowiak.pl/services/',
            'name' => 'Work with me · Bartosz Frąckowiak',
            'isPartOf' => ['@id' => 'https://bfrackowiak.pl/#website'],
            'breadcrumb' => [
                '@type' => 'BreadcrumbList',
                'itemListElement' => [
                    ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => 'https://bfrackowiak.pl/'],
                    ['@type' => 'ListItem', 'position' => 2, 'name' => 'Work with me', 'item' => 'https://bfrackowiak.pl/services/'],
                ],
            ],
        ],
        [
            '@type' => 'ProfessionalService',
            '@id' => 'https://bfrackowiak.pl/services/#service',
            'name' => 'Bartosz Frąckowiak — Architecture Consulting, Workshops & AI-Native Training',
            'url' => 'https://bfrackowiak.pl/services/',
            'areaServed' => 'Worldwide',
            'provider' => ['@id' => 'https://bfrackowiak.pl/#person'],
            'hasOfferCatalog' => [
                '@type' => 'OfferCatalog',
                'name' => 'Engagements',
                'itemListElement' => [
                    ['@type' => 'Offer', 'price' => '8000',  'priceCurrency' => 'PLN', 'itemOffered' => ['@type' => 'Service', 'name' => 'Architecture Health Check']],
                    ['@type' => 'Offer', 'price' => '12000', 'priceCurrency' => 'PLN', 'itemOffered' => ['@type' => 'Service', 'name' => 'AI-Native Architecture Review']],
                    ['@type' => 'Offer', 'price' => '40000', 'priceCurrency' => 'PLN', 'itemOffered' => ['@type' => 'Service', 'name' => 'Architecture Advisory Sprint']],
                    ['@type' => 'Offer', 'price' => '15000', 'priceCurrency' => 'PLN', 'itemOffered' => ['@type' => 'Service', 'name' => 'DDD & Event Sourcing Workshop (1 day)']],
                    ['@type' => 'Offer', 'price' => '18000', 'priceCurrency' => 'PLN', 'itemOffered' => ['@type' => 'Service', 'name' => 'Claude Code Mastery Workshop (1 day)']],
                    ['@type' => 'Offer', 'price' => '8000',  'priceCurrency' => 'PLN', 'itemOffered' => ['@type' => 'Service', 'name' => 'Advisory Retainer (monthly)']],
                    ['@type' => 'Offer', 'price' => '20000', 'priceCurrency' => 'PLN', 'itemOffered' => ['@type' => 'Service', 'name' => 'Fractional Principal Engineer (monthly)']],
                ],
            ],
        ],
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

$extra = <<<HTML
<script type="application/ld+json">{$ld}</script>
<style>
.svc-hero { padding: 6.5rem 0 1rem; }
.svc-hero h1 { margin: 0.9rem 0 1rem; }
.svc-hero .lede { max-width: 46rem; }
.price-row { display: flex; align-items: baseline; gap: 0.6rem; flex-wrap: wrap; margin-top: 1.1rem; padding-top: 0.9rem; border-top: 1px solid var(--line); }
.price-row .price { font-weight: 800; font-size: 1.28rem; color: var(--ink); letter-spacing: -0.01em; }
.price-row .alt { font-size: 0.85rem; color: var(--muted); }
.price-row .len { margin-left: auto; font-family: 'JetBrains Mono', monospace; font-size: 0.66rem; letter-spacing: 0.09em; text-transform: uppercase; color: var(--muted); }
.badge-flag { display: inline-block; font-family: 'JetBrains Mono', monospace; font-size: 0.62rem; letter-spacing: 0.11em; text-transform: uppercase; color: var(--paper); background: var(--accent); border-radius: 999px; padding: 0.28em 0.75em; margin-left: 0.6rem; vertical-align: middle; }
.how { counter-reset: step; display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); margin-top: 2rem; }
.how li { list-style: none; background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 1.1rem 1.25rem; }
.how li::before { counter-increment: step; content: counter(step, decimal-leading-zero); font-family: 'JetBrains Mono', monospace; font-size: 0.72rem; letter-spacing: 0.1em; color: var(--accent); display: block; margin-bottom: 0.5rem; }
.how li b { display: block; color: var(--ink); margin-bottom: 0.3rem; }
.how li p { font-size: 0.88rem; color: var(--muted); margin: 0; }
.small-print { font-size: 0.85rem; color: var(--muted); margin-top: 2rem; max-width: 46rem; }
.read-first { margin-top: 0.9rem; font-size: 0.92rem; color: var(--muted); }
</style>
HTML;

echo page_head(
    'Work with me · Bartosz Frąckowiak — architecture consulting, workshops & AI-native training',
    'Fixed-scope architecture engagements, team workshops (DDD, Claude Code), and ongoing advisory — from the architect who runs an AI transformation across several hundred developers. Transparent pricing.',
    'https://bfrackowiak.pl/services/',
    $extra
);

echo site_nav('services');
?>

<main>

  <section class="svc-hero wrap">
    <?= eyebrow('Work with me') ?>
    <h1 class="display" style="font-size:clamp(2rem,4.5vw,3rem)">The essays are free.<br><em>This is how the work happens.</em></h1>
    <p class="lede">
      Everything I learn ends up <a href="/blog/">on the blog</a> — in public, for free, most Mondays.
      When you want it applied to <em>your</em> system, with your constraints and your team in the room,
      these are the shapes I work in. Fixed scope, fixed price, written deliverables. No hourly meters.
    </p>
    <p class="read-first">New here? The honest starting point is <a href="/blog/start/">reading a few essays</a> — it costs nothing and tells you exactly how I think.</p>
  </section>

  <!-- ============ ENGAGEMENTS ============ -->
  <section id="engagements" class="section">
    <div class="wrap">
      <div class="section-head">
        <?= eyebrow('Advisory engagements') ?>
        <h2>Fixed-scope, one week or less</h2>
        <p>Each one ends with a written artifact your team keeps — not a slide deck that evaporates.</p>
      </div>

      <div class="grid-3">
        <article class="card">
          <div class="card-top"><span class="k">Audit</span></div>
          <h3>Architecture Health Check</h3>
          <p>A structured look at your system: top 5 risks across scalability, reliability, and team velocity, quick wins, and a 3–6 month roadmap.</p>
          <ul>
            <li><span>15-page written report</span></li>
            <li><span>60-minute review call, recorded</span></li>
            <li><span>As-is architecture diagram</span></li>
          </ul>
          <div class="price-row"><span class="price">8 000 PLN</span><span class="alt">· €2 200</span><span class="len">1 week</span></div>
        </article>

        <article class="card">
          <div class="card-top"><span class="k">Audit + AI</span><span class="badge-flag">Flagship</span></div>
          <h3>AI-Native Architecture Review</h3>
          <p>The Health Check plus an AI-tooling adoption plan: where agentic workflows fit your codebase, where they don't, and how to start in two weeks without buying anything.</p>
          <ul>
            <li><span>Everything in the Health Check</span></li>
            <li><span>Claude Code / agentic workflow rollout plan</span></li>
            <li><span>Built on the playbook I run across fifteen squads</span></li>
          </ul>
          <div class="price-row"><span class="price">12 000 PLN</span><span class="alt">· €3 200</span><span class="len">1 week</span></div>
        </article>

        <article class="card">
          <div class="card-top"><span class="k">Deep dive</span></div>
          <h3>Architecture Advisory Sprint</h3>
          <p>One hard problem — a migration, a scaling wall, a new product's architecture — worked embedded with your team for a week.</p>
          <ul>
            <li><span>Full RFC (20–40 pages) with decision framework</span></li>
            <li><span>Optional proof-of-concept</span></li>
            <li><span>6-month implementation roadmap</span></li>
          </ul>
          <div class="price-row"><span class="price">40 000 PLN</span><span class="alt">· €10 000</span><span class="len">5 days</span></div>
        </article>
      </div>
    </div>
  </section>

  <!-- ============ WORKSHOPS ============ -->
  <section id="workshops" class="section">
    <div class="wrap">
      <div class="section-head">
        <?= eyebrow('Team workshops') ?>
        <h2>One day, max 12 people, your codebase</h2>
        <p>Hands-on, on-site or remote. No generic slides — the exercises run on your systems, and the artifacts stay with the team.</p>
      </div>

      <div class="grid-3">
        <article class="card">
          <div class="card-top"><span class="k">Architecture</span></div>
          <h3>DDD &amp; Event Sourcing — 1 day</h3>
          <p>Bounded contexts, event storming on a real domain of yours, and an honest map of where DDD pays off and where it's ceremony.</p>
          <ul>
            <li><span>Live event-storming session</span></li>
            <li><span>ADR template pack</span></li>
            <li><span>Anti-pattern review in your code</span></li>
          </ul>
          <div class="price-row"><span class="price">15 000 PLN</span><span class="alt">· €4 000</span><span class="len">1 day</span></div>
        </article>

        <article class="card">
          <div class="card-top"><span class="k">AI-native</span></div>
          <h3>Claude Code Mastery — 1 day</h3>
          <p>The workflows that survived contact with several hundred developers: fundamentals, custom skills, MCP servers, spec-driven development — finished with a live exercise on your repository.</p>
          <ul>
            <li><span>Everyone leaves with a working setup</span></li>
            <li><span>Custom skills + MCP hands-on</span></li>
            <li><span>60-minute follow-up call included</span></li>
          </ul>
          <div class="price-row"><span class="price">18 000 PLN</span><span class="alt">· €4 800</span><span class="len">1 day</span></div>
        </article>

        <article class="card">
          <div class="card-top"><span class="k">Ongoing</span></div>
          <h3>Ongoing advisory</h3>
          <p>For teams that want the second opinion on tap rather than a one-off engagement.</p>
          <ul>
            <li><span><strong>Advisory Retainer</strong> — 8h/month, async + 2 calls, &lt;24h response · <strong>8 000 PLN/mo</strong> <span style="color:var(--muted)">(€2 200)</span>, 3-month minimum</span></li>
            <li><span><strong>Fractional Principal Engineer</strong> — 20h/month embedded, weekly standup, owns one critical RFC per month · <strong>20 000 PLN/mo</strong> <span style="color:var(--muted)">(€5 500)</span>, 6-month minimum, max 2 clients at a time</span></li>
          </ul>
        </article>
      </div>
    </div>
  </section>

  <!-- ============ HOW IT WORKS / BOOK ============ -->
  <section id="book" class="section">
    <div class="wrap">
      <div class="section-head">
        <?= eyebrow('How it works') ?>
        <h2>Start with a conversation, not a contract</h2>
        <p>A free 30-minute call. You talk about your system, I ask questions, and I tell you honestly which of the above fits — or that none of it does and you don't need me. That happens too, and I'd rather say it on a free call.</p>
      </div>

      <ol class="how">
        <li><b>Discovery call — 30 min, free</b><p>Your stack, your team, where it hurts. No pitch deck.</p></li>
        <li><b>Fixed proposal within 24h</b><p>Scope, deliverable, date, price. One page, no surprises.</p></li>
        <li><b>The work</b><p>Delivered on the stated date, with the written artifact your team keeps.</p></li>
      </ol>

      <p style="margin-top:2.2rem">
        <?php if ($calendly !== ''): ?>
          <a class="btn btn-primary" href="<?= e($calendly) ?>" target="_blank" rel="noopener">Book the free 30-min call <?= arrow_icon() ?></a>
          <a class="btn btn-ghost" href="/#contact" style="margin-left:0.6rem">Or send a message</a>
        <?php else: ?>
          <a class="btn btn-primary" href="/#contact">Get in touch — I reply within 2 business days <?= arrow_icon() ?></a>
        <?php endif; ?>
      </p>

      <p class="small-print">
        Remote-first, worldwide; on-site within the EU. Engagements run under a standard B2B invoice
        (EU reverse-charge VAT for international clients) with a mutual NDA on request. Workshop
        materials remain yours to reuse internally. If a delivered engagement doesn't match its
        stated scope, you don't pay for the gap.
      </p>
    </div>
  </section>

  <!-- ============ NEWSLETTER ============ -->
  <section class="section">
    <div class="wrap">
      <?= newsletter_box() ?>
    </div>
  </section>

</main>

<?= site_footer() ?>
