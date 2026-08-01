# Probabilistic Metrics

*Why the most useful numbers in our AI transformation were never in a database.*

In my previous article ([From Signals to Blips](/blog/from-signals-to-blips/)) I described the engine room of our AI transformation: a tech radar fed by weekly AI sweeps over 15 squad reports. Every week an agent reads the reports, extracts signals, scores them, and hands me a ranked candidate list for human validation.

This article is about the piece of that machinery I trust least and use most. The scores that steer our architecture attention are not computed from clean, structured data. They are **extracted by AI from messy human text: probabilistically, with a known and accepted margin of error.**

For once there is a production system behind my private conclusions, so the usual grain of salt can be slightly smaller ;)

## The clean data tax

Classical metrics demand clean inputs: instrumented pipelines, structured events, agreed definitions. That cleanliness is a tax, and you pay it three times. Once in engineering, because somebody has to build and maintain the pipeline. Once in politics, because somebody has to win the meeting where "active user" gets defined. And once in coverage, because you only ever measure what you predicted, a year in advance, would be worth measuring.

And after paying all of that, the numbers still get reshaped in preprocessing before anyone sees a chart. I wrote a whole article about that theater ([Data-Driven Theater](/blog/data-driven-theater/)).

The result is an organization precisely informed about a thin slice of reality and blind everywhere else. And the blind spots are not random. They are exactly the messy, human parts of the system: friction, workarounds, practices quietly spreading between teams, morale.

## What AI actually changes

AI changes the **input space of measurement**. A metric no longer has to start its life as a structured event. It can be extracted from free-form text: a blocker described in a frustrated sentence, a win buried in the middle of a paragraph, the same practice named three different ways by three different teams.

On the radar this is very concrete. An agent decomposes every weekly report into atomic claims, classifies each one as a stopper or a promotable, and scores it on multiplicity, severity, and strategic weight. Nobody built a data pipeline for this. The "sensor" is a versioned prompt pointed at documents that already existed.

Any single extraction can be wrong. And that is not a bug waiting for a fix. That is the deal. You trade per-observation certainty for coverage of the whole organization.

## The assumptions

This trade is only sane under explicit assumptions. Here are mine, all learned (some painfully) from running the radar:

1. Accept the error rate, and say it out loud. A dose of acceptance is part of the contract. If a stakeholder expects audit-grade numbers, stop them at the door. This instrument doesn't produce those, and pretending otherwise is how trust dies.
2. Never trust a single extraction. Trust convergence. One squad's report can be misread; five squads independently describing the same friction is a fact about the organization, whatever the error rate per reading. Redundancy replaces precision.
3. Every number must cite its source. Our rule is *cite or it didn't happen*: each scored signal points back to the exact report, week, and section it came from. Probabilism without traceability is just noise with confidence.
4. A human gate before consequences. Probabilistic metrics route attention; they must never issue verdicts. In our case nothing enters the radar without a human ticking a checkbox. AI proposes, humans dispose.
5. The prompt is the preprocessing, so treat it like code. In *Data-Driven Theater* I argued that data manipulation lives in the preprocessing phase. Here, the preprocessing *is* the extraction prompt. So it's versioned, its scoring anchors have a changelog, and anyone can read it. If you hide the prompt, you've rebuilt the theater with better lighting.
6. Shared language still matters, but now it's the model's dictionary. An LLM will happily map three teams' different vocabularies onto one concept. That's the superpower and the danger in one move: it can silently merge things that are not the same. The semantic contracts between people don't disappear; auditing how the model translates becomes part of the method.

## What I take out of it

Coverage beats precision when the job is steering. I'd rather be roughly right about the whole organization than precisely right about the 5% of it I managed to instrument. For triage (*where should attention go this week*) that is simply the correct trade.

You can also measure things that never had a pipeline. Converging practices, recurring friction, the mood of a transformation. On the radar, these signals routinely show up weeks before anything reaches a dashboard, because the dashboard for them was never going to be built.

And metrics become disposable, which is the change I underestimated most: when a new metric costs one prompt instead of one quarter, you can try ten and throw away eight, and the metric portfolio stops being an investment you defend in meetings and becomes a hypothesis you test on Tuesday.

The failure modes move; they don't vanish. Yesterday people gamed the data cleaning. Tomorrow they'll game the extraction.

> "When a measure becomes a target, it ceases to be a good measure." ― [Goodhart's law](https://en.wikipedia.org/wiki/Goodhart%27s_law), in Marilyn Strathern's phrasing

Same theater, new stage, and the same defense: documented preprocessing, cited sources, and a culture where asking "how was this computed?" is a compliment, not an attack.

None of it will survive an audit, and that's fine. A compass is not an accounting system. Use probabilistic metrics to decide where to look; once you know what you're looking at, instrument it properly. The two don't compete. One finds the question, the other defends the answer.

## One question

So here is what I'd leave you with: **what would you measure in your organization if a new metric cost one prompt instead of one quarter?**

Because that is the actual offer on the table now. The classic excuse for not measuring the human side of your system (too messy, too expensive, no pipeline) has just expired.

---

**Updated:** 2026-08-01 16:10

**Scores:** tension=7 originality=8 personal=6 contrarian=8 substance=9 punch=8 trend=8
**Scored:** 2026-07-27 rubric=v1 model=claude-opus-5

**Tags:** Metrics, Artificial Intelligence, Data-Driven Decision Making, AI Transformation, Engineering Leadership
