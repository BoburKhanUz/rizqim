# `postgres` service image (docker-compose.yml): the official postgres:17-alpine + pgBackRest for continuous WAL
# archiving (PITR). The server, its data directory, users and entrypoint are the official image's, unchanged: an
# existing postgres_data volume starts as before. With PITR_ENABLED unset the archive command exits 0 (nothing is
# archived). infra/backup/Dockerfile installs the same packages on the same base, so pgBackRest is the same version in
# both containers (CI checks it). docs/OPERATIONS.md, "Uzluksiz zaxira (PITR)".
FROM postgres:17-alpine

# Alpine's pgbackrest package depends on the virtual "postgresql" package. Without a pin apk would pick the highest
# provider (postgresql18); naming postgresql17 keeps it on the server's major version. Those Alpine binaries land in
# /usr/libexec/postgresql17 (+ /usr/bin links); the official ones in /usr/local/bin come first in PATH and stay the
# ones that run (checked below). pgBackRest itself only uses libpq and reads the data files.
# hadolint ignore=DL3018
RUN apk add --no-cache pgbackrest postgresql17 \
    && pgbackrest version \
    && test "$(command -v postgres)" = /usr/local/bin/postgres \
    && postgres --version | grep -q '(PostgreSQL) 17' \
    && install -d -o postgres -g postgres -m 0750 /var/lib/pgbackrest /var/spool/pgbackrest \
    && install -d -o postgres -g postgres -m 0700 /tmp/pgbackrest-log

COPY pitr-env.sh /usr/local/bin/pitr-env.sh
COPY archive.sh /usr/local/bin/pgbackrest-archive.sh
RUN chmod 0644 /usr/local/bin/pitr-env.sh && chmod 0755 /usr/local/bin/pgbackrest-archive.sh \
    && PITR_ENABLED=0 /usr/local/bin/pgbackrest-archive.sh /nonexistent
