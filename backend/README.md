# Rizqim backend

Laravel 12 asosidagi REST API (`/api/v1`, Mini App uchun) va ikkita Filament paneli: `/admin` (do'kon paneli: `owner`, `manager`; `super_admin` istalgan do'konga kira oladi) hamda `/superadmin` (platforma boshqaruvi). Umumiy tavsif va biznes qoidalari: [../README.md](../README.md), deploy: [../docs/DEPLOY.md](../docs/DEPLOY.md).

## Talablar

- PHP 8.3+ (`composer.lock` 8.3 dan pastini qabul qilmaydi). Kengaytmalar: `pdo_pgsql`, `pgsql`, `redis` (phpredis), `intl`, `gd`, `zip`, `bcmath`, `mbstring`, `xml`, `curl`, `pcntl`; productionda `opcache`.
- Composer 2.
- PostgreSQL — majburiy, testlar uchun ham: kod PostgreSQL'ga xos SQL ishlatadi (`TRUNCATE ... CASCADE`, `payload->>'time'`, `CREATE INDEX CONCURRENTLY`). Docker'da 17, CI'da 16.
- Redis (kesh, navbat, sessiya, rate-limit, bloklar). Redis'siz ishlash: `CACHE_STORE`, `QUEUE_CONNECTION`, `SESSION_DRIVER` ni `database` qiling (jadvallari migratsiyada bor).
- Node.js kerak emas: `backend/package.json` Laravel'ning standart Vite sozlamasi, API va panellar uchun ishlatilmaydi. Filament aktivlarini `composer install` ichidagi `filament:upgrade` e'lon qiladi.

## Lokal ishga tushirish

```bash
cd backend
cp .env.example .env
composer install
php artisan key:generate
```

`.env` da lokal uchun o'zgartiring: `APP_ENV=local`, `APP_DEBUG=true`, `APP_URL=http://127.0.0.1:8000`, `DB_*` (bo'sh PostgreSQL bazasi, masalan `createdb order_mini_app`) va `REDIS_*`. Postgres va Redis'ni o'zingizga o'rnating yoki `backend/compose.yaml` (Laravel Sail'ning standart fayli) dagi `pgsql` va `redis` xizmatlarini ishlating: `docker compose -f compose.yaml up -d pgsql redis` (portlar hostga chiqadi, `testing` bazasi ham yaratiladi). Bu fayl deploy uchun ishlatilmaydi. Ildizdagi `docker-compose.yml` esa Postgres/Redis portlarini tashqariga ochmaydi va production rejimini talab qiladi.

```bash
php artisan migrate
php artisan db:seed --class=SuperAdminSeeder   # local/testing da SUPERADMIN_* bo'sh bo'lsa: admin@gmail.com / 12345678
php artisan db:seed --class=LocationSeeder     # viloyat va tumanlar (qayta ishga tushirish xavfsiz, pastga qarang)
php artisan db:seed                            # DatabaseSeeder = faqat SettingSeeder
php artisan storage:link                       # yuklangan rasmlar uchun (public disk)

php artisan serve          # http://127.0.0.1:8000 (Mini App dev serveri /api ni shu yerga proksilaydi)
php artisan queue:work --queue=default,reports   # alohida terminalda: Telegram xabarlari (default) va hisobotlar (reports); productionda ikkita alohida ishchi
php artisan schedule:work  # alohida terminalda: to'lanmagan buyurtmalarni bekor qilish va h.k.
```

**Birinchi kirish tartibi.** Mini App'ga kirish (`POST /api/v1/auth/telegram`) kamida bitta `is_active` do'kon bo'lishini talab qiladi, aks holda 404 `Store not found or bot not configured.` qaytadi (mijoz ham, super admin ham kira olmaydi). Shuning uchun avval `/superadmin` ga email + parol bilan kiring va "Restoranlar" bo'limida birinchi do'konni yarating (egasi, birinchi filial, hudud; viloyat/tumanlar ro'yxati uchun `LocationSeeder` kerak). Mini App orqali super admin bo'lish uchun Mini App'ni bir marta oching va `php artisan user:assign-role <telegram_id> super_admin` ni bajaring.

**Telegramsiz sinash (oddiy brauzer).** `.env`: `APP_ENV=local`, `TELEGRAM_ALLOW_MOCK_AUTH=true`, `TELEGRAM_BOT_TOKEN` ga istalgan bo'sh bo'lmagan qiymat; Mini App'ni `npm run dev` bilan oching (u dev rejimida soxta foydalanuvchi yaratadi va uning id'sini brauzer `localStorage` dagi `mock_telegram_id` kalitiga yozadi). Xodim rolini sinash: `php artisan user:assign-role <mock_telegram_id> owner --store=<id>`. Haqiqiy botni webhooksiz sinash: `php artisan telegram:poll`.

### Seederlar

| Seeder | Nima qiladi | Eslatma |
|---|---|---|
| `DatabaseSeeder` | faqat `SettingSeeder` ni chaqiradi | qolganlarini `--class=` bilan ishga tushiring; Docker/VPS deploy hech qaysi seederni o'zi ishga tushirmaydi |
| `SettingSeeder` | `settings` jadvaliga `sms_driver=log`, `eskiz_email`, `eskiz_password` (bo'sh), `eskiz_from=4546` yozadi (mavjudini o'zgartirmaydi) | bu qatorlar (bo'sh bo'lsa ham) `SMS_DRIVER`/`ESKIZ_*` env'dan ustun; SMS'ni Superadmin > Tizim Sozlamalari'dan yoqing |
| `SuperAdminSeeder` | `SUPERADMIN_EMAIL`/`SUPERADMIN_PASSWORD` bilan `super_admin` yaratadi | `local`/`testing` dan boshqa muhitda ikkalasi majburiy, parol kamida 12 belgi; mavjud email'ning paroli va roli o'zgarmaydi |
| `LocationSeeder` | 14 hudud (viloyatlar, Qoraqalpog'iston, Toshkent shahri) va ularning tumanlari | Idempotent: `firstOrCreate` bilan faqat yetishmaydigan hudud va tumanlarni qo'shadi, hech narsani o'chirmaydi va o'zgartirmaydi (`TRUNCATE` yo'q), shuning uchun ishlaydigan bazada ham xavfsiz. Faqat o'zi yaratgan qatorlar uchun Andijon viloyati/Asaka tumani faol bo'ladi; panelda yoqilgan/o'chirilgan holat saqlanadi, qolganini Superadmin > Viloyatlar/Tumanlar'da yoqing |
| `PaymentProviderSeeder` | `payme`, `click`, `uzum`, `paynet` qatorlarini bo'sh kalitlar bilan yaratadi | Idempotent (`firstOrCreate`): mavjud qatorga tegmaydi, qayta ishga tushirish kiritilgan kalitlar va yoqilgan holatni saqlaydi |
| `PromoCodeSeeder` | demo promo-kodlar `TEZFOOD` (10%), `RIZQIM_81` (10%), `YANGI2026` (15%), har mijozga 1 marta | faqat ishlab chiqish uchun |

## Muhit o'zgaruvchilari (`.env`)

Namuna: [.env.example](.env.example). "Standart" ustunida `.env.example` qiymati ko'rsatilgan (boshqacha bo'lsa config standarti qavs ichida). Docker'da `DB_*` va `REDIS_*` ni `docker-compose.yml` ildizdagi `.env` dan olib o'zi beradi.

**Ilova**

| O'zgaruvchi | Standart | Ma'nosi |
|---|---|---|
| `APP_NAME` | `Laravel` | ilova nomi (kesh va sessiya prefikslarida ham) |
| `APP_ENV` | `production` | `production` bo'lmasa `app:check-production` deploy'ni to'xtatadi; lokal: `local` |
| `APP_KEY` | bo'sh | shifrlash kaliti, `php artisan key:generate`; majburiy. Almashtirilsa eskisini `APP_PREVIOUS_KEYS` ga yozing |
| `APP_DEBUG` | `false` | productionda `true` bo'lishi mumkin emas |
| `APP_URL` | `http://localhost` | ommaviy manzil; webhook URL'i, geokoder `User-Agent`'i va CORS ruxsatlari ham shundan olinadi |
| `APP_LOCALE`, `APP_FALLBACK_LOCALE`, `APP_FAKER_LOCALE` | `en`, `en`, `en_US` | Laravel tili |
| `APP_MAINTENANCE_DRIVER` | `file` | texnik ish rejimi |
| `BCRYPT_ROUNDS` | `12` | parol xeshlash |
| `LOG_CHANNEL`, `LOG_STACK`, `LOG_LEVEL` | `stack`, `single`, `debug` | loglar `storage/logs/laravel.log` ga; productionda `LOG_LEVEL=warning` tavsiya etiladi (webhook payload'lari `info` da logga tushadi) |

**Ma'lumotlar bazasi, Redis, kesh**

| O'zgaruvchi | Standart | Ma'nosi |
|---|---|---|
| `DB_CONNECTION` | `pgsql` (config: `sqlite`) | faqat PostgreSQL qo'llab-quvvatlanadi |
| `DB_HOST`, `DB_PORT` | `127.0.0.1`, `5432` | |
| `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | `order_mini_app`, `postgres`, `postgres` | |
| `CACHE_STORE` | `redis` (config: `database`) | kesh; rate-limit hisoblagichlari, geokod keshi, `setting()` keshi shu yerda |
| `QUEUE_CONNECTION` | `redis` (config: `database`) | navbat; testlarda `sync` |
| `SESSION_DRIVER` | `redis` (config: `database`) | faqat Filament panellari sessiyasi; `SESSION_LIFETIME=120` |
| `REDIS_CLIENT` | `phpredis` | |
| `REDIS_HOST`, `REDIS_PORT`, `REDIS_PASSWORD` | `127.0.0.1`, `6379`, `null` | |
| `REDIS_DB`, `REDIS_CACHE_DB` | `0`, `1` | navbat/sessiya va kesh bazalari; farqli bo'lishi shart, chunki `cache:clear` kesh bazasini butunlay tozalaydi. `REDIS_URL` ni o'rnatmang (ikkalasini birlashtirib yuboradi) |

**Telegram**

| O'zgaruvchi | Standart | Ma'nosi |
|---|---|---|
| `TELEGRAM_BOT_TOKEN` | bo'sh | BotFather tokeni; bo'sh bo'lsa hech qanday xabar yuborilmaydi va `telegram:set-webhook --if-configured` jim o'tadi |
| `TELEGRAM_BOT_USERNAME` | bo'sh | `@` siz; deep-linklar shundan quriladi, Mini App'dagi `VITE_BOT_USERNAME` bilan bir xil bo'lsin |
| `TELEGRAM_WEBAPP_URL` | bo'sh | Mini App'ning HTTPS manzili: bot tugmalari va CORS ruxsatlari uchun (productionda majburiy) |
| `TELEGRAM_WEBHOOK_SECRET` | bo'sh | majburiy; faqat `A-Z a-z 0-9 _ -`, 1–256 belgi; webhook shu sarlavha bilan himoyalangan |
| `TELEGRAM_WEBHOOK_URL` | bo'sh | bo'sh bo'lsa `APP_URL` + `/api/v1/telegram/webhook`; HTTPS bo'lishi shart |
| `TELEGRAM_ALLOW_MOCK_AUTH` | `false` | imzosiz `mock_` initData; faqat `APP_ENV=local` bilan ishlaydi, productionda doim `false` |
| `TELEGRAM_SUPERADMIN_CHAT_ID` | bo'sh | ixtiyoriy; settlement xatolari haqidagi ogohlantirishlar shu chatga yuboriladi |

**Kirish va xavfsizlik**

| O'zgaruvchi | Standart | Ma'nosi |
|---|---|---|
| `SANCTUM_TOKEN_EXPIRATION` | `43200` | API token muddati, daqiqada (30 kun); har foydalanuvchida ko'pi bilan 5 ta faol token |
| `CORS_ALLOWED_ORIGINS` | bo'sh | vergul bilan originlar; bo'sh bo'lsa `TELEGRAM_WEBAPP_URL` va `APP_URL` originlari (Mini App odatda `/api` ni o'z nginx'i orqali same-origin chaqiradi) |
| `SUPERADMIN_EMAIL`, `SUPERADMIN_PASSWORD` | bo'sh | faqat `SuperAdminSeeder` uchun; productionda majburiy, parol kamida 12 belgi |
| `SMS_EXPOSE_CODE_IN_RESPONSE` | `false` | OTP kodini javobda qaytaradi; faqat `APP_ENV=local` bilan, productionda `false` |

**To'lov, buyurtma, geokodlash, SMS**

| O'zgaruvchi | Standart | Ma'nosi |
|---|---|---|
| `CLICK_SECRET_KEY`, `PAYME_SECRET_KEY` | bo'sh | platforma merchant kalitlari; bo'sh bo'lsa webhooklar rad etadi |
| `CLICK_SERVICE_ID`, `CLICK_MERCHANT_ID`, `PAYME_MERCHANT_ID` | namunaviy qiymatlar `config/services.php` da | o'zingiznikiga almashtiring. Superadmin > To'lov Tizimlari (`payment_providers`, shifrlangan) qiymati bo'sh bo'lmasa env'dan ustun |
| `ORDERS_UNPAID_ONLINE_TTL_MINUTES` | `30` | to'lanmagan onlayn buyurtma shuncha daqiqadan keyin bekor qilinadi, `0` — o'chirilgan (scheduler kerak) |
| `ORDERS_SETTLEMENT_RETRY_MAX_ATTEMPTS` | `12` | xato bergan settlement'ni `RetryFailedSettlementsJob` necha marta qayta urinadi; keyin `orders:retry-settlements` bilan qo'lda |
| `ORDERS_REPORTS_QUEUE` | `reports` | hisobot eksporti navbati; shu navbatni tinglaydigan worker bo'lmasa eksportlar navbatda qoladi |
| `ORDERS_CASH_PICKUP_BAN_WINDOW_DAYS` | `30` | naqd + olib ketish taqiqi oynasi (kun), `0` — muddatsiz (`.env.example` da yo'q) |
| `ORDERS_CASH_PICKUP_BAN_THRESHOLD` | `1` | taqiq uchun bekor qilingan buyurtmalar soni, `0` — taqiq o'chirilgan (`.env.example` da yo'q) |
| `GEOCODING_PROVIDER` | `nominatim` | hozircha faqat `nominatim` |
| `GEOCODING_CACHE_TTL` | `604800` | geokod natijasi keshi, soniya (kamida 86400) |
| `NOMINATIM_BASE_URL` | `https://nominatim.openstreetmap.org` | productionda o'z serveringiz yoki shartnomali provayder |
| `NOMINATIM_EMAIL` | bo'sh | `User-Agent` dagi aloqa e-pochtasi; bo'sh bo'lsa `APP_URL` |
| `NOMINATIM_MIN_INTERVAL` | `1` | butun platforma bo'yicha so'rovlar orasidagi pauza (soniya); `0` faqat o'z serveringiz uchun |
| `SMS_DRIVER`, `ESKIZ_EMAIL`, `ESKIZ_PASSWORD`, `ESKIZ_FROM` | `log`, bo'sh, bo'sh, `4546` | SMS (`log` yoki `eskiz`). `settings` jadvalidagi qiymat (Superadmin > Tizim Sozlamalari) ustun, env faqat u yerda qiymat bo'lmasa ishlatiladi. Driver `eskiz` bo'lsa Mini App telefonni SMS bilan tasdiqlashni talab qiladi |

`MAIL_*`, `AWS_*`, `MEMCACHED_HOST`, `VITE_APP_NAME`, `BROADCAST_CONNECTION`, `FILESYSTEM_DISK` — Laravel skeletidan qolgan standart sozlamalar: loyiha pochta yubormaydi, S3 va broadcasting ishlatmaydi (yuklangan rasmlar `public` diskka yoziladi).

## Artisan buyruqlari

| Buyruq | Vazifasi |
|---|---|
| `user:assign-role {telegram_id} {role} [--store=ID_yoki_UUID] [--branch=ID]` | Rol berish (`super_admin`, `owner`, `manager`, `courier`, `customer`). `owner`/`manager`/`courier` uchun `--store` majburiy. Foydalanuvchi Mini App'ni kamida bir marta ochgan bo'lishi kerak. `audit_logs` ga `cli_role_assigned` yoziladi |
| `app:check-production` | Xavfli sozlamalarda `exit 1`: `APP_ENV` `production` emas, `APP_DEBUG=true`, `APP_KEY` bo'sh (production kalitni o'zi yaratmaydi), `TELEGRAM_ALLOW_MOCK_AUTH=true`, `TELEGRAM_WEBHOOK_SECRET` bo'sh yoki noto'g'ri belgili, `TELEGRAM_WEBAPP_URL` bo'sh yoki `https://` bilan boshlanmaydi, super adminda standart parol `12345678`. Do'konsiz xodim hisoblari haqida faqat ogohlantiradi. Click/Payme kalitlari, Redis va CORS tekshirilmaydi. Docker entrypoint va `scripts/deploy.sh` ishga tushiradi |
| `telegram:set-webhook [--url=] [--if-configured]` | Webhookni `secret_token` va `allowed_updates` (`message`, `callback_query`) bilan ro'yxatdan o'tkazadi. URL tartibi: `--url`, `TELEGRAM_WEBHOOK_URL`, `APP_URL`; HTTPS shart. `--if-configured`: token yo'q bo'lsa jim chiqadi |
| `telegram:poll` | Lokal long polling: botning webhookini o'chiradi va yangilanishlarni `http://127.0.0.1:8000/api/v1/telegram/webhook` ga secret bilan yuboradi (`TELEGRAM_BOT_TOKEN`, `TELEGRAM_WEBHOOK_SECRET` kerak). Productionda ishlatmang |
| `queue:work --queue=default --tries=3 --max-time=3600` | Asosiy navbat ishchisi (Docker'dagi `queue` xizmati va `rizqim-queue.service` shu flaglar bilan) |
| `queue:work --queue=reports --memory=512 --timeout=150 --tries=1 --max-time=3600` | `reports` navbati ishchisi (Docker'da `queue-reports`, VPS'da `rizqim-queue-reports.service`; PHP `memory_limit=768M` bilan). `SendOrderReport` shu navbatga qo'yiladi |
| `schedule:work` / `schedule:run` | Scheduler: lokal/Docker'da `schedule:work`, VPS'da cron orqali har daqiqada `schedule:run` |
| `queue:restart`, `queue:failed`, `queue:retry all` | Deploydan keyin ishchilarni yangilash (ikkala ishchini ham); muvaffaqiyatsiz joblarni ko'rish va qayta urinish |
| `migrate --force`, `storage:link`, `filament:upgrade`, `config:cache`, `route:cache`, `view:cache` | Deploy qadamlari (tartibi [../docs/DEPLOY.md](../docs/DEPLOY.md) da) |

## Navbat va scheduler

Docker'da artisan buyruqlarini `www-data` nomidan bajaring: `docker compose exec -u www-data backend php artisan ...` (`exec` standart bo'yicha root; root yaratgan `storage/logs/laravel.log` ga php-fpm yoza olmaydi). Fayl yuklash chegaralari (`backend/docker/php.ini`: `upload_max_filesize=12M`, `post_max_size=16M`; nginx `client_max_body_size` 16m, Mini App nginx'i 10m; API rasmni 5 MB bilan cheklaydi) haqida: [../docs/DEPLOY.md](../docs/DEPLOY.md).

`php artisan schedule:list` hozir to'rtta vazifani ko'rsatadi:

| Vazifa | Jadval | Izoh |
|---|---|---|
| `CancelUnpaidOnlineOrdersJob` (`routes/console.php`) | har daqiqa, `withoutOverlapping(10)` | to'lanmagan onlayn buyurtmalarni bekor qiladi; navbatga tushmaydi, scheduler ichida bajariladi |
| `CheckUnacceptedAssignmentsJob` (`bootstrap/app.php`) | har daqiqa | `ShouldQueue`: kuryer biriktirilganiga 15 daqiqa bo'lib ham yo'lga chiqmasa, filial menejeriga bir marta Telegram ogohlantirishi |
| `RetryFailedSettlementsJob` (`routes/console.php`) | har 10 daqiqa, `withoutOverlapping(30)` | yetkazilgan, lekin hisob-kitob yozuvi muvaffaqiyatsiz bo'lgan buyurtmalarni qayta yozadi; navbatga tushmaydi. Qo'lda: `php artisan orders:retry-settlements <id_yoki_uuid>...` |
| `sanctum:prune-expired --hours=24` | har kuni | muddati o'tgan tokenlarni o'chiradi |

Navbat ishchisi bajaradigan joblar: `SendTelegramRequest` (har bir Telegram Bot API chaqiruvi; commit'dan keyin yuboriladi, 5 urinish, kutish 10/30/90/300 s, 429 va tarmoq xatolari qayta uriniladi), `SendOrderReport` (CSV hisobot, 3 urinish, 120 s; alohida `reports` navbatida, shuning uchun uni `--queue=reports` ishchisi bajaradi), navbatdagi listenerlar (`SendCustomerStatusAlert`, `SendOrderCancelledAlert`, `SendOwnerFinanceAlert`) va `CheckUnacceptedAssignmentsJob`. `default` ishchisi bo'lmasa Telegram xabarlari, `reports` ishchisi bo'lmasa hisobotlar ketmaydi; scheduler bo'lmasa to'lanmagan buyurtmalar bekor qilinmaydi.

## Testlar

```bash
createdb testing                    # bir marta; DB_USERNAME shu bazada jadval yarata olishi kerak
composer test                       # config:clear + php artisan test
php artisan test --filter=OrderLifecycle
```

- PHPUnit 11. `phpunit.xml` o'rnatadi: `APP_ENV=testing`, `DB_DATABASE=testing`, `DB_URL=` (bo'sh), `CACHE_STORE=array`, `QUEUE_CONNECTION=sync`, `SESSION_DRIVER=array`, `MAIL_MAILER=array`, `BCRYPT_ROUNDS=4` — Redis kerak emas. Shell'da oldindan eksport qilingan o'zgaruvchi `phpunit.xml` dan ustun keladi.
- Qolgan ulanish sozlamalari (`DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_USERNAME`, `DB_PASSWORD`) `.env` dan olinadi, shuning uchun `.env` PostgreSQL'ga ishora qilishi (`DB_CONNECTION=pgsql`) va `APP_KEY` bo'lishi kerak. Baza nomi `phpunit.xml` dagi `testing` bo'lgani uchun ishlab chiqish bazangizga tegilmaydi.
- Testlar `RefreshDatabase` bilan har safar migratsiyalarni qayta yuritadi. `config:cache` qilingan bo'lsa avval `php artisan config:clear` (`composer test` buni o'zi qiladi).
- `tests/Unit/NoEnvOutsideConfigTest` `app/`, `routes/`, `database/` da `env()` chaqirilmasligini tekshiradi (`config:cache` dan keyin `env()` `null` qaytaradi): yangi o'zgaruvchi uchun `config/` ga kalit qo'shing.
- CI (`.github/workflows/ci.yml`): PostgreSQL 16 servisi, `.env.example` dan `.env` (`sed` bilan `APP_ENV=testing` va `DB_*`), `key:generate`, `php artisan test`.

## Statik tahlil (PHPStan / Larastan)

Sozlama: `phpstan.neon` (level 5; `app/`, `config/`, `database/`, `routes/`; Larastan kengaytmasi va bo'sh `phpstan-baseline.neon`).

```bash
vendor/bin/phpstan analyse --memory-limit=1G
```

Larastan ilovani yuklaydi (bazaga ulanmaydi), shuning uchun `.env` bo'lishi kerak (CI'da `APP_ENV=testing`). `larastan/larastan` va `phpstan/phpstan` `require-dev` da; ular GitHub'dan (`composer.lock` dagi `api.github.com` zipball manzillari) yuklanadi. GitHub'ga chiqish cheklangan muhitda o'rnatish muvaffaqiyatsiz bo'lishi va `vendor/bin/phpstan` bo'lmasligi mumkin — u holda tahlilni CI'dagi `backend-static-analysis` jobi bajaradi. Bu job hozircha ogohlantiruvchi (`continue-on-error: true`); workflow izohiga ko'ra birinchi baseline (`vendor/bin/phpstan analyse --generate-baseline`) yaratilgach bu qator olib tashlanib, job majburiy qilinadi.

## Kod tuzilmasi (`app/`)

| Papka | Nima bor |
|---|---|
| `Actions/` | `Auth/` (initData tekshirish, Telegram login, token berish), `Order/CreateOrderAction` (buyurtma yaratish: tekshiruvlar, zaxira, promo, keshbek) |
| `Services/Order/` | `OrderStatusService` (holat o'zgarishining yagona joyi, bekor qilish/qaytarish oqibatlari), `OrderQueryService`, `PaymentLinkService`, `CourierListService`, `OrderReportService` |
| `Services/Finance/` | `LedgerService`: komissiya, hisob-kitob, top-up, yechib olish, qaytarish, bloklash |
| `Services/Promo/`, `Services/Auth/` | `PromoCodeService`; `ReferralService` |
| `Services/Telegram/`, `Services/Notifications/` | bot xabarlari va tugmalar (`TelegramMessageHandler`, `TelegramCallbackHandler`), navbatga qo'yuvchi `TelegramMessenger`, buyurtma kartochkalari, ilova ichidagi xabarnoma lentasi |
| `Services/Geo/`, `Services/Sms/`, `Services/Ops/` | geokodlash (`GeocoderManager` > `CachedGeocoder` > `NominatimGeocoder`); Eskiz SMS; panel metrikalari (`Domain/Ops/` qiymat obyektlari bilan) |
| `Enums/` | `OrderStatus` (o'tish xaritasi), `OrderType`, `PaymentStatus`, `StoreStatus`, `UserRole`, `StoreTransactionType` va b. |
| `Policies/` | `OrderPolicy` (rolga ko'ra holat o'zgartirish), `StorePolicy`, `BranchPolicy`, `ProductPolicy`, `UserPolicy`, ... |
| `Http/Controllers/Api/V1/` | `Auth`, `Catalog`, `Order`, `Payment` (Click/Payme webhook), `Store` (`Owner*`), `Superadmin`, `Telegram`, `User`, `GeocodeController`, `PromoCodeController` |
| `Http/Middleware/` | `EnsureUserHasRole` (`role:owner,manager`), `EnsureStaffHasStore` (`staff.store`), `VerifyTelegramSecretToken` |
| `Jobs/`, `Events/`, `Listeners/` | yuqoridagi joblar; `OrderCreated`, `OrderStatusUpdated`, `OrderCancelled`, `PaymentPaid`, `StoreBalanceLow` va ularning listenerlari (avtomatik topiladi) |
| `Console/Commands/` | `user:assign-role`, `app:check-production`, `telegram:set-webhook`, `telegram:poll` |
| `Filament/` | `/admin` paneli (tenant — do'kon, slug `uuid`): `Resources/` (Orders, Products, Branches, Employees), `Pages/`, `Widgets/`; `Superadmin/` — `/superadmin` paneli; `Support/StorePanelScope` — panel so'rovlarini tenant do'kon (filial menejeri uchun filial) bilan cheklaydi. Kirish `CustomLogin`: email yoki telefon + parol |
| `Models/`, `Traits/`, `Support/Money`, `Helpers/settings.php` | Eloquent modellar; `ApiResponse`, `Auditable` (super admin o'zgarishlari `audit_logs` ga), `HasPublicUuid`; aniq o'nli pul arifmetikasi; `setting()` yordamchisi |

Qoidalar: holat faqat `OrderStatusService::transition` orqali o'zgaradi; qarz/hamyon faqat `LedgerService` orqali; pul — faqat `Money` (float emas); `env()` faqat `config/` ichida.

## API qisqacha

- Javob shakli: `{ "success": bool, "message": string, "data": ... }`; validatsiya xatosi 422 (`errors.<maydon>`); `api/*` da xatolar doim JSON. `GET /up` — sog'liq tekshiruvi.
- Himoya: `auth:sanctum` (Bearer token) + `staff.store` + `throttle:api` (300/daqiqa, foydalanuvchi yoki IP bo'yicha). Webhooklar (`/payments/click/webhook`, `/payments/payme/webhook`) autentifikatsiyasiz va limitsiz, imzo/kalit bilan tekshiriladi; `/telegram/webhook` secret sarlavhasi bilan.
- Alohida limitlar (`AppServiceProvider`): `telegram-auth` (IP bo'yicha 300/daqiqa, Telegram id + IP bo'yicha 10/daqiqa), `otp-send` (1/daqiqa va 5/soat — foydalanuvchi va telefon bo'yicha), `otp-verify` 10, `orders` 10, `promo` 20, `store-ratings` 10, `complaints` 5, `geocode` 30 (daqiqasiga).
- Proksi: faqat `127.0.0.1`, `::1` va xususiy tarmoqlar (`10/8`, `172.16/12`, `192.168/16`) ishonchli, faqat `X-Forwarded-For`/`X-Forwarded-Proto` hisobga olinadi.
