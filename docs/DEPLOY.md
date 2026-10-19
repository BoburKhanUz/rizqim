# Deploy: Docker Compose va VPS

Ikki yo'l bor va ular backend uchun bir xil qadamlarni bir xil tartibda bajaradi:

| # | Docker (`backend/docker/entrypoint.sh`) | VPS (`scripts/deploy.sh`) |
|---|---|---|
| 0 | — (nginx va php-fpm entrypoint tugagach ishga tushadi, shuning uchun migratsiya paytida so'rov qabul qilinmaydi) | huquqlarni to'g'rilash (`storage`, `bootstrap/cache`), `php artisan down --retry=15` (texnik rejim) |
| 1 | `app:check-production` (xato bo'lsa konteyner ishga tushmaydi) | `git pull origin main`, `composer install --no-dev`, `config:clear`, `app:check-production` (xato bo'lsa deploy to'xtaydi) |
| 2 | `migrate --force` | `migrate --force` |
| 3 | `storage:link`, `filament:upgrade` | `storage:link`, `optimize:clear` |
| 4 | `config:cache`, `route:cache`, `view:cache` | `config:cache`, `route:cache`, `view:cache` |
| 5 | `telegram:set-webhook --if-configured` (xato bo'lsa faqat ogohlantirish) | — (`up` dan keyin, 7-qadam) |
| 6 | `queue:restart` | `queue:restart`, huquqlarni qayta to'g'rilash, php-fpm qayta ishga tushadi, `php artisan up` |
| 7 | — | `telegram:set-webhook --if-configured` (xuddi shu), `npm ci` + `npm run build`, nginx qayta ishga tushadi |

Docker'da 2–6-qadamlarni faqat `backend` konteyneri bajaradi (`RUN_MIGRATIONS=true`); `queue`, `queue-reports` va `scheduler` faqat 1-qadamni bajarib ishga tushadi. Docker'da `artisan` va navbat/scheduler jarayonlari `www-data` nomidan ishlaydi (entrypoint `su-exec` bilan tushiradi).

**Nega VPS'da texnik rejim.** `git pull` kodni almashtirgach `migrate` tugagunicha yangi kod eski bazada ishlab qoladi (masalan, yetkazilgan buyurtmalar hisob-kitob yozuvisiz qolishi mumkin). `deploy.sh` shu oraliqni `php artisan down --retry=15` … `php artisan up` bilan yopadi: API `503` va `Retry-After: 15` qaytaradi, navbat ishchilari `up` gacha job olmaydi. Telegram webhook, Click va Payme `503` olgan so'rovlarini qayta yuboradi, shuning uchun qisqa oyna (odatda yarim daqiqadan bir necha daqiqagacha) ularni yo'qotmaydi. Skript xato bilan to'xtasa ham `up` `trap` orqali bajariladi (ilova texnik rejimda abadiy qolmaydi); xato `git pull` dan keyin chiqqan bo'lsa, yangi kod eski bazada ishlab turgan bo'lishi mumkin: xatoni zudlik bilan tuzatib, skriptni qayta ishga tushiring. Birinchi deployda (`vendor/` hali yo'q) texnik rejim o'tkazib yuboriladi. Texnik rejim fayl drayverida turadi (`APP_MAINTENANCE_DRIVER=file`, standart): `optimize:clear` uni o'chirmaydi.

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
| `TELEGRAM_BOT_TOKEN`, `TELEGRAM_BOT_USERNAME`, `TELEGRAM_WEBAPP_URL`, `TELEGRAM_WEBHOOK_SECRET` | 1-bo'limdagi qiymatlar. `TELEGRAM_WEBAPP_URL` majburiy va `https://` bilan boshlanishi shart (`app:check-production` tekshiradi). `APP_URL` HTTPS bo'lmasa `TELEGRAM_WEBHOOK_URL` ham kerak |
| `CLICK_SECRET_KEY`, `PAYME_SECRET_KEY` (+ `CLICK_SERVICE_ID`, `CLICK_MERCHANT_ID`, `PAYME_MERCHANT_ID`) | merchant ma'lumotlari. Standart id'lar `config/services.php` dagi namunalar |
| `SUPERADMIN_EMAIL`, `SUPERADMIN_PASSWORD` | `SuperAdminSeeder` uchun, parol kamida 12 belgi |
| `CORS_ALLOWED_ORIGINS` | faqat Mini App API'ga boshqa domendan murojaat qilsa |
| `LOG_LEVEL` | `warning` tavsiya etiladi: Click, Payme va Telegram webhook payload'lari `info` darajasida to'liq logga yoziladi (shaxsiy ma'lumot bo'lishi mumkin) |

Docker uchun **ildizdagi `.env`** (compose o'qiydi): `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` (postgres ham, backend ham shundan oladi va `backend/.env` dagilarni bekor qiladi), `REDIS_PASSWORD` (`$` belgisiz), `BACKEND_PORT` (8000), `FRONTEND_PORT` (8080), `VITE_BOT_USERNAME`, `VITE_API_URL`, `VITE_MAP_TILE_URL`, `VITE_MAP_ATTRIBUTION`. Ildizdagi `.env.example` ning pastki qismidagi `APP_ENV`, `CACHE_STORE`, `SUPERADMIN_*` kabi qatorlar faqat eslatma: konteyner ularni `backend/.env` dan o'qiydi, ildizdagi `.env` dan emas.

## 3. A yo'l: Docker Compose

Xizmatlar: `postgres` (17), `redis` (7, AOF, parol ixtiyoriy), `backend` (nginx + php-fpm, deploy qadamlarini shu bajaradi), `queue` (`--queue=default`: Telegram xabarlari va h.k.), `queue-reports` (`--queue=reports`: og'ir CSV hisobotlar, `SendOrderReport`), `scheduler`, `frontend` (nginx). `queue`, `queue-reports`, `scheduler` va `frontend` `backend` healthcheck'i (`/up`) o'tgunicha kutadi.

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

- **`APP_KEY`ni oldindan yozing.** Entrypoint kalit yaratmaydi (`key:generate` yo'q): `APP_KEY` bo'sh bo'lsa `app:check-production` konteynerni ishga tushirmaydi. Sabab: web, queue va scheduler alohida konteynerlar, ularning har biri o'z kalitini olardi va konteyner qayta yaratilganda kalit o'zgarardi, natijada sessiyalar va shifrlangan ma'lumotlar (masalan, Superadmin > To'lov Tizimlari kalitlari) o'qilmay qolardi.
- Konteyner `APP_ENV=production` va boshqa xavfsizlik shartlari bajarilmasa ishga tushmaydi, shuning uchun lokal ishlab chiqish uchun compose mos emas ([backend/README.md](../backend/README.md)).
- Portlar: Mini App `FRONTEND_PORT` da (statika; `/api/` va `/storage/` ni backendga uzatadi), admin panellar (`/admin`, `/superadmin`) va `/up` `BACKEND_PORT` da. Postgres va Redis tashqariga ochilmagan.
- Volume'lar: `postgres_data`, `redis_data`, `backend_storage` (= `storage/app`; yuklangan rasmlar shu yerda).
- Loglar: `storage/logs/laravel.log` konteyner ichida (volume emas, konteyner qayta yaratilganda yo'qoladi): `docker compose exec backend tail -f storage/logs/laravel.log`. `docker compose logs` da ko'rish uchun `backend/.env` ga `LOG_CHANNEL=stderr`.
- Yangilash: `git pull && docker compose up -d --build`. Faqat `.env` o'zgarganda: `docker compose up -d --force-recreate` (`config:cache` konteyner ishga tushganda yig'iladi). `docker compose down -v` volume'larni, ya'ni bazani ham o'chiradi.
- **Ops buyruqlari `www-data` nomidan:** `docker compose exec -u www-data backend php artisan ...`. `exec` standart bo'yicha root: root yaratgan `storage/logs/laravel.log` ni php-fpm (`www-data`) yoza olmaydi va logga yozadigan har bir so'rov 500 beradi. (Entrypoint o'zi `artisan` ni `www-data` bilan ishga tushiradi va konteyner qayta ishga tushganda `storage/logs`, `storage/framework`, `bootstrap/cache` egaligini tuzatadi.) `config:clear` ni ishlayotgan `backend` konteynerida bajarmang: sozlamalar konteyner ishga tushganda keshlanadi (`config:cache`), o'zgartirish uchun konteynerni qayta yarating.
- **Fayl yuklash chegaralari.** Zanjir: Mini App nginx `client_max_body_size 10m` (API rasmni 5 MB bilan cheklaydi) → backend nginx `16m` → PHP `post_max_size=16M` ≥ `upload_max_filesize=12M` (`backend/docker/php.ini`; 12 MB — Filament panel yuklashlari chegarasi). Standart nginx (1 MB) va PHP (2 MB) chegaralari rasmlarni rad etardi; chegarani oshirsangiz, uchalasini birga oshiring.
- **Backend manzili qayta yaratilganda.** Mini App nginx'i `backend` nomini Docker DNS (`127.0.0.11`) orqali har so'rovda qayta aniqlaydi (5 s keshlanadi), shuning uchun `docker compose up -d --build backend` dan keyin frontend'ni qayta ishga tushirish shart emas. Backend tayyor bo'lgunicha `/api/` va `/storage/` 502 qaytaradi.
- **Mini App keshi.** `index.html` har safar qayta tekshiriladi (`Cache-Control: no-cache`), `/assets/*` (nomida hash bor) bir yil `immutable` keshlanadi: deploydan keyin eski `index.html` yo'q bo'lib ketgan bo'laklarni so'ramaydi.
- Birinchi ishga tushishdan keyingi qadamlar (super admin, birinchi do'kon) 6-bo'limda.

## 4. B yo'l: VPS (Ubuntu)

Skriptlar loyiha `APP_DIR` (standart `/var/www/order-mini-app`) da turishini kutadi: cron va systemd shu yo'lga yozilgan; `deploy.sh` `main` shoxidan `git pull origin main` qiladi.

```bash
git clone <repo-url> /var/www/order-mini-app   # deploy foydalanuvchisi shu papkaga yoza olishi va sudo ishlata olishi kerak (git pull, composer, npm; chown/chmod/systemctl)
cd /var/www/order-mini-app
sudo ./scripts/setup-server.sh        # bir marta (qayta ishga tushirish xavfsiz)
# o'zgaruvchilar (sudo VAR=qiymat ./scripts/setup-server.sh): PHP_VERSION=8.3, PG_VERSION=17, APP_DIR, APP_USER=www-data, REDIS_CONF
```

`setup-server.sh` o'rnatadi: nginx; PHP 8.3 (ppa:ondrej) va kengaytmalar (`pgsql`, `redis`, `gd`, `intl`, `zip`, `bcmath`, `opcache`, ...); `redis-server` (AOF yoqiladi, `127.0.0.1`); Composer; Node.js 22 LTS; PostgreSQL (faqat hech qaysi versiya o'rnatilmagan bo'lsa, PGDG orqali 17); cron `/etc/cron.d/rizqim-scheduler` (har daqiqa `schedule:run`); systemd `rizqim-queue.service` (`queue:work --queue=default --tries=3 --max-time=3600`) va `rizqim-queue-reports.service` (`--queue=reports --memory=512 --timeout=150 --tries=1`, PHP `memory_limit=768M`); `/etc/php/<versiya>/fpm/conf.d/99-rizqim.ini` (`upload_max_filesize=12M`, `post_max_size=16M`). Skript baza, nginx sozlamasi va TLS'ni **qilmaydi**. Eski serverda `reports` ishchisi yo'q: yangi kod bilan deploydan keyin `sudo ./scripts/setup-server.sh` ni qayta ishga tushiring (xavfsiz), aks holda hisobotlar navbatda qotib qoladi (`deploy.sh` oxirida ogohlantiradi).

```bash
sudo -u postgres psql -c "CREATE USER rizqim WITH PASSWORD '<parol>';"
sudo -u postgres psql -c "CREATE DATABASE rizqim OWNER rizqim;"
cp backend/.env.example backend/.env       # to'ldiring (REDIS_HOST=127.0.0.1, DB_HOST=127.0.0.1, APP_KEY, ...)
sudo chgrp www-data backend/.env && chmod 640 backend/.env   # php-fpm, navbat va cron (www-data) o'qiy olsin, boshqalar o'qimasin
cat > mini-app/.env.production <<'EOF'
VITE_BOT_USERNAME=<bot_username>
VITE_API_URL=/api/v1
VITE_MAP_TILE_URL=
VITE_MAP_ATTRIBUTION=
EOF
./scripts/deploy.sh                        # birinchi marta ham, har yangilashda ham
```

nginx: Laravel uchun `root backend/public`, `try_files $uri $uri/ /index.php?$query_string`, PHP-FPM, `client_max_body_size 16m` (= `post_max_size`); Mini App uchun `mini-app/dist`, SPA qaytishi, `client_max_body_size 10m`, `index.html` uchun `Cache-Control: no-cache`, `/assets/` uchun `max-age=31536000, immutable` (hozirgi namuna `mini-app/docker/nginx.conf` da). Tayyor namunalar: `backend/docker/nginx.conf` va `mini-app/docker/nginx.conf` (VPS'da `fastcgi_pass` ni PHP-FPM soketiga yo'naltiring). TLS (masalan, Let's Encrypt) o'zingiz sozlaysiz.

Tekshirish: `systemctl is-active rizqim-queue rizqim-queue-reports`, `cat /etc/cron.d/rizqim-scheduler`, `journalctl -u rizqim-queue -u rizqim-queue-reports -f`; `deploy.sh` oxirida navbat xizmatlari yoki cron fayli yo'q bo'lsa ogohlantiradi.

**Fayl huquqlari.** php-fpm, navbat va cron `www-data` nomidan, `deploy.sh` esa deploy foydalanuvchisi nomidan ishlaydi; ikkalasi `backend/storage` va `backend/bootstrap/cache` ga yozadi. Eski tartib (egasi `www-data`, 775) deploy foydalanuvchisining composer va artisan'ini ikkinchi deploydan boshlab to'sib qo'yardi. Endi `deploy.sh` (sudo bilan, deploy boshida va php-fpm qayta ishga tushishidan oldin): egasi deploy foydalanuvchisi, guruhi `www-data` (`APP_GROUP`), `chmod -R ug+rwX`, kataloglarga `g+s` (yangi fayllar guruhni meros qiladi), `umask 002` (yangi fayllar guruh uchun yoziladi). Guruhga qo'shilish shart emas. `composer` va `artisan` deploy foydalanuvchisi nomidan ishlaydi (`vendor/` va `public/` dagi Filament aktivlari ham uniki), `backend/.env` ni shu foydalanuvchi o'qiydi; `www-data` ham o'qiy olishi uchun yuqoridagi `chgrp`/`chmod 640`. Ops buyruqlarini `www-data` nomidan bajaring: `sudo -u www-data php artisan ...` (aks holda sizning nomingizdan yaratilgan log fayliga php-fpm yoza olmay qolishi mumkin; keyingi deploy buni tuzatadi).

## 5. HTTPS va marshrutlash

- Mini App `VITE_API_URL=/api/v1` bilan same-origin ishlaydi: o'z domenidagi `/api/` backendga uzatilishi kerak (compose'dagi `frontend` buni qiladi), shunda CORS kerak emas. Telegram va to'lov webhooklari ham `/api/...` ostida.
- `/admin` va `/superadmin` (Livewire/Filament aktivlari bilan) backend portida (`BACKEND_PORT`) xizmat qilinadi. Mini App nginx'i `/api/` va `/storage/` ni backendga uzatadi (yo'lni o'zgartirmasdan); boshqa yo'llar SPA uchun `index.html` ga qaytadi, `/assets/` dagi yo'q fayl 404 beradi.
- Yuklangan rasm havolalari (`logo_url`, `image_url`) `asset('storage/...')` bilan **so'rov Host'idan** quriladi, `APP_URL` dan emas. Compose'dagi `frontend` `/storage/` ni ham backendga uzatadi va `Host` ni o'zgartirmaydi, shuning uchun havolalar Mini App domenida ochiladi. Boshqa nginx/proksi qo'ysangiz, `/storage/` ni ham yo'naltiring (yoki `/api/` proksisida `Host` ni backend domeniga o'zgartiring), aks holda rasmlar `index.html` qaytaradi. Deploydan keyin API javobidagi rasm URL'ini oching: `200` va `image/*` bo'lishi kerak.
- Backend `X-Forwarded-Proto: https` bo'lsagina `https` havolalar quradi va faqat xususiy tarmoq manzillaridan kelgan `X-Forwarded-For`/`X-Forwarded-Proto` ga ishonadi. `mini-app/docker/nginx.conf` kelgan `X-Forwarded-Proto` ni o'zgartirmay uzatadi va faqat u yo'q so'rovlarda o'zining `$scheme` ini qo'yadi. Shuning uchun TLS'ni compose'dan tashqaridagi proksi tugatsa, u `X-Forwarded-Proto: https` yuborishi shart, aks holda `$scheme` `http` bo'ladi: rasm URL'lari `https://` bilan boshlanishini tekshiring.

## 6. Birinchi ishga tushishdan keyin

Deploy hech qaysi seederni ishga tushirmaydi (`DatabaseSeeder` faqat `SettingSeeder`). Docker: buyruqlarni `docker compose exec -u www-data backend ...` bilan, VPS: `backend/` papkasida (`sudo -u www-data` nomidan) bajaring.

```bash
php artisan db:seed --class=SuperAdminSeeder --force   # SUPERADMIN_EMAIL / SUPERADMIN_PASSWORD bilan
php artisan db:seed --class=LocationSeeder --force     # viloyat/tumanlar; faqat yetishmaganini qo'shadi, qayta ishga tushirish xavfsiz
php artisan db:seed --force                             # ixtiyoriy: SettingSeeder (sms_driver=log va h.k.)
```

1. `/superadmin` ga SUPERADMIN_* bilan kiring. Superadmin > Viloyatlar/Tumanlar da kerakli hududlarni faollashtiring (seeder faqat Andijon viloyati/Asaka tumanini yoqadi).
2. Ixtiyoriy: Superadmin > To'lov Tizimlari da platforma merchant kalitlarini kiriting (`click`, `payme` slug'li qatorlar; qatorlarni `PaymentProviderSeeder` yaratadi (mavjud qatorga tegmaydi, qayta ishga tushirish kiritilgan kalitlarni saqlaydi) yoki panelda qo'lda qo'shasiz; bo'sh qiymat env'ga qaytadi). SMS kerak bo'lsa Superadmin > Tizim Sozlamalari.
3. Superadmin > Restoranlar da birinchi do'konni yarating (egasi, birinchi filial, koordinata va radius). **Kamida bitta faol do'kon bo'lmaguncha Mini App'ga hech kim kira olmaydi** (`POST /auth/telegram` 404 qaytaradi). Egasi uchun Telegram taklif havolasi Restoranlar jadvalidagi "Telegram Link" amalida chiqadi (egasi botga shu havola bilan kirib akkauntini ulaydi).
4. Mini App'ni bot orqali oching, so'ng o'zingizga `php artisan user:assign-role <telegram_id> super_admin` bering (Mini App'da super admin bo'lish uchun).
5. Webhook: deploy `telegram:set-webhook` ni ishga tushirgan; holatni tekshirish: `curl "https://api.telegram.org/bot<TOKEN>/getWebhookInfo"` (`url` va `last_error_message`). Muvaffaqiyatsiz bo'lsa qo'lda: `php artisan telegram:set-webhook [--url=https://...]`.

## 7. Production checklist

| Tekshiruv | Izoh |
|---|---|
| `APP_ENV=production`, `APP_DEBUG=false`, `TELEGRAM_ALLOW_MOCK_AUTH=false` | `app:check-production` buzilganda `exit 1` qiladi: konteyner ishga tushmaydi, `deploy.sh` to'xtaydi. U `APP_ENV`, `APP_DEBUG`, `APP_KEY` (bo'sh bo'lmasligi), `TELEGRAM_ALLOW_MOCK_AUTH`, `TELEGRAM_WEBHOOK_SECRET`, `TELEGRAM_WEBAPP_URL` (`https://`) va standart super admin parolini tekshiradi; Click/Payme kalitlari, Redis, CORS va `SMS_EXPOSE_CODE_IN_RESPONSE` ni **tekshirmaydi** |
| `CLICK_SECRET_KEY`, `PAYME_SECRET_KEY` (yoki Superadmin > To'lov Tizimlari) | Kalit yo'q bo'lsa webhook rad etadi (Click `error -1`, Payme `-32504`): onlayn to'lov hech qachon `paid` bo'lmaydi va buyurtmalar TTL bo'yicha bekor bo'ladi |
| `TELEGRAM_WEBHOOK_SECRET` + `telegram:set-webhook` | Secret bo'sh yoki noto'g'ri belgili bo'lsa `app:check-production` yiqiladi; webhook ro'yxatdan o'tmasa bot `/start` ga va buyurtma tugmalari bosilishiga javob bermaydi (chiquvchi xabarlar ketaveradi) |
| `TELEGRAM_WEBAPP_URL` | Mini App'ning `https://` manzili. Bo'sh bo'lsa bot xabarlaridagi Mini App tugmalari tushirib qoldiriladi (zaxira manzil yo'q) va `app:check-production` yiqiladi; `https://` bilan boshlanmasa ham yiqiladi |
| Standart super admin parolini almashtiring | `12345678` parolli `super_admin` bo'lsa `app:check-production` yiqiladi (lokal seeder `admin@gmail.com` yaratgan bo'lishi mumkin). Almashtirish: Superadmin > Foydalanuvchilar |
| Do'konsiz xodimlar | `app:check-production` ogohlantiradi ("N ta xodim hisobi hech qaysi do'konga biriktirilmagan"), API 403 beradi, panelga kirmaydi. Tuzatish: `php artisan user:assign-role <telegram_id> <rol> --store=<id> [--branch=<id>]` yoki Superadmin > Foydalanuvchilar |
| Redis | `CACHE_STORE`, `QUEUE_CONNECTION`, `SESSION_DRIVER` = `redis`; AOF yoqilgan; `maxmemory-policy` ni `allkeys-*`/`volatile-*` qilmang (navbat/sessiya kalitlari o'chadi); `REDIS_DB` ≠ `REDIS_CACHE_DB`. Navbatni `database` dan Redis'ga o'tkazishdan oldin bo'shating: `php artisan queue:work database --stop-when-empty` |
| `queue`, `queue-reports` va `scheduler` ishlayaptimi | Navbatsiz Telegram xabarlari ketmaydi; `reports` ishchisisiz CSV hisobotlar yuborilmaydi (job `reports` navbatida qotadi); schedulersiz to'lanmagan buyurtmalar bekor bo'lmaydi va kuryer ogohlantirishi chiqmaydi. `php artisan schedule:list` to'rtta vazifani ko'rsatishi kerak |
| CORS | Mini App same-origin bo'lsa kerak emas; aks holda `CORS_ALLOWED_ORIGINS` |
| Xarita va geokodlash | Ochiq Nominatim/OSM tile serverlari faqat ishlab chiqish va kam trafik uchun: `NOMINATIM_BASE_URL`, `NOMINATIM_EMAIL`, `VITE_MAP_TILE_URL`, `VITE_MAP_ATTRIBUTION` |
| SMS | `SettingSeeder` yozgan `settings` qatorlari `SMS_DRIVER`/`ESKIZ_*` env'dan ustun: SMS'ni Superadmin > Tizim Sozlamalari'dan yoqing |
| Zaxira nusxa | Postgres (`pg_dump`) va yuklangan rasmlar (Docker: `backend_storage` volume; VPS: `backend/storage/app/public`) |
| `LocationSeeder`, `PaymentProviderSeeder` | Ikkalasi ham qayta ishga tushirish uchun xavfsiz: faqat yetishmaydigan qatorlarni qo'shadi, hech narsani o'chirmaydi va kiritilgan kalitlar hamda yoqilgan/o'chirilgan holatni saqlaydi |

## 8. Ikki yo'l o'rtasidagi qolgan farqlar

| Mavzu | Docker | VPS |
|---|---|---|
| nginx va TLS | konteyner nginx'lari faqat HTTP (80); TLS tashqarida | vhost va TLS skriptlanmagan, qo'lda |
| Versiyalar | `php:8.3-fpm-alpine`, `postgres:17-alpine`, `redis:7-alpine`, build uchun Node 22 | PHP 8.3 (`PHP_VERSION`), PostgreSQL: o'rnatilgani saqlanadi, bo'lmasa 17, Redis — distributiv versiyasi (6 yoki 7), Node 22 |
| Kesh | `optimize:clear` ishlatilmaydi | har deployda `optimize:clear` kesh bazasini (`REDIS_CACHE_DB`) butunlay tozalaydi: `setting()` keshi, geokod keshi, rate-limit hisoblagichlari, botdan kutilayotgan referral'lar (30 kun) va ops-metrikalar yo'qoladi. Navbat va sessiyalar `REDIS_DB` da qoladi |
| Redis | parol ixtiyoriy (ildiz `REDIS_PASSWORD`), portlari yopiq | `127.0.0.1` da, parolsiz (paket standarti); parol qo'ysangiz `redis.conf` va `backend/.env` ni birga o'zgartiring |
| Loglar | konteyner ichida, yo'qolib ketadi (`LOG_CHANNEL=stderr` yoki `docker compose logs`) | doimiy `backend/storage/logs/laravel.log`, navbat uchun `journalctl` |
| `VITE_*` | ildiz `.env` -> build argument (`docker compose up -d --build frontend`) | `mini-app/.env.production` |
| Migratsiya | `backend` konteyneri har ishga tushganda (u tugamaguncha hech narsa so'rov qabul qilmaydi) | `deploy.sh` ichida, texnik rejim (`down`/`up`) ichida |
| `APP_KEY` | `backend/.env` da oldindan (3-bo'lim) | `backend/.env` da |
| scheduler / queue | `scheduler`, `queue` (`default`), `queue-reports` (`reports`) xizmatlari (`stop_grace_period: 150s`) | cron / `rizqim-queue.service`, `rizqim-queue-reports.service` (`TimeoutStopSec=150`); `queue:restart` ikkala ishchini ham yangilaydi |
| Fayl huquqlari | konteynerda `www-data` (`artisan` ham `www-data` nomidan; ops: `exec -u www-data`) | `deploy.sh` `backend/storage` va `backend/bootstrap/cache` ni deploy foydalanuvchisi:`www-data`, `ug+rwX`, `g+s` qiladi (4-bo'lim) |

## 9. Yangilash, zaxira va orqaga qaytarish

**Yangilash:** Docker — `git pull && docker compose up -d --build`; VPS — `./scripts/deploy.sh` (texnik rejimda: yuqoridagi 0-qadam). Migratsiya oldidan zaxira oling:

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

| Maqsad | Buyruq (VPS'da `backend/` ichida, `sudo -u www-data` nomidan; Docker'da `docker compose exec -u www-data backend ...`) |
|---|---|
| Holat | `docker compose ps`, `docker compose logs -f backend queue queue-reports scheduler`; VPS: `systemctl status rizqim-queue rizqim-queue-reports` |
| Muvaffaqiyatsiz joblar | `php artisan queue:failed`, `php artisan queue:retry all` |
| Scheduler vazifalari | `php artisan schedule:list` |
| Sozlamalarni qayta tekshirish | `php artisan app:check-production`. `.env` ni o'zgartirgandan keyin: VPS — `php artisan config:clear` (keyin `config:cache`); Docker — konteynerni qayta yarating (`docker compose up -d --force-recreate backend queue queue-reports scheduler`) |
| Rol berish | `php artisan user:assign-role <telegram_id> <rol> [--store=<id_yoki_uuid>] [--branch=<id>]` |
| Webhook | `php artisan telegram:set-webhook [--url=...]` |
