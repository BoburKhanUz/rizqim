#!/bin/sh
# PHP-FPM pool sizes and the market limits of nginx from the environment, with safe defaults (wave M1 workload
# isolation; docs/DEPLOY.md, docs/OPERATIONS.md "PHP-FPM pullari"). Run by supervisord (supervisord.conf):
#   pools.sh php-fpm         defaults exported, then php-fpm in the foreground (php-fpm.conf reads the ${FPM_*} values)
#   pools.sh nginx           rizqim-pools-*.conf rendered into /etc/nginx, then nginx in the foreground
#   pools.sh render <dir>    only render the three nginx includes into <dir> (the image build, scripts/scale-test)
#   pools.sh env             print the effective values as `export NAME=value` lines (scripts/scale-test)
#   pools.sh check           render into /etc/nginx, then `nginx -t` and `php-fpm -t` (entrypoint.sh, before supervisord)
#   pools.sh wait-for NAME HOST PORT   wait until HOST:PORT accepts connections, at most STARTUP_WAIT_SECONDS (entrypoint.sh)
# A value that is not a whole number in range, or a combination that defeats the isolation (one client able to fill
# the market or analytics pool, more php-fpm workers than PgBouncer server connections, TLS to a pooler that has none), stops the
# container (entrypoint.sh runs `check` before supervisord): a typo must not start a pool of 0 workers.
set -eu

: "${FPM_CRITICAL_CHILDREN:=4}"      # tills, KDS, display, staff board, PIN sign-in, payment webhooks, health
: "${FPM_MARKET_CHILDREN:=4}"        # Mini App customer reads
: "${FPM_DEFAULT_CHILDREN:=4}"       # owner/partner panel, admin, customer writes (checkout), the rest
: "${FPM_ANALYTICS_CHILDREN:=2}"     # Mini App analytics batches (POST /api/v1/analytics/events): may be lost under load
: "${FPM_MAX_REQUESTS:=500}"         # a worker is replaced after this many requests (bounds slow memory growth)
: "${FPM_MARKET_BACKLOG:=32}"        # listen queue of the market pool
: "${FPM_MARKET_PRIORITY:=10}"       # nice of the market workers: the tills win the CPU when it is short
: "${FPM_DEFAULT_PRIORITY:=5}"       # nice of the default workers (heavy owner reports)
: "${NGINX_MARKET_RATE:=5}"          # market requests per second per client (address + token id)...
: "${NGINX_MARKET_BURST:=20}"        # ...plus this many at once (a Mini App screen loads several); above: 429
: "${NGINX_MARKET_IP_RATE:=50}"      # market requests per second per client address (many customers behind one NAT)...
: "${NGINX_MARKET_IP_BURST:=100}"    # ...plus this many at once; above: 429
: "${FPM_ANALYTICS_BACKLOG:=8}"       # listen queue of the analytics pool
: "${FPM_ANALYTICS_PRIORITY:=15}"     # nice of the analytics workers: below the market's (they can wait)
: "${NGINX_ANALYTICS_RATE:=30}"       # analytics batches per MINUTE per client (address + token id; Laravel: 20/min a user)...
: "${NGINX_ANALYTICS_BURST:=10}"      # ...plus this many at once; above: 429
: "${NGINX_ANALYTICS_IP_RATE:=600}"   # analytics batches per MINUTE per address (carrier NAT; Laravel: 600/min)...
: "${NGINX_ANALYTICS_IP_BURST:=100}"  # ...plus this many at once; above: 429
: "${NGINX_RETRY_AFTER:=2}"          # Retry-After (seconds) of those 503/429 answers
: "${STARTUP_WAIT_SECONDS:=120}"     # how long the entrypoint waits for Postgres / PgBouncer before it gives up

check() { # name value min max
    case "$2" in
        ''|*[!0-9]*) echo "pools.sh: $1=$2 butun son emas" >&2; exit 1 ;;
    esac
    if [ "$2" -lt "$3" ] || [ "$2" -gt "$4" ]; then
        echo "pools.sh: $1=$2 ruxsat etilgan oraliqdan tashqarida ($3..$4)" >&2; exit 1
    fi
}
fail() { echo "pools.sh: $*" >&2; exit 1; }
check FPM_CRITICAL_CHILDREN "$FPM_CRITICAL_CHILDREN" 1 256
check FPM_MARKET_CHILDREN "$FPM_MARKET_CHILDREN" 1 256
# Market requests in PHP at once in this container (the market workers plus three times as many waiting in their
# listen queue); above: 503 at once. One client (address + token) may hold a quarter of it, one address half
: "${NGINX_MARKET_MAX_INFLIGHT:=$((FPM_MARKET_CHILDREN * 4))}"
check NGINX_MARKET_MAX_INFLIGHT "$NGINX_MARKET_MAX_INFLIGHT" 2 65535
: "${NGINX_MARKET_CONN_PER_CLIENT:=$(( NGINX_MARKET_MAX_INFLIGHT / 4 > 0 ? NGINX_MARKET_MAX_INFLIGHT / 4 : 1 ))}"
: "${NGINX_MARKET_CONN_PER_IP:=$(( NGINX_MARKET_MAX_INFLIGHT / 2 > 0 ? NGINX_MARKET_MAX_INFLIGHT / 2 : 1 ))}"
check FPM_DEFAULT_CHILDREN "$FPM_DEFAULT_CHILDREN" 1 256
check FPM_ANALYTICS_CHILDREN "$FPM_ANALYTICS_CHILDREN" 1 64
# Analytics batches in PHP at once (its workers plus three times as many waiting); above: 503 at once (the batch is
# lost or retried by the app, never queued behind anything else). One client a quarter of it, one address half
: "${NGINX_ANALYTICS_MAX_INFLIGHT:=$((FPM_ANALYTICS_CHILDREN * 4))}"
check NGINX_ANALYTICS_MAX_INFLIGHT "$NGINX_ANALYTICS_MAX_INFLIGHT" 2 65535
: "${NGINX_ANALYTICS_CONN_PER_CLIENT:=$(( NGINX_ANALYTICS_MAX_INFLIGHT / 4 > 0 ? NGINX_ANALYTICS_MAX_INFLIGHT / 4 : 1 ))}"
: "${NGINX_ANALYTICS_CONN_PER_IP:=$(( NGINX_ANALYTICS_MAX_INFLIGHT / 2 > 0 ? NGINX_ANALYTICS_MAX_INFLIGHT / 2 : 1 ))}"
check FPM_MAX_REQUESTS "$FPM_MAX_REQUESTS" 0 1000000
check FPM_MARKET_BACKLOG "$FPM_MARKET_BACKLOG" 1 65535
check FPM_MARKET_PRIORITY "$FPM_MARKET_PRIORITY" 0 19
check FPM_DEFAULT_PRIORITY "$FPM_DEFAULT_PRIORITY" 0 19
check NGINX_MARKET_RATE "$NGINX_MARKET_RATE" 1 100000
# nginx refuses burst=0
check NGINX_MARKET_BURST "$NGINX_MARKET_BURST" 1 100000
check NGINX_MARKET_IP_RATE "$NGINX_MARKET_IP_RATE" 1 100000
check NGINX_MARKET_IP_BURST "$NGINX_MARKET_IP_BURST" 1 100000
check NGINX_MARKET_CONN_PER_CLIENT "$NGINX_MARKET_CONN_PER_CLIENT" 1 65535
check NGINX_MARKET_CONN_PER_IP "$NGINX_MARKET_CONN_PER_IP" 1 65535
check FPM_ANALYTICS_BACKLOG "$FPM_ANALYTICS_BACKLOG" 1 65535
check FPM_ANALYTICS_PRIORITY "$FPM_ANALYTICS_PRIORITY" 0 19
check NGINX_ANALYTICS_RATE "$NGINX_ANALYTICS_RATE" 1 100000
check NGINX_ANALYTICS_BURST "$NGINX_ANALYTICS_BURST" 1 100000
check NGINX_ANALYTICS_IP_RATE "$NGINX_ANALYTICS_IP_RATE" 1 1000000
check NGINX_ANALYTICS_IP_BURST "$NGINX_ANALYTICS_IP_BURST" 1 100000
check NGINX_ANALYTICS_CONN_PER_CLIENT "$NGINX_ANALYTICS_CONN_PER_CLIENT" 1 65535
check NGINX_ANALYTICS_CONN_PER_IP "$NGINX_ANALYTICS_CONN_PER_IP" 1 65535
check NGINX_RETRY_AFTER "$NGINX_RETRY_AFTER" 1 3600
check STARTUP_WAIT_SECONDS "$STARTUP_WAIT_SECONDS" 1 3600

# One client must not be able to fill the market pool (M1 review R2-1): a client at most half of it, one address
# (customers behind one NAT) less than all of it and at least one client's share
[ $((NGINX_MARKET_CONN_PER_CLIENT * 2)) -le "$NGINX_MARKET_MAX_INFLIGHT" ] \
    || fail "NGINX_MARKET_CONN_PER_CLIENT=$NGINX_MARKET_CONN_PER_CLIENT NGINX_MARKET_MAX_INFLIGHT=$NGINX_MARKET_MAX_INFLIGHT ning yarmidan oshmasin (bitta mijoz butun bozor pulini egallab qo'yadi)"
if [ "$NGINX_MARKET_CONN_PER_IP" -ge "$NGINX_MARKET_MAX_INFLIGHT" ] || [ "$NGINX_MARKET_CONN_PER_IP" -lt "$NGINX_MARKET_CONN_PER_CLIENT" ]; then
    fail "NGINX_MARKET_CONN_PER_IP=$NGINX_MARKET_CONN_PER_IP NGINX_MARKET_CONN_PER_CLIENT ($NGINX_MARKET_CONN_PER_CLIENT) dan kam bo'lmasin va NGINX_MARKET_MAX_INFLIGHT ($NGINX_MARKET_MAX_INFLIGHT) dan kam bo'lsin"
fi
if [ "$NGINX_MARKET_IP_RATE" -lt "$NGINX_MARKET_RATE" ] || [ "$NGINX_MARKET_IP_BURST" -lt "$NGINX_MARKET_BURST" ]; then
    fail "NGINX_MARKET_IP_RATE/NGINX_MARKET_IP_BURST ($NGINX_MARKET_IP_RATE/$NGINX_MARKET_IP_BURST) bitta mijozning NGINX_MARKET_RATE/NGINX_MARKET_BURST ($NGINX_MARKET_RATE/$NGINX_MARKET_BURST) dan kam bo'lmasin"
fi
# More in flight than the workers plus their listen queue: php-fpm refuses connections (502) instead of nginx's 503
[ "$NGINX_MARKET_MAX_INFLIGHT" -le $((FPM_MARKET_CHILDREN + FPM_MARKET_BACKLOG)) ] \
    || fail "NGINX_MARKET_MAX_INFLIGHT=$NGINX_MARKET_MAX_INFLIGHT FPM_MARKET_CHILDREN + FPM_MARKET_BACKLOG ($((FPM_MARKET_CHILDREN + FPM_MARKET_BACKLOG))) dan oshmasin"

# The same rules for the analytics pool (wave P step 2): one client or address cannot fill it, and nginx answers 503
# before php-fpm would refuse the connection
[ $((NGINX_ANALYTICS_CONN_PER_CLIENT * 2)) -le "$NGINX_ANALYTICS_MAX_INFLIGHT" ] \
    || fail "NGINX_ANALYTICS_CONN_PER_CLIENT=$NGINX_ANALYTICS_CONN_PER_CLIENT NGINX_ANALYTICS_MAX_INFLIGHT=$NGINX_ANALYTICS_MAX_INFLIGHT ning yarmidan oshmasin (bitta mijoz butun statistika pulini egallab qo'yadi)"
if [ "$NGINX_ANALYTICS_CONN_PER_IP" -ge "$NGINX_ANALYTICS_MAX_INFLIGHT" ] || [ "$NGINX_ANALYTICS_CONN_PER_IP" -lt "$NGINX_ANALYTICS_CONN_PER_CLIENT" ]; then
    fail "NGINX_ANALYTICS_CONN_PER_IP=$NGINX_ANALYTICS_CONN_PER_IP NGINX_ANALYTICS_CONN_PER_CLIENT ($NGINX_ANALYTICS_CONN_PER_CLIENT) dan kam bo'lmasin va NGINX_ANALYTICS_MAX_INFLIGHT ($NGINX_ANALYTICS_MAX_INFLIGHT) dan kam bo'lsin"
fi
if [ "$NGINX_ANALYTICS_IP_RATE" -lt "$NGINX_ANALYTICS_RATE" ] || [ "$NGINX_ANALYTICS_IP_BURST" -lt "$NGINX_ANALYTICS_BURST" ]; then
    fail "NGINX_ANALYTICS_IP_RATE/NGINX_ANALYTICS_IP_BURST ($NGINX_ANALYTICS_IP_RATE/$NGINX_ANALYTICS_IP_BURST) bitta mijozning NGINX_ANALYTICS_RATE/NGINX_ANALYTICS_BURST ($NGINX_ANALYTICS_RATE/$NGINX_ANALYTICS_BURST) dan kam bo'lmasin"
fi
[ "$NGINX_ANALYTICS_MAX_INFLIGHT" -le $((FPM_ANALYTICS_CHILDREN + FPM_ANALYTICS_BACKLOG)) ] \
    || fail "NGINX_ANALYTICS_MAX_INFLIGHT=$NGINX_ANALYTICS_MAX_INFLIGHT FPM_ANALYTICS_CHILDREN + FPM_ANALYTICS_BACKLOG ($((FPM_ANALYTICS_CHILDREN + FPM_ANALYTICS_BACKLOG))) dan oshmasin"

# Through PgBouncer (DB_POOLER=pgbouncer), the four pools share one PgBouncer pool of PGBOUNCER_POOL_SIZE server
# connections (one database, one user). Every php-fpm worker needs at most one at a time, so isolation holds only
# while all of this container's workers fit (M1 review R2-5): else a saturated market pool takes the connections a
# till waits for. docker-compose.yml passes the pooler's PGBOUNCER_POOL_SIZE to this container too.
if [ "${DB_POOLER:-direct}" = "pgbouncer" ]; then
    : "${PGBOUNCER_POOL_SIZE:=20}"
    check PGBOUNCER_POOL_SIZE "$PGBOUNCER_POOL_SIZE" 1 10000
    workers=$((FPM_CRITICAL_CHILDREN + FPM_MARKET_CHILDREN + FPM_DEFAULT_CHILDREN + FPM_ANALYTICS_CHILDREN))
    [ "$workers" -le "$PGBOUNCER_POOL_SIZE" ] \
        || fail "PGBOUNCER_POOL_SIZE=$PGBOUNCER_POOL_SIZE php-fpm ishchilari soni (FPM_CRITICAL_CHILDREN + FPM_MARKET_CHILDREN + FPM_DEFAULT_CHILDREN + FPM_ANALYTICS_CHILDREN = $workers) dan kam: bozor puli kassalarning bazaga ulanishini olib qo'yadi. PGBOUNCER_POOL_SIZE ni oshiring yoki ishchilarni kamaytiring"
    # PgBouncer has no TLS towards php-fpm (infra/pgbouncer/entrypoint.sh, private compose network)
    case "${DB_SSLMODE:-prefer}" in
        require|verify-ca|verify-full)
            fail "DB_SSLMODE=$DB_SSLMODE DB_POOLER=pgbouncer bilan ishlamaydi (PgBouncer'da php-fpm tomonga TLS yo'q). DB_SSLMODE=prefer qiling yoki DB_POOLER=direct" ;;
    esac
fi

VARS="FPM_CRITICAL_CHILDREN FPM_MARKET_CHILDREN FPM_DEFAULT_CHILDREN FPM_ANALYTICS_CHILDREN FPM_MAX_REQUESTS
    FPM_MARKET_BACKLOG FPM_MARKET_PRIORITY FPM_DEFAULT_PRIORITY NGINX_MARKET_MAX_INFLIGHT NGINX_MARKET_RATE
    NGINX_MARKET_BURST NGINX_MARKET_IP_RATE NGINX_MARKET_IP_BURST NGINX_MARKET_CONN_PER_CLIENT NGINX_MARKET_CONN_PER_IP
    FPM_ANALYTICS_BACKLOG FPM_ANALYTICS_PRIORITY NGINX_ANALYTICS_MAX_INFLIGHT NGINX_ANALYTICS_RATE NGINX_ANALYTICS_BURST
    NGINX_ANALYTICS_IP_RATE NGINX_ANALYTICS_IP_BURST NGINX_ANALYTICS_CONN_PER_CLIENT NGINX_ANALYTICS_CONN_PER_IP
    NGINX_RETRY_AFTER"
for v in $VARS; do export "${v?}"; done

render() { # dir
    # http context: the zones (keys and maps in nginx.conf; an empty key, i.e. any request outside the zone's own
    # location, is not counted)
    cat > "$1/rizqim-pools-http.conf" <<EOF
# Rendered by backend/docker/pools.sh from the environment; do not edit.
limit_req_zone \$rizqim_market_client zone=rizqim_market_rate:10m rate=${NGINX_MARKET_RATE}r/s;
limit_req_zone \$rizqim_market_ip zone=rizqim_market_ip_rate:10m rate=${NGINX_MARKET_IP_RATE}r/s;
limit_conn_zone \$rizqim_market_client zone=rizqim_market_client:10m;
limit_conn_zone \$rizqim_market_ip zone=rizqim_market_ip:10m;
limit_conn_zone \$rizqim_market_all zone=rizqim_market_all:1m;
limit_req_zone \$rizqim_analytics_client zone=rizqim_analytics_rate:10m rate=${NGINX_ANALYTICS_RATE}r/m;
limit_req_zone \$rizqim_analytics_ip zone=rizqim_analytics_ip_rate:10m rate=${NGINX_ANALYTICS_IP_RATE}r/m;
limit_conn_zone \$rizqim_analytics_client zone=rizqim_analytics_client:10m;
limit_conn_zone \$rizqim_analytics_ip zone=rizqim_analytics_ip:10m;
limit_conn_zone \$rizqim_analytics_all zone=rizqim_analytics_all:1m;
map \$host \$rizqim_retry_after { default ${NGINX_RETRY_AFTER}; }
EOF
    # location context (location @rizqim_market of nginx.conf)
    cat > "$1/rizqim-pools-limits.conf" <<EOF
# Rendered by backend/docker/pools.sh from the environment; do not edit.
limit_conn rizqim_market_all ${NGINX_MARKET_MAX_INFLIGHT};
limit_conn rizqim_market_ip ${NGINX_MARKET_CONN_PER_IP};
limit_conn rizqim_market_client ${NGINX_MARKET_CONN_PER_CLIENT};
limit_req zone=rizqim_market_ip_rate burst=${NGINX_MARKET_IP_BURST} nodelay;
limit_req zone=rizqim_market_rate burst=${NGINX_MARKET_BURST} nodelay;
limit_conn_status 503;
limit_req_status 429;
limit_conn_log_level warn;
limit_req_log_level warn;
EOF
    # location context (the analytics location of nginx.conf)
    cat > "$1/rizqim-pools-analytics.conf" <<EOF
# Rendered by backend/docker/pools.sh from the environment; do not edit.
limit_conn rizqim_analytics_all ${NGINX_ANALYTICS_MAX_INFLIGHT};
limit_conn rizqim_analytics_ip ${NGINX_ANALYTICS_CONN_PER_IP};
limit_conn rizqim_analytics_client ${NGINX_ANALYTICS_CONN_PER_CLIENT};
limit_req zone=rizqim_analytics_ip_rate burst=${NGINX_ANALYTICS_IP_BURST} nodelay;
limit_req zone=rizqim_analytics_rate burst=${NGINX_ANALYTICS_BURST} nodelay;
limit_conn_status 503;
limit_req_status 429;
limit_conn_log_level warn;
limit_req_log_level warn;
EOF
}

case "${1:-}" in
    php-fpm)
        exec php-fpm -F ;;
    nginx)
        render /etc/nginx
        exec nginx -g 'daemon off;' ;;
    render)
        render "${2:?pools.sh render <dir>}" ;;
    check)
        render /etc/nginx
        nginx -t -q || fail "nginx sozlamasi yaroqsiz"
        php-fpm -t >/dev/null 2>&1 || { php-fpm -t; fail "php-fpm sozlamasi yaroqsiz"; } ;;
    wait-for)
        name=${2:?pools.sh wait-for NAME HOST PORT}; host=${3:?}; port=${4:?}
        waited=0
        until nc -z -w 2 "$host" "$port" 2>/dev/null; do
            waited=$((waited + 1))
            [ "$waited" -lt "$STARTUP_WAIT_SECONDS" ] \
                || fail "$name ($host:$port) ${STARTUP_WAIT_SECONDS} s ichida javob bermadi (STARTUP_WAIT_SECONDS). Xizmat ishlayotganini va manzilni tekshiring"
            sleep 1
        done ;;
    env)
        for v in $VARS; do
            eval "echo \"export $v=\$$v\""
        done ;;
    *)
        echo "usage: pools.sh php-fpm | nginx | render <dir> | env | check | wait-for NAME HOST PORT" >&2; exit 2 ;;
esac
