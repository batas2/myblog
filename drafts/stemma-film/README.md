# The Stemma film — how it is made

Two cuts of one film, 118.2s each, 1920×1080 at 30fps, silent. The page serves whichever one the
reader's system theme asks for and swaps live if that changes; see the hero script in
`stemma/index.php`.

Nothing here is animation of a mock-up. Every product beat is a recorded session against the real
app, and the `git diff` the film quotes is a real diff of the model the film builds on camera.

```
assets/video/stemma-film-dark.{mp4,webm}
assets/video/stemma-film-light.{mp4,webm}
```

## The four stages

    capture.mjs   drives the real app with Playwright and records frame sequences
    film-source.html   the film itself: deterministic, render(t) draws any moment
    render.mjs    walks render(t) at 30fps and writes one PNG per frame
    encode.sh     PNGs → mp4 (h264) + webm (vp9)

Everything writes under a working directory. Point it wherever you like:

    export STEMMA_FILM_WORK=/tmp/stemma-film     # default if unset

### 1 · capture

Needs the Stemma app running locally (`./run.sh --prod` in the Stemma repo, port 5050) and a copy
of the AuroraRail sample at `$STEMMA_FILM_WORK/ws/AuroraRail`, committed to git so the book beat
can reset the sidecar between themes.

    node capture.mjs dark
    node capture.mjs light

Four sequences per theme, resampled onto the exact frame budgets `film-source.html` expects:

| beat   | frames | what is on screen                                                        |
|--------|--------|--------------------------------------------------------------------------|
| `arch` | 990    | an empty model, then contexts, modules, layout and relationships          |
| `book` | 615    | a story book selected, paged through, and a narrative written into it     |
| `expo` | 150    | Export → Architecture report (.html)                                      |
| `rept` | 225    | the exported file, opened and driven the way a recipient drives it        |

Two notes on why the recordings look the way they do, both forced by the app as it stands:

- Book pages only drive the canvas for the two built-in views, so the recording cut of
  `Concepts/view-book.stemma.yaml` puts every page on `moduleMap` or `dependencyGraph`.
- The book reader takes YAML scalars verbatim, so narratives in that file are single quoted
  lines rather than `|` blocks.

Capture runs at 1440×810 on purpose. CDP screencast hands back CSS pixels whatever the device
scale is, and the film draws these plates at 1920 — that 1.33× upscale is what makes the app's own
12px chrome legible at a distance.

### 2 · render

`film-source.html` needs to be served from the site root (it links `/assets/fonts.css`), with the
captured sequences reachable as `seq-dark/` and `seq-light/` beside it:

    ln -s $STEMMA_FILM_WORK/seq-dark  drafts/stemma-film/seq-dark
    ln -s $STEMMA_FILM_WORK/seq-light drafts/stemma-film/seq-light
    python3 -m http.server 8919 --bind 127.0.0.1 --directory .

    node render.mjs dark
    node render.mjs light

Roughly ten minutes a cut. The film is a pure function of `t`, so a rerun produces identical bytes
and a crashed run can be resumed with `node render.mjs dark <from> <to>`.

`preview.mjs <t…>` shoots single moments from both cuts — use it before committing to a full
render.

### 3 · encode

    ./encode.sh dark
    ./encode.sh light

Writes straight into `assets/video/`. If the film's length changes, update the `VideoObject`
`duration` in `stemma/index.php` — `tests/integration.php` will catch a filename that stops
matching a real file, but not a stale duration.
