#!/bin/sh
# RIZQIM: tiklash mashqi, BITTA buyruq (serverda, loyiha papkasida): scripts/restore-drill.sh
#
# Nima qiladi: `backup` konteynerida pitr-drill.sh (PITR: ish bazasiga belgi yozadi, uni arxivga yetguncha kutadi,
# zaxiradan shu daqiqaga YANGI papkaga tiklaydi, ikkinchi Postgres'ni 5499 portida ochib tekshiradi), so'ng ilova
# tekshiruvi (`php artisan db:restore-smoke`, bo'lsa) tiklangan nusxaga qarshi, so'ng natija: tiklash vaqti, qaytarilgan
# nuqta, ma'lumot yo'qotish oynasi va tekshiruvlar. Natija restore_drills jadvaliga yoziladi (Monitoring ko'rsatadi).
# Ish bazasi va ilova to'xtatilmaydi, hech narsa ustiga yozilmaydi. Davomiyligi: baza hajmiga qarab daqiqalar.
#
# Foydalanish:
#   scripts/restore-drill.sh                 oddiy mashq (repo1, shu serverdagi zaxira)
#   scripts/restore-drill.sh --repo 2        S3 dagi nusxadan (server yo'qolgan holatni sinash)
#   scripts/restore-drill.sh --target-time "2026-12-01 09:00:00+00" [--expect-token <uuid>]   (CI, scripts/pitr-ci.sh)
#   scripts/restore-drill.sh --local ...     Docker'siz: shu xostdagi klasterlar (scripts/pitr-ci.sh --local)
#   --ci                                     faqat mashina uchun (KEY=VALUE natija stdout'da, har doim ham chiqadi)
#
# Ilova tekshiruvi: PITR_SMOKE_CMD berilsa shu buyruq (DB_HOST/DB_PORT tiklangan nusxaga; 77 kodi = buyruq yo'q) bajariladi; aks holda
# `docker compose run backend php artisan db:restore-smoke` (buyruq ilovada bo'lsa; yo'q bo'lsa "o'tkazib yuborildi").
# Chiqish kodi: 0 = mashq muvaffaqiyatli, 1 = xato (sababi yuqorida).

set -eu

SCRIPT_DIR=$(cd "$(dirname "$0")" && pwd)
ROOT_DIR=$(cd "$SCRIPT_DIR/.." && pwd)

log() { printf '%s restore-drill: %s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$*" >&2; }
die() { log "XATO: $*"; exit 1; }

LOCAL=0
for a in "$@"; do [ "$a" != "--local" ] || LOCAL=1; done
set -- "$@" __end__
while [ "$1" != "__end__" ]; do
    a=$1; shift
    case "$a" in
        --local) ;;
        -h | --help) sed -n '2,20p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
        *) set -- "$@" "$a" ;;
    esac
done
shift

PORT=${PITR_RESTORE_PORT:-5499}
run_out=$(mktemp "${TMPDIR:-/tmp}/rizqim-drill.XXXXXX")
trap 'rm -f "$run_out"' EXIT

if [ "$LOCAL" != "1" ]; then
    command -v docker >/dev/null 2>&1 || die "docker topilmadi (buyruqni serverda, loyiha papkasida bajaring)"
    cd "$ROOT_DIR"
    docker compose ps --status running --services 2>/dev/null | grep -qx backup || die "backup xizmati ishlamayapti: docker compose up -d backup"
fi

# The drill runs inside the backup container (or directly with --local); a few tuning variables are passed through
run_drill() { # run_drill <verb> [args...]
    if [ "$LOCAL" = "1" ]; then
        sh "$SCRIPT_DIR/pitr-drill.sh" "$@"
    else
        verb=$1; shift
        set -- backup sh /usr/local/bin/pitr-drill.sh "$verb" "$@"
        for v in PITR_DRILL_EXTRA_CHECK_SQL PITR_DRILL_EXTRA_CHECK_EXPECT PITR_DRILL_AMCHECK PITR_DRILL_ARCHIVE_TIMEOUT; do
            eval "val=\${$v:-}"
            [ -z "$val" ] || set -- -e "$v=$val" "$@"
        done
        docker compose exec -T "$@"
    fi
}

log "tiklash mashqi boshlandi"
run_drill run --keep "$@" | tee "$run_out" || true
if ! grep -qx 'DRILL_STATUS=running' "$run_out"; then
    # The drill already stopped its server and recorded the failure
    exit 1
fi

# App smoke against the restored copy
smoke=skipped
if [ -n "${PITR_SMOKE_CMD:-}" ]; then
    log "ilova tekshiruvi: PITR_SMOKE_CMD"
    # Exit 77 = the app has no db:restore-smoke yet (skipped)
    src=0
    DB_HOST=${PITR_SMOKE_DB_HOST:-127.0.0.1} DB_PORT=${PITR_SMOKE_DB_PORT:-$PORT} DB_POOLER=direct RUN_MIGRATIONS=false sh -c "$PITR_SMOKE_CMD" || src=$?
    case "$src" in 0) smoke=ok ;; 77) smoke=skipped; log "ilova tekshiruvi o'tkazib yuborildi (db:restore-smoke buyrug'i hali yo'q)" ;; *) smoke=failed ;; esac
elif [ "$LOCAL" = "1" ]; then
    if command -v php >/dev/null 2>&1 && [ -f "$ROOT_DIR/backend/artisan" ] \
        && (cd "$ROOT_DIR/backend" && php artisan help db:restore-smoke >/dev/null 2>&1); then
        log "ilova tekshiruvi: php artisan db:restore-smoke (127.0.0.1:$PORT)"
        if (cd "$ROOT_DIR/backend" && DB_HOST=127.0.0.1 DB_PORT=$PORT DB_POOLER=direct php artisan db:restore-smoke); then smoke=ok; else smoke=failed; fi
    else
        log "ilova tekshiruvi o'tkazib yuborildi (db:restore-smoke buyrug'i hali yo'q)"
    fi
elif docker compose exec -T backend php artisan help db:restore-smoke >/dev/null 2>&1; then
    log "ilova tekshiruvi: docker compose run backend php artisan db:restore-smoke (backup:$PORT)"
    if docker compose run --rm --no-deps -e DB_HOST=backup -e DB_PORT="$PORT" -e DB_POOLER=direct -e RUN_MIGRATIONS=false \
        backend php artisan db:restore-smoke; then smoke=ok; else smoke=failed; fi
else
    log "ilova tekshiruvi o'tkazib yuborildi (backend ishlamayapti yoki db:restore-smoke buyrug'i hali yo'q)"
fi

run_drill finish --smoke "$smoke"
