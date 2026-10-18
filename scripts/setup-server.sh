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

echo "O'rnatish yakunlandi!"
echo "Iltimos, Nginx konfiguratsiya fayllarini (/etc/nginx/sites-available/) joylashtiring va PostgreSQL da kerakli DB hamda foydalanuvchini yarating."
