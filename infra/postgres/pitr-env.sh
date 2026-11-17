# shellcheck shell=sh
# shellcheck disable=SC2034 # PITR_REPO2 / PITR_REPO2_NOTE are read by the sourcing scripts
# RIZQIM PITR: pgBackRest configuration from the environment (sourced, never executed).
#
# The same file is baked into the `postgres` image (archive_command, infra/postgres/archive.sh) and the `backup`
# image (backups, restores, the drill: scripts/pitr*.sh), so both sides always build the same configuration. It turns
# the operator's PITR_* keys (root .env, docker-compose.yml) into PGBACKREST_* variables. Nothing is written to disk:
# the S3 secret and the cipher pass live only in the process environment.
#
# Keys (defaults in brackets):
#   PITR_ENABLED            1 = PITR on. Anything else: archive_command exits 0 and nothing is archived [unset]
#   PITR_STANZA             [rizqim]
#   PITR_PGDATA             data directory, identical in both containers [$PGDATA or /var/lib/postgresql/data]
#   PITR_SOCKET_DIR         unix socket directory shared by both containers [/var/run/postgresql]
#   PITR_PG_PORT            [5432]
#   PITR_PG_USER/_DATABASE  superuser and database pgBackRest connects to [POSTGRES_USER/PGUSER, POSTGRES_DB/PGDATABASE]
#   PITR_REPO1_PATH         local repository (volume pgbackrest_repo) [/var/lib/pgbackrest]
#   PITR_REPO1_RETENTION_FULL  full backups kept locally [2]
#   PITR_SPOOL_PATH         async archive spool (postgres container) [/var/spool/pgbackrest]
#   PITR_LOCK_PATH          lock directory, local to each container [/tmp/pgbackrest]. Not shared on purpose: the
#                           archive-push of the postgres container and a backup/expire in the sidecar take different
#                           lock types anyway, and a shared archive lock would make stanza-create or a restore's
#                           archive-get fail while the async push runs
#   PITR_LOG_PATH           [/tmp/pgbackrest-log]; file logging is off unless PITR_LOG_LEVEL_FILE is set
#   PITR_ARCHIVE_QUEUE_MAX  WAL kept while the archive target is down; above it WAL is DROPPED (PITR gap) [4GiB]
#   PITR_ARCHIVE_CHECK_TIMEOUT  seconds a backup/check waits for its WAL to be archived [180]
#   PITR_PROCESS_MAX        [2]
#   S3 (repo2, optional; each key falls back to the BACKUP_S3_* key of the nightly dump):
#   PITR_S3_ENDPOINT (https://host[:port]), PITR_S3_BUCKET, PITR_S3_ACCESS_KEY, PITR_S3_SECRET, PITR_S3_REGION
#   [us-east-1], PITR_S3_PREFIX (folder in the bucket; "/pitr" is appended), PITR_S3_URI_STYLE [path],
#   PITR_S3_VERIFY_TLS [y], PITR_S3_RETENTION_FULL [4]
#   PITR_CIPHER_PASS        AES-256 pass of repo2: REQUIRED for repo2 (without it repo2 stays off). Lost pass =
#                           S3 copies unreadable: keep it outside the server.
#
# After pitr_env: PITR_REPO2 is one of on, off (no S3 configured), no_cipher, bad_endpoint, partial; PITR_REPO2_NOTE
# holds the Uzbek explanation for the operator whenever it is not "on" (also "off": a local-only copy is a standing risk).

pitr_env() {
    PGBACKREST_STANZA=${PITR_STANZA:-rizqim}
    PGBACKREST_PG1_PATH=${PITR_PGDATA:-${PGDATA:-/var/lib/postgresql/data}}
    PGBACKREST_PG1_SOCKET_PATH=${PITR_SOCKET_DIR:-/var/run/postgresql}
    PGBACKREST_PG1_PORT=${PITR_PG_PORT:-5432}
    PGBACKREST_PG1_USER=${PITR_PG_USER:-${POSTGRES_USER:-${PGUSER:-postgres}}}
    PGBACKREST_PG1_DATABASE=${PITR_PG_DATABASE:-${POSTGRES_DB:-${PGDATABASE:-postgres}}}
    PGBACKREST_REPO1_PATH=${PITR_REPO1_PATH:-/var/lib/pgbackrest}
    PGBACKREST_REPO1_RETENTION_FULL=${PITR_REPO1_RETENTION_FULL:-2}
    PGBACKREST_SPOOL_PATH=${PITR_SPOOL_PATH:-/var/spool/pgbackrest}
    PGBACKREST_LOCK_PATH=${PITR_LOCK_PATH:-/tmp/pgbackrest}
    PGBACKREST_LOG_PATH=${PITR_LOG_PATH:-/tmp/pgbackrest-log}
    PGBACKREST_LOG_LEVEL_FILE=${PITR_LOG_LEVEL_FILE:-off}
    PGBACKREST_LOG_LEVEL_CONSOLE=${PITR_LOG_LEVEL_CONSOLE:-info}
    PGBACKREST_LOG_LEVEL_STDERR=${PITR_LOG_LEVEL_STDERR:-off}
    PGBACKREST_ARCHIVE_ASYNC=${PITR_ARCHIVE_ASYNC:-y}
    PGBACKREST_ARCHIVE_PUSH_QUEUE_MAX=${PITR_ARCHIVE_QUEUE_MAX:-4GiB}
    PGBACKREST_ARCHIVE_TIMEOUT=${PITR_ARCHIVE_CHECK_TIMEOUT:-180}
    PGBACKREST_COMPRESS_TYPE=${PITR_COMPRESS_TYPE:-zst}
    PGBACKREST_PROCESS_MAX=${PITR_PROCESS_MAX:-2}
    PGBACKREST_START_FAST=y
    PGBACKREST_EXPIRE_AUTO=${PITR_EXPIRE_AUTO:-y}
    export PGBACKREST_STANZA PGBACKREST_PG1_PATH PGBACKREST_PG1_SOCKET_PATH PGBACKREST_PG1_PORT PGBACKREST_PG1_USER \
        PGBACKREST_PG1_DATABASE PGBACKREST_REPO1_PATH PGBACKREST_REPO1_RETENTION_FULL PGBACKREST_SPOOL_PATH \
        PGBACKREST_LOCK_PATH PGBACKREST_LOG_PATH PGBACKREST_LOG_LEVEL_FILE PGBACKREST_LOG_LEVEL_CONSOLE \
        PGBACKREST_LOG_LEVEL_STDERR PGBACKREST_ARCHIVE_ASYNC PGBACKREST_ARCHIVE_PUSH_QUEUE_MAX PGBACKREST_ARCHIVE_TIMEOUT \
        PGBACKREST_COMPRESS_TYPE PGBACKREST_PROCESS_MAX PGBACKREST_START_FAST PGBACKREST_EXPIRE_AUTO

    # repo2 (S3) is configured only when complete; a half-filled set never reaches pgBackRest
    unset PGBACKREST_REPO2_TYPE PGBACKREST_REPO2_PATH PGBACKREST_REPO2_S3_ENDPOINT PGBACKREST_REPO2_STORAGE_PORT \
        PGBACKREST_REPO2_S3_BUCKET PGBACKREST_REPO2_S3_KEY PGBACKREST_REPO2_S3_KEY_SECRET PGBACKREST_REPO2_S3_REGION \
        PGBACKREST_REPO2_S3_URI_STYLE PGBACKREST_REPO2_STORAGE_VERIFY_TLS PGBACKREST_REPO2_RETENTION_FULL \
        PGBACKREST_REPO2_CIPHER_TYPE PGBACKREST_REPO2_CIPHER_PASS
    PITR_REPO2=off
    PITR_REPO2_NOTE="S3 sozlanmagan: uzluksiz zaxira faqat shu serverda (disk yoki server yo'qolsa nusxalar ham yo'qoladi). PITR_S3_* yoki BACKUP_S3_* ni to'ldiring"
    _pe_endpoint=${PITR_S3_ENDPOINT:-${BACKUP_S3_ENDPOINT:-}}
    _pe_bucket=${PITR_S3_BUCKET:-${BACKUP_S3_BUCKET:-}}
    _pe_key=${PITR_S3_ACCESS_KEY:-${BACKUP_S3_ACCESS_KEY:-}}
    _pe_secret=${PITR_S3_SECRET:-${BACKUP_S3_SECRET:-}}
    if [ -n "${PITR_TEST_REPO2_PATH:-}" ]; then
        # scripts/pitr-ci.sh --local only: a second, posix repository stands in for S3 (two-repository logic without S3)
        PGBACKREST_REPO2_TYPE=posix
        PGBACKREST_REPO2_PATH=$PITR_TEST_REPO2_PATH
        PGBACKREST_REPO2_RETENTION_FULL=${PITR_S3_RETENTION_FULL:-4}
        export PGBACKREST_REPO2_TYPE PGBACKREST_REPO2_PATH PGBACKREST_REPO2_RETENTION_FULL
        PITR_REPO2=on
        PITR_REPO2_NOTE=
    elif [ -n "$_pe_endpoint$_pe_bucket$_pe_key$_pe_secret" ]; then
        if [ -z "$_pe_endpoint" ] || [ -z "$_pe_bucket" ] || [ -z "$_pe_key" ] || [ -z "$_pe_secret" ]; then
            PITR_REPO2=partial
            PITR_REPO2_NOTE="S3 sozlamasi to'liq emas (endpoint, bucket, kalit, maxfiy kalit): PITR faqat shu serverda"
        elif [ -z "${PITR_CIPHER_PASS:-}" ]; then
            PITR_REPO2=no_cipher
            PITR_REPO2_NOTE="PITR_CIPHER_PASS berilmagan: S3 ga uzluksiz zaxira yozilmaydi (faqat shu serverda). Parol yarating: openssl rand -base64 48"
        else
            case "$_pe_endpoint" in
                http://*)
                    PITR_REPO2=bad_endpoint
                    PITR_REPO2_NOTE="pgBackRest S3 ga faqat https orqali yozadi: $_pe_endpoint"
                    ;;
                *)
                    _pe_hostport=${_pe_endpoint#https://}
                    _pe_hostport=${_pe_hostport%%/*}
                    case "$_pe_hostport" in
                        *:*) _pe_host=${_pe_hostport%%:*}; _pe_port=${_pe_hostport##*:} ;;
                        *) _pe_host=$_pe_hostport; _pe_port=443 ;;
                    esac
                    _pe_prefix=$(printf '%s' "${PITR_S3_PREFIX:-${BACKUP_S3_PREFIX:-}}" | sed -e 's#^/*##' -e 's#/*$##')
                    PGBACKREST_REPO2_TYPE=s3
                    PGBACKREST_REPO2_PATH=/${_pe_prefix:+$_pe_prefix/}pitr
                    PGBACKREST_REPO2_S3_ENDPOINT=$_pe_host
                    PGBACKREST_REPO2_STORAGE_PORT=$_pe_port
                    PGBACKREST_REPO2_S3_BUCKET=$_pe_bucket
                    PGBACKREST_REPO2_S3_KEY=$_pe_key
                    PGBACKREST_REPO2_S3_KEY_SECRET=$_pe_secret
                    PGBACKREST_REPO2_S3_REGION=${PITR_S3_REGION:-${BACKUP_S3_REGION:-us-east-1}}
                    PGBACKREST_REPO2_S3_URI_STYLE=${PITR_S3_URI_STYLE:-path}
                    PGBACKREST_REPO2_STORAGE_VERIFY_TLS=${PITR_S3_VERIFY_TLS:-y}
                    PGBACKREST_REPO2_RETENTION_FULL=${PITR_S3_RETENTION_FULL:-4}
                    PGBACKREST_REPO2_CIPHER_TYPE=aes-256-cbc
                    PGBACKREST_REPO2_CIPHER_PASS=$PITR_CIPHER_PASS
                    export PGBACKREST_REPO2_TYPE PGBACKREST_REPO2_PATH PGBACKREST_REPO2_S3_ENDPOINT \
                        PGBACKREST_REPO2_STORAGE_PORT PGBACKREST_REPO2_S3_BUCKET PGBACKREST_REPO2_S3_KEY \
                        PGBACKREST_REPO2_S3_KEY_SECRET PGBACKREST_REPO2_S3_REGION PGBACKREST_REPO2_S3_URI_STYLE \
                        PGBACKREST_REPO2_STORAGE_VERIFY_TLS PGBACKREST_REPO2_RETENTION_FULL PGBACKREST_REPO2_CIPHER_TYPE \
                        PGBACKREST_REPO2_CIPHER_PASS
                    PITR_REPO2=on
                    ;;
            esac
        fi
    fi
    unset _pe_endpoint _pe_bucket _pe_key _pe_secret _pe_hostport _pe_host _pe_port _pe_prefix
}
