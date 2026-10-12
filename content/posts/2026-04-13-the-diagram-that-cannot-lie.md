# The Diagram That Cannot Lie

*Why I'm building an architecture modeling tool where the diagram is physically incapable of drifting from the code.*

`target-architecture-FINAL-v3-updated.drawio`. Every shared drive I've been given access to contains a file with roughly that name, sitting in a folder full of architecture diagrams. Some are beautiful. Some took days of somebody's life. And every one of them is lying to you. Right now.

I can say this with confidence. I have drawn hundreds of them. As a Solution Architect, diagrams are supposedly my trade. And almost every diagram I've ever produced started lying within a quarter of being finished: without malice, the way milk goes off.

## Death by export

Here is the lifecycle of a typical architecture diagram. It is born in a meeting, where it is genuinely useful. It gets polished, exported to PNG, pasted into a wiki page. Then it dies. Not *wrong* yet, but dying, because the system it describes keeps moving and the picture does not.

draw.io, Lucidchart, Enterprise Architect, and every in-house viewer somebody built once and left running. Different price tags, same disease: **the model and the truth live in two different places.** Two sources, one guaranteed divergence. The only question is how many sprints it takes.

Reverse-engineering tools attack from the other side. Read the code, generate the picture. Honest, but mute. You can look at the model; you cannot *decide* anything into it. A one-way street never closes the feedback loop.

Run this audit in your own team: find the diagram labeled "current state" and ask when it last matched the current state. I've written elsewhere that [diagrams are conversations, not documentation](/blog/diagrams-are-conversations-not-documentation/): their value is consumed in the meeting where they're drawn. This article is about the other half of that thought: what would it take for a diagram to *be* documentation without rotting?

## The code is the model

My answer is a side project I now call **[Stemma](/stemma/)**. It was Verso when this essay first went out. The name changed, the bet below did not. So consider this both a design essay and a confession of bias ;)

The core bet: there is no database. None. The architecture model is stored as plain C# records in Git. Bounded contexts, containers, data flows, ownership, risks, decisions. When you open a workspace, the model lives in memory. Then you edit something on the canvas. Rename a container, redraw a dependency, re-parent a module. The tool rewrites the actual `.cs` files using [Roslyn](https://github.com/dotnet/roslyn). Not code generation. Code *editing*.

And one rule overrides everything else in the project: **round-trip fidelity.** Any edit made in the UI must produce a git diff that contains only the intended change. Rename one element and the diff shows one identifier changed: no reformatting, no reshuffled members, no re-indented block three files away, and no collateral noise for a reviewer to wade through before finding the change. If that rule ever breaks, the tool is broken. Full stop. Fidelity is the only thing that makes the loop worth betting on.

Layout lives in a small committed sidecar file, separate from the model: positions, colours, which elements each view shows, and the zoom somebody left it at. Content and presentation, finally divorced: the separation we've preached for documents for thirty years and somehow never applied to diagrams.

The first name came from bookbinding: the *verso* is the reverse side of a page, and the canvas was the verso of the code. The name it carries now comes from philology. A *stemma codicum* is the family tree scholars draw for a manuscript, every surviving copy traced back to one archetype, and that is the shape of the thing: two committed files, every view descended from them. Either way, a diagram cannot drift from the code when the diagram **is** the code.

## Architecture review becomes a pull request

Storing the model as source sounds like a technicality. It quietly changes who architecture belongs to.

An architecture change arrives as a pull request: diffable, commentable, blameable, revertable. That's governance for free, running on muscles every developer already has. No new committee. No new tool to police. And you get `git log` for your architecture. You can finally ask who moved this capability out of that context, when, and in which commit. I have wanted that in every architecture discussion of my career and never once had it.

The model also outlives tools, licenses, and reorgs, because it is just source files in a repository. Nobody is held hostage by an export format.

Then the effect I did not expect to care about most. One long-living model, projected into audience-specific views: the executive gets capabilities, the team gets containers and flows. Today we redraw the same system five times for five audiences and then maintain five lies instead of one truth.

And agents, which matters more every month. They read and write the same single truth as humans, with no export/import ceremony and no stale copy for the bot. I argued in [Stop Calling It Legacy Code](https://medium.com/@bfrackowiak/stop-calling-it-legacy-code-02308db28cb2) that a codebase is a knowledge container; Stemma just extends the container to hold the architecture itself.

When people ask why I'd spend evenings on this, I give them one sentence: **a diagram you can't diff is a rumor with better fonts.**

## Where I landed

After enough years of watching beautiful models rot, I simply stopped believing in any model that a compiler doesn't check.

> "All models are wrong, but some are useful." ― George Box

The useful ones, it turns out, are the ones whose wrongness you can still measure. That disbelief nagged me long enough to become a side project. The side project slowly became the tool I wanted as an architect: one long-living model, projected into audience-specific views, versioned like the code it describes. Because it *is* code.

I don't know yet whether Stemma becomes a real product or stays my favorite workshop experiment. I do know the question it asks is the right one.

So let me pass it on: how old is your "current state" diagram, and would you bet a production incident on it?

---

**Updated:** 2026-09-12 16:50

**Scores:** tension=8 originality=8 personal=8 contrarian=7 substance=8 punch=9 trend=6
**Scored:** 2026-07-27 rubric=v1 model=claude-opus-5

**Tags:** Software Architecture, Developer Tools, Docs as Code, Architecture Diagrams, Side Projects
