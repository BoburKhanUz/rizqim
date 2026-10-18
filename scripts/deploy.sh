#!/bin/bash

# Loyihani serverda yangilash (Deploy) skripti
# Ushbu skriptni loyiha papkasida ishga tushiring (/var/www/order-mini-app)

echo "Git dan so'nggi o'zgarishlar olinmoqda..."
git pull origin main

echo "==== BACKEND (Laravel) YANGILANMOQDA ===="
cd backend

echo "Composer paketlari o'rnatilmoqda..."
composer install --optimize-autoloader --no-dev

echo "Production xavfsizlik sozlamalari tekshirilmoqda (APP_ENV, APP_DEBUG, TELEGRAM_ALLOW_MOCK_AUTH, TELEGRAM_WEBHOOK_SECRET, super admin paroli)..."
php artisan config:clear
if ! php artisan app:check-production; then
    echo "DEPLOY TO'XTATILDI: backend/.env xavfsiz emas. Yuqoridagi sozlamalarni tuzatib, qayta ishga tushiring."
    exit 1
fi

echo "Ma'lumotlar bazasi migratsiyalari ishga tushirilmoqda..."
php artisan migrate --force

echo "Keshlar tozalanmoqda..."
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# After optimize:clear, which also clears the app cache where the restart signal is stored
echo "Navbat (queue) ishchilari yangi kod bilan qayta ishga tushirilmoqda..."
php artisan queue:restart

echo "Telegram webhook ro'yxatdan o'tkazilmoqda (secret_token bilan)..."
php artisan telegram:set-webhook --if-configured \
    || echo "OGOHLANTIRISH: Telegram webhook o'rnatilmadi — 'php artisan telegram:set-webhook' ni qo'lda ishga tushiring."

cd ..

echo "==== FRONTEND (React/Vite) YANGILANMOQDA ===="
cd mini-app

echo "NPM paketlari o'rnatilmoqda..."
npm install

echo "Loyiha build qilinmoqda (Production uchun)..."
npm run build

cd ..

echo "Papkalar huquqlari to'g'rilanmoqda (Nginx/www-data uchun)..."
sudo chown -R www-data:www-data backend/storage backend/bootstrap/cache
sudo chmod -R 775 backend/storage backend/bootstrap/cache

echo "Nginx va PHP-FPM qayta ishga tushirilmoqda..."
sudo systemctl restart nginx
sudo systemctl restart php8.2-fpm # PHP versiyangizga qarab o'zgartiring

echo "DEPLOY MUVAFFAQIYATLI YAKUNLANDI! 🎉"
