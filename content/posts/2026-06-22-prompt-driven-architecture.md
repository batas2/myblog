# Prompt-Driven Architecture

*Part three of the "tools that scale" series: what happens when the daily practice of a Solution Architect starts running on AI.*

Every active architecture workstream I run (a risk aggregation design, a multi-agent assistant, a gnarly integration decision) has the same file at its root: `prompt.md`. It sits next to the diagrams and the decision records, and it outranks both. That file is not a note. It is the workstream's engine.

In [From Signals to Blips](/blog/from-signals-to-blips/) I described how an AI sweep of squad reports feeds our tech radar, and in [Probabilistic Metrics](/blog/probabilistic-metrics/) what that does to measurement. This one is more personal: how my everyday deliverables became prompt-driven, from solution designs through decision documents to diagrams.

## Every workstream carries its prompt

Here is the practice, concretely. A solution design no longer starts in a document editor. It starts in `prompt.md`: the role, the task, the constraints, and pointers to context such as relevant decision records, discovery notes, and the previous design it supersedes. In skeleton form:

```
role:        …
task:        …
constraints: …
context:     decision records · discovery notes · the design this supersedes
``` Diagrams have their own prompt file that describes what the drawing must answer and for whom. The weekly signal sweep is a prompt applied to 15 squad reports. Even decision documents start from a prompt that knows which decision framework we use and who holds which role in it.

These prompts are **versioned like code, diffed like code, and re-run like builds**. When the output is wrong, I usually don't edit the output. I fix the prompt or the context and regenerate. The consequence took a while to sink in: the conversation with the model is scaffolding, the generated document is a build artifact, and the thing worth reviewing, improving, and keeping is the prompt plus its context.

The chat is ephemeral. **The prompt is the asset.**

## The context repository

A prompt is only half of the machine. The other half is what it reads: a context repository (decision records, diagrams-as-code, discovery documents, meeting notes) organized *for agents to read*. Predictable folder structure. One concern per file. Explicit links instead of tribal knowledge. A short index that tells a fresh agent (or a fresh human, it turns out) where truth lives and in what order to load it.

Three years ago, in [Stop Calling It Legacy Code](https://medium.com/@bfrackowiak/stop-calling-it-legacy-code-02308db28cb2), I argued that a codebase is really a Knowledge Container: structured knowledge waiting for AI to read and act on it. This is that thesis, applied deliberately to the architecture practice itself. I now write documentation the way I used to write integration tests: knowing a machine will consume it, and that its quality directly determines the machine's output quality.

That changed one habit above all: when an agent produces nonsense, my first question is no longer "what's wrong with the model?" but "what's stale in the container?"

## What I delegate, and what I never will

What delegates well, after a year of trying: first drafts of solution designs, sweeps across dozens of documents, diagram generation from a described intent, gap analysis of a design against a question checklist, consistency checks between artifacts that humans stopped cross-reading years ago. The pattern is clear. **AI compresses everything that is reading, restructuring, or drafting.**

What never leaves the human: the decision. Our decision framework gives every architecture decision exactly one owner, and that owner is never a model. The agent can rank options, argue trade-offs, even draft the decision record, but the moment of commitment, the one someone must later defend in front of other humans, stays human. And so does the meeting where a design earns trust. People, it turns out, still buy architecture from people.

## Where it burned me

An honesty section, because the glossy version of this article would be useless.

A generated context diagram once confidently included a component we had decommissioned a quarter earlier. The model didn't lie. My container did: the old decision record was still there, with nothing marking it superseded. Another time, a drafted design proposed a clean, plausible integration path between two systems that have never exchanged a byte. *Plausible* is the operative word and the real danger: **wrong drafts no longer look wrong.** Pre-AI, a bad document signaled itself with gaps and hand-waving. Now it arrives fully formed, well-written, and quietly fictional.

The countermeasures are unglamorous: every generated claim must cite the source document it came from; decision records get explicit superseded-by markers the day they die; and nothing leaves the workstream folder without a human pass. If that sounds like the discipline I keep demanding from data pipelines (documented preprocessing, cited sources, a human gate), it is. Same theater risk, same defense.

## The operator

In the Legacy Code article I predicted engineers would become Operators: people who express intent, mine their knowledge containers, and judge results instead of typing every line. I just didn't expect the prediction to catch *me* first.

I author less than I used to. I operate a small system of prompts and context that produces first versions of nearly everything, and I spend the recovered hours where they count for more: decisions, conversations, and asking better questions of both humans and machines.

So here is my closing challenge. Imagine writing your practice's `prompt.md` tomorrow, the file that would let a machine draft your typical week's work. Could you? Whatever you cannot yet put into that file is the part of your own job you haven't fully understood. That gap is worth more attention than any tool.

---

**Updated:** 2026-08-01 16:10

**Scores:** tension=7 originality=6 personal=8 contrarian=6 substance=8 punch=8 trend=9
**Scored:** 2026-07-27 rubric=v1 model=claude-opus-5

**Tags:** AI Transformation, Software Architecture, Prompt Engineering, Knowledge Management, Engineering Leadership
