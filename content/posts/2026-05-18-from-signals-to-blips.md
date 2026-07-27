# From Signals to Blips

*How a tech radar became my favorite tool for driving an AI transformation across several hundred developers.*

For the past months, as a Solution Architect, I've been an active participant in our AI transformation. Several hundred developers. Fifteen squads across four tribes. One goal: change how software gets built.

And one painfully practical problem: **how do you drive change at that scale?**

You can't attend every standup. You can't review every experiment. You can't personally convince three hundred people that a new way of working is worth their time. Whatever instrument you pick, it has to scale better than you do, and it has to survive contact with a real organization, where attention is scarce and opinions are loud.

My answer turned out to be a classic: **a tech radar.** Not the industry-wide kind you read once a year and forget, but an internal, living one: refreshed in editions, fed by real squad data. It scales beautifully, and it shows the state of the transformation in several dimensions at once. The *ring* tells you how much we trust something (Adopt, Trial, Assess, Hold), the *quadrant* tells you what kind of thing it is, the *movement* tells you where it's heading, and the *evidence* tells you who actually proved it. A radar is opinionated data. It doesn't just list what exists; it shows what we, as an organization, decided to do about it.

*This is the first post in a short series about driving an AI transformation with tools that scale. We start with the engine room: how raw signals become blips.*

## From Reports to a Score

Every week, each squad files a structured report: adoption numbers, workflows integrated, wins, learnings, blockers, a maturity self-assessment. Fifteen files. Roughly two dozen distinct concerns surface every single week.

The old me would try to read everything and keep the picture in his head. The current me sends an AI agent through all of it with a very strict contract.

First, extraction. Every report is decomposed into **atomic claims**, each tagged with squad, week, and section. Rule number one: **cite or it didn't happen.** A signal that cannot point back to a concrete cell in a concrete report does not exist.

Second, classification. Only two classes of signal survive the sweep:

- **STOPPERS**: anything that is stopping, or about to stop, a group of developers from making progress.
- **PROMOTABLES**: a working practice, skill, or integration that delivered impact and deserves to spread.

Everything else is noise *for this run*, and it gets dropped. No parking of "interesting but not actionable" items. That discipline hurts at first and pays off every week after.

Third, the score:

```
importance = multiplicity × severity × strategic_weight
```

*Multiplicity* (1–5): how many squads independently raised the same signal. *Severity* (1–3): from local inconvenience to a blocked initiative (or, for promotables, from a quality-of-life win to a genuine capability unlock). *Strategic weight* (1–3): how far the decision ripples, from one squad to an architecture-level precedent.

The multiplication is deliberate. If any factor is low, the product collapses. A concern raised by one developer shouldn't outrank a cross-squad blocker, no matter how eloquently it was written. **A signal has to score on all three dimensions to reach the top.** The result is a number between 1 and 45, and it comes with bands: above 30 goes to the architecture forum this week, the middle band waits for the next radar edition, the rest is monitored.

It works in practice. Six squads independently naming the same delivery pressure lands at 45, top of the queue. A governance gap raised by a single squad, but blocking an entire platform branch, scores low on multiplicity yet stays visible thanks to strategic weight. The loudest voice stops mattering; the math does the listening.

One thing the score is *not*: a verdict on truth. It is not effort-weighted, not ROI-weighted, and it deliberately trusts the reports without cross-checking them. It measures **signal strength, not validation status**. It tells me what to discuss first, not what is true.

## From Score to Blips

One part I consider non-negotiable: **the AI never touches the radar.**

The sweep produces a candidate list. Each candidate is matched against the existing radar and typed: a new blip, a ring move, a confirmation of something already there, or, my favorite, a contradiction, where the field evidence says an adopted practice is breaking in real life.

Validation is human. Candidates go to a stakeholder meeting with a checkbox next to every decision. Ticked means it merges into the next radar edition, and every change lands in a changelog so trends across editions stay honest. The radar grew from 42 blips to over 50 this way. Every single addition can be traced back to the squad report that started it.

AI proposes. Humans dispose.

There is a bigger idea hiding inside this machinery. The radar's score is not computed from clean, structured data. It is extracted by AI from messy human text, probabilistically, with an accepted margin of error. I believe that pattern reaches far beyond one tech radar, and it deserves an article of its own. It's the next one in this series: **[Probabilistic Metrics](/blog/probabilistic-metrics/)**.

Until then, one question. You've just seen my tool for driving change across several hundred developers. What's yours, and does it scale better than you do?

---

**Scores:** tension=5 originality=7 personal=7 contrarian=5 substance=9 punch=7 trend=8
**Scored:** 2026-07-27 rubric=v1 model=claude-opus-5

**Tags:** Tech Radar, AI Transformation, Software Architecture, Engineering Leadership, Data-Driven Decision Making
