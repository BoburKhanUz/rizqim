#!/bin/sh
# RIZQIM: PITR, bazani istalgan daqiqaga tiklash. Ish bazasiga TEGMAYDI: har doim YANGI papkaga tiklaydi va ish
# bazasining papkasiga (yoki uning volume'iga) tiklashni rad etadi.
#
# Foydalanish (serverda, loyiha papkasida):
#   scripts/pitr-restore.sh --info                         qaysi vaqtlarga tiklash mumkin (zaxiralar, WAL oralig'i)
#   scripts/pitr-restore.sh "2026-12-01 14:05"             shu daqiqaga tiklab, ikkinchi Postgres'ni `backup` konteynerida
#                                                          5499 portida ochadi (ko'rish, kerakli qatorlarni olish)
#   scripts/pitr-restore.sh "2026-12-01 14:05" --into nom  papka nomi (pitr_restore volume'ida, standart pitr-<vaqt>)
#   scripts/pitr-restore.sh "2026-12-01 14:05" --into /srv/rizqim/pgdata-0105
#                                                          xostdagi YANGI papkaga (bo'sh yoki yo'q bo'lishi shart), server
#                                                          ochilmaydi: ish bazasi qilish runbook'i uchun (docs/OPERATIONS.md)
#   --repo 2                                               S3 dagi nusxadan (standart 1: shu serverdagi)
#   --no-start                                             tiklaydi, lekin serverni ochmaydi
#   scripts/pitr-restore.sh --list | --stop <nom> | --drop <nom>   tiklangan nusxalar: ro'yxat, serverni to'xtatish, o'chirish
#
# Vaqt: vaqt zonasi yozilmasa TOSHKENT vaqti (+05) deb olinadi; UTC uchun oxiriga +00 yozing ("2026-12-01 09:05+00").
# Tiklangan nusxani ish bazasi qilish alohida, tasdiqli runbook (ilovani to'xtatish, POSTGRES_DATA_VOLUME, ishga
# tushirish): docs/OPERATIONS.md, «Uzluksiz zaxira (PITR)». --local: Docker'siz (scripts/pitr-ci.sh --local).

set -eu
umask 077

PITR_SCRIPT_DIR=$(cd "$(dirname "$0")" && pwd)
PITR_LOG_TAG=pitr-restore
# shellcheck source=scripts/pitr.sh
. "$PITR_SCRIPT_DIR/pitr.sh"

ROOT=${PITR_RESTORE_ROOT:-/restore}
PORT=${PITR_RESTORE_PORT:-5499}
SOCK=${PITR_RESTORE_SOCKET_DIR:-$ROOT/restore-socket}

usage() { sed -n '2,21p' "$0" | sed 's/^# \{0,1\}//'; }

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
    into=
    prev=
    for a in "$@"; do
        [ "$prev" != "--into" ] || into=$a
        prev=$a
    done
    case "$into" in
        /*)
            [ ! -e "$into" ] || [ -z "$(ls -A "$into" 2>/dev/null)" ] || pitr_die "$into bo'sh emas: faqat YANGI (bo'sh) papkaga tiklanadi"
            live=$(sed -n 's/^POSTGRES_DATA_VOLUME=//p' .env 2>/dev/null | tail -n 1)
            [ "${live%/}" != "${into%/}" ] || pitr_die "$into ish bazasining papkasi (POSTGRES_DATA_VOLUME): RAD ETILDI"
            mkdir -p "$into"
            # A one-off backup container with the new directory mounted; it also mounts the live data read-only, so the
            # inner guard compares the two by device and inode
            set -- "$@" __end__
            while [ "$1" != "__end__" ]; do
                a=$1; shift
                if [ "$a" = "$into" ]; then set -- "$@" /restore/target; else set -- "$@" "$a"; fi
            done
            shift
            exec docker compose run --rm --no-deps -v "$into:/restore/target" backup sh /usr/local/bin/pitr-restore.sh --inner --no-start "$@"
            ;;
    esac
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
action=restore
name_arg=
while [ $# -gt 0 ]; do
    case "$1" in
        --local | --inner) shift ;;
        --into) into=${2:-}; shift 2 ;;
        --repo) repo=${2:-1}; shift 2 ;;
        --no-start) start=0; shift ;;
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

case "$action" in
    info)
        pgbr info
        exit 0
        ;;
    list)
        for d in "$ROOT"/*/; do
            [ -f "$d/PG_VERSION" ] || continue
            n=$(basename "$d")
            st="to'xtatilgan"
            [ ! -f "$d/postmaster.pid" ] || st="ishlayapti (port $PORT)"
            echo "$n: $st, $(du -sh "$d" 2>/dev/null | cut -f1)"
        done
        exit 0
        ;;
    stop | drop)
        check_name "$name_arg"
        [ -d "$ROOT/$name_arg" ] || pitr_die "$ROOT/$name_arg yo'q (--list)"
        pitr_stop_scratch "$ROOT/$name_arg"
        if [ "$action" = "drop" ]; then rm -rf "${ROOT:?}/$name_arg"; pitr_log "o'chirildi: $name_arg"; else pitr_log "to'xtatildi: $name_arg"; fi
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

if [ "$start" = "0" ]; then
    pitr_log "server ochilmadi (--no-start). Papka: $dir. U birinchi ishga tushganda WAL'ni $target_full gacha qo'llab, promote bo'ladi"
    exit 0
fi
if psql -X -A -t -q -h "$SOCK" -p "$PORT" -c 'select 1' >/dev/null 2>&1; then
    pitr_die "port $PORT band (boshqa tiklangan nusxa yoki mashq ochiq): avval --stop <nom>. Nusxa tayyor: $dir"
fi
slog="$ROOT/$name.server.log"
pitr_start_scratch "$dir" "$PORT" "$SOCK" "$slog" || pitr_die "tiklangan Postgres ishga tushmadi (log: $slog)"
pitr_wait_promoted "$dir" "$PORT" "$SOCK" "${PITR_RESTORE_TIMEOUT:-7200}" "$slog" || pitr_die "tiklangan nusxa promote bo'lmadi (log: $slog)"
rp=$(pitr_recovery_point "$slog")
[ -n "$rp" ] || rp="noma'lum"
pitr_log "TAYYOR. Qaytarilgan nuqta (oxirgi tranzaksiya): $rp"
echo "Ulanish (serverda): docker compose exec backup psql -h $SOCK -p $PORT -U $PGUSER -d $PGDATABASE"
echo "To'xtatish: scripts/pitr-restore.sh --stop $name    O'chirish: scripts/pitr-restore.sh --drop $name"
