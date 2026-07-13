# The Automerger

*How we taught our repositories to merge themselves, and what two years of running it taught us.*

If your team uses a branching model with more than two long-living branches, you know the ritual. A bug gets fixed on the release branch. Somebody has to remember to bring that fix back to develop, or the bug will happily reappear in the next release. Multiply that by several release branches, several teams, and several fixes per week, and you have a full-time job nobody applied for.

At EcoVadis we lived exactly that. GitFlow-style branching, multiple active branches at once (master, develop, releases in flight), and changes that had to propagate across all of them. Manual coordination was error-prone and slow, and the errors were the expensive kind: a regression returning in production because one merge was forgotten.

So we automated it. We call it the **Automerger**.

## What It Does

The Automerger is a script built with Cake, the C# build automation tool. Its job is deliberately boring:

1. Detect which branches need to be synchronized.
2. Create a temporary branch and perform the merge there.
3. Validate the result by building the code and running the tests.
4. If everything is green, push the merge.
5. If there is a conflict or a failed build, stop and notify the developers responsible.

The key design decision: **the tool only involves humans when a human is actually needed.** A clean merge that builds and passes tests does not deserve anyone's attention. A conflict does, and then the notification goes to the right team, not to everyone (alert fatigue is real).

Although the tool is written in C#, there is nothing .NET-specific about the workflow. It merges JavaScript and Java repositories just as happily; the validation step simply runs whatever build and test commands the repository defines. It plugs into our CI/CD platform (Azure DevOps in our case) and notifications go wherever the team lives, Slack included.

## Convention over Configuration

The part I like most: the Automerger needs almost no configuration. It discovers branches and orders them by naming convention. If your branches follow a sensible pattern (and with GitFlow they already do), the tool can infer the merge direction on its own: fixes flow from the oldest release branch, through newer ones, into develop.

One honest requirement comes with that: the workflow relies on *real merges*, not cherry-picks. Cherry-picked commits look different to git, and the tool cannot reason about them. Convention over configuration works only if you keep the convention.

## Two Years Later

After two years of running the Automerger every 3–4 hours during working days, the numbers settled around **90% of merges completing fully autonomously**: no conflicts, builds green, nobody interrupted. Nine out of ten of those "remember to merge back" tasks simply stopped existing.

The remaining 10% is exactly where humans belong: genuine conflicts, where two branches disagree about the same lines and somebody has to decide.

The lesson generalizes beyond git. Find the task that is repetitive, mechanical, and only occasionally requires judgment. Automate the mechanical majority, and route the judgment cases to people with full context attached. Developer time is too expensive to spend on merges that a script can prove safe.

What is the "merge back to develop" of your team, the recurring chore everyone does by hand because nobody sat down for a week to kill it?

---

**Medium:** https://medium.com/ecovadis-engineering/the-automerger-216d1b060396

**Tags:** Developer Tools, Automation, Engineering Practices, DevOps, Software Engineering
