# Field Notes: the topic engine

Companion to [BLOG-IMPROVEMENT-PLAN.md](./BLOG-IMPROVEMENT-PLAN.md). This file answers one question: **where does next Monday's post come from?**

Why this file matters right now: both old idea files ([blog-post-ideas.md](./archive/blog-post-ideas.md) and [blog-post-ideas-side-projects.md](./archive/blog-post-ideas-side-projects.md), now in `docs/archive/`) are **fully used up. All 16 ideas are already published.** The blog has 23 posts, but the idea list behind it is now empty. You cannot pick ideas from a list anymore. You need a system that produces new ideas every week.

This file is that system. At the end, it also gives you **12 ready-to-write ideas** to restart the pipeline.

How to use this file:

- Read [The loop](#the-loop) once.
- Run the [Monday ritual](#the-monday-ritual-30-minutes) every week. It takes 30 minutes.
- During the week, when something interesting happens, write one line in the [Inbox](#inbox-raw-captures).
- Everything else is reference. Come back to it when you need it.

---

## The loop

Every idea goes through four steps:

```
CAPTURE ──▶ ANGLE ──▶ FORMAT ──▶ SCHEDULE
(5 min,     (which     (note?     (which
 when it     lens?      essay?     Monday,
 happens)    which      data       which
             reader?)   essay?)    slot)
```

- **Capture** = write one line in the Inbox within 24 hours of the event. Do not polish it. Just save it before you forget.
- **Angle** = once a week, look at your captures. Pick the 1–2 lenses (see below). Name the reader. Write the one claim the post must make.
- **Format** = decide the size, not the quality. The same idea can be a 200-word note this Monday, or grow into a full essay next month. Start small. Grow it only if readers respond.
- **Schedule** = give it a real date. An idea without a Monday attached is still just a capture, not a plan.

**The math.** The plan's target is 4 posts + 8 notes per month. Here is how the sources pay for it:

| Source | Gives per month |
|---|---|
| Day job material | 1 essay |
| Build-logs (product stories) | 1 build-log |
| Questions from people | 0.5–1 essay or note |
| Replies to famous bloggers | ~0.5 (when a trigger appears) |
| Contrarian-with-data | 0.33 (one data essay per quarter) |
| TILs ("Today I Learned" notes) | 8 notes (2 per week) |

That adds up to 3–4 real posts plus 8 notes per month, without heroic effort. **If a month is empty, the problem is capture, not writing.** Fix the ritual, not the calendar.

---

## The four lenses

A lens is the angle you write from. Every idea gets 1–2 lenses. The lens tells you who the reader is. **If an idea fits no lens, drop it.** It may be interesting, but it does not build your brand.

| Lens | What it means | Who reads it | Test before writing |
|---|---|---|---|
| **[ARCH]** | Architecture craft: real patterns, real decisions, real scars | Senior engineers and architects | Will a working architect learn a pattern or a question they can reuse? |
| **[HUMAN]** | The people around the systems: identity, trust, communication | Tech leads, staff+ engineers | Does it name a feeling or situation the reader knows but never had words for? |
| **[ORG]** | How the corporate machine works: incentives, structure, theater | Engineering leaders — your future consulting clients | Does it explain the system, instead of blaming people? |
| **[SHIP]** | Building and running real products alone, with AI agents | Senior devs curious about indie work; proof for clients that you still build | Does it show something real: a diff, a number, a screenshot, a test count? |

Examples from your own archive, if you are unsure what "good" looks like for each lens: [ARCH] *The Anti-Corruption Layer Is for People Too*, *Prompt-Driven Architecture* · [HUMAN] *Grinding Dijkstra at Senior Level*, *Technical Leader Identity Disorder* · [ORG] *Nobody Owns the Sum*, *Data-Driven Theater*, *Mathematicians vs. Lawyers* · [SHIP] *A Company of One, Staffed by Agents*, *The Diagram That Cannot Lie*.

---

## The six standing sources

Each source has three parts:

- a **trigger** — the event that fires it,
- a **ritual** — what you do within 24 hours (it takes minutes),
- a **yield** — how much content it owes the calendar.

The rituals ARE the engine. If you skip them for two weeks, the pipeline is empty again.

### 1. Day job material — 1 essay per month

- **Trigger:** a radar edition ships · a RAPID decision closes · a workshop or training ends · any meeting that made you angry enough to write a note to yourself.
- **Ritual (5 min, within 24 h):** write one line in the Inbox: *the pattern + the concrete scene it came from + which lens*. Remove company names and code names right away (same rule as the radar posts). Then the capture is already safe to publish later.
- **Yield:** one pattern essay per month. This is your best-performing type of post. Protect its slot.
- **Quality rule:** the essay needs a scene ("I watched a meeting where…"), not only a thesis. If the capture has no scene, let it wait until one appears.

### 2. Build-logs — 1 per month, products take turns

- **Trigger:** something happens in **Desta**, **Verso**, **scheduler-go**, or **expenses2027**: a feature is done, a bug fought back, you made a decision, a number changed.
- **Ritual (10 min, same day):** capture it *together with the proof*: the diff, the failing-then-passing test output, the screenshot, the number. The proof is the post. Writing around a saved artifact takes one hour. Writing from memory takes three hours and reads worse.
- **Yield:** one build-log per month, under the tag **Building in the Open**. Rotate the products, so each one appears 3–4 times a year.
- **Why it matters:** the [SHIP] pillar answers the question "does this architect still build anything?". And every Verso build-log is free marketing for Verso.

### 3. TILs ("Today I Learned" notes) — 2 notes per week

- **Trigger:** any daily AI-workflow surprise: a prompt that failed in an interesting way, a hook or skill that worked, an MCP discovery, a check that caught the agent lying.
- **Ritual (2 min, end of the day):** ask yourself "what surprised me today?". If something did, save the *exact* command, prompt, or snippet. Word for word. TILs die when the details are lost.
- **Yield:** two notes per week (100–300 words each), once the `/notes/` section exists (Action 6). Until then, collect them in the Inbox. They become the launch content.
- **Quality rule:** every note must contain one concrete thing the reader can copy: a command, a prompt, a config, a rule. No concrete thing = no note.

### 4. Replies to famous bloggers — 1–2 per quarter

The original name of this tactic is "pick up what they put down" (from swyx): when a big-name blogger publishes something, you extend it, test it, or push back on it. Done well, they notice you. This is the fastest way to get links and readers from people much bigger than you.

- **Trigger:** a post from your watchlist makes a claim that you can *extend, test, or challenge* with material they do not have: your transformation data (several hundred developers), six radar editions, four shipped products.
- **Watchlist (put these in one RSS folder):** simonwillison.net · lethain.com (Will Larson) · jvns.ca (Julia Evans) · swyx.io · Pragmatic Engineer · martinfowler.com.
- **Ritual (20 min, part of the Monday ritual):** read through the folder. For at most one post, ask: "do I have data or scale they don't have?" If yes, capture the claim + your evidence.
- **Yield:** one or two reply essays per quarter. Always link to the original post and mention the author when you share it on LinkedIn/X. Be generous — that is the whole point.
- A ready template for this is waiting in the backlog below: *What Scales to Three Hundred Developers*.

### 5. Questions from people — 0.5–1 piece per month

- **Trigger:** every question from a training session, a blog comment, a LinkedIn comment or DM.
- **Ritual (2 min):** write down the question *word for word*, plus the role of the person who asked ("a tech lead at a product company" — no names). The role matters: it tells you which reader the answer is for.
- **Rule:** one question = a capture. **Three similar questions = a validated post.** People already told you they want it. This is the only source with built-in market research.
- **Bonus:** the newsletter's call-to-action ("reply with your biggest architecture challenge") feeds this source on purpose.

### 6. Contrarian-with-data — 1 data essay per quarter

"Contrarian" = a claim that goes against what most people believe. The research on Hacker News says this is the single best-performing type of post: **a claim the audience half-believes, backed by real data.**

- **Trigger:** none. This one is planned. Block half a day once a quarter.
- **Ritual:** pick one claim the industry half-believes. Then check if your radar or product data really supports it. **If the data does not support it, do not publish it.** Faking the shape is exactly the "Data-Driven Theater" your own blog argues against. Readers will smell it.
- **The recipe:** contrarian claim + first-person story + concrete numbers + no self-promotion. *Mathematicians vs. Lawyers* and *Data-Driven Theater* already have this shape. The data essay adds the numbers.
- **Yield:** one per quarter. These are your Hacker News submissions. Aim carefully, don't spray (see Action 4 for how to submit).

---

## The format ladder

A format is a size. **Pick the smallest size that fits the idea.** Grow it only when readers show interest.

| Format | Time | Length | Structure | How often | Where |
|---|---|---|---|---|---|
| **Note / TIL** | 15–30 min | 100–300 words | What I tried → what surprised me → the thing to copy (command/prompt/rule) → one-line takeaway | 2 per week | `/notes/` + RSS feed |
| **Build-log** | ~1 h | 400–800 words | Context in 2 lines → the problem → what I did (with proof: diff/screenshot/test) → what broke → what's next | 1 per month | `/blog/`, tag *Building in the Open* |
| **Essay** | 3–5 h | 650–1300 words | Exactly as [POST-PROMPT.md](./POST-PROMPT.md) says: hook scene, disclaimer, one aphorism, closing question, 5 tags | 2 per month, Mondays | `/blog/` |
| **Data essay** | 1–2 days | 900–1500 words + 2–3 charts | Claim → how I measured (honest) → charts → what it means → caveats | 1 per quarter | `/blog/` + Hacker News |
| **Pillar page** | assembled from posts | evergreen | Intro + map of the series, links in both directions | refresh quarterly | `/blog/…playbook/` |
| **Ebook / talk** | assembled | — | Series + data essay collected into one artifact (like Will Larson did with his essays) | month 6+ | newsletter gift / conference |

**Growing up the ladder (only with evidence):**

- A note gets comments, traffic, or newsletter replies → grow it into an essay.
- Three or more essays share a theme → name the series, give it a page.
- A series plus a data essay → fold them into the pillar page.
- A pillar page pulls search traffic → make it an ebook chapter and a talk.

**Going down the ladder (protects your rhythm):** if an essay draft is stuck after two writing sessions, stop fighting it. Publish the main point as a short note this Monday. The full essay can wait for a better angle. **A stuck draft must never cost you a publishing slot.**

---

## The Monday ritual (30 minutes)

This fits the Monday-evening planning slot already defined in `09-personal-brand.md`. It is the only scheduled maintenance the engine needs.

1. **Sweep (10 min):** read the Inbox and the RSS watchlist. Turn good captures into idea cards (title + lens + one-sentence thesis). Delete captures that died.
2. **Slot (5 min):** confirm what ships *next* Monday and that it is on track. Check the month's mix: 2 essays + 1 build-log + notes. If the slot is empty, take the top card from the backlog below.
3. **Angle (10 min):** for the next post, write three lines before drafting: the lens, the reader, the one bold claim it must make. If you cannot write the claim line, the topic is not ready. Swap it for another.
4. **Log (5 min):** did anything happen last week that you should have captured but didn't? Capture it now. Note which ritual failed, so it does not fail twice.

---

## Inbox (raw captures)

During the week, add one-liners here. The Monday ritual empties it. Format: `YYYY-MM-DD · source · the line · [lens?]`. Nothing here is sacred. Delete freely.

*(empty — the backlog below carries the pipeline until captures build up)*

---

## Ready-to-write backlog: 12 ideas

Twelve cards to restart the pipeline. Mined from the same places as the two used-up backlogs: open threads in published posts, the product repos, and the transformation work. Same rules as before: concept-name titles; keep the mechanics, drop the code names; your own products can be named with real detail.

### Essays

**1. The Bottleneck Moved — [ARCH] [ORG] · essay · from day job**
Main idea: AI made producing code fast. Now the slow part is reviewing it. But companies still staff, measure, and reward people as if typing was the work.
- The evidence: in squad reports, teams stopped being blocked on "can't produce" and started being blocked on "can't absorb" (review queues)
- "Reviewability" is a system property: good tests, contracts, and context files make a system easier to review, like indexes make a database easier to query
- What should change: the definition of done, who owns verification, what "senior" means when reading matters more than writing
- Links back to: *A Company of One* (the same bottleneck, solo version), *Probabilistic Metrics*

**2. The Org Chart Writes Your Prompts — [ORG] [ARCH] · essay · from day job**
Main idea: Conway's law survives the AI transition. Squads' agent workflows copy their team boundaries — including the broken hand-offs. Direct sequel to *Multi-Agent Is an Org Chart*, now with adoption evidence.
- Which practices spread inside a tribe vs. across tribes — and why the border is organizational, not technical
- The "telephone game" problem appears in agent pipelines in exactly the same places as in the org
- The architect's job: design the context boundaries, not only the agent graph

**3. Training Three Hundred Skeptics — [HUMAN] [ORG] · essay · from questions people ask**
Main idea: senior developers resist AI tools in a few repeatable ways. You can name those ways. And converted skeptics become your strongest allies.
- The objection types met in workshops (pride in craft, burned before, quietly overloaded) — named with respect, not mockery
- What actually changes each type's mind — and the one thing that never works: forcing them
- Workshop mechanics that survived contact with several hundred developers

**4. The Missing Rung — [HUMAN] [ORG] · essay · from questions people ask**
Main idea: "Will AI take the juniors' jobs?" is the wrong question. The first step of the career ladder moves from writing code to verifying it — and companies have not rebuilt the ladder.
- The question every single training session asks, and the answers I stopped giving
- What junior work looks like when producing code is free and trust is expensive
- What companies owe the career ladder — and why nobody pays for it by default

### Build-logs (the launch trio for *Building in the Open* — see Action 9)

**5. Teaching AI to Read Five Banks — [SHIP] [ARCH] · build-log · expenses2027**
Main idea: five banks, five different CSV formats, one agent pipeline. Letting the agent infer the schema, plus a verification loop, beats writing parsers by hand.
- The zoo of formats: encodings, separators, sign conventions, headers that drift
- The "verify, then trust" loop — and the cases where it still lies to me
- Proof: redacted diffs and the test fixtures that encode each bank's quirks

**6. The Diff Is the Contract — [SHIP] [ARCH] · build-log · Verso**
Main idea: Verso has one rule above all others: a UI edit must produce a git diff with *only* the intended change. That rule is the product. Everything else follows from it.
- What "only" costs in practice: Roslyn rewrites, the committed layout sidecar, round-trip tests
- How one hard rule ends feature debates painlessly (the *Anti-Personas* trick, applied to internals)
- Picks up the flag planted in *The Diagram That Cannot Lie*

**7. A Thousand Tests Between Me and the Agent — [SHIP] [ARCH] · build-log · Desta**
Main idea: when agents write the code, the test suite changes its job. It stops being quality insurance and becomes the thing that lets the operator trust the changes.
- What the ~990 tests actually protect, and which changes get read line-by-line vs. trusted to the suite
- The day the suite caught the agent: one concrete story
- What "coverage" means when you deliberately do not read every line

### Data essay (this quarter's Hacker News shot — see Action 8)

**8. The Graveyard in the Adopt Ring — [ORG] [ARCH] · data essay · contrarian-with-data**
Main idea (the contrarian claim): practices that get promoted fastest also die fastest. The unpopular Hold-ring items quietly outlive the hype. Six radar editions of evidence.
- Table of ring changes across editions; survival by ring
- The mechanism: promotion driven by enthusiasm instead of evidence ("cite or it didn't happen" exists exactly because of this)
- Honest methodology + caveats section — the *Data-Driven Theater* test applies to your own charts too

### Standing reply (waiting for its trigger)

**9. What Scales to Three Hundred Developers — [ARCH] [ORG] · reply essay · reply to a famous blogger**
A template kept warm: when the next big claim about solo AI workflows appears (Willison, swyx, Larson…), test it against your transformation-scale reality.
- Their claim, quoted generously and linked → your data at scale → exactly where it holds and where it breaks
- Ship it within a week of their post, while the conversation is still alive; tag the author when sharing

### Notes (launch content for `/notes/` — see Action 6)

**10. A grep that guards the house style** — the em-dash check from POST-PROMPT.md as a 150-word note: the rule, the one-liner command, and why a dumb grep beats a style guide nobody reads.
**11. One context file to route them all** — the hierarchy trick from *A Company of One* as a 200-word recipe you can copy: one big-picture context file that routes agents to per-project ones.
**12. A prompt that failed honestly** — one real failed prompt, word for word, plus two lines on why it failed. Sets the tone for the whole notes stream: real details or nothing.

### Suggested order (next five essay slots)

1. **The Bottleneck Moved** (#1) — the strongest fresh observation; continues the archive's AI momentum; prepares the ground for the data essay
2. **Teaching AI to Read Five Banks** (#5) — starts *Building in the Open* with the most relatable story of the trio
3. **The Org Chart Writes Your Prompts** (#2) — a sequel to the archive's best multi-agent piece
4. **The Graveyard in the Adopt Ring** (#8) — the quarter's data essay, once the data is prepared (needs the half-day block first)
5. **Training Three Hundred Skeptics** (#3) — a human-centered piece, good change of pace after two data-heavy ones

Cards #4, #6, #7, #9 float on their triggers and fill the following weeks. Notes #10–12 ship the day `/notes/` goes live.

---

*Refill rule: when this backlog drops below 8 cards, the quarterly review (Action 14) refills it. An empty backlog is a system failure with a known fix: run the capture rituals.*
