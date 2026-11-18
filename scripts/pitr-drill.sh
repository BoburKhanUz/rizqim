#!/bin/sh
# RIZQIM: tiklash mashqi (PITR restore drill), `backup` konteyneri ichida. Odatda to'g'ridan-to'g'ri emas, xostdagi
# bitta buyruq orqali ishga tushiriladi: scripts/restore-drill.sh (u ilova tekshiruvini ham qo'shadi). Oylik avtomatik
# mashqni `backup` demoni (scripts/backup.sh --daemon) shu skript bilan bajaradi.
#
# Foydalanish (konteyner ichida):
#   pitr-drill.sh run [--target-time "YYYY-MM-DD HH:MM:SS+00"] [--expect-token <uuid>] [--repo 1|2] [--keep]
#   pitr-drill.sh finish [--smoke ok|failed|skipped]     (--keep dan keyin: serverni to'xtatadi, natijani yozadi)
#   pitr-drill.sh cleanup                                (qolib ketgan mashq serverini to'xtatib, papkani o'chiradi)
#
# Qadamlar: (1) tekshiruv: PITR yoqiq, `pgbackrest check`, bo'sh joy >= 1.2 x baza hajmi; (2) ish bazasiga
# monitoring_markers 'drill_probe' belgisi (token1), T = uning vaqti + 2 s (soniyaga yaxlitlangan), T dan keyin token2 yoziladi va uning WAL'i
# arxivga yetguncha kutiladi (shu kutish = ma'lumot yo'qotish oynasi); (3) pgBackRest T ga tiklaydi (yangi papka
# $PITR_RESTORE_ROOT/drill), ikkinchi Postgres $PITR_RESTORE_PORT portida ochiladi, promote bo'lguncha vaqt = tiklash
# vaqti; (4) tekshiruvlar: token1 bor, token2 yo'q, asosiy jadvallar soni (T - 5 daq gacha) bir xil, migratsiyalar bir
# xil, pg_amcheck (eng faol 10 jadval), bo'lingan jadvallar va audit_logs_append_only trigger; (5) natija Uzbek tilida
# va restore_drills jadvaliga bitta qator; server to'xtatiladi, papka o'chiriladi.
#
# --target-time: T ni o'zingiz berasiz (CI: scripts/pitr-ci.sh); token1 o'rniga --expect-token tekshiriladi.
# Mashina uchun natija stdout'da KEY=VALUE qatorlari: DRILL_STATUS, RESTORE_SECONDS, RECOVERY_POINT, DATA_LOSS_SECONDS,
# TARGET_TIME, BACKUP_LABEL, CHECK_<nom>.
# Sozlamalar: PITR_RESTORE_ROOT [/restore], PITR_RESTORE_PORT [5499], PITR_DRILL_SOCKET_DIR [<root>/drill-socket],
# PITR_DRILL_ARCHIVE_TIMEOUT [240], PITR_DRILL_TIMEOUT [7200], PITR_DRILL_AMCHECK [1], PITR_DRILL_EXTRA_CHECK_SQL va
# PITR_DRILL_EXTRA_CHECK_EXPECT (qo'shimcha tekshiruv: tiklangan nusxada SQL natijasi kutilganga teng bo'lsin).

set -eu
umask 077

PITR_SCRIPT_DIR=$(cd "$(dirname "$0")" && pwd)
PITR_LOG_TAG=drill
# shellcheck source=scripts/pitr.sh
. "$PITR_SCRIPT_DIR/pitr.sh"

PGTZ=UTC; export PGTZ
pitr_resolve_conn

ROOT=${PITR_RESTORE_ROOT:-/restore}
PORT=${PITR_RESTORE_PORT:-5499}
DIR=$ROOT/drill
SOCK=${PITR_DRILL_SOCKET_DIR:-$ROOT/drill-socket}
STATE=$ROOT/drill.state
SRVLOG=$ROOT/drill-server.log
RLOG=$ROOT/drill-restore.log
ARCHIVE_TIMEOUT=${PITR_DRILL_ARCHIVE_TIMEOUT:-240}
DRILL_TIMEOUT=${PITR_DRILL_TIMEOUT:-7200}
MANIFEST_TABLES="orders order_items payments fiscal_receipts audit_logs notification_logs users stores"

# --- state (survives between `run --keep` and `finish`) ---------------------------------------------------------------
STARTED_AT=; TARGET_TIME=; REACHED=; RESTORE_SECONDS=; DATA_LOSS_SECONDS=; BACKUP_LABEL=; REPO=1; CHECKS=; ERRORS=
WAL_RESTORED=; PROBE_TOKEN=; TRIGGER=auto

save_state() {
    {
        for v in STARTED_AT TARGET_TIME REACHED RESTORE_SECONDS DATA_LOSS_SECONDS BACKUP_LABEL REPO CHECKS ERRORS WAL_RESTORED PROBE_TOKEN TRIGGER; do
            eval "val=\${$v}"
            # shellcheck disable=SC2154
            printf "%s='%s'\n" "$v" "$(printf '%s' "$val" | sed "s/'/'\\\\''/g")"
        done
    } >"$STATE"
}

add_check() { CHECKS="${CHECKS:+$CHECKS }$1=$2"; }
add_error() { ERRORS="${ERRORS:+$ERRORS; }$1"; }

# {"probe": "ok", ..., "app_smoke": "ok|failed|skipped", "trigger": "manual|auto"}. trigger = auto: the monthly drill
# of the backup daemon, which has no PHP: app_smoke is always "skipped" there (only scripts/restore-drill.sh runs it)
checks_json() {
    out="{\"trigger\":\"$TRIGGER\""
    sep=,
    for c in $CHECKS; do
        out="$out$sep\"${c%%=*}\":\"${c#*=}\""
        sep=,
    done
    printf '%s}' "$out"
}

all_ok() {
    for c in $CHECKS; do
        case "${c#*=}" in ok | skipped) ;; *) return 1 ;; esac
    done
    [ -z "$ERRORS" ]
}

rpsql() { psql -X -A -t -q -h "$SOCK" -p "$PORT" -v ON_ERROR_STOP=1 -c "$1" 2>/dev/null | head -n 1; }

cleanup_scratch() {
    pitr_stop_scratch "$DIR"
    rm -rf "$DIR" "$SOCK"
}

# --- record + report ----------------------------------------------------------------------------------------------
record_row() { # record_row <status>
    if [ "$(pitr_val "SELECT to_regclass('public.restore_drills') IS NOT NULL")" != "t" ]; then
        pitr_log "OGOHLANTIRISH: restore_drills jadvali yo'q (migratsiya bajarilmagan): natija bazaga yozilmadi"
        return 0
    fi
    extra_cols=
    extra_vals=
    for col in created_at updated_at; do
        if [ "$(pitr_val "SELECT count(*) FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'restore_drills' AND column_name = '$col'")" = "1" ]; then
            extra_cols="$extra_cols, $col"
            extra_vals="$extra_vals, now() AT TIME ZONE 'UTC'"
        fi
    done
    if ! pitr_psql -c "INSERT INTO restore_drills (started_at, finished_at, status, repo, target_time, reached_time, restore_seconds, data_loss_seconds, backup_label, checks, error$extra_cols) VALUES ($(pitr_sqlq "$STARTED_AT"), now() AT TIME ZONE 'UTC', $(pitr_sqlq "$1"), $(pitr_sqlq "$REPO"), $(pitr_sqlq "${TARGET_TIME%+00}"), $(pitr_sqlq "$REACHED"), $(pitr_sqlq "$RESTORE_SECONDS"), $(pitr_sqlq "$DATA_LOSS_SECONDS"), $(pitr_sqlq "$BACKUP_LABEL"), $(pitr_sqlq "$(checks_json)"), $(pitr_sqlq "$ERRORS")$extra_vals)" >/dev/null; then
        pitr_log "OGOHLANTIRISH: restore_drills ga yozib bo'lmadi"
    fi
}

report() { # report <status>
    loss=-
    [ -z "$DATA_LOSS_SECONDS" ] || loss=$(pitr_human_seconds "$DATA_LOSS_SECONDS")
    {
        echo "=============================================================="
        if [ "$1" = "ok" ]; then echo "TIKLASH MASHQI: MUVAFFAQIYATLI"; else echo "TIKLASH MASHQI: MUVAFFAQIYATSIZ"; fi
        [ "$TRIGGER" != "auto" ] || echo "(avtomatik mashq: ilova tekshiruvi bajarilmadi, uni faqat scripts/restore-drill.sh bajaradi)"
        echo "Tiklash vaqti: $(pitr_human_seconds "${RESTORE_SECONDS:-}")"
        echo "Qaytarilgan nuqta: ${REACHED:--} (UTC)"
        echo "Ma'lumot yo'qotish oynasi: $loss"
        echo "Maqsad vaqt (T): ${TARGET_TIME%+00} (UTC); repo$REPO; asosiy zaxira: ${BACKUP_LABEL:--}; arxivdan olingan WAL: ${WAL_RESTORED:-0}"
        for c in $CHECKS; do
            case "${c#*=}" in ok) mark="to'g'ri" ;; skipped) mark="o'tkazib yuborildi" ;; *) mark="XATO" ;; esac
            echo "  tekshiruv ${c%%=*}: $mark"
        done
        [ -z "$ERRORS" ] || echo "Xatolar: $ERRORS"
        echo "=============================================================="
    } >&2
    echo "DRILL_STATUS=$1"
    echo "RESTORE_SECONDS=${RESTORE_SECONDS:-}"
    echo "RECOVERY_POINT=${REACHED:-}"
    echo "DATA_LOSS_SECONDS=${DATA_LOSS_SECONDS:-}"
    echo "TARGET_TIME=${TARGET_TIME%+00}"
    echo "BACKUP_LABEL=${BACKUP_LABEL:-}"
    echo "PROBE_TOKEN=${PROBE_TOKEN:-}"
    echo "TRIGGER=$TRIGGER"
    for c in $CHECKS; do echo "CHECK_${c%%=*}=${c#*=}"; done
}

finish() { # finish <status>
    cleanup_scratch
    record_row "$1"
    report "$1"
    rm -f "$STATE"
    [ "$1" = "ok" ]
}

fail() {
    add_error "$1"
    pitr_log "XATO: $1"
    finish failed || exit 1
}

# --- run ----------------------------------------------------------------------------------------------------------
run() {
    target=
    token1=
    keep=0
    while [ $# -gt 0 ]; do
        case "$1" in
            --target-time) target=${2:-}; shift 2 ;;
            --expect-token) token1=${2:-}; shift 2 ;;
            --repo) REPO=${2:-1}; shift 2 ;;
            --keep) keep=1; TRIGGER=manual; shift ;;
            --ci) shift ;;
            *) pitr_die "noma'lum argument: $1" ;;
        esac
    done
    case "$REPO" in 1 | 2) ;; *) pitr_die "--repo 1 yoki 2 bo'lishi kerak" ;; esac

    pitr_enabled || pitr_die "PITR o'chiq (ildiz .env da PITR_ENABLED=1, so'ng docker compose up -d --build): mashq qilinadigan narsa yo'q"
    pitr_load
    note=${PITR_REPO2_NOTE:-"PITR_S3_* va BACKUP_S3_* bo'sh"}
    [ "$REPO" = "1" ] || [ "$PITR_REPO2" = "on" ] || pitr_die "repo2 (S3) sozlanmagan: $note"
    mkdir -p "$ROOT"
    if [ -f "$STATE" ] || [ -d "$DIR" ]; then
        pitr_log "avvalgi mashqdan qolgan server/papka tozalanmoqda"
        cleanup_scratch
        rm -f "$STATE"
    fi
    STARTED_AT=$(date -u '+%Y-%m-%d %H:%M:%S')

    # (1) preflight
    if ! pgbr check >"$ROOT/drill-check.log" 2>&1; then
        fail "pgbackrest check o'tmadi (WAL arxivga tushmayapti?): $(grep -E 'ERROR|HINT' "$ROOT/drill-check.log" | tail -n 2 | tr '\n' ' ')"
    fi
    db_bytes=$(pitr_val "SELECT sum(pg_database_size(datname))::bigint FROM pg_database WHERE datallowconn")
    [ -n "$db_bytes" ] || fail "ish bazasiga ulanib bo'lmadi ($PGHOST:$PGPORT)"
    free_kb=$(df -Pk "$ROOT" | awk 'NR == 2 { print $4 }')
    need_kb=$((db_bytes * 12 / 10 / 1024))
    if [ "$free_kb" -lt "$need_kb" ]; then
        fail "joy yetmaydi: $ROOT da $((free_kb / 1024)) MB bo'sh, kerak ~$((need_kb / 1024)) MB (baza x 1.2). Diskni bo'shating yoki mashqni boshqa serverda S3 dan bajaring"
    fi

    # (2) markers and T
    # T is at least one full second after the statement starts: with + 1 s a token1 written in the last milliseconds
    # of a second could commit after T, and recovery to T would then stop before it (a false "token1 missing").
    probe_sql() { pitr_val "INSERT INTO monitoring_markers (key, recorded_at, meta) VALUES ('drill_probe', now() AT TIME ZONE 'UTC', json_build_object('token', '$1', 'phase', '$2')) ON CONFLICT (key) DO UPDATE SET recorded_at = EXCLUDED.recorded_at, meta = EXCLUDED.meta RETURNING to_char(date_trunc('second', now() + interval '2 seconds') AT TIME ZONE 'UTC', 'YYYY-MM-DD HH24:MI:SS')"; }
    if [ -z "$target" ]; then
        token1=$(pitr_uuid)
        t=$(probe_sql "$token1" before)
        [ -n "$t" ] || fail "drill_probe belgisini yozib bo'lmadi (monitoring_markers jadvali yo'qmi?)"
        TARGET_TIME="$t+00"
    else
        t=$(pitr_val "SELECT to_char('$(printf '%s' "$target" | sed "s/'/''/g")'::timestamptz AT TIME ZONE 'UTC', 'YYYY-MM-DD HH24:MI:SS')")
        [ -n "$t" ] || fail "vaqt noto'g'ri: $target"
        TARGET_TIME="$t+00"
    fi
    # token2 must be committed after T: it is the first commit recovery stops before, and it must be archived
    waited=0
    while [ "$(pitr_val "SELECT clock_timestamp() > '$TARGET_TIME'::timestamptz")" != "t" ]; do
        sleep 1
        waited=$((waited + 1))
        [ "$waited" -lt 120 ] || fail "maqsad vaqt kelajakda: $TARGET_TIME"
    done
    token2=$(pitr_uuid)
    [ -n "$(probe_sql "$token2" after)" ] || fail "ikkinchi drill_probe belgisini yozib bo'lmadi"
    lsn=$(pitr_val "SELECT pg_current_wal_lsn()")
    pitr_log "T = $TARGET_TIME; token2 ning WAL'i arxivga yetishi kutilmoqda (eng ko'pi ${ARCHIVE_TIMEOUT} s)"
    if ! DATA_LOSS_SECONDS=$(pitr_wait_archived "$lsn" "$ARCHIVE_TIMEOUT"); then
        fail "WAL ${ARCHIVE_TIMEOUT} s ichida arxivga yetmadi (pg_stat_archiver: $(pitr_archiver_state)): arxivlash ishlamayapti"
    fi

    # manifest of the live database as of T - 5 min
    manifest_sql=
    for tbl in $MANIFEST_TABLES; do
        col=$(pitr_val "SELECT column_name FROM information_schema.columns WHERE table_schema = 'public' AND table_name = '$tbl' AND column_name IN ('created_at', 'sent_at') ORDER BY column_name LIMIT 1")
        [ -n "$col" ] || continue
        manifest_sql="${manifest_sql:+$manifest_sql || ' ' || }'$tbl=' || (SELECT count(*) FROM public.$tbl WHERE $col <= '$TARGET_TIME'::timestamptz - interval '5 minutes')"
    done
    live_manifest=
    [ -z "$manifest_sql" ] || live_manifest=$(pitr_val "SELECT $manifest_sql")
    mig_sql="SELECT count(*) || ':' || coalesce(max(migration), '') FROM migrations"
    live_mig=
    [ "$(pitr_val "SELECT to_regclass('public.migrations') IS NOT NULL")" != "t" ] || live_mig=$(pitr_val "$mig_sql")
    parts_sql="SELECT coalesce(string_agg(c.relname, ',' ORDER BY c.relname), '') FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace WHERE n.nspname = 'public' AND c.relkind = 'p'"
    live_parts=$(pitr_val "$parts_sql")
    trig_sql="SELECT count(*) FROM pg_trigger WHERE tgname = 'audit_logs_append_only' AND tgrelid = coalesce(to_regclass('public.audit_logs'), 0)"
    live_trig=$(pitr_val "$trig_sql")
    # the 10 busiest plain tables (leaf partitions included, partitioned parents have no storage)
    hot=$(psql -X -A -t -q -c "SELECT s.relname FROM pg_stat_user_tables s JOIN pg_class c ON c.oid = s.relid WHERE s.schemaname = 'public' AND c.relkind = 'r' ORDER BY s.n_tup_ins + s.n_tup_upd + s.n_tup_del DESC, s.relname LIMIT 10" 2>/dev/null || true)

    # (3) restore + promote
    pitr_log "repo$REPO dan $TARGET_TIME ga tiklanmoqda: $DIR (port $PORT)"
    r0=$(pitr_now)
    if ! pitr_restore_to "$DIR" "$TARGET_TIME" "$RLOG" "$REPO"; then
        fail "pgbackrest restore xato berdi: $(grep -E 'ERROR|error' "$RLOG" | tail -n 2 | tr '\n' ' ')"
    fi
    BACKUP_LABEL=$(pitr_backup_label "$RLOG")
    pitr_start_scratch "$DIR" "$PORT" "$SOCK" "$SRVLOG" || fail "tiklangan Postgres ishga tushmadi: $(tail -n 3 "$SRVLOG" | tr '\n' ' ')"
    if ! pitr_wait_promoted "$DIR" "$PORT" "$SOCK" "$DRILL_TIMEOUT" "$SRVLOG"; then
        fail "tiklangan nusxa promote bo'lmadi: $(grep -E 'FATAL|PANIC' "$SRVLOG" | tail -n 2 | tr '\n' ' ')"
    fi
    RESTORE_SECONDS=$(($(pitr_now) - r0))
    rp=$(pitr_recovery_point "$SRVLOG")
    [ -z "$rp" ] || REACHED=$(rpsql "SELECT to_char('$rp'::timestamptz AT TIME ZONE 'UTC', 'YYYY-MM-DD HH24:MI:SS')")
    WAL_RESTORED=$(grep -c 'restored log file' "$SRVLOG" || true)

    # (4) checks on the restored copy
    PROBE_TOKEN=$(rpsql "SELECT meta->>'token' FROM monitoring_markers WHERE key = 'drill_probe'")
    if [ "$PROBE_TOKEN" = "$token2" ]; then
        add_check probe failed; add_error "T dan keyin yozilgan belgi tiklangan nusxada bor (tiklash T da to'xtamadi)"
    elif [ -n "$token1" ] && [ "$PROBE_TOKEN" != "$token1" ]; then
        add_check probe failed; add_error "T dan oldingi belgi tiklangan nusxada yo'q (kutilgan $token1, topildi '${PROBE_TOKEN:-yo q}')"
    else
        add_check probe ok
    fi
    if [ -n "$manifest_sql" ]; then
        restored_manifest=$(rpsql "SELECT $manifest_sql")
        if [ "$restored_manifest" = "$live_manifest" ]; then add_check manifest ok; else
            add_check manifest failed; add_error "jadvallar soni farq qiladi: ish bazasi [$live_manifest], tiklangan [$restored_manifest]"
        fi
    else
        add_check manifest skipped
    fi
    if [ -n "$live_mig" ]; then
        if [ "$(rpsql "$mig_sql")" = "$live_mig" ]; then add_check migrations ok; else
            add_check migrations failed; add_error "migratsiyalar farq qiladi (ish bazasi $live_mig)"
        fi
    else
        add_check migrations skipped
    fi
    if [ "$(rpsql "$parts_sql")" = "$live_parts" ] && [ "$(rpsql "$trig_sql")" = "$live_trig" ]; then add_check partitions ok; else
        add_check partitions failed; add_error "bo'lingan jadvallar yoki audit_logs_append_only trigger farq qiladi"
    fi
    if [ "${PITR_DRILL_AMCHECK:-1}" = "1" ] && command -v pg_amcheck >/dev/null 2>&1 && [ -n "$hot" ]; then
        set --
        for tbl in $hot; do set -- "$@" -t "public.$tbl"; done
        if pg_amcheck -h "$SOCK" -p "$PORT" -d "$PGDATABASE" --install-missing --heapallindexed --no-strict-names "$@" >"$ROOT/drill-amcheck.log" 2>&1; then
            add_check amcheck ok
        else
            add_check amcheck failed; add_error "pg_amcheck buzilish topdi: $(tail -n 3 "$ROOT/drill-amcheck.log" | tr '\n' ' ')"
        fi
    else
        add_check amcheck skipped
    fi
    if [ -n "${PITR_DRILL_EXTRA_CHECK_SQL:-}" ]; then
        got=$(rpsql "$PITR_DRILL_EXTRA_CHECK_SQL")
        if [ "$got" = "${PITR_DRILL_EXTRA_CHECK_EXPECT:-t}" ]; then add_check extra ok; else
            add_check extra failed; add_error "qo'shimcha tekshiruv: kutilgan '${PITR_DRILL_EXTRA_CHECK_EXPECT:-t}', topildi '$got'"
        fi
    fi

    if [ "$keep" = "1" ] && all_ok; then
        save_state
        pitr_log "tiklangan nusxa ochiq: port $PORT (ilova tekshiruvi uchun); tugatish: pitr-drill.sh finish"
        echo "DRILL_STATUS=running"
        echo "RESTORE_SECONDS=$RESTORE_SECONDS"
        echo "DATA_LOSS_SECONDS=$DATA_LOSS_SECONDS"
        echo "RECOVERY_POINT=$REACHED"
        echo "TARGET_TIME=${TARGET_TIME%+00}"
        return 0
    fi
    # Not kept open for scripts/restore-drill.sh: nobody runs the app smoke against this copy
    add_check app_smoke skipped
    if all_ok; then finish ok; else finish failed; fi
}

case "${1:-}" in
    run) shift; run "$@" ;;
    finish)
        shift
        smoke=skipped
        while [ $# -gt 0 ]; do
            case "$1" in
                --smoke) smoke=${2:-skipped}; shift 2 ;;
                *) pitr_die "noma'lum argument: $1" ;;
            esac
        done
        [ -f "$STATE" ] || pitr_die "ochiq mashq yo'q ($STATE): avval pitr-drill.sh run --keep"
        pitr_load
        # shellcheck disable=SC1090
        . "$STATE"
        case "$smoke" in
            ok | skipped) add_check app_smoke "$smoke" ;;
            *) add_check app_smoke failed; add_error "ilova tekshiruvi (db:restore-smoke) o'tmadi" ;;
        esac
        if all_ok; then finish ok; else finish failed; fi
        ;;
    cleanup) pitr_load; cleanup_scratch; rm -f "$STATE"; pitr_log "tozalandi" ;;
    -h | --help | '') sed -n '2,27p' "$0" | sed 's/^# \{0,1\}//' ;;
    *) pitr_die "noma'lum buyruq: $1 (run, finish, cleanup)" ;;
esac
