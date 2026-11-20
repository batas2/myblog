# The Diagram That Cannot Lie

*Why I'm building an architecture modeling tool where the diagram is physically incapable of drifting from the code.*

`target-architecture-FINAL-v3-updated.drawio`. Every shared drive I've had access to contains a file with more or less that name, in a folder full of architecture diagrams. Some of them are beautiful. Some took days of somebody's work. And every one of them is lying to you right now.

I can say this with confidence because I have drawn hundreds of them. I am a Solution Architect, so diagrams are supposed to be my job. And almost every diagram I've ever produced stopped being true within a quarter after I finished it. Nobody did anything wrong. The system simply changed and the picture did not.

## Death by export

The life of a typical architecture diagram looks like this. It is created in a meeting, where it is really useful. Then it gets polished, exported to PNG and pasted into a wiki page, and from that moment it starts to die. It is not *wrong* yet, but the system it describes keeps changing and the picture does not.

It does not matter if it is draw.io, Lucidchart, Enterprise Architect, or an in-house viewer somebody built once and left running. The price is different and the problem is the same: **the model and the truth live in two different places.** With two sources they will diverge. The only question is how many sprints it takes.

Reverse-engineering tools come from the other side: they read the code and generate the picture. That picture is true. But you can only look at it. You cannot *decide* anything in it, so the feedback loop stays open.

You can check this in your own team: find the diagram labeled "current state" and ask when it last matched the current state. I've written before that [diagrams are conversations, not documentation](/blog/diagrams-are-conversations-not-documentation/), because most of their value is used up in the meeting where they're drawn. This article is about the other half of that thought: what would it take for a diagram to *be* documentation and stay true?

## The code is the model

My answer is a side project I now call **[Stemma](/stemma/)**. It was called Verso when this essay was first published. The name changed. The idea below did not. So please read this as a design essay written by somebody who is clearly biased ;)

The main decision is that there is no database at all. The architecture model is stored as plain C# records in Git: bounded contexts, containers, data flows, ownership, risks, decisions. When you open a workspace, the model is loaded into memory. Then you edit something on the canvas. You rename a container, redraw a dependency or move a module to another parent. The tool rewrites the actual `.cs` files using [Roslyn](https://github.com/dotnet/roslyn). This is code *editing* and not code generation, because the tool changes the files that are already there.

One rule is more important than everything else in the project: **round-trip fidelity.** Any edit made in the UI must produce a git diff that contains only the intended change. If you rename one element, the diff shows one changed identifier. There is no reformatting, no reordered members and no re-indented block three files away that a reviewer has to read before finding the real change. If that rule breaks, the tool is broken. Without it nobody would trust the loop.

Layout is kept in a small committed sidecar file, separate from the model: positions, colours, which elements each view shows, and the zoom somebody left it at. So content and presentation are finally separated. We have been saying this about documents for thirty years and somehow never applied it to diagrams.

The first name came from bookbinding: the *verso* is the reverse side of a page, and the canvas was the verso of the code. The name it carries now comes from philology. A *stemma codicum* is the family tree scholars draw for a manuscript, every surviving copy traced back to one archetype. The tool works the same way. There are two committed files and every view comes from them. With both names the idea is the same: a diagram cannot drift from the code when the diagram **is** the code.

## Architecture review becomes a pull request

Storing the model as source sounds like a technical detail, but it changes who the architecture belongs to.

An architecture change arrives as a pull request. You can diff it, comment on it, blame it and revert it. That is governance for free, with habits every developer already has. You need no new committee and no new tool to police. You also get `git log` for your architecture. You can finally ask who moved this capability out of that context, when, and in which commit. I wanted that in every architecture discussion of my career. I never had it.

The model also survives tools, licenses and reorgs, because it is just source files in a repository and nobody depends on an export format.

Then there is the effect I did not expect to care about the most: one long-living model, shown in different views for different audiences. The executive gets capabilities. The team gets containers and flows. Today we redraw the same system five times for five audiences. Then we have five pictures to keep true, which in practice means none of them is.

And there are agents, which is more important every month. They read and write the same model as humans. There is no export and import step and no outdated copy for the bot. I argued in [Stop Calling It Legacy Code](https://medium.com/@bfrackowiak/stop-calling-it-legacy-code-02308db28cb2) that a codebase is a knowledge container. Stemma extends that container so it also holds the architecture.

When people ask why I'd spend evenings on this, I give them one sentence: **a diagram you can't diff is a rumor with better fonts.**

## Where I landed

After enough years of watching beautiful models go out of date, I simply stopped believing in any model that a compiler doesn't check.

> "All models are wrong, but some are useful." ― George Box

For me the useful ones are those where you can still measure how wrong they are. That thought stayed with me long enough to become a side project, and the side project slowly became the tool I wanted to have as an architect: one long-living model, shown in views for different audiences and versioned like the code it describes, because it *is* code.

I don't know yet if Stemma becomes a real product or stays my favorite workshop experiment. I am quite sure that the question it asks is the right one.

So let me pass it on: how old is your "current state" diagram, and would you bet a production incident on it?

---

**Updated:** 2026-11-14 12:34

**Scores:** tension=8 originality=8 personal=8 contrarian=7 substance=8 punch=9 trend=6
**Scored:** 2026-07-27 rubric=v1 model=claude-opus-5

**Tags:** Software Architecture, Developer Tools, Docs as Code, Architecture Diagrams, Side Projects
