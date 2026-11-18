# shellcheck shell=sh
# RIZQIM PITR: shared helpers for backup.sh, pitr-drill.sh, pitr-restore.sh, archive-*.sh and pitr-ci.sh (sourced).
#
# The callers set PITR_SCRIPT_DIR (their own directory) before sourcing. Everything is configured by environment, so
# the same code runs in the `backup` container (docker compose) and on a plain host with --local (two initdb clusters,
# a posix repository; scripts/pitr-ci.sh --local). Paths and ports:
#   PITR_RESTORE_ROOT   where restores and the drill write [/restore, the pitr_restore volume]
#   PITR_RESTORE_PORT   port of the scratch Postgres started on a restored copy [5499]
#   PITR_PG_BINDIR      directory of postgres/pg_ctl if they are not in PATH (local Ubuntu: /usr/lib/postgresql/16/bin)
#   PG* / DB_*          connection to the LIVE database for SQL (as backup.sh)
# Operator-facing messages are Uzbek; comments English.

PITR_LOG_TAG=${PITR_LOG_TAG:-pitr}

pitr_log() { printf '%s %s: %s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$PITR_LOG_TAG" "$*" >&2; }
pitr_die() { pitr_log "XATO: $*"; exit 1; }

pitr_enabled() { [ "${PITR_ENABLED:-0}" = "1" ]; }

if [ -n "${PITR_PG_BINDIR:-}" ]; then
    PATH="$PITR_PG_BINDIR:$PATH"
    export PATH
fi

# Loads infra/postgres/pitr-env.sh (baked into the images; the repository copy on a host) and exports PGBACKREST_*
pitr_load() {
    for _pl_f in "${PITR_ENV_FILE:-}" /usr/local/bin/pitr-env.sh "${PITR_SCRIPT_DIR:-.}/../infra/postgres/pitr-env.sh"; do
        if [ -n "$_pl_f" ] && [ -f "$_pl_f" ]; then
            # shellcheck source=infra/postgres/pitr-env.sh
            . "$_pl_f"
            pitr_env
            return 0
        fi
    done
    pitr_die "pitr-env.sh topilmadi (backup obrazi eskimi? docker compose up -d --build backup)"
}

# Runs a command as the postgres OS user: pgBackRest and the scratch server must own the files they write.
# In the containers the scripts run as root (backup.sh writes /backups); on a host they already run as that user.
pitr_as_pg() {
    if [ "$(id -u)" != "0" ]; then
        "$@"
    elif command -v gosu >/dev/null 2>&1; then
        gosu postgres "$@"
    elif command -v su-exec >/dev/null 2>&1; then
        su-exec postgres "$@"
    elif command -v runuser >/dev/null 2>&1; then
        # Plain hosts (scripts/pitr-ci.sh --local); keeps the environment
        runuser -u postgres -- "$@"
    else
        pitr_die "root sifatida ishlayapti, lekin gosu/su-exec yo'q (postgres foydalanuvchisiga o'tib bo'lmaydi)"
    fi
}

pgbr() { pitr_as_pg pgbackrest "$@"; }

# SQL on the live database (PG* environment). pitr_val prints one value, empty on error.
pitr_psql() { psql -X -q -v ON_ERROR_STOP=1 "$@"; }
pitr_val() { psql -X -A -t -q -v ON_ERROR_STOP=1 -c "$1" 2>/dev/null | head -n 1; }

# SQL literal: NULL for an empty value, else a quoted string (quotes doubled). Untyped, so Postgres coerces it to the
# column's type (timestamp, integer, json...).
pitr_sqlq() {
    if [ -z "$1" ]; then printf 'NULL'; else printf "'%s'" "$(printf '%s' "$1" | sed "s/'/''/g")"; fi
}

pitr_uuid() {
    if [ -r /proc/sys/kernel/random/uuid ]; then cat /proc/sys/kernel/random/uuid; else od -An -N16 -tx1 /dev/urandom | tr -d ' \n'; fi
}

pitr_now() { date -u +%s; }

# Seconds as "4 daq 12 s"
pitr_human_seconds() {
    _hs=${1%%.*}
    [ -n "$_hs" ] || { printf '-'; return 0; }
    if [ "$_hs" -ge 3600 ]; then printf '%s soat %s daq %s s' $((_hs / 3600)) $((_hs % 3600 / 60)) $((_hs % 60))
    elif [ "$_hs" -ge 60 ]; then printf '%s daq %s s' $((_hs / 60)) $((_hs % 60))
    else printf '%s s' "$_hs"; fi
}

# monitoring_markers upsert (same table and shape as backup.sh record_marker). $2 is an SQL expression yielding json,
# evaluated with the psql variables given after it (-v name=value ...).
pitr_marker() { # pitr_marker <key> <json-sql> [psql args...]
    _pm_key=$1
    _pm_expr=$2
    shift 2
    if ! psql -X -q -v ON_ERROR_STOP=1 -v "mkey=$_pm_key" "$@" >/dev/null 2>&1 <<SQL
INSERT INTO monitoring_markers (key, recorded_at, meta)
SELECT :'mkey', now() AT TIME ZONE 'UTC', ($_pm_expr)
ON CONFLICT (key) DO UPDATE SET recorded_at = EXCLUDED.recorded_at, meta = EXCLUDED.meta;
SQL
    then
        pitr_log "OGOHLANTIRISH: '$_pm_key' belgisini markazga yozib bo'lmadi (monitoring_markers jadvali yo'qmi? migratsiyani bajaring)"
        return 1
    fi
    return 0
}

# --- archiver state (live) -------------------------------------------------------------------------------------------
# Prints "<archived-wal>|<archived-epoch>|<failed-wal>|<failed-epoch>|<failed-count>"
pitr_archiver_state() {
    pitr_val "SELECT coalesce(last_archived_wal,'') || '|' || coalesce(extract(epoch from last_archived_time)::bigint::text,'') || '|' || coalesce(last_failed_wal,'') || '|' || coalesce(extract(epoch from last_failed_time)::bigint::text,'') || '|' || failed_count FROM pg_stat_archiver"
}

# Waits until the WAL segment holding LSN $1 is archived (pg_stat_archiver). Prints the seconds waited. Fails after
# $2 seconds. Archive status names compare as strings within a timeline; history files are skipped.
pitr_wait_archived() { # pitr_wait_archived <lsn> <timeout-seconds>
    _wa_file=$(pitr_val "SELECT pg_walfile_name('$1'::pg_lsn)")
    [ -n "$_wa_file" ] || return 1
    _wa_start=$(pitr_now)
    while :; do
        _wa_ok=$(pitr_val "SELECT last_archived_wal ~ '^[0-9A-F]{24}\$' AND last_archived_wal >= '$_wa_file' FROM pg_stat_archiver")
        if [ "$_wa_ok" = "t" ]; then
            printf '%s' $(($(pitr_now) - _wa_start))
            return 0
        fi
        if [ $(($(pitr_now) - _wa_start)) -ge "$2" ]; then
            return 1
        fi
        sleep 1
    done
}

# --- restore + scratch server (drill and pitr-restore) ---------------------------------------------------------------
# Refuses any restore target that is the live data directory or inside it (by path and by device:inode, so the same
# volume mounted twice is caught too).
pitr_guard_target() { # pitr_guard_target <dir>
    _gt_live=${PGBACKREST_PG1_PATH:-/var/lib/postgresql/data}
    _gt_dir=$1
    case "$_gt_dir" in /*) ;; *) pitr_die "tiklash papkasi mutlaq yo'l bo'lishi kerak: $_gt_dir" ;; esac
    case "$_gt_dir/" in "$_gt_live"/*) pitr_die "tiklash papkasi ish bazasining papkasi yoki uning ichida: $_gt_dir (RAD ETILDI: ish ma'lumoti hech qachon ustiga yozilmaydi)" ;; esac
    if [ -d "$_gt_live" ] && [ -d "$_gt_dir" ]; then
        if [ "$(stat -c %d:%i "$_gt_live")" = "$(stat -c %d:%i "$_gt_dir")" ]; then
            pitr_die "tiklash papkasi ish bazasining o'zi (bir volume ikki joyga ulangan): $_gt_dir (RAD ETILDI)"
        fi
    fi
    if [ -f "$_gt_dir/postmaster.pid" ]; then
        pitr_die "$_gt_dir da ishlab turgan Postgres bor (postmaster.pid): RAD ETILDI"
    fi
}

# Environment for a scratch server and its archive-get: own data path, synchronous archive-get (no spool, no
# archive lock shared with the live push), own lock directory.
pitr_scratch_env() { # pitr_scratch_env <data-dir>
    PGBACKREST_PG1_PATH=$1
    PGBACKREST_ARCHIVE_ASYNC=n
    PGBACKREST_LOCK_PATH=${PITR_SCRATCH_LOCK_PATH:-/tmp/pgbackrest-scratch}
    PGBACKREST_LOG_LEVEL_CONSOLE=warn
    export PGBACKREST_PG1_PATH PGBACKREST_ARCHIVE_ASYNC PGBACKREST_LOCK_PATH PGBACKREST_LOG_LEVEL_CONSOLE
}

# pgBackRest restore to a time into a new, empty directory. Prints pgBackRest's output to $3 (log file).
pitr_restore_to() { # pitr_restore_to <dir> <target-time> <log> [repo]
    pitr_guard_target "$1"
    if [ -e "$1" ] && [ -n "$(ls -A "$1" 2>/dev/null)" ]; then
        pitr_die "$1 bo'sh emas: tiklash faqat YANGI papkaga (boshqa nom bering yoki eski nusxani o'chiring)"
    fi
    mkdir -p "$1"
    if [ "$(id -u)" = "0" ]; then chown postgres:postgres "$1"; fi
    chmod 0700 "$1"
    _rt_repo=${4:-1}
    # --archive-mode=off: the restored copy must never push its new timeline into the live stanza.
    # --target-timeline=current: stay on the timeline of the chosen backup. Postgres' default ("latest") would follow
    # the newest timeline in the archive, which after a switch-over (or a failed, rolled-back attempt that archived its
    # history) is a branch the chosen backup is not on: the restore then fails with "could not locate required
    # checkpoint record"
    ( pitr_scratch_env "$1"
      pgbr restore --repo="$_rt_repo" --pg1-path="$1" --type=time --target="$2" --target-action=promote \
          --target-timeline=current --archive-mode=off --log-level-console=info ) >"$3" 2>&1
}

# Starts a scratch Postgres on a restored directory (no archiving, no hot standby: it accepts connections once
# promoted). Port and socket directory from the arguments; log to $4.
pitr_start_scratch() { # pitr_start_scratch <dir> <port> <socket-dir> <log>
    mkdir -p "$3"
    if [ "$(id -u)" = "0" ]; then chown postgres:postgres "$3" "$(dirname "$4")" 2>/dev/null || true; fi
    : >"$4"
    if [ "$(id -u)" = "0" ]; then chown postgres:postgres "$4"; fi
    ( pitr_scratch_env "$1"
      pitr_as_pg pg_ctl -D "$1" -l "$4" -w -t 5 \
          -o "-p $2 -c listen_addresses='*' -c unix_socket_directories=$3 -c archive_mode=off -c hot_standby=off -c shared_buffers=${PITR_DRILL_SHARED_BUFFERS:-64MB} -c max_wal_size=1GB -c logging_collector=off" \
          start >/dev/null 2>&1 ) || true
    # pg_ctl -w returns early while recovery runs (no connections with hot_standby=off): the caller waits
    [ -f "$1/postmaster.pid" ] || { sleep 2; [ -f "$1/postmaster.pid" ]; }
}

pitr_stop_scratch() { # pitr_stop_scratch <dir>
    if [ -f "$1/postmaster.pid" ]; then
        pitr_as_pg pg_ctl -D "$1" -m fast -w -t 120 stop >/dev/null 2>&1 || pitr_as_pg pg_ctl -D "$1" -m immediate -w -t 60 stop >/dev/null 2>&1 || true
    fi
}

# Waits until the scratch server is promoted (pg_is_in_recovery() = f). Fails if the server exits or after $4 s.
pitr_wait_promoted() { # pitr_wait_promoted <dir> <port> <socket-dir> <timeout> <log>
    _wp_start=$(pitr_now)
    while :; do
        _wp_r=$(psql -X -A -t -q -h "$3" -p "$2" -d "${PGDATABASE:-postgres}" -c "SELECT pg_is_in_recovery()" 2>/dev/null || true)
        [ "$_wp_r" = "f" ] && return 0
        if [ ! -f "$1/postmaster.pid" ]; then
            pitr_log "tiklangan server to'xtab qoldi; logning oxiri:"
            tail -n 20 "$5" >&2 || true
            return 1
        fi
        if [ $(($(pitr_now) - _wp_start)) -ge "$4" ]; then
            pitr_log "tiklangan server $4 s ichida tayyor bo'lmadi; logning oxiri:"
            tail -n 20 "$5" >&2 || true
            return 1
        fi
        sleep 1
    done
}

# Last transaction replayed, from the scratch server's log: "last completed transaction was at log time ...".
pitr_recovery_point() { # pitr_recovery_point <log>
    sed -n 's/.*last completed transaction was at log time \(.*\)$/\1/p' "$1" | tail -n 1
}

# "1 2026-11-17 21:30:05" etc: the base backup pgBackRest chose
pitr_backup_label() { # pitr_backup_label <restore-log>
    sed -n 's/.*restore backup set \([0-9A-Za-z_-]*\).*/\1/p' "$1" | tail -n 1
}

# --- continuity of the archived WAL (gap detection) ---------------------------------------------------------------
# When the archive target is down longer than the spool cap (PITR_ARCHIVE_QUEUE_MAX), pgBackRest DROPS WAL and reports
# success: pg_stat_archiver then looks healthy although the chain has a hole. This looks at the repository itself:
# the WAL segment files of one timeline must form one continuous run from the oldest retained backup to the newest
# segment. Prints KEY=VALUE lines:
#   TLI, RANGES (number of continuous runs from the oldest backup's start on), LAST_FROM/LAST_TO (segment names of the
#   newest run), LAST_TO_TIME (epoch the newest segment was archived), GAP=0|1, GAP_AFTER/GAP_BEFORE (segments around
#   the newest hole), GAP_AFTER_TIME/GAP_BEFORE_TIME (epochs), BACKUP_IN_LAST_RUN (label of the oldest backup whose WAL
#   starts in the newest run, empty = none: nothing after the hole is restorable yet), WINDOW_FROM (stop epoch of that
#   backup), WINDOWS (all restorable windows "from_epoch-to_epoch,...", oldest first) and one HOLE line per hole, oldest
#   first: "HOLE <after-seg> <after-time> <before-seg> <before-time> <backup-after-label|-> <its-stop|->"
# The segment size comes from the live server (wal_segment_size, bytes); 16 MB when unknown.
pitr_wal_report() { # pitr_wal_report <repo> [timeline-decimal] [wal_segment_size bytes]
    _wr_json=$(pgbr repo-ls --repo="$1" --recurse --output=json "archive/$PGBACKREST_STANZA" 2>/dev/null) || return 1
    _wr_info=$(pgbr info --repo="$1" --output=json 2>/dev/null) || return 1
    {
        printf '%s' "$_wr_json" | sed 's/},"/}\n"/g' \
            | sed -n 's/^"[^"]*\/\([0-9A-F]\{24\}\)-[0-9a-f]*\(\.[a-z0-9]*\)\{0,1\}":{"type":"file","size":[0-9]*,"time":\([0-9]*\)}.*/S \1 \3/p'
        printf '%s' "$_wr_info" | sed 's/"archive":{"start":"/\n@/g' \
            | sed -n 's/^@\([0-9A-F]\{24\}\)".*"label":"\([^"]*\)".*"timestamp":{"start":[0-9]*,"stop":\([0-9]*\)}.*/B \1 \3 \2/p'
    } | sort -k2,2 | awk -v want="${2:-}" -v perlog="$((4294967296 / ${3:-16777216}))" '
        function hex(s,   i, v) { v = 0; for (i = 1; i <= length(s); i++) v = v * 16 + index("0123456789ABCDEF", substr(s, i, 1)) - 1; return v }
        function idx(n) { return hex(substr(n, 9, 8)) * perlog + hex(substr(n, 17, 8)) }
        $1 == "S" { ns++; sn[ns] = $2; st[ns] = $3; tl = hex(substr($2, 1, 8)); if (tl > maxtl) maxtl = tl }
        $1 == "B" { nb++; bs[nb] = $2; bt[nb] = $3; bl[nb] = $4 }
        END {
            tli = (want != "" ? want + 0 : maxtl)
            printf "TLI=%d\n", tli
            # backups on this timeline, oldest start
            first = -1
            for (i = 1; i <= nb; i++) if (hex(substr(bs[i], 1, 8)) == tli) { x = idx(bs[i]); if (first < 0 || x < first) first = x }
            runs = 0; prev = -2
            for (i = 1; i <= ns; i++) {
                if (hex(substr(sn[i], 1, 8)) != tli) continue
                x = idx(sn[i])
                if (first >= 0 && x < first) continue
                if (x == prev) continue
                if (x != prev + 1) { runs++; rf[runs] = sn[i]; rft[runs] = st[i]; rfi[runs] = x }
                rl[runs] = sn[i]; rlt[runs] = st[i]; rli[runs] = x; prev = x
            }
            printf "RANGES=%d\n", runs
            if (runs == 0) { print "GAP=0"; exit }
            # the oldest backup whose WAL starts in each run (a window opens there)
            for (r = 1; r <= runs; r++) {
                lab[r] = ""; from[r] = -1
                for (i = 1; i <= nb; i++) {
                    if (hex(substr(bs[i], 1, 8)) != tli) continue
                    x = idx(bs[i])
                    if (x >= rfi[r] && x <= rli[r] && (from[r] < 0 || bt[i] < from[r])) { from[r] = bt[i]; lab[r] = bl[i] }
                }
            }
            printf "LAST_FROM=%s\nLAST_TO=%s\nLAST_TO_TIME=%s\n", rf[runs], rl[runs], rlt[runs]
            if (runs > 1) printf "GAP=1\nGAP_AFTER=%s\nGAP_AFTER_TIME=%s\nGAP_BEFORE=%s\nGAP_BEFORE_TIME=%s\n", rl[runs-1], rlt[runs-1], rf[runs], rft[runs]
            else print "GAP=0"
            # every hole: HOLE <after-seg> <after-time> <before-seg> <before-time> <first backup after it: label stop|- ->
            # (no backup after a hole = nothing after it is restorable yet: an open gap)
            for (r = 2; r <= runs; r++) {
                al = "-"; at = "-"
                for (q = r; q <= runs; q++) if (from[q] >= 0) { al = lab[q]; at = from[q]; break }
                printf "HOLE %s %s %s %s %s %s\n", rl[r-1], rlt[r-1], rf[r], rft[r], al, at
            }
            w = ""
            for (r = 1; r <= runs; r++) if (from[r] >= 0) w = w (w == "" ? "" : ",") from[r] "-" rlt[r]
            printf "BACKUP_IN_LAST_RUN=%s\nWINDOW_FROM=%s\n", lab[runs], (from[runs] >= 0 ? from[runs] : "")
            printf "WINDOWS=%s\n", w
        }'
}

pitr_epoch_utc() { # epoch -> "YYYY-MM-DD HH:MM:SS" (busybox and GNU date)
    date -u -d "@$1" '+%Y-%m-%d %H:%M:%S' 2>/dev/null || printf '%s' "$1"
}

# --- rclone (S3 for the journal archive) ---------------------------------------------------------------------------
# ARCHIVE_S3_* (each falling back to the BACKUP_S3_* key of the nightly dump). The archive must never be deletable:
# the safe layout is its own bucket (Object Lock / versioning) or at least its own key WITHOUT delete permission,
# while pgBackRest (PITR_S3_*) needs delete permission to expire old backups. Remote name "archive", env only.
pitr_archive_s3_load() {
    AR_ENDPOINT=${ARCHIVE_S3_ENDPOINT:-${BACKUP_S3_ENDPOINT:-}}
    AR_BUCKET=${ARCHIVE_S3_BUCKET:-${BACKUP_S3_BUCKET:-}}
    AR_KEY=${ARCHIVE_S3_ACCESS_KEY:-${BACKUP_S3_ACCESS_KEY:-}}
    AR_SECRET=${ARCHIVE_S3_SECRET:-${BACKUP_S3_SECRET:-}}
    AR_REGION=${ARCHIVE_S3_REGION:-${BACKUP_S3_REGION:-}}
    AR_PREFIX=$(printf '%s' "${ARCHIVE_S3_PREFIX:-${BACKUP_S3_PREFIX:-}}" | sed -e 's#^/*##' -e 's#/*$##')
}

pitr_s3_configured() {
    pitr_archive_s3_load
    [ -n "$AR_ENDPOINT" ] && [ -n "$AR_BUCKET" ] && [ -n "$AR_KEY" ] && [ -n "$AR_SECRET" ]
}

pitr_rclone_env() {
    pitr_archive_s3_load
    export RCLONE_CONFIG_ARCHIVE_TYPE=s3
    export RCLONE_CONFIG_ARCHIVE_PROVIDER=Other
    export RCLONE_CONFIG_ARCHIVE_ENDPOINT="$AR_ENDPOINT"
    export RCLONE_CONFIG_ARCHIVE_ACCESS_KEY_ID="$AR_KEY"
    export RCLONE_CONFIG_ARCHIVE_SECRET_ACCESS_KEY="$AR_SECRET"
    export RCLONE_CONFIG_ARCHIVE_NO_CHECK_BUCKET=true
    if [ -n "$AR_REGION" ]; then export RCLONE_CONFIG_ARCHIVE_REGION="$AR_REGION"; fi
}

# archive:<bucket>[/<prefix>]/archive (no trailing slash)
pitr_s3_target() {
    pitr_archive_s3_load
    printf 'archive:%s/%sarchive' "$AR_BUCKET" "${AR_PREFIX:+$AR_PREFIX/}"
}

# s3://<bucket>/[<prefix>/]archive/<rest>
pitr_s3_uri() { pitr_archive_s3_load; printf 's3://%s/%sarchive/%s' "$AR_BUCKET" "${AR_PREFIX:+$AR_PREFIX/}" "$1"; }

# Live connection settings like backup.sh: PG*, then DB_*, then backend/.env
pitr_resolve_conn() {
    _rc_root=$(cd "${PITR_SCRIPT_DIR:-.}/.." && pwd)
    for _rc_pair in PGHOST:DB_HOST:127.0.0.1 PGPORT:DB_PORT:5432 PGUSER:DB_USERNAME: PGPASSWORD:DB_PASSWORD: PGDATABASE:DB_DATABASE:; do
        _rc_pg=${_rc_pair%%:*}; _rc_rest=${_rc_pair#*:}; _rc_db=${_rc_rest%%:*}; _rc_def=${_rc_rest#*:}
        eval "_rc_v=\${$_rc_pg:-}"
        [ -n "$_rc_v" ] || eval "_rc_v=\${$_rc_db:-}"
        if [ -z "$_rc_v" ] && [ -f "$_rc_root/backend/.env" ]; then
            _rc_v=$(sed -n "s/^$_rc_db=//p" "$_rc_root/backend/.env" | tail -n 1 | sed -e 's/^"\(.*\)"$/\1/' -e "s/^'\(.*\)'\$/\1/")
        fi
        [ -n "$_rc_v" ] || _rc_v=$_rc_def
        eval "$_rc_pg=\$_rc_v"
        eval "export $_rc_pg"
    done
}
