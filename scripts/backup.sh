#!/bin/sh
# Rizqim: PostgreSQL zaxira nusxasi (pg_dump custom format, zlib bilan siqilgan), vaqt belgili fayl, eskilarini o'chirish.
#
# Foydalanish:
#   scripts/backup.sh            bir marta zaxira oladi (deploy oldidan qo'lda: docker compose exec backup sh /usr/local/bin/backup.sh)
#   scripts/backup.sh --daemon   har kuni BACKUP_TIME (UTC) da zaxira oladi; docker-compose.yml dagi `backup` xizmati shuni ishlatadi
#   scripts/backup.sh --list-remote          S3 dagi zaxiralar ro'yxati (BACKUP_S3_* kerak)
#   scripts/backup.sh --fetch <fayl.dump>    S3 dan dump va .sha256 ni BACKUP_DIR ga yuklab oladi (tiklashdan oldin; restore.sh tekshiradi)
#   scripts/backup.sh --pitr-backup [full|diff] [1|2]   PITR (pgBackRest) zaxirasi hozir (repo1, S3 bo'lsa repo2 ham; yoki faqat bittasi) + expire
#   scripts/backup.sh --pitr-init                 stanza-create + check (+ to'liq zaxira yo'q bo'lsa oladi); demon buni o'zi qiladi
#   scripts/backup.sh --markers                   monitoring_markers dagi `pitr` va `disk` belgilarini hozir yozadi
#   scripts/backup.sh --pitr-heal                 WAL zanjirida uzilish yoki uzoq to'xtashdan keyin kerak bo'lsa to'liq nusxa
#
# M3 demon (--daemon) tartibi: ishga tushganda (PITR_ENABLED=1 bo'lsa) stanza-create + check, to'liq zaxira yo'q bo'lsa
# darhol oladi; har kuni BACKUP_TIME (UTC) da ketma-ket: PITR zaxirasi (yakshanba to'liq, boshqa kunlar farq) va expire,
# so'ng pg_dump (--load-via-partition-root; analytics_events ma'lumotisiz), so'ng jurnallar arxivi (archive-partitions.sh);
# har PITR_MARKER_INTERVAL (300 s) da `pitr` va `disk` belgilari; har oy birinchi yakshanba PITR_DRILL_TIME (23:00 UTC) da tiklash mashqi
# (PITR_DRILL_MONTHLY=0 o'chiradi); arxivlash PITR_GAP_FULL_AFTER (1800 s) dan uzoq ishlamay qolib tiklansa, PITR
# zanjirida uzilish bo'lishi mumkin: demon o'zi yangi to'liq zaxira oladi.
#
# Sozlamalar (env; docker compose'da ildizdagi .env dan):
#   BACKUP_DIR             papka (standart: <loyiha>/backups; `backup` konteynerida /backups)
#   BACKUP_RETENTION_DAYS  shu kundan eski zaxiralar o'chiriladi (standart 14)
#   BACKUP_TIME            --daemon uchun HH:MM, UTC (standart 21:30 = Toshkent 02:30)
#   BACKUP_RUN_ON_START    --daemon ishga tushganda darhol bir marta (standart 1)
#   BACKUP_OFFSITE_CMD     ixtiyoriy: zaxiradan keyin `sh -c` bilan bajariladi, fayl yo'li $BACKUP_FILE da.
#                          Masalan: rclone copy "$BACKUP_FILE" remote:rizqim-backups
#                          Xato bersa zaxira baribir saqlanadi, skript 2 kod bilan tugaydi va Boshqaruv markaziga "zaxira olindi" belgisi
#                          YOZILMAYDI (belgi faqat tashqi nusxalar ham bajarilgach yoziladi).
#   BACKUP_S3_ENDPOINT, BACKUP_S3_BUCKET, BACKUP_S3_ACCESS_KEY, BACKUP_S3_SECRET   ixtiyoriy: to'rttasi ham berilsa har bir muvaffaqiyatli
#                          dump (va .sha256) S3-mos xotiraga (AWS S3, Cloudflare R2, Backblaze B2, MinIO, ...) yuklanadi (rclone).
#                          Bittasi ham berilmasa hech narsa qilinmaydi. Yuklash xato bersa mahalliy zaxira saqlanadi, kod 2.
#   BACKUP_S3_REGION       ixtiyoriy (ba'zi provayderlar talab qiladi, masalan auto yoki eu-central-1)
#   BACKUP_S3_PREFIX       ixtiyoriy: bucket ichidagi papka (masalan rizqim/production)
#   BACKUP_S3_RETENTION_DAYS  ixtiyoriy: S3 dagi shu kundan eski zaxiralarni o'chiradi (kalitda o'chirish huquqi kerak). Bo'sh = o'chirmaydi;
#                          tavsiya: bucket'ning lifecycle qoidasi (docs/OPERATIONS.md)
#   PGHOST PGPORT PGUSER PGPASSWORD PGDATABASE   ulanish; berilmasa DB_HOST DB_PORT DB_USERNAME DB_PASSWORD DB_DATABASE,
#                          ular ham bo'lmasa backend/.env dan o'qiladi
#
# POSIX sh: postgres:alpine konteynerida bash yo'q.

set -eu
umask 077

SCRIPT_DIR=$(cd "$(dirname "$0")" && pwd)
ROOT_DIR=$(cd "$SCRIPT_DIR/.." && pwd)

log() { printf '%s backup: %s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$*" >&2; }
die() { log "XATO: $*"; exit 1; }

# M3 PITR helpers (scripts/pitr.sh; in the container mounted next to this file)
PITR_SCRIPT_DIR=$SCRIPT_DIR
PITR_LOG_TAG=backup
if [ -f "$SCRIPT_DIR/pitr.sh" ]; then
    # shellcheck source=scripts/pitr.sh
    . "$SCRIPT_DIR/pitr.sh"
    HAVE_PITR_LIB=1
else
    HAVE_PITR_LIB=0
fi

# backend/.env dan bitta qiymat (qo'shtirnoqsiz); fayl yoki kalit bo'lmasa bo'sh
dotenv_get() {
    [ -f "$ROOT_DIR/backend/.env" ] || return 0
    sed -n "s/^$1=//p" "$ROOT_DIR/backend/.env" | tail -n 1 | sed -e 's/^"\(.*\)"$/\1/' -e "s/^'\(.*\)'\$/\1/"
}

# Birinchi bo'sh bo'lmagan qiymat: env PG*, env DB_*, backend/.env
resolve() { # resolve <PG-nomi> <DB-nomi> [standart]
    eval "pg=\${$1:-}"
    eval "db=\${$2:-}"
    if [ -n "$pg" ]; then printf '%s' "$pg"; elif [ -n "$db" ]; then printf '%s' "$db"; else
        env_value=$(dotenv_get "$2")
        printf '%s' "${env_value:-${3:-}}"
    fi
}

PGHOST=$(resolve PGHOST DB_HOST 127.0.0.1); export PGHOST
PGPORT=$(resolve PGPORT DB_PORT 5432); export PGPORT
PGUSER=$(resolve PGUSER DB_USERNAME); export PGUSER
PGPASSWORD=$(resolve PGPASSWORD DB_PASSWORD); export PGPASSWORD
PGDATABASE=$(resolve PGDATABASE DB_DATABASE); export PGDATABASE

BACKUP_DIR=${BACKUP_DIR:-$ROOT_DIR/backups}
BACKUP_RETENTION_DAYS=${BACKUP_RETENTION_DAYS:-14}
BACKUP_TIME=${BACKUP_TIME:-21:30}
BACKUP_RUN_ON_START=${BACKUP_RUN_ON_START:-1}
BACKUP_OFFSITE_CMD=${BACKUP_OFFSITE_CMD:-}
BACKUP_S3_ENDPOINT=${BACKUP_S3_ENDPOINT:-}
BACKUP_S3_BUCKET=${BACKUP_S3_BUCKET:-}
BACKUP_S3_ACCESS_KEY=${BACKUP_S3_ACCESS_KEY:-}
BACKUP_S3_SECRET=${BACKUP_S3_SECRET:-}
BACKUP_S3_REGION=${BACKUP_S3_REGION:-}
BACKUP_S3_PREFIX=${BACKUP_S3_PREFIX:-}
BACKUP_S3_RETENTION_DAYS=${BACKUP_S3_RETENTION_DAYS:-}

# --- S3-mos tashqi nusxa (rclone) ---------------------------------------------------------------------------------
# To'rt asosiy o'zgaruvchi (endpoint, bucket, kalit, maxfiy kalit) bo'lsa yoqiladi; bo'sh bo'lsa hech narsa qilinmaydi.
s3_configured() {
    [ -n "$BACKUP_S3_ENDPOINT" ] && [ -n "$BACKUP_S3_BUCKET" ] && [ -n "$BACKUP_S3_ACCESS_KEY" ] && [ -n "$BACKUP_S3_SECRET" ]
}

s3_any() {
    [ -n "$BACKUP_S3_ENDPOINT$BACKUP_S3_BUCKET$BACKUP_S3_ACCESS_KEY$BACKUP_S3_SECRET" ]
}

s3_validate() {
    if s3_any && ! s3_configured; then
        log "OGOHLANTIRISH: BACKUP_S3_ENDPOINT, BACKUP_S3_BUCKET, BACKUP_S3_ACCESS_KEY va BACKUP_S3_SECRET to'rttasi ham kerak: S3 nusxa O'CHIQ, har zaxira 2 kod bilan tugaydi"
    fi
    if [ -n "$BACKUP_S3_RETENTION_DAYS" ]; then
        case "$BACKUP_S3_RETENTION_DAYS" in *[!0-9]*) die "BACKUP_S3_RETENTION_DAYS butun son bo'lishi kerak: $BACKUP_S3_RETENTION_DAYS" ;; esac
        [ "$BACKUP_S3_RETENTION_DAYS" -ge 1 ] || die "BACKUP_S3_RETENTION_DAYS kamida 1 bo'lishi kerak"
    fi
}

# rclone sozlamasi faqat env orqali (fayl yozilmaydi, maxfiy kalit buyruq qatorida ko'rinmaydi): masofaviy nom "offsite"
s3_env() {
    export RCLONE_CONFIG_OFFSITE_TYPE=s3
    export RCLONE_CONFIG_OFFSITE_PROVIDER=Other
    export RCLONE_CONFIG_OFFSITE_ENDPOINT="$BACKUP_S3_ENDPOINT"
    export RCLONE_CONFIG_OFFSITE_ACCESS_KEY_ID="$BACKUP_S3_ACCESS_KEY"
    export RCLONE_CONFIG_OFFSITE_SECRET_ACCESS_KEY="$BACKUP_S3_SECRET"
    # Kalitda bucket yaratish huquqi bo'lmasligi mumkin: bucket oldindan yaratilgan
    export RCLONE_CONFIG_OFFSITE_NO_CHECK_BUCKET=true
    if [ -n "$BACKUP_S3_REGION" ]; then export RCLONE_CONFIG_OFFSITE_REGION="$BACKUP_S3_REGION"; fi
}

s3_target() { # bucket/prefix (oxirida / yo'q)
    prefix=$(printf '%s' "$BACKUP_S3_PREFIX" | sed -e 's#^/*##' -e 's#/*$##')
    if [ -n "$prefix" ]; then printf 'offsite:%s/%s' "$BACKUP_S3_BUCKET" "$prefix"; else printf 'offsite:%s' "$BACKUP_S3_BUCKET"; fi
}

s3_require() {
    s3_configured || die "BACKUP_S3_ENDPOINT, BACKUP_S3_BUCKET, BACKUP_S3_ACCESS_KEY va BACKUP_S3_SECRET berilmagan"
    command -v rclone >/dev/null 2>&1 || die "rclone topilmadi (docker compose'dagi backup obrazida bor: infra/backup/Dockerfile)"
    s3_env
}

# Bitta zaxirani (dump + .sha256) yuklaydi; 0 = muvaffaqiyatli yoki o'chiq. Dump'dan keyin .sha256 yuklanadi: u
# S3 da bo'lsa, dump ham to'liq yuklangan. rclone yuklagandan keyin hajm/kontrol yig'indisini o'zi solishtiradi.
s3_upload() { # s3_upload <fayl-nomi>
    s3_configured || { s3_any && return 1; return 0; }
    if ! command -v rclone >/dev/null 2>&1; then
        log "XATO: rclone topilmadi, S3 ga yuklanmadi"
        return 1
    fi
    s3_env
    target=$(s3_target)
    log "S3: $1 -> $target/ (endpoint $BACKUP_S3_ENDPOINT)"
    if ! rclone copyto --retries 3 --low-level-retries 5 "$BACKUP_DIR/$1" "$target/$1" \
        || ! rclone copyto --retries 3 --low-level-retries 5 "$BACKUP_DIR/$1.sha256" "$target/$1.sha256"; then
        log "XATO: S3 ga yuklash muvaffaqiyatsiz (mahalliy zaxira saqlandi)"
        return 1
    fi
    log "S3: yuklandi"
    if [ -n "$BACKUP_S3_RETENTION_DAYS" ]; then
        # Only the dumps at the top of the prefix: --max-depth 1 and anchored names, so the PITR repository (<prefix>/pitr)
        # and the journal archive (<prefix>/archive, never deleted) are out of reach whatever the prefixes are
        if ! rclone delete --max-depth 1 --min-age "${BACKUP_S3_RETENTION_DAYS}d" --include '/rizqim-*.dump' --include '/rizqim-*.dump.sha256' "$target"; then
            log "OGOHLANTIRISH: S3 dagi eski zaxiralarni o'chirib bo'lmadi (kalitda o'chirish huquqi yo'qmi?)"
        fi
    fi
    return 0
}

validate() {
    [ -n "$PGUSER" ] || die "ma'lumotlar bazasi foydalanuvchisi yo'q (PGUSER / DB_USERNAME / backend/.env)"
    [ -n "$PGDATABASE" ] || die "ma'lumotlar bazasi nomi yo'q (PGDATABASE / DB_DATABASE / backend/.env)"
    case "$BACKUP_RETENTION_DAYS" in '' | *[!0-9]*) die "BACKUP_RETENTION_DAYS butun son bo'lishi kerak: $BACKUP_RETENTION_DAYS" ;; esac
    [ "$BACKUP_RETENTION_DAYS" -ge 1 ] || die "BACKUP_RETENTION_DAYS kamida 1 bo'lishi kerak"
    case "$BACKUP_TIME" in [0-2][0-9]:[0-5][0-9]) ;; *) die "BACKUP_TIME HH:MM ko'rinishida bo'lishi kerak: $BACKUP_TIME" ;; esac
    s3_validate
    command -v pg_dump >/dev/null 2>&1 || die "pg_dump topilmadi (postgresql-client o'rnating yoki backup konteynerida ishga tushiring)"
}

# P4-15: oxirgi tekshirilgan dump vaqtini bazadagi monitoring_markers jadvaliga yozadi: Boshqaruv markazi (Monitoring > Holat)
# va "zaxira eskirgan" ogohlantirishi shundan o'qiydi. Belgi FAQAT dump tekshiruvdan o'tgach VA sozlangan tashqi nusxa
# (BACKUP_OFFSITE_CMD, S3) muvaffaqiyatli bajarilgach yoziladi: faqat mahalliy qolgan zaxira "tayyor" hisoblanmaydi, belgi
# eskirib, "zaxira eskirgan" ogohlantirishi chiqadi. Belgini yozib bo'lmasa faqat ogohlantirish chiqadi (jadval yo'q:
# migratsiya bajarilmagan; psql yo'q); zaxiraning o'zi baribir saqlangan.
record_marker() { # record_marker <fayl-nomi> <hajm-bayt> <tashqi-nusxa: none|ok>
    if ! command -v psql >/dev/null 2>&1; then
        log "OGOHLANTIRISH: psql topilmadi, zaxira vaqti markazga yozilmadi"
        return 0
    fi
    if ! psql -X -q -v ON_ERROR_STOP=1 -c "INSERT INTO monitoring_markers (key, recorded_at, meta) VALUES ('backup', now() AT TIME ZONE 'UTC', json_build_object('file', '$1', 'size', $2, 'offsite', '$3')) ON CONFLICT (key) DO UPDATE SET recorded_at = EXCLUDED.recorded_at, meta = EXCLUDED.meta" >/dev/null 2>&1; then
        log "OGOHLANTIRISH: zaxira vaqtini markazga yozib bo'lmadi (monitoring_markers jadvali yo'qmi? migratsiyani bajaring)"
    fi
    return 0
}

run_backup() {
    mkdir -p "$BACKUP_DIR"
    name="rizqim-${PGDATABASE}-$(date -u +%Y%m%dT%H%M%SZ).dump"
    final="$BACKUP_DIR/$name"
    partial="$final.partial"

    log "$PGDATABASE ($PGHOST:$PGPORT) -> $final"
    # --no-owner/--no-privileges: boshqa foydalanuvchi nomli bazaga ham tiklash mumkin bo'lsin
    # --load-via-partition-root: rows of the M3 partitioned journals restore through their parent, into any partition layout
    # --exclude-table-data: Mini App usage statistics (analytics_events, its monthly partitions analytics_events_YYYY_MM
    # and analytics_events_default; config/partitions.php) are not copied into the daily dumps, only their structure:
    # the privacy policy (s6, s8) keeps raw events 3 months and promises no longer copy than the PITR window. The
    # patterns name exactly those tables, so a future analytics_events_<something> table is still dumped.
    if ! pg_dump --format=custom --compress=6 --no-owner --no-privileges --load-via-partition-root \
        --exclude-table-data='analytics_events' \
        --exclude-table-data='analytics_events_[0-9][0-9][0-9][0-9]_[0-9][0-9]' \
        --exclude-table-data='analytics_events_default' \
        --file="$partial"; then
        rm -f "$partial"
        log "XATO: pg_dump bajarilmadi"
        return 1
    fi
    # Fayl o'qiladigan arxiv ekanini tekshirish: buzuq nusxa "muvaffaqiyatli zaxira" bo'lib qolmasin
    if ! pg_restore --list "$partial" >/dev/null 2>&1; then
        rm -f "$partial"
        log "XATO: zaxira fayli tekshiruvdan o'tmadi (pg_restore --list)"
        return 1
    fi
    mv "$partial" "$final"
    (cd "$BACKUP_DIR" && sha256sum "$name" >"$name.sha256")
    size=$(wc -c <"$final" | tr -d ' ')
    log "tayyor: $name ($size bayt)"

    status=0
    offsite=none
    if [ -n "$BACKUP_OFFSITE_CMD" ]; then
        offsite=ok
        log "tashqi nusxa: BACKUP_OFFSITE_CMD bajarilmoqda"
        if ! BACKUP_FILE="$final" sh -c "$BACKUP_OFFSITE_CMD"; then
            log "XATO: BACKUP_OFFSITE_CMD xato bilan tugadi (mahalliy zaxira saqlandi)"
            status=2
        fi
    fi

    if s3_any; then
        offsite=ok
    fi
    if ! s3_upload "$name"; then
        status=2
    fi

    # Boshqaruv markaziga "tekshirilgan zaxira bor" faqat tashqi nusxa ham bajarilgandan keyin aytiladi
    if [ "$status" -eq 0 ]; then
        record_marker "$name" "$size" "$offsite"
    else
        log "OGOHLANTIRISH: tashqi nusxa bajarilmadi, zaxira vaqti markazga yozilmadi (Monitoring'dagi 'zaxira eskirgan' qoidasi ogohlantiradi)"
    fi

    # Eskilarini o'chirish; yangi zaxira allaqachon yozilgan, shuning uchun bo'sh qolib ketmaydi
    find "$BACKUP_DIR" -maxdepth 1 -type f \( -name 'rizqim-*.dump' -o -name 'rizqim-*.dump.sha256' -o -name 'rizqim-*.dump.partial' \) \
        -mtime "+$BACKUP_RETENTION_DAYS" -exec rm -f {} + 2>/dev/null || true

    return "$status"
}

# --- M3: PITR (pgBackRest) and the 5-minute markers ---------------------------------------------------------------
PITR_MARKER_INTERVAL=${PITR_MARKER_INTERVAL:-300}
PITR_GAP_FULL_AFTER=${PITR_GAP_FULL_AFTER:-1800}
PITR_DRILL_MONTHLY=${PITR_DRILL_MONTHLY:-1}
PITR_DRILL_TIME=${PITR_DRILL_TIME:-23:00}
PITR_STATE_DIR=${PITR_STATE_DIR:-$BACKUP_DIR/.pitr}

pitr_require_lib() { [ "$HAVE_PITR_LIB" = "1" ] || die "scripts/pitr.sh topilmadi (docker-compose.yml dagi backup volume'larini yangilang)"; }

pitr_has_full() { # pitr_has_full <repo>
    pgbr info --repo="$1" --output=json 2>/dev/null | grep -q '"type":"full"'
}

pitr_ping() {
    [ -n "${PITR_PING_URL:-}" ] || return 0
    wget -q -T 10 -O /dev/null "$PITR_PING_URL" 2>/dev/null || log "OGOHLANTIRISH: PITR_PING_URL ga ping yuborilmadi"
}

# --- "a new full backup is needed" (after a WAL gap or a long archive outage), per repository --------------------------
# need-full-repo<N> holds the reason (gap | outage). It is cleared only when THAT repository's full backup succeeded (or,
# for a gap, when the hole closed by itself). The old single flag full-needed is read as "both repositories".
need_set() { # need_set <repo> <reason>
    [ -f "$PITR_STATE_DIR/need-full-repo$1" ] || log "PITR: repo$1 uchun yangi to'liq nusxa kerak ($2)"
    printf '%s' "$2" >"$PITR_STATE_DIR/need-full-repo$1"
}
need_get() { # prints the reason, empty = not needed (the old single flag is converted on first sight)
    if [ -f "$PITR_STATE_DIR/full-needed" ]; then
        printf 'outage' >"$PITR_STATE_DIR/need-full-repo1"
        [ "$PITR_REPO2" != "on" ] || printf 'outage' >"$PITR_STATE_DIR/need-full-repo2"
        rm -f "$PITR_STATE_DIR/full-needed"
    fi
    if [ -f "$PITR_STATE_DIR/need-full-repo$1" ]; then cat "$PITR_STATE_DIR/need-full-repo$1"; fi
}
need_clear() { rm -f "$PITR_STATE_DIR/need-full-repo$1"; }
need_list() { # "repo1 repo2" of the repositories that need a full backup
    nl=
    for repo in 1 2; do
        [ "$repo" = "1" ] || [ "$PITR_REPO2" = "on" ] || continue
        [ -z "$(need_get "$repo")" ] || nl="${nl:+$nl }repo$repo"
    done
    printf '%s' "$nl"
}

# One backup run, per repository: repo1, then repo2 (S3) if configured. $1: full | diff | auto (Sunday, no full yet, or
# this repository needs one = full, else diff); $2: only this repository (optional). A repository that needs a full
# backup (an incident is open) gets one WITHOUT expire: heal backups never rotate out the history from before the
# incident; the next ordinary backup expires as usual. Test hooks: PITR_TEST_BACKUP_FAIL, PITR_TEST_EXPIRE_FAIL (1|2).
pitr_backup() {
    pitr_require_lib
    pitr_enabled || { log "PITR o'chiq (PITR_ENABLED=1 emas): pgBackRest zaxirasi olinmadi"; return 0; }
    pitr_load
    rc=0
    for repo in 1 2; do
        [ -z "${2:-}" ] || [ "$2" = "$repo" ] || continue
        if [ "$repo" = "2" ] && [ "$PITR_REPO2" != "on" ]; then
            [ -z "$PITR_REPO2_NOTE" ] || log "OGOHLANTIRISH: $PITR_REPO2_NOTE"
            continue
        fi
        need=$(need_get "$repo")
        rtype=$1
        if [ "$rtype" = "auto" ]; then
            rtype="diff"
            if [ "$(date -u +%u)" = "7" ] || [ -n "$need" ] || ! pitr_has_full "$repo"; then rtype=full; fi
        fi
        [ "$rtype" = "full" ] || pitr_has_full "$repo" || rtype=full
        noexp=
        if [ -n "$need" ] || [ "${PITR_SKIP_EXPIRE:-0}" = "1" ]; then noexp=--no-expire-auto; fi
        where=" (S3)"; [ "$repo" = "2" ] || where=" (shu server)"
        [ -z "$noexp" ] || where="$where, expire yo'q"
        log "PITR: $rtype zaxira, repo$repo$where"
        brc=0
        if [ "${PITR_TEST_BACKUP_FAIL:-}" = "$repo" ]; then log "XATO: (sinov) repo$repo zaxirasi bajarilmadi"; brc=1
        else
            # shellcheck disable=SC2086
            pgbr backup --repo="$repo" --type="$rtype" $noexp || brc=1
        fi
        if [ "$brc" != "0" ]; then
            log "XATO: PITR zaxirasi (repo$repo) bajarilmadi"
            rc=1
            continue
        fi
        if [ -n "$need" ] && [ "$rtype" = "full" ]; then
            need_clear "$repo"
            log "PITR: repo$repo: yangi to'liq nusxa olindi, zanjir shu nuqtadan davom etadi"
        fi
        if [ -z "$noexp" ]; then
            if [ "${PITR_TEST_EXPIRE_FAIL:-}" = "$repo" ] || ! pgbr expire --repo="$repo"; then
                log "XATO: pgbackrest expire (repo$repo) bajarilmadi (kalitda o'chirish huquqi bormi?)"
                rc=1
            fi
        fi
    done
    if [ "$rc" -eq 0 ]; then
        log "PITR: zaxira tayyor"
        pitr_ping
    fi
    write_markers || true
    return "$rc"
}

# The main loop's repair step, also callable once (--pitr-heal): a full backup for every repository that needs one,
# once archiving works again, at most once per PITR_HEAL_INTERVAL (6 h; the last attempt is kept in heal-last).
pitr_heal() {
    [ "$HAVE_PITR_LIB" = "1" ] || return 0
    pitr_enabled || return 0
    pitr_load
    needed=$(need_list)
    [ -n "$needed" ] || return 0
    [ ! -f "$PITR_STATE_DIR/outage-since" ] || return 0
    now=$(date -u +%s)
    last=$(cat "$PITR_STATE_DIR/heal-last" 2>/dev/null || echo 0)
    if [ $((now - last)) -lt "${PITR_HEAL_INTERVAL:-21600}" ]; then
        return 0
    fi
    pgbr check >/dev/null 2>&1 || { log "OGOHLANTIRISH: to'liq nusxa kerak ($needed), lekin pgbackrest check o'tmayapti"; return 1; }
    printf '%s' "$now" >"$PITR_STATE_DIR/heal-last"
    hrc=0
    for r in $needed; do
        pitr_backup full "${r#repo}" || hrc=1
    done
    # the back-off is for an incident that keeps failing; once healed, the next incident is healed at once
    [ -n "$(need_list)" ] || rm -f "$PITR_STATE_DIR/heal-last"
    return "$hrc"
}

# On daemon start: stanza (idempotent; also adds repo2 once S3 is configured), check, a first full backup
pitr_init() {
    pitr_require_lib
    pitr_enabled || { log "PITR o'chiq (PITR_ENABLED bo'sh): faqat kunlik pg_dump"; return 0; }
    pitr_load
    mkdir -p "$PITR_STATE_DIR"
    log "PITR yoqiq: stanza $PGBACKREST_STANZA, repo1 $PGBACKREST_REPO1_PATH, repo2 (S3): $PITR_REPO2"
    [ -z "$PITR_REPO2_NOTE" ] || log "OGOHLANTIRISH: $PITR_REPO2_NOTE"
    i=0
    until pgbr stanza-create; do
        i=$((i + 1))
        [ "$i" -lt "${PITR_INIT_TRIES:-10}" ] || { log "XATO: pgbackrest stanza-create bajarilmadi (Postgres ishlayaptimi? postgres obrazi yangilanganmi: docker compose up -d --build postgres)"; return 1; }
        sleep 15
    done
    if ! pgbr check; then
        log "XATO: pgbackrest check o'tmadi: WAL arxivga tushmayapti (postgres xizmatida PITR_ENABLED=1 va docker compose up -d --build postgres bajarilganmi?)"
        return 1
    fi
    if ! pitr_has_full 1; then
        log "PITR: hali to'liq zaxira yo'q, hozir olinadi"
        pitr_backup full || return 1
    fi
    return 0
}

# monitoring_markers `pitr` (pgBackRest info + pg_stat_archiver) and `disk` (data filesystem, pg_wal, spool).
# Read by the control center checks pitr_backup_stale and disk_usage (M3 A3).
write_markers() {
    [ "$HAVE_PITR_LIB" = "1" ] || return 0
    command -v psql >/dev/null 2>&1 || return 0
    if pitr_enabled; then
        pitr_load
        info=$(pgbr info --output=json 2>"$PITR_STATE_DIR/info.err") || info='[]'
        [ -n "$info" ] || info='[]'
        lasterr=$(tail -n 1 "$PITR_STATE_DIR/info.err" 2>/dev/null || true)
        outage=
        [ ! -f "$PITR_STATE_DIR/outage-since" ] || outage=$(cat "$PITR_STATE_DIR/outage-since")
        check_gaps
        needed=$(need_list)
        heal_last=$(cat "$PITR_STATE_DIR/heal-last" 2>/dev/null || true)
        heal_msg=
        [ -z "$needed" ] || heal_msg="Yangi to'liq nusxa kerak ($needed): avtomatik olinadi (eng ko'pi har ${PITR_HEAL_INTERVAL:-21600} s da bir urinish)"
        iso="'YYYY-MM-DD\"T\"HH24:MI:SS\"Z\"'"
        pitr_marker pitr "WITH s AS (SELECT (:'info')::jsonb -> 0 AS s),
              b AS (SELECT e FROM s, jsonb_array_elements(coalesce(s.s -> 'backup', '[]'::jsonb)) e),
              a AS (SELECT * FROM pg_stat_archiver)
            SELECT json_build_object(
              'enabled', true,
              'stanza', :'stanza',
              'status', CASE WHEN (SELECT (s -> 'status' ->> 'code')::int FROM s) = 0 AND :'gapopen' = '0' AND :'needed' = '' THEN 'ok' ELSE 'error' END,
              'message', coalesce(nullif(:'gapmsg', ''), nullif(:'healmsg', ''), (SELECT s -> 'status' ->> 'message' FROM s), nullif(:'lasterr', '')),
              'gap', CASE WHEN :'gap' = '1' THEN json_build_object(
                    'detected', true,
                    'repo', :'gaprepo'::int,
                    'timeline', :'gaptli'::int,
                    'after_wal', :'gapafter',
                    'after_at', to_char(to_timestamp(nullif(:'gapaftert', '')::bigint) AT TIME ZONE 'UTC', $iso),
                    'before_wal', :'gapbefore',
                    'before_at', to_char(to_timestamp(nullif(:'gapbeforet', '')::bigint) AT TIME ZONE 'UTC', $iso),
                    'backup_after', nullif(:'gapbackup', ''),
                    'restorable_from', to_char(to_timestamp(nullif(:'gapfrom', '')::bigint) AT TIME ZONE 'UTC', $iso),
                    'message', :'gapnote',
                    'restorable_windows', (SELECT json_agg(json_build_object(
                          'from', to_char(to_timestamp(split_part(w, '-', 1)::bigint) AT TIME ZONE 'UTC', $iso),
                          'to', to_char(to_timestamp(split_part(w, '-', 2)::bigint) AT TIME ZONE 'UTC', $iso)))
                        FROM unnest(string_to_array(nullif(:'gapwindows', ''), ',')) w)) END,
              'heal', CASE WHEN :'needed' = '' THEN NULL ELSE json_build_object(
                    'needed', to_json(string_to_array(:'needed', ' ')),
                    'last_attempt_at', to_char(to_timestamp(nullif(:'heallast', '')::bigint) AT TIME ZONE 'UTC', $iso),
                    'next_attempt_at', to_char(to_timestamp(coalesce(nullif(:'heallast', '')::bigint, 0) + :'healint'::bigint) AT TIME ZONE 'UTC', $iso)) END,
              'repo2', :'repo2',
              'repo2_note', nullif(:'repo2note', ''),
              'offsite', :'repo2' = 'on',
              'backups', (SELECT count(*) FROM b),
              'last_full_at', (SELECT to_char(to_timestamp(max((e -> 'timestamp' ->> 'stop')::bigint)) AT TIME ZONE 'UTC', $iso) FROM b WHERE e ->> 'type' = 'full'),
              'last_backup_at', (SELECT to_char(to_timestamp(max((e -> 'timestamp' ->> 'stop')::bigint)) AT TIME ZONE 'UTC', $iso) FROM b),
              'last_backup_type', (SELECT e ->> 'type' FROM b ORDER BY (e -> 'timestamp' ->> 'stop')::bigint DESC LIMIT 1),
              'last_backup_label', (SELECT e ->> 'label' FROM b ORDER BY (e -> 'timestamp' ->> 'stop')::bigint DESC LIMIT 1),
              'repos', (SELECT coalesce(json_agg(json_build_object(
                    'key', (r ->> 'key')::int,
                    'status', CASE WHEN (r -> 'status' ->> 'code')::int = 0 THEN 'ok' ELSE 'error' END,
                    'message', r -> 'status' ->> 'message',
                    'last_full_at', (SELECT to_char(to_timestamp(max((e -> 'timestamp' ->> 'stop')::bigint)) AT TIME ZONE 'UTC', $iso) FROM b WHERE e ->> 'type' = 'full' AND e -> 'database' ->> 'repo-key' = r ->> 'key'),
                    'last_backup_at', (SELECT to_char(to_timestamp(max((e -> 'timestamp' ->> 'stop')::bigint)) AT TIME ZONE 'UTC', $iso) FROM b WHERE e -> 'database' ->> 'repo-key' = r ->> 'key')
                  ) ORDER BY (r ->> 'key')::int), '[]'::json) FROM s, jsonb_array_elements(coalesce(s.s -> 'repo', '[]'::jsonb)) r),
              'wal_min', (SELECT s -> 'archive' -> 0 ->> 'min' FROM s),
              'wal_max', (SELECT s -> 'archive' -> 0 ->> 'max' FROM s),
              'archived_wal', (SELECT last_archived_wal FROM a),
              'archived_at', (SELECT to_char(last_archived_time AT TIME ZONE 'UTC', $iso) FROM a),
              'failed_wal', (SELECT last_failed_wal FROM a),
              'failed_at', (SELECT to_char(last_failed_time AT TIME ZONE 'UTC', $iso) FROM a),
              'failed_count', (SELECT failed_count FROM a),
              'archive_failing', (SELECT coalesce(last_failed_time > coalesce(last_archived_time, '-infinity'::timestamptz), false) FROM a),
              'ready_wal', (SELECT count(*) FROM pg_ls_archive_statusdir() WHERE name LIKE '%.ready'),
              'outage_since', to_char(to_timestamp(nullif(:'outage', '')::bigint) AT TIME ZONE 'UTC', $iso)
            )" -v "info=$info" -v "stanza=$PGBACKREST_STANZA" -v "repo2=$PITR_REPO2" -v "repo2note=$PITR_REPO2_NOTE" \
            -v "lasterr=$lasterr" -v "outage=$outage" -v "gap=$GAP" -v "gapopen=$GAP_OPEN" -v "gaprepo=${GAP_REPO:-0}" \
            -v "gaptli=${GAP_TLI:-0}" -v "gapafter=$GAP_AFTER" -v "gapaftert=$GAP_AFTER_TIME" -v "gapbefore=$GAP_BEFORE" \
            -v "gapbeforet=$GAP_BEFORE_TIME" -v "gapbackup=$GAP_BACKUP" -v "gapfrom=$GAP_FROM" -v "gapmsg=$GAP_MSG" \
            -v "gapnote=$GAP_NOTE" -v "gapwindows=$GAP_WINDOWS" -v "needed=$needed" -v "healmsg=$heal_msg" -v "heallast=$heal_last" \
            -v "healint=${PITR_HEAL_INTERVAL:-21600}" || true
        track_outage
    else
        pitr_marker pitr "json_build_object('enabled', false)" || true
    fi
    data=${PITR_PGDATA:-${PGDATA:-/var/lib/postgresql/data}}
    # df -Pk: total, used, available (KiB) of the filesystem that holds the data directory
    fs=$(df -Pk "$data" 2>/dev/null | awk 'NR == 2 { print $2 " " $3 " " $4 }')
    repofs=$(df -Pk "${PITR_REPO1_PATH:-/var/lib/pgbackrest}" 2>/dev/null | awk 'NR == 2 { print $2 " " $3 " " $4 }')
    spool_kb=$(du -sk "${PITR_SPOOL_MEASURE_PATH:-/var/spool/pgbackrest}" 2>/dev/null | cut -f1)
    # shellcheck disable=SC2086
    set -- ${fs:-0 0 0} ${repofs:-0 0 0}
    pitr_marker disk "json_build_object(
          'data_path', :'dpath',
          'fs_total_bytes', :'t'::bigint * 1024, 'fs_used_bytes', :'u'::bigint * 1024, 'fs_avail_bytes', :'a'::bigint * 1024,
          'used_percent', CASE WHEN :'u'::bigint + :'a'::bigint > 0 THEN round(100.0 * :'u'::bigint / (:'u'::bigint + :'a'::bigint), 1) END,
          'repo_used_percent', CASE WHEN :'ru'::bigint + :'ra'::bigint > 0 THEN round(100.0 * :'ru'::bigint / (:'ru'::bigint + :'ra'::bigint), 1) END,
          'repo_avail_bytes', :'ra'::bigint * 1024,
          'pg_wal_bytes', (SELECT coalesce(sum(size), 0) FROM pg_ls_waldir()),
          'spool_bytes', :'spool'::bigint * 1024,
          'db_bytes', (SELECT sum(pg_database_size(datname)) FROM pg_database WHERE datallowconn)
        )" -v "dpath=$data" -v "t=$1" -v "u=$2" -v "a=$3" -v "ru=$5" -v "ra=$6" -v "spool=${spool_kb:-0}" || true
}

# WAL continuity per repository (pitr_wal_report): a hole in the archived WAL of the live timeline = the PITR chain is
# broken there, whatever pg_stat_archiver says (a spool overflow drops WAL and reports success). Every repository is
# checked; the marker reports the worst one (an open gap, then a healed one). A hole counts only after a grace time:
# the segment after it must have been archived PITR_GAP_GRACE (600 s) ago (async pushes can arrive out of order).
# An open gap sets "a full backup is needed" for that repository; a need set for a hole that has since closed is
# cleared. repo2 (S3) is listed at most every PITR_GAP_S3_INTERVAL (3600 s; the result is cached), repo1 every run.
check_gaps() {
    GAP=0; GAP_OPEN=0; GAP_REPO=; GAP_TLI=; GAP_AFTER=; GAP_AFTER_TIME=; GAP_BEFORE=; GAP_BEFORE_TIME=; GAP_BACKUP=
    GAP_FROM=; GAP_MSG=; GAP_NOTE=; GAP_WINDOWS=
    live_tli=$(pitr_val "SELECT timeline_id FROM pg_control_checkpoint()")
    seg_size=$(pitr_val "SELECT setting FROM pg_settings WHERE name = 'wal_segment_size'")
    now=$(date -u +%s)
    worst=0
    for repo in 1 2; do
        [ "$repo" = "1" ] || [ "$PITR_REPO2" = "on" ] || continue
        cache="$PITR_STATE_DIR/gap-report-repo$repo"
        if [ "$repo" = "2" ] && [ "${PGBACKREST_REPO2_TYPE:-}" = "s3" ] && [ -f "$cache" ] && [ $((now - $(sed -n 's/^AT=//p' "$cache" 2>/dev/null || echo 0))) -lt "${PITR_GAP_S3_INTERVAL:-3600}" ]; then
            report=$(cat "$cache")
        else
            report=$(pitr_wal_report "$repo" "$live_tli" "$seg_size") || continue
            { echo "AT=$now"; printf '%s\n' "$report"; } >"$cache"
        fi
        # every hole on its own: one counts once the segment after it arrived PITR_GAP_GRACE ago. The oldest counted
        # hole with no backup after it = an open gap (nothing after it is restorable); a young hole never changes the
        # verdict about an older one. Otherwise the newest counted hole with a backup after it = healed.
        sev=0; h_after=; h_after_t=; h_before=; h_before_t=; h_lab=; h_stop=
        holes=$(printf '%s\n' "$report" | sed -n 's/^HOLE //p')
        old_ifs=$IFS
        IFS='
'
        for h in $holes; do
            IFS=$old_ifs
            # shellcheck disable=SC2086
            set -- $h
            IFS='
'
            [ $((now - $4)) -ge "${PITR_GAP_GRACE:-600}" ] || continue
            if [ "$5" = "-" ]; then
                sev=2; h_after=$1; h_after_t=$2; h_before=$3; h_before_t=$4; h_lab=; h_stop=
                break
            fi
            sev=1; h_after=$1; h_after_t=$2; h_before=$3; h_before_t=$4; h_lab=$5; h_stop=$6
        done
        IFS=$old_ifs
        if [ "$sev" = "2" ]; then
            need_set "$repo" gap
        elif [ "$(need_get "$repo")" = "gap" ]; then
            need_clear "$repo"
            log "PITR: repo$repo: uzilishdan keyin to'liq nusxa bor (yoki uzilish yopildi): yangi to'liq nusxa endi kerak emas"
        fi
        [ "$sev" -gt "$worst" ] || continue
        worst=$sev
        GAP=1; GAP_REPO=$repo; GAP_TLI=$(rv TLI)
        GAP_AFTER=$h_after; GAP_AFTER_TIME=$h_after_t
        GAP_BEFORE=$h_before; GAP_BEFORE_TIME=$h_before_t
        GAP_BACKUP=$h_lab; GAP_FROM=$h_stop; GAP_WINDOWS=$(rv WINDOWS)
        after_h=$(pitr_epoch_utc "$GAP_AFTER_TIME")
        if [ "$sev" = "2" ]; then
            GAP_OPEN=1
            GAP_MSG="Zaxira zanjiri uzilgan (repo$repo): $after_h (UTC) dan keyingi daqiqalarga qaytarib bo'lmaydi (WAL $GAP_AFTER va $GAP_BEFORE orasidagi fayllar yo'q). Yangi to'liq nusxa olinmoqda"
            GAP_NOTE=$GAP_MSG
        else
            GAP_MSG=
            GAP_NOTE="Zaxira zanjirida uzilish bor (repo$repo): $after_h dan $(pitr_epoch_utc "$GAP_FROM") gacha (UTC) bo'lgan daqiqalarga qaytarib bo'lmaydi; $(pitr_epoch_utc "$GAP_FROM") dan keyin tiklash mumkin (to'liq nusxa $GAP_BACKUP)"
        fi
    done
    if [ "$GAP_OPEN" = "1" ] && [ "$(cat "$PITR_STATE_DIR/gap-logged" 2>/dev/null)" != "$GAP_REPO:$GAP_AFTER" ]; then
        log "XATO: $GAP_MSG"
        printf '%s' "$GAP_REPO:$GAP_AFTER" >"$PITR_STATE_DIR/gap-logged"
    fi
    # Every growth of failed_count is logged once (the gap check above is what decides)
    fc=$(pitr_val "SELECT failed_count FROM pg_stat_archiver")
    prev_fc=$(cat "$PITR_STATE_DIR/failed-count" 2>/dev/null || echo "")
    if [ -n "$fc" ] && [ "$fc" != "$prev_fc" ]; then
        [ -z "$prev_fc" ] || log "OGOHLANTIRISH: WAL arxivlash xatolari ko'paydi ($prev_fc -> $fc): zanjir tekshirildi (uzilish: $([ "$GAP" = "1" ] && echo bor || echo "yo'q"))"
        printf '%s' "$fc" >"$PITR_STATE_DIR/failed-count"
    fi
}
rv() { printf '%s\n' "$report" | sed -n "s/^$1=//p"; }

# Archive outage bookkeeping (pg_stat_archiver): an outage longer than PITR_GAP_FULL_AFTER may have dropped WAL
# (spool cap): the main loop then takes a new full backup once archiving works again.
track_outage() {
    failing=$(pitr_val "SELECT coalesce(last_failed_time > coalesce(last_archived_time, '-infinity'::timestamptz), false) FROM pg_stat_archiver")
    if [ "$failing" = "t" ]; then
        [ -f "$PITR_STATE_DIR/outage-since" ] || { date -u +%s >"$PITR_STATE_DIR/outage-since"; log "OGOHLANTIRISH: WAL arxivlash ishlamayapti (pg_stat_archiver): PITR nusxasi eskirmoqda"; }
    elif [ "$failing" = "f" ] && [ -f "$PITR_STATE_DIR/outage-since" ]; then
        since=$(cat "$PITR_STATE_DIR/outage-since")
        lasted=$(($(date -u +%s) - since))
        rm -f "$PITR_STATE_DIR/outage-since"
        log "WAL arxivlash tiklandi ($lasted s ishlamagan)"
        if [ "$lasted" -ge "$PITR_GAP_FULL_AFTER" ]; then
            need_set 1 outage
            [ "$PITR_REPO2" != "on" ] || need_set 2 outage
            log "OGOHLANTIRISH: arxivlash uzoq to'xtagan: PITR zanjirida uzilish bo'lishi mumkin, yangi to'liq zaxira olinadi"
        fi
    fi
}

marker_loop() {
    while :; do
        write_markers || true
        sleep "$PITR_MARKER_INTERVAL"
    done
}

# HH:MM >= HH:MM
hm_reached() { # hm_reached <now HH:MM> <target HH:MM>
    n=$(( $(printf '%s' "${1%%:*}" | sed 's/^0//') * 60 + $(printf '%s' "${1##*:}" | sed 's/^0//') ))
    t=$(( $(printf '%s' "${2%%:*}" | sed 's/^0//') * 60 + $(printf '%s' "${2##*:}" | sed 's/^0//') ))
    [ "$n" -ge "$t" ]
}

# The nightly chain: PITR backup + expire, pg_dump, partition archive
nightly() {
    if [ "$HAVE_PITR_LIB" = "1" ] && pitr_enabled; then pitr_backup auto || true; fi
    run_backup || true
    if [ -f "$SCRIPT_DIR/archive-partitions.sh" ]; then
        sh "$SCRIPT_DIR/archive-partitions.sh" --local || log "OGOHLANTIRISH: jurnallar arxivi xato bilan tugadi (keyingi kecha qayta urinadi)"
    fi
}

daemon() {
    validate
    case "$PITR_DRILL_TIME" in [0-2][0-9]:[0-5][0-9]) ;; *) die "PITR_DRILL_TIME HH:MM ko'rinishida bo'lishi kerak: $PITR_DRILL_TIME" ;; esac
    mkdir -p "$BACKUP_DIR" "$PITR_STATE_DIR"
    marker_pid=
    trap 'log "to'"'"'xtatildi"; [ -z "$marker_pid" ] || kill "$marker_pid" 2>/dev/null; exit 0' TERM INT
    log "demon: har kuni $BACKUP_TIME UTC (PITR zaxirasi, pg_dump, jurnallar arxivi), $BACKUP_RETENTION_DAYS kun saqlanadi, papka $BACKUP_DIR"
    if [ "$HAVE_PITR_LIB" = "1" ]; then pitr_init || log "OGOHLANTIRISH: PITR tayyor emas (yuqoridagi xato); demon ishlashda davom etadi va keyingi kecha qayta urinadi"; fi
    if [ "$BACKUP_RUN_ON_START" = "1" ]; then run_backup || true; fi
    if [ "$HAVE_PITR_LIB" = "1" ]; then
        marker_loop &
        marker_pid=$!
    fi
    today=$(date -u +%F)
    last_nightly=
    last_drill=
    if hm_reached "$(date -u +%H:%M)" "$BACKUP_TIME"; then last_nightly=$today; fi
    if hm_reached "$(date -u +%H:%M)" "$PITR_DRILL_TIME"; then last_drill=$today; fi
    while true; do
        sleep 60 &
        wait $! || true
        today=$(date -u +%F)
        now_hm=$(date -u +%H:%M)
        if [ "$last_nightly" != "$today" ] && hm_reached "$now_hm" "$BACKUP_TIME"; then
            last_nightly=$today
            nightly
        fi
        if [ "$HAVE_PITR_LIB" = "1" ] && pitr_enabled && [ "$PITR_DRILL_MONTHLY" = "1" ] && [ "$last_drill" != "$today" ] \
            && [ "$(date -u +%u)" = "7" ] && [ "$(date -u +%d)" -le 7 ] && hm_reached "$now_hm" "$PITR_DRILL_TIME"; then
            last_drill=$today
            log "oylik tiklash mashqi boshlandi"
            sh "$SCRIPT_DIR/pitr-drill.sh" run || log "OGOHLANTIRISH: oylik tiklash mashqi muvaffaqiyatsiz (natija restore_drills da, Monitoring ogohlantiradi)"
        fi
        pitr_heal || true
    done
}

case "${1:-}" in
    -h | --help)
        sed -n '2,40p' "$0" | sed 's/^# \{0,1\}//'
        ;;
    --daemon)
        daemon
        ;;
    --pitr-backup)
        validate
        mkdir -p "$PITR_STATE_DIR"
        case "${2:-auto}" in full | diff | incr | auto) ;; *) die "foydalanish: backup.sh --pitr-backup [full|diff|auto] [1|2]" ;; esac
        case "${3:-}" in '' | 1 | 2) ;; *) die "ombor 1 yoki 2 bo'lishi kerak: $3" ;; esac
        pitr_enabled || die "PITR o'chiq: ildiz .env da PITR_ENABLED=1, so'ng docker compose up -d --build"
        pitr_backup "${2:-auto}" "${3:-}"
        ;;
    --pitr-init)
        validate
        pitr_enabled || die "PITR o'chiq: ildiz .env da PITR_ENABLED=1, so'ng docker compose up -d --build"
        pitr_init
        ;;
    --pitr-verify)
        # pgbackrest verify of every repository (checksums of the archived WAL and the backups) plus the timeline
        # history files the repository knows; exit 1 when verify reports a problem
        validate
        pitr_require_lib
        pitr_enabled || die "PITR o'chiq"
        pitr_load
        vrc=0
        for repo in 1 2; do
            [ "$repo" = "1" ] || [ "$PITR_REPO2" = "on" ] || continue
            out=$(pgbr verify --repo="$repo" --log-level-console=info 2>&1) || vrc=1
            printf '%s\n' "$out"
            if printf '%s\n' "$out" | grep -qiE 'status: error|invalid|missing'; then vrc=1; fi
            echo "repo$repo timeline tarixi: $(pgbr repo-ls --repo="$repo" --recurse "archive/$PGBACKREST_STANZA" 2>/dev/null | grep -o '[0-9A-F]\{8\}\.history' | sort -u | tr '\n' ' ')"
        done
        exit "$vrc"
        ;;
    --pitr-heal)
        validate
        mkdir -p "$PITR_STATE_DIR"
        write_markers || true
        pitr_heal
        ;;
    --markers)
        mkdir -p "$PITR_STATE_DIR"
        write_markers
        ;;
    --list-remote)
        s3_require
        rclone lsl "$(s3_target)"
        ;;
    --fetch)
        [ -n "${2:-}" ] || die "foydalanish: backup.sh --fetch <fayl.dump>"
        case "$2" in */* | *..*) die "faqat fayl nomi bering (papkasiz): $2" ;; esac
        s3_require
        mkdir -p "$BACKUP_DIR"
        rclone copyto "$(s3_target)/$2" "$BACKUP_DIR/$2"
        rclone copyto "$(s3_target)/$2.sha256" "$BACKUP_DIR/$2.sha256"
        # rclone mavjud bo'lmagan faylda ham 0 kod qaytarishi mumkin: mavjudligini va butunligini o'zimiz tekshiramiz
        if [ ! -s "$BACKUP_DIR/$2" ] || ! (cd "$BACKUP_DIR" && sha256sum -c "$2.sha256" >/dev/null 2>&1); then
            rm -f "$BACKUP_DIR/$2" "$BACKUP_DIR/$2.sha256"
            die "S3 da $2 topilmadi yoki sha256 mos kelmadi (--list-remote bilan nomini tekshiring)"
        fi
        log "yuklab olindi: $BACKUP_DIR/$2 (tiklash: restore.sh $BACKUP_DIR/$2 <baza>)"
        ;;
    '')
        validate
        run_backup
        ;;
    *)
        die "noma'lum argument: $1 (--daemon, --list-remote, --fetch, --pitr-backup, --pitr-init, --markers yoki --help)"
        ;;
esac
