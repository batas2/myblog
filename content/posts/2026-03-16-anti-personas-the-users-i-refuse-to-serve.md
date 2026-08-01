# Anti-Personas: The Users I Refuse to Serve

*The most important architecture document in my side project contains no architecture. It's a list of people I will never build for.*

In the vision document of my latest side project (a self-hostable scheduling tool written in Go) there is a section I'm prouder of than any diagram I've drawn this year. It comes right after the personas, and it's titled **anti-personas**: the users this product will never serve. Named, described, and rejected before the first line of code existed.

It sounds negative. It's the most constructive thing in the repository.

## The feature that is absence

The tool itself is deliberately small: a single binary, SQLite by default, no Docker required, a hard budget of about 5,000 lines of Go, and eleven ADRs explaining the boring choices. It is not trying to compete with [Calendly](https://calendly.com/) or [Cal.com](https://cal.com/). They have funding, teams, and ecosystems. It competes with their *narrow case*: one professional, one website, one booking flow they want to own end-to-end.

Every absence in that description is a decision, not a gap. No team features means no roles-and-permissions tree, no admin screens, no invitation flows. No billing integration means no webhook forest and no PCI anxiety. No video conferencing means no vendor SDK whose breaking changes become my weekend.

The absences even select the audience. The secondary persona in the vision doc is the senior engineer who self-hosts everything and will only adopt a tool that stays small. For them, "no Docker required" is the pitch.

A feature list tells you what a product does. **The anti-feature list tells you what it *is*.** Constraints are the taste. Anyone can add; the identity lives in what you refuse.

## Rejection, written down

The anti-personas are specific, and that specificity is the whole trick. A 50-person sales team needing round-robin assignment: not our user. A recruiting org wanting in-app video interviews: not our user. Anyone hoping for a free tier with an upgrade path: there is no business model, this is a tool, so also not our user.

These are real people with valid needs, and they will happily pay somebody. They are simply *someone else's* users. Writing that down feels almost rude, which tells you exactly how rarely we do it.

Watch what each rejection buys. Kill round-robin and you never implement availability-merging across calendars, fairness algorithms, or the admin UI to configure them, plus every bug report at the intersection of all three. **One anti-persona kills an entire tree of future edge cases before it sprouts.** The cheapest code review is the one for code that never got written.

And feature triage becomes almost mechanical. Every incoming idea (mine included, at 2 a.m., when everything seems like a good idea) answers one question: *which persona is this for?* If the answer is an anti-persona, it's a clean no. Painlessly. Nothing personal; it's in writing.

## The corporate mirror

This is where the side project starts explaining the day job.

In [Mathematicians vs. Lawyers](/blog/mathematicians-vs-lawyers/) I described the edge-case explosion: specification documents swelling to hundreds of cases that nobody reads in full. I blamed corporate culture's inability to say no. The scheduler taught me the same lesson from the other side. The explosion doesn't happen because anyone is careless. It happens because **a product company cannot refuse anyone.** Every exception is revenue. Every "no" needs a VP's spine behind it. Every accepted special case is one more branch in the system, and no incentive structure on earth rewards the person who turned down money.

A solo project can refuse everyone. That is its superpower: not the tech stack, not the single binary, not the artisanal ADRs. The economics of "no" are simply different. At home, refusing costs nothing; at work, it costs a fight.

Which is exactly why organizations shouldn't dismiss this as hobby-scale wisdom. Two practices copy over directly. First, **anti-personas as first-class strategy artifacts**: written, named, and signed by someone with authority, so that "we don't serve this segment" is a document, not a rumor that dies in the next QBR. Second, **absence-of-feature as a decision with an owner**: an ADR for the thing you deliberately didn't build and why, so the next PM inherits the reasoning instead of innocently reopening the trapdoor.

I've watched million-euro complexity enter systems through the simple absence of a written "no". The document would have cost an afternoon.

## Practicing "no" where it's free

My private conclusion is this: the ability to say no is the scarcest architectural resource I know, scarcer than senior engineers, scarcer than budget. And like any scarce skill, it atrophies without reps.

Side projects are my gym for refusal. Every anti-persona I write at home, every feature I decline with a one-line reference to the vision doc, is a rep. Those reps are what keep the diplomatic, watered-down, corporate-compatible version of my "no" from collapsing when a stakeholder leans on it.

The gym matters because of the transfer: the refusal I practice on a scheduling tool nobody uses yet is the same muscle I need in a room where somebody very senior is explaining why this one exception is different, and there is no way on earth to train it inside that room. So I train it in the repo. Write the anti-personas while refusing is still free. Get a signature on them before it isn't.

---

**Updated:** 2026-08-01 16:10

**Scores:** tension=6 originality=6 personal=8 contrarian=6 substance=7 punch=8 trend=2
**Scored:** 2026-07-27 rubric=v1 model=claude-opus-5

**Tags:** Product Strategy, Software Architecture, Side Projects, Product Management, Minimalism
