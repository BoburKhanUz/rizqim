#!/bin/sh
# PHP-FPM pool sizes and the market limits of nginx from the environment, with safe defaults (wave M1 workload
# isolation; docs/DEPLOY.md, docs/OPERATIONS.md "PHP-FPM pullari"). Run by supervisord (supervisord.conf):
#   pools.sh php-fpm         defaults exported, then php-fpm in the foreground (php-fpm.conf reads the ${FPM_*} values)
#   pools.sh nginx           rizqim-pools-*.conf rendered into /etc/nginx, then nginx in the foreground
#   pools.sh render <dir>    only render the two nginx includes into <dir> (the image build, scripts/scale-test)
#   pools.sh env             print the effective values as `export NAME=value` lines (scripts/scale-test)
# A value that is not a whole number in range stops the container: a typo must not start a pool of 0 workers.
set -eu

: "${FPM_CRITICAL_CHILDREN:=4}"      # tills, KDS, display, staff board, PIN sign-in, payment webhooks, health
: "${FPM_MARKET_CHILDREN:=4}"        # Mini App customer reads
: "${FPM_DEFAULT_CHILDREN:=4}"       # owner/partner panel, admin, customer writes (checkout), the rest
: "${FPM_MAX_REQUESTS:=500}"         # a worker is replaced after this many requests (bounds slow memory growth)
: "${FPM_MARKET_BACKLOG:=32}"        # listen queue of the market pool
: "${FPM_MARKET_PRIORITY:=10}"       # nice of the market workers: the tills win the CPU when it is short
: "${FPM_DEFAULT_PRIORITY:=5}"       # nice of the default workers (heavy owner reports)
: "${NGINX_MARKET_RATE:=5}"          # market requests per second per client (token, else address)...
: "${NGINX_MARKET_BURST:=20}"        # ...plus this many at once (a Mini App screen loads several); above: 429
: "${NGINX_MARKET_CONN_PER_CLIENT:=10}"  # market requests of one client in PHP at once; above: 503
: "${NGINX_RETRY_AFTER:=2}"          # Retry-After (seconds) of those 503/429 answers

check() { # name value min max
    case "$2" in
        ''|*[!0-9]*) echo "pools.sh: $1=$2 butun son emas" >&2; exit 1 ;;
    esac
    if [ "$2" -lt "$3" ] || [ "$2" -gt "$4" ]; then
        echo "pools.sh: $1=$2 ruxsat etilgan oraliqdan tashqarida ($3..$4)" >&2; exit 1
    fi
}
check FPM_CRITICAL_CHILDREN "$FPM_CRITICAL_CHILDREN" 1 256
check FPM_MARKET_CHILDREN "$FPM_MARKET_CHILDREN" 1 256
# Market requests in PHP at once in this container (= the market workers plus as many waiting); above: 503 at once
: "${NGINX_MARKET_MAX_INFLIGHT:=$((FPM_MARKET_CHILDREN * 2))}"
check FPM_DEFAULT_CHILDREN "$FPM_DEFAULT_CHILDREN" 1 256
check FPM_MAX_REQUESTS "$FPM_MAX_REQUESTS" 0 1000000
check FPM_MARKET_BACKLOG "$FPM_MARKET_BACKLOG" 1 65535
check FPM_MARKET_PRIORITY "$FPM_MARKET_PRIORITY" 0 19
check FPM_DEFAULT_PRIORITY "$FPM_DEFAULT_PRIORITY" 0 19
check NGINX_MARKET_MAX_INFLIGHT "$NGINX_MARKET_MAX_INFLIGHT" 1 65535
check NGINX_MARKET_RATE "$NGINX_MARKET_RATE" 1 100000
check NGINX_MARKET_BURST "$NGINX_MARKET_BURST" 0 100000
check NGINX_MARKET_CONN_PER_CLIENT "$NGINX_MARKET_CONN_PER_CLIENT" 1 65535
check NGINX_RETRY_AFTER "$NGINX_RETRY_AFTER" 1 3600

export FPM_CRITICAL_CHILDREN FPM_MARKET_CHILDREN FPM_DEFAULT_CHILDREN FPM_MAX_REQUESTS FPM_MARKET_BACKLOG \
    FPM_MARKET_PRIORITY FPM_DEFAULT_PRIORITY NGINX_MARKET_MAX_INFLIGHT NGINX_MARKET_RATE NGINX_MARKET_BURST \
    NGINX_MARKET_CONN_PER_CLIENT NGINX_RETRY_AFTER

render() { # dir
    # http context: the zones (keys and maps in nginx.conf; an empty key, i.e. any non-market request, is not counted)
    cat > "$1/rizqim-pools-http.conf" <<EOF
# Rendered by backend/docker/pools.sh from the environment; do not edit.
limit_req_zone \$rizqim_market_client zone=rizqim_market_rate:10m rate=${NGINX_MARKET_RATE}r/s;
limit_conn_zone \$rizqim_market_client zone=rizqim_market_client:10m;
limit_conn_zone \$rizqim_market_all zone=rizqim_market_all:1m;
map \$host \$rizqim_retry_after { default ${NGINX_RETRY_AFTER}; }
EOF
    # location context (the PHP location of nginx.conf)
    cat > "$1/rizqim-pools-limits.conf" <<EOF
# Rendered by backend/docker/pools.sh from the environment; do not edit.
limit_conn rizqim_market_all ${NGINX_MARKET_MAX_INFLIGHT};
limit_conn rizqim_market_client ${NGINX_MARKET_CONN_PER_CLIENT};
limit_req zone=rizqim_market_rate burst=${NGINX_MARKET_BURST} nodelay;
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
    env)
        for v in FPM_CRITICAL_CHILDREN FPM_MARKET_CHILDREN FPM_DEFAULT_CHILDREN FPM_MAX_REQUESTS FPM_MARKET_BACKLOG \
            FPM_MARKET_PRIORITY FPM_DEFAULT_PRIORITY NGINX_MARKET_MAX_INFLIGHT NGINX_MARKET_RATE NGINX_MARKET_BURST \
            NGINX_MARKET_CONN_PER_CLIENT NGINX_RETRY_AFTER; do
            eval "echo \"export $v=\$$v\""
        done ;;
    *)
        echo "usage: pools.sh php-fpm | nginx | render <dir> | env" >&2; exit 2 ;;
esac
