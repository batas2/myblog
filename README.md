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
│                        fetch_images.sh (CC image fetcher)
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
php tests/run.php                  # 500+ assertions, unit + integration
```

## Writing a post

Create `content/posts/YYYY-MM-DD-my-slug.md`:

```markdown
# Title

*One-line subtitle that doubles as the meta description.*

Body in markdown (h2, lists, quotes, code fences, links)…

---

**Medium:** https://medium.com/... (optional cross-post link)

**Scores:** tension=7 originality=6 personal=9 contrarian=5 substance=8 punch=4 trend=3
**Scored:** 2026-07-27 rubric=v1 model=claude-opus-5

**Tags:** Tag One, Tag Two, Tag Three, Tag Four, Tag Five
```

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
  single page via the path drilldown. Password login: `admin_password_hash` in
  config (`php -r "echo password_hash('pw', PASSWORD_DEFAULT), PHP_EOL;"`).
  Sessions are cookie-hardened; login is rate-limited; actions are CSRF-protected.
  Analytics-only access without the password: `/stats.php?key=<stats_key>` mints
  a read-only stats session and redirects into the analytics tabs.
- Self-exclusion: logging into `/admin.php` (or presenting the stats key) plants a
  1-year `bfx` cookie that keeps that browser out of the analytics — refreshed
  on every admin visit. Manual toggle per device: `/a.php?me=off` / `/a.php?me=on`.
  Enforced twice: the beacon doesn't send, and the endpoint drops carried hits.
- GeoIP: `php tools/geoip_update.php` downloads the free DB-IP country database
  (CC BY 4.0, ~15 MB compiled) into `content/geoip.dat`. Re-run monthly — locally
  before a deploy, or straight on the server. Lookups are offline `fseek` binary
  search; no extension, no external calls, no IP ever stored.

## Deploying

`deploy.sh` reads its SSH target from a gitignored `deploy.env` (copy
`deploy.env.example`), runs the tests, rebuilds, refreshes the GeoIP database,
**backs up the remote docroot to a local `_remote-backup/<timestamp>.tar.gz`**,
then rsyncs. Runtime data on the server (comments, analytics, subscribers,
scores mirror) is excluded in both directions and can never be overwritten.
`--dry-run` previews, `--clean` also removes remotely deleted files,
`--no-geoip` skips the refresh.

`content/geoip.dat` is the one data file that ships *from* your machine, so a
stale local copy would overwrite a fresher one on the server. The deploy
re-runs `tools/geoip_update.php` whenever the local database is older than
`GEOIP_MAX_AGE_DAYS` (default 7, set it in `deploy.env`; DB-IP publishes
monthly). If that refresh cannot run or fails, the file is excluded from the
sync instead, so the server's copy is never replaced by an older one.

## License

Code: [MIT](LICENSE). Blog content (`content/posts/`) and the site's personal branding
are **not** covered by the MIT license — all rights reserved. Post header images are
CC-licensed third-party photographs; per-image attribution lives in
`assets/img/posts/credits.json` and is rendered on each post.
