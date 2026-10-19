#!/bin/sh
# Rizqim: PostgreSQL zaxira nusxasi (pg_dump custom format, zlib bilan siqilgan), vaqt belgili fayl, eskilarini o'chirish.
#
# Foydalanish:
#   scripts/backup.sh            bir marta zaxira oladi (cron, deploy oldidan qo'lda)
#   scripts/backup.sh --daemon   har kuni BACKUP_TIME (UTC) da zaxira oladi; docker-compose.yml dagi `backup` xizmati shuni ishlatadi
#
# Sozlamalar (env; docker compose'da ildizdagi .env dan):
#   BACKUP_DIR             papka (standart: <loyiha>/backups; `backup` konteynerida /backups)
#   BACKUP_RETENTION_DAYS  shu kundan eski zaxiralar o'chiriladi (standart 14)
#   BACKUP_TIME            --daemon uchun HH:MM, UTC (standart 03:00)
#   BACKUP_RUN_ON_START    --daemon ishga tushganda darhol bir marta (standart 1)
#   BACKUP_OFFSITE_CMD     ixtiyoriy: zaxiradan keyin `sh -c` bilan bajariladi, fayl yo'li $BACKUP_FILE da.
#                          Masalan: rclone copy "$BACKUP_FILE" remote:rizqim-backups
#                          Xato bersa zaxira baribir saqlanadi, skript 2 kod bilan tugaydi.
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

validate() {
    [ -n "$PGUSER" ] || die "ma'lumotlar bazasi foydalanuvchisi yo'q (PGUSER / DB_USERNAME / backend/.env)"
    [ -n "$PGDATABASE" ] || die "ma'lumotlar bazasi nomi yo'q (PGDATABASE / DB_DATABASE / backend/.env)"
    case "$BACKUP_RETENTION_DAYS" in '' | *[!0-9]*) die "BACKUP_RETENTION_DAYS butun son bo'lishi kerak: $BACKUP_RETENTION_DAYS" ;; esac
    [ "$BACKUP_RETENTION_DAYS" -ge 1 ] || die "BACKUP_RETENTION_DAYS kamida 1 bo'lishi kerak"
    case "$BACKUP_TIME" in [0-2][0-9]:[0-5][0-9]) ;; *) die "BACKUP_TIME HH:MM ko'rinishida bo'lishi kerak: $BACKUP_TIME" ;; esac
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
        sed -n '2,20p' "$0" | sed 's/^# \{0,1\}//'
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
    '')
        validate
        run_backup
        ;;
    *)
        die "noma'lum argument: $1 (--daemon yoki --help)"
        ;;
esac
