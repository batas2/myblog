<?php
/**
 * about/index.php — the one page about the person rather than the writing.
 *
 * Hand-written rather than generated: it carries the contact form, which needs
 * a POST handler, and its content changes on the scale of years rather than
 * weekly. The home page is the blog; this is where anyone who wants to know
 * who is writing it ends up, in that order on purpose.
 */

declare(strict_types=1);

require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/components.php';
require_once __DIR__ . '/../lib/page.php';
require_once __DIR__ . '/../lib/schema.php';
require_once __DIR__ . '/../lib/contact.php';

$form = handle_contact_form();
$form_sent    = $form['sent'];
$form_error   = $form['error'];
$form_message = $form['message'];

$person = schema_person() + [
    'alternateName' => ['bfrackowiak', 'Bartosz Frackowiak'],
    'description' => 'Solution architect with 12+ years of experience in distributed systems, domain-driven design, and AI-native engineering. Writes weekly about architecture, people, and organizational systems.',
    'alumniOf' => [
        ['@type' => 'CollegeOrUniversity', 'name' => 'University of Warsaw'],
        ['@type' => 'CollegeOrUniversity', 'name' => 'Nicolaus Copernicus University'],
    ],
    'knowsAbout' => [
        'Software Architecture', 'Distributed Systems', 'Domain-Driven Design',
        'Event-Driven Architecture', 'AI Transformation', 'Technical Leadership',
        'Organizational Design',
    ],
];

$extra  = ld_script([
    '@context' => 'https://schema.org',
    '@type' => 'ProfilePage',
    '@id' => cfg('site_url') . '/about/#page',
    'url' => cfg('site_url') . '/about/',
    'name' => 'About Bartosz Frąckowiak',
    'mainEntity' => $person,
]) . "\n";
$extra .= ld_script(schema_breadcrumbs([
    'Field Notes' => cfg('site_url') . '/',
    'About' => cfg('site_url') . '/about/',
])) . "\n";
$extra .= '<meta property="og:image:width" content="460">' . "\n"
        . '<meta property="og:image:height" content="460">' . "\n"
        . '<meta property="og:image:alt" content="Bartosz Frąckowiak">' . "\n";

echo page_head(
    'About · Bartosz Frąckowiak',
    'Bartosz Frąckowiak, solution architect in Warsaw. Twelve-plus years of distributed systems, doctoral research on architecture validation, and an AI transformation across several hundred developers. How I got here, and how to reach me.',
    cfg('site_url') . '/about/',
    $extra,
    cfg('site_url') . '/myself.jpeg'
);
?>

<?= site_nav('about') ?>

<main>

  <!-- ================= INTRO ================= -->
  <section class="hero wrap">
    <div class="hero-grid">
      <div>
        <span class="eyebrow rise"><span class="blip"></span>Solution Architect · Warsaw, Poland</span>
        <h1 class="rise">Software architecture, <em>with the people left&nbsp;in.</em></h1>
        <p class="lede rise-2">
          I'm <strong>Bartosz Frąckowiak</strong>. For twelve-plus years I've been building distributed
          systems in production, and studying the humans and org charts that shape them. I write about
          both here, most Mondays, because thinking in public is how I find out whether an idea holds.
        </p>
        <p class="lede rise-2" style="margin-top:1rem">
          This page is the short version of how I got here. The long version is
          <a href="/">the essays</a>.
        </p>
        <p class="proof rise-3">
          <span>12+ years in distributed systems</span>
          <span>PhD research, University of Warsaw</span>
          <span>Top 3% on Toptal</span>
          <span>Architect at a global SaaS platform</span>
        </p>
      </div>
      <div class="portrait rise-3">
        <img src="/myself.jpeg" alt="Bartosz Frąckowiak" width="460" height="460" fetchpriority="high" decoding="async">
        <div class="portrait-caption">
          <span class="pc-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></span>
          <span><strong>B. Frąckowiak</strong>solution architect</span>
        </div>
      </div>
    </div>
  </section>

  <!-- ================= JOURNEY ================= -->
  <section id="journey" class="section">
    <div class="wrap">
      <div class="section-head">
        <?= eyebrow('The journey', false) ?>
        <h2>From proving architectures to shipping them, and now teaching machines to help.</h2>
        <p>I started by trying to formalize architecture in a PhD, learned in production why people bend every formal model, and now co-lead an AI transformation across hundreds of engineers. The essays linked below are the receipts from each stage.</p>
      </div>

      <ol class="timeline">
        <li>
          <div class="tl-rail">
            <span class="tl-when">2008–2013</span>
            <span class="tl-place">Toruń</span>
          </div>
          <div class="tl-body">
            <h3>Computer science, then the production shock</h3>
            <p><strong>University taught elegant models. Production taught their price.</strong> A CS degree in Toruń, the first .NET jobs, the first incident, and the lasting discovery that no theory survives deployment unchanged.</p>
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
            <p><strong>Built a formal language for validating software architecture.</strong> The honest result: boxes and arrows can be formalized, and the forces that bend them are organizational. That tension powers every essay on this site.</p>
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
            <p><strong>Every engagement was two systems: the codebase, and the organization around it.</strong> Senior consultant and team leader across client projects and offshore teams. The second system was never in the repository, and usually mattered more.</p>
            <div class="tl-facts"><span>consulting</span><span>team leadership</span><span>offshore delivery</span></div>
          </div>
        </li>
        <li>
          <div class="tl-rail">
            <span class="tl-when">2017–2022</span>
            <span class="tl-place">Global SaaS platform</span>
            <span class="tl-metric"><b>15-yr</b><i>monolith moved</i></span>
          </div>
          <div class="tl-body">
            <h3>Technical Leader: a fifteen-year monolith, moved</h3>
            <p><strong>Led a 15-year legacy monolith toward microservices.</strong> CI/CD where there was none, domain-driven design as the shared language, repositories that merged themselves, and the hard conversations no diagram captures. Also the era that taught me "technical leader" is two jobs wearing one badge.</p>
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
            <span class="tl-place">Global SaaS platform</span>
            <span class="tl-metric"><b>20+</b><i>bounded contexts</i></span>
          </div>
          <div class="tl-body">
            <h3>Solution Architect / Senior Staff Engineer</h3>
            <p><strong>Architecture across 20+ bounded contexts on an event-driven Azure platform.</strong> Decision records, governance, integration design, and the discovery that most architecture work is deciding who decides. The sharpest tool at this altitude is a well-aimed question, not a diagram.</p>
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
            <span class="tl-place">Global SaaS platform</span>
            <span class="tl-metric"><b>15</b><i>squads, AI-native</i></span>
          </div>
          <div class="tl-body">
            <h3>Co-leading an AI transformation at scale</h3>
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
            <p><strong>Three products, one person, all in production.</strong> An invoicing SaaS wrestling the government e-invoicing spec and its parliament-written edge cases, an architecture-modeling tool where the diagram cannot lie because it <em>is</em> the code, and a self-hosted scheduler in Go. Proof of shipping, and an endless source of material.</p>
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
        <?= eyebrow('The goal', true) ?>
        <p style="margin-top:0.9rem">"To be the architect who connects classic distributed-systems discipline with an AI-native way of working, and to show, in public, one essay a week, how systems and the people who build them actually behave."</p>
        <div class="who">— where all of this is heading</div>
        <div class="aim-cta">
          <a class="btn btn-primary" href="/">Read the field notes <?= arrow_icon() ?></a>
          <a class="btn btn-ghost" href="/blog/start/" style="margin-left:0.6rem">Start here</a>
        </div>
      </div>
    </div>
  </section>

  <?php /* ================= NEWSLETTER =================
     This page used to send anyone who wanted the newsletter to /#newsletter,
     which is the bottom of the home page, past twenty-six post cards. So the
     cheap ask — an email address — was the one that cost a page load, while the
     expensive one, a written message, sat right here in a form. On 2026-08-07
     the first real LinkedIn traffic arrived: 22 readers, five of whom got as far
     as this page, and none of whom could subscribe from it.

     It sits after the goal quote and before the contact form on purpose. Someone
     who has just read "one essay a week, in public" is as warm as this page
     makes anybody, and "send me a paragraph" is a much larger thing to ask of
     them than "leave an address".

     A PHP comment rather than an HTML one: the reasoning belongs in the repo,
     the traffic numbers do not belong in every visitor's view-source. */ ?>
  <section class="section" style="padding:4.5rem 0">
    <div class="wrap">
      <?= newsletter_box() ?>
    </div>
  </section>

  <!-- ================= CONTACT ================= -->
  <section id="contact" class="section">
    <div class="wrap">
      <div class="contact-grid">
        <div>
          <?= eyebrow('Contact', false) ?>
          <h2 class="display" style="font-size:clamp(1.8rem,3.2vw,2.4rem);margin:0.9rem 0">Say hello, or argue with an essay.</h2>
          <p style="color:var(--muted)">I answer everything within two business days, and disagreements are the most interesting mail I get.</p>
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
            <?php /* local anchor, not /#newsletter — the form is on this page now */ ?>
            <li><a href="#newsletter">
              <span class="ico"><svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/></svg></span>
              Newsletter <span class="ext">one essay, most Mondays</span></a></li>
          </ul>

          <?php /* The offer, deliberately one paragraph and below the contact links.
                   The blog is the point; this is what I do in daylight, mentioned once
                   for the small number of readers who came looking for it. */ ?>
          <div class="daylight">
            <p><strong>What I do in daylight.</strong> Architecture consulting, and training on domain-driven
              design, event-driven systems, and the AI workflows I write about here. It is the same material,
              in a room, with your systems on the whiteboard instead of mine.
              <a href="/services/">The detail is here</a> if you need it.</p>
          </div>
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
            <form method="POST" action="/about/#contact" novalidate>
              <input type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px">
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
                  <option value="About an essay">About an essay</option>
                  <option value="Architecture Consulting">Architecture Consulting</option>
                  <option value="Software Architecture Training">Software Architecture Training</option>
                  <option value="Generative AI / Claude Code Training">Generative AI / Claude Code Training</option>
                  <option value="General Enquiry">General Enquiry</option>
                </select>
              </div>
              <div class="field">
                <label for="message">Message</label>
                <textarea id="message" name="message" required placeholder="What are you working on, or which essay did I get wrong?"><?= e((string) ($_POST['message'] ?? '')) ?></textarea>
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

<?= reveal_script() ?>
<?= site_footer() ?>
