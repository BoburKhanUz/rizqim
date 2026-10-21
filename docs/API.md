# Rizqim API: qanday foydalanish

Bu hujjat ilova yozuvchi uchun: API'ga qanday kirish, ruxsatlar qanday ishlashi, xatolar qanday keladi, takroriy
so'rov, sahifalash, cheklovlar va real-time. Har bir endpoint'ning to'liq tavsifi OpenAPI spetsifikatsiyasida
(10-bo'lim). Umumiy arxitektura: [ARCHITECTURE.md](ARCHITECTURE.md).

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
- **Xodimlar (P1-36):** `POST /auth/phone/verify` da `account=staff` faqat tasdiqlangan login telefoni bor xodim
  hisoblarini topadi; bir odam bir nechta do'konda bo'lsa `POST /auth/phone/select-account` bilan tanlaydi.
  Batafsil: [AUTH.md](AUTH.md).
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
- **Xodimlar uchun buyurtmalar (P2-11):** `/orders/*` avvalgidek ega, menejer, kuryer va mijoz uchun (ma'nosi va javob
  shakli o'zgarmagan). Ega, menejer, kassir, ofitsiant va oshpaz filial buyurtmalari bilan `/staff/orders*` va
  `/staff/kitchen/tickets` orqali ishlaydi (9-bo'lim, "Xodim buyurtmalari"). Kuryer va mijoz bu yo'llarga kira olmaydi
  (403). POS'ning buyurtma ochish, schyot va to'lov endpointlari 3-bosqichda ([POS_SPEC.md](POS_SPEC.md)).

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
| `branch_in_use` | 422 | `DELETE /owner/branches/{id}` | filialda buyurtmalar bor yoki u do'konning yagona filiali (P2-12) |
| `too_many_rows` | 422 | `/owner/reports/export` | davrda CSV uchun buyurtmalar juda ko'p (`data.limit`), qisqaroq davr tanlang (P2-12) |

`outside_zone` va boshqa hudud sabablari `POST /orders` da ham qaytadi (P1-32), narx taklifi bilan bir xil.

## 5. Takroriy so'rov (Idempotency-Key)

Tarmoq uzilib, ilova bir so'rovni qayta yuborsa ikkinchi buyurtma yaratilmasligi uchun:

- `POST /orders` `Idempotency-Key: <1–255 ta ko'rinadigan belgi>` sarlavhasini qabul qiladi. Har bir checkout urinishi
  uchun yangi UUID yarating va qayta yuborishda **o'shasini** ishlating.
- Bir foydalanuvchi (yoki terminal) shu kalit va shu tana bilan 24 soat ichida qayta yuborsa — birinchi javob aynan
  qaytadi, sarlavha `Idempotent-Replayed: true`.
- Shu kalit **boshqa tana** bilan — 409. Bir vaqtda kelgan dublikat birinchisini kutadi (10 soniyadan oshsa 409).
- Muvaffaqiyatsiz so'rov (masalan 422) kalitni bo'shatadi: tuzatib, shu kalit bilan qayta yuborish mumkin.
- Kalit buyurtma bilan bitta tranzaksiyada belgilanadi: buyurtma saqlanib, javob yozilmay qolgan bo'lsa ham qayta
  yuborish ikkinchi buyurtma yaratmaydi (409; ilova buyurtmalar ro'yxatini yangilaydi).
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
| `GET /staff/orders` (P2-11) | kursor (keyset) | `per_page` (1–100, standart 30), `cursor`; `meta.next_cursor`. Filtrlarni har sahifada bir xil yuboring |
| `GET /staff/kitchen/tickets` | sahifasiz, eng ko'pi 200 | `meta.truncated: true` — yana bor |
| `GET /owner/audit-logs`, `GET /owner/invoices` | Laravel sahifalari | `page`, `per_page` (1–100); `data` ichida `current_page`, `data`, `last_page`, `total`... |
| `GET /owner/finance/ledger`, `/owner/finance/wallet`, `/owner/payouts`, `/owner/payments` (P2-12) | Laravel sahifalari | xuddi shu, `per_page` standart 25 |
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
| CSV yuklab olish (`GET /owner/reports/export`, P2-12) | 6/daq, 100/kun (`report-csv`) |
| Geokodlash | 30/daq |
| Hududlar / narx so'rovi (ochiq) | IP bo'yicha 120/daq / 60/daq |
| Real-time obuna (`/broadcasting/auth`) | 60/daq |
| Qurilmalar, xodim PIN/rol, jurnal | 60/daq, 30/daq, 60/daq |

`/health` va webhooklar cheklovsiz.

## 8. Real-time kanallar va hodisalar

WebSocket: Laravel Reverb (Pusher protokoli). Ulanish: `wss://<domen>/app/<REVERB_APP_KEY>` (Pusher/Echo klient).
Private kanalga obuna `POST /api/broadcasting/auth` (Bearer token) bilan imzolanadi: mehmonga 401, ruxsatsizga 403.
Bu endpoint foydalanuvchi tokenidan tashqari **ulangan terminalning o'z tokenini** ham qabul qiladi (P2-11), lekin
terminal faqat `private-pos-branch.{branchId}` kanaliga qo'shila oladi (boshqa kanallarga 403).

| Kanal | Kim qo'shiladi | Hodisalar |
|---|---|---|
| `private-store.{storeId}` | ega, filialsiz menejer | `order.created`, `order.status_changed`, `order.assigned`, `menu.availability_changed` |
| `private-branch.{branchId}` | shu filial menejeri, ega | xuddi shu |
| `private-pos-branch.{branchId}` (P2-11) | filialda ishlaydigan hamma: shu filialda `orders.view` huquqi bor xodim (ega va filialsiz xodim — do'konning istalgan filiali; filialga biriktirilgan menejer, kassir, ofitsiant, oshpaz — o'z filiali; terminaldagi PIN sessiyasi — faqat terminal filiali) va shu filialning ulangan POS/KDS terminali (terminal tokeni bilan; filialsiz ulangan terminal — do'konning har bir filiali) | `order.created`, `order.status_changed`, `order.assigned`, `kitchen.item_status_changed`, `menu.availability_changed` |
| `private-courier.{userId}` | kuryerning o'zi (o'rnini bosgan ega/menejer ham) | `order.status_changed`, `order.assigned` |
| `private-user.{userId}` | mijozning o'zi | `order.status_changed` |
| `menu.{storeId}` | ochiq, obunasiz | `menu.availability_changed` |

Hodisa tanalari:

```json
// order.created, order.status_changed, order.assigned
{"order": {"uuid": "...", "status": "preparing", "store_id": 3, "branch_id": 5, "courier_id": null, "updated_at": "..."}}

// kitchen.item_status_changed (P2-11): oshpaz taom qatorini "tayyor" deb belgiladi yoki qaytardi
{"order": {"uuid": "...", "status": "preparing", "store_id": 3, "branch_id": 5, "courier_id": null, "updated_at": "..."},
 "item": {"id": 812, "kitchen_status": "ready", "ready_at": "..."}, "items_total": 3, "items_ready": 2}

// menu.availability_changed
{"store_id": 3, "branch_id": 5, "reason": "stop_list", "items": [{"type": "variant", "id": 41, "available": false}], "changed_at": "..."}
```

Buyurtma bekor qilinishi yoki rad etilishi alohida hodisa emas: `order.status_changed` (`status`: `canceled` yoki
`rejected`). Buyurtma hodisalari `branch_id` bor bo'lsa `store`, `branch` va `pos-branch` kanallariga, `branch_id`
siz eski buyurtmalar faqat `store` kanaliga boradi. Oshxona hodisasi ham xuddi shu kanallarga.

Qoidalar:

- Hodisada shaxsiy ma'lumot yo'q. Ilova hodisani olgach buyurtmani (`GET /orders?scope=active`) yoki mavjudlikni
  (`GET /catalog/availability`) **API'dan qayta so'raydi**.
- Real-time kafolat emas: Reverb o'chiq bo'lishi yoki hodisa yo'qolishi mumkin. Ilova har doim so'rov (polling) bilan
  ham yangilanadi va qayta ulanganda ro'yxatni to'liq qayta yuklaydi.
- Kassir, ofitsiant, oshpaz va terminallar `private-pos-branch.{branchId}` ga qo'shiladi (P2-11); `store`, `branch`,
  `courier`, `user` kanallari avvalgidek. Hodisada kim ko'rishidan qat'i nazar shaxsiy ma'lumot (ism, telefon, manzil,
  narx) yo'q: ilova `GET /staff/orders/{uuid}` yoki `GET /staff/kitchen/tickets` ni qayta so'raydi va server rolga qarab
  javob beradi (oshpaz telefon va manzilni ko'rmaydi).

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
| Xodim buyurtmalari (P2-11) | `GET /staff/orders`, `GET /staff/orders/{uuid}`, `POST /staff/orders/{uuid}/status` | filial doskasi (filtrlar, kursor), tafsilot, rolga qarab holat o'zgartirish |
| Oshxona ekrani (P2-11) | `GET /staff/kitchen/tickets`, `POST /staff/orders/{uuid}/items/{id}/ready`, `.../recall` | oshxona chiptalari, taom qatorini "tayyor" deb belgilash va qaytarish |
| Promo, shikoyat | `POST /promo/validate`, `GET/POST /complaints`, `/complaints/{id}/reply`, `/escalate` | promo-kodni oldindan ko'rish, shikoyatlar |
| Ega: menyu | `/owner/categories`, `/owner/products`, `/owner/modifier-groups`, `/owner/products/{id}/modifier-groups`, `/owner/products/{id}/channel-prices`, `/owner/branch-variants` | menyu, modifikatorlar, kanal narxlari, filial mavjudligi |
| Ega: oshxona va stop-list | `/owner/batches` (+ `/adjust`, `/close`), `/owner/stop-list` | qozon/partiya qoldig'i, stop-list |
| Ega: xodimlar va kirish | `/owner/employees` (+ `invite`, `transfer`), `/owner/staff/{id}/pin`, `/owner/staff/{id}/role`, `/owner/permissions`, `/owner/devices` (+ `pairing-codes`, `revoke`), `/owner/audit-logs` | xodimlar, PIN, rollar, ruxsatlar, terminallar, jurnal |
| Ega: moliya va sozlamalar | `/owner/finance`, `/owner/finance/top-up`, `/owner/payouts`, `/owner/settings` (+ `upload`), `/owner/service-fees`, `/owner/subscription`, `/owner/invoices` | balans, to'ldirish, pul yechish, sozlamalar, xizmat haqi, tarif |
| Ega: hisobot | `GET /owner/analytics`, `POST /reports/export` | ko'rsatkichlar, CSV hisobot (Telegram'ga) |
| Ega: boshqaruv (P2-12) | `/owner/branches`, `/owner/delivery-zones`, `/owner/staff`, `/owner/reports/sales`, `/owner/reports/top-products`, `/owner/reports/export`, `/owner/finance/ledger`, `/owner/finance/wallet`, `GET /owner/payouts`, `GET /owner/payments` | web panel uchun filiallar, hududlar, xodimlar, hisobotlar, moliya tarixi (12-bo'lim) |
| Feature flag'lar | `GET /features` | do'kon bo'yicha yoqilgan imkoniyatlar |
| Superadmin | `/superadmin/dashboard`, `/superadmin/stores`, `/superadmin/locations` | Mini App'dagi superadmin ekranlari (asosiy boshqaruv Filament'da) |
| Webhooklar | `POST /payments/click/webhook`, `/payments/payme/webhook`, `/telegram/webhook` | serverdan serverga, imzo bilan |
| Xizmat | `GET /health` (va `/api/v1/health`), `POST /api/broadcasting/auth` | monitoring, real-time obuna |

### 9.1. Xodim buyurtmalari va oshxona ekrani (P2-11)

Hamkor veb-paneli doskasi, Mini App xodim ekranlari va oshxona ekrani (KDS) uchun. Oddiy xodim kirishi (Mini App,
ilova, panel) yoki terminaldagi xodim PIN sessiyasi tokeni bilan ishlaydi; terminalning o'z tokeni bu endpointlarga
kirmaydi (401). Kim nimani ko'radi:

| Kim | Qaysi buyurtmalar |
|---|---|
| Ega | do'konning barcha filiallari |
| Menejer, kassir, ofitsiant, oshpaz (filialga biriktirilgan) | faqat o'z filiali |
| Filialsiz xodim | do'konning barcha filiallari |
| Filialga ulangan terminaldagi PIN sessiyasi | faqat shu terminal filiali (masalan, filialsiz oshpaz A filial KDS'ida faqat A ni ko'radi) |
| Kuryer, mijoz | kira olmaydi (403); `GET /orders` avvalgidek |

Boshqa do'kon yoki boshqa filial buyurtmasi — 404 (mavjudligi oshkor qilinmaydi). Filtrda (`branch_id`) o'zi ishlamaydigan
filial — 403, boshqa do'kon filiali — 404.

**`GET /staff/orders`** (`orders.view`) — yangidan eskiga, kursor bilan. Filtrlar: `branch_id`, `scope`
(`active` — jarayonda, `history` — yakunlangan, bekor qilingan, rad etilgan, qaytarilgan), `status` (vergul bilan:
`new,accepted`), `type` (`delivery`, `pickup`, `dine_in`), `channel`, `date` yoki `from`/`to` (do'kon vaqt
mintaqasidagi kunlar, `Y-m-d`, ko'pi bilan 93 kun), `q` (buyurtma raqamining boshi, 1–20 harf/raqam), `per_page`,
`cursor`. Noto'g'ri qiymat — 422.

**`GET /staff/orders/{uuid}`** (`orders.view`) — tafsilot: taomlar (modifikatorlar, miqdor va birlik — `quantity`,
`unit`, `quantity_label`: `"1.5 kg"`; narxlar; `kitchen_status`, `ready_at`), izoh, `customer`, `delivery`, `courier`,
summalar, vaqtlar (`created_at`, `accepted_at`, `preparing_at`, `ready_at`, `closed_at`) va `status_history`.
Ro'yxat elementida ham xuddi shu maydonlar bor (`status_history` dan tashqari).

**Kim nimani ko'radi (maydonlar).** Mijozning ismi va telefoni (`customer`), yetkazish manzili (`delivery`) va kuryer
(`courier`) faqat `orders.manage` huquqi borlarga (standart: ega, menejer, kassir, ofitsiant). Oshpazga (`orders.view` +
`kitchen.manage`) bu maydonlar `null`, izohdagi telefon raqami niqoblanadi (`+99890*****67`).

**`POST /staff/orders/{uuid}/status`** `{status, reason?, note?}` — `status`: `accepted`, `preparing`, `ready`,
`delivered`, `canceled`, `rejected`. Yangi holat qo'shilmagan, mavjud `OrderStatus` va uning o'tish qoidalari ishlatiladi.
Kim nima qila oladi:

| Kim | O'tishlar |
|---|---|
| `orders.manage` (ega, menejer, kassir, ofitsiant) | qabul qilish, tayyorlash, tayyor, topshirish (olib ketish va zal: `delivered`) |
| `kitchen.manage` (oshpaz; ega va menejerda ham bor) | faqat `accepted → preparing` va `preparing → ready` |
| `orders.cancel` (ega, menejer) | bekor qilish va rad etish (`reason` majburiy, `POST /orders/{uuid}/status` dagidek) |

Ruxsat yo'q — 403, holat mashinasi ruxsat bermasa — 422, xuddi shu holat — 200 (o'zgarishsiz). Kuryer biriktirish —
avvalgidek `POST /orders/{uuid}/assign-courier`. Buyurtma `ready` bo'lganda (qaysi yo'l bilan bo'lmasin: KDS, panel,
Telegram tugmasi) uning hamma qatorlari ham `ready` bo'ladi. Har o'zgarish holat tarixiga va harakatlar jurnaliga
(PIN sessiyasida terminal bilan) yoziladi, mijozga avvalgidek xabar boradi.

**`GET /staff/kitchen/tickets`** (`kitchen.manage` yoki `orders.view`) — oshxona chiptalari: `accepted` va
`preparing` buyurtmalar (`include_ready=1` bilan `ready` ham), eskisidan yangisiga, eng ko'pi 200. `branch_id` ixtiyoriy.
Chiptada: buyurtma raqami, turi, kanali, stol (`hall_ref`, `table_ref`), mehmonlar soni, izoh (telefon niqoblangan),
taomlar (modifikatorlar, miqdor/vazn, `kitchen_status`, `ready_at`; narxsiz), `items_total`, `items_ready`,
`created_at`, `accepted_at`, `preparing_at`, `ready_at` va `waiting_seconds` (qabul qilingandan — qabul qilinmagan
bo'lsa yaratilgandan — hozirgacha yoki tayyor bo'lgunicha). Mijoz ma'lumoti yo'q. Ekran vaqtni `accepted_at` dan o'zi
sanab turadi.

**`POST /staff/orders/{uuid}/items/{id}/ready`** (`kitchen.manage`) — taom qatori tayyor ("bump"). Faqat buyurtma
`accepted` yoki `preparing` bo'lganda (aks holda 422, `data.reason = kitchen_status`). Qabul qilingan buyurtmada birinchi
"tayyor" uni `preparing` ga o'tkazadi. **Oxirgi qator tayyor bo'lganda buyurtma o'zi `ready` bo'ladi** — faqat
yetkazish va olib ketish buyurtmalarida (mijoz yoki kuryer kutyapti). Zal buyurtmasi (`dine_in`) stol yopilguncha
`preparing` da qoladi ([POS_SPEC.md](POS_SPEC.md), 3-bo'lim); uni oshpaz xohlasa `status = ready` bilan o'zi belgilaydi.
Qayta yuborilsa — 200, o'zgarishsiz. **`.../recall`** — xato bosilgan "tayyor"ni qaytarish (qator yana `pending`),
faqat buyurtma hali `accepted`/`preparing` bo'lsa. Ikkalasi ham chiptani qaytaradi va `kitchen.item_status_changed`
hodisasini yuboradi. Boshqa buyurtma qatori — 404.

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

## 12. Boshqaruv API xaritasi (P2-12)

Hamkor web paneli (`partner/web`, 2-bosqich, to'lqin 2) ega va menejer bugun Filament do'kon panelida va Mini App'ning ega
ekranlarida qiladigan hamma ishni API orqali qilishi kerak. Quyida har bir imkoniyat qaysi endpointga to'g'ri kelishi.
"Yangi" — P2-12 da qo'shilgan; "bor" — oldin ham bor edi; "faqat Filament" — nima uchun API'ga chiqmagani pastda.
Hamma yangi endpointlar: `auth:sanctum` + `staff.store` + ruxsat, do'kon va filial bo'yicha ajratilgan (boshqa do'konniki
— 404, o'z do'konidagi begona filial — 403), id'lar 18 xonagacha (`RowId`), xato xabarlari o'zbekcha, pul — ikki xonali
satr (`"12000.00"`), sanalar do'kon vaqt mintaqasida (`stores.timezone`, standart `Asia/Tashkent`).

### Xarita

| Ekran (Filament / Mini App) | Imkoniyat | API | Ruxsat | Holat |
|---|---|---|---|---|
| Buyurtmalar / Mini App menejer ekrani | ro'yxat, tafsilot, holat, bekor qilish, kuryer | `GET /orders`, `/staff/orders*` (P2-11), `POST /orders/{uuid}/status`, `/cancel`, `/assign-courier`, `GET /couriers` | `orders.*` | bor (P2-11) |
| Buyurtmalar | pulni qaytarish | — | `orders.refund` (faqat superadmin) | faqat Filament (superadmin) |
| Mahsulotlar / Mini App menyu | taom, variant, vazn, rasm, faol/nofaol | `GET/POST /owner/products`, `POST/DELETE /owner/products/{id}` | ega | bor; menyudagi o'rni (`sort_order`) — yangi |
| Mahsulotlar | kategoriyalar | `GET /owner/categories` (yaratish — superadmin) | ega | bor |
| Modifikator guruhlari | guruh, modifikator, taomga ulash | `/owner/modifier-groups*`, `PUT /owner/products/{id}/modifier-groups` | `menu.manage` | bor |
| Mahsulotlar | kanal narxlari | `GET/PUT /owner/products/{id}/channel-prices` | `menu.manage` | bor |
| Stop-list | qo'shish, olib tashlash | `/owner/stop-list*` | `stoplist.manage` | bor |
| Qozon qoldig'i | ochish, to'ldirish, yopish | `/owner/batches*` | `batches.manage` | bor |
| Filiallar | ro'yxat | `GET /owner/branches`, `GET /owner/branches/{id}` | buyurtma, menyu, xodim yoki hisobot ruxsati | yangi |
| Filiallar | yaratish, o'chirish | `POST /owner/branches`, `DELETE /owner/branches/{id}` | `settings.manage` (ega) | yangi |
| Filiallar | manzil, joylashuv, radius, ish vaqti, ochiq/yopiq | `PATCH /owner/branches/{id}` | `branches.manage` (ega, menejer — o'z filiali) | yangi |
| Yetkazib berish hududlari | ro'yxat, yaratish, tahrirlash, o'chirish | `GET/POST /owner/delivery-zones`, `GET/PATCH/DELETE /owner/delivery-zones/{id}` | `zones.manage` (faqat ega) | yangi |
| Xodimlar / Mini App xodimlar | ro'yxat | `GET /owner/staff` (ega va menejer) ; `GET /owner/employees` (ega, eski shakl) | `staff.view` | yangi / bor |
| Xodimlar | qo'shish (kirish telefoni bilan) | `POST /owner/staff`; taklif havolasi `POST /owner/employees/invite` | `staff.view` + rol darajasi | yangi / bor |
| Xodimlar | ism, filial, kirish telefoni | `PATCH /owner/staff/{id}` | `staff.view` + rol darajasi | yangi |
| Xodimlar | filialga o'tkazish, o'chirish | `POST /owner/employees/{id}/transfer`, `DELETE /owner/employees/{id}` | ega | bor |
| Xodimlar | rol, PIN, "Mening PIN kodim" | `PUT /owner/staff/{id}/role`, `PUT/DELETE /owner/staff/{id}/pin` | `staff.manage`, `staff.pin` | bor |
| Ruxsatlar | jadval, o'zgartirish | `GET/PUT /owner/permissions` | `permissions.manage` | bor |
| Qurilmalar | ro'yxat, nom, o'chirish, ulash kodi | `/owner/devices*` | `devices.view`, `devices.manage` | bor |
| Harakatlar jurnali | o'qish, filtrlar | `GET /owner/audit-logs` | `audit.view` (ega) | bor; id chegarasi, `DeliveryZone` filtri, javob hujjatlandi |
| Analitika / Mini App analitika | qisqa ko'rsatkichlar | `GET /owner/analytics` | `reports.view` | bor |
| Analitika | davr bo'yicha savdo, kanal, filial, to'lov usuli, kunlik/haftalik/oylik qator | `GET /owner/reports/sales` | `reports.view` | yangi |
| Analitika | eng ko'p sotilgan taomlar | `GET /owner/reports/top-products` | `reports.view` | yangi |
| Mini App hisobotlar | CSV Telegram'ga | `POST /reports/export` | ega, menejer | bor |
| — | CSV yuklab olish (buyurtmalar, savdo, taomlar) | `GET /owner/reports/export` | `reports.view` | yangi |
| To'lovlar | mijozlarning onlayn to'lovlari | `GET /owner/payments` | `finance.view` | yangi |
| Moliya / Mini App to'lovlar | balans, oxirgi 20 ta yozuv | `GET /owner/finance` | ega | bor |
| Moliya | qarz hisobi, hamyon tarixi (sahifalab) | `GET /owner/finance/ledger`, `GET /owner/finance/wallet` | `finance.view` | yangi |
| Moliya | pul yechish so'rovlari tarixi | `GET /owner/payouts` | `finance.view` | yangi |
| Moliya | pul yechish, balansni to'ldirish | `POST /owner/payouts`, `POST /owner/finance/top-up` | ega | bor |
| Tarif va hisob-fakturalar | tarif, hisob-fakturalar | `GET /owner/subscription`, `GET /owner/invoices` | `finance.view` | bor |
| Sozlamalar / Mini App sozlamalar | logo, banner, tavsif, manzil, telefon, ochiq/yopiq, minimal summa, ish vaqti, to'lov usullari, yetkazish/olib ketish | `GET/POST /owner/settings`, `POST /owner/settings/upload` | ega | bor; do'kon nomi (`name`) va vaqt mintaqasi (`timezone`) — yangi |
| Sozlamalar | xizmat haqi va joy narxi | `GET/PUT /owner/service-fees` | `settings.manage` | bor |

### Faqat Filament'da qoladiganlar va sababi

- **Pulni qaytarish (refund), kategoriya yaratish, kuryer turi `fleet`** — superadmin ishi (Rizqim kuryerlarini platforma
  biriktiradi), hamkor paneliga kerak emas.
- **`telegram_admin_chat_id` (buyurtmalar guruhi)** — guruh botdagi `?start=store_<uuid>` havolasi bilan ulanadi; raqamni
  qo'lda yozish superadmin yordami uchun. API'da ochilsa, begona chatga buyurtma yuborish xavfi bor.
- **Dashboard vidjetlari** (soatlik grafiklar, "war room", jonli lenta), **Inventar** va **Staff** sahifalari — navigatsiyada
  yashirin yoki bezak uchun (bo'sh bazada namunaviy raqam ko'rsatadi), biznes qoidasi yo'q. Web panel ko'rsatkichlarni
  `/owner/reports/*` va `/staff/orders` dan oladi; haqiqiy qoldiq — variant `stock` va `/owner/batches`.
- **Buyurtmasi bor filialni o'chirish** — Filament ruxsat beradi, API rad etadi (`branch_in_use`): buyurtmalar va hisobotlar
  filialsiz qolmasin. Bunday filial "faol emas" qilinadi.

### Yangi endpointlar qisqacha

**Yetkazib berish hududlari** (`zones.manage`, terminal PIN sessiyasidan emas). Qoidalar panel tahrirlovchisi bilan bir
xil ([ZONES.md](ZONES.md)): `polygon` — GeoJSON `Polygon`/`MultiPolygon` (yoki `Feature`, bitta hududli
`FeatureCollection`, obyekt yoki JSON satr), ko'pi bilan 20 ko'pburchak va 500 nuqta, yopiq halqa, maydoni noldan katta.
Saqlashda tozalanadi, chegara to'rtburchagi (`bbox`) va maydon (`area_km2`) qayta hisoblanadi. `branch_id` — do'konning
faol filiali yoki `null`. Narxlar 0–99 999 999, ko'pi bilan 2 kasr; `priority` −1000..1000; `eta_minutes` 1–600. Har
o'zgarish jurnalga (`delivery_zone.created/updated/deleted`; shakl o'zgarsa `polygon_changed`). Ro'yxat qisqa — sahifasiz.

**Filiallar.** Maydonlar: `name`, `phone`, `address`, `region_id`, `district_id` (shu viloyatniki), `latitude`,
`longitude`, `radius_km` (0–100), `open_time`/`close_time` (`"HH:MM"`, ikkalasi birga yoki ikkalasi ham bo'sh),
`status` (`active`, `inactive`, `temporarily_closed`), `is_active`. Ish vaqti — kunda bitta oraliq (panel va
`ZoneResolver` kabi): vaqt yo'q yoki teng — sutka bo'yi; yopilish ochilishdan oldin — tungi. Javobda `working_hours
{open, close, all_day, overnight, timezone}` va `is_open_now` (yetkazib berish hozir shu filialni ochiq deb biladimi).
O'chirish rad etiladi (422, `branch_in_use`): filialda buyurtma bo'lsa yoki do'konning yagona filiali bo'lsa.
Jurnal: `branch.created/updated/deleted`.

**Hisobotlar** (`reports.view`). `from`, `to` — `YYYY-MM-DD`, ikkalasi kiradi, do'kon vaqt mintaqasidagi kunlar,
ko'pi bilan 366 kun. Buyurtma joylashtirilgan kuni hisoblanadi; tushum — yetkazilgan buyurtmalar summasi (Mini App
analitikasi bilan bir xil raqam). Filialga biriktirilgan menejer doim o'z filialini oladi.
- `GET /owner/reports/sales?from&to&branch_id&group_by=day|week|month` → `summary`, `series`, `by_channel`,
  `by_branch`, `by_payment_method`, `by_order_type`, `by_status`.
- `GET /owner/reports/top-products?from&to&branch_id&limit=1..50&sort=quantity|revenue`.
- `GET /owner/reports/export?report=orders|sales|products&from&to&...` — `text/csv; charset=UTF-8` (BOM bilan), oqim
  bilan yuboriladi. `orders` — ko'pi bilan 93 kun va 50 000 buyurtma (oshsa 422, `too_many_rows`); `sales`,
  `products` — 366 kun. Formula sifatida o'qilishi mumkin bo'lgan katak (`=`, `+`, `-`, `@` bilan boshlanadi) oldiga `'`
  qo'yiladi. Mijoz telefoni CSV'ga yozilmaydi.

**Moliya tarixi** (`finance.view`, terminal PIN sessiyasidan emas; Laravel sahifalari, `per_page` 1–100, standart 25;
`from`/`to` do'kon vaqtida):
- `GET /owner/finance/ledger?type=` — qarz hisobi (`store_transactions`), musbat summa qarzni oshiradi.
- `GET /owner/finance/wallet?type=` — hamyon (`store_wallet_transactions`).
- `GET /owner/payouts?status=pending|completed|rejected` — karta raqami niqoblangan (`8600 **** **** 9012`), izohlardagi
  karta raqamlari ham.
- `GET /owner/payments?status=&provider=&branch_id=` — onlayn to'lovlar (provayder tranzaksiya raqami bilan, xom
  payload'siz). Filialga biriktirilgan xodim — faqat o'z filiali.

**Xodimlar** (`staff.view`, terminal PIN sessiyasidan emas). Kim kimni boshqaradi — panelning xodimlar ekrani bilan bir
xil (`StaffAccountService`): ega — hamma xodimni, menejer — o'z filialidagi o'zidan past rollarni.
- `GET /owner/staff` — `login_phone_enabled`, `has_pin`, `staff_code`, `telegram_linked`, `invite_pending`, `can_edit`.
- `POST /owner/staff {name, role, branch_id, phone?}` — `phone` (`+998XXXXXXXXX`) kirish raqami bo'ladi (ega yoki
  menejer kafolati, [AUTH.md](AUTH.md)); javobda `invite_link`.
- `PATCH /owner/staff/{id} {name?, branch_id?, phone?}` — xodimning o'zi SMS bilan tasdiqlagan raqamni almashtirib yoki
  o'chirib bo'lmaydi (422).

**Sozlamalar.** `POST /owner/settings` ixtiyoriy `name` va `timezone` (masalan `Asia/Tashkent`) qabul qiladi; yuborilmasa
o'zgarmaydi. `GET /owner/settings` javobida `timezone`.
