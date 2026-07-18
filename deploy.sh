#!/usr/bin/env bash
set -euo pipefail

# deploy.sh — build, back up the remote, push bfrackowiak.pl to MyDevil.
#
# Usage:
#   ./deploy.sh            build + backup remote + rsync (additive, no deletions)
#   ./deploy.sh --clean    same, but also delete remote files that no longer
#                          exist locally (runtime data stays protected)
#   ./deploy.sh --dry-run  show what would be transferred, touch nothing
#
# Runtime data on the server is NEVER overwritten or deleted:
# content/comments/*.json, content/analytics.db, content/analytics.seed

# --- config ---
SSH_USER="bfrackowiak"
SSH_HOST="server.example.com"
SSH_PORT="22"
REMOTE_DIR="/home/bfrackowiak/domains/bfrackowiak.pl/public_html"
LOCAL_DIR="$(cd "$(dirname "$0")" && pwd)/"
BACKUP_DIR="${LOCAL_DIR}_remote-backup"
TIMESTAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP_STAGE="${BACKUP_DIR}/${TIMESTAMP}"
BACKUP_ARCHIVE="${BACKUP_DIR}/${TIMESTAMP}.tar.gz"

# --- colors ---
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; NC='\033[0m'

DELETE_FLAG=()
RSYNC_MODE=()
for arg in "$@"; do
  case "$arg" in
    --clean)   DELETE_FLAG=(--delete) ;;
    --dry-run) RSYNC_MODE=(--dry-run) ;;
    *) echo -e "${RED}Unknown option: ${arg}${NC}"; exit 1 ;;
  esac
done

# --- sanity: config must exist (endpoints read it at runtime) ---
if [ ! -f "${LOCAL_DIR}config.php" ]; then
  echo -e "${RED}Missing config.php — copy config.example.php and fill it in.${NC}"; exit 1
fi

# geoip.dat may live only on the server (generated there by tools/geoip_update.php);
# when there is no local copy, protect the remote one from --clean deletion
GEOIP_PROTECT=()
if [ ! -f "${LOCAL_DIR}content/geoip.dat" ]; then
  GEOIP_PROTECT=(--exclude='content/geoip.dat')
fi

# --- 0. tests ---
echo -e "${YELLOW}Running test suite${NC}"
php "${LOCAL_DIR}tests/run.php"

# --- 1. build ---
echo -e "${YELLOW}Building site (php tools/build.php)${NC}"
php "${LOCAL_DIR}tools/build.php"

# --- 2. backup remote -> local archive (skipped on --dry-run) ---
if [ ${#RSYNC_MODE[@]} -eq 0 ]; then
  mkdir -p "${BACKUP_STAGE}"
  echo -e "${YELLOW}Backing up ${SSH_USER}@${SSH_HOST}:${REMOTE_DIR} -> ${BACKUP_ARCHIVE}${NC}"

  rsync -avz --progress \
    -e "ssh -p ${SSH_PORT}" \
    "${SSH_USER}@${SSH_HOST}:${REMOTE_DIR}/" \
    "${BACKUP_STAGE}/"

  tar -czf "${BACKUP_ARCHIVE}" -C "${BACKUP_DIR}" "${TIMESTAMP}"
  rm -rf "${BACKUP_STAGE}"

  echo -e "${GREEN}Backup saved: ${BACKUP_ARCHIVE}${NC}"
else
  echo -e "${YELLOW}--dry-run: skipping remote backup${NC}"
fi

# --- 3. deploy ---
echo -e "${YELLOW}Deploying to ${SSH_USER}@${SSH_HOST}:${REMOTE_DIR}${NC}"

# What ships: index.php, comments.php, a.php, stats.php, admin.php, .htaccess,
# robots.txt, llms*.txt, sitemap.xml, feed.xml, favicons, photo, assets/, blog/,
# vendor/, lib/, tools/ (the server cron + admin "rebuild now" run tools/build.php),
# content/posts/ (the generator + comment-slug validation read it),
# content/geoip.dat (offline IP→country db) and content/.htaccess.
# What never ships: docs, drafts, tests, archives, backups, git, runtime data.
rsync -avz --progress "${RSYNC_MODE[@]}" "${DELETE_FLAG[@]}" "${GEOIP_PROTECT[@]}" \
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
  --exclude='srvstats/' \
  --exclude='content/analytics.db' \
  --exclude='content/analytics.db-*' \
  --exclude='content/analytics.seed' \
  --exclude='*.log' \
  --exclude='.DS_Store' \
  -e "ssh -p ${SSH_PORT}" \
  "${LOCAL_DIR}" \
  "${SSH_USER}@${SSH_HOST}:${REMOTE_DIR}"

echo -e "${GREEN}Deploy done: https://bfrackowiak.pl${NC}"
echo -e "Post-deploy checks:"
echo -e "  curl -s -o /dev/null -w '%{http_code}' https://bfrackowiak.pl/"
echo -e "  curl -s -o /dev/null -w '%{http_code}' https://bfrackowiak.pl/blog/"
echo -e "  stats: https://bfrackowiak.pl/stats.php?key=<STATS_KEY>"
echo -e "  admin: https://bfrackowiak.pl/admin.php"

# --- 4. scheduled-posts reminder ---
# A scheduled post only goes live when tools/build.php runs on the server after
# its publish time — via the cron below, or the admin "Rebuild site now" button.
QUEUE="$(cd "${LOCAL_DIR}" && php -r '
require "lib/config.php"; require "lib/helpers.php"; require "lib/markdown.php"; require "lib/blog.php";
foreach (load_posts(true) as $p) if ($p["status"] === "scheduled")
    echo "  " . $p["slug"] . " -> " . date("Y-m-d H:i", $p["publish_ts"]) . "\n";
' 2>/dev/null || true)"
if [ -n "${QUEUE}" ]; then
  echo -e "${YELLOW}Scheduled posts waiting to go live:${NC}"
  echo "${QUEUE}"
  echo -e "Make sure the server cron is set (MyDevil panel or crontab -e):"
  echo -e "  */15 * * * * cd ${REMOTE_DIR} && php tools/build.php >/dev/null 2>&1"
fi
