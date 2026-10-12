# Nobody Resisted

*We planned for skeptics and got 1,372 skill files in eleven weeks. Notes on an AI adoption that went too well.*

In spring I had a chapter ready for the skeptics. The workshop plan for our AI rollout had a section called "objections", with the lines I expected to hear from senior developers: I am faster by hand, it will leak our code, I tried it in 2023 and it was useless. I had an answer for each one, written down.

I never used the chapter.

In the second week of July somebody ran a count over the repository index. Skill files, the small instruction files an agent reads before it does a task: 219 in early May, 1,372 by late July. Context files, the ones at the root of a repo: 75 to 496. Part of that jump is the search index catching up with itself. I still read the number twice.

What follows is a count, not a study. I co-lead this adoption across several hundred developers, so I am also counting my own mess.

## The problem I prepared for

Every transformation playbook I have read starts with resistance. You find champions. You run pilots. You draw the curve with the early adopters on the left and the laggards on the right, and you plan the year around moving people along it. The numbers backed this up. In the 2025 Stack Overflow survey [46% of developers said they distrust](https://survey.stackoverflow.co/2025/) the accuracy of AI tools, against 33% who trust it. So I planned for distrust.

Our quarterly health check asks every team, among other things, how working with AI feels, on a scale from one to four. In June the average came back at 2.96. The lowest team sat at 2.18 and the highest at 3.62. Two hundred and four answers. Nobody was at one.

I had prepared for a wall. There was no wall.

## What happened instead

The internal board where teams register their experiments listed 405 proofs of concept and 89 marketplace plugins by midsummer. In the testing area alone there were 69 tagged experiments, of which the working group agreed to anchor 4. About 60 of the rest were the same idea, built again in a different team. For implementation the count was five pipelines that take a ticket through to a pull request, eight variants of spec-driven development, and seven review bots.

Seven review bots. Each one reads a pull request and leaves comments, each in a different tone, none of them aware the other six exist.

![The adoption plan assumed resistance. What happened was faster, and in the other direction.](/assets/img/posts/nobody-resisted/plan-versus-what-happened.png)

**Nobody was blocking. Everybody was forking.** And the forking is the thing no chapter in my plan was about.

## Conway's law at agent speed

Why does a team build its own review bot when six already exist? Because there was no place in the organization to share one, and because building it now costs an afternoon.

That second part is new. Two years ago a review bot was a quarter of somebody's time, so before you built one you asked around, found the team that had already tried it, and usually ended up using theirs or dropping the idea. The asking was the coordination. It was slow. It was free. Now the asking takes longer than the building, so people skip the asking. Every team's agent setup ends up looking exactly like that team's boundaries. I wrote in [Multi-Agent Is an Org Chart](/blog/multi-agent-is-an-org-chart/) that an agent pipeline copies the organization that built it. The skills folder is where you can watch it happen, one directory per team.

**Duplication used to be limited by effort. Now nothing limits it.**

There is a second problem hiding in the 1,372, and research from this year puts a number on it. [SkillsBench](https://arxiv.org/abs/2602.12670) tested 84 tasks across several agents in February. Skills written by a person raised the pass rate by about 16 points on average. Skills the model wrote for itself gave no gain at all, and in some setups made things worse. ETH Zurich found [the same pattern](https://arxiv.org/abs/2602.11988) for context files: the generated ones cost 20% more tokens for slightly worse results. Most of our 1,372 were generated. So most of them do nothing. A few do harm. And every agent reads all of them, on every task.

## Consolidate, not discover

At the sixty-day workshop the group looked at the board and wrote down a verdict I did not expect in month two of anything: consolidate, not discover. Stop finding new things. Pick.

That is the opposite of what the innovation playbook says to do this early. It is also the first moment the transformation produced architecture, because consolidation is an architecture problem. Somebody has to decide which review bot is the shared one. Somebody has to decide where the domain knowledge lives, so that every team reads one copy. And somebody has to write down which practices are banned because they already hurt a team.

The radar did the last part. Its Hold ring names harms we had seen: markdown files in repos treated as the company's knowledge base, oversized pull requests approved because the reviewer was tired, code committed to prove to a manager that AI was used. Nothing enters the Adopt ring unless two teams can show it in a repository, a rule I described in [From Signals to Blips](/blog/from-signals-to-blips/). **Cite it or it did not happen.**

The part I got wrong at first was the order. I wanted to pick the standard in a meeting and then roll it out. What worked was the reverse: watch which fork three teams had already copied on their own, and make that one the shared one. **The stamp comes after the spread, never before.** It is the same lesson as [Adoption Is the Deliverable](/blog/adoption-is-the-deliverable/), and I needed to learn it a second time.

## Count forks, not pull requests

There was one metric we refused from the start: pull requests per developer with an AI co-author. We asked leadership not to measure it, because that number goes up when people commit garbage to prove a point.

Count forks instead. For each capability, how many tools exist that do the same thing: seven review bots, eight spec variants, five ticket pipelines. **That number should go down.** When it reaches one, or two for good reasons, the organization has learned to share. When it goes up, the organization is still at the stage where saying yes is cheaper than asking.

An honest caveat, because I have complained about [counting the wrong thing](/blog/data-driven-theater/) before. A falling fork count says the company learned to share. It says nothing about whether the shared bot is any good. That needs a different measurement, and I do not have it yet.

## The chapter I kept

The skeptic chapter is still in my drafts folder. I opened it last week, for the first time since spring, because the skeptics finally turned up.

They did not come with "why should I use this". They came with "which of the seven do we keep". That is a much better question, and I had nothing rehearsed for it.

---

**Scores:** tension=7 originality=7 personal=8 contrarian=7 substance=7 punch=7 trend=8
**Scored:** 2026-10-11 rubric=v1 model=claude-fable-5-1

**Tags:** AI Transformation, Organizational Design, Engineering Leadership, Artificial Intelligence, Systems Thinking
