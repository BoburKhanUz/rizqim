#!/bin/sh
# RIZQIM PITR: Postgres archive_command (docker-compose.yml: archive_command='/usr/local/bin/pgbackrest-archive.sh %p').
#
# Installed as pgbackrest-archive.sh: pgBackRest's `check` and `backup` refuse an archive_command that does not
# contain the word "pgbackrest". With PITR_ENABLED unset (every install before M3) it exits 0 at once, so Postgres
# recycles WAL exactly as before and nothing else changes. With PITR_ENABLED=1 it pushes the segment to the
# repositories configured by pitr-env.sh (local volume, and S3 when configured), asynchronously through the spool.
# A non-zero exit makes Postgres keep the segment and retry; the spool cap (PITR_ARCHIVE_QUEUE_MAX) keeps pg_wal from
# filling the disk when the archive target is down.
[ "${PITR_ENABLED:-0}" = "1" ] || exit 0
[ -n "${1:-}" ] || { echo "pgbackrest-archive.sh: WAL yo'li berilmadi (%p)" >&2; exit 1; }

here=$(dirname "$0")
if [ -n "${PITR_ENV_FILE:-}" ]; then
    # shellcheck disable=SC1090
    . "$PITR_ENV_FILE"
elif [ -f "$here/pitr-env.sh" ]; then
    # shellcheck source=infra/postgres/pitr-env.sh
    . "$here/pitr-env.sh"
else
    # shellcheck disable=SC1091
    . /usr/local/bin/pitr-env.sh
fi
pitr_env
PGBACKREST_LOG_LEVEL_CONSOLE=${PITR_ARCHIVE_LOG_LEVEL:-warn}
export PGBACKREST_LOG_LEVEL_CONSOLE
exec pgbackrest archive-push "$1"
