#!/usr/bin/env bash
# Serverda deploy: GHCR'dan tayyor obrazlarni tortadi, xizmatlarni yangilaydi, sog'liqni tekshiradi va muvaffaqiyatsiz bo'lsa
# avvalgi versiyaga AVTOMATIK qaytaradi. GitHub Actions (.github/workflows/deploy.yml) uni SSH orqali ishga tushiradi; qo'lda ham
# ishlatsa bo'ladi. Qayta ishga tushirish xavfsiz (idempotent): bir xil reliz ustida ikkinchi marta hech narsani buzmaydi.
#
# Reliz papkalari: CI fayllar to'plamini loyiha papkasiga EMAS, <loyiha>/.deploy/releases/<tag>/ ga ochadi va skriptni shu
# papkadan ishga tushiradi. Ishlayotgan fayllar (compose, scripts/, infra/) faqat tekshiruvlar (oldindan tekshiruv, compose
# config, obrazlarni tortish, production zaxirasi) o'tgandan KEYIN almashtiriladi; orqaga qaytarishda avvalgi relizning
# o'z fayllari qaytariladi (eski obraz + eski compose/Caddyfile).
#
# Ishlatish:
#   <loyiha>/.deploy/releases/<tag>/infra/deploy/deploy.sh deploy [--release-file FILE] [--registry-user USER]
#       (CI shunday chaqiradi). GHCR tokeni (GITHUB_TOKEN) STDIN'dan o'qiladi (argumentda emas: ps'da ko'rinmasin) va faqat shu
#       jarayonning vaqtinchalik DOCKER_CONFIG papkasida turadi (~/.docker ga yozilmaydi; chiqishda, INT/TERM/HUP da ham o'chadi).
#       Token bo'lmasa login qilinmaydi (obrazlar serverda allaqachon bo'lsa, yoki ommaviy bo'lsa).
#   <loyiha>/.deploy/releases/<tag>/infra/deploy/deploy.sh install-files
#       faqat BIRINCHI o'rnatish (CI `only_upload`): fayllarni loyiha papkasiga ko'chiradi (.env namunalari uchun). Reliz
#       joylangan serverda rad etadi.
#   infra/deploy/deploy.sh deploy       loyiha papkasining o'zidagi fayllar bilan (qo'lda; fayllar almashtirilmaydi)
#   infra/deploy/deploy.sh rollback     avvalgi muvaffaqiyatli relizga (uning fayllari va obrazlariga) qaytaradi
#   infra/deploy/deploy.sh status       joriy/avvalgi reliz va xizmatlar holati
#
# release.env (CI yozadi, paket ichida keladi): DEPLOY_TARGET, GIT_SHA, IMAGE_PREFIX, IMAGE_TAG, IMAGE_TAG_POSTGRES,
# IMAGE_TAG_PGBOUNCER, IMAGE_TAG_BACKUP. Skript ularni ildiz .env ga yozadi (qo'lda `docker compose` ham shu relizni ko'radi).
#
# Qadamlar: 1) oldindan tekshiruv (reliz papkasidagi fayllar bilan)  2) obrazlarni tortish  3) production'da zaxira (backup
# xizmati ishlamasa va bazada ma'lumot bo'lishi mumkin bo'lsa: RAD ETILADI)  -> 1-3 da xato bo'lsa hech narsaga tegilmagan
# 4) fayllarni almashtirish  5) docker compose up -d  6) edge: caddy reload (yangi Caddyfile)  7) hamma xizmat healthy
# 8) migrate --force (backend entrypoint allaqachon bajargan, bu qayta tekshiruv)  9) HTTP tekshiruvlari
# -> xato bo'lsa: avvalgi relizning fayllari va obrazlariga qaytarish (caddy reload bilan) va nolga teng bo'lmagan chiqish kodi.
#
# Eslatma: migratsiyalar faqat oldinga ketadi. Orqaga qaytarish KOD obrazini qaytaradi, bazani emas: yangi migratsiya eski kod
# bilan ishlay olishi kerak (avval ustun qo'shish, keyingi relizda eskisini olib tashlash). Aks holda bazani zaxiradan tiklang
# (docs/OPERATIONS.md, 4 va 18-bo'limlar). docs/DEPLOY.md, 11-bo'lim.

set -Eeuo pipefail

# Skript reliz papkasidan (<loyiha>/.deploy/releases/<nom>/infra/deploy/) ishga tushsa, loyiha papkasi shu yo'ldan olinadi va
# BUNDLE_DIR = reliz papkasi; loyiha papkasining o'zidan ishga tushsa BUNDLE_DIR bo'sh (fayllar allaqachon joyida)
SCRIPT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
case "$SCRIPT_ROOT" in
    */.deploy/releases/*)
        APP_DIR="${SCRIPT_ROOT%/.deploy/releases/*}"
        BUNDLE_DIR="$SCRIPT_ROOT"
        if [ -z "$APP_DIR" ] || [ "$APP_DIR/.deploy/releases/${BUNDLE_DIR##*/}" != "$BUNDLE_DIR" ]; then
            echo "reliz papkasi kutilmagan joyda: $BUNDLE_DIR" >&2
            exit 1
        fi
        ;;
    *)
        APP_DIR="$SCRIPT_ROOT"
        BUNDLE_DIR=""
        ;;
esac
cd "$APP_DIR"

STATE_DIR="$APP_DIR/.deploy"
RELEASES_DIR="$STATE_DIR/releases"
# Nechta eski reliz papkasi saqlanadi (joriy va avvalgisidan tashqari)
KEEP_RELEASES="${DEPLOY_KEEP_RELEASES:-3}"
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

# env_set KEY VALUE: qatorni almashtiradi yoki oxiriga qo'shadi. Atomar: yangi mazmun shu papkadagi vaqtinchalik faylga
# (avval .env nusxasi: ruxsatlar va egasi saqlanadi) yoziladi va `mv` bilan almashtiriladi: yozish o'rtasida uzilsa ham
# .env yarim qolmaydi (eski yoki yangi, to'liq)
env_set() {
    local key="$1" value="$2" tmp
    tmp="$(mktemp "$ENV_FILE.tmp.XXXXXX")"
    if cp -p "$ENV_FILE" "$tmp" \
        && { grep -vE "^${key}=" "$ENV_FILE" || true; } > "$tmp" \
        && printf '%s=%s\n' "$key" "$value" >> "$tmp" \
        && mv -f "$tmp" "$ENV_FILE"; then
        return 0
    fi
    rm -f "$tmp"
    die ".env ga yozib bo'lmadi ($key)"
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

# dc_files DIR ...: compose'ni DIR dagi compose fayllari bilan, lekin loyiha papkasi (.env, backend/.env, volume yo'llari,
# loyiha nomi) bilan ishlatadi. Fayllar almashtirilishidan OLDIN yangi relizni tekshirish va obrazlarni tortish uchun
dc_files() {
    local dir="$1" cf file list=""
    shift
    if [ "$dir" = "$APP_DIR" ]; then
        dc "$@"
        return
    fi
    cf="$(env_get COMPOSE_FILE)"
    # shellcheck disable=SC2086  # COMPOSE_FILE ":" bilan ajratilgan, fayl nomlarida bo'sh joy yo'q
    for file in ${cf//:/ }; do list="${list:+$list:}$dir/$file"; done
    COMPOSE_FILE="$list" docker compose --project-directory "$APP_DIR" "$@"
}

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

# Oldindan zaxira. Faqat haqiqiy birinchi deploy'da (hali hech qanday reliz joylanmagan va postgres konteyneri yo'q) o'tkazib
# yuboriladi. Bazada ma'lumot bo'lishi mumkin bo'lsa-yu, backup xizmati ishlamasa, deploy RAD ETILADI (hech narsa o'zgarmagan)
pre_backup() {
    local want backup_state
    want="$(env_get DEPLOY_BACKUP)"
    if [ -z "$want" ]; then
        if [ "$(env_get DEPLOY_TARGET)" = production ]; then want=1; else want=0; fi
    fi
    if [ "$want" != 1 ]; then
        if [ "$(env_get DEPLOY_TARGET)" = production ]; then
            warn "production'da oldindan zaxira o'chirilgan (.env: DEPLOY_BACKUP=$want): migratsiyadan oldin zaxira OLINMAYDI"
        else
            log "oldindan zaxira o'chiq (DEPLOY_BACKUP=$want)"
        fi
        return 0
    fi
    backup_state="$(service_state backup)"
    if [ "$backup_state" != running ] && [ "$backup_state" != healthy ]; then
        if [ -f "$STATE_DIR/current.env" ] || [ "$(service_state postgres)" != missing ]; then
            die "backup xizmati ishlamayapti ($backup_state), bazada esa ma'lumot bor: zaxirasiz deploy qilinmaydi (hech narsa o'zgarmagan). Tuzating: docker compose up -d backup; sababi: docker compose logs --tail=50 backup. Keyin deploy'ni qayta ishga tushiring"
        fi
        log "birinchi deploy (reliz ham, postgres konteyneri ham yo'q): oldindan zaxira o'tkazib yuborildi"
        return 0
    fi
    log "oldindan zaxira: backup.sh"
    dc exec -T backup sh /usr/local/bin/backup.sh || die "oldindan zaxira muvaffaqiyatsiz: deploy to'xtatildi (hech narsa o'zgarmagan). Sabab backup logida: docker compose logs backup"
}

# ---------------------------------------------------------------------------------------------------------------------
# Oldindan tekshiruv

# preflight TARGET [DIR]: DIR = tekshiriladigan fayllar (standart: loyiha papkasi; deploy'da: yangi reliz papkasi)
preflight() {
    local target="$1" dir="${2:-$APP_DIR}" cf file
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
        [ -f "$dir/$file" ] || die "COMPOSE_FILE dagi fayl yo'q: $dir/$file"
    done
    case ":$cf:" in
        *:docker-compose.edge.yml:*)
            [ -n "$(env_get EDGE_DOMAIN)" ] || die ".env da EDGE_DOMAIN bo'sh (rizqim.uz yoki stg.rizqim.uz)"
            [ -n "$(env_get EDGE_ACME_EMAIL)" ] || die ".env da EDGE_ACME_EMAIL bo'sh (Let's Encrypt uchun e-pochta)"
            [ -f "$dir/infra/edge/Caddyfile" ] || die "infra/edge/Caddyfile yo'q: $dir"
            ;;
    esac
    # Bo'sh yoki namuna qiymatli parollar bilan ishga tushmaylik
    case "$(env_get DB_PASSWORD)" in
        '' | secret | CHANGE_ME*) die ".env da DB_PASSWORD to'ldirilmagan" ;;
    esac
    # Production'da Redis parolsiz bo'lmasin (navbat, sessiya, kesh shu yerda; compose tarmog'idagi har konteyner unga ulana oladi)
    if [ "$target" = production ]; then
        case "$(env_get REDIS_PASSWORD)" in
            '' | null | secret | CHANGE_ME*) die ".env da REDIS_PASSWORD to'ldirilmagan (production'da shart: openssl rand -hex 24)" ;;
        esac
    fi
    [ -n "$(grep -E '^APP_KEY=base64:.+' "$APP_DIR/backend/.env" || true)" ] || die "backend/.env da APP_KEY yo'q (echo \"base64:\$(openssl rand -base64 32)\")"
    dc_files "$dir" config -q || die "docker compose config xato"
}

# Token faqat shu jarayonning vaqtinchalik DOCKER_CONFIG papkasiga (mktemp -d, 0700) yoziladi: ~/.docker/config.json ga
# tushmaydi va deploy qanday tugamasin (muvaffaqiyat, xato, SSH uzilishi = HUP, INT, TERM) cleanup() uni o'chiradi
TMP_DOCKER_CONFIG=""
registry_login() {
    local user="$1" token plugins
    [ -n "$user" ] || return 0
    IFS= read -r token || true
    if [ -z "$token" ]; then
        warn "--registry-user berilgan, lekin STDIN'da token yo'q: login qilinmadi"
        return 0
    fi
    plugins="${DOCKER_CONFIG:-$HOME/.docker}/cli-plugins"
    TMP_DOCKER_CONFIG="$(mktemp -d)"
    # docker compose foydalanuvchi papkasiga (~/.docker/cli-plugins) o'rnatilgan bo'lsa ham topilsin
    [ ! -d "$plugins" ] || ln -s "$plugins" "$TMP_DOCKER_CONFIG/cli-plugins"
    export DOCKER_CONFIG="$TMP_DOCKER_CONFIG"
    printf '%s\n' "$token" | docker login "$REGISTRY_HOST" -u "$user" --password-stdin > /dev/null || die "$REGISTRY_HOST ga kirib bo'lmadi"
    token=""
    log "$REGISTRY_HOST ga kirildi (vaqtinchalik DOCKER_CONFIG)"
}

cleanup() {
    if [ -n "$TMP_DOCKER_CONFIG" ] && [ -d "$TMP_DOCKER_CONFIG" ]; then
        docker logout "$REGISTRY_HOST" > /dev/null 2>&1 || true
        rm -rf -- "${TMP_DOCKER_CONFIG:?}"
        TMP_DOCKER_CONFIG=""
    fi
}

# ---------------------------------------------------------------------------------------------------------------------
# Reliz holati (.deploy/current.env, previous.env)

# save_state FILE [RELIZ_PAPKASI_NOMI]: current.env (+ current.dir) -> previous.*, yangi reliz -> current.*
save_state() {
    local release_file="$1" dir_name="${2:-}"
    mkdir -p "$STATE_DIR"
    if [ -f "$STATE_DIR/current.env" ] && ! cmp -s "$STATE_DIR/current.env" "$release_file"; then
        cp "$STATE_DIR/current.env" "$STATE_DIR/previous.env"
        if [ -f "$STATE_DIR/current.dir" ]; then
            cp "$STATE_DIR/current.dir" "$STATE_DIR/previous.dir"
        else
            rm -f "$STATE_DIR/previous.dir"
        fi
    fi
    cp "$release_file" "$STATE_DIR/current.env"
    if [ -n "$dir_name" ]; then
        printf '%s\n' "$dir_name" > "$STATE_DIR/current.dir"
    else
        rm -f "$STATE_DIR/current.dir"
    fi
}

# release_dir_of current|previous: shu relizning papkasi (saqlangan bo'lsa), aks holda bo'sh satr
release_dir_of() {
    local name
    [ -f "$STATE_DIR/$1.dir" ] || return 0
    name="$(head -n 1 "$STATE_DIR/$1.dir")"
    case "$name" in '' | . | .. | */*) return 0 ;; esac
    if [ -d "$RELEASES_DIR/$name" ]; then printf '%s' "$RELEASES_DIR/$name"; fi
    return 0
}

# ---------------------------------------------------------------------------------------------------------------------
# Fayllarni almashtirish (reliz papkasi -> loyiha papkasi)

# install_files DIR: DIR dagi hamma faylni loyiha papkasiga ko'chiradi va avvalgi o'rnatilgan relizda bo'lib, bunda yo'q fayllarni
# o'chiradi. Faqat reliz bilan kelgan fayllarga tegiladi: .env, backend/.env, infra/edge/conf.d/*.caddy, infra/edge/admin/*.caddy,
# backups/ va boshqa serverdagi fayllar hech qachon o'chirilmaydi. Papkalar o'rnida qoladi (edge konteyneri infra/edge ni papka
# sifatida ulaydi va yangi fayllarni darhol ko'radi; keyin reload_edge)
install_files() {
    local dir="$1" list f
    list="$(mktemp "$STATE_DIR/files.XXXXXX")"
    (cd "$dir" && find . -type f -print | sed 's#^\./##' | LC_ALL=C sort) > "$list"
    if [ ! -s "$list" ]; then
        rm -f "$list"
        die "reliz papkasi bo'sh: $dir"
    fi
    if ! (cd "$dir" && tar -cf - -T "$list") | tar -C "$APP_DIR" -xf -; then
        rm -f "$list"
        die "fayllarni ko'chirib bo'lmadi: $dir"
    fi
    if [ -f "$STATE_DIR/installed.list" ]; then
        LC_ALL=C comm -23 "$STATE_DIR/installed.list" "$list" | while IFS= read -r f; do
            case "$f" in '' | /* | *..*) continue ;; esac
            rm -f -- "${APP_DIR:?}/${f:?}"
        done
    fi
    mv -f "$list" "$STATE_DIR/installed.list"
    log "fayllar almashtirildi: ${dir#"$APP_DIR"/}"
}

# Reliz papkalari nomlari, yangisi birinchi
list_releases() {
    [ -d "$RELEASES_DIR" ] || return 0
    find "$RELEASES_DIR" -mindepth 1 -maxdepth 1 -type d -printf '%T@ %f\n' | sort -rn | cut -d' ' -f2-
}

# Eski reliz papkalarini tozalash: joriy, avvalgi, hozir ishlayotgan va eng yangi KEEP_RELEASES tasi qoladi
prune_releases() {
    local keep_cur keep_prev name d n=0
    [ -d "$RELEASES_DIR" ] || return 0
    keep_cur="$(release_dir_of current)"
    keep_prev="$(release_dir_of previous)"
    while IFS= read -r name; do
        case "$name" in '' | . | .. | */*) continue ;; esac
        d="$RELEASES_DIR/$name"
        if [ "$d" = "$keep_cur" ] || [ "$d" = "$keep_prev" ] || [ "$d" = "$BUNDLE_DIR" ]; then continue; fi
        n=$((n + 1))
        [ "$n" -le "$KEEP_RELEASES" ] && continue
        rm -rf -- "${RELEASES_DIR:?}/${name:?}"
    done < <(list_releases)
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

# Edge konteyneri `up -d` da faqat obrazi yoki compose sozlamasi o'zgarsa qayta yaratiladi: Caddyfile (va admin/, conf.d/)
# o'zgarishi o'zi qo'llanmaydi. Shuning uchun har deploy va qaytarishda reload: Caddy yangi faylni tekshiradi va faqat to'g'ri
# bo'lsa almashtiradi (xato bo'lsa eski sozlama ishlashda davom etadi, reliz esa yaroqsiz deb qaytariladi)
reload_edge() {
    local i
    list_services | grep -qx edge || return 0
    for i in 1 2 3 4 5; do
        if dc exec -T edge caddy reload --config /etc/caddy/Caddyfile --adapter caddyfile; then
            log "edge: caddy reload (Caddyfile qo'llandi)"
            return 0
        fi
        # Edge hozirgina qayta yaratilgan bo'lsa admin API hali tayyor emas
        [ "$i" -lt 5 ] && sleep 3
    done
    warn "edge: caddy reload muvaffaqiyatsiz (Caddyfile xato?): docker compose logs --tail=50 edge"
    return 1
}

# apply_release FILE: .env ga yozadi va xizmatlarni shu reliz bilan ko'taradi. `up -d` backend healthy bo'lishini kutadi
# (depends_on): healthcheck o'tmasa u nolga teng bo'lmagan kod bilan chiqadi. So'ng edge joriy Caddyfile'ni oladi
apply_release() {
    local file="$1"
    export_release "$file"
    apply_release_to_env "$file"
    log "up -d (tag $(release_get IMAGE_TAG "$file"))"
    dc up -d --no-build --remove-orphans || return 1
    reload_edge
}

verify_release() {
    wait_all_ready || return 1
    run_migrations || { warn "migrate --force muvaffaqiyatsiz"; return 1; }
    internal_checks || return 1
    public_checks || return 1
    return 0
}

# rollback_to FILE [RELIZ_PAPKASI]: shu relizga qaytaradi (tayyor bo'lishini kutadi). Papka berilsa avval uning fayllari
# (compose, scripts/, infra/edge/Caddyfile ...) qaytariladi; papka yo'q bo'lsa (eski reliz) faqat obrazlar qaytadi
rollback_to() {
    local prev="$1" prev_dir="${2:-}"
    if [ ! -f "$prev" ]; then
        warn "qaytariladigan reliz yo'q (birinchi deploy?): $prev. Loglar: docker compose logs --tail=100"
        return 1
    fi
    summary "ORQAGA QAYTARISH" "$prev"
    validate_release_file "$prev"
    if [ -n "$prev_dir" ]; then
        install_files "$prev_dir"
    elif [ -n "$BUNDLE_DIR" ]; then
        warn "avvalgi relizning fayllari saqlanmagan: faqat obrazlar qaytariladi (fayllar yangi relizniki bo'lib qoladi)"
    fi
    if apply_release "$prev" && wait_all_ready && internal_checks; then
        log "orqaga qaytarish muvaffaqiyatli: tizim avvalgi versiyada ishlayapti"
        return 0
    fi
    warn "orqaga qaytarishdan keyin ham tizim sog'lom emas: qo'lda aralashuv kerak (docker compose ps; docker compose logs --tail=100)"
    return 1
}

cmd_deploy() {
    local release_file="" registry_user="" target files_dir back_dir
    while [ $# -gt 0 ]; do
        case "$1" in
            --release-file) release_file="${2:?--release-file qiymati kerak}"; shift 2 ;;
            --registry-user) registry_user="${2:?--registry-user qiymati kerak}"; shift 2 ;;
            *) die "noma'lum argument: $1" ;;
        esac
    done
    # Tekshiriladigan va joylanadigan fayllar: reliz papkasi (CI) yoki loyiha papkasining o'zi (qo'lda)
    files_dir="${BUNDLE_DIR:-$APP_DIR}"
    [ -n "$release_file" ] || release_file="$files_dir/infra/deploy/release.env"
    case "$release_file" in /*) ;; *) release_file="$APP_DIR/$release_file" ;; esac
    validate_release_file "$release_file"
    target="$(release_get DEPLOY_TARGET "$release_file")"
    case "$target" in staging | production) ;; *) die "DEPLOY_TARGET staging yoki production bo'lishi kerak: $target" ;; esac

    # 1-3-qadamlar: shu yergacha ishlayotgan fayllar va konteynerlarga tegilmaydi
    preflight "$target" "$files_dir"
    summary "yangi reliz" "$release_file"
    summary "joriy reliz" "$STATE_DIR/current.env"

    registry_login "$registry_user"

    # Yangi obrazlar shu yerda, yangi relizning compose fayllari bilan tortiladi: xato bo'lsa (tarmoq, ruxsat, yo'q tag) hech
    # qaysi konteynerga tegilmagan bo'ladi. Ular ildiz .env ga yozilmaguncha jarayon muhitida beriladi.
    export_release "$release_file"
    log "obrazlarni tortish"
    dc_files "$files_dir" pull --quiet || die "obrazlarni tortib bo'lmadi: deploy to'xtatildi (hech narsa o'zgarmagan)"

    pre_backup

    # 4-qadam: tekshiruvlar o'tdi, endi ishlayotgan fayllar almashtiriladi (qaytarish uchun joriy reliz papkasi oldindan olinadi)
    back_dir="$(release_dir_of current)"
    [ -z "$BUNDLE_DIR" ] || install_files "$BUNDLE_DIR"

    if apply_release "$release_file" && verify_release; then
        save_state "$release_file" "${BUNDLE_DIR##*/}"
        prune_releases
        docker image prune -f > /dev/null 2>&1 || true
        log "DEPLOY MUVAFFAQIYATLI: $(release_get GIT_SHA "$release_file")"
        return 0
    fi

    warn "yangi reliz sog'lom emas: oxirgi muvaffaqiyatli relizga qaytariladi"
    docker ps --format 'table {{.Names}}\t{{.Status}}' >&2 || true
    dc logs --tail=40 backend >&2 || true
    # Oxirgi yaxshi reliz = joriy (current.env). Xuddi shu relizni qayta joylash muvaffaqiyatsiz bo'lsa, undan oldingisi
    local back="$STATE_DIR/current.env"
    if [ ! -f "$back" ] || cmp -s "$back" "$release_file"; then
        back="$STATE_DIR/previous.env"
        back_dir="$(release_dir_of previous)"
    fi
    if rollback_to "$back" "$back_dir"; then
        die "DEPLOY MUVAFFAQIYATSIZ, tizim avvalgi versiyaga qaytarildi ($(release_get GIT_SHA "$back"))"
    fi
    die "DEPLOY MUVAFFAQIYATSIZ va avtomatik qaytarish ham bo'lmadi"
}

cmd_rollback() {
    local target prev_dir
    [ -f "$STATE_DIR/previous.env" ] || die "avvalgi reliz yo'q: $STATE_DIR/previous.env"
    target="$(release_get DEPLOY_TARGET "$STATE_DIR/previous.env")"
    prev_dir="$(release_dir_of previous)"
    preflight "$target" "${prev_dir:-$APP_DIR}"
    # Avvalgi obrazlar serverda qolgan bo'lishi kerak; yo'q bo'lsa `up` ularni o'zi tortadi (login kerak bo'lishi mumkin)
    rollback_to "$STATE_DIR/previous.env" "$prev_dir" || die "qaytarish bo'lmadi"
    # Joriy va avvalgi o'rin almashadi (reliz fayli ham, papka ko'rsatkichi ham): yana rollback qilsa oldingi relizga qaytadi
    local tmp ext
    tmp="$(mktemp)"
    for ext in env dir; do
        if [ -f "$STATE_DIR/current.$ext" ]; then cp "$STATE_DIR/current.$ext" "$tmp"; else : > "$tmp"; fi
        if [ -f "$STATE_DIR/previous.$ext" ]; then
            cp "$STATE_DIR/previous.$ext" "$STATE_DIR/current.$ext"
        else
            rm -f "$STATE_DIR/current.$ext"
        fi
        if [ -s "$tmp" ]; then cp "$tmp" "$STATE_DIR/previous.$ext"; else rm -f "$STATE_DIR/previous.$ext"; fi
    done
    rm -f "$tmp"
    log "joriy reliz endi: $(release_get GIT_SHA "$STATE_DIR/current.env")"
}

# Birinchi o'rnatish (CI only_upload): reliz papkasidagi fayllarni loyiha papkasiga ko'chiradi, toki serverda .env namunalari
# paydo bo'lsin. Reliz joylangan serverda (current.env bor) rad etadi: ishlayotgan fayllar faqat `deploy` bilan, tekshiruvlardan
# keyin almashtiriladi
cmd_install_files() {
    [ -n "$BUNDLE_DIR" ] || die "install-files faqat reliz papkasidan ishga tushiriladi (.deploy/releases/<tag>/infra/deploy/deploy.sh)"
    [ ! -f "$STATE_DIR/current.env" ] || die "bu serverda reliz allaqachon joylangan ($(release_get GIT_SHA "$STATE_DIR/current.env")): fayllar faqat deploy orqali almashtiriladi"
    install_files "$BUNDLE_DIR"
    log "fayllar $APP_DIR ga ko'chirildi. Keyingi qadam: .env va backend/.env (docs/SERVER_SETUP.md), so'ng oddiy deploy"
}

cmd_status() {
    summary "joriy reliz" "$STATE_DIR/current.env"
    summary "avvalgi reliz" "$STATE_DIR/previous.env"
    log "reliz papkalari (.deploy/releases, yangisi birinchi): $(list_releases | tr '\n' ' ')"
    dc ps
}

main() {
    local cmd="${1:-}"
    [ -n "$cmd" ] || die "buyruq kerak: deploy | rollback | status | install-files"
    shift
    mkdir -p "$STATE_DIR"
    case "$cmd" in
        status) cmd_status "$@"; return 0 ;;
        deploy | rollback | install-files) ;;
        *) die "noma'lum buyruq: $cmd (deploy | rollback | status | install-files)" ;;
    esac
    # Vaqtinchalik registry tokeni har qanday chiqishda o'chirilsin: signal bilan tugaganda ham EXIT trap ishlashi uchun
    # signallar `exit` ga aylantiriladi (SSH uzilsa sshd HUP yuboradi)
    trap cleanup EXIT
    trap 'exit 129' HUP
    trap 'exit 130' INT
    trap 'exit 143' TERM
    # Bir vaqtda bitta deploy
    exec 9> "$STATE_DIR/lock"
    flock -n 9 || die "boshqa deploy ishlayapti"
    "cmd_${cmd//-/_}" "$@"
}

main "$@"
