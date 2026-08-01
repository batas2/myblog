# Grinding Dijkstra at Senior Level

*Twelve years into my career, there's a repo on my disk where I practice graph traversals like a student. Here's why that's not humiliating.*

Somewhere on my disk, between a SaaS I operate and an architecture tool I'm building, sits a small C# solution nobody was ever supposed to see. Inside: `Dijkstra.cs`. Not production code. Practice. Preparation for a big-tech interview loop, the kind with shared editors, 40-minute slots, and a rubric.

Yes. Twelve-plus years in, carrying the title *Solution Architect*, and I was grinding shortest-path algorithms in the evenings like a third-year student before finals.

I'm writing this down because almost nobody at my seniority level admits to it, and the silence creates a myth: that past some invisible line, you're beyond being tested. You're not. And the test, it turns out, is one of the most instructive systems I've ever put myself through, just not for the reasons the recruiters advertise.

## The humbling

The first thing the loop teaches you: **your title means nothing here.** "Solution Architect, twelve years of experience" compresses into one line of a CV that the interviewer skimmed four minutes before the call. The loop doesn't care who I think I am. It cares what I can demonstrate, under pressure, in 40 minutes, in a shared editor with someone watching my cursor.

And there is something bracingly *honest* about that. Inside a company, seniority accumulates a protective layer: reputation, relationships, past wins that answer questions before they're asked. The loop strips all of it. What's left is just you and a graph with weighted edges.

Algorithms, I discovered, are a language I used to speak fluently and now translate in my head. Exams, early jobs, the first competitive years: fluent. A decade-plus of architecture later, I still *read* the language perfectly well. But producing it at speed is different. You don't relearn BFS; the concept never left. You relearn *fluency*. Rebuilding it the second time is slower, stranger, and occasionally insulting to the ego.

I once wrote about the identity split this role produces ([Technical Leader — Identity Disorder](/blog/technical-leader-identity-disorder/)). Interview prep is that split, sharpened to a point: it forces you to separate "I am senior" from "I can demonstrate X under pressure in 40 minutes." The first is a story. The second is a fact. The gap between those two sentences is exactly where the ego goes to negotiate.

## The system behind the loop

Now the part where I stop feeling and start doing my actual job, which is looking at the system.

Big-tech hiring is not designed to measure your worth. It is designed to **manage their risk at scale**. A loop that processes tens of thousands of candidates optimizes ruthlessly against false positives (the bad hire who gets in) and accepts false negatives, the great engineer who gets rejected, as a tolerable loss. There are always more candidates. Rational for them. Brutal for you. Once you see that, a rejection stops being a verdict about your competence and becomes what it actually is: an artifact of someone else's risk function.

There's theater in it too, and everyone involved knows it. Candidates perform "thinking out loud" because the rubric rewards it. Interviewers calibrate against what a "strong hire" is supposed to sound like. Algorithms won as the measurement proxy not because anyone believes they're the job, but because *experience*, the thing that actually matters, cannot be assessed in an hour. Experience only shows over months. So the industry measures what fits in the slot, and quietly agrees not to mention that it's a proxy. And even the best-calibrated funnel delivers you into the same building where [the Peter Principle](https://en.wikipedia.org/wiki/Peter_principle) is patiently waiting upstairs anyway ;)

I don't say this with bitterness. I say it with the professional respect of one systems designer examining another's trade-offs. I'd probably design it similarly, and it would be similarly brutal.

## What I gained

The part nobody tells you: the grind pays dividends completely unrelated to the offer.

**The fundamentals sharpened my day job.** Complexity intuition is an architecture skill wearing a t-shirt. Estimating the blast radius of a fan-out, sensing why a graph traversal in a hot path should scare me, knowing when "we'll cache it" is a real answer. Same muscle, different dress code. A few weeks of deliberate practice tuned instincts I'd been running on autopilot for years.

**I became a different interviewer.** I've sat on the hiring side of the table for years. After feeling the pressure from the candidate's chair again (the blank second where a thing you know simply won't come out), I redesigned my own interviews. Less performance, more collaboration. Pressure reveals nerves; it does not reveal competence.

**Market calibration changed how I behave at work, in the calm way.** Knowing what you're worth outside your company removes a quiet fear from every disagreement inside it. Architecture debates stop being existential. You argue on merits, concede gracefully, and push hard when it matters, because your security no longer depends on winning the room.

## The mirror

So here's my genuinely private opinion, the one this whole post was building toward: **every senior person should run an interview loop once every few years, even if they fully intend to stay.**

Not for the offer. For the mirror.

Seniority inside one organization slowly replaces feedback with comfort, and it does it so gradually that you never notice the swap: people stop challenging you because challenging you got expensive, context does half your thinking before you walk into the room, and your reputation answers questions your skills should be answering. The loop is the one mirror nobody has adjusted to flatter you.

One question to leave you with: when was the last time your competence was tested by someone who had no reason to be polite to you?

If you have to think back more than a few years, that repo full of graph algorithms might be worth creating after all.

---

**Updated:** 2026-08-01 16:10

**Scores:** tension=8 originality=6 personal=9 contrarian=8 substance=6 punch=8 trend=2
**Scored:** 2026-07-27 rubric=v1 model=claude-opus-5

**Tags:** Technical Interviews, Career Development, Engineering Leadership, Personal Growth, Software Engineering
