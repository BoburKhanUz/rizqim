#!/bin/sh
# RIZQIM: guard in front of the official postgres entrypoint (docker-entrypoint.sh).
#
# With POSTGRES_DATA_VOLUME set (a restored PITR copy made live by scripts/pitr-switch.sh) the data directory is a host
# path. If that path is missing or mistyped, Docker creates it empty and the official entrypoint would initdb a NEW,
# EMPTY database that the backend then migrates and serves as if nothing happened. Refuse instead.
data=${PGDATA:-/var/lib/postgresql/data}
if [ -n "${RIZQIM_DATA_SOURCE:-}" ] && [ ! -s "$data/PG_VERSION" ]; then
    echo "rizqim-postgres: XATO: POSTGRES_DATA_VOLUME=$RIZQIM_DATA_SOURCE da Postgres ma'lumoti yo'q (papka bo'sh, yo'q yoki yo'l noto'g'ri). Bo'sh baza YARATILMAYDI. Ildiz .env dagi POSTGRES_DATA_VOLUME ni tekshiring yoki oldingi bazaga qayting: scripts/pitr-switch.sh --back" >&2
    exit 78
fi
exec docker-entrypoint.sh "$@"
