# Stemma — the architecture model that is your source code

*An architecture modelling tool for solution and data architects in which the C# source on disk is the model database.*

- Author: Bartosz Frąckowiak
- Canonical: https://bfrackowiak.pl/stemma/
- Repository: https://github.com/batas2/Stemma
- Film: https://bfrackowiak.pl/assets/video/stemma-film-dark.mp4 (1 min 58 s, silent)
- Status: side project, source-available, started 4 May 2026

---

## The claim

The architecture diagram on your wiki was wrong by the next deploy. It always is — because it is a
picture of the system rather than part of it, and nothing makes the picture change when the system
does.

Stemma's answer is not a better canvas. It is to make the diagram and the code the same artefact.
A model is C# records in `Architecture/Architecture.cs` inside your own repository. Editing an
element on the canvas is a Roslyn `DocumentEditor` rewrite of that file. There is no export step to
forget, no second copy to reconcile, and no proprietary format in between.

## Round-trip fidelity

This is the guarantee everything else rests on: **a UI edit arrives as a diff containing only that
edit.** Rename `Ledger` to `Ledger & Reconciliation` on the canvas and `git diff` reports the
declaration and the references Roslyn can see — and nothing else. No reordering, no reformatting,
no blank line moved, no comment lost.

```diff
- var modLedger = new Module("mod_004", "Ledger", "ctx_002");
+ var modLedgerReconciliation = new Module("mod_004", "Ledger & Reconciliation", "ctx_002");
- return Model.Of(…, modAuthorisation, modLedger, modDispatch, …);
+ return Model.Of(…, modAuthorisation, modLedgerReconciliation, modDispatch, …);
```

The ids never change, so every other reference still resolves; only display names and the local
symbols Roslyn renamed move. Every operation carries minimal, realistic and deliberately
pathological round-trip fixtures, and the codebase has one standing instruction about them: when
the fidelity suite fails, the implementation is wrong, not the test.

## The vocabulary

**Elements** — bounded contexts, modules, containers, software systems, people, use cases and
capabilities, plus risks, questions and assumptions as first-class citizens rather than sticky
notes beside the picture.

**Relationships** — data flows and dependencies that name *what moves*, not merely that something
moves. `new DataFlow("flow_ordered", "mod_order", "mod_auth", "OrderPlaced")` is a sentence.

**Metadata** — lifecycle (`current` / `target` / `to-be-created`) and ownership (squad, domain), so
target state lives on the model instead of in a deck nobody opens twice.

**Views** — a module map, a dependency graph and a concerns board are built in; any number of saved
custom views live alongside them, themselves stored as code in `Views/*.cs`.

## Story books

The other half of the problem is not modelling; it is getting a room to agree. A story book is an
ordered, audience-tagged sequence of pages. Each page names a view and carries the narrative you
would actually say while it is on screen. Present mode drives the canvas from the page, so the
walkthrough and the model move together. Books are stored as
`Concepts/view-book.stemma.yaml` beside the model, which means the deck cannot go stale either.

## Getting it in front of people who will not install it

Export a single self-contained HTML report: one file, opens offline, no server, no login and no
seat. It carries audience modes (builder, stakeholder, reviewer), per-view layer toggles, element
search, and element-anchored comments that export as a pack and import back into the workspace.
Other exports: PNG, SVG, draw.io XML, Mermaid, and multi-page PDF view-books.

## Specifications

| | |
|---|---|
| Engine | .NET 10 · Roslyn. `MSBuildWorkspace` for a repository, `AdhocWorkspace` for a model-only folder. All edits are `DocumentEditor` rewrites. |
| Host | ASP.NET Core 10 — REST for workspace, layout, views and violations; SignalR for the operation stream and delta broadcast. |
| Client | React 19 · Vite · `@xyflow/react` · zustand · Tailwind. |
| Requirements | A .NET 10 runtime. A model-only workspace opens with no SDK, no restore and no NuGet. |
| Model storage | `Architecture/*.cs` and `Views/*.cs` in your Git working tree. |
| Presentation | `stemma.layout.json`, committed beside the model. |
| Runtime database | None. In memory while open; permanent storage is the files. |
| Layout | Architectural (by type), hierarchical, force-directed and manual — tunable and remembered per view. |
| Collaboration | Git. Branch, tag, blame, merge, revert, pull request. Live sync over SignalR between clients on the same workspace. |
| Licence | Source-available; exact terms still an open decision. |

## Questions people ask first

**Does the diagram stay in sync with the code?**
It cannot drift, because it is the same artefact. The model is C# in `Architecture/*.cs` inside
your repository, and a canvas edit is a Roslyn rewrite of that file. There is no export step to
forget and no second copy to reconcile.

**Does my whole system have to be written in C#?**
No. C# is the notation the model is written in, not a constraint on what you model. The workspace
can be a model-only folder with no solution in it, and the systems it describes can be written in
anything.

**Where is the data stored?**
In your Git working tree, and nowhere else. There is no runtime database at any layer.

**How is this different from draw.io, Lucidchart or an EA tool?**
Drawing tools produce a picture that is true on the day it is saved. Reverse-engineering tools read
code but cannot edit it. Stemma closes the loop in both directions and guarantees round-trip
fidelity, which is what makes the loop trustworthy enough to use.

**What does a change look like in review?**
A normal Git diff — the change that was made, and nothing else.

**How do stakeholders see the model without installing anything?**
A story book presented from the canvas, or the single-file HTML report, which opens anywhere with
no server, no login and no seat.

## Related reading

- [The Diagram That Cannot Lie](https://bfrackowiak.pl/blog/the-diagram-that-cannot-lie/) — the argument in full: why a diagram that *can* drift always does.
- [Diagrams Are Conversations, Not Documentation](https://bfrackowiak.pl/blog/diagrams-are-conversations-not-documentation/) — what diagrams are actually for.

## Honest position

Stemma is a pet project. It is built by one person on evenings and weekends, and it is not a
company — there is no roadmap owed to anybody, no support line and no sales call at the end. It
exists because the author wanted the tool and nobody had built it.
