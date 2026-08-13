# Pricing the Invisible

*Your best architecture work produces nothing observable. Backlogs only rank observables. Let's fix the mismatch.*

The prioritization meeting is where architecture goes to lose. I've watched this match for years, and the script barely changes. A feature walks in with a number attached ("+2% conversion," "unlocks segment X") and takes its seat at the top of the backlog. Then the architecture item walks in: "we should consolidate the integration layer." No number. Just an architect, gesturing at the future.

Final score, season after season: features 500, architecture 3. And architecture only scored after live incidents did the lobbying. I suspect your scoreboard looks similar.

## The curse of invisible work

In [Mathematicians vs. Lawyers](/blog/mathematicians-vs-lawyers/) I wrote about the thinking archetype whose impact is chronically less visible: change the model, and whole families of problems silently stop existing. This is that curse, operationalized in a backlog.

When architecture work succeeds, the visible result is *nothing*. The incident that never fired opens no ticket. The complexity that never arrived has no demo. The integration that didn't need a workaround generates no praise thread. Prevention deletes its own evidence. That is the whole problem.

And backlogs, the machinery we use to decide what matters, only rank observables. A backlog is a list of things that will visibly happen. It has no column for things that will invisibly *not* happen. **The counterfactual has no product owner.**

## Why "the architect wants it" always loses

So how does architecture work usually get argued for? With authority. "This is important." "Trust me, this will bite us." "It's best practice."

Now watch what happens in the room. The feature brings a number; the architecture item brings an adjective. In any meeting where work gets ranked, **a weak number beats a strong adjective**, even when everyone privately suspects the number is fiction. Arithmetic, even bad arithmetic, gives people something to compare, and comparing is the only thing a backlog knows how to do.

"Trust me" is a real currency, but it doesn't scale. It spends the trust you have, it expires when you leave the room, and it loses every time to a spreadsheet cell. If invisible work is going to survive that meeting, it needs a number of its own.

## Give it an honest number

My own fix is borrowed from my tech-radar practice (described in [From Signals to Blips](/blog/from-signals-to-blips/)): score architecture and debt items on shared, explicit anchors.

```
importance = multiplicity × severity × strategic_weight
```

*Multiplicity*: how many teams or systems the item touches. You count them, which makes this the hardest factor to argue with. *Severity* (1–3): from a nuisance to actively blocked work, each level anchored in words everyone agreed on beforehand. *Strategic weight* (1–3): from local cleanup to a decision that sets an organization-wide precedent.

The multiplication matters: an item has to score on several dimensions at once to rank high. But the payoff is elsewhere. It is that you can **compare** them. Two architecture items scored on the same public anchors can be ranked against each other, and against features, without a single euro of fictional ROI. The anchors are published, so any score can be challenged; "why severity 3?" becomes a productive argument instead of a status contest. And scoring takes minutes, not a discovery sprint.

The fight changes shape immediately. The architect stops begging and starts comparing. "This scores 24 because it blocks four teams at severity 2 and sets a precedent" is a sentence a product owner can accept, challenge, re-weight, or throw out on the spot, and every one of those is better than a shrug: accept it, challenge it, trade against it. "We really should do this" never was.

## The trap: fake precision

Now the failure mode, because there is one, and it's seductive.

Having learned that numbers win, the tempted architect goes one step further: "this refactoring will save €340,000 per year." A currency! Decimals! Nobody can verify it, everybody smells it, and you've just converted your credibility into a one-time payment. I wrote about exactly this in [Data-Driven Theater](/blog/data-driven-theater/). Manufactured precision is worse than being honestly vague, because it teaches the room to distrust *all* your numbers, including the honest ones.

The anchored score works precisely because it says out loud how crude it is. It says: this is a triage tool built on public guesses (multiplicity counted, severity argued, weight assigned), not an audited business case. Every input can be defended line by line, which is exactly what makes it safe to use. **A number you can't defend is a loan against your credibility.**

So don't fake ROI. Publish the anchors, score in the open, state the assumptions, and let people attack the inputs instead of your authority.

The invisible work will never fully market itself. Prevention deletes its own evidence, and no formula changes that. But there is a difference between invisible and unpriced. You can't make the counterfactual observable. You *can* make it comparable.

One exercise before your next prioritization meeting: take the most valuable thing your team never shipped a ticket for, the outage that didn't happen or the rewrite that turned out unnecessary, and score it. Multiplicity, severity, strategic weight. Three inputs and a multiplication.

These days I don't walk into that room to argue. I walk in with a column instead. That is a smaller thing to ask for than it sounds. A column does not need anybody to believe me. It needs somebody to disagree with a severity rating in public, and that is an argument I can win. The one about whether architecture is worth doing is not.

---

**Updated:** 2026-08-13 10:45

**Scores:** tension=8 originality=6 personal=4 contrarian=7 substance=8 punch=8 trend=3
**Scored:** 2026-07-27 rubric=v1 model=claude-opus-5

**Tags:** Software Architecture, Technical Debt, Prioritization, Engineering Leadership, Data-Driven Decision Making
