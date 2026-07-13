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

# What ships: index.php, comments.php, a.php, stats.php, .htaccess, robots.txt,
# llms*.txt, sitemap.xml, feed.xml, favicons, photo, assets/, blog/, vendor/,
# content/posts/ (comment-slug validation reads it) and content/.htaccess.
# What never ships: docs, tools, archives, backups, git, runtime data.
rsync -avz --progress "${RSYNC_MODE[@]}" "${DELETE_FLAG[@]}" \
  --exclude='.git/' \
  --exclude='.gitignore' \
  --exclude='.claude/' \
  --exclude='deploy.sh' \
  --exclude='docs/' \
  --exclude='tools/' \
  --exclude='tests/' \
  --exclude='README.md' \
  --exclude='LICENSE' \
  --exclude='config.example.php' \
  --exclude='_archive/' \
  --exclude='_remote-backup/' \
  --exclude='content/comments/' \
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
