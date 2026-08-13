# ADRs for an Audience of One

*My hobby project has eleven Architecture Decision Records and zero users. That ratio is correct.*

In the evenings I build a small self-hosted scheduling tool in Go. Just me. Single binary, SQLite by default, no Docker required, deliberately single-user. It has no users. Not even me, technically, since it isn't deployed yet. It does have eleven [Architecture Decision Records](https://adr.github.io/): one for the database, one for auth, one for the scheduler, one for the deployment target, and seven more for the questions I knew I would otherwise re-open at midnight six months later.

Eleven ADRs. Zero users. Colleagues have laughed at that ratio and I get it. Writing formal decision documents for a project whose entire engineering department is one guy in the evenings looks like corporate cosplay. I'm going to argue the opposite: **that ratio is correct**, and the reasoning applies far beyond hobby projects.

## The stakeholder with amnesia

The awkward truth about solo projects: you are not one person. You are a sequence of people wearing the same face, and they don't talk to each other. Never have.

There's the me who chose the [chi router](https://github.com/go-chi/chi) over the more popular frameworks. The me who decided SQLite is the default and Postgres just an adapter behind an interface. The me who picked sessions plus TOTP. Not an identity provider. Each of those people had context: benchmarks skimmed, trade-offs weighed, constraints that were obvious *that week*.

And then there's future-me. Future-me is a genuine stakeholder, one with amnesia. He opens the repo after three months away, finds a decision he doesn't remember making, and immediately starts distrusting it. *Why is there no ORM here? Was I lazy, or was I smart?* Without a record, he cannot tell. And an engineer who distrusts his own codebase starts rewriting things that were never broken.

The economics are absurdly lopsided. An ADR written on the day of the decision costs ten minutes. Reconstructing the reasoning a year later costs an afternoon, and the reconstruction is usually wrong, because you rebuild yesterday's decision out of today's knowledge. Eleven ADRs before the project is even feature-complete are **the project's memory**, and the only memory that survives the gap between evening sessions.

## Writing it down changes what you decide

The bigger surprise was what ADRs did to the decisions themselves.

Every ADR template carries one quietly brutal section: *alternatives considered*. That is the whole trick. You cannot fill it honestly without laying the options side by side, in daylight. And in that daylight, a certain kind of decision dies: the resume-driven one. Try writing "Alternative B: the boring technology that fits the problem perfectly. Rejected because: I wanted to play with Alternative A", then signing your name under it. The template forces the question every architect should ask and rarely does: **do I want this technology, or does the problem?**

Two more effects I didn't expect:

- **Timestamped context.** An ADR records what was true and known at decision time. When the world changes (and it will), the record protects past-you from unfair judgment, and tells present-you exactly which assumption expired.
- **Deferred decisions stop haunting you.** One of my eleven ADRs decides *not to decide*: a query-codegen library that looks attractive, postponed until the hand-written SQL layer actually hurts, with a written trigger for revisiting. Before ADRs, that would have been ambient anxiety, a tab left open in my head. Now it's a documented "not yet" with an alarm attached.

There's a wry pleasure in noticing that the discipline works precisely because nobody is watching. No committee to perform for, no reviewer to impress. Just the decision and me, on paper ;)

## From hobby repo to boardroom

Now scale it up, because this is where it stops being a solo-dev quirk.

An ADR log plus one named decider is **the cheapest governance stack that exists**. No tooling. No steering committee. A folder of text files and a name. Organizations design their systems obsessively and leave the decisions about those systems undesigned. I wrote about that in [Decisions Have an Architecture Too](/blog/decisions-have-an-architecture-too/). The ADR is the smallest fix I know for that gap. It does nothing to make decisions faster. It simply makes them *exist*: a question somebody outside the room can raise, a record somebody not yet hired can inherit, a position the author can defend, and a date saying when all of it was still true.

When I want to judge the health of a team, I ask for their decision log before I read a line of their code. A living log full of honest alternatives tells me more than any maturity assessment. An empty one tells me the architecture lives in somebody's head. Heads get promoted. Heads get poached.

And there's a reason for this practice that didn't exist when the ADR format was invented. Agents read our repositories now. Code tells them *what* the system does; only decision records tell them *why*: which constraints were real, which paths were rejected and on what grounds. Decision reasoning is precisely the thing a model cannot infer from source, no matter how good it gets. My eleven small markdown files turn out to be premium context for every future agent, and every future human, working in that repo.

## An audience of one

So yes: eleven ADRs, zero users, and I'll write the twelfth tonight without a trace of embarrassment.

Over the years I have arrived at a personal rule. My side projects are where I enforce it hardest, precisely because nobody is watching. **If a decision isn't worth writing down, it was a mood.**

Moods are fine. I've shipped plenty of good code on moods. But a mood can't be reviewed, can't be inherited, and can't defend itself six months later.

Open your most important repository and try to find where its five biggest decisions are written down. Not the code that implements them, the reasoning that produced them. If it isn't in the repo then it is stored in a person. That storage medium gets promoted, poached, and eventually hands in its notice with two weeks' warning and a leaving card.

Mine live in a folder. It took ten minutes each.

---

**Updated:** 2026-08-13 10:45

**Scores:** tension=5 originality=6 personal=9 contrarian=5 substance=8 punch=8 trend=6
**Scored:** 2026-07-27 rubric=v1 model=claude-opus-5

**Tags:** Architecture Decision Records, Software Architecture, Side Projects, Engineering Practices, Documentation
