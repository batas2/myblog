# Data-Driven Theater

*Everyone wants decisions backed by data. Almost nobody asks what the data went through to get there.*

Before we start, a fair warning. What follows is a light reflection: a private opinion built entirely on private observations. There is zero scientific literature behind it. No studies, no citations, no methodology section. Just years of sitting in meeting rooms and paying attention. If that doesn't sound rigorous enough for you, good catch. That's exactly the kind of honesty about data sources I wish I saw more often.

I have lost count of how many meetings I've attended that were officially *data-driven*. There was a deck. There were charts. At some point somebody said "the data clearly shows." And yet, walking out, I couldn't shake the feeling that I had just watched a performance, not a decision.

> "There are three kinds of lies: lies, damned lies, and statistics."

Mark Twain popularized that line and attributed it to Benjamin Disraeli. Historians still can't confirm Disraeli ever said it. I love that detail: the most famous quote about misleading statistics ships with a broken attribution. Even the quote about data lying is a matter of interpretation.

## Same Words, Different Worlds

Here is my core observation: **making decisions based on data makes sense only when the organization speaks the same language, built on the same concepts.**

Take something as innocent as "active user." For the product team it's anyone who logged in this month. For sales it's a paying account. For support it's someone who opened a ticket and is still breathing. Now put all three in one room, each armed with a chart about "our active users," and enjoy the show. Everybody is right. Everybody is talking about a different universe.

As a solution architect, I spend a large part of my life on contracts between systems. We version APIs, we validate schemas, we break builds when a field changes its meaning. And then we walk into a meeting where two departments compare numbers built on definitions that were never aligned. These are **semantic contracts between people that nobody ever wrote down**. Being data-driven is empty if those contracts were never seriously considered. The dashboard is just the visible tip of an agreement that doesn't exist.

## The Preprocessing Trap

> "If you torture the data long enough, it will confess to anything."

That one is usually pinned on economist Ronald Coase, also in a paraphrased form, because apparently we can't even quote people about data accurately.

Here's the uncomfortable part: data is surprisingly easy to manipulate, and you rarely need to lie in the data itself. The comfortable place to cheat is earlier, in the preprocessing phase. Which rows were dropped as "invalid"? Which period was chosen as "representative"? Which outliers were "obviously" errors? Every one of those choices is invisible in the final chart.

That's why I believe **explaining how a dataset was cleaned and prepared is often exactly as important as the conclusions drawn from it**. The conclusions are the last five percent of the journey. Everything that shaped the answer happened before.

And there are two symmetric ways to fail here:

- **Conclusions drawn from raw data** give you a picture distorted by outliers. One huge client, one migration script, one QA account with ten thousand test orders, and your "average" is science fiction.
- **Conclusions drawn from over-cleaned data** give you optimization toward a thesis. Scrub away every inconvenient observation as an "anomaly," and the data will happily confirm whatever you believed before you opened the file.

Between those two extremes lives an honest analysis. You'll recognize it by one feature: it comes with a changelog.

## It's Rarely the Conclusions

In organizations (I'm deliberately not saying *corporations*, because this has nothing to do with company size) the problem I keep seeing is almost never the inference step. People are genuinely good at drawing conclusions. Give a smart group a dataset and they will reason about it just fine.

The problem sits earlier and deeper: **the method of collecting the data, and the absence of a shared language to describe it.** We argue passionately about what the numbers mean while quietly disagreeing, without knowing it, about what the numbers *are*. Calling that process data-driven doesn't fix it. It just gives the disagreement better slides.

## Unless…

Of course, there is one scenario where none of this matters.

If your actual goal is not a decision but an appearance, walking into the meeting as *the prepared one* and radiating competence from behind a wall of charts, then yes, data helps enormously. A confident slide with big numbers is a wonderful prop.

But it works under exactly one condition: your audience must not be competent enough to ask about definitions and preprocessing. And trust me, that condition is satisfied more often than we'd like to admit ;) If you're wondering how those audiences end up in those rooms, [The Peter Principle](https://en.wikipedia.org/wiki/Peter_principle) has been explaining it since 1969.

## A Small Experiment

So here's my suggestion for your next data-driven meeting. Don't challenge the conclusions. That's playing the game on the presenter's terms. Ask two questions instead:

1. *"How exactly do we define this metric, and does everyone here use the same definition?"*
2. *"What was removed or transformed during cleaning, and why?"*

Then watch the room.

If the answers come easily, congratulations: you're in one of those rare places where data-driven actually means something. If what you get is a pause, a nervous smile, and "let's take it offline"…

Well. Enjoy the theater. You're the audience.

---

**Scores:** tension=8 originality=6 personal=4 contrarian=8 substance=6 punch=8 trend=4
**Scored:** 2026-07-27 rubric=v1 model=claude-opus-5

**Tags:** Data-Driven Decision Making, Data Quality, Organizational Culture, Engineering Leadership, Software Architecture
