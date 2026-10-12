# Stemma: the architecture model that is your source code

*An architecture modelling tool for solution and data architects. The C# source in your repository is the model: people edit it on a canvas, agents edit it as text, and both change the same files.*

- Author: Bartosz Frąckowiak
- Canonical: https://bfrackowiak.pl/stemma/
- Changelog: https://bfrackowiak.pl/stemma/changelog/ (plain text at /stemma/changelog/index.md)
- Repository: https://github.com/batas2/Stemma
- Film: https://bfrackowiak.pl/assets/video/stemma-film-dark.mp4 (1 min 58 s, silent)
- Licence: PolyForm Noncommercial 1.0.0, source-available, not open source
- Status: side project, started 4 May 2026

---

## Two layers, one model

The model has two faces, and both are the same files in Git. I built it like this because an
architecture today has two audiences: the people who decide and build, and the agents that write
more and more of the code.

**The visual layer is for people.** The canvas, the views and the story books. Here an architect, a
team and the people who pay for the work look at the same picture and agree on it. Every edit on the
canvas is a small Roslyn rewrite of the C# file, so agreeing on the picture means agreeing on the
model.

**The text layer is for people and agents.** The same model as three text files:
`Architecture.cs`, one file per view, and a layout sidecar with a JSON Schema. An agent reads and
writes them, checks them with `stemma validate`, renders them with `stemma render`, and a human
reviews the result as a normal diff. You get this out of the box: no plugin, no API key, no export
step.

## Why the diagram cannot drift

The architecture diagram on a wiki is usually wrong after the next deploy, because it is a picture
of the system and not part of it. Nothing makes the picture change when the system does.

In Stemma the diagram and the code are the same thing. A model is C# records in
`Architecture/Architecture.cs` inside your own repository. Editing an element on the canvas is a
Roslyn `DocumentEditor` rewrite of that file. There is no export step to forget, no second copy to
keep in sync, and no proprietary format in between.

## Round-trip fidelity

Everything else depends on this: **a UI edit becomes a diff with only that edit in it.** Rename
`Ledger` to `Ledger & Reconciliation` on the canvas, and `git diff` shows the declaration and the
references Roslyn can see, and nothing else. No reordering, no reformatting, no moved blank line,
no lost comment.

```diff
- var modLedger = new Module("mod_004", "Ledger", "ctx_002");
+ var modLedgerReconciliation = new Module("mod_004", "Ledger & Reconciliation", "ctx_002");
- return Model.Of(…, modAuthorisation, modLedger, modDispatch, …);
+ return Model.Of(…, modAuthorisation, modLedgerReconciliation, modDispatch, …);
```

The ids never change, so every other reference still works; only display names and the local
symbols Roslyn renamed move. Every operation has small, realistic and deliberately nasty round-trip
fixtures, and the codebase has one fixed rule about them: when the fidelity suite fails, the
implementation is wrong, not the test.

## The vocabulary

**Elements:** bounded contexts, modules, containers, software systems, people, use cases and
capabilities, plus risks, questions and assumptions as real model elements instead of sticky notes
next to the picture. And `Concept`: a named box that asserts *nothing* about a system, for drawing
an argument instead of an architecture.

**Relationships:** data flows and dependencies that name *what moves*, not only that something
moves. `new DataFlow("flow_ordered", "mod_order", "mod_auth", "OrderPlaced")` is a sentence. And
`Relation`, the pair of `Concept`: an arrow that claims nothing more than its own label.

**Metadata:** lifecycle (`current`, `target`, `to-be-created` and more) and ownership (squad,
domain), so target state lives in the model and not in a deck nobody opens twice.

**Views:** a module map, a dependency graph and a concerns board are built in, and any number of
saved custom views live next to them, stored as code in `Views/*.cs`.

## The text layer up close

A workspace is three text files with a vocabulary that is written down, so the canvas is *one* way
to edit the model, not the only one. An agent with a filesystem and a shell writes the model; one
command turns it into a picture.

A workspace is exactly:

```
my-diagram/
├── Architecture/Architecture.cs   the model: every element and link
├── Views/<Name>.cs                one file per view, a named subset of the model
└── stemma.layout.json             presentation: arrangement, styling, annotation
```

**A box that asserts nothing.** Every other element kind is a claim: *this is a bounded context*,
*this is deployable*. The boxes in an argument claim nothing, and if you force one into a `Module`,
you write something false into a file other people will copy. `Concept` and `Relation` are the way
out.

```csharp
var humanDiff = new Concept("con_human_diff", "a colleague's diff");
var ask       = new Concept("con_ask", "ask them why");
var nobody    = new Concept("con_nobody", "nobody to ask");
var artifacts = new Concept("con_artifacts", "tests, contracts, decision records");
```

**The view is the question.** A view names the subset one picture is about. A link is drawn only
when *both* ends are in the view. This is the most common reason a diagram looks fine and still
misses an arrow, so the validator reports this case by name.

**Describe the arrangement, not the positions.** A language model is good at intent and bad at
pixels, so the agent writes the intent and Stemma calculates the coordinates. When you move one box
by hand, Stemma stores an offset *against* the constraint instead of replacing it, so the
arrangement survives the correction and the offset moves with its slot when the grid is solved
again. `grid`, `row`, `column` and `stack` all work this way.

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

**Ink is not architecture.** Swimlane labels, brackets and callouts carry no architectural meaning,
so they are free-form shapes in the sidecar, never elements. Agents break this rule first: if you
want an element only to make the picture look better, what you want is a shape.

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

- **Deterministic.** The same input gives the same picture. Every render starts its own short-lived
  backend, so two renders can run in parallel and neither touches a workspace you have open.
- **Checkable.** Every render writes a `.manifest.json` next to the image, with a fingerprint of the
  model, the view, the layout and the profile. `stemma render --check` calculates it again and exits
  non-zero when a committed image no longer matches its source, so the build fails before a reader
  sees an old picture.
- **Written down.** The presentation sidecar has a published JSON Schema, and every file points to
  it. The repository has a protocol reference that explains the whole chain for someone who never
  opens the app, with three worked examples that were built and rendered exactly as printed.

`stemma validate` catches what the compiler cannot: ids are strings, and a typo gives you a missing
node or a missing arrow with no error. It reports dangling endpoints, view members that do not
exist, duplicate ids, dangling shape anchors, links with one end outside the view, and a warning
past seven boxes.

## Story books: the visual layer at work

Modelling the system is half of the job. The other half is getting a room to agree. A story book is
an ordered sequence of pages for one audience. Each page names a view and carries the narrative you
would say while it is on the screen. Present mode drives the canvas from the page, so the
walkthrough and the model move together. Books are stored as `Concepts/view-book.stemma.yaml` next
to the model, so the deck cannot get stale either.

## For people who will not install it

Export one self-contained HTML report: a single file that opens offline, with no server, no login
and no seat. It has audience modes (builder, stakeholder, reviewer), layer toggles per view, element
search, and comments attached to elements that export as a pack and import back into the
workspace. Other exports: PNG, true vector SVG, draw.io XML, Mermaid, and multi-page PDF
view-books.

## Specifications, for the record

| | |
|---|---|
| Engine | .NET 10, Roslyn. `MSBuildWorkspace` for a repository, `AdhocWorkspace` for a model-only folder. All edits are `DocumentEditor` rewrites. |
| Host | ASP.NET Core 10. REST for workspace, layout, views and violations; SignalR for the operation stream and delta broadcast. |
| Client | React 19, Vite, `@xyflow/react`, zustand, Tailwind. |
| Headless | `Stemma.Cli`: `stemma render` and `stemma validate`, using the same render route as the canvas. One short-lived process per render, so renders are isolated and can run in parallel. |
| Requirements | A .NET 10 runtime. A model-only workspace opens with no SDK, no restore and no NuGet. Building from source needs the .NET 10 SDK and Node 24+. |
| Model storage | `Architecture/*.cs` and `Views/*.cs` in your Git working tree. |
| Presentation | `stemma.layout.json`, committed next to the model, with a published JSON Schema. |
| Runtime database | None. In memory while open; the files are the permanent storage. |
| Layout | Architectural (by type), hierarchical, force-directed and manual, tunable and remembered per view. Or a declarative constraint (`grid`, `row`, `column`, `stack`) solved to coordinates and kept live. |
| Provenance | A render manifest per image, and `stemma render --check` to fail a build when a committed image no longer matches its model. |
| Diagnostics | Structured JSON-lines logs under `~/.stemma/logs/`, capped and rotated, level changeable at runtime. Redacted bundles you can share. No network sink, and none can be configured. |
| Samples | `samples/AuroraRail`: 66 elements, four views, three books. `samples/StemmaArchitecture`: Stemma modelling itself. |
| Collaboration | Git: branch, tag, blame, merge, revert, pull request. Live sync over SignalR between clients on the same workspace. |
| Licence | PolyForm Noncommercial 1.0.0. Free for noncommercial use; commercial use needs a commercial licence. |

## Questions people ask first

**Does the diagram stay in sync with the code?**
It cannot drift, because it is the same thing. The model is C# in `Architecture/*.cs` inside your
repository, and a canvas edit is a Roslyn rewrite of that file. There is no export step to forget
and no second copy to keep in sync.

**Does my whole system have to be written in C#?**
No. C# is only the notation of the model, not a limit on what you model. The workspace can be a
model-only folder with no solution in it, and the systems it describes can be written in anything.

**Where is the data stored?**
In your Git working tree, and nowhere else. There is no runtime database at any layer.

**How is this different from draw.io, Lucidchart or an EA tool?**
A drawing tool gives you a picture that is true on the day you save it. A reverse-engineering tool
reads code but cannot edit it. Stemma works in both directions and keeps round-trip fidelity, and
that is what makes the loop safe to use.

**What does a change look like in review?**
A normal Git diff: the change that was made, and nothing else.

**How do stakeholders see the model without installing anything?**
A story book presented from the canvas, or the single-file HTML report, which opens anywhere with no
server, no login and no seat.

**Can an agent use this without opening the canvas?**
Yes, and out of the box: that is the text layer. An agent writes the three text files, runs
`stemma validate` to catch the string ids nothing else checks, and runs `stemma render` to get the
image with no browser and no mouse. The layout is a constraint instead of coordinates, because a
model is good at intent and bad at pixels.

**What stops a published diagram from going stale?**
Every render writes a manifest next to the image, with a fingerprint of the model, the view, the
layout and the profile. `stemma render --check` calculates it again and exits non-zero when the
committed image no longer matches its source, so the build fails before a reader sees an old
picture.

**What licence is it under?**
PolyForm Noncommercial 1.0.0. Free for any noncommercial use: personal use, study, hobby projects,
and use by charities, schools, public research bodies and government. Any commercial use needs a
commercial licence: kontakt@bfrackowiak.pl. It is source-available, not open source, and that is on
purpose.

## Related reading

- [The Diagram That Cannot Lie](https://bfrackowiak.pl/blog/the-diagram-that-cannot-lie/): the full argument, why a diagram that *can* drift always does.
- [Diagrams Are Conversations, Not Documentation](https://bfrackowiak.pl/blog/diagrams-are-conversations-not-documentation/): what diagrams are really for.
- [The Stemma changelog](https://bfrackowiak.pl/stemma/changelog/): every release since 4 May 2026, with the fixes next to the new features.

## Honest position

Stemma is my pet project. I build it alone, on evenings and weekends, and it is not a company. There
is no roadmap I owe anybody, no support line and no sales call at the end. The commercial licence
exists because a licence has to say something, not because there is a funnel behind it. I built the
tool because I wanted it and nobody else had.
