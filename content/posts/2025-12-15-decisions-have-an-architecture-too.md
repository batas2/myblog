# Decisions Have an Architecture Too

*We design our systems obsessively, then let the decisions about them happen by loudest voice.*

I once counted the workshops we had held about the same topic: who owns a shared access-control integration. Three. Three workshops, the same diagrams, largely the same people, and at the end of each one the warm feeling of a productive discussion and no decision whatsoever. The fourth workshop was already in the calendar.

The fourth workshop never happened. We assigned letters instead. More on the letters in a moment.

As architects we obsess over system design: boundaries, contracts, failure modes, who calls whom. Meanwhile the *decisions about those systems*, often worth more than the systems themselves, happen with no design at all. Whoever speaks loudest, or last, or closest to the deadline, wins.

## The meeting that cannot decide

Here's the anatomy of a stalled architecture decision, and I bet you've lived it.

A question appears: who owns X, do we build Y, do we retire Z. A meeting is called. Smart people attend and say smart things. Somewhere near the end, someone summarizes "so we're aligned", and everyone nods, each person having heard a slightly different decision. Two weeks later the topic is back, re-litigated from scratch by whoever wasn't in the room.

The root cause is almost never missing information. It's that **nobody knows who actually decides.** A decision made by a meeting belongs to no one, so it evaporates the moment the meeting ends. Un-deciding it costs nothing, because deciding it cost nothing.

We would never build a system where any service can silently overwrite another's data. Yet we run decision processes where any sufficiently senior passer-by can overwrite last month's conclusion.

## RAPID, in plain words

The letters that saved us from workshop number four come from [Bain & Company](https://www.bain.com/insights/rapid-tool-to-clarify-decision-accountability/): **RAPID** (their trademark, their consultants will be pleased I said so). Strip away the consulting varnish and it's simply an **API contract for decision-making**:

- R: Recommend. The person who does the analysis and brings a concrete proposal. Not a vibe, a proposal.
- A: Agree. The few whose formal sign-off is required (security, compliance). A veto with named grounds, not a mood.
- P: Perform. Whoever will do the work. They get a voice *before* the decision, not a surprise after it.
- I: Input. People consulted because they know things. Consulted, not obeyed.
- D: Decide. One name. Not a committee, not a forum, not "the leadership". One name.

Five letters, one rule: every letter is a person, and D appears exactly once.

Writing the letters down is what changed the behavior; the framework did nothing on its own. Our ownership dispute that survived three workshops was resolved in days once the letters existed. The R stopped selling to the whole room and wrote one decent document. The I's gave input without needing to "win". And the D, suddenly aware the decision was *theirs* with their name on it, asked sharper questions in one meeting than the previous three had produced in total.

Nothing about the problem changed. Only the architecture of the decision did.

## The anti-patterns

Once you have the vocabulary, you start seeing the broken decision architectures everywhere.

Everyone has a veto. The A role inflates until half the org can block and nobody can approve, which is a system where every component can halt the pipeline and no component can ship it. The result is decision starvation with extra meetings.

Consensus theater is the harder one to name, because from the inside it looks like respect. The meeting is formally seeking a decision while what it wants is unanimous comfort, so it waits not for the best argument but for the last objection to get tired. I've written about the staged version of data-driven meetings in [Data-Driven Theater](/blog/data-driven-theater/). Consensus theater is its twin: same stage, different prop.

Then the alignment ritual. "Alignment meeting" is frequently the phrase an organization uses when nobody is willing to say *"I decide."* Alignment is a wonderful outcome and a terrible process goal: you align *around* a decision, not *instead of* one.

None of these people are acting in bad faith. That's the uncomfortable part. Each anti-pattern is locally rational: vetoes feel like diligence, consensus feels like respect, alignment feels like collaboration. The system produced by these good intentions cannot decide anything.

> "No matter how it looks at first, it's always a people problem." ― Gerald Weinberg, The Secrets of Consulting

## ADR records what, RAPID records who

Architects already have a beloved decision artifact: the [ADR](https://cognitect.com/blog/2011/11/15/documenting-architecture-decisions), the Architecture Decision Record. I write them, I love them, I make agents read them. But an ADR documents *what* was decided and *why*. It says nothing about *who* had the right to decide it, and whether they actually did.

The two are complements, not competitors. RAPID (or any explicit decision contract; the brand doesn't matter) defines who; the ADR preserves what. Pair them and decisions become durable: made once, by a named person, with named input, written down.

Skip the first half and you get my favorite one-liner from this whole experience: **an ADR without a decider is a wish with a timestamp.**

## One question instead of one more meeting

So here's my practical suggestion. Next time an architecture decision stalls, bouncing between forums and gathering workshops like a hobby, don't schedule another meeting. Ask one question, in writing, where everyone can see it:

**"Who is the D on this?"**

If the answer comes quickly, you'll have a decision within days. If the answer is silence… congratulations, you've found the actual blocker, and it was never the technology.

Start with the oldest one. Every organization is carrying a question that has been circling for a year, gathering forums. It is circling because no one has ever been named to end it. That also makes it the cheapest thing on your list to unstick.

---

**Updated:** 2026-08-13 10:45

**Scores:** tension=6 originality=6 personal=7 contrarian=6 substance=7 punch=8 trend=3
**Scored:** 2026-07-27 rubric=v1 model=claude-opus-5

**Tags:** Decision Making, Software Architecture, Governance, Corporate Culture, Engineering Leadership
