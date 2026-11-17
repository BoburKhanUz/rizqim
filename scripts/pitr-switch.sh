#!/bin/sh
# RIZQIM: PITR dan tiklangan nusxani ISH BAZASI qilish (va orqaga qaytarish). Faqat haqiqiy avariyada, texnik yordamchi
# bilan. Bitta buyruq (serverda, loyiha papkasida):
#
#   scripts/pitr-switch.sh <nom>              pitr-restore.sh --into <nom> bilan tiklangan nusxa (pitr_restore volume'i)
#   scripts/pitr-switch.sh --dir /srv/...     pitr-restore.sh --into-dir bilan xostdagi papkaga tiklangan nusxa
#   scripts/pitr-switch.sh --back             oldingi ish bazasiga qaytish (oxirgi almashtirish yozib qo'ygan)
#   --confirm <baza-nomi>                     tasdiqni oldindan berish (avtomatlashtirish, CI); aks holda so'raladi
#
# Nima qiladi: (1) tekshiradi: nusxa toza to'xtatilgan (yoki hali ochilmagan, birinchi ishga tushishda tiklanadi), joy
# yetadi, nusxa ish bazasining o'zi emas; (2) nima bo'lishini ko'rsatadi va baza nomini yozib tasdiqlashni so'raydi;
# (3) ilova xizmatlarini, backup va postgres ni to'xtatadi; (4) ildiz .env da POSTGRES_DATA_VOLUME ni nusxaga
# o'rnatadi (eski qiymat saqlanadi, eski ma'lumot o'zgarmaydi: orqaga qaytish mumkin); (5) postgres ni ko'taradi,
# pg_is_in_recovery() = f va asosiy jadvallar sonini tekshiradi; (6) hamma xizmatni ko'taradi, migratsiyalar holati va
# `db:restore-smoke` ni tekshiradi; (7) YANGI TO'LIQ PITR nusxa oladi (baza tarixi yangi yo'nalishda davom etadi);
# (8) orqaga qaytish buyrug'ini chiqaradi. Tiklash nuqtasidan keyin eski bazaga yozilgan hamma narsa yangi bazada YO'Q.
# --back: shu qadamlar teskari yo'nalishda; almashtirishdan keyin yangi bazaga yozilgan narsalar eski bazada YO'Q.
#
# --local: Docker'siz mashq (scripts/pitr-ci.sh --local): "ish bazasi" = PITR_PGDATA klasteri, pg_ctl bilan.
# Chiqish kodi: 0 = almashtirildi va tekshirildi; 1 = xato (qaysi qadamda to'xtagani yozilgan).

set -eu

SCRIPT_DIR=$(cd "$(dirname "$0")" && pwd)
ROOT_DIR=$(cd "$SCRIPT_DIR/.." && pwd)
log() { printf '%s pitr-switch: %s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$*" >&2; }
die() { log "XATO: $*"; exit 1; }

LOCAL=0
name=
dir_arg=
back=0
confirm=
while [ $# -gt 0 ]; do
    case "$1" in
        --local) LOCAL=1; shift ;;
        --back) back=1; shift ;;
        --dir) dir_arg=${2:-}; shift 2 ;;
        --confirm) confirm=${2:-}; shift 2 ;;
        -h | --help) sed -n '2,23p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
        -*) die "noma'lum flag: $1" ;;
        *) [ -z "$name" ] || die "ortiqcha argument: $1"; name=$1; shift ;;
    esac
done
[ "$back" = "1" ] || [ -n "$name$dir_arg" ] || { sed -n '2,23p' "$0" | sed 's/^# \{0,1\}//'; exit 1; }

dotenv() { [ -f "$ROOT_DIR/.env" ] && sed -n "s/^$1=//p" "$ROOT_DIR/.env" | tail -n 1 | sed -e 's/^"\(.*\)"$/\1/' -e "s/^'\(.*\)'\$/\1/"; true; }

# --- the two implementations of the same steps ------------------------------------------------------------------
if [ "$LOCAL" = "1" ]; then
    : "${PITR_PGDATA:?--local: PITR_PGDATA}" "${PITR_LOCAL_PG_OPTS:?--local: PITR_LOCAL_PG_OPTS (postgres flags of the live cluster)}"
    STATE=${PITR_STATE_DIR:-$ROOT_DIR/backups/.pitr}/switch.state
    LIVE_LINK=${PITR_LOCAL_LIVE_FILE:-$(dirname "$STATE")/live-data}
    DB=${PGDATABASE:?}
    as_pg() { runuser -u postgres -- "$@"; }
    current_data() { if [ -f "$LIVE_LINK" ]; then cat "$LIVE_LINK"; else printf '%s' "$PITR_PGDATA"; fi; }
    copy_path() { if [ -n "$dir_arg" ]; then printf '%s' "$dir_arg"; else printf '%s/%s' "${PITR_RESTORE_ROOT:?}" "$name"; fi; }
    check_copy() { sh "$SCRIPT_DIR/pitr-restore.sh" --local --check-copy "$(copy_path)"; }
    stop_all() { as_pg pg_ctl -D "$(current_data)" -m fast -w -t 300 stop >/dev/null; }
    set_data() { mkdir -p "$(dirname "$LIVE_LINK")"; printf '%s' "$1" >"$LIVE_LINK"; }
    start_db() {
        slog="${PITR_RESTORE_ROOT:?}/switch-postgres.log"
        : >>"$slog"; chown postgres "$slog"
        as_pg env PITR_PGDATA="$(current_data)" pg_ctl -D "$(current_data)" -l "$slog" -w -t 5 -o "$PITR_LOCAL_PG_OPTS" start >/dev/null 2>&1 || true
        [ -f "$(current_data)/postmaster.pid" ] || { tail -n 5 "$slog" >&2; die "postgres ishga tushmadi"; }
    }
    start_all() { :; }
    psql_live() { psql -X -A -t -q -v ON_ERROR_STOP=1 -c "$1"; }
    full_backup() { PITR_PGDATA=$(current_data) sh "$SCRIPT_DIR/backup.sh" --pitr-backup full; }
    full_backup_back() {
        PITR_PGDATA=$(current_data) PITR_SKIP_EXPIRE=1 PITR_EXPIRE_AUTO=n sh "$SCRIPT_DIR/backup.sh" --pitr-backup full \
            && PITR_PGDATA=$(current_data) sh "$SCRIPT_DIR/backup.sh" --pitr-forget-timeline "$1"
    }
    previous_value() { printf '%s' "$PITR_PGDATA"; }
else
    command -v docker >/dev/null 2>&1 || die "docker topilmadi (buyruqni serverda, loyiha papkasida bajaring)"
    cd "$ROOT_DIR"
    [ -f .env ] || die "ildizdagi .env yo'q"
    STATE="${BACKUP_HOST_DIR:-$(dotenv BACKUP_HOST_DIR)}"
    STATE="${STATE:-./backups}/.pitr/switch.state"
    DB=$(dotenv DB_DATABASE); DB=${DB:-rizqimbot}
    DBUSER=$(dotenv DB_USERNAME); DBUSER=${DBUSER:-rizqimbot}
    APP_SERVICES="frontend partner-web platform-admin reverb scheduler queue queue-reports backend pgbouncer"
    running() { docker compose ps --status running --services 2>/dev/null | grep -qx "$1"; }
    current_data() { v=$(dotenv POSTGRES_DATA_VOLUME); printf '%s' "${v:-postgres_data}"; }
    restore_src() { # host path of the backup container's /restore (the pitr_restore volume)
        cid=$(docker compose ps -q backup)
        [ -n "$cid" ] || die "backup xizmati yo'q: docker compose up -d backup"
        docker inspect -f '{{range .Mounts}}{{if eq .Destination "/restore"}}{{.Source}}{{end}}{{end}}' "$cid"
    }
    copy_path() { if [ -n "$dir_arg" ]; then printf '%s' "$dir_arg"; else printf '%s/%s' "$(restore_src)" "$name"; fi; }
    check_copy() {
        if [ -n "$dir_arg" ]; then
            docker compose run --rm --no-deps -v "$dir_arg:/restore/target" backup sh /usr/local/bin/pitr-restore.sh --inner --check-copy /restore/target
        else
            running backup || die "backup xizmati ishlamayapti: docker compose up -d backup"
            docker compose exec -T backup sh /usr/local/bin/pitr-restore.sh --inner --check-copy "$name"
        fi
    }
    stop_all() {
        # shellcheck disable=SC2086
        docker compose stop $APP_SERVICES backup >/dev/null 2>&1 || true
        docker compose stop postgres
    }
    set_data() {
        cp .env ".env.before-pitr-switch-$(date -u +%Y%m%dT%H%M%SZ)"
        if [ "$1" = "postgres_data" ] || [ -z "$1" ]; then
            sed -i '/^POSTGRES_DATA_VOLUME=/d' .env
        elif grep -q '^POSTGRES_DATA_VOLUME=' .env; then
            sed -i "s#^POSTGRES_DATA_VOLUME=.*#POSTGRES_DATA_VOLUME=$1#" .env
        else
            printf '\n# scripts/pitr-switch.sh (%s): ish bazasi PITR nusxasiga almashtirilgan. Orqaga: scripts/pitr-switch.sh --back\nPOSTGRES_DATA_VOLUME=%s\n' "$(date -u +%F)" "$1" >>.env
        fi
    }
    start_db() { docker compose up -d --wait --wait-timeout "${PITR_SWITCH_DB_TIMEOUT:-3600}" postgres; }
    start_all() {
        # Everything that ran before the switch (on CI only postgres, backup and minio exist)
        docker compose up -d backup
        for s in $APP_SERVICES; do
            if [ -n "$(docker compose ps -a -q "$s" 2>/dev/null)" ]; then docker compose up -d "$s"; fi
        done
    }
    psql_live() { docker compose exec -T postgres psql -X -A -t -q -v ON_ERROR_STOP=1 -U "$DBUSER" -d "$DB" -c "$1"; }
    wait_daemon() {
        # The daemon restarted with the backup container: let its init (stanza-create, check) finish first, it holds the lock
        i=0
        until [ "$(psql_live "SELECT recorded_at > now() AT TIME ZONE 'UTC' - interval '10 minutes' AND recorded_at >= '$switched_at' FROM monitoring_markers WHERE key = 'pitr'" 2>/dev/null)" = "t" ]; do
            i=$((i + 1)); [ "$i" -lt 200 ] || break
            sleep 3
        done
    }
    full_backup() {
        wait_daemon
        docker compose exec -T backup sh /usr/local/bin/backup.sh --pitr-backup full
    }
    full_backup_back() {
        wait_daemon
        docker compose exec -T -e PITR_SKIP_EXPIRE=1 -e PITR_EXPIRE_AUTO=n backup sh /usr/local/bin/backup.sh --pitr-backup full \
            && docker compose exec -T backup sh /usr/local/bin/backup.sh --pitr-forget-timeline "$1"
    }
    previous_value() { current_data; }
fi

# --- 1. what goes where -------------------------------------------------------------------------------------------
mkdir -p "$(dirname "$STATE")"
if [ "$back" = "1" ]; then
    [ -f "$STATE" ] || die "oldingi almashtirish yozuvi yo'q ($STATE): qaytariladigan narsa yo'q"
    target=$(sed -n 's/^PREVIOUS=//p' "$STATE" | tail -n 1)
    from=$(sed -n 's/^CURRENT=//p' "$STATE" | tail -n 1)
    abandoned_tli=$(sed -n 's/^TIMELINE=//p' "$STATE" | tail -n 1)
    [ -n "$target" ] || die "$STATE da PREVIOUS yo'q"
    [ "$(current_data)" = "$from" ] || die "hozirgi ish bazasi ($(current_data)) oxirgi almashtirishdagi ($from) emas: qo'lda tekshiring"
    kind=back
    log "ORQAGA QAYTISH: ish bazasi $from -> $target"
    log "DIQQAT: almashtirishdan keyin ($(sed -n 's/^AT=//p' "$STATE" | tail -n 1)) yangi bazaga yozilgan hamma narsa eski bazada YO'Q"
else
    target=$(copy_path)
    case "$target" in /*) ;; *) die "nusxa yo'li aniqlanmadi: $target" ;; esac
    [ "$target" != "$(current_data)" ] || die "$target allaqachon ish bazasi"
    log "1/8 tekshiruv: $target"
    check_out=$(check_copy) || die "nusxa ish bazasi qilishga yaroqsiz (yuqoridagi XATO)"
    printf '%s\n' "$check_out" | sed 's/^/    /' >&2
    kind=$(printf '%s\n' "$check_out" | sed -n 's/^COPY_KIND=//p')
    log "ISH BAZASI ALMASHTIRILADI: $(current_data) -> $target"
    log "DIQQAT: tiklash nuqtasidan keyin hozirgi bazaga yozilgan HAMMA narsa (buyurtmalar, to'lovlar) yangi bazada bo'lmaydi. Hozirgi baza o'chirilmaydi: orqaga qaytish mumkin (--back)"
fi

# --- 2. typed confirmation ----------------------------------------------------------------------------------------
if [ -z "$confirm" ]; then
    [ -t 0 ] || die "tasdiq kerak: terminalda ishga tushiring yoki --confirm $DB bering"
    printf 'Davom etish uchun bazaning nomini yozing (%s): ' "$DB" >&2
    read -r confirm || confirm=
fi
[ "$confirm" = "$DB" ] || die "tasdiqlanmadi ('$confirm' != '$DB'): hech narsa o'zgarmadi"

# --- 3-5. stop, switch, start the database ------------------------------------------------------------------------
switched_at=$(date -u '+%Y-%m-%d %H:%M:%S')
previous=$(previous_value)
log "3/8 ilova, backup va postgres to'xtatilmoqda"
stop_all
log "4/8 POSTGRES_DATA_VOLUME = $target (oldingi: $previous)"
set_data "$target"
if [ "$back" = "1" ]; then
    { echo "AT=$switched_at"; echo "BACK_FROM=$from"; echo "CURRENT=$target"; } >>"$STATE.history"
    rm -f "$STATE"
else
    { echo "AT=$switched_at"; echo "PREVIOUS=$previous"; echo "CURRENT=$target"; } >"$STATE"
    cat "$STATE" >>"$STATE.history"
fi
log "5/8 postgres ishga tushirilmoqda$([ "$kind" != "pending" ] || echo ' (nusxa birinchi marta ochiladi: WAL qo'"'"'llanib promote bo'"'"'ladi, baza hajmiga qarab daqiqalar)')"
start_db
i=0
until [ "$(psql_live "SELECT pg_is_in_recovery()" 2>/dev/null)" = "f" ]; do
    i=$((i + 1)); [ "$i" -lt "${PITR_SWITCH_RECOVERY_WAIT:-3600}" ] || die "baza ${PITR_SWITCH_RECOVERY_WAIT:-3600} s ichida tayyor bo'lmadi (postgres logini ko'ring). Orqaga: scripts/pitr-switch.sh --back"
    sleep 1
done
counts=$(psql_live "SELECT string_agg(t || '=' || n, ' ') FROM (SELECT 'orders' t, (SELECT count(*) FROM orders) n UNION ALL SELECT 'payments', (SELECT count(*) FROM payments) UNION ALL SELECT 'fiscal_receipts', (SELECT count(*) FROM fiscal_receipts) UNION ALL SELECT 'users', (SELECT count(*) FROM users)) x" 2>/dev/null || true)
tli=$(psql_live "SELECT timeline_id FROM pg_control_checkpoint()" 2>/dev/null || echo '?')
log "    baza ishlayapti (pg_is_in_recovery = f), timeline $tli; ${counts:-jadvallar soni olinmadi}"
[ "$back" = "1" ] || echo "TIMELINE=$tli" >>"$STATE"

# --- 6. the rest of the stack and the app's own checks ------------------------------------------------------------
log "6/8 xizmatlar ishga tushirilmoqda va ilova tekshirilmoqda"
start_all
app_rc=0
if [ -n "${PITR_SWITCH_APP_CMD:-}" ]; then
    sh -c "$PITR_SWITCH_APP_CMD" || app_rc=$?
elif [ "$LOCAL" = "0" ] && running backend; then
    docker compose exec -T -u www-data backend php artisan migrate:status >"$(dirname "$STATE")/switch-migrate-status.txt" 2>&1 || app_rc=1
    if grep -q 'Pending' "$(dirname "$STATE")/switch-migrate-status.txt"; then log "OGOHLANTIRISH: bajarilmagan migratsiyalar bor (backend ishga tushganda bajariladi)"; fi
    docker compose exec -T -u www-data backend php artisan db:restore-smoke || app_rc=1
else
    log "    ilova tekshiruvi o'tkazib yuborildi (backend ishlamayapti)"
fi
[ "$app_rc" = "0" ] || log "OGOHLANTIRISH: ilova tekshiruvi o'tmadi (yuqorida XATO qatorlari): texnik yordamchi bilan ko'ring; orqaga: scripts/pitr-switch.sh --back"

# --- 7. a new full backup: the database continues on a new timeline -------------------------------------------------
log "7/8 yangi to'liq PITR nusxa olinmoqda"
if [ "$back" = "1" ] && [ -n "${abandoned_tli:-}" ] && [ "$abandoned_tli" != "?" ]; then
    # Back on the older timeline: the backups of the abandoned one must go, or expire would delete this one's WAL
    full_backup_back "$abandoned_tli" || log "OGOHLANTIRISH: to'liq nusxa yoki eski timeline nusxalarini tozalash bajarilmadi: qo'lda: backup.sh --pitr-backup full, so'ng backup.sh --pitr-forget-timeline $abandoned_tli"
else
    full_backup || log "OGOHLANTIRISH: to'liq nusxa olinmadi: qo'lda bajaring: docker compose exec backup sh /usr/local/bin/backup.sh --pitr-backup full"
fi

# --- 8. how to go back ----------------------------------------------------------------------------------------------
if [ "$back" = "1" ]; then
    log "8/8 TAYYOR: ish bazasi qaytarildi: $target"
else
    log "8/8 TAYYOR: ish bazasi endi $target. Eski ma'lumot o'zgarmagan: $previous"
    log "    Orqaga qaytish (almashtirishdan keyingi yozuvlar yo'qoladi): scripts/pitr-switch.sh --back"
fi
[ "$app_rc" = "0" ]
