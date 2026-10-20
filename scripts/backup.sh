#!/bin/sh
# Rizqim: PostgreSQL zaxira nusxasi (pg_dump custom format, zlib bilan siqilgan), vaqt belgili fayl, eskilarini o'chirish.
#
# Foydalanish:
#   scripts/backup.sh            bir marta zaxira oladi (deploy oldidan qo'lda: docker compose exec backup sh /usr/local/bin/backup.sh)
#   scripts/backup.sh --daemon   har kuni BACKUP_TIME (UTC) da zaxira oladi; docker-compose.yml dagi `backup` xizmati shuni ishlatadi
#   scripts/backup.sh --list-remote          S3 dagi zaxiralar ro'yxati (BACKUP_S3_* kerak)
#   scripts/backup.sh --fetch <fayl.dump>    S3 dan dump va .sha256 ni BACKUP_DIR ga yuklab oladi (tiklashdan oldin; restore.sh tekshiradi)
#
# Sozlamalar (env; docker compose'da ildizdagi .env dan):
#   BACKUP_DIR             papka (standart: <loyiha>/backups; `backup` konteynerida /backups)
#   BACKUP_RETENTION_DAYS  shu kundan eski zaxiralar o'chiriladi (standart 14)
#   BACKUP_TIME            --daemon uchun HH:MM, UTC (standart 03:00)
#   BACKUP_RUN_ON_START    --daemon ishga tushganda darhol bir marta (standart 1)
#   BACKUP_OFFSITE_CMD     ixtiyoriy: zaxiradan keyin `sh -c` bilan bajariladi, fayl yo'li $BACKUP_FILE da.
#                          Masalan: rclone copy "$BACKUP_FILE" remote:rizqim-backups
#                          Xato bersa zaxira baribir saqlanadi, skript 2 kod bilan tugaydi.
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
BACKUP_TIME=${BACKUP_TIME:-03:00}
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
        if ! rclone delete --min-age "${BACKUP_S3_RETENTION_DAYS}d" --include 'rizqim-*.dump' --include 'rizqim-*.dump.sha256' "$target"; then
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

run_backup() {
    mkdir -p "$BACKUP_DIR"
    name="rizqim-${PGDATABASE}-$(date -u +%Y%m%dT%H%M%SZ).dump"
    final="$BACKUP_DIR/$name"
    partial="$final.partial"

    log "$PGDATABASE ($PGHOST:$PGPORT) -> $final"
    # --no-owner/--no-privileges: boshqa foydalanuvchi nomli bazaga ham tiklash mumkin bo'lsin
    if ! pg_dump --format=custom --compress=6 --no-owner --no-privileges --file="$partial"; then
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
    if [ -n "$BACKUP_OFFSITE_CMD" ]; then
        log "tashqi nusxa: BACKUP_OFFSITE_CMD bajarilmoqda"
        if ! BACKUP_FILE="$final" sh -c "$BACKUP_OFFSITE_CMD"; then
            log "XATO: BACKUP_OFFSITE_CMD xato bilan tugadi (mahalliy zaxira saqlandi)"
            status=2
        fi
    fi

    if ! s3_upload "$name"; then
        status=2
    fi

    # Eskilarini o'chirish; yangi zaxira allaqachon yozilgan, shuning uchun bo'sh qolib ketmaydi
    find "$BACKUP_DIR" -maxdepth 1 -type f \( -name 'rizqim-*.dump' -o -name 'rizqim-*.dump.sha256' -o -name 'rizqim-*.dump.partial' \) \
        -mtime "+$BACKUP_RETENTION_DAYS" -exec rm -f {} + 2>/dev/null || true

    return "$status"
}

# Sekundlar: keyingi BACKUP_TIME (UTC) gacha
seconds_until_next() {
    strip() { v=${1#0}; printf '%s' "${v:-0}"; }
    h=$(strip "${BACKUP_TIME%%:*}"); m=$(strip "${BACKUP_TIME##*:}")
    now=$(date -u +%H:%M:%S)
    nh=$(strip "${now%%:*}"); rest=${now#*:}; nm=$(strip "${rest%%:*}"); ns=$(strip "${rest##*:}")
    target=$((h * 3600 + m * 60))
    current=$((nh * 3600 + nm * 60 + ns))
    wait_for=$((target - current))
    [ "$wait_for" -gt 0 ] || wait_for=$((wait_for + 86400))
    printf '%s' "$wait_for"
}

case "${1:-}" in
    -h | --help)
        sed -n '2,28p' "$0" | sed 's/^# \{0,1\}//'
        ;;
    --daemon)
        validate
        trap 'log "to'"'"'xtatildi"; exit 0' TERM INT
        log "demon: har kuni $BACKUP_TIME UTC, $BACKUP_RETENTION_DAYS kun saqlanadi, papka $BACKUP_DIR"
        if [ "$BACKUP_RUN_ON_START" = "1" ]; then run_backup || true; fi
        while true; do
            delay=$(seconds_until_next)
            log "keyingi zaxira $delay soniyadan keyin"
            sleep "$delay" &
            wait $! || true
            run_backup || true
        done
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
        die "noma'lum argument: $1 (--daemon, --list-remote, --fetch yoki --help)"
        ;;
esac
