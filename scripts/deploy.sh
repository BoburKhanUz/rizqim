#!/bin/bash

# Loyihani serverda yangilash (Deploy) skripti — VPS varianti
# Ushbu skriptni loyiha papkasida ishga tushiring (/var/www/order-mini-app)
# Docker varianti: `docker compose up -d --build` (xuddi shu backend qadamlarini backend/docker/entrypoint.sh bajaradi)

# Birorta qadam xato bersa, deploy to'xtaydi (yarim yangilangan holat bilan davom etmaydi)
set -euo pipefail

# Hozirgi PHP versiyasiga mos php-fpm xizmati (setup-server.sh o'rnatgan versiya), masalan php8.3-fpm
PHP_FPM_SERVICE="php$(php -r 'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;')-fpm"

echo "Git dan so'nggi o'zgarishlar olinmoqda..."
git pull origin main

echo "==== BACKEND (Laravel) YANGILANMOQDA ===="
cd backend

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

# optimize:clear kesh bazasini (Redis) to'liq tozalaydi; navbat va sessiyalar boshqa Redis bazasida (REDIS_DB) qoladi
echo "Keshlar tozalanmoqda..."
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "Telegram webhook ro'yxatdan o'tkazilmoqda (secret_token bilan)..."
php artisan telegram:set-webhook --if-configured \
    || echo "OGOHLANTIRISH: Telegram webhook o'rnatilmadi — 'php artisan telegram:set-webhook' ni qo'lda ishga tushiring."

# After optimize:clear, which also clears the app cache where the restart signal is stored
echo "Navbat (queue) ishchilari yangi kod bilan qayta ishga tushirilmoqda..."
php artisan queue:restart

cd ..

echo "==== FRONTEND (React/Vite) YANGILANMOQDA ===="
cd mini-app

echo "NPM paketlari o'rnatilmoqda (npm ci: Docker build bilan bir xil, package-lock.json bo'yicha)..."
npm ci

echo "Loyiha build qilinmoqda (Production uchun)..."
npm run build

cd ..

echo "Papkalar huquqlari to'g'rilanmoqda (Nginx/www-data uchun)..."
sudo chown -R www-data:www-data backend/storage backend/bootstrap/cache
sudo chmod -R 775 backend/storage backend/bootstrap/cache

echo "Nginx va PHP-FPM qayta ishga tushirilmoqda..."
sudo systemctl restart nginx
sudo systemctl restart "$PHP_FPM_SERVICE"

# Docker'dagi queue va scheduler xizmatlarining VPS'dagi o'xshashlari (scripts/setup-server.sh o'rnatadi)
if ! systemctl is-active --quiet rizqim-queue.service; then
    echo "OGOHLANTIRISH: rizqim-queue xizmati ishlamayapti (navbat ishchisi yo'q) — 'sudo systemctl enable --now rizqim-queue' yoki scripts/setup-server.sh ni ishga tushiring." >&2
fi
if [ ! -f /etc/cron.d/rizqim-scheduler ]; then
    echo "OGOHLANTIRISH: scheduler cron fayli yo'q (to'lanmagan buyurtmalar bekor qilinmaydi) — scripts/setup-server.sh ni ishga tushiring." >&2
fi

echo "DEPLOY MUVAFFAQIYATLI YAKUNLANDI! 🎉"
