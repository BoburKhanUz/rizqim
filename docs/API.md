# Rizqim API: qanday foydalanish

Bu hujjat ilova yozuvchi uchun: API'ga qanday kirish, ruxsatlar qanday ishlashi, xatolar qanday keladi, takroriy
so'rov, sahifalash, cheklovlar va real-time. Har bir endpoint'ning to'liq tavsifi OpenAPI spetsifikatsiyasida
(10-bo'lim). Umumiy arxitektura: [ARCHITECTURE.md](ARCHITECTURE.md).

Ikki joy hozir o'zgarmoqda (to'lqin 3), ular haqida bu hujjat faqat qisqa eslatma beradi:

- **Xodimlarning SMS orqali kirishi** (P1-36): hamkor paneliga ega va menejer telefon + SMS kod bilan kiradigan bo'ladi,
  kuryerni Telegramsiz tayinlash. Hozirgi holat — 2-bo'limda; o'zgarish tugagach [AUTH.md](AUTH.md) yangilanadi.
- **Buyurtma yaratishda hududlar** (P1-32): `POST /orders` da yetkazish narxi, minimal summa va filial yetkazish
  hududidan olinadi, yangi 422 sabablari qo'shiladi. Hozirgi holat — [ZONES.md](ZONES.md) va README.

## 1. Asosiy qoidalar

- **Manzil:** `https://<domen>/api/v1/...`. Mini App o'z domenidagi `/api/` orqali (same-origin), hamkor paneli va
  boshqa ilovalar backend domeniga to'g'ridan-to'g'ri (CORS: `CORS_ALLOWED_ORIGINS`).
- **Versiya:** hamma narsa `/api/v1` ostida. v1 ichida faqat **qo'shimcha** o'zgarishlar qilinadi: yangi endpoint,
  yangi ixtiyoriy parametr, javobga yangi maydon. Mavjud maydonni o'chirish, nomini yoki turini o'zgartirish mumkin
  emas (Mini App va yaratilgan TS/Dart klientlar shunga tayanadi). Mos kelmaydigan o'zgarish kerak bo'lsa — `/api/v2`.
  Ilova javobdagi notanish maydonlarni e'tiborsiz qoldirishi kerak.
- **Format:** JSON. So'rovda `Accept: application/json`. Rasm yuklash — `multipart/form-data`.
- **Pul:** javoblarda ikki xonali satr (`"12000.00"`) yoki son; ilova uni float bilan hisoblamasligi kerak
  (Dart: `rizqim_core` `Money`, TS: `@rizqim/ui` formatlash). Narx va summani har doim server hisoblaydi.
- **Miqdor:** donali taomda butun son (`2`), kg/l da kasr (`1.5`, ko'pi bilan 3 xona).
- **Vaqt:** ISO 8601 (`2026-10-20T14:05:00+05:00`). Do'kon vaqt mintaqasi standart `Asia/Tashkent`.
- **Identifikatorlar:** buyurtma, qurilma, do'kon — tashqi ilovalar uchun `uuid`; ichki obyektlar (filial, mahsulot,
  partiya) — raqamli `id`.
- **So'rov raqami:** har bir javobda `X-Request-Id` sarlavhasi. Xato haqida xabar berganda shu raqamni yuboring: u
  bo'yicha serverdagi barcha log qatorlari topiladi.

## 2. Kirish turlari

Hammasi `Authorization: Bearer <token>`. Tafsilotlar va barcha muddatlar: [AUTH.md](AUTH.md).

| Kim | Qanday | Endpoint | Token |
|---|---|---|---|
| Telegram Mini App | Telegram `initData` (HMAC tekshiriladi, `auth_date` 10 daqiqadan eski emas) | `POST /auth/telegram {initData, bot_username, start_param?}` | bitta token, 30 kun; 401 bo'lsa qayta `initData` bilan kiradi |
| Mobil ilova, hamkor web | Telegram yoki telefon + SMS kod, **`device` obyekti bilan** | `POST /auth/telegram` yoki `POST /auth/phone/send-code` → `POST /auth/phone/verify {phone, code, device}` | access token (15 daqiqa) + refresh token (30 kun) |
| Tokenni yangilash | refresh token | `POST /auth/refresh {refresh_token, device_uuid}` | yangi juftlik; eskisi sarflanadi |
| POS/KDS terminal | 8 belgili ulash kodi (egasi panelda oladi) | `POST /devices/pair {code, name?, platform?, app_version?}` | terminal tokeni (365 kun yoki o'chirilguncha) |
| Terminaldagi xodim | PIN yoki `staff_code` + PIN, terminal tokeni bilan | `POST /devices/{uuid}/staff-session {user_id \| staff_code, pin}` | xodim sessiyasi (12 soat), terminalga bog'langan |

Muhim qoidalar:

- **Refresh bir vaqtda faqat bitta.** Ilova refresh so'rovini bir marta yuborishi kerak (single-flight). Sarflangan
  refresh token qayta kelsa, shu kirishdan boshlangan hamma tokenlar bekor bo'ladi (401, `refresh_reused`).
- **Terminal tokeni foydalanuvchi emas.** U faqat terminal endpointlariga (`GET /terminal`, `GET /terminal/staff`,
  `staff-session`) kiradi. Buyurtma va menyu bilan ishlash — xodim PIN sessiyasi tokeni bilan.
- **Xodim sessiyasi cheklangan.** U buyurtma va kassa ishi uchun: moliya, sozlamalar, xodimlar, ruxsatlar, PIN va
  rollar, qurilmalar, harakatlar jurnali, profil va telefon endpointlari uni 403 bilan rad etadi
  (`data.reason = staff_session_not_allowed`).
- **Chiqish:** `POST /auth/logout` (joriy sessiya), `POST /auth/logout-all` (hamma qurilmalar).
- **Hozirgi holat (P1-36 dan oldin):** SMS bilan kirish mijoz hisoblarini telefon bo'yicha topadi; xodim hisoblarida
  telefon faqat aloqa ma'lumoti. Xodimlarning SMS orqali kirishi **to'lqin 3 da o'zgarmoqda**.
- Filament panellari (`/admin`, `/superadmin`) API emas: ular email/telefon + parol bilan, cookie sessiyasida ishlaydi.

## 3. Ruxsatlar

Ikki qatlam:

1. **Rol** (`users.role`): `super_admin`, `owner`, `manager`, `cashier`, `waiter`, `cook`, `courier`, `customer`.
   Ba'zi marshrutlar hali rolni tekshiradi (`role:owner`, `role:owner,manager`).
2. **Ruxsat** (`App\Enums\Permission`, masalan `orders.cancel`, `menu.manage`, `pos.take_payment`): har bir rolning
   standart to'plami bor, ega uni do'kon bo'yicha o'zgartira oladi (`PUT /owner/permissions`). Ba'zi ruxsatlarni
   boshqa rolga berib bo'lmaydi (`orders.refund`, `finance.manage`, `settings.manage`, `zones.manage`,
   `staff.manage`, `audit.view`, `permissions.manage`). To'liq jadval: [AUTH.md](AUTH.md), 6-bo'lim.

Qo'shimcha cheklovlar:

- **Do'kon:** xodim faqat o'z do'koni ma'lumotini ko'radi; do'konsiz xodim hamma joyda 403 (`staff.store`).
- **Filial:** filialga biriktirilgan xodim (egadan boshqa) faqat o'z filialining buyurtma, qurilma, stop-list va
  partiyalari bilan ishlaydi. Begona yozuv 404 bo'ladi (403 emas: mavjudligi ham oshkor qilinmaydi).
- **Ilova uchun:** `GET /me/permissions` → `{role, store_id, branch_id, branch_scoped, permissions[], device_uuid}`.
  Ilova faqat ishlaydigan tugmalarni ko'rsatadi, lekin server baribir har bir amalni o'zi tekshiradi.
- **Hozirgi cheklov:** buyurtma API'si (`/orders/*`) hozircha ega, menejer, kuryer va mijoz uchun. Kassir, ofitsiant va
  oshpaz uchun buyurtma endpointlari POS bosqichida ochiladi ([POS_SPEC.md](POS_SPEC.md)).

## 4. Javob va xato formati

Muvaffaqiyatli javob:

```json
{"success": true, "message": "", "data": { ... }}
```

Xato (`ApiResponse::error`):

```json
{"success": false, "message": "O'zbekcha, foydalanuvchiga ko'rsatsa bo'ladigan matn", "data": {"reason": "pin_locked", "retry_after": 120}}
```

| HTTP | Qachon | Tanasi |
|---|---|---|
| 400 | noto'g'ri so'rov | `success: false`, `message` |
| 401 | token yo'q, eskirgan yoki bekor qilingan | Laravel: `{"message": "Unauthenticated."}`; auth endpointlarida `success: false` va `data.reason` |
| 403 | ruxsat yo'q | `{"success": false, "message": "Ruxsat etilmagan", "data": []}` yoki `data.reason` bilan |
| 404 | topilmadi yoki boshqa do'kon/filialniki | `success: false`, `message` |
| 409 | to'qnashuv (idempotency, hisoblarni birlashtirish) | `success: false`, `message`, ba'zan `data.reason` |
| 422 | validatsiya | Laravel: `{"message": "...", "errors": {"items.0.modifiers": ["..."]}}` |
| 422 | biznes qoida (holat o'tishi, ulash kodi va h.k.) | `success: false`, `message`, ba'zan `data.reason` |
| 429 | juda ko'p so'rov | `success: false`, `message` ("... so'ng qayta urinib ko'ring"), `Retry-After` sarlavhasi |
| 503 | tashqi xizmat ishlamayapti (geokodlash) | `success: false`, `message` |

**Mashina uchun sabablar (`data.reason`).** Ilova `message` matniga emas, shu kodga qarab qaror qiladi:

| `reason` | HTTP | Qayerda | Ma'nosi |
|---|---|---|---|
| `refresh_reused` | 401 | `/auth/refresh` | sarflangan refresh token qayta keldi, hamma sessiyalar bekor — qaytadan kirish |
| `refresh_invalid` | 401 | `/auth/refresh` | token noma'lum, chiqishda bekor qilingan yoki boshqa qurilma uuid'i bilan keldi (oxirgi holatda butun oila ham bekor) |
| `refresh_expired` | 401 | `/auth/refresh` | muddati tugagan |
| `pairing_invalid` | 422 | `/devices/pair` | kod noto'g'ri, ishlatilgan yoki eskirgan (hammasiga bir xil javob) |
| `pairing_limit` | 429 | `/owner/devices/pairing-codes` | bir vaqtdagi faol kodlar chegarasi (do'konda 5 ta) |
| `pin_invalid` | 422 | `staff-session` | xodim yoki PIN noto'g'ri (kim borligi oshkor qilinmaydi) |
| `pin_locked` | 429 | `staff-session` | ko'p xato; `data.retry_after` soniya va `Retry-After` |
| `staff_session_not_allowed` | 403 | moliya, sozlamalar, xodimlar va boshq. | terminaldagi xodim sessiyasi bilan mumkin emas |
| `store_required` | 403 | `/owner/permissions`, menyu xodim endpointlari | do'konsiz hisob (masalan superadmin) |
| `phone_linked_to_another_account` | 409 | `/phone/verify-code` | raqam boshqa mijozga tegishli, birlashtirib bo'lmaydi |
| `phone_linked_to_several_accounts` | 409 | `/auth/phone/verify` | bir raqamda bir nechta mijoz |
| `outside_zone` | 422 | `/delivery/quote` | nuqta hech qaysi hududda emas |
| `zones_not_configured` | 422 | `/delivery/quote` | do'konda faol hudud yo'q |
| `branch_closed` | 422 | `/delivery/quote` | hududga xizmat qiladigan filial yopiq |

P1-32 tugagach `outside_zone` va boshqa hudud sabablari `POST /orders` da ham qaytadi (**to'lqin 3 da o'zgarmoqda**).

## 5. Takroriy so'rov (Idempotency-Key)

Tarmoq uzilib, ilova bir so'rovni qayta yuborsa ikkinchi buyurtma yaratilmasligi uchun:

- `POST /orders` `Idempotency-Key: <1–255 ta ko'rinadigan belgi>` sarlavhasini qabul qiladi. Har bir checkout urinishi
  uchun yangi UUID yarating va qayta yuborishda **o'shasini** ishlating.
- Bir foydalanuvchi (yoki terminal) shu kalit va shu tana bilan 24 soat ichida qayta yuborsa — birinchi javob aynan
  qaytadi, sarlavha `Idempotent-Replayed: true`.
- Shu kalit **boshqa tana** bilan — 409. Bir vaqtda kelgan dublikat birinchisini kutadi (10 soniyadan oshsa 409).
- Muvaffaqiyatsiz so'rov (masalan 422) kalitni bo'shatadi: tuzatib, shu kalit bilan qayta yuborish mumkin.
- Boshqa endpointga ulash (backend): `->middleware(EnsureIdempotency::class . ':scope')`. POS'ning barcha o'zgartiruvchi
  so'rovlari shunday bo'ladi ([POS_SPEC.md](POS_SPEC.md)).
- Qo'shimcha himoya: `orders.external_id` do'kon va kanal ichida yagona (agregator yoki POS'ning o'z raqami).

Batafsil: [MENU.md](MENU.md), 8-bo'lim.

## 6. Sahifalash

Hozir uchta usul bor (endpointga qarab):

| Endpoint | Usul | Parametrlar va javob |
|---|---|---|
| `GET /orders?scope=history` | kursor (keyset) | `per_page` (1–50), `cursor`; javobda `meta.next_cursor` — `null` bo'lguncha qaytarib yuboring |
| `GET /orders?scope=active` | sahifasiz, eng ko'pi 200 | `meta.truncated: true` — yana bor |
| `GET /orders` (scope'siz) | sahifasiz, to'liq ro'yxat | eski ilova versiyalari uchun saqlangan; yangi ilovalar `scope` ishlatsin |
| `GET /owner/audit-logs`, `GET /owner/invoices` | Laravel sahifalari | `page`, `per_page` (1–100); `data` ichida `current_page`, `data`, `last_page`, `total`... |
| `GET /notifications` | Laravel sahifalari | 20 tadan |

Yangi ro'yxat endpointlari uchun tavsiya: o'sib boradigan ro'yxatda kursor (`meta.next_cursor`), kichik ro'yxatda
sahifasiz.

## 7. So'rovlar cheklovi (rate limit)

Umumiy: kirgan foydalanuvchiga daqiqasiga 300 ta (`api`). Oshsa 429 va `Retry-After`. Maxsus cheklovlar:

| Nima | Chegara |
|---|---|
| `POST /auth/telegram` | IP bo'yicha 300/daq, Telegram foydalanuvchi + IP bo'yicha 10/daq |
| SMS kod yuborish | raqam va foydalanuvchi bo'yicha 1/daq va 5/soat |
| SMS kodni tekshirish | 10/daq; bitta raqamga sutkasiga 30 urinish |
| `POST /auth/refresh` | IP bo'yicha 30/daq |
| `POST /devices/pair` | IP bo'yicha 10/daq, tarmoq (/24, /64) bo'yicha 30/daq |
| Ulash kodi yaratish | 5/daq, 30/soat |
| Terminal endpointlari | terminal bo'yicha 120/daq |
| PIN bilan kirish | terminal bo'yicha 10/daq, IP bo'yicha 60/daq, ustiga PIN bloklash ([AUTH.md](AUTH.md), 5-bo'lim) |
| `POST /orders` | 10/daq |
| Buyurtmani bekor qilish (mijoz) | 5/daq |
| Promo-kod | 20/daq |
| Shikoyat | 5/daq; baho — 10/daq |
| Hisobot eksporti | 3/daq, 20/kun |
| Geokodlash | 30/daq |
| Hududlar / narx so'rovi (ochiq) | IP bo'yicha 120/daq / 60/daq |
| Real-time obuna (`/broadcasting/auth`) | 60/daq |
| Qurilmalar, xodim PIN/rol, jurnal | 60/daq, 30/daq, 60/daq |

`/health` va webhooklar cheklovsiz.

## 8. Real-time kanallar va hodisalar

WebSocket: Laravel Reverb (Pusher protokoli). Ulanish: `wss://<domen>/app/<REVERB_APP_KEY>` (Pusher/Echo klient).
Private kanalga obuna `POST /api/broadcasting/auth` (Bearer token) bilan imzolanadi: mehmonga 401, ruxsatsizga 403.

| Kanal | Kim qo'shiladi | Hodisalar |
|---|---|---|
| `private-store.{storeId}` | ega, filialsiz menejer | `order.created`, `order.status_changed`, `order.assigned`, `menu.availability_changed` |
| `private-branch.{branchId}` | shu filial menejeri, ega | xuddi shu |
| `private-courier.{userId}` | kuryerning o'zi (o'rnini bosgan ega/menejer ham) | `order.status_changed`, `order.assigned` |
| `private-user.{userId}` | mijozning o'zi | `order.status_changed` |
| `menu.{storeId}` | ochiq, obunasiz | `menu.availability_changed` |

Hodisa tanalari:

```json
// order.created, order.status_changed, order.assigned
{"order": {"uuid": "...", "status": "preparing", "store_id": 3, "branch_id": 5, "courier_id": null, "updated_at": "..."}}

// menu.availability_changed
{"store_id": 3, "branch_id": 5, "reason": "stop_list", "items": [{"type": "variant", "id": 41, "available": false}], "changed_at": "..."}
```

Qoidalar:

- Hodisada shaxsiy ma'lumot yo'q. Ilova hodisani olgach buyurtmani (`GET /orders?scope=active`) yoki mavjudlikni
  (`GET /catalog/availability`) **API'dan qayta so'raydi**.
- Real-time kafolat emas: Reverb o'chiq bo'lishi yoki hodisa yo'qolishi mumkin. Ilova har doim so'rov (polling) bilan
  ham yangilanadi va qayta ulanganda ro'yxatni to'liq qayta yuklaydi.
- Kassir, ofitsiant, oshpaz va terminal tokeni hozir xodim kanallariga qo'shila olmaydi; POS uchun kanal
  [POS_SPEC.md](POS_SPEC.md) da taklif qilingan.

Sozlash: [DEPLOY.md](DEPLOY.md), 9-bo'lim.

## 9. Endpoint guruhlari

Barchasi `/api/v1` ostida. To'liq ro'yxat, parametrlar va javob shakllari — OpenAPI'da.

| Guruh | Endpointlar | Nima uchun |
|---|---|---|
| Kirish | `POST /auth/telegram`, `/auth/phone/send-code`, `/auth/phone/verify`, `/auth/refresh`, `/auth/logout`, `/auth/logout-all` | token olish, yangilash, chiqish |
| Profil | `GET /user`, `POST /user/profile`, `POST /phone/send-code`, `/phone/verify-code`, `GET /me/permissions` | joriy foydalanuvchi, telefonni tasdiqlash, ruxsatlar |
| Qurilmalar | `GET/POST /devices`, `DELETE /devices/{uuid}`, `PUT/DELETE /devices/{uuid}/push-token` | shaxsiy ilovani ro'yxatga olish, push token |
| Terminal | `POST /devices/pair`, `GET /terminal`, `GET /terminal/staff`, `POST/DELETE /devices/{uuid}/staff-session` | POS/KDS ulash va xodim PIN kirishi |
| Xabar sozlamalari | `GET/PUT /me/notification-preferences`, `GET /notifications`, `POST /notifications/read` | qaysi xabar qaysi kanaldan, ilova ichidagi xabarlar |
| Do'konlar va katalog | `GET /stores`, `GET /catalog`, `GET /catalog/availability`, `POST /stores/{id}/rate` | mijoz uchun do'konlar, menyu (kanal narxi bilan), mavjudlik, baho |
| Manzil va xarita | `GET/POST/PUT/DELETE /addresses`, `GET /locations`, `GET /geocode/reverse`, `/geocode/search` | mijoz manzillari, viloyat/tuman, geokodlash proksisi |
| Yetkazish hududlari | `GET /stores/{store}/zones`, `GET /delivery/quote` | ochiq: hududlar va yetkazish narxi |
| Buyurtmalar | `GET/POST /orders`, `POST /orders/{uuid}/status`, `/cancel`, `/complete`, `/assign-courier`, `GET /couriers` | yaratish (Idempotency-Key), ro'yxat, holat o'zgartirish, kuryer |
| Promo, shikoyat | `POST /promo/validate`, `GET/POST /complaints`, `/complaints/{id}/reply`, `/escalate` | promo-kodni oldindan ko'rish, shikoyatlar |
| Ega: menyu | `/owner/categories`, `/owner/products`, `/owner/modifier-groups`, `/owner/products/{id}/modifier-groups`, `/owner/products/{id}/channel-prices`, `/owner/branch-variants` | menyu, modifikatorlar, kanal narxlari, filial mavjudligi |
| Ega: oshxona va stop-list | `/owner/batches` (+ `/adjust`, `/close`), `/owner/stop-list` | qozon/partiya qoldig'i, stop-list |
| Ega: xodimlar va kirish | `/owner/employees` (+ `invite`, `transfer`), `/owner/staff/{id}/pin`, `/owner/staff/{id}/role`, `/owner/permissions`, `/owner/devices` (+ `pairing-codes`, `revoke`), `/owner/audit-logs` | xodimlar, PIN, rollar, ruxsatlar, terminallar, jurnal |
| Ega: moliya va sozlamalar | `/owner/finance`, `/owner/finance/top-up`, `/owner/payouts`, `/owner/settings` (+ `upload`), `/owner/service-fees`, `/owner/subscription`, `/owner/invoices` | balans, to'ldirish, pul yechish, sozlamalar, xizmat haqi, tarif |
| Ega: hisobot | `GET /owner/analytics`, `POST /reports/export` | ko'rsatkichlar, CSV hisobot (Telegram'ga) |
| Feature flag'lar | `GET /features` | do'kon bo'yicha yoqilgan imkoniyatlar |
| Superadmin | `/superadmin/dashboard`, `/superadmin/stores`, `/superadmin/locations` | Mini App'dagi superadmin ekranlari (asosiy boshqaruv Filament'da) |
| Webhooklar | `POST /payments/click/webhook`, `/payments/payme/webhook`, `/telegram/webhook` | serverdan serverga, imzo bilan |
| Xizmat | `GET /health` (va `/api/v1/health`), `POST /api/broadcasting/auth` | monitoring, real-time obuna |

## 10. OpenAPI spetsifikatsiyasi va klientlar

- Fayl: `packages/api-spec/openapi.json` (OpenAPI 3.1). Qo'lda tahrirlanmaydi: Scramble uni koddan (marshrutlar,
  validatsiya, modellar, controllerlardagi `#[...]` izohlar) yaratadi.
- Brauzerda: backend'da `/docs/api` (JSON: `/docs/api.json`). `local`/`testing` da ochiq, boshqa muhitda faqat
  superadmin (`API_DOCS_PUBLIC=true` hammaga ochadi).
- Klientlar: `packages/ts/api-client` (`@rizqim/api-client`: `openapi-typescript` turlari + `openapi-fetch`),
  `packages/dart/rizqim_api` (`swagger_parser` + Retrofit; kirish qatlami — terminal ulash, PIN, refresh — qo'lda
  yozilgan).

API o'zgarganda (backend'da), shu tartibda:

```bash
cd backend
php artisan migrate                      # model sxemalari jadval ustunlaridan olinadi
php artisan api-spec:export              # packages/api-spec/openapi.json
cd ../packages/ts/api-client && npm run generate
cd ../../.. && PATH=/opt/flutter/bin:$PATH PUB_CACHE=/opt/pub-cache bash packages/dart/rizqim_api/tool/generate.sh
```

(`PATH`/`PUB_CACHE` — Flutter shu yo'lda o'rnatilgan muhit uchun; o'z kompyuteringizda `flutter` PATH'da bo'lsa kerak
emas.) Uchala natijani ham commit qiling. CI `api-spec:export --check`, `npm run check:generated` va Dart klient
farqini tekshiradi: biri orqada qolsa, job yiqiladi.

Yangi endpoint yozganda: javob shaklini `#[ApiDocResponse(...)]`, so'rov parametrlarini `#[QueryParameter(...)]` bilan
izohlang, aks holda klientda tur `unknown` bo'lib qoladi.

## 11. Yangi endpoint uchun nazorat ro'yxati

- `/api/v1` ichida, mavjud javob shakllarini o'zgartirmasdan.
- `auth:sanctum` + `staff.store` + kerakli `permission:...`; pul, sozlama, xodim bo'lsa `not-staff-session`.
- Do'kon va filial bo'yicha ajratish controller'da, boshqa do'kon yozuvi uchun test (404).
- Pul — `App\Support\Money`; miqdor — `App\Support\Quantity`.
- O'zgartiruvchi va qayta yuborilishi mumkin bo'lgan so'rovda — `EnsureIdempotency`.
- Kerak bo'lsa alohida rate limiter (`AppServiceProvider` yoki `AccessServiceProvider`).
- Muhim harakat — `AuditLogger` orqali jurnalga.
- Oxirida spetsifikatsiya va ikkala klientni qayta yarating (10-bo'lim).
