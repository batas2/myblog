# Field Notes: readability, round 4

Companion to [POST-PROMPT.md](./POST-PROMPT.md) (the rules) and
[HUMAN-VOICE-PLAN.md](./HUMAN-VOICE-PLAN.md) (round 3, sounding human).

**The brief:** the posts no longer read as machine-written. They still read as *hard work*. You have
to stop and go back over sentences. The target is light: personal, spoken to one listener, loose
reflections rather than a paper.

**Method:** measured all 31 posts on the things that actually predict comprehension, compared them
against the two Bartosz wrote himself in 2020, and read the worst sentences in the corpus. Sources
at the bottom.

---

## The short version

Sounding human and being easy to read are **different axes**, and round 2 optimised the first one
hard. A few of its rules push against the second. The corpus passes `voice_check` completely and
`read_check` fails 18 of 31 posts.

The problem is not sentence length, vocabulary, or missing glue. It is **clause depth in exactly
the wrong sentences**.

---

## What the numbers say

Averages first, his own 2020 writing against the 29 ghostwritten posts:

| | his 2020 | corpus | reading |
|---|---|---|---|
| Flesch reading ease | 57.4 | 59.7 | about the same, and both under the 60–70 plain-English band |
| mean sentence | 15.0 w | 13.5 w | shorter already; length is not the problem |
| causal connectives /1k | 5.2 | 7.0 | more glue than his own writing |
| contrastive /1k | 2.3 | 4.8 | more signposting than his own writing |
| "you" /1k | 10.9 | 13.2 | he is already addressing someone |
| abstract nouns /1k | 29.4 | 31.9 | slightly worse |

So cohesion is fine and the reader is being spoken to. Those are not the deficit.

The averages hide the real story. Flesch across the corpus runs **44 to 74**, and the split is
sharp:

| | easiest three | hardest three |
|---|---|---|
| Flesch | 72–74 | 44–50 |
| commas per sentence | 0.49–0.55 | 0.75–1.07 |
| abstract nouns /1k | 14–30 | 35–54 |

Two levers, and neither is length: **how many clauses are stacked into one sentence**, and **how
much of the noun vocabulary is abstract**.

## The finding that matters

Here are the most expensive sentences in the corpus:

> I walk in with a column, which is a smaller ambition than it sounds, because a column does not
> need anybody to believe me; it needs somebody to disagree with a severity rating in public, and…

> And you get git log for your architecture, so you can finally ask who moved this capability out of
> that context, when, and in which commit, which is the archaeology I have wished for in every
> architecture discussion of my career and never once had.

Notice what these are. They are not padding. **They are the argument.** Four turns of reasoning
chained through commas and subordinators, and the reader has to hold the first clause open while
parsing the fourth.

Only 6% of sentences are this dense. But they are the load-bearing ones, so the reader works hardest
at exactly the moment the stakes are highest, and 37% of sentences carry no clause markers at all.
The prose alternates between trivial and dense. That is not rhythm. It is a bumpy road.

And it is instructed. POST-PROMPT.md, round 2:

> Practically: after every long, clause-heavy sentence that carries the argument, put a short one
> that lands it.

That sentence is the bug. It puts the argument in the hardest sentence and the punchline in the
easy one. The CV ≥ 0.60 target then rewards the pattern, because stacking clauses is the cheapest
way to manufacture variance.

---

## Proposed changes to POST-PROMPT.md

### 1. Flip the argument-carrying rule

Replace it with: **the argument goes in the short sentence.** A long sentence may carry texture, an
example, a list, an aside. It may never carry the claim the paragraph exists to make.

### 2. Bound depth, not length

Keep "at least one sentence over 35 words". Add: **no sentence carries more than four clause
markers** (commas plus subordinators). Long is fine. *Deep* is what costs. A 45-word sentence that
flows in one direction is easier than a 25-word sentence with four hinges in it.

### 3. Abstract nouns get a budget

**28 per 1000 words.** Every `-tion`, `-ment`, `-ness`, `-ity` is a verb somebody flattened.
"the convergence of the two" → "the two converge". This one lever separates the easiest posts from
the hardest more cleanly than anything else.

### 4. Read it aloud, one breath per sentence

If you run out of air, the sentence is too deep. This is the cheapest test available and it needs no
tooling. It also enforces the thing the brief actually asks for: prose that sounds spoken.

### 5. Retire the compression bias

The aphorism cap of one per post stays. Add the reason: **compression is what makes a line quotable
and a paragraph slow.** Around the one aphorism, prose should be plain. A post where every third
sentence is lathed is a post nobody finishes.

### 6. Speak to one person

He asked for "do drugiego słuchacza", and the corpus is already at 13 "you" per 1000. What is
missing is not density but *direction*: at least one moment per section where the reader is
addressed as a person with a problem, not as an audience.

### 7. Add the gate

`php tools/read_check.php <slug>` alongside `voice_check.php`. Two levels: a floor a post may not
ship below, and an aim set where his own easiest posts already sit.

| metric | floor | aim |
|---|---|---|
| Flesch | 52 | 62 |
| commas per sentence | 0.85 | 0.60 |
| abstract nouns /1k | 42 | 28 |
| dense sentences /1k | 5.0 | 2.5 |
| clauses in one sentence | 6 | 4 |

Calibrated on this corpus, not on a grade level nobody writes to.

### 8. Retrofit, worst first

`read_check` names them. `pricing-the-invisible` (Flesch 50, 54 abstract nouns/1k),
`multi-agent-is-an-org-chart` (44), `prompt-driven-architecture` (1.07 commas/sentence). Frozen
posts stay frozen.

---

## The council

[claude-skills-llm-council](https://github.com/aiwithremy/claude-skills-llm-council) runs five
advisors over one question, has them review each other, and a chairman synthesises where they agree
and where they clash. The shape fits this problem, because "is this hard to read" is exactly the
kind of question one reader answers badly and five answer well.

`.claude/skills/readability-council/` adapts it, with one change that makes it worth having here:
**the council writes its findings into `content/review/<slug>.json`**, so they arrive in the margin
of `review.php` beside your own notes and travel through the loop you already built.

Five readers, because each one fails differently:

1. **The skimmer** reads it on a phone in forty seconds and says where they lost the thread.
2. **The non-native reader** says which sentences needed a second pass. Much of the audience reads
   English as a second language, and so does the author.
3. **The listener** has it read aloud and says where the breath runs out.
4. **The sceptic** is the staff+ engineer, and says where compression is hiding a hole rather than
   holding an insight.
5. **The editor** applies Gopen and Swan: subject next to verb, old information first, new
   information in the stress position at the end.

Then they read each other and drop what they cannot defend, and the chairman ranks what is left into
specific sentences with specific rewrites.

The rule that keeps it useful: **a finding names a sentence.** "The middle section drags" is not a
finding. "This 47-word sentence has four hinges, split it after 'believe me'" is.

---

## Sources

- Graesser, McNamara et al., *Coh-Metrix: analysis of text on cohesion and language* —
  <https://link.springer.com/article/10.3758/BF03195564>. Cohesion predicts readability better than
  the word-length-and-sentence-length formulas do, which is why this plan does not chase Flesch alone.
- Gopen & Swan, *The Science of Scientific Writing* —
  <https://www.usenix.org/sites/default/files/gopen_and_swan_science_of_scientific_writing.pdf>.
  Topic position, stress position, and keeping a subject next to its verb. The clearest account of
  why grammatical prose can still be exhausting.
- Chandler & Sweller on split attention, already cited in the sequence-diagram post, for why an
  explanation placed away from the thing it explains costs the reader twice.
- The corpus itself: `tools/read_check.php`, and the measurement script that produced the tables
  above (session scratchpad, worth moving into `tools/` if this becomes routine).
