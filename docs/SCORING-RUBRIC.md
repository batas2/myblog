# Field Notes: the scoring rubric (v1)

Every post gets seven scores, 1–10, one per axis. The scores live in the post's own markdown
(`**Scores:**` / `**Scored:**`), the build mirrors them into `content/scores.db`, and
`/admin.php` shows them next to what analytics actually recorded for that page.

**The point of this file is not the numbers. It is the loop:** score every post the same way,
watch which axes move pageviews and engaged time, then re-fit the weights in
[`lib/scores.php`](../lib/scores.php) against the evidence. Until the corpus is big enough for
that, the weights below are a research-backed prior, nothing more.

Companion to [POST-PROMPT.md](./POST-PROMPT.md) (how a post gets written),
[TOPIC-ENGINE.md](./TOPIC-ENGINE.md) (where topics come from), and
[BLOG-IMPROVEMENT-PLAN.md](./BLOG-IMPROVEMENT-PLAN.md) (why any of this matters).

---

## The seven axes

| Key | Axis | Category | Weight |
|---|---|---|---|
| `tension` | Tension | Hook | 0.20 |
| `originality` | Originality | Market | 0.18 |
| `personal` | Personal stake | Voice | 0.16 |
| `contrarian` | Contrarianism | Position | 0.15 |
| `substance` | Substance | Substance | 0.13 |
| `punch` | Punch | Craft | 0.10 |
| `trend` | Trend fit | Market | 0.08 |

Each axis below gives anchors at 1, 3, 5, 7, and 9. Scores of 2, 4, 6, 8, 10 mean "between
these two, closer to the lower/upper one". **When torn between two bands, take the lower.**

### 1. Tension (`tension`)

How much friction, stakes, or discomfort the piece creates, and whether it sustains it.

- **1** Pure agreement. Nothing is at risk. The reader nods and leaves.
- **3** A mild observation. A problem is named, but nobody loses anything by ignoring it.
- **5** A real problem with a named cost, resolved comfortably before the end.
- **7** Names something the reader recognises and would rather not admit. The cost is concrete.
- **9** Puts a widely-held practice on trial and refuses to let the reader off. The discomfort outlives the last line.

**Tension is not pessimism.** It measures the stakes an essay raises, not how bleak it leaves the
reader. An essay that names a real cost and then shows the way out scores high here; POST-PROMPT.md
requires exactly that shape. What lowers this axis is softening the problem, not resolving it.

*Why this axis and not "positivity":* the Upworthy RCT archive (105k headline variants, 22,743
randomised trials, 5.7M clicks over 370M impressions) found **each additional negative word in a
headline raises click-through by 2.3%, and positive words lower it**. Berger & Milkman, across
every NYT article in a three-month window, found high-arousal emotion (awe, anger, anxiety)
drives sharing while low-arousal emotion (sadness, contentment) suppresses it. Optimising for
pleasantness optimises against reach. Optimise for *charge*.

### 2. Originality (`originality`)

Distance from what the reference blogs (below) have already published.

- **1** Restates a well-known post by a bigger voice without extending it.
- **3** A known idea in fresh words.
- **5** A known idea plus one genuine extension.
- **7** A framing not found elsewhere. A search of the reference list turns up nothing equivalent.
- **9** A new concept, with a name other people could cite.

**Method:** before scoring, actually search the reference list for the thesis. Write down what
you found. A score of 7+ without that search is a guess.

### 3. Personal stake (`personal`)

- **1** Could have been written by anyone. No first person, or first person with no content behind it.
- **3** First person, but the anchor is generic ("in my experience", "I've often seen").
- **5** One real situation, described lightly.
- **7** A specific scene with a decision, a cost, and the author's own mistake in it, inside the first four paragraphs.
- **9** The essay could not exist without this author. The scar is load-bearing for the argument.

*Why:* on the Hacker News front page, blog posts are 45% of all items, and **personal blogs take
26% of the front page against corporate blogs' 11%**. Corporate product announcements reach it 8%
of the time. First-person and non-promotional is not a style preference, it is the distribution channel.

### 4. Contrarianism (`contrarian`)

Distance from consensus, and whether the post defends the distance.

- **1** Consensus, undefended.
- **3** Consensus with a caveat.
- **5** A minority position, stated but not defended.
- **7** A position the audience half-believes but rarely says out loud, defended with reasoning or data.
- **9** Directly contradicts a norm the audience professes, and answers the obvious objection inside the text.

**Hard rule:** a position taken and *not* defended caps this axis at **4**. Contrarian without
argument is noise, and noise damages the brand it was meant to build.

### 5. Substance (`substance`)

Transferable knowledge per thousand words.

- **1** Opinion only. Nothing to reuse.
- **3** One reusable idea, stated abstractly.
- **5** A clear model or heuristic the reader can still name afterwards.
- **7** A model, plus the conditions under which it fails, plus at least one concrete artifact (a number, a diagram, code, a named tool, a decision record).
- **9** The reader can apply it on Monday and knows when not to.

### 6. Punch (`punch`)

- **1** Long undifferentiated paragraphs, hedged, adverb-heavy, no rhythm.
- **3** Readable but flat. Sentences average 25+ words. No line worth quoting.
- **5** Clean prose, some variation, one decent line.
- **7** Deliberate rhythm (short sentence after long), concrete verbs, an aphorism that earns its bold.
- **9** Nearly every section has a line that survives out of context. No sentence could be cut without loss.

*Calibration:* adult web prose engages best around Flesch 60–70; shorter sentences correlate with
lower bounce and longer dwell. Subheads instead of plain runs of text lifted average engaged time
about 20% in Chartbeat's NZZ case study, and 66% of engaged time happens below the fold.

### 7. Trend fit (`trend`)

Proximity to what the market is actively arguing about, judged against the dated trend list below.

- **1** A topic nobody is discussing, and not by design.
- **3** Evergreen, with no current hook.
- **5** Adjacent to a live conversation; the connection is implicit.
- **7** Explicitly inside a named live conversation from the list.
- **9** On the leading edge of one, with a position most posts do not have yet.

**Trend fit decays.** It is the lowest-weighted axis for exactly that reason. Re-score this axis
whenever the trend list is refreshed; leave the other six alone unless the rubric version changes.

---

## Reach score

```
reach = Σ (weight_axis × score_axis)        # 1.0 – 10.0, one decimal
```

Weights live in one constant, `SC_AXES` in [`lib/scores.php`](../lib/scores.php). Changing them is
a one-line edit, which is the whole design: **when there is enough traffic data, re-fit the weights
against measured pageviews and engaged time and bump the rubric version.**

The v1 weights are ordered by strength of external evidence: tension (largest measured CTR effect),
originality and personal stake (the HN front-page composition data), contrarianism (the
strongest-performing template in HN pattern research), then substance, punch, and trend fit.

| Reach | Band | What to do |
|---|---|---|
| < 4.5 | **floor** | Rewrite it or drop it. It is not earning its place in the archive. |
| 4.5 – 5.9 | weak | Fix the lowest axis before promoting it anywhere. |
| 6.0 – 7.4 | solid | Publish. Normal distribution effort. |
| ≥ 7.5 | strong | Publish and push: HN, LinkedIn, newsletter, cross-posts. |

---

## How to score (and why it has to be this strict)

LLM judges are **not calibrated on bare 1–10 scales**. The same text scores 7 on one run and 9 on
the next; minor rubric rewording shifts every score; known failure modes are rubric-execution
drift, unverifiable score attribution, and human-scale misalignment. The fixes that hold up in the
literature are exactly the ones this rubric enforces:

1. **Score one axis at a time, in isolation.** Do not read the other axes' scores first.
2. **Every score carries one verbatim quote from the post as evidence.** No quote, no score.
   A score you cannot attach a sentence to is a vibe.
3. **Read the whole post.** Scoring from the subtitle is worthless.
4. **When torn, take the lower band.**
5. **Record rubric version and model** in the `**Scored:**` line, so scores from different
   rubrics are never silently compared.
6. **Re-score the whole corpus when the rubric version changes.** Mixed-version comparisons are
   meaningless, and the admin panel will show the version so you can tell.

### Where it goes

In the post source, next to the other meta lines:

```markdown
**Scores:** tension=7 punch=8 trend=5 personal=9 substance=6 originality=7 contrarian=8
**Scored:** 2026-07-27 rubric=v1 model=claude-opus-5
```

Evidence quotes and one-line rationales go in `content/score-notes.json`, keyed by slug and axis.
That file is optional: the numbers work without it, and the admin panel shows the rationale when
it is there.

In `/admin.php` → **Scores**, clicking any number opens the reasoning behind it: what the axis
measures, the anchor band the score sits in, the stored rationale for that post, and the arithmetic
share it contributes to the reach score. Clicking the reach badge decomposes the whole weighted sum
and names the axis where a rewrite buys the most reach. The band definitions and axis prose come
from `SC_AXIS_DOC` / `SC_BAND_DOC` in [`lib/scores.php`](../lib/scores.php), which mirror this file —
change one, change the other.

---

## Reference blogs (the Originality axis is measured against these)

- Simon Willison — <https://simonwillison.net/>
- Julia Evans — <https://jvns.ca/>
- swyx — <https://www.swyx.io/>
- Gergely Orosz, The Pragmatic Engineer — <https://newsletter.pragmaticengineer.com/>
- Will Larson — <https://lethain.com/> and <https://staffeng.com/>
- Martin Fowler — <https://martinfowler.com/>
- InfoQ architecture — <https://www.infoq.com/architecture-design/>
- Thoughtworks Technology Radar — <https://www.thoughtworks.com/radar>

---

## Trend list v1 (dated 2026-07-27)

Refresh this list quarterly. Re-score the `trend` axis when you do.

1. Agentic AI and multi-agent workflows (the "digital assembly line" framing)
2. MCP and tool/interop protocols between agents and systems
3. Small language models as a counterweight to frontier-model cost
4. AI applied to legacy comprehension and modernisation, not just to writing new code
5. Platform engineering and the internal developer platform
6. AI governance, auditability, explainability, and enterprise trust
7. Measuring developer productivity when AI writes some of the code
8. Human-in-the-loop system design and where the human boundary sits
9. GEO: being cited by answer engines rather than ranked by search engines
10. Inference cost and capacity as an architectural constraint

---

## Research behind this rubric

- Robertson et al., *Negativity drives online news consumption*, Nature Human Behaviour 7 (2023) 812–822 — <https://www.nature.com/articles/s41562-023-01538-4>
- Berger & Milkman, *What Makes Online Content Viral?*, Journal of Marketing Research (2012) — <https://journals.sagepub.com/doi/10.1509/jmr.10.0353>
- Amplify Partners, *What gets to the front page of Hacker News?* — <https://www.amplifypartners.com/blog-posts/what-gets-to-the-front-page-of-hackernews>
- Chartbeat, *Is there an optimal article length?* — <https://chartbeat.com/resources/articles/is-there-an-optimal-article-length-the-relationship-between-word-count-and-engagement/>
- Chartbeat / NZZ, scroll depth and subheads case study — <https://chartbeat.com/resources/customer/content-scroll-depth-use-case/>
- *From Rubrics to Reliable Scores: Evidence-Grounded Text Evaluation with LLM Judges*, arXiv:2601.08654 — <https://arxiv.org/abs/2601.08654>
- Evidently AI, *F.A.Q. on LLM judges* — <https://www.evidentlyai.com/blog/llm-judges-faq>
- *What Gets Cited: Competitive GEO in AI Answer Engines*, arXiv:2605.25517 — <https://arxiv.org/pdf/2605.25517>
