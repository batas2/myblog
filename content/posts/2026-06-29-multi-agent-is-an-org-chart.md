# Multi-Agent Is an Org Chart

*I designed a multi-agent AI system and accidentally rebuilt the org chart, failure modes included.*

Recently, as part of our AI platform work, I've been designing a multi-agent experience: a supervisor agent that understands the user's intent and routes work to specialist agents, each owning a slice of the domain, with escalation paths for the cases nobody can handle.

Somewhere around the third iteration of the target-architecture diagram I stopped and started laughing. Boxes with narrow responsibilities. A coordinator on top. Formal handoffs. Escalation paths. I had seen this diagram before: in every HR tool I've ever been onboarded into.

**I wasn't designing a system. I was designing an organization.** And the dozen-plus years I've spent watching human organizations misbehave suddenly became the most relevant architecture experience I own.

Field notes from one solution design follow. Private observations, not a paper.

## Same Primitives, Different Substrate

Strip the buzzwords and a multi-agent architecture is built from exactly the primitives an org designer uses: **roles** (specialist agents with narrow charters), **handoffs** (who passes work to whom, in what format), **escalation paths** (what happens when an agent is out of its depth), and **trust boundaries** (whose output is taken at face value and whose gets verified).

This is not a cute metaphor. The primitives are identical because the underlying problem is identical: **coordinating limited intelligences with partial context toward a shared goal.** For humans the limits are attention and working memory; for agents, the context window. Same constraint, different units.

The parallels get uncomfortably specific:

- **Context windows are span of control.** A manager can effectively lead only so many people before nuance drops on the floor. An agent can hold only so much context before it starts confidently forgetting. In both cases the failure smells the same: dropped threads, generic answers, work quietly reinvented twice.
- **Agent-to-agent contracts are… contracts.** Schemas, expectations, what "done" means. Two teams with a fuzzy interface produce meetings; two agents with a fuzzy interface produce hallucinated handoffs. The meetings are cheaper.
- **Decomposition is charter-writing.** Deciding what each agent owns is the same act as writing team charters, and it fails the same way, through overlaps (two agents both "own" the answer) and gaps (a question no agent considers theirs).

## Supervisors Are Middle Management

The supervisor pattern (one orchestrator routing everything) is multi-agent middle management. And it fails exactly the way middle management fails:

**The bottleneck.** Route every interaction through one coordinator and you've serialized your parallelism. One overloaded node, everyone else idle. Any resemblance to a manager whose calendar is the company's critical path is fully intended.

**The telephone game.** Each relay compresses. The specialist's rich answer becomes the supervisor's summary, which becomes a summary of a summary. Fidelity degrades per hop. In orgs we call it "communication overhead", in agents "context loss", and it's the same tax.

**Empire building.** Give a supervisor agent loose instructions and it starts *doing* the work instead of routing it: answering domain questions itself, badly, because delegating "feels" riskier than acting. I have met this middle manager. Several times. Some of them were prompts.

The fixes are org-design fixes, translated: push decisions down to the specialists, allow direct channels between agents that collaborate often (not everything needs to go "up"), and define escalation *criteria* instead of escalation *habits*. If your supervisor agent starts scheduling coordination meetings between the other agents, turn the computer off ;)

## Guardrails Are Governance

Every serious multi-agent design ends up with guardrails: what an agent may do autonomously, what requires human approval, what gets logged for audit. Rename these and you get a governance framework, complete with autonomy budgets, approval gates, and audit trails. The corporate déjà vu is total.

And the same tension applies. Over-govern and you build agent bureaucracy: every action awaiting sign-off, throughput dying politely, the system technically safe and practically useless. Under-govern and you're one confident hallucination away from an incident report. Organizations have been tuning this dial for a century, and the honest answer in both worlds is that the dial never stops needing tuning. You're not choosing a setting; you're accepting a job.

## The Mirror

What this exercise actually gave me, beyond a chatbot architecture:

Organizational experience turned out to be directly transferable. Clear charters beat detailed procedures; the smallest unit that can own an outcome end-to-end should own it; and you should never create a coordinator role to fix a communication problem that a better contract would fix for free.

But the transfer runs both ways, and that's the part I can't stop thinking about. When you design coordination from scratch, with no politics, no history, no feelings to spare, you would never include a layer whose only function is summarizing upward. You would never require every hand-off to pass through one node. You would never let two roles both vaguely own the same outcome, and you would never leave an escalation path undefined.

Then you look at your real org chart and ask why it's full of things you would never design. The polite version of the answer is "history". I explored the human side of sitting inside such a structure in [Technical Leader — Identity Disorder](/blog/technical-leader-identity-disorder/); designing agents is the first time I've gotten to sit on the other side of the whiteboard.

There is a Conway's law for agents, too: left unattended, your multi-agent architecture will faithfully copy your organization's communication patterns, including the dysfunctional ones, because its designers can't easily imagine anything else. We nearly shipped our org chart into the prompt. I suspect we weren't the first.

**Multi-agent design is org design with a compiler.** For the first time, our coordination theories produce stack traces instead of opinions.

So here's the question I'd leave you with: if you rebuilt your organization the way you'd architect a multi-agent system (from scratch, charters first, no history to respect), **which of your current roles would survive?**

And if the answer makes you uncomfortable: the agents are ready whenever you are.

---

**Medium:** https://medium.com/@bfrackowiak/multi-agent-is-an-org-chart-396e78ac6a22

**DevTo:** https://dev.to/bfrackowiak/multi-agent-is-an-org-chart-152n

**Scores:** tension=8 originality=8 personal=8 contrarian=7 substance=8 punch=9 trend=9
**Scored:** 2026-07-27 rubric=v1 model=claude-opus-5

**Related:** nobody-owns-the-sum, nobody-resisted, prompt-driven-architecture

**Tags:** Multi-Agent Systems, Artificial Intelligence, Organizational Design, Software Architecture, Conways Law
