<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/config.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/components.php';
require_once __DIR__ . '/lib/page.php';
require_once __DIR__ . '/lib/contact.php';

$form = handle_contact_form();
$form_sent    = $form['sent'];
$form_error   = $form['error'];
$form_message = $form['message'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Bartosz Frąckowiak · Solution Architect. Systems, People, and the Machine Between Them.</title>
<meta name="description" content="Bartosz Frąckowiak, solution architect with 12+ years in distributed systems, leading AI transformation at scale. Weekly essays on architecture, people, and how the corporate machine really works. Consulting & training available." />
<meta name="author" content="Bartosz Frąckowiak" />
<meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large" />
<link rel="canonical" href="https://bfrackowiak.pl/" />
<link rel="alternate" type="text/plain" href="https://bfrackowiak.pl/llms.txt" title="LLM context" />
<link rel="alternate" type="application/rss+xml" title="Field Notes · RSS" href="https://bfrackowiak.pl/feed.xml" />

<meta property="og:type" content="website" />
<meta property="og:url" content="https://bfrackowiak.pl/" />
<meta property="og:title" content="Bartosz Frąckowiak · Solution Architect" />
<meta property="og:description" content="Weekly essays on software architecture, the people around it, and the corporate machine they form together. Consulting & training." />
<meta property="og:image" content="https://bfrackowiak.pl/mysefl.jpeg" />
<meta property="og:site_name" content="bfrackowiak.pl" />
<meta name="twitter:card" content="summary_large_image" />

<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@graph": [
    {
      "@type": "Person",
      "@id": "https://bfrackowiak.pl/#person",
      "name": "Bartosz Frąckowiak",
      "alternateName": ["bfrackowiak", "Bartosz Frackowiak"],
      "url": "https://bfrackowiak.pl",
      "image": "https://bfrackowiak.pl/mysefl.jpeg",
      "jobTitle": "Solution Architect",
      "description": "Solution architect with 12+ years of experience in distributed systems, domain-driven design, and AI-native engineering. Writes weekly about architecture, people, and organizational systems.",
      "alumniOf": [
        { "@type": "CollegeOrUniversity", "name": "University of Warsaw" },
        { "@type": "CollegeOrUniversity", "name": "Nicolaus Copernicus University" }
      ],
      "knowsAbout": ["Software Architecture","Distributed Systems","Domain-Driven Design","Event-Driven Architecture","AI Transformation","Claude Code","Technical Leadership","Organizational Design"],
      "sameAs": [
        "https://www.linkedin.com/in/bartoszfrackowiak/",
        "https://medium.com/@bfrackowiak",
        "https://github.com/batas2"
      ]
    },
    {
      "@type": "WebSite",
      "@id": "https://bfrackowiak.pl/#website",
      "name": "Bartosz Frąckowiak",
      "url": "https://bfrackowiak.pl",
      "inLanguage": "en",
      "author": { "@id": "https://bfrackowiak.pl/#person" }
    },
    {
      "@type": "Blog",
      "@id": "https://bfrackowiak.pl/blog/#blog",
      "name": "Field Notes",
      "url": "https://bfrackowiak.pl/blog/",
      "description": "Weekly essays on software architecture, the people around it, and the corporate machine they form together.",
      "author": { "@id": "https://bfrackowiak.pl/#person" }
    },
    {
      "@type": "ProfessionalService",
      "@id": "https://bfrackowiak.pl/#service",
      "name": "Bartosz Frąckowiak: Architecture Consulting & AI Training",
      "url": "https://bfrackowiak.pl",
      "areaServed": "Worldwide",
      "provider": { "@id": "https://bfrackowiak.pl/#person" },
      "hasOfferCatalog": {
        "@type": "OfferCatalog",
        "name": "Services",
        "itemListElement": [
          { "@type": "Offer", "itemOffered": { "@type": "Service", "name": "Software Architecture Consulting" } },
          { "@type": "Offer", "itemOffered": { "@type": "Service", "name": "Software Architecture Training" } },
          { "@type": "Offer", "itemOffered": { "@type": "Service", "name": "Generative AI & Claude Code Training" } }
        ]
      }
    }
  ]
}
</script>

<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="icon" href="/favicon-32.png" sizes="32x32" type="image/png">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">
<meta name="theme-color" media="(prefers-color-scheme: light)" content="#fbfbfd">
<meta name="theme-color" media="(prefers-color-scheme: dark)" content="#0f1014">
<meta property="og:image:width" content="460">
<meta property="og:image:height" content="460">
<meta property="og:image:alt" content="Bartosz Frąckowiak">

<link rel="stylesheet" href="/assets/fonts.css">
<link rel="stylesheet" href="/assets/site.css">
</head>
<body>

<?= site_nav('home') ?>

<main>

  <!-- ================= HERO ================= -->
  <section class="hero wrap">
    <div class="hero-grid">
      <div>
        <span class="eyebrow rise"><span class="blip"></span>Solution Architect · Warsaw, Poland</span>
        <h1 class="rise">Software architecture, <em>with the people left&nbsp;in.</em></h1>
        <p class="lede rise-2">
          I'm <strong>Bartosz Frąckowiak</strong>. For twelve-plus years I've been building distributed
          systems in production, and studying the humans and org charts that shape them. Every week
          I write about both: the architecture, the people, and the corporate machine they form together.
        </p>
        <div class="hero-actions rise-2">
          <a class="btn btn-primary" href="/blog/">Read the field notes
            <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
          </a>
          <a class="btn btn-ghost" href="#contact">Work with me</a>
        </div>
        <p class="proof rise-3">
          <span>12+ years in distributed systems</span>
          <span>PhD research, University of Warsaw</span>
          <span>Top 3% on Toptal</span>
          <span>Architect @ EcoVadis</span>
        </p>
      </div>
      <div class="portrait rise-3">
        <img src="/mysefl.jpeg" alt="Bartosz Frąckowiak" width="460" height="460" fetchpriority="high" decoding="async">
        <div class="portrait-caption">
          <span class="pc-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></span>
          <span><strong>B. Frąckowiak</strong>solution architect</span>
        </div>
      </div>
    </div>
  </section>

  <!-- ================= WRITING ================= -->
  <section id="writing" class="section">
    <div class="wrap">
      <div class="section-head">
        <span class="eyebrow"><span class="blip blip-live"></span>Field Notes</span>
        <h2>The latest signals</h2>
        <p>Weekly essays on architecture, people, and the corporate machine. New signal most Mondays.</p>
      </div>

      <?php include __DIR__ . '/blog/_latest.php'; ?>

      <p style="margin-top:2rem">
        <a class="btn btn-ghost" href="/blog/">All essays &rarr;</a>
        <a class="btn btn-ghost" href="https://medium.com/@bfrackowiak" target="_blank" rel="noopener" style="margin-left:0.6rem">Also on Medium ↗</a>
      </p>
    </div>
  </section>

  <!-- ================= JOURNEY ================= -->
  <section id="journey" class="section">
    <div class="wrap">
      <div class="section-head">
        <span class="eyebrow"><span class="blip"></span>The journey</span>
        <h2>From proving architectures to shipping them — and now teaching machines to help.</h2>
        <p>I started by trying to formalize architecture in a PhD, learned in production why people bend every formal model, and now lead an AI transformation across hundreds of engineers. The essays below are the receipts from each stage.</p>
      </div>

      <ol class="timeline">
        <li>
          <div class="tl-rail">
            <span class="tl-when">2008–2013</span>
            <span class="tl-place">Toruń</span>
          </div>
          <div class="tl-body">
            <h3>Computer science, then the production shock</h3>
            <p><strong>University taught elegant models. Production taught their price.</strong> A CS degree in Toruń, the first .NET jobs, the first incident — and the lasting discovery that no theory survives deployment unchanged.</p>
            <div class="tl-facts"><span>.NET</span><span>first production systems</span></div>
          </div>
        </li>
        <li>
          <div class="tl-rail">
            <span class="tl-when">2013–2017</span>
            <span class="tl-place">University of Warsaw</span>
            <span class="tl-metric"><b>4 yrs</b><i>doctoral research</i></span>
          </div>
          <div class="tl-body">
            <h3>Doctoral research: can architecture be proven?</h3>
            <p><strong>Built a formal language for validating software architecture.</strong> The honest result: boxes and arrows can be formalized — the forces that bend them are organizational. That tension powers every essay on this site.</p>
            <div class="tl-facts"><span>formal methods</span><span>architecture validation</span></div>
          </div>
        </li>
        <li>
          <div class="tl-rail">
            <span class="tl-when">2015–2017</span>
            <span class="tl-place">Cybercom</span>
          </div>
          <div class="tl-body">
            <h3>Consulting years</h3>
            <p><strong>Every engagement was two systems: the codebase, and the organization around it.</strong> Senior consultant and team leader across client projects and offshore teams. The second system was never in the repository — and usually mattered more.</p>
            <div class="tl-facts"><span>consulting</span><span>team leadership</span><span>offshore delivery</span></div>
          </div>
        </li>
        <li>
          <div class="tl-rail">
            <span class="tl-when">2017–2022</span>
            <span class="tl-place">EcoVadis</span>
            <span class="tl-metric"><b>15-yr</b><i>monolith moved</i></span>
          </div>
          <div class="tl-body">
            <h3>Technical Leader: a fifteen-year monolith, moved</h3>
            <p><strong>Led a 15-year legacy monolith toward microservices.</strong> CI/CD where there was none, domain-driven design as the shared language, repositories that merged themselves — and the hard conversations no diagram captures. Also the era that taught me "technical leader" is two jobs wearing one badge.</p>
            <div class="tl-facts"><span>legacy migration</span><span>domain-driven design</span><span>CI/CD</span><span>microservices</span></div>
            <div class="tl-notes"><span class="lbl">Field notes from this era</span>
              <a href="/blog/technical-leader-identity-disorder/">Technical Leader: Identity Disorder</a>
              <a href="/blog/the-automerger/">The Automerger</a>
              <a href="/blog/stop-calling-it-legacy-code/">Stop Calling It Legacy Code</a>
            </div>
          </div>
        </li>
        <li>
          <div class="tl-rail">
            <span class="tl-when">2022–today</span>
            <span class="tl-place">EcoVadis</span>
            <span class="tl-metric"><b>20+</b><i>bounded contexts</i></span>
          </div>
          <div class="tl-body">
            <h3>Solution Architect / Senior Staff Engineer</h3>
            <p><strong>Architecture across 20+ bounded contexts on an event-driven Azure platform.</strong> Decision records, governance, integration design — and the discovery that most architecture work is deciding who decides. The sharpest tool at this altitude is a well-aimed question, not a diagram.</p>
            <div class="tl-facts"><span>event-driven</span><span>Azure</span><span>ADRs &amp; governance</span></div>
            <div class="tl-notes"><span class="lbl">Field notes from this era</span>
              <a href="/blog/decisions-have-an-architecture-too/">Decisions Have an Architecture Too</a>
              <a href="/blog/nobody-owns-the-sum/">Nobody Owns the Sum</a>
              <a href="/blog/questions-are-an-architects-sharpest-tool/">Questions Are an Architect's Sharpest Tool</a>
            </div>
          </div>
        </li>
        <li>
          <div class="tl-rail">
            <span class="tl-when">2025–today</span>
            <span class="tl-place">EcoVadis</span>
            <span class="tl-metric"><b>15</b><i>squads, AI-native</i></span>
          </div>
          <div class="tl-body">
            <h3>Leading an AI transformation at scale</h3>
            <p><strong>AI-native engineering across fifteen squads and several hundred developers.</strong> An agentic tech radar that turns signals into decisions, probabilistic metrics for work no database records, and a prompt-driven architecture practice. This blog exists because that work keeps producing field notes worth publishing.</p>
            <div class="tl-facts"><span>Claude Code</span><span>agentic tech radar</span><span>probabilistic metrics</span></div>
            <div class="tl-notes"><span class="lbl">Field notes from this era</span>
              <a href="/blog/from-signals-to-blips/">From Signals to Blips</a>
              <a href="/blog/probabilistic-metrics/">Probabilistic Metrics</a>
              <a href="/blog/prompt-driven-architecture/">Prompt-Driven Architecture</a>
              <a href="/blog/multi-agent-is-an-org-chart/">Multi-Agent Is an Org Chart</a>
            </div>
          </div>
        </li>
        <li>
          <div class="tl-rail">
            <span class="tl-when">nights &amp; weekends</span>
            <span class="tl-place">own products</span>
            <span class="tl-metric"><b>×3</b><i>shipped solo</i></span>
          </div>
          <div class="tl-body">
            <h3>Building my own products</h3>
            <p><strong>Three products, one person, all in production.</strong> An invoicing SaaS wrestling the government e-invoicing spec and its parliament-written edge cases, an architecture-modeling tool where the diagram cannot lie because it <em>is</em> the code, and a self-hosted scheduler in Go. Proof of shipping — and an endless source of material.</p>
            <div class="tl-facts"><span>SaaS</span><span>Go</span><span>product design</span></div>
            <div class="tl-notes"><span class="lbl">Field notes from this era</span>
              <a href="/blog/a-company-of-one-staffed-by-agents/">A Company of One, Staffed by Agents</a>
              <a href="/blog/the-diagram-that-cannot-lie/">The Diagram That Cannot Lie</a>
              <a href="/blog/edge-cases-written-by-parliament/">Edge Cases Written by Parliament</a>
              <a href="/blog/anti-personas-the-users-i-refuse-to-serve/">Anti-Personas</a>
            </div>
          </div>
        </li>
      </ol>

      <div class="aim">
        <span class="eyebrow"><span class="blip blip-live"></span>The goal</span>
        <p style="margin-top:0.9rem">"To be the architect who connects classic distributed-systems discipline with an AI-native way of working — and to show, in public, one essay a week, how systems and the people who build them actually behave."</p>
        <div class="who">— where all of this is heading</div>
        <div class="aim-cta">
          <a class="btn btn-primary" href="/blog/">Read the field notes
            <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
          </a>
          <a class="btn btn-ghost" href="#contact">Work with me</a>
        </div>
      </div>
    </div>
  </section>

  <!-- ================= SERVICES ================= -->
  <section id="services" class="section">
    <div class="wrap">
      <div class="section-head">
        <span class="eyebrow"><span class="blip"></span>Services</span>
        <h2>Three ways to work with me</h2>
        <p>Everything I offer comes from practice: the same systems, radars, and workflows I write about.</p>
      </div>

      <?php
      $check = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>';
      $arrow = '<svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>';
      $services = [
        [
          'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z"/></svg>',
          'k' => 'Consulting',
          'title' => 'Software Architecture Consulting',
          'desc' => 'Strategic architecture reviews, system design advisory, and hands-on guidance for the decisions where a wrong call costs months.',
          'bullets' => ['Architecture audits &amp; health checks', 'System design for new products', 'Microservices &amp; distributed systems', 'Decision rights &amp; governance (ADRs, RAPID)'],
          'href' => '/services/#engagements',
        ],
        [
          'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.436 60.436 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.57 50.57 0 00-2.658-.813A59.905 59.905 0 0112 3.493a59.902 59.902 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.697 50.697 0 0112 13.489a50.702 50.702 0 017.74-3.342M6.75 15a.75.75 0 100-1.5.75.75 0 000 1.5zm0 0v-3.675A55.378 55.378 0 0112 8.443m-7.007 11.55A5.981 5.981 0 006.75 15.75v-1.5"/></svg>',
          'k' => 'Training',
          'title' => 'Software Architecture Training',
          'desc' => 'Workshops and mentoring that give engineers the architectural thinking their companies keep expecting them to absorb by osmosis.',
          'bullets' => ['Domain-Driven Design workshops', 'Event-driven &amp; CQRS/ES patterns', 'API &amp; integration design', 'Architecture decision records'],
          'href' => '/services/#workshops',
        ],
        [
          'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456zM16.894 20.567L16.5 21.75l-.394-1.183a2.25 2.25 0 00-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 001.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 001.423 1.423l1.183.394-1.183.394a2.25 2.25 0 00-1.423 1.423z"/></svg>',
          'k' => 'AI Training',
          'title' => 'Generative AI &amp; Claude Code Training',
          'desc' => 'I lead an AI transformation across several hundred developers. I\'ll teach your team the workflows that survived contact with reality.',
          'bullets' => ['Claude Code mastery for developers', 'Agentic workflow design', 'Spec- and prompt-driven development', 'AI-assisted architecture &amp; review'],
          'href' => '/services/#workshops',
        ],
      ];
      ?>
      <div class="grid-3">
        <?php foreach ($services as $s): ?>
        <article class="card">
          <div class="card-top">
            <div class="icon-tile"><?= $s['icon'] ?></div>
            <span class="k"><?= $s['k'] ?></span>
          </div>
          <h3><?= $s['title'] ?></h3>
          <p><?= $s['desc'] ?></p>
          <ul>
            <?php foreach ($s['bullets'] as $b): ?>
            <li><?= $check ?><span><?= $b ?></span></li>
            <?php endforeach; ?>
          </ul>
          <a class="more" href="<?= $s['href'] ?>">Details <?= $arrow ?></a>
        </article>
        <?php endforeach; ?>
      </div>

      <p style="margin-top:2rem">
        <a class="btn btn-ghost" href="/services/">See the engagements &rarr;</a>
      </p>
    </div>
  </section>

  <!-- ================= NEWSLETTER ================= -->
  <section class="section">
    <div class="wrap">
      <div class="newsletter" id="newsletter">
        <div class="nl-text">
          <span class="eyebrow"><span class="blip blip-live"></span>The signal</span>
          <h3>Get the next field note</h3>
          <p>One essay on architecture, the people around it, and the corporate machine. Most Mondays, straight to your inbox.</p>
        </div>
        <form action="<?= e((string) cfg('newsletter_action')) ?>" method="post" class="nl-form">
          <label class="visually-hidden" for="nl-email-home">Email address</label>
          <input type="email" id="nl-email-home" name="email" required placeholder="you@example.com" autocomplete="email">
          <input type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px">
          <button type="submit">Subscribe</button>
          <?php if (turnstile_enabled()): ?><div class="nl-captcha"><?= turnstile_widget('newsletter') ?></div><?php endif; ?>
          <span class="nl-note">Double opt-in, one-click unsubscribe. Your address stays here.</span>
        </form>
      </div>
    </div>
  </section>

  <!-- ================= CONTACT ================= -->
  <section id="contact" class="section">
    <div class="wrap">
      <div class="contact-grid">
        <div>
          <span class="eyebrow"><span class="blip"></span>Contact</span>
          <h2 class="display" style="font-size:clamp(1.8rem,3.2vw,2.4rem);margin:0.9rem 0">Let's talk architecture, training, or AI.</h2>
          <p style="color:var(--muted)">Evaluating a consulting engagement, planning team training, or just want to argue with one of my essays: I answer everything within two business days.</p>
          <ul class="contact-links">
            <li><a href="https://www.linkedin.com/in/bartoszfrackowiak/" target="_blank" rel="noopener">
              <span class="ico"><svg fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M20.447 20.452h-3.554v-5.569c0-1.327-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg></span>
              LinkedIn <span class="ext">/in/bartoszfrackowiak ↗</span></a></li>
            <li><a href="https://medium.com/@bfrackowiak" target="_blank" rel="noopener">
              <span class="ico">M</span>
              Medium <span class="ext">@bfrackowiak ↗</span></a></li>
            <li><a href="https://github.com/batas2" target="_blank" rel="noopener">
              <span class="ico"><svg fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 0C5.374 0 0 5.373 0 12c0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23A11.509 11.509 0 0112 5.803c1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576C20.566 21.797 24 17.3 24 12c0-6.627-5.373-12-12-12z"/></svg></span>
              GitHub <span class="ext">/batas2 ↗</span></a></li>
            <li><a href="#newsletter">
              <span class="ico"><svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/></svg></span>
              Newsletter <span class="ext">one essay, most Mondays</span></a></li>
          </ul>
        </div>

        <div class="form-card">
          <?php if ($form_sent): ?>
            <h3>Message sent.</h3>
            <p class="form-note" style="color:var(--muted)">Thanks for reaching out. I will get back to you within 1–2 business days.</p>
            <p class="form-note" style="margin-top:1rem"><a href="#contact">Send another message</a></p>
          <?php else: ?>
            <h3>Send me a message</h3>
            <?php if ($form_error): ?>
              <div class="form-err"><?= e($form_message) ?></div>
            <?php endif; ?>
            <form method="POST" action="/#contact" novalidate>
              <div class="two-col">
                <div class="field">
                  <label for="name">Name</label>
                  <input type="text" id="name" name="name" required value="<?= e((string) ($_POST['name'] ?? '')) ?>" placeholder="Jane Smith">
                </div>
                <div class="field">
                  <label for="email">Email</label>
                  <input type="email" id="email" name="email" required value="<?= e((string) ($_POST['email'] ?? '')) ?>" placeholder="jane@company.com">
                </div>
              </div>
              <div class="field">
                <label for="subject">Topic</label>
                <select id="subject" name="subject">
                  <option value="">Select a topic…</option>
                  <option value="Architecture Consulting">Architecture Consulting</option>
                  <option value="Software Architecture Training">Software Architecture Training</option>
                  <option value="Generative AI / Claude Code Training">Generative AI / Claude Code Training</option>
                  <option value="General Enquiry">General Enquiry</option>
                </select>
              </div>
              <div class="field">
                <label for="message">Message</label>
                <textarea id="message" name="message" required placeholder="Tell me about your team, your system, and what you're trying to achieve…"><?= e((string) ($_POST['message'] ?? '')) ?></textarea>
              </div>
              <?php if (turnstile_enabled()): ?>
              <div class="field">
                <label>Spam check</label>
                <?= turnstile_widget('contact') ?>
              </div>
              <?php endif; ?>
              <button class="submit" type="submit" name="contact_submit">Send message</button>
            </form>
            <?php if (turnstile_enabled()) echo turnstile_script(); ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </section>

</main>

<?= site_footer() ?>
