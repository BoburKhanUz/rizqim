#!/bin/sh
# RIZQIM: jurnallar arxivi (M3). Ajratilgan (detached) oylik bo'laklarni (audit_logs_2026_03 kabi) faylga oladi va
# server tashqarisiga nusxalaydi. `backup` demoni har kecha pg_dump'dan keyin o'zi ishga tushiradi; qo'lda:
#   scripts/archive-partitions.sh          (serverda, loyiha papkasida: backup konteynerida bajariladi)
#
# Har partition_archives qatori uchun (status detached, uploaded yoki failed; jadvalni `php artisan db:partitions:archive`
# to'ldiradi): pg_dump -Fc --no-owner -t public.<bo'lak> -> /backups/archive/<jadval>/<YYYY-MM>.dump, sha256, pg_restore
# --list bilan tekshiruv; S3 (BACKUP_S3_*) bo'lsa <prefix>/archive/<jadval>/ ga yuklaydi (--immutable: bor faylni
# hech qachon almashtirmaydi), QAYTA YUKLAB OLIB sha256 va pg_restore --list ni tekshiradi, so'ng `verified`. Faqat
# `verified` qatorni bazadan o'chirish mumkin (buni keyingi db:partitions:archive bajaradi, 3 kundan keyin).
# S3 bo'lmasa: fayl faqat shu serverda, status `uploaded`; bunday bo'lak faqat backend/.env da ARCHIVE_ALLOW_LOCAL_ONLY=1
# bo'lsa o'chiriladi (db:partitions:archive; tavsiya etilmaydi: server yo'qolsa arxiv ham yo'qoladi).
# Fiskal va audit arxiv fayllari hech qachon o'chirilmaydi (bu skript o'chirmaydi; bucket'da lifecycle qo'ymang).

set -eu
umask 077

PITR_SCRIPT_DIR=$(cd "$(dirname "$0")" && pwd)
PITR_LOG_TAG=archive
# shellcheck source=scripts/pitr.sh
. "$PITR_SCRIPT_DIR/pitr.sh"

if [ "${RIZQIM_BACKUP_CONTAINER:-0}" != "1" ] && [ "${1:-}" != "--local" ] && command -v docker >/dev/null 2>&1; then
    cd "$PITR_SCRIPT_DIR/.."
    exec docker compose exec -T backup sh /usr/local/bin/archive-partitions.sh
fi

PGTZ=UTC; export PGTZ
pitr_resolve_conn
BACKUP_DIR=${BACKUP_DIR:-/backups}
ARCHIVE_DIR=$BACKUP_DIR/archive

if [ "$(pitr_val "SELECT to_regclass('public.partition_archives') IS NOT NULL")" != "t" ]; then
    pitr_log "partition_archives jadvali yo'q (M3 migratsiyasi bajarilmagan): arxivlanadigan narsa yo'q"
    exit 0
fi

has_updated_at=$(pitr_val "SELECT count(*) FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'partition_archives' AND column_name = 'updated_at'")
set_row() { # set_row <id> <sql assignments>
    extra=
    [ "$has_updated_at" != "1" ] || extra=", updated_at = now() AT TIME ZONE 'UTC'"
    pitr_psql -c "UPDATE partition_archives SET $2$extra WHERE id = $1" >/dev/null
}
mark_failed() { # mark_failed <id> <message>
    pitr_log "XATO: $2"
    set_row "$1" "status = 'failed', error = $(pitr_sqlq "$2")" || true
    failures=$((failures + 1))
}

s3=0
if pitr_s3_configured; then
    command -v rclone >/dev/null 2>&1 || pitr_die "rclone topilmadi (backup obrazida bor: infra/backup/Dockerfile)"
    pitr_rclone_env
    s3=1
fi

# uploaded rows are retried only when S3 is now configured (a local-only copy waiting for an off-site one)
statuses="'detached', 'failed'"
[ "$s3" = "0" ] || statuses="'detached', 'failed', 'uploaded'"
rows=$(psql -X -A -t -q -F '|' -v ON_ERROR_STOP=1 -c "SELECT id, table_name, partition_name, to_char(range_from, 'YYYY-MM') FROM partition_archives WHERE status IN ($statuses) ORDER BY range_from, id")
[ -n "$rows" ] || { pitr_log "arxivlanadigan bo'lak yo'q"; exit 0; }

failures=0
done_count=0
tab='
'
old_ifs=$IFS
IFS=$tab
for row in $rows; do
    IFS=$old_ifs
    id=${row%%|*}; rest=${row#*|}
    table=${rest%%|*}; rest=${rest#*|}
    part=${rest%%|*}; month=${rest#*|}
    case "$part" in
        [a-z]*_[0-9][0-9][0-9][0-9]_[0-9][0-9]) ;;
        *) mark_failed "$id" "bo'lak nomi kutilgan ko'rinishda emas: $part"; continue ;;
    esac
    case "$part" in *[!a-z0-9_]*) mark_failed "$id" "bo'lak nomida ruxsat etilmagan belgi: $part"; continue ;; esac
    case "$table" in '' | *[!a-z0-9_]*) mark_failed "$id" "jadval nomi noto'g'ri: $table"; continue ;; esac
    mkdir -p "$ARCHIVE_DIR/$table"
    file="$ARCHIVE_DIR/$table/$month.dump"
    name="$month.dump"
    if [ -s "$file" ] && [ -s "$file.sha256" ]; then
        pitr_log "$part: mahalliy fayl bor ($file), qayta olinmaydi"
    else
        if [ "$(pitr_val "SELECT to_regclass('public.$part') IS NOT NULL")" != "t" ]; then
            mark_failed "$id" "$part jadvali bazada yo'q va mahalliy arxiv fayli ham yo'q: qo'lda tekshiring"
            continue
        fi
        pitr_log "$part -> $file"
        if ! pg_dump --format=custom --compress=6 --no-owner --no-privileges -t "public.$part" --file="$file.partial"; then
            rm -f "$file.partial"
            mark_failed "$id" "pg_dump $part bajarilmadi"
            continue
        fi
        if ! pg_restore --list "$file.partial" >/dev/null 2>&1; then
            rm -f "$file.partial"
            mark_failed "$id" "$part arxiv fayli tekshiruvdan o'tmadi (pg_restore --list)"
            continue
        fi
        mv "$file.partial" "$file"
        (cd "$ARCHIVE_DIR/$table" && sha256sum "$name" >"$name.sha256")
    fi
    if ! (cd "$ARCHIVE_DIR/$table" && sha256sum -c "$name.sha256" >/dev/null 2>&1); then
        mark_failed "$id" "$file sha256 mos kelmadi (fayl buzilgan)"
        continue
    fi
    sha=$(cut -d' ' -f1 "$file.sha256")
    bytes=$(wc -c <"$file" | tr -d ' ')

    if [ "$s3" = "1" ]; then
        remote="$(pitr_s3_target)/archive/$table"
        prefix=$(printf '%s' "${BACKUP_S3_PREFIX:-}" | sed -e 's#^/*##' -e 's#/*$##')
        uri="s3://$BACKUP_S3_BUCKET/${prefix:+$prefix/}archive/$table/$name"
        # --immutable: an existing object with other content is an error, never overwritten
        if ! rclone copyto --immutable --retries 3 --low-level-retries 5 "$file" "$remote/$name" \
            || ! rclone copyto --immutable --retries 3 --low-level-retries 5 "$file.sha256" "$remote/$name.sha256"; then
            mark_failed "$id" "$part S3 ga yuklanmadi (yoki S3 da boshqa mazmunli shu nomli fayl bor): $remote/$name"
            continue
        fi
        set_row "$id" "status = 'uploaded', bytes = $bytes, sha256 = $(pitr_sqlq "$sha"), storage_uri = $(pitr_sqlq "$uri"), uploaded_at = now() AT TIME ZONE 'UTC', error = NULL"
        check=$(mktemp -d "${TMPDIR:-/tmp}/rizqim-archive-check.XXXXXX")
        ok=0
        if rclone copyto --retries 3 "$remote/$name" "$check/$name" \
            && [ "$(sha256sum "$check/$name" | cut -d' ' -f1)" = "$sha" ] \
            && pg_restore --list "$check/$name" >/dev/null 2>&1; then
            ok=1
        fi
        rm -rf "$check"
        if [ "$ok" != "1" ]; then
            mark_failed "$id" "$part S3 dan qayta yuklab olingan nusxa tekshiruvdan o'tmadi (sha256 / pg_restore --list)"
            continue
        fi
        set_row "$id" "status = 'verified', verified_at = now() AT TIME ZONE 'UTC', error = NULL"
        pitr_log "$part: S3 da tekshirildi (verified), $bytes bayt"
    else
        set_row "$id" "status = 'uploaded', bytes = $bytes, sha256 = $(pitr_sqlq "$sha"), storage_uri = $(pitr_sqlq "file://$file"), uploaded_at = now() AT TIME ZONE 'UTC', error = NULL"
        pitr_log "$part: faqat shu serverda saqlandi (S3 yo'q, status uploaded): bazadan O'CHIRILMAYDI (backend/.env da ARCHIVE_ALLOW_LOCAL_ONLY=1 bo'lmasa). S3 ni sozlang (BACKUP_S3_*)"
    fi
    done_count=$((done_count + 1))
    IFS=$tab
done
IFS=$old_ifs

pitr_log "tayyor: $done_count ta bo'lak, $failures ta xato"
[ "$failures" -eq 0 ]
