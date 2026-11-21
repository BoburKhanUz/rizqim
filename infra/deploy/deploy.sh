#!/usr/bin/env bash
# Serverda deploy: GHCR'dan tayyor obrazlarni tortadi, xizmatlarni yangilaydi, sog'liqni tekshiradi va muvaffaqiyatsiz bo'lsa
# avvalgi versiyaga AVTOMATIK qaytaradi. GitHub Actions (.github/workflows/deploy.yml) uni SSH orqali ishga tushiradi; qo'lda ham
# ishlatsa bo'ladi. Qayta ishga tushirish xavfsiz (idempotent): bir xil reliz ustida ikkinchi marta hech narsani buzmaydi.
#
# Ishlatish (loyiha papkasida, odatda /opt/rizqim):
#   infra/deploy/deploy.sh deploy [--release-file infra/deploy/release.env] [--registry-user USER]
#       GHCR tokeni (GITHUB_TOKEN) STDIN'dan o'qiladi (argumentda emas: ps'da ko'rinmasin). Token bo'lmasa login qilinmaydi
#       (obrazlar serverda allaqachon bo'lsa, yoki ommaviy bo'lsa).
#   infra/deploy/deploy.sh rollback     avvalgi muvaffaqiyatli relizga qaytaradi (xuddi avtomatik qaytarish kabi)
#   infra/deploy/deploy.sh status       joriy/avvalgi reliz va xizmatlar holati
#
# release.env (CI yozadi, paket ichida keladi): DEPLOY_TARGET, GIT_SHA, IMAGE_PREFIX, IMAGE_TAG, IMAGE_TAG_POSTGRES,
# IMAGE_TAG_PGBOUNCER, IMAGE_TAG_BACKUP. Skript ularni ildiz .env ga yozadi (qo'lda `docker compose` ham shu relizni ko'radi).
#
# Qadamlar: 1) oldindan tekshiruv  2) obrazlarni tortish (xato bo'lsa hech narsaga tegilmaydi)  3) production'da zaxira
# 4) docker compose up -d  5) hamma xizmat healthy  6) migrate --force (backend entrypoint allaqachon bajargan, bu qayta tekshiruv)
# 7) HTTP tekshiruvlari  -> xato bo'lsa: avvalgi relizga qaytarish va nolga teng bo'lmagan chiqish kodi.
#
# Eslatma: migratsiyalar faqat oldinga ketadi. Orqaga qaytarish KOD obrazini qaytaradi, bazani emas: yangi migratsiya eski kod
# bilan ishlay olishi kerak (avval ustun qo'shish, keyingi relizda eskisini olib tashlash). Aks holda bazani zaxiradan tiklang
# (docs/OPERATIONS.md, 4 va 18-bo'limlar). docs/DEPLOY.md, 11-bo'lim.

set -Eeuo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$APP_DIR"

STATE_DIR="$APP_DIR/.deploy"
ENV_FILE="$APP_DIR/.env"
# Servislar sog'lom bo'lishini kutish (soniya). backend start_period 120 s, scheduler 180 s: standart zaxira bilan
HEALTH_TIMEOUT="${DEPLOY_HEALTH_TIMEOUT:-600}"
REGISTRY_HOST="${DEPLOY_REGISTRY_HOST:-ghcr.io}"

log() { printf '%s [deploy] %s\n' "$(date -u +%H:%M:%S)" "$*"; }
warn() { printf '%s [deploy] OGOHLANTIRISH: %s\n' "$(date -u +%H:%M:%S)" "$*" >&2; }
die() { printf '%s [deploy] XATO: %s\n' "$(date -u +%H:%M:%S)" "$*" >&2; exit 1; }

# ---------------------------------------------------------------------------------------------------------------------
# .env bilan ishlash

# env_get KEY [FILE]: ildiz .env dagi qiymat (qo'shtirnoqsiz; bo'sh bo'lsa bo'sh satr)
env_get() {
    local key="$1" file="${2:-$ENV_FILE}" line
    [ -f "$file" ] || return 0
    line="$(grep -E "^${key}=" "$file" | tail -n 1 || true)"
    line="${line#*=}"
    line="${line%\"}"
    line="${line#\"}"
    line="${line%\'}"
    line="${line#\'}"
    printf '%s' "$line"
}

# env_set KEY VALUE: qatorni almashtiradi yoki oxiriga qo'shadi. Fayl o'sha-o'zida yoziladi (ruxsatlar va egasi saqlanadi)
env_set() {
    local key="$1" value="$2" tmp
    tmp="$(mktemp)"
    grep -vE "^${key}=" "$ENV_FILE" > "$tmp" || true
    printf '%s=%s\n' "$key" "$value" >> "$tmp"
    cat "$tmp" > "$ENV_FILE"
    rm -f "$tmp"
}

# release_get KEY FILE: release.env qiymati (faqat xavfsiz belgilar bilan tekshirilgan)
release_get() {
    local key="$1" file="$2" line
    line="$(grep -E "^${key}=" "$file" | tail -n 1 || true)"
    printf '%s' "${line#*=}"
}


validate_release_file() {
    local file="$1" key value
    [ -f "$file" ] || die "reliz fayli topilmadi: $file"
    # Faqat KEY=qiymat qatorlari; qiymatda faqat harf, raqam va . / : _ - (shell'ga hech narsa o'tmaydi)
    if grep -vE '^(#.*|[A-Z_]+=[A-Za-z0-9./:_-]*|)$' "$file" > /dev/null; then
        die "reliz fayli noto'g'ri qator bor: $file"
    fi
    for key in DEPLOY_TARGET IMAGE_PREFIX IMAGE_TAG IMAGE_TAG_POSTGRES IMAGE_TAG_PGBOUNCER IMAGE_TAG_BACKUP; do
        value="$(release_get "$key" "$file")"
        [ -n "$value" ] || die "reliz faylida $key yo'q: $file"
    done
}

apply_release_to_env() {
    local file="$1" key
    for key in IMAGE_PREFIX IMAGE_TAG IMAGE_TAG_POSTGRES IMAGE_TAG_PGBOUNCER IMAGE_TAG_BACKUP; do
        env_set "$key" "$(release_get "$key" "$file")"
    done
}

# ---------------------------------------------------------------------------------------------------------------------
# docker compose

dc() { docker compose "$@"; }

# Compose'ning ko'radigan xizmatlari (db-roles: bir martalik)
list_services() { dc config --services; }

# -a: bir martalik (tugagan) konteynerlar ham ro'yxatda bo'lsin
service_cid() { dc ps -a -q "$1" 2> /dev/null | head -n 1; }

# Xizmat holati: healthy | unhealthy | starting | running | exited:<kod> | missing
service_state() {
    local cid state health code
    cid="$(service_cid "$1")"
    [ -n "$cid" ] || { echo missing; return 0; }
    state="$(docker inspect -f '{{.State.Status}}' "$cid" 2> /dev/null || echo missing)"
    health="$(docker inspect -f '{{if .State.Health}}{{.State.Health.Status}}{{end}}' "$cid" 2> /dev/null || true)"
    case "$state" in
        running)
            if [ -n "$health" ]; then echo "$health"; else echo running; fi
            ;;
        exited)
            code="$(docker inspect -f '{{.State.ExitCode}}' "$cid" 2> /dev/null || echo '?')"
            echo "exited:$code"
            ;;
        *) echo "$state" ;;
    esac
}

# Hamma xizmat tayyor bo'lguncha kutadi. db-roles bir martalik: exited:0 yaxshi. Qolganlar: healthy (healthcheck bor) yoki running.
# Qaytish: 0 hammasi tayyor, 1 vaqt tugadi yoki xizmat yiqildi (sababi logga yoziladi)
wait_all_ready() {
    local deadline=$((SECONDS + HEALTH_TIMEOUT)) svc st pending bad
    local -a services
    mapfile -t services < <(list_services)
    while :; do
        pending=""
        bad=""
        for svc in "${services[@]}"; do
            st="$(service_state "$svc")"
            case "$svc:$st" in
                db-roles:exited:0) ;;
                db-roles:*) bad="$bad $svc=$st" ;;
                *:healthy | *:running) ;;
                *:unhealthy | *:exited:* | *:dead | *:restarting | *:missing) bad="$bad $svc=$st" ;;
                *) pending="$pending $svc=$st" ;;
            esac
        done
        if [ -z "$pending" ] && [ -z "$bad" ]; then
            log "hamma xizmat tayyor (${#services[@]} ta)"
            return 0
        fi
        # `unhealthy`/yiqilgan xizmat darhol xato: kutishning ma'nosi yo'q (Compose o'zi qayta urinadi, lekin healthcheck
        # qayta-qayta o'tmasa, bu reliz yaroqsiz)
        if [ -n "$bad" ]; then
            warn "tayyor emas:$bad"
            return 1
        fi
        if [ "$SECONDS" -ge "$deadline" ]; then
            warn "${HEALTH_TIMEOUT} soniyada tayyor bo'lmadi:$pending"
            return 1
        fi
        sleep 5
    done
}

# curl bilan tekshiruv: url, urinishlar soni, urinishlar orasidagi pauza
http_ok() {
    local url="$1" tries="${2:-3}" pause="${3:-5}" i
    for ((i = 1; i <= tries; i++)); do
        if curl -fsS --max-time 15 -o /dev/null "$url"; then
            return 0
        fi
        [ "$i" -lt "$tries" ] && sleep "$pause"
    done
    return 1
}

# Ichki HTTP tekshiruvlari: xost portlari (127.0.0.1) orqali; backend /up va /health, Mini App nginx orqali API, landing
internal_checks() {
    local backend_port frontend_port landing_port
    backend_port="$(env_get BACKEND_PORT)"
    frontend_port="$(env_get FRONTEND_PORT)"
    landing_port="$(env_get LANDING_PORT)"
    backend_port="${backend_port:-8000}"
    frontend_port="${frontend_port:-8080}"
    landing_port="${landing_port:-8091}"

    http_ok "http://127.0.0.1:${backend_port}/up" 6 5 || { warn "backend /up javob bermadi"; return 1; }
    # /health 200 qaytaradi, "fail" bo'lsa 503; "degraded" (masalan navbat uzun) 200
    http_ok "http://127.0.0.1:${backend_port}/health" 6 5 || { warn "backend /health muvaffaqiyatsiz"; return 1; }
    http_ok "http://127.0.0.1:${frontend_port}/api/v1/health" 6 5 || { warn "Mini App nginx orqali API javob bermadi"; return 1; }
    http_ok "http://127.0.0.1:${landing_port}/" 3 5 || { warn "landing javob bermadi"; return 1; }
    return 0
}

# Ommaviy manzil (DNS va TLS tayyor bo'lsa): https://api.<EDGE_DOMAIN>/up va https://<EDGE_DOMAIN>/. Standart: faqat ogohlantirish
# (DNS yoki sertifikat muammosi reliz aybi emas); DEPLOY_PUBLIC_CHECK=required bo'lsa xato (orqaga qaytariladi)
public_checks() {
    local domain mode
    domain="$(env_get EDGE_DOMAIN)"
    mode="$(env_get DEPLOY_PUBLIC_CHECK)"
    mode="${mode:-warn}"
    [ -n "$domain" ] || { log "EDGE_DOMAIN yo'q: ommaviy tekshiruv o'tkazib yuborildi"; return 0; }
    # Sertifikat birinchi marta olinayotgan bo'lishi mumkin: 12 marta x 10 s
    if http_ok "https://api.${domain}/up" 12 10 && http_ok "https://app.${domain}/" 3 5 && http_ok "https://${domain}/" 3 5; then
        log "ommaviy manzillar javob beryapti (api., app., asosiy)"
        return 0
    fi
    if [ "$mode" = required ]; then
        warn "ommaviy tekshiruv muvaffaqiyatsiz (DEPLOY_PUBLIC_CHECK=required)"
        return 1
    fi
    warn "ommaviy tekshiruv muvaffaqiyatsiz (DNS yoki sertifikat hali tayyor emasmi?): https://api.${domain}/up. Davom etamiz"
    return 0
}

run_migrations() {
    log "migrate --force (scheduler: bazaga to'g'ridan-to'g'ri ulanadi, jadvallar egasi kalitlari bilan)"
    dc exec -T -u www-data scheduler php artisan migrate --force
}

# Oldindan zaxira (faqat bazada nimadir bo'lsa: birinchi deploy'da yo'q)
pre_backup() {
    local want
    want="$(env_get DEPLOY_BACKUP)"
    if [ -z "$want" ]; then
        if [ "$(env_get DEPLOY_TARGET)" = production ]; then want=1; else want=0; fi
    fi
    [ "$want" = 1 ] || { log "oldindan zaxira o'chiq (DEPLOY_BACKUP=$want)"; return 0; }
    if [ "$(service_state backup)" != running ]; then
        log "backup xizmati ishlamayapti (birinchi deploy?): oldindan zaxira o'tkazib yuborildi"
        return 0
    fi
    log "oldindan zaxira: backup.sh"
    dc exec -T backup sh /usr/local/bin/backup.sh || die "oldindan zaxira muvaffaqiyatsiz: deploy to'xtatildi (hech narsa o'zgarmagan). Sabab backup logida: docker compose logs backup"
}

# ---------------------------------------------------------------------------------------------------------------------
# Oldindan tekshiruv

preflight() {
    local target="$1" cf file
    command -v docker > /dev/null || die "docker topilmadi"
    docker compose version > /dev/null 2>&1 || die "docker compose (v2) topilmadi"
    command -v curl > /dev/null || die "curl topilmadi"
    command -v flock > /dev/null || die "flock topilmadi (util-linux)"
    [ -f "$ENV_FILE" ] || die "$ENV_FILE yo'q: cp .env.<muhit>.example .env va to'ldiring (docs/SERVER_SETUP.md)"
    [ -f "$APP_DIR/backend/.env" ] || die "backend/.env yo'q: cp backend/.env.<muhit>.example backend/.env va to'ldiring"
    # Production'ga staging reliz (va aksincha) tushib qolmasin
    local env_target
    env_target="$(env_get DEPLOY_TARGET)"
    [ -n "$env_target" ] || die ".env da DEPLOY_TARGET yo'q (staging yoki production)"
    [ "$env_target" = "$target" ] || die "reliz '$target' uchun, bu serverning .env i '$env_target' (DEPLOY_TARGET). Noto'g'ri server?"
    cf="$(env_get COMPOSE_FILE)"
    case ":$cf:" in *:docker-compose.images.yml:*) ;; *) die ".env dagi COMPOSE_FILE ichida docker-compose.images.yml yo'q (namunaga qarang)" ;; esac
    # COMPOSE_FILE dagi hamma fayl mavjud bo'lsin
    # shellcheck disable=SC2086  # COMPOSE_FILE ":" bilan ajratilgan, fayl nomlarida bo'sh joy yo'q
    for file in ${cf//:/ }; do
        [ -f "$APP_DIR/$file" ] || die "COMPOSE_FILE dagi fayl yo'q: $file"
    done
    case ":$cf:" in
        *:docker-compose.edge.yml:*)
            [ -n "$(env_get EDGE_DOMAIN)" ] || die ".env da EDGE_DOMAIN bo'sh (rizqim.uz yoki stg.rizqim.uz)"
            [ -n "$(env_get EDGE_ACME_EMAIL)" ] || die ".env da EDGE_ACME_EMAIL bo'sh (Let's Encrypt uchun e-pochta)"
            [ -f "$APP_DIR/infra/edge/Caddyfile" ] || die "infra/edge/Caddyfile yo'q"
            ;;
    esac
    # Bo'sh yoki namuna qiymatli parollar bilan ishga tushmaylik
    case "$(env_get DB_PASSWORD)" in
        '' | secret | CHANGE_ME*) die ".env da DB_PASSWORD to'ldirilmagan" ;;
    esac
    [ -n "$(grep -E '^APP_KEY=base64:.+' "$APP_DIR/backend/.env" || true)" ] || die "backend/.env da APP_KEY yo'q (echo \"base64:\$(openssl rand -base64 32)\")"
    dc config -q || die "docker compose config xato"
}

registry_login() {
    local user="$1" token
    [ -n "$user" ] || return 0
    IFS= read -r token || true
    if [ -z "$token" ]; then
        warn "--registry-user berilgan, lekin STDIN'da token yo'q: login qilinmadi"
        return 0
    fi
    printf '%s\n' "$token" | docker login "$REGISTRY_HOST" -u "$user" --password-stdin > /dev/null || die "$REGISTRY_HOST ga kirib bo'lmadi"
    LOGGED_IN=1
    log "$REGISTRY_HOST ga kirildi"
}

registry_logout() {
    if [ "${LOGGED_IN:-0}" = 1 ]; then
        # Vaqtinchalik token (GITHUB_TOKEN) ish tugagach baribir yaroqsiz: serverda qolib ketmasin
        docker logout "$REGISTRY_HOST" > /dev/null 2>&1 || true
    fi
}

# ---------------------------------------------------------------------------------------------------------------------
# Reliz holati (.deploy/current.env, previous.env)

save_state() {
    local release_file="$1"
    mkdir -p "$STATE_DIR"
    if [ -f "$STATE_DIR/current.env" ] && ! cmp -s "$STATE_DIR/current.env" "$release_file"; then
        cp "$STATE_DIR/current.env" "$STATE_DIR/previous.env"
    fi
    cp "$release_file" "$STATE_DIR/current.env"
}

summary() {
    local label="$1" file="$2"
    if [ -f "$file" ]; then
        log "$label: $(release_get GIT_SHA "$file") (tag $(release_get IMAGE_TAG "$file"))"
    else
        log "$label: yo'q"
    fi
}

# ---------------------------------------------------------------------------------------------------------------------
# Joylash va qaytarish

# export_release FILE: reliz qiymatlari jarayon muhitida ham bo'lsin (muhit ildiz .env dan ustun: aks holda oldingi relizning
# eksport qilingan qiymatlari qaytarishni buzardi)
export_release() {
    local file="$1" key
    for key in IMAGE_PREFIX IMAGE_TAG IMAGE_TAG_POSTGRES IMAGE_TAG_PGBOUNCER IMAGE_TAG_BACKUP; do
        export "$key=$(release_get "$key" "$file")"
    done
}

# apply_release FILE: .env ga yozadi va xizmatlarni shu reliz bilan ko'taradi. `up -d` backend healthy bo'lishini kutadi
# (depends_on): healthcheck o'tmasa u nolga teng bo'lmagan kod bilan chiqadi
apply_release() {
    local file="$1"
    export_release "$file"
    apply_release_to_env "$file"
    log "up -d (tag $(release_get IMAGE_TAG "$file"))"
    dc up -d --no-build --remove-orphans
}

verify_release() {
    wait_all_ready || return 1
    run_migrations || { warn "migrate --force muvaffaqiyatsiz"; return 1; }
    internal_checks || return 1
    public_checks || return 1
    return 0
}

# rollback_to FILE: shu relizga qaytaradi (tayyor bo'lishini kutadi)
rollback_to() {
    local prev="$1"
    if [ ! -f "$prev" ]; then
        warn "qaytariladigan reliz yo'q (birinchi deploy?): $prev. Loglar: docker compose logs --tail=100"
        return 1
    fi
    summary "ORQAGA QAYTARISH" "$prev"
    validate_release_file "$prev"
    if apply_release "$prev" && wait_all_ready && internal_checks; then
        log "orqaga qaytarish muvaffaqiyatli: tizim avvalgi versiyada ishlayapti"
        return 0
    fi
    warn "orqaga qaytarishdan keyin ham tizim sog'lom emas: qo'lda aralashuv kerak (docker compose ps; docker compose logs --tail=100)"
    return 1
}

cmd_deploy() {
    local release_file="infra/deploy/release.env" registry_user="" target
    while [ $# -gt 0 ]; do
        case "$1" in
            --release-file) release_file="${2:?--release-file qiymati kerak}"; shift 2 ;;
            --registry-user) registry_user="${2:?--registry-user qiymati kerak}"; shift 2 ;;
            *) die "noma'lum argument: $1" ;;
        esac
    done
    case "$release_file" in /*) ;; *) release_file="$APP_DIR/$release_file" ;; esac
    validate_release_file "$release_file"
    target="$(release_get DEPLOY_TARGET "$release_file")"
    case "$target" in staging | production) ;; *) die "DEPLOY_TARGET staging yoki production bo'lishi kerak: $target" ;; esac

    preflight "$target"
    summary "yangi reliz" "$release_file"
    summary "joriy reliz" "$STATE_DIR/current.env"

    registry_login "$registry_user"
    trap registry_logout EXIT

    # Yangi obrazlar shu yerda tortiladi: xato bo'lsa (tarmoq, ruxsat, yo'q tag) hech qaysi konteynerga tegilmagan bo'ladi.
    # Ular ildiz .env ga yozilmaguncha jarayon muhitida beriladi.
    export_release "$release_file"
    log "obrazlarni tortish"
    dc pull --quiet || die "obrazlarni tortib bo'lmadi: deploy to'xtatildi (hech narsa o'zgarmagan)"

    pre_backup

    if apply_release "$release_file" && verify_release; then
        save_state "$release_file"
        docker image prune -f > /dev/null 2>&1 || true
        log "DEPLOY MUVAFFAQIYATLI: $(release_get GIT_SHA "$release_file")"
        return 0
    fi

    warn "yangi reliz sog'lom emas: oxirgi muvaffaqiyatli relizga qaytariladi"
    docker ps --format 'table {{.Names}}\t{{.Status}}' >&2 || true
    dc logs --tail=40 backend >&2 || true
    # Oxirgi yaxshi reliz = joriy (current.env). Xuddi shu relizni qayta joylash muvaffaqiyatsiz bo'lsa, undan oldingisi
    local back="$STATE_DIR/current.env"
    if [ ! -f "$back" ] || cmp -s "$back" "$release_file"; then back="$STATE_DIR/previous.env"; fi
    if rollback_to "$back"; then
        die "DEPLOY MUVAFFAQIYATSIZ, tizim avvalgi versiyaga qaytarildi ($(release_get GIT_SHA "$back"))"
    fi
    die "DEPLOY MUVAFFAQIYATSIZ va avtomatik qaytarish ham bo'lmadi"
}

cmd_rollback() {
    local target
    [ -f "$STATE_DIR/previous.env" ] || die "avvalgi reliz yo'q: $STATE_DIR/previous.env"
    target="$(release_get DEPLOY_TARGET "$STATE_DIR/previous.env")"
    preflight "$target"
    # Avvalgi obrazlar serverda qolgan bo'lishi kerak; yo'q bo'lsa `up` ularni o'zi tortadi (login kerak bo'lishi mumkin)
    rollback_to "$STATE_DIR/previous.env" || die "qaytarish bo'lmadi"
    # Joriy va avvalgi o'rin almashadi: yana rollback qilsa oldingi relizga qaytadi
    local tmp
    tmp="$(mktemp)"
    cp "$STATE_DIR/current.env" "$tmp"
    cp "$STATE_DIR/previous.env" "$STATE_DIR/current.env"
    cp "$tmp" "$STATE_DIR/previous.env"
    rm -f "$tmp"
    log "joriy reliz endi: $(release_get GIT_SHA "$STATE_DIR/current.env")"
}

cmd_status() {
    summary "joriy reliz" "$STATE_DIR/current.env"
    summary "avvalgi reliz" "$STATE_DIR/previous.env"
    dc ps
}

main() {
    local cmd="${1:-}"
    [ -n "$cmd" ] || die "buyruq kerak: deploy | rollback | status"
    shift
    mkdir -p "$STATE_DIR"
    case "$cmd" in
        status) cmd_status "$@"; return 0 ;;
        deploy | rollback) ;;
        *) die "noma'lum buyruq: $cmd (deploy | rollback | status)" ;;
    esac
    # Bir vaqtda bitta deploy
    exec 9> "$STATE_DIR/lock"
    flock -n 9 || die "boshqa deploy ishlayapti"
    "cmd_$cmd" "$@"
}

main "$@"
