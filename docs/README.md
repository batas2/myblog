# docs/ — how this blog is run

Strategy and reference documents for Field Notes. None of this is deployed (see `deploy.sh` excludes).

> **Career strategy lives elsewhere:** `../../bfrackowiak/` (CLAUDE.md = full context, `00-plan-overview.md` = the 3-track plan and weekly cadence, `11-market-2026-07.md` = market research). This folder covers ONLY blog operations; the blog is Track C of that plan. Division of knowledge set 2026-07-19 — don't duplicate strategy here.

## The map

| File | Question it answers | When to open it |
|---|---|---|
| [BLOG-IMPROVEMENT-PLAN.md](./BLOG-IMPROVEMENT-PLAN.md) | Why these goals, what the research says | Quarterly, or when priorities feel off |
| [ACTION-PLAN.md](./ACTION-PLAN.md) | What to do next, step by step, with "done when" tests | Weekly; it is the working checklist |
| [TOPIC-ENGINE.md](./TOPIC-ENGINE.md) | Where next Monday's post comes from | The Monday ritual (30 min) |
| [POST-PROMPT.md](./POST-PROMPT.md) | The ghostwriting prompt, the human-voice rules, and the pre-publish lint | Every time a post is written |
| [SYNDICATION.md](./SYNDICATION.md) | Platform rules for mirrors (dev.to, Hashnode, Medium, HN) | When distributing a post |
| [ANALYTICS-SETUP.md](./ANALYTICS-SETUP.md) | Manual runbook: GoatCounter + monthly GoAccess on MyDevil | One-time setup, then rollback/reference |
| [archive/](./archive/) | Used-up idea backlogs (all 16 ideas published) | Rarely; historical reference |

## Where everything else lives

- **`drafts/`** (repo root) — the current publish cycle: unpublished post drafts, editable
  draw.io diagram sources, rendered diagram PNGs staged for `assets/`, and the cycle's
  working docs (REVIEW-STATUS, PROPOSALS, REVIEW-NOTES). Never deployed, not read by the build.
- **`content/posts/`** — published markdown sources. Moving a file here (with a Monday date)
  plus `php tools/build.php` is what "publishing" means.
- **Repo root** — only `README.md` (public repo readme) and `LICENSE`. Working documents
  do not live at the root: everything at the root that is not excluded in `deploy.sh`
  ships to the public site.
