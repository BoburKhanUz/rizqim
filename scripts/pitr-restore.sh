#!/bin/sh
# RIZQIM: PITR, bazani istalgan daqiqaga tiklash. Ish bazasiga TEGMAYDI: har doim YANGI papkaga tiklaydi va ish
# bazasining papkasiga (yoki uning volume'iga) tiklashni rad etadi.
#
# Foydalanish (serverda, loyiha papkasida):
#   scripts/pitr-restore.sh --info                         qisqa xulosa (oxirgi nusxalar, qaysi vaqtlarga tiklash mumkin)
#                                                          va pgBackRest'ning to'liq ro'yxati (faqat o'qiydi)
#   scripts/pitr-restore.sh "2026-12-01 14:05"             shu daqiqaga tiklab, ikkinchi Postgres'ni `backup` konteynerida
#                                                          5499 portida ochadi (ko'rish, kerakli qatorlarni olish)
#   scripts/pitr-restore.sh "2026-12-01 14:05" --into nom  nusxa nomi (pitr_restore volume'ida; standart pitr-<vaqt>)
#   scripts/pitr-restore.sh "2026-12-01 14:05" --into-dir /srv/rizqim/pgdata-0105
#                                                          xostdagi YANGI papkaga (bo'sh yoki yo'q bo'lishi shart): tiklaydi,
#                                                          promote qiladi va toza yopadi (pitr-switch.sh --dir uchun tayyor)
#   --repo 2                                               S3 dagi nusxadan (standart 1: shu serverdagi)
#   --no-start                                             tiklaydi, lekin serverni ochmaydi
#   scripts/pitr-restore.sh --list                         tiklangan nusxalar ro'yxati
#   scripts/pitr-restore.sh --psql [nom]                   ochiq nusxaga psql (5499 portidagi)
#   scripts/pitr-restore.sh --start <nom> | --stop <nom> | --drop <nom>   nusxani ochish, to'xtatish, o'chirish
#
# Vaqt: vaqt zonasi yozilmasa TOSHKENT vaqti (+05) deb olinadi; UTC uchun oxiriga +00 yozing ("2026-12-01 09:05+00").
# Tiklangan nusxani ish bazasi qilish: scripts/pitr-switch.sh <nom> (tasdiq so'raydi, orqaga qaytish mumkin;
# docs/OPERATIONS.md, 18-bo'lim). --local: Docker'siz (scripts/pitr-ci.sh --local).

set -eu
umask 077

PITR_SCRIPT_DIR=$(cd "$(dirname "$0")" && pwd)
PITR_LOG_TAG=pitr-restore
# shellcheck source=scripts/pitr.sh
. "$PITR_SCRIPT_DIR/pitr.sh"

ROOT=${PITR_RESTORE_ROOT:-/restore}
PORT=${PITR_RESTORE_PORT:-5499}
SOCK=${PITR_RESTORE_SOCKET_DIR:-$ROOT/restore-socket}

usage() { sed -n '2,24p' "$0" | sed 's/^# \{0,1\}//'; }

LOCAL=0
INNER=0
for a in "$@"; do
    case "$a" in --local) LOCAL=1 ;; --inner) INNER=1 ;; esac
done
if [ "${RIZQIM_BACKUP_CONTAINER:-0}" = "1" ]; then INNER=1; fi

# --- host side: forward into the backup container ----------------------------------------------------------------
if [ "$LOCAL" = "0" ] && [ "$INNER" = "0" ]; then
    command -v docker >/dev/null 2>&1 || pitr_die "docker topilmadi (buyruqni serverda, loyiha papkasida bajaring)"
    cd "$PITR_SCRIPT_DIR/.."
    into_dir=
    prev=
    for a in "$@"; do
        [ "$prev" != "--into-dir" ] || into_dir=$a
        if [ "$prev" = "--into" ]; then
            case "$a" in /*) into_dir=$a; pitr_log "eslatma: mutlaq yo'l uchun --into-dir ishlating (--into <nom> pitr_restore volume'idagi nom)" ;; esac
        fi
        prev=$a
    done
    if [ -n "$into_dir" ]; then
        case "$into_dir" in /*) ;; *) pitr_die "--into-dir mutlaq yo'l bo'lishi kerak (masalan /srv/rizqim/pgdata-0105): $into_dir" ;; esac
        [ ! -e "$into_dir" ] || [ -z "$(ls -A "$into_dir" 2>/dev/null)" ] || pitr_die "$into_dir bo'sh emas: faqat YANGI (bo'sh) papkaga tiklanadi"
        live=$(sed -n 's/^POSTGRES_DATA_VOLUME=//p' .env 2>/dev/null | tail -n 1)
        [ "${live%/}" != "${into_dir%/}" ] || pitr_die "$into_dir ish bazasining papkasi (POSTGRES_DATA_VOLUME): RAD ETILDI"
        mkdir -p "$into_dir"
        # A one-off backup container with the new directory mounted; it also mounts the live data read-only, so the
        # inner guard compares the two by device and inode
        set -- "$@" __end__
        prev=
        while [ "$1" != "__end__" ]; do
            a=$1; shift
            if [ "$prev" = "--into" ] || [ "$prev" = "--into-dir" ]; then set -- "$@" /restore/target
            elif [ "$a" = "--into" ]; then set -- "$@" --into-dir
            else set -- "$@" "$a"; fi
            prev=$a
        done
        shift
        exec docker compose run --rm --no-deps -v "$into_dir:/restore/target" backup sh /usr/local/bin/pitr-restore.sh --inner --promote-and-stop "$@"
    fi
    prev=
    for a in "$@"; do
        if [ "$prev" = "--drop" ] || [ "$prev" = "--stop" ]; then
            f=.pitr-switch/switch.state
            if [ -f "$f" ] && sed -n 's/^\(PREVIOUS\|CURRENT\)=//p' "$f" | grep -q "/$a\$"; then
                pitr_die "$a oxirgi almashtirishning ish bazasi yoki orqaga qaytish nuqtasi (scripts/pitr-switch.sh): avval --back yoki --finalize"
            fi
        fi
        prev=$a
    done
    for a in "$@"; do
        if [ "$a" = "--psql" ] && [ -t 0 ]; then
            exec docker compose exec backup sh /usr/local/bin/pitr-restore.sh --inner "$@"
        fi
    done
    docker compose ps --status running --services 2>/dev/null | grep -qx backup || pitr_die "backup xizmati ishlamayapti: docker compose up -d backup"
    exec docker compose exec -T backup sh /usr/local/bin/pitr-restore.sh --inner "$@"
fi

# --- inside the backup container (or --local) ----------------------------------------------------------------------
PGTZ=UTC; export PGTZ
pitr_resolve_conn
pitr_load

target=
into=
repo=1
start=1
promote_stop=0
action=restore
name_arg=
while [ $# -gt 0 ]; do
    case "$1" in
        --local | --inner) shift ;;
        --into) into=${2:-}; shift 2 ;;
        --into-dir) into=${2:-}; promote_stop=1; case "$into" in /*) ;; *) pitr_die "--into-dir mutlaq yo'l bo'lishi kerak: $into" ;; esac; shift 2 ;;
        --psql) action=psql; case "${2:-}" in '' | -*) shift ;; *) name_arg=$2; shift 2 ;; esac ;;
        --start) action=start; name_arg=${2:-}; shift 2 ;;
        --check-copy) action=check; name_arg=${2:-}; shift 2 ;;
        --repo) repo=${2:-1}; shift 2 ;;
        --no-start) start=0; shift ;;
        --promote-and-stop) promote_stop=1; shift ;;
        --info) action=info; shift ;;
        --list) action=list; shift ;;
        --stop) action=stop; name_arg=${2:-}; shift 2 ;;
        --drop) action=drop; name_arg=${2:-}; shift 2 ;;
        -h | --help) usage; exit 0 ;;
        -*) pitr_die "noma'lum flag: $1" ;;
        *) [ -z "$target" ] || pitr_die "vaqt ikki marta berildi: $target / $1"; target=$1; shift ;;
    esac
done

check_name() {
    case "$1" in '' | *[!A-Za-z0-9_-]*) pitr_die "nom faqat harf, raqam, - va _ dan iborat bo'lsin: '$1'" ;; esac
    [ "$1" != "drill" ] || pitr_die "'drill' nomi tiklash mashqiniki"
}

live_dir=${PGBACKREST_PG1_PATH:-/var/lib/postgresql/data}
# The live data directory: the same inode as the live mount, or a server running on it on another port than ours
is_live() {
    if [ -d "$live_dir" ] && [ -d "$1" ] && [ "$(stat -c %d:%i "$live_dir")" = "$(stat -c %d:%i "$1")" ]; then return 0; fi
    [ -f "$1/postmaster.pid" ] && [ "$(sed -n 4p "$1/postmaster.pid")" != "$PORT" ]
}

# Short Uzbek summary of `pgbackrest info` (text output, one stanza): newest/oldest backups per repository and the
# window a restore can reach (oldest backup end .. newest archived WAL)
info_summary() {
    pgbr info >"$1" 2>&1 || true
    awk '
        /^stanza:/ { next }
        /^    status:/ { sub(/^    status: */, ""); status = $0 }
        /wal archive min\/max/ { split($NF, w, "/"); walmin = w[1]; walmax = w[2] }
        /^        (full|diff|incr) backup:/ { type = $1; label = $3; n++ }
        /timestamp start\/stop:/ { stop = $(NF-1) " " $NF; sub(/\+00$/, "", stop); stops[n] = stop; types[n] = type; labels[n] = label }
        /^            repo[0-9]+: backup/ { r = $1; sub(/:$/, "", r); repos[n] = r; cnt[r]++ ; if (type == "full") lastfull[r] = stop; last[r] = stop; if (!(r in first)) first[r] = stop }
        END {
            printf "Holat: %s\n", (status == "" ? "nomaʼlum (pgBackRest javob bermadi)" : status)
            if (n == 0) { print "Hali hech qanday nusxa yoʻq: birinchi toʻliq nusxa tugaguncha tiklab boʻlmaydi"; exit }
            for (r in cnt) printf "%s: %d ta nusxa; oxirgi toʻliq: %s; oxirgi nusxa: %s; eng eskisi: %s (UTC)\n", r, cnt[r], (r in lastfull ? lastfull[r] : "yoʻq"), last[r], first[r]
        }' "$1"
}

case "$action" in
    info)
        tmp=$(mktemp "${TMPDIR:-/tmp}/pitr-info.XXXXXX")
        echo "=== Qisqa xulosa ==="
        info_summary "$tmp"
        # The restorable windows from the repository itself: one continuous WAL run per window (a hole = a gap)
        live_tli=$(pitr_val "SELECT timeline_id FROM pg_control_checkpoint()" || true)
        for repo in 1 2; do
            [ "$repo" = "1" ] || [ "$PITR_REPO2" = "on" ] || continue
            report=$(pitr_wal_report "$repo" "$live_tli") || { echo "repo$repo: WAL ro'yxatini o'qib bo'lmadi"; continue; }
            tli=$(printf '%s\n' "$report" | sed -n 's/^TLI=//p')
            windows=$(printf '%s\n' "$report" | sed -n 's/^WINDOWS=//p')
            if [ -z "$windows" ]; then echo "repo$repo: timeline $tli da tiklanadigan oraliq yo'q (bu timeline'da to'liq nusxa yo'q)"; continue; fi
            for w in $(printf '%s' "$windows" | tr ',' ' '); do
                echo "Tiklash mumkin (repo$repo, timeline $tli): $(pitr_epoch_utc "${w%-*}") dan $(pitr_epoch_utc "${w#*-}") gacha (UTC; oxirgi WAL arxivga tushgan payt)"
            done
            if [ "$(printf '%s\n' "$report" | sed -n 's/^GAP=//p')" = "1" ]; then
                ga=$(pitr_epoch_utc "$(printf '%s\n' "$report" | sed -n 's/^GAP_AFTER_TIME=//p')")
                if [ -z "$(printf '%s\n' "$report" | sed -n 's/^BACKUP_IN_LAST_RUN=//p')" ]; then
                    echo "DIQQAT: zaxira zanjiri uzilgan (repo$repo): $ga (UTC) dan keyingi daqiqalarga qaytarib bo'lmaydi; yangi to'liq nusxa kerak (backup.sh --pitr-backup full)"
                else
                    echo "DIQQAT: zaxira zanjiri bir marta uzilgan (repo$repo): $ga (UTC) dan keyingi to'liq nusxagacha bo'lgan daqiqalarga qaytarib bo'lmaydi"
                fi
            fi
        done
        echo "=== pgBackRest ==="
        cat "$tmp"
        rm -f "$tmp"
        exit 0
        ;;
    list)
        for d in "$ROOT"/*/; do
            [ -f "$d/PG_VERSION" ] || continue
            n=$(basename "$d")
            st="to'xtatilgan"
            [ ! -f "$d/postmaster.pid" ] || st="ishlayapti (port $PORT)"
            ! is_live "$d" || st="ISH BAZASI (pitr-switch.sh bilan almashtirilgan)"
            echo "$n: $st, $(du -sh "$d" 2>/dev/null | cut -f1)"
        done
        exit 0
        ;;
    psql)
        if [ -n "$name_arg" ]; then
            check_name "$name_arg"
            [ -f "$ROOT/$name_arg/postmaster.pid" ] || pitr_die "$name_arg ochiq emas: avval scripts/pitr-restore.sh --start $name_arg"
        fi
        psql -X -A -t -q -h "$SOCK" -p "$PORT" -c 'select 1' >/dev/null 2>&1 || pitr_die "5499 portida ochiq nusxa yo'q (--list, --start <nom>)"
        exec psql -h "$SOCK" -p "$PORT"
        ;;
    start)
        check_name "$name_arg"
        dir="$ROOT/$name_arg"
        [ -f "$dir/PG_VERSION" ] || pitr_die "$dir yo'q (--list)"
        ! is_live "$dir" || pitr_die "$name_arg hozir ISH BAZASI: uni bu yerda ochib bo'lmaydi"
        [ ! -f "$dir/postmaster.pid" ] || pitr_die "$name_arg allaqachon ochiq"
        psql -X -A -t -q -h "$SOCK" -p "$PORT" -c 'select 1' >/dev/null 2>&1 && pitr_die "port $PORT band: avval boshqa nusxani --stop qiling"
        slog="$ROOT/$name_arg.server.log"
        pitr_start_scratch "$dir" "$PORT" "$SOCK" "$slog" || pitr_die "Postgres ishga tushmadi (log: $slog)"
        pitr_wait_promoted "$dir" "$PORT" "$SOCK" "${PITR_RESTORE_TIMEOUT:-7200}" "$slog" || pitr_die "nusxa tayyor bo'lmadi (log: $slog)"
        pitr_log "ochildi: $name_arg (port $PORT). psql: scripts/pitr-restore.sh --psql"
        exit 0
        ;;
    stop | drop)
        check_name "$name_arg"
        [ -d "$ROOT/$name_arg" ] || pitr_die "$ROOT/$name_arg yo'q (--list)"
        sf=${PITR_STATE_DIR:-/nonexistent}/switch.state
        if [ "$LOCAL" = "1" ] && [ -f "$sf" ] && sed -n 's/^\(PREVIOUS\|CURRENT\)=//p' "$sf" | grep -q "/$name_arg\$"; then
            pitr_die "$name_arg oxirgi almashtirishning ish bazasi yoki orqaga qaytish nuqtasi (scripts/pitr-switch.sh): avval --back yoki --finalize"
        fi
        ! is_live "$ROOT/$name_arg" || pitr_die "$name_arg hozir ISH BAZASI (pitr-switch.sh): uni to'xtatib yoki o'chirib bo'lmaydi"
        pitr_stop_scratch "$ROOT/$name_arg"
        if [ "$action" = "drop" ]; then rm -rf "${ROOT:?}/$name_arg"; pitr_log "o'chirildi: $name_arg"; else pitr_log "to'xtatildi: $name_arg"; fi
        exit 0
        ;;
    check)
        # Preflight for scripts/pitr-switch.sh: may this copy become the live data directory?
        case "$name_arg" in /*) dir=$name_arg ;; *) check_name "$name_arg"; dir="$ROOT/$name_arg" ;; esac
        [ -f "$dir/PG_VERSION" ] || pitr_die "$dir da Postgres nusxasi yo'q"
        ! is_live "$dir" || pitr_die "$dir allaqachon ish bazasi"
        [ ! -f "$dir/postmaster.pid" ] || pitr_die "$dir ochiq (port $PORT): avval scripts/pitr-restore.sh --stop $(basename "$dir")"
        state=$(pitr_as_pg pg_controldata "$dir" 2>/dev/null | sed -n 's/^Database cluster state: *//p')
        tli=$(pitr_as_pg pg_controldata "$dir" 2>/dev/null | sed -n "s/^Latest checkpoint's TimeLineID: *//p")
        ckpt=$(pitr_as_pg pg_controldata "$dir" 2>/dev/null | sed -n 's/^Time of latest checkpoint: *//p')
        # Only a copy that finished its recovery here (promoted) and was shut down cleanly may become live: a pending one
        # would need this container's restore_command, a failed one would never start
        if [ -f "$dir/recovery.signal" ] || [ -f "$dir/standby.signal" ]; then
            case "$name_arg" in
                /*) pitr_die "$dir hali tiklanib bo'lmagan (recovery.signal bor). Uni qaytadan tiklang: scripts/pitr-restore.sh \"<vaqt>\" --into-dir <YANGI papka> (endi tiklab, promote qilib yopadi)" ;;
                *) pitr_die "$name_arg hali tiklanib bo'lmagan (recovery.signal bor). Avval: scripts/pitr-restore.sh --start $name_arg, so'ng --stop $name_arg (tiklash xato bersa logida sababi)" ;;
            esac
        fi
        [ "$state" = "shut down" ] || pitr_die "$dir holati '$state': toza yopilmagan nusxa. scripts/pitr-restore.sh --start $(basename "$dir") va --stop $(basename "$dir") qiling"
        kind=promoted
        # The copy's timeline history was written while archive_mode was off: queue it for the archiver, so the
        # repository knows the timeline (later promotions then choose a new one, segment names never collide)
        for h in "$dir"/pg_wal/*.history; do
            [ -f "$h" ] || continue
            hn=$(basename "$h")
            [ -f "$dir/pg_wal/archive_status/$hn.done" ] || [ -f "$dir/pg_wal/archive_status/$hn.ready" ] || {
                : >"$dir/pg_wal/archive_status/$hn.ready"
                if [ "$(id -u)" = "0" ]; then chown postgres:postgres "$dir/pg_wal/archive_status/$hn.ready"; fi
            }
        done
        size_kb=$(du -sk "$dir" | cut -f1)
        free_kb=$(df -Pk "$dir" | awk 'NR == 2 { print $4 }')
        need_kb=$((size_kb / 10 + 2097152))
        [ "$free_kb" -ge "$need_kb" ] || pitr_die "joy kam: $((free_kb / 1024)) MB bo'sh, kamida $((need_kb / 1024)) MB kerak (yangi WAL uchun)"
        echo "COPY_KIND=$kind"
        echo "COPY_STATE=$state"
        echo "COPY_TIMELINE=$tli"
        echo "COPY_CHECKPOINT=$ckpt"
        echo "COPY_SIZE_MB=$((size_kb / 1024))"
        echo "FREE_MB=$((free_kb / 1024))"
        exit 0
        ;;
esac

pitr_enabled || pitr_die "PITR o'chiq (PITR_ENABLED=1 emas): tiklash uchun uzluksiz zaxira yo'q. Kunlik pg_dump'dan tiklash: scripts/restore.sh"
[ -n "$target" ] || { usage; exit 1; }
case "$repo" in 1) ;; 2) [ "$PITR_REPO2" = "on" ] || pitr_die "repo2 (S3) sozlanmagan" ;; *) pitr_die "--repo 1 yoki 2" ;; esac

# Time: without an explicit zone the operator means Tashkent time (UTC+5, no DST). pgBackRest and Postgres take the
# offset as given, so nothing here needs the live database (it may be the thing that is broken).
tz_note=
case "$target" in
    *[+-][0-9][0-9] | *[+-][0-9][0-9]:[0-9][0-9] | *[+-][0-9][0-9][0-9][0-9]) zone= ;;
    *Z) target=${target%Z}; zone=+00 ;;
    *) zone=${PITR_DEFAULT_TZ:-+05}; tz_note=" (vaqt zonasi yozilmagan: Toshkent vaqti, $zone)" ;;
esac
base=$target
case "$base" in
    [12][0-9][0-9][0-9]-[01][0-9]-[0-3][0-9][\ T][0-2][0-9]:[0-5][0-9]) base="$base:00" ;;
esac
case "$base" in
    [12][0-9][0-9][0-9]-[01][0-9]-[0-3][0-9][\ T][0-2][0-9]:[0-5][0-9]:[0-5][0-9]*) ;;
    *) pitr_die "vaqtni tushunib bo'lmadi: '$target' (masalan \"2026-12-01 14:05\" yoki \"2026-12-01 09:05:00+00\")" ;;
esac
target_full=$base$zone
utc=$(pitr_val "SELECT to_char('$target_full'::timestamptz AT TIME ZONE 'UTC', 'YYYY-MM-DD HH24:MI:SS')" || true)
if [ -n "$utc" ] && [ "$(pitr_val "SELECT '$target_full'::timestamptz > now()")" = "t" ]; then
    pitr_die "vaqt kelajakda: $target_full"
fi

case "$into" in
    /*) dir=$into; name=$(basename "$into") ;;
    '') name="pitr-$(printf '%s' "$base" | tr -d ': T-' | cut -c1-12)"; dir="$ROOT/$name" ;;
    *) check_name "$into"; name=$into; dir="$ROOT/$into" ;;
esac
[ "$name" = "target" ] || check_name "$name"

pitr_log "tiklanmoqda: $target_full$tz_note${utc:+ = $utc UTC}, repo$repo -> $dir"
rlog="$ROOT/$name.restore.log"
mkdir -p "$ROOT"
t0=$(pitr_now)
if ! pitr_restore_to "$dir" "$target_full" "$rlog" "$repo"; then
    grep -E 'ERROR|WARN' "$rlog" | tail -n 5 >&2 || true
    pitr_die "pgbackrest restore bajarilmadi (to'liq log: $rlog). Vaqt eng birinchi zaxiradan oldinmi? --info"
fi
pitr_log "tiklandi ($(pitr_human_seconds $(($(pitr_now) - t0)))), asosiy zaxira: $(pitr_backup_label "$rlog")"

# A restore that recovery cannot finish (the target lies after the last archived commit): say what the last
# restorable moment is
explain_failed() { # explain_failed <server-log>
    if grep -q "recovery ended before configured recovery target was reached" "$1" 2>/dev/null; then
        lp=$(pitr_recovery_point "$1")
        pitr_log "XATO: $target_full gacha WAL yo'q (bu vaqtdan keyin hali hech narsa arxivlanmagan yoki zanjir uzilgan)."
        [ -z "$lp" ] || pitr_log "Oxirgi tiklanadigan nuqta: $lp. Shu vaqtga tiklang: scripts/pitr-restore.sh \"$lp\" (ixtiyoriy nom bilan). Oraliq: scripts/pitr-restore.sh --info"
    fi
}

if [ "$start" = "0" ]; then
    pitr_log "server ochilmadi (--no-start). Papka: $dir. Ochish: scripts/pitr-restore.sh --start $name"
    exit 0
fi
if [ "$promote_stop" = "1" ]; then
    psock=$ROOT/promote-socket
    slog="$ROOT/$name.server.log"
    pitr_start_scratch "$dir" "$PORT" "$psock" "$slog" || { explain_failed "$slog"; pitr_die "tiklangan Postgres ishga tushmadi (log: $slog)"; }
    if ! pitr_wait_promoted "$dir" "$PORT" "$psock" "${PITR_RESTORE_TIMEOUT:-7200}" "$slog"; then
        explain_failed "$slog"
        pitr_stop_scratch "$dir"
        pitr_die "nusxa tiklanib bo'lmadi (log: $slog)"
    fi
    rp=$(pitr_recovery_point "$slog")
    pitr_stop_scratch "$dir"
    pitr_log "TAYYOR: $dir tiklandi, promote qilindi va toza yopildi (qaytarilgan nuqta: ${rp:-?}). Ish bazasi qilish: scripts/pitr-switch.sh --dir <shu papka>"
    exit 0
fi
if psql -X -A -t -q -h "$SOCK" -p "$PORT" -c 'select 1' >/dev/null 2>&1; then
    pitr_die "port $PORT band (boshqa tiklangan nusxa yoki mashq ochiq): avval --stop <nom>. Nusxa tayyor: $dir"
fi
slog="$ROOT/$name.server.log"
pitr_start_scratch "$dir" "$PORT" "$SOCK" "$slog" || pitr_die "tiklangan Postgres ishga tushmadi (log: $slog)"
pitr_wait_promoted "$dir" "$PORT" "$SOCK" "${PITR_RESTORE_TIMEOUT:-7200}" "$slog" || { explain_failed "$slog"; pitr_die "tiklangan nusxa promote bo'lmadi (log: $slog)"; }
rp=$(pitr_recovery_point "$slog")
[ -n "$rp" ] || rp="noma'lum"
pitr_log "TAYYOR. Qaytarilgan nuqta (oxirgi tranzaksiya): $rp"
echo "Ulanish (serverda): docker compose exec backup psql -h $SOCK -p $PORT -U $PGUSER -d $PGDATABASE"
echo "To'xtatish: scripts/pitr-restore.sh --stop $name    O'chirish: scripts/pitr-restore.sh --drop $name"
