# @rizqim/staff

Xodim ekranlari uchun umumiy kod: **Mini App'dagi xodim sahifalari** (menejer, kuryer, ega) va **hamkor web panel** shu paketdan
foydalanadi, shuning uchun bitta tuzatish ikkala joyga tushadi ([docs/PHASE2_PLAN.md](../../../docs/PHASE2_PLAN.md), "Asosiy tamoyil").

**Biznes qoidalari faqat backend'da.** Paket faqat *ko'rsatish* mantig'ini beradi: buyurtma statuslari, nomlari, ranglari va ekran qaysi
tugmalarni taklif qilishi; formatlar; tarjimalar; API chaqiruvlari. Status o'tishi mumkinmi — backend hal qiladi (rad etsa, 403/422 va
xabar ekranga chiqadi).

Paket **manba kod** sifatida ulanadi (build yo'q), [`@rizqim/ui`](../ui/README.md) kabi: `exports` to'g'ridan-to'g'ri `src/index.ts` ga ishora qiladi.
Telegram, router, til ombori va tokenni saqlash haqida hech narsa bilmaydi: til har chaqiruvga beriladi, `fetch` (va u bilan token)
ilovadan keladi.

## Nima bor

| Papka | |
|---|---|
| `src/orders/status.ts` | Barcha `OrderStatus` qiymatlari, ochiq/yakunlangan ro'yxatlari, rang guruhlari (`STATUS_TONE`; `@rizqim/ui` nishoni shu guruhlardan chiziladi, testi tenglikni tekshiradi), doska ustunlari (`GROUP_STATUSES`), **rolga qarab keyingi amallar** (`getOrderActions(order, 'manager' \| 'owner' \| 'courier')`: qabul qilish, rad etish, tayyorlash, kuryer tayinlash, topshirish, bekor qilish; har amal qaysi endpoint'ga ketishi va sabab/kuryer kerakligini aytadi) |
| `src/orders/` | bekor qilish sabablari (`getStaffCancelReasons`), bosqichlar vaqti (`getStageDurations`), schema'da bo'sh turlangan ustunlarni xavfsiz o'qish (`getShippingAddress`, `getItemModifiers`), buyurtma ro'yxatlari keshi (`refetchOrders`, `applyFinishedOrderChange`, `mergeOrders`); kutish vaqti va uning rangi (`getOrderWaiting`, `WAITING_THRESHOLDS`: serverning `waiting_seconds` qiymati, web doska va Mini App bir xil chegaralar bilan); yangi buyurtma signali (`createChime`: Web Audio, birinchi bosishdan keyin ochiladi, `close()` bilan bo'shatiladi) |
| `src/i18n/` | uz/ru (ba'zi joyda en) matnlar: status nomlari (`statusLabel`), buyurtma turi, to'lov usuli, bekor qilish sabablari, tugma nomlari (`actionLabel`), menejer va kuryer ekranlari matnlari (`staffText(key, language, params)`) |
| `src/format/` | Pul, son, sana, vaqt: `@rizqim/ui` ning o'zi (Mini App hozir ham shu bilan yozadi); kg/l miqdor (`formatQuantity`, `formatLineQuantity`) |
| `src/api/` | `createStaffApi({ baseUrl, fetch })` + funksiyalar: buyurtmalar (ro'yxat, tarix sahifasi, status, kuryer tayinlash, kuryerlar), menyu (mahsulotlar, kategoriyalar, saqlash, stop-list, qozonlar, mavjudlik), xodimlar (taklif, filialga o'tkazish, PIN, rol, ruxsatlar), sozlamalar, to'lovlar (balans, to'ldirish, payout, tarif, hisob-fakturalar), analitika va hisobot eksporti. Xato — `StaffApiError` (`status`, server `message`, `fieldError('x')`) |
| `src/api/` (POS, P3-23) | `fiscal.ts` (MXIK/qadoq kodi tekshiruvi, fiskal tayyorlik, do'kon fiskal sozlamalari, cheklar va qayta urinish, onlayn to'lov `fiscal_status` muammolari, `productFiscalFormData`: mahsulotni **to'liq** qayta yuboradi), `floor.ts` (zallar, stollar/xonalar/tapchanlar, `saveFloorLayout`, xona tariflari + dam olish/bayram kalendari, avto choy-non, zal xizmat haqi), `posSetup.ts` (oshxona stansiyalari, yo'nalishlar, printerlar, filial POS sozlamalari), `bookings.ts` (bron taqvimi va amallar), `accounts.ts` (nasiya hisoblari, qarzdorlar, ko'chirma), `shifts.ts` (smenalar, X/Z hisobot, filial smena xulosasi), `idempotency.ts` (`Idempotency-Key`) |
| `src/hooks/` (POS, P3-23) | `useFiscalReadiness`, `useFiscalSettings`, `useFiscalReceipts`, `useHalls`, `useSaveFloorLayout`, `useRoomTariffs`, `useKitchenStations`, `usePrinters`, `useBookingCalendar`, `useAccounts`, `useDebtors`, `useShifts`, `useBranchShiftSummary`, ... Kalitlar `['owner', ...]` va `['pos', ...]`; yozuvlar tegishli ro'yxatlarni yangilaydi |
| `src/api/entitlements.ts`, `src/hooks/entitlements.ts` (P4-21) | tarif nimaga yo'l qo'yadi: `GET /owner/billing/entitlements` (qo'lda yozilgan turlar: `Entitlements`, `BillingNotice`, `DeviceEntitlement` — spetsifikatsiya yangilangach yaratilganlarga almashtiriladi), `hasFeature`, `requiredPlanName`, `daysUntilLock`, `trialDaysLeft`, `billingRefusalOf(err)` (403 `plan_feature_missing` / `subscription_locked` / `device_limit`), `useEntitlements`, `useFeature('bookings')` (noma'lum bo'lsa — ochiq: hal qilishni server qiladi), `subscriptionState`, `invoiceOutstanding` |
| `src/hooks/` | TanStack Query hook'lari shu funksiyalar ustida: `useActiveOrders`, `useOrderHistory`, `useUpdateOrderStatus`, `useCouriers`, `useOwnerProducts`, `useStopList`, `useBatches`, `useEmployees`, `useOwnerSettings`, `useOwnerFinance`, `useOwnerAnalytics`, ... Polling, `enabled` va h.k. chaqiruvchidan `options` bilan keladi |

Turlar (`Order`, `Product`, `OwnerFinance`, ...) `@rizqim/api-client` ning yaratilgan `schema.d.ts` idan olinadi; qo'lda yozilgan API turi yo'q.
Yagona istisno — `src/api/specGaps.ts`: OpenAPI spetsifikatsiyasi nohaq bo'lgan ba'zi javoblar (`GET /owner/employees` ro'yxatini `string`
deydi, `GET/POST /owner/settings` `payment_settings` ni noto'g'ri turlaydi, ...) uchun to'g'rilangan
turlar, har biri qaysi endpoint va spetsifikatsiya nima deyishini aytadi. Backend annotatsiyasi to'g'rilangach, o'chiriladi.

## API va tokenni ulash

Paket tokenni o'zi o'qimaydi. Ilova `fetch` beradi (`Request` qabul qilib `Response` qaytaradi), yoki `createAuthFetch` bilan kichik adapterdan yasaydi:

```ts
import { createAuthFetch, createStaffApi, setDefaultStaffApi, toApiRoot } from '@rizqim/staff';

const staffApi = createStaffApi({
  baseUrl: toApiRoot('/api/v1'),               // "/api": yaratilgan yo'llar /v1/... bilan boshlanadi
  fetch: createAuthFetch({
    getToken: () => store.token,               // Bearer token
    renewToken: async (sentWith) => newTokenOrNull, // 401 da: yangi token yoki null (bir marta, single-flight adapterniki)
    onSignedOut: () => store.logout(),
  }),
});
```

- **Mini App** (`customer/miniapp/src/services/staffApi.ts`): token — auth ombori, 401 da Telegram `initData` bilan qayta kirish (`services/api.ts` dagi `recoverToken`: axios mijozi bilan bitta single-flight va cooldown). `setDefaultStaffApi(staffApi)` — bitta API butun umr.
- **Hamkor web** (`partner/web/src/lib/session/client.ts`): o'zining `authFetch` i (xotiradagi access token, cookie refresh, single-flight) to'g'ridan-to'g'ri `fetch` sifatida beriladi; `<StaffApiProvider api={getStaffApi(apiBaseUrl)}>` `Providers` da.

Hook'lar APIni `<StaffApiProvider>` dan, bo'lmasa `setDefaultStaffApi()` dan oladi.

## Ilovaga ulash

1. `package.json`: `"@rizqim/staff": "file:../../packages/ts/staff"` (va `@rizqim/api-client`, `@rizqim/ui`), so'ng `npm install`. `@rizqim/api-client` `dist/` ga yig'ilgan bo'lishi kerak (`cd packages/ts/api-client && npm ci && npm run build`).
2. **Bitta React va bitta query konteksti.** Paket fayllari symlink orqali haqiqiy joyida turadi, shuning uchun uning `react`, `@tanstack/react-query`, `@rizqim/api-client`, `@rizqim/ui` importlari ilovaning nusxalariga yo'naltirilishi kerak (`@rizqim/ui` README dagi usul, ro'yxat kengaygan):
   - Vite (Mini App): `resolve.dedupe: ['react', 'react-dom', 'lucide-react', '@tanstack/react-query', '@rizqim/api-client', '@rizqim/ui']`;
   - TypeScript: `paths` — shu paketlarni ilovaning `node_modules` iga (`@rizqim/ui` ni `.../src/index.ts` ga);
   - Next.js (partner/web): `transpilePackages: ['@rizqim/ui', '@rizqim/staff']` va `turbopack.resolveAlias`.
3. Docker: `packages/ts/staff` ni `Dockerfile` ga `COPY` qiling va `Dockerfile.dockerignore` ro'yxatiga kiriting; `packages/ts/api-client` `npm ci && npm run build` bilan yig'iladi.

## Tekshiruv

```bash
npm ci                # packages/ts/api-client ni avval yig'ing (yuqorida)
npm run lint
npm run typecheck     # testlardagi expectTypeOf tekshiruvlari ham: spetsifikatsiya o'zgarsa, shu yerda yiqiladi
npm test              # formatlar, status ma'lumotlari, matnlar, API funksiyalari (soxta fetch), adapter, hook'lar
```

CI: `staff-package` job (`.github/workflows/ci.yml`) paket yoki `packages/ts/ui`, `packages/ts/api-client`, `packages/api-spec` o'zgarganda ishlaydi;
Mini App va hamkor web job'lari ham paket o'zgarganda ishlaydi.
