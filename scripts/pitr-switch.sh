#!/bin/sh
# RIZQIM: PITR dan tiklangan nusxani ISH BAZASI qilish (va orqaga qaytarish). Faqat haqiqiy avariyada, texnik yordamchi
# bilan. Bitta buyruq (serverda, loyiha papkasida):
#
#   scripts/pitr-switch.sh <nom>              pitr-restore.sh --into <nom> bilan tiklangan, so'ng --stop qilingan nusxa
#   scripts/pitr-switch.sh --dir /srv/...     pitr-restore.sh --into-dir bilan xostdagi papkaga tiklangan nusxa
#   scripts/pitr-switch.sh --back             oldingi ish bazasiga qaytish (oxirgi almashtirish yozib qo'ygan)
#   scripts/pitr-switch.sh --finalize         hozirgi bazani yakuniy deb belgilash (orqaga qaytish yozuvi yopiladi)
#   --confirm <baza-nomi>                     tasdiqni oldindan berish (avtomatlashtirish, CI); aks holda so'raladi
#
# Nima qiladi: (1) tekshiradi: nusxa to'liq tiklangan (promote bo'lgan) va toza to'xtatilgan, joy yetadi, nusxa ish
# bazasining o'zi emas, oldingi almashtirish yakunlangan (--back yoki --finalize); (2) nima bo'lishini ko'rsatadi va
# baza nomini yozib tasdiqlashni so'raydi; (3) ilova xizmatlarini, backup va postgres ni to'xtatadi; (4) ildiz .env da
# POSTGRES_DATA_VOLUME ni nusxaga o'rnatadi (eski qiymat saqlanadi, eski ma'lumot o'zgarmaydi); (5) postgres ni
# ko'taradi, pg_is_in_recovery() = f va asosiy jadvallar sonini tekshiradi; nusxaning timeline tarixi zaxira omboriga
# yuboriladi; (6) hamma xizmatni ko'taradi, `migrate:status` va `db:restore-smoke` ni tekshiradi; (7) YANGI TO'LIQ PITR
# nusxa oladi; (8) orqaga qaytish buyrug'ini chiqaradi. 3-5 qadamlarda xato bo'lsa skript o'zi oldingi holatga qaytaradi
# (qaytara olmasa nima qilish kerakligini yozadi). Tiklash nuqtasidan keyin eski bazaga yozilganlar yangi bazada YO'Q.
# --back: shu qadamlar teskari yo'nalishda; avval tashlab ketilayotgan timeline nusxalarini tozalash belgisi qo'yiladi
# (tozalash tugaguncha eski nusxalar o'chirilmaydi). Almashtirishdan keyin yangi bazaga yozilganlar eski bazada YO'Q.
#
# --local: Docker'siz mashq (scripts/pitr-ci.sh --local): "ish bazasi" = PITR_PGDATA klasteri, pg_ctl bilan.
# Chiqish kodi: 0 = almashtirildi va tekshirildi; 1 = xato (qaysi qadamda to'xtagani va nima qilish yozilgan).

set -eu

SCRIPT_DIR=$(cd "$(dirname "$0")" && pwd)
ROOT_DIR=$(cd "$SCRIPT_DIR/.." && pwd)
log() { printf '%s pitr-switch: %s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$*" >&2; }
die() { log "XATO: $*"; exit 1; }
usage() { sed -n '2,25p' "$0" | sed 's/^# \{0,1\}//'; }

LOCAL=0
name=
dir_arg=
back=0
finalize=0
confirm=
while [ $# -gt 0 ]; do
    case "$1" in
        --local) LOCAL=1; shift ;;
        --back) back=1; shift ;;
        --finalize) finalize=1; shift ;;
        --dir) dir_arg=${2:-}; shift 2 ;;
        --confirm) confirm=${2:-}; shift 2 ;;
        -h | --help) usage; exit 0 ;;
        -*) die "noma'lum flag: $1" ;;
        *) [ -z "$name" ] || die "ortiqcha argument: $1"; name=$1; shift ;;
    esac
done
[ "$back$finalize" != "00" ] || [ -n "$name$dir_arg" ] || { usage; exit 1; }

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
    check_copy() { if [ -n "$name" ]; then sh "$SCRIPT_DIR/pitr-restore.sh" --local --check-copy "$name"; else sh "$SCRIPT_DIR/pitr-restore.sh" --local --check-copy "$dir_arg"; fi; }
    stop_db() { [ ! -f "$(current_data)/postmaster.pid" ] || as_pg pg_ctl -D "$(current_data)" -m fast -w -t 300 stop >/dev/null; }
    stop_all() { stop_db; }
    set_data() { mkdir -p "$(dirname "$LIVE_LINK")"; printf '%s' "$1" >"$LIVE_LINK"; }
    start_db() {
        slog="${PITR_RESTORE_ROOT:?}/switch-postgres.log"
        : >>"$slog"; chown postgres "$slog"
        as_pg env PITR_PGDATA="$(current_data)" pg_ctl -D "$(current_data)" -l "$slog" -w -t "${PITR_SWITCH_DB_TIMEOUT:-600}" -o "$PITR_LOCAL_PG_OPTS" start >/dev/null 2>&1 || true
        if [ ! -f "$(current_data)/postmaster.pid" ]; then tail -n 5 "$slog" >&2; return 1; fi
    }
    start_all() { :; }
    psql_live() { psql -X -A -t -q -v ON_ERROR_STOP=1 -c "$1"; }
    backup_sh() { PITR_PGDATA=$(current_data) sh "$SCRIPT_DIR/backup.sh" "$@"; }
    wait_daemon() { :; }
    previous_value() { current_data; }
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
                    print "# scripts/pitr-switch.sh (" stamp "): ish bazasi PITR nusxasiga almashtirilgan. Orqaga: scripts/pitr-switch.sh --back"
                    print "POSTGRES_DATA_VOLUME=" v
                }
            }' .env >"$tmp"
        cat "$tmp" >.env
        rm -f "$tmp"
    }
    start_db() { timeout "${PITR_SWITCH_DB_TIMEOUT:-600}" docker compose up -d --wait --wait-timeout "${PITR_SWITCH_DB_TIMEOUT:-600}" postgres; }
    start_all() {
        # Everything that ran before the switch (on CI only postgres, backup and minio exist)
        docker compose up -d backup
        for s in $APP_SERVICES; do
            if [ -n "$(docker compose ps -a -q "$s" 2>/dev/null)" ]; then docker compose up -d "$s"; fi
        done
    }
    psql_live() { docker compose exec -T postgres psql -X -A -t -q -v ON_ERROR_STOP=1 -U "$DBUSER" -d "$DB" -c "$1"; }
    backup_sh() {
        if [ -n "${PITR_TEST_FORGET_FAIL:-}" ]; then
            docker compose exec -T -e PITR_TEST_FORGET_FAIL="$PITR_TEST_FORGET_FAIL" backup sh /usr/local/bin/backup.sh "$@"
        else
            docker compose exec -T backup sh /usr/local/bin/backup.sh "$@"
        fi
    }
    wait_daemon() {
        # The daemon restarted with the backup container: let its init (stanza-create, check) finish first, it holds the lock
        i=0
        until [ "$(psql_live "SELECT recorded_at > now() AT TIME ZONE 'UTC' - interval '10 minutes' AND recorded_at >= '$switched_at' FROM monitoring_markers WHERE key = 'pitr'" 2>/dev/null)" = "t" ]; do
            i=$((i + 1)); [ "$i" -lt 200 ] || break
            sleep 3
        done
    }
    previous_value() { current_data; }
fi

mkdir -p "$(dirname "$STATE")"
state_get() { sed -n "s/^$1=//p" "$STATE" 2>/dev/null | tail -n 1; }

# --- --finalize: the current database is the final one; no way back is kept -------------------------------------
if [ "$finalize" = "1" ]; then
    [ -f "$STATE" ] || die "yakunlanadigan almashtirish yo'q"
    if [ -z "$confirm" ] && [ -t 0 ]; then
        printf 'Hozirgi bazani yakuniy qilish uchun baza nomini yozing (%s): ' "$DB" >&2
        read -r confirm || confirm=
    fi
    [ "$confirm" = "$DB" ] || die "tasdiqlanmadi: hech narsa o'zgarmadi"
    { cat "$STATE"; echo "FINALIZED=$(date -u '+%Y-%m-%d %H:%M:%S')"; } >>"$STATE.history"
    rm -f "$STATE"
    # the backups of the replaced database's timeline stay in the repository until retention expires them (forward:
    # pgBackRest handles a growing timeline)
    log "TAYYOR: $(current_data) yakuniy ish bazasi. Oldingi baza endi orqaga qaytish nuqtasi emas (yozuv: $STATE.history)"
    exit 0
fi

# --- 1. what goes where -------------------------------------------------------------------------------------------
if [ "$back" = "1" ]; then
    [ -f "$STATE" ] || die "oldingi almashtirish yozuvi yo'q ($STATE): qaytariladigan narsa yo'q"
    target=$(state_get PREVIOUS)
    from=$(state_get CURRENT)
    abandoned_tli=$(state_get TIMELINE)
    back_at=$(state_get AT)
    [ -n "$target" ] || die "$STATE da PREVIOUS yo'q"
    [ "$(current_data)" = "$from" ] || die "hozirgi ish bazasi ($(current_data)) oxirgi almashtirishdagi ($from) emas: qo'lda tekshiring"
    log "ORQAGA QAYTISH: ish bazasi $from -> $target"
    log "DIQQAT: almashtirishdan keyin ($(state_get AT)) yangi bazaga yozilgan hamma narsa eski bazada YO'Q"
else
    if [ -f "$STATE" ]; then
        die "oldingi almashtirish ($(state_get AT): $(state_get PREVIOUS) -> $(state_get CURRENT)) hali yakunlanmagan: avval scripts/pitr-switch.sh --back (qaytish) yoki --finalize (hozirgisini yakuniy qilish). Hech narsa o'zgarmadi"
    fi
    target=$(copy_path)
    case "$target" in /*) ;; *) die "nusxa yo'li aniqlanmadi: $target" ;; esac
    [ "$target" != "$(current_data)" ] || die "$target allaqachon ish bazasi"
    log "1/8 tekshiruv: $target"
    check_out=$(check_copy) || die "nusxa ish bazasi qilishga yaroqsiz (yuqoridagi XATO). Hech narsa o'zgarmadi"
    printf '%s\n' "$check_out" | sed 's/^/    /' >&2
    log "ISH BAZASI ALMASHTIRILADI: $(current_data) -> $target"
    log "DIQQAT: tiklash nuqtasidan keyin hozirgi bazaga yozilgan HAMMA narsa (buyurtmalar, to'lovlar) yangi bazada bo'lmaydi. Hozirgi baza o'chirilmaydi: orqaga qaytish mumkin (--back)"
fi

# Everything this run must write, checked before anything is stopped
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

# Back to the older timeline: suspend expire BEFORE anything changes (pgBackRest's expire would otherwise delete the
# WAL of the older timeline's newest backups while backups of the abandoned one are retained)
if [ "$back" = "1" ] && [ -n "$abandoned_tli" ]; then
    backup_sh --pitr-forget-pending "$abandoned_tli" || die "zaxira tozalash belgisini qo'yib bo'lmadi (backup xizmati ishlayaptimi?): hech narsa o'zgarmadi"
fi

# --- 3-5. stop, switch, start the database; any failure here puts the previous database back ----------------------
switched_at=$(date -u '+%Y-%m-%d %H:%M:%S')
previous=$(previous_value)
stage=none
revert() {
    rc=$?
    [ "$stage" = "stopped" ] || exit "$rc"
    trap - EXIT
    log "XATO yuz berdi: oldingi ish bazasi ($previous) avtomatik qaytarilmoqda"
    stop_db >/dev/null 2>&1 || true
    set_data "$previous"
    if [ "$back" = "1" ]; then
        # still switched: the way back stays recorded
        { echo "AT=$back_at"; echo "PREVIOUS=$target"; echo "CURRENT=$previous"; echo "TIMELINE=$abandoned_tli"; } >"$STATE"
    else
        rm -f "$STATE"
    fi
    ok=0
    if start_db; then
        i=0
        while [ "$i" -lt 120 ]; do
            [ "$(psql_live "SELECT pg_is_in_recovery()" 2>/dev/null)" != "f" ] || { ok=1; break; }
            i=$((i + 1)); sleep 1
        done
    fi
    if [ "$ok" = "1" ]; then
        start_all || true
        # the timeline that was going to be abandoned is live again: its backups must stay
        [ "$back" != "1" ] || backup_sh --pitr-forget-clear || log "OGOHLANTIRISH: tozalash belgisini olib tashlang: docker compose exec backup sh /usr/local/bin/backup.sh --pitr-forget-clear"
        log "QAYTARILDI: ish bazasi yana $previous, hamma xizmat ko'tarildi. Almashtirish BAJARILMADI (sababi yuqorida)"
    else
        log "DIQQAT: oldingi bazani ham ko'tarib bo'lmadi. Qo'lda: ildiz .env da POSTGRES_DATA_VOLUME $( [ "$previous" = "postgres_data" ] && echo "qatorini o'chiring" || echo "=$previous qiling"), so'ng docker compose up -d; postgres logini ko'ring (docker compose logs --tail=100 postgres). Texnik yordamchiga qo'ng'iroq qiling"
    fi
    exit 1
}
trap revert EXIT

log "3/8 ilova, backup va postgres to'xtatilmoqda"
stage=stopped
stop_all
log "4/8 POSTGRES_DATA_VOLUME = $target (oldingi: $previous)"
[ "$LOCAL" = "1" ] || cp .env ".env.before-pitr-switch-$(date -u +%Y%m%dT%H%M%SZ)"
set_data "$target"
if [ "$back" = "1" ]; then
    { echo "AT=$switched_at"; echo "BACK_FROM=$from"; echo "CURRENT=$target"; } >>"$STATE.history"
    rm -f "$STATE"
else
    { echo "AT=$switched_at"; echo "PREVIOUS=$previous"; echo "CURRENT=$target"; } >"$STATE"
    cat "$STATE" >>"$STATE.history"
fi
log "5/8 postgres ishga tushirilmoqda"
start_db || die "postgres ishga tushmadi"
i=0
until [ "$(psql_live "SELECT pg_is_in_recovery()" 2>/dev/null)" = "f" ]; do
    i=$((i + 1)); [ "$i" -lt "${PITR_SWITCH_RECOVERY_WAIT:-600}" ] || die "baza ${PITR_SWITCH_RECOVERY_WAIT:-600} s ichida tayyor bo'lmadi"
    sleep 1
done
counts=$(psql_live "SELECT string_agg(t || '=' || n, ' ') FROM (SELECT 'orders' t, (SELECT count(*) FROM orders) n UNION ALL SELECT 'payments', (SELECT count(*) FROM payments) UNION ALL SELECT 'fiscal_receipts', (SELECT count(*) FROM fiscal_receipts) UNION ALL SELECT 'users', (SELECT count(*) FROM users)) x" 2>/dev/null || true)
tli=$(psql_live "SELECT timeline_id FROM pg_control_checkpoint()" 2>/dev/null || echo '?')
log "    baza ishlayapti (pg_is_in_recovery = f), timeline $tli; ${counts:-jadvallar soni olinmadi}"
[ "$back" = "1" ] || echo "TIMELINE=$tli" >>"$STATE"
stage=started
trap - EXIT

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

# --- 7. a new full backup -------------------------------------------------------------------------------------------
wait_daemon
if [ "$back" = "1" ] && [ -n "$abandoned_tli" ]; then
    log "7/8 tashlab ketilgan timeline $abandoned_tli nusxalari tozalanmoqda, so'ng yangi to'liq PITR nusxa"
    # forget first: the backup below retries it anyway, and never expires a repository whose cleanup is still pending
    backup_sh --pitr-forget-timeline || log "OGOHLANTIRISH: tozalash tugamadi: eski nusxalarni o'chirish to'xtatilgan holda qoladi (xavfsiz). Keyin qo'lda: docker compose exec backup sh /usr/local/bin/backup.sh --pitr-forget-timeline"
else
    log "7/8 yangi to'liq PITR nusxa olinmoqda"
fi
backup_sh --pitr-backup full || log "OGOHLANTIRISH: to'liq nusxa olinmadi: qo'lda bajaring: docker compose exec backup sh /usr/local/bin/backup.sh --pitr-backup full"

# --- 8. how to go back ----------------------------------------------------------------------------------------------
if [ "$back" = "1" ]; then
    log "8/8 TAYYOR: ish bazasi qaytarildi: $target"
else
    log "8/8 TAYYOR: ish bazasi endi $target. Eski ma'lumot o'zgarmagan: $previous"
    log "    Orqaga qaytish (almashtirishdan keyingi yozuvlar yo'qoladi): scripts/pitr-switch.sh --back; shu holatda qoldirish: scripts/pitr-switch.sh --finalize"
fi
[ "$app_rc" = "0" ]
