# Editorial scoring system: build summary

**Date:** 2026-07-27
**Scope:** research, seven-axis scoring model, admin instrumentation, full-archive backfill
**Rubric version:** v1 · **Scoring model:** claude-opus-5
**Status:** shipped locally, tests green, site rebuilt, nothing committed or deployed yet

Companion documents: [SCORING-RUBRIC.md](./SCORING-RUBRIC.md) (the rubric itself),
[POST-PROMPT.md](./POST-PROMPT.md) (the writing prompt, now with a scoring gate),
[BLOG-IMPROVEMENT-PLAN.md](./BLOG-IMPROVEMENT-PLAN.md), [TOPIC-ENGINE.md](./TOPIC-ENGINE.md).

---

## Why this exists

The goal was never the scores. It was the loop: score every post the same way, watch which axes
actually move pageviews and engaged time, then re-fit the model against the evidence and write to
the fit. Recognition and brand-building are the objective; the scores are the instrument.

---

## What the research said

Six findings shaped the design. Two of them contradicted the original plan.

**1. Positivity is the wrong axis.** The Upworthy RCT archive (105,000 headline variants, 22,743
randomised trials, 5.7M clicks over 370M impressions, published in Nature Human Behaviour 2023)
found that **each additional negative word in a headline raises click-through by 2.3%, and positive
words lower it**. Berger & Milkman, across every New York Times article in a three-month window,
found high-arousal emotion (awe, anger, anxiety) drives sharing while low-arousal emotion
(sadness, contentment) suppresses it. The original proposal was to score "pozytywność". Scoring it
would have optimised against reach. The axis became **tension** instead.

**2. Personal beats corporate, measurably.** On the Hacker News front page, blog posts are 45% of
all items, and **personal blogs take 26% of the front page against corporate blogs' 11%**.
Corporate product announcements reach it 8% of the time. First-person and non-promotional is a
distribution channel, not a style preference. Hence the **personal stake** axis, weighted third.

**3. Contrarian-plus-defence is the strongest template.** HN pattern research repeatedly identifies
a contrarian claim the audience half-believes, backed with reasoning or data, as the shape that
spreads. Hence **contrarianism** as its own axis, with a hard rule: an undefended position caps the
axis at 4, because contrarian without argument damages the brand it was meant to build.

**4. The target length sits at the low end of the engagement curve.** Chartbeat, analysing millions
of articles published 2019–2022, found engaged time **rising with word count up to about 4,000
words**, monotonically from 0 to 2,000. POST-PROMPT.md targets ~900. Also: 66% of engaged time
happens below the fold, and replacing plain runs of text with subheads lifted average engaged time
roughly 20% in their NZZ case study. **Left unchanged** — cadence has real value and this was
outside the requested scope — but it deserves a deliberate decision rather than an inherited default.

**5. Readability band.** Adult web prose engages best around Flesch 60–70; shorter sentences
correlate with lower bounce and longer dwell. Folded into the `punch` axis calibration note rather
than added as a separate computed metric.

**6. Bare 1–10 scales are not calibrated, and this constrained the whole build.** The LLM-judge
literature (arXiv:2601.08654 and practitioner reports) documents the same text scoring 7 on one run
and 9 on the next, scores swinging on rubric wording alone, and three recurring failure modes:
rubric-execution drift, unverifiable score attribution, and human-scale misalignment. The
mitigations that hold up are exactly what the rubric now enforces: per-criterion atomic scoring,
anchored band descriptors, a stored evidence quote per score, and a recorded rubric version and
model. Without those, a 1–10 scale is noise and the correlation with traffic never appears.

---

## The model

Seven axes, 1–10, anchored at 1/3/5/7/9 in [SCORING-RUBRIC.md](./SCORING-RUBRIC.md).

| Key | Axis | Category | Weight | Replaces |
|---|---|---|---|---|
| `tension` | Tension | Hook | 0.20 | "pozytywność", inverted |
| `originality` | Originality | Market | 0.18 | "odtwórczość", inverted |
| `personal` | Personal stake | Voice | 0.16 | "personalny" |
| `contrarian` | Contrarianism | Position | 0.15 | "społeczna opinia vs normy" |
| `substance` | Substance | Substance | 0.13 | "czysta wiedza" |
| `punch` | Punch | Craft | 0.10 | "text punchy" |
| `trend` | Trend fit | Market | 0.08 | "dopasowanie do trendów" |

`reach = Σ (weight × score)`, 1.0–10.0. Bands: **≥ 7.5 strong** · **6.0–7.4 solid** ·
**4.5–5.9 weak** · **< 4.5 floor** (rewrite or drop).

The weights are a research-backed **prior, not a measurement**. Re-fitting them against real
traffic is the entire point of the analytics join.

Originality is measured against a named reference list: Simon Willison, Julia Evans, swyx, Gergely
Orosz, Will Larson, Martin Fowler, InfoQ architecture, Thoughtworks Radar. Trend fit is measured
against a dated ten-item trend list (agentic AI and multi-agent workflows, MCP, small language
models, AI for legacy comprehension, platform engineering, AI governance, productivity measurement
under AI, human-in-the-loop design, GEO, inference cost), refreshed quarterly.

---

## What was built

| Change | File |
|---|---|
| Scoring core: parsing, reach, bands, writer, SQLite mirror, Spearman correlation | `lib/scores.php` (new) |
| Score meta parsed off posts; shared atomic meta-writer extracted | `lib/blog.php` |
| Mirror sync, unscored-post gate, mixed-rubric warning | `tools/build.php` |
| **Scores** tab: 7 axes per post next to pageviews and engaged time, sortable, hover rationale; reach badge in Pipeline | `admin.php` |
| Mandatory pre-publish scoring, publish thresholds, two self-check items | `docs/POST-PROMPT.md` |
| The rubric | `docs/SCORING-RUBRIC.md` (new) |
| 175 evidence quotes, one per axis per post | `content/score-notes.json` (new) |
| 80 unit + 15 integration assertions | `tests/unit.php`, `tests/integration.php` |
| `scores.db` excluded from git and from deploy rsync | `.gitignore`, `deploy.sh` |
| Feature documented | `README.md` |

**Storage:** the markdown is the source of truth (git-tracked, survives every rsync deploy, same
idiom as Status/Publish). The build mirrors into `content/scores.db`, which is a cache and is
replaced wholesale on every build so a deleted post cannot leave a stale row behind.

**Visibility:** admin only. The meta lines are stripped from the rendered page, the RSS feed, the
markdown mirrors, and `llms-full.txt` — verified. Publishing self-ratings on a credibility-first
brand invites arguing with the number instead of the argument.

**Defect found and fixed along the way:** `post_write_status` left one extra blank line behind on
every rewrite. Pre-existing and harmless at its old frequency, but scoring rewrites every post
before publish, so it would have compounded. Both writers now share one `post_meta_write` routine
that is byte-for-byte idempotent, covered by a test.

**Verification:** 438 unit + 245 integration assertions, zero failures. Build clean. Backfill
re-run three times with an identical diff each time.

---

## Results: the whole archive scored

25 posts, rubric v1. Sorted by reach.

| Post | Reach | TEN | ORI | PER | CON | SUB | PUN | TRE | Band |
|---|---|---|---|---|---|---|---|---|---|
| multi-agent-is-an-org-chart | 8.0 | 8 | 8 | 8 | 7 | 8 | 9 | 9 | strong |
| the-model-is-the-easy-part | 7.9 | 7 | 7 | 8 | 8 | 9 | 9 | 9 | strong |
| the-diagram-that-cannot-lie | 7.8 | 8 | 8 | 8 | 7 | 8 | 9 | 6 | strong |
| a-company-of-one-staffed-by-agents | 7.8 | 8 | 6 | 9 | 7 | 8 | 9 | 9 | strong |
| probabilistic-metrics | 7.6 | 7 | 8 | 6 | 8 | 9 | 8 | 8 | strong |
| pricing-is-architecture | 7.4 | 7 | 8 | 9 | 6 | 9 | 8 | 3 | solid |
| diagrams-are-conversations-not-documentation | 7.3 | 7 | 6 | 9 | 7 | 8 | 8 | 6 | solid |
| prompt-driven-architecture | 7.2 | 7 | 6 | 8 | 6 | 8 | 8 | 9 | solid |
| edge-cases-written-by-parliament | 7.1 | 6 | 8 | 8 | 7 | 8 | 8 | 3 | solid |
| grinding-dijkstra-at-senior-level | 7.1 | 8 | 6 | 9 | 8 | 6 | 8 | 2 | solid |
| the-migration-that-never-starts | 7.0 | 8 | 7 | 6 | 7 | 8 | 8 | 4 | solid |
| nobody-owns-the-sum | 6.9 | 7 | 7 | 8 | 5 | 8 | 8 | 4 | solid |
| adrs-for-an-audience-of-one | 6.6 | 5 | 6 | 9 | 5 | 8 | 8 | 6 | solid |
| from-signals-to-blips | 6.6 | 5 | 7 | 7 | 5 | 9 | 7 | 8 | solid |
| pricing-the-invisible | 6.5 | 8 | 6 | 4 | 7 | 8 | 8 | 3 | solid |
| stop-calling-it-legacy-code | 6.4 | 8 | 7 | 3 | 7 | 5 | 7 | 8 | solid |
| data-driven-theater | 6.4 | 8 | 6 | 4 | 8 | 6 | 8 | 4 | solid |
| mathematicians-vs-lawyers | 6.4 | 7 | 7 | 5 | 7 | 7 | 8 | 2 | solid |
| decisions-have-an-architecture-too | 6.3 | 6 | 6 | 7 | 6 | 7 | 8 | 3 | solid |
| anti-personas-the-users-i-refuse-to-serve | 6.3 | 6 | 6 | 8 | 6 | 7 | 8 | 2 | solid |
| a-feature-is-not-a-service | 6.2 | 6 | 5 | 8 | 5 | 8 | 8 | 3 | solid |
| the-anti-corruption-layer-is-for-people-too | 6.1 | 6 | 7 | 4 | 7 | 8 | 7 | 3 | solid |
| questions-are-an-architects-sharpest-tool | 6.1 | 5 | 6 | 7 | 6 | 8 | 8 | 2 | solid |
| the-automerger | 4.5 | 4 | 5 | 5 | 2 | 7 | 6 | 2 | **weak** |
| technical-leader-identity-disorder | 3.0 | 2 | 3 | 5 | 1 | 5 | 3 | 2 | **floor** |

---

## Findings

### One post is genuinely bad

**`technical-leader-identity-disorder` — reach 3.0.** Bottom band, and not close to the next post up.

Contrarian 1 and tension 2: it takes no position and nothing is at risk anywhere in it. Originality
3: knowledge-sharing hours, pair programming, growth 1:1s — standard tech-lead advice in the
author's own words. Punch 3: long paragraphs and seven block quotes doing the work the prose should
be doing. It is a 2020 post and it reads like one.

**Recommendation: rewrite it around a single scar, or unpublish it.** It is the only post in the
archive that actively costs credibility rather than merely underperforming.

Second-lowest, **`the-automerger` — 4.5**, weak band. A sound engineering write-up with a real
number in it (90% of merges fully autonomous over two years), but contrarian 2 and trend 2. Keep it;
do not promote it.

### Trend fit is the corpus-wide bottleneck

| Axis | Corpus average |
|---|---|
| Punch | 7.8 |
| Substance | 7.6 |
| Personal | 6.9 |
| Tension | 6.6 |
| Originality | 6.5 |
| Contrarian | 6.2 |
| **Trend fit** | **4.8** |

Craft is not the problem. Punch and substance are the two strongest axes in the archive, by a clear
margin. **Trend fit is the weakest, by a wider one.**

All five strong-band posts score 6–9 on trend. Meanwhile several genuinely excellent posts score 3
or below and sit unread-adjacent: `pricing-is-architecture` (substance 9, personal 9, trend 3),
`edge-cases-written-by-parliament` (originality 8, personal 8, trend 3),
`grinding-dijkstra-at-senior-level` (personal 9, contrarian 8, trend 2). Same craft, no tailwind.

### The cheapest available lift

Four posts carry high tension and contrarianism but a personal stake of only 3 or 4:

- `stop-calling-it-legacy-code` — tension 8, contrarian 7, **personal 3**
- `data-driven-theater` — tension 8, contrarian 8, **personal 4**
- `pricing-the-invisible` — tension 8, contrarian 7, **personal 4**
- `the-anti-corruption-layer-is-for-people-too` — contrarian 7, substance 8, **personal 4**

They argue well and have no scene in them. One concrete anchor each (a named situation, a decision,
a cost paid) would lift the axis carrying the third-largest weight without touching the argument.
Given the HN composition data, this is the highest return per hour available in the archive.

---

## Next steps

1. **Decide on `technical-leader-identity-disorder`**: rewrite or unpublish. Nothing else in the
   archive is at the floor.
2. **Add a personal anchor** to the four high-tension, low-personal posts listed above. Add
   `**Updated:** YYYY-MM-DD HH:MM` when you do — it feeds `dateModified`, `article:modified_time`,
   and the sitemap `lastmod`.
3. **Raise trend fit deliberately** on new posts. The trend list in the rubric is the checklist; the
   five strongest posts in the archive are the proof it works.
4. **Let the correlation table fill.** The Scores tab shows "not enough data" until three scored
   posts have recorded traffic. Once the signs and magnitudes are stable across a few months,
   re-fit `SC_AXES` in `lib/scores.php`, bump `SC_RUBRIC`, and re-score the corpus. Never compare
   scores across rubric versions.
5. **Refresh the trend list quarterly** and re-score only the `trend` axis when you do.
6. **Open question, not acted on:** the ~900-word target versus Chartbeat's engagement curve.

---

## Research sources

- Robertson et al., *Negativity drives online news consumption*, Nature Human Behaviour 7 (2023) 812–822 — <https://www.nature.com/articles/s41562-023-01538-4>
- Berger & Milkman, *What Makes Online Content Viral?*, Journal of Marketing Research (2012) — <https://journals.sagepub.com/doi/10.1509/jmr.10.0353>
- Amplify Partners, *What gets to the front page of Hacker News?* — <https://www.amplifypartners.com/blog-posts/what-gets-to-the-front-page-of-hackernews>
- Chartbeat, *Is there an optimal article length?* — <https://chartbeat.com/resources/articles/is-there-an-optimal-article-length-the-relationship-between-word-count-and-engagement/>
- Chartbeat / NZZ, scroll depth and subheads case study — <https://chartbeat.com/resources/customer/content-scroll-depth-use-case/>
- *From Rubrics to Reliable Scores: Evidence-Grounded Text Evaluation with LLM Judges*, arXiv:2601.08654 — <https://arxiv.org/abs/2601.08654>
- Evidently AI, *F.A.Q. on LLM judges* — <https://www.evidentlyai.com/blog/llm-judges-faq>
- *What Gets Cited: Competitive GEO in AI Answer Engines*, arXiv:2605.25517 — <https://arxiv.org/pdf/2605.25517>
