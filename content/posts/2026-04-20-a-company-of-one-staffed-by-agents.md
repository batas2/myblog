# A Company of One, Staffed by Agents

*Four shipping products, one person, and a field report on what AI actually changes about building software.*

On a random Tuesday evening, my backlog looks like this: a database migration waiting for review in a multi-tenant Laravel SaaS, a release candidate for a native Android app, a pricing change that touches two marketing sites, and an integration test run against Poland's mandatory e-invoicing API.

The engineering department responsible for all of this is me, after hours, because I also have a day job as a Solution Architect.

A few years ago this portfolio would have required a small software house: a couple of backend developers, a mobile developer, a tester, and somebody to coordinate them. Today it's one architect and a group of AI agents. I don't want to present it as a success story, because the things that broke turned out to be the most interesting part of the experiment.

## The portfolio that shouldn't fit in one head

The product is Desta, an invoicing platform for the Polish market: a multi-tenant Laravel web application, a native Android app in Kotlin and Jetpack Compose, and two marketing sites for two different audiences. Below that are the integrations nobody builds for fun. There is [KSeF](https://www.podatki.gov.pl/ksef/), Poland's mandatory e-invoicing system, where the XML is the legal invoice and the PDF is legally only a "visualization". There is a SOAP-based government registry lookup for company data, and there is JPK tax file generation. Roughly 990 automated tests hold the whole thing together.

One thing I had to learn the hard way: the constraint was never typing speed. There was always enough code. **The bottleneck of a company of one is the number of decisions one head can hold per day**: which schema change is safe, what happens to a finalized invoice when the law changes, which release goes first.

So "operating" the company means writing specifications, reviewing diffs and verifying behavior, with almost no typing at all. In [Stop Calling It Legacy Code](https://medium.com/@bfrackowiak/stop-calling-it-legacy-code-02308db28cb2) I argued we're moving from engineers who write logic to *operators* who express intent and judge results. Desta is me testing that thesis with my own evenings and my own money.

## The operating model

The setup that makes this possible has three parts, and none of them is a clever prompt.

**Context files as boot loaders.** Every sub-project carries its own AI context file: a maintained, code-verified document that tells an agent what the system is, which invariants cannot be broken, and where the source of truth is. One top-level file routes between them. An agent that enters the polyrepo finds its way like a well-onboarded new colleague, only without the three months of onboarding. This is the Knowledge Container idea from the legacy-code article. Here it stopped being a metaphor, because I maintain it like infrastructure.

**Specs as prompts, versioned like code.** Features start as written specifications that are at the same time agent instructions. When the output is wrong, I don't argue in a chat window. I fix the spec file and run it again. For me the spec is the program and the conversation is only its execution log.

**Tests as the trust boundary.** The ~990 tests are not there because I'm so diligent. They are there because I don't trust an agent on its word, I give it assertions. Every agent-generated change has to pass a mechanical safety net that doesn't care how confident the model sounded.

## What breaks first

Now the part that did not go so well.

Review capacity breaks before generation capacity. Generating code is practically free now, and my attention in the evening is not. So what a company of one runs out of is the reviewer. The bottleneck moved exactly where our transformation data at the day job said it would, and I wrote about that in [From Signals to Blips](/blog/from-signals-to-blips/).

Nobody teaches how much to trust a diff. Which ones need to be read line by line, and for which is the test suite enough? I use a simple rule: anything that touches migrations or money I read line by line, and everything else goes through the tests only. I still get it wrong in both directions. I read trivial things too carefully, and I read too quickly a "trivial" change that was not trivial at all. The incident I learned the most from was an agent that made a red test green by weakening the assertion. So the tests guard the code, but somebody still has to guard the tests.

And domain decisions don't get faster at all: tax rules, pricing boundaries, what a correction invoice may reference. They take exactly as long as they took in 2020, because the hard part there is to understand the consequences and the code was never the hard part. AI compresses everything except responsibility.

## The moat is not the code

My conclusion after this experiment is not comfortable for people who sell code, and it is good news for people who know a domain: one architect plus agents is now a viable software company, for a *narrow* domain.

The code was never the moat, and now it's cheaper than ever. The moat is to know which invoice field the law wants left blank, which government API header carries the session, and which pricing edge case will generate a support email at 7 a.m. This domain knowledge, written down in context files and tests, is in practice the whole company, and the agents are only very fast hands.

It also means that "I don't have a team" is no longer a good excuse for the product you keep describing at lunch. I say that as somebody who used this excuse for years and liked it. The question that replaced it is less comfortable: which domain do you know so well that writing it down in context files and tests would be worth somebody's money? To find my own answer took me longer than to build any of this ;)

---

**Updated:** 2026-11-14 12:34

**Scores:** tension=8 originality=6 personal=9 contrarian=7 substance=8 punch=9 trend=9
**Scored:** 2026-07-27 rubric=v1 model=claude-opus-5

**Tags:** AI Agents, Solopreneurship, SaaS, Software Development, Artificial Intelligence
