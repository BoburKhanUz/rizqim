# platform/admin: RIZQIM boshqaruv markazi

RIZQIM jamoasi (operator, yordam, moliya, savdo, rahbar) uchun veb-ilova: platformani bitta joydan boshqarish
([docs/PHASE4_PLAN.md](../../docs/PHASE4_PLAN.md), 3–5 va 7-bo'limlar). Next.js 16 (App Router), React 19, TypeScript, Tailwind CSS 4,
TanStack Query: hamkor paneli ([partner/web](../../partner/web/README.md)) bilan bir xil texnologiya va bir xil dizayn tizimi
([`@rizqim/ui`](../../packages/ts/ui/README.md)). Biznes mantiq va ruxsatlarni tekshirish faqat backend'da (`/api/v1/admin/*`);
ilova o'qiydi va ko'rsatadi, menyuda ruxsat berilmagan bo'limlarni yashiradi. Qo'llanma jamoa uchun:
[docs/guides/boshqaruv-markazi.md](../../docs/guides/boshqaruv-markazi.md).

Hozir (P4-12, qobiq): email + parol + 2FA bilan kirish, taklifni qabul qilish, qayta tasdiqlash oynasi, yon menyu, `Ctrl+K` qidiruv,
**Tizim**: Jamoa, Rollar, Harakatlar jurnali, Sessiyalarim. Qolgan bo'limlar ("Bu bo'lim keyingi to'lqinda qo'shiladi") keyingi
to'lqinlarda to'ldiriladi. Matnlar o'zbekcha (lotin); `src/i18n/` hamkor paneli tuzilmasida, rus tili keyin qo'shiladi. Mavzu
(yorug'/qorong'i) tizim sozlamasidan. 360 px telefon kengligida ishlaydi.

## Ishga tushirish (mahalliy)

Node.js 22 va npm kerak. Ilova `file:` orqali ikki paketni ulaydi: `ui` (manba kod) va `api-client` (admin endpoint'lari **turlari** shu paketdan:
`src/lib/api/types.ts`; so'rovlarni ilovaning o'z qatlami yuboradi — sessiya cookie'si, qayta tasdiqlash va bo'sh turish uchun). `@rizqim/api-client`
`dist/` ga yig'ilgan bo'lishi kerak.

```bash
cd packages/ts/api-client && npm ci && npm run build      # bir marta (API o'zgarsa qayta)
cd ../../../platform/admin
cp .env.example .env.local                                  # API_BASE_URL ni tekshiring
npm ci
npm run dev                                                 # http://localhost:3100
```

Backend ishlab turishi kerak (`php artisan serve`, `http://127.0.0.1:8000`). Birinchi superadmin: `php artisan rizqim:admin:create`
([docs/OPERATIONS.md](../../docs/OPERATIONS.md)). **CORS sozlash kerak emas** (pastga qarang).

| Buyruq | Vazifasi |
|---|---|
| `npm run dev` | Dev server (Turbopack, port 3100) |
| `npm run build` / `npm start` | Production build (`output: 'standalone'`) va uni ishga tushirish. HTTP orqali bo'lsa `SESSION_COOKIE_SECURE=false` kerak |
| `npm run lint` | ESLint (`eslint-config-next`) |
| `npm run typecheck` / `typecheck:e2e` | `tsc --noEmit` (ilova / `e2e/` papkasi) |
| `npm test` | Vitest + Testing Library (har bir ekran, kirish, 2FA, qayta tasdiqlash, bo'sh turish ogohlantirishi, proksi) |
| `npm run test:e2e` | Playwright (brauzer, production build) soxta backend bilan, 360 px ham |

## Sozlamalar (muhit o'zgaruvchilari, `.env.example`)

Hammasi **runtime**'da o'qiladi (build'ga yozilmaydi): bitta image barcha muhitlarda ishlaydi.

| O'zgaruvchi | Ma'nosi |
|---|---|
| `API_BASE_URL` | Next **serveri** backend'ga shu manzil bilan murojaat qiladi. Docker tarmog'ida ichki manzil, `/api` bilan tugaydi |
| `SESSION_COOKIE_SECURE` | Sessiya cookie'siga `Secure`. Production'da standart `true`; HTTP orqali sinashda `false` |

## Kirish va sessiya

Admin token hamkor panelidagidan farq qiladi: u **httpOnly cookie**da (`rz_admin`, `SameSite=Strict`) turadi va sahifa JavaScript'i uni
hech qachon ko'rmaydi. Brauzer backend'ga to'g'ridan-to'g'ri murojaat qilmaydi: hamma so'rov ilovaning o'z manzilidan
`/api/backend/v1/admin/...` o'tadi ([src/lib/server/proxy.ts](src/lib/server/proxy.ts), [route](src/app/api/backend/[...path]/route.ts)).
Proksi: faqat `/v1/admin/*` va `/broadcasting/auth` yo'llarini uzatadi; yozuvchi so'rovlarda `Origin` ni tekshiradi; cookie'ni
`Authorization: Bearer` ga aylantiradi; mijoz IP'sini (`X-Forwarded-For`, oxirgi yozuv) va brauzer nomini backend'ga uzatadi
(jurnal va kirish limitlari uchun); `2fa/verify` va `2fa/confirm` javobidagi `token` ni javobdan olib cookie'ga qo'yadi;
backend 401 desa cookie'ni o'chiradi. CORS shuning uchun kerak emas.

1. `POST /auth/login {email, parol}` → `otp_required` yoki `setup_required` va 5 daqiqalik bir martalik `challenge_token`
   (u faqat sahifa xotirasida turadi).
2. 6 xonali kod: `2fa/verify`. Telefon yo'q bo'lsa tiklash kodi. Birinchi marta: `2fa/setup` (QR `<img>` ichida data-URI sifatida
   chiziladi, kalit nusxa olinadi) → `2fa/confirm` → 10 ta tiklash kodi **bir marta** ko'rsatiladi ("Nusxa olish", "Yuklab olish
   (.txt)", majburiy "Saqlab qo'ydim" belgisi) → qobiq.
3. Taklif: `/invite/<token>` — parol (12+ belgi), keyin 2FA o'rnatish.
4. **Qayta tasdiqlash**: backend `403 {code: "reauth_required"}` desa, 6 xonali kod oynasi o'zi ochiladi
   ([StepUpProvider](src/components/StepUpProvider.tsx)), `POST /auth/confirm` 10 daqiqaga tasdiqlaydi va so'rov **bir marta**
   takrorlanadi. Bekor qilinsa so'rov rad etilgan bo'lib qoladi.
5. **Bo'sh turish**: backend sessiyani 60 daqiqa so'rovsiz qolsa tugatadi (12 soat — mutlaq chegara). 55 daqiqadan keyin
   ogohlantirish chiqadi ("Men shu yerdaman" bitta arzon so'rov bilan yangilaydi), 60 da ilova o'zi chiqaradi
   ([IdleGuard](src/components/IdleGuard.tsx)). Har qanday 401 ham kirish sahifasiga qaytaradi.

## Tuzilma va kengaytirish nuqtalari

```
src/app/(app)/<bo'lim>/page.tsx    har bir bo'lim uchun bitta fayl: today, orders, stores, pos, finance, customers, marketing,
                                   monitoring, integrations (hozir <Placeholder>), team, roles, audit, sessions (tayyor)
src/app/(app)/{orders/[uuid], stores/[id], users/[id], team/[id]}   qidiruv natijalari olib boradigan manzillar
src/lib/nav.ts                     yon menyu: tartib, bo'lim -> ruxsatlar (admin.* nomi "admin." siz yoziladi)
src/lib/api/                       admin endpoint'lari: types.ts (api-client'dan olingan turlar), endpoints.ts, hooks.ts (TanStack Query),
                                   client.ts (xato, qayta tasdiqlash, bo'sh turish), normalize.ts (spetsifikatsiya yetarli aniq bo'lmagan
                                   joylar), usedCodes.ts (hozirgina ishlatilgan 2FA kodi)
src/lib/errors.ts                  har bir xato kodi (docs/API.md "Xato formati") -> oddiy o'zbekcha gap
src/i18n/sections/<bo'lim>.ts      bo'limning matnlari (kalitlar `<bo'lim>_` bilan boshlanadi — test tekshiradi)
e2e/fake/                          soxta admin backend (kirish, 2FA, jamoa, rollar, jurnal, qidiruv)
```

**To'lqin 1, B bosqichi** (P4-13 Bugun, P4-14 Buyurtmalar, P4-15 Monitoring): tegishli `page.tsx` dagi `<Placeholder>` ni haqiqiy
ekranga almashtiring; yon menyu yozuvi, ruxsat (`today.view`, `orders.view`, `monitoring.view`) va manzil tayyor. `platform.ops`
kanaliga ulanish (`laravel-echo`, `pusher-js` hamkor panelida bor) shu bosqichda qo'shiladi: imzolash `/api/backend/broadcasting/auth`
orqali o'tadi.

## Qayta tasdiqlash va 2FA kodlari

Backend har bir 2FA kodini **bir marta** qabul qiladi (va undan eskisini ham qayta qabul qilmaydi), kirish paytida ishlatilgan kod ham
hisobda. Shuning uchun kirgandan keyin darhol muhim amal qilinsa, qayta tasdiqlash oynasida xuddi shu kod rad etiladi. Ilova oxirgi qabul qilingan kodni
90 soniya xotirada (faqat sahifa xotirasida) saqlaydi va uni qayta yozilganda serverga yubormay "Bu kod hozirgina ishlatilgan — keyingi kodni kiriting"
deydi; server `invalid_code` desa: "ilovada yangi kod chiqquncha (30 soniya) kuting".

## Umumiy UI

Hamkor paneli bilan umumiy komponentlar `packages/ts/ui` da: `PageHeader`, `Notice`/`FormError`, `Pill`, `Switch`, `Tabs`,
`ConfirmDialog` (matnlari ilovadan beriladi). Nusxa ko'chirilmaydi: ikkala ilova bir xil kodni ishlatadi.

## E2E testlar

`npm run test:e2e` production build'ni (`next build` + `next start`, port 13200) ishga tushiradi va Chromium'da soxta backend bilan
(`e2e/fake`, port 14200) sinaydi: kirish + 2FA (o'rnatish, tiklash kodlari, bloklash, muddati o'tgan challenge), taklif, rol o'zgartirish
(qayta tasdiqlash bilan), rollar jadvali, qidiruv oynasi, jurnal (filtr, kursor, tafsilot), sessiyalar, 360 px. Bitta worker.
Chromium boshqa joyda bo'lsa `PW_CHROMIUM_PATH`; `E2E_SKIP_BUILD=1` oxirgi build'ni qayta ishlatadi; `E2E_WEB_PORT`/`E2E_FAKE_PORT` portlarni almashtiradi.

### Haqiqiy backend bilan

`scripts/e2e-stack.sh` (hamkor paneli va Mini App bilan umumiy stek: Laravel, Postgres, Reverb) boshqaruv markazini ham ishga tushiradi (`E2E_ADMIN_PORT`,
13300) va `php artisan rizqim:admin:create` bilan birinchi superadminni yaratadi. `e2e/stack/tests/admin-journey.spec.ts`: taklif havolasi -> parol -> 2FA
(TOTP kodlarni test o'zi kalitdan hisoblaydi, RFC 6238) -> tiklash kodlari -> boshqa a'zoni taklif qilish (qayta tasdiqlash: keyingi 30 soniyalik qadamni kutadi) ->
u taklifni qabul qiladi -> tiklash kodi bilan qayta kirish -> rolni o'zgartirish (yana qayta tasdiqlash) -> jurnal (oldin/keyin, IP, brauzer) -> qidiruv -> chiqish.

## Docker

```bash
docker build -f platform/admin/Dockerfile -t rizqim-platform-admin .     # kontekst — repozitoriy ildizi
```

Compose'da `platform-admin` xizmati, host va proksi: [docs/DEPLOY.md](../../docs/DEPLOY.md), 4-bo'lim, «RIZQIM boshqaruv markazi».
