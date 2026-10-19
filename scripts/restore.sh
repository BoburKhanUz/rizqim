#!/bin/sh
# Rizqim: backup.sh olgan zaxiradan PostgreSQL bazasini tiklash (pg_restore).
#
# Foydalanish:
#   scripts/restore.sh [--yes] [--allow-live] <zaxira.dump> <maqsad-baza>
#
# Maqsad baza yo'q bo'lsa yaratiladi (tasdiq so'raladi). Bor bo'lsa uning jadvallari ustiga yoziladi
# (pg_restore --clean --if-exists --single-transaction: xato bo'lsa hammasi bekor qilinadi), buning uchun
# bazaning nomini aynan yozib tasdiqlash kerak.
#
# Ish bazasini (PGDATABASE / DB_DATABASE) ustiga yozish faqat --allow-live bilan va ilovani to'xtatib turib:
#   docker compose stop backend queue queue-reports scheduler
# Xavfsiz tekshiruv: avval boshqa nomli bazaga tiklang (masalan rizqimbot_restore) va ma'lumotni solishtiring.
#
# --yes: savol bermaydi (avtomatlashtirish uchun). Ulanish sozlamalari: scripts/backup.sh dagi kabi (PG*, DB_*, backend/.env).

set -eu

SCRIPT_DIR=$(cd "$(dirname "$0")" && pwd)
ROOT_DIR=$(cd "$SCRIPT_DIR/.." && pwd)

log() { printf '%s\n' "$*" >&2; }
die() { log "XATO: $*"; exit 1; }

dotenv_get() {
    [ -f "$ROOT_DIR/backend/.env" ] || return 0
    sed -n "s/^$1=//p" "$ROOT_DIR/backend/.env" | tail -n 1 | sed -e 's/^"\(.*\)"$/\1/' -e "s/^'\(.*\)'\$/\1/"
}

resolve() {
    eval "pg=\${$1:-}"
    eval "db=\${$2:-}"
    if [ -n "$pg" ]; then printf '%s' "$pg"; elif [ -n "$db" ]; then printf '%s' "$db"; else
        env_value=$(dotenv_get "$2")
        printf '%s' "${env_value:-${3:-}}"
    fi
}

ASSUME_YES=0
ALLOW_LIVE=0
while [ $# -gt 0 ]; do
    case "$1" in
        --yes) ASSUME_YES=1; shift ;;
        --allow-live) ALLOW_LIVE=1; shift ;;
        -h | --help) sed -n '2,17p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
        --) shift; break ;;
        -*) die "noma'lum flag: $1" ;;
        *) break ;;
    esac
done
[ $# -eq 2 ] || die "foydalanish: restore.sh [--yes] [--allow-live] <zaxira.dump> <maqsad-baza>"

DUMP=$1
TARGET=$2

PGHOST=$(resolve PGHOST DB_HOST 127.0.0.1); export PGHOST
PGPORT=$(resolve PGPORT DB_PORT 5432); export PGPORT
PGUSER=$(resolve PGUSER DB_USERNAME); export PGUSER
PGPASSWORD=$(resolve PGPASSWORD DB_PASSWORD); export PGPASSWORD
LIVE_DB=$(resolve PGDATABASE DB_DATABASE)

[ -n "$PGUSER" ] || die "ma'lumotlar bazasi foydalanuvchisi yo'q (PGUSER / DB_USERNAME / backend/.env)"
for tool in psql pg_restore createdb; do
    command -v "$tool" >/dev/null 2>&1 || die "$tool topilmadi (postgresql-client kerak)"
done
[ -f "$DUMP" ] || die "zaxira fayli topilmadi: $DUMP"
case "$TARGET" in '' | *[!A-Za-z0-9_]*) die "baza nomida faqat harf, raqam va _ bo'lishi mumkin: $TARGET" ;; esac

# Fayl butunligi: sha256 yonida bo'lsa tekshiriladi, va pg_restore uni o'qiy olishi kerak
if [ -f "$DUMP.sha256" ]; then
    (cd "$(dirname "$DUMP")" && sha256sum -c "$(basename "$DUMP").sha256" >/dev/null 2>&1) \
        || die "sha256 mos kelmadi: $DUMP buzilgan yoki o'zgartirilgan"
    log "sha256 to'g'ri."
else
    log "OGOHLANTIRISH: $DUMP.sha256 yo'q, faqat arxiv tuzilmasi tekshiriladi."
fi
pg_restore --list "$DUMP" >/dev/null 2>&1 || die "$DUMP o'qiladigan pg_dump arxivi emas"

confirm() { # confirm <savol> <kutilgan javob>
    [ "$ASSUME_YES" = 1 ] && return 0
    [ -t 0 ] || die "interaktiv terminal yo'q: --yes bering yoki 'docker compose exec' ga -it qo'shing"
    printf '%s ' "$1" >&2
    read -r answer
    [ "$answer" = "$2" ] || die "tasdiqlanmadi, hech narsa o'zgarmadi"
}

exists=$(psql -d postgres -Atq -c "SELECT 1 FROM pg_database WHERE datname = '$TARGET'")

if [ "$TARGET" = "$LIVE_DB" ]; then
    [ "$ALLOW_LIVE" = 1 ] || die "$TARGET ish bazasi. Avval boshqa nomli bazaga tiklang yoki ilovani to'xtatib --allow-live bering."
    log "DIQQAT: ISH BAZASI '$TARGET' ustiga yoziladi. Backend, queue va scheduler to'xtatilganini tekshiring."
fi

if [ "$exists" = 1 ]; then
    tables=$(psql -d "$TARGET" -Atq -c "SELECT count(*) FROM information_schema.tables WHERE table_schema = 'public'")
    log "'$TARGET' bazasi bor ($tables ta jadval). Tiklash uning jadvallarini zaxiradagilar bilan almashtiradi."
    confirm "Tasdiqlash uchun baza nomini yozing ($TARGET):" "$TARGET"
else
    confirm "'$TARGET' bazasi yo'q. Yaratib, tiklaymizmi? (yes):" "yes"
    createdb "$TARGET"
    log "'$TARGET' yaratildi."
fi

log "Tiklanmoqda: $DUMP -> $TARGET"
if pg_restore --clean --if-exists --no-owner --no-privileges --single-transaction --dbname="$TARGET" "$DUMP"; then
    psql -d "$TARGET" -Atq -c "ANALYZE" >/dev/null 2>&1 || true
    tables=$(psql -d "$TARGET" -Atq -c "SELECT count(*) FROM information_schema.tables WHERE table_schema = 'public'")
    log "Tayyor: '$TARGET' bazasida $tables ta jadval."
    log "Keyingi qadam (agar zaxira joriy koddan eski bo'lsa): php artisan migrate --force && php artisan queue:restart"
else
    log "XATO: pg_restore tugamadi; --single-transaction tufayli '$TARGET' o'zgarmagan."
    exit 1
fi
