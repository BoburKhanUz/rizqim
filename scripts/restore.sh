#!/bin/sh
# Rizqim: backup.sh olgan zaxiradan PostgreSQL bazasini tiklash (pg_restore).
#
# Foydalanish:
#   scripts/restore.sh [--yes] [--allow-live] <zaxira.dump> <maqsad-baza>
#
# Maqsad baza yo'q bo'lsa yaratiladi (tasdiq so'raladi). Bor bo'lsa butunlay almashtiriladi (bazaning nomini aynan
# yozib tasdiqlash kerak): zaxira avval yangi vaqtinchalik bazaga tiklanadi (--single-transaction), faqat muvaffaqiyatli
# bo'lsa eski baza o'chirilib, yangisi uning nomini oladi. Xato bo'lsa maqsad baza o'zgarmaydi. (pg_restore --clean
# faqat dump'dagi obyektlarni o'chiradi: yangi migratsiyalar qo'shgan jadvallar qolib, keyingi `migrate` "already
# exists" bilan yiqilardi.) Bazaga kimdir ulangan bo'lsa almashtirish rad etiladi.
#
# Ish bazasini (PGDATABASE / DB_DATABASE) almashtirish faqat --allow-live bilan va ilovani to'xtatib turib:
#   docker compose stop backend queue queue-reports scheduler reverb frontend
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
        -h | --help) sed -n '2,19p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
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
# Vaqtinchalik nomlar (<baza>_restoring_<pid>, <baza>_replaced_<pid>) PostgreSQL'ning 63 belgilik chegarasiga sig'sin
[ ${#TARGET} -le 40 ] || die "baza nomi 40 belgidan uzun: $TARGET"

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

sql() { psql -d postgres -v ON_ERROR_STOP=1 -Atq -c "$1"; }

exists=$(sql "SELECT 1 FROM pg_database WHERE datname = '$TARGET'")

if [ "$TARGET" = "$LIVE_DB" ]; then
    [ "$ALLOW_LIVE" = 1 ] || die "$TARGET ish bazasi. Avval boshqa nomli bazaga tiklang yoki ilovani to'xtatib --allow-live bering."
    log "DIQQAT: ISH BAZASI '$TARGET' ustiga yoziladi. Backend, queue va scheduler to'xtatilganini tekshiring."
fi

if [ "$exists" = 1 ]; then
    tables=$(psql -d "$TARGET" -Atq -c "SELECT count(*) FROM information_schema.tables WHERE table_schema = 'public'")
    log "'$TARGET' bazasi bor ($tables ta jadval). Tiklash uni butunlay zaxiradagi baza bilan almashtiradi."
    confirm "Tasdiqlash uchun baza nomini yozing ($TARGET):" "$TARGET"
    # Ulangan jarayon almashtirishga to'sqinlik qiladi: uzoq tiklashdan oldin aytiladi
    busy=$(sql "SELECT count(*) FROM pg_stat_activity WHERE datname = '$TARGET' AND pid <> pg_backend_pid()")
    [ "$busy" = 0 ] || die "'$TARGET' ga $busy ta ulanish bor: backend, queue, scheduler (va psql) ni to'xtating; hech narsa o'zgarmadi."
    # Bo'sh yangi bazaga tiklanadi va faqat muvaffaqiyatli bo'lsa almashtiriladi
    WORK_DB="${TARGET}_restoring_$$"
else
    confirm "'$TARGET' bazasi yo'q. Yaratib, tiklaymizmi? (yes):" "yes"
    WORK_DB=$TARGET
fi

createdb "$WORK_DB"
log "Tiklanmoqda: $DUMP -> $WORK_DB"
if ! pg_restore --no-owner --no-privileges --single-transaction --dbname="$WORK_DB" "$DUMP"; then
    sql "DROP DATABASE IF EXISTS \"$WORK_DB\"" >/dev/null 2>&1 || log "OGOHLANTIRISH: '$WORK_DB' bazasini qo'lda o'chiring."
    log "XATO: pg_restore tugamadi; '$TARGET' o'zgarmagan."
    exit 1
fi
psql -d "$WORK_DB" -Atq -c "ANALYZE" >/dev/null 2>&1 || true

if [ "$WORK_DB" != "$TARGET" ]; then
    OLD_DB="${TARGET}_replaced_$$"
    # Ikkala nom bitta tranzaksiyada almashadi. Bazaga kimdir ulangan bo'lsa RENAME rad etiladi va hech narsa o'zgarmaydi
    if ! sql "BEGIN; ALTER DATABASE \"$TARGET\" RENAME TO \"$OLD_DB\"; ALTER DATABASE \"$WORK_DB\" RENAME TO \"$TARGET\"; COMMIT;" >/dev/null; then
        sql "DROP DATABASE IF EXISTS \"$WORK_DB\"" >/dev/null 2>&1 || log "OGOHLANTIRISH: '$WORK_DB' bazasini qo'lda o'chiring."
        die "'$TARGET' almashtirilmadi (unga ulangan jarayonlar bormi? backend, queue, scheduler, psql ni to'xtating); '$TARGET' o'zgarmagan."
    fi
    sql "DROP DATABASE \"$OLD_DB\"" >/dev/null || log "OGOHLANTIRISH: eski baza '$OLD_DB' nomi bilan qoldi, keraksiz bo'lsa o'chiring."
fi

tables=$(psql -d "$TARGET" -Atq -c "SELECT count(*) FROM information_schema.tables WHERE table_schema = 'public'")
log "Tayyor: '$TARGET' bazasida $tables ta jadval."
log "Keyingi qadam (agar zaxira joriy koddan eski bo'lsa): php artisan migrate --force && php artisan queue:restart"
