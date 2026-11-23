# Field Notes: human-voice plan, round 3

Research round 3, written 2026-08-01. Companion to [POST-PROMPT.md](./POST-PROMPT.md) (round 2, the de-AI-ification rules) and [BLOG-IMPROVEMENT-PLAN.md](./BLOG-IMPROVEMENT-PLAN.md) (round 1, growth).

**The brief:** make the blog read 100% human. Learn the voice from the two articles Bartosz wrote himself (*The Automerger*, *Technical Leader: Identity Disorder*), study what the most-read tech articles have that Field Notes does not, and produce an action plan for updating the existing posts and the writing system.

**Method:** 4 parallel research sweeps over 35+ fetched source pages (canonical most-read posts · 2024–2026 viral essays + HN pattern studies · human-vs-AI stylometry · writing-craft masters), plus corpus measurements over all 29 posts, plus close-reads of the two Medium originals from 2020. Five analysis iterations, documented below in order.

---

## TL;DR

Round 2 worked: all 24 editable posts pass every mechanical check, and the obvious AI tells are gone. But the corpus now reads like **one flawless essayist who is not Bartosz**. The de-AI-ification created its own fingerprint (a ritual disclaimer in 11 posts, a concept-naming tic, an austere no-numbers register in the newest posts), and the things that made his 2020 writing his — a team, real numbers, artifacts on the page, famous quotes, warmth, invitations — got stripped as collateral.

The research is unusually unanimous about what the most-read tech articles have that polished generated prose lacks, and it is not "fewer tells." It is **presence**: the author risks something, other people appear and speak, the page contains an inspectable thing, the concept comes priced with a number, and the texture spikes. Every one of those is currently the corpus's weakest dimension.

The plan, in five moves:

1. **Flip the pipeline.** Bartosz supplies raw anchor material first (voice memo / bullet dump, in his words); the model becomes the editor, not the author. Research shows feeding a model biographical details makes output *less* human — the weaving must be his.
2. **Add the four unfakeables** to every substantial post: a personal cost, a scene with a spoken line, an artifact on the page, a number that prices the idea.
3. **De-template the corpus**: retire the ritual disclaimer, the naming tic, the "somewhere on a disk" opener, the uniform 4–5-section skeleton, the aphorism-per-section cadence.
4. **Retrofit in waves**: the 3 unpublished scheduled posts first, then ~10 high-value published posts in a working session where Bartosz supplies the real details, then a light corpus-wide pass. Nothing is ever invented to fill a gap.
5. **Restore the 2020 DNA deliberately**: team "we", famous-quote punctuation (rationed), ending invitations, the ":)", operational numbers, occasional 2,000-word generosity — held to 2026 craft standards, not 2020 ones.

---

## Iteration 1 — the fingerprint: what "written by Bartosz" measurably means

Sources: the two Medium originals (2020), the repo versions, and metrics over both. The originals matter: the repo import already smoothed them ("makes you searching for" → "makes you search for", "thru" → "through"), and *The Automerger* was cut from ~2,100 words to 548, losing most of its texture. The originals are the ground truth.

### Measured markers, his posts vs the 26 new-era posts

| Marker (per 1k words unless noted) | 2020 human | 2025+ corpus | Newest 4 (Aug schedule) |
|---|---|---|---|
| "we/our/us" density | 10.8–13.9 | ~3.6 | 0–6.4 (two posts at 0) |
| Numerals in body | 8.8 (Automerger 16.4) | 2.1 | **0.0** |
| Parentheticals | 2.4–7.3 | 2.5 | **0.0** |
| Quoted speech (dialogue-like) | present | some | **0** |
| Blockquoted famous quotes (per post) | 7 in one post | 2 in 26 posts | 0 |
| Contractions | ~6–10 (originals higher) | 14.5 | **~4** |
| First-person "I" density | 12.0 | 18.2 | high |
| Smileys | ":)" | one ";)" in ~half | rare |

Two readings of that table:

- **The corpus over-performs "I" and under-performs "we".** His human writing is communal: "One day we decided that we don't want to waste any more time…" — the team builds, the team learns, and he invites the reader in. The ghostwritten corpus is a lone monologue. People are the missing population.
- **The newest posts drifted into austerity.** Zero digits, zero parens, zero quoted speech, contractions collapsed from ~15/1k to ~4/1k. Nothing in round 2 asked for this; it is the model's own "measured literary essayist" attractor. It reads beautiful and bloodless — arguably the 2026-generation AI tell, exactly because every model's "good prose" converges there.

### Qualitative markers from the originals

1. **Talks *to* the reader, warmly and directly.** "Pretty complex with multiple teams working towards one common regular deployment, isn't it?" · "Do you like data science?" · "Simply, you have something to share? Come and talk about it."
2. **The team is the protagonist.** Eight teams of seven; 5:2 backend-to-frontend; the meetup "specially designed to guide participants into the same mistakes we made."
3. **Operational numbers nobody would invent.** Automerger runs at 5am/8am/12pm/3pm/6pm; 7 instances (2 .NET, 5 JavaScript); 90% of merges fully autonomous; one booked hour per week at the end of the week.
4. **Structural generosity.** The Automerger original had 13 sections *including an FAQ*; identity-disorder is a catalog of named practices with logistics. He writes to be **used**, not admired.
5. **Famous quotes as section punctuation.** Socrates, Robert C. Martin, Shaw, Fuller, Jobs, Stallman, Eliot — seven in one essay, each attributed, each on-topic.
6. **Artifacts.** The original Automerger article shipped a GitFlow diagram, an execution-log screenshot, and a branch-sorting diagram.
7. **Non-native charm kept.** "That's life, what can we do about it? Automate it." · "Yep." · "tough nut to crack" · "spiritually releasing" · "sounds like a split personality". Slightly formal in places ("I would like to tell you my story"), colloquial in others — the mix is the accent.
8. **Confessional ambivalence.** "One day I would never change this position…, the next day I was almost ready to quit." The worry that his own development time is "purely egoistic."
9. **Endings that invite or command.** "Please share your experiences with the set of tools that you have developed over time." · "Automation is crucial. Do not let your team or yourself find a comfortable place."
10. **Warmth without irony.** "Fresh and joyful for everyone", "heart-touching". He is earnest. The corpus is wry. Those are different people.

**Target voice = the 2020 DNA × 2026 craft.** Keep the warmth, the team, the numbers, the invitations, the accent; apply the compression, openings, and endings craft below. Not a regression to 2020 rambling — the originals *were* baggy, and the user asked for "improved, in a human way."

---

## Iteration 2 — the corpus autopsy: the fingerprint the de-AI pass created

All 24 editable posts pass `voice_check.php`. The greps can no longer see the problem. These are the patterns a binge-reader (or a classifier trained on 2026 model output) still sees:

1. **The ritual disclaimer.** 11 of 26 posts carry a near-identical line: "Private observations, not science" / "Field notes from one platform, not a study" / "Private notes from one career, no study behind them" / "Usual disclaimer applies…". Round 2 *requires* this ("one honest disclaimer, never the same phrasing") — and produced eleven cosmetic variants of one sentence. At corpus level it is a signature, and it also quietly does the same job every time: pre-emptively lowering stakes.
2. **The concept-naming tic.** "what I have come to call the mandate window" · "I have started calling these authorless diffs" · "what I have started calling a resting position" — 8 occurrences of the `I have started calling / come to call / stopped` family across the 5 newest posts. Concept handles are good (see Iteration 3); the *ritualized way of unveiling them* is a template.
3. **"The part I…"** — "the part I genuinely have not solved" / "the part I can't stop thinking about" / "the part nobody tells you" — 11 hits across 8 posts.
4. **Skeleton uniformity.** Of 26 posts: 15 have exactly 4 `##` sections, 10 have exactly 5, 1 has 2. Word count spans just 840–1,140 for 24 of them. As the canonical-posts research put it: *nothing in the most-read corpus is a tidy five-paragraph 900-word essay — which is exactly the shape AI drafts converge to.*
5. **Opening-family repetition.** The "somewhere on a disk/drive/storage" opener appears in 5–6 posts (*the-migration…*, *the-diagram-that-cannot-lie*, *grinding-dijkstra*, *adrs…*, *prompt-driven…*). Each is good alone; together they are a house macro.
6. **Aphorism cadence.** Round 2 capped "one aphorism per post," but most sections still *end* on a lathed line ("The design never owed anything. The trail did."). One mic-drop per section × 4 sections = an aphorism delivery vehicle. The most-read pieces average about one per essay, often zero.
7. **Uniform emotional temperature.** Every post is the same knowing, wry, quietly melancholic diagnostician. No post is excited. None is annoyed. None is proud. None gushes. The human-vs-AI research calls generated prose "affectively beige"; this corpus is affectively consistent, which at archive scale is the same tell.
8. **An empty room.** In 26 posts: ~5 external links total, 2 blockquotes, one code block, zero screenshots, zero named public tools outside his own products, zero colleagues with voices (three posts quote an anonymized sentence; none has an exchange). Compare: his two 2020 posts alone contain 7 attributed quotes, 3 visuals, an FAQ, and a team.
9. **Title convergence.** 9 of 29 titles are the copula pattern ("X Is (Not) Y"), including 4 of the last 6. The plain copula is fine (humans say what things are); the *repetition of one title template* is the issue. Zero first-person titles in the new era — while the viral corpus is full of them ("I'd rather read the prompt", "I Will F—ing Piledrive You…", "My AI Skeptic Friends Are All Nuts").
10. **Where the voice does survive:** *Data-Driven Theater* (Twain/Coase quotes with the broken-attribution joke, "trust me… ;)", the Peter Principle link, the two-question experiment ending) is the closest thing to the 2020 voice in the new corpus, and the model for retrofits. *From Signals to Blips* (the formula block, radar mechanics) is the closest to the Automerger's show-the-machine energy.

---

## Iteration 3 — the gap analysis: what the most-read pieces have that Field Notes doesn't

Ranked by convergence across the four research sweeps. Each gap: evidence → current state → direction.

### Gap 1 — Visible personal cost (the author risks something)
All 11 canonical posts and all 6 viral essays anchor in something the author pays for: Yegge mocks his employer publicly; McKenzie admits "I cost myself five figures with a single email"; Julia Evans transcribes her embarrassment; Suresh confesses his own field's fraud *before* attacking anyone else's. Slop research agrees: accountability — "I attach my name and stake my credibility" (Willison) — is a property readers feel.
**Corpus:** does mild failure-admission ("it took longer than I would like to admit"), which is becoming its own house cliché. Real cost — a number lost, a decision he got publicly wrong, a rejection count — appears rarely.
**Direction:** one real confession with a price tag per substantial post. *Count Your Rejections* should state his own ledger count. The mild-admission formula gets retired.

### Gap 2 — Scenes with dialogue; other people exist
The 500-Mile Email is carried by a chairman who *speaks*. McKenzie hands the reader literal lines to say. Spolsky invents "Nancy" for one sentence and humanizes a whole argument. **Dan Luu is the existence proof that employer-anonymity and hyper-specificity coexist**: employers unnamed, details razor-sharp ("the @flaky annotation that reports a pass if any of the runs pass"). This resolves the site's employer-agnostic constraint: anonymize the *who*, never the *what*.
**Corpus:** people are silhouettes ("an engineering director asked me a question"). The actual question is never quoted. The colleague's line in *Count Your Rejections* is paraphrased ("put it better than I have managed since") — the one thing that should be verbatim.
**Direction:** every post that contains a conversation renders at least one line of it as speech. The "we" returns wherever the story was a team's.

### Gap 3 — An artifact on the page
9 of 11 canonical posts embed an inspectable thing: FizzBuzz verbatim, the Bezos mandate, Evans's full template (the ending *is* the artifact), the `units` transcript, negotiation scripts, hand-drawn Crazy/Sane charts. Recent viral essays: annotated Gemini output, readiness-report charts, `catch (theseHands)`. Lynch's data: images are the biggest bang-for-buck, and *crude hand drawings outrank stock photos and AI images*.
**Corpus:** diagrams (good, keep) but nothing else: no report excerpt, no prompt skeleton, no ADR text, no price table, no log line, no template. Own products (Desta, Verso, the Go scheduler) are *permitted* full specificity by site policy and still appear artifact-free.
**Direction:** one artifact per [ARCH]/[SHIP]/[AI] post: a redacted squad-report sentence, the real `prompt.md` skeleton, an ADR excerpt, the rejection-ledger table, Desta's actual tier table, a scheduler config block. Where the post teaches a practice, end on the reusable artifact (the Evans move).

### Gap 4 — The concept comes priced with a number
"You get about three innovation tokens." Boring tech is safe for "5–7 years, preferably 10–15." FizzBuzz filters in "10–15 minutes." Brag doc: "5–10 pages a year." The number is what gets quoted in meetings — and his own Automerger already did it ("90% of merges fully autonomous").
**Corpus:** concept handles exist (good — mandate window, authorless diffs, resting position) but ship unpriced, and the last four posts contain zero numerals.
**Direction:** attach an operating number to each named concept where one honestly exists ("a resting position must survive **two years** without apology" is already there — surface more of these; the two-year figure should appear as a digit). At least 3 real numerals per post unless the topic genuinely has none.

### Gap 5 — Texture: register drops and form variance
"As if source code *rusted*." · "I buy a fucking table." · "It wasn't April Fool's Day." · "Until. Until I read an application written entirely by a person." Long analysis detonated by a 3–6-word colloquial line. And at corpus level: 1,100-word linkposts next to 8,500-word literature reviews next to a 200-word litany (antirez, 1,012 HN points for ~200 words).
**Corpus:** sentence-length CV is enforced and passes, but *register* never drops — no slang spike, no "Yep." (which is literally his word), no aside that breaks the fourth wall. And all posts are the same size and shape.
**Direction:** one deliberate register drop per post, in his own idiom ("Yep", "That's life", "what can we do about it?"). Per quarter: at least one deliberately non-standard form — a 300-word litany, an FAQ post (his native form!), an annotated-experiment post, a 2,000-word program.

### Gap 6 — Emotional range with real stakes
The viral corpus runs on rage (Suresh), grief (Sinker, antirez), gleeful combat (Ptacek), care (Ramsey). Medium's curation: "full human stories with context and emotionality." This does **not** mean import the anger — his register is warm, and the constructive-register policy stays. It means vary the key: earnest, excited, proud, annoyed-at-a-system, amused.
**Corpus:** one key, wry-melancholic, 26 times.
**Direction:** pick the emotional key per post *before* writing, and let 1 post in 3 be openly enthusiastic or openly irritated (at a system, never a person). His 2020 earnestness ("heart-touching") is a differentiator in 2026, not a weakness.

### Gap 7 — Community presence: quotes, links, replies
The greats are in conversation: Atwood's post is mostly other people's blockquotes; Luu quotes anesthesiology literature; round-1 research already prescribed "pick up what they put down" (swyx). And famous-quote punctuation is *his own 2020 signature* — deleted in the new era.
**Corpus:** ~5 external links in 26 posts; 2 blockquotes; no post ever responds to a named essay or paper.
**Direction:** 2–4 honest external links per post (tools, papers, essays, specs). Famous quotes return, rationed: 1–2 quotes in roughly 1 post in 3, real and attributed, only where the quote argues. One reply-post per quarter that engages a named public position (the platform-engineering consensus paragraph in *Adoption Is the Deliverable* is the seed of exactly this).

### Gap 8 — Epistemic signature: calibrated, first-person, asymmetric
The stylometry inverts folk wisdom: **humans hedge more than models, interpersonally** — "I suspect", "I'd bet", "oddly", "I don't know why this worked" (Jiang & Hyland 2025: ChatGPT shows *lower* interactional metadiscourse; humans show more epistemic stance and authorial presence). The round-2 rule "take the position or cut the sentence" is right about committee-hedges ("it's worth noting", "arguably") and wrong if it flattens honest uncertainty.
**Corpus:** decent ("what nobody has measured, as far as I can find") — deepen it, and vary confidence claim-by-claim (Wes Kao: "if you have strong evidence, speak confidently; if brainstorming, be honest about this").
**Direction:** split the hedging rule: committee-hedges stay banned; first-person epistemic markers are encouraged, one flat "I don't know" per post where true.

### Gap 9 — Thinking visibly in progress
PG: writing that discovers something mid-piece carries evidence of the thinking it *is*; generated text is single-pass and pre-resolved. Russell et al. 2026: AI favors tidy single-track resolution; humans leave residue.
**Corpus:** round 2's "one unresolved thread" rule survives ("the exchange rate nobody has measured") — good. What's missing is the *mid-essay correction*: "I wrote three paragraphs blaming the org and deleted them; the org was fine" — the turn where the essay changes its own mind on camera.
**Direction:** one post in 3 keeps a visible self-correction.

### Gap 10 — Openings and endings with a pulse
Openings: the corpus does concrete scenes well (round 2's win — keep). Sharpen with McEnerney: open inside the *reader's* costly instability, not just the author's anecdote; Lynch: title + first 3 sentences must answer "is this for someone like me, and what do I get?"
Endings: canonical endings stop hard on an earned line, a punchline, or a working artifact — never a recap, and *not always a sigh*. His 2020 endings invite ("please share your experiences") or command ("Do not let your team find a comfortable place").
**Corpus:** round 2 made the elegiac closing-note the default; it is now 24 posts of the same dying fall.
**Direction:** four ending registers, rotated: closing note · invitation to share experience · blunt imperative · artifact handoff. Never the same register three posts running. (The LinkedIn engagement-bait concern from round 2 applies to the *LinkedIn teaser*, not the essay body on his own site.)

---

## Iteration 4 — the action plan

### 4.0 Three principles before any edit

1. **Distributions, not checkboxes.** Round 2 proved that per-post rules become per-post fingerprints. Everything below that says "1 in 3" or "per quarter" is a corpus-level target, checked across posts, never a box every post ticks the same way.
2. **Nothing is invented. Ever.** Every number, quote, scene, and artifact in a retrofit comes from Bartosz in a working session. A post he has no real material for gets the light pass only (de-templating, links, register). This is already site policy; it is the hard constraint on everything below.
3. **The human writes the human parts.** The research's fakeable/unfakeable split is the division of labor: syntax, structure, compression — assistable. Cost, scenes, idiom, jokes, stakes — his, in his words, preserved verbatim.

### 4.1 Flip the pipeline (the deepest fix)

Evidence: Cornell 2025 — feeding a model biographical details makes essays *less* human-sounding (models inventory traits instead of weaving them). iA's authorship principle: the final surface should be substantially re-formed in the author's own syntax. PG: writing is the thinking. The round-2 prompt is ghostwriter-centric; it optimizes the surface of a machine draft. Round 3 inverts it:

1. **Anchor dump first.** Before any drafting, Bartosz records a 5–10 minute voice memo or writes a raw bullet dump *in his own words*: the scene, who said what (verbatim as remembered), the numbers, what failed first, what he still doesn't know. This replaces the TOPIC block's one-line "detail only Bartosz has" with a page of them.
2. **The model interviews, then structures.** It may ask questions ("what time did the pipeline run?", "what exactly did the director say?"). It may **never** answer them itself. Unanswered questions stay out of the draft.
3. **Draft around his sentences.** Sentences from the anchor dump survive verbatim wherever possible, accent included. The model's job is order, compression, and connective tissue — Orwell's cuts, Shapiro's three passes, McEnerney's stakes — not voice synthesis.
4. **The read-aloud pass is his.** PG's friend test, per sentence: would he say it like that to a colleague? Anything he wouldn't say gets rewritten *by him*, out loud, transcribed. Sivers's one-sentence-per-line format makes this pass fast.
5. Then the existing greps, rhythm check, and scoring, unchanged.

This is also the honest position under Medium's disclosure policy: materially author-driven, AI-assisted — the right side of the line to actually be on, not just claim.

### 4.2 POST-PROMPT.md v3 — the edit list

Apply as a v3 header plus surgical edits; the round-2 rules stay unless amended here.

1. Add the pipeline section above (4.1) as the mandatory workflow; demote the current "fill the TOPIC block" flow.
2. Add **the four unfakeables** as a floor: a real cost · a spoken line · an artifact · a priced number. Minimum 2 of 4 per essay, all 4 for pillar posts. (Replaces the softer "four specifics that could be wrong" list, which the corpus satisfies with texture-free specifics.)
3. **Disclaimer rule amended:** no longer mandatory. Max 1 post in 3, must be structurally fresh or folded into narrative ("I have exactly one organization to observe, so weigh accordingly"), never the "private observations, not science" family again.
4. **Endings:** four registers (closing note / invitation / imperative / artifact handoff), rotate, never three of one in a row. Recap still banned.
5. **Hedging rule split:** committee-hedges banned (unchanged); first-person epistemic markers encouraged; one honest "I don't know" per post where true.
6. **Famous quotes restored:** 0–2 per post, attributed, load-bearing, ~1 post in 3 max.
7. **Numbers:** ≥3 numerals in body prose unless the topic is genuinely numberless; write data as digits ("15 squads", "90%"), not words.
8. **People:** if the story involved a team, "we" appears; at least one human with a voice per narrative post.
9. **Register:** one colloquial drop per post in his idiom ("Yep." / "That's life."); emotional key chosen per post from {earnest, excited, annoyed-at-system, amused, proud, wry} — wry loses its default status.
10. **Structure variance:** section count 2–7 free; word count 400–2,500 with median ~900 and at least 1 post in 6 outside 650–1,300; per quarter one non-essay form (FAQ / litany / annotated experiment / build-log). Kill the section-ending-aphorism cadence: max one section may end on a lathed line.
11. **Corpus-uniqueness pre-flight:** before drafting, check the last 3 posts + archive for: opening move, ending register, disclaimer presence, title template (copula-title budget: no more than 1 in 3 new titles), "I have started calling…" (once per post max, and vary the unveiling), "the part I" (retire).
12. **Contraction band:** 8–18 per 1k words. The austere sub-8 register of the newest posts is flagged as a tell in both directions.
13. **Titles:** allow first-person, speech-act, and artifact-name titles ("The Automerger" was one). The concept-name style stays but stops being the only mold.
14. Cross-reference this file the way v2 references its "Why the voice rules exist" section.

### 4.3 voice_check.php v2 — new checks

Soft warnings (advisory), because these are distribution targets, not per-post gates:

- `digits`: warn when body prose has 0 numerals.
- `we-presence`: warn at 0 "we/our/us" in narrative posts.
- `links-out`: warn at 0 external links.
- `disclaimer-formula`: regex the "private (observations|notes)|not (science|a study|a paper)|no study" family; per-post warn + corpus count.
- `naming-tic`: warn on >1 hit of "I have (started calling|come to call|taken to calling)".
- `the-part-i`: warn on >1 hit of "the part (I|that|nobody)".
- `contraction-band`: warn outside 8–18/1k.
- **`--corpus` mode** (the important one): opening 4-gram collisions across posts, ending-register histogram (last paragraph: question / imperative / statement), section-count histogram, title-template share, disclaimer share, quote/artifact/external-link totals. Run it before publishing anything; the dashboards catch the next emergent template while it is 3 posts old, not 11.

### 4.4 The retrofit, in waves

Rules: frozen posts stay frozen (*identity-disorder*, *automerger*, *legacy-code*, *multi-agent*, *mathematicians-vs-lawyers* — published elsewhere, editing would fork them). Published posts are never retitled (slugs are URLs). Substantive edits get the `**Updated:**` line. Every detail added comes from the working session with Bartosz, or the edit doesn't happen.

**Wave 0 — the unpublished schedule (this week; zero churn, highest leverage):**

| Post (publish date) | Targeted moves |
|---|---|
| *The Bottleneck Moved* (08-03) | Add the real numbers around the review queue (reports/month exists; add e.g. PR-age or review-load figures if real). Quote one redacted squad-report sentence verbatim as an exhibit (the Ramsey move: show the artifact the essay is about). One register drop. Loosen 2–3 contractions. Let one section end flat instead of on the aphorism. |
| *Design the Stopping Point* (08-10) | The director gets a voice: his actual question, quoted. Show the one-page artifact as a 5-line template block (the Evans ending: hand the reader the thing). Digits for the operating numbers already present as words ("two years" → the rule with a number). It is the most austere post in the corpus (contractions 1/1k, zero parens); rehydrate. |
| *Count Your Rejections* (08-17) | The colleague's line, verbatim, as dialogue (it is currently paraphrased — the single highest-value 10-word edit in the corpus). Render the ledger as an actual 4-column table with 2–3 of his real rows. State his own last-12-months rejection count: the confession with a price tag. |
| *Adoption Is the Deliverable* (08-24) | Already the strongest of the four. Add the lump's real integration timeline numbers if available; 1–2 external links (Wardley mapping source, the platform-as-product position it argues with — seeds the reply-post pattern). |

**Wave 1 — high-value published posts, deep retrofit (one working session, ~10 posts):**
The unlock is that [SHIP] posts are allowed full, checkable specificity (own products) — they should carry the artifact load:

- *Pricing Is Architecture* — Desta's actual tier table, real prices.
- *ADRs for an Audience of One* — paste one real ADR excerpt (it already namechecks eleven; show one).
- *Anti-Personas* — quote the actual vision-doc anti-persona text.
- *Prompt-Driven Architecture* — the real `prompt.md` skeleton, redacted.
- *A Company of One* — the real agent roster/costs; a screenshot.
- *From Signals to Blips* — one redacted report row + a radar screenshot; already has the formula (keep).
- *Probabilistic Metrics* — one real week's numbers.
- *Grinding Dijkstra* — the practice-repo file listing, dates included.
- *Data-Driven Theater* — smallest pass; it is the tone model. Maybe one real meeting scene line.
- *Edge Cases Written by Parliament* — name the actual public artifacts (Polish e-invoicing specs are public and checkable; the state as PO deserves its receipts).

**Wave 2 — corpus-wide light pass (mechanical, low risk):**
Disclaimer dedupe (11 → ≤4, rewritten or removed) · opening dedupe (retire 2–3 of the "somewhere on a disk" family) · naming-tic and "the part I" variation · 2–4 external links per post where honest · famous quotes into 4–6 posts where one genuinely argues · ending-register redistribution (~1/3 become invitation or artifact-handoff) · re-run `voice_check --corpus` and confirm the dashboards moved.

### 4.5 New-content moves (from the next post onward)

1. **One flagship "Automerger 2026"**: the next [SHIP] or [AI] post written artifact-first (screenshots, logs, FAQ section, operating numbers) as the proof-of-formula. Candidate: the tech-radar machinery or the Go scheduler, whichever has the best artifacts lying around.
2. **The form backlog, one per quarter:** an FAQ post (his native 2020 form) · a ~300-word litany · an annotated experiment ("I let the agent design X and graded it") · a 2,000-word program piece.
3. **One reply-post per quarter** engaging a named essay/position ("pick up what they put down" — round 1 prescribed it, round 3 found the craft reason it works).
4. **Hero images, later:** Lynch's data says stock photos underperform crude own drawings. The drawio-sketch pipeline already exists for diagrams; consider extending that style to heroes for new posts. Low priority, real signal.

---

## Iteration 5 — the adversarial pass: attacking this plan before adopting it

1. **"These additions become template v3."** The strongest objection, because it already happened once (the disclaimer rule → 11 ritual lines). Mitigations baked in: distribution targets instead of per-post rules, the `--corpus` dashboards to catch emergent patterns at n=3, and the pipeline flip, which attacks the root — a template can only take over when one author (the model) writes every surface. Thirty anchor dumps by one human will vary more than thirty drafts by one model ever will.
2. **"Retrofitting invites fabrication."** If a post needs a number and Bartosz doesn't have one, the honest state is: that post stays number-light. Principle 4.0-2 is the gate; the working session supplies material, the plan never does. This is also why Wave 1 leans on [SHIP] posts — the artifacts already exist on his disk.
3. **"Don't import the rage."** The viral corpus runs hot (Suresh, Ptacek). Copying the anger would be as fake as the current wryness. What transfers is the *stake*, not the temperature — his native keys are earnest, warm, amused, occasionally annoyed at a system. The constructive-register policy survives intact.
4. **"His 2020 writing was objectively weaker prose."** True — baggy, sometimes ungrammatical. The plan restores its DNA (who is present, what is shown, how it ends), not its editing standards. Round 2's rhythm metrics, banned vocabulary, and compression craft all stay. The bar is: *Dan Luu's discipline, Bartosz's blood.*
5. **"Editing published posts has costs."** Mitigated: `Updated:` lines exist and feed `dateModified`; slugs never change; frozen mirrors stay frozen; Google's scaled-abuse policy is untriggered by an author deepening 20 essays. The Medium/DEV copies of the three syndicated posts don't change — they are already frozen.
6. **"Famous quotes read as LinkedIn kitsch in 2026."** Risk is real if they decorate. The ration (1 in 3 posts, quotes that argue) plus his own precedent (the broken-attribution joke in *Data-Driven Theater* — a quote *about* quoting being unreliable) keeps them on the right side. If in doubt, the quote goes.
7. **"Perfect compliance is itself detectable."** Yes — a corpus that hits every human-marker uniformly is the next uniformity. The end state to protect is *variance*: some posts short, some long, some quoted, some bare, some earnest, some annoyed. When a future check and this goal conflict, variance wins. That rule outranks this document.

**Explicit keep-list (working, don't touch):** concept handles and concept-name titles as one option among several · diagrams with standalone captions and the two-theme palette · sentence-case headings · the scoring system and rubric · the constructive second half · no fabricated specifics, no employer naming, systems-not-people · the CV/rhythm floors · the banned-vocabulary greps · no seeded typos, ever.

**Open questions for Bartosz (blocking Wave 1, not Wave 0):**
- Which Wave-1 posts do you actually have artifacts/numbers for? (30–60 min inventory session against the list above.)
- The rejection count in *Count Your Rejections*: willing to publish the real number?
- Voice memos or written dumps for the anchor-first pipeline? (Either works; memos preserve more accent.)
- Hero-image style change: now, later, or never?

---

## Sources (35+ pages fetched across four sweeps + own analysis)

**A. Canonical most-read posts (close-read):**
[Things You Should Never Do](https://www.joelonsoftware.com/2000/04/06/things-you-should-never-do-part-i/) (Spolsky) · [Maker's Schedule, Manager's Schedule](https://www.paulgraham.com/makersschedule.html) (Graham) · [Stevey's Google Platforms Rant](https://gist.github.com/chitchcock/1281611) (Yegge) · [Choose Boring Technology](https://mcfunley.com/choose-boring-technology) (McKinley) · [Normalization of Deviance](https://danluu.com/wat/) (Luu) · [Salary Negotiation](https://www.kalzumeus.com/2012/01/23/salary-negotiation/) (McKenzie) · [Get your work recognized: write a brag document](https://jvns.ca/blog/brag-documents/) (Evans) · [The Grug Brained Developer](https://grugbrain.dev/) (Gross) · [The mythical 10x programmer](http://antirez.com/news/112) (antirez) · [Why Can't Programmers.. Program?](https://blog.codinghorror.com/why-cant-programmers-program/) (Atwood) · [The case of the 500-mile email](https://www.ibiblio.org/harris/500milemail.html) (Harris) · HN score data via hn.algolia.com.

**B. 2024–2026 breakouts and HN pattern studies:**
[I Will F—ing Piledrive You If You Mention AI Again](https://ludic.mataroa.blog/blog/i-will-fucking-piledrive-you-if-you-mention-ai-again/) (Suresh) · [My AI Skeptic Friends Are All Nuts](https://fly.io/blog/youre-all-nuts/) (Ptacek) · [We are destroying software](https://antirez.com/news/145) (antirez) · [How I ship projects at big tech companies](https://www.seangoedecke.com/how-to-ship/) (Goedecke) · [The Who Cares Era](https://dansinker.com/posts/2025-05-23-who-cares/) (Sinker) · [I'd rather read the prompt](https://claytonwramsey.com/blog/prompt/) (Ramsey) · [What gets to the front page of Hacker News?](https://www.amplifypartners.com/blog-posts/what-gets-to-the-front-page-of-hacker-news) (Amplify) · [How to write stuff that gets on the front page of HN](https://blog.alinelerner.com/how-to-write-stuff-that-gets-on-the-front-page-of-hacker-news/) (Lerner) · [The Most Popular Blogs of Hacker News in 2025](https://refactoringenglish.com/blog/2025-hn-top-5/) + [How to Write Blog Posts that Developers Read](https://refactoringenglish.com/chapters/write-blog-posts-developers-read/) (Lynch) · [Reverse Engineering My #1 Hacker News Article](https://danielwirtz.com/blog/successful-hacker-news-article) (Wirtz).

**C. Human-vs-AI prose (measured layer):**
[Wikipedia: Signs of AI writing](https://en.wikipedia.org/wiki/Wikipedia:Signs_of_AI_writing) · [Writes and Write-Nots](https://paulgraham.com/writes.html) + [Good Writing](https://paulgraham.com/goodwriting.html) (Graham) · [Do LLMs write like humans?](https://arxiv.org/abs/2410.16107) (Reinhart et al., PNAS 2025) + [LLM style literature notebook](https://www.refsmmat.com/notebooks/llm-style.html) (incl. Goulart 2024, Jiang & Hyland 2025, Mizumoto 2024, Ming 2026, Russell 2026) · [Slop](https://simonwillison.net/2024/May/8/slop/) (Willison) · [Writing with AI](https://ia.net/topics/writing-with-ai) (iA/Reichenstein) + [interview](https://www.polaine.com/power-of-ten/oliver-reichenstein-keeping-it-real-when-writing-with-ai/) · Medium/Stubblebine: [Q&A](https://medium.com/westenberg/inside-mediums-mission-to-elevate-authentic-voices-a-q-a-with-ceo-tony-stubblebine-233b24e9415e) + [I'd Rather Be Writing summary](https://idratherbewriting.com/blog/stubblebine-how-ai-is-changing-writing) · [The Great Smoothing](https://stanforddaily.com/2026/03/08/the-great-smoothing/) (Gupta) · [Ted Chiang Q&A](https://cdh.princeton.edu/blog/ted-chiang/) · [Here lies the internet](https://www.theintrinsicperspective.com/p/here-lies-the-internet-murdered-by) (Hoel) · [AI can write your college essay; it won't sound like you](https://news.cornell.edu/stories/2025/09/ai-can-write-your-college-essay-it-wont-sound-you) (Cornell).

**D. Craft masters:**
McEnerney, *The Craft of Writing Effectively* ([summary 1](https://auresnotes.com/summary-the-craft-of-writing-effectively/), [summary 2](https://www.robincussol.com/the-craft-of-writing-effectively-summary/)) · [How to Write Usefully](https://paulgraham.com/useful.html) + [Write Like You Talk](https://paulgraham.com/talk.html) (Graham) · [Writing Well](https://www.julian.com/guide/write/intro) (Shapiro) · Wes Kao: [limbo writing](https://newsletter.weskao.com/p/how-to-fix-limbo-writing) + [accurate language](https://newsletter.weskao.com/p/tone-and-words-use-accurate-language) · [A blog post is a very long and complex search query](https://www.henrikkarlsson.xyz/p/search-query) (Karlsson) · [Nonfiction Writing Advice](https://slatestarcodex.com/2016/02/20/writing-advice/) (Alexander) · [Politics and the English Language](https://www.orwellfoundation.com/the-orwell-foundation/orwell/essays-and-other-works/politics-and-the-english-language/) (Orwell) · [Obvious to you, amazing to others](https://sive.rs/obvious) + [one sentence per line](https://sive.rs/1s) (Sivers) · [Trusting Your Voice as an ESL Writer](https://sfwa.org/2024/04/09/trusting-your-voice-as-esl-writer/) (De Winter/SFWA) · Sugarman's slippery slide ([secondary](https://datadriventribe.com/blog/slippery-slide-great-copy/)).

**E. Corpus-internal:** the two 2020 Medium originals (publication URLs withheld here per the employer-agnostic policy; known to the author) · `tools/voice_check.php` run 2026-08-01 (24/24 editable posts clean) · per-post marker metrics computed over all 29 posts (script: session scratchpad `voice_metrics.py`; worth moving into `tools/` as part of 4.3).
