#!/bin/sh
# RIZQIM: arxivlangan oyni o'qish uchun BITTA buyruq (serverda, loyiha papkasida):
#   scripts/archive-restore.sh <jadval> <YYYY-MM>          masalan: scripts/archive-restore.sh audit_logs 2026-03
#
# Arxiv faylini oladi (shu serverda bo'lmasa S3 dan), sha256 ni katalog (partition_archives) bilan solishtiradi va
# bo'lakni shu Postgres serveridagi alohida `rizqim_archive` bazasiga tiklaydi (baza bo'lmasa yaratiladi). Ish
# bazasiga TEGMAYDI. Oxirida qatorlar soni va tayyor psql buyrug'i chiqadi. Bo'lak o'z nomini saqlaydi (audit_logs_2026_03).
#
#   --attach [--yes]   FAQAT MUTAXASSIS UCHUN: bo'lakni ISH bazasiga qaytarib, asosiy jadvalga qayta ulaydi va katalog
#                      qatorini `held` qiladi (arxiv jarayoni uni boshqa o'chirmaydi). Tasdiq uchun bo'lak nomini yozish
#                      so'raladi. Tashqi kalitlar (FK) qayta qo'shilmaydi.
# Sozlamalar: ARCHIVE_RESTORE_DB (standart rizqim_archive), BACKUP_DIR, BACKUP_S3_* (backup.sh bilan bir xil).

set -eu
umask 077

PITR_SCRIPT_DIR=$(cd "$(dirname "$0")" && pwd)
PITR_LOG_TAG=archive-restore
# shellcheck source=scripts/pitr.sh
. "$PITR_SCRIPT_DIR/pitr.sh"

usage() { sed -n '2,13p' "$0" | sed 's/^# \{0,1\}//'; }

if [ "${RIZQIM_BACKUP_CONTAINER:-0}" != "1" ] && [ "${1:-}" != "--local" ] && command -v docker >/dev/null 2>&1; then
    cd "$PITR_SCRIPT_DIR/.."
    if [ -t 0 ]; then exec docker compose exec backup sh /usr/local/bin/archive-restore.sh "$@"; fi
    exec docker compose exec -T backup sh /usr/local/bin/archive-restore.sh "$@"
fi
[ "${1:-}" != "--local" ] || shift

table=
month=
attach=0
yes=0
while [ $# -gt 0 ]; do
    case "$1" in
        --attach) attach=1; shift ;;
        --yes) yes=1; shift ;;
        -h | --help) usage; exit 0 ;;
        -*) pitr_die "noma'lum flag: $1" ;;
        *) if [ -z "$table" ]; then table=$1; elif [ -z "$month" ]; then month=$1; else pitr_die "ortiqcha argument: $1"; fi; shift ;;
    esac
done
if [ -z "$table" ] || [ -z "$month" ]; then usage; exit 1; fi
case "$table" in *[!a-z0-9_]*) pitr_die "jadval nomi noto'g'ri: $table" ;; esac
case "$month" in [12][0-9][0-9][0-9]-[01][0-9]) ;; *) pitr_die "oy YYYY-MM ko'rinishida bo'lsin: $month" ;; esac

PGTZ=UTC; export PGTZ
pitr_resolve_conn
BACKUP_DIR=${BACKUP_DIR:-/backups}
ADB=${ARCHIVE_RESTORE_DB:-rizqim_archive}
case "$ADB" in *[!a-z0-9_]*) pitr_die "ARCHIVE_RESTORE_DB noto'g'ri: $ADB" ;; esac
[ "$ADB" != "$PGDATABASE" ] || pitr_die "ARCHIVE_RESTORE_DB ish bazasi bilan bir xil bo'lmasin"

row=$(psql -X -A -t -q -F '|' -v ON_ERROR_STOP=1 -c "SELECT id, partition_name, status, coalesce(sha256, ''), coalesce(storage_uri, ''), range_from::date, range_to::date FROM partition_archives WHERE table_name = '$table' AND to_char(range_from, 'YYYY-MM') = '$month' ORDER BY id DESC LIMIT 1" 2>/dev/null) \
    || pitr_die "partition_archives jadvalini o'qib bo'lmadi (M3 migratsiyasi bajarilganmi?)"
[ -n "$row" ] || pitr_die "$table $month katalogda yo'q (arxivlanmagan yoki hali ish bazasida: php artisan db:partitions:status)"
IFS='|' read -r id part status sha uri rfrom rto <<EOF
$row
EOF
case "$part" in *[!a-z0-9_]*) pitr_die "katalogdagi bo'lak nomi noto'g'ri: $part" ;; esac
case "$status" in uploaded | verified | dropped | held) ;; *) pitr_die "$part holati '$status': arxiv fayli hali tayyor emas (archive-partitions.sh)" ;; esac

dir="$BACKUP_DIR/archive/$table"
file="$dir/$month.dump"
mkdir -p "$dir"
if [ ! -s "$file" ]; then
    pitr_s3_configured || pitr_die "$file shu serverda yo'q va S3 sozlanmagan (BACKUP_S3_*): fayl $uri"
    command -v rclone >/dev/null 2>&1 || pitr_die "rclone topilmadi"
    pitr_rclone_env
    pitr_log "S3 dan olinmoqda: $uri"
    rclone copyto --retries 3 "$(pitr_s3_target)/archive/$table/$month.dump" "$file.partial" || pitr_die "S3 dan yuklab bo'lmadi: $uri"
    mv "$file.partial" "$file"
fi
got=$(sha256sum "$file" | cut -d' ' -f1)
if [ -n "$sha" ] && [ "$got" != "$sha" ]; then
    pitr_die "sha256 katalogdagiga mos emas: $file buzilgan yoki boshqa fayl (katalog $sha, fayl $got)"
fi
pg_restore --list "$file" >/dev/null 2>&1 || pitr_die "$file o'qilmaydi (pg_restore --list)"
pitr_log "fayl tekshirildi: $file (sha256 to'g'ri)"

if [ "$attach" = "1" ]; then
    [ "$(pitr_val "SELECT to_regclass('public.$part') IS NULL")" = "t" ] || pitr_die "$part ish bazasida allaqachon bor"
    if [ "$yes" != "1" ]; then
        printf 'DIQQAT: %s ish bazasiga qaytariladi va %s ga ulanadi. Tasdiqlash uchun bo'"'"'lak nomini yozing: ' "$part" "$table" >&2
        read -r answer || answer=
        [ "$answer" = "$part" ] || pitr_die "tasdiqlanmadi, hech narsa o'zgarmadi"
    fi
    pg_restore --no-owner --no-privileges --exit-on-error -d "$PGDATABASE" "$file" || pitr_die "ish bazasiga tiklab bo'lmadi"
    if ! pitr_psql -c "SET lock_timeout = '3s'; ALTER TABLE public.$table ATTACH PARTITION public.$part FOR VALUES FROM ('$rfrom') TO ('$rto')"; then
        pitr_die "ATTACH bajarilmadi (qulf band yoki DEFAULT bo'lakda shu oy qatorlari bor). $part ish bazasida alohida jadval bo'lib qoldi"
    fi
    pitr_psql -c "UPDATE partition_archives SET status = 'held', error = NULL WHERE id = $id" >/dev/null || pitr_log "OGOHLANTIRISH: katalog qatorini held qilib bo'lmadi"
    pitr_log "TAYYOR: $part ish bazasiga qaytarildi va $table ga ulandi (katalog: held)"
    exit 0
fi

if [ "$(pitr_val "SELECT 1 FROM pg_database WHERE datname = '$ADB'")" != "1" ]; then
    pitr_log "$ADB bazasi yaratilmoqda"
    pitr_psql -c "CREATE DATABASE $ADB" >/dev/null
fi
if [ "$(PGDATABASE=$ADB pitr_val "SELECT to_regclass('public.$part') IS NOT NULL")" = "t" ]; then
    pitr_log "$part $ADB da allaqachon tiklangan"
else
    # A detached partition keeps its parent's column default (nextval('public.<table>_id_seq')): the side database has
    # no such sequence, so create the ones the dump names (empty, only to satisfy the default)
    for seq in $(pg_restore -f - "$file" 2>/dev/null | sed -n "s/.*nextval('public\.\([a-z0-9_]*\)'.*/\1/p" | sort -u); do
        PGDATABASE=$ADB pitr_psql -c "CREATE SEQUENCE IF NOT EXISTS public.$seq" >/dev/null || pitr_die "$ADB da $seq yaratib bo'lmadi"
    done
    PGDATABASE=$ADB pg_restore --no-owner --no-privileges --exit-on-error -d "$ADB" "$file" || pitr_die "$ADB ga tiklab bo'lmadi"
fi
count=$(PGDATABASE=$ADB pitr_val "SELECT count(*) FROM public.$part")
pitr_log "TAYYOR: $part ($rfrom .. $rto), $count qator, baza $ADB"
echo "O'qish: docker compose exec backup psql -d $ADB -c 'SELECT * FROM $part ORDER BY id DESC LIMIT 20'"
