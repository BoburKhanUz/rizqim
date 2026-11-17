#!/bin/sh
# RIZQIM: katta jurnal jadvalini oylik bo'laklarga ONLAYN ko'chirish (M3), bitta buyruq. Faqat bazasida ko'p ma'lumot
# bor o'rnatishda kerak (docs/OPERATIONS.md, 20-bo'lim, «Pilot paytida»); kechasi, texnik yordamchi bilan.
#
#   scripts/partitions-convert.sh <jadval>              ko'chirish (ilova ishlab turadi; to'xtatilsa qayta bajarilganda davom etadi)
#   scripts/partitions-convert.sh <jadval> --finish     almashtirish (yozuvlar bir necha soniya kutadi)
#   scripts/partitions-convert.sh <jadval> --rollback   7 kun ichida eski jadvalga qaytish
#   scripts/partitions-convert.sh <jadval> --drop-old   7 kundan keyin eski jadvalni o'chirish (qatorlar mos kelsa)
#   <jadval>: audit_logs, fiscal_receipts yoki notification_logs
#
# Ichida: docker compose run --rm --no-deps -e RUN_MIGRATIONS=false -e DB_POOLER=direct backend php artisan
# db:partitions:convert <jadval> [...] (ishlab turgan backend'ga tegmaydi, bazaga to'g'ridan-to'g'ri ulanadi).

set -eu
die() { printf 'partitions-convert: XATO: %s\n' "$*" >&2; exit 1; }

[ $# -ge 1 ] || { sed -n '2,12p' "$0" | sed 's/^# \{0,1\}//'; exit 1; }
table=$1; shift
case "$table" in
    audit_logs | fiscal_receipts | notification_logs) ;;
    -h | --help) sed -n '2,12p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
    *) die "jadval audit_logs, fiscal_receipts yoki notification_logs bo'lishi kerak: $table" ;;
esac
for a in "$@"; do
    case "$a" in --finish | --rollback | --drop-old | --batch=*) ;; *) die "noma'lum argument: $a (--finish, --rollback, --drop-old)" ;; esac
done
command -v docker >/dev/null 2>&1 || die "docker topilmadi (buyruqni serverda, loyiha papkasida bajaring)"
cd "$(dirname "$0")/.."
exec docker compose run --rm --no-deps -e RUN_MIGRATIONS=false -e DB_POOLER=direct backend php artisan db:partitions:convert "$table" "$@"
