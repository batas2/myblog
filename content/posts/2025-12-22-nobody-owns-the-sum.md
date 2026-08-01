# Nobody Owns the Sum

*Every squad owns its piece. The customer buys the whole. Guess which one has no owner.*

Some time ago I sat in a meeting where somebody asked a simple question: **"Who owns the aggregated risk score?"**

Silence. Then everyone answered at once, each about their own piece. One squad owned the data ingestion. Another owned one of the sub-scores feeding the aggregate. A third owned the screen where the final number is displayed to the customer. The number itself (the thing customers actually look at, the thing sales sells and support gets called about) was owned by nobody.

It took three meetings to establish that. Not because anyone was hiding. Everyone genuinely believed someone else had it.

What follows is a pattern I've seen too many times to ignore.

## The org chart produces parts

We assign ownership by component, because components map neatly onto teams. A squad gets a service, a database, a screen. Clean boundaries, clear accountability, everyone can draw their box. So far, so good.

But the most valuable outcomes an organization produces are not components. They are **sums**: the aggregated score, the end-to-end response time, the coherence of the user experience, the total cloud bill. A sum, by definition, crosses boxes. And whatever crosses boxes on an org chart falls between them.

[Conway's law](https://en.wikipedia.org/wiki/Conway%27s_law) is usually quoted about system design: your architecture will mirror your communication structure. There's an ownership corollary that gets much less airtime: **an org chart can produce parts, but it cannot produce wholes.** Wholes need an owner that the chart does not naturally generate. Someone has to create that role on purpose.

Nobody plans an orphan. An orphan is simply what's left over after everyone finishes claiming what's theirs.

## How to spot one

Orphaned aggregates don't announce themselves. They surface as symptoms, and every symptom looks like something else:

- The ping-pong ticket. A cross-squad bug bounces between teams, each proving, correctly, that their part behaves as specified. Every part worked; the sum was wrong. The ticket ages until a frustrated customer escalates it past all of you.
- The decision that waits months. Changing a whole requires a yes from everyone who owns a part. A yes from everyone is, in practice, a yes from no one. So the improvement idea circles the organization like luggage nobody claims.
- The invisible roadmap item. Aggregate improvements appear on no backlog, because no backlog hosts them. Each squad's roadmap is full, full of parts.
- The human failsafe. The only owner is a veteran who remembers how the whole thing fits together. That's memory rather than ownership, and it resigns when they do.

Recognize two of these and you have an orphan.

## The architect's move

Where I've landed on what a solution architect should do about it is less heroic than you might hope.

Name the orphan out loud. This sounds trivial and isn't. As long as the aggregate has no name, its non-ownership is invisible; there is literally nothing to point at. The moment you name it ("the overall score", "the end-to-end latency budget", "the network aggregate") the question *who owns this?* stops being unaskable and becomes merely awkward. Awkward is progress.

Then force an explicit decision. Not make it. Force it. The architect's job is to put the organization in a position where *not deciding* is the visible, embarrassing option. Getting from "we should discuss this" to a named owner is a craft of its own, and I wrote about that machinery separately in [Decisions Have an Architecture Too](/blog/decisions-have-an-architecture-too/).

Sometimes the honest answer to that decision is a role nobody has yet. Occasionally an existing squad's charter genuinely grows to cover the sum. More often you end up creating something that didn't exist before: a capability owner, a small platform team, a named service with a named human. Read that as the org finally matching what it sells.

And one warning from experience. The default answer in the room will be: *"the architect can own it."* Refuse politely. Architecture must not become the attic where the organization stores what it doesn't want to decide about.

Components get owners; sums get hopes. The whole game is converting one hope at a time into an owner.

## Try it on your own system

Here's a 15-minute exercise that costs nothing. Walk your value stream end to end, from the first customer click to the last invoice, and count the things that *everyone depends on and no one owns*. The aggregate score. The overall latency. The consistency of terminology across screens. The total cost of the pipeline.

My prediction: you'll find at least three. Every organization I've looked at has them, because org charts are made of boxes and value is made of sums.

I do that walk now before I draw a single box on a new engagement, which is a habit I picked up the expensive way, by accepting a couple of these orphans myself and finding out that the flattery wears off in about a week while the babysitting does not. The orphans are always there. What changed is that I stopped being surprised by them, and started naming them in the first week instead of the third meeting ;)

---

**Updated:** 2026-08-01 16:10

**Scores:** tension=7 originality=7 personal=8 contrarian=5 substance=8 punch=8 trend=4
**Scored:** 2026-07-27 rubric=v1 model=claude-opus-5

**Tags:** Software Architecture, Organizational Design, Conways Law, Engineering Leadership, Ownership
