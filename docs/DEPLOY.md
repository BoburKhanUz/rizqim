# Deploy: Docker Compose

Production uchun yagona yo'l: Docker Compose (`docker-compose.yml`). Backend deploy qadamlarini `backend/docker/entrypoint.sh` bajaradi (`backend` konteyneri har ishga tushganda):

| # | Qadam |
|---|---|
| 1 | `app:check-production` (xato bo'lsa konteyner ishga tushmaydi) |
| 2 | `migrate --force` |
| 3 | `storage:link`, `filament:upgrade` |
| 4 | `config:cache`, `route:cache`, `view:cache` |
| 5 | `telegram:set-webhook --if-configured` (xato bo'lsa faqat ogohlantirish) |
| 6 | `queue:restart` |

2–6-qadamlarni faqat `backend` konteyneri bajaradi (`RUN_MIGRATIONS=true`); `queue`, `queue-reports`, `scheduler` va `reverb` faqat 1-qadamni bajarib ishga tushadi va `backend` healthcheck'i (`/up`) o'tgunicha kutadi, ya'ni migratsiyadan keyin yangi kod bilan boshlanadi. `artisan` va navbat/scheduler jarayonlari `www-data` nomidan ishlaydi (entrypoint `su-exec` bilan tushiradi).

**Texnik rejim (`artisan down`) kerak emas.** nginx va php-fpm entrypoint tugagach ishga tushadi, shuning uchun migratsiya paytida hech narsa so'rov qabul qilmaydi va yangi kod eski bazada ishlab qolmaydi (masalan, yetkazilgan buyurtmalar hisob-kitob yozuvisiz qolmaydi). Yangilash paytida `backend` qisqa muddat (odatda yarim daqiqadan bir necha daqiqagacha) javob bermaydi: tashqi proksi shu vaqtda `502` qaytaradi. Telegram yetkazilmagan webhook'larni keyinroq qayta yuboradi; to'lov tizimlarining (Click, Payme) qayta yuborishiga tayanmang. Yangilashni kam yuklangan vaqtda qiling.

## 1. Oldindan tayyorlash

- **Server.** Docker Engine va Compose v2 (`docker compose`), `git`. Server qayta yoqilganda Docker o'zi ishga tushishi kerak: xizmatlar `restart: unless-stopped` bilan o'zlari qayta ko'tariladi. Portlar: `FRONTEND_PORT` (8080) va `BACKEND_PORT` (8000) tashqi proksi uchun; Postgres va Redis tashqariga ochilmaydi, ularni va ularning dasturlarini serverga o'rnatish shart emas.
- **Domen va HTTPS.** Telegram Mini App va webhook faqat HTTPS bilan ishlaydi. Compose TLS o'rnatmaydi: sertifikatni tashqi reverse proxy (nginx, Caddy, Cloudflare) beradi (4-bo'lim).
- **Telegram bot** (BotFather): token (`TELEGRAM_BOT_TOKEN`), username (`TELEGRAM_BOT_USERNAME` va Mini App'dagi `VITE_BOT_USERNAME`, `@` siz), Mini App manzili (`TELEGRAM_WEBAPP_URL`).
- **Click / Payme** merchant: maxfiy kalitlar (`CLICK_SECRET_KEY`, `PAYME_SECRET_KEY`) va id'lar. Merchant kabinetida webhook manzillari: `https://<domen>/api/v1/payments/click/webhook` va `https://<domen>/api/v1/payments/payme/webhook`.
- **Maxfiy qiymatlar:** `APP_KEY` — `echo "base64:$(openssl rand -base64 32)"`; `TELEGRAM_WEBHOOK_SECRET` — `openssl rand -hex 32` (faqat `A-Za-z0-9_-` ruxsat etiladi); super admin paroli kamida 12 belgi.

## 2. Muhit o'zgaruvchilari

Hammasining ma'nosi va standarti: [backend/README.md](../backend/README.md), [customer/miniapp/README.md](../customer/miniapp/README.md). Production uchun majburiylari (`backend/.env`):

| O'zgaruvchi | Qiymat |
|---|---|
| `APP_ENV`, `APP_DEBUG` | `production`, `false` |
| `APP_KEY` | `base64:...` (oldindan yozing, 3-bo'limga qarang) |
| `APP_URL` | `https://<domen>` |
| `TELEGRAM_ALLOW_MOCK_AUTH`, `SMS_EXPOSE_CODE_IN_RESPONSE` | `false`, `false` |
| `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | PostgreSQL (qiymatlarni ildizdagi `.env` beradi, `postgres` konteyneri bazani va foydalanuvchini shundan o'zi yaratadi) |
| `CACHE_STORE`, `QUEUE_CONNECTION`, `SESSION_DRIVER` | `redis` |
| `REDIS_CLIENT`, `REDIS_HOST`, `REDIS_PORT`, `REDIS_PASSWORD` | `phpredis`, Redis manzili (`redis`, `6379` va parolni compose o'zi beradi) |
| `REDIS_DB`, `REDIS_CACHE_DB` | `0`, `1` (farqli bo'lsin; `REDIS_URL` ni o'rnatmang) |
| `TELEGRAM_BOT_TOKEN`, `TELEGRAM_BOT_USERNAME`, `TELEGRAM_WEBAPP_URL`, `TELEGRAM_WEBHOOK_SECRET` | 1-bo'limdagi qiymatlar. `TELEGRAM_WEBAPP_URL` majburiy va `https://` bilan boshlanishi shart (`app:check-production` tekshiradi). `APP_URL` HTTPS bo'lmasa `TELEGRAM_WEBHOOK_URL` ham kerak |
| `CLICK_SECRET_KEY`, `PAYME_SECRET_KEY` (+ `CLICK_SERVICE_ID`, `CLICK_MERCHANT_ID`, `PAYME_MERCHANT_ID`) | merchant ma'lumotlari. Standart id'lar `config/services.php` dagi namunalar |
| `SUPERADMIN_EMAIL`, `SUPERADMIN_PASSWORD` | `SuperAdminSeeder` uchun, parol kamida 12 belgi |
| `CORS_ALLOWED_ORIGINS` | faqat Mini App API'ga boshqa domendan murojaat qilsa |
| `AUTH_*` (`AUTH_ACCESS_TOKEN_TTL`, `AUTH_REFRESH_TOKEN_TTL_DAYS`, `AUTH_DEVICE_TOKEN_TTL_DAYS`, `AUTH_STAFF_SESSION_TTL`, `AUTH_PAIRING_CODE_TTL`, `AUTH_PIN_MAX_ATTEMPTS`, `AUTH_PIN_LOCKOUT_SECONDS`) | mobil ilovalar va POS terminallari uchun token muddatlari va PIN cheklovlari; standart qiymatlar yaxshi, ma'nosi `backend/.env.example` va `docs/AUTH.md` da. Mini App tokenlariga ta'sir qilmaydi (`SANCTUM_TOKEN_EXPIRATION`) |
| `LOG_LEVEL` | `warning` tavsiya etiladi: Click, Payme va Telegram webhook payload'lari `info` darajasida to'liq logga yoziladi (shaxsiy ma'lumot bo'lishi mumkin) |

**Ildizdagi `.env`** (compose o'qiydi): `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` (postgres ham, backend ham shundan oladi va `backend/.env` dagilarni bekor qiladi), `REDIS_PASSWORD` (`$` belgisiz), `BACKEND_PORT` (8000), `FRONTEND_PORT` (8080), `VITE_BOT_USERNAME`, `VITE_API_URL`, `VITE_MAP_TILE_URL`, `VITE_MAP_ATTRIBUTION`, real-time uchun `BROADCAST_CONNECTION` (standart `null`; kalitlar bilan `reverb`), `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`, `REVERB_ALLOWED_ORIGINS` va `VITE_REVERB_*` (9-bo'lim; bular `backend/.env` dagilarni bekor qiladi). Ildizdagi `.env.example` ning pastki qismidagi `APP_ENV`, `CACHE_STORE`, `SUPERADMIN_*` kabi qatorlar faqat eslatma: konteyner ularni `backend/.env` dan o'qiydi, ildizdagi `.env` dan emas.

## 3. Ishga tushirish

Xizmatlar: `postgres` (17), `redis` (7, AOF, parol ixtiyoriy), `backend` (nginx + php-fpm, deploy qadamlarini shu bajaradi), `queue` (`--queue=default,broadcasts`: Telegram xabarlari va h.k., so'ng Reverb eventlari), `queue-reports` (`--queue=reports`: og'ir CSV hisobotlar, `SendOrderReport`), `scheduler`, `reverb` (WebSocket, 9-bo'lim), `frontend` (nginx). `queue`, `queue-reports`, `scheduler`, `reverb` va `frontend` `backend` healthcheck'i (`/up`) o'tgunicha kutadi.

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
- **Versiyalar.** `php:8.3-fpm-alpine` (+ `ext-redis` va Reverb uchun `ext-uv`), `postgres:17-alpine` (CI ham 17; eski deployment'ni 17 ga o'tkazish: `docs/OPERATIONS.md`, 7-bo'lim), `redis:7-alpine`, Mini App build'i uchun Node 22. Redis'da parol ixtiyoriy (ildiz `REDIS_PASSWORD`), portlari yopiq.
- **`VITE_*` qiymatlari** ildizdagi `.env` dan build argument sifatida o'tadi: o'zgartirgandan keyin `docker compose up -d --build frontend`.
- Birinchi ishga tushishdan keyingi qadamlar (super admin, birinchi do'kon) 5-bo'limda.

## 4. HTTPS va marshrutlash

- Mini App `VITE_API_URL=/api/v1` bilan same-origin ishlaydi: o'z domenidagi `/api/` backendga uzatilishi kerak (compose'dagi `frontend` buni qiladi), shunda CORS kerak emas. Telegram va to'lov webhooklari ham `/api/...` ostida.
- `/admin` va `/superadmin` (Livewire/Filament aktivlari bilan) backend portida (`BACKEND_PORT`) xizmat qilinadi. Mini App nginx'i `/api/` va `/storage/` ni backendga uzatadi (yo'lni o'zgartirmasdan); boshqa yo'llar SPA uchun `index.html` ga qaytadi, `/assets/` dagi yo'q fayl 404 beradi.
- Yuklangan rasm havolalari (`logo_url`, `image_url`) `asset('storage/...')` bilan **so'rov Host'idan** quriladi, `APP_URL` dan emas. Compose'dagi `frontend` `/storage/` ni ham backendga uzatadi va `Host` ni o'zgartirmaydi, shuning uchun havolalar Mini App domenida ochiladi. Boshqa nginx/proksi qo'ysangiz, `/storage/` ni ham yo'naltiring (yoki `/api/` proksisida `Host` ni backend domeniga o'zgartiring), aks holda rasmlar `index.html` qaytaradi. Deploydan keyin API javobidagi rasm URL'ini oching: `200` va `image/*` bo'lishi kerak.
- Backend `X-Forwarded-Proto: https` bo'lsagina `https` havolalar quradi va faqat xususiy tarmoq manzillaridan kelgan `X-Forwarded-For`/`X-Forwarded-Proto` ga ishonadi. `customer/miniapp/docker/nginx.conf` kelgan `X-Forwarded-Proto` ni o'zgartirmay uzatadi va faqat u yo'q so'rovlarda o'zining `$scheme` ini qo'yadi. Shuning uchun TLS'ni compose'dan tashqaridagi proksi tugatsa, u `X-Forwarded-Proto: https` yuborishi shart, aks holda `$scheme` `http` bo'ladi: rasm URL'lari `https://` bilan boshlanishini tekshiring.

## 5. Birinchi ishga tushishdan keyin

Deploy hech qaysi seederni ishga tushirmaydi (`DatabaseSeeder` faqat `SettingSeeder`). Buyruqlarni `docker compose exec -u www-data backend ...` bilan bajaring.

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

## 6. Production checklist

| Tekshiruv | Izoh |
|---|---|
| `APP_ENV=production`, `APP_DEBUG=false`, `TELEGRAM_ALLOW_MOCK_AUTH=false` | `app:check-production` buzilganda `exit 1` qiladi: konteyner ishga tushmaydi. U `APP_ENV`, `APP_DEBUG`, `APP_KEY` (bo'sh bo'lmasligi), `TELEGRAM_ALLOW_MOCK_AUTH`, `TELEGRAM_WEBHOOK_SECRET`, `TELEGRAM_WEBAPP_URL` (`https://`) va standart super admin parolini tekshiradi; Click/Payme kalitlari, Redis, CORS va `SMS_EXPOSE_CODE_IN_RESPONSE` ni **tekshirmaydi** |
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
| Zaxira nusxa | Postgres (`pg_dump`) va yuklangan rasmlar (`backend_storage` volume); tartibi: `docs/OPERATIONS.md`, 4-bo'lim |
| Real-time (Reverb) | `BROADCAST_CONNECTION=reverb` va `REVERB_APP_ID`/`KEY`/`SECRET` to'ldirilgan (ildiz `.env`), `REVERB_ALLOWED_ORIGINS` = Mini App hosti, `docker compose ps` da `reverb` healthy, Mini App build'ida `VITE_REVERB_APP_KEY` = `REVERB_APP_KEY`. Tashqi proksi `/app/` uchun WebSocket upgrade'ni o'tkazishi shart (9-bo'lim). `app:check-production` `reverb` + bo'sh kalitni rad etadi, `REVERB_ALLOWED_ORIGINS=*` da ogohlantiradi |
| `LocationSeeder`, `PaymentProviderSeeder` | Ikkalasi ham qayta ishga tushirish uchun xavfsiz: faqat yetishmaydigan qatorlarni qo'shadi, hech narsani o'chirmaydi va kiritilgan kalitlar hamda yoqilgan/o'chirilgan holatni saqlaydi |

## 7. Yangilash, zaxira va orqaga qaytarish

**Yangilash:** `git pull && docker compose up -d --build` (deploy qadamlari: yuqoridagi jadval). Migratsiya oldidan zaxira oling (`backup` xizmati bilan: `docker compose exec backup sh /usr/local/bin/backup.sh`, yoki oddiy SQL dump):

```bash
docker compose exec -T postgres sh -c 'pg_dump -U "$POSTGRES_USER" "$POSTGRES_DB"' > backup-$(date +%F).sql
```

**Orqaga qaytarish.** Kod orqaga qaytsa ham migratsiyalar o'z-o'zidan qaytmaydi. Yangi migratsiya eski kodga to'sqinlik qilsa, `down()` yangi kodda bo'lgani uchun avval migratsiyani **yangi kod bilan** qaytaring, keyin kodni almashtiring:

```bash
docker compose exec -u www-data backend php artisan migrate:status
docker compose exec -u www-data backend php artisan migrate:rollback --step=<N> --force
```

Bu repodagi migratsiyalardan ehtiyot talab qiladiganlari (qolganlarining `down()` si o'z o'zgarishini qaytaradi):
- `down()` bo'sh (ma'lumot tuzatuvchi, hech narsa tiklanmaydi): `backfill_commission_amount_for_open_orders`, `mark_open_online_orders_platform_collected`, `normalize_legacy_store_payment_settings`.
- `rename_balance_to_debt_balance_on_stores_table` `stores.balance` ustunini `debt_balance` ga o'zgartirgan; eski kod `balance` ni kutadi, shuning uchun kod va bazani birga qaytaring.
- `add_non_negative_stock_check_to_product_variants_table` manfiy zaxirani 0 ga tenglashtirgan (qaytmaydi); `down()` faqat `CHECK` cheklovini olib tashlaydi.
- `copy_order_status_logs_into_order_status_histories` `down()` da ko'chirilgan qatorlar va `order_status_log_id` ustunini o'chiradi; asl `order_status_logs` jadvali o'zgarmaydi.
- `add_promo_code_id_to_orders_table` indekslarni `CREATE INDEX CONCURRENTLY` bilan tranzaksiyasiz quradi: yarim yo'lda to'xtasa `INVALID` indeks qolishi mumkin (`\d orders`), uni `DROP INDEX CONCURRENTLY <nom>` bilan o'chirib qayta yurgizing. `add_non_negative_stock_check_...` ham tranzaksiyasiz (`NOT VALID`, so'ng `VALIDATE`), uni qayta yurgizish xavfsiz.
- Eng ishonchli yo'l — `pg_dump` zaxirasidan tiklash.

Kodni qaytarish: eski commit'ni checkout qilib `docker compose up -d --build` (yoki zararli commit'ni `git revert` qilib, `git pull && docker compose up -d --build`). Konteyner ishga tushganda `migrate --force` qayta yuradi: migratsiyani oldin qaytarmagan bo'lsangiz, eski kod yangi sxemada ishlaydi.

## 8. Kundalik buyruqlar

| Maqsad | Buyruq (`docker compose exec -u www-data backend ...` ichida) |
|---|---|
| Holat | `docker compose ps`, `docker compose logs -f backend queue queue-reports scheduler` |
| Muvaffaqiyatsiz joblar | `php artisan queue:failed`, `php artisan queue:retry all` |
| Scheduler vazifalari | `php artisan schedule:list` |
| Sozlamalarni qayta tekshirish | `php artisan app:check-production`. `.env` ni o'zgartirgandan keyin konteynerni qayta yarating (`docker compose up -d --force-recreate backend queue queue-reports scheduler`) |
| Rol berish | `php artisan user:assign-role <telegram_id> <rol> [--store=<id_yoki_uuid>] [--branch=<id>]` |
| Webhook | `php artisan telegram:set-webhook [--url=...]` |
| Eski tokenlarni tozalash | Scheduler o'zi `auth:prune-tokens` ni ishga tushiradi. **`php artisan sanctum:prune-expired` ni hech qachon qo'lda ishga tushirmang:** u `SANCTUM_TOKEN_EXPIRATION` dan eski BARCHA tokenlarni, shu jumladan POS/KDS terminal tokenlarini o'chiradi va hamma terminallar ulanishdan chiqib ketadi (qayta ulash kodi kerak bo'ladi). Tafsilot: `docs/AUTH.md` |

## 9. Real-time: Laravel Reverb (WebSocket)

Buyurtma yaratilganda, holati o'zgarganda va kuryer biriktirilganda Mini App sahifani yangilamasdan bilib oladi. Reverb o'chiq yoki ishlamay qolsa hech narsa buzilmaydi: buyurtma yaratish va holat o'zgartirish so'rovlari muvaffaqiyatli tugaydi (yuborilmagan event faqat Sentry/logga yoziladi), Mini App so'rov (polling) bilan yangilanishda davom etadi.

**Zanjir.** `OrderStatusService` / `CreateOrderAction` → event (tranzaksiya commit bo'lgandan keyin, `queue` ishchisining `broadcasts` navbati orqali, bir urinish) → backend Reverb'ning HTTP API'siga yozadi (`REVERB_HOST:REVERB_PORT`, Docker'da `http://reverb:8080`) → Reverb private kanal obunachilariga WebSocket orqali yuboradi. Brauzer Mini App'ning o'z origin'iga ulanadi: `wss://<domen>/app/<REVERB_APP_KEY>`; `customer/miniapp/docker/nginx.conf` faqat `/app/` ni (WebSocket, `Upgrade`/`Connection` sarlavhalari, 1 soatlik timeout) `reverb:8080` ga uzatadi. Reverb'ning HTTP API'si (`/apps/`) internetga ochilmagan: unga faqat backend ichki tarmoq orqali yozadi.

**Kanallar va ruxsat** (`backend/routes/channels.php`, OrderPolicy bilan bir xil doira). Obuna `POST /api/broadcasting/auth` (`Authorization: Bearer <Sanctum token>`) bilan imzolanadi (foydalanuvchiga daqiqada 60 ta, ortig'i `429`); mehmonga `401`, ruxsatsizga `403`:

| Kanal | Kim qo'shila oladi |
|---|---|
| `private-store.{storeId}` | do'kon egasi, filialsiz menejerlar |
| `private-branch.{branchId}` | shu filial menejerlari, do'kon egasi |
| `private-courier.{userId}` | faqat shu kuryer (kuryer o'rnida buyurtma olgan ega/menejer ham o'z kanaliga) |
| `private-user.{userId}` | faqat shu mijoz |

Do'konsiz xodimlar va super admin hech qaysi do'kon kanaliga qo'shilmaydi.

**Eventlar** (`backend/app/Events/Broadcast`). Payload faqat `{"order":{"uuid","status","store_id","branch_id","courier_id","updated_at"}}` (ism, telefon, manzil yo'q; mijoz buyurtmani API'dan qayta oladi):

| Event | Kanallar |
|---|---|
| `order.created` | store, branch (bo'lsa) |
| `order.status_changed` | store, branch, user (mijoz), courier (bo'lsa; kuryer almashtirilganda oldingisi ham) |
| `order.assigned` | courier, store, branch |

**Sozlash.** Standart `BROADCAST_CONNECTION=null` (real-time o'chiq): bo'sh kalitlar bilan private kanal imzosi bo'sh secret'li HMAC bo'ladi va uni istalgan kishi soxtalashtirib, istalgan do'kon/kuryer/mijoz kanalini tinglay oladi. Shuning uchun `reverb` faqat kalitlar bilan yoqiladi: `BROADCAST_CONNECTION=reverb` va kalitlardan biri bo'sh bo'lsa `app:check-production` konteynerni ishga tushirmaydi. Ildiz `.env` da kalitlarni bir marta yarating, `BROADCAST_CONNECTION=reverb` qiling va `docker compose up -d --build` qiling (frontend build `VITE_REVERB_APP_KEY` ni shu `REVERB_APP_KEY` dan oladi):

```bash
echo "REVERB_APP_ID=$(openssl rand -hex 4)"
echo "REVERB_APP_KEY=$(openssl rand -hex 16)"
echo "REVERB_APP_SECRET=$(openssl rand -hex 32)"   # faqat serverda; Mini App'ga hech qachon kirmaydi
```

`reverb` xizmati (`php artisan reverb:start --host=0.0.0.0 --port=8080`) tashqariga port ochmaydi; healthcheck Reverb'ning `GET /up` yo'li. `REVERB_HOST=reverb`, `REVERB_PORT=8080`, `REVERB_SCHEME=http` ni compose backend/queue/scheduler'ga o'zi beradi. Mini App build argumentlari: `VITE_REVERB_APP_KEY` (bo'sh bo'lsa `REVERB_APP_KEY`), `VITE_REVERB_HOST` (bo'sh = `window.location.hostname`), `VITE_REVERB_PORT` (`443`), `VITE_REVERB_SCHEME` (`https`) — brauzer ko'radigan tashqi HTTPS manzil. `REVERB_ALLOWED_ORIGINS` ga Mini App hostini yozing (masalan `mini.example.uz`; standart `*` — kanallar baribir imzolanadi, lekin `app:check-production` ogohlantiradi). Kalitni almashtirsangiz backend ham, frontend ham qayta build/yaratilishi kerak.

**Katta yuklama.** Backend obrazida `ext-uv` (libuv) o'rnatilgan: Reverb uni o'zi tanlaydi va `stream_select()` ning ~1000 ulanish chegarasidan chiqadi; `reverb` xizmatida `nofile` = 65536. Tekshirish va chegaralar: `docs/OPERATIONS.md`, 6-bo'lim.

**Tashqi TLS proksi.** Compose oldidagi nginx/Caddy/Cloudflare `/app/` uchun WebSocket upgrade'ni o'tkazishi va bo'sh ulanishni kamida 120 s ushlab turishi kerak (Reverb har 60 s ping yuboradi). nginx misoli: `proxy_http_version 1.1; proxy_set_header Upgrade $http_upgrade; proxy_set_header Connection "upgrade"; proxy_read_timeout 3600s;`.

**O'chirish.** `BROADCAST_CONNECTION=null` (yoki `log`, eventlar logga yoziladi) — ildiz `.env` da, so'ng `docker compose up -d --force-recreate backend queue queue-reports scheduler`. Mini App `VITE_REVERB_APP_KEY` bo'sh build qilinsa ulanishga urinmaydi ham.

**Tekshirish.** `docker compose ps reverb` (healthy); `docker compose exec reverb wget -qO- http://127.0.0.1:8080/up`; brauzerning DevTools > Network > WS da `wss://<domen>/app/<key>` `101` va `pusher:connection_established`; buyurtma holatini o'zgartirib `order.status_changed` kelishini ko'ring. Muammo bo'lsa: `docker compose logs --tail=100 reverb queue`, `php artisan queue:failed` (`BroadcastEvent` joblari — backend Reverb'ga yoza olmadi; qayta urinilmaydi, eskirgan eventni `queue:retry` qilish shart emas). Kanal obunasi `403` bo'lsa foydalanuvchining roli/do'koni/filiali kanalga mos emas (yuqoridagi jadval).

## 10. Staging va production'ga ko'tarish

Staging production bilan **bir xil stek** (bir xil Dockerfile'lar va deploy qadamlari), faqat `docker-compose.staging.yml` qo'shiladi. Maqsad: har bir o'zgarishni (migratsiya, Reverb, to'lov oqimi, yangi feature flag) mijozlarga tegmasdan haqiqiy muhitda sinash. Production serverda staging faylini **ishlatmang**.

| | Production | Staging |
|---|---|---|
| Server/domen | asosiy | **alohida** server (yoki alohida domen/portlar), masalan `staging.example.uz` |
| `APP_ENV` / `APP_DEBUG` | `production` / `false` | `staging` / `false` (compose majburlaydi) |
| Baza, volume'lar | `rizqimbot`, `<loyiha>_postgres_data` | `rizqim_staging`, `rizqim_staging_postgres_data` (loyiha nomi `rizqim-staging`) |
| Telegram | haqiqiy bot | alohida **test bot** (BotFather), alohida webhook va `TELEGRAM_WEBHOOK_SECRET` |
| To'lov (Click/Payme) | haqiqiy merchant | faqat **test (sandbox)** merchant kalitlari |
| E-pochta / SMS | haqiqiy | `MAIL_MAILER=log`, `SMS_DRIVER=log` (compose majburlaydi): hech kimga xabar ketmaydi |
| Qidiruv tizimlari | ochiq | `X-Robots-Tag: noindex` (Mini App va backend) va `robots.txt` `Disallow: /` |
| Resurslar | cheklovsiz | xotira/CPU chegaralari, kichik Postgres (`shared_buffers=64MB`) |
| Zaxira | 14 kun + S3 | 3 kun; S3 ixtiyoriy (production bucket'iga yozmang) |
| Sentry | `production` | alohida loyiha yoki `staging` environment |

### Ishga tushirish (alohida serverda)

```bash
git clone <repo-url> rizqim && cd rizqim
cp .env.staging.example .env                    # compose uchun; ichida COMPOSE_FILE=docker-compose.yml:docker-compose.staging.yml
cp backend/.env.staging.example backend/.env    # ilova uchun
# Ikkala faylni to'ldiring: DB paroli, APP_KEY (echo "base64:$(openssl rand -base64 32)"), TELEGRAM_BOT_TOKEN (TEST bot),
# TELEGRAM_WEBAPP_URL va APP_URL (staging domeni, https), TELEGRAM_WEBHOOK_SECRET (openssl rand -hex 32), Reverb kalitlari
# (3-bo'lim va 9-bo'lim), CLICK_*/PAYME_* (faqat test), SUPERADMIN_* (production parolidan farqli)
docker compose config -q                         # staging fayli ham o'qilganini tekshiring: docker compose config | head -1  ->  name: rizqim-staging
docker compose up -d --build
docker compose ps
curl -fsS http://127.0.0.1:8100/up
docker compose exec -u www-data backend php artisan db:seed --class=SuperAdminSeeder --force
docker compose exec -u www-data backend php artisan db:seed --class=LocationSeeder --force
```

`COMPOSE_FILE` bo'lmasa har buyruqqa `-f docker-compose.yml -f docker-compose.staging.yml` qo'shing. Domen va HTTPS: 4-bo'limdagidek, staging domeniga alohida sertifikat va tashqi proksi (Mini App `FRONTEND_PORT`=8180 ga, `/admin` va `/superadmin` `BACKEND_PORT`=8100 ga). Telegram test botida Mini App manzili `TELEGRAM_WEBAPP_URL`; deploy `telegram:set-webhook` ni o'zi ishga tushiradi. Birinchi do'kon va superadmin: 5-bo'lim (test ma'lumotlari bilan). Faqat bitta xostda ishlatsangiz, `.env` dagi portlar (8100/8180) productionnikidan farq qilsin; loyiha va volume nomlari allaqachon alohida.

**`app:check-production` staging'da.** Buyruq `APP_ENV=production` **yoki** `staging` ni qabul qiladi. Qolgan hamma shartlar staging'da ham o'zgarishsiz tekshiriladi (debug o'chiq, `APP_KEY`, Telegram secret va HTTPS manzil, Reverb kalitlari, standart super admin paroli yo'q): staging internetda turadi, shuning uchun "staging deb" zaiflashtirilmaydi. Production-only shart bitta: `APP_ENV` qiymatining o'zi (`local`/`testing` va boshqalar ikkalasida ham rad etiladi). Muvaffaqiyatli bo'lsa chiqadi: "Staging sozlamalari tekshiruvdan o'tdi".

**Production ma'lumotlarini staging'ga ko'chirmang.** Zarur bo'lsa faqat anonimlashtirilgan dump yuklang va keyin: `Superadmin > Tizim Sozlamalari` da `sms_driver=log` (bazadagi qiymat env'dan ustun, aks holda haqiqiy mijozlarga SMS ketadi), do'konlarning Telegram bot tokenlari va to'lov kalitlarini test qiymatlarga almashtiring.

### Staging'da tekshiruv ro'yxati

1. `docker compose ps`: hamma xizmat `healthy`; `docker compose logs backend | grep -i "tekshiruvdan o'tdi"`.
2. `curl -I https://<staging-domen>/` va `/superadmin/login`: `X-Robots-Tag: noindex, nofollow, noarchive`; `/robots.txt` — `Disallow: /`.
3. Superadmin'ga kirish, test do'kon va mahsulot yaratish; test bot orqali Mini App'dan buyurtma berish; holatni o'zgartirganda real-time yangilanishi (9-bo'lim).
4. Click/Payme sandbox to'lovi (webhook'lar `https://<staging-domen>/api/v1/payments/...`).
5. Yangi migratsiyalar toza bazada ham, oldingi release ma'lumotlari ustida ham o'tdi (`docker compose logs backend`).
6. Yangi feature flag bo'lsa: Superadmin > Sozlamalar > Feature flaglar da bitta test do'konda yoqib, `GET /api/v1/features` ni tekshiring.

### Build'ni production'ga ko'tarish (promote)

Qoida: production'ga **staging'da sinalgan aniq commit** boradi, shuning uchun u git tegi bilan belgilanadi (tegni bir marta qo'yasiz, ikkala serverda shu teg olinadi). Obrazlar har serverda shu commit'dan yig'iladi (bir xil Dockerfile va `composer.lock`). Mini App build'i `VITE_*` qiymatlarni (bot, Reverb kaliti, Sentry) ichiga yozadi, shuning uchun `frontend` obrazi staging va production uchun baribir alohida yig'iladi (staging obrazini production'ga ko'chirib bo'lmaydi).

```bash
# 1. Staging: commit'ni joylab sinab ko'ring
git fetch && git checkout <commit-yoki-branch> && docker compose up -d --build
#    ... tekshiruv ro'yxati (yuqorida) o'tdi ...
git rev-parse --short HEAD                        # masalan 3fa9c1e: Sentry release va tegga nom

# 2. Tegni qo'ying (staging'da yoki lokal, so'ng push)
git tag -a release-2026-10-21-1 -m "staging'da sinalgan: 3fa9c1e" <commit>
git push origin release-2026-10-21-1

# 3. Production: kam yuklangan vaqtda
git fetch --tags
docker compose exec backup sh /usr/local/bin/backup.sh       # migratsiya oldidan zaxira (7-bo'lim)
git checkout release-2026-10-21-1
# backend/.env: SENTRY_RELEASE=3fa9c1e; ildiz .env: VITE_SENTRY_RELEASE=3fa9c1e (build argumenti)
docker compose up -d --build                                  # production fayli faqat docker-compose.yml
docker compose ps && curl -fsS https://<domen>/api/v1/health
```

- **Production serverda `COMPOSE_FILE` bo'lmasin** va `.env` ga `.env.staging.example` ni nusxalamang: staging fayli production'da resurslarni cheklab, `APP_ENV=staging` qilib qo'yadi.
- **Migratsiyalar orqaga mos bo'lsin.** Orqaga qaytishda eski kod yangi sxemada ishlab qoladi (7-bo'lim), shuning uchun ustunni o'chirish yoki nomini o'zgartirishni ikki release'ga bo'ling: avval kod eski va yangi ustunni birga qo'llaydi, keyingi release'da eskisi o'chiriladi.
- **Yangi imkoniyatni xavfsiz yoyish.** Katta o'zgarishni feature flag orqasida chiqaring (`docs/OPERATIONS.md`, 8-bo'lim): production'ga flag o'chiq holda tushadi, so'ng Superadmin > Feature flaglar da avval bitta restoranda, keyin boshqalarda yoqiladi. Muammo bo'lsa kod qaytarilmaydi, flag o'chiriladi.
- **Orqaga qaytarish:** oldingi tegga `git checkout <eski-teg> && docker compose up -d --build` (7-bo'lim: migratsiya `down()` lari va zaxiradan tiklash).
- Production'dan keyin tegni va commit'ni jamoaga yozib qo'ying (Sentry release nomi bilan bir xil), shunda xato qaysi release'dan boshlanganini topish oson bo'ladi.

