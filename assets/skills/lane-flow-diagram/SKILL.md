---
name: lane-flow-diagram
description: >
  Draw a cross-team flow as a lane diagram in draw.io: participants as horizontal bands,
  time across the x-axis, and the owning team written into every lane label. Use instead of a
  UML sequence diagram whenever the flow crosses teams or services and the reader needs to see
  who owns each step, what actually happens inside it, and where it can hurt.
  Triggers: "sequence diagram", "data flow diagram", "swimlane", "draw the flow",
  "how does X work end to end", "diagram this integration", "show the handoffs".
---

# Lane flow diagram

A sequence diagram gives each participant a vertical line. A line has no width, so the only place
to write anything is the arrow, and an arrow label holds about four words. Everything else about the
step gets exiled into a paragraph underneath, where it is skimmed.

Rotate the picture 90 degrees. Participants become horizontal bands. A band has height, so each step
becomes a box you can write three lines in, and the lane label has room for the team that owns it.

That rotation is the entire skill. Everything below is how to spend the space it buys.

## When to use this, and when not to

Use it when the flow crosses teams, services or organizations, and the reader's real question is
some version of *who owns this and where does it break*.

Do not use it when the question is ordering and control: who holds a transaction open, when a stack
unwinds, what happens under `alt` / `loop` / `par`. UML sequence notation is better at that and it
is not close. Draw lifelines, or write PlantUML.

## The rules

**One diagram, one question.** Put the question in your head before drawing, and put it in the
title. Every box that does not change the answer is decoration, and decoration attracts feedback
about decoration. If you need two questions answered, draw two diagrams.

**Lanes are participants, and the label carries the owner.** Two lines per lane label: the module,
service or actor on the first, the owning team in small caps on the second. The team line is the
reason this notation exists. Without it you have drawn a nicer flowchart. External actors (a
customer, a regulator, someone else's API) have no owning team and get one line; that absence is
information, so do not invent a squad to fill it.

**The x-axis is time and only time.** A step to the right happens after a step to the left. Never
reuse a column for two unrelated things.

**Title what the step does, in English. Body carries the mechanism.** "Ask who this is", not
`resolve(candidate)`. A diagram whose argument is that non-engineers can read it should not be titled in
a language they do not speak; the method name goes in the body if it earns its place. The body is
what a reviewer would otherwise have to ask about: the timeout, the retry, the ordering constraint,
the thing that is idempotent, the state nobody owns. Two or three lines, about 22 characters each at
the geometry below. If a box has nothing to say beyond its name, it is probably not a step.

**Never encode meaning in colour alone.** Every colour is doubled by a glyph, a stroke weight or a
word, so the diagram survives greyscale printing and colour blindness.

**Put the reasoning on the page.** One or two note boxes under the flow, each answering a "why did
you do it that way" that a reviewer will otherwise raise in a comment thread. This is the highest
value part of the whole notation and the part people skip.

**Seven boxes is a lot.** Past about eight steps or six lanes, split the diagram, and notice when
you cannot: a flow that will not fit on one page rarely fits inside one team either. That is a
finding about the design, not about the page.

## The visual vocabulary

| Channel | Meaning | Never used for |
|---|---|---|
| Vertical position (lane) | Who does the step, and which team owns them | Anything else |
| Horizontal position | Order in time | Importance |
| Solid edge | Command. Someone is waiting for an answer | |
| Dashed edge | Event. Nobody is waiting | |
| Stroke weight 2.4 | Doubles the colour, so meaning never rests on hue alone | A third channel |
| Accent colour + ★ | New build. Everything unmarked already exists | |
| Warning colour | Where the flow can hurt you: a timeout, a legal clock, a state nobody owns | More than 2 boxes |
| Note box | Why it was designed this way | Restating the boxes |

Three semantic hues is the budget, and most diagrams spend two. Past that the reader is hunting a
legend, which is the split-attention cost this notation exists to avoid.

## Palette

Two palettes, not one ink. A single colour set tuned to survive both a light and a dark page is the
right answer when you ship one file, and it costs you every colour: no hue clears 4.5:1 against both
ends of that range, so the whole diagram sits near 4.2 and stays grey. Render twice instead, export
`name-light.png` and `name-dark.png`, and let the page pick with a `<picture>`. Every value below
clears WCAG AA against its own ground.

The hues are semantic, not decorative: indigo for the thing being added, rose for risk, amber for a
person, zinc for everything that already exists.

```
                        light (on #fbfbfd)      dark (on #0f1014)
title text              #18181b                 #f4f4f5
body text               #52525b   7.5:1         #d4d4d8  12.9:1
box stroke, existing    #71717a   4.7:1         #a1a1aa   7.4:1
accent, the new build   #4f46e5   6.1:1         #818cf8   6.4:1
risk, where it hurts    #be123c   6.1:1         #fb7185   7.1:1
person, an actor        #b45309   4.9:1         #fbbf24  11.4:1
rules and borders       #e4e4e7                 #3f3f46
box fill, existing      #ffffff                 #18181b
box fill, tinted        the hue at 12%          the hue at 14%
lane zebra              neutral at 6%           neutral at 5%
background              transparent             transparent
font                    Inter (or your site's), 12.5px lane labels, 11px box titles, 10px bodies
```

Existing geometry uses the softer stroke on purpose. If the boxes that were already there are drawn
at full contrast, they shout louder than the one box you are adding, and the reader's eye goes to
the wrong place.

If you ship only one file, fall back to a single dual-theme ink (`#767884` stroke, `#6366e8` accent,
no fills) and accept the grey. That is the honest trade, not a lesser palette.

## Geometry that works

Tuned for a 976px content column, which is a normal blog or wiki width.

```
canvas width        976
lane label gutter   0 .. 126          vertical rule at x=126
lane height         92                zebra: fillColor=#71717a;opacity=6 on alternate lanes
step box            x = 134 + col*139, width 128, height 66, y = laneTop + 8
step box padding    8 left, title baseline +19, body lines every 12.5px, ~22 chars per line
orchestrator band   5px bar at laneTop + 78, spanning the columns the caller holds open
note box            below the lanes, width ~560, height ~94
legend              bottom right, one row per channel you actually used
```

**This caps at 6 columns and 5 lanes at 976px.** Check the arithmetic before you exceed it:
`134 + col*139 + 128` must stay under the canvas width, and `lanes * 92 + note` must stay under the
height. If the flow needs more, widen the canvas by 139 per extra column and 92 per extra lane and
accept that the export will be scaled down, or split the diagram. Do not silently emit a seventh
column at the default width; it lands off the page.

At any width, the body text is the first thing to become unreadable. On a 390px phone the boxes
render around 4px and the notation loses the thing it exists for. Treat the output as a desktop
artifact, and if the target is narrow, cut steps rather than shrink type.

Edges are orthogonal: out of the right edge of the source box, along to the midpoint between
columns, down or up to the target lane, into the left edge of the target box. Pin that with
`exitX=1;exitY=0.5;entryX=0;entryY=0.5;` or mxGraph picks its own perimeter points and arrows leave
from the top of boxes.

The resulting staircase is a feature. A flow that runs straight down and to the right is a pipeline;
a flow that returns to the same lane between calls is an orchestration, and the shape tells you
which one you have before you read a word. If the source diagram had an orchestrator, the lane
version must show it returning to that lane, or you have drawn a different architecture and the
comparison is worthless.

## Output

Write a `.drawio` file. It is XML, every renderer reads it, and it stays editable by a human who
does not have your toolchain. It carries the light palette, because draw.io edits on a white canvas;
the dark export is the same geometry re-inked.

Emit every element below. The note box especially: it is the highest-value part of the notation and
the one an agent copying structure will leave out because the example did.

```xml
<mxfile host="app.diagrams.net" agent="lane-flow-diagram">
  <diagram name="the-question-this-answers" id="flow">
    <mxGraphModel dx="976" dy="600" grid="0" page="1" pageWidth="976" pageHeight="600"
                  background="none" math="0" shadow="0">
      <root>
        <mxCell id="0"/>
        <mxCell id="1" parent="0"/>

        <!-- Lane. Two type sizes in one cell means the label has to be HTML.
             Alternate lanes carry the 7% zebra; the others use fillColor=none. -->
        <mxCell id="lane2" value="&lt;b&gt;Resolver&lt;/b&gt;&lt;br&gt;&lt;span style=&quot;font-size:9px;letter-spacing:0.6px&quot;&gt;IDENTITY SQUAD&lt;/span&gt;"
          style="rounded=0;whiteSpace=wrap;html=1;fillColor=#71717a;opacity=6;strokeColor=none;fontColor=#18181b;fontFamily=Inter;fontSize=12;align=left;verticalAlign=middle;spacingLeft=8;"
          vertex="1" parent="1">
          <mxGeometry x="0" y="192" width="976" height="92" as="geometry"/>
        </mxCell>

        <!-- Step. Title says what happens; the body says how it can go wrong. -->
        <mxCell id="s3" value="&lt;b&gt;3&amp;nbsp;&amp;nbsp;Match, or mint&lt;/b&gt;&lt;br&gt;Under the threshold we&lt;br&gt;mint a second company&lt;br&gt;that never merges back."
          style="rounded=1;arcSize=8;whiteSpace=wrap;html=1;fillColor=#be123c;opacity=10;strokeColor=#be123c;fontColor=#be123c;fontFamily=Inter;fontSize=10;strokeWidth=2.4;align=left;verticalAlign=top;spacingLeft=8;spacingTop=4;"
          vertex="1" parent="1">
          <mxGeometry x="412" y="200" width="128" height="66" as="geometry"/>
        </mxCell>

        <mxCell id="s4" value="&lt;b&gt;4&amp;nbsp;&amp;nbsp;Link the two&lt;/b&gt;&lt;br&gt;linkOrgs(), once&lt;br&gt;identity came back"
          style="rounded=1;arcSize=8;whiteSpace=wrap;html=1;fillColor=#ffffff;strokeColor=#71717a;fontColor=#18181b;fontFamily=Inter;fontSize=10;strokeWidth=1.6;align=left;verticalAlign=top;spacingLeft=8;spacingTop=4;"
          vertex="1" parent="1">
          <mxGeometry x="551" y="108" width="128" height="66" as="geometry"/>
        </mxCell>

        <!-- Edge. Dashed is an event or a return. The exit/entry pins keep it orthogonal. -->
        <mxCell id="e3" value=""
          style="edgeStyle=orthogonalEdgeStyle;rounded=1;html=1;exitX=1;exitY=0.5;exitDx=0;exitDy=0;entryX=0;entryY=0.5;entryDx=0;entryDy=0;strokeColor=#71717a;fontColor=#52525b;fontFamily=Inter;fontSize=10;strokeWidth=1.6;endArrow=block;endFill=1;dashed=1;dashPattern=6 4;"
          edge="1" parent="1" source="s3" target="s4">
          <mxGeometry relative="1" as="geometry"/>
        </mxCell>

        <!-- Orchestrator band: this caller holds the transaction open across these columns. -->
        <mxCell id="band" value=""
          style="rounded=0;html=1;fillColor=#52525b;opacity=45;strokeColor=none;"
          vertex="1" parent="1">
          <mxGeometry x="273" y="178" width="684" height="5" as="geometry"/>
        </mxCell>

        <!-- The count, printed so nobody has to recount it. -->
        <mxCell id="count" value="4 squads&amp;nbsp;&amp;nbsp;·&amp;nbsp;&amp;nbsp;3 team handovers"
          style="text;html=1;strokeColor=none;fillColor=none;fontColor=#18181b;fontFamily=Inter;fontSize=10;fontStyle=1;align=right;"
          vertex="1" parent="1">
          <mxGeometry x="656" y="16" width="312" height="18" as="geometry"/>
        </mxCell>

        <!-- The reason. Never skip this one. -->
        <mxCell id="note" value="&lt;b&gt;Where this flow needs a conversation&lt;/b&gt;&lt;br&gt;Steps 3 and 4 cross a squad boundary with a confidence threshold in between. Whose ticket that is gets agreed here, or it gets agreed in an incident."
          style="rounded=1;arcSize=6;whiteSpace=wrap;html=1;fillColor=#be123c;opacity=10;strokeColor=#be123c;fontColor=#be123c;fontFamily=Inter;fontSize=10;align=left;verticalAlign=top;spacingLeft=10;spacingTop=6;spacingRight=10;"
          vertex="1" parent="1">
          <mxGeometry x="134" y="490" width="560" height="94" as="geometry"/>
        </mxCell>

      </root>
    </mxGraphModel>
  </diagram>
</mxfile>
```

A legend goes bottom right, one row per channel the diagram actually uses. Omit rows for channels
you did not spend; an unused legend entry is a reader hunting for something that is not there.

## Before you hand it over

Read the diagram back and answer these. If any answer is "in the doc", move it onto the picture.

- Which single question does this answer, and is that question the title?
- Can a reader name the owning team of every step without asking?
- How many **distinct team pairs** hand each other something? Not arrows, and not lane crossings:
  two lanes owned by one squad are a standup, and six arrows between the same two squads are one
  conversation. That number is how many negotiations this feature needs, and it belongs in the
  summary next to the estimate. Print it on the diagram so nobody has to recount it.
- Which box is the one that can hurt you, and does it look different?
- Is there a step whose body only repeats its title? Delete the body or delete the step.
- Print it in greyscale. Does it still parse?

## Credit

Nothing here is a new notation, and you should know that before you show it to anyone as one.
Cross-functional process charts predate software entirely. Rummler and Brache popularised the form
in *Improving Performance: How to Manage the White Space on the Organization Chart* (1990), on the
observation that process failures cluster at the handoffs between departments rather than inside
them. BPMN has had pools and lanes since 2004, UML ships activity partitions, and PlantUML and
Mermaid both do swimlanes in text.

What this file adds is a discipline, not a shape: the owning team in the lane label, the mechanism
in the box body, the reason on the canvas, and a handover count you can take to a planning meeting.
If your tooling already gives you lanes, keep the tooling and take the four rules.

Daniel Moody's *The "Physics" of Notations* (IEEE TSE, 2009) is the reference for why spending more
than one visual variable is worth the trouble. It is also the reference for not spending too many:
his graphic economy principle puts the ceiling around six categories, which is why the palette above
has three colours and not eight.

Written up at <https://bfrackowiak.pl/blog/turn-the-sequence-diagram-on-its-side/>.
MIT licensed. No attribution needed.
