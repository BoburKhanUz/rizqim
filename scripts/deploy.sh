#!/bin/bash

# Loyihani serverda yangilash (Deploy) skripti — VPS varianti
# Ushbu skriptni loyiha papkasida ishga tushiring (/var/www/order-mini-app), oddiy deploy foydalanuvchisi nomidan
# (sudo huquqi kerak: chown/chmod/systemctl). Papka huquqlari tartibi: docs/DEPLOY.md, 4-bo'lim.
# Docker varianti: `docker compose up -d --build` (xuddi shu backend qadamlarini backend/docker/entrypoint.sh bajaradi)

# Birorta qadam xato bersa, deploy to'xtaydi (yarim yangilangan holat bilan davom etmaydi)
set -euo pipefail

# Skript yaratgan fayllar guruh uchun ham yoziladigan bo'lsin (php-fpm, navbat va cron www-data nomidan ishlaydi)
umask 002

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BACKEND_DIR="$ROOT_DIR/backend"
cd "$ROOT_DIR"

# php-fpm, navbat ishchilari va cron ishlaydigan foydalanuvchi/guruh (setup-server.sh dagi APP_USER bilan bir xil)
APP_USER="${APP_USER:-www-data}"
APP_GROUP="${APP_GROUP:-$APP_USER}"
DEPLOY_USER="$(id -un)"

# Hozirgi PHP versiyasiga mos php-fpm xizmati (setup-server.sh o'rnatgan versiya), masalan php8.3-fpm
PHP_FPM_SERVICE="php$(php -r 'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;')-fpm"

# storage/ va bootstrap/cache/ ga ikki tomon yozadi: skript (deploy foydalanuvchisi: loglar, view/config/route keshlari)
# va php-fpm/navbat/cron (www-data). Shuning uchun egasi deploy foydalanuvchisi, guruhi www-data, guruh yoza oladi va
# yangi fayllar guruhni meros qilib oladi (setgid; umask 002 fayllarni guruh uchun yoziladigan qiladi). Eski tartib
# (egasi www-data, 775) deploy foydalanuvchisining composer/artisan'ini ikkinchi deploydan boshlab to'sib qo'yardi.
# php-fpm/cron yaratgan fayllar (www-data:www-data, 644) deploy foydalanuvchisi uchun yopiq bo'lib qoladi, shuning uchun
# bu qadam har deploy boshida va php-fpm qayta ishga tushishidan oldin takrorlanadi.
fix_runtime_permissions() {
    mkdir -p "$BACKEND_DIR/storage" "$BACKEND_DIR/bootstrap/cache"
    sudo chown -R "${DEPLOY_USER}:${APP_GROUP}" "$BACKEND_DIR/storage" "$BACKEND_DIR/bootstrap/cache"
    sudo chmod -R ug+rwX "$BACKEND_DIR/storage" "$BACKEND_DIR/bootstrap/cache"
    sudo find "$BACKEND_DIR/storage" "$BACKEND_DIR/bootstrap/cache" -type d -exec chmod g+s {} +
}

# Texnik ish rejimi: git pull dan keshlar yig'ilgunicha yangi kod eski bazada ishlamasin (aks holda, masalan, yetkazilgan
# buyurtmalar hisob-kitob yozuvisiz qolishi mumkin). Bu vaqtda API 503 + Retry-After: 15 qaytaradi; Telegram, Click va
# Payme 503 olgan so'rovni qayta yuboradi, navbat ishchilari esa ilova `up` bo'lguncha job olmaydi. Qisqa oyna shu bilan
# yopiladi. Skript xato bilan to'xtasa ham ilova trap orqali `up` qilinadi (texnik rejimda abadiy qolmasin).
MAINTENANCE=0

app_up() {
    if [ "$MAINTENANCE" = 1 ]; then
        if (cd "$BACKEND_DIR" && php artisan up); then
            MAINTENANCE=0
        else
            echo "OGOHLANTIRISH: 'php artisan up' bajarilmadi — ilova texnik rejimda qoldi, qo'lda bajaring: cd $BACKEND_DIR && php artisan up" >&2
        fi
    fi
}

on_exit() {
    local status=$?
    trap - EXIT
    if [ "$status" -ne 0 ]; then
        echo "DEPLOY XATO BILAN TUGADI (kod ${status})." >&2
        if [ "$MAINTENANCE" = 1 ]; then
            echo "Ilova qayta yoqilmoqda. Xato git pull dan keyin chiqqan bo'lsa, yangi kod eski bazada ishlayotgan bo'lishi mumkin: xatoni tuzatib, skriptni zudlik bilan qayta ishga tushiring." >&2
        fi
    fi
    app_up
    exit "$status"
}
trap on_exit EXIT
trap 'exit 130' INT
trap 'exit 143' TERM
trap 'exit 129' HUP

echo "Papkalar huquqlari to'g'rilanmoqda (storage, bootstrap/cache)..."
fix_runtime_permissions

# Birinchi deployda vendor/ hali yo'q: ilovani ishga tushirib bo'lmaydi va yurgizilayotgan narsa ham yo'q
if [ -f "$BACKEND_DIR/vendor/autoload.php" ]; then
    echo "Texnik ish rejimi yoqilmoqda (php artisan down --retry=15)..."
    if (cd "$BACKEND_DIR" && php artisan down --retry=15); then
        MAINTENANCE=1
    else
        echo "OGOHLANTIRISH: texnik rejim yoqilmadi (joriy kod ishga tushmayapti) — deploy shunday davom etadi." >&2
    fi
fi

echo "Git dan so'nggi o'zgarishlar olinmoqda..."
git pull origin main

echo "==== BACKEND (Laravel) YANGILANMOQDA ===="
cd "$BACKEND_DIR"

echo "Composer paketlari o'rnatilmoqda..."
composer install --optimize-autoloader --no-dev

echo "Production xavfsizlik sozlamalari tekshirilmoqda (APP_ENV, APP_DEBUG, APP_KEY, TELEGRAM_ALLOW_MOCK_AUTH, TELEGRAM_WEBHOOK_SECRET, TELEGRAM_WEBAPP_URL, super admin paroli)..."
php artisan config:clear
if ! php artisan app:check-production; then
    echo "DEPLOY TO'XTATILDI: backend/.env xavfsiz emas. Yuqoridagi sozlamalarni tuzatib, qayta ishga tushiring."
    exit 1
fi

echo "Ma'lumotlar bazasi migratsiyalari ishga tushirilmoqda..."
php artisan migrate --force

echo "Public disk havolasi (storage:link) tekshirilmoqda..."
php artisan storage:link || true

# optimize:clear kesh bazasini (Redis) to'liq tozalaydi; navbat va sessiyalar boshqa Redis bazasida (REDIS_DB) qoladi.
# Texnik rejim fayl drayverida (APP_MAINTENANCE_DRIVER=file, standart) turadi, shuning uchun tozalash uni o'chirmaydi.
echo "Keshlar tozalanmoqda..."
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# After optimize:clear, which also clears the app cache where the restart signal is stored. Still in maintenance mode:
# workers (queue and queue-reports; the signal restarts all of them) are paused now, notice the signal, exit and
# systemd starts them with the new code, so none of them runs old code on the new schema after `up`
echo "Navbat (queue) ishchilari yangi kod bilan qayta ishga tushirilmoqda..."
php artisan queue:restart

cd "$ROOT_DIR"

# php-fpm yangi kod va keshlar bilan `up` dan oldin qayta ishga tushadi (eski opcache qolmasin)
echo "Papkalar huquqlari to'g'rilanmoqda va PHP-FPM qayta ishga tushirilmoqda..."
fix_runtime_permissions
sudo systemctl restart "$PHP_FPM_SERVICE"

echo "Texnik ish rejimi o'chirilmoqda (php artisan up)..."
app_up

cd "$BACKEND_DIR"
echo "Telegram webhook ro'yxatdan o'tkazilmoqda (secret_token bilan)..."
php artisan telegram:set-webhook --if-configured \
    || echo "OGOHLANTIRISH: Telegram webhook o'rnatilmadi — 'php artisan telegram:set-webhook' ni qo'lda ishga tushiring."

cd "$ROOT_DIR"

echo "==== FRONTEND (React/Vite) YANGILANMOQDA ===="
cd mini-app

# puppeteer (faqat qo'lda ishlatiladigan test-browser.js uchun) npm ci paytida Chrome yuklab olmasin
echo "NPM paketlari o'rnatilmoqda (npm ci: Docker build bilan bir xil, package-lock.json bo'yicha)..."
PUPPETEER_SKIP_DOWNLOAD=1 npm ci

echo "Loyiha build qilinmoqda (Production uchun)..."
npm run build

cd "$ROOT_DIR"

echo "Nginx qayta ishga tushirilmoqda..."
sudo systemctl restart nginx

# Docker'dagi queue, queue-reports va scheduler xizmatlarining VPS'dagi o'xshashlari (scripts/setup-server.sh o'rnatadi)
if ! systemctl is-active --quiet rizqim-queue.service; then
    echo "OGOHLANTIRISH: rizqim-queue xizmati ishlamayapti (navbat ishchisi yo'q) — 'sudo systemctl enable --now rizqim-queue' yoki scripts/setup-server.sh ni ishga tushiring." >&2
fi
if ! systemctl is-active --quiet rizqim-queue-reports.service; then
    echo "OGOHLANTIRISH: rizqim-queue-reports xizmati ishlamayapti — hisobotlar (CSV) navbatda qotib qoladi. Eski serverda scripts/setup-server.sh ni qayta ishga tushiring (xavfsiz): u ikkala xizmatni yangilaydi." >&2
fi
if [ ! -f /etc/cron.d/rizqim-scheduler ]; then
    echo "OGOHLANTIRISH: scheduler cron fayli yo'q (to'lanmagan buyurtmalar bekor qilinmaydi) — scripts/setup-server.sh ni ishga tushiring." >&2
fi

echo "DEPLOY MUVAFFAQIYATLI YAKUNLANDI! 🎉"
