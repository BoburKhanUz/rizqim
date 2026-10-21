# partner/web: hamkor veb-panel

Restoran egasi va menejeri uchun veb-panel (kompyuter va planshet). Next.js 16 (App Router), React 19, TypeScript, Tailwind CSS 4,
TanStack Query. Biznes mantiq faqat backend'da: panel API'dan o'qiydi va ko'rsatadi ([docs/ROADMAP.md](../../docs/ROADMAP.md)).
Dizayn tizimi — [`@rizqim/ui`](../../packages/ts/ui/README.md) (Mini App bilan umumiy), API klienti —
[`@rizqim/api-client`](../../packages/ts/api-client/README.md) (OpenAPI'dan yaratilgan), xodim ekranlari uchun umumiy kod (status nomlari, formatlar, API funksiyalari va hook'lar; Mini App xodim sahifalari bilan umumiy) —
[`@rizqim/staff`](../../packages/ts/staff/README.md). Hozir `Buyurtmalar` sahifasi va status/tur/to'lov nomlari shu paketdan.

Hozir (P1-25, skelet): telefon + SMS kod bilan kirish, yon menyu, **Boshqaruv paneli** (bugungi ko'rsatkichlar,
`GET /api/v1/owner/analytics`) va **Buyurtmalar** (faol buyurtmalar, faqat ko'rish, `GET /api/v1/orders?scope=active`). Menyu,
Stop-list, Filiallar, Xodimlar, Hisobotlar, Moliya va Sozlamalar sahifalari hozircha bo'sh joy (keyingi bosqichlar).
Matnlar `uz` (asosiy), `ru`, `en` (`src/i18n/`); mavzu (yorug'/qorong'i) tizim sozlamasidan.

## Ishga tushirish (mahalliy)

Node.js 22 va npm kerak. Panel `file:` orqali uchta paketni ulaydi (`ui`, `staff` — manba kod; `api-client`); `@rizqim/api-client` `dist/` ga yig'ilgan bo'lishi kerak.

```bash
cd packages/ts/api-client && npm ci && npm run build      # bir marta (API o'zgarsa qayta)
cd ../../../partner/web
cp .env.example .env.local                                  # API manzillarini tekshiring
npm ci
npm run dev                                                 # http://localhost:3000
```

Backend ishlab turishi kerak (`php artisan serve`, `http://127.0.0.1:8000`), va **backend'da `CORS_ALLOWED_ORIGINS=http://localhost:3000`**
bo'lishi shart (pastga qarang). Kirish uchun faol hisob kerak: telefon raqamiga SMS kod boradi (mahalliy rejimda
`SMS_EXPOSE_CODE_IN_RESPONSE`/log drayveri, [backend/README.md](../../backend/README.md)).

| Buyruq | Vazifasi |
|---|---|
| `npm run dev` | Dev server (Turbopack, HMR; `packages/ts/ui` o'zgarishi ham darhol ko'rinadi) |
| `npm run build` / `npm start` | Production build (`output: 'standalone'`) va uni ishga tushirish. `npm start` HTTP orqali bo'lsa `SESSION_COOKIE_SECURE=false` kerak |
| `npm run lint` | ESLint (`eslint-config-next`) |
| `npm run typecheck` | `tsc --noEmit` |
| `npm test` | Vitest + Testing Library (kirish oqimi, dashboard, buyurtmalar, route handler'lar, single-flight refresh, i18n) |
| `npm run test:e2e` | Playwright (brauzer, production build) soxta backend bilan: [E2E testlar](#e2e-testlar-p2-31) |
| `npm run typecheck:e2e` | `e2e/` papkasi uchun `tsc` |

## Sozlamalar (muhit o'zgaruvchilari, `.env.example`)

Hammasi **runtime**'da o'qiladi (build'ga yozilmaydi), shuning uchun bitta image barcha muhitlarda ishlaydi.

| O'zgaruvchi | Ma'nosi |
|---|---|
| `API_BASE_URL` | Next **serveri** backend'ga shu manzil bilan murojaat qiladi (kirish: `/api/session/*`). Docker tarmog'ida ichki manzil. `/api` bilan tugaydi |
| `PUBLIC_API_BASE_URL` | **Brauzer** API'ga shu manzil bilan to'g'ridan-to'g'ri murojaat qiladi (access token bilan). Bo'sh bo'lsa `API_BASE_URL` |
| `SESSION_COOKIE_SECURE` | Cookie'larga `Secure`. Production'da standart `true`; HTTP orqali sinashda `false` |

### CORS (muhim)

Brauzer API'ga boshqa origin'dan murojaat qiladi, shuning uchun backend'da `CORS_ALLOWED_ORIGINS` ga panelning origin'ini qo'shing
(`https://panel.rizqim.uz`, mahalliy `http://localhost:3000`). Vergul bilan ajratiladi. Eslatma: ro'yxat berilsa, standart originlar
(`TELEGRAM_WEBAPP_URL`, `APP_URL`) ishlamay qoladi; kerak bo'lsa ularni ham yozing ([backend/.env.example](../../backend/.env.example),
[docs/DEPLOY.md](../../docs/DEPLOY.md)). Kirish so'rovlari (`/api/session/*`) brauzerdan Next serveriga boradi (same-origin), ularga
CORS kerak emas. Cookie'siz Bearer token ishlatilgani uchun `supports_credentials` kerak emas.

## Kirish va sessiya ([docs/AUTH.md](../../docs/AUTH.md))

1. `POST /api/session/send-code {phone}` → backend `POST /auth/phone/send-code`.
2. `POST /api/session/login {phone, code}` → backend `POST /auth/phone/verify` `device: {type: 'web', uuid, name, platform}` bilan.
   Javobdagi **refresh token httpOnly cookie**ga (`rz_refresh`, `SameSite=Strict`, `Path=/api/session`, `Secure` production'da)
   yoziladi va brauzerga **berilmaydi**. Brauzer faqat qisqa muddatli access token (15 daqiqa) oladi va uni **xotirada** saqlaydi
   (`localStorage`/`sessionStorage`/oddiy cookie ishlatilmaydi). `rz_device` cookie'si bir brauzerning qurilmasini saqlaydi.
3. Sahifa ochilganda `POST /api/session/refresh` cookie'dagi refresh token bilan yangi access token oladi (token almashadi: eski
   token sarflanadi, yangisi cookie'ga yoziladi). Rad etilgan token (muddati o'tgan, qayta ishlatilgan) → 401 va cookie tozalanadi;
   backend'ga ulanib bo'lmasa — 502 va cookie qoladi (tarmoq uzilishi chiqarib yubormaydi).
4. **Single-flight:** refresh token bir martalik, ikkinchi so'rov backend'da "qayta ishlatish" (o'g'irlik) deb hisoblanadi va butun
   oila bekor qilinadi. Shuning uchun bir vaqtdagi barcha so'rovlar bitta refresh'ni kutadi (`src/lib/session/client.ts`), bir nechta
   tab esa `navigator.locks` orqali navbatma-navbat ishlaydi. API 401 qaytarsa, sessiya bir marta yangilanadi va so'rov takrorlanadi.
5. `POST /api/session/logout` backend'da sessiyani (va qurilmaning refresh tokenlarini) tugatadi va cookie'ni o'chiradi.

Route handler'lar `Origin`/`Sec-Fetch-Site` ni tekshiradi (boshqa saytdan chaqirib bo'lmaydi).

**Xodim hisobi (P1-36).** Login `account: 'staff'` bilan yuboriladi: backend mijoz hisobini emas, shu raqam **kirish raqami**
bo'lgan do'kon xodimini (ega, menejer va boshqalar) topadi, hech qachon yangi hisob yaratmaydi ([docs/AUTH.md](../../docs/AUTH.md),
"Xodim sifatida SMS bilan kirish"). Kirish raqami: ega uchun superadmin do'kon yaratganda yozgan raqam, boshqa xodimlar uchun
do'kon panelidagi Xodimlar formasidagi "Telefon (SMS bilan kirish)". Raqam hech qaysi xodimniki bo'lmasa, backend sababini
aytadi va forma shuni ko'rsatadi.

**Bir odam ikki do'konda.** Raqam bir nechta xodim hisobida bo'lsa, `/api/session/login` 409 `account_selection_required`
va hisoblar ro'yxatini (do'kon, rol, filial) qaytaradi; backend bergan bir martalik tanlov chiptasi brauzerga berilmaydi,
u httpOnly `rz_select` cookie'sida (5 daqiqa). Forma do'konlarni ko'rsatadi, tanlanganda `POST /api/session/select-account
{user_id}` → backend `POST /auth/phone/select-account` (chipta cookie'dan) va sessiya odatdagidek boshlanadi.

## Huquqlar va menyu

Menyu `GET /api/v1/me/permissions` ro'yxatiga qarab chiziladi (`src/lib/permissions.ts`); backend baribir har so'rovni tekshiradi.

| Bo'lim | Ruxsat (istalgan biri) |
|---|---|
| Boshqaruv paneli | `orders.view` (doska xulosasi), `reports.view` (bugungi analitika) |
| Buyurtmalar (jonli doska) | `orders.view` |
| Menyu | `menu.view`, `menu.manage` |
| Stop-list | `stoplist.manage` |
| Qozon (`/stoplist/batches`) | `batches.manage` |
| Xodimlar | `staff.view` |
| Filiallar | `branches.manage`, `settings.manage` |
| Yetkazish zonalari | `zones.manage` |
| Sozlamalar | `settings.manage` |
| Hisobotlar | `reports.view` |
| Moliya | `finance.view` |

**Filial tanlash** (yuqori panel): ega va filialsiz xodim "Barcha filiallar" yoki bitta filialni tanlaydi (har kishi uchun
brauzerda eslab qolinadi, `rz_branch:<store>:<user>`), filialga biriktirilgan xodim faqat o'z filialida ishlaydi. Sahifalar
tanlovni `useBranchScope()` (`src/components/shell/BranchScope.tsx`) dan o'qiydi.

**Jonli buyurtmalar (P2-21).** `/orders` — ustunlar: Yangi, Tayyorlanmoqda, Tayyor, Yo'lda, Bugun yakunlangan (yig'iladi);
`GET /staff/orders` (kursor), tafsilot `GET /staff/orders/{uuid}`, amallar `POST /staff/orders/{uuid}/status` va
`/orders/{uuid}/assign-courier`. Real-time: Reverb (`REVERB_APP_KEY`, `REVERB_HOST`, `REVERB_PORT`, `REVERB_SCHEME`, runtime),
`private-pos-branch.{id}` yoki barcha filiallarda ega/filialsiz menejer uchun `private-store.{id}`; obuna
`PUBLIC_API_BASE_URL/broadcasting/auth` orqali sessiya tokeni bilan. Real-time bo'lmasa har 15 soniyada so'rov (ulanganda 60 s).
Yangi buyurtmada ovoz (o'chirish mumkin, brauzerda eslab qolinadi; brauzer birinchi bosishdan keyin ruxsat beradi), menyuda va
sarlavhada son.

**Tarjimalar bo'limlari.** Har bo'lim matnlari `src/i18n/sections/<area>.ts` da (`defineSection`, uz/ru/en, kalit bo'lim
prefiksi bilan); `src/i18n/index.ts` ularni asosiy lug'atga qo'shadi, test kalitlar to'liqligi va takrorlanmasligini tekshiradi.

Ruxsati bo'lmagan bo'limga o'tilsa, birinchi ruxsat etilgan bo'limga yo'naltiriladi; hech qanday ruxsat bo'lmasa "kirish yo'q" ekrani.

## E2E testlar (P2-31)

Playwright, Chromium, production build (`next build` + `next start`, `playwright.config.ts` o'zi ishga tushiradi). Backend **soxta**
(`e2e/fake/`): test jarayonida ishlaydigan oddiy HTTP server, u ham Next serverining sessiya handler'lariga (`/api/session/*` →
`/v1/auth/...`), ham brauzerning o'z so'rovlariga javob beradi (holat xotirada, har test oldidan yangilanadi). Reverb'ni sahifaning
o'zi `page.routeWebSocket` bilan almashtiradi (`e2e/fake/reverb.ts`: haqiqiy `laravel-echo` va `pusher-js` ulanadi, test hodisani o'zi
yuboradi), xarita plitkalari — 1x1 rasm. Hech qanday Laravel, baza yoki Reverb kerak emas.

```bash
cd partner/web && npm ci
npx playwright install chromium            # yoki PW_CHROMIUM_PATH=/opt/pw-browsers/chromium-1194/chrome-linux/chrome
npm run test:e2e                           # build + start + testlar (E2E_SKIP_BUILD=1: oxirgi build'ni ishlatish)
```

Qamrab olinadi: SMS kod bilan kirish (xato kod, raqam topilmadi, ikki do'kon — hisob tanlash, chiqish, sessiyani tiklash), doska (yangi
buyurtma real-time bilan va so'rov bilan, qabul → tayyorlash → tayyor → topshirish, kuryer tayinlash → yo'lda → yetkazildi, sabab bilan
rad etish, serverning o'zbekcha rad javobi), menyu (kg variantli taom va modifikator guruhi), stop-list, xodim qo'shish va PIN, hudud
chizish, hisobot davri va CSV, yorug'/qorong'i mavzu. Testlar ko'rinadigan matn va yuborilgan so'rovlarni tekshiradi, ichki holatni emas.
Bitta worker (soxta backend umumiy), 28 test ~35 soniya. Haqiqiy backend bilan umumiy sinov: `scripts/e2e-stack.sh` (`e2e/stack/`).

## Docker

`Dockerfile` (standalone output, `node:22-alpine`, `node` foydalanuvchisi). Build konteksti — **repozitoriy ildizi** (panel `packages/ts/*` ni
ulaydi); `Dockerfile.dockerignore` kontekstni qisqartiradi:

```bash
docker build -f partner/web/Dockerfile -t rizqim-partner-web .
docker run --rm -p 3000:3000 -e API_BASE_URL=http://backend:8000/api -e PUBLIC_API_BASE_URL=https://api.rizqim.uz/api \
  -e SESSION_COOKIE_SECURE=false rizqim-partner-web      # false faqat http://localhost sinovi uchun
```

**docker-compose.yml'da `partner-web` xizmati** (P1-36): `API_BASE_URL=http://backend/api` (compose tarmog'i), `PUBLIC_API_BASE_URL` va
`SESSION_COOKIE_SECURE` ildiz `.env` dagi `PARTNER_PUBLIC_API_BASE_URL`, `PARTNER_SESSION_COOKIE_SECURE` dan, port `PARTNER_WEB_PORT`
(3000, faqat `127.0.0.1` da; `PARTNER_WEB_BIND`). Tashqi TLS proksi `panel.<domen>` ni shu portga uzatadi (`Host`
o'zgarmasin, `X-Forwarded-For` ga mijoz IP'si qo'shilsin: kirish handler'lari uning oxirgi yozuvini backend'ga uzatadi,
backend'ning IP limitlari har kishiga alohida bo'ladi), panel origin'i backend'dagi
`CORS_ALLOWED_ORIGINS` da bo'ladi: [docs/DEPLOY.md](../../docs/DEPLOY.md), 4-bo'lim. Image sog'liq tekshiruvi: `GET /login`.

## Tuzilma

```
src/app/                  App Router: /login, (app)/{dashboard,orders,menu,stoplist,branches,zones,staff,reports,finance,settings}, api/session/* (send-code, login, select-account, refresh, logout)
src/components/           AppShell + Sidebar, shell/ (filial tanlash, jonli buyurtmalar, yuqori panel), orders/ (doska), LoginForm, Dashboard ...
src/lib/session/          server.ts (cookie va backend), client.ts (access token xotirada, single-flight refresh, authFetch, API klienti)
src/lib/permissions.ts    bo'lim <-> ruxsat
src/i18n/                 uz (asosiy), ru, en; til `rz_lang` cookie'sida (server uni o'qib, sahifani shu tilda chizadi)
```
