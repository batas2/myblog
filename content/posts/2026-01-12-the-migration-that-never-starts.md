# The Migration That Never Starts

*Every organization has one. Yours does too. It has a folder, a deck, and a birthday.*

Somewhere in your company's document storage there is a folder. It might be called "Migration Options," or "Target Architecture," or "Platform Modernization Analysis." It contains several well-written documents, each comparing three to five options, each ending with "recommendation to be validated." The oldest file is years old. The newest was added last quarter.

I have contributed to such folders. I have *created* such folders. So take what follows as private observations, not science, but observations from both sides of the crime scene ;)

Here is the conclusion those folders eventually forced on me: **organizations don't fail migrations. They fail to start them.** The industry loves horror stories about big migrations that went wrong. Honestly, I've seen far more damage done by migrations that never left PowerPoint.

## The Options-Collection Ritual

The mechanism looks thoroughly respectable. A migration is clearly needed: the old platform hurts, everyone agrees, nobody objects. So we do the responsible thing: we analyze. We produce a comparison matrix. Options, strengths, weaknesses, costs, risks.

Then the quarter ends, priorities wobble, and the document goes to the folder. Next quarter someone reasonably notes that "the landscape has changed" (the landscape has always changed) and commissions a refresh. A new document is added. **Nothing is ever subtracted.**

This is analysis as a socially acceptable form of postponement. Nobody ever got fired for producing a comparison matrix. Producing one *feels* like progress, reads like diligence in a status report, and carries none of the personal risk of an actual decision. The ritual has all the ceremonial properties of work (effort, artifacts, meetings) with none of the consequences.

And every refresh quietly resets the clock. You can postpone anything indefinitely if you re-analyze it at the right frequency.

## Decision Debt

We track technical debt in backlogs. We track risks in registers. The compounding cost of *not deciding* is tracked exactly nowhere, and it's often the largest item on the invisible balance sheet.

Because while the decision waits, the old platform doesn't wait with it. Every sprint adds new integrations to the thing you intend to leave. Every new feature is another consumer of the legacy contract, another strand in the net that will have to be cut later. The migration doesn't get cheaper while you analyze it. The price of "not yet" is invoiced monthly and itemized never.

There's a human line item too, and it compounds faster than the technical one. The third time a migration is announced and doesn't start, teams stop believing announcements. Engineers who prepared for the move quietly file the experience under "management noise." The zombie migration doesn't just burn analysis budget; it devalues every future commitment leadership makes. That currency does not come back at the next all-hands.

## One-Way Doors, Two-Way Doors

Amazon popularized a genuinely useful question: is this decision a one-way door or a two-way door? Irreversible decisions deserve slow, heavy scrutiny. Reversible decisions deserve speed; the cost of a wrong reversible decision is just the walk back through the door.

Now look honestly at a migration. Routing one endpoint through the new service: reversible. Dual-writing to both data stores: reversible by design. Moving one read path, one team, one low-risk workload: two-way doors, all of them. The overwhelming majority of migration *steps* can be undone with a config change and a mildly embarrassing standup update.

Yet the organization treats every step as a bet-the-company moment. Why? Because the migration was framed as one giant decision, "we are moving to X", and *that* framing is a one-way door, socially if not technically. Nobody wants to personally own the giant door, so everybody keeps studying the hinges.

The trick is almost insultingly simple: stop deciding the migration. Decide the next step. **Decompose until every step is a two-way door, then walk through the first one.**

## What Actually Unblocks

Three things, in my experience, and none of them is another analysis:

1. **A timebox on the analysis.** Options expire like milk. Decide by a date, not by certainty, because certainty is not coming. It never was. An 80%-informed decision this quarter beats a 95%-informed decision that will never be made.
2. **A named decider.** Not a committee, not "alignment," one accountable person. Consensus is where migrations go to die; a name is where they wake up. I've written more about decision rights in [Decisions Have an Architecture Too](/blog/decisions-have-an-architecture-too/).
3. **A first step too small to refuse.** One endpoint, one consumer, two weeks, fully reversible. Its job is not impact. Its job is to convert the migration from a document into a fact, because facts attract budget, volunteers, and momentum in a way folders never do.

A failed migration at least produces knowledge: you learn where it breaks, what it costs, who steps up. A migration that never starts produces only documents, plus a slow, expensive lesson in how little your organization's announcements are worth.

So, one honest question: which folder in your document storage is quietly approaching its fifth birthday?

Maybe don't refresh it this quarter. Maybe pick the smallest reversible step it describes and just open door number one.

---

**Tags:** Software Architecture, Decision Making, Legacy Systems, Engineering Leadership, Organizational Culture
