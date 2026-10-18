# Deploy: Docker Compose va VPS

Ikki yo'l bor va ular backend uchun bir xil qadamlarni bir xil tartibda bajaradi:

| # | Docker (`backend/docker/entrypoint.sh`) | VPS (`scripts/deploy.sh`) |
|---|---|---|
| 1 | `app:check-production` (xato bo'lsa konteyner ishga tushmaydi) | `git pull origin main`, `composer install --no-dev`, `config:clear`, `app:check-production` (xato bo'lsa deploy to'xtaydi) |
| 2 | `migrate --force` | `migrate --force` |
| 3 | `storage:link`, `filament:upgrade` | `storage:link`, `optimize:clear` |
| 4 | `config:cache`, `route:cache`, `view:cache` | `config:cache`, `route:cache`, `view:cache` |
| 5 | `telegram:set-webhook --if-configured` (xato bo'lsa faqat ogohlantirish) | xuddi shu |
| 6 | `queue:restart` | `queue:restart`; keyin `npm ci` + `npm run build`, huquqlarni to'g'rilash, nginx va php-fpm qayta ishga tushadi |

Docker'da 2–6-qadamlarni faqat `backend` konteyneri bajaradi (`RUN_MIGRATIONS=true`); `queue` va `scheduler` faqat 1-qadamni bajarib ishga tushadi.

## 1. Oldindan tayyorlash

- **Domen va HTTPS.** Telegram Mini App va webhook faqat HTTPS bilan ishlaydi. Compose ham, VPS skriptlari ham TLS o'rnatmaydi: sertifikatni tashqi reverse proxy yoki o'z nginx'ingiz beradi (5-bo'lim).
- **Telegram bot** (BotFather): token (`TELEGRAM_BOT_TOKEN`), username (`TELEGRAM_BOT_USERNAME` va Mini App'dagi `VITE_BOT_USERNAME`, `@` siz), Mini App manzili (`TELEGRAM_WEBAPP_URL`).
- **Click / Payme** merchant: maxfiy kalitlar (`CLICK_SECRET_KEY`, `PAYME_SECRET_KEY`) va id'lar. Merchant kabinetida webhook manzillari: `https://<domen>/api/v1/payments/click/webhook` va `https://<domen>/api/v1/payments/payme/webhook`.
- **Maxfiy qiymatlar:** `APP_KEY` — `echo "base64:$(openssl rand -base64 32)"`; `TELEGRAM_WEBHOOK_SECRET` — `openssl rand -hex 32` (faqat `A-Za-z0-9_-` ruxsat etiladi); super admin paroli kamida 12 belgi.

## 2. Muhit o'zgaruvchilari

Hammasining ma'nosi va standarti: [backend/README.md](../backend/README.md), [mini-app/README.md](../mini-app/README.md). Production uchun majburiylari (`backend/.env`, ikkala yo'lda):

| O'zgaruvchi | Qiymat |
|---|---|
| `APP_ENV`, `APP_DEBUG` | `production`, `false` |
| `APP_KEY` | `base64:...` (oldindan; Docker'da ham, 3-bo'limga qarang) |
| `APP_URL` | `https://<domen>` |
| `TELEGRAM_ALLOW_MOCK_AUTH`, `SMS_EXPOSE_CODE_IN_RESPONSE` | `false`, `false` |
| `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | PostgreSQL (Docker'da qiymatlarni ildizdagi `.env` beradi) |
| `CACHE_STORE`, `QUEUE_CONNECTION`, `SESSION_DRIVER` | `redis` |
| `REDIS_CLIENT`, `REDIS_HOST`, `REDIS_PORT`, `REDIS_PASSWORD` | `phpredis`, Redis manzili (Docker'da `redis`, `6379` va parolni compose o'zi beradi) |
| `REDIS_DB`, `REDIS_CACHE_DB` | `0`, `1` (farqli bo'lsin; `REDIS_URL` ni o'rnatmang) |
| `TELEGRAM_BOT_TOKEN`, `TELEGRAM_BOT_USERNAME`, `TELEGRAM_WEBAPP_URL`, `TELEGRAM_WEBHOOK_SECRET` | 1-bo'limdagi qiymatlar. `APP_URL` HTTPS bo'lmasa `TELEGRAM_WEBHOOK_URL` ham kerak |
| `CLICK_SECRET_KEY`, `PAYME_SECRET_KEY` (+ `CLICK_SERVICE_ID`, `CLICK_MERCHANT_ID`, `PAYME_MERCHANT_ID`) | merchant ma'lumotlari. Standart id'lar `config/services.php` dagi namunalar |
| `SUPERADMIN_EMAIL`, `SUPERADMIN_PASSWORD` | `SuperAdminSeeder` uchun, parol kamida 12 belgi |
| `CORS_ALLOWED_ORIGINS` | faqat Mini App API'ga boshqa domendan murojaat qilsa |
| `LOG_LEVEL` | `warning` tavsiya etiladi: Click, Payme va Telegram webhook payload'lari `info` darajasida to'liq logga yoziladi (shaxsiy ma'lumot bo'lishi mumkin) |

Docker uchun **ildizdagi `.env`** (compose o'qiydi): `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` (postgres ham, backend ham shundan oladi va `backend/.env` dagilarni bekor qiladi), `REDIS_PASSWORD` (`$` belgisiz), `BACKEND_PORT` (8000), `FRONTEND_PORT` (8080), `VITE_BOT_USERNAME`, `VITE_API_URL`, `VITE_MAP_TILE_URL`, `VITE_MAP_ATTRIBUTION`. Ildizdagi `.env.example` ning pastki qismidagi `APP_ENV`, `CACHE_STORE`, `SUPERADMIN_*` kabi qatorlar faqat eslatma: konteyner ularni `backend/.env` dan o'qiydi, ildizdagi `.env` dan emas.

## 3. A yo'l: Docker Compose

Xizmatlar: `postgres` (17), `redis` (7, AOF, parol ixtiyoriy), `backend` (nginx + php-fpm, deploy qadamlarini shu bajaradi), `queue`, `scheduler`, `frontend` (nginx). `queue`, `scheduler` va `frontend` `backend` healthcheck'i (`/up`) o'tgunicha kutadi.

```bash
git clone <repo-url> rizqim && cd rizqim
cp .env.example .env                  # compose uchun (2-bo'limdagi ildiz qiymatlari)
cp backend/.env.example backend/.env  # ilova uchun; compose bu faylsiz ishga tushmaydi (env_file)
echo "base64:$(openssl rand -base64 32)"   # chiqqan qiymatni backend/.env dagi APP_KEY ga yozing
# ikkala faylni to'ldiring, so'ng:
docker compose up -d --build
docker compose ps                     # birinchi ishga tushishda backend healthy bo'lishi ~2 daqiqagacha cho'zilishi mumkin
curl -fsS http://127.0.0.1:8000/up
```

- **`APP_KEY`ni oldindan yozing.** Entrypoint `APP_KEY` bo'sh bo'lsa `key:generate --force` ni konteynerning ichki `.env` fayliga yozadi: bunday kalit har konteynerda alohida bo'ladi va konteyner qayta yaratilganda yo'qoladi, u bilan shifrlangan ma'lumotlar (masalan, Superadmin > To'lov Tizimlari kalitlari) esa o'qilmay qoladi.
- Konteyner `APP_ENV=production` va boshqa xavfsizlik shartlari bajarilmasa ishga tushmaydi, shuning uchun lokal ishlab chiqish uchun compose mos emas ([backend/README.md](../backend/README.md)).
- Portlar: Mini App `FRONTEND_PORT` da (faqat `/api/` ni backendga uzatadi), admin panellar (`/admin`, `/superadmin`) va `/up` `BACKEND_PORT` da. Postgres va Redis tashqariga ochilmagan.
- Volume'lar: `postgres_data`, `redis_data`, `backend_storage` (= `storage/app`; yuklangan rasmlar shu yerda).
- Loglar: `storage/logs/laravel.log` konteyner ichida (volume emas, konteyner qayta yaratilganda yo'qoladi): `docker compose exec backend tail -f storage/logs/laravel.log`. `docker compose logs` da ko'rish uchun `backend/.env` ga `LOG_CHANNEL=stderr`.
- Yangilash: `git pull && docker compose up -d --build`. Faqat `.env` o'zgarganda: `docker compose up -d --force-recreate` (`config:cache` konteyner ishga tushganda yig'iladi). `docker compose down -v` volume'larni, ya'ni bazani ham o'chiradi.
- Birinchi ishga tushishdan keyingi qadamlar (super admin, birinchi do'kon) 6-bo'limda.

## 4. B yo'l: VPS (Ubuntu)

Skriptlar loyiha `APP_DIR` (standart `/var/www/order-mini-app`) da turishini kutadi: cron va systemd shu yo'lga yozilgan; `deploy.sh` `main` shoxidan `git pull origin main` qiladi.

```bash
git clone <repo-url> /var/www/order-mini-app   # deploy foydalanuvchisi shu papkaga yoza olishi kerak (git pull, composer, npm)
cd /var/www/order-mini-app
sudo ./scripts/setup-server.sh        # bir marta (qayta ishga tushirish xavfsiz)
# o'zgaruvchilar (sudo VAR=qiymat ./scripts/setup-server.sh): PHP_VERSION=8.3, PG_VERSION=17, APP_DIR, APP_USER=www-data, REDIS_CONF
```

`setup-server.sh` o'rnatadi: nginx; PHP 8.3 (ppa:ondrej) va kengaytmalar (`pgsql`, `redis`, `gd`, `intl`, `zip`, `bcmath`, `opcache`, ...); `redis-server` (AOF yoqiladi, `127.0.0.1`); Composer; Node.js 20; PostgreSQL (faqat hech qaysi versiya o'rnatilmagan bo'lsa, PGDG orqali 17); cron `/etc/cron.d/rizqim-scheduler` (har daqiqa `schedule:run`); systemd `rizqim-queue.service` (`queue:work --tries=3 --max-time=3600`). Skript baza, nginx sozlamasi va TLS'ni **qilmaydi**.

```bash
sudo -u postgres psql -c "CREATE USER rizqim WITH PASSWORD '<parol>';"
sudo -u postgres psql -c "CREATE DATABASE rizqim OWNER rizqim;"
cp backend/.env.example backend/.env       # to'ldiring (REDIS_HOST=127.0.0.1, DB_HOST=127.0.0.1, APP_KEY, ...)
cat > mini-app/.env.production <<'EOF'
VITE_BOT_USERNAME=<bot_username>
VITE_API_URL=/api/v1
VITE_MAP_TILE_URL=
VITE_MAP_ATTRIBUTION=
EOF
./scripts/deploy.sh                        # birinchi marta ham, har yangilashda ham
```

nginx: Laravel uchun `root backend/public`, `try_files $uri $uri/ /index.php?$query_string`, PHP-FPM, `client_max_body_size 32m`; Mini App uchun `mini-app/dist` va SPA qaytishi. Tayyor namunalar: `backend/docker/nginx.conf` va `mini-app/docker/nginx.conf` (VPS'da `fastcgi_pass` ni PHP-FPM soketiga yo'naltiring). TLS (masalan, Let's Encrypt) o'zingiz sozlaysiz.

Tekshirish: `systemctl is-active rizqim-queue`, `cat /etc/cron.d/rizqim-scheduler`, `journalctl -u rizqim-queue -f`; `deploy.sh` oxirida navbat xizmati yoki cron fayli yo'q bo'lsa ogohlantiradi.

## 5. HTTPS va marshrutlash

- Mini App `VITE_API_URL=/api/v1` bilan same-origin ishlaydi: o'z domenidagi `/api/` backendga uzatilishi kerak (compose'dagi `frontend` buni qiladi), shunda CORS kerak emas. Telegram va to'lov webhooklari ham `/api/...` ostida.
- `/admin`, `/superadmin` (Livewire/Filament aktivlari bilan) va `/storage/*` backend tomonidan xizmat qilinadi. Mini App nginx'i faqat `/api/` ni uzatadi: boshqa yo'llar unda topilmaydi (SPA uchun `index.html` yoki 404).
- Yuklangan rasm havolalari (`logo_url`, `image_url`) `asset('storage/...')` bilan **so'rov Host'idan** quriladi, `APP_URL` dan emas. Mini App domenida `/storage/` ham backendga yo'naltirilmasa (yoki `/api/` proksisida `Host` backend domeniga o'zgartirilmasa) rasmlar `index.html` qaytaradi. Deploydan keyin API javobidagi rasm URL'ini oching: `200` va `image/*` bo'lishi kerak.
- Backend `X-Forwarded-Proto: https` bo'lsagina `https` havolalar quradi va faqat xususiy tarmoq manzillaridan kelgan `X-Forwarded-For`/`X-Forwarded-Proto` ga ishonadi. `mini-app/docker/nginx.conf` bu sarlavhani `$scheme` bilan qayta yozadi, TLS esa compose'dan tashqarida tugasa `$scheme` `http` bo'ladi: rasm URL'lari `https://` bilan boshlanishini tekshiring.

## 6. Birinchi ishga tushishdan keyin

Deploy hech qaysi seederni ishga tushirmaydi (`DatabaseSeeder` faqat `SettingSeeder`). Docker: buyruqlarni `docker compose exec backend ...` bilan, VPS: `backend/` papkasida bajaring.

```bash
php artisan db:seed --class=SuperAdminSeeder --force   # SUPERADMIN_EMAIL / SUPERADMIN_PASSWORD bilan
php artisan db:seed --class=LocationSeeder --force     # viloyat/tumanlar; TRUNCATE ... CASCADE: faqat bo'sh bazada!
php artisan db:seed --force                             # ixtiyoriy: SettingSeeder (sms_driver=log va h.k.)
```

1. `/superadmin` ga SUPERADMIN_* bilan kiring. Superadmin > Viloyatlar/Tumanlar da kerakli hududlarni faollashtiring (seeder faqat Andijon viloyati/Asaka tumanini yoqadi).
2. Ixtiyoriy: Superadmin > To'lov Tizimlari da platforma merchant kalitlarini kiriting (`click`, `payme` slug'li qatorlar; qatorlarni `PaymentProviderSeeder` yaratadi yoki panelda qo'lda qo'shasiz; bo'sh qiymat env'ga qaytadi). SMS kerak bo'lsa Superadmin > Tizim Sozlamalari.
3. Superadmin > Restoranlar da birinchi do'konni yarating (egasi, birinchi filial, koordinata va radius). **Kamida bitta faol do'kon bo'lmaguncha Mini App'ga hech kim kira olmaydi** (`POST /auth/telegram` 404 qaytaradi). Egasi uchun Telegram taklif havolasi Restoranlar jadvalidagi "Telegram Link" amalida chiqadi (egasi botga shu havola bilan kirib akkauntini ulaydi).
4. Mini App'ni bot orqali oching, so'ng o'zingizga `php artisan user:assign-role <telegram_id> super_admin` bering (Mini App'da super admin bo'lish uchun).
5. Webhook: deploy `telegram:set-webhook` ni ishga tushirgan; holatni tekshirish: `curl "https://api.telegram.org/bot<TOKEN>/getWebhookInfo"` (`url` va `last_error_message`). Muvaffaqiyatsiz bo'lsa qo'lda: `php artisan telegram:set-webhook [--url=https://...]`.

## 7. Production checklist

| Tekshiruv | Izoh |
|---|---|
| `APP_ENV=production`, `APP_DEBUG=false`, `TELEGRAM_ALLOW_MOCK_AUTH=false` | `app:check-production` buzilganda `exit 1` qiladi: konteyner ishga tushmaydi, `deploy.sh` to'xtaydi. Bu buyruq `APP_KEY`, Click/Payme kalitlari, Redis, CORS va `SMS_EXPOSE_CODE_IN_RESPONSE` ni **tekshirmaydi** |
| `CLICK_SECRET_KEY`, `PAYME_SECRET_KEY` (yoki Superadmin > To'lov Tizimlari) | Kalit yo'q bo'lsa webhook rad etadi (Click `error -1`, Payme `-32504`): onlayn to'lov hech qachon `paid` bo'lmaydi va buyurtmalar TTL bo'yicha bekor bo'ladi |
| `TELEGRAM_WEBHOOK_SECRET` + `telegram:set-webhook` | Secret bo'sh yoki noto'g'ri belgili bo'lsa `app:check-production` yiqiladi; webhook ro'yxatdan o'tmasa bot `/start` ga va buyurtma tugmalari bosilishiga javob bermaydi (chiquvchi xabarlar ketaveradi) |
| `TELEGRAM_WEBAPP_URL` | Bo'sh qolsa bot tugmalari kodga yozilgan zaxira manzillarga yo'naladi |
| Standart super admin parolini almashtiring | `12345678` parolli `super_admin` bo'lsa `app:check-production` yiqiladi (lokal seeder `admin@gmail.com` yaratgan bo'lishi mumkin). Almashtirish: Superadmin > Foydalanuvchilar |
| Do'konsiz xodimlar | `app:check-production` ogohlantiradi ("N ta xodim hisobi hech qaysi do'konga biriktirilmagan"), API 403 beradi, panelga kirmaydi. Tuzatish: `php artisan user:assign-role <telegram_id> <rol> --store=<id> [--branch=<id>]` yoki Superadmin > Foydalanuvchilar |
| Redis | `CACHE_STORE`, `QUEUE_CONNECTION`, `SESSION_DRIVER` = `redis`; AOF yoqilgan; `maxmemory-policy` ni `allkeys-*`/`volatile-*` qilmang (navbat/sessiya kalitlari o'chadi); `REDIS_DB` ≠ `REDIS_CACHE_DB`. Navbatni `database` dan Redis'ga o'tkazishdan oldin bo'shating: `php artisan queue:work database --stop-when-empty` |
| `queue` va `scheduler` ishlayaptimi | Navbatsiz Telegram xabarlari ketmaydi; schedulersiz to'lanmagan buyurtmalar bekor bo'lmaydi va kuryer ogohlantirishi chiqmaydi. `php artisan schedule:list` uchta vazifani ko'rsatishi kerak |
| CORS | Mini App same-origin bo'lsa kerak emas; aks holda `CORS_ALLOWED_ORIGINS` |
| Xarita va geokodlash | Ochiq Nominatim/OSM tile serverlari faqat ishlab chiqish va kam trafik uchun: `NOMINATIM_BASE_URL`, `NOMINATIM_EMAIL`, `VITE_MAP_TILE_URL`, `VITE_MAP_ATTRIBUTION` |
| SMS | `SettingSeeder` yozgan `settings` qatorlari `SMS_DRIVER`/`ESKIZ_*` env'dan ustun: SMS'ni Superadmin > Tizim Sozlamalari'dan yoqing |
| Zaxira nusxa | Postgres (`pg_dump`) va yuklangan rasmlar (Docker: `backend_storage` volume; VPS: `backend/storage/app/public`) |
| `LocationSeeder` | Ishlaydigan bazada qayta ishga tushirmang (`branches`, `addresses` va bog'liq jadvallarni o'chiradi); `PaymentProviderSeeder` ham kiritilgan kalitlarni bo'shatadi |

## 8. Ikki yo'l o'rtasidagi qolgan farqlar

| Mavzu | Docker | VPS |
|---|---|---|
| nginx va TLS | konteyner nginx'lari faqat HTTP (80); TLS tashqarida | vhost va TLS skriptlanmagan, qo'lda |
| Versiyalar | `php:8.3-fpm-alpine`, `postgres:17-alpine`, `redis:7-alpine`, build uchun Node 20 | PHP 8.3 (`PHP_VERSION`), PostgreSQL: o'rnatilgani saqlanadi, bo'lmasa 17, Redis — distributiv versiyasi (6 yoki 7), Node 20 |
| Kesh | `optimize:clear` ishlatilmaydi | har deployda `optimize:clear` kesh bazasini (`REDIS_CACHE_DB`) butunlay tozalaydi: `setting()` keshi, geokod keshi, rate-limit hisoblagichlari, botdan kutilayotgan referral'lar (30 kun) va ops-metrikalar yo'qoladi. Navbat va sessiyalar `REDIS_DB` da qoladi |
| Redis | parol ixtiyoriy (ildiz `REDIS_PASSWORD`), portlari yopiq | `127.0.0.1` da, parolsiz (paket standarti); parol qo'ysangiz `redis.conf` va `backend/.env` ni birga o'zgartiring |
| Loglar | konteyner ichida, yo'qolib ketadi (`LOG_CHANNEL=stderr` yoki `docker compose logs`) | doimiy `backend/storage/logs/laravel.log`, navbat uchun `journalctl` |
| `VITE_*` | ildiz `.env` -> build argument (`docker compose up -d --build frontend`) | `mini-app/.env.production` |
| Migratsiya | `backend` konteyneri har ishga tushganda | `deploy.sh` ichida |
| `APP_KEY` | `backend/.env` da oldindan (3-bo'lim) | `backend/.env` da |
| scheduler / queue | `scheduler`, `queue` xizmatlari (`stop_grace_period: 150s`) | cron / `rizqim-queue.service` (`TimeoutStopSec=150`) |
| Fayl huquqlari | konteynerda `www-data` | `deploy.sh` `backend/storage` va `backend/bootstrap/cache` ni `www-data` ga `chown`, `chmod 775` qiladi |

## 9. Yangilash, zaxira va orqaga qaytarish

**Yangilash:** Docker — `git pull && docker compose up -d --build`; VPS — `./scripts/deploy.sh`. Migratsiya oldidan zaxira oling:

```bash
docker compose exec -T postgres sh -c 'pg_dump -U "$POSTGRES_USER" "$POSTGRES_DB"' > backup-$(date +%F).sql   # Docker
sudo -u postgres pg_dump rizqim > backup-$(date +%F).sql                                                       # VPS
```

**Orqaga qaytarish.** Kod orqaga qaytsa ham migratsiyalar o'z-o'zidan qaytmaydi. Yangi migratsiya eski kodga to'sqinlik qilsa, `down()` yangi kodda bo'lgani uchun avval migratsiyani **yangi kod bilan** qaytaring, keyin kodni almashtiring:

```bash
php artisan migrate:status
php artisan migrate:rollback --step=<N> --force      # Docker: docker compose exec backend php artisan ...
```

Bu repodagi migratsiyalardan ehtiyot talab qiladiganlari (qolganlarining `down()` si o'z o'zgarishini qaytaradi):
- `down()` bo'sh (ma'lumot tuzatuvchi, hech narsa tiklanmaydi): `backfill_commission_amount_for_open_orders`, `mark_open_online_orders_platform_collected`, `normalize_legacy_store_payment_settings`.
- `rename_balance_to_debt_balance_on_stores_table` `stores.balance` ustunini `debt_balance` ga o'zgartirgan; eski kod `balance` ni kutadi, shuning uchun kod va bazani birga qaytaring.
- `add_non_negative_stock_check_to_product_variants_table` manfiy zaxirani 0 ga tenglashtirgan (qaytmaydi); `down()` faqat `CHECK` cheklovini olib tashlaydi.
- `copy_order_status_logs_into_order_status_histories` `down()` da ko'chirilgan qatorlar va `order_status_log_id` ustunini o'chiradi; asl `order_status_logs` jadvali o'zgarmaydi.
- `add_promo_code_id_to_orders_table` indekslarni `CREATE INDEX CONCURRENTLY` bilan tranzaksiyasiz quradi: yarim yo'lda to'xtasa `INVALID` indeks qolishi mumkin (`\d orders`), uni `DROP INDEX CONCURRENTLY <nom>` bilan o'chirib qayta yurgizing. `add_non_negative_stock_check_...` ham tranzaksiyasiz (`NOT VALID`, so'ng `VALIDATE`), uni qayta yurgizish xavfsiz.
- Eng ishonchli yo'l — `pg_dump` zaxirasidan tiklash.

Kodni qaytarish: Docker'da eski commit'ni checkout qilib `docker compose up -d --build`. VPS'da `deploy.sh` har safar `git pull origin main` qilgani uchun zararli commit'ni `main` da `git revert` qiling va skriptni qayta ishga tushiring.

## 10. Kundalik buyruqlar

| Maqsad | Buyruq (VPS'da `backend/` ichida; Docker'da `docker compose exec backend ...`) |
|---|---|
| Holat | `docker compose ps`, `docker compose logs -f backend queue scheduler`; VPS: `systemctl status rizqim-queue` |
| Muvaffaqiyatsiz joblar | `php artisan queue:failed`, `php artisan queue:retry all` |
| Scheduler vazifalari | `php artisan schedule:list` |
| Sozlamalarni qayta tekshirish | `php artisan app:check-production` (o'zgartirgandan keyin `php artisan config:clear` yoki konteynerni qayta yarating) |
| Rol berish | `php artisan user:assign-role <telegram_id> <rol> [--store=<id_yoki_uuid>] [--branch=<id>]` |
| Webhook | `php artisan telegram:set-webhook [--url=...]` |
