# Rizqim Mini App

Telegram Mini App: React 19, TypeScript, Vite 8, Tailwind CSS 4, TanStack Query, zustand, axios, Leaflet. Bitta bundle barcha rollar uchun: mijoz (katalog, savat, buyurtmalar) hamda `owner`, `manager`, `courier`, `super_admin` ekranlari. Ilova faqat API (`/api/v1`) bilan ishlaydi: [../backend/README.md](../backend/README.md). Umumiy tavsif: [../README.md](../README.md).

## Ishga tushirish

Node.js 22 (CI shu versiyada ishlaydi; Vite 8 uchun kamida 20.19, Vitest 5 uchun 22.12) va npm kerak.

```bash
cd customer/miniapp
npm ci
npm run dev        # http://localhost:5173
```

**Umumiy UI paketi.** Dizayn tokenlari va tilga bog'liq bo'lmagan komponentlar `packages/ts/ui` (`@rizqim/ui`) da: `package.json` da `"@rizqim/ui": "file:../../packages/ts/ui"`, `npm ci` uni symlink qiladi (alohida build yo'q). Ulash tafsilotlari (bitta React, Tailwind `@source`, Docker): [../../packages/ts/ui/README.md](../../packages/ts/ui/README.md); `src/components/ui` da faqat Mini App'ga xos qismlar qoladi.

**Xodim ekranlari uchun umumiy paket.** Menejer, kuryer, ega va superadmin sahifalari API chaqiruvlari, buyurtma statuslari/tugmalari, kg/l formati va xodim matnlarini `packages/ts/staff` (`@rizqim/staff`) dan oladi; hamkor web ham shu paketdan foydalanadi. Paket `fetch` orqali gaplashadi: token va 401 da qayta kirish Mini App'da qoladi (`src/services/staffApi.ts` → `services/api.ts` dagi `recoverToken`; mijoz sahifalari axios'da qoladi). Testlarda xodim ekranlarining API'si `src/test/staffFetch.ts` (soxta `fetch`) bilan almashtiriladi. Tafsilot: [../../packages/ts/staff/README.md](../../packages/ts/staff/README.md). Paket `@rizqim/api-client` ga tayanadi: u `dist/` ga yig'ilgan bo'lishi kerak (`cd packages/ts/api-client && npm ci && npm run build`).

Dev server `/api` so'rovlarini `http://127.0.0.1:8000` ga proksilaydi (`vite.config.ts`), shuning uchun backend shu portda ishlab turishi kerak (`php artisan serve`); `allowedHosts: true` — tunnel orqali ochish mumkin.

**Telegramsiz ochish.** `window.Telegram.WebApp.initData` bo'lmasa ilova "faqat Telegram" ekranini (`OnlyTelegramBlocker`) ko'rsatadi. Brauzerda sinash uchun manzilga `?dev=true` qo'shing (yoki `VITE_APP_DEBUG=true`). Dev serverda (`npm run dev`) `initData` bo'lmasa ilova soxta foydalanuvchi bilan kiradi; uning id'si `localStorage` dagi `mock_telegram_id` (shuningdek `mock_telegram_username`, `mock_first_name`) kalitlarida turadi. Buning uchun backendda `APP_ENV=local`, `TELEGRAM_ALLOW_MOCK_AUTH=true`, bo'sh bo'lmagan `TELEGRAM_BOT_TOKEN` va kamida bitta faol do'kon bo'lishi shart. Production build'da soxta kirish yo'q: `?dev=true` bilan ochilsa ham Telegramsiz "Iltimos, ilovani Telegram orqali oching" xatosi chiqadi. Soxta foydalanuvchiga xodim roli berish: `php artisan user:assign-role <mock_telegram_id> owner --store=<id>`.

## Muhit o'zgaruvchilari (`VITE_*`)

Build vaqtida bundle ichiga yoziladi; o'zgartirgandan keyin qayta build qiling. Docker'da qiymatlar ildizdagi `.env` dan build argument sifatida uzatiladi (`docker compose up -d --build frontend`; `Dockerfile.dockerignore` `.env.*` fayllarni image'ga kiritmaydi; Docker build konteksti — repozitoriy ildizi, chunki ilova `packages/ts/ui`, `staff` va `api-client` ni ulaydi). Docker'siz qo'lda build qilsangiz (`npm run build`), qiymatlarni `customer/miniapp/.env.production` ga yozing (git e'tiborsiz qoldiradi).

| O'zgaruvchi | Standart | Ma'nosi |
|---|---|---|
| `VITE_API_URL` | `/api/v1` | API bazaviy manzili. Boshqa domen bo'lsa to'liq URL bering va backendda `CORS_ALLOWED_ORIGINS` ni sozlang |
| `VITE_BOT_USERNAME` | `rizqimbot` | Bot username'i (`@` siz). Loginda `bot_username` sifatida yuboriladi (backend `TELEGRAM_BOT_TOKEN` ni shu bo'yicha tanlaydi, `TELEGRAM_BOT_USERNAME` bilan bir xil bo'lsin), referral havolasi va "Bot orqali ochish" tugmasi shundan quriladi |
| `VITE_MAP_TILE_URL` | `https://tile.openstreetmap.org/{z}/{x}/{y}.png` | Xarita tile shabloni (`{z}/{x}/{y}` bilan). Ochiq OSM serveri faqat ishlab chiqish va kam trafik uchun: productionda akkauntingiz bor provayder yoki o'z tile serveringizni bering |
| `VITE_MAP_ATTRIBUTION` | OpenStreetMap attribution (HTML) | Tile provayderi talab qiladigan attribution matni (HTML); xaritada ko'rsatiladi |
| `VITE_APP_DEBUG` | o'chiq | `true` bo'lsa Telegramsiz ochishga ruxsat beradi va auth debug panelini ko'rsatadi. Productionda qo'ymang |
| `VITE_ANALYTICS_ENABLED` | o'chiq | `true` bo'lsa Mini App o'z serverimizga foydalanish statistikasini yuboradi (pastdagi "Analitika" bo'limi). O'chiq bo'lsa statistika klienti yuklanmaydi ham: hech narsa yuborilmaydi, navbatga qo'yilmaydi va `sessionStorage` ga yozilmaydi. Build vaqtidagi qiymat: serverda repozitoriy Variable `PROD_VITE_ANALYTICS_ENABLED` (staging: `STAGING_VITE_ANALYTICS_ENABLED`) = `true` va qayta deploy bilan yoqiladi (`.github/workflows/deploy.yml`; yo'q = o'chiq). Maxfiylik siyosatining yangi matni e'lon qilingandan keyingina yoqing. Ishlayotgan o'chirgich — serverdagi `ANALYTICS_ENABLED` (o'chiq bo'lsa server hech narsa saqlamaydi) |

## Skriptlar

| Buyruq | Vazifasi |
|---|---|
| `npm run dev` | Vite dev serveri (HMR, `/api` proksisi) |
| `npm run build` | `tsc -b && vite build` — tur xatosi bo'lsa build yiqiladi; natija `dist/` |
| `npm run preview` | `dist/` ni lokal ko'rish |
| `npm run lint` | `eslint .` (typescript-eslint, react-hooks, react-refresh) |
| `npm test` | `vitest run` (bir martalik); `npm run test:watch` — kuzatuv rejimi |
| `npm run test:e2e` | Playwright brauzer testlari (`e2e/`), backend kerak emas — pastdagi "E2E testlar" bo'limiga qarang |

`test-browser.js` — qo'lda ishga tushiriladigan puppeteer skripti (`node test-browser.js`, dev server `localhost:5173` da ishlab turishi kerak): sahifani ochib brauzer konsoli va so'rov xatolarini chiqaradi. CI va Docker build uni ishlatmaydi va `npm ci` ni `PUPPETEER_SKIP_DOWNLOAD` bilan yuritadi (Chrome yuklanmaydi). Lokalda ham kerak bo'lmasa: `PUPPETEER_SKIP_DOWNLOAD=1 npm ci`; skriptni ishga tushirish uchun Chrome'ni `npx puppeteer browsers install chrome` bilan o'rnating.

## Tuzilma (`src/`)

| Yo'l | Nima bor |
|---|---|
| `main.tsx`, `App.tsx` | kirish nuqtasi; marshrutlar (har sahifa `lazyWithRetry` bilan alohida chunk), rolga mos pastki navigatsiya, offline va "faqat Telegram" ekranlari |
| `pages/` | bitta ekran — bitta fayl: mijoz (`Home`, `Stores`, `StoreDetail`, `CategoryDetail`, `Cart`, `Orders`, `CustomerProfile`, `Complaints`, `Notifications`, `Success`), xodim (`ManagerDashboard`, `CourierDashboard`, `Admin*`, `Owner*`, `Profile`, `ShiftSummary` — «Smena xulosasi», `Debtors` — «Qarzdorlar»; menejer va ega uchun, `@rizqim/staff` hook'lari bilan), `Superadmin*` |
| `components/` | umumiy komponentlar (`Feedback`, `PhoneVerificationModal`, `VariantSelectorModal`, `NotificationCenter`, `HeroPromoSlider`); `map/` — xarita (`LazyMapPicker`, `MapPicker`, `mapConfig`) |
| `features/address/` | manzil tanlash: `AddressLocationField` (GPS, qidiruv, pin), `geocodeApi`, `geolocation`, `coordinates` |
| `hooks/` | `useTelegramAuth`, `useOrders`/`useStaffOrders`, `useStores`, `useCatalog`, `useNotifications`, `useVisibleInterval` |
| `services/api.ts` | axios klienti: token qo'shish va 401 da qayta autentifikatsiya |
| `store/` | zustand. `localStorage` ga saqlanadiganlar: har bir Telegram foydalanuvchisi uchun alohida kalitda (`lib/userStorage.ts`) `authStore` (`auth-storage:<telegram id>`), `cartStore` (`cart-storage:<telegram id>`), `useAddressStore` (`address-storage:<telegram id>`); qurilma sozlamasi `languageStore` (`restaurant-language-store`; `uz`/`ru`/`en`). `useAppStore` (UI holati) saqlanmaydi |
| `lib/` | sof yordamchi funksiyalar: `money`, `format`, `orderStatus`, `polling`, `roles`, `phone`, `stock`, `catalog`, `location`, `analytics` (o'z serverimizga statistika, "Analitika" bo'limi), `apiError`, `telegram`, `lazyWithRetry`, `user` |
| `types/` | API JSON shakllari (backend controller va modellaridan yozilgan) va `telegram.d.ts` |
| `test/` | `setup.ts`, `factories.ts`, `httpErrors.ts` |

**Rollar va bosh sahifa** (`lib/roles.ts`): `/` ni ochganda `owner` -> `/admin/analytics` (Mini App'ning o'z ega ekrani), `manager` -> `/manager`, `courier` -> `/courier`; mijoz katalogda qoladi. Superadmin va RIZQIM jamoasi Mini App'ga kirmaydi (boshqaruv markazi, `docs/AUTH.md`). Marshrutlar klientda rol bo'yicha to'silmaydi: ruxsatni server tekshiradi (`role:` middleware va policy'lar), ekranlar faqat navigatsiyani rolga moslaydi.

## Autentifikatsiya (`hooks/useTelegramAuth.ts`, `services/api.ts`)

0. Qurilmaning `localStorage` i undagi barcha Telegram akkauntlari uchun umumiy: shu sababli foydalanuvchi ma'lumotlari (sessiya, savat, manzillar) `initData` dagi Telegram foydalanuvchisi kaliti ostida saqlanadi (`lib/userStorage.ts`, ishga tushishda bir marta tanlanadi; dev serverda `mock_telegram_id`). Bir akkaunt boshqasinikini o'qimaydi va ustidan yozmaydi. Telegram foydalanuvchisi bo'lmasa (production) faqat xotirada, qurilmada hech narsa o'chirilmaydi.
   - Eski kalitlar (per-user saqlashdan oldingi versiya): `cart-storage` va `address-storage` har doim o'chiriladi, hech kimga ko'rsatilmaydi va ko'chirilmaydi (eski versiya akkaunt almashganda ularni tozalamagan, kimniki ekani noma'lum). `auth-storage` ichidagi foydalanuvchining `telegram_id` si bo'lsa, ilovani kim ochganidan qat'i nazar, o'sha egasining kalitiga (`auth-storage:<uning telegram_id>`) ko'chiriladi — agar u kalitda allaqachon sessiya bo'lmasa; aks holda o'chiriladi. Har bir qadam alohida: `localStorage` xato bersa (to'lgan, private mode) ham qolgan kalitlar o'chiriladi; o'chmay qolgan eski kalitni hech bir store o'qimaydi, keyingi ishga tushirish uni yana o'chiradi (sessiyani yana o'sha egasiga ko'chiradi).
   - Ishlatilmagan akkauntlar: har ishga tushirishda joriy foydalanuvchi sanasi (`user-storage-last-used`) yoziladi. 90 kun ochilmagan akkaunt ma'lumotlari faqat ikki ishga tushirishda (yozilgan sanalar bo'yicha kamida 1 kun oralig'ida) eskirgan deb ko'rilsa o'chiriladi (birinchi ko'rilgani `user-storage-expiring` da), shuning uchun soati ancha oldinga qo'yilgan bitta ishga tushirish boshqa akkauntlarni o'chira olmaydi. Kelajakdagi sanalar hozirgi vaqtga tushiriladi; soati eng yangi yozilgan sanadan orqada bo'lgan ishga tushirish hech narsani o'chirmaydi; joriy foydalanuvchi hech qachon o'chirilmaydi.
1. Ishga tushganda saqlangan sessiya (`auth-storage:<telegram id>`) joriy Telegram foydalanuvchisiga mos va `start_param` yo'q bo'lsa ilova darhol ochiladi, `GET /user` fonda yangilaydi (javob faqat so'rov yuborilgan token hali joriy bo'lsa qo'llanadi; 401 bo'lsa chiqish). Aks holda `POST /auth/telegram` ga `initData`, `bot_username`, `start_param` yuboriladi (10 s timeout); javobdagi Sanctum tokeni saqlanadi. `start_param` (Mini App `startapp` havolasidan) bo'lsa saqlangan sessiya ishlatilmaydi, login qaytadan bajariladi: backend `start_param` ni taklif (`invite_...`), referral (`rizqim_<id>`) yoki do'kon (`store_<uuid>`) sifatida qayta ishlaydi.
2. So'rov interceptori har so'rovga `Authorization: Bearer <token>` qo'shadi.
3. 401 da: `/auth/telegram` ning o'zi yoki allaqachon qayta yuborilgan so'rov bo'lsa — chiqish (`lib/session.ts` `signOut`: sessiya, React Query keshi va revision tokenlari tozalanadi, kirish xatosi ekrani ko'rsatiladi; savat va manzillar qoladi); boshqa so'rov token'ni allaqachon yangilagan bo'lsa — yangi token bilan qayta yuborish; aks holda qayta kirish (`lib/telegramSignIn.ts`): bitta vaqtda bittasi — `useTelegramAuth` (ishga tushirish, `signInAgain`) va interceptor bir xil yo'ldagi kirishni bo'lishadi, ikkinchi `POST /auth/telegram` ketmaydi; interceptorsiz `authClient` orqali (rad etilgan kirish o'zi hech kimni chiqarib yubormaydi). Qayta kirish 30 soniya ichida muvaffaqiyatsiz bo'lgan bo'lsa — yangi so'rovsiz chiqish. Muvaffaqiyatli kirish oldingi xato holatini tozalaydi. So'rov bir marta qayta yuboriladi; yangilab bo'lmasa — chiqish.
4. Eski ishga tushirish (`lib/launch.ts`): backend `initData` ni faqat 10 daqiqadan yosh bo'lsa qabul qiladi (`auth_date`; `ValidateTelegramInitDataAction`), sahifani qayta yuklash esa o'sha `initData` ni saqlaydi. Server `POST /auth/telegram` ga 401 qaytarsa, bu sahifada o'sha `initData` boshqa yuborilmaydi; kirish muvaffaqiyatsiz bo'lsa va (401 bo'lgan yoki qurilma soati bo'yicha `auth_date` dan 9 daqiqadan ko'p o'tgan bo'lsa) xato ekrani "Kirish muddati tugadi" deydi va qayta yuklash o'rniga "Ilovani yopish" (`Telegram.WebApp.close()`, keyin Telegram'dan qayta ochiladi) tugmasini beradi; `initData` hali yosh bo'lsa vaqtinchalik xatolar uchun "Qayta urinish" (reload) qoladi. Qurilma soati noto'g'ri bo'lishi mumkin, shuning uchun kirish baribir bir marta sinab ko'riladi (qarorni server qiladi). Deep link (`start_param`) bilan qayta kirishda shu odamning amaldagi saqlangan sessiyasi bo'lsa, har qanday xatoda (401 ham) o'sha sessiya qoladi; token faqat server o'sha tokenni rad etganda tashlanadi. Token o'lik va `initData` eskirgan bo'lsa — "yoping va qayta oching" ekraniga toza chiqish, so'rovlar takrorlanmaydi. Token muddati serverda (`SANCTUM_TOKEN_EXPIRATION`, standart 30 kun).

**Ma'lum cheklovlar (known limits).** Per-user kalit va saqlangan sessiya bilan darhol ochilish Telegram'ning imzolanmagan `initDataUnsafe.user.id` siga tayanadi. Shuning uchun umumiy desktop brauzer profilida (Telegram Web) launch hash'ini soxtalashtira oladigan yoki devtools ochgan odam o'sha brauzerdagi boshqa akkauntning saqlangan ma'lumotlariga yeta oladi — bu o'zgarishdan oldingidek. Mobil Telegram webview'lariga soxta hash berib bo'lmaydi. Buni yopish har ishga tushirishda `initData` ni server bilan tekshirishni talab qiladi (darhol ochilish yo'qoladi).

## Xarita va manzil

- Hamma geokodlash backend orqali (`/geocode/reverse`, `/geocode/search`): ilova geokoderga o'zi murojaat qilmaydi. Server tomoni: [../README.md](../README.md).
- `AddressLocationField`: "Joylashuvimni aniqlash" (avval Telegram `LocationManager`, Bot API 8.0+, so'ng brauzer geolokatsiyasi), manzil qidirish (kamida 3 belgi), xaritada bosish yoki pinni sudrash — 500 ms dan keyin nuqta manzilga aylantiriladi. Mijoz o'zi yozgan manzil ustidan yozilmaydi, faqat taklif qilinadi. Pin — haqiqat manbai: koordinata haqiqiy nuqta tanlanmaguncha `null`, standart yoki "o'ylab topilgan" nuqta yo'q (shunday eski saqlangan manzillarni `useAddressStore` migratsiyasi tozalaydi).
- Leaflet va CSS'i faqat manzil formasi ochilganda yuklanadi (`LazyMapPicker`, alohida `leaflet` chunk); yuklanmasa `MapErrorBoundary` faqat xarita o'rnini almashtiradi, forma ishlashda qoladi. Pin ham, qo'shimcha markaz (masalan, do'kon joylashuvi) ham bo'lmasa xarita Toshkentga qaratiladi (faqat ko'rinish, manzil emas).

## To'liq ekran va xavfsiz hududlar (safe area)

Telegram Bot API 8.0+ to'liq ekran rejimida ilova telefon status bari va Telegramning "Yopish" / "⌄ ⋯" tugmalari ostidan
chiziladi. `src/index.css` `--rz-inset-*` (top/bottom/left/right) = qurilma xavfsiz hududi (`--tg-safe-area-inset-*`,
eski klientlarda `env(safe-area-inset-*)`) + Telegram tugmalari hududi (`--tg-content-safe-area-inset-*`). `lib/twa.ts`
(`applySafeAreas`) ularni `safeAreaInset` / `contentSafeAreaInset` dan `--rz-tg-*` sifatida ham yozadi va
`safeAreaChanged`, `contentSafeAreaChanged`, `fullscreenChanged` hodisalarini kuzatadi. Oddiy rejimda hammasi 0.
Ilova o'zi to'liq ekran so'ramaydi (`requestFullscreen` chaqirilmaydi): buni foydalanuvchi yoki BotFather sozlamasi hal
qiladi. Tekshiruv: `e2e/safe-area.spec.ts` (`MINIAPP_SHOTS_DIR=<papka>` bilan skrinshotlar ham oladi).

## Analitika (P to'lqini, 3-qadam)

Mini App voronka hodisalarini (ochildi → restoran → savat → buyurtmaga o'tish → buyurtma) **faqat o'z serverimizga**
yuboradi: `POST /api/v1/analytics/events`, mijoz tokeni bilan. Shartnoma va hodisalar ro'yxati:
[docs/API.md](../../docs/API.md) "Analitika hodisalari"; reja: [docs/ANALYTICS_PLAN.md](../../docs/ANALYTICS_PLAN.md) 4.1, A.4.

- `src/lib/analytics.ts` — kirish bundle'idagi kichik qism: `track`, `trackAppOpen`, `setAnalyticsAuth`. Build'da
  `VITE_ANALYTICS_ENABLED=true` bo'lsagina klient (`src/lib/analyticsClient.ts`, alohida chunk) birinchi chaqiruvda
  yuklanadi; yuklanguncha chaqiruvlar kichik buferda turadi va o'z vaqti bilan, tartibda uzatiladi (`app_open` birinchi).
  O'chiq bo'lsa hech narsa yuklanmaydi.
- `src/lib/analyticsClient.ts` — klient (qaramliksiz): xotiradagi navbat (200 tadan oshsa eng eskisi tashlanadi), ~10 s da
  bir paket (≤ 50 hodisa, ≤ 32 KB); `order_placed` va `payment_redirect` darhol, `fetch keepalive` bilan; sahifa
  yashirilganda/yopilganda `keepalive`. Bir vaqtda bitta so'rov; sahifa yashirilganda yo'ldagi so'rov yonida yana bitta
  (yo'lda bo'lmagan hodisalar bilan; nginx mijozga 2 tagacha ruxsat beradi, server `uid` bo'yicha takrorni tanib oladi),
  daqiqasiga ko'pi bilan 10 ta. `202` — tayyor (`accepted: 0` ham: server standart bo'yicha o'chiq); `429`/`5xx`/tarmoq —
  o'sha `uid` lar bilan, kutish o'sib boradi (`Retry-After` birinchi, ko'pi bilan 5 daqiqa); faqat HTTP xatolari 3
  urinishga sanaladi (uzilgan aloqa va sahifa yopilishidagi oxirgi urinish sanalmaydi); `413`/`422` — paket tashlanadi;
  `401` — o'sha hisobning yangi tokeni kelguncha kutadi.
  Har hodisa qaysi hisob ostida yozilgani bilan navbatda turadi va faqat o'sha hisob tokeni bilan ketadi: kirishdan
  oldingi (yoki chiqishdan keyingi) hodisalar shu sahifada keyin kirgan hisobniki; boshqa hisob kirsa oldingisining
  hodisalari tashlanadi va yangi sessiya boshlanadi. Xom `start_param` hech qachon yuborilmaydi (unda xodim taklif tokeni
  yoki boshqa odamning referral id'si bo'lishi mumkin): faqat `app_open.props.start_kind` (kampaniya bo'lsa
  `campaign_code`).
  Sessiya: tasodifiy UUID; mijoz ekani ma'lum bo'lgandan keyingina `sessionStorage` dagi `rz_session` ga yoziladi (id,
  oxirgi harakat, ishga tushirish va hisobning qisqa hash'i — xom qiymat emas). 30 daqiqa hodisasiz o'tsa, boshqa hisob
  kirsa yoki yangi ishga tushirish bo'lsa (Telegram Web bir tabda qayta ochilganda `sessionStorage` saqlanadi; yangi
  `initData` = yangi ishga tushirish) yangi sessiya va uning `app_open` i; o'sha ishga tushirishni sahifani qayta
  yuklash sessiyani davom ettiradi. `localStorage` ga hech narsa yozilmaydi. Hech qachon xato otmaydi va
  buyurtma/to'lov so'rovlarini kutdirmaydi.
- `src/hooks/useAnalytics.ts` — React qismi: kirishni uzatish (`useAnalyticsAuth`; roli `customer` bo'lmagan yoki
  do'konga biriktirilgan hisob, shuningdek saqlangan hisobning `telegram_id` si shu ishga tushirishdagi Telegram
  foydalanuvchisiga mos kelmasa — **hech narsa yuborilmaydi**, navbat tozalanadi, sessiya yozuvi o'chiriladi), bir
  harakatga bitta hodisa (`useTrackOnce` — StrictMode va qayta render ikki marta yubormaydi), qidiruv (`useTrackSearch` —
  faqat natijalar soni va so'z uzunligi, matn hech qachon).
- Hodisalar qayerda: `App.tsx` (`app_open`, har ishga tushirishda bir marta, `start_param` turi bilan),
  `StoreDetail.tsx` (`store_view`, `category_view`), `ProductSheet.tsx` (`product_view`), `useAddToCart.ts` va
  `ReplaceCartDialog.tsx` (`add_to_cart`), `Cart.tsx` (`cart_view`; `checkout_start` — "Buyurtma berish" ilovaning
  o'z tekshiruvlaridan o'tib `POST /orders` yuborilganda; `checkout_error` — ilova yoki server rad etganda, yetkazib
  berish narxi hali hisoblanayotgan bo'lsa emas; `order_placed` — `POST /orders` 2xx dan keyin, `payment_redirect` —
  `tg.openLink` dan oldin), `Home.tsx`, `Stores.tsx`, `CategoryDetail.tsx` (`search`), `HeroPromoSlider.tsx`
  (`promo_banner_click`).
- Testlar: `src/lib/analytics.test.ts` (yuklovchi va bufer), `src/lib/analyticsClient.test.ts` (navbat, paketlar, qayta
  urinish, hisob almashishi, sessiya, qayta ishga tushirish, xodim, o'chirgich, maxfiylik),
  `src/hooks/useAnalytics.test.tsx`, `src/pages/StoreDetail.analytics.test.tsx`, `src/pages/Cart.analytics.test.tsx`,
  `src/test/analyticsPlaces.test.tsx`; e2e: `e2e/analytics.spec.ts` (e2e build'ida `VITE_ANALYTICS_ENABLED=true`, soxta
  API serverning konvert va ruxsat ro'yxati qoidalarini takrorlaydi: paketlar `api.analyticsBatches` da, server
  tashlaydiganlari `api.analyticsDropped` da).

**Yangi hodisa qo'shish** (shu tartibda, aks holda server hodisani jim tashlaydi):

1. **Ro'yxat:** `docs/API.md` "Hodisalar ro'yxati" jadvaliga qator — nom, qachon, id'lar, ruxsat etilgan `props` va
   ularning qoidasi. Erkin matn, ism, telefon, manzil, Telegram id hech qachon prop bo'lmaydi; maxfiylik siyosati
   (`privacy.s2.b`) yangi turdagi ma'lumotni qamrab olishini tekshiring.
2. **Server:** `backend/app/Domain/Analytics/AnalyticsEvent.php` ga yangi `case` (keyingi raqam; raqamlar saqlangan
   ma'lumot — hech qachon o'zgartirilmaydi) va `spec()` dagi ids/props; `EventIngestor` testi.
3. **Klient:** `src/lib/analyticsClient.ts` dagi `AnalyticsCatalogue` (tur: ro'yxatda yo'q prop kompilyatsiya bo'lmaydi) va
   `SPEC` (ishga tushganda ruxsat ro'yxati); hodisani joyida `track(...)` bilan chaqiring (bir harakatga bir marta:
   kerak bo'lsa `useTrackOnce`), testini yozing; `e2e/fakeApi.ts` dagi `ANALYTICS_SPEC` ni yangilang.

## Ma'lumot yangilanishi

Ro'yxatlar so'rov (polling) bilan yangilanadi (`lib/polling.ts`): mijoz buyurtmalari 10 s, menejer 8 s, kuryer va admin buyurtmalari 15 s, xabarnomalar 15 s, egasi moliyasi 10 s, smena xulosasi 30 s, qarzdorlar 60 s. Sahifa yashirin bo'lsa so'rov to'xtaydi, qaytganda yoki tarmoq tiklanganda yangilanadi; ketma-ket xatolarda interval ikki baravar oshadi (ko'pi bilan 120 s). Pul summalari API'dan o'nli satr (`"15000.00"`) bo'lib keladi: hisob-kitob va ko'rsatishdan oldin `lib/money.ts` (`toAmount`, `sumAmounts`) va `lib/format.ts` (`formatUzbekSum`) dan o'tkaziladi.

## Testlar

- Vitest 5 + jsdom + Testing Library (`vitest.config.ts`: `src/**/*.test.{ts,tsx}`, `css: false`, har testdan oldin mock/spy/stub'lar tiklanadi). Testlar kodning yonida (`foo.ts` — `foo.test.ts`).
- `src/test/setup.ts`: jest-dom matcherlari, har testdan keyin `cleanup()` va `localStorage.clear()`. `factories.ts` — API JSON shaklidagi tipli fixture'lar (`makeUser`, `makeStore`, `makeOrder`, ...). `httpErrors.ts` — haqiqiy axios xatosini yasaydi va konsolga/ekranga token, OTP, telefon, `initData` chiqib ketmasligini tekshirish uchun.
- Tarmoq haqiqiy ishlatilmaydi: `services/api.test.ts` o'z axios adapterini beradi va modulni har testda yangidan yuklaydi (qayta autentifikatsiya holati modul darajasida turgani uchun); polling va taymerlar soxta vaqt bilan tekshiriladi; sahifa testlari (`pages/*.test.tsx`) API'ni mock qiladi.
- CI (`.github/workflows/ci.yml`, `customer/miniapp/` papkasida, Node 22): `frontend` job — `npm ci`, `npx eslint .`, `npm test`, `npm run build`; alohida `e2e` job — `npx playwright install --with-deps chromium`, `npm run test:e2e`.

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
- Haqiqiy backend bilan umumiy sinov (P2-31): `scripts/e2e-stack.sh` Mini App'ni (`vite preview`, `/api` proksisi `API_PROXY_TARGET` bilan backend'ga) partner web, Laravel va Reverb bilan birga ishga tushiradi; testlar `e2e/stack/` da.

## Docker image

`Dockerfile` ko'p bosqichli: `node:22-alpine` da `PUPPETEER_SKIP_DOWNLOAD=1 npm ci` va `npm run build` (`VITE_*` build argumentlari bilan), so'ng `nginx:1.27-alpine` ga `dist/` nusxalanadi. `docker/nginx.conf`:

- SPA uchun `index.html` ga qaytish; `/api/` va `/storage/` (yuklangan rasmlar) `http://backend:80` ga yo'lni o'zgartirmasdan proksilanadi (`Host`, `X-Real-IP`, `X-Forwarded-For` va `X-Forwarded-Proto` bilan; kelgan `X-Forwarded-Proto` o'zgarmay uzatiladi, yo'q bo'lsa `$scheme` qo'yiladi).
- `backend` nomi Docker DNS (`resolver 127.0.0.11`, 5 s kesh) orqali har so'rovda aniqlanadi, shuning uchun backend konteyneri alohida qayta yaratilsa ham frontend'ni qayta ishga tushirish shart emas. Bu qism faqat Docker Compose tarmog'ida ishlaydi.
- `client_max_body_size 10m` (nginx standarti 1 MB rasm yuklashni to'sardi; API rasmni 5 MB bilan cheklaydi).
- Kesh: `index.html` — `Cache-Control: no-cache` (har safar qayta tekshiriladi), `/assets/*` (nomida hash bor) — `public, max-age=31536000, immutable`, `public/` dagi hashsiz fayllar (`favicon.svg`, ...) — 30 kun.
