#!/bin/sh
# Rizqim: PostgreSQL zaxira nusxasi (pg_dump custom format, zlib bilan siqilgan), vaqt belgili fayl, eskilarini o'chirish.
#
# Foydalanish:
#   scripts/backup.sh            bir marta zaxira oladi (deploy oldidan qo'lda: docker compose exec backup sh /usr/local/bin/backup.sh)
#   scripts/backup.sh --daemon   har kuni BACKUP_TIME (UTC) da zaxira oladi; docker-compose.yml dagi `backup` xizmati shuni ishlatadi
#   scripts/backup.sh --list-remote          S3 dagi zaxiralar ro'yxati (BACKUP_S3_* kerak)
#   scripts/backup.sh --fetch <fayl.dump>    S3 dan dump va .sha256 ni BACKUP_DIR ga yuklab oladi (tiklashdan oldin; restore.sh tekshiradi)
#   scripts/backup.sh --pitr-backup [full|diff]   PITR (pgBackRest) zaxirasini hozir oladi (repo1, S3 sozlangan bo'lsa repo2 ham) + expire
#   scripts/backup.sh --pitr-init                 stanza-create + check (+ to'liq zaxira yo'q bo'lsa oladi); demon buni o'zi qiladi
#   scripts/backup.sh --markers                   monitoring_markers dagi `pitr` va `disk` belgilarini hozir yozadi
#
# M3 demon (--daemon) tartibi: ishga tushganda (PITR_ENABLED=1 bo'lsa) stanza-create + check, to'liq zaxira yo'q bo'lsa
# darhol oladi; har kuni BACKUP_TIME (UTC) da ketma-ket: PITR zaxirasi (yakshanba to'liq, boshqa kunlar farq) va expire,
# so'ng pg_dump (--load-via-partition-root), so'ng jurnallar arxivi (archive-partitions.sh); har PITR_MARKER_INTERVAL
# (300 s) da `pitr` va `disk` belgilari; har oy birinchi yakshanba PITR_DRILL_TIME (23:00 UTC) da tiklash mashqi
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
    if ! pg_dump --format=custom --compress=6 --no-owner --no-privileges --load-via-partition-root --file="$partial"; then
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

# One backup run: repo1, then repo2 (S3) if configured, then expire. $1: full | diff | auto (Sunday or no full yet or
# after an archive outage = full, else diff). pgBackRest itself turns a diff without a prior full into a full.
pitr_backup() {
    pitr_require_lib
    pitr_enabled || { log "PITR o'chiq (PITR_ENABLED=1 emas): pgBackRest zaxirasi olinmadi"; return 0; }
    pitr_load
    type=$1
    if [ "$type" = "auto" ]; then
        type="diff"
        if [ "$(date -u +%u)" = "7" ] || [ -f "$PITR_STATE_DIR/full-needed" ] || ! pitr_has_full 1; then type=full; fi
    fi
    rc=0
    log "PITR: $type zaxira, repo1 (shu server)"
    pgbr backup --repo=1 --type="$type" || { log "XATO: PITR zaxirasi (repo1) bajarilmadi"; rc=1; }
    if [ "$PITR_REPO2" = "on" ]; then
        type2=$type
        pitr_has_full 2 || type2=full
        log "PITR: $type2 zaxira, repo2 (S3)"
        pgbr backup --repo=2 --type="$type2" || { log "XATO: PITR zaxirasi (repo2, S3) bajarilmadi"; rc=1; }
    elif [ -n "$PITR_REPO2_NOTE" ]; then
        log "OGOHLANTIRISH: $PITR_REPO2_NOTE"
    fi
    if [ "${PITR_SKIP_EXPIRE:-0}" != "1" ]; then
        pgbr expire || { log "XATO: pgbackrest expire bajarilmadi"; rc=1; }
    fi
    if [ "$rc" -eq 0 ]; then
        rm -f "$PITR_STATE_DIR/full-needed"
        log "PITR: zaxira tayyor"
        pitr_ping
    fi
    write_markers || true
    return "$rc"
}

# After scripts/pitr-switch.sh --back: the database is again on the older timeline, but the repository still holds
# backups taken on the abandoned (switched-to) timeline. pgBackRest's expire assumes timelines only grow: with such a
# backup as the oldest retained one it deletes the WAL the newer backup on the old timeline needs. So the backups whose
# WAL starts on the abandoned timeline are expired explicitly (expire --set), then the normal expire runs.
pitr_forget_timeline() { # pitr_forget_timeline <timeline id, decimal>
    pitr_require_lib
    pitr_load
    tli_hex=$(printf '%08X' "$1")
    for repo in 1 2; do
        [ "$repo" = "1" ] || [ "$PITR_REPO2" = "on" ] || continue
        info=$(pgbr info --repo="$repo" --output=json 2>/dev/null) || info='[]'
        labels=$(psql -X -A -t -q -v ON_ERROR_STOP=1 -v "info=$info" -v "tli=$tli_hex" <<'SQL'
SELECT coalesce(string_agg(e ->> 'label', ' ' ORDER BY e ->> 'label' DESC), '')
FROM jsonb_array_elements(coalesce((:'info')::jsonb -> 0 -> 'backup', '[]'::jsonb)) e
WHERE substr(e -> 'archive' ->> 'start', 1, 8) = :'tli'
SQL
)
        for label in $labels; do
            log "PITR: repo$repo dagi tashlab ketilgan timeline $1 nusxasi o'chirilmoqda: $label"
            pgbr expire --repo="$repo" --set="$label" || log "XATO: expire --set=$label (repo$repo) bajarilmadi"
        done
    done
    pgbr expire
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
        iso="'YYYY-MM-DD\"T\"HH24:MI:SS\"Z\"'"
        pitr_marker pitr "WITH s AS (SELECT (:'info')::jsonb -> 0 AS s),
              b AS (SELECT e FROM s, jsonb_array_elements(coalesce(s.s -> 'backup', '[]'::jsonb)) e),
              a AS (SELECT * FROM pg_stat_archiver)
            SELECT json_build_object(
              'enabled', true,
              'stanza', :'stanza',
              'status', CASE WHEN (SELECT (s -> 'status' ->> 'code')::int FROM s) = 0 THEN 'ok' ELSE 'error' END,
              'message', coalesce((SELECT s -> 'status' ->> 'message' FROM s), nullif(:'lasterr', '')),
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
            -v "lasterr=$lasterr" -v "outage=$outage" || true
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
            : >"$PITR_STATE_DIR/full-needed"
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
        if [ "$HAVE_PITR_LIB" = "1" ] && pitr_enabled && [ -f "$PITR_STATE_DIR/full-needed" ] && [ ! -f "$PITR_STATE_DIR/outage-since" ]; then
            pitr_load
            if pgbr check >/dev/null 2>&1; then pitr_backup full || true; fi
        fi
    done
}

case "${1:-}" in
    -h | --help)
        sed -n '2,39p' "$0" | sed 's/^# \{0,1\}//'
        ;;
    --daemon)
        daemon
        ;;
    --pitr-backup)
        validate
        mkdir -p "$PITR_STATE_DIR"
        case "${2:-auto}" in full | diff | incr | auto) ;; *) die "foydalanish: backup.sh --pitr-backup [full|diff|auto]" ;; esac
        pitr_enabled || die "PITR o'chiq: ildiz .env da PITR_ENABLED=1, so'ng docker compose up -d --build"
        pitr_backup "${2:-auto}"
        ;;
    --pitr-init)
        validate
        pitr_enabled || die "PITR o'chiq: ildiz .env da PITR_ENABLED=1, so'ng docker compose up -d --build"
        pitr_init
        ;;
    --pitr-forget-timeline)
        validate
        case "${2:-}" in '' | *[!0-9]*) die "foydalanish: backup.sh --pitr-forget-timeline <timeline raqami>" ;; esac
        pitr_forget_timeline "$2"
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
