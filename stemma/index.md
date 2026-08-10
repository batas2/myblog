# Stemma — the architecture model that is your source code

*An architecture modelling tool for solution and data architects in which the C# source on disk is the model database — editable on a canvas, or written as text by an agent and rendered with one command.*

- Author: Bartosz Frąckowiak
- Canonical: https://bfrackowiak.pl/stemma/
- Changelog: https://bfrackowiak.pl/stemma/changelog/ (plain text at /stemma/changelog/index.md)
- Repository: https://github.com/batas2/Stemma
- Film: https://bfrackowiak.pl/assets/video/stemma-film-dark.mp4 (1 min 58 s, silent)
- Licence: PolyForm Noncommercial 1.0.0 — source-available, not open source
- Status: side project, started 4 May 2026

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
notes beside the picture. And `Concept`: a named box that asserts *nothing* about a system, for
drawing an argument rather than an architecture.

**Relationships** — data flows and dependencies that name *what moves*, not merely that something
moves. `new DataFlow("flow_ordered", "mod_order", "mod_auth", "OrderPlaced")` is a sentence. And
`Relation`, the counterpart to `Concept`: an arrow that claims nothing beyond its own label.

**Metadata** — lifecycle (`current` / `target` / `to-be-created`) and ownership (squad, domain), so
target state lives on the model instead of in a deck nobody opens twice.

**Views** — a module map, a dependency graph and a concerns board are built in; any number of saved
custom views live alongside them, themselves stored as code in `Views/*.cs`.

## The agent's half — authoring and rendering without the app

Everything above happens with a mouse in it. But a workspace is three text files in a vocabulary
that is written down, so the canvas is *an* authoring surface, not the only one. An agent with a
filesystem and a shell writes the model; one command turns it into a picture.

A workspace is exactly:

```
my-diagram/
├── Architecture/Architecture.cs   the model — every element and link
├── Views/<Name>.cs                one file per view; a named subset of the model
└── stemma.layout.json             presentation: arrangement, styling, annotation
```

**A box that asserts nothing.** Every other element kind is a claim — *this is a bounded context*,
*this is deployable*. An argument's boxes claim nothing, and forcing one into a `Module` writes a
falsehood into a file other people will copy from. `Concept` and `Relation` are the way out.

```csharp
var humanDiff = new Concept("con_human_diff", "a colleague's diff");
var ask       = new Concept("con_ask", "ask them why");
var nobody    = new Concept("con_nobody", "nobody to ask");
var artifacts = new Concept("con_artifacts", "tests, contracts, decision records");
```

**The view is the question.** A view names the subset a particular picture is about. A link is drawn
only when *both* of its endpoints are in it — the single most common reason a diagram comes out
looking right and missing an arrow — so the validator reports that case by name.

**Say what the arrangement is, not where things are.** A language model is good at intent and poor at
pixels, so it states the intent and Stemma solves it to coordinates. Nudging one box by hand records
an offset *against* the constraint instead of replacing it, so the arrangement survives being
corrected and the nudge travels with its slot when the grid re-solves. `grid`, `row`, `column` and
`stack` all work this way.

```jsonc
"custom:view_ask": {
  "layout": {
    "kind": "grid",
    "columns": [["con_human_diff", "con_agent_diff"],
                ["con_ask",        "con_nobody"],
                ["con_answered",   "con_artifacts"]],
    "gap": [60, 40], "align": "center"
  },
  "shapes": [
    { "id": "sh_human", "kind": "label", "x": -140, "y": 10, "text": "human", "color": "#767884" }
  ]
}
```

**Ink is not architecture.** Swimlane labels, brackets and callouts — anything carrying no
architectural meaning — are free-form shapes in the sidecar, never elements. It is the rule an agent
breaks first: if you want to add an element so that a picture looks better, what you want is a shape.

Then the shell, with no browser open:

```
stemma validate --workspace . [--json] [--strict]
stemma render --workspace <path> --view <id> --out <file> [options]

  --profile publication|app      publication strips the app UI; the default
  --palette dual-theme|print|slide
  --theme light|dark|light,dark  two themes write the -light/-dark pair
  --format png|svg|png,svg
  --opaque                       bake the page background in; default is transparent
  --scale 1-4                    device pixel multiplier; default 2
  --pad <px>                     padding around the content bounds; default 24
  --check                        do not render; report whether the image is still current
```

Three properties make this worth having:

- **Deterministic.** Same input, same picture. Each render starts a short-lived backend of its own,
  so two can run at once and neither disturbs a workspace you have open.
- **Checkable.** Every render writes a sibling `.manifest.json` fingerprinting the model, the view,
  the layout and the profile it came from. `stemma render --check` re-computes it and exits non-zero
  when a committed image has stopped matching its source — the drift guarantee, extended past the
  app window, so a build fails instead of a reader being misled.
- **Written down.** The presentation sidecar has a published JSON Schema and every file written
  points at it. The repository carries a protocol reference explaining the whole chain to somebody
  who will never open the app, with three worked examples that were built and rendered exactly as
  printed.

`stemma validate` catches what the compiler cannot: ids are strings, and a typo produces a missing
node or a missing arrow, silently. It reports dangling endpoints, view members that do not exist,
duplicate ids, dangling shape anchors, links with one end outside the view, and a warning past seven
boxes.

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
Other exports: PNG, true vector SVG, draw.io XML, Mermaid, and multi-page PDF view-books.

## Specifications

| | |
|---|---|
| Engine | .NET 10 · Roslyn. `MSBuildWorkspace` for a repository, `AdhocWorkspace` for a model-only folder. All edits are `DocumentEditor` rewrites. |
| Host | ASP.NET Core 10 — REST for workspace, layout, views and violations; SignalR for the operation stream and delta broadcast. |
| Client | React 19 · Vite · `@xyflow/react` · zustand · Tailwind. |
| Headless | `Stemma.Cli` — `stemma render` and `stemma validate`, driving the same render route the canvas uses. One short-lived process per render, so renders are isolated and parallelisable. |
| Requirements | A .NET 10 runtime. A model-only workspace opens with no SDK, no restore and no NuGet. Building from source needs the .NET 10 SDK and Node 24+. |
| Model storage | `Architecture/*.cs` and `Views/*.cs` in your Git working tree. |
| Presentation | `stemma.layout.json`, committed beside the model, with a published JSON Schema. |
| Runtime database | None. In memory while open; permanent storage is the files. |
| Layout | Architectural (by type), hierarchical, force-directed and manual — tunable and remembered per view. Or a declarative constraint (`grid`, `row`, `column`, `stack`) solved to coordinates and kept live. |
| Provenance | A render manifest per image, and `stemma render --check` to fail a build when a committed image no longer matches its model. |
| Diagnostics | Structured JSON-lines logs under `~/.stemma/logs/`, capped and rotated, level changeable at runtime. Redacted shareable bundles. No network sink, and none can be configured. |
| Samples | `samples/AuroraRail` — 66 elements, four views, three books. `samples/StemmaArchitecture` — Stemma modelling itself. |
| Collaboration | Git. Branch, tag, blame, merge, revert, pull request. Live sync over SignalR between clients on the same workspace. |
| Licence | PolyForm Noncommercial 1.0.0. Free for noncommercial use; commercial use needs a commercial licence. |

## Questions people ask first

**Does the diagram stay in sync with the code?**
It cannot drift, because it is the same artefact. The model is C# in `Architecture/*.cs` inside your
repository, and a canvas edit is a Roslyn rewrite of that file. There is no export step to forget
and no second copy to reconcile.

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

**Can an agent use this without opening the canvas?**
That is the whole second half of the tool. An agent authors the three text files, runs
`stemma validate` to catch the string ids nothing else checks, and runs `stemma render` to produce
the image with no browser open and no mouse. Arrangement is expressed as a constraint rather than as
coordinates, because a model is good at intent and bad at pixels.

**What stops a published diagram from going stale?**
Every render writes a sibling manifest fingerprinting the model, the view, the layout and the profile
it was drawn from. `stemma render --check` re-computes that fingerprint and exits non-zero when the
committed image has stopped matching its source, so a build fails instead of a reader being misled.

**What licence is it under?**
PolyForm Noncommercial 1.0.0. Free for any noncommercial purpose — personal use, study, hobby
projects, and use by charities, educational institutions, public research bodies and government.
Commercial use of any kind needs a commercial licence: kontakt@bfrackowiak.pl. It is
source-available, not open source, and the distinction is deliberate.

## Related reading

- [The Diagram That Cannot Lie](https://bfrackowiak.pl/blog/the-diagram-that-cannot-lie/) — the argument in full: why a diagram that *can* drift always does.
- [Diagrams Are Conversations, Not Documentation](https://bfrackowiak.pl/blog/diagrams-are-conversations-not-documentation/) — what diagrams are actually for.
- [The Stemma changelog](https://bfrackowiak.pl/stemma/changelog/) — every release since 4 May 2026, with the repairs alongside the additions.

## Honest position

Stemma is a pet project. It is built by one person on evenings and weekends, and it is not a company
— there is no roadmap owed to anybody, no support line and no sales call at the end. A commercial
licence exists because the licence had to say something and that is the honest thing for it to say,
not because there is a funnel behind it. The tool exists because the author wanted it and nobody had
built it.
