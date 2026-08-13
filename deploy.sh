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
#   ./deploy.sh --verbose   stream every command's raw output (-v works too).
#                           Without it the deploy prints one line per step and
#                           a summary; a failing step still dumps its full log.
#   ./deploy.sh --pull      bring post sources and review notes home, then
#                           write the briefs an agent works from
#   ./deploy.sh --force     push even when the server is ahead (last resort)
#   ./deploy.sh --pull-logs pull the live site's runtime data into
#                           _remote-logs/<timestamp>/ and exit without
#                           deploying anything. Read-only on the server.
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
LOGS_DIR="${LOCAL_DIR}_remote-logs"
LOGS_STAGE="${LOGS_DIR}/${TIMESTAMP}"

# --- colors ---
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; BLUE='\033[0;34m'; BOLD='\033[1m'; NC='\033[0m'

# --- output helpers ---
STEP_NUM=0
STEP_TOTAL=7   # +1 for the two-way guard
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
note() { echo -e "  ${1}"; }
warn() { echo -e "${YELLOW}⚠ $1${NC}"; }
die() { echo -e "${RED}✗ $1${NC}"; exit 1; }

DELETE_FLAG=()
RSYNC_MODE=()
SKIP_GEOIP=0
VERBOSE=0
PULL_LOGS=0
PULL_REVIEW=0
FORCE_PUSH=0
for arg in "$@"; do
  case "$arg" in
    --clean)    DELETE_FLAG=(--delete) ;;
    --dry-run)  RSYNC_MODE=(--dry-run) ;;
    --no-geoip) SKIP_GEOIP=1 ;;
    --verbose|-v) VERBOSE=1 ;;
    --pull-logs) PULL_LOGS=1 ;;
    --pull)     PULL_REVIEW=1 ;;
    --force)    FORCE_PUSH=1 ;;
    *) die "Unknown option: ${arg}" ;;
  esac
done

# --- quiet-by-default plumbing ---
# Every noisy command runs through run(): verbose streams it, quiet captures it.
# Nothing is ever lost — a failing step replays its whole log before dying, so
# the clean output only ever hides successful noise.
LOG_DIR="$(mktemp -d)"
# --pull-logs stages database snapshots in a directory on the server; clear it
# on every exit path, including the failing ones, so a dead deploy never leaves
# a copy of the analytics database sitting in the docroot.
REMOTE_SNAP=""
cleanup() {
  rm -rf "${LOG_DIR}"
  [ -z "${REMOTE_SNAP}" ] || ssh -p "${SSH_PORT}" -o ConnectTimeout=10 \
    "${SSH_USER}@${SSH_HOST}" "rm -rf '${REMOTE_SNAP}'" >/dev/null 2>&1 || true
}
trap cleanup EXIT
RSYNC_PROGRESS=()
[ "${VERBOSE}" -eq 0 ] || RSYNC_PROGRESS=(--progress)

run() {
  local log="$1"; shift
  local rc=0
  if [ "${VERBOSE}" -eq 1 ]; then
    "$@" 2>&1 | tee "${log}" || rc=$?
  else
    "$@" >"${log}" 2>&1 || rc=$?
  fi
  return "${rc}"
}

# Replay a captured log (no-op in verbose mode, where it was already streamed).
dump_log() {
  [ "${VERBOSE}" -eq 0 ] || return 0
  [ -s "$1" ] || return 0
  echo
  sed 's/^/  │ /' "$1"
  echo
}

# run() failed: show what happened, then stop.
fail_with_log() { dump_log "$1"; die "$2"; }

# --- the two-way guard --------------------------------------------------------
# review.php writes post sources on the server, and a deploy pushes post sources up. Without a
# marker there is no way to tell "I edited locally, push it" from "I edited on the site and forgot
# to pull", and the second one silently destroys the afternoon's corrections.
#
# So every pull and every successful push records what both sides agreed on. A file whose remote
# hash differs from local AND from that record changed on the server since, and the push stops.
SYNC_STATE="${LOCAL_DIR}content/.sync-state"

# md5 of every post source and review note, one "hash  path" per line, sorted.
sync_hashes_local() {
  ( cd "${LOCAL_DIR}" && find content/posts content/review -type f \( -name '*.md' -o -name '*.json' \) 2>/dev/null \
    | sort | xargs -r md5sum 2>/dev/null ) || true
}
sync_hashes_remote() {
  ssh -p "${SSH_PORT}" -o ConnectTimeout=15 "${SSH_USER}@${SSH_HOST}" \
    "cd '${REMOTE_DIR}' 2>/dev/null && find content/posts content/review -type f \( -name '*.md' -o -name '*.json' \) 2>/dev/null | sort | xargs -r md5sum 2>/dev/null" || true
}
sync_state_write() { sync_hashes_local > "${SYNC_STATE}" 2>/dev/null || true; }

# --- --pull: bring the writing round trip home, then stop ---
# review.php edits post sources in place and stores margin notes next to them, so after a reading
# session the server holds the newer copy of both. This is the only thing that brings them back, and
# it must run before the next deploy or the push guard below will (correctly) refuse to start.
#
# Everything here is a plain file copy: no snapshot, no database, nothing on the server changes.
if [ "${PULL_REVIEW}" -eq 1 ]; then
  STEP_TOTAL=2
  DRY=0
  [ ${#RSYNC_MODE[@]} -eq 0 ] || DRY=1

  echo -e "${BOLD}Pulling post sources and review notes${NC}"
  echo -e "  source: ${SSH_USER}@${SSH_HOST}:${REMOTE_DIR}"
  echo -e "  target: ${LOCAL_DIR}content/"
  [ "${DRY}" -eq 0 ] || echo -e "  mode:   dry-run (nothing is written)"
  echo

  step "Downloading content/posts and content/review"
  run "${LOG_DIR}/pullreview.log" rsync -az "${RSYNC_PROGRESS[@]}" --itemize-changes "${RSYNC_MODE[@]}" \
    --include='/content/' \
    --include='/content/posts/' \
    --include='/content/posts/*.md' \
    --include='/content/review/' \
    --include='/content/review/*.json' \
    --exclude='*' \
    -e "ssh -p ${SSH_PORT}" \
    "${SSH_USER}@${SSH_HOST}:${REMOTE_DIR}/" \
    "${LOCAL_DIR}" \
    || fail_with_log "${LOG_DIR}/pullreview.log" "Downloading posts and review notes failed."

  CHANGED="$(grep -cE '^[<>ch][fL]' "${LOG_DIR}/pullreview.log" || true)"
  if [ "${DRY}" -eq 1 ]; then
    step_done "${CHANGED} file(s) would be updated"
  else
    step_done "${CHANGED} file(s) updated"
    sync_state_write
  fi

  step "Writing the briefs"
  if [ "${DRY}" -eq 1 ]; then
    step_done "skipped in dry-run"
  else
    php "${LOCAL_DIR}tools/review_export.php" || warn "review_export.php reported a problem"
    step_done "drafts/review/ is current"
  fi

  echo
  echo -e "${GREEN}${BOLD}Done.${NC} Point Claude Code at drafts/review/, then ./deploy.sh to push the result."
  exit 0
fi

# --- --pull-logs: bring the live site's runtime data home, then stop ---
# Runtime data is excluded from the sync in both directions, which also means a
# full deploy is the only thing that ever pulls it — and then only as part of a
# whole-docroot backup tarball, where a 33 KB database hides inside 35 MB of
# site. This mode fetches just the data worth analysing: the analytics database
# (every visit ever recorded — nothing is pruned or rotated), the subscriber
# list, the scores mirror, the comment JSON, and whatever the cron has been
# writing to content/newsletter.log.
#
# Nothing on the server is modified. Set REMOTE_LOG_DIR in deploy.env to pull
# the host's own access/error logs alongside it.
if [ "${PULL_LOGS}" -eq 1 ]; then
  STEP_TOTAL=2
  [ -z "${REMOTE_LOG_DIR:-}" ] || STEP_TOTAL=3
  DRY=0
  [ ${#RSYNC_MODE[@]} -eq 0 ] || DRY=1

  echo -e "${BOLD}Pulling runtime data from bfrackowiak.pl${NC}"
  echo -e "  source: ${SSH_USER}@${SSH_HOST}:${REMOTE_DIR}"
  echo -e "  target: ${LOGS_STAGE}/"
  [ "${DRY}" -eq 0 ] || echo -e "  mode:   dry-run (nothing is written)"

  # 1. consistent database snapshots, made on the server
  #
  # SQLite runs in WAL mode (an_db() sets it), so the .db file alone is a torn
  # read: recent writes live in the -wal sidecar until a checkpoint folds them
  # in, and rsyncing the three files one after another still races the live
  # beacon. VACUUM INTO writes a single consistent copy while readers and
  # writers carry on, so the server builds the snapshot and we fetch that.
  step "Snapshotting databases on the server"
  if [ "${DRY}" -eq 1 ]; then
    step_done "Skipped (dry run) — the server is never written to anyway"
  else
    cat > "${LOG_DIR}/snapshot.php" <<'SNAPSHOT_PHP'
<?php
// Piped to the server's php CLI by deploy.sh --pull-logs. Writes a consistent
// copy of each runtime database into $argv[1] and reports one line per file.
$dest = $argv[1] ?? '';
if ($dest === '' || !is_dir($dest)) {
    fwrite(STDERR, "snapshot destination is not a directory: {$dest}\n");
    exit(1);
}
$open = static function (string $src): PDO {
    $db = new PDO('sqlite:' . $src, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    // the live site is writing to these; wait rather than fail on a busy lock
    $db->exec('PRAGMA busy_timeout=10000');
    return $db;
};
foreach (['analytics.db', 'newsletter.db', 'scores.db'] as $name) {
    $src = 'content/' . $name;
    if (!is_file($src)) { echo "skip {$name} — not on the server\n"; continue; }
    $out = $dest . '/' . $name;
    $method = '';
    try {
        $db = $open($src);
        $db->exec('VACUUM INTO ' . $db->quote($out));
        $method = 'vacuum';
    } catch (Throwable $e) {
        // VACUUM INTO needs SQLite 3.27+. Older hosts get the next best thing:
        // fold the WAL back into the main file, then copy it. A checkpoint is
        // routine maintenance the database does on its own anyway.
        try {
            $db = $open($src);
            $db->exec('PRAGMA wal_checkpoint(TRUNCATE)');
            $db = null;
            if (!copy($src, $out)) throw new RuntimeException('copy failed');
            $method = 'checkpoint+copy';
        } catch (Throwable $e2) {
            echo "fail {$name} — {$e2->getMessage()}\n";
            continue;
        }
    }
    printf("ok %s %d %s\n", $name, (int) filesize($out), $method);
}
SNAPSHOT_PHP
    REMOTE_SNAP_PATH="${REMOTE_DIR}/content/.logpull.${TIMESTAMP}"
    run "${LOG_DIR}/snapmk.log" ssh -p "${SSH_PORT}" "${SSH_USER}@${SSH_HOST}" "mkdir -p '${REMOTE_SNAP_PATH}'" \
      || fail_with_log "${LOG_DIR}/snapmk.log" "Could not create the snapshot directory on the server."
    REMOTE_SNAP="${REMOTE_SNAP_PATH}"   # armed: cleanup() removes it from here on

    run "${LOG_DIR}/snapshot.log" bash -c \
      "ssh -p ${SSH_PORT} '${SSH_USER}@${SSH_HOST}' \"cd '${REMOTE_DIR}' && php -- '${REMOTE_SNAP}'\" < '${LOG_DIR}/snapshot.php'" \
      || fail_with_log "${LOG_DIR}/snapshot.log" "Snapshot failed on the server — nothing was pulled."

    SNAP_OK="$(grep -c '^ok ' "${LOG_DIR}/snapshot.log" || true)"
    step_done "${SNAP_OK} database(s) snapshotted"
    # a database that could not be copied is the whole point of the run — say so
    while IFS= read -r line; do
      case "${line}" in
        ok\ *)   [ "${VERBOSE}" -eq 1 ] || note "${line#ok }" ;;
        fail\ *) warn "${line#fail }" ;;
        skip\ *) note "${line#skip }" ;;
      esac
    done < "${LOG_DIR}/snapshot.log"
  fi

  # 2. everything, into a timestamped directory mirroring the server's layout
  step "Downloading to ${LOGS_STAGE}"
  [ "${DRY}" -eq 1 ] || mkdir -p "${LOGS_STAGE}/content"

  if [ "${DRY}" -eq 0 ]; then
    run "${LOG_DIR}/pulldb.log" rsync -az "${RSYNC_PROGRESS[@]}" \
      -e "ssh -p ${SSH_PORT}" \
      "${SSH_USER}@${SSH_HOST}:${REMOTE_SNAP}/" \
      "${LOGS_STAGE}/content/" \
      || fail_with_log "${LOG_DIR}/pulldb.log" "Downloading the database snapshots failed."
  fi

  # Anchored patterns (leading /) so *.log means the docroot's own logs and not
  # every stray log file under vendor/.
  run "${LOG_DIR}/pullfiles.log" rsync -az "${RSYNC_PROGRESS[@]}" --itemize-changes "${RSYNC_MODE[@]}" \
    --include='/*.log' \
    --include='/content/' \
    --include='/content/*.log' \
    --include='/content/analytics.seed' \
    --include='/content/comments/' \
    --include='/content/comments/*.json' \
    --exclude='*' \
    -e "ssh -p ${SSH_PORT}" \
    "${SSH_USER}@${SSH_HOST}:${REMOTE_DIR}/" \
    "${LOGS_STAGE}/" \
    || fail_with_log "${LOG_DIR}/pullfiles.log" "Downloading the logs and comments failed."

  if [ "${DRY}" -eq 1 ]; then
    # a dry run exists to be read: name the files instead of claiming success
    FILES="$(grep -E '^[<>ch.][fLDS]' "${LOG_DIR}/pullfiles.log" | sed -E 's/^[^ ]+ //' | grep -v '/$' || true)"
    FILES_N="$([ -z "${FILES}" ] && echo 0 || printf '%s\n' "${FILES}" | wc -l | tr -d ' ')"
    step_done "${FILES_N} log/comment file(s) would be pulled, plus the database snapshots"
    while IFS= read -r line; do [ -z "${line}" ] || note "${line}"; done < <(printf '%s\n' "${FILES}" | head -20)
  else
    step_done "Downloaded"
  fi

  # 3. the host's own web logs, when deploy.env points at them
  if [ -n "${REMOTE_LOG_DIR:-}" ]; then
    step "Downloading server logs from ${REMOTE_LOG_DIR}"
    [ "${DRY}" -eq 1 ] || mkdir -p "${LOGS_STAGE}/server-logs"
    if run "${LOG_DIR}/pullsrv.log" rsync -az "${RSYNC_PROGRESS[@]}" "${RSYNC_MODE[@]}" \
      -e "ssh -p ${SSH_PORT}" \
      "${SSH_USER}@${SSH_HOST}:${REMOTE_LOG_DIR}/" \
      "${LOGS_STAGE}/server-logs/"; then
      step_done "Server logs downloaded"
    else
      # rotated away, renamed by the host, or simply not readable — the site's
      # own data is already down and that is what the run was for
      dump_log "${LOG_DIR}/pullsrv.log"
      warn "could not read ${REMOTE_LOG_DIR} — check REMOTE_LOG_DIR in deploy.env"
    fi
  fi

  echo
  if [ "${DRY}" -eq 1 ]; then
    echo -e "${GREEN}${BOLD}Dry run done — nothing was written.${NC}"
    echo -e "${BOLD}Total time: $((SECONDS))s${NC}"
    exit 0
  fi

  # --- what came back ---
  PULLED_SIZE="$(du -sh "${LOGS_STAGE}" | cut -f1)"
  PULLED_N="$(find "${LOGS_STAGE}" -type f | wc -l | tr -d ' ')"
  echo -e "${GREEN}${BOLD}Pulled ${PULLED_N} file(s), ${PULLED_SIZE} → ${LOGS_STAGE}/${NC}"

  # The analytics database is the reason this mode exists; summarise it rather
  # than leaving the user to open sqlite just to see whether it arrived intact.
  AN_LOCAL="${LOGS_STAGE}/content/analytics.db"
  if [ -f "${AN_LOCAL}" ]; then
    echo
    php -r '
      $db = new PDO("sqlite:" . $argv[1], null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
      $r = $db->query("SELECT COUNT(*) n, COUNT(DISTINCT day) d, MIN(day) f, MAX(day) l FROM hits")->fetch(PDO::FETCH_ASSOC);
      if (!(int) $r["n"]) { echo "  analytics.db: no hits recorded yet\n"; exit; }
      printf("  analytics.db: %s hits over %d day(s), %s → %s\n", number_format((int) $r["n"]), (int) $r["d"], $r["f"], $r["l"]);
      $by = $db->query("SELECT excl, COUNT(*) n FROM hits GROUP BY excl ORDER BY excl")->fetchAll(PDO::FETCH_KEY_PAIR);
      $label = [0 => "counted", 1 => "automated", 2 => "owner"];
      $parts = [];
      foreach ($by as $k => $n) $parts[] = number_format((int) $n) . " " . ($label[(int) $k] ?? "excluded({$k})");
      if ($parts) printf("  verdicts:     %s\n", implode(", ", $parts));
    ' "${AN_LOCAL}" 2>/dev/null || note "analytics.db pulled but could not be read locally"
  fi

  echo
  echo -e "To analyse it:"
  echo -e "  sqlite3 ${LOGS_STAGE}/content/analytics.db"
  echo -e "  # or browse it in the local admin dashboard (overwrites your local copy):"
  echo -e "  cp ${LOGS_STAGE}/content/analytics.db content/analytics.db"
  echo
  echo -e "${BOLD}Total time: $((SECONDS))s${NC}"
  exit 0
fi

[ ${#RSYNC_MODE[@]} -eq 0 ] || STEP_TOTAL=6   # --dry-run skips the backup step

echo -e "${BOLD}Deploying bfrackowiak.pl${NC}"
echo -e "  target: ${SSH_USER}@${SSH_HOST}:${REMOTE_DIR}"
echo -e "  mode:   $([ ${#RSYNC_MODE[@]} -eq 0 ] && echo 'live' || echo 'dry-run')$([ ${#DELETE_FLAG[@]} -eq 0 ] || echo ', clean (deletes stale remote files)')$([ "${VERBOSE}" -eq 1 ] && echo ', verbose')"

# --- sanity: config must exist (endpoints read it at runtime) ---
if [ ! -f "${LOCAL_DIR}config.php" ]; then
  die "Missing config.php — copy config.example.php and fill it in."
fi

# --- 1. tests ---
step "Running test suite"
run "${LOG_DIR}/tests.log" php "${LOCAL_DIR}tests/run.php" \
  || fail_with_log "${LOG_DIR}/tests.log" "Test suite failed — nothing was deployed."
# run.php prints one tally per script (unit, integration) and no grand total
TEST_TALLY="$(grep -oE 'PASS: [0-9]+ +FAIL: [0-9]+' "${LOG_DIR}/tests.log" \
  | awk '{p += $2} END {if (NR) printf "%d checks", p}' || true)"
step_done "Tests passed${TEST_TALLY:+ — ${TEST_TALLY}}"

# --- 2. build ---
step "Building site (php tools/build.php)"
run "${LOG_DIR}/build.log" php "${LOCAL_DIR}tools/build.php" \
  || fail_with_log "${LOG_DIR}/build.log" "Build failed — nothing was deployed."
# Surface the build's headline and anything that wants a human, even when quiet.
BUILT="$(grep -m1 '^built ' "${LOG_DIR}/build.log" || true)"
step_done "Build complete${BUILT:+ — ${BUILT%% →*}}"
if [ "${VERBOSE}" -eq 0 ]; then
  while IFS= read -r line; do [ -z "${line}" ] || note "${line}"; done \
    < <(grep -E '^(removed stale|UNSCORED|scheduled:|draft:|scores:)' "${LOG_DIR}/build.log" || true)
fi

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
elif run "${LOG_DIR}/geoip.log" bash -c "cd '${LOCAL_DIR}' && php tools/geoip_update.php"; then
  step_done "Updated (was ${GEOIP_WAS})"
else
  # geoip_compile writes through a temp file and renames, and a failed download
  # exits before touching anything, so the old database is always still intact
  # here. Pushing it could still replace a newer copy on the server, so protect
  # the remote one and carry on: country lookup is not worth failing a deploy.
  GEOIP_PROTECT=(--exclude='content/geoip.dat')
  dump_log "${LOG_DIR}/geoip.log"
  warn "geoip refresh failed (local copy: ${GEOIP_WAS})"
  note "the existing database was not modified and the remote one is protected"
  note "deploy continues; retry later with: php tools/geoip_update.php"
fi

# --- 3b. refuse to overwrite a server that is ahead ---
# Cheap: one ssh, a few dozen md5s. Runs before the backup so a refusal costs nothing.
step "Checking the server is not ahead"
REMOTE_H="$(sync_hashes_remote)"
LOCAL_H="$(sync_hashes_local)"
CONFLICTS=""
if [ -n "${REMOTE_H}" ]; then
  while IFS= read -r line; do
    [ -n "${line}" ] || continue
    RHASH="${line%% *}"
    RPATH="${line#* }"; RPATH="${RPATH# }"
    LHASH="$(printf '%s\n' "${LOCAL_H}" | awk -v p="${RPATH}" '$2==p {print $1; exit}')"
    [ "${RHASH}" != "${LHASH}" ] || continue                 # identical: nothing to decide
    KHASH=""
    [ ! -f "${SYNC_STATE}" ] || KHASH="$(awk -v p="${RPATH}" '$2==p {print $1; exit}' "${SYNC_STATE}")"
    # remote still matches what we last agreed on, so the difference is our local edit going up
    [ "${RHASH}" != "${KHASH}" ] || continue
    CONFLICTS="${CONFLICTS}  ${RPATH}\n"
  done <<< "${REMOTE_H}"
fi

if [ -n "${CONFLICTS}" ] && [ "${FORCE_PUSH}" -eq 0 ]; then
  echo
  warn "The server has changes these files do not have locally:"
  echo -en "${CONFLICTS}"
  echo
  die "Run ./deploy.sh --pull first, or ./deploy.sh --force to overwrite the server."
fi
if [ -n "${CONFLICTS}" ]; then
  warn "--force: overwriting server-side changes in $(echo -en "${CONFLICTS}" | wc -l) file(s)"
  step_done "Guard overridden"
else
  step_done "Server is in step"
fi

# --- 4. backup remote -> local archive (skipped on --dry-run) ---
BACKUP_HINT=""   # only points at an archive once one actually exists
if [ ${#RSYNC_MODE[@]} -eq 0 ]; then
  step "Backing up remote -> ${BACKUP_ARCHIVE}"
  mkdir -p "${BACKUP_STAGE}"

  run "${LOG_DIR}/backup.log" rsync -az "${RSYNC_PROGRESS[@]}" \
    -e "ssh -p ${SSH_PORT}" \
    "${SSH_USER}@${SSH_HOST}:${REMOTE_DIR}/" \
    "${BACKUP_STAGE}/" \
    || fail_with_log "${LOG_DIR}/backup.log" "Remote backup failed — nothing was deployed."

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
  BACKUP_HINT=" — backup: ${BACKUP_ARCHIVE}"
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
RSYNC_LOG="${LOG_DIR}/sync.log"
run "${RSYNC_LOG}" rsync -az "${RSYNC_PROGRESS[@]}" --stats --itemize-changes "${RSYNC_MODE[@]}" "${DELETE_FLAG[@]}" "${GEOIP_PROTECT[@]}" \
  --exclude='.git/' \
  --exclude='.gitignore' \
  --exclude='.claude/' \
  --exclude='deploy.sh' \
  --exclude='docs/' \
  --exclude='drafts/' \
  --exclude='Refernce/' \
  --exclude='tests/' \
  --exclude='README.md' \
  --exclude='LICENSE' \
  --exclude='config.example.php' \
  --exclude='tools/preview_admin.php' \
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
  || fail_with_log "${RSYNC_LOG}" "Sync failed — the remote may be half-updated${BACKUP_HINT}."

# rsync groups digits with the locale's separator — dot here, comma elsewhere
XFERRED="$(grep -m1 'Number of regular files transferred' "${RSYNC_LOG}" | grep -oE '[0-9.,]+$' || echo '?')"
SENT="$(grep -m1 -oE 'Total transferred file size: [0-9.,]+ bytes' "${RSYNC_LOG}" | grep -oE '[0-9.,]+ bytes' || true)"
DELETED="$(grep -c '^\*deleting' "${RSYNC_LOG}" || true)"
step_done "Synced (${XFERRED} files transferred${SENT:+, ${SENT}}$([ "${DELETED}" -eq 0 ] || echo ", ${DELETED} deleted"))"

# Both sides now hold the same post sources, so record that: the next push measures against this.
[ ${#RSYNC_MODE[@]} -ne 0 ] || sync_state_write

# A dry run exists to be read: quiet mode still lists what would move, capped so
# a first-time sync cannot bury the summary.
if [ ${#RSYNC_MODE[@]} -ne 0 ] && [ "${VERBOSE}" -eq 0 ]; then
  # files only — directory entries are itemized too and say nothing useful
  CHANGES="$(grep -E '^(\*deleting|[<>ch.][fLDS])' "${RSYNC_LOG}" | sed -E 's/^[^ ]+ //' || true)"
  CHANGED_N="$([ -z "${CHANGES}" ] && echo 0 || printf '%s\n' "${CHANGES}" | wc -l | tr -d ' ')"
  if [ "${CHANGED_N}" -gt 0 ]; then
    while IFS= read -r line; do note "${line}"; done < <(printf '%s\n' "${CHANGES}" | head -15)
    [ "${CHANGED_N}" -le 15 ] || note "… and $((CHANGED_N - 15)) more (run with --verbose for the full list)"
  fi
fi

# --- 6. mirror content/posts ---
# The sync above is additive on purpose: it must never delete runtime data it
# does not own. content/posts/ is the one directory this repo owns outright,
# and rescheduling a post renames its file. Additive + rename = the old
# filename lives on forever on the server, the post parses twice, and the
# pipeline lists it twice. Mirror that directory exactly, every deploy.
step "Mirroring content/posts (clearing renamed leftovers)"
POSTS_LOG="${LOG_DIR}/posts.log"
run "${POSTS_LOG}" rsync -az --delete --itemize-changes "${RSYNC_MODE[@]}" \
  -e "ssh -p ${SSH_PORT}" \
  "${LOCAL_DIR}content/posts/" \
  "${SSH_USER}@${SSH_HOST}:${REMOTE_DIR}/content/posts/" \
  || fail_with_log "${POSTS_LOG}" "content/posts mirror failed${BACKUP_HINT}."
GONE="$(grep -c '^\*deleting' "${POSTS_LOG}" || true)"
step_done "content/posts mirrored (${GONE} stale file(s) removed)"
# Deletions are the whole point of this step — name them even in clean output.
if [ "${VERBOSE}" -eq 0 ] && [ "${GONE}" -gt 0 ]; then
  while IFS= read -r line; do note "removed ${line##* }"; done \
    < <(grep '^\*deleting' "${POSTS_LOG}")
fi

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
