#!/usr/bin/env bash
set -euo pipefail

# deploy.sh — move the site, and the writing round trip, between here and the server.
#
#   ./deploy.sh help        the full reference. Start there.
#
# Four commands: push (the default), pull, data, status. Everything else is a modifier.
#
# Runtime data on the server is NEVER overwritten or deleted:
# content/comments/*.json, content/analytics.db, content/analytics.seed,
# content/newsletter.db, content/scores.db, content/.sessions/

# --- config ---
# The deployment target lives in deploy.env (gitignored) — copy
# deploy.env.example and fill in your host. Required vars:
#   SSH_USER, SSH_HOST, SSH_PORT, REMOTE_DIR
LOCAL_DIR="$(cd "$(dirname "$0")" && pwd)/"
ENV_FILE="${DEPLOY_ENV_FILE:-${LOCAL_DIR}deploy.env}"
if [ ! -f "${ENV_FILE}" ]; then
  echo "Missing ${ENV_FILE} — copy deploy.env.example and fill in your SSH target." >&2
  exit 1
fi
# shellcheck source=deploy.env.example
source "${ENV_FILE}"
: "${SSH_USER:?deploy.env must set SSH_USER}"
: "${SSH_HOST:?deploy.env must set SSH_HOST}"
: "${SSH_PORT:=22}"
: "${REMOTE_DIR:?deploy.env must set REMOTE_DIR}"
# The ssh command, with any options it needs (CI passes a key: "ssh -i … -o IdentitiesOnly=yes").
# A string for rsync -e, an array for direct calls.
SSH_RSH="${DEPLOY_SSH:-ssh}"
read -r -a SSH_CMD <<< "${SSH_RSH}"

# Everything a deploy does on the server, apart from rsync, is a subcommand of one
# program installed in ~/bin there (ci/remote/blog-remote). That is what lets the
# CI key be pinned to rsync and that program (ci/remote/blog-gate).
REMOTE_HELPER='~/bin/blog-remote'
HELPER_HEADER="# blog-remote 1 ${REMOTE_DIR%/}"
remote_helper() {
  "${SSH_CMD[@]}" -p "${SSH_PORT}" -o ConnectTimeout=15 "${SSH_USER}@${SSH_HOST}" "${REMOTE_HELPER} $*"
}
# CI keeps its backups outside the throwaway checkout (a mounted directory on the build host)
BACKUP_DIR="${DEPLOY_BACKUP_DIR:-${LOCAL_DIR}_remote-backup}"
BACKUP_DIR="${BACKUP_DIR%/}"
TIMESTAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP_STAGE="${BACKUP_DIR}/${TIMESTAMP}"
BACKUP_ARCHIVE="${BACKUP_DIR}/${TIMESTAMP}.tar.gz"
# data writes here; the nightly job points it at the backup mount (DEPLOY_LOGS_DIR)
LOGS_DIR="${DEPLOY_LOGS_DIR:-${LOCAL_DIR}_remote-logs}"
LOGS_DIR="${LOGS_DIR%/}"
LOGS_STAGE="${LOGS_DIR}/${TIMESTAMP}"

# --- colors ---
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; BLUE='\033[0;34m'; BOLD='\033[1m'; NC='\033[0m'
# DEPLOY_CI=1: run by the pipeline. Plain output for the job log; ~/bin on the server is out of
# reach of the CI key, so the helper is never installed from there.
if [ "${DEPLOY_CI:-0}" = "1" ] || [ -n "${NO_COLOR:-}" ]; then
  RED=''; GREEN=''; YELLOW=''; BLUE=''; BOLD=''; NC=''
fi

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
FORCE_PUSH=0
SMOKE_ARGS=()
ROLLBACK_ARCHIVE=""
CMD=""

usage() {
  cat <<'HELPTEXT'
deploy.sh — move the site, and the writing round trip, between here and the server.

USAGE
  ./deploy.sh [command] [options]

COMMANDS
  push        Build the site and send it up. This is what runs when you name no
              command, so plain ./deploy.sh still means "deploy".
  pull        Bring post sources and review notes home, then write the briefs in
              drafts/review/. Run this after marking a post up on the site, and
              before the next push.
  data        Bring the live site's runtime data home: analytics, subscribers,
              the scores mirror, comment threads, and the newsletter log. Reads
              the server, changes nothing on it.
  status      Say whether both sides hold the same post sources and notes. Reads
              only; useful when you cannot remember whether you pulled.
  smoke [url] [--sha=<commit>] [--local]
              Check the live site (or url): pages, feed, sitemap, 404s, files that
              must stay hidden, security headers, certificate, and with --sha the
              commit in build.txt. push runs it at the end (DEPLOY_SMOKE=0 skips).
  rollback <archive.tar.gz> [-n]
              Put a pre-deploy backup back on the server: code, pages and post
              sources as they were then. Runtime data (comments, analytics,
              subscribers) stays as it is now, and the server is backed up first.
              No tests, no build. The next push ships its own tree again, so fix
              forward on main once the site is safe.
  backup      Archive the live docroot into _remote-backup/ (DEPLOY_BACKUP_DIR), the
              same backup a push takes first, without deploying anything.
  install-remote
              Copy ci/remote/* into ~/bin on the server. push, pull and data do it
              on their own; run it by hand after changing those scripts when you
              only deploy from CI, whose key cannot write there.
  help        This.

OPTIONS
  -n, --dry-run     Say what would happen and write nothing, on either side.
  -v, --verbose     Stream every command instead of one line per step. A failing
                    step dumps its whole log either way.
      --delete      push only. Remove remote files that no longer exist here.
                    Runtime data stays protected regardless.
      --no-geoip    push only. Skip the geoip refresh, which otherwise waits out
                    its own timeout when you are offline.
      --force       push only. Push even when the server holds changes this
                    machine does not. Overwrites them. Last resort.
  -h, --help        This.

THE WRITING ROUND TRIP
  Both sides can write content/posts now: review.php edits post sources in place
  and stores margin notes beside them. So every successful push records on the
  server what the two sides agreed on, and a push from any machine (this one or
  CI) stops, with exit code 3, when the server has moved on since. The loop is:

      read the post on the site, leave notes, fix small things
      ./deploy.sh pull            posts and notes come home, briefs are written
      claude code                 works from drafts/review/, resolves each note
      ./deploy.sh                 the result and the history go back up
                                  (or commit and push: CI deploys main)

WHAT IS NEVER TOUCHED ON THE SERVER
  content/comments/*.json, content/analytics.db, content/analytics.seed,
  content/newsletter.db, content/scores.db, content/.sessions/

EXIT CODES
  0 done   1 failed   2 bad arguments   3 the server is ahead (pull first)
  4 another deploy holds the lock on the server

EXAMPLES
  ./deploy.sh                     build and deploy
  ./deploy.sh -n                  rehearse the deploy, write nothing
  ./deploy.sh --delete            deploy and prune files gone from this machine
  ./deploy.sh pull -n             see what a pull would bring home
  ./deploy.sh status              are the two sides in step?
  ./deploy.sh rollback _remote-backup/20261124-102357.tar.gz
                                  undo a bad deploy (the backup it took first)
HELPTEXT
}

# A command is the first bare word; everything starting with a dash is a modifier, in any order.
# The old flag spellings still work, because muscle memory is a real cost.
for arg in "$@"; do
  case "$arg" in
    push|pull|data|status|install-remote|smoke|rollback|backup|help)
      [ -z "${CMD}" ] || die "Two commands given (${CMD}, ${arg}) — pick one."
      CMD="${arg}" ;;
    *.tar.gz|*.tgz) ROLLBACK_ARCHIVE="${arg}" ;;
    --pull)       CMD="pull";   echo "note: --pull is now 'deploy.sh pull'" >&2 ;;
    --pull-logs)  CMD="data";   echo "note: --pull-logs is now 'deploy.sh data'" >&2 ;;
    --clean)      DELETE_FLAG=(--delete); echo "note: --clean is now --delete" >&2 ;;
    --delete)     DELETE_FLAG=(--delete) ;;
    -n|--dry-run) RSYNC_MODE=(--dry-run) ;;
    --no-geoip)   SKIP_GEOIP=1 ;;
    -v|--verbose) VERBOSE=1 ;;
    --force)      FORCE_PUSH=1 ;;
    -h|--help)    CMD="help" ;;
    --sha=*|--local|http://*|https://*) SMOKE_ARGS+=("$arg") ;;
    *) echo "Unknown argument: ${arg}" >&2; echo "Run ./deploy.sh help for the reference." >&2; exit 2 ;;
  esac
done
[ -n "${CMD}" ] || CMD="push"

if [ "${CMD}" = "help" ]; then usage; exit 0; fi

# Modifiers that only mean something to a push, named where they were given rather than ignored in
# silence: a flag that quietly does nothing is how you end up trusting one that never applied.
if [ "${CMD}" != "push" ]; then
  [ ${#DELETE_FLAG[@]} -eq 0 ] || die "--delete applies to push, not to ${CMD}."
  [ "${SKIP_GEOIP}" -eq 0 ]    || die "--no-geoip applies to push, not to ${CMD}."
  [ "${FORCE_PUSH}" -eq 0 ]    || die "--force applies to push, not to ${CMD}."
fi
[ "${CMD}" = "smoke" ] || [ ${#SMOKE_ARGS[@]} -eq 0 ] || die "${SMOKE_ARGS[0]} applies to smoke, not to ${CMD}."
[ "${CMD}" = "rollback" ] || [ -z "${ROLLBACK_ARCHIVE}" ] || die "${ROLLBACK_ARCHIVE} applies to rollback, not to ${CMD}."
if [ "${CMD}" = "rollback" ]; then
  if [ -z "${ROLLBACK_ARCHIVE}" ]; then
    echo "usage: ./deploy.sh rollback <archive.tar.gz> [-n]" >&2
    RB_DIR="${DEPLOY_BACKUP_DIR:-${LOCAL_DIR}_remote-backup}"
    if compgen -G "${RB_DIR%/}/*.tar.gz" >/dev/null; then
      echo "newest archives in ${RB_DIR%/}:" >&2
      find "${RB_DIR%/}" -maxdepth 1 -name '*.tar.gz' -printf '  %f\n' | sort -r | head -10 >&2
    fi
    exit 2
  fi
  [ -f "${ROLLBACK_ARCHIVE}" ] || { echo "rollback: no such archive: ${ROLLBACK_ARCHIVE}" >&2; exit 2; }
fi

# --- smoke: is the live site up, in one piece, and the commit we think it is? ---
# tools/smoke.php does the work; the URL defaults to SMOKE_URL (deploy.env) or the live site.
run_smoke() {
  local args=("$@")
  [ "${SMOKE_LOCAL:-0}" != "1" ] || args+=(--local)
  local has_url=0 a
  for a in "${args[@]}"; do case "${a}" in http://*|https://*) has_url=1 ;; esac; done
  [ "${has_url}" -eq 1 ] || args=("${SMOKE_URL:-https://bfrackowiak.pl}" "${args[@]}")
  php "${LOCAL_DIR}tools/smoke.php" "${args[@]}"
}
if [ "${CMD}" = "smoke" ]; then
  run_smoke "${SMOKE_ARGS[@]}"
  exit $?
fi

# --- quiet-by-default plumbing ---
# Every noisy command runs through run(): verbose streams it, quiet captures it.
# Nothing is ever lost — a failing step replays its whole log before dying, so
# the clean output only ever hides successful noise.
LOG_DIR="$(mktemp -d)"
# --pull-logs stages database snapshots in a directory on the server; clear it
# on every exit path, including the failing ones, so a dead deploy never leaves
# a copy of the analytics database sitting in the docroot.
REMOTE_SNAP=""
LOCK_HELD=0
# Uncommitted changes that matter to a deploy: everything except the private material that is
# never shipped (drafts, notes, references), where work in progress is normal.
shipped_changes() {
  git -C "${LOCAL_DIR}" status --porcelain -- . ':(exclude)drafts' ':(exclude)docs' \
    ':(exclude)_archive' ':(exclude)Refernce' 2>/dev/null
}
cleanup() {
  rm -rf "${LOG_DIR}"
  [ -z "${REMOTE_SNAP}" ] || remote_helper cleanup "${REMOTE_SNAP}" >/dev/null 2>&1 || true
  [ "${LOCK_HELD}" -eq 0 ] || remote_helper unlock >/dev/null 2>&1 || true
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
# The marker lives on the server (content/.sync-state there), written by every successful push
# from any machine, so the laptop and CI measure against the same record. A marker left on this
# machine by the old deploy.sh is read once, when the server has none yet.
SYNC_STATE="${LOCAL_DIR}content/.sync-state"

# md5 of every post source and review note, one "hash  path" per line, sorted.
sync_hashes_of() {
  ( cd "$1" && find content/posts content/review -type f \( -name '*.md' -o -name '*.json' \) 2>/dev/null \
    | sort | xargs -r md5sum 2>/dev/null ) || true
}
sync_hashes_local() { sync_hashes_of "${LOCAL_DIR}"; }
# The server's side of the same list, into REMOTE_H. No answer, or an answer from a
# helper that is not the expected version for this docroot, stops everything: an
# empty list would read as "nothing on the server", and the guard would wave the
# push through over whatever is really there.
remote_hashes() {
  local out
  out="$(remote_helper hashes 2>/dev/null)" || true
  [ "$(printf '%s\n' "${out}" | head -1)" = "${HELPER_HEADER}" ] || helper_missing "${out}"
  REMOTE_H="$(printf '%s\n' "${out}" | tail -n +2)"
}
helper_missing() {
  local got
  got="$(printf '%s\n' "${1:-}" | head -1)"
  die "remote helper missing or outdated: run ./deploy.sh install-remote (expected '${HELPER_HEADER}', got '${got:-nothing}')"
}
# The right helper, for this docroot, answering on the server — or a stop that says so.
check_helper() {
  local v
  v="$(remote_helper version 2>/dev/null || true)"
  [ "# ${v}" = "${HELPER_HEADER}" ] || helper_missing "${v}"
}
install_remote() {
  run "${LOG_DIR}/install.log" rsync -a --chmod=F755 -e "${SSH_RSH} -p ${SSH_PORT}" \
    "${LOCAL_DIR}ci/remote/" "${SSH_USER}@${SSH_HOST}:bin/" \
    || fail_with_log "${LOG_DIR}/install.log" "Installing the remote helper into ~/bin failed."
}
# push, pull and data keep the helper current on their own. Not in CI (its key
# cannot write ~/bin) and not on a dry run (which writes nothing anywhere).
maybe_install_remote() {
  [ "${DEPLOY_CI:-0}" != "1" ] || return 0
  [ ${#RSYNC_MODE[@]} -eq 0 ] || return 0
  install_remote
}

if [ "${CMD}" = "install-remote" ]; then
  [ "${DEPLOY_CI:-0}" != "1" ] || die "install-remote runs from a machine with a full key: the CI key cannot write ~/bin on the server."
  install_remote
  VERSION_LINE="$(remote_helper version 2>&1 || true)"
  [ "# ${VERSION_LINE}" = "${HELPER_HEADER}" ] || die "installed, but the server answers '${VERSION_LINE}' instead of '${HELPER_HEADER#\# }'"
  echo -e "${GREEN}${BOLD}Remote helper installed:${NC} ${VERSION_LINE}"
  exit 0
fi
# Record the post sources of a tree (this checkout by default) as what both sides agree on.
sync_state_write() {
  sync_hashes_of "${1:-${LOCAL_DIR}}" | remote_helper sync-state-write >/dev/null \
    || warn "could not record the sync marker on the server — the next push may ask for a pull"
}
# The agreed hashes into KNOWN_H: the server's marker, or this machine's old one as a fallback.
known_hashes() {
  local out
  out="$(remote_helper sync-state-read 2>/dev/null)" || true
  [ "$(printf '%s\n' "${out}" | head -1)" = "${HELPER_HEADER}" ] || helper_missing "${out}"
  KNOWN_H="$(printf '%s\n' "${out}" | tail -n +2)"
  if [ -z "${KNOWN_H}" ] && [ -s "${SYNC_STATE}" ]; then
    KNOWN_H="$(cat "${SYNC_STATE}")"
    note "using local sync marker (migration): the server has none yet"
  fi
}
# One deploy at a time on the server; the lock is ours to release only once we hold it.
acquire_lock() {
  local who="laptop" out rc=0
  [ "${DEPLOY_CI:-0}" != "1" ] || who="ci"
  out="$(remote_helper lock "${who}" 2>&1)" || rc=$?
  if [ "${rc}" -eq 4 ]; then
    echo -e "${RED}✗ ${out#blog-remote: }${NC}"
    echo "  Wait for it to finish, then deploy again."
    exit 4
  fi
  [ "${rc}" -eq 0 ] || die "could not take the deploy lock on the server: ${out}"
  LOCK_HELD=1
}

# --- status: which side is ahead, and nothing else ---------------------------
# The guard below answers this question in order to refuse a push. Asking it on its own is cheaper
# than remembering whether you pulled after the last reading session.
if [ "${CMD}" = "status" ]; then
  echo -e "${BOLD}Comparing post sources and review notes${NC}"
  echo -e "  here:   ${LOCAL_DIR}"
  echo -e "  server: ${SSH_USER}@${SSH_HOST}:${REMOTE_DIR}"
  echo

  remote_hashes
  known_hashes
  LOCAL_H="$(sync_hashes_local)"

  SAME=0; AHEAD_LOCAL=0; AHEAD_REMOTE=0; ONLY_LOCAL=0
  AHEAD_REMOTE_LIST=""; AHEAD_LOCAL_LIST=""; ONLY_LOCAL_LIST=""

  while IFS= read -r line; do
    [ -n "${line}" ] || continue
    RHASH="${line%% *}"; RPATH="${line#* }"; RPATH="${RPATH# }"
    LHASH="$(printf '%s\n' "${LOCAL_H}" | awk -v p="${RPATH}" '$2==p {print $1; exit}')"
    if [ "${RHASH}" = "${LHASH}" ]; then SAME=$((SAME + 1)); continue; fi
    KHASH="$(printf '%s\n' "${KNOWN_H}" | awk -v p="${RPATH}" '$2==p {print $1; exit}')"
    if [ "${RHASH}" = "${KHASH}" ]; then
      AHEAD_LOCAL=$((AHEAD_LOCAL + 1)); AHEAD_LOCAL_LIST="${AHEAD_LOCAL_LIST}  ${RPATH}\n"
    else
      AHEAD_REMOTE=$((AHEAD_REMOTE + 1)); AHEAD_REMOTE_LIST="${AHEAD_REMOTE_LIST}  ${RPATH}\n"
    fi
  done <<< "${REMOTE_H}"

  while IFS= read -r line; do
    [ -n "${line}" ] || continue
    LPATH="${line#* }"; LPATH="${LPATH# }"
    printf '%s\n' "${REMOTE_H}" | awk -v p="${LPATH}" '$2==p {found=1} END {exit !found}' && continue
    ONLY_LOCAL=$((ONLY_LOCAL + 1)); ONLY_LOCAL_LIST="${ONLY_LOCAL_LIST}  ${LPATH}\n"
  done <<< "${LOCAL_H}"

  echo -e "  ${GREEN}${SAME}${NC} identical"
  if [ "${AHEAD_LOCAL}" -gt 0 ]; then
    echo -e "  ${AHEAD_LOCAL} newer here (a push will send them):"; echo -en "${AHEAD_LOCAL_LIST}"
  fi
  if [ "${ONLY_LOCAL}" -gt 0 ]; then
    echo -e "  ${ONLY_LOCAL} here only (a push will create them):"; echo -en "${ONLY_LOCAL_LIST}"
  fi
  if [ "${AHEAD_REMOTE}" -gt 0 ]; then
    echo
    echo -e "  ${YELLOW}${AHEAD_REMOTE} changed on the server since the last sync:${NC}"
    echo -en "${AHEAD_REMOTE_LIST}"
    echo
    echo -e "  ${BOLD}Run ./deploy.sh pull before the next push.${NC}"
    exit 3
  fi
  echo
  [ -n "${KNOWN_H}" ] || warn "no sync marker yet — the first push writes it"
  echo -e "${GREEN}${BOLD}In step.${NC} A push would not overwrite anything on the server."
  exit 0
fi

# --- --pull: bring the writing round trip home, then stop ---
# review.php edits post sources in place and stores margin notes next to them, so after a reading
# session the server holds the newer copy of both. This is the only thing that brings them back, and
# it must run before the next deploy or the push guard below will (correctly) refuse to start.
#
# Everything here is a plain file copy: no snapshot, no database, nothing on the server changes.
if [ "${CMD}" = "pull" ]; then
  STEP_TOTAL=2
  DRY=0
  [ ${#RSYNC_MODE[@]} -eq 0 ] || DRY=1

  echo -e "${BOLD}Pulling post sources and review notes${NC}"
  echo -e "  source: ${SSH_USER}@${SSH_HOST}:${REMOTE_DIR}"
  echo -e "  target: ${LOCAL_DIR}content/"
  [ "${DRY}" -eq 0 ] || echo -e "  mode:   dry-run (nothing is written)"
  echo

  maybe_install_remote
  step "Downloading content/posts and content/review"
  run "${LOG_DIR}/pullreview.log" rsync -az "${RSYNC_PROGRESS[@]}" --itemize-changes "${RSYNC_MODE[@]}" \
    --include='/content/' \
    --include='/content/posts/' \
    --include='/content/posts/*.md' \
    --include='/content/review/' \
    --include='/content/review/*.json' \
    --exclude='*' \
    -e "${SSH_RSH} -p ${SSH_PORT}" \
    "${SSH_USER}@${SSH_HOST}:${REMOTE_DIR}/" \
    "${LOCAL_DIR}" \
    || fail_with_log "${LOG_DIR}/pullreview.log" "Downloading posts and review notes failed."

  CHANGED="$(grep -cE '^[<>ch][fL]' "${LOG_DIR}/pullreview.log" || true)"
  if [ "${DRY}" -eq 1 ]; then
    step_done "${CHANGED} file(s) would be updated"
  else
    step_done "${CHANGED} file(s) updated"
    # no marker: a pull brings the server's edits here, it does not put anything there.
    # The next push from any machine that carries them is what makes the sides agree.
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
if [ "${CMD}" = "data" ]; then
  # The host's own logs are symlinks into its log tree. The CI key may not follow links
  # (rrsync refuses -L), and the nightly copy is the site's own data: in CI they stay out.
  SERVER_LOGS="${REMOTE_LOG_DIR:-}"
  if [ "${DEPLOY_CI:-0}" = "1" ] && [ -n "${SERVER_LOGS}" ]; then
    SERVER_LOGS=""
    note "server logs: not in CI (the CI key cannot follow the host's log links); ./deploy.sh data from the laptop brings them"
  fi
  STEP_TOTAL=2
  [ -z "${SERVER_LOGS}" ] || STEP_TOTAL=3
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
  maybe_install_remote
  step "Snapshotting databases on the server"
  if [ "${DRY}" -eq 1 ]; then
    step_done "Skipped (dry run) — the server is never written to anyway"
  else
    # blog-remote creates content/.logpull.<ts>, prints "dir <path>" and one ok|fail|skip
    # line per database (ci/remote/blog-snapshot.php)
    SNAP_RC=0
    run "${LOG_DIR}/snapshot.log" remote_helper snapshot || SNAP_RC=$?
    REMOTE_SNAP="$(sed -n 's/^dir //p' "${LOG_DIR}/snapshot.log" | head -1)"   # armed: cleanup() removes it
    [ "${SNAP_RC}" -eq 0 ] && [ -n "${REMOTE_SNAP}" ] \
      || fail_with_log "${LOG_DIR}/snapshot.log" "Snapshot failed on the server — nothing was pulled."

    SNAP_OK="$(grep -c '^ok ' "${LOG_DIR}/snapshot.log" || true)"
    SNAP_FAILS=""
    step_done "${SNAP_OK} database(s) snapshotted"
    # a database that could not be copied is the whole point of the run — say so
    while IFS= read -r line; do
      case "${line}" in
        ok\ *)   [ "${VERBOSE}" -eq 1 ] || note "${line#ok }" ;;
        fail\ *) warn "${line#fail }"; SNAP_FAILS="${SNAP_FAILS}  ${line#fail }\n" ;;
        skip\ *) note "${line#skip }" ;;
      esac
    done < "${LOG_DIR}/snapshot.log"
  fi

  # 2. everything, into a timestamped directory mirroring the server's layout
  step "Downloading to ${LOGS_STAGE}"
  [ "${DRY}" -eq 1 ] || mkdir -p "${LOGS_STAGE}/content"

  if [ "${DRY}" -eq 0 ]; then
    run "${LOG_DIR}/pulldb.log" rsync -az "${RSYNC_PROGRESS[@]}" \
      -e "${SSH_RSH} -p ${SSH_PORT}" \
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
    -e "${SSH_RSH} -p ${SSH_PORT}" \
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
  if [ -n "${SERVER_LOGS}" ]; then
    step "Downloading server logs from ${REMOTE_LOG_DIR}"
    [ "${DRY}" -eq 1 ] || mkdir -p "${LOGS_STAGE}/server-logs"
    # -L: on MyDevil ~/domains/<domain>/logs/access is a symlink into the host's
    # log tree; follow it, or the pull brings a dangling link instead of the logs
    if run "${LOG_DIR}/pullsrv.log" rsync -azL "${RSYNC_PROGRESS[@]}" "${RSYNC_MODE[@]}" \
      -e "${SSH_RSH} -p ${SSH_PORT}" \
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
  # From the laptop a missing database is a warning above. For the nightly backup it is the
  # failure the job exists to catch: everything that could come down did, and the run says no.
  if [ "${DEPLOY_CI:-0}" = "1" ] && [ -n "${SNAP_FAILS:-}" ]; then
    echo -e "${RED}✗ incomplete copy in ${LOGS_STAGE}/:${NC}"
    echo -en "${SNAP_FAILS}"
    exit 1
  fi
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

# --- shared by push and rollback -------------------------------------------------
# What never goes up from here, and what is never overwritten up there: the private material
# of this repository, and the runtime data the live site writes for itself.
SHIP_EXCLUDES=(
  --exclude='/.git'
  --exclude='.gitignore'
  --exclude='/.gitea/'
  --exclude='/.githooks/'
  --exclude='/.superpowers/'
  --exclude='/ci/'
  --exclude='/content/outlines/'
  --exclude='.claude/'
  --exclude='deploy.sh'
  --exclude='deploy.env'
  --exclude='deploy.env.example'
  --exclude='docs/'
  --exclude='drafts/'
  --exclude='Refernce/'
  --exclude='tests/'
  --exclude='README.md'
  --exclude='LICENSE'
  --exclude='config.example.php'
  --exclude='tools/preview_admin.php'
  --exclude='_archive/'
  --exclude='/_remote-backup/'
  --exclude='/_remote-logs/'
  --exclude='content/comments/'
  --exclude='content/.sessions/'
  --exclude='content/.ratelimit/'
  --exclude='content/.buildlock'
  --exclude='content/.sync-state'
  --exclude='content/.sync-state.*'
  --exclude='content/.deploylock/'
  --exclude='content/.logpull.*'
  --exclude='Dockerfile'
  --exclude='docker-compose.yml'
  --exclude='run.sh'
  --exclude='content/analytics.db'
  --exclude='content/analytics.db-*'
  --exclude='content/analytics.seed'
  --exclude='content/newsletter.db'
  --exclude='content/newsletter.db-*'
  --exclude='content/scores.db'
  --exclude='content/scores.db-*'
  --exclude='*.log'
  --exclude='.DS_Store'
)

# The live docroot into BACKUP_ARCHIVE, before anything overwrites it. $1 names what would have
# happened ("deployed", "rolled back") for the messages that stop it. Sets BACKUP_WHERE.
backup_remote() {
  local what="$1"
  step "Backing up remote -> ${BACKUP_ARCHIVE}"
  mkdir -p "${BACKUP_STAGE}"

  run "${LOG_DIR}/backup.log" rsync -az "${RSYNC_PROGRESS[@]}" \
    -e "${SSH_RSH} -p ${SSH_PORT}" \
    "${SSH_USER}@${SSH_HOST}:${REMOTE_DIR}/" \
    "${BACKUP_STAGE}/" \
    || fail_with_log "${LOG_DIR}/backup.log" "Remote backup failed — nothing was ${what}."

  # sanity: an (almost) empty remote means something is very wrong — pushing over
  # it would make the loss permanent and silent (this exact thing ate the
  # analytics history on 2026-07-19). Stop and let a human look first.
  BACKUP_FILES="$(find "${BACKUP_STAGE}" -type f | wc -l | tr -d ' ')"
  if [ "${BACKUP_FILES}" -lt 20 ]; then
    die "Remote backup contains only ${BACKUP_FILES} files — the remote looks empty or wrong. Investigate first; nothing was ${what} (backup kept at ${BACKUP_STAGE})."
  fi

  tar -czf "${BACKUP_ARCHIVE}" -C "${BACKUP_DIR}" "${TIMESTAMP}"
  rm -rf "${BACKUP_STAGE}"

  BACKUP_SIZE="$(du -h "${BACKUP_ARCHIVE}" | cut -f1)"
  BACKUP_WHERE="${BACKUP_ARCHIVE}"
  # From the laptop the archive moves to the home server (HOME_BACKUP_TARGET in deploy.env), where
  # the CI's own backups live too. If that fails it stays here: a backup is never lost to a hop.
  if [ "${DEPLOY_CI:-0}" != "1" ] && [ -n "${HOME_BACKUP_TARGET:-}" ]; then
    if rsync -a -e "ssh -o ConnectTimeout=5 -o BatchMode=yes" "${BACKUP_ARCHIVE}" "${HOME_BACKUP_TARGET}" >"${LOG_DIR}/homebackup.log" 2>&1; then
      rm -f "${BACKUP_ARCHIVE}"
      BACKUP_WHERE="${HOME_BACKUP_TARGET%/}/${TIMESTAMP}.tar.gz"
      step_done "Backup saved (${BACKUP_SIZE}, ${BACKUP_FILES} files) → ${HOME_BACKUP_TARGET}"
    else
      warn "could not copy the backup to ${HOME_BACKUP_TARGET}: kept here, ${BACKUP_ARCHIVE}"
      step_done "Backup saved (${BACKUP_SIZE}, ${BACKUP_FILES} files)"
    fi
  else
    step_done "Backup saved (${BACKUP_SIZE}, ${BACKUP_FILES} files)"
  fi
  BACKUP_HINT=" — backup: ${BACKUP_WHERE}"
}

# How to undo what just went up, for the message that says it went wrong.
undo_hint() {
  if [ -f "${BACKUP_WHERE}" ]; then
    echo "  to undo it: ./deploy.sh rollback ${BACKUP_WHERE}"
  elif [ -n "${BACKUP_WHERE}" ]; then
    echo "  to undo it: Gitea → Actions → rollback, archive $(basename "${BACKUP_WHERE}") (or copy it here and ./deploy.sh rollback it)"
  fi
}

# --- rollback: put a pre-deploy archive back ----------------------------------------
# An archive is what a push saved just before it overwrote the server: the whole docroot.
# Code, pages and post sources go back as they were; runtime data written since stays, by the
# same excludes as a push. The server is archived first, so a rollback can be undone as well.
# No tests and no build: the archive was a working site, and this is for when the current one
# is not.
if [ "${CMD}" = "rollback" ]; then
  STEP_TOTAL=4
  [ ${#RSYNC_MODE[@]} -eq 0 ] || STEP_TOTAL=3   # --dry-run skips the backup step
  echo -e "${BOLD}Rolling back bfrackowiak.pl${NC}"
  echo -e "  target:  ${SSH_USER}@${SSH_HOST}:${REMOTE_DIR}"
  echo -e "  archive: ${ROLLBACK_ARCHIVE}"
  echo -e "  mode:    $([ ${#RSYNC_MODE[@]} -eq 0 ] && echo 'live' || echo 'dry-run')"

  step "Unpacking the archive"
  RB_UNPACK="${LOG_DIR}/rollback"
  mkdir -p "${RB_UNPACK}"
  run "${LOG_DIR}/unpack.log" tar -xzf "${ROLLBACK_ARCHIVE}" --no-same-owner -C "${RB_UNPACK}" \
    || fail_with_log "${LOG_DIR}/unpack.log" "Could not unpack ${ROLLBACK_ARCHIVE} — nothing was touched."
  # a push archive holds one directory, named after its timestamp: the docroot is inside it
  RB_ROOT="${RB_UNPACK}"
  RB_ENTRIES="$(find "${RB_UNPACK}" -mindepth 1 -maxdepth 1)"
  if [ "$(printf '%s\n' "${RB_ENTRIES}" | grep -c .)" -eq 1 ] && [ -d "${RB_ENTRIES}" ]; then
    RB_ROOT="${RB_ENTRIES}"
  fi
  RB_FILES="$(find "${RB_ROOT}" -type f | wc -l | tr -d ' ')"
  # the same floor as the backup: restoring a near-empty tree is how a site disappears
  [ "${RB_FILES}" -ge 20 ] || die "${ROLLBACK_ARCHIVE} looks empty (${RB_FILES} files) — refusing to roll back onto it. Nothing was touched."
  RB_SHA="$(sed -n 's/^sha=//p' "${RB_ROOT}/build.txt" 2>/dev/null | head -1 || true)"
  step_done "Unpacked (${RB_FILES} files$([ -z "${RB_SHA}" ] || echo ", commit ${RB_SHA:0:12}"))"

  maybe_install_remote
  check_helper
  BACKUP_HINT=""
  BACKUP_WHERE=""
  if [ ${#RSYNC_MODE[@]} -eq 0 ]; then
    acquire_lock
    backup_remote "rolled back"
  fi

  step "Restoring the archive to ${SSH_USER}@${SSH_HOST}:${REMOTE_DIR}"
  RSYNC_LOG="${LOG_DIR}/rollback.log"
  run "${RSYNC_LOG}" rsync -az "${RSYNC_PROGRESS[@]}" --stats --itemize-changes "${RSYNC_MODE[@]}" "${SHIP_EXCLUDES[@]}" \
    -e "${SSH_RSH} -p ${SSH_PORT}" \
    "${RB_ROOT}/" \
    "${SSH_USER}@${SSH_HOST}:${REMOTE_DIR}" \
    || fail_with_log "${RSYNC_LOG}" "Restore failed — the remote may be half-restored${BACKUP_HINT}."
  XFERRED="$(grep -m1 'Number of regular files transferred' "${RSYNC_LOG}" | grep -oE '[0-9.,]+$' || echo '?')"
  step_done "Restored (${XFERRED} files transferred)"

  step "Mirroring content/posts from the archive"
  POSTS_LOG="${LOG_DIR}/rollback-posts.log"
  run "${POSTS_LOG}" rsync -az --delete --itemize-changes "${RSYNC_MODE[@]}" \
    -e "${SSH_RSH} -p ${SSH_PORT}" \
    "${RB_ROOT}/content/posts/" \
    "${SSH_USER}@${SSH_HOST}:${REMOTE_DIR}/content/posts/" \
    || fail_with_log "${POSTS_LOG}" "content/posts mirror failed${BACKUP_HINT}."
  GONE="$(grep -c '^\*deleting' "${POSTS_LOG}" || true)"
  step_done "content/posts mirrored (${GONE} file(s) removed)"

  echo
  if [ ${#RSYNC_MODE[@]} -ne 0 ]; then
    CHANGES="$(cat "${RSYNC_LOG}" "${POSTS_LOG}" | grep -E '^(\*deleting|[<>ch.][fLDS])' | grep -v '^\.' | sed -E 's/^[^ ]+ //' || true)"
    while IFS= read -r line; do [ -z "${line}" ] || note "${line}"; done < <(printf '%s\n' "${CHANGES}" | head -15)
    echo -e "${GREEN}${BOLD}Dry run done — nothing was touched.${NC}"
    exit 0
  fi
  # the server now holds the archive's post sources: the next push measures against those
  sync_state_write "${RB_ROOT}"
  echo -e "${GREEN}${BOLD}Rolled back to $(basename "${ROLLBACK_ARCHIVE}")${RB_SHA:+ (commit ${RB_SHA})}${NC}"
  note "the server as it was a moment ago: ${BACKUP_WHERE}"
  note "the next deploy ships its own tree again: fix forward on main"

  if [ "${DEPLOY_SMOKE:-1}" != "0" ]; then
    echo
    echo -e "${BOLD}Smoke checks${NC}"
    RB_SMOKE=()
    [ -z "${RB_SHA}" ] || RB_SMOKE=("--sha=${RB_SHA}")
    if ! run_smoke "${RB_SMOKE[@]}" | sed 's/^/  /'; then
      echo -e "${RED}✗ rolled back, but the smoke checks failed${BACKUP_HINT}.${NC}"
      exit 1
    fi
  fi
  exit 0
fi

# --- backup: the pre-deploy archive on its own, for the weekly copy of the whole site ---
if [ "${CMD}" = "backup" ]; then
  STEP_TOTAL=1
  echo -e "${BOLD}Backing up bfrackowiak.pl${NC}"
  echo -e "  source: ${SSH_USER}@${SSH_HOST}:${REMOTE_DIR}"
  echo -e "  target: ${BACKUP_ARCHIVE}"
  if [ ${#RSYNC_MODE[@]} -ne 0 ]; then
    echo -e "${GREEN}${BOLD}Dry run done — nothing was touched.${NC}"
    exit 0
  fi
  maybe_install_remote
  check_helper
  acquire_lock   # a deploy halfway through would be archived halfway through
  BACKUP_HINT=""
  BACKUP_WHERE=""
  backup_remote "backed up"
  echo
  echo -e "${GREEN}${BOLD}Backup: ${BACKUP_WHERE}${NC}"
  exit 0
fi

[ ${#RSYNC_MODE[@]} -eq 0 ] || STEP_TOTAL=6   # --dry-run skips the backup step

echo -e "${BOLD}Deploying bfrackowiak.pl${NC}"
echo -e "  target: ${SSH_USER}@${SSH_HOST}:${REMOTE_DIR}"
echo -e "  mode:   $([ ${#RSYNC_MODE[@]} -eq 0 ] && echo 'live' || echo 'dry-run')$([ ${#DELETE_FLAG[@]} -eq 0 ] || echo ', clean (deletes stale remote files)')$([ "${VERBOSE}" -eq 1 ] && echo ', verbose')"

# --- what the next CI deploy would undo ---
# CI deploys main from the repository. Whatever goes up from here that main does not carry
# is replaced by the next pipeline run, silently. Say so; deploying it is still allowed.
if [ "${DEPLOY_CI:-0}" != "1" ] && git -C "${LOCAL_DIR}" rev-parse --git-dir >/dev/null 2>&1; then
  if [ -n "$(shipped_changes)" ]; then
    warn "uncommitted changes: the next CI deploy will replace this deploy"
  fi
  if git -C "${LOCAL_DIR}" remote get-url origin >/dev/null 2>&1; then
    if timeout 10 git -C "${LOCAL_DIR}" fetch -q origin main >/dev/null 2>&1; then
      git -C "${LOCAL_DIR}" merge-base --is-ancestor HEAD origin/main 2>/dev/null \
        || warn "HEAD is not in main on origin: the next CI deploy will replace this deploy"
    else
      note "could not reach origin to check whether HEAD is pushed"
    fi
  fi
fi

# --- sanity: config must exist (endpoints read it at runtime) ---
if [ ! -f "${LOCAL_DIR}config.php" ]; then
  die "Missing config.php — copy config.example.php and fill it in."
fi

# --- 1. tests ---
step "Running test suite"
if [ "${DEPLOY_SKIP_TESTS:-0}" = "1" ]; then
  step_done "Skipped (DEPLOY_SKIP_TESTS=1: the caller ran them)"
else
  run "${LOG_DIR}/tests.log" php "${LOCAL_DIR}tests/run.php" \
    || fail_with_log "${LOG_DIR}/tests.log" "Test suite failed — nothing was deployed."
  # run.php prints one tally per script (unit, integration) and no grand total
  TEST_TALLY="$(grep -oE 'PASS: [0-9]+ +FAIL: [0-9]+' "${LOG_DIR}/tests.log" \
    | awk '{p += $2} END {if (NR) printf "%d checks", p}' || true)"
  step_done "Tests passed${TEST_TALLY:+ — ${TEST_TALLY}}"
fi

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
maybe_install_remote
check_helper
[ ${#RSYNC_MODE[@]} -ne 0 ] || acquire_lock
step "Checking the server is not ahead"
remote_hashes
known_hashes
LOCAL_H="$(sync_hashes_local)"
CONFLICTS=""
if [ -n "${REMOTE_H}" ]; then
  while IFS= read -r line; do
    [ -n "${line}" ] || continue
    RHASH="${line%% *}"
    RPATH="${line#* }"; RPATH="${RPATH# }"
    LHASH="$(printf '%s\n' "${LOCAL_H}" | awk -v p="${RPATH}" '$2==p {print $1; exit}')"
    [ "${RHASH}" != "${LHASH}" ] || continue                 # identical: nothing to decide
    KHASH="$(printf '%s\n' "${KNOWN_H}" | awk -v p="${RPATH}" '$2==p {print $1; exit}')"
    # remote still matches what we last agreed on, so the difference is our local edit going up
    [ "${RHASH}" != "${KHASH}" ] || continue
    CONFLICTS="${CONFLICTS}  ${RPATH}\n"
  done <<< "${REMOTE_H}"
fi

if [ -n "${CONFLICTS}" ] && [ "${FORCE_PUSH}" -eq 0 ]; then
  echo
  warn "These files changed on the server since the last deploy (review.php or admin.php):"
  echo -en "${CONFLICTS}"
  echo
  echo -e "${RED}✗ Run ./deploy.sh pull, commit, push — or ./deploy.sh --force to overwrite the server.${NC}"
  exit 3
fi
if [ -n "${CONFLICTS}" ]; then
  warn "--force: overwriting server-side changes in $(echo -en "${CONFLICTS}" | wc -l) file(s)"
  step_done "Guard overridden"
else
  step_done "Server is in step"
fi

# --- 4. backup remote -> local archive (skipped on --dry-run) ---
BACKUP_HINT=""   # only points at an archive once one actually exists
BACKUP_WHERE=""
[ ${#RSYNC_MODE[@]} -ne 0 ] || backup_remote "deployed"

# --- 5. deploy ---
# build.txt: which commit is live, since when, deployed by whom. The smoke checks compare it with
# the commit the pipeline deployed. Written into the tree only for a real deploy.
if [ ${#RSYNC_MODE[@]} -eq 0 ]; then
  BUILD_SHA="${DEPLOY_SHA:-}"
  if [ -z "${BUILD_SHA}" ]; then
    BUILD_SHA="$(git -C "${LOCAL_DIR}" rev-parse HEAD 2>/dev/null || echo unknown)"
    [ -z "$(shipped_changes)" ] || BUILD_SHA="${BUILD_SHA}-dirty"
  fi
  printf 'sha=%s\ndeployed=%s\nby=%s\n' "${BUILD_SHA}" "$(date -u +%Y-%m-%dT%H:%M:%SZ)" \
    "$([ "${DEPLOY_CI:-0}" = "1" ] && echo ci || echo laptop)" > "${LOCAL_DIR}build.txt"
fi
step "Syncing to ${SSH_USER}@${SSH_HOST}:${REMOTE_DIR}"

# What ships: index.php, comments.php, a.php, stats.php, admin.php, .htaccess,
# robots.txt, llms*.txt, sitemap.xml, feed.xml, favicons, photo, assets/, blog/,
# vendor/, lib/, tools/ (the server cron + admin "rebuild now" run tools/build.php),
# content/posts/ (the generator + comment-slug validation read it),
# content/geoip.dat (offline IP→country db) and content/.htaccess.
# What never ships: docs, drafts, tests, archives, backups, git, runtime data.
RSYNC_LOG="${LOG_DIR}/sync.log"
# A CI checkout is fresh: every file has a new mtime, and rsync's quick check would resend all
# of them. Compare by content there.
RSYNC_CMP=()
[ "${DEPLOY_CI:-0}" != "1" ] || RSYNC_CMP=(--checksum)
run "${RSYNC_LOG}" rsync -az "${RSYNC_CMP[@]}" "${RSYNC_PROGRESS[@]}" --stats --itemize-changes "${RSYNC_MODE[@]}" "${DELETE_FLAG[@]}" "${GEOIP_PROTECT[@]}" \
  "${SHIP_EXCLUDES[@]}" \
  -e "${SSH_RSH} -p ${SSH_PORT}" \
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
run "${POSTS_LOG}" rsync -az "${RSYNC_CMP[@]}" --delete --itemize-changes "${RSYNC_MODE[@]}" \
  -e "${SSH_RSH} -p ${SSH_PORT}" \
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

# --- 7. smoke checks of what just went live ---
if [ ${#RSYNC_MODE[@]} -eq 0 ] && [ "${DEPLOY_SMOKE:-1}" != "0" ]; then
  echo
  echo -e "${BOLD}Smoke checks${NC}"
  if ! run_smoke "--sha=${BUILD_SHA}" | sed 's/^/  /'; then
    echo -e "${RED}✗ deployed, but the smoke checks failed${BACKUP_HINT}.${NC}"
    undo_hint
    exit 1
  fi
fi

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
