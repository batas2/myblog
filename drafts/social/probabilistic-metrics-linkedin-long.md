--- THE LONG CUT. Kept, not for posting. The short version in
--- probabilistic-metrics-linkedin.md is the one to use: this one argues the
--- whole case in the feed, which leaves nobody a reason to click through.
--- Useful if the post ever becomes a LinkedIn article, where length is the point.
--- clicks come back as utm_source=linkedin in the Acquisition tab; without the
--- tag below LinkedIn's lnkd.in wrapper would report them as direct traffic.
--- plain text on purpose: LinkedIn renders no markdown, so nothing below is
--- formatted. Copy from the first line of the body to the last hashtag.
--- deliberately does not open on "I read 15 squad reports a month" — that was
--- the opener of the Bottleneck Moved post and repeating it would read as a
--- template. The fact still appears, further down, where it is evidence
--- rather than a hook.

The numbers that steer our architecture attention have a known error rate. We accept it on purpose.

They are not computed from clean events. An agent reads the weekly squad reports, breaks them into atomic claims, and scores each one. Nobody built a data pipeline for this. The sensor is a versioned prompt pointed at documents that already existed.

Any single extraction can be wrong. That is not a bug waiting to be fixed. That is the trade: certainty about each observation, exchanged for coverage of the whole organization.

Classical metrics demand clean inputs, and that cleanliness is a tax you pay three times. Once in engineering, because somebody builds and maintains the pipeline. Once in politics, because somebody has to win the meeting where "active user" gets defined. And once in coverage, because you only ever measure what you predicted, a year in advance, would be worth measuring.

What you get for it is an organization precisely informed about a thin slice of reality and blind everywhere else. The blind spots are not random. They are the messy human parts: friction, workarounds, practices spreading between teams, morale.

The trade only stays sane under rules. These are the ones I learned the hard way, running this across 15 squad reports a month.

Never trust a single extraction. Trust convergence. One squad's report can be misread. Five squads independently describing the same friction is a fact about the organization, whatever the error rate per reading. Redundancy replaces precision.

Every number cites its source. Ours is cite or it didn't happen: each scored signal points back to the exact report, week and section it came from. Probabilism without traceability is just noise with confidence.

A human gate before consequences. These numbers route attention. They must never issue verdicts.

The prompt is the preprocessing, so it is versioned like code and anyone can read it. Hide the prompt and you have rebuilt the same theater with better lighting.

The change I underestimated most is that metrics became disposable. When a new one costs one prompt instead of one quarter, you try ten and throw eight away, and your metric portfolio stops being an investment you defend in meetings.

None of it would survive an audit, and that is fine. A compass is not an accounting system. Use it to decide where to look, then instrument what you found properly.

The old excuse for not measuring the human side of your system, too messy, too expensive, no pipeline, has quietly expired.

The failure modes move, though, they do not vanish. People used to game the data cleaning. Now they will game the extraction. If you have watched that happen already, I would rather hear about it than be agreed with.

https://bfrackowiak.pl/blog/probabilistic-metrics/?utm_source=linkedin&utm_medium=social&utm_campaign=probabilistic-metrics

#Metrics #AITransformation #EngineeringLeadership #DataDrivenDecisionMaking
