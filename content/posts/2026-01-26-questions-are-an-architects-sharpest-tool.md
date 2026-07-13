# Questions Are an Architect's Sharpest Tool

*The highest-leverage artifact I produce is not a diagram, not a decision record, not a slide deck. It's a list of questions.*

The best architecture review I can remember produced no decisions, no action items, and one question. Someone looked at a beautifully complete design for an aggregation service and asked: "Who owns this number after it's published?" Silence. Two weeks later the design was different, and better, in ways no amount of diagram polishing would have achieved.

I've been collecting that silence ever since.

Early in my career I believed seniority meant having answers: fast, confident, preferably with a diagram. A dozen years in, I measure my weeks differently: by the quality of the questions I managed to ask before the answers got expensive. Private observation, not science, as usual. But this one I'd defend in any review.

## The question list is a deliverable

Every solution design I ship travels with a companion artifact: an open-questions file. Beyond that, I maintain separate documents with titles like "architecture questions to product". They are versioned, statused (open, answered, overtaken by events), and reviewed with the same seriousness as any diagram. Colleagues occasionally find this odd. Aren't architects paid for answers?

Here's the asymmetry that convinced me otherwise. **Answers age badly.** An answer encodes the constraints of the day it was written: the team's size, the vendor's pricing, the traffic profile, what the organization believed that quarter. Constraints rot silently, but the answer keeps getting repeated, confidently, like a cached value nobody thought to invalidate. Some of the most dangerous sentences in engineering start with "we already decided that."

Good questions do the opposite: **they compound.** "Who owns this after go-live?" produces fresh truth every single time you re-ask it. "What happens when these two sources disagree?" outlives reorgs, migrations, and three generations of diagrams. An answer is a snapshot. A good question is a probe you can re-run.

That's why the question list deserves version control. It is the only artifact I produce whose value reliably *grows* with time.

## Two kinds of questions

Now the craft, because not everything with a question mark is a tool.

The first kind **exposes hidden assumptions**. "What must be true for this design to work?" "Who pays when this fails at 2 a.m.?" "Which of these arrows is a wish and which is a contract?" These questions are cheap before the design is approved and brutal after go-live. Their entire value is in the timing. They tend to be short, slightly uncomfortable, and impossible to answer with a framework name.

The second kind **performs seniority**. The gotcha delivered for the audience. The technology flex ("have you considered Kafka?") asked not to learn but to be seen knowing Kafka exists. The fifteen-minute "question" that is actually a speech with rising intonation. This kind produces no design changes, only defensive meetings.

My test is simple: would I still ask this question in a one-on-one, with no witnesses? If yes, it's a probe. If it only makes sense with spectators, it's theater. I've written before about how much of corporate life already is ([Data-Driven Theater](/blog/data-driven-theater/)).

One more piece of craft: a question without an owner and a check-back date is not a tool, it's a sigh. "Someone should clarify this" has never once, in my experience, resulted in someone clarifying anything.

## Open questions as an honesty mechanism

The deepest value of the question list is what it does to trust.

Every solution design of mine ends with an "Open Questions" section: the things I don't know, stated before someone else discovers them for me. It felt exposing the first few times. It turned out to be disarming. A review of a "complete" design is a hunt: reviewers win by finding holes. A review of a design with three declared holes is a search party: the reviewers join you, because you've already conceded the interesting part (that holes exist) and named where you think they are.

Declared uncertainty, I keep relearning, builds more credibility than performed certainty ever did.

Years ago, in [Technical Leader — Identity Disorder](https://medium.com/ecovadis-engineering/technical-leader-identity-disorder-c5bfd797f871), I leaned on Socrates while untangling my own role. I've since stopped treating "I know that I know nothing" as philosophical decoration. It's a working method, and the open-questions file is its production implementation. Confidence is not the absence of open questions. **Confidence is knowing exactly which questions are open.**

Which is why an *empty* Open Questions section in a big design is, for me, a red flag with a siren attached. If you can't name three things you don't know about your own architecture, that's not completeness. That's the first open question.

## Try it

A small experiment for your next design document. Add an "Open Questions" section with at least three honest entries, each with an owner and a date to check back. Then watch what the review becomes.

And a question to leave you with, naturally. What's the best question anyone ever asked about your design, the one that stung for a second and then saved you a quarter of rework? That's the sharpest tool in our trade. Collect those. Sharpen yours.

---

**Tags:** Software Architecture, Solution Design, Critical Thinking, Engineering Leadership, Communication
