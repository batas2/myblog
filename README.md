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
- **Comments without a database** — JSON files per post, stateless HMAC math captcha,
  honeypot, time trap, per-IP rate limit. Commenter emails are stored but rendered
  masked (`j***@example.com`).
- **First-party analytics** — a `sendBeacon` endpoint writing to SQLite. No cookies;
  visitors are counted via a daily-rotating salted hash, so raw IPs are never stored
  and visitors are unlinkable across days. Private dashboard at `/stats.php?key=…`.
- **Newsletter capture** — plain HTML form posting to a Buttondown embed endpoint
  (configurable; hidden entirely when unconfigured).
- **SEO / AI-ready** — canonical URLs, OpenGraph + article meta, JSON-LD
  (BlogPosting, Breadcrumbs, Blog, ItemList, CollectionPage), `llms.txt`,
  hardened `.htaccess` with security headers and CSP.

## Layout

```
├── index.php            homepage (the only hand-written page)
├── comments.php         comment endpoint + rendered thread (dynamic island)
├── a.php                analytics beacon
├── stats.php            analytics dashboard (key-protected)
├── config.example.php   copy to config.php and fill in — all secrets live there
├── lib/                 config loader, helpers, markdown, content model, templates
├── tools/               build.php (generator), fetch_images.sh (CC image fetcher)
├── content/posts/       markdown sources: YYYY-MM-DD-slug.md
├── content/comments/    runtime comment JSON (gitignored)
├── assets/              css + post images (+ credits.json with licenses)
├── blog/                generated output (gitignored)
├── tests/               dependency-free unit + integration suite
└── deploy.sh            build → backup remote → rsync to shared hosting
```

## Quickstart

```bash
cp config.example.php config.php   # fill in secrets: openssl rand -hex 24
php tools/build.php                # generate the site
php -S 127.0.0.1:8080              # serve locally
php tests/run.php                  # 120+ assertions, unit + integration
```

## Writing a post

Create `content/posts/YYYY-MM-DD-my-slug.md`:

```markdown
# Title

*One-line subtitle that doubles as the meta description.*

Body in markdown (h2, lists, quotes, code fences, links)…

---

**Medium:** https://medium.com/... (optional cross-post link)

**Tags:** Tag One, Tag Two, Tag Three, Tag Four, Tag Five
```

Optionally drop a hero image at `assets/img/posts/my-slug.jpg` (the build picks it up,
generates dimensions and uses the `.thumb.jpg` variant in lists), then `php tools/build.php`.

## Deploying

`deploy.sh` runs the tests, rebuilds, **backs up the remote docroot to a local
`_remote-backup/<timestamp>.tar.gz`**, then rsyncs. Runtime data on the server
(comments, analytics) is excluded in both directions and can never be overwritten.
`--dry-run` previews, `--clean` also removes remotely deleted files.

## License

Code: [MIT](LICENSE). Blog content (`content/posts/`) and the site's personal branding
are **not** covered by the MIT license — all rights reserved. Post header images are
CC-licensed third-party photographs; per-image attribution lives in
`assets/img/posts/credits.json` and is rendered on each post.
