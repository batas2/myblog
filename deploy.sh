#!/usr/bin/env bash
set -euo pipefail

# deploy.sh — build, back up the remote, push bfrackowiak.pl to MyDevil.
#
# Usage:
#   ./deploy.sh             refresh geoip + build + backup remote + rsync
#                           (additive, no deletions)
#   ./deploy.sh --clean     same, but also delete remote files that no longer
#                           exist locally (runtime data stays protected)
#   ./deploy.sh --dry-run   show what would be transferred, touch nothing
#   ./deploy.sh --no-geoip  skip the geoip refresh (offline deploys: the
#                           updater would otherwise wait out its own timeout)
#
# Runtime data on the server is NEVER overwritten or deleted:
# content/comments/*.json, content/analytics.db, content/analytics.seed,
# content/newsletter.db, content/scores.db, content/.sessions/

# --- config ---
# The deployment target lives in deploy.env (gitignored) — copy
# deploy.env.example and fill in your host. Required vars:
#   SSH_USER, SSH_HOST, SSH_PORT, REMOTE_DIR
LOCAL_DIR="$(cd "$(dirname "$0")" && pwd)/"
if [ ! -f "${LOCAL_DIR}deploy.env" ]; then
  echo "Missing deploy.env — copy deploy.env.example and fill in your SSH target." >&2
  exit 1
fi
# shellcheck source=deploy.env.example
source "${LOCAL_DIR}deploy.env"
: "${SSH_USER:?deploy.env must set SSH_USER}"
: "${SSH_HOST:?deploy.env must set SSH_HOST}"
: "${SSH_PORT:=22}"
: "${REMOTE_DIR:?deploy.env must set REMOTE_DIR}"
BACKUP_DIR="${LOCAL_DIR}_remote-backup"
TIMESTAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP_STAGE="${BACKUP_DIR}/${TIMESTAMP}"
BACKUP_ARCHIVE="${BACKUP_DIR}/${TIMESTAMP}.tar.gz"

# --- colors ---
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; BLUE='\033[0;34m'; BOLD='\033[1m'; NC='\033[0m'

# --- output helpers ---
STEP_NUM=0
STEP_TOTAL=5
STEP_START=0
step() {
  STEP_NUM=$((STEP_NUM + 1))
  STEP_START=$SECONDS
  echo
  echo -e "${BLUE}${BOLD}[${STEP_NUM}/${STEP_TOTAL}] $1${NC}"
}
step_done() {
  local elapsed=$((SECONDS - STEP_START))
  echo -e "${GREEN}✓ $1 (${elapsed}s)${NC}"
}
die() { echo -e "${RED}✗ $1${NC}"; exit 1; }

DELETE_FLAG=()
RSYNC_MODE=()
SKIP_GEOIP=0
for arg in "$@"; do
  case "$arg" in
    --clean)    DELETE_FLAG=(--delete) ;;
    --dry-run)  RSYNC_MODE=(--dry-run) ;;
    --no-geoip) SKIP_GEOIP=1 ;;
    *) die "Unknown option: ${arg}" ;;
  esac
done
[ ${#RSYNC_MODE[@]} -eq 0 ] || STEP_TOTAL=4   # --dry-run skips the backup step

echo -e "${BOLD}Deploying bfrackowiak.pl${NC}"
echo -e "  target: ${SSH_USER}@${SSH_HOST}:${REMOTE_DIR}"
echo -e "  mode:   $([ ${#RSYNC_MODE[@]} -eq 0 ] && echo 'live' || echo 'dry-run')$([ ${#DELETE_FLAG[@]} -eq 0 ] || echo ', clean (deletes stale remote files)')"

# --- sanity: config must exist (endpoints read it at runtime) ---
if [ ! -f "${LOCAL_DIR}config.php" ]; then
  die "Missing config.php — copy config.example.php and fill it in."
fi

# --- 1. tests ---
step "Running test suite"
php "${LOCAL_DIR}tests/run.php"
step_done "Tests passed"

# --- 2. build ---
step "Building site (php tools/build.php)"
php "${LOCAL_DIR}tools/build.php"
step_done "Build complete"

# --- 3. geoip database ---
# content/geoip.dat ships with everything else, so whatever is on this machine
# lands on the server. A stale local copy therefore *overwrites* a fresher
# remote one. Refresh it here so the deploy always carries the newest data, and
# whenever the refresh does not happen, exclude the file rather than regress the
# server. DB-IP publishes monthly; GEOIP_MAX_AGE_DAYS (deploy.env) decides how
# eagerly we re-fetch inside that month.
step "Refreshing content/geoip.dat"
: "${GEOIP_MAX_AGE_DAYS:=7}"
# hand-edited in deploy.env: a typo must not silently disable the age check
[[ "${GEOIP_MAX_AGE_DAYS}" =~ ^[0-9]+$ ]] || GEOIP_MAX_AGE_DAYS=7
GEOIP_PROTECT=()
GEOIP_AGE="$(cd "${LOCAL_DIR}" && php -r '
require "lib/config.php"; require "lib/geoip.php";
echo geoip_db_age_days() ?? -1;
' 2>/dev/null || true)"
[[ "${GEOIP_AGE}" =~ ^-?[0-9]+$ ]] || GEOIP_AGE=-1
[ "${GEOIP_AGE}" -ge 0 ] && GEOIP_WAS="${GEOIP_AGE}d old" || GEOIP_WAS="missing"

if [ "${GEOIP_AGE}" -ge 0 ] && [ "${GEOIP_AGE}" -lt "${GEOIP_MAX_AGE_DAYS}" ]; then
  step_done "Current (${GEOIP_WAS}, threshold ${GEOIP_MAX_AGE_DAYS}d)"
elif [ "${SKIP_GEOIP}" -eq 1 ] || [ ${#RSYNC_MODE[@]} -ne 0 ]; then
  # --no-geoip or --dry-run: never touch the network or the local file, and
  # never push the copy we just declined to verify
  GEOIP_PROTECT=(--exclude='content/geoip.dat')
  step_done "Skipped (${GEOIP_WAS}) — remote copy left untouched"
elif (cd "${LOCAL_DIR}" && php tools/geoip_update.php); then
  step_done "Updated (was ${GEOIP_WAS})"
else
  # geoip_compile writes through a temp file and renames, and a failed download
  # exits before touching anything, so the old database is always still intact
  # here. Pushing it could still replace a newer copy on the server, so protect
  # the remote one and carry on: country lookup is not worth failing a deploy.
  GEOIP_PROTECT=(--exclude='content/geoip.dat')
  echo -e "${YELLOW}⚠ geoip refresh failed (local copy: ${GEOIP_WAS})${NC}"
  echo -e "  the existing database was not modified and the remote one is protected"
  echo -e "  deploy continues; retry later with: php tools/geoip_update.php"
fi

# --- 4. backup remote -> local archive (skipped on --dry-run) ---
if [ ${#RSYNC_MODE[@]} -eq 0 ]; then
  step "Backing up remote -> ${BACKUP_ARCHIVE}"
  mkdir -p "${BACKUP_STAGE}"

  rsync -az --progress \
    -e "ssh -p ${SSH_PORT}" \
    "${SSH_USER}@${SSH_HOST}:${REMOTE_DIR}/" \
    "${BACKUP_STAGE}/"

  # sanity: an (almost) empty remote means something is very wrong — pushing over
  # it would make the loss permanent and silent (this exact thing ate the
  # analytics history on 2026-07-19). Stop and let a human look first.
  BACKUP_FILES="$(find "${BACKUP_STAGE}" -type f | wc -l | tr -d ' ')"
  if [ "${BACKUP_FILES}" -lt 20 ]; then
    die "Remote backup contains only ${BACKUP_FILES} files — the remote looks empty or wrong. Investigate before deploying (backup kept at ${BACKUP_STAGE})."
  fi

  tar -czf "${BACKUP_ARCHIVE}" -C "${BACKUP_DIR}" "${TIMESTAMP}"
  rm -rf "${BACKUP_STAGE}"

  BACKUP_SIZE="$(du -h "${BACKUP_ARCHIVE}" | cut -f1)"
  step_done "Backup saved (${BACKUP_SIZE}, ${BACKUP_FILES} files)"
fi

# --- 5. deploy ---
step "Syncing to ${SSH_USER}@${SSH_HOST}:${REMOTE_DIR}"

# What ships: index.php, comments.php, a.php, stats.php, admin.php, .htaccess,
# robots.txt, llms*.txt, sitemap.xml, feed.xml, favicons, photo, assets/, blog/,
# vendor/, lib/, tools/ (the server cron + admin "rebuild now" run tools/build.php),
# content/posts/ (the generator + comment-slug validation read it),
# content/geoip.dat (offline IP→country db) and content/.htaccess.
# What never ships: docs, drafts, tests, archives, backups, git, runtime data.
RSYNC_LOG="$(mktemp)"
rsync -az --progress --stats "${RSYNC_MODE[@]}" "${DELETE_FLAG[@]}" "${GEOIP_PROTECT[@]}" \
  --exclude='.git/' \
  --exclude='.gitignore' \
  --exclude='.claude/' \
  --exclude='deploy.sh' \
  --exclude='docs/' \
  --exclude='drafts/' \
  --exclude='tests/' \
  --exclude='README.md' \
  --exclude='LICENSE' \
  --exclude='config.example.php' \
  --exclude='_archive/' \
  --exclude='_remote-backup/' \
  --exclude='content/comments/' \
  --exclude='content/.sessions/' \
  --exclude='content/.buildlock' \
  --exclude='Dockerfile' \
  --exclude='docker-compose.yml' \
  --exclude='run.sh' \
  --exclude='content/analytics.db' \
  --exclude='content/analytics.db-*' \
  --exclude='content/analytics.seed' \
  --exclude='content/newsletter.db' \
  --exclude='content/newsletter.db-*' \
  --exclude='content/scores.db' \
  --exclude='content/scores.db-*' \
  --exclude='*.log' \
  --exclude='.DS_Store' \
  -e "ssh -p ${SSH_PORT}" \
  "${LOCAL_DIR}" \
  "${SSH_USER}@${SSH_HOST}:${REMOTE_DIR}" \
  | tee "${RSYNC_LOG}"

XFERRED="$(grep -m1 'Number of regular files transferred' "${RSYNC_LOG}" | grep -oE '[0-9,]+$' || echo '?')"
rm -f "${RSYNC_LOG}"
step_done "Synced (${XFERRED} files transferred)"

echo
if [ ${#RSYNC_MODE[@]} -eq 0 ]; then
  echo -e "${GREEN}${BOLD}Deploy done: https://bfrackowiak.pl${NC}"
else
  echo -e "${GREEN}${BOLD}Dry run done — nothing was touched.${NC}"
fi
echo -e "Post-deploy checks:"
echo -e "  curl -s -o /dev/null -w '%{http_code}' https://bfrackowiak.pl/"
echo -e "  curl -s -o /dev/null -w '%{http_code}' https://bfrackowiak.pl/blog/"
echo -e "  stats: https://bfrackowiak.pl/stats.php?key=<STATS_KEY>"
echo -e "  admin: https://bfrackowiak.pl/admin.php"

# --- scheduled-posts reminder ---
# A scheduled post only goes live when tools/build.php runs on the server after
# its publish time — via the cron below, or the admin "Rebuild site now" button.
QUEUE="$(cd "${LOCAL_DIR}" && php -r '
require "lib/config.php"; require "lib/helpers.php"; require "lib/markdown.php"; require "lib/blog.php";
foreach (load_posts(true) as $p) if ($p["status"] === "scheduled")
    echo "  " . $p["slug"] . " -> " . date("Y-m-d H:i", $p["publish_ts"]) . "\n";
' 2>/dev/null || true)"
if [ -n "${QUEUE}" ]; then
  echo
  echo -e "${YELLOW}Scheduled posts waiting to go live:${NC}"
  echo "${QUEUE}"
  echo -e "Make sure the server cron is set (MyDevil panel or crontab -e):"
  echo -e "  */15 * * * * cd ${REMOTE_DIR} && php tools/build.php >/dev/null 2>&1 && php tools/newsletter_send.php >> content/newsletter.log 2>&1"
fi

echo
echo -e "${BOLD}Total time: $((SECONDS))s${NC}"
