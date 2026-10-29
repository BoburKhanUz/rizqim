# Deploy: Docker Compose

Production uchun yagona yo'l: Docker Compose (`docker-compose.yml`). Backend deploy qadamlarini `backend/docker/entrypoint.sh` bajaradi (`backend` konteyneri har ishga tushganda):

| # | Qadam |
|---|---|
| 1 | `app:check-production` (xato bo'lsa konteyner ishga tushmaydi) |
| 2 | `migrate --force` |
| 3 | `storage:link` |
| 4 | `config:cache`, `route:cache`, `view:cache` |
| 5 | `telegram:set-webhook --if-configured` (xato bo'lsa faqat ogohlantirish) |
| 6 | `queue:restart` |

2–6-qadamlarni faqat `backend` konteyneri bajaradi (`RUN_MIGRATIONS=true`); `queue`, `queue-reports`, `scheduler` va `reverb` faqat 1-qadamni bajarib ishga tushadi va `backend` healthcheck'i (`/up`) o'tgunicha kutadi, ya'ni migratsiyadan keyin yangi kod bilan boshlanadi. `artisan` va navbat/scheduler jarayonlari `www-data` nomidan ishlaydi (entrypoint `su-exec` bilan tushiradi).

**Texnik rejim (`artisan down`) kerak emas.** nginx va php-fpm entrypoint tugagach ishga tushadi, shuning uchun migratsiya paytida hech narsa so'rov qabul qilmaydi va yangi kod eski bazada ishlab qolmaydi (masalan, yetkazilgan buyurtmalar hisob-kitob yozuvisiz qolmaydi). Yangilash paytida `backend` qisqa muddat (odatda yarim daqiqadan bir necha daqiqagacha) javob bermaydi: tashqi proksi shu vaqtda `502` qaytaradi. Telegram yetkazilmagan webhook'larni keyinroq qayta yuboradi; to'lov tizimlarining (Click, Payme) qayta yuborishiga tayanmang. Yangilashni kam yuklangan vaqtda qiling.

## 1. Oldindan tayyorlash

- **Server.** Docker Engine va Compose v2 (`docker compose`), `git`. Server qayta yoqilganda Docker o'zi ishga tushishi kerak: xizmatlar `restart: unless-stopped` bilan o'zlari qayta ko'tariladi. Portlar: `FRONTEND_PORT` (8080), `BACKEND_PORT` (8000) va hamkor veb-panel uchun `PARTNER_WEB_PORT` (3000, faqat `127.0.0.1` da, 4-bo'lim) tashqi proksi uchun; Postgres va Redis tashqariga ochilmaydi, ularni va ularning dasturlarini serverga o'rnatish shart emas.
- **Domen va HTTPS.** Telegram Mini App va webhook faqat HTTPS bilan ishlaydi. Compose TLS o'rnatmaydi: sertifikatni tashqi reverse proxy (nginx, Caddy, Cloudflare) beradi (4-bo'lim).
- **Telegram bot** (BotFather): token (`TELEGRAM_BOT_TOKEN`), username (`TELEGRAM_BOT_USERNAME` va Mini App'dagi `VITE_BOT_USERNAME`, `@` siz), Mini App manzili (`TELEGRAM_WEBAPP_URL`).
- **Click / Payme** merchant: maxfiy kalitlar (`CLICK_SECRET_KEY`, `PAYME_SECRET_KEY`) va id'lar. Merchant kabinetida webhook manzillari: `https://<domen>/api/v1/payments/click/webhook` va `https://<domen>/api/v1/payments/payme/webhook`.
- **Maxfiy qiymatlar:** `APP_KEY` — `echo "base64:$(openssl rand -base64 32)"`; `TELEGRAM_WEBHOOK_SECRET` — `openssl rand -hex 32` (faqat `A-Za-z0-9_-` ruxsat etiladi); super admin paroli (`SuperAdminSeeder` ishlatilsa) kamida 12 belgi.

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
| `SUPERADMIN_EMAIL`, `SUPERADMIN_PASSWORD` | faqat `SuperAdminSeeder` uchun (ixtiyoriy, parol kamida 12 belgi). Birinchi superadmin odatda `rizqim:admin:create` bilan yaratiladi (5-bo'lim) |
| `ADMIN_APP_URL` | boshqaruv markazining manzili (`https://admin.<domen>`): taklif havolalari shundan yasaladi (4-bo'lim) |
| `PARTNER_WEB_URL` | hamkor paneli manzili (`https://panel.<domen>`): ovqatxona balansini onlayn to'ldirgan ega to'lovdan keyin panelning Moliya sahifasiga (`/finance`) qaytadi. Bo'sh bo'lsa `APP_URL` ga qaytadi. Buyurtma to'lovidan keyin mijoz Mini App'ga (`TELEGRAM_WEBAPP_URL`) qaytadi |
| `CORS_ALLOWED_ORIGINS` | Mini App API'ga boshqa domendan murojaat qilsa; hamkor veb-panel (`partner/web`) ishga tushirilsa, uning origin'i shu yerga yoziladi (ro'yxat berilsa, standart originlar o'chadi: kerak bo'lsa ularni ham yozing) |
| `AUTH_*` (`AUTH_ACCESS_TOKEN_TTL`, `AUTH_REFRESH_TOKEN_TTL_DAYS`, `AUTH_DEVICE_TOKEN_TTL_DAYS`, `AUTH_STAFF_SESSION_TTL`, `AUTH_PAIRING_CODE_TTL`, `AUTH_PIN_MAX_ATTEMPTS`, `AUTH_PIN_LOCKOUT_SECONDS`) | mobil ilovalar va POS terminallari uchun token muddatlari va PIN cheklovlari; standart qiymatlar yaxshi, ma'nosi `backend/.env.example` va `docs/AUTH.md` da. Mini App tokenlariga ta'sir qilmaydi (`SANCTUM_TOKEN_EXPIRATION`) |
| `FIREBASE_CREDENTIALS_JSON` (yoki `FIREBASE_CREDENTIALS` fayl yo'li), ixtiyoriy `FIREBASE_PROJECT_ID`, `PUSH_ANDROID_CHANNEL_ID` | Push xabarlar (P1-22). Bo'sh bo'lsa push o'chiq turadi va hech narsa buzilmaydi. Service account kalitini `base64 -w0 kalit.json` bilan bir qatorga o'tkazib bering; qadamlari `docs/PUSH.md` da |
| `LOG_LEVEL` | `warning` tavsiya etiladi: Click, Payme va Telegram webhook payload'lari `info` darajasida to'liq logga yoziladi (shaxsiy ma'lumot bo'lishi mumkin) |

**Ildizdagi `.env`** (compose o'qiydi): `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` (postgres ham, backend ham shundan oladi va `backend/.env` dagilarni bekor qiladi), `REDIS_PASSWORD` (`$` belgisiz), `BACKEND_PORT` (8000), `BACKEND_BIND` (standart `127.0.0.1`: backend portiga faqat shu serverdagi tashqi proksi ulanadi), `FRONTEND_PORT` (8080), hamkor veb-panel uchun `PARTNER_WEB_PORT` (3000), `PARTNER_WEB_BIND` (standart `127.0.0.1`), `PARTNER_PUBLIC_API_BASE_URL` va `PARTNER_SESSION_COOKIE_SECURE` (4-bo'lim), `VITE_BOT_USERNAME`, `VITE_API_URL`, `VITE_MAP_TILE_URL`, `VITE_MAP_ATTRIBUTION`, `VITE_PARTNER_WEB_URL` (Mini App xodim ekranlaridagi "Web panelda tahrirlash" havolasi — hamkor paneli manzili, `https://panel.<domen>`; bo'sh = havolasiz; build vaqtida, o'zgartirgach `docker compose up -d --build frontend`), real-time uchun `BROADCAST_CONNECTION` (standart `null`; kalitlar bilan `reverb`), `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`, `REVERB_ALLOWED_ORIGINS` va `VITE_REVERB_*` (9-bo'lim; bular `backend/.env` dagilarni bekor qiladi). Ildizdagi `.env.example` ning pastki qismidagi `APP_ENV`, `CACHE_STORE`, `SUPERADMIN_*` kabi qatorlar faqat eslatma: konteyner ularni `backend/.env` dan o'qiydi, ildizdagi `.env` dan emas.

## 3. Ishga tushirish

Xizmatlar: `postgres` (17), `redis` (7, AOF, parol ixtiyoriy), `backend` (nginx + php-fpm, deploy qadamlarini shu bajaradi), `queue` (`--queue=default,broadcasts`: Telegram xabarlari va h.k., so'ng Reverb eventlari), `queue-reports` (`--queue=reports`: og'ir CSV hisobotlar, `SendOrderReport`), `scheduler`, `reverb` (WebSocket, 9-bo'lim), `frontend` (nginx), `partner-web` (hamkor veb-panel, Next.js, 4-bo'lim), `platform-admin` (RIZQIM boshqaruv markazi, Next.js, 4-bo'lim, «RIZQIM boshqaruv markazi»). `queue`, `queue-reports`, `scheduler`, `reverb`, `frontend`, `partner-web` va `platform-admin` `backend` healthcheck'i (`/up`) o'tgunicha kutadi.

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

- **`APP_KEY`ni oldindan yozing.** Entrypoint kalit yaratmaydi (`key:generate` yo'q): `APP_KEY` bo'sh bo'lsa `app:check-production` konteynerni ishga tushirmaydi. Sabab: web, queue va scheduler alohida konteynerlar, ularning har biri o'z kalitini olardi va konteyner qayta yaratilganda kalit o'zgarardi, natijada sessiyalar va shifrlangan ma'lumotlar (masalan, boshqaruv markazida kiritilgan to'lov tizimlari kalitlari) o'qilmay qolardi.
- Konteyner `APP_ENV=production` va boshqa xavfsizlik shartlari bajarilmasa ishga tushmaydi, shuning uchun lokal ishlab chiqish uchun compose mos emas ([backend/README.md](../backend/README.md)).
- Portlar: Mini App `FRONTEND_PORT` da (statika; `/api/` va `/storage/` ni backendga uzatadi), backend'ning o'zi (`/up`, `/health`, API) `BACKEND_PORT` da, hamkor veb-panel `PARTNER_WEB_PORT` da, boshqaruv markazi `ADMIN_WEB_PORT` da. Postgres va Redis tashqariga ochilmagan.
- Volume'lar: `postgres_data`, `redis_data`, `backend_storage` (= `storage/app`; yuklangan rasmlar shu yerda).
- Loglar: `storage/logs/laravel.log` konteyner ichida (volume emas, konteyner qayta yaratilganda yo'qoladi): `docker compose exec backend tail -f storage/logs/laravel.log`. `docker compose logs` da ko'rish uchun `backend/.env` ga `LOG_CHANNEL=stderr`.
- Yangilash: `git pull && docker compose up -d --build`. Faqat `.env` o'zgarganda: `docker compose up -d --force-recreate` (`config:cache` konteyner ishga tushganda yig'iladi). `docker compose down -v` volume'larni, ya'ni bazani ham o'chiradi.
- **Ops buyruqlari `www-data` nomidan:** `docker compose exec -u www-data backend php artisan ...`. `exec` standart bo'yicha root: root yaratgan `storage/logs/laravel.log` ni php-fpm (`www-data`) yoza olmaydi va logga yozadigan har bir so'rov 500 beradi. (Entrypoint o'zi `artisan` ni `www-data` bilan ishga tushiradi va konteyner qayta ishga tushganda `storage/logs`, `storage/framework`, `bootstrap/cache` egaligini tuzatadi.) `config:clear` ni ishlayotgan `backend` konteynerida bajarmang: sozlamalar konteyner ishga tushganda keshlanadi (`config:cache`), o'zgartirish uchun konteynerni qayta yarating.
- **Fayl yuklash chegaralari.** Zanjir: Mini App nginx `client_max_body_size 10m` (API rasmni 5 MB bilan cheklaydi) → backend nginx `16m` → PHP `post_max_size=16M` ≥ `upload_max_filesize=12M` (`backend/docker/php.ini`; API rasmni o'zi 5 MB bilan cheklaydi, qolgani zaxira). Standart nginx (1 MB) va PHP (2 MB) chegaralari rasmlarni rad etardi; chegarani oshirsangiz, uchalasini birga oshiring.
- **Backend manzili qayta yaratilganda.** Mini App nginx'i `backend` nomini Docker DNS (`127.0.0.11`) orqali har so'rovda qayta aniqlaydi (5 s keshlanadi), shuning uchun `docker compose up -d --build backend` dan keyin frontend'ni qayta ishga tushirish shart emas. Backend tayyor bo'lgunicha `/api/` va `/storage/` 502 qaytaradi.
- **Mini App keshi.** `index.html` har safar qayta tekshiriladi (`Cache-Control: no-cache`), `/assets/*` (nomida hash bor) bir yil `immutable` keshlanadi: deploydan keyin eski `index.html` yo'q bo'lib ketgan bo'laklarni so'ramaydi.
- **Versiyalar.** `php:8.3-fpm-alpine` (+ `ext-redis` va Reverb uchun `ext-uv`), `postgres:17-alpine` (CI ham 17; eski deployment'ni 17 ga o'tkazish: `docs/OPERATIONS.md`, 7-bo'lim), `redis:7-alpine`, Mini App build'i uchun Node 22. Redis'da parol ixtiyoriy (ildiz `REDIS_PASSWORD`), portlari yopiq.
- **`VITE_*` qiymatlari** ildizdagi `.env` dan build argument sifatida o'tadi: o'zgartirgandan keyin `docker compose up -d --build frontend`.
- Birinchi ishga tushishdan keyingi qadamlar (birinchi superadmin, birinchi do'kon) 5-bo'limda.

## 4. HTTPS va marshrutlash

- Mini App `VITE_API_URL=/api/v1` bilan same-origin ishlaydi: o'z domenidagi `/api/` backendga uzatilishi kerak (compose'dagi `frontend` buni qiladi), shunda CORS kerak emas. Telegram va to'lov webhooklari ham `/api/...` ostida.
- Backend o'zida veb-panel yo'q (P4-33 dan beri): RIZQIM jamoasi boshqaruv markazida (`admin.<domen>`), ovqatxona egalari va xodimlari hamkor panelida (`panel.<domen>`) ishlaydi. Backend'ning `/` manzili `PARTNER_WEB_URL` ga yo'naltiradi (bo'sh bo'lsa 404). Mini App nginx'i `/api/` va `/storage/` ni backendga uzatadi (yo'lni o'zgartirmasdan); boshqa yo'llar SPA uchun `index.html` ga qaytadi, `/assets/` dagi yo'q fayl 404 beradi.
- Yuklangan rasm havolalari (`logo_url`, `image_url`) `asset('storage/...')` bilan **so'rov Host'idan** quriladi, `APP_URL` dan emas. Compose'dagi `frontend` `/storage/` ni ham backendga uzatadi va `Host` ni o'zgartirmaydi, shuning uchun havolalar Mini App domenida ochiladi. Boshqa nginx/proksi qo'ysangiz, `/storage/` ni ham yo'naltiring (yoki `/api/` proksisida `Host` ni backend domeniga o'zgartiring), aks holda rasmlar `index.html` qaytaradi. Deploydan keyin API javobidagi rasm URL'ini oching: `200` va `image/*` bo'lishi kerak.
- Backend `X-Forwarded-Proto: https` bo'lsagina `https` havolalar quradi va faqat xususiy tarmoq manzillaridan kelgan `X-Forwarded-For`/`X-Forwarded-Proto` ga ishonadi. `customer/miniapp/docker/nginx.conf` kelgan `X-Forwarded-Proto` ni o'zgartirmay uzatadi va faqat u yo'q so'rovlarda o'zining `$scheme` ini qo'yadi. Shuning uchun TLS'ni compose'dan tashqaridagi proksi tugatsa, u `X-Forwarded-Proto: https` yuborishi shart, aks holda `$scheme` `http` bo'ladi: rasm URL'lari `https://` bilan boshlanishini tekshiring.

### Hamkor veb-panel (`partner-web`)

Restoran egasi, menejeri va boshqa xodimlar uchun veb-panel ([partner/web/README.md](../partner/web/README.md)). Compose'da
`partner-web` xizmati (`partner/web/Dockerfile`, build konteksti repozitoriy ildizi, Mini App kabi), port `PARTNER_WEB_PORT`
(3000). Mini App kabi, TLS'ni tashqi proksi beradi; panel **alohida hostda** turadi:

| Host | Tashqi proksi qayerga uzatadi |
|---|---|
| `<domen>` (Mini App) | `FRONTEND_PORT` |
| `panel.<domen>` (hamkor paneli) | `PARTNER_WEB_PORT` |

- **Muhit (ildiz `.env`).** `PARTNER_PUBLIC_API_BASE_URL` — brauzer API'ga murojaat qiladigan ochiq manzil, `/api` bilan
  tugaydi, odatda Mini App domenidagi `/api` (`https://<domen>/api`; Mini App nginx'i uni backendga uzatadi). Bo'sh qolsa
  panel ishlamaydi: Next serveri backend'ga compose tarmog'i ichida (`API_BASE_URL=http://backend/api`, compose o'zi beradi)
  murojaat qiladi, bu manzil brauzerga ko'rinmaydi. `PARTNER_SESSION_COOKIE_SECURE` standart `true` (cookie faqat HTTPS
  orqali); `false` faqat `http://` orqali sinash uchun.
- **Real-time va xarita.** Buyurtmalar doskasi Reverb'ga ulanadi: ildiz `.env` dagi `REVERB_APP_KEY` (backend bilan bir
  xil) panelga ham beriladi; `PARTNER_REVERB_HOST` bo'sh bo'lsa `PARTNER_PUBLIC_API_BASE_URL` host'i olinadi va brauzer
  `wss://<domen>/app/...` ga ulanadi (Mini App nginx'i uni `reverb` ga uzatadi). Kalit bo'sh bo'lsa doska har 15 soniyada
  so'rov bilan yangilanadi. Brauzer `Origin: https://panel.<domen>` yuboradi: `REVERB_ALLOWED_ORIGINS` da `panel.<domen>`
  bo'lmasa Reverb ulanishni rad etadi (4009) va doska jim, faqat polling bilan ishlaydi (9-bo'lim). Hududlar xaritasi plitkalari: `PARTNER_MAP_TILE_URL` (va `PARTNER_MAP_ATTRIBUTION`); bo'sh =
  ochiq OpenStreetMap serveri, u faqat sinov uchun — production'da hisobli provayder. Hammasi runtime'da o'qiladi.
  `PARTNER_MAP_ATTRIBUTION` (va Mini App'ning `VITE_MAP_ATTRIBUTION`) xaritada **HTML** sifatida chiqadi (Leaflet uni
  escape qilmaydi, provayder havolasi `<a href="...">` yozish mumkin): u faqat administrator yozadigan ishonchli matn,
  foydalanuvchi kiritgan narsa bu yerga tushmasin.
- **CORS (`backend/.env`).** Brauzer API'ga panel origin'idan murojaat qiladi: `CORS_ALLOWED_ORIGINS` da panel origin'i
  bo'lishi shart, masalan `CORS_ALLOWED_ORIGINS=https://panel.<domen>`. Ro'yxat berilsa standart originlar
  (`TELEGRAM_WEBAPP_URL`, `APP_URL`) o'chadi; Mini App same-origin ishlagani uchun odatda bu muammo emas.
- **Proksi.** `Host` sarlavhasini o'zgartirmay uzating (yoki `X-Forwarded-Host` yuboring): panelning kirish so'rovlari
  (`/api/session/*`) `Origin` ni shu host bilan solishtiradi, boshqa host bilan 403 qaytaradi. nginx misoli:
  `proxy_set_header Host $host; proxy_set_header X-Forwarded-Proto https; proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;`.
- **Mijoz IP manzili.** Kirish so'rovlari (SMS kod, kodni tekshirish, refresh) backend'ga Next serveri orqali boradi;
  panel backend'ga mijozning IP manzilini `X-Forwarded-For` da uzatadi (backend uni faqat xususiy tarmoqdan, ya'ni
  compose tarmog'idagi `partner-web` dan qabul qiladi). Usiz backend'ning IP bo'yicha limitlari (SMS: daqiqada 1 ta,
  noto'g'ri kod, refresh) va audit IP'si barcha hamkorlar uchun bitta bo'lardi. Panel kelgan `X-Forwarded-For` ning
  faqat **oxirgi** yozuvini oladi — uni tashqi proksi yozadi (`$proxy_add_x_forwarded_for` yoki `$remote_addr`);
  brauzer yuborgan chapdagi yozuvlar e'tiborga olinmaydi. Shuning uchun `partner-web` porti standart bo'yicha faqat
  `127.0.0.1` da ochiladi (`PARTNER_WEB_BIND`): proksini chetlab portga kelgan kishi o'z IP'sini soxtalashtirib limitlarni
  aylanib o'tardi. Proksi boshqa xostda bo'lsa `PARTNER_WEB_BIND` ni o'zgartiring va portni faydalanuvchilardan
  firewall bilan yoping. Proksi oldida CDN (masalan Cloudflare) bo'lsa, proksi avval haqiqiy IP'ni tiklashi kerak
  (nginx `real_ip` moduli: `set_real_ip_from <CDN tarmoqlari>; real_ip_header CF-Connecting-IP;`), aks holda oxirgi
  yozuv CDN manzili bo'ladi. Mini App va backend portlariga bu shart emas: Mini App nginx'i o'zi ko'rgan manzilni
  zanjir oxiriga qo'shadi.
- **Kirish.** Xodim telefon raqami va SMS kod bilan kiradi (`docs/AUTH.md`, 1-bo'lim). Raqam xodim hisobiga "kirish raqami"
  sifatida biriktirilgan bo'lishi kerak: egasi uchun ovqatxona yaratilganda arizadagi telefon (boshqaruv markazida yangisi:
  ovqatxona kartasi → «Kirish raqami»), boshqa xodimlar uchun hamkor panelidagi Xodimlar sahifasi. Productionda SMS
  drayveri sozlangan bo'lishi kerak (boshqaruv markazi: Tizim → Tizim sozlamalari → Sozlamalar).
- **To'lovdan qaytish.** Ega balansni onlayn to'ldirgach panelning Moliya sahifasiga qaytadi: `backend/.env` da
  `PARTNER_WEB_URL=https://panel.<domen>` (bo'sh bo'lsa `APP_URL` ga qaytadi).
- **Tekshirish.** `docker compose ps` da `partner-web` healthy (`GET /login`); `https://panel.<domen>/login` ochiladi;
  brauzer konsolida API so'rovlarida CORS xatosi yo'q.
- Yangilash: `docker compose up -d --build partner-web` (sozlamalar runtime'da o'qiladi, `.env` o'zgarsa
  `docker compose up -d --force-recreate partner-web` yetadi).

### RIZQIM boshqaruv markazi (`platform-admin`)

Platforma jamoasi (operator, yordam, moliya, savdo, rahbar) uchun veb-ilova ([platform/admin/README.md](../platform/admin/README.md),
xodimlar uchun qo'llanma: [docs/guides/boshqaruv-markazi.md](guides/boshqaruv-markazi.md)). Compose'da `platform-admin` xizmati
(`platform/admin/Dockerfile`, build konteksti repozitoriy ildizi), port `ADMIN_WEB_PORT` (3100). Alohida hostda turadi:

| Host | Tashqi proksi qayerga uzatadi |
|---|---|
| `admin.<domen>` (boshqaruv markazi) | `ADMIN_WEB_PORT` |

- **Muhit (ildiz `.env`).** `ADMIN_SESSION_COOKIE_SECURE` standart `true` (cookie faqat HTTPS orqali; `false` faqat `http://`
  orqali sinash uchun). Taklif havolasini backend yasaydi: `backend/.env` da `ADMIN_APP_URL=https://admin.<domen>`
  bo'lishi shart (ilovaning o'zida bunday o'zgaruvchi yo'q). Backend manzili compose tarmog'ida (`API_BASE_URL=http://backend/api`) beriladi.
- **CORS kerak emas.** Brauzer backend'ga to'g'ridan-to'g'ri murojaat qilmaydi: hamma so'rov ilovaning o'z manzilidan
  (`/api/backend/v1/admin/...`) o'tadi, Next serveri uni backend'ga uzatadi. Admin token (Sanctum, 12 soat, 60 daqiqa
  faolsizlikdan keyin tugaydi) **httpOnly cookie**da turadi, sahifa JavaScript'i uni ko'rmaydi. Shuning uchun
  `CORS_ALLOWED_ORIGINS` ga `admin.<domen>` qo'shish shart emas va `PUBLIC_API_BASE_URL` yo'q. Ilova faqat
  `/v1/admin/*` va `/broadcasting/auth` yo'llarini uzatadi.
- **Proksi.** `Host` sarlavhasini o'zgartirmay uzating (yoki `X-Forwarded-Host` yuboring): yozuvchi so'rovlar `Origin` ni shu
  host bilan solishtiradi. nginx misoli `partner-web` bilan bir xil (`proxy_set_header Host $host; X-Forwarded-Proto https;
  X-Forwarded-For $proxy_add_x_forwarded_for;`).
- **Mijoz IP manzili.** Kirish limitlari (15 daqiqalik bloklash) va jurnaldagi IP uchun ilova `X-Forwarded-For` ning
  **oxirgi** yozuvini backend'ga uzatadi. Shuning uchun port standart bo'yicha faqat `127.0.0.1` da ochiladi
  (`ADMIN_WEB_BIND`): proksini chetlab kelgan kishi IP'sini soxtalashtirib limitlarni aylanib o'tardi. Proksi boshqa xostda
  bo'lsa `ADMIN_WEB_BIND` ni o'zgartiring va portni firewall bilan yoping. CDN bo'lsa, `partner-web` bilan bir xil qoida
  (nginx `real_ip`).
- **Qo'shimcha himoya (ixtiyoriy).** Rejadagi "IP cheklovi" uchun tashqi proksida `admin.<domen>` ga faqat ofis/VPN
  manzillaridan kirishga ruxsat bering (`allow`/`deny`).
- **Birinchi superadmin**, 2FA'siz qolgan superadminlarni qayta ulash va yo'qolgan 2FA'ni tiklash: [OPERATIONS.md](OPERATIONS.md) (`rizqim:admin:create`, `rizqim:admin:invite`, `rizqim:admin:reset-2fa`). 2FA faqat taklif havolasi yoki tiklashdan keyin 24 soat ichida o'rnatiladi.
- **Tekshirish.** `docker compose ps` da `platform-admin` healthy (`GET /login`); `https://admin.<domen>/login` ochiladi.
- Yangilash: `docker compose up -d --build platform-admin` (sozlamalar runtime'da o'qiladi, `.env` o'zgarsa
  `docker compose up -d --force-recreate platform-admin` yetadi). Real-time (`platform.ops` kanali, Reverb) keyingi
  to'lqinda (P4-13) shu xizmatga ulanadi.

## 5. Birinchi ishga tushishdan keyin

Deploy hech qaysi seederni ishga tushirmaydi (`DatabaseSeeder` faqat `SettingSeeder`). Buyruqlarni `docker compose exec -u www-data backend ...` bilan bajaring.

```bash
php artisan rizqim:admin:create boss@rizqim.uz "Bosh admin"   # birinchi superadmin: bir martalik taklif havolasini chiqaradi
php artisan db:seed --class=LocationSeeder --force     # viloyat/tumanlar; faqat yetishmaganini qo'shadi, qayta ishga tushirish xavfsiz
php artisan db:seed --class=PaymentProviderSeeder --force  # ixtiyoriy: click/payme qatorlari (mavjud qatorga tegmaydi)
php artisan db:seed --force                             # ixtiyoriy: SettingSeeder (sms_driver=log va h.k.)
```

Qadamlari batafsil (2FA, jamoa, rollar): [guides/boshqaruv-markazi.md](guides/boshqaruv-markazi.md), «Birinchi ishga tushirish»; buyruqlar: [OPERATIONS.md](OPERATIONS.md), 10-bo'lim.

1. Taklif havolasini oching (`https://admin.<domen>/invite/...`, 72 soat): parol, 2FA va tiklash kodlari. Jamoani **Tizim → Jamoa** dan taklif qiling.
2. **Tizim → Tizim sozlamalari → Viloyatlar va tumanlar** da kerakli hududlarni faollashtiring (seeder faqat Andijon viloyati/Asaka tumanini yoqadi).
3. Ixtiyoriy: **Tizim → Tizim sozlamalari → To'lov tizimlari** da platforma merchant kalitlarini kiriting (bo'sh qiymat env'ga qaytadi). SMS: **Sozlamalar** yorlig'i. Har bir tizim **Faol** va **Ishchi** rejimda ekanini ko'ring (productionda «Sinov»ga faqat `PAYMENTS_ALLOW_TEST_MODE=true` bilan o'tiladi).
4. **Ovqatxonalar → Ulash jarayoni** da birinchi ovqatxonaning arizasini qo'shing va «Ovqatxonani yaratish» bilan ovqatxonaga aylantiring (egasi, birinchi filial, koordinata). **Kamida bitta faol do'kon bo'lmaguncha Mini App'ga hech kim kira olmaydi** (`POST /auth/telegram` 404 qaytaradi). Egasining Telegram taklif havolasi ovqatxona kartasidagi «Telegram havola» amali bilan egasiga SMS'da boradi (egasi botga shu havola bilan kirib akkauntini ulaydi); ega hamkor paneliga telefon va SMS kod bilan kiradi.
5. Webhook: deploy `telegram:set-webhook` ni ishga tushirgan; holatni tekshirish: `curl "https://api.telegram.org/bot<TOKEN>/getWebhookInfo"` (`url` va `last_error_message`). Muvaffaqiyatsiz bo'lsa qo'lda: `php artisan telegram:set-webhook [--url=https://...]`.

## 6. Production checklist

| Tekshiruv | Izoh |
|---|---|
| `APP_ENV=production`, `APP_DEBUG=false`, `TELEGRAM_ALLOW_MOCK_AUTH=false` | `app:check-production` buzilganda `exit 1` qiladi: konteyner ishga tushmaydi. U `APP_ENV`, `APP_DEBUG`, `APP_KEY` (bo'sh bo'lmasligi), `TELEGRAM_ALLOW_MOCK_AUTH`, `TELEGRAM_WEBHOOK_SECRET`, `TELEGRAM_WEBAPP_URL` (`https://`) va standart super admin parolini tekshiradi; Click/Payme kalitlari, Redis, CORS va `SMS_EXPOSE_CODE_IN_RESPONSE` ni **tekshirmaydi** |
| `CLICK_SECRET_KEY`, `PAYME_SECRET_KEY` (yoki boshqaruv markazi: Tizim → To'lov tizimlari) | Kalit yo'q bo'lsa webhook rad etadi (Click `error -1`, Payme `-32504`): onlayn to'lov hech qachon `paid` bo'lmaydi va buyurtmalar TTL bo'yicha bekor bo'ladi |
| To'lov tizimlarining rejimi va yoqilgani (Tizim → To'lov tizimlari) | Productionda «Sinov» rejimidagi tizim Payme to'lovini sinov sahifasiga (`checkout.test.paycom.uz`) yuboradi, o'chirilgani hech bir ovqatxonada taklif qilinmaydi: `app:check-production` ikkalasi haqida **ogohlantiradi** (to'xtatmaydi). `PAYMENTS_ALLOW_TEST_MODE` odatda `false`: productionda sinov rejimiga o'tkazish 422 `test_mode_not_allowed` bilan rad etiladi |
| `TELEGRAM_WEBHOOK_SECRET` + `telegram:set-webhook` | Secret bo'sh yoki noto'g'ri belgili bo'lsa `app:check-production` yiqiladi; webhook ro'yxatdan o'tmasa bot `/start` ga va buyurtma tugmalari bosilishiga javob bermaydi (chiquvchi xabarlar ketaveradi) |
| `TELEGRAM_WEBAPP_URL` | Mini App'ning `https://` manzili. Bo'sh bo'lsa bot xabarlaridagi Mini App tugmalari tushirib qoldiriladi (zaxira manzil yo'q) va `app:check-production` yiqiladi; `https://` bilan boshlanmasa ham yiqiladi |
| Standart super admin parolini almashtiring | `12345678` parolli `super_admin` bo'lsa `app:check-production` yiqiladi (lokal seeder `admin@gmail.com` yaratgan bo'lishi mumkin). Almashtirish: shu hisobni boshqaruv markazida o'chiring (Tizim → Jamoa) yoki `rizqim:admin:invite <email>` bilan yangi parol o'rnating |
| Do'konsiz xodimlar | `app:check-production` ogohlantiradi ("N ta xodim hisobi hech qaysi do'konga biriktirilmagan"), API 403 beradi. Tuzatish: `php artisan user:assign-role <telegram_id> <rol> --store=<id> [--branch=<id>]` |
| Redis | `CACHE_STORE`, `QUEUE_CONNECTION`, `SESSION_DRIVER` = `redis`; AOF yoqilgan; `maxmemory-policy` ni `allkeys-*`/`volatile-*` qilmang (navbat/sessiya kalitlari o'chadi); `REDIS_DB` ≠ `REDIS_CACHE_DB`. Navbatni `database` dan Redis'ga o'tkazishdan oldin bo'shating: `php artisan queue:work database --stop-when-empty` |
| `queue`, `queue-reports` va `scheduler` ishlayaptimi | Navbatsiz Telegram xabarlari ketmaydi; `reports` ishchisisiz CSV hisobotlar yuborilmaydi (job `reports` navbatida qotadi); schedulersiz to'lanmagan buyurtmalar bekor bo'lmaydi va kuryer ogohlantirishi chiqmaydi. `php artisan schedule:list` to'rtta vazifani ko'rsatishi kerak |
| CORS | Mini App same-origin bo'lsa kerak emas; aks holda `CORS_ALLOWED_ORIGINS`. Hamkor veb-panel brauzerdan API'ga to'g'ridan-to'g'ri murojaat qiladi: origin'i `CORS_ALLOWED_ORIGINS` da bo'lishi shart ([partner/web/README.md](../partner/web/README.md)) |
| Xarita va geokodlash | Ochiq Nominatim/OSM tile serverlari faqat ishlab chiqish va kam trafik uchun: `NOMINATIM_BASE_URL`, `NOMINATIM_EMAIL`, `VITE_MAP_TILE_URL`, `VITE_MAP_ATTRIBUTION` |
| Push (Firebase) | Ixtiyoriy, `app:check-production` tekshirmaydi. Yoqilgan bo'lsa: `FIREBASE_CREDENTIALS_JSON` to'g'ri, `queue` ishchisi ishlayapti (pushlar navbat orqali ketadi), `php artisan push:test <foydalanuvchi_id>` sinov pushini yuboradi. Tafsilot: `docs/PUSH.md` |
| SMS | `SettingSeeder` yozgan `settings` qatorlari `SMS_DRIVER`/`ESKIZ_*` env'dan ustun: SMS'ni boshqaruv markazida Tizim → Tizim sozlamalari → Sozlamalar dan yoqing |
| Zaxira nusxa | Postgres (`pg_dump`) va yuklangan rasmlar (`backend_storage` volume); tartibi: `docs/OPERATIONS.md`, 4-bo'lim |
| Real-time (Reverb) | `BROADCAST_CONNECTION=reverb` va `REVERB_APP_ID`/`KEY`/`SECRET` to'ldirilgan (ildiz `.env`), `REVERB_ALLOWED_ORIGINS` = Mini App hosti, hamkor paneli hosti (`panel.<domen>`) va KDS ilovasining API hosti (`RIZQIM_API_URL` xosti; Mini App hosti bilan bir xil bo'lsa alohida yozish shart emas), vergul bilan, `docker compose ps` da `reverb` healthy, Mini App build'ida `VITE_REVERB_APP_KEY` = `REVERB_APP_KEY`. Tashqi proksi `/app/` uchun WebSocket upgrade'ni o'tkazishi shart (9-bo'lim). `app:check-production` `reverb` + bo'sh kalitni rad etadi, `REVERB_ALLOWED_ORIGINS=*` da ogohlantiradi |
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

**To'lov tizimlari (P4 wave 3 migratsiyasidan keyin).** `2026_11_06_300200_add_mode_to_payment_providers_table` hamma
`payment_providers` qatorini `mode=production` va `is_active=true` qilib qo'yadi: eski panelda ikkalasi hech narsaga ta'sir
qilmasdi (eski seeder Payme'ga `test_mode: 'true'` yozgan edi), endi esa jonli to'lovlarni yo'naltiradi. Kalitlarida
`test_mode` bo'lgan tizimlar logga yoziladi («the old credentials said test_mode»). **Migratsiyadan keyin boshqaruv
markazida Tizim → To'lov tizimlari'ni ko'rib chiqing**: ataylab o'chirilishi kerak bo'lgan tizimni o'chiring, sinov
serveri bo'lsa (staging) kerakli tizimni «Sinov»ga o'tkazing. So'ng `php artisan app:check-production` ogohlantirishlarini
tekshiring.

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

`reverb` xizmati (`php artisan reverb:start --host=0.0.0.0 --port=8080`) tashqariga port ochmaydi; healthcheck Reverb'ning `GET /up` yo'li. `REVERB_HOST=reverb`, `REVERB_PORT=8080`, `REVERB_SCHEME=http` ni compose backend/queue/scheduler'ga o'zi beradi. Mini App build argumentlari: `VITE_REVERB_APP_KEY` (bo'sh bo'lsa `REVERB_APP_KEY`), `VITE_REVERB_HOST` (bo'sh = `window.location.hostname`), `VITE_REVERB_PORT` (`443`), `VITE_REVERB_SCHEME` (`https`) — brauzer ko'radigan tashqi HTTPS manzil. `REVERB_ALLOWED_ORIGINS` ga WebSocket ochadigan **hamma** ekranlarning hostini vergul bilan yozing (standart `*` — kanallar baribir imzolanadi, lekin `app:check-production` ogohlantiradi). Reverb `Origin` sarlavhasining hostini shu ro'yxat bilan solishtiradi; ro'yxatda yo'q host 4009 bilan rad etiladi va o'sha ekran xatosiz, faqat polling bilan ishlaydi (P2-33). Ro'yxatda bo'lishi kerak: Mini App hosti (`<domen>`); hamkor paneli hosti (`panel.<domen>`, panel doskasining real-time'i); oshxona ekrani (KDS) uchun API hosti — native ilova `Origin` sifatida `RIZQIM_API_URL` ning hostini yuboradi (yoki `RIZQIM_REVERB_ORIGIN` berilgan bo'lsa uning hostini, `pos/apps/kds/README.md`); odatda bu Mini App hosti bilan bir xil, API alohida hostda bo'lsa uni ham qo'shing. Masalan: `REVERB_ALLOWED_ORIGINS=example.uz,panel.example.uz`. Kalitni almashtirsangiz backend ham, frontend ham qayta build/yaratilishi kerak.

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

`COMPOSE_FILE` bo'lmasa har buyruqqa `-f docker-compose.yml -f docker-compose.staging.yml` qo'shing. Domen va HTTPS: 4-bo'limdagidek, staging domeniga alohida sertifikat va tashqi proksi (Mini App `FRONTEND_PORT`=8180 ga, backend `BACKEND_PORT`=8100 ga, hamkor paneli `panel.` hosti `PARTNER_WEB_PORT`=8130 ga, boshqaruv markazi `admin.` hosti `ADMIN_WEB_PORT` ga; panel origin'i `backend/.env` dagi `CORS_ALLOWED_ORIGINS` da). Telegram test botida Mini App manzili `TELEGRAM_WEBAPP_URL`; deploy `telegram:set-webhook` ni o'zi ishga tushiradi. Birinchi do'kon va superadmin: 5-bo'lim (test ma'lumotlari bilan). `backend/.env` da `PARTNER_WEB_URL` (staging hamkor paneli) va `ADMIN_APP_URL`. Faqat bitta xostda ishlatsangiz, `.env` dagi portlar (8100/8180/8130) productionnikidan farq qilsin; loyiha va volume nomlari allaqachon alohida.

**`app:check-production` staging'da.** Buyruq `APP_ENV=production` **yoki** `staging` ni qabul qiladi. Qolgan hamma shartlar staging'da ham o'zgarishsiz tekshiriladi (debug o'chiq, `APP_KEY`, Telegram secret va HTTPS manzil, Reverb kalitlari, standart super admin paroli yo'q): staging internetda turadi, shuning uchun "staging deb" zaiflashtirilmaydi. Production-only shart bitta: `APP_ENV` qiymatining o'zi (`local`/`testing` va boshqalar ikkalasida ham rad etiladi). Muvaffaqiyatli bo'lsa chiqadi: "Staging sozlamalari tekshiruvdan o'tdi".

**Production ma'lumotlarini staging'ga ko'chirmang.** Zarur bo'lsa faqat anonimlashtirilgan dump yuklang va keyin: boshqaruv markazida Tizim → Tizim sozlamalari → Sozlamalar da SMS usulini «Log» qiling (`sms_driver=log`; bazadagi qiymat env'dan ustun, aks holda haqiqiy mijozlarga SMS ketadi), do'konlarning Telegram bot tokenlari va to'lov kalitlarini test qiymatlarga almashtiring.

### Staging'da tekshiruv ro'yxati

1. `docker compose ps`: hamma xizmat `healthy`; `docker compose logs backend | grep -i "tekshiruvdan o'tdi"`.
2. `curl -I https://<staging-domen>/` va `https://<staging-domen>/api/v1/stores`: `X-Robots-Tag: noindex, nofollow, noarchive`; `/robots.txt` — `Disallow: /`.
3. Boshqaruv markaziga kirish (parol + 2FA), test ovqatxona yaratish (Ovqatxonalar → Ulash jarayoni), hamkor panelida mahsulot qo'shish; test bot orqali Mini App'dan buyurtma berish; holatni o'zgartirganda real-time yangilanishi (9-bo'lim).
4. Click/Payme sandbox to'lovi (webhook'lar `https://<staging-domen>/api/v1/payments/...`).
5. Yangi migratsiyalar toza bazada ham, oldingi release ma'lumotlari ustida ham o'tdi (`docker compose logs backend`).
6. Yangi feature flag bo'lsa: boshqaruv markazida Tizim → Tizim sozlamalari → Funksiyalar da bitta test do'konda yoqib, `GET /api/v1/features` ni tekshiring.

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
- **Yangi imkoniyatni xavfsiz yoyish.** Katta o'zgarishni feature flag orqasida chiqaring (`docs/OPERATIONS.md`, 8-bo'lim): production'ga flag o'chiq holda tushadi, so'ng boshqaruv markazida Tizim → Tizim sozlamalari → Funksiyalar da avval bitta restoranda, keyin boshqalarda yoqiladi. Muammo bo'lsa kod qaytarilmaydi, flag o'chiriladi.
- **Orqaga qaytarish:** oldingi tegga `git checkout <eski-teg> && docker compose up -d --build` (7-bo'lim: migratsiya `down()` lari va zaxiradan tiklash).
- Production'dan keyin tegni va commit'ni jamoaga yozib qo'ying (Sentry release nomi bilan bir xil), shunda xato qaysi release'dan boshlanganini topish oson bo'ladi.

