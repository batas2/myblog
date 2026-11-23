# Field Notes: whose English, round 5

Written 2026-11-14. Companion to [POST-PROMPT.md](./POST-PROMPT.md) (v4, the rules),
[HUMAN-VOICE-PLAN.md](./HUMAN-VOICE-PLAN.md) (round 3) and [READABILITY-PLAN.md](./READABILITY-PLAN.md) (round 4).

**The brief:** the texts still read as written by AI. They should read as written by the author:
a solution architect, Polish, fifteen years in IT, fluent in English and not native in it.

**Method:** read the rules and all four earlier rounds, measured all 35 posts for the tells the
existing lint cannot see, compared them with the texts he wrote himself, and ran two research
sweeps (what survives after the known tells are removed; how a fluent Polish professional writes
English). Sources at the bottom, each marked by how far it can be trusted.

---

## The short version

Four rounds removed things from a draft the model wrote. Every post passes every check, and the
posts still read as one polished native essayist. Three findings explain why.

1. **The gates were calibrated on the model.** The hard rhythm floor (CV ≥ 0.60, at least 15% of
   sentences of six words or fewer) was set just under the ghostwritten average. The two texts
   Bartosz wrote himself measure CV 0.49 to 0.50 and 11 to 12%. His own writing failed the gate
   that was supposed to protect his voice, so every round pushed the corpus away from him.
2. **The remaining tells are in the movement, not in the words.** A paragraph sets up and lands on
   a short line. In his own long text 3% of paragraphs end on a sentence of eight words or fewer.
   In the ghostwritten posts it was 13 to 59%. Fragments: 4 per thousand words in his text, up to
   19 in the corpus. And the banned "it's not X, it's Y" survived as two sentences.
3. **The register is not his.** "The way milk goes off", "a rumor with better fonts", "doing the
   rounds", "earns its keep". No Polish engineer says these in a design review. The research on
   fluent non-native writers says they are recognised by word choice, and hardly at all by
   sentence length or grammar.

And one finding about method, which matters more than the three above: a text that a model drafted
and a person then edited stays measurably closer to the model, even when the person is sure it now
sounds like them (ACL 2026, pre-registered, N=81). Rules applied afterwards cannot fix this. The
order of work has to change.

## What the measurements say

| | his 2020 long text | ghostwritten posts | the five posts after this round |
|---|---|---|---|
| Paragraphs ending on a sentence ≤ 8 words | 3% | 13–59% | 0–12% |
| Sentences ≤ 4 words, per 1000 | 4.1 | 4–19 | 0–5.5 |
| Sentence-length CV | 0.50 | 0.63 mean, up to 0.81 | 0.47–0.55 |
| Sentences ≤ 6 words | 12% | 20% mean, up to 33% | 9–15% |
| "we / our / us" per 1000 | 11.5 | 0–7 | unchanged, needs his material |
| Contractions per 1000 | 10 | 2–4 in the ten newest | unchanged |

Caveat: the comparison text is one essay of 2,400 words, and the repo copy was lightly smoothed
on import. It is the best baseline available and it is thin. The Medium originals would be better.

Other patterns counted in the corpus: the ritual disclaimer paragraph is still in at least 6 posts (the regex finds 6, reading finds more) although
round 3 retired it, because the prompt still said "never skip it". Bold aphorisms close 1 to 3
paragraphs in most posts although the prompt allows one aphorism. Almost every post ends by
returning to its opening image.

## What the research adds

Process (strongest evidence):

- Post-edited LLM drafts stay closer to LLM text than to the author's unassisted writing, and the
  authors do not notice. [peer-reviewed, ACL 2026]
- Handing over the drafting stage costs the most ownership; help with planning costs the least.
  [peer-reviewed, DIS 2026]
- LLM rewriting keeps the meaning and narrows the style, across 880,000 texts. [peer-reviewed,
  Nature Human Behaviour, read via the university release]
- An "edit this" instruction changes the argument too, and pushes essays toward neutral.
  [preprint]

Tells that survive a scrub:

- Inanimate subjects with animate verbs, measured in nine current models. [preprint]
- Tells move when banned. One model generation dropped em dashes by 99% and started writing "this
  matters" about a hundred times more often than people. [vendor study, reported by TechCrunch]
- "Every thought receives a landing sentence", colon reveals, even paragraph lengths, no
  digression. [practitioner lists: tropes.fyi, Rough at Sea]
- The comma-and-period overcorrection is now listed as a sign of its own. [Wikipedia, community]
- No study measures a fingerprint of prose written to an anti-AI list. That part is our own
  measurement above plus opinion.

How a fluent Polish professional writes English:

- Natives and fluent non-natives are separated mainly by lexical choice; sentence length and depth
  separate them at near chance. [NLP literature: Berti et al., Rabinovich et al.]
- Polish learner corpora show fewer phrasal-verb types and a preference for the single Latinate
  verb. [corpus studies, mixed between written and spoken data]
- Polish expository style elaborates and takes side steps before the point. [Duszak]
- Readers are not bothered by a few incidental non-native slips, and trust drops when errors
  become noticeable. [business-communication studies]
- A model asked to sound non-native produces caricature errors, or stays too polished. [preprints
  and press coverage, thin]
- Not verified by any source found: which connectors he in particular overuses, and anything
  specific to long-term professionals with a Slavic first language.

Platforms, changed since round 2:

- Hacker News guidelines now say not to post generated or AI-edited text. That is a rule about
  honesty and not about detection. Whether a given essay is his is for him to judge before
  submitting.
- Substack lets readers run a detector on a post since July 2026. No penalty attached.

## What changed

### 1. The order of work (POST-PROMPT "Where the text comes from")

He supplies the source, in Polish or rough English. The model translates before it composes and
marks every paragraph it wrote without a source sentence with `<!-- M -->`. After the draft the
model critiques and does not rewrite. His sentences are frozen. `voice_check` fails a post that
still carries a marker.

This is round 3's "flip the pipeline" with two things it lacked: Polish as the source language,
which removes the model's English from the first step, and a mark on the page that shows whose
paragraph each one is.

### 2. A register to aim at (POST-PROMPT "Whose English this is")

The earlier rounds only said what to avoid, and the model filled the gap with its default. Now
there is a positive target: the English of a design review call. Plain single verbs, visible
connectors, paragraphs allowed to elaborate, "we" for the team, no native idiom, no abstract noun
doing a person's verb. And no planted mistakes, ever.

### 3. The gates (tools/voice_check.php)

- Hard rhythm floors moved under his own numbers: CV 0.42, short sentences 7%.
- A cadence tier, advisory: short paragraph endings, fragments, denial-then-claim pairs, bold
  closers, repeated openings, the ritual disclaimer, too many short sentences.
- One new hard check: no `<!-- M -->` marker left in a post.

One side effect to know about: `read_check` counts commas per sentence, and fragments made that
number look good. Joining fragments back into sentences raises it. The five edited posts stay under
the floor, but the aim of 0.60 is partly an artefact of the drum and should be re-fitted when more
of his own text exists to calibrate on.

### 4. Rules reversed or retired in the prompt

- "After every long sentence put a short one that lands it": removed. Round 4 named it a bug and
  it was still in the file.
- The mandatory disclaimer: retired for real.
- Bold: from 4–6 to at most 3, never a whole closing sentence.
- Endings: the circle back to the opening image at most one post in three.
- The pasted block that asked for text that "bypasses AI detectors" with phrases like "In my
  experience": removed. It contradicted the section right above it, and inserted first-person
  filler is itself on the tell lists. The v3 file is kept in `archive/`.

## The five posts

Chosen for sending: not yet syndicated, strongest reach scores or strongest own data, and a spread
of pillars.

| Post | Why this one | Before → after (short endings · fragments/1k · pairs) |
|---|---|---|
| The Model Is the Easy Part | highest reach score, evergreen, technical | 37% · 9.3 · 5 → quiet |
| Nobody Resisted | numbers nobody else has | 32% · 12.4 · 2 → quiet |
| The Model Grades Its Own Homework | a concrete proposal with sources | 38% · 7.3 · 2 → quiet |
| A Company of One, Staffed by Agents | own product, so full detail is allowed | 31% · 4.5 · 3 → quiet but for one pair |
| The Diagram That Cannot Lie | carries the Stemma page | 33% · 11.2 · 3 → quiet |

What was done in each: landing lines joined to their paragraph or removed, fragments turned back
into sentences, split reframes stated directly, idioms replaced with the plain verb, ritual
disclaimers removed, bold reduced. No fact, number, link or diagram was changed.

What was not done, and cannot be done from here: these are still model drafts, now with less of
the model's rhythm. Each carries one or two margin notes in `review.php` asking for a paragraph in
his own words at the place where it counts most. Fifteen minutes per post there moves more than
this whole pass did.

## Open points

- The baseline is one essay. Two or three pages of his unassisted English (even old emails or
  design-review comments, scrubbed) would let every threshold here be re-fitted.
- Contractions: his 2020 text has about 10 per thousand, the newest posts 2 to 4. Not changed in
  this round, because which forms he contracts is exactly the kind of thing only his own text shows.
- The other 23 editable posts carry the same cadence notes. `php tools/voice_check.php --detail`
  lists them.

## Sources

Read by a research agent on 2026-11-14; items marked "snippet" were not opened in full.

Process and detection: Baumler et al., post-editing study, ACL 2026 — <https://arxiv.org/abs/2604.24444> ·
Gero et al., ownership by writing stage, DIS 2026 — <https://arxiv.org/abs/2604.11009> ·
Abdulhai et al., edit prompts and argument drift — <https://arxiv.org/abs/2603.18161> ·
Sourati et al., stylistic narrowing — <https://today.usc.edu/remembering-the-human-encouraging-unique-voices-in-the-age-of-ai/> ·
Pangram EditLens — <https://arxiv.org/abs/2510.03154> ·
detectors on edited non-native manuscripts, EMNLP 2026 — <https://arxiv.org/abs/2608.26710> ·
Pangram on humanizers (vendor) — <https://www.pangram.com/blog/humanizers-aug-25>

Surviving tells: animate verbs with inanimate subjects — <https://arxiv.org/abs/2610.29162> ·
Graphite tell-migration study via TechCrunch — <https://techcrunch.com/2026/10/01/opus-5-5-loves-to-tell-you-this-matters-and-other-ai-writing-tells/> ·
<https://tropes.fyi/tropes-md> · <https://roughatsea.com/notes/the-llm-ism-dictionary/> ·
<https://en.wikipedia.org/wiki/Wikipedia:Signs_of_AI_writing> ·
narrative-level detection — <https://arxiv.org/abs/2604.03136>

Non-native register: Berti et al. — <https://arxiv.org/pdf/2208.01468> ·
Rabinovich et al. 2018 — <https://arxiv.org/pdf/1805.09590> ·
Kaszubski 1999 on PICLE — <https://old.hltmag.co.uk/dec99/idea.htm> ·
Garbatovič & Grigaliūnienė 2020 on phrasal verbs — <https://journal.lu.lv/bjellc/article/view/83> ·
Duszak 1994 — <https://benjamins.com/online/ebop/publications/25550> ·
Liang et al. 2023, detectors and non-native writers — <https://arxiv.org/abs/2304.02819> ·
reader attitudes to non-native errors — <https://experts.colorado.edu/display/pubid_385639>

Platforms: <https://news.ycombinator.com/newsguidelines.html> ·
Substack and Pangram — <https://thenextweb.com/news/substack-pangram-ai-detection-claudefishing>
