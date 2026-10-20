#!/bin/sh
# Staging: Mini App nginx (frontend) har javobga `X-Robots-Tag: noindex` qo'shsin.
# nginx:alpine entrypoint /docker-entrypoint.d/*.sh ni nginx ishga tushishidan oldin bajaradi (docker-compose.staging.yml shuni ulaydi).
# add_header faqat o'z `add_header` i bo'lmagan blokka meros bo'ladi, shuning uchun sarlavha server blokiga va
# `add_header Cache-Control` bor har bir location'ga qo'shiladi. Qayta ishga tushirishda ikki marta qo'shilmaydi.
set -eu

conf=${NGINX_CONF:-/etc/nginx/conf.d/default.conf}
header='add_header X-Robots-Tag "noindex, nofollow, noarchive" always;'

if grep -q 'X-Robots-Tag' "$conf"; then
    exit 0
fi

sed -i \
    -e "/^[[:space:]]*server_name /a\\
    $header" \
    -e "/^[[:space:]]*add_header Cache-Control /a\\
        $header" \
    "$conf"

# Config o'zgarib, sed hech narsani topmagan bo'lsa, indekslanishga yo'l qo'ymaslik uchun ishga tushirmaymiz
grep -q 'X-Robots-Tag' "$conf" || { echo "staging: X-Robots-Tag qo'shilmadi ($conf)" >&2; exit 1; }
