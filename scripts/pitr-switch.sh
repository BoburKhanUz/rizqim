#!/bin/sh
# RIZQIM: PITR dan tiklangan nusxani ISH BAZASI qilish. Faqat haqiqiy avariyada, texnik yordamchi bilan. Faqat OLDINGA:
# har almashtirish yangi (kattaroq) timeline'ga o'tadi. "Orqaga qaytish" ham xuddi shu amal: kerakli vaqtga yangi nusxa
# tiklanadi (scripts/pitr-restore.sh) va unga almashtiriladi. Bitta buyruq (serverda, loyiha papkasida):
#
#   scripts/pitr-switch.sh <nom>              pitr-restore.sh --into <nom> bilan tiklangan, so'ng --stop qilingan nusxa
#   scripts/pitr-switch.sh --dir /srv/...     pitr-restore.sh --into-dir bilan xostdagi papkaga tiklangan nusxa
#   scripts/pitr-switch.sh --recover          to'xtab qolgan almashtirishni (elektr, kill -9) oldingi bazaga qaytarish
#   --confirm <baza-nomi>                     tasdiqni oldindan berish (avtomatlashtirish, CI); aks holda so'raladi
#
# Nima qiladi: (1) tekshiradi: nusxa to'liq tiklangan (promote bo'lgan), toza to'xtatilgan, avvalgi ish bazasi emas, joy
# yetadi; (2) baza nomini yozib tasdiqlashni so'raydi; (3) qaytarish uchun kerak bo'lgan hamma narsani yozib qo'yadi, so'ng
# ilova, backup va postgres ni to'xtatadi; (4) ildiz .env da POSTGRES_DATA_VOLUME ni nusxaga o'rnatadi; (5) postgres ni
# ko'taradi, pg_is_in_recovery() = f va asosiy jadvallar sonini tekshiradi (nusxaning timeline tarixi omborga ketadi);
# (6) ilova tekshiruvi (migrate:status, db:restore-smoke); (7) YANGI TIMELINE'DA TO'LIQ NUSXA (repo1) oladi; (8) ilovani
# ko'taradi, so'ng repo2 (S3) ga ham to'liq nusxa. 7-qadam muvaffaqiyatli bo'lgandagina almashtirish tugagan hisoblanadi;
# undan oldin har qanday xato, Ctrl-C yoki SSH uzilishida skript oldingi bazani o'zi qaytaradi (oldingi baza o'zgarmagan).
# Avvalgi ish bazasi o'chirilmaydi: diskda "avvalgi baza (saqlab qo'yilgan)" bo'lib qoladi (texnik yordamchi uchun
# oxirgi chora; qayta ishga tushirilmaydi). O'chirish: scripts/pitr-restore.sh --drop <nom> (nomini yozib tasdiqlanadi).
#
# --local: Docker'siz mashq (scripts/pitr-ci.sh --local): "ish bazasi" = PITR_PGDATA klasteri, pg_ctl bilan.
# Chiqish kodi: 0 = almashtirildi; 1 = almashtirilmadi (oldingi baza ishlayapti yoki nima qilish yozilgan).

set -eu

SCRIPT_DIR=$(cd "$(dirname "$0")" && pwd)
ROOT_DIR=$(cd "$SCRIPT_DIR/.." && pwd)
log() { printf '%s pitr-switch: %s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$*" >&2; }
die() { log "XATO: $*"; exit 1; }
usage() { sed -n '2,24p' "$0" | sed 's/^# \{0,1\}//'; }
PREV_MARK=.rizqim-previous-live

LOCAL=0
name=
dir_arg=
recover=0
confirm=
while [ $# -gt 0 ]; do
    case "$1" in
        --local) LOCAL=1; shift ;;
        --recover) recover=1; shift ;;
        --dir) dir_arg=${2:-}; shift 2 ;;
        --confirm) confirm=${2:-}; shift 2 ;;
        --back | --finalize) die "$1 endi yo'q: almashtirish faqat oldinga. Boshqa vaqtga qaytish: scripts/pitr-restore.sh \"<vaqt>\" --into <nom>, so'ng scripts/pitr-switch.sh <nom>" ;;
        -h | --help) usage; exit 0 ;;
        -*) die "noma'lum flag: $1" ;;
        *) [ -z "$name" ] || die "ortiqcha argument: $1"; name=$1; shift ;;
    esac
done
[ "$recover" = "1" ] || [ -n "$name$dir_arg" ] || { usage; exit 1; }

dotenv() { [ -f "$ROOT_DIR/.env" ] && sed -n "s/^$1=//p" "$ROOT_DIR/.env" | tail -n 1 | sed -e 's/^"\(.*\)"$/\1/' -e "s/^'\(.*\)'\$/\1/"; true; }

# Test hooks (scripts/pitr-ci.sh): fail, or pause for a signal, at a phase
test_point() { # test_point <phase>
    if [ "${PITR_SWITCH_TEST_FAIL_AT:-}" = "$1" ]; then die "(sinov) $1 bosqichida to'xtatildi"; fi
    if [ "${PITR_SWITCH_TEST_PAUSE_AT:-}" = "$1" ]; then
        : >"$STATE.paused-$1"
        sleep 120 &
        wait $! || true
    fi
}

# --- the two implementations of the same steps ------------------------------------------------------------------
if [ "$LOCAL" = "1" ]; then
    : "${PITR_PGDATA:?--local: PITR_PGDATA}" "${PITR_LOCAL_PG_OPTS:?--local: PITR_LOCAL_PG_OPTS (postgres flags of the live cluster)}"
    STATE=${PITR_STATE_DIR:-$ROOT_DIR/backups/.pitr}/switch.state
    LIVE_LINK=${PITR_LOCAL_LIVE_FILE:-$(dirname "$STATE")/live-data}
    DB=${PGDATABASE:?}
    as_pg() { runuser -u postgres -- "$@"; }
    current_data() { if [ -f "$LIVE_LINK" ]; then cat "$LIVE_LINK"; else printf '%s' "$PITR_PGDATA"; fi; }
    copy_path() { if [ -n "$dir_arg" ]; then printf '%s' "$dir_arg"; else printf '%s/%s' "${PITR_RESTORE_ROOT:?}" "$name"; fi; }
    check_copy() { if [ -n "$name" ]; then sh "$SCRIPT_DIR/pitr-restore.sh" --local --check-copy "$name"; else sh "$SCRIPT_DIR/pitr-restore.sh" --local --check-copy "$dir_arg"; fi; }
    stop_db() { [ ! -f "$(current_data)/postmaster.pid" ] || as_pg pg_ctl -D "$(current_data)" -m fast -w -t 300 stop >/dev/null; }
    stop_all() { stop_db; }
    set_data() { mkdir -p "$(dirname "$LIVE_LINK")"; printf '%s' "$1" >"$LIVE_LINK"; }
    env_copy() { :; }
    start_db() {
        slog="${PITR_RESTORE_ROOT:?}/switch-postgres.log"
        : >>"$slog"; chown postgres "$slog"
        as_pg env PITR_PGDATA="$(current_data)" pg_ctl -D "$(current_data)" -l "$slog" -w -t "${PITR_SWITCH_DB_TIMEOUT:-600}" -o "$PITR_LOCAL_PG_OPTS" start >/dev/null 2>&1 || true
        if [ ! -f "$(current_data)/postmaster.pid" ]; then tail -n 5 "$slog" >&2; return 1; fi
    }
    start_backup() { :; }
    start_apps() { :; }
    psql_live() { psql -X -A -t -q -v ON_ERROR_STOP=1 -c "$1"; }
    backup_sh() { PITR_PGDATA=$(current_data) sh "$SCRIPT_DIR/backup.sh" "$@"; }
    app_check() { :; }
    wait_daemon() { :; }
    data_source() { current_data; }
    mark_previous() { : >"$1/$PREV_MARK"; printf 'Avvalgi ish bazasi, %s gacha. Qayta ishga tushirilmaydi.\n' "$(date -u '+%Y-%m-%d %H:%M:%S')" >"$1/$PREV_MARK"; }
else
    command -v docker >/dev/null 2>&1 || die "docker topilmadi (buyruqni serverda, loyiha papkasida bajaring)"
    cd "$ROOT_DIR"
    [ -f .env ] || die "ildizdagi .env yo'q"
    # In the project directory, owned by the operator (./backups/.pitr is root-only: the backup container writes it)
    STATE="$ROOT_DIR/.pitr-switch/switch.state"
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
    stop_db() { docker compose stop postgres; }
    stop_all() {
        # shellcheck disable=SC2086
        docker compose stop $APP_SERVICES backup >/dev/null 2>&1 || true
        stop_db
    }
    set_data() {
        # awk with the value from the environment: a path may hold any character sed would treat specially
        tmp=".env.pitr-switch.tmp"
        PITR_SWITCH_VALUE=$1 awk -v stamp="$(date -u +%F)" '
            BEGIN { v = ENVIRON["PITR_SWITCH_VALUE"]; done = 0 }
            /^POSTGRES_DATA_VOLUME=/ { if (v != "" && v != "postgres_data" && !done) print "POSTGRES_DATA_VOLUME=" v; done = 1; next }
            { print }
            END {
                if (!done && v != "" && v != "postgres_data") {
                    print ""
                    print "# scripts/pitr-switch.sh (" stamp "): ish bazasi PITR nusxasiga almashtirilgan (docs/OPERATIONS.md, 18-bo'"'"'lim)"
                    print "POSTGRES_DATA_VOLUME=" v
                }
            }' .env >"$tmp"
        cat "$tmp" >.env
        rm -f "$tmp"
    }
    env_copy() { cp .env ".env.before-pitr-switch-$1"; }
    start_db() { timeout "${PITR_SWITCH_DB_TIMEOUT:-600}" docker compose up -d --wait --wait-timeout "${PITR_SWITCH_DB_TIMEOUT:-600}" postgres; }
    start_backup() { docker compose up -d backup; }
    start_apps() {
        # Everything that ran before the switch (on CI only postgres, backup and minio exist)
        for s in $APP_SERVICES; do
            if [ -n "$(docker compose ps -a -q "$s" 2>/dev/null)" ]; then docker compose up -d "$s"; fi
        done
    }
    psql_live() { docker compose exec -T postgres psql -X -A -t -q -v ON_ERROR_STOP=1 -U "$DBUSER" -d "$DB" -c "$1"; }
    backup_sh() { docker compose exec -T backup sh /usr/local/bin/backup.sh "$@"; }
    app_check() {
        # the backend is still stopped: a one-off container against the new database
        if [ -n "$(docker compose ps -a -q backend 2>/dev/null)" ]; then
            docker compose run --rm --no-deps -e DB_POOLER=direct -e RUN_MIGRATIONS=false backend sh -c \
                '! php artisan migrate:status | grep -q Pending && php artisan db:restore-smoke'
        else
            log "    ilova tekshiruvi o'tkazib yuborildi (backend xizmati yo'q)"
        fi
    }
    wait_daemon() {
        # The daemon restarted with the backup container: let its init (stanza-create, check) finish first, it holds the lock
        i=0
        until [ "$(psql_live "SELECT recorded_at >= '$switched_at' FROM monitoring_markers WHERE key = 'pitr'" 2>/dev/null)" = "t" ]; do
            i=$((i + 1)); [ "$i" -lt 200 ] || break
            sleep 3
        done
    }
    data_source() { # host path of the live data directory (a named volume included)
        cid=$(docker compose ps -q postgres)
        [ -n "$cid" ] || { current_data; return 0; }
        docker inspect -f '{{range .Mounts}}{{if eq .Destination "/var/lib/postgresql/data"}}{{.Source}}{{end}}{{end}}' "$cid"
    }
    mark_previous() {
        # inside the previous data directory: --list shows it, --drop asks for its name, the entrypoint never starts it
        docker compose run --rm --no-deps -v "$1:/restore/previous-live" backup sh -c \
            "printf 'Avvalgi ish bazasi, %s gacha. Qayta ishga tushirilmaydi.\n' \"\$(date -u '+%Y-%m-%d %H:%M:%S')\" > /restore/previous-live/$PREV_MARK" >/dev/null
    }
fi

mkdir -p "$(dirname "$STATE")"
state_get() { sed -n "s/^$1=//p" "$STATE" 2>/dev/null | tail -n 1; }

# --- revert: back to the database that was live before this switch (it never diverged) ----------------------------
reverting=0
stage=none
revert_now() {
    [ "$reverting" = "0" ] || return 0
    reverting=1
    trap '' INT TERM HUP
    log "oldingi ish bazasi ($previous) avtomatik qaytarilmoqda"
    stop_db >/dev/null 2>&1 || true
    set_data "$previous"
    ok=0
    if start_db; then
        i=0
        while [ "$i" -lt 120 ]; do
            [ "$(psql_live "SELECT pg_is_in_recovery()" 2>/dev/null)" != "f" ] || { ok=1; break; }
            i=$((i + 1)); sleep 1
        done
    fi
    if [ "$ok" = "1" ]; then
        start_backup || true
        start_apps || true
        rm -f "$STATE"
        log "QAYTARILDI: ish bazasi yana $previous, hamma xizmat ko'tarildi. Almashtirish BAJARILMADI (sababi yuqorida)"
        return 0
    fi
    log "DIQQAT: oldingi bazani ham ko'tarib bo'lmadi. Qayta urinish: scripts/pitr-switch.sh --recover. Yoki qo'lda: ildiz .env da POSTGRES_DATA_VOLUME $( [ "$previous" = "postgres_data" ] && echo "qatorini o'chiring" || echo "=$previous qiling"), so'ng docker compose up -d; postgres logini ko'ring (docker compose logs --tail=100 postgres). Texnik yordamchiga qo'ng'iroq qiling"
    return 1
}
on_exit() {
    rc=$?
    trap - EXIT
    if [ "$stage" = "switching" ]; then
        revert_now || exit 1
        exit 1
    fi
    exit "$rc"
}

# --- --recover: an interrupted switch (killed, power loss): the state file says where to go back to ------------------
if [ "$recover" = "1" ]; then
    [ -f "$STATE" ] || die "to'xtab qolgan almashtirish yo'q ($STATE): hech narsa qilinmadi"
    previous=$(state_get PREVIOUS)
    [ -n "$previous" ] || die "$STATE da PREVIOUS yo'q"
    log "to'xtab qolgan almashtirish ($(state_get AT): $previous -> $(state_get TARGET), bosqich $(state_get PHASE)) qaytarilmoqda"
    revert_now || exit 1
    exit 0
fi

if [ -f "$STATE" ]; then
    die "oldingi almashtirish tugallanmagan ($(state_get AT): $(state_get PREVIOUS) -> $(state_get TARGET)): avval scripts/pitr-switch.sh --recover. Hech narsa o'zgarmadi"
fi

# --- 1. what goes where -------------------------------------------------------------------------------------------
target=$(copy_path)
case "$target" in /*) ;; *) die "nusxa yo'li aniqlanmadi: $target" ;; esac
[ "$target" != "$(current_data)" ] || die "$target allaqachon ish bazasi"
log "1/8 tekshiruv: $target"
check_out=$(check_copy) || die "nusxa ish bazasi qilishga yaroqsiz (yuqoridagi XATO). Hech narsa o'zgarmadi"
printf '%s\n' "$check_out" | sed 's/^/    /' >&2
log "ISH BAZASI ALMASHTIRILADI: $(current_data) -> $target"
log "DIQQAT: tiklash nuqtasidan keyin hozirgi bazaga yozilgan HAMMA narsa (buyurtmalar, to'lovlar) yangi bazada bo'lmaydi. Hozirgi baza o'chirilmaydi (saqlab qo'yiladi), lekin unga qaytilmaydi"

[ -w "$(dirname "$STATE")" ] || die "$(dirname "$STATE") ga yozib bo'lmaydi (shu foydalanuvchi bilan): hech narsa o'zgarmadi"
if [ "$LOCAL" = "0" ]; then
    if [ ! -w .env ] || [ ! -w . ]; then die "ildizdagi .env yoki loyiha papkasiga yozib bo'lmaydi: hech narsa o'zgarmadi"; fi
fi

# --- 2. typed confirmation ----------------------------------------------------------------------------------------
if [ -z "$confirm" ]; then
    [ -t 0 ] || die "tasdiq kerak: terminalda ishga tushiring yoki --confirm $DB bering"
    printf 'Davom etish uchun bazaning nomini yozing (%s): ' "$DB" >&2
    read -r confirm || confirm=
fi
[ "$confirm" = "$DB" ] || die "tasdiqlanmadi ('$confirm' != '$DB'): hech narsa o'zgarmadi"

# --- 3-7: everything until the new full backup is revertible --------------------------------------------------------
switched_at=$(date -u '+%Y-%m-%d %H:%M:%S')
stamp=$(date -u +%Y%m%dT%H%M%SZ)
previous=$(current_data)
previous_src=$(data_source)
env_copy "$stamp"
# everything the revert needs, BEFORE the first destructive step (a later kill -9 is undone by --recover)
{ echo "AT=$switched_at"; echo "PREVIOUS=$previous"; echo "TARGET=$target"; echo "PHASE=stopping"; } >"$STATE"
phase() { echo "PHASE=$1" >>"$STATE"; }
stage=switching
trap on_exit EXIT
trap 'exit 130' INT TERM HUP

log "3/8 ilova, backup va postgres to'xtatilmoqda"
test_point stopping
stop_all
phase stopped
log "4/8 POSTGRES_DATA_VOLUME = $target (oldingi: $previous)"
set_data "$target"
test_point switched
log "5/8 postgres ishga tushirilmoqda"
start_db || die "postgres ishga tushmadi"
i=0
until [ "$(psql_live "SELECT pg_is_in_recovery()" 2>/dev/null)" = "f" ]; do
    i=$((i + 1)); [ "$i" -lt "${PITR_SWITCH_RECOVERY_WAIT:-600}" ] || die "baza ${PITR_SWITCH_RECOVERY_WAIT:-600} s ichida tayyor bo'lmadi"
    sleep 1
done
phase started
counts=$(psql_live "SELECT string_agg(t || '=' || n, ' ') FROM (SELECT 'orders' t, (SELECT count(*) FROM orders) n UNION ALL SELECT 'payments', (SELECT count(*) FROM payments) UNION ALL SELECT 'fiscal_receipts', (SELECT count(*) FROM fiscal_receipts) UNION ALL SELECT 'users', (SELECT count(*) FROM users)) x" 2>/dev/null || true)
tli=$(psql_live "SELECT timeline_id FROM pg_control_checkpoint()" 2>/dev/null || echo '?')
log "    baza ishlayapti (pg_is_in_recovery = f), timeline $tli; ${counts:-jadvallar soni olinmadi}"
test_point started
start_backup

log "6/8 ilova tekshiruvi (migrate:status, db:restore-smoke)"
if [ -n "${PITR_SWITCH_APP_CMD:-}" ]; then
    sh -c "$PITR_SWITCH_APP_CMD" || die "ilova tekshiruvi o'tmadi"
else
    app_check || die "ilova tekshiruvi o'tmadi (yuqorida XATO qatorlari)"
fi
test_point checked

log "7/8 yangi timeline $tli da to'liq PITR nusxa (repo1)"
wait_daemon
backup_sh --pitr-backup full 1 || die "repo1 ga to'liq nusxa olinmadi"
phase complete
stage=complete
trap - EXIT INT TERM HUP
rm -f "$STATE"

# --- 8. the switch is complete ------------------------------------------------------------------------------------
log "8/8 ilova ko'tarilmoqda; repo2 (S3)"
start_apps
mark_previous "${previous_src:-$previous}" || log "OGOHLANTIRISH: avvalgi bazaga belgi qo'yilmadi ($previous)"
backup_sh --pitr-backup full 2 || log "DIQQAT: repo2 (S3) ga to'liq nusxa olinmadi: almashtirish tugagan, lekin server tashqarisidagi nusxa eski. Sababini tuzating va bajaring: docker compose exec backup sh /usr/local/bin/backup.sh --pitr-backup full 2"
log "TAYYOR: ish bazasi endi $target (timeline $tli). Avvalgi baza saqlab qo'yilgan: $previous (qayta ishga tushirilmaydi; texnik yordamchi uchun oxirgi chora)"
