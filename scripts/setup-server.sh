#!/bin/bash

# Serverga asosiy dasturlarni o'rnatish uchun yordamchi skript (Ubuntu uchun) — VPS varianti.
# Eslatma: Ushbu skriptni serverda root yoki sudo huquqi bilan ishga tushiring!
# Skriptni qayta ishga tushirish xavfsiz: o'rnatilganlar o'zgarmaydi, sozlamalar bir xil holatga keltiriladi.
#
# Docker varianti (docker-compose.yml) bilan bir xil versiyalar: PHP 8.3 (composer.lock 8.3+ talab qiladi), PostgreSQL 17,
# Redis (kesh, navbat, sessiya), Node.js 20. Versiyalarni o'zgartirish: PHP_VERSION=8.4 PG_VERSION=17 ./setup-server.sh

set -euo pipefail

PHP_VERSION="${PHP_VERSION:-8.3}"
PG_VERSION="${PG_VERSION:-17}"
PHP_BIN="/usr/bin/php${PHP_VERSION}"

# Loyiha joylashuvi (deploy.sh shu papkada ishga tushiriladi) va ilova foydalanuvchisi
APP_DIR="${APP_DIR:-/var/www/order-mini-app}"
APP_USER="${APP_USER:-www-data}"

echo "Server yangilanmoqda..."
sudo apt update && sudo apt upgrade -y

echo "Nginx o'rnatilmoqda..."
sudo apt install nginx -y

echo "PHP ${PHP_VERSION} va kengaytmalar o'rnatilmoqda (backend/Dockerfile dagi to'plam bilan bir xil + redis)..."
sudo apt install software-properties-common -y
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install -y "php${PHP_VERSION}-fpm" "php${PHP_VERSION}-cli" "php${PHP_VERSION}-pgsql" "php${PHP_VERSION}-curl" \
    "php${PHP_VERSION}-mbstring" "php${PHP_VERSION}-xml" "php${PHP_VERSION}-zip" "php${PHP_VERSION}-bcmath" \
    "php${PHP_VERSION}-gd" "php${PHP_VERSION}-intl" "php${PHP_VERSION}-opcache" "php${PHP_VERSION}-redis"
# composer va deploy.sh "php" ni PATH dan oladi: u systemd/cron dagi PHP_BIN bilan bir xil versiya bo'lsin
sudo update-alternatives --set php "$PHP_BIN" \
    || echo "OGOHLANTIRISH: 'php' buyrug'i PHP ${PHP_VERSION} ga o'tkazilmadi — 'php -v' ni tekshiring." >&2

echo "Redis (kesh, navbat, sessiya, rate-limit) o'rnatilmoqda..."
sudo apt install -y redis-server
REDIS_CONF="${REDIS_CONF:-/etc/redis/redis.conf}"
if [ ! -f "$REDIS_CONF" ]; then
    echo "XATO: Redis sozlama fayli topilmadi: ${REDIS_CONF}" >&2
    exit 1
fi
# AOF: navbatdagi joblar Redis qayta ishga tushganda yo'qolmasin (Docker'dagi redis ham --appendonly yes bilan ishlaydi).
# Faqat o'zgargan bo'lsa Redis qayta ishga tushiriladi. maxmemory-policy standarti (noeviction) qoldiriladi:
# allkeys-*/volatile-* navbat va sessiya kalitlarini ham o'chirib yuboradi. Redis faqat 127.0.0.1 da tinglaydi (paket standarti).
if ! sudo grep -Eq '^[[:space:]]*appendonly[[:space:]]+yes' "$REDIS_CONF"; then
    if sudo grep -Eq '^[[:space:]]*appendonly[[:space:]]+no' "$REDIS_CONF"; then
        sudo sed -i -E 's/^[[:space:]]*appendonly[[:space:]]+no.*$/appendonly yes/' "$REDIS_CONF"
    else
        echo "appendonly yes" | sudo tee -a "$REDIS_CONF" > /dev/null
    fi
    sudo systemctl restart redis-server
fi
sudo systemctl enable --now redis-server

echo "O'rnatilgan PHP kengaytmalari tekshirilmoqda..."
for ext in redis pdo_pgsql pgsql bcmath gd intl zip pcntl mbstring xml curl; do
    if ! "$PHP_BIN" -r "exit(extension_loaded('${ext}') ? 0 : 1);"; then
        echo "XATO: PHP ${PHP_VERSION} da '${ext}' kengaytmasi yuklanmadi." >&2
        exit 1
    fi
done
if ! sudo systemctl is-active --quiet redis-server; then
    echo "XATO: redis-server ishlamayapti ('sudo systemctl status redis-server' ni ko'ring)." >&2
    exit 1
fi

echo "Composer o'rnatilmoqda..."
curl -sS https://getcomposer.org/installer | "$PHP_BIN"
sudo mv composer.phar /usr/local/bin/composer

echo "Node.js 20 va NPM o'rnatilmoqda..."
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs

echo "Ma'lumotlar bazasi sifatida PostgreSQL ${PG_VERSION} o'rnatilmoqda (docker-compose.yml dagi postgres:${PG_VERSION}-alpine bilan bir xil major versiya)..."
INSTALLED_PG="$(dpkg-query -W -f='${db:Status-Abbrev} ${Package} ${Version}\n' 'postgresql-[0-9]*' 2>/dev/null | grep '^ii ' || true)"
if [ -n "$INSTALLED_PG" ]; then
    # Mavjud klasterga tegmaymiz: yangi major versiya yonma-yon o'rnatilsa, alohida klaster (5433-port) paydo bo'ladi
    echo "PostgreSQL allaqachon o'rnatilgan, o'tkazib yuborildi:"
    echo "$INSTALLED_PG"
else
    sudo apt install postgresql-common -y
    # Ubuntu'ning o'z postgresql paketi eskiroq versiya bo'lishi mumkin; PGDG (rasmiy) repozitoriyasini qo'shadi
    sudo /usr/share/postgresql-common/pgdg/apt.postgresql.org.sh -y
    sudo apt install "postgresql-${PG_VERSION}" -y
fi

echo "Laravel scheduler (har daqiqada schedule:run) cron orqali sozlanmoqda..."
sudo apt install cron -y
echo "* * * * * ${APP_USER} cd ${APP_DIR}/backend && ${PHP_BIN} artisan schedule:run >> /dev/null 2>&1" \
    | sudo tee /etc/cron.d/rizqim-scheduler > /dev/null
sudo chmod 644 /etc/cron.d/rizqim-scheduler

echo "Navbat ishchisi (queue:work) systemd xizmati sifatida sozlanmoqda..."
# queue:work QUEUE_CONNECTION ga ergashadi (backend/.env: redis; database zaxira). Flaglar docker-compose.yml dagi queue xizmati bilan bir xil.
sudo tee /etc/systemd/system/rizqim-queue.service > /dev/null <<EOF
[Unit]
Description=Rizqim Laravel queue worker
After=network.target postgresql.service redis-server.service
Wants=redis-server.service

[Service]
User=${APP_USER}
Group=${APP_USER}
WorkingDirectory=${APP_DIR}/backend
ExecStart=${PHP_BIN} artisan queue:work --tries=3 --max-time=3600
# --max-time and queue:restart (deploy.sh) make the worker exit; systemd starts it again with fresh code
Restart=always
RestartSec=5
# On stop the worker finishes its current job first; the longest job (SendOrderReport) may run 120 s
TimeoutStopSec=150

[Install]
WantedBy=multi-user.target
EOF
sudo systemctl daemon-reload
sudo systemctl enable --now rizqim-queue.service

echo "O'rnatish yakunlandi!"
echo "Iltimos, Nginx konfiguratsiya fayllarini (/etc/nginx/sites-available/) joylashtiring va PostgreSQL da kerakli DB hamda foydalanuvchini yarating."
echo "backend/.env da REDIS_HOST=127.0.0.1, CACHE_STORE/QUEUE_CONNECTION/SESSION_DRIVER=redis bo'lishi kerak (backend/.env.example), so'ng scripts/deploy.sh ni ishga tushiring."
