# Stemma changelog — what shipped, and when

*Every release of Stemma since 4 May 2026. Releases, not commits, and the repairs alongside the additions.*

- Author: Bartosz Frąckowiak
- Canonical: https://bfrackowiak.pl/stemma/changelog/
- Project page: https://bfrackowiak.pl/stemma/
- Repository: https://github.com/batas2/Stemma
- Licence: PolyForm Noncommercial 1.0.0
- Status: side project, built on evenings and weekends since 4 May 2026

Dates are when the work landed in the repository, not when it was announced — there is nothing here to
announce it to. Anything described as shipped has a feature record and, where it touches the engine,
round-trip fixtures behind it.

---

## 2026-08-08 — The headless half

Everything Stemma could do, it could only do with a mouse in it. This release is the other authoring
surface: a model written as text, rendered by a command, and checked by a build that has never opened
the app.

- **`stemma render`** — a workspace and a view become an image, with no browser of your own open and no
  mouse. It runs a short-lived backend of its own, so two renders can run at once and neither disturbs
  a workspace you have open.
- **A publication profile** — outline boxes, no kind chip, no icon, no shadow, no background grid, no
  status badge. Transparent by default; scale, padding and palette are options. Rendering for a screen
  and rendering for a page are different jobs, and Stemma only had the first.
- **Layout as a constraint** — `grid`, `row`, `column` and `stack`, solved to coordinates. A view can
  now say *two columns, these four, 56 apart* instead of carrying the residue of somebody's mouse. A
  later drag records an offset against the constraint rather than replacing it, so the arrangement
  survives being corrected. Authorable as text, or from the canvas selection.
- **`stemma validate`** — ids are strings and nothing checks them: a typo produces a missing node or a
  missing arrow, silently. This reports dangling endpoints, view members that do not exist, links with
  one end outside the view, and a warning past seven boxes. `--json` for something acting on the answer
  rather than reading it.
- **A render manifest, and `--check`** — every render writes a sibling manifest fingerprinting the
  model, the view, the layout and the profile it was drawn from. `--check` re-computes it and exits
  non-zero when a committed image has stopped matching its source — the drift guarantee, extended past
  the app window.
- **True vector SVG** — the publication profile emits SVG primitives out of the same component tree the
  canvas renders, instead of wrapping serialised HTML in a `foreignObject`. The old export opened in
  Chrome and essentially nowhere else.
- **`Concept` and `Relation`** — a box that asserts nothing about a system, and an arrow that claims
  nothing beyond its label. Drawing an argument previously meant declaring something like "what the
  repository can answer" as a bounded context, which compiles, validates, and is false.
- **A published schema, and a protocol reference** — `stemma.layout.json` has a JSON Schema, and every
  sidecar now carries a pointer to it. The protocol document explains the whole chain to someone who
  will never open the app, with three worked examples that were built and rendered exactly as written.
- **Diagnostics** — a leveled, structured record of what the engine did, as JSON lines under
  `~/.stemma/logs/`, capped and rotated without being asked. Shareable bundles are redacted by default.
  There is no network sink and none can be configured: a log record contains element names, ownership
  and sometimes your source.
- **Fixed: the sidecar stopped dropping what it did not model** — free-form shapes and edge dock handles
  were discarded on their way to disk, so both survived only until reload. Unknown sections now
  round-trip verbatim.
- **Fixed: node titles wrap** — they clipped at around thirty characters, which was quietly shaping what
  things could be called.
- **Fixed: the draw.io export became usable** — it ignored the view and the layout and emitted the whole
  model on a grid in default pastel. It now honours both. Six further defects, found by auditing the
  render pipeline end to end, went with it.

## 2026-08-03 — In the open

The repository, a licence, and two workspaces worth reading before writing your own.

- **`github.com/batas2/Stemma`** — source-available, with the engine and the round-trip fidelity suite
  in it.
- **PolyForm Noncommercial 1.0.0** — free for personal use, study, hobby projects, and for charities,
  educational institutions, public research bodies and government. Commercial use of any kind needs a
  licence.
- **Aurora Rail** — the reference workspace: a fictional rail-ticketing platform with 66 elements, four
  saved views and three narrative books, exercising every part of the DSL.
- **Stemma's own architecture, as a Stemma workspace** — the tool modelling itself, which is the
  cheapest honest test of whether the vocabulary is sufficient.

## 2026-08-01 — Stemma, and a workspace with no solution in it

A rename, and the removal of the largest thing standing between somebody and their first model.

- **Renamed from Verso** — a *stemma codicum* is the family tree philologists draw for a manuscript:
  every surviving copy traced back to one archetype. That is the shape of the thing.
- **Model-only workspaces** — a folder holding `Architecture/` and nothing else opens with no MSBuild,
  no SDK, no restore and no NuGet. Until this, the first step was owning a .NET solution.
- **From scratch** — start a model by name; the empty canvas says what to do next instead of sitting
  there.
- **A desktop shell, decided** — Photino, with a window flag and a renderer benchmark behind the choice.

## 2026-06-10 — The report

An answer to the question every modelling tool eventually meets: how do I show this to somebody who will
never install it?

- **A single-file interactive HTML report** — one file. It opens offline, with no server, no login and
  no seat. Audience modes (builder, stakeholder, reviewer), per-view layer toggles, element search, and
  element-anchored comments that export as a pack and import back into the workspace. Styled to match
  the canvas it came from.
- **Inline edge editing** — a unified properties blade, sticky inspector tabs, and a floating direction
  toolbar on a selected relationship.
- **Keyboard shortcuts** — delete, rename, zoom, view switching, selection.
- **Fixed: dependency links appeared on the module map** — they were being computed and not drawn.
  Default-coloured arrowheads were invisible against the canvas, too.

## 2026-06-08 — Docking, routing, and the rail

The month the canvas stopped feeling like a prototype.

- **draw.io-style edge docking** — relationships anchor to connection dots on a node and reconnect to
  another box.
- **Four routing modes** — curved, elbow, step and straight, with per-end markers and predefined
  relationship types that carry their own styling.
- **The inspector became an icon rail** — one panel at a time (properties, appearance, text, lifecycle,
  ownership, custom properties, comments, layout), plus a full-screen rich editor for notes.
- **Per-view layout modes** — organic, hierarchical, by-type and custom, each tunable and remembered for
  the view it belongs to.
- **`.doc/` became the single source of truth** — product, architecture, UX and UI as four pillars, with
  the pre-refactor specs archived rather than left to rot beside them.

## 2026-05-19 — Books, and a second adapter

Modelling a system is half the job. This is the half about getting a room to agree.

- **Story books** — ordered, audience-tagged sequences of pages, each naming a view and carrying the
  narrative you would actually say out loud. Present mode drives the canvas from the page; the whole
  thing exports as a multi-page PDF.
- **A YAML adapter that preserves trivia** — comments and formatting survive a write, which is the same
  rule the C# writer follows and the reason either one can be trusted.
- **Free-form shapes and a comments sidecar** — ink that carries no architectural meaning, kept out of
  the model on purpose.
- **Data-layer kinds, and two more built-in views** — a data model and a resource tree, alongside the
  module map and the dependency graph.

## 2026-05-04 — The first workspace

Five spikes in a day, answering one question: can the C# on disk be the model database, rather than an
export of it?

- **C# source as storage** — the model read off the syntax tree by name, and written back through
  Roslyn — the foundation everything since has rested on.
- **The canvas** — a drag-and-drop palette, custom views stored as code, two auto-arrange algorithms,
  snapping, alignment and multi-select.
- **The layout sidecar** — positions and styling committed beside the model, so presentation never
  enters it.
- **Lifecycle, undo, and a file watcher** — plus a validation engine with fixtures, and export to PNG,
  SVG and draw.io.

---

## Related

- [The Stemma project page](https://bfrackowiak.pl/stemma/) — what it is, the vocabulary, the agentic
  pipeline, the specifications and the roadmap. Plain text at https://bfrackowiak.pl/stemma/index.md
- [The Diagram That Cannot Lie](https://bfrackowiak.pl/blog/the-diagram-that-cannot-lie/) — the argument
  the tool is built on.
