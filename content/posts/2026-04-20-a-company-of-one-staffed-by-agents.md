# A Company of One, Staffed by Agents

*Four shipping products, one person, and a field report on what AI actually changes about building software.*

On a random Tuesday evening, my backlog looks like this: a database migration waiting for review in a multi-tenant Laravel SaaS, a release candidate for a native Android app, a pricing change that touches two marketing sites, and an integration test run against Poland's mandatory e-invoicing API.

The engineering department responsible for all of this is me. After hours. I also have a day job as a Solution Architect.

A few years ago this portfolio would have required a small software house: a couple of backend developers, a mobile developer, a tester, and somebody to herd them. Today it's one architect and a fleet of AI agents. What follows is a field report, not a victory lap. As usual, the failure modes turned out to be the most interesting part of the experiment.

## The Portfolio That Shouldn't Fit in One Head

The product is **Desta**, an invoicing platform for the Polish market: a multi-tenant Laravel web application, a native Android app in Kotlin and Jetpack Compose, and two marketing sites for two different audiences. Under the hood sit the integrations nobody builds for fun: KSeF, Poland's mandatory e-invoicing system, where the XML is the legal invoice and the PDF is legally demoted to a "visualization"; a SOAP-based government registry lookup for company data; and JPK tax file generation. Roughly 990 automated tests hold the whole thing together.

One thing I had to learn the hard way: the constraint was never typing speed. **The real bottleneck of a company of one is the number of decisions a single head can hold per day.** Which schema change is safe. What happens to a finalized invoice when the law changes. Which release goes first. Code was never the scarce resource. Judgment is.

So "operating" the company means almost no typing at all. It means writing specifications, reviewing diffs, and verifying behavior. In [Stop Calling It Legacy Code](https://medium.com/@bfrackowiak/stop-calling-it-legacy-code-02308db28cb2) I argued we're moving from engineers who write logic to *operators* who express intent and judge results. Desta is me testing that thesis with my own evenings and my own money.

## The Operating Model

The setup that makes this survivable has three parts, and none of them is a clever prompt.

**Context files as boot loaders.** Every sub-project carries its own AI context file: a maintained, code-verified document that tells an agent what the system is, which invariants are non-negotiable, and where the truth lives. One big-picture file routes between them. An agent lands in the polyrepo and navigates it like a well-onboarded hire, minus the three months of onboarding. This is the Knowledge Container idea from the legacy-code article, except now it's not a metaphor; it's infrastructure.

**Specs as prompts, versioned like code.** Features start as written specifications that double as agent instructions. When the output is wrong, I don't argue in a chat window. I fix the spec file and rerun. The spec is the program; the conversation is just its execution log.

**Tests as the trust boundary.** The ~990 tests are not there because I'm diligent. They are there because agents don't get faith; they get assertions. Every agent-generated change lands against a mechanical safety net that doesn't care how confident the model sounded.

## What Breaks First

Now the honest part.

**Review capacity breaks before generation capacity.** Generating code is effectively free now; my evening attention is not. The bottleneck moved exactly where our transformation data at the day job said it would. I wrote about that in [From Signals to Blips](/blog/from-signals-to-blips/). A company of one doesn't run out of code. It runs out of reviewer.

**Trust calibration is a skill nobody teaches.** Which diffs deserve line-by-line reading and which deserve test-suite faith? I've settled on a simple rule: anything touching migrations or money gets my eyes on every line. Everything else gets the net. I still get it wrong in both directions, over-reading trivia and under-reading a "trivial" change that wasn't. My most instructive incident so far: an agent that made a red test green by weakening the assertion. The tests guard the code, but somebody still has to guard the tests.

**Domain decisions don't compress.** Tax rules, pricing boundaries, what a correction invoice may reference. These take exactly as long as they took in 2020, because the hard part is understanding consequences, not producing code. **AI compresses everything except responsibility.**

## The Moat Is Not the Code

My conclusion after this experiment is uncomfortable for people selling code and comforting for people who know things: one architect plus agents is now a viable software company, for a *narrow* domain.

The code was never the moat; it's cheaper than ever. The moat is knowing which invoice field the law wants left blank, which government API header actually carries the session, which pricing edge case will generate a support email at 7 a.m. Domain knowledge, encoded into context files and tests, is the entire company. The agents are just very fast hands.

So here's my question for you: the "I don't have a team" excuse for not building that product you keep describing at lunch? It just expired. What's the new excuse? ;)

---

**Tags:** AI Agents, Solopreneurship, SaaS, Software Development, Artificial Intelligence
