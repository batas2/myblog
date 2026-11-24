# bfrackowiak.pl

Personal site and blog ("Field Notes") of Bartosz Frąckowiak — solution architect.
Live at [bfrackowiak.pl](https://bfrackowiak.pl).

Built on a deliberately boring stack: **PHP + markdown + SQLite + rsync**. No framework,
no database server, no external services in the critical path. Runs on any shared PHP
hosting. Every page is pre-rendered; the only dynamic islands are comments, a contact
form, and first-party analytics.

## Features

- **Static blog generator** — markdown posts in `content/posts/` become pre-rendered
  pages, tag archives, an RSS feed with full content, a sitemap, and per-post markdown
  mirrors (`/blog/<slug>/index.md`) plus a single `llms-full.txt` for AI crawlers.
- **Draft / scheduled / published workflow** — `**Status:** draft` keeps a post out of
  the build; a future date (filename, or an explicit `**Publish:** YYYY-MM-DD HH:MM`
  line) schedules it. A server cron re-runs the generator so scheduled posts go live
  on time, everywhere at once: page, index, tags, RSS, sitemap, `llms-full.txt`.
- **Admin control room** — `/admin.php` (password-protected, sessions + CSRF): the
  publishing pipeline with countdowns, one-click "rebuild now", comment moderation,
  analytics overview, system health.
- **Editorial scoring** — every post carries seven 1–10 scores in its own markdown
  (`**Scores:**` / `**Scored:**`): tension, originality, personal stake,
  contrarianism, substance, punch, and trend fit. The rubric
  ([docs/SCORING-RUBRIC.md](docs/SCORING-RUBRIC.md)) has anchored band descriptors and
  requires an evidence quote per score, because bare 1–10 scales drift between runs.
  A weighted reach score gates publishing; the build mirrors everything into
  `content/scores.db`, and the admin Scores tab puts each axis next to the pageviews
  and engaged time the post actually earned, with a per-axis rank correlation. The
  weights start as a research-backed prior and are meant to be re-fitted against that
  measured traffic.
- **Optional Cloudflare Turnstile** — one config block arms the widget on the
  contact form, comments, and newsletter (server-side siteverify, fails closed);
  without keys every form falls back to the built-in dependency-free defenses.
- **Comments without a database** — JSON files per post, stateless HMAC math captcha,
  honeypot, time trap, per-IP rate limit. Commenter emails are stored but rendered
  masked (`j***@example.com`).
- **First-party analytics** — a `sendBeacon` endpoint writing to SQLite. No cookies;
  visitors are counted via a daily-rotating salted hash, so raw IPs are never stored
  and visitors are unlinkable across days. v2 records coarse, privacy-safe context:
  country (offline GeoIP — no external calls), referrer + source group (search /
  social / AI assistants / news / campaigns), browser, OS, device class, language,
  screen bucket, visitor timezone, UTM tags, and engaged reading time per view.
  Dashboard lives in `/admin.php` (analytics tabs; stats key or admin session)
  with daily + hour-of-day charts, per-page drilldown, and a recent-visits feed.
- **Self-hosted newsletter** — no external service. A form on every page posts
  to `n.php`: double opt-in over your own SMTP, subscribers in SQLite
  (`content/newsletter.db`), RFC 8058 one-click unsubscribe (link + mail
  headers), honeypot + per-IP rate limit. `tools/newsletter_send.php` (cron,
  after the generator) mails each newly published essay to the confirmed list
  exactly once — the first run seeds the ledger with the existing archive so
  nobody gets the whole back-catalog. Stores the address and two timestamps,
  nothing else.
- **SEO / AI-ready** — canonical URLs, OpenGraph + article meta, JSON-LD
  (BlogPosting, Breadcrumbs, Blog, ItemList, CollectionPage), `llms.txt`,
  hardened `.htaccess` with security headers and CSP.

## Layout

```
├── index.php            homepage (hand-written)
├── services/            services & engagements page (hand-written)
├── comments.php         comment endpoint + rendered thread (dynamic island)
├── n.php                newsletter endpoint (subscribe / confirm / unsubscribe)
├── a.php                analytics beacon (pageviews + engaged-time pings)
├── stats.php            back-compat shim → admin.php (mints the stats session)
├── admin.php            the one dashboard: pipeline, newsletter, comments, analytics
├── config.example.php   copy to config.php and fill in — all secrets live there
├── lib/                 config, helpers, markdown, content model, templates,
│                        analytics, geoip, admin core
├── tools/               build.php (generator), geoip_update.php (country db),
│                        analytics_prune.php (what counts and what does not),
│                        fetch_images.sh (CC image fetcher),
│                        preview_admin.php (throwaway local copy of admin, never deployed)
├── docs/                strategy: improvement plan, action plan, topic engine,
│                        post prompt, scoring rubric
├── drafts/              working area: unpublished drafts, diagrams, review docs (never deployed)
├── content/posts/       markdown sources: YYYY-MM-DD-slug.md
├── content/comments/    runtime comment JSON (gitignored)
├── content/score-notes.json  per-axis evidence quotes behind each score
├── content/scores.db    mirror of the post scores (gitignored; rebuilt by build.php)
├── content/geoip.dat    compiled IP→country db (gitignored; tools/geoip_update.php)
├── assets/              css + post images (+ credits.json with licenses)
├── blog/                generated output (gitignored)
├── tests/               dependency-free unit + integration suite
└── deploy.sh            tests → build → geoip → backup remote → rsync to hosting
```

## Quickstart

```bash
cp config.example.php config.php   # fill in secrets: openssl rand -hex 24
php tools/build.php                # generate the site
php -S 127.0.0.1:8080              # serve locally
./run.sh                           # …or the docker-compose equivalent
php tests/run.php                  # 1,900+ checks: unit, integration, deploy, CI scripts
```

## Writing a post

Create `content/posts/YYYY-MM-DD-my-slug.md`:

```markdown
# Title

*One-line subtitle that doubles as the meta description.*

Body in markdown (h2, lists, quotes, code fences, links)…

---

**Medium:** https://medium.com/... (optional cross-post link)

**DevTo:** https://dev.to/... (optional cross-post link)

**Scores:** tension=7 originality=6 personal=9 contrarian=5 substance=8 punch=4 trend=3
**Scored:** 2026-07-27 rubric=v1 model=claude-opus-5

**Related:** other-slug, another-slug (optional; the author's "keep reading" picks)

**Tags:** Tag One, Tag Two, Tag Three, Tag Four, Tag Five
```

`**Related:**` lists up to three slugs shown under the essay as "Picked by the author", in the
order written; without it the block falls back to shared tags, which is coarse because most
essays share *Software Architecture*. A post that belongs to a thread goes into `SERIES` in
`lib/blog.php`: the essay then carries a series box after the text and a "Series, part N" label
in every list. The homepage lists the newest `HOME_LATEST_COUNT` (10) essays and links `/blog/archive/` for the rest.

Optionally drop a hero image at `assets/img/posts/my-slug.jpg` (the build picks it up,
generates dimensions and uses the `.thumb.jpg` variant in lists), then `php tools/build.php`.

Score the draft against [docs/SCORING-RUBRIC.md](docs/SCORING-RUBRIC.md) before publishing.
The meta lines are stripped from the rendered page, the feed, and the markdown mirrors, so
the scores stay private to `/admin.php`. The build prints any published post that has none.

## Drafts, scheduling, publishing

A post is in exactly one of three states, derived from the markdown itself:

| State | How | Behavior |
|---|---|---|
| **draft** | `**Status:** draft` line | never built, never listed; visible in `/admin.php` |
| **scheduled** | publish moment in the future | built + listed automatically once the time passes |
| **published** | publish moment in the past | live everywhere |

The publish moment is the filename date at **09:00** (site timezone, config
`timezone`), or an explicit line:

```markdown
**Publish:** 2026-08-17 08:30
```

`tools/build.php` renders only published posts and prints the waiting queue.
For scheduled posts to flip live unattended, run the generator on the server
via cron (MyDevil panel or `crontab -e`):

```
*/15 * * * * cd ~/domains/bfrackowiak.pl/public_html && php tools/build.php >/dev/null 2>&1 && php tools/newsletter_send.php >> content/newsletter.log 2>&1
```

The admin **"Rebuild site now"** button does the same on demand. `deploy.sh`
prints the queue after every deploy so nothing waits unnoticed.

## Admin & analytics

- `/admin.php` — the one dashboard, tabbed: publishing pipeline (statuses,
  go-live countdowns, one-click rebuild), newsletter (list health, weekly growth,
  send ledger, subscriber moderation), comment moderation, system health — plus
  the full analytics view: daily + hour-of-day charts, top pages with engaged
  reading time, source groups (search / social / AI / news / campaigns),
  referrers, countries (offline GeoIP), browsers/OS/devices/screens, languages,
  timezones, UTM campaigns, and a recent-visits feed. Every table narrows to a
  single page via the path drilldown. Sign-in: `admin_user` + `admin_password_hash`
  in config (`php -r "echo password_hash('pw', PASSWORD_DEFAULT), PHP_EOL;"`), then
  a one-time code when `admin_totp_secret` holds a TOTP secret for an authenticator
  app (`php tools/admin_totp.php --new` makes one; without `--new` it prints the
  code valid right now — the way back in when the phone is gone). Sessions are
  cookie-hardened, pinned to the network they were opened from, and end after
  2 h idle; actions are CSRF-protected.
  Analytics-only access without the password: `/stats.php?key=<stats_key>` mints
  a read-only stats session and redirects into the analytics tabs.
- Who gets to the login form, from where: `admin_allow_countries` (e.g. `['PL']`)
  closes the admin to every other country, judged by the offline GeoIP database on
  every request, so a session cookie carried abroad stops working. Five wrong
  attempts lock an address for 15 min, four times longer on each repeat; thirty
  failures from anywhere inside ten minutes freeze the form for everyone for
  15 min (your own networks from `admin_trusted_ips` excepted — from those the
  password alone signs in, the code is asked everywhere else). Turnstile
  guards the form when configured. Every attempt lands in `content/auth.log`
  (listed under **Admin → System**, pulled home by `deploy.sh data`), and every
  sign-in or lockout mails `smtp.to` (`admin_alerts`).
- Self-exclusion: logging into `/admin.php` (or presenting the stats key) plants a
  1-year `bfx` cookie that keeps that browser out of the analytics — refreshed
  on every admin visit. Manual toggle per device: `/a.php?me=off` / `/a.php?me=on`.
  Enforced twice: the beacon doesn't send, and the endpoint drops carried hits.
- Self-exclusion by address: `analytics_exclude_ips` in `config.php` takes plain
  addresses or CIDR blocks, IPv4 and IPv6 (`['203.0.113.7', '2001:db8::/32']`).
  Covers every browser and device on a connection, which the cookie cannot: it
  dies with an incognito window or a cleared profile. The address is compared in
  memory on the beacon and is never stored, so nothing about the privacy model
  changes. **Admin → System** shows the address you are connecting from and
  whether it is currently excluded, so you can copy it straight into the config.
- What does not count: crawlers naming themselves are dropped at the door, but
  headless browsers ship a stock user agent and do run the beacon. Those are
  written and marked instead — a browser reporting plain `UTC` is running on a
  server, since every real device reports a region zone. A desktop OS on a phone-sized screen is an emulated viewport and is marked the same way. Marked rows stay in the
  table and out of every total, visible in **Recent visits** with the verdict, so
  the rule can be tuned rather than trusted blindly.
  `php tools/analytics_prune.php --list` shows the split; `--rescan` re-judges
  history after a rule change; `--owner --from=… --to=… --tz=… --os=…` marks your
  own past browsing from before the address exclusion existed; `--restore` undoes
  any of it. Every mode counts first and writes only with `--apply`.
- Attribution: Medium, DEV, LinkedIn and mail clients all strip or hide the
  referrer, so an untagged cross-post link comes back indistinguishable from
  somebody typing the address. The pipeline row for each post carries copy-ready
  tagged links (`?utm_source=medium|devto|linkedin|newsletter`); `an_utm_group()`
  maps the tag back to the platform's own acquisition group, so a naked DEV click
  is still credited to DEV.
- "Daily visitors, summed" means exactly that: the visitor hash is salted per UTC
  day and unlinkable across days, so one reader on Monday and Thursday counts
  twice. Counting returning readers would need a durable identifier, which is the
  one thing this design refuses to store.
- Optional cross-check: point `analytics_access_log_dir` at the web server's daily
  logs (`YYYY-MM-DD.log`) and **Admin → System** compares rows written against
  `POST /a.php` requests logged. Requests should always be the larger number; rows
  exceeding them means one of the two sources is dropping events.
- GeoIP: `php tools/geoip_update.php` downloads the free DB-IP country database
  (CC BY 4.0, ~15 MB compiled) into `content/geoip.dat`. Re-run monthly — locally
  before a deploy, or straight on the server. Lookups are offline `fseek` binary
  search; no extension, no external calls, no IP ever stored.

## Deploying

`deploy.sh` reads its SSH target from a gitignored `deploy.env` (copy
`deploy.env.example`), runs the tests, rebuilds, refreshes the GeoIP database,
**backs up the remote docroot** to `<timestamp>.tar.gz` (into `_remote-backup/`,
or onward to `HOME_BACKUP_TARGET` when `deploy.env` sets one), then rsyncs and
finishes with the smoke checks. Runtime data on the server (comments, analytics,
subscribers, scores mirror) is excluded in both directions and can never be
overwritten. `./deploy.sh help` is the reference.

| Command | What it does |
|---|---|
| `push` | The default, so a bare `./deploy.sh` still deploys |
| `pull` | Post sources and review notes come home, briefs are written |
| `data` | The live site's runtime data comes home; nothing is deployed |
| `status` | Do both sides hold the same post sources and notes? Reads only |
| `smoke [url] [--sha=…] [--local]` | Pages, feed, sitemap, 404s, hidden files, security headers, certificate, and the live commit in `build.txt` |
| `rollback <archive.tar.gz>` | Puts a pre-deploy backup back: code, pages and post sources as they were, runtime data as it is now. The server is backed up first |
| `backup` | The pre-deploy backup on its own, without deploying |
| `install-remote` | Copies `ci/remote/*` (the server-side helpers) into `~/bin` on the server; push, pull and data do it on their own |

Modifiers: `-n/--dry-run` previews, `--delete` also removes remotely deleted
files, `--no-geoip` skips the refresh, `--force` pushes over a server that has
moved on, `-v/--verbose` streams every command.

Exit codes: `0` done, `1` failed, `2` bad arguments, `3` the server is ahead
(pull first), `4` another deploy holds the lock on the server.

Since `review.php` lets the server edit post sources too, both sides can write
`content/posts/`. Every pull and successful push records on the server what the
two agreed on, and a push from any machine **stops** rather than overwriting a
server that has changed since:

    read the post on the site, leave notes, fix small things
    ./deploy.sh pull            posts and notes come home, briefs are written
    claude code                 works from drafts/review/, resolves each note
    ./deploy.sh                 the result and the history go back up
                                (or commit and push: the pipeline deploys main)

One deploy runs at a time: the laptop and the pipeline take the same lock on the
server, and a second one exits with `4`. A deploy from the laptop warns when it
ships uncommitted changes or a commit that is not on `main` yet, because the
next pipeline deploy replaces it.

`./deploy.sh data` downloads the live site's runtime data into
`_remote-logs/<timestamp>/` (`DEPLOY_LOGS_DIR` moves it), mirroring the
server's layout: `analytics.db` (every visit ever recorded), `newsletter.db`,
`scores.db`, `content/comments/*.json` and the cron's `content/*.log`. The
databases are copied with `VACUUM INTO` **on the server**, so a WAL-mode
database being written to by live traffic still arrives as a consistent single
file. Set `REMOTE_LOG_DIR` in `deploy.env` to pull the host's own access and
error logs alongside it.

`content/geoip.dat` is the one data file that ships *from* your machine, so a
stale local copy would overwrite a fresher one on the server. The deploy
re-runs `tools/geoip_update.php` whenever the local database is older than
`GEOIP_MAX_AGE_DAYS` (default 7, set it in `deploy.env`; DB-IP publishes
monthly). If that refresh cannot run or fails, the file is excluded from the
sync instead, so the server's copy is never replaced by an older one.

## CI/CD

The repository of record is a private Gitea; this GitHub repository is its
public mirror. A self-hosted Gitea runner, in a container with the production
PHP version, runs the pipeline on every push:

| Job | When | What |
|---|---|---|
| `checks` | every push | PHP lint, the test suite, a build, the site check (links, feeds, sitemap, post metadata, nothing unpublished in the output), the editorial report |
| `public-export` | every push | The public history of this commit, through the publication gate, into a private preview |
| `dry-run` | branches | What a deploy of the branch would change on the server |
| `deploy` | `main` | Stops if the server has edits not yet pulled, backs the server up, deploys, smoke checks the live commit, prunes old backups |
| `publish` | `main`, after `deploy` | The gated public history onto GitHub, fast-forward only |

By hand from the Gitea UI: `deploy-manual` (any ref, dry run by default),
`rollback` (lists the archives; acts only with `confirm=ROLLBACK`) and
`publish-github`. On a schedule: `nightly` copies the site's data every night,
checks every database, takes a copy of the whole docroot on Mondays and prunes
(14 nightly copies plus one a month for a year, 8 weekly ones); `restore-test`
restores the newest copy once a month, serves it with `php -S` and runs the
smoke checks against it.

The pipeline's key to the host is pinned to one command (`ci/remote/blog-gate`):
rsync inside the blog's directory, and the helper's own subcommands, nothing
else, no shell. It is still a deploy key: whatever PHP it deploys runs as the
hosting account's user, so it is guarded like one.

**The public history.** GitHub gets the real history of `main`, filtered with
`git filter-repo`: an allowlist of public paths (anything new is private until
it is listed), private paths dropped once more, host names and home paths
replaced, the author mapped to the GitHub noreply address. The rewrite is
deterministic, so every export fast-forwards the last one. Before anything
leaves, a gate reads every commit and stops on a private path, a gitleaks
finding, an infrastructure name, a value from the real `config.php`, an e-mail
address outside an allowlist, or a blob of 50 MB or more.

**How the writing shows in it.** Posts are written in two passes: I outline,
AI turns the outline into native English, and each step is its own commit.
Outlines live in `content/outlines/<slug>.md`, which never ships to the server.

| Prefix | Commit |
|---|---|
| `Outline: <title>` | The outline |
| `Draft: <title>` | The post, `**Status:** draft` |
| `Native pass: <title>` | An AI pass for native English, with a `Co-Authored-By` trailer |
| `Voice check: <title>` | An AI pass against the voice rules, with a `Co-Authored-By` trailer |
| `Publish: <title>` | A date set, the draft status gone |

## License

Code: [MIT](LICENSE). Blog content (`content/posts/`) and the site's personal branding
are **not** covered by the MIT license — all rights reserved. Post header images are
CC-licensed third-party photographs; per-image attribution lives in
`assets/img/posts/credits.json` and is rendered on each post.
