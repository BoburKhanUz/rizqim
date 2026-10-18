#!/bin/bash

# Serverga asosiy dasturlarni o'rnatish uchun yordamchi skript (Ubuntu uchun)
# Eslatma: Ushbu skriptni serverda root yoki sudo huquqi bilan ishga tushiring!

echo "Server yangilanmoqda..."
sudo apt update && sudo apt upgrade -y

echo "Nginx o'rnatilmoqda..."
sudo apt install nginx -y

echo "PHP 8.2 va kengaytmalar o'rnatilmoqda..."
sudo apt install software-properties-common -y
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install php8.2-fpm php8.2-cli php8.2-pgsql php8.2-curl php8.2-mbstring php8.2-xml php8.2-zip php8.2-bcmath -y

echo "Composer o'rnatilmoqda..."
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

echo "Node.js 20 va NPM o'rnatilmoqda..."
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs

echo "Ma'lumotlar bazasi sifatida PostgreSQL o'rnatilmoqda..."
sudo apt install postgresql postgresql-contrib -y

# Loyiha joylashuvi (deploy.sh shu papkada ishga tushiriladi) va ilova foydalanuvchisi
APP_DIR="${APP_DIR:-/var/www/order-mini-app}"
APP_USER="${APP_USER:-www-data}"

echo "Laravel scheduler (har daqiqada schedule:run) cron orqali sozlanmoqda..."
sudo apt install cron -y
echo "* * * * * ${APP_USER} cd ${APP_DIR}/backend && /usr/bin/php artisan schedule:run >> /dev/null 2>&1" \
    | sudo tee /etc/cron.d/rizqim-scheduler > /dev/null
sudo chmod 644 /etc/cron.d/rizqim-scheduler

echo "Navbat ishchisi (queue:work) systemd xizmati sifatida sozlanmoqda..."
sudo tee /etc/systemd/system/rizqim-queue.service > /dev/null <<EOF
[Unit]
Description=Rizqim Laravel queue worker
After=network.target postgresql.service

[Service]
User=${APP_USER}
Group=${APP_USER}
WorkingDirectory=${APP_DIR}/backend
ExecStart=/usr/bin/php artisan queue:work --tries=3 --max-time=3600
# --max-time and queue:restart (deploy.sh) make the worker exit; systemd starts it again with fresh code
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
EOF
sudo systemctl daemon-reload
sudo systemctl enable --now rizqim-queue.service

echo "O'rnatish yakunlandi!"
echo "Iltimos, Nginx konfiguratsiya fayllarini (/etc/nginx/sites-available/) joylashtiring va PostgreSQL da kerakli DB hamda foydalanuvchini yarating."
