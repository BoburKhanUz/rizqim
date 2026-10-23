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
  (Dart: `rizqim_core` `Money`, TS: `@rizqim/ui` formatlash). Narx va summani har doim server hisoblaydi. Yangi va
  P2-12 dan keyingi javoblarda — doim satr; eski javoblardagi son maydonlar (masalan `GET /owner/finance` balanslari)
  v1 qoidasiga ko'ra son bo'lib qoladi, OpenAPI'da aniq turi yozilgan (P2-30).
- **Karta raqami hech qachon qaytmaydi (P2-30):** pul yechish so'rovi qaysi javobda bo'lmasin (`POST /owner/payouts`,
  `GET /owner/payouts`, `GET /owner/finance`) karta niqoblangan (`card`: `"8600 **** **** 9012"`), izohlardagi karta
  raqamlari ham. To'liq raqam faqat bazada (platforma pulni o'tkazishi uchun).
- **Miqdor:** donali taomda butun son (`2`), kg/l da kasr (`1.5`, ko'pi bilan 3 xona).
- **Vaqt:** ISO 8601 (`2026-10-20T14:05:00+05:00`). Do'kon vaqt mintaqasi standart `Asia/Tashkent`. P2-33: sana
  filtrlari, hisobotlar, `GET /owner/analytics` va filial ish vaqti bir xil o'qiydi (`App\Support\StoreTimezone`):
  noma'lum mintaqa yozilgan bo'lsa — `Asia/Tashkent` (500 emas). Mini App analitikasi ham do'kon mintaqasidagi kunlarni
  sanaydi (`period.timezone`), shuning uchun `/owner/reports/sales` bilan bir xil raqam.
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
   Ba'zi marshrutlar hali rolni tekshiradi (`role:owner`, `role:owner,manager`): pul yechish va to'ldirish, sozlamalar,
   `/owner/employees`, filial variantlari. P2-30 dan menyu (`/owner/products*`, `GET /owner/categories`: `menu.view` —
   o'qish, `menu.manage` — o'zgartirish), kuryerlar (`GET /couriers`, `POST /orders/{uuid}/assign-courier`:
   `orders.assign_courier`) va `GET /owner/finance` (`finance.view`) rol emas, ruxsat bo'yicha.
2. **Ruxsat** (`App\Enums\Permission`, masalan `orders.cancel`, `menu.manage`, `pos.take_payment`): har bir rolning
   standart to'plami bor, ega uni do'kon bo'yicha o'zgartira oladi (`PUT /owner/permissions`). Ba'zi ruxsatlarni
   boshqa rolga berib bo'lmaydi (`orders.refund`, `finance.manage`, `settings.manage`, `zones.manage`,
   `staff.manage`, `audit.view`, `permissions.manage`). To'liq jadval: [AUTH.md](AUTH.md), 6-bo'lim.

Qo'shimcha cheklovlar:

- **Do'kon:** xodim faqat o'z do'koni ma'lumotini ko'radi; do'konsiz xodim hamma joyda 403 (`staff.store`).
- **Filial:** filialga biriktirilgan xodim (egadan boshqa) faqat o'z filialining buyurtma, qurilma, stop-list va
  partiyalari bilan ishlaydi. Begona yozuv 404 bo'ladi (403 emas: mavjudligi ham oshkor qilinmaydi). P2-33: filialga
  ulangan terminaldagi PIN sessiyasi (ega va filialsiz xodim ham) hamma endpointda terminal filiali bilan ishlaydi —
  eski `/orders/*`, hisobotlar, CSV va analitika ham; terminali topilmaydigan sessiya hech narsa qila olmaydi
  ([AUTH.md](AUTH.md), 5-bo'lim).
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
| 422 | validatsiya | `{"success": false, "message": "...", "errors": {"items.0.modifiers": ["..."]}, "data": {"reason": "validation", "errors": {...}}}` — xabarlar o'zbekcha (pastda). P3-26 gacha faqat `message` va `errors` edi; ular o'z joyida qoldi |
| 422 | biznes qoida (holat o'tishi, ulash kodi va h.k.) | `success: false`, `message`, ba'zan `data.reason` |
| 429 | juda ko'p so'rov | `success: false`, `message` ("... so'ng qayta urinib ko'ring"), `Retry-After` sarlavhasi |
| 503 | tashqi xizmat ishlamayapti (geokodlash) | `success: false`, `message` |

**Validatsiya xabarlari o'zbekcha (P2-30).** API so'rovlarida Laravel'ning qoida xabarlari ham (`required`, `max`,
`image`...) `backend/lang/uz` dan keladi: `{"message": "Nomi majburiy. (va yana 1 ta xato)", "errors": {"name":
["Nomi majburiy."]}}`. Maydon nomlari `lang/uz/validation.php` dagi `attributes` dan (`category_id` → "Kategoriya").
Endpointning o'z xabari bo'lsa, o'shanisi. Bu `APP_LOCALE` ga bog'liq emas (Filament paneli o'z tilida qoladi):
`API_LOCALE` (standart `uz`, `App\Http\Middleware\SetApiLocale`). 401 `{"message": "Unauthenticated."}` o'zgarmagan.
Ilova xabarni ko'rsatishi mumkin, qaror esa `data.reason` va HTTP kod bo'yicha.

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
| `branch_in_use` | 422 | `DELETE /owner/branches/{id}` | filialda buyurtmalar bor yoki u do'konning yagona filiali (P2-12); P2-33: filialga xodim, faol qurilma yoki hudud bog'langan (`data.staff`, `data.devices`, `data.zones` — soni) |
| `too_many_rows` | 422 | `/owner/reports/export` | davrda CSV uchun buyurtmalar juda ko'p (`data.limit`), qisqaroq davr tanlang (P2-12) |
| `kitchen_status` | 422 | `/staff/orders/{uuid}/items/{id}/ready`, `.../recall` | buyurtma oshxonada emas (P2-11): yangi, yo'lda, topshirilgan yoki bekor qilingan (P2-33: kuryer biriktirilgan buyurtmada qatorlar bilan ishlash mumkin) |
| `courier_required` | 422 | `POST /staff/orders/{uuid}/status` (`on_the_way`) | buyurtmaga hali kuryer biriktirilmagan (P2-30) |
| `validation` | 422 | `/api/*` dagi har qanday so'rov | P3-26: maydon xatosi; `data.errors` (va yuqoridagi `errors`) — maydon → xabarlar |

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
- **OpenAPI (P3-26).** `EnsureIdempotency` ortidagi har bir endpointda `Idempotency-Key` sarlavhasi spetsifikatsiyada
  bor (`App\OpenApi\IdempotencyKeyHeader`), shuning uchun TypeScript va Dart klientlari uni beradi. `pos.*` doirali
  endpointlarda u **majburiy** (yo'q bo'lsa 428 `idempotency_key_required`), `EnsureIdempotency:pos.x,optional` bo'lsa
  ixtiyoriy (`POST /pos/approvals`, `POST /pos/bills/{uuid}/fiscal-retry`), qolganlarida ixtiyoriy (yuborilsa
  hisobga olinadi). Doiralar: avans olish `pos.bookings.deposit`, qarz to'lovi `pos.accounts.repay`.

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
| `GET /owner/finance/ledger`, `/owner/finance/wallet`, `/owner/payouts`, `/owner/payments` (P2-12) | Laravel sahifalari | xuddi shu, `per_page` standart 25; `page` OpenAPI'da ham bor (P2-30) |
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
| `private-pos-branch.{branchId}` (P2-11) | filialda ishlaydigan hamma: shu filialda `orders.view` huquqi bor xodim (ega va filialsiz xodim — do'konning istalgan filiali; filialga biriktirilgan menejer, kassir, ofitsiant, oshpaz — o'z filiali; terminaldagi PIN sessiyasi — faqat terminal filiali) va shu filialning ulangan POS/KDS terminali (terminal tokeni bilan; filialsiz ulangan terminal — do'konning har bir filiali) | `order.created`, `order.status_changed`, `order.assigned`, `kitchen.item_status_changed`, `menu.availability_changed`; POS hodisalari (P3-12, P3-17, P3-18) va P3-26 `order.bill_requested` |
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

// order.bill_requested (P3-26): ofitsiant kassadan schyot so'radi (POST /pos/orders/{uuid}/request-bill)
{"order": {"uuid": "...", "status": "preparing", "store_id": 3, "branch_id": 5, "courier_id": null, "updated_at": "..."},
 "version": 7, "table_id": 12, "bill_requested_at": "...", "requested_by": 41}

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
| Terminal | `POST /devices/pair`, `GET /terminal`, `GET /terminal/staff`, `POST/DELETE /devices/{uuid}/staff-session`, `DELETE /terminal` | POS/KDS ulash va xodim PIN kirishi; `GET /terminal` da `branches` — terminal obuna bo'la oladigan filiallar, `GET /terminal/staff?role=cook,manager` — rol bo'yicha (P2-30); `DELETE /terminal` — terminal o'z tokeni bilan o'zini uzadi (P2-33, [AUTH.md](AUTH.md) 4-bo'lim) |
| Xabar sozlamalari | `GET/PUT /me/notification-preferences`, `GET /notifications`, `POST /notifications/read` | qaysi xabar qaysi kanaldan, ilova ichidagi xabarlar |
| Do'konlar va katalog | `GET /stores`, `GET /catalog`, `GET /catalog/availability`, `POST /stores/{id}/rate` | mijoz uchun do'konlar, menyu (kanal narxi bilan), mavjudlik, baho |
| Manzil va xarita | `GET/POST/PUT/DELETE /addresses`, `GET /locations`, `GET /geocode/reverse`, `/geocode/search` | mijoz manzillari, viloyat/tuman, geokodlash proksisi |
| Yetkazish hududlari | `GET /stores/{store}/zones`, `GET /delivery/quote` | ochiq: hududlar va yetkazish narxi |
| Buyurtmalar | `GET/POST /orders`, `POST /orders/{uuid}/status`, `/cancel`, `/complete`, `/assign-courier`, `GET /couriers` | yaratish (Idempotency-Key), ro'yxat, holat o'zgartirish, kuryer |
| Xodim buyurtmalari (P2-11) | `GET /staff/orders`, `GET /staff/orders/{uuid}`, `POST /staff/orders/{uuid}/status` | filial doskasi (filtrlar, kursor), tafsilot, rolga qarab holat o'zgartirish |
| Oshxona ekrani (P2-11) | `GET /staff/kitchen/tickets`, `POST /staff/orders/{uuid}/items/{id}/ready`, `.../recall` | oshxona chiptalari, taom qatorini "tayyor" deb belgilash va qaytarish |
| Promo, shikoyat | `POST /promo/validate`, `GET/POST /complaints`, `/complaints/{id}/reply`, `/escalate` | promo-kodni oldindan ko'rish, shikoyatlar |
| Ega: menyu | `/owner/categories`, `/owner/products`, `DELETE /owner/products/{id}/image`, `/owner/modifier-groups`, `/owner/products/{id}/modifier-groups`, `/owner/products/{id}/channel-prices`, `/owner/branch-variants` | menyu, rasm, modifikatorlar, kanal narxlari, filial mavjudligi |
| Ega: oshxona va stop-list | `/owner/batches` (+ `/adjust`, `/close`), `/owner/stop-list` | qozon/partiya qoldig'i, stop-list |
| Ega: xodimlar va kirish | `/owner/employees` (+ `invite`, `transfer`), `/owner/staff/{id}/pin`, `/owner/staff/{id}/role`, `/owner/permissions`, `/owner/devices` (+ `pairing-codes`, `revoke`), `/owner/audit-logs` | xodimlar, PIN, rollar, ruxsatlar, terminallar, jurnal |
| Ega: moliya va sozlamalar | `/owner/finance`, `/owner/finance/top-up`, `/owner/payouts`, `/owner/settings` (+ `upload`), `/owner/service-fees`, `/owner/subscription`, `/owner/invoices` | balans, to'ldirish, pul yechish, sozlamalar, xizmat haqi, tarif |
| Ega: hisobot | `GET /owner/analytics`, `POST /reports/export` | ko'rsatkichlar, CSV hisobot (Telegram'ga) |
| Ega: boshqaruv (P2-12) | `/owner/branches`, `/owner/delivery-zones`, `/owner/staff`, `/owner/reports/sales`, `/owner/reports/top-products`, `/owner/reports/export`, `/owner/finance/ledger`, `/owner/finance/wallet`, `GET /owner/payouts`, `GET /owner/payments` | web panel uchun filiallar, hududlar, xodimlar, hisobotlar, moliya tarixi (12-bo'lim) |
| POS sozlamalari (P3-13) | `GET /pos/bootstrap`, `GET /catalog?channel=pos_hall\|pos_takeaway`, `/owner/kitchen-stations`, `/owner/kitchen-station-routes`, `/owner/printers`, `/owner/branches/{id}/pos-settings` | terminal ishga tushishi, POS menyusi, sexlar va yo'naltirish, printerlar, filial POS sozlamalari ("POS sozlamalari (P3-13)" bo'limi) |
| Ega: fiskal (P3-11) | `/owner/fiscal/settings`, `GET /owner/fiscal/readiness`, `GET /owner/fiscal/receipts`, `/owner/fiscal/receipts/{uuid}` (+ `retry`) | STIR, soliq rejimi, fiskal drayver, MXIK tayyorligi, cheklar va qayta yuborish ("Fiskal cheklar" bo'limi) |
| POS: bron va nasiya (P3-17) | `/pos/bookings` (+ `calendar`, `{uuid}/confirm`, `/cancel`, `/no-show`, `/arrive`, `/items`, `/deposits`, `.../refund`, `.../retain`), `/pos/customer-accounts` (+ `debtors`, `{uuid}/statement`, `/repayments`, `/adjustments`) | bron taqvimi va to'qnashuv, oldindan buyurtma, avans (bo'nak cheki), "Keldi", nasiya hisoblari va qarz to'lovi (kredit cheki) ("Bron, avans va nasiya (P3-17)" bo'limi) |
| POS: schyot, to'lov va smena (P3-18) | `/pos/orders/{uuid}/bills`, `/pos/bills/{uuid}/print`, `/pos/bills/{uuid}/payments`, `/pos/bills/{uuid}/fiscal-retry`, `POST /pos/orders/{uuid}/close`, `POST /pos/refunds`, `/pos/shifts` (+ `current`, `{uuid}`, `/x-report`, `/cash-movements`, `/close`), `POST /pos/approvals` | schyot va bo'lish, aralash to'lov, sotuv cheki, smena ichida qaytarish, smena, X va Z, menejer PIN tasdig'i ("Schyot, to'lov va smena (P3-18)" bo'limi) |
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
Ro'yxat elementida ham xuddi shu maydonlar bor (`status_history` dan tashqari). P2-30: `waiting_seconds` — oshxona
chiptasidagi qoida bilan (qabul qilingandan, qabul qilinmagan bo'lsa yaratilgandan; yangi va oshxonadagi buyurtmada
hozirgacha, keyin tayyor bo'lgunicha, tayyor bo'lmay yopilgan bo'lsa yopilgunicha). Doska ishlayotgan soatni
`accepted_at` (yoki `created_at`) dan o'zi sanaydi, to'xtaganini serverdan oladi.

**Kim nimani ko'radi (maydonlar).** Mijozning ismi va telefoni (`customer`), yetkazish manzili (`delivery`) va kuryer
(`courier`) faqat `orders.manage` huquqi borlarga (standart: ega, menejer, kassir, ofitsiant). Oshpazga (`orders.view` +
`kitchen.manage`) bu maydonlar `null`, izohdagi telefon raqami niqoblanadi (`+99890*****67`).

**`POST /staff/orders/{uuid}/status`** `{status, reason?, note?}` — `status`: `accepted`, `preparing`, `ready`,
`on_the_way` (P2-30), `delivered`, `canceled`, `rejected`. Yangi holat qo'shilmagan, mavjud `OrderStatus` va uning o'tish
qoidalari ishlatiladi.
Kim nima qila oladi:

| Kim | O'tishlar |
|---|---|
| `orders.manage` (ega, menejer, kassir, ofitsiant) | qabul qilish, tayyorlash, tayyor, topshirish (olib ketish va zal: `delivered`). P2-33: yetkazish buyurtmasini `delivered` qilish — faqat biriktirilgan kuryer yoki `orders.assign_courier` (ega, menejer) bilan; kassir va ofitsiantga 403 |
| `kitchen.manage` (oshpaz; ega va menejerda ham bor) | faqat `accepted → preparing` va `preparing → ready` |
| `orders.cancel` (ega, menejer) | bekor qilish va rad etish (`reason` majburiy, `POST /orders/{uuid}/status` dagidek) |
| `orders.assign_courier` (ega, menejer) | P2-30: biriktirilgan kuryer buyurtmani olib ketdi — `courier_assigned → on_the_way`. Kuryer biriktirilmagan buyurtma — 422 `courier_required` (avval `POST /orders/{uuid}/assign-courier`) |

Ruxsat yo'q — 403, holat mashinasi ruxsat bermasa — 422, xuddi shu holat — 200 (o'zgarishsiz). P2-33: buyurtma
oshxonadan chiqsa (`on_the_way`, `delivery`, `delivered`) uning hali `pending` qatorlari ham `ready` bo'ladi (taom
kuryer yoki mijoz bilan ketdi), doskadagi `items_ready` to'g'ri qoladi. Kuryer biriktirish —
avvalgidek `POST /orders/{uuid}/assign-courier` (P2-30 dan `orders.assign_courier` ruxsati bilan, rol emas). Doska
endi `on_the_way` ni ham shu endpoint orqali yuboradi; eski `POST /orders/{uuid}/status` ishlashda davom etadi. Buyurtma `ready` bo'lganda (qaysi yo'l bilan bo'lmasin: KDS, panel,
Telegram tugmasi) uning hamma qatorlari ham `ready` bo'ladi. Har o'zgarish holat tarixiga va harakatlar jurnaliga
(PIN sessiyasida terminal bilan) yoziladi, mijozga avvalgidek xabar boradi.

**`GET /staff/kitchen/tickets`** (`kitchen.manage` yoki `orders.view`) — oshxona chiptalari: `accepted` va
`preparing` buyurtmalar (`include_ready=1` bilan `ready` ham), eskisidan yangisiga, eng ko'pi 200. **P2-33: oshxona
qatorlar holatiga qarab ishlaydi.** Dispetcher taom hali pishayotganda kuryer biriktirsa (`preparing →
courier_assigned`), buyurtma chiptalarda qoladi — toki hech bo'lmasa bitta qatori `pending` ekan; `include_ready=1` bilan
har qanday `courier_assigned` buyurtma ham (kuryer hali olib ketmagan). `on_the_way` va undan keyingilari chiptada
yo'q: buyurtma yo'lga chiqqanda qolgan qatorlari yopiladi (`ready`), chunki taom kuryer bilan ketgan. `branch_id` ixtiyoriy.
Chiptada: buyurtma raqami, turi, kanali, stol (`hall_ref`, `table_ref`), mehmonlar soni, izoh (telefon niqoblangan),
taomlar (modifikatorlar, miqdor/vazn, `kitchen_status`, `ready_at`; narxsiz), `items_total`, `items_ready`,
`created_at`, `accepted_at`, `preparing_at`, `ready_at` va `waiting_seconds` (qabul qilingandan — qabul qilinmagan
bo'lsa yaratilgandan — hozirgacha yoki tayyor bo'lgunicha). Mijoz ma'lumoti yo'q. Ekran vaqtni `accepted_at` dan o'zi
sanab turadi.

**P3-26: bekor qilingan chiptalar.** `canceled_within` (daqiqa, 1–60) berilsa, shu vaqt ichida bekor qilingan yoki rad
etilgan va oshxonaga yetib borgan (qatori yuborilgan yoki onlayn buyurtma qabul qilingan) buyurtmalar ham jonli
chiptalardan keyin keladi: `status` (`canceled`/`rejected`), `canceled_at` va qatorlari o'z holicha (o'chirilganlari
`voided_at` bilan) — ekran ularni yo'qotmasdan ustidan chizadi. Jonli chiptada `canceled_at: null`. Jami 200 tadan
oshsa `meta.truncated: true`.

**`POST /staff/orders/{uuid}/items/{id}/ready`** (`kitchen.manage`) — taom qatori tayyor ("bump"). Faqat buyurtma
`accepted` yoki `preparing` bo'lganda (aks holda 422, `data.reason = kitchen_status`). **P3-26:** ushlab turilgan
(`fire_mode = hold`) va `due_at` vaqti bor oldindan buyurtma qatori (osh soatlab oldin pishiriladi) kassa yubormasdan
ham "tayyor" bo'la oladi: tayyor — yuborilgan degani, qator shu payt `sent_at` va sexini oladi, buyurtma `version` i
oshadi. `due_at` siz ushlab turilgan qator — avvalgidek 422 `kitchen_status`. Qabul qilingan buyurtmada birinchi
"tayyor" uni `preparing` ga o'tkazadi. **Oxirgi qator tayyor bo'lganda buyurtma o'zi `ready` bo'ladi** — faqat
yetkazish va olib ketish buyurtmalarida (mijoz yoki kuryer kutyapti). Zal buyurtmasi (`dine_in`) stol yopilguncha
`preparing` da qoladi ([POS_SPEC.md](POS_SPEC.md), 3-bo'lim); uni oshpaz xohlasa `status = ready` bilan o'zi belgilaydi.
Qayta yuborilsa — 200, o'zgarishsiz. **P2-33:** `courier_assigned` buyurtmada ham "tayyor" va qaytarish ishlaydi,
lekin holat o'zgarmaydi (oxirgi qator tayyor bo'lsa ham buyurtma `courier_assigned` da qoladi: kuryer kutyapti;
qaytarish ham `preparing` ga o'tkazmaydi). **`.../recall`** — xato bosilgan "tayyor"ni qaytarish (qator yana `pending`),
buyurtma `accepted`/`preparing` bo'lganda. **P2-30:** oxirgi "tayyor" buyurtmani o'zi `ready` qilgan bo'lsa ham
qaytarish mumkin (`kitchen.manage`), toki buyurtmaga kuryer biriktirilmagan, u yo'lga chiqmagan va topshirilmagan
ekan: qator `pending`, buyurtma yana `preparing` (holat tarixi, jurnal, `order.status_changed`); keyingi oxirgi
"tayyor" uni yana `ready` qiladi. `ready → preparing` faqat shu yo'l bilan: holat endpointlari uni qabul qilmaydi (422).
Yo'lda yoki yakunlangan buyurtma — 422 `kitchen_status` (P2-33 gacha kuryer biriktirilgani ham).

**Xabarlar (P2-33).** Buyurtma `ready` bo'lganda kuryerlarga (biriktirilgan kuryerga yoki filial kuryerlariga, ular
bo'lmasa menejer/egaga) xabar faqat **yetkazish** buyurtmasida boradi; olib ketish va zal buyurtmasida kuryer
chaqirilmaydi (mijozga avvalgidek xabar boradi). Oshxonaning o'z tuzatishi xabar emas: `ready` buyurtmadan qatorni
qaytarish (`ready → preparing`) va undan keyingi qayta `ready` (oxirgi "tayyor" yoki status tugmasi) mijozga ham,
kuryerga ham qayta xabar yubormaydi — ular "tayyor" ni bir marta eshitgan. Holat tarixi, jurnal va real-time hodisalar
(`order.status_changed`, `kitchen.item_status_changed`) avvalgidek; buyurtma topshirilgani (`delivered`) yana xabar. Ikkalasi ham chiptani qaytaradi va
`kitchen.item_status_changed` hodisasini yuboradi. Boshqa buyurtma qatori — 404.

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
| Buyurtmalar / Mini App menejer ekrani | ro'yxat, tafsilot, holat, bekor qilish, kuryer | `GET /orders`, `/staff/orders*` (P2-11), `POST /orders/{uuid}/status`, `/cancel`, `/assign-courier`, `GET /couriers` | `orders.*` (kuryer — `orders.assign_courier`, P2-30) | bor (P2-11) |
| Buyurtmalar | pulni qaytarish | — | `orders.refund` (faqat superadmin) | faqat Filament (superadmin) |
| Mahsulotlar / Mini App menyu | taom, variant, vazn, rasm, faol/nofaol | `GET/POST /owner/products`, `POST/DELETE /owner/products/{id}`, `DELETE /owner/products/{id}/image` (P2-30) | `menu.view` (o'qish), `menu.manage` (o'zgartirish; P2-30 gacha — faqat ega roli) | bor; menyudagi o'rni (`sort_order`) — yangi; `modifier_group_links`, yangi variant qoldig'i (`stock`), rasmni o'chirish — P2-30 |
| Mahsulotlar | kategoriyalar | `GET /owner/categories` (yaratish — superadmin) | `menu.view` | bor |
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
| Moliya / Mini App to'lovlar | balans, oxirgi 20 ta yozuv | `GET /owner/finance` | `finance.view` (P2-30 gacha — ega roli) | bor; pul yechish so'rovlari va izohlardagi karta niqoblangan |
| Moliya | qarz hisobi, hamyon tarixi (sahifalab) | `GET /owner/finance/ledger`, `GET /owner/finance/wallet` | `finance.view` | yangi |
| Moliya | pul yechish so'rovlari tarixi | `GET /owner/payouts` | `finance.view` | yangi |
| Moliya | pul yechish, balansni to'ldirish | `POST /owner/payouts`, `POST /owner/finance/top-up` | ega | bor; `POST /owner/payouts` javobi — `GET /owner/payouts` shakli, karta niqoblangan (P2-30) |
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
  filialsiz qolmasin. Bunday filial "faol emas" qilinadi. P2-33: xodim, faol qurilma yoki hudud bog'langan filialni
  ikkalasi ham rad etadi (Filament — xabar bilan, `BranchDeletionGuard`).

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
O'chirish rad etiladi (422, `branch_in_use`): filialda buyurtma bo'lsa yoki do'konning yagona filiali bo'lsa. P2-33:
filialga xodim, bekor qilinmagan qurilma (terminal) yoki yetkazib berish hududi bog'langan bo'lsa ham (aks holda ular
`branch_id = null` bo'lib, butun do'konga tegishli bo'lib qolardi): xabarda va `data` da soni — `{"reason":
"branch_in_use", "staff": 2, "devices": 1, "zones": 0}`. Avval xodimlarni boshqa filialga o'tkazing, qurilmalarni
bekor qiling, hududlarni boshqa filialga biriktiring yoki o'chiring.
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
  payload'siz). Filialga biriktirilgan xodim — faqat o'z filiali. P3-14: har qatorda `fiscal` (fiskal chek holati) va
  `?fiscal_status=` filtri — "Onlayn to'lov fiskal cheki (P3-14)" bo'limiga qarang.

**Xodimlar** (`staff.view`, terminal PIN sessiyasidan emas). Kim kimni boshqaradi — panelning xodimlar ekrani bilan bir
xil (`StaffAccountService`): ega — hamma xodimni, menejer — o'z filialidagi o'zidan past rollarni.
- `GET /owner/staff` — `login_phone_enabled`, `login_phone_proof` (P2-30: `sms` — xodimning o'zi kod bilan tasdiqlagan,
  formada o'zgartirib bo'lmaydi; `vouched` — ega, menejer yoki superadmin yozgan; kirish raqami bo'lmasa `null`),
  `has_pin`, `staff_code`, `telegram_linked`, `invite_pending`, `can_edit`.
- `POST /owner/staff {name, role, branch_id, phone?}` — `phone` (`+998XXXXXXXXX`) kirish raqami bo'ladi (ega yoki
  menejer kafolati, [AUTH.md](AUTH.md)); javobda `invite_link`.
- `PATCH /owner/staff/{id} {name?, branch_id?, phone?}` — xodimning o'zi SMS bilan tasdiqlagan raqamni almashtirib yoki
  o'chirib bo'lmaydi (422).

**Sozlamalar.** `POST /owner/settings` ixtiyoriy `name` va `timezone` (masalan `Asia/Tashkent`) qabul qiladi; yuborilmasa
o'zgarmaydi. `GET /owner/settings` javobida `timezone`. `payment_settings` — obyekt (provayder bo'yicha, yetkazish
narxi va kalitlari, ish vaqti), OpenAPI'da shunday yozilgan (P2-30).

**Do'kon vaqt mintaqasi xodimlar uchun (P2-30).** `GET /me/permissions` javobida `timezone` — do'konning vaqt mintaqasi
(`orders.view` yoki `reports.view` ruxsati bor xodimga; boshqalarga `null`). Doska "bugun"ni, hisobotlar kunlarni shu
bo'yicha sanaydi.

### P2-30: 2-to'lqinda topilgan kamchiliklar

Hammasi qo'shimcha (mavjud maydonlar o'chirilmagan, turi o'zgarmagan): yuqoridagi bo'limlarda belgilangan. Qisqacha:
karta raqami hech qaysi moliya javobida yo'q; menyu, kuryerlar va `GET /owner/finance` ruxsat bo'yicha; xodim
ro'yxatida `login_phone_proof`; buyurtma ro'yxatida `waiting_seconds`; `/me/permissions` da `timezone`;
`GET /owner/products` da `modifier_group_links` (va bog'lash ro'yxatini qoidalarsiz saqlash ularni o'chirmaydi);
`/owner/branch-variants` da `batch_tracked`; stop-listda `created_by_name` (`stoplist.manage` bilan);
`DELETE /owner/products/{id}/image`; xodim holat endpointida `on_the_way`; tayyor buyurtmadan taomni qaytarish;
`/terminal/staff?role=`, `GET /terminal` da `branches`; yangi variant `stock`i; validatsiya xabarlari o'zbekcha;
OpenAPI'dagi xatolar tuzatildi (`/owner/employees`, `/superadmin/*`, `/owner/settings`, `toggle_active`, payout
`status` enum, `page`, `/owner/modifier-groups` tanasi).

## POS sozlamalari (P3-13)

Choyxona kassasi, ofitsiant telefoni va oshxona ekrani ishga tushganda kerak bo'ladigan sozlamalar: sexlar, printerlar,
filial POS sozlamalari va POS menyusi ([POS_SPEC.md](POS_SPEC.md) 2.8, 2.11, 2.12, 10, 12). Hamma endpoint `auth:sanctum` +
`staff.store` + ruxsat, do'kon va filial bo'yicha ajratilgan (boshqa do'konniki — 404; filialga biriktirilgan xodim yoki
terminal sessiyasi boshqa filialni so'rasa — 403), id'lar 18 xonagacha, xato xabarlari o'zbekcha, pul — ikki xonali satr.
Qurilma ochiq API'da **uuid** bilan ko'rsatiladi (`device_id`, `printing_device_id`): raqamli id hech qachon chiqmaydi.

### Boshqaruv endpointlari (web panel)

`settings.manage` (faqat ega) va `not-staff-session`: terminaldagi PIN sessiyasi bularga kira olmaydi (403
`staff_session_not_allowed`). Filial `branch_id` bilan beriladi (filialga biriktirilgan chaqiruvchida — o'zinik).

| Endpoint | Vazifasi |
|---|---|
| `GET /owner/kitchen-stations?branch_id=` | sexlar (nofaollari ham) va `kinds` ro'yxati |
| `POST /owner/kitchen-stations` | sex qo'shish: `branch_id`, `name` (filialda yagona), `kind` (`osh`, `mangal`, `salad_bar`, `tea`, `kitchen`, `other`), `sort_order`, `is_active`, `show_on_kds` |
| `PATCH /owner/kitchen-stations/{id}` | tahrirlash (filial o'zgarmaydi). Nofaol sex terminalga berilmaydi, uning yo'naltirishlari hisobga olinmaydi |
| `DELETE /owner/kitchen-stations/{id}` | o'chirish: yo'naltirishlari o'chadi, printerlari sexsiz qoladi. Unga buyurtma qatori yuborilgan bo'lsa — 422 `station_in_use` (faolsizlantiring) |
| `GET /owner/kitchen-station-routes?branch_id=` | filialning yo'naltirishlari: `category_id` yoki `product_id`, `station_id`, nomi |
| `PUT /owner/kitchen-station-routes` | ommaviy biriktirish: `branch_id`, `station_id`, `category_ids[]` (≤200), `product_ids[]` (≤500). Boshqa sexga biriktirilgan bo'lsa — ko'chadi. `station_id: null` — ko'rsatilganlarning yo'naltirishini olib tashlaydi. Javob — filialning yo'naltirishlari |
| `DELETE /owner/kitchen-station-routes/{id}` | bitta yo'naltirishni olib tashlash |
| `GET/POST /owner/printers`, `PATCH/DELETE /owner/printers/{id}` | printerlar (quyida) |
| `GET/PUT /owner/branches/{id}/pos-settings` | biznes kun boshlanishi va `pos_settings` (quyida) |

**Yo'naltirish qoidasi.** Taom qatori sexga shunday tushadi: **taomning o'z yo'naltirishi** → **kategoriyasining
yo'naltirishi** → sexsiz (`null`: standart marka printeri). Bir filialda taom va kategoriya bittadan ortiq sexga
biriktirilmaydi. Nofaol sex yoki boshqa filialning sexi hisobga olinmaydi. Buyurtma qatorining sexini serverdagi
`KitchenStationResolver` aniqlaydi (`App\Domain\Pos\Setup\KitchenStationRouter`); terminal oflayn rejimda uni
`GET /pos/bootstrap` dagi `station_routes` dan o'zi hisoblaydi.

**Printer.** `name` (filialda yagona), `purpose` (`receipt`, `kitchen`, `label`), `connection`, `paper_width` (58 yoki 80),
`encoding` (printer shrifti talab qilsa, masalan `cp866`; `null` — UTF-8), `print_as_image` (matnni rasm sifatida chop
etish), `is_active`, `station_id` (shu filial sexi; faqat `kitchen` va `label` uchun). Ulanish turiga qarab:
- `lan` — `address` (IP yoki nom) majburiy, `port` (standart 9100); har bir terminal manzil bo'yicha ulanadi, `device_id` yo'q;
- `bluetooth` — `address` majburiy;
- `usb`, `builtin` (Sunmi ichki printeri) — `device_id` majburiy: shu filialniki (yoki butun do'konga ulangan) faol POS
  terminalining uuid'si; manzil saqlanmaydi. Terminal almashsa, printer sozlamasi qoladi — faqat `device_id` o'zgaradi.
Chop etishni server emas, terminalning o'zi bajaradi ([POS_SPEC.md](POS_SPEC.md) 2.11).

**Filial POS sozlamalari.** `PUT /owner/branches/{id}/pos-settings` faqat yuborilganini o'zgartiradi:
- `business_day_starts_at` — `"SS:DD"`, 00:00 dan 11:59 gacha (standart `05:00`; erta ochiladigan filial `04:00` qo'yishi
  mumkin). Do'kon vaqt mintaqasida: shu vaqtgacha bo'lgan savdo avvalgi biznes kunga kiradi.
- `pos_settings.mode` — `counter` (tez kassa), `table` (stol/xona), `hybrid` (standart).
- `pos_settings.printing_device_id` — "chop etuvchi" kassaning uuid'si (shu filialniki yoki do'konga ulangan POS terminali)
  yoki `null`. U onlayn buyurtmalar markasini chiqaradi ([POS_SPEC.md](POS_SPEC.md) 2.11).
- `pos_settings.screen_lock_minutes` — 0–240 (0 = qulflanmaydi; standart 5).
- `pos_settings.queue_numbers` — navbat raqami (standart `false`).
- `pos_settings.alerts` — kalit bo'yicha qo'shiladi, `null` shu ogohlantirishni o'chiradi: `open_room_hours` (1–72, standart 6),
  `void_amount` (0–100 000 000, standart `"200000.00"`), `discount_percent` (0–100, standart `"10.00"`),
  `unaccepted_online_minutes` (1–120, standart 3).
- `pos_settings.holidays` — `YYYY-MM-DD` sanalar (≤400), butun ro'yxat almashtiriladi. P3-26: filialning **yagona**
  bayramlar ro'yxati — `GET/PUT /owner/room-tariffs/calendar` dagi `holidays` ham shu ro'yxat (u yerdan yozilsa shu
  yerda ko'rinadi). Migratsiya `2026_10_27_100300` eski kalendar bayramlarini shu yerga qo'shib, kalendar ustunini
  bo'shatdi (`down` qaytarib ko'chiradi).
- `pos_settings.booking_slot_minutes` — tugash vaqtisiz bron xonani qancha ushlab turadi: 30–1440 daqiqa yoki `null` (serverning standarti, 300).
Noma'lum kalit — 422 (saqlanmaydi). Javob: `branch_id`, `timezone`, `business_day_starts_at`, to'liq `pos_settings`
(saqlanmaganlari standart qiymat bilan).

### `GET /pos/bootstrap` — terminal ishga tushishi

Terminaldagi xodim PIN sessiyasi (terminal filiali) yoki oddiy xodim kirishi (xodimning filiali) bilan, `orders.view`.
Filialga biriktirilmagan chaqiruvchi (ega, butun do'konga ulangan terminal) `?branch_id=` beradi (bo'lmasa 422).
Javob (`data`):

| Kalit | Mazmuni |
|---|---|
| `server_time` | server vaqti (ISO 8601): terminal soat farqini shundan hisoblaydi |
| `menu_version` | menyu versiyasi (12 belgi). Narx, nom, birlik, kategoriya, modifikator, kanal narxi o'zgarsa o'zgaradi; qoldiq, stop-list va qozonda **o'zgarmaydi** (ular uchun `menu.availability_changed`). O'zgarsa — `GET /catalog?channel=pos_*` ni qayta oling |
| `store` | `id`, `name`, `timezone` |
| `branch` | `id`, `name`, `address`, `phone`, `timezone`, `business_day_starts_at`, `business_date` (hozir qaysi biznes kun), `pos_settings` |
| `device` | terminal: `uuid`, `type`, `name`, `is_printing_device` (u "chop etuvchi" kassami); terminalsiz kirishda `null` |
| `user` | `id`, `name`, `role`, `permissions[]` |
| `stations` | faol sexlar (`sort_order` bo'yicha) |
| `station_routes` | `categories[]` va `products[]`: `{category_id\|product_id, station_id}` (faol sexlar) |
| `printers` | filialning faol printerlari, **shu terminal ishlata oladiganlari**: LAN va Bluetooth hammaga, USB va ichki — faqat o'zi ulangan terminalga |
| `staff` | filial va butun do'kon xodimlari: `id`, `name`, `role`, `photo_url` (ofitsiant tanlash uchun; PIN va kodlar yo'q — PIN klaviatura `GET /terminal/staff` dan) |
| `halls`, `tables` | filialning ochiq zallari (`id`, `name`, `sort_order`) va ulardagi faol joylar: stol, xona, tapchan (`id`, `hall_id`, `name`, `kind`, `seats`, `layout` {x,y,w,h}, `sort_order`, `min_spend`, `min_spend_mode`). O'chirilgan mavsumiy zal va uning joylari yo'q. Xona bandligi bu yerda emas — buyurtma endpointlarida |
| `shift` | **shu terminalda** ochiq smena: `uuid`, `status`, `business_date`, `opened_at`, `opened_by`, `opening_cash`, `device_id`; terminalsiz kirishda yoki smena ochiq bo'lmasa `null` (to'liq smena — smena endpointlarida) |
| `quick_items` | P3-26: kassa va ofitsiantning bir bosishli tugmalari ("choy, non"), ko'pi bilan 24: avval menyuda `quick_add` belgilangan faol taomlar (birinchi faol varianti, `source: "quick_add"`), keyin filialning faol "choy-non" (mehmon boshiga) taomlari (`source: "auto_guest"`, `hall_id`, `quantity_per_guest`). Har birida `product_id`, `variant_id`, `variant_uuid`, `name`, `variant_name`, `sort_order`. Narx va mavjudlik — POS menyusidan |

Spetsifikatsiyada `halls`, `tables`, `shift` va `quick_items` turlangan (P3-26). `tables[].layout` — zal xaritasi
to'ridagi katakchalar (`x`, `y` — chap yuqori burchak, `w`, `h` — eni va bo'yi, katakda; web panel muharriri 40×24 to'r
ishlatadi; o'nlik satr yoki `null`), piksel emas: ilova uni o'z ekraniga moslab cho'zadi.

`halls`, `tables`, `shift` va `quick_items` `PosBootstrapContributor` orqali qo'shiladi (`PosBootstrap::CONTRIBUTORS` tegi): boshqa modul o'z bo'limini shu tegga ulab, bu javobni o'zgartirmasdan kengaytiradi.

### `GET /catalog?channel=pos_hall|pos_takeaway` — POS menyusi

P3-26: xuddi shu menyu **`GET /pos/catalog?channel=pos_hall|pos_takeaway&branch_id&store_id`** da ham (standart kanal
`pos_hall`; boshqa kanal — 422). U OpenAPI'da to'liq turlangan (generatsiya qilingan klientlar shuni ishlatadi);
`GET /catalog?channel=pos_*` o'zgarmasdan ishlayveradi. Mahsulotda `batch_tracked` (qozon bo'yicha sotiladi) va
`quick_add` (tezkor tugma) ham bor. Ega uni web paneldagi taom formasida belgilaydi: `POST /owner/products[/{id}]`
dagi `quick_add` (`1`/`0`; yangilashda yuborilmasa o'zgarmaydi), migratsiya `2026_10_27_100200`.

Mijoz menyusining (`GET /catalog`) o'sha endpointi, lekin `channel` POS kanali bo'lsa so'rov `PosCatalogController`ga
beriladi: javob **boshqa shaklda** (do'kon menyusi emas, bitta filial menyusi). Shu sababli POS kanallari mijoz
katalogiga aralashmaydi: mijoz yoki boshqa do'kon xodimi so'rasa — 403/404; `telegram`, `mobile` va kanalsiz javoblar
o'zgarmagan (boshqa qiymatlar avvalgidek `telegram` ga tushadi).
- Ruxsat: `menu.view` (kassir, ofitsiant, oshpaz, menejer, ega). Filial: xodimning/terminalning filiali; ega `?branch_id=`
  beradi. `store_id` ixtiyoriy va faqat o'z do'koniniki bo'lishi mumkin.
- `data`: `channel`, `menu_version`, `branch`, `categories`, `products`, `availability`.
- Mahsulot va variantlarda mijoz katalogidagi maydonlar (kanal narxi `price`, variantning o'z narxi `base_price`,
  `unit` `piece|kg|l`, `quantity_step`, `min_quantity`, `modifier_groups` bilan `modifiers`) + qo'shimcha:
  `station_id` (sexi: taomning yo'naltirishi, bo'lmasa kategoriyasiniki, bo'lmasa `null`), `is_available` va
  `unavailable_reason` (`stopped` — stop-listda; `sold_out` — qozon/partiya tugagan; mahsulotda: stop-list yoki
  barcha variantlari tugagan; modifikatorda faqat `stopped`).
- Shu kanalda yashirilgan taom/variant (`channel_prices.is_hidden`) ro'yxatda yo'q.
- `availability` — shu filialning stop-listi, tugagan variantlar va ochiq qozonlari (`GET /catalog/availability` bilan bir xil
  shakl, bitta filial uchun).

### Test va tekshiruv

`PosStationsPrintersApiTest`, `PosBranchSettingsApiTest`, `PosBootstrapTest`, `PosCatalogTest`, `KitchenStationRouterTest`:
ruxsat, boshqa do'kon va filial izolyatsiyasi, yo'naltirish ustuvorligi, sozlamalar validatsiyasi, terminal va oddiy
kirish uchun bootstrap shakli, POS kanal narxi va ko'rinishi, mijoz katalogi o'zgarmagani.

## Onlayn to'lov fiskal cheki (P3-14)

Mini App buyurtmasi Payme yoki Click orqali to'langanda fiskal chekni **to'lov tizimining o'zi** ro'yxatdan o'tkazadi,
restoran STIRi chekda **komitent** sifatida turadi. Natija `fiscal_receipts` ga `external` chek bo'lib yoziladi
(`FiscalIssuer::recordExternal`, provayder `payme` yoki `click`, `subject_type = order`). Bu faqat do'konda fiskal rejim
yoqilgan bo'lsa (`stores.fiscal_settings.enabled`) ishlaydi; o'chiq bo'lsa webhook javoblari avvalgidek, bayt-bayt bir xil.

**Payme** ([CheckPerformTransaction](https://developer.help.paycom.uz/metody-merchant-api/checkperformtransaction),
[SetFiscalData](https://developer.help.paycom.uz/metody-merchant-api/setfiscaldata)):
- `CheckPerformTransaction` javobida `allow: true` bilan birga `detail`: `receipt_type: 0`, `items[]` (`title`, `price`
  tiyinda, `count`, `code` — MXIK, `package_code`, `vat_percent`, kerak bo'lsa `discount` va raqamli `units`) va
  yetkazish bo'lsa `shipping {title: "Yetkazib berish", price}`. Summa aniq: `Σ(price × count − discount) +
  shipping.price` = to'lov summasi (tiyin). Promo-chegirma qatorlarga summaga mutanosib taqsimlanadi (eng katta qoldiq
  usuli, bir xil buyurtma doim bir xil natija); butun bo'lmagan miqdor (1.5 kg) `count: 1` va qator summasi bilan,
  miqdor nomda ("Somsa (1.5)"). Xizmat haqi va joy narxi — alohida qator (do'konning shu xizmatlar uchun MXIK'i bilan).
- `detail` faqat **do'konning o'z Payme kassasi** (`payment_owner = STORE`) uchun yuboriladi: Merchant API'da chek
  bo'yicha komitent maydoni yo'q, platforma kassasi orqali chek platformaning o'z savdosi bo'lib qoladi. Platforma
  kassasidagi to'lov `payme_platform_cashbox` belgisi bilan qayd etiladi.
- `SetFiscalData` (yangi metod, o'sha Basic auth — to'lovning o'z kaliti): `{id, type: PERFORM|CANCEL, fiscal_data:
  {receipt_id, status_code, message, terminal_id, fiscal_sign, qr_code_url, date}}` → `{"result": {"success": true}}`.
  `PERFORM` — sotuv cheki, `CANCEL` — alohida qaytarish cheki (asl sotuv chekiga bog'lanadi). Takroriy chaqiruv hech
  narsani o'zgartirmaydi (birinchi qabul qilingan chek saqlanadi). Xatolar: `-32001` (bunday chek yo'q), `-32602`
  (noto'g'ri parametr, `message` da nomi: `Invalid parameter: type`), `-32504` (kalit noto'g'ri). `status_code ≠ 0`
  (OFD rad etgan) — to'lovda `failed` / `payme_ofd_rejected` deb belgilanadi, keyingi muvaffaqiyatli chaqiruv yoziladi.

**Click** ([Фискализация данных](https://docs.click.uz/merchant-api/fiscalization)):
- `Complete` muvaffaqiyatli bo'lgach navbatga `SubmitClickFiscalData` qo'yiladi; Click callback'i javobi uni kutmaydi va
  o'zgarmaydi. Ish `POST https://api.click.uz/v2/merchant/payment/ofd_data/submit_items` ga `{service_id, payment_id
  (= click_trans_id), items[], received_ecash: 0, received_cash: 0, received_card}` yuboradi. `items[]`: `Name` (63
  belgi), `SPIC` (MXIK), `PackageCode`, `Price` (qator summasi, tiyin), `Amount` (miqdor mingdan birlarda: 1000 = 1
  dona, 1500 = 1.5 kg), `VAT` (tiyin), `VATPercent`, `CommissionInfo {TIN}` (yakka tadbirkorda `{PINFL}`), kerak
  bo'lsa `GoodPrice`, `Units`. Yetkazish — do'konning `delivery` xizmati MXIK'i bilan alohida qator.
- So'ng `GET .../ofd_data/{service_id}/{payment_id}` dan `qrCodeURL` olinadi; havoladagi `t`, `r`, `c`, `s` —
  terminal, chek raqami, sana, fiskal belgi. Click uzilishi va chek hali tayyor bo'lmasa qayta urinish (60 s, 5, 15,
  30 daqiqa, 1, 2, 4 soat; jami 8 marta; `error_code ≠ 0` ham qayta uriniladi), mahsulotlar qayta yuborilmaydi.
  Click so'rovni rad etsa (HTTP 4xx) yoki urinishlar tugasa — `failed`.
- Kirish sarlavhasi: `Auth: merchant_user_id:sha1(timestamp + secret_key):timestamp`. Platforma kassasi uchun
  `CLICK_MERCHANT_USER_ID` (yoki superadmin'dagi Click provayderi `credentials.merchant_user_id`), do'konning o'z
  hisobi uchun `payment_settings.click.merchant_user_id`.

**Chek chiqmasa to'lov to'xtamaydi.** Fiskal rejim yoqilgan, lekin chek tuzib bo'lmasa (taomda MXIK yoki qadoq kodi
yo'q, do'kon STIRi yo'q, Click kalitlari yo'q, platforma Payme kassasi) — to'lov odatdagidek o'tadi (provayder hujjati
bo'yicha `detail`/`items` shunchaki yuborilmaydi), serverda `Online fiscal receipt not sent` ogohlantirishi (buyurtma,
do'kon, muammo kodlari, taom nomlari) yoziladi va to'lovda belgi qoladi. Belgi `GET /owner/payments` qatorida:

```json
"fiscal": {"status": "blocked", "problems": ["mxik_missing"]}
```

`status`: `blocked` (ega tuzatishi kerak), `pending` (Click'ga yuborildi, chek kutilmoqda), `failed` (provayder yoki
OFD rad etdi, urinishlar tugadi), `recorded` (chek `fiscal_receipts` da); fiskal rejim o'chiq yoki naqd to'lovda
`null`. Filtr: `GET /owner/payments?fiscal_status=blocked|pending|failed|recorded` (`finance.view`). Muammo kodlari:
`mxik_missing`, `package_code_missing`, `store_tin_missing`, `charge_mxik_missing` (xizmat haqi yoki joy narxi),
`delivery_mxik_missing`, `items_missing`, `sum_mismatch`, `fiscal_unavailable`, `payme_platform_cashbox`,
`payme_ofd_rejected`, `click_credentials_missing`, `click_submit_failed`, `click_qr_missing`. Xom provayder
ma'lumoti hech qachon qaytarilmaydi.

Testlar: `tests/Feature/OnlineFiscal/*` — hujjatdagi so'rov va javob namunalari, summalar va yaxlitlash, SetFiscalData
idempotentligi va auth, Click ishining muvaffaqiyati, xatosi va qayta urinishi, MXIK va STIR yo'qligi, haqiqiy fiskal
yadro bilan to'liq sikl.

## Bron, avans va nasiya (P3-17)

Kassa va web panel uchun bron daftari, oldindan buyurtma, avans (bo'nak) va nasiya hisoblari (tadqiqot hisoboti
R14–R18, POS_SPEC 2.9–2.10). Hammasi `/api/v1/pos/*` ostida: terminaldagi xodim PIN sessiyasi yoki oddiy xodim kirishi
(web panel ham shu endpointlardan foydalanadi). Do'kon va filial bo'yicha ajratilgan: boshqa do'kon yoki o'zi
ishlamaydigan filial yozuvi — 404; filialga biriktirilgan xodim va terminal sessiyasi faqat o'z (terminal) filialida
ishlaydi; terminali bekor qilingan sessiya hech narsa qila olmaydi. Pul — `"12000.00"` satr, vaqt — UTC ISO 8601
(so'rovda zonasiz vaqt do'kon vaqt mintaqasida o'qiladi), id'lar 18 xonagacha. Xatolar standart shaklda,
`data.reason` — barqaror kod.

### Bron (`bookings.manage`: ega, menejer, kassir)

| Endpoint | Vazifasi |
|---|---|
| `GET /pos/bookings/calendar?branch_id&from&to&include_canceled` | filial xonalari (zal tartibida), har birida o'sha kunlardagi bronlar; xonasiz bronlar `unassigned` da; ko'pi bilan 31 kun |
| `GET /pos/bookings?branch_id&from&to&status` | kunlar bo'yicha ro'yxat |
| `GET /pos/bookings?phone=901234567` | telefon bo'yicha qidiruv (kamida 4 raqam, istalgan ko'rinishda): mijoz yoki guruh tarixi, yangisi birinchi |
| `POST /pos/bookings` | yaratish: `dining_table_id`, banket uchun `extra_table_ids` (birlashtirilgan xonalar), `starts_at`, `ends_at` (yo'q bo'lsa filial sloti, standart 5 soat), `guests_planned`, `event_type` (`gap`, `ziyofat`, `tugilgan_kun`, `nahor_oshi`, `korporativ`, `other`), `contact_name`, `contact_phone` (+998XXXXXXXXX ga keltiriladi), `source` (`phone`, `telegram`, `instagram`, `walk_in`, `other`), `min_spend`, `note`, `confirm` |
| `GET /pos/bookings/{uuid}` | bron, oldindan buyurtma qatorlari (`items`) va avanslar (`payments`) bilan |
| `PATCH /pos/bookings/{uuid}` | faqat yuborilgan maydonlar; vaqt yoki xona o'zgarsa to'qnashuv qayta tekshiriladi |
| `POST /pos/bookings/{uuid}/confirm`, `/cancel` (`reason` majburiy), `/no-show` | holatni o'zgartirish |
| `POST /pos/bookings/{uuid}/arrive` | "Keldi": buyurtma ochiladi (`guests`, boshqa xonaga — `dining_table_id`) |
| `POST/PATCH/DELETE /pos/bookings/{uuid}/items[/{id}]` | oldindan buyurtma: `product_variant_id` yoki P3-26 `product_variant_uuid` (POS buyurtma qatorlaridagi kabi uuid; javobda ikkalasi ham), `quantity` (kg qadam bilan), `modifiers` `[{modifier_id, quantity}]`, `note`, `due_at` (tayyor bo'lish vaqti; standart — bron boshlanishi) |
| `POST /pos/bookings/{uuid}/deposits` | avans olish (`Idempotency-Key` majburiy, doira `pos.bookings.deposit`) |
| `POST /pos/bookings/{uuid}/deposits/{payment}/refund` | avansni qaytarish (`reason`, kerak bo'lsa `approval_token`) |
| `POST /pos/bookings/{uuid}/deposits/{payment}/retain` | avansni ushlab qolish (`reason`, kerak bo'lsa `approval_token`) |

**Holatlar.** `requested` (so'rov) → `confirmed` → `arrived` (buyurtma ochiq) → `completed` (buyurtma yopildi yoki
bekor qilindi — avtomatik); `requested`/`confirmed` → `canceled`; `confirmed` → `no_show` (faqat bron boshlanish
vaqtidan keyin); kelib qolgan mehmon uchun `requested` → `arrived` ham mumkin. Avans olinganda `requested` bron
`confirmed` bo'ladi (hisobotdagi "avans olindi" — `confirmed` + `deposit_available > 0`). Noto'g'ri o'tish — 422
`invalid_transition`. O'zgartiruvchi so'rovlarda ixtiyoriy `version`: boshqa qurilma o'zgartirib ulgurgan bo'lsa 409
`stale_version`.

**To'qnashuv.** Bir xona bir vaqtda ikki marta band qilinmaydi: `requested`, `confirmed`, `arrived` bronlar o'z
xonalarini (banketda — birlashtirilgan hamma xonani) `[starts_at, ends_at)` oralig'ida band qiladi. 18:00 da tugagan
va 18:00 da boshlangan bronlar to'qnashmaydi. To'qnashuvda 409 `booking_conflict`, `data.conflicts` — band qilgan
bronlar (`uuid`, `status`, vaqtlar, `table_ids`). Bekor qilingan yoki "kelmadi" bron xonani bo'shatadi. Tekshiruv va
yozish filial qatori qulfi ostida: ikki kassir bir xonani bir vaqtda band qila olmaydi. Standart slot
`POS_BOOKING_SLOT_MINUTES` (300) yoki filialning `pos_settings.booking_slot_minutes`.

**"Keldi".** `PosOrderOpener` (P3-12) orqali `pos_hall` buyurtma ochiladi: xona, mehmonlar soni, `orders.booking_id`;
oldindan buyurtma qatorlari "ushlab turish" holatida, har biri o'z `due_at` vaqti bilan (oshxona ularni vaqtida
ko'radi). Bronning avanslari shu buyurtmaga bog'lanadi va yopishda ayiriladi (`AdvanceLedger`, P3-18). Ikkinchi marta —
409 `booking_already_arrived`, `data.order_uuid`. Javob: `{booking, order: {uuid, status}}`.

**Avans (bo'nak).** `amount`, `tender` (`cash`, `card`, `qr`; P2P yo'q), `card_type`, `transaction_id`. Avans
yozilgach **bo'nak (ADVANCE) cheki** chiqariladi: bitta "avans" qatori (do'konning `deposit` xizmati MXIK'i, standart
10202002004000001), to'lov shakli avans usuli bo'yicha. Javobda `payment.fiscal_receipt_uuid` va `fiscal_status`.
Takroriy so'rov (bir xil `Idempotency-Key`) o'sha avansni qaytaradi, ikkinchi chek chiqmaydi. Fiskal sozlama xatosi
(masalan avans uchun MXIK yo'q) avansni yozmasdan 422 `mxik_missing` beradi; provayder xatosida avans saqlanadi,
`fiscal_status: "error"`. Bo'nak cheki soliq hisobotiga tushmaydi: kechqurun yopishda **to'liq** summaga sotuv cheki
chiqadi, avans undagi oldindan to'lov qismi bo'ladi (P3-18).

**Qaytarish va ushlab qolish.** Avansning ishlatilmagan qoldig'i bilan, sabab majburiy, menejer tasdig'i bilan:
qaytarish — `pos.refund`, ushlab qolish — `orders.cancel`. Shu huquqi bor xodim (menejer, ega) o'zi tasdiqlaydi;
kassir menejerning bir martalik `approval_token` ini yuboradi (`POST /pos/approvals`, P3-18). Tasdiqsiz — 403
`approval_required`, yaroqsiz token — 403 `approval_invalid`. Qaytarish mehmonlar o'tirgan paytda (`arrived`) mumkin
emas; ushlab qolish faqat `canceled`, `no_show` yoki `completed` (hisobdan ortgan qoldiq) bronda. Egaga xabar
boradi (`pos_alerts`, egasining xabar sozlamalari bo'yicha), harakatlar jurnaliga yoziladi.

**Qaytarish qaysi kassadan (P3 integratsiya tuzatishi).** Terminalda qaytarilsa, avansda terminal va undagi ochiq
smena yoziladi (`booking_payments.refunded_device_id`, `refunded_shift_id`): shu smenaning X/Z hisobotida
`deposits.refunded` ko'rinadi, **naqd** avans esa shu kassadan chiqqan hisoblanadi — `cash.deposit_refunds` va
kutilgan naqddan ayiriladi. Web paneldan (terminalsiz) yoki smenasi ochiq bo'lmagan terminalda qaytarilgan avans
hech qaysi smenaga tushmaydi (pul boshqa joydan berilgan). Avans pulining yagona yozuvi — `booking_payments`:
`cash_movements` ga nusxa olinmaydi, shuning uchun ikki marta hisoblanmaydi.

> **Tekshirilmagan fiskal qoidalar (buxgalter bilan tasdiqlash kerak, tadqiqot T3).** Qaytarishda standart bo'yicha
> chek **chiqmaydi** (buxgalter.uz tavsiyasi: ariza, qaytarish dalolatnomasi, asl bo'nak cheki); do'kon
> `fiscal_settings.deposit_refund_receipt: true` bilan asl bo'nak chekiga bog'langan qaytarish (REFUND) chekini
> yoqishi mumkin. Ushlab qolingan avansga standart bo'yicha **sotuv (SALE) cheki** chiqadi: "bron xizmati" qatori
> (`booking_fee`, standart MXIK 10202002003000002), to'lov — oldindan to'langan (avans hisobidan); do'kon
> `fiscal_settings.retained_deposit_receipt: false` bilan o'chira oladi. Ikkalasining standarti:
> `POS_DEPOSIT_REFUND_RECEIPT`, `POS_RETAINED_DEPOSIT_RECEIPT` (`config/pos_bookings.php`).

### Nasiya hisoblari

| Endpoint | Ruxsat | Vazifasi |
|---|---|---|
| `GET /pos/customer-accounts?q&debtors&per_page` | `customer_accounts.manage` yoki `pos.take_payment` | qidiruv (ism, guruh, telefon raqamlari), sahifalangan |
| `GET /pos/customer-accounts/debtors` | xuddi shu | qarzdorlar (eng katta qarz birinchi) va jami qarz |
| `GET /pos/customer-accounts/{uuid}` | xuddi shu | hisob va oxirgi 20 yozuv |
| `GET /pos/customer-accounts/{uuid}/statement?from&to` | xuddi shu | ko'chirma: boshlang'ich va yakuniy qarz, tur bo'yicha jami, yozuvlar |
| `POST /pos/customer-accounts/{uuid}/repayments` | xuddi shu | qarz to'lovi: `amount` (qarzdan ko'p emas), `tender`, `card_type`, `transaction_id`, `note`; `Idempotency-Key` majburiy |
| `POST /pos/customer-accounts` | `customer_accounts.manage` (ega, menejer); P3-26: kassir (`pos.take_payment`) menejerning bir martalik `approval_token` i bilan (tasdiqsiz — 403 `approval_required`) | ochish: `name`, `phone` (do'konda yagona), `group_name`, `credit_limit` (`null` — cheksiz, `0` — nasiya yo'q), `note`, `branch_id` |
| `PATCH /pos/customer-accounts/{uuid}` | xuddi shu (tasdiq bilan kassir ham) | o'zgartirish, `is_blocked` (yangi nasiya to'xtaydi, to'lov qabul qilinaveradi) |
| `POST /pos/customer-accounts/{uuid}/adjustments` | xuddi shu | tuzatish: `amount` (+ qarzga qo'shadi, − kechadi), `note` majburiy; qarz manfiy bo'lmaydi |

P3-26: avans va qarz to'lovi javobidagi `fiscal.receipt` — chop etish uchun to'liq chek ("Fiskal cheklar" bo'limi);
schyotlar ro'yxati (`GET /pos/orders/{uuid}/bills`) buyurtmasida `booking_uuid` va `deposit_available` — kassa bron
avansini shundan topadi. OpenAPI'da avans qaytarish/ushlab qolish (`reason`, `approval_token`) va tasdiqlash
(`version`) tanalari bor.

Hisob do'kon bo'yicha (`branch_id: null`) yoki bitta filialniki; filialga biriktirilgan menejer ochgan hisob uning
filialiniki. Filial xodimi va terminal sessiyasi do'kon hisoblarini va o'z filiali hisoblarini ko'radi.

Qarz faqat yozuvlar orqali o'zgaradi (`charge` +, `repayment` −, `adjustment` ±; har birida `balance_after`), hisob
qatori qulfi ostida. **Nasiyaga yopish** P3-18 dagi schyot to'lovi (`PosTender::ACCOUNT`, menejer tasdig'i bilan)
`CustomerAccountLedger::charge` ni chaqiradi: noma'lum, bloklangan, boshqa do'kon yoki (tasdiqlagan menejer uchun)
boshqa filial hisobi, limitdan oshish — rad etiladi; bitta schyot bitta marta yoziladi. Taom berilgan kuni **to'liq
sotuv cheki** chiqadi (P3-18), har bir qarz to'loviga esa **kredit (CREDIT) cheki** (QR va fiskal belgisiz; do'konning
`credit` xizmati qatori, to'lov shakli to'lov usuli bo'yicha). Egaga Telegram xabari: chegaradan
(`POS_CREDIT_ALERT_THRESHOLD`, standart 500 000 so'm) katta har bir yangi nasiya va har bir qarz to'lovi.

### Real-time

`private-pos-branch.{branchId}` kanalida `booking.changed`: bron yaratildi, o'zgardi, holati yoki avansi o'zgardi.
Tanasi faqat id, holat va vaqtlar — ilova `GET /pos/bookings/{uuid}` ni qayta so'raydi:

```json
{"booking": {"uuid": "...", "status": "confirmed", "branch_id": 5, "version": 3, "starts_at": "...", "ends_at": "..."}}
```

Testlar: `tests/Feature/PosBooking/*` — to'qnashuv (birlashtirilgan xonalar, chegaraviy vaqtlar, bir vaqtdagi ikki
so'rov), holatlar, bo'nak cheki va idempotentlik, tasdiq bilan qaytarish va ushlab qolish, "Keldi" va ushlab turilgan
qatorlar, avans ofseti (bir vaqtdagi ikki schyot), nasiya limiti va kredit cheki, ruxsat va izolyatsiya.

## Fiskal cheklar (P3-11)

22.10.2026 dan ovqatlanish korxonasi faqat soliq tizimi bilan integratsiya qilingan buyurtma dasturidan foydalana oladi
(VM 393). Shuning uchun Rizqim'da har bir sotuv, qaytarish, bo'nak (avans) va kredit (nasiya to'lovi) cheki
`fiscal_receipts` jadvaliga yoziladi va provayderga (virtual kassa) yuboriladi. Provayder hali tanlanmagan: hozir
ikki drayver bor — `sandbox` (soxta, lekin haqiqiyga o'xshash javob) va `null` (fiskal emas). Ikkalasining cheki ham
`non_fiscal: true` — qog'ozda "Bu fiskal chek emas" yoziladi. Haqiqiy drayver P3-32 da qo'shiladi.

**Chek turlari** (VM 943, 1-ilova, 7-band): `sale` (sotuv), `refund` (qaytarish — asl sotuv yoki bo'nak chekiga
bog'lanadi), `advance` (bo'nak: bron avansi; bitta "avans" qatori, fiskal belgi va QR yo'q), `credit` (nasiya
to'lovi; bitta "qarz to'lovi" qatori, fiskal belgi va QR yo'q). Avansli bron: avval `advance` cheki, keyin **to'liq
summaga** `sale` cheki, avans qismi `prepayment: true` to'lov bo'lib ko'rsatiladi. Nasiya: xizmat kuni to'liq `sale`
cheki, nasiyaga qolgan qismi `credit: true` to'lov bo'lib ko'rsatiladi, har bir qarz to'loviga `credit` cheki.
`prepayment` va `credit` faqat sotuv chekida va bitta to'lovda bittasi (aks holda 422 `invalid_document`); nasiya
qismining shakli (`cash`) — provayder aniqlaguncha belgi (T4). **To'lov shakli** (`payment_form`): `cash`, `card`,
`qr` yoki bir nechta usulda olinsa `mixed` (avans va nasiya qismi hisobga olinmaydi). Chek javobidagi har to'lovda
`prepayment` va `credit` (oldin yozilgan cheklarda `credit: false`); sandbox javobida `prepaid` va `credit` summalari.

**Chek qatori:** nom (menyudagi nom va variant), MXIK (17 raqam), qadoq kodi, o'lchov birligi (variantning
`unit_label` i yoki "dona", "kg", "l"), miqdor, birlik narxi, summa (chegirmadan keyin), QQS stavkasi va summasi,
kerak bo'lsa komitent STIRi va markirovka kodi. QQS narx ichida: `QQS = summa × stavka / (100 + stavka)`, har qator
alohida tiyingacha yaxlitlanadi (chekning QQS jami — qatorlar yig'indisi). Stavka do'konning soliq rejimidan:
`vat` — 12% (taomning o'z `vat_rate` i bo'lsa — o'sha), `vat_simplified` — 6%, `turnover` (aylanma solig'i) — QQSsiz
(stavka `"0"`, qatorda `no_vat: true`). Rejim tanlanmagan bo'lsa — `vat`.

**"MXIK'siz sotilmaydi".** Fiskal rejim yoqilgan do'konda MXIK kodi yo'q taom yoki xizmatni POS buyurtmasiga qo'shib
bo'lmaydi, xizmatlar (xona puli, xizmat haqi...) ham sozlamada MXIK kodisiz chekka tushmaydi. Fiskal rejim **o'chiq**
do'konda MXIK talab qilinmaydi: chek qatori MXIK'siz (bo'sh) quriladi va chek `null` drayveri bilan `non_fiscal`
chiqadi — kodlari kiritilmagan menyu bilan ham to'lov, avans va qarz to'lovi qabul qilinadi (kiritilgan kod baribir
17 raqam bo'lishi kerak). Fiskal rejimdagi javob — 422:

```json
{"success": false, "message": "\"Osh\" uchun MXIK kodi kiritilmagan. Fiskal rejimda MXIK'siz taom yoki xizmat sotilmaydi: menyuda MXIK kodini kiriting.", "data": {"reason": "mxik_missing", "product_id": 12, "name": "Osh"}}
```

Boshqa sabablar (`data.reason`): `invalid_document` (qatorlar, to'lovlar va jami mos emas, chek turi qoidasi
buzilgan), `idempotency_conflict` (shu kalit bilan boshqa chek bor), `original_receipt_invalid` (qaytarishda asl chek
yo'q, ro'yxatdan o'tmagan yoki summa oshib ketdi), `unknown_charge`.

**Kutish va qayta yuborish.** Chek avval `pending` bo'lib yoziladi, keyin provayderga yuboriladi: tasdiqlansa
`issued`, provayder javob bermasa `pending` qoladi ("fiskal kutmoqda") va navbatdagi ish 30 s, 1, 2, 5, 10, 30 daqiqa,
keyin har soat qayta urinadi (`config/fiscal.php`, ko'pi bilan 12 marta); har daqiqada `fiscal:retry-pending`
yo'qolgan urinishlarni ham yig'ib oladi. Provayder rad etsa yoki urinishlar tugasa — `failed`; ega panelda "Qayta
yuborish" bosadi. Sotuv hech qachon yo'qolmaydi: chek qatori provayderdan oldin saqlanadi. Bitta `Idempotency` kaliti
(do'kon ichida) — bitta chek.

**Real-time:** `fiscal.receipt_updated` — chek `issued` yoki `failed` bo'lganda `private-pos-branch.{branchId}` (chekda
filial bo'lsa) va `private-store.{storeId}` kanallariga: `{"receipt": {"uuid", "type", "status", "store_id",
"branch_id", "subject_type", "subject_id"}}`. Ilova chekni API'dan qayta so'raydi.

### Endpointlar

Hammasi `auth:sanctum` + `staff.store` + ruxsat, do'kon va filial bo'yicha ajratilgan (boshqa do'konniki — 404,
filialga biriktirilgan xodim yoki terminal sessiyasi uchun boshqa filialniki — 403), xatolar o'zbekcha, pul — ikki
xonali satr.

| Endpoint | Ruxsat | Vazifasi |
|---|---|---|
| `GET /owner/fiscal/settings` | `settings.manage`, terminal sessiyasidan emas | STIR, soliq rejimi, fiskal sozlamalar (standart qiymatlar bilan), drayverlar va rejimlar ro'yxati |
| `PUT /owner/fiscal/settings` | `settings.manage`, terminal sessiyasidan emas | `tin` (9 raqam), `tax_regime` (`vat`, `vat_simplified`, `turnover`), `fiscal_settings` (qisman; yuborilmagani o'zgarmaydi) |
| `GET /owner/fiscal/readiness` | `menu.view` yoki `settings.manage` | MXIK'siz taomlar va xizmatlar ro'yxati, STIR bormi |
| `GET /owner/fiscal/receipts` | `finance.view` yoki `reports.view` | cheklar ro'yxati (yangisi birinchi, sahifalab): `status`, `type`, `payment_form`, `branch_id`, `subject_type` + `subject_id`, `from`/`to` (do'kon vaqti), `per_page` 1–100 |
| `GET /owner/fiscal/receipts/{uuid}` | `finance.view` yoki `reports.view` | bitta chek: qatorlar, to'lovlar, fiskal belgi, QR, xato, urinishlar |
| `POST /owner/fiscal/receipts/{uuid}/retry` | `settings.manage`, terminal sessiyasidan emas | `pending` yoki `failed` chekni darhol qayta yuborish (`issued` yoki Payme/Click cheki — 422 `already_issued`) |
| `GET /pos/fiscal-receipts/{uuid}` | `pos.take_payment` (chek filialida; terminal sessiyasi — terminal filiali) | P3-26: kassada chop etish uchun chek: `fiscal_sign`, `receipt_number`, `qr_url`, `terminal_id` (fiskal modul), `non_fiscal`, `issued_at`, `type`, `payment_form`, `total`, `vat_total`, `lines`, `payments`, `original_receipt_uuid`. Boshqa do'kon yoki filial cheki — 404 |
| `PATCH /owner/products/{id}/fiscal` | `menu.manage` | P3-26: faqat taomning fiskal maydonlari (pastda) |

**Chek kassaga (P3-26).** Schyot to'lovi, qaytarish, avans va qarz to'lovi javoblaridagi `fiscal` obyekti endi
`{status, receipt_uuid, error, receipt}`: `receipt` — chek yozilgan bo'lsa yuqoridagi `GET /pos/fiscal-receipts/{uuid}`
obyekti, aks holda `null`. Kassa fiskal belgi va QR'ni shundan chop etadi (avval faqat holat bor edi). Bo'nak (avans)
va kredit cheklarida fiskal belgi va QR yo'q (`null`) — bu provayder qoidasi.

`fiscal_settings` (`stores.fiscal_settings`):

```json
{
  "enabled": true,
  "driver": "sandbox",
  "charges": {
    "room_fee": {"mxik": "10202002…", "package_code": null, "unit": "xizmat", "name": "Xona puli"},
    "deposit": {"name": "Bron avansi"}
  },
  "sandbox": {"latency_ms": 0, "fail_attempts": 0, "failure_rate": 0, "reject": false},
  "deposit_refund_receipt": null,
  "retained_deposit_receipt": null
}
```

- `enabled` — fiskal rejim; yoqish uchun STIR va soliq rejimi kerak. O'chiq bo'lsa cheklar `null` drayveri bilan
  "fiskal emas" bo'lib yoziladi. `driver` — `sandbox` yoki `null` (bo'sh — `FISCAL_DRIVER`, standart `sandbox`);
  javobdagi `active_driver` — hozir cheklar qayerga ketadi.
- `charges` — menyuda yo'q xizmatlar qatori: `room_fee` (xona puli), `place_fee` (joy puli), `min_spend_topup`
  (minimal summaga qo'shimcha), `service_charge` (xizmat haqi), `deposit` (avans, standart MXIK `10202002004000001`),
  `booking_fee` (bron xizmati, `10202002003000002`), `credit` (qarz to'lovi, `10202002004000001`), `delivery`
  (yetkazib berish). Xona puli, joy puli, minimal summa, xizmat haqi va yetkazish uchun rasmiy kod topilmagan: ega
  tasnif.soliq.uz da tekshirib kiritadi. Maydonni `null` yuborish standart qiymatga qaytaradi.
- `sandbox` — faqat sinov drayveri uchun: `latency_ms` (0–3000) kechikish, `fail_attempts` — har chekning birinchi N
  urinishi "javob yo'q", `failure_rate` — urinishlarning shuncha foizi (uuid bo'yicha takrorlanadigan), `reject` —
  hamma chek rad etiladi.
- `deposit_refund_receipt`, `retained_deposit_receipt` — bron avansi qaytarilganda yoki ushlab qolinganda chek
  chiqarilsinmi (P3-17; `null` — standart).

`GET/POST /owner/settings` ham `tin`, `tax_regime`, `fiscal_settings` ni qaytaradi va qabul qiladi (ixtiyoriy).

**Menyu.** `POST /owner/products` va `POST /owner/products/{id}` ixtiyoriy `kind` (`dish` — taom, `service` —
xizmat: oshpaz xizmati, qozon ijarasi), `mxik_code` (17 raqam), `package_code` (faqat raqamlar, ko'pi bilan 20),
`vat_rate` (0–100, bo'sh — rejim bo'yicha) qabul qiladi; variantlar JSON'ida `unit_label` (chekdagi birlik, masalan
"kg (guruch)"). Yangilashda yuborilmagan maydon o'zgarmaydi, bo'sh qiymat tozalaydi. `GET /owner/products` ularni
qaytaradi.

**Faqat fiskal maydonlar (P3-26).** `PATCH /owner/products/{id}/fiscal` (JSON, `menu.manage`): `kind`, `mxik_code`,
`package_code`, `vat_rate`, `variants: [{id, unit_label}]` — boshqa hech narsa (nom, narx, variantlar, rasm) yuborilmaydi
va o'zgarmaydi. Yuborilmagan maydon saqlanadi, `null` yoki `""` tozalaydi; boshqa taomning varianti — 422
`variants.N.id`; boshqa do'kon taomi — 404. Javob — to'liq taom (`GET /owner/products` shaklida). Web paneldagi
"Fiskal tayyorlik" jadvali shuni ishlatadi (avval butun taomni qayta yuborardi).

Testlar: `tests/Feature/FiscalIssuerTest.php` (to'rt chek turi, idempotentlik, validatsiya, kutish → qayta urinish →
`issued`, rad etish, tashqi chek, qaytarish bog'lanishi), `FiscalLineBuilderTest.php` (QQS va yaxlitlash rejim
bo'yicha, MXIK tayyorligi), `FiscalApiTest.php` (ruxsatlar va izolyatsiya).

## Schyot, to'lov va smena (P3-18)

Kassa uchun: schyot (pre-check), hisobni bo'lish, to'lov, fiskal sotuv cheki, smena ichida pul qaytarish, smena, X va
Z, menejer PIN tasdig'i ([POS_SPEC.md](POS_SPEC.md) 2.2, 2.3, 2.9, 2.10). Oddiy xodim kirishi yoki terminaldagi PIN
sessiyasi; filial — xodimniki (PIN sessiyasida terminalniki), boshqa filial yoki do'kon buyurtmasi, schyoti, smenasi
— 404. Har o'zgartiruvchi so'rovda `Idempotency-Key` majburiy (yo'q bo'lsa 428 `idempotency_key_required`), takror
so'rov birinchi javobni qaytaradi. Buyurtmani o'zgartirishda `version` (eskisi — 409 `version_conflict`, javobda
joriy `version`). Rad javoblari `data.reason` bilan.

### Menejer tasdig'i

`POST /pos/approvals {permission, user_id | staff_code, pin}` — terminaldagi xodimda ruxsat yo'q bo'lsa, menejer
shu terminalda o'z PIN'ini teradi. Javob: bir martalik `token` — faqat shu `permission` va shu terminal uchun,
**60 soniya**. Amal so'rovida `approval_token` sifatida yuboriladi. PIN tekshiruvi va bloklash xodim kirishi bilan bir
xil (noto'g'ri — 422 `pin_invalid`, bloklangan — 429 `pin_locked`); menejerda ruxsat bo'lmasa 403
`approver_not_allowed`; terminal sessiyasidan tashqari — 422 `terminal_required`. Tasdiqlanadigan ruxsatlar:
`orders.manage`, `orders.cancel`, `orders.discount`, `orders.void_item`, `tables.manage`, `pos.*`, `bookings.manage`,
`customer_accounts.manage`. Token yo'q bo'lsa amal 403 `approval_required` (`data.permission`), muddati o'tgan,
ishlatilgan, boshqa terminal yoki boshqa ruxsat uchun bo'lsa 403 `approval_expired` / `approval_used` /
`approval_wrong_device` / `approval_wrong_permission`. Jurnalda ikkalasi: `pos.approval_granted` (kim tasdiqladi,
kim so'radi), `pos.approval_used`.

### Schyot va bo'lish

| Endpoint | Ruxsat | Nima qiladi |
|---|---|---|
| `GET /pos/orders/{uuid}/bills` | `orders.view` | buyurtmaning hisob holati va schyotlari (`all=1` — bekor qilinganlari bilan), qaytarishlar |
| `POST /pos/orders/{uuid}/bills {version, mode, parts?, bills?, approval_token?}` | bitta schyot — `orders.manage` yoki `pos.take_payment`; bo'lish — `pos.take_payment`; ofitsiant — faqat o'z buyurtmasi (boshqasiniki — menejer tasdig'i, `orders.manage`) | schyot tuzish yoki bo'lish |
| `POST /pos/bills/{uuid}/print {version?, approval_token?}` | `orders.manage` yoki `pos.take_payment`; nusxa — `pos.reprint_bill` (yoki tasdiq) | schyotni chiqarish |

- `mode`: `single` — bitta schyot; `equal` + `parts` (2–30) — teng summali schyotlar, **qoldiq tiyin birinchi
  schyotga**; `items` + `bills: [{items: [{order_item_id, quantity}], guests?}]` — taom bo'yicha, kasr miqdor ham
  (1,5 kg oshdan 0,5 va 1). Qolgan har bir qator to'liq taqsimlanishi kerak (422 `items_not_covered`).
- Chegirma va xizmatlar (xona puli, minimal summaga qo'shimcha, xizmat haqi, joy puli) schyotlar orasida summasiga
  proporsional bo'linadi; hammasi tiyingacha aniq: schyotlar yig'indisi buyurtmaga, har qator va xizmat ulushlari o'ziga
  teng. Teng bo'lishda har qator ham ulushlarga bo'linadi — har schyot alohida sotuv cheki oladi.
- Schyot buyurtmaning **hali schyotga tushmagan qismidan** tuziladi: to'lov boshlangan yoki to'langan schyotlar
  qoladi, qolgan ochiq yoki chiqarilgan schyotlar bekor qilinib qayta tuziladi. Schyotdan keyin yangi bosqich
  qo'shilsa — shunchaki qayta schyot tuziladi. Ochiq schyot tuzilgandan keyin buyurtma o'zgargan bo'lsa, uni chiqarish
  yoki to'lash 409 `order_changed` (ro'yxatda `bills_out_of_date: true`).
- Schyot javobida: `subtotal`, `discount`, xizmat qatorlari, `total`, `paid_amount`, `due_amount`, `guests_share` va
  `per_guest_amount` ("kishi boshiga", butun so'mga yuqoriga yaxlitlangan), qatorlar ulushi, to'lovlar, `fiscal`
  (`status`, `receipt_uuid`, `error`). Buyurtma holatida `billed_amount`, `paid_amount`, `unbilled_amount`,
  `fiscal_pending` ("fiskal kutmoqda"), `queue_number`, `business_date`, `bill_locked_at`, `closed_at`.
- Faqat POS buyurtmalari (`pos_hall`, `pos_takeaway`; boshqasi 409 `not_pos_order`); onlayn to'lovda (Payme, Click)
  fiskal cheki chiqqan buyurtma kassada qayta hisoblanmaydi — 409 `order_receipted_online` (ikkinchi sotuv cheki yo'q).
- Birinchi chiqarish buyurtmani **qulflaydi** (`bill_locked_at`): shundan keyin o'chirish va chegirma menejer tasdig'i
  bilan (P3-12). Qayta chiqarish — `copy: true`, chekda "NUSXA".

### To'lov va yopish

`POST /pos/bills/{uuid}/payments {tenders: [...], approval_token?, shift_uuid?, version?}` (`pos.take_payment`).
Bitta so'rovda bir nechta to'lov turi (aralash), yoki bir necha so'rovda qismlab; jami schyot qoldig'idan oshmaydi
(422 `overpayment`, `data.due`).

| `tender` | Maydonlar | Izoh |
|---|---|---|
| `cash` | `amount`, `tendered_amount?` | qaytim hisoblanadi (`change_amount`); naqd smena kassasiga tushadi |
| `card` | `amount`, `card_type`, `transaction_id` | alohida bank terminali; karta turi (`uzcard`, `humo`, `visa`, `mastercard`, `unionpay`, `mir`, `other`) va tranzaksiya raqami **majburiy** (422 `card_type_required`, `transaction_id_required`) |
| `qr` | `amount`, `reference?` | statik yoki dinamik QR, kassir tasdiqlaydi |
| `prepayment` | `amount` | bron avansidan ayirish (P3-17 `AdvanceLedger`), mavjudidan ko'p emas (422 `advance_insufficient`), schyotga bir marta |
| `account` | `amount`, `account_uuid` | nasiya (P3-17 `CustomerAccountLedger`); `customer_accounts.manage` yoki menejer tasdig'i; schyotga bir marta |

- To'lov kassaning ochiq smenasiga yoziladi (terminal smenasi yoki `shift_uuid`; yo'q bo'lsa 409 `shift_not_open`).
- Schyot to'liq qoplanganda **sotuv cheki** so'raladi (to'liq summaga). Schyot `paid` bo'ladi faqat chek `issued`
  bo'lganda; hamma schyot to'langach buyurtma **yopiladi**: `delivered`, `payment_status = paid`, `closed_at`, stol
  bo'shaydi (real-time `order.status_changed`). Chek kutilayotgan yoki xato bo'lsa schyot "fiskal kutmoqda"
  (`fiscal.status`: `pending` / `failed`), pul saqlanadi; `POST /pos/bills/{uuid}/fiscal-retry` o'sha chekni qayta
  so'raydi (yangi chek chiqmaydi). Provayder keyinroq tasdiqlasa (`fiscal.receipt_updated`) schyot o'zi yopiladi.
- MXIK'siz qator — 422 `mxik_missing`, pul olinmasdan oldin.
- `POST /pos/orders/{uuid}/close` — uzilib qolgan yopishni yakunlash (kutilayotgan cheklarni qayta so'raydi);
  to'lanmagan bo'lsa 409 `order_not_settled`.

**Sotuv chekidagi to'lovlar (P3-11 `FiscalPayment`):** naqd, karta (`card_type`, `transaction_id`), QR — o'z shakli
bilan; avans qismi — `prepayment: true`, avans olingan shaklda (bo'nak → sotuv, tadqiqot 5-bo'lim); **nasiya qismi —
`credit: true`** (hozir olingan pul emas): chek to'liq summaga chiqadi, qarz to'langanda kredit cheklari (P3-17).
Nasiya qismining to'lov shakli provayder aniqlaguncha `cash` (tadqiqotdagi T4).

### Qaytarish

`POST /pos/refunds {payment_uuid, amount, reason, approval_token?, shift_uuid?}` — `pos.refund` (yangi ruxsat: ega,
menejer) yoki menejer tasdig'i. Faqat **shu smenada** olingan naqd, karta yoki QR to'lov (409 `refund_outside_shift`,
422 `refund_tender_invalid` — avans va nasiya o'z bo'limida), to'lovdan qolganidan ko'p emas (422
`refund_exceeds_payment`), schyotning sotuv cheki chiqqan bo'lsa (409 `bill_not_fiscalized`), naqd — kassadagidan
ko'p emas (409 `not_enough_cash`). Naqd kassadan chiqadi, asl sotuv chekiga bog'langan **qaytarish cheki** so'raladi,
egaga Telegram xabari boradi. To'lov to'liq qaytsa `refunded`, buyurtmaning hamma to'lovi qaytsa buyurtma
`payment_status = refunded`.

### Smena, kassa, X va Z

| Endpoint | Ruxsat | Nima qiladi |
|---|---|---|
| `POST /pos/shifts {opening_cash, note?}` | `pos.open_shift` | shu terminalda smena ochish (faqat PIN sessiyasi; terminalda bitta ochiq smena — 409 `shift_already_open`) |
| `GET /pos/shifts/current?branch_id` | `pos.open_shift`, `pos.close_shift` yoki `pos.take_payment` | terminal: o'z ochiq smenasi va X hisobot. P3-26: oddiy kirish (ega, menejer — web panel, Mini App) — filialning ochiq smenalari: `branch_id` (standart — o'z filiali; filialsiz ega bermasa — ko'radigan hamma filial). Javob `{shift, report, shifts: [{shift, report}]}`: `shift`/`report` — eng oxirgi ochilgani, `shifts` — hammasi (terminalda — faqat o'ziniki). Boshqa do'kon filiali — 404, o'zi ishlamaydigan filial — 403 |
| `GET /pos/shifts?branch_id&status&from&to&limit` | `pos.close_shift` yoki `reports.view` | smenalar ro'yxati (web panel uchun ham) |
| `GET /pos/shifts/{uuid}` | xuddi shu | smena va Z hisobot (`summary`) |
| `GET /pos/shifts/{uuid}/x-report` | xuddi shu | X hisobot |
| `POST /pos/shifts/{uuid}/cash-movements {type, category, amount, note?, employee_id?, approval_token?}` | `pos.cash_in_out` yoki tasdiq | kassaga kirim/chiqim |
| `POST /pos/shifts/{uuid}/close {counted_breakdown, force?, reason?, approval_token?}` | `pos.close_shift` | Z: smenani yopish |

- **Biznes kun** — do'kon vaqt zonasida, filialning `business_day_starts_at` (standart 05:00) dan: 02:00 dagi sotuv
  oldingi kunniki. Smena, hisobot va **navbat raqami** (`orders.queue_number`, har biznes kun 1 dan, filial bo'yicha)
  biznes kun bo'yicha. Yangi POS buyurtmasi biznes kun, navbat raqami va terminalning ochiq smenasini o'zi oladi.
- Kassa harakatlari: `cash_in` (`change_fund`, `owner`, `other`), `cash_out` (`collection`, `owner`, `supplies`,
  `utilities`, `other`), `wage` — xodimga berilgan pul (`daily_wage`, `advance`, `bonus`; `employee_id` majburiy),
  `purchase` — bozorlik (`bazaar`, `meat`, `bread`, `supplies`, `other`). `other` izohsiz bo'lmaydi; chiqim kassadagi
  naqddan oshmaydi (409 `not_enough_cash`). P3-26: javobdagi harakatda (`movement`) id'lar bilan birga X hisobotdagi
  kabi `employee`, `user`, `approver` — `{id, name}` yoki `null`.
- **OpenAPI (P3-26).** X va Z hisobot (`report`, smena tafsilotidagi `summary`) to'liq turlangan; `summary` smena ochiq
  bo'lsa `null`.
- **X hisobot:** to'lov turlari (`by_tender`) va karta turlari (`cards`), kanallar (`by_channel`), ofitsiantlar
  (`by_waiter`: tushum va xizmat haqi), chegirma, o'chirilgan taomlar, qaytarishlar, avans (olingan — shu terminalda;
  ayirilgan; qaytarilgan — shu smenada, `cash` qismi bilan), nasiya savdosi va qarz to'lovlari (musbat summa), kassa
  harakatlari va **kutilgan naqd** (boshlang'ich + naqd sotuv + kirim + naqd avans va qarz to'lovi − qaytarish − naqd
  avans qaytarish (`cash.deposit_refunds`) − chiqim, ish haqi, bozorlik). P3 integratsiya tuzatishi: qarz to'lovlari
  avval manfiy qo'shilardi (nasiya yozuvida to'lov manfiy) — endi to'lov summasi bilan.
- **Z:** `counted_breakdown` — kupyura va soni (`{"100000": 2, "50000": 1}`; 200000 dan 100 gacha), sanalgan naqd va
  farq (`difference` = sanalgan − kutilgan) yoziladi, `z_number` filial bo'yicha ketma-ket, Z hisobot `summary` da
  saqlanadi va egaga Telegram'ga qisqa xulosa boradi. Ochiq POS buyurtmalari (shu smenada ochilgan; filialning oxirgi
  ochiq smenasi bo'lsa — filialning hammasi) bo'lsa 409 `shift_has_open_orders`, cheki kutilayotgan to'lov bo'lsa 409
  `shift_has_pending_receipts`. `force: true` + `reason` — `orders.cancel` (menejer) yoki uning tasdig'i bilan
  majburan yopish; ochiq buyurtmalar keyingi smenaga o'tadi.

### Real-time

`private-pos-branch.{branchId}` (va `branch`, `store`): `order.bill_changed` — schyot tuzildi, chiqarildi, to'landi,
chek holati o'zgardi (`{order: {...}, bill: {uuid, status, fiscal_status} | null, change}`); `shift.changed`
(`pos-branch` va `branch`) — smena ochildi, kassa harakati, yopildi (`{shift: {uuid, status, store_id, branch_id,
device_uuid, business_date, z_number}, change, changed_at}`). Faqat id va holat: ilova API'dan qayta so'raydi.

### Jurnal

`pos.approval_granted`, `pos.approval_used`, `pos.bills_created`, `pos.bill_printed`, `pos.bill_reprinted`,
`pos.payment`, `pos.order_closed`, `pos.refund`, `pos.shift_opened`, `pos.cash_movement`, `pos.shift_closed`.

Testlar: `tests/Feature/PosBilling/*` (bo'lish matematikasi, to'lov turlari, chek chiqdi / kutilmoqda, yopish,
qaytarish, smena va Z, biznes kun, navbat raqami, tasdiqlar, izolyatsiya, idempotentlik, versiya; P3-11 sandbox bilan
integratsiya), `tests/Unit/PosBillingAllocationTest.php`.

## Zal va xona hisobi (P3-12)

Choyxonaning uchta rejimi bitta `orders` qatorida: bronsiz stol yoki tapchan (qisqa hisob), xona (kabinka) sessiyasi
(4–6 soatlik ochiq hisob, bosqichlar, xona puli, minimal summa, zal bo'yicha xizmat haqi) va oldindan buyurtma (bron
"Keldi" bo'lganda P3-17 `PosOrderOpener` orqali ochadi) ([POS_SPEC.md](POS_SPEC.md) 2.4–2.10; tadqiqot R1–R4, R6,
R11, R26). Narx, xizmat haqi va xona pulini **faqat server** hisoblaydi: ilova javobdagi summalarni ko'rsatadi.

### Boshqaruv endpointlari (web panel)

Terminaldagi PIN sessiyasidan kirilmaydi (403). Filialga biriktirilgan xodim faqat o'z filiali (boshqa filial —
403), boshqa do'kon yozuvi — 404. **Zal xaritasini o'zgartirish faqat ega va menejerga** (P3 integratsiya tuzatishi):
`tables.manage` kassir va ofitsiantda ham bor (POS'da stol tanlash, ko'chirish, birlashtirish uchun), shuning uchun
zal va stollarni yaratish, o'zgartirish, o'chirish va xaritani saqlash `tables.manage` **va** ega yoki menejer rolini
talab qiladi (boshqasiga 403). Ega `tables.manage` ni har doim ushlaydi; menejerdan ega uni olib qo'ysa, menejer ham
o'zgartira olmaydi. Ro'yxatlarni (`GET`) `tables.manage` bilan hamma ko'radi.

| Endpoint | Ruxsat | Nima qiladi |
|---|---|---|
| `GET /owner/halls?branch_id` | `tables.manage` | zallar (tartib bilan), har birida o'z xizmat haqi (`service_fee`, yo'q bo'lsa `null` — filialniki) va stollari |
| `POST /owner/halls`, `PATCH /owner/halls/{id}`, `DELETE /owner/halls/{id}` | `tables.manage` + ega yoki menejer | zal yoki zona; `is_active: false` — mavsumiy zona yopiq (xaritadan chiqadi, ochiq buyurtmalar qoladi); ochiq buyurtmali zal o'chmaydi (422 `hall_in_use`) |
| `GET /owner/dining-tables?branch_id&hall_id` | `tables.manage` | stol, xona, tapchanlar |
| `POST /owner/dining-tables`, `PATCH .../{id}`, `DELETE .../{id}` | `tables.manage` + ega yoki menejer | `kind` (`table`, `room`, `tapchan`), `seats`, `layout {x, y, w, h}`, `sort_order`, `is_active`, `min_spend` va `min_spend_mode` (`per_table` yoki `per_person`); nom filialda yagona; ochiq buyurtmali joy o'chmaydi (422 `table_in_use`) |
| `PUT /owner/dining-tables/layout {branch_id, tables: [{id, hall_id?, sort_order?, layout}]}` | `tables.manage` + ega yoki menejer | zal xaritasini bir martada saqlash; boshqa filial stoli bo'lsa hech narsa saqlanmaydi (422) |
| `GET /owner/room-tariffs?branch_id` | `settings.manage` | xona tariflari va filial kalendari (`weekend_days`, `holidays`; P3-26: `holidays` — `pos_settings.holidays` ning o'zi) |
| `POST /owner/room-tariffs`, `PATCH .../{id}`, `DELETE .../{id}` | `settings.manage` | xona puli: `dining_table_id` (bitta xona) yoki `table_kind` (+ ixtiyoriy `hall_id`), `day_type` (`weekday`, `weekend`, `holiday`), `price` (0 — tekin), `valid_from`/`valid_to` |
| `PUT /owner/room-tariffs/calendar {branch_id, weekend_days, holidays}` | `settings.manage` | dam olish kunlari (ISO: 1 — dushanba … 7 — yakshanba; standart 6 va 7) va bayramlar (`Y-m-d`; P3-26: filialning `pos_settings.holidays` iga yoziladi, boshqa sozlamalar o'zgarmaydi) |
| `GET /owner/auto-guest-items?branch_id`, `POST`, `PATCH .../{id}`, `DELETE .../{id}` | `settings.manage` | "choy-non" qoidasi: `product_variant_id`, `quantity_per_guest`, `hall_id` (ixtiyoriy), `is_active` |
| `PUT /owner/service-fees` + `hall_id` | `settings.manage` | zalning o'z xizmat haqi va joy narxi (VIP 15%, o'z-o'ziga xizmat zonasi 0%) |
| `DELETE /owner/service-fees/halls/{id}` | `settings.manage` | zal sozlamasini olib tashlash (filialniki qo'llanadi) |

### POS endpointlari (kassa va ofitsiant)

Oddiy xodim kirishi yoki terminaldagi PIN sessiyasi; filial — xodimniki (PIN sessiyasida terminalniki). Boshqa do'kon
yoki filial buyurtmasi — 404, boshqa filialda ochish — 403. Har o'zgartiruvchi so'rovda `Idempotency-Key` **majburiy**
(yo'q bo'lsa 428 `idempotency_key_required`); takror so'rov birinchi javobni qaytaradi. Buyurtmani o'zgartirishda
`version` (javobdagi oxirgi qiymat): boshqa qurilma o'zgartirib ulgurgan bo'lsa 409 `version_conflict` va
`data.version` — ilova buyurtmani qayta yuklaydi. Har javob — buyurtmaning to'liq ko'rinishi (pastda).

| Endpoint | Ruxsat | Nima qiladi |
|---|---|---|
| `GET /pos/floor?branch_id` | `orders.view` | zal xaritasi: zallar → joylar, har birida `status` va ochiq buyurtmalar; `untabled_orders` — stolsiz va olib ketish |
| `GET /pos/orders?scope=open&branch_id` | `orders.view` | ochiq buyurtmalar navbati — barcha kanallar (pastda) |
| `GET /pos/orders?scope=incoming&branch_id` | `orders.view` | P3-26: qabul qilinishini kutayotgan onlayn buyurtmalar (`new`; Telegram, ilova, agregator), eskisi birinchi |
| `GET /pos/orders?scope=closed&business_date&branch_id` | `orders.view` | P3-26: bir biznes kunda yopilgan (to'langan) POS buyurtmalari, yangisi birinchi, ko'pi bilan 300 — qaytarish uchun ro'yxat (standart — filialning hozirgi biznes kuni) |
| `POST /pos/orders/{uuid}/accept {version?}` | `orders.manage` | P3-26: onlayn buyurtmani qabul qilish (`new` → `accepted`; mijozga xabar, oshxonaga tushadi). Qabul qilingan — 200 o'zgarishsiz; POS buyurtmasi — 422 `not_online_order` |
| `POST /pos/orders/{uuid}/reject {reason, cancel_reason?, approval_token?, version?}` | `orders.cancel` yoki `orders.manage` + menejer tasdig'i | P3-26: onlayn buyurtmani rad etish (`new` → `rejected`). `reason` — mijoz va ega o'qiydigan matn; `cancel_reason`: `customer_request`, `out_of_stock`, `store_closed`, `courier_unavailable`, `other` (standart). Tasdiqsiz — 403 `approval_required` |
| `POST /pos/orders/{uuid}/request-bill {version?, approval_token?}` | `orders.manage` (+ ofitsiant qoidasi) | P3-26: ofitsiantning "Schyot so'rash": `bill_requested_at`/`bill_requested_by`, `order.bill_requested` hodisasi; schyot bu yerda tuzilmaydi. Takroriy — 200 o'zgarishsiz |
| `GET /pos/orders/{uuid}` | `orders.view` | bitta POS buyurtma |
| `POST /pos/orders {branch_id?, channel, table_id?, guests_count, waiter_id?, external_id?, note?, items?, send?}` | `orders.manage` | buyurtma ochish: `pos_hall` (stol, xona, tapchan yoki stolsiz) yoki `pos_takeaway` |
| `POST /pos/orders/{uuid}/rounds {version, items, fire_mode?, send?}` | `orders.manage` | yangi bosqich (`round_no`, kim qo'shdi, qachon) |
| `POST /pos/orders/{uuid}/send {version, item_ids?}` | `orders.manage` | oshxonaga yuborish |
| `POST /pos/orders/{uuid}/items/{id}/void {version, reason?, approval_token?, restock?}` | `orders.manage` (+ pastdagi qoida) | qatorni o'chirish ("O'CHIRILDI") |
| `POST /pos/orders/{uuid}/guests {version, guests_count}` | `orders.manage` | mehmonlar soni |
| `POST /pos/orders/{uuid}/move {version, table_id}` | `tables.manage` | boshqa stol yoki xonaga ko'chirish |
| `POST /pos/orders/{uuid}/merge {version, source_uuid, source_version}` | `tables.manage` | ikkinchi buyurtmani shu buyurtmaga qo'shish |
| `POST /pos/orders/{uuid}/charges/{charge}/waive {version, reason, approval_token?}` | `orders.manage` + `orders.discount` yoki tasdiq | `service_charge`, `place_fee`, `room_fee` yoki `min_spend_topup` ni sabab bilan olib tashlash |
| `POST /pos/orders/{uuid}/charges/{charge}/restore {version}` | `orders.manage` | olib tashlangan qatorni qaytarish |
| `POST /pos/orders/{uuid}/items/{id}/served {version}` | `orders.manage` | taom mehmonga berildi (`ready` → `served`) |
| `POST /pos/orders/{uuid}/cancel {version, reason, approval_token?}` | `orders.cancel` yoki `orders.manage` + menejer tasdig'i | butun buyurtmani bekor qilish (pastda) |

O'zgartiruvchi har POS endpointi ixtiyoriy `approval_token` ham qabul qiladi (ofitsiant qoidasi, pastda).

**Ochish.** Xonada bir vaqtda bitta ochiq buyurtma (ikkinchisi 422 `table_occupied` — birlashtiring); stol va
tapchanda bir nechta. Yopiq zona yoki faol bo'lmagan joy — 422 `table_inactive`. `guests_count` zalda kamida 1.
`waiter_id` berilmasa, ochgan xodim ofitsiant bo'lsa — u. `external_id` — qurilmaning o'z raqami: shu raqam bilan
qayta yuborilsa o'sha buyurtma qaytadi. POS buyurtmasi `accepted` holatda ochiladi (`order_type`: zal — `dine_in`,
olib ketish — `pickup`); `user_id` — ochgan xodim (POS buyurtmasida mijoz akkaunti yo'q, unga Telegram xabari
bormaydi). Komissiya olinmaydi.

**Qatorlar** (`items[]`): `variant_id` (uuid), `quantity` (kg va kasr — menyu qadami va eng kam miqdori bilan,
[MENU.md](MENU.md)), `modifiers`, `note`, `seat_no`, `fire_mode` (`now` — keyingi yuborishda, `hold` — faqat id bilan
yuborilganda), `due_at` (oldindan buyurtma vaqti). Narx — kanal narxi (`pos_hall` / `pos_takeaway`). Stop-list,
filialda yashirilgan taom, qoldiq, qozon (`menu_batches`: ochiq qozondan `batch_consumption` bo'yicha ayiriladi,
yetmasa yoki qozon yopiq bo'lsa "tugadi") — 422. Fiskal rejimda MXIK'siz taom — 422 `mxik_missing` (P3-11).

**Choy-non.** Zal buyurtmasi ochilganda va mehmon qo'shilganda har mehmonga `quantity_per_guest` (donali taomda
yuqoriga yaxlitlanadi: 0.5 choynak × 3 mehmon = 2) avtomatik qator bo'lib qo'shiladi (ochishda 1-bosqichda,
keyin yangi bosqich sifatida). Zalning qoidasi filialning shu taom qoidasini almashtiradi. Mehmon kamaysa hech narsa
olib tashlanmaydi. Sotib bo'lmaydigan taom (stop-list, tugagan) jim o'tkazib yuboriladi.

**Oshxonaga yuborish.** `item_ids` siz — yuborilmagan hamma `now` qator; `item_ids` bilan — aynan shular (`hold`
qatorlar ham). Har qatorga sex (`station_id`, P3-13 marshruti) va `sent_at`; birinchi yuborish buyurtmani
`preparing` qiladi. Yangi qator yo'q — 422 `nothing_to_send`. Oshxona ekrani (`GET /staff/kitchen/tickets`) POS
qatorini faqat yuborilgandan keyin (o'chirilgani ham — `void`) yoki `hold` + `due_at` bo'lsa (oldindan buyurtma
navbati) ko'radi; yuborilmagan qatorni "tayyor" deb bo'lmaydi (422 `kitchen_status`). POS qatorini qaytarish
(`recall`) uni `sent` ga qaytaradi.

**Qator holati** (`kitchen_status`): POS qatori `pending` (faqat buyurtmada) → `sent` → `ready` → `served`; `void` —
o'chirilgan. Onlayn buyurtma qatorlari avvalgidek `pending` → `ready`. Xodim buyurtmasi va oshxona chiptasida POS
qatorida qo'shimcha `round_no`, `fire_mode`, `station_id`, `sent_at`, `due_at`, `seat_no`, `note`, `voided_at`,
`void_reason` (onlayn qator shakli o'zgarmagan).

**O'chirish.** Oshxonaga yuborilmagan qator — `orders.manage`, sabab ixtiyoriy, qoldiq va qozon ulushi qaytadi.
Yuborilgan qator yoki schyot chiqarilgan (`bill_locked_at`, P3-18) buyurtma — `reason` majburiy va `orders.void_item`
yoki menejer tasdig'i (`approval_token`, `POST /pos/approvals`); yo'q bo'lsa 403 `approval_required`, yaroqsiz bo'lsa
403 `approval_invalid` (yoki P3-18 sababi: `approval_expired`, `approval_used`, ...). Yuborilgan qatorning ulushi
faqat `restock: true` bo'lsa qaytadi (pishmagan bo'lsa). Allaqachon o'chirilgan — 422 `already_void`.

**Ko'chirish va birlashtirish.** Ko'chirishda yangi zalning xizmat haqi, yangi joyning xona puli va minimal summasi
qo'llanadi (kun turi — buyurtma ochilgan biznes kun bo'yicha); band xonaga — 422 `table_occupied`, o'sha stolga — 422
`same_table`, olib ketish — 422 `not_hall_order`. Birlashtirishda `source_uuid` qatorlari bosqichlari bilan (targetdan
keyin) ko'chadi, mehmonlar qo'shiladi, manba buyurtma `canceled` bo'ladi (izoh: "#… buyurtmasiga qo'shildi", stok
qaytmaydi — qatorlar yashashda davom etadi). Ikkalasi bitta filialning ochiq zal buyurtmasi, schyot chiqmagan
(422 `bill_locked`), to'lovsiz (422 `has_payments`); boshqa filial — 422 `merge_not_allowed`.

Yopilgan yoki bekor qilingan buyurtma — 422 `order_closed`; Telegram yoki mobil buyurtma — 422 `not_pos_order`.

**Ochiq buyurtmalar navbati** (P3 integratsiya tuzatishi; POS_SPEC 2.5). `GET /pos/orders?scope=open` — filialning
(standart: xodimniki, PIN sessiyasida terminalniki; `branch_id` bilan boshqasi, ruxsat bo'lsa) hamma ochiq
buyurtmasi, eskisi birinchi, ko'pi bilan 300: POS buyurtmalari (`accepted`, `preparing`, `ready`, yopilmagan —
to'langan, lekin cheki kutilayotgani ham) va qabul qilingan onlayn buyurtmalar (Telegram, mobil, agregator:
`accepted`, `preparing`, `ready`; `new` holatdagilar emas). Har birida `uuid`, `short_code`, `status`, `channel`,
`type`, `version`, `hall`, `table` (`{id, name, kind}`), `hall_ref`, `table_ref`, `guests_count`, `waiter`,
`items_count` (o'chirilmagan qatorlar), `subtotal`, `total_price`, `payment_status`, `bill_locked`,
`fiscal_pending` (schyot to'langan, cheki `pending`/`failed` — "fiskal kutmoqda"), `booking_id`, `opened_at`,
`updated_at`. `scope` hozircha faqat `open` (boshqasi 422).

**Berildi.** `POST /pos/orders/{uuid}/items/{id}/served` — oshxona "tayyor" degan qator (`ready`) yoki ekransiz sexga
yuborilgan qator (`sent`, masalan choy) `served` bo'ladi; `kitchen.item_status_changed` yuboriladi. Yuborilmagan yoki
o'chirilgan qator — 422 `item_not_ready`, ikkinchi marta — 422 `already_served`.

**Butun buyurtmani bekor qilish.** `POST /pos/orders/{uuid}/cancel {version, reason, approval_token?}` — `reason`
majburiy; `orders.cancel` (ega, menejer) yoki menejerning `orders.cancel` uchun bir martalik tasdig'i (yo'q bo'lsa 403
`approval_required`). Buyurtmada to'lov bo'lsa — **409 `has_payments`** (`data.paid_amount`): avval to'lovni
qaytaring (`POST /pos/refunds`, qaytarish cheki bilan). Bekor qilishda: buyurtmadagi har qator `void` bo'ladi (sabab
— bekor qilish sababi) — oshxonaga yuborilganlari uchun sexga "O'CHIRILDI" (`kitchen.item_status_changed`, ulushi
qaytmaydi — pishgan), yuborilmaganlarining qoldig'i va qozon ulushi qaytadi; ochiq yoki chiqarilgan schyotlar `void`;
buyurtma `canceled` (stol yoki xona bo'shaydi), `cancelled_by` — xodim; bron buyurtmasi bo'lsa bron `completed`
bo'ladi, avanslari `taken` qoladi — keyin qaytariladi yoki ushlab qolinadi (P3-17). Jurnal `pos.order_cancelled`
(summa, sabab, kim tasdiqladi, nechta qator), egaga xabar. `ready` holatdagi olib ketish buyurtmasini ham bekor qilish
mumkin.

**Ofitsiant qoidasi** (POS_SPEC 11). Roli ofitsiant bo'lgan xodim faqat **o'z** buyurtmasini o'zgartiradi
(`waiter_id` — u). Ofitsianti yo'q buyurtma uning birinchi o'zgarishida (odatda bosqich qo'shishda) unga o'tadi.
Boshqa ofitsiantning buyurtmasi — ega yoki menejerning `orders.manage` uchun bir martalik tasdig'i bilan
(`approval_token`); yo'q bo'lsa 403 `approval_required` (`data.permission: "orders.manage"`, `data.waiter_id`),
kassir yoki boshqa ofitsiant tasdiqlasa — 403 `approver_not_allowed`. O'z ruxsati uchun tasdiq talab qiladigan amal
(yuborilgandan keyin o'chirish, xizmat qatorini olib tashlash, bekor qilish) menejer tasdig'i bilan bo'lsa, ikkinchi
tasdiq kerak emas. Qoida bosqich, yuborish, o'chirish, mehmonlar, ko'chirish, birlashtirish (ikkala buyurtma),
xizmat qatorlari, "berildi", bekor qilish va schyot (tuzish, chiqarish) uchun. Ega, menejer va kassirga ta'sir qilmaydi.

**Egaga ogohlantirishlar (R20).** Do'konning admin chatiga va har egaga — xabar turi `pos_alerts` (Telegram va push,
egasining `/me/notification-preferences` sozlamasi bo'yicha, [PUSH.md](PUSH.md)), o'zgarish saqlangandan keyin.
Chegaralar filialning `pos_settings.alerts` da (P3-13; `null` — o'chiq): oshxonaga yuborilgan qator `void_amount` dan
qimmat o'chirilsa; xizmat qatori (xona puli, minimal summa farqi, xizmat haqi, joy narxi) olib tashlansa — har doim;
buyurtma bekor qilinsa — har doim (bron avansi qolgan bo'lsa, u ham yoziladi); zal buyurtmasi `open_room_hours` dan
uzoq ochiq bo'lsa — har buyurtmaga **bir marta** (`pos:alert-long-open`, har 10 daqiqada; `orders.long_open_alerted_at`).
`discount_percent` — chegirma endpointi qo'shilganda (hozir POS'da chegirma endpointi yo'q). Avans qaytarish va ushlab
qolish, nasiya xabarlari P3-17 dan (xuddi shu kanal bilan).

### Xona puli, minimal summa va xizmat haqi

Har qator, mehmon, stol yoki chegirma o'zgarishida qayta hisoblanadi va buyurtmaga yoziladi
(`App\Domain\Pos\Orders\PosChargesCalculator`):

```
taomlar           = o'chirilmagan qatorlar yig'indisi                       (subtotal)
chegirmadan keyin = max(0, taomlar − discount)
xizmat haqi       = chegirmadan keyin × foiz, butun so'm      (service_charge_amount; zal → filial → do'kon foizi)
joy narxi         = mavjud sozlama: kishi boshiga × mehmon yoki stol uchun              (place_fee_amount)
xona puli         = joyning tarifi, buyurtma ochilgan biznes kun turi bo'yicha           (room_fee_amount)
minimal summa     = min_spend (per_person bo'lsa × mehmon)
qo'shimcha        = max(0, minimal summa − chegirmadan keyin), butun so'm                 (min_spend_topup_amount)
jami              = chegirmadan keyin + xizmat haqi + joy narxi + xona puli + qo'shimcha  (total_price)
```

- **Biznes kun** — do'kon vaqt zonasida, filialning `business_day_starts_at` (standart 05:00) dan: yakshanba 03:30
  shanba kuniga tegishli. **Kun turi:** bayram (filialning yagona ro'yxati `pos_settings.holidays`, P3-26), dam olish kuni
  (`weekend_days`), aks holda hafta ichi.
- **Tarif tanlash:** eng aniqi — joyning o'zi, keyin zaldagi joy turi, keyin filialdagi joy turi; har darajada
  bayramning o'z narxi bo'lmasa — dam olish kuni narxi, u ham bo'lmasa — hafta ichi narxi. Tarif yo'q — xona puli 0.
- Xona puli va minimal summa faqat `pos_hall` va stol bilan; xizmat haqi va joy narxi sozlamadagi kanallar bo'yicha
  (standart faqat `pos_hall`). Telegram va mobil buyurtmaga hech qachon qo'llanmaydi.
- Olib tashlangan qator 0 bo'ladi; `charges.waived` va `charges.waivers` (kim, sabab, kim tasdiqladi) ko'rsatadi.

**Javob shakli** (har POS endpointi): `uuid`, `short_code`, `status`, `channel`, `type`, `version`, `hall`, `table`
(`{id, name, kind}`), `guests_count`, `waiter`, `opened_by`, `device_id`, `booking_id`, `bill_locked`, `opened_at`,
summalar (`subtotal`, `discount`, `service_charge_amount`, `place_fee_amount`, `room_fee_amount`,
`min_spend_topup_amount`, `total_price`), `charges` (`service_charge_percent`, `room_tariff_id`, `business_date`,
`day_type`, `min_spend`, `waived`, `waivers`, `total`), `rounds` va `items` (har qator: `round_no`, `added_by`,
`added_at`, `fire_mode`, `due_at`, `seat_no`, `note`, `kitchen_status`, `station_id`, `sent_at`, `ready_at`,
`voided_at`, `voided_by`, `void_reason`, narxlar).

### Zal xaritasi holatlari

`free` — bo'sh; `reserved_soon` — bo'sh, bron bir soat ichida boshlanadi (`reservation`); `occupied` — ochiq buyurtma;
`overdue` — buyurtma filial chegarasidan uzoq ochiq (`pos_settings.alerts.open_room_hours`, standart 6 soat);
`bill_printed` — schyot chiqarilgan (mehmonlar to'lamoqda). Har buyurtmada `opened_at`, `open_minutes`,
`guests_count`, `waiter`, `total_price`, `items_count`, `unsent_count`, `version`. Yopiq zona va faol bo'lmagan joy
ochiq buyurtmasi bo'lmasa ko'rinmaydi.

P3-26: buyurtmada `ready_count` (oshxona tayyor degan, hali berilmagan qatorlar), `unsent_count` (oshxonaga
yuborilmagan qatorlar), `bill_requested` va `bill_requested_at`; joyda `bill_requested` (biror buyurtmasi schyot
so'ragan va schyoti hali chiqarilmagan — holat qiymatlari o'zgarmagan, bu alohida belgi). `reservation` da
`contact_name` va `contact_phone` — faqat `orders.manage` yoki `bookings.manage` huquqi borlarga (boshqalarga `null`).
`layout` — to'r katakchalari (bootstrap'dagi kabi, web panel 40×24).

`GET /pos/orders` qatorlarida P3-26 dan `bill_requested` va `online`: onlayn buyurtmaning doska kartochkasi
(`GET /staff/orders/{uuid}` shaklida: taomlar, to'lov; mijoz va manzil — `orders.manage` bilan), POS buyurtmasida `null`.
`GET /pos/orders/{uuid}` da `bill_requested_at`, `booking_uuid` va `deposit_available` (bronning ishlatilmagan avansi).

### Real-time

`private-pos-branch.{branchId}` (va `branch`, `store`): **`order.items_changed`** — bosqich qo'shildi, yuborildi,
o'chirildi, mehmonlar, ko'chirish, birlashtirish, xizmat qatori o'zgardi:
`{order: {...}, version, table_id, change, item_ids}` (`change`: `round_added`, `sent`, `voided`, `guests_changed`,
`moved`, `merged`, `charges_changed`, `served`, `cancelled`). P3-26: **`order.bill_requested`** — ofitsiant schyot
so'radi (`{order, version, table_id, bill_requested_at, requested_by}`). Ochishda `order.created`, birinchi yuborishda va birlashtirilgan manba
buyurtmada `order.status_changed`, yuborilgan qator o'chirilganda `kitchen.item_status_changed` (`void`). Faqat id va
holat: ilova buyurtmani (`GET /pos/orders/{uuid}`) yoki xaritani qayta so'raydi.

### Jurnal

`pos.order_opened`, `pos.round_added`, `pos.items_sent`, `pos.item_voided` (sabab, summa, kim tasdiqladi),
`pos.guests_changed`, `pos.order_moved`, `pos.orders_merged`, `pos.charge_waived`, `pos.charge_restored`,
`pos.item_served`, `pos.order_cancelled`, `pos.bill_requested` (P3-26);
boshqaruv: `pos.hall_*`, `pos.table_*`, `pos.layout_saved`, `pos.room_tariff_*`, `pos.room_calendar_changed`,
`pos.auto_guest_item_*`. Terminal (qurilma) har yozuvda.

Testlar: `tests/Feature/PosOrderFlowTest.php`, `PosChargesTest.php`, `PosHallManagementTest.php`,
`PosHallIsolationTest.php`, `PosOrderIntegrationFixesTest.php` (bekor qilish, navbat, "berildi", ofitsiant qoidasi,
ogohlantirishlar, zal xaritasi huquqi, prune), `PosChoyxonaEveningEndToEndTest.php` (hamma haqiqiy amalga oshirish
bilan to'liq kechqurun: bron → avans → keldi → bosqichlar → bo'lingan schyot → sotuv cheklari → nasiya → X → Z;
fiskal rejim o'chiq, MXIK'siz menyu bilan ham).

## 2-to'lqinda topilgan POS kamchiliklari (P3-26)

Kassa, ofitsiant, KDS, web panel va Mini App ishlaganda topilgan backend bo'shliqlari yopildi va mijozlardagi
aylanma yo'llar olib tashlandi. Tafsilotlar tegishli bo'limlarda ("P3-26" belgisi bilan):

- **Fiskal chek kassaga** — `fiscal.receipt` (to'lov, qaytarish, avans, qarz to'lovi) va `GET /pos/fiscal-receipts/{uuid}`.
- **Buyurtmalar** — `scope=incoming|closed`, `POST /pos/orders/{uuid}/accept|reject|request-bill`, `order.bill_requested`,
  `booking_uuid`/`deposit_available`, zal xaritasida `ready_count`, `unsent_count`, `bill_requested`, bron kontakti.
- **Oshxona** — `due_at` li ushlab turilgan qatorni oldindan "tayyor"; `GET /staff/kitchen/tickets?canceled_within=`.
- **Sozlash** — `products.quick_add`, bootstrap `quick_items`, turlangan `GET /pos/catalog`, `PATCH /owner/products/{id}/fiscal`,
  yagona bayramlar ro'yxati (`pos_settings.holidays`).
- **Smena** — ega/menejer uchun `GET /pos/shifts/current?branch_id`, kassa harakatida `{id, name}`.
- **Nasiya** — kassir hisob ochish/o'zgartirishda menejer `approval_token` i.
- **Umumiy** — 422 da `data.reason = "validation"`, OpenAPI'da hamma `Idempotency-Key` sarlavhalari va POS shakllari
  (bootstrap, katalog, X/Z hisobot, sex/printer/yo'naltirish tanalari, avans qaytarish va bron tasdiqlash tanalari),
  bron qatorida `product_variant_uuid`.

Migratsiyalar (qo'shimcha, qaytariladigan): `2026_10_27_100100_add_bill_requested_to_orders_table`,
`2026_10_27_100200_add_quick_add_to_products_table`, `2026_10_27_100300_move_room_calendar_holidays_to_pos_settings`.

Keyinga qoldirildi: tez-tez sotiladigan taomlar ("Tez-tez") — hozircha qurilmaning o'z hisobi.

Testlar: `tests/Feature/PosBackendGapsOrdersTest.php`, `PosBackendGapsKitchenTest.php`, `PosBackendGapsSetupTest.php`,
`PosBilling/PosFiscalSnapshotTest.php`, `PosBilling/PosBackendGapsShiftsTest.php`,
`PosBooking/PosBookingFiscalSnapshotTest.php`, `ApiDocsSpecTest`.

## Internetsiz rejim (P3-31)

Kassa internet yo'qligida cheklangan rejimda ishlaydi (POS_SPEC §5): amallar qurilmadagi navbatga yoziladi va aloqa
tiklanganda `POST /pos/sync` bilan tartib bo'yicha yuboriladi. Server haqiqat manbai, lekin bo'lib o'tgan sotuv va
olingan pul hech qachon rad etilmaydi.

| Metod | Yo'l | Kim | Vazifasi |
|---|---|---|---|
| POST | `/pos/sync` | kassa terminali (`auth:device`) | navbatdagi amallar to'plami (≤ 50) |
| GET | `/pos/sync/status` | kassa terminali | aloqa tekshiruvi, navbat hisoboti, shu kassaning xatolari |
| GET | `/pos/sync/errors` | xodim, `orders.view` | "Sinxronlash xatolari" ro'yxati |
| POST | `/pos/sync/errors/{opId}/resolve` | xodim, `orders.cancel` | xatoni "hal qilindi" yoki "yopildi" deb belgilash |

Terminal so'rovlari `throttle:pos-sync` (qurilmaga daqiqada 120) bilan. Faqat POS turidagi terminal tokeni sinxronlaydi,
aks holda 403 `terminal_required`.

### POST /pos/sync

`Idempotency-Key` ixtiyoriy (har amal o'z `op_id` si bilan baribir idempotent).

```json
{
  "sent_at": "2026-10-22T18:00:05Z",
  "queue": {"size": 12, "oldest_at": "2026-10-22T16:41:00Z"},
  "operations": [
    {"op_id": "uuid", "seq": 41, "type": "order.open", "occurred_at": "2026-10-22T16:41:00Z",
     "staff_id": 7, "payload": {"order": "uuid", "local_number": "K1-0041", "channel": "pos_hall", "table_id": 3,
     "guests_count": 4, "items": [...], "send": true, "menu_taken_at": "2026-10-22T08:00:00Z"}}
  ]
}
```

- `operations` — 1…50 ta; `op_id` (UUID) va `seq` to'plam ichida takrorlanmaydi. Server ularni `seq` bo'yicha
  tartiblab birma-bir qo'llaydi, har biri o'z tranzaksiyasida.
- `staff_id` — amalni bajargan xodim: shu do'kon/filialda kassa roli, shu kassada 36 soat ichida PIN bilan kirgani
  (`staff.session_started` auditi) va amal uchun huquq tekshiriladi. Bo'lmasa amal `rejected`
  (`staff_invalid`, `staff_not_signed_in`, `permission_denied`).
- `sent_at` — qurilma soati; server farqni hisoblaydi (`clock_skew_seconds`), 300 soniyadan oshsa `clock_warning`.
- `queue` — qurilmadagi navbat holati: menejer paneli uchun `devices.sync_pending_ops`, `sync_oldest_op_at`.

Amal turlari va `payload`:

| Tur | Huquq | Payload |
|---|---|---|
| `order.open` | `orders.manage` | `order` (qurilma UUID'si → `external_id`), `local_number`, `channel` (`pos_hall`/`pos_takeaway`), `table_id?`, `guests_count?`, `waiter_id?`, `note?`, `items?`, `send?`, `menu_taken_at?` |
| `order.round` | `orders.manage` | `order`, `items`, `send?`, `menu_taken_at?` |
| `order.send` | `orders.manage` | `order`, `lines?` (qator id'lari; bo'lmasa hamma yuborilmagan "hozir" qatorlar) |
| `order.void` | `orders.manage` | `order`, `line`, `reason?` |
| `order.served` | `orders.manage` | `order`, `line` |
| `bill.create` | `orders.manage` + `pos.take_payment` | `order`, `bill` (qurilma UUID'si), `expected_total?` |
| `bill.pay` | `pos.take_payment` | `order`, `bill`, `shift_uuid?`, `tenders`: `[{tender: cash\|card, amount, tendered_amount?, card_type?, transaction_id?}]` |
| `order.close` | `pos.take_payment` | `order` |

`order` va `bill` — qurilma bergan UUID yoki serverdagi UUID (onlayn ochilgan buyurtma). `items[]` — onlayn
buyurtmadagi kabi qator, qo'shimcha: `line_id` (qurilma UUID'si → `order_items.client_line_id`), `unit_price`
(qurilma ko'rgan narx), `modifiers[].price_delta`. Har amalda ixtiyoriy `idempotency_key` — uzilishdan oldin yuborilgan
onlayn so'rovning kaliti: server o'sha so'rovni bajargan bo'lsa amal qayta qo'llanmaydi, o'sha natija qaytadi.

Javob (200):

```json
{"server_time": "...", "clock_skew_seconds": 4, "clock_warning": false, "stopped_at": null,
 "results": [{"op_id": "uuid", "seq": 41, "type": "order.open", "status": "applied", "reason": null,
   "message": null, "duplicate": false,
   "refs": {"order_uuid": "...", "order_version": 3, "local_number": "K1-0041", "items": {"<line_id>": 812}}}],
 "device": {"uuid": "...", "name": "Kassa 1", "prefix": "K1", "last_seq": 41, "pending_ops": 0, "oldest_op_at": null,
   "reported_at": "...", "clock_skew_seconds": 4, "clock_warning": false}}
```

- `status`: `applied`, `conflict` (qo'llandi, lekin menejer ko'rishi kerak — `refs.flags`), `rejected` (qo'llanmadi,
  "Sinxronlash xatolari"ga tushadi; `reason` va o'zbekcha `message`).
- Bir `op_id` ikkinchi marta kelsa — saqlangan natija `duplicate: true` bilan (javob yo'qolgan holat).
- `refs`: `order_uuid`, `order_version`, `local_number`, `items` (qator id → server id), `bill_uuid`, `bill_total`,
  `payments`, `fiscal_status`, `sent`, `closed`, `flags`.
- `stopped_at` — baza xatosi tufayli to'xtagan `seq`: shu va keyingi amallar javobda yo'q, navbatda qoladi.
- 409: `sync_in_progress` (shu kassaning boshqa so'rovi ketyapti), `op_id_conflict` (amal raqami boshqa qurilmaniki),
  `seq_reused` (shu `seq` boshqa amalga berilgan).

Rad etish sabablari (`reason`): `order_not_found`, `order_closed`, `line_not_found`, `line_sent` (o'chirilayotgan qator
shu orada oshxonaga ketgan), `bill_not_found`, `bill_paid`, `bill_void`, `items_required`, `tenders_required`,
`tender_not_offline` (oflayn faqat naqd va karta), `amount_invalid`, `channel_invalid`, `type_unknown`,
`terminal_without_branch`, xodim sabablari.

### Ziddiyat qoidalari

- **Bir amal ikki marta** (`op_id`) yoki **bir buyurtma ikki marta** (`external_id`) — bir marta qo'llanadi.
- **Buyurtma versiyasi** solishtirilmaydi (oshxona "tayyor"i ham versiyani oshiradi): amal mazmunan sig'masa rad
  etiladi — buyurtma yopilgan/bekor qilingan, qator shu orada yuborilgan yoki o'chirilgan.
- **Joy band** (`table_occupied`, `table_inactive`, `table_missing`) — mehmonlar xizmat olgan: buyurtma joysiz
  ochiladi, `conflict`.
- **Narx** — qurilma narxi saqlanadi (`order_items.price_source = offline_snapshot`), `offline_price` belgisi (ma'lumot uchun).
  Menyu nusxasi 24 soatdan eski bo'lsa `stale_menu` — `conflict`. Serverda sotuvdan olingan taom —
  `sold_while_unavailable`.
- **Qozon/qoldiq yetmadi** — sotuv saqlanadi, qoldiq 0 dan pastga tushmaydi, kamomad
  `menu_batches.oversold_quantity` ga yoziladi (`oversold`, `stock_short`) — `conflict`.
- **Pul** hech qachon rad etilmaydi: schyot shu orada to'langan yoki buyurtma bekor qilingan bo'lsa to'lov baribir
  yoziladi va buyurtma `refund_needed` (bekor qilingan) yoki `payment_unallocated` deb belgilanadi (audit
  `pos.offline_payment_unallocated`). Server hisobidan kam to'langan — `underpaid`, ko'p — `overpaid` (naqd ortig'i
  schyotga sig'diriladi). `bill.create` dagi `expected_total` farqi — `amount_mismatch` (ma'lumot uchun).
- `local_number` band bo'lsa — `local_number_taken` (ma'lumot uchun), buyurtma raqamsiz ochiladi.

Belgilar buyurtmaning `sync_flags` ida ham saqlanadi va buyurtma JSON'ida `offline` blokida ko'rinadi:
`{"local_number": "K1-0041", "created_at": "...", "synced_at": "...", "flags": [...]}` (oflayn ochilmagan buyurtmada
`null`).

**Fiskal chek** sinxronlashda, tranzaksiyadan keyin ro'yxatdan o'tadi; sotuv vaqti sifatida oflayn to'lov vaqti
(`payments.offline_paid_at` → `fiscal_receipts.sold_at`) yuboriladi. Provayder javob bermasa chek odatdagidek
"fiskal kutmoqda" navbatida qoladi.

### GET /pos/sync/status

Aloqa tekshiruvi (kassa oflaynda har 15–60 soniyada chaqiradi). `queue_size`, `oldest_at` query parametrlari —
navbat hisoboti. Javob: `server_time`, `device` (yuqoridagi kabi), `errors` (shu kassaning ko'rib chiqilmagan
`conflict`/`rejected` amallari, 100 tagacha) va `errors_count`.

### GET /pos/sync/errors va resolve

`GET /pos/sync/errors?branch_id=&device=&all=&page=` — yangisi birinchi, sahifada 50 ta; `all=1` hal qilinganlarini
ham. Har qator: `op_id`, `seq`, `type`, `type_label`, `status`, `reason`, `message`, `staff`, `device`, `occurred_at`,
`received_at`, `applied_at`, `clock_skew_seconds`, `order` (`uuid`, `local_number`, `short_code`, `table_ref`,
`status`), `payload`, `refs`, `resolved_at`, `resolved_by`, `resolution`, `resolution_note`.

`POST /pos/sync/errors/{opId}/resolve` `{"resolution": "resolved"|"dismissed", "note": "..."}` — audit
`pos.sync_error_resolved` / `pos.sync_error_dismissed`. `Idempotency-Key` ixtiyoriy.

### Bootstrap `offline` qismi

`GET /pos/bootstrap` da `offline`: `device_prefix` (kassaga bir marta beriladi: `K1`, `K2`…), `next_local_number`,
`business_date`, `day_type`, `fees[]` (`hall_id`, `channel`, `service_charge_percent`, `place_fee_amount`,
`place_fee_mode`), `places[]` (`table_id`, `hall_id`, `room_fee`, `min_spend`, `min_spend_mode`), `guest_items[]`
(`hall_id`, `variant_uuid`, `quantity_per_guest`, `sort_order`) va `limits` (`batch_size` 50, `warn_after_minutes` 15,
`alert_after_minutes` 120, `max_queued_orders` 500, `menu_max_age_hours` 24, `contact_max_age_hours` 24,
`fiscal_warn_hours` [24, 40], `fiscal_limit_hours` 48, `clock_skew_warn_seconds` 300).

### Smenani yopish

`POST /pos/shifts/{uuid}/close` yangi 409 sabablari (ikkalasi ham `force` bilan o'tkaziladi):

- `shift_has_unsynced_ops` — kassa navbatida serverga yetmagan amallar bor (`devices.sync_pending_ops > 0`); boshqa
  tekshiruvlardan oldin.
- `shift_has_sync_errors` — smena ochilgandan beri ko'rib chiqilmagan rad etilgan amallar bor.

Migratsiyalar (qo'shimcha, qaytariladigan): `2026_10_28_100000_create_pos_sync_operations_table`,
`2026_10_28_100100_add_offline_columns_to_orders_tables` (orders: `local_number`, `offline_created_at`, `synced_at`,
`sync_flags`; order_items: `client_line_id`, `price_source`; payments: `offline_paid_at`, `synced_at`; menu_batches:
`oversold_quantity`; devices: `sync_*`; fiscal_receipts: `sold_at`).

Testlar: `tests/Feature/PosSync/PosSyncFlowTest.php`, `PosSyncConflictsTest.php`, `PosSyncAccessTest.php`.
