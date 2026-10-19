# Rizqim Mini App

Telegram Mini App: React 19, TypeScript, Vite 8, Tailwind CSS 4, TanStack Query, zustand, axios, Leaflet. Bitta bundle barcha rollar uchun: mijoz (katalog, savat, buyurtmalar) hamda `owner`, `manager`, `courier`, `super_admin` ekranlari. Ilova faqat API (`/api/v1`) bilan ishlaydi: [../backend/README.md](../backend/README.md). Umumiy tavsif: [../README.md](../README.md).

## Ishga tushirish

Node.js 22 (CI shu versiyada ishlaydi; Vite 8 uchun kamida 20.19, Vitest 5 uchun 22.12) va npm kerak.

```bash
cd mini-app
npm ci
npm run dev        # http://localhost:5173
```

Dev server `/api` so'rovlarini `http://127.0.0.1:8000` ga proksilaydi (`vite.config.ts`), shuning uchun backend shu portda ishlab turishi kerak (`php artisan serve`); `allowedHosts: true` — tunnel orqali ochish mumkin.

**Telegramsiz ochish.** `window.Telegram.WebApp.initData` bo'lmasa ilova "faqat Telegram" ekranini (`OnlyTelegramBlocker`) ko'rsatadi. Brauzerda sinash uchun manzilga `?dev=true` qo'shing (yoki `VITE_APP_DEBUG=true`). Dev serverda (`npm run dev`) `initData` bo'lmasa ilova soxta foydalanuvchi bilan kiradi; uning id'si `localStorage` dagi `mock_telegram_id` (shuningdek `mock_telegram_username`, `mock_first_name`) kalitlarida turadi. Buning uchun backendda `APP_ENV=local`, `TELEGRAM_ALLOW_MOCK_AUTH=true`, bo'sh bo'lmagan `TELEGRAM_BOT_TOKEN` va kamida bitta faol do'kon bo'lishi shart. Production build'da soxta kirish yo'q: `?dev=true` bilan ochilsa ham Telegramsiz "Iltimos, ilovani Telegram orqali oching" xatosi chiqadi. Soxta foydalanuvchiga xodim roli berish: `php artisan user:assign-role <mock_telegram_id> owner --store=<id>`.

## Muhit o'zgaruvchilari (`VITE_*`)

Build vaqtida bundle ichiga yoziladi; o'zgartirgandan keyin qayta build qiling. Docker'da qiymatlar ildizdagi `.env` dan build argument sifatida uzatiladi (`docker compose up -d --build frontend`; `.dockerignore` `.env.*` fayllarni image'ga kiritmaydi). VPS'da `scripts/deploy.sh` `npm run build` ni ishga tushiradi, shuning uchun qiymatlarni `mini-app/.env.production` ga yozing (git e'tiborsiz qoldiradi).

| O'zgaruvchi | Standart | Ma'nosi |
|---|---|---|
| `VITE_API_URL` | `/api/v1` | API bazaviy manzili. Boshqa domen bo'lsa to'liq URL bering va backendda `CORS_ALLOWED_ORIGINS` ni sozlang |
| `VITE_BOT_USERNAME` | `rizqimbot` | Bot username'i (`@` siz). Loginda `bot_username` sifatida yuboriladi (backend `TELEGRAM_BOT_TOKEN` ni shu bo'yicha tanlaydi, `TELEGRAM_BOT_USERNAME` bilan bir xil bo'lsin), referral havolasi va "Bot orqali ochish" tugmasi shundan quriladi |
| `VITE_MAP_TILE_URL` | `https://tile.openstreetmap.org/{z}/{x}/{y}.png` | Xarita tile shabloni (`{z}/{x}/{y}` bilan). Ochiq OSM serveri faqat ishlab chiqish va kam trafik uchun: productionda akkauntingiz bor provayder yoki o'z tile serveringizni bering |
| `VITE_MAP_ATTRIBUTION` | OpenStreetMap attribution (HTML) | Tile provayderi talab qiladigan attribution matni (HTML); xaritada ko'rsatiladi |
| `VITE_APP_DEBUG` | o'chiq | `true` bo'lsa Telegramsiz ochishga ruxsat beradi va auth debug panelini ko'rsatadi. Productionda qo'ymang |

## Skriptlar

| Buyruq | Vazifasi |
|---|---|
| `npm run dev` | Vite dev serveri (HMR, `/api` proksisi) |
| `npm run build` | `tsc -b && vite build` — tur xatosi bo'lsa build yiqiladi; natija `dist/` |
| `npm run preview` | `dist/` ni lokal ko'rish |
| `npm run lint` | `eslint .` (typescript-eslint, react-hooks, react-refresh) |
| `npm test` | `vitest run` (bir martalik); `npm run test:watch` — kuzatuv rejimi |
| `npm run test:e2e` | Playwright brauzer testlari (`e2e/`), backend kerak emas — pastdagi "E2E testlar" bo'limiga qarang |

`test-browser.js` — qo'lda ishga tushiriladigan puppeteer skripti (`node test-browser.js`, dev server `localhost:5173` da ishlab turishi kerak): sahifani ochib brauzer konsoli va so'rov xatolarini chiqaradi. CI, Docker build va `scripts/deploy.sh` uni ishlatmaydi va `npm ci` ni `PUPPETEER_SKIP_DOWNLOAD` bilan yuritadi (Chrome yuklanmaydi). Lokalda ham kerak bo'lmasa: `PUPPETEER_SKIP_DOWNLOAD=1 npm ci`; skriptni ishga tushirish uchun Chrome'ni `npx puppeteer browsers install chrome` bilan o'rnating.

## Tuzilma (`src/`)

| Yo'l | Nima bor |
|---|---|
| `main.tsx`, `App.tsx` | kirish nuqtasi; marshrutlar (har sahifa `lazyWithRetry` bilan alohida chunk), rolga mos pastki navigatsiya, offline va "faqat Telegram" ekranlari |
| `pages/` | bitta ekran — bitta fayl: mijoz (`Home`, `Stores`, `StoreDetail`, `CategoryDetail`, `Cart`, `Orders`, `CustomerProfile`, `Complaints`, `Notifications`, `Success`), xodim (`ManagerDashboard`, `CourierDashboard`, `Admin*`, `Owner*`, `Profile`), `Superadmin*` |
| `components/` | umumiy komponentlar (`Feedback`, `PhoneVerificationModal`, `VariantSelectorModal`, `NotificationCenter`, `HeroPromoSlider`); `map/` — xarita (`LazyMapPicker`, `MapPicker`, `mapConfig`) |
| `features/address/` | manzil tanlash: `AddressLocationField` (GPS, qidiruv, pin), `geocodeApi`, `geolocation`, `coordinates` |
| `hooks/` | `useTelegramAuth`, `useOrders`/`useStaffOrders`, `useStores`, `useCatalog`, `useNotifications`, `useVisibleInterval` |
| `services/api.ts` | axios klienti: token qo'shish va 401 da qayta autentifikatsiya |
| `store/` | zustand. `localStorage` ga saqlanadiganlar: `authStore` (`auth-storage`), `cartStore` (`cart-storage`), `useAddressStore` (`address-storage`), `languageStore` (`restaurant-language-store`; `uz`/`ru`/`en`). `useAppStore` (UI holati) saqlanmaydi |
| `lib/` | sof yordamchi funksiyalar: `money`, `format`, `orderStatus`, `polling`, `roles`, `phone`, `stock`, `catalog`, `location`, `analytics`, `apiError`, `telegram`, `lazyWithRetry`, `user` |
| `types/` | API JSON shakllari (backend controller va modellaridan yozilgan) va `telegram.d.ts` |
| `test/` | `setup.ts`, `factories.ts`, `httpErrors.ts` |

**Rollar va bosh sahifa** (`lib/roles.ts`): `/` ni ochganda `super_admin` -> `/superadmin/dashboard`, `owner` -> `/admin/analytics`, `manager` -> `/manager`, `courier` -> `/courier`; mijoz katalogda qoladi. Marshrutlar klientda rol bo'yicha to'silmaydi: ruxsatni server tekshiradi (`role:` middleware va policy'lar), ekranlar faqat navigatsiyani rolga moslaydi.

## Autentifikatsiya (`hooks/useTelegramAuth.ts`, `services/api.ts`)

1. Ishga tushganda saqlangan sessiya (`auth-storage`) joriy Telegram foydalanuvchisiga mos va `start_param` yo'q bo'lsa ilova darhol ochiladi, `GET /user` fonda yangilaydi (401 bo'lsa chiqish). Aks holda `POST /auth/telegram` ga `initData`, `bot_username`, `start_param` yuboriladi (10 s timeout); javobdagi Sanctum tokeni saqlanadi. `start_param` (Mini App `startapp` havolasidan) bo'lsa saqlangan sessiya ishlatilmaydi, login qaytadan bajariladi: backend `start_param` ni taklif (`invite_...`), referral (`rizqim_<id>`) yoki do'kon (`store_<uuid>`) sifatida qayta ishlaydi.
2. So'rov interceptori har so'rovga `Authorization: Bearer <token>` qo'shadi.
3. 401 da: `/auth/telegram` ning o'zi yoki allaqachon qayta yuborilgan so'rov bo'lsa — chiqish (`logout`); boshqa so'rov token'ni allaqachon yangilagan bo'lsa — yangi token bilan qayta yuborish; qayta autentifikatsiya 30 soniya ichida muvaffaqiyatsiz bo'lgan bo'lsa — chiqish; aks holda parallel 401 lar bitta qayta autentifikatsiyani bo'lishadi (shu ishga tushirishdagi `initData` bilan, interceptorsiz `authClient` orqali) va so'rov bir marta qayta yuboriladi. Yangilab bo'lmasa — chiqish.
4. Chegara: backend `initData` ni faqat 10 daqiqadan yosh bo'lsa qabul qiladi, shuning uchun bu mexanizm eski token bilan ilovani ochish holatini tiklaydi, uzoq ochiq turgan sessiyani emas. Token muddati serverda (`SANCTUM_TOKEN_EXPIRATION`, standart 30 kun).

## Xarita va manzil

- Hamma geokodlash backend orqali (`/geocode/reverse`, `/geocode/search`): ilova geokoderga o'zi murojaat qilmaydi. Server tomoni: [../README.md](../README.md).
- `AddressLocationField`: "Joylashuvimni aniqlash" (avval Telegram `LocationManager`, Bot API 8.0+, so'ng brauzer geolokatsiyasi), manzil qidirish (kamida 3 belgi), xaritada bosish yoki pinni sudrash — 500 ms dan keyin nuqta manzilga aylantiriladi. Mijoz o'zi yozgan manzil ustidan yozilmaydi, faqat taklif qilinadi. Pin — haqiqat manbai: koordinata haqiqiy nuqta tanlanmaguncha `null`, standart yoki "o'ylab topilgan" nuqta yo'q (shunday eski saqlangan manzillarni `useAddressStore` migratsiyasi tozalaydi).
- Leaflet va CSS'i faqat manzil formasi ochilganda yuklanadi (`LazyMapPicker`, alohida `leaflet` chunk); yuklanmasa `MapErrorBoundary` faqat xarita o'rnini almashtiradi, forma ishlashda qoladi. Pin ham, qo'shimcha markaz (masalan, do'kon joylashuvi) ham bo'lmasa xarita Toshkentga qaratiladi (faqat ko'rinish, manzil emas).

## Ma'lumot yangilanishi

Ro'yxatlar so'rov (polling) bilan yangilanadi (`lib/polling.ts`): mijoz buyurtmalari 10 s, menejer 8 s, kuryer va admin buyurtmalari 15 s, xabarnomalar 15 s, egasi moliyasi 10 s. Sahifa yashirin bo'lsa so'rov to'xtaydi, qaytganda yoki tarmoq tiklanganda yangilanadi; ketma-ket xatolarda interval ikki baravar oshadi (ko'pi bilan 120 s). Pul summalari API'dan o'nli satr (`"15000.00"`) bo'lib keladi: hisob-kitob va ko'rsatishdan oldin `lib/money.ts` (`toAmount`, `sumAmounts`) va `lib/format.ts` (`formatUzbekSum`) dan o'tkaziladi.

## Testlar

- Vitest 5 + jsdom + Testing Library (`vitest.config.ts`: `src/**/*.test.{ts,tsx}`, `css: false`, har testdan oldin mock/spy/stub'lar tiklanadi). Testlar kodning yonida (`foo.ts` — `foo.test.ts`).
- `src/test/setup.ts`: jest-dom matcherlari, har testdan keyin `cleanup()` va `localStorage.clear()`. `factories.ts` — API JSON shaklidagi tipli fixture'lar (`makeUser`, `makeStore`, `makeOrder`, ...). `httpErrors.ts` — haqiqiy axios xatosini yasaydi va konsolga/ekranga token, OTP, telefon, `initData` chiqib ketmasligini tekshirish uchun.
- Tarmoq haqiqiy ishlatilmaydi: `services/api.test.ts` o'z axios adapterini beradi va modulni har testda yangidan yuklaydi (qayta autentifikatsiya holati modul darajasida turgani uchun); polling va taymerlar soxta vaqt bilan tekshiriladi; sahifa testlari (`pages/*.test.tsx`) API'ni mock qiladi.
- CI (`.github/workflows/ci.yml`, `mini-app/` papkasida, Node 22): `frontend` job — `npm ci`, `npx eslint .`, `npm test`, `npm run build`; alohida `e2e` job — `npx playwright install --with-deps chromium`, `npm run test:e2e`.

## E2E testlar

Playwright (`@playwright/test`, faqat Chromium, 390x844 mobil ko'rinish) production build'ni brauzerda ochib asosiy oqimlarni tekshiradi: mijoz buyurtma beradi va bekor qiladi, buyurtmalar tarixini sahifalab yuklaydi, menejer buyurtmani sabab bilan rad etadi, kuryer yetkazadi, eskirgan token qayta autentifikatsiya qilinadi. Backend ham, Telegram ham kerak emas: `e2e/fixtures.ts` `/api/v1` ga ketadigan barcha so'rovlarni ushlab, xotiradagi soxta API (`e2e/fakeApi.ts`, javoblar `src/types` shaklida) bilan javob beradi; Telegram ishga tushirish parametrlari (`initData`) ham fixture'da beriladi. Soxta API bilmaydigan endpoint chaqirilsa yoki sahifada tutilmagan xato chiqsa, test yiqiladi.

```bash
npx playwright install chromium     # bir marta; CI'da: npx playwright install --with-deps chromium
npm run test:e2e                    # `npm run build` + `npm run preview` (127.0.0.1:4173) ni o'zi ishga tushiradi
```

- Brauzer boshqa joyda turgan bo'lsa (Playwright o'zinikini yuklay olmasa), Chromium fayliga yo'lni bering: `PW_CHROMIUM_PATH=/yo'l/chrome npm run test:e2e`. Port o'zgartirish: `E2E_PORT`.
- Build `VITE_API_URL=/api/v1` va bo'sh `VITE_REVERB_APP_KEY` bilan olinadi (real-time o'chiq, ekranlar so'rov bilan yangilanadi). Xato bo'lsa hisobot `playwright-report/`, iz (trace) `test-results/` da; CI ularni artifact sifatida yuklaydi.
- Yangi test: kerakli ma'lumotni `api.addOrder(...)` / `api.signInAs('manager')` bilan tayyorlang, `page.goto(...)` qiling, so'rovlarni `api.callsTo('POST', '/orders')` orqali tekshiring. Locator'lar rol va matn bo'yicha, kutish uchun `expect` ning o'zi (sleep yo'q).
- Vitest `e2e/` ni olmaydi (`include: src/**/*.test.{ts,tsx}`); `tsc -b` (`tsconfig.e2e.json`) va `eslint .` esa ularni tekshiradi.
- Qamrab olinmagan: xaritada nuqta tanlash va geokodlash (Leaflet); yetkazib berish testi oldindan saqlangan manzildan foydalanadi.

## Docker image

`Dockerfile` ko'p bosqichli: `node:22-alpine` da `PUPPETEER_SKIP_DOWNLOAD=1 npm ci` va `npm run build` (`VITE_*` build argumentlari bilan), so'ng `nginx:1.27-alpine` ga `dist/` nusxalanadi. `docker/nginx.conf`:

- SPA uchun `index.html` ga qaytish; `/api/` va `/storage/` (yuklangan rasmlar) `http://backend:80` ga yo'lni o'zgartirmasdan proksilanadi (`Host`, `X-Real-IP`, `X-Forwarded-For` va `X-Forwarded-Proto` bilan; kelgan `X-Forwarded-Proto` o'zgarmay uzatiladi, yo'q bo'lsa `$scheme` qo'yiladi).
- `backend` nomi Docker DNS (`resolver 127.0.0.11`, 5 s kesh) orqali har so'rovda aniqlanadi, shuning uchun backend konteyneri alohida qayta yaratilsa ham frontend'ni qayta ishga tushirish shart emas. Bu qism faqat Docker Compose tarmog'ida ishlaydi.
- `client_max_body_size 10m` (nginx standarti 1 MB rasm yuklashni to'sardi; API rasmni 5 MB bilan cheklaydi).
- Kesh: `index.html` — `Cache-Control: no-cache` (har safar qayta tekshiriladi), `/assets/*` (nomida hash bor) — `public, max-age=31536000, immutable`, `public/` dagi hashsiz fayllar (`favicon.svg`, ...) — 30 kun.
