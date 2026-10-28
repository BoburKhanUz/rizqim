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
| RIZQIM jamoasi (P4-11, Boshqaruv markazi) | email + parol, keyin TOTP kod (2FA) | `POST /admin/auth/login` → `POST /admin/auth/2fa/verify` (birinchi marta `2fa/setup` → `2fa/confirm`) | admin sessiyasi: 12 soat, 60 daqiqa harakatsizlikda tugaydi; faqat `/admin/*` uchun ("Admin API" bo'limi) |

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

**Query'dagi mantiqiy qiymatlar (P4-01).** GET so'rovida `boolean` parametr (`include_revoked`, `include_canceled`,
`debtors`, `include_closed`...) `1` yoki `0` bilan yuboriladi. `true`/`false` matni 422 beradi (Laravel `boolean` qoidasi
faqat `1`, `0`, `"1"`, `"0"` va JSON `true`/`false` ni qabul qiladi). `@rizqim/api-client` mantiqiy qiymatni o'zi `1`/`0`
ga aylantiradi; boshqa klientlar (Dart) ham shunday qilishi kerak.

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
| `platform_account` | 403 | `/auth/telegram`, `/auth/phone/verify` | Telegram id yoki raqam faqat superadmin/RIZQIM jamoasi hisobida: token berilmaydi, "Boshqaruv markazidan kiring" (P4-25) |
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
| `private-platform.ops` (P4-11) | RIZQIM jamoasi: `admin.today.view` ruxsati bor a'zo, faqat **admin tokeni** bilan | `ops.ping` (sinov); 2-bosqichda yangi buyurtmalar, muammolar, ogohlantirishlar |

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
`preparing` buyurtmalar (`include_ready=1` bilan `ready` ham; P3-35 dan `true`/`false` ham qabul qilinadi), eskisidan yangisiga, eng ko'pi 200. **P2-33: oshxona
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

**P4-32 (eski `/admin` panelidan).** `GET /owner/settings` javobida `telegram: {group_link, group_linked}` — buyurtmalar va
bildirishnomalar keladigan Telegram guruhini ulash havolasi (`https://t.me/<bot>?start=store_<uuid>`; bot nomi sozlanmagan
bo'lsa `null`) va guruh ulanganmi (chat ID'ning o'zi qaytmaydi). Taklif qilinmaydigan to'lov tizimi
`{enabled: false, available: false, unavailable_reason}`: `not_integrated` (Uzum, Paynet) yoki `switched_off` (RIZQIM
vaqtincha o'chirgan); `POST` uni yoqishga urinsa 422. `own_account` ovqatxona Click (`service_id`, `merchant_id`,
`secret_key`) va Payme (`merchant_id`, `secret_key`) kalitlarini `payment_settings.<tizim>` ichida yuboradi; maxfiy kalit
hech qachon qaytmaydi (`has_secret_key`), bo'sh yoki yulduzchali qiymat saqlanganini qoldiradi. Hamkor panelida: «Sozlamalar»
(Telegram guruhi, o'z kassa kalitlari), «Harakatlar jurnali» (`/audit`, `GET /owner/audit-logs`), «Xodimlar» → «Mening PIN
kodim» (`PUT /owner/staff/{o'z id}/pin`).

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
  `unaccepted_online_minutes` (1–120, standart 3), `cash_out_amount` (P3-37: bitta kassadan chiqim — `cash_out`,
  `wage`, `purchase` — shu summadan kam bo'lmasa egaga xabar; 0–100 000 000, standart `"500000.00"`).
- `pos_settings.cashier_discount_max_percent` (P3-37) — kassir (`pos.take_payment`, `orders.discount` siz) o'zi bera
  oladigan chegirma foizi: 0–100, 2 xonagacha (son yoki satr), javobda satr (`"10.00"`, standart); `null` yoki `0` —
  kassir har doim menejer PIN bilan. `GET /pos/bootstrap` da ham shu kalit.
- `pos_settings.holidays` — `YYYY-MM-DD` sanalar (≤400), butun ro'yxat almashtiriladi. P3-26: filialning **yagona**
  bayramlar ro'yxati — `GET/PUT /owner/room-tariffs/calendar` dagi `holidays` ham shu ro'yxat (u yerdan yozilsa shu
  yerda ko'rinadi). Migratsiya `2026_10_27_100300` eski kalendar bayramlarini shu yerga qo'shib, kalendar ustunini
  bo'shatdi (`down` qaytarib ko'chiradi).
- `pos_settings.booking_slot_minutes` — tugash vaqtisiz bron xonani qancha ushlab turadi: 30–1440 daqiqa yoki `null` (serverning standarti, 300).
- `pos_settings.booking_confirmation` (P3-38) — mehmonga boradigan bron tasdig'i, kalit bo'yicha qo'shiladi:
  `auto_send` (standart `true`: bron tasdiqlanganda o'zi yuboriladi), `sms` (standart `false`: SMS pulli — yoqilsa,
  Telegrami yo'q mehmonga SMS ketadi), `cancel_policy` (bekor qilish qoidasi matni, ≤300 belgi, yoki `null`). Boshqa
  kalit — 422. `GET /pos/bootstrap` da ham shu blok.
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
| `GET /pos/bookings/{uuid}/confirmation` | P3-38: mehmonga boradigan tasdiq matni (`text`, `lines`, `room_fee`, `channels`) — "Bron tasdig'i" bo'limi |
| `POST /pos/bookings/{uuid}/confirmation/send` | P3-38: tasdiqni yuborish (`channel`: `auto`, `telegram`, `sms`; `Idempotency-Key`) |
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

### Bron tasdig'i (P3-38)

Kassa yoki web panelda yozilgan bron haqida mehmonga **bitta matn** boradi: Telegram, SMS yoki kassir nusxalab o'zi
yuboradi (yoki chekdek chop etadi). Web panel bronlarni shu `/pos/*` endpointlari orqali (ega yoki menejer kirishi
bilan) o'qiydi, shuning uchun alohida `/owner/...` nusxasi yo'q. Ruxsat — bronni o'qish bilan bir xil
(`bookings.manage`), terminal PIN sessiyasi ham; boshqa do'kon yoki filial broni — 404.

```
Bron tasdiqlandi ✅
Bahor choyxonasi, Chilonzor 9-kvartal
📅 Shanba, 31-oktabr, 19:00–23:00
🚪 Xona: "Katta xona" (20 kishilik) · 12 kishi
💵 Xona puli: 300 000 so'm (dam olish kuni narxi)
💵 Minimal buyurtma: 1 500 000 so'm
💵 Xizmat haqi: 10%
💵 Joy narxi: 5 000 so'm kishi boshiga (jami 60 000 so'm)
🍽 Oldindan buyurtma: Osh 3 kg, Qozon kabob 2 kg
💳 Bo'nak olindi: 500 000 so'm
ℹ️ Bekor qilish: 24 soat oldin ayting.
📞 +998712000000 · Bron raqami: 3F9A2C
```

**`GET /pos/bookings/{uuid}/confirmation`** → `{text, lines, room_fee, channels}`:
- `text` — tayyor xabar (Telegram'ga shu ketadi). Sarlavha holatga qarab: `confirmed`/`arrived`/`completed` — "Bron
  tasdiqlandi ✅", `requested` — "Bron qabul qilindi 📝", bekor qilingan/kelmagan — "Bron bekor qilindi ❌".
- `lines` — `[{kind, label, value}]`, tartibi matndagidek. `kind`: `header`, `place`, `time`, `room`, `room_fee`,
  `min_spend`, `service_charge`, `place_fee`, `pre_order`, `deposit`, `cancel_policy`, `contact`. `label` bo'sh
  bo'lishi mumkin (`header`, `place`, `time`, `cancel_policy`, `contact`); matndagi qator — `label: value` (yoki faqat
  `value`) va oldida belgi (emoji). Chop etishda belgilarsiz `lines` dan foydalaning. Bir nechta xonali banketda
  `room` qatorining `label` i "Xonalar".
- `room_fee` — `{amount: "300000.00", day_type: "weekday"|"weekend"|"holiday", tariff_id}` yoki `null` (xona hali
  belgilanmagan yoki tarif yo'q). Tarif narxi 0 bo'lsa obyekt bor, matnda qator yo'q.
- `channels` — `{telegram, sms}`: "Yuborish" hozir qaysi yo'l bilan keta oladi.

**Summalar buyurtma hisobining o'z qoidalari bilan** (`PosChargesCalculator`, "Xona puli, minimal summa va xizmat
haqi" bo'limi):
- **Xona puli** — bronning asosiy xonasi tarifi (`RoomTariffResolver`: xonaning o'zi → zal ichidagi turi → filialdagi
  turi) bron boshlanadigan **biznes kun**ning turi bo'yicha (`BusinessCalendar`: filial bayramlari
  `pos_settings.holidays`, dam olish kunlari; kun boshlanishi 05:00 — shanba 01:00 dagi bron jumaniki). Bayramga o'z
  tarifi bo'lmasa dam olish, u ham bo'lmasa hafta ichi narxi; `day_type` va qavsdagi so'z — **kunning** turi.
  Xona belgilanmagan bo'lsa: "Xona: keyin belgilanadi", xona puli qatori va `room_fee` yo'q.
- **Minimal buyurtma** — bronning o'z `min_spend` i (mehmon bilan kelishilgan), bo'lmasa xonaniki
  (`dining_tables.min_spend`; `per_person` — "kishi boshiga (jami …)", `guests_planned` ga ko'paytirilib). Buyurtma
  ham xuddi shu qoida bilan hisoblaydi ("Xona puli, minimal summa va xizmat haqi"): bronning summasi buyurtmaga tushadi.
- **Xizmat haqi** va **joy narxi** — xona zalining sozlamasi, bo'lmasa filial yoki do'konniki
  (`service_fee_settings`), faqat `pos_hall` kanaliga qo'llansa. Joy narxi `per_person` — "kishi boshiga (jami …)",
  `per_table` — summa.
- **Oldindan buyurtma** — bron qatorlari: "Nom (variant) 3 kg", "Somsa 10 dona".
- **Bo'nak olindi** — bron avanslari yig'indisi, qaytarilganlari ayirilgan (`amount − refunded_amount`); 0 bo'lsa
  qator yo'q.
- **Bekor qilish qoidasi** — filialning `pos_settings.booking_confirmation.cancel_policy`.
- **Aloqa** — filial telefoni (yo'q bo'lsa do'konniki) va **bron raqami**: bron `uuid` ining oxirgi 6 belgisi, katta
  harflarda.

**`POST /pos/bookings/{uuid}/confirmation/send`** — tana `{channel?: "auto"|"telegram"|"sms"}` (standart `auto`),
`Idempotency-Key` sarlavhasi (bir xil kalit javobni qaytaradi, ikkinchi marta yubormaydi; har yangi yuborish — yangi
kalit). Javob `{channel: "telegram"|"sms", queued: true}`:
- `telegram` — `contact_phone` shu do'kon mijozi (`role: customer`, `store_id` shu do'kon yoki bo'sh; telefon
  tasdiqlangani birinchi) ning raqami, uning Telegram akkaunti bor, bot sozlangan va mijoz "Buyurtma holati" xabarlarini
  Telegram'da o'chirmagan. Xabar mijozning buyurtma xabarlari bilan bir yo'ldan ketadi (`TelegramMessenger`, navbat).
- `sms` — filial `booking_confirmation.sms: true` qilgan va telefon bor. SMS matni xuddi shu, faqat belgilarsiz
  (emoji SMS ni qimmatlashtiradi).
- `auto` — Telegram bo'lsa Telegram, bo'lmasa SMS (yoqilgan bo'lsa), aks holda **422 `no_channel`** ("matnni nusxalab
  o'zingiz yuboring"). Aniq `telegram` yoki `sms` so'ralib, u yo'l bo'lmasa ham 422 `no_channel`.
- Faqat `requested` yoki `confirmed` bron: boshqa holatda 422 `booking_not_editable`.
- Yuborish navbatga qo'yiladi (`SendBookingConfirmation`), bronda `confirmation_sent_at` va `confirmation_channel`
  yoziladi (bron `version` i o'zgarmaydi), jurnalga `booking.confirmation_sent` (`channel`, `auto`), `booking.changed`
  hodisasi. Qayta yuborish mumkin.

**O'zi yuborish.** Bron tasdiqlanganda — `POST .../confirm`, `confirm: true` bilan yaratilganda yoki birinchi avans
`requested` bronni tasdiqlaganda — filialda `auto_send: true` bo'lsa tasdiq `auto` yo'l bilan o'zi ketadi (jurnalda
`auto: true`). Yo'l yo'q yoki xato bo'lsa faqat logga yoziladi, bron baribir tasdiqlanadi. Keyin vaqt, xona yoki
mehmonlar soni o'zgarsa **qayta o'zi yuborilmaydi** — kassir qo'lda qayta yuboradi.

**Bron JSON** (ro'yxat, taqvim, bitta bron): `confirmation_sent_at` (UTC ISO 8601 yoki `null`) va
`confirmation_channel` (`telegram`, `sms` yoki `null`) — oxirgi yuborilgan tasdiq. Migratsiya
`2026_10_31_100000_add_confirmation_to_bookings_table`.

Testlar: `tests/Feature/PosBooking/PosBookingConfirmationTest.php`.

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
  P3-35: smena qatori qulf ostida qayta o'qiladi — shu orada Z bilan yopilgan bo'lsa 409 `shift_closed` (pul
  olinmaydi), shuning uchun Z hech qachon bir vaqtdagi to'lovni o'tkazib yubormaydi.
- Schyot to'liq qoplanganda **sotuv cheki** so'raladi (to'liq summaga). Schyot `paid` bo'ladi faqat chek `issued`
  bo'lganda; hamma schyot to'langach buyurtma **yopiladi**: `delivered`, `payment_status = paid`, `closed_at`, stol
  bo'shaydi (real-time `order.status_changed`). Chek kutilayotgan yoki xato bo'lsa schyot "fiskal kutmoqda"
  (`fiscal.status`: `pending` / `failed`), pul saqlanadi; `POST /pos/bills/{uuid}/fiscal-retry` o'sha chekni qayta
  so'raydi (yangi chek chiqmaydi). P3-35: provayder rad etgan (`failed`) chek ham **qayta yuboriladi** (o'sha chek,
  `FiscalIssuer::reissue`) — tugma, `POST /pos/orders/{uuid}/close` va smenani yopish (kutilayotgan cheklar) shunday
  qiladi; boshqa urinish hozir yuborayotgan chekka tegilmaydi (ijara, `fiscal_receipts.lease_token`/`leased_until`). Provayder keyinroq tasdiqlasa (`fiscal.receipt_updated`) schyot o'zi yopiladi.
- MXIK'siz qator — 422 `mxik_missing`, pul olinmasdan oldin.
- `POST /pos/orders/{uuid}/close` — uzilib qolgan yopishni yakunlash (kutilayotgan cheklarni qayta so'raydi);
  to'lanmagan bo'lsa 409 `order_not_settled`.

**Sotuv chekidagi to'lovlar (P3-11 `FiscalPayment`):** naqd, karta (`card_type`, `transaction_id`), QR — o'z shakli
bilan; avans qismi — `prepayment: true`, avans olingan shaklda (bo'nak → sotuv, tadqiqot 5-bo'lim); **nasiya qismi —
`credit: true`** (hozir olingan pul emas): chek to'liq summaga chiqadi, qarz to'langanda kredit cheklari (P3-17).
Nasiya qismining to'lov shakli provayder aniqlaguncha `cash` (tadqiqotdagi T4).

### Qaytarish

`POST /pos/refunds {payment_uuid, amount, reason, approval_token?, shift_uuid?}` — `pos.refund` (yangi ruxsat: ega,
menejer) yoki menejer tasdig'i. Faqat **shu smenada** olingan, schyotga tegishli to'lov (409 `refund_outside_shift`,
422 `refund_tender_invalid`), to'lovdan qolganidan ko'p emas (422 `refund_exceeds_payment`), schyotning sotuv cheki
chiqqan bo'lsa (409 `bill_not_fiscalized`), smena shu orada yopilmagan (409 `shift_closed`, P3-35), naqd —
kassadagidan ko'p emas (409 `not_enough_cash`; smena qulfi ostida tekshiriladi). Naqd kassadan chiqadi, karta va QR
o'z yo'li bilan qaytadi, asl sotuv chekiga bog'langan **qaytarish cheki** so'raladi, egaga Telegram xabari boradi.
To'lov to'liq qaytsa `refunded`, buyurtmaning hamma to'lovi qaytsa buyurtma `payment_status = refunded` (va uni
`POST /pos/orders/{uuid}/cancel` bilan bekor qilish mumkin).

P3-35: **avans va nasiya qismlari ham qaytariladi** (`method`: `prepayment`, `account`), kassadan pul chiqmaydi:

| To'lov | Nima bo'ladi | Qaytarish chekidagi to'lov |
|---|---|---|
| `prepayment` | summa bron avansiga qaytadi (`booking_payments.applied_amount` kamayadi, `applications` ga manfiy yozuv, avans yana `taken`): keyin boshqa schyotga ayiriladi, qaytariladi yoki ushlab qolinadi (bron bo'limi); schyot olgandan ko'pi — 409 `reverse_exceeds_applied` | avans olingan shaklda, `prepayment: true` |
| `account` | mijoz qarzidan ayiriladi: `adjustment` yozuvi (manfiy, izoh "Kassada qaytarildi", `idempotency_key` = `bill-reversal:{bill}:{refund}`), egaga xabar; schyot nasiyaga yozilgandan ko'pi — 409 `reverse_exceeds_charge`, mijoz qarzni to'lab bo'lgan bo'lsa (qolgan qarzdan ko'p) — 409 `more_than_debt` (menejer qo'lda hal qiladi) | `credit: true` (`cash` shaklida, sotuv chekidagidek) |

### Smena, kassa, X va Z

| Endpoint | Ruxsat | Nima qiladi |
|---|---|---|
| `POST /pos/shifts {opening_cash, note?}` | `pos.open_shift` | shu terminalda smena ochish (faqat PIN sessiyasi; terminalda bitta ochiq smena — 409 `shift_already_open`) |
| `GET /pos/shifts/current?branch_id` | `pos.open_shift`, `pos.close_shift`, `pos.take_payment` yoki (P3-35, faqat o'qish) `reports.view` | terminal: o'z ochiq smenasi va X hisobot. P3-26: oddiy kirish (ega, menejer — web panel, Mini App) — filialning ochiq smenalari: `branch_id` (standart — o'z filiali; filialsiz ega bermasa — ko'radigan hamma filial). Javob `{shift, report, shifts: [{shift, report}]}`: `shift`/`report` — eng oxirgi ochilgani, `shifts` — hammasi (terminalda — faqat o'ziniki). Boshqa do'kon filiali — 404, o'zi ishlamaydigan filial — 403 |
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
| `POST /pos/orders/{uuid}/cancel {version, reason, approval_token?}` | `orders.cancel` yoki `orders.manage` + menejer tasdig'i | butun buyurtmani bekor qilish (pastda); P3-37: to'liq qaytarilgan yopiq buyurtmani ham |
| `POST /pos/orders/{uuid}/discount {version, kind, value, reason?, approval_token?}` | `orders.discount`, yoki `orders.manage` (kassir — o'z chegarasigacha, qolgani menejer tasdig'i bilan) | chegirma berish yoki olib tashlash (P3-37, pastda "Kassadagi chegirma va kassir huquqlari") |

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
qatorlar ham). P3-35: qator qo'shilganda (ushlab turilgan oldindan buyurtma ham) sexini oladi (`station_id`, P3-13
marshruti) — sex ekrani oldindan buyurtmani yuborilmasdan ko'radi; yuborishda sex qayta aniqlanadi va `sent_at`; birinchi yuborish buyurtmani
`preparing` qiladi. Yangi qator yo'q — 422 `nothing_to_send`. Oshxona ekrani (`GET /staff/kitchen/tickets`) POS
qatorini faqat yuborilgandan keyin (o'chirilgani ham — `void`) yoki `hold` + `due_at` bo'lsa (oldindan buyurtma
navbati) ko'radi; yuborilmagan qatorni "tayyor" deb bo'lmaydi (422 `kitchen_status`). POS qatorini qaytarish
(`recall`) uni `sent` ga qaytaradi.

**Qator holati** (`kitchen_status`): POS qatori `pending` (faqat buyurtmada) → `sent` → `ready` → `served`; `void` —
o'chirilgan. Onlayn buyurtma qatorlari avvalgidek `pending` → `ready`. Xodim buyurtmasi va oshxona chiptasida POS
qatorida qo'shimcha `round_no`, `fire_mode`, `station_id`, `sent_at`, `due_at`, `seat_no`, `note`, `voided_at`,
`void_reason` va (P3-35) `product_id`, `category_id` — taomning mahsuloti va kategoriyasi (o'chirilgan bo'lsa ham):
sexi yo'q qatorni ekran bootstrap'ning `station_routes` bo'yicha yo'naltiradi (onlayn qator shakli o'zgarmagan).

**O'chirish.** Oshxonaga yuborilmagan qator — `orders.manage`, sabab ixtiyoriy, qoldiq va qozon ulushi qaytadi.
Yuborilgan qator yoki schyot chiqarilgan (`bill_locked_at`, P3-18) buyurtma — `reason` majburiy va `orders.void_item`
yoki menejer tasdig'i (`approval_token`, `POST /pos/approvals`); yo'q bo'lsa 403 `approval_required`, yaroqsiz bo'lsa
403 `approval_invalid` (yoki P3-18 sababi: `approval_expired`, `approval_used`, ...). Yuborilgan qatorning ulushi
faqat `restock: true` bo'lsa qaytadi (pishmagan bo'lsa). Allaqachon o'chirilgan — 422 `already_void`.

**To'lovi boshlangan schyot** (P3-35). O'chirish, mehmon soni, ko'chirish, birlashtirish yoki xizmat qatorini olib
tashlash to'langan (yoki to'lovi olingan) schyotdagi qatorni yoki xizmat haqi ulushini kamaytirsa — 409
`paid_bill_affected` (`data.bills_reason`: `bills_out_of_date` yoki `discount_exceeds_lines`), hech narsa
o'zgarmaydi: avval o'sha to'lovni qaytaring (`POST /pos/refunds`). Shu tufayli buyurtma "to'lab ham, bekor qilib ham
bo'lmaydigan" holatga tushmaydi.

**Ko'chirish va birlashtirish.** Ko'chirishda yangi zalning xizmat haqi, yangi joyning xona puli va minimal summasi
qo'llanadi (kun turi — buyurtma ochilgan biznes kun bo'yicha); band xonaga — 422 `table_occupied`, o'sha stolga — 422
`same_table`, olib ketish — 422 `not_hall_order`. Birlashtirishda `source_uuid` qatorlari bosqichlari bilan (targetdan
keyin) ko'chadi, mehmonlar qo'shiladi, manba buyurtma `canceled` bo'ladi (izoh: "#… buyurtmasiga qo'shildi", stok
qaytmaydi — qatorlar yashashda davom etadi). Ikkalasi bitta filialning ochiq zal buyurtmasi, schyot chiqmagan
(422 `bill_locked`), to'lovsiz (422 `has_payments`); boshqa filial — 422 `merge_not_allowed`. P3-35: manba bronning
buyurtmasi bo'lsa **bron va avans targetga o'tadi** (`orders.booking_id`, `bookings.order_id`,
`booking_payments.order_id`; audit `booking.order_merged`, `booking.changed`): targetning schyoti avansni oladi, bron
target yopilganda yakunlanadi. Ikkalasi boshqa-boshqa bronniki — 422 `merge_bookings_conflict`.

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
mumkin. P3-37: **yopilgan** buyurtma faqat har bir to'lovi to'liq qaytarilgan bo'lsa (`payment_status = refunded`,
`paid` to'lov qolmagan) bekor qilinadi — o'sha ruxsat va tasdiq bilan; holati `canceled`, `closed_at` qoladi, qatorlari
va to'langan schyotlari o'zgarmaydi, yangi chek chiqmaydi (qaytarish cheklari bor), jurnalda `after_refund: true`.
Boshqa har qanday yopiq yoki bekor qilingan buyurtma — 422 `order_closed`.

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
minimal summa     = bronning min_spend i (buyurtma brondan: orders.booking_id), bo'lmasa joyning min_spend i
                    (per_person bo'lsa × mehmon)
qo'shimcha        = max(0, minimal summa − chegirmadan keyin), butun so'm                 (min_spend_topup_amount)
jami              = chegirmadan keyin + xizmat haqi + joy narxi + xona puli + qo'shimcha  (total_price)
```

- **Biznes kun** — do'kon vaqt zonasida, filialning `business_day_starts_at` (standart 05:00) dan: yakshanba 03:30
  shanba kuniga tegishli. **Kun turi:** bayram (filialning yagona ro'yxati `pos_settings.holidays`, P3-26), dam olish kuni
  (`weekend_days`), aks holda hafta ichi.
- **Tarif tanlash:** eng aniqi — joyning o'zi, keyin zaldagi joy turi, keyin filialdagi joy turi; har darajada
  bayramning o'z narxi bo'lmasa — dam olish kuni narxi, u ham bo'lmasa — hafta ichi narxi. Tarif yo'q — xona puli 0.
- **Minimal summa** — buyurtma brondan ochilgan bo'lsa (`orders.booking_id`: "Keldi", yoki birlashtirishda bron shu
  buyurtmaga o'tgan), mehmon bilan kelishilgan **bronning o'z `min_spend` i** (butun bron uchun, mehmonga
  ko'paytirilmaydi) — joyniki undan katta yoki kichik bo'lsa ham. Bronda yo'q (yoki 0) bo'lsa — joyniki
  (`dining_tables.min_spend`, `per_person` bo'lsa × buyurtmadagi mehmon). Bu bron tasdig'idagi "Minimal buyurtma"
  bilan bitta qoida (`DiningFeeCalculator::minimumSpend`): mehmonga aytilgani hisobga tushadi. `charges.min_spend` —
  amaldagi summa; farq qatori fiskal chekda "Minimal summaga qo'shimcha" bo'lib chiqadi.
- Xona puli va joyning minimal summasi faqat `pos_hall` va stol bilan (bronning minimal summasi `pos_hall` da stolsiz
  ham); xizmat haqi va joy narxi sozlamadagi kanallar bo'yicha (standart faqat `pos_hall`). Telegram va mobil
  buyurtmaga hech qachon qo'llanmaydi.
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
`moved`, `merged`, `charges_changed`, `served`, `cancelled`). P3-35: `send: true` bilan qo'shilgan bosqich ikki xabar
beradi: avval `round_added` (bosqichning hamma qatorlari), keyin `sent` (yuborilganlari), ikkalasi bitta `version` bilan
(oflayn sinxronlangan bosqich ham). P3-26: **`order.bill_requested`** — ofitsiant schyot
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
- `stopped_at` — bazaning vaqtinchalik xatosi (deadlock, serializatsiya, ulanish uzilishi, qulf kutish) tufayli
  to'xtagan `seq`: shu va keyingi amallar javobda yo'q, navbatda qoladi. P3-35: amalning o'z mazmuni sabab bo'lgan xato
  (ustunga sig'maydigan qiymat va h.k.) navbatni to'xtatmaydi — amal `rejected` `invalid_payload` (pul amali —
  `payment_unallocated` sifatida yoziladi, u ham bo'lmasa `rejected`); noto'g'ri sana (`menu_taken_at`,
  `queue.oldest_at`) e'tiborsiz qoldiriladi, qatorning noto'g'ri `due_at` i — `rejected` `invalid`.
- P3-35: karta to'lovida `transaction_id` 64 belgigacha qisqartiriladi, noma'lum `card_type` — `other`; summa ko'pi
  bilan 9 999 999 999.99 (aks holda `amount_invalid`).
- 409: `sync_in_progress` (shu kassaning boshqa so'rovi ketyapti), `op_id_conflict` (amal raqami boshqa qurilmaniki),
  `seq_reused` (shu `seq` boshqa amalga berilgan).

Rad etish sabablari (`reason`): `order_not_found`, `order_closed`, `line_not_found`, `line_sent` (o'chirilayotgan qator
shu orada oshxonaga ketgan), `bill_not_found`, `bill_paid`, `bill_void`, `items_required`, `tenders_required`,
`tender_not_offline` (oflayn faqat naqd va karta), `amount_invalid`, `channel_invalid`, `type_unknown`,
`terminal_without_branch`, `variant_not_found`, `modifier_not_found` (P3-35: qo'shimcha bu do'konniki emas),
`invalid_payload` (P3-35), xodim sabablari.

### Ziddiyat qoidalari

- **Bir amal ikki marta** (`op_id`) yoki **bir buyurtma ikki marta** (`external_id`) — bir marta qo'llanadi.
- **Buyurtma versiyasi** solishtirilmaydi (oshxona "tayyor"i ham versiyani oshiradi): amal mazmunan sig'masa rad
  etiladi — buyurtma yopilgan/bekor qilingan, qator shu orada yuborilgan yoki o'chirilgan.
- **Joy band** (`table_occupied`, `table_inactive`, `table_missing`) — mehmonlar xizmat olgan: buyurtma joysiz
  ochiladi, `conflict`.
- **Narx** (P3-35) — qurilma narxi (taomniki va qo'shimchaniki) faqat taom yoki qo'shimcha **menyu nusxasi olingan
  paytdan (`menu_taken_at`; bo'lmasa 24 soat oldindan) hozirgacha** shu narxda bo'lgan bo'lsa saqlanadi
  (`order_items.price_source = offline_snapshot`, joriy narxdan farq qilsa `offline_price` belgisi — ma'lumot uchun).
  Narxlar tarixi `menu_price_history` da (POS kanallari bo'yicha samarali narx, qo'shimchaning `price_delta` si).
  Bunday narx bo'lmagan bo'lsa **server narxi qo'yiladi** (`price_source = online`), `offline_price_mismatch` belgisi
  (`device_price`, `server_price`, `name`, `line_id`) — `conflict` ("Sinxronlash xatolari"da menejer ko'radi);
  qurilma narxi past bo'lsa egaga xabar boradi. Menyu nusxasi 24 soatdan eski bo'lsa `stale_menu` — `conflict`.
  Serverda sotuvdan olingan taom — `sold_while_unavailable`.
- **Qurilma soati** (P3-35) — `occurred_at` paketning soat farqi (`clock_skew_seconds`, 300 soniyadan katta bo'lsa)
  bilan tuzatiladi va `[hozir − 48 soat, hozir]` oralig'ida ushlanadi; to'lov vaqti smena ochilganidan oldin bo'lmaydi.
  Vaqt 300 soniyadan ko'p surilsa — `device_time_clamped` belgisi (`device_time`, `used_time`; ma'lumot uchun). Shu
  vaqt `offline_created_at`, `payments.offline_paid_at` va `fiscal_receipts.sold_at` ga yoziladi;
  `pos_sync_operations.occurred_at` — qurilma aytgan vaqt.
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

P3-35 migratsiyalari (qo'shimcha, qaytariladigan): `2026_10_29_100000_add_lease_to_fiscal_receipts_table`
(`lease_token`, `leased_until`), `2026_10_29_100100_create_menu_price_history_table` (bugungi narxlar bilan
to'ldiriladi).

Testlar: `tests/Feature/PosSync/PosSyncFlowTest.php`, `PosSyncConflictsTest.php`, `PosSyncAccessTest.php`,
`PosSyncReviewFixesTest.php` (P3-35).

## Kassadagi chegirma va kassir huquqlari (P3-37)

Egasining 24.10.2026 qarori ([POS_SPEC.md](POS_SPEC.md) 2.9, 2.10, 11): kassir schyot nusxasini chiqaradi va kassaga
pul kiritadi/chiqaradi menejersiz; chegirmani o'z chegarasigacha o'zi beradi; qaytarish va bekor qilish menejer PIN
bilan qoladi. Har bir rol huquqini ega do'kon bo'yicha o'zgartiradi (`GET/PUT /owner/permissions`, pastda).

### `POST /pos/orders/{uuid}/discount`

Boshqa POS buyurtma amallari kabi: oddiy xodim kirishi yoki terminaldagi PIN sessiyasi, `Idempotency-Key` majburiy
(yo'q — 428), `version` (eskisi — 409 `version_conflict`). Marshrut `permission:orders.discount,orders.manage`.

So'rov:

| Maydon | Tur | Ma'nosi |
|---|---|---|
| `version` | int | buyurtmaning terminaldagi versiyasi |
| `kind` | `"percent"` \| `"amount"` | foiz yoki summa |
| `value` | son yoki satr, 2 xonagacha | `percent`: 0 < v ≤ 100; `amount`: 0 < v ≤ taomlar summasi (`charges.items_subtotal`). `0` — chegirmani olib tashlash |
| `reason` | string ≤255 | majburiy (`value` 0 bo'lsa — yo'q) |
| `approval_token` | string? | menejerning `orders.discount` uchun bir martalik tasdig'i (`POST /pos/approvals`) |

Qoidalar:

- Chegirma butun buyurtmaning **taomlariga** (xizmat haqi, xona puli va boshqa qatorlardan oldin); xizmat haqi va
  minimal summa farqi chegirmadan keyingi summadan hisoblanadi.
- `orders.discount` — buyurtmaning hisoblangan chegirma summasi, butun so'mgacha (half-up). Foiz **foizligicha
  qoladi**: taom qo'shilsa yoki o'chirilsa qayta hisoblanadi. Summa taomlardan oshmaydi (taomlar kamaysa, chegirma ham
  taomlar summasigacha tushadi; yana ko'paysa — to'liq qaytadi).
- Kim: `orders.discount` (ega, menejer) — har qanday; kassir (`pos.take_payment`) — filialning
  `pos_settings.cashier_discount_max_percent` gacha (summa — taomlarga nisbatan foizi bo'yicha), faqat schyot
  chiqmaguncha (schyot chiqqach buyurtma qulflangan: chegirma faqat menejer bilan); qolgan hollarda 403
  `approval_required` (`data.permission = orders.discount`) — menejer PIN tasdig'i bilan qayta yuboriladi (noto'g'ri
  token — 403 `approval_invalid` va h.k.). Chegirmani olib tashlash (`value` 0) — `orders.manage` yetarli. Ofitsiant
  qoidasi amal qiladi.
- Rad etiladi: buyurtma yopilgan yoki bekor qilingan — 422 `order_closed`; schyotida to'lov bor yoki to'langan — 409
  `paid_bill_affected` (avval to'lovni qaytaring); noto'g'ri qiymat — 422 (`errors.value`, `errors.reason`,
  `errors.kind`). Chiqarilgan (lekin to'lanmagan) schyot buyurtmaga mos kelmay qoladi: uni va nusxasini chop etish
  409 `order_changed`, schyot qayta tuziladi (`POST /pos/orders/{uuid}/bills`).
- Javob — buyurtma (`GET /pos/orders/{uuid}` shakli) yangi maydonlar bilan: `discount_kind` (`percent`|`amount`|`null`),
  `discount_value` (kiritilgan qiymat, `"10.00"` yoki `"50000.00"`; `null`), `discount_reason`, `discount_by` (xodim
  id), `discount` (summa).
- Jurnal `pos.order.discount` (`before`/`after`: `kind`, `value`, `amount`, `reason`; `payload`: `items_subtotal`,
  `percent`, `approved_by`, `removed`). Chegirma taomlarning `pos_settings.alerts.discount_percent` foizidan kam
  bo'lmasa — egaga xabar (olib tashlash xabar bermaydi). Real-time: `order.items_changed` (`change: charges_changed`).
- Internetsiz rejimda yo'q (sinxronlash amali yo'q): kassa ilovasi oflaynda chegirmani bermaydi.

Pul oqimi: schyotlar chegirmani taomlar summasiga proporsional bo'ladi (`order_bill_items.discount_amount`,
`order_bills.discount`); fiskal chekda har bir taom qatori chegirmadan keyingi summada, chek jami — schyot jami; X/Z
hisobotida `discounts` — chegirmali schyotlar soni va chegirma summasi, `sales.discount` — summa.

### Kassir huquqlari va kassadan chiqim

- Kassirning standart huquqlariga `pos.reprint_bill` (schyot nusxasi, "NUSXA") va `pos.cash_in_out` (kirim, chiqim, ish
  haqi, bozorlik) qo'shildi ([AUTH.md](AUTH.md)). Ega ularni do'kon bo'yicha olib qo'ysa, avvalgidek menejer PIN
  tasdig'i so'raladi.
- Bitta chiqim harakati (`cash_out`, `wage`, `purchase`) `pos_settings.alerts.cash_out_amount` dan kam bo'lmasa (standart
  500 000 so'm, `null` — o'chiq), egaga xabar boradi (summa, sabab — kategoriya va izoh, xodim, kim). Kirim xabar
  bermaydi.
- Schyot nusxasi ham, birinchi chop etish kabi, buyurtma schyotdan keyin o'zgargan bo'lsa (to'lanmagan schyot)
  chiqmaydi: 409 `order_changed`.

### `GET/PUT /owner/permissions` (rol huquqlari sahifasi)

`permissions.manage` (ega), `not-staff-session`; do'konsiz hisob — 403 `store_required`.

`GET /owner/permissions` javobi (`data`):

```json
{
  "roles": [{"role": "owner", "label": "Ega", "editable": false}, {"role": "cashier", "label": "Kassir", "editable": true}],
  "permissions": [{"key": "orders.discount", "label": "Chegirma berish", "grantable": true}],
  "matrix":   {"cashier": {"orders.discount": false, "pos.reprint_bill": true}},
  "defaults": {"cashier": {"orders.discount": false, "pos.reprint_bill": true}}
}
```

- `roles` — `owner` (o'zgarmaydi), `manager`, `cashier`, `waiter`, `cook`, `courier` tartibida.
- `permissions` — hamma ruxsat kaliti va o'zbekcha nomi; `grantable: false` (orders.refund, finance.manage,
  settings.manage, zones.manage, staff.manage, audit.view, permissions.manage) boshqa rolga berilmaydi, faqat olib
  qo'yiladi.
- `matrix` — do'konning amaldagi jadvali: rol → ruxsat kaliti → `true|false` (har rol va har kalit bor). `defaults` —
  xuddi shu shaklda standart jadval; farq qiladigan katak — shu do'konning o'zgartirishi.

`PUT /owner/permissions {role, permission, allowed}`: `role` — `manager|cashier|waiter|cook|courier` (ega — 422),
`permission` — kalit, `allowed` — `true` (berish), `false` (olib qo'yish), `null` (standartga qaytarish; standart
qiymatning o'zi yuborilsa ham shunday). Berib bo'lmaydigan ruxsatni berish — 422. Javob: `{role, permission, allowed}`
(amaldagi qiymat). O'zgarsa jurnal `permissions.changed`; shu roldagi hamma xodimga darhol ta'sir qiladi (terminal PIN
sessiyalari ham).

Migratsiya (qo'shimcha, qaytariladigan): `2026_10_30_100000_add_discount_details_to_orders_table` (orders:
`discount_kind`, `discount_value`, `discount_reason`, `discount_by`).

Testlar: `tests/Feature/PosDiscountTest.php`, `tests/Feature/PosDiscountEndToEndTest.php` (chegirma → ikki schyot →
to'lov → cheklar → X/Z), `tests/Feature/PosBilling/PosCashierPermissionsTest.php` (katta chiqim xabari, to'liq
qaytarilgan buyurtmani bekor qilish), `PosBranchSettingsApiTest`, `PermissionMatrixTest`.

## Admin API (P4-11)

Boshqaruv markazi (`platform/admin`) uchun: RIZQIM jamoasining kirishi, 2FA, jamoa, rollar, harakatlar jurnali,
qidiruv. Hammasi `/api/v1/admin` ostida, OpenAPI'da **Admin** guruhi. Kod: `routes/api_admin.php`,
`app/Http/Controllers/Api/V1/Admin`, `app/Services/Admin`.

### Kim kiradi

- Jamoa a'zosi — `users.platform_role` to'ldirilgan foydalanuvchi: `superadmin`, `operator`, `support` (Yordam),
  `finance` (Moliya), `sales` (Savdo va ulash), `viewer` (Faqat ko'rish).
- Eski `super_admin` lar avtomatik `superadmin` bo'ldi; ular do'kon API'si va eski Filament panellarida avvalgidek
  ishlaydi. 2FA'ni ular `php artisan rizqim:admin:invite <email>` havolasi (yoki boshqa superadmin "2FA ni tiklash")
  orqali o'rnatadi: faqat parol bilan o'rnatib bo'lmaydi (pastda, `setup_not_allowed`; docs/OPERATIONS.md, 10-bo'lim).
- Boshqa rollar uchun yangi `users.role = platform`: do'kon huquqi ham, mijoz imkoniyati ham yo'q; hamkor paneli,
  Mini App, POS va eski panellarga kira olmaydi.

### Token: alohida `admin` turi

- Sessiya — Sanctum token qatori, nomi va yagona huquqi `admin` (`App\Auth\AdminTokenGuard`, guard `auth:admin`).
  Har kirish — bitta qator; "Sessiyalarim"da ko'rinadi va bekor qilinadi.
- Muddat: kirishdan 12 soat (`ADMIN_SESSION_LIFETIME`), so'rovsiz 60 daqiqa (`ADMIN_SESSION_IDLE`) o'tsa tugaydi —
  keyin 401.
- **Ikki tomonlama devor:** admin tokeni do'kon, mijoz va terminal endpointlarida 401 oladi (superadmin'niki ham);
  do'kon, mijoz, xodim va terminal tokenlari `/admin/*` da 401 oladi. `platform` rolidagi a'zoning boshqa tokenlari
  umuman ishlamaydi; o'chirilgan a'zoniki ham.

### Kirish ketma-ketligi (`/admin/auth`, cheklangan)

1. `POST /admin/auth/login {email, password}` → `data: {status: "otp_required" | "setup_required", challenge_token,
   expires_at}`. Challenge 5 daqiqa, bir marta ishlatiladi. Noma'lum email va noto'g'ri parol — bir xil 422
   `invalid_credentials`. O'chirilgan a'zo (faqat to'g'ri paroldan keyin) — 403 `member_disabled`.
   `setup_required` faqat **o'rnatish oynasi** ichida: taklifni qabul qilgandan yoki 2FA tiklangandan keyin 24 soat
   (`users.two_factor_setup_allowed_until`, 2FA yoqilganda yopiladi). 2FA'siz a'zo oynadan tashqarida to'g'ri parol
   bilan 403 `setup_not_allowed` oladi (challenge berilmaydi): parolni topgan odam o'z ilovasini ulay olmaydi. Yo'l —
   yangi taklif (`rizqim:admin:invite`) yoki 2FA'ni tiklash.
2. `POST /admin/auth/2fa/setup {challenge_token}` (faqat `setup_required`) → `{secret, otpauth_uri, qr_svg}` (issuer
   `RIZQIM`, label — email; QR serverda chiziladi, SVG matn). Shu challenge bilan qayta so'ralsa — o'sha kalit.
   2FA yoqilgan bo'lsa — 409 `two_factor_already_enabled`.
3. `POST /admin/auth/2fa/confirm {challenge_token, code}` → sessiya `{token, expires_at, member}` va
   `recovery_codes` (10 ta, faqat shu javobda; bazada xeshlangan).
4. `POST /admin/auth/2fa/verify {challenge_token, code | recovery_code}` → sessiya `{token, expires_at, member}`.
   Zaxira kodi bir marta ishlaydi. Qabul qilingan kod (yoki undan eskisi) qayta ishlamaydi — 30 soniyalik oyna
   ichida ham.
5. `POST /admin/auth/invite/accept {token, password}` (taklif havolasidan; parol kamida 12 belgi, email emas) →
   `setup_required` challenge (keyin 2- va 3-qadam; o'rnatish oynasi 24 soatga ochiladi). 2FA'si bor a'zoga
   (`rizqim:admin:invite` bilan unutilgan parol) — `otp_required`. Hisobi bor a'zoning barcha sessiyalari yopiladi.
   Eskirgan, ishlatilgan yoki qayta berilgan havola — 422 `invite_invalid`.

Qulflash: bitta hisob + bitta IP manzildan (IPv6 — butun `/64` tarmoq) 5 ta noto'g'ri parol yoki 5 ta noto'g'ri kod
(2FA, zaxira, step-up) — 15 daqiqaga 429 `locked` (`data.retry_after`, `Retry-After` sarlavhasi). Hisob bo'yicha
barcha manzillardan jami 15 ta noto'g'ri parol (alohida 15 ta kod) — hisob hamma joydan 15 daqiqaga bloklanadi (to'g'ri
parol bilan ham). Noma'lum email ham xuddi shunday bloklanadi (farqi bilinmasin). Eski `/superadmin` panel bilan
hisoblagich umumiy. Jamoa hisobiga har bir urinish jurnalga yoziladi (parol, kod, tokenlarsiz); jamoa a'zosiga
tegishli bo'lmagan email bilan urinishlar jurnalga emas, ilova logiga (daqiqasiga ko'pi bilan 30 qator, emailsiz).

### Sessiya bilan

| Endpoint | Nima |
|---|---|
| `GET /admin/me` (yoki `GET /admin/auth/me`) | `{member, platform_role, permissions, two_factor_enabled, recovery_codes_left, last_login_at, session}` |
| `POST /admin/auth/logout` | joriy sessiyani yopadi |
| `GET /admin/auth/sessions` | mening jonli sessiyalarim (`current` — shu sessiya) |
| `DELETE /admin/auth/sessions/{id}` | sessiyani yopadi; boshqasini yopish uchun step-up kerak |
| `POST /admin/auth/confirm {code}` | **step-up**: joriy sessiya 10 daqiqa "tasdiqlangan" bo'ladi |
| `POST /admin/auth/2fa/recovery-codes` | yangi 10 ta zaxira kodi (step-up) |

**Step-up (`admin.recent`):** muhim amal oldidan yangi TOTP kod so'raladi. Tasdiqsiz bo'lsa javob 403
`{code: "reauth_required"}` — ilova kodni so'raydi, `POST /admin/auth/confirm` qiladi va so'rovni bir marta qayta
yuboradi. Hozir: jamoa va rollar o'zgarishi, 2FA tiklash, zaxira kodlari, boshqa sessiyani yopish; 2-bosqichda pul
va buyurtmaga aralashish amallari.

### Jamoa va rollar (`admin.team.manage`)

| Endpoint | Nima |
|---|---|
| `GET /admin/team`, `GET /admin/team/{id}` | a'zolar: rol, `status` (`invited`, `active`, `disabled`), 2FA, oxirgi kirish, jonli sessiyalar |
| `POST /admin/team {name, email, platform_role}` | taklif (step-up): a'zo yaratiladi, `invite_url` (72 soat) faqat shu javobda; qabul qilgach 24 soat ichida 2FA o'rnatiladi |
| `POST /admin/team/{id}/invite` | taklifni qayta berish (step-up); eski havola ishlamay qoladi |
| `PATCH /admin/team/{id} {platform_role?, disabled?}` | rolni o'zgartirish, o'chirish/yoqish (step-up); o'chirish barcha sessiyalarni yopadi |
| `POST /admin/team/{id}/reset-2fa` | 2FA ni o'chirish (step-up): a'zo 24 soat ichida kirib qayta o'rnatadi (keyin `setup_not_allowed` — qayta tiklang) |
| `GET /admin/roles` | `{roles: [{key, label, editable}], permissions: [{key, label, group}], grants: {rol: [ruxsat…]}, defaults: {rol: [ruxsat…]}}` — `defaults` o'zgartirishlarsiz standart jadval ("O'zgartirilgan" belgisi uchun) |
| `PUT /admin/roles {role, permission, allowed}` | `allowed`: `true`, `false`, `null` (standartga qaytarish) (step-up); **faqat superadmin** (boshqalarga 403 `superadmin_only`) |

Qoidalar: o'zingizning rolingizni o'zgartira, o'zingizni o'chira yoki 2FA'ingizni tiklay olmaysiz
(`cannot_change_self`); kirish mumkin bo'lgan (faol, paroli va 2FA'si bor) oxirgi superadmin qoladi
(`last_superadmin`: qabul qilinmagan taklif yoki 2FA'siz eski superadmin hisobga olinmaydi); superadmin hisobiga va
superadmin rolini berishga faqat superadmin tegadi (`superadmin_only`). Superadmin roli o'zgarmaydi
(`role_not_editable`). Rollar jadvalini (`PUT /admin/roles`) faqat superadmin o'zgartiradi. **Imtiyozli ruxsatlar**
(`team.manage`, `pii.view`, `system.manage`, `orders.refund_approve`, `payouts.approve`;
`AdminPermissionService::PRIVILEGED`): `team.manage` li superadmin bo'lmagan a'zo bunday ruxsati bor rolga taklif
qila, a'zoni shunday rolga o'tkaza, shunday roldagi a'zoning taklifini qayta bera, uni o'zgartira, o'chira yoki 2FA'sini
tiklay olmaydi (403 `superadmin_only`); qolgan rollar bilan avvalgidek ishlaydi.

Ruxsatlar (`App\Enums\AdminPermission`, `admin.*`): `today.view`, `orders.view`, `orders.intervene`,
`orders.refund_request`, `orders.refund_approve`, `stores.view`, `stores.manage`, `stores.impersonate`,
`finance.view`, `finance.manage`, `payouts.approve`, `subscriptions.manage`, `devices.view`, `monitoring.view`,
`monitoring.manage`, `support.view`, `support.manage`, `users.view`, `marketing.manage`, `integrations.view`,
`integrations.manage`, `system.manage`, `team.manage`, `audit.view`, `pii.view`, `export`, `marketing.view`. Standart jadval:
`App\Services\Admin\PlatformRolePermissions` (Faqat ko'rish — hamma `*.view`, `pii.view` va `export` siz).

### Harakatlar jurnali (`admin.audit.view`)

`GET /admin/audit-logs` — butun platforma (jamoa va hamma do'konlar yozuvlari), eng yangisi birinchi, kursor bilan
(`cursor` ← `next_cursor`, `per_page` 1–100). Filtrlar: `actor_id`, `action` (prefiks: `admin.team` yoki
`admin.team.*`), `store_id`, `subject_type` + `subject_id`, `from`/`to`, `q`. Har yozuvda `actor`, `store`,
`subject_type/id`, `before`, `after`, `meta`, `reason`, `ip`, `user_agent`.

- Har bir admin o'zgartirishi avtomatik yoziladi (`App\Http\Middleware\RecordAdminAudit`): amal nomi — route nomi
  (`admin.team.updated`, `admin.roles.updated`...), aktyor, obyekt, oldin/keyin, sabab, IP, brauzer.
- Jurnal **o'zgarmaydi**: model ham, PostgreSQL trigger ham yozuvni yangilash va o'chirishni rad etadi.
- IP va brauzer so'rovdan olinadi: admin ilovasining server proksisi (ichki tarmoq manzili) yuborgan
  `X-Forwarded-For` ning oxirgi bo'g'ini va brauzer `User-Agent` i (ishonchli proksilar: `bootstrap/app.php`
  `trustProxies`, faqat xususiy tarmoqlar). Taklif havolalari `ADMIN_APP_URL` ga olib boradi.
- Sabab talab qiladigan amallar (2-bosqich) `reason` (3–500 belgi) oladi: `App\Http\Requests\Admin\AdminRules::reason()`.

### Qidiruv

`GET /admin/search?q=` (kamida 2 belgi) → `[{type, id, title, subtitle, href}]`: `store` (nomi, STIR, telefon;
`stores.view`), `order` (kod yoki uuid boshi; `orders.view`), `user` (telefon, ism; `users.view`), `member` (jamoa;
`team.manage`), har turdan ko'pi bilan 5 ta. `href` — admin ilovadagi yo'l (`/stores/{id}`, `/orders/{uuid}`,
`/users/{id}`, `/team/{id}`). Telefon bo'yicha: `admin.pii.view` bor a'zoda raqamning istalgan 4+ raqami; yo'q a'zoda
faqat **to'liq raqam** (`+998901234567`, `90 123 45 67`, `+998 (90) 123-45-67` — bir xil), aks holda niqoblangan
raqamni bo'lak-bo'lak qidirib ochib olish mumkin edi.

**Shaxsiy ma'lumot:** telefon `+998 90 *** ** 67`, karta `8600 **** **** 1234` ko'rinishida (`App\Support\PiiMask`,
`App\Services\Admin\AdminPii`). `admin.pii.view` bor a'zoga to'liq ko'rsatiladi va har ochilish jurnalga
`admin.pii.revealed` bo'lib yoziladi.

### Xato formati

`{success: false, message, code, data: {reason, ...}}` — `code` va `data.reason` bir xil: `invalid_credentials`,
`member_disabled`, `locked`, `challenge_invalid`, `invalid_code`, `invalid_recovery_code`, `setup_required`,
`setup_not_started`, `setup_not_allowed`, `two_factor_already_enabled`, `invite_invalid`, `invite_already_accepted`, `email_taken`,
`reauth_required`, `forbidden`, `superadmin_only`, `cannot_change_self`, `last_superadmin`, `role_not_editable`,
`not_found`. Validatsiya — odatdagidek 422 `errors`.

### Eski panellar

`/superadmin` (Filament) endi xuddi shu 2FA ni talab qiladi: bir xil kalit, zaxira kodlari va qayta ishlatishni
taqiqlash qoidasi (`App\Filament\Auth\PlatformAppAuthentication`), bir xil bloklash hisoblagichi (parol ham, kod
ham) va jurnal (`admin.auth.2fa_failed` / `2fa_succeeded`, `channel: filament`). 2FA siz superadmin uni panelda ham
faqat o'rnatish oynasi ichida o'rnatadi. `/admin` (do'kon paneli) sessiyasi bilan bir xil, shuning uchun superadmin u
yerga ham faqat 2FA bilan kiradi; egalar va menejerlar avvalgidek.

Admin ilovasi sessiya cookie'si: `__Host-rz_admin` (httpOnly, Secure, `Path=/`, domensiz, `SameSite=Strict`); faqat
`SESSION_COOKIE_SECURE=false` (http:// orqali sinov) da `rz_admin`.

### 2-bosqich uchun ulash joylari

- Bo'lim route fayllari: `routes/api_admin_today.php`, `api_admin_orders.php`, `api_admin_monitoring.php` —
  `routes/api_admin.php` oxiridagi izoh joyiga `require` qilinadi (imzolangan guruh ichida, jurnal avtomatik).
- Real-time: `App\Events\Broadcast\PlatformOpsBroadcast` dan meros oling (kanal `platform.ops`).
- Migratsiyalar (qaytariladigan): `2026_11_01_100100` … `2026_11_01_100600` (users jamoa ustunlari,
  `platform_role_permissions`, `admin_login_challenges`, `platform_invites`, token sessiya ustunlari, jurnal triggeri),
  `2026_11_03_100100` (`users.two_factor_setup_allowed_until`, 2FA o'rnatish oynasi).

Testlar: `tests/Feature/AdminAuthLoginTest.php`, `AdminSessionTest.php`, `AdminTeamTest.php`, `AdminAuditTest.php`,
`AdminSearchTest.php`, `AdminChannelAndPanelTest.php`, `AdminAccessHardeningTest.php` (xavfsizlik tekshiruvi: rollar,
parol tanlash, 2FA oynasi, Filament, qidiruv).

## Bugun (P4-13)

Boshqaruv markazining bosh sahifasi: bugungi raqamlar va "Diqqat talab qiladi". Ikkalasi ham `admin.today.view`
(superadmin, operator, faqat ko'rish), faqat o'qiladi. Kod: `routes/api_admin_today.php`,
`App\Http\Controllers\Api\V1\Admin\AdminTodayController`, `App\Services\Admin\Today`,
`App\Services\Admin\Attention\AttentionCollector`.

### Nima hisoblanadi

| Ko'rsatkich | Ta'rif |
|---|---|
| Kun | **Toshkent vaqti** (Asia/Tashkent) bo'yicha kalendar kun, 00:00 dan 24:00 gacha. Buyurtma yaratilgan vaqti (`created_at`) bo'yicha kunga va soatga tushadi |
| Marketpleys buyurtmasi | kanal `telegram` yoki `mobile` (RIZQIM boti, Mini App, ilova). Kassa (`pos_hall`, `pos_takeaway`) va tashqi agregator (`aggregator`) buyurtmalari GMV ga kirmaydi, faqat kanallar bo'linmasida ko'rinadi |
| Hisoblanadigan buyurtma | o'chirilmagan, holati `canceled`, `rejected`, `refunded` emas va **to'lovini kutayotgan onlayn buyurtma emas** (holati `new`, to'lov usuli `config/orders.php` → `online_payment_methods` — click, payme, uzum, paynet — dan biri, `payment_status` esa `paid` emas: mijoz hali to'lamagan, ovqatxona uni ko'rmagan; to'lanmasa `CancelUnpaidOnlineOrdersJob` uni bekor qiladi). Jarayondagi (qabul qilinishi kerak bo'lgan yangi, tayyorlanayotgan, yo'ldagi) buyurtmalar kiradi; keyin to'lansa, bekor qilinsa yoki pul qaytarilsa, keyingi yangilanishda kiradi yoki chiqib ketadi (pastga qarang) |
| **GMV** | hisoblanadigan marketpleys buyurtmalarining `total_price` yig'indisi — mijoz to'laydigan summa (chegirmadan keyingi taom + yetkazish + xizmat haqi va joy puli) |
| **Buyurtmalar** | shu buyurtmalar soni |
| **Komissiya** | shu buyurtmalarning `commission_amount` yig'indisi — checkout paytida yozilgan va yetkazilganda ledger aynan shu summani oladigan komissiya. Ya'ni bugungi buyurtmalar olib keladigan komissiya; "Rizqim daromadi" oylik hisoboti esa uni **yetkazilgan kuni** bo'yicha yozadi, shuning uchun kun chegarasida ikkisi biroz farq qilishi mumkin |
| **Obuna daromadi** | holati `paid` bo'lgan obuna hisob-fakturalarining `amount` yig'indisi, to'langan vaqti (`paid_at`) bo'yicha |
| Bekor qilinganlar | `canceled` va `rejected` marketpleys buyurtmalari soni (qo'shimcha ko'rsatkich) |

Pul summalari — o'nlik satr (`"120000.00"`), float emas.

### `GET /admin/today`

```
data: {
  date: "2026-11-10", timezone: "Asia/Tashkent",
  generated_at, stats_computed_at, live_from,
  kpis: { gmv, orders, commission, subscription_revenue, canceled_orders },
  channels: [{channel, label, marketplace, orders, gmv}],
  regions:  [{region_id, name, orders, gmv, active_stores}]
}
```

- Har bir `kpis.*`: `value` (bugun hozirgacha), `yesterday_same_time` va `last_week_same_time` (kecha va o'tgan haftaning
  shu kuni, **xuddi shu soat-daqiqagacha**), `yesterday` va `last_week` (butun kun), `vs_yesterday` va `vs_last_week`:
  `{direction: "up" | "down" | "same", percent}` — `percent` oldingi qiymatga nisbatan, bir xona aniqlikda; oldingi qiymat
  0 bo'lsa `null`. Solishtirma "shu vaqtgacha" bilan qilinadi: tushdagi bugunni kechaning butun kuni bilan solishtirish
  noto'g'ri bo'lardi.
- `channels`: bugun hamma kanallar (marketpleys birinchi, `marketplace: true`).
- `regions`: buyurtma filialining viloyati bo'yicha bugungi marketpleys buyurtmalari va GMV; `active_stores` — holati
  `active`, o'chirilmagan, shu viloyatda faol filiali bor ovqatxonalar. Viloyati yo'q filial — `region_id: null`,
  "Viloyat ko'rsatilmagan". Eng ko'p buyurtmali viloyat birinchi.
- `stats_computed_at` — yig'ma jadvallar bugun uchun oxirgi marta qachon yangilangani (null: hali yangilanmagan);
  `live_from` — shu vaqtdan boshlab raqamlar to'g'ridan-to'g'ri buyurtmalardan sanaladi.

### Yig'ma jadvallar va yangilanish

Sahifa hamma buyurtmalarni har so'rovda qayta sanamaydi:

- `platform_hourly_stats` (sana, soat, viloyat, kanal) va `platform_daily_stats` (sana, viloyat, kanal): buyurtmalar
  soni, GMV, komissiya, bekor qilingan va qaytarilganlar soni; `channel = subscriptions` qatorlarida to'langan
  hisob-fakturalar soni va obuna daromadi (viloyat 0). Viloyat 0 — filialda viloyat yo'q.
- `platform_stats_days`: qaysi kun hisoblangan va uning soatlik qatorlari qaysi vaqtgacha yakuniy (`complete_through`):
  bugun uchun — oxirgi yangilanishdagi joriy soat boshi, o'tgan kun uchun — kun oxiri.
- `App\Jobs\RefreshPlatformStatsJob`: har **5 daqiqada** bugun va kechani, har kecha **00:20** (Toshkent) oxirgi 8 kunni
  noldan qayta hisoblaydi (keyin bekor qilingan yoki qaytarilgan buyurtmalar va "o'tgan hafta" solishtirmasi uchun).
  Qayta ishga tushirish xavfsiz; bir kunni bir vaqtda ikki jarayon yozmaydi (advisory lock).
- `GET /admin/today` yakuniy soatlarni jadvaldan, qolganini (odatda joriy soat) indeksli tor so'rov bilan jonli sanaydi.
  Shuning uchun raqamlar har doim shu daqiqagacha to'g'ri; yangilanish to'xtab qolsa, sahifa sekinlashadi, lekin
  xato ko'rsatmaydi.
- **Soat chegarasi va o'z-o'zidan tuzalish:** tugagan soatlar — oxirgi yangilanishdagi holat (surat). Shu soatdagi
  buyurtma keyin o'zgarsa (bekor qilinsa, pul qaytarilsa, to'lanmagan onlayn buyurtma to'lansa yoki job uni bekor
  qilsa), raqam keyingi yangilanishda, ya'ni **ko'pi bilan 5 daqiqada** to'g'rilanadi; joriy soat esa har doim jonli.
  Soat almashgandan keyin keyingi yangilanishgacha (≤5 daqiqa) o'tgan soat ham jonli sanaladi (`live_from` o'sha
  soat boshida qoladi), shuning uchun soat chegarasida raqam yo'qolmaydi va ikki marta sanalmaydi.
- O'tgan kunlarni to'ldirish (birinchi deploydan keyin yoki qo'lda tuzatish):
  `php artisan admin:stats-backfill --from=2026-10-01 --to=2026-11-01` (yoki `--days=35`, standart). Hisoblanmagan
  kun so'ralsa, u jonli sanaladi (to'g'ri, lekin sekinroq).

### `GET /admin/today/attention?limit=`

```
data: { groups: [{kind, label, count, severity, items: [{kind, severity, title, subtitle, href, since, store_id}]}], generated_at }
```

- Har bir bo'lim o'z manbalarini (`App\Services\Admin\Attention\AttentionSource`, `admin.attention` tegi) o'z service
  provider'ida ro'yxatdan o'tkazadi; endpoint a'zo ruxsati yetadigan barcha manbalarni so'raydi.
- Bitta guruh — bitta muammo turi: `count` — hammasi, `items` — ko'pi bilan `limit` ta (1–20, standart 5), eng
  muhimi birinchi. Guruhlar: avval `critical`, keyin `warning`, keyin `info`; bir xil darajada soni ko'pi birinchi.
  Hech narsa bo'lmasa `groups: []` (sahifada "Hammasi joyida").
- `href` — admin ilovadagi karta yo'li; `since` — muammo qachondan beri (ISO 8601, Toshkent vaqti).
- Bir manba xato bersa, u Sentry/logga yoziladi va ro'yxatdan tushib qoladi; qolganlari ko'rinadi.
- Manba guruh nomini o'zi bermasa (`DescribesAttention`), nom `kind` bo'yicha jadvaldan olinadi.

Bugun bo'limining manbalari (`App\Services\Admin\Today\Attention`, `App\Providers\AdminTodayServiceProvider`):

| `kind` | Nima | Ruxsat | Daraja | `href` |
|---|---|---|---|---|
| `settlement_failed` | yetkazilgan, lekin ledger yozuvi yiqilgan buyurtmalar (`settlement_failed_at`) | `finance.view` | avtomatik urinishlar tugagan bo'lsa `critical`, aks holda `warning` | `/orders/{uuid}` |
| `fiscal_failed` | oxirgi 7 kunda yiqilgan fiskal cheklar (`status = failed`) | `devices.view` | `critical` | `/pos?fiscal={uuid}` |
| `device_offline` | faol ovqatxonaning POS/KDS terminali 10 daqiqadan beri aloqa qilmagan, filial esa hozir ish vaqtida (filial ish soatlari; filialsiz yoki soati belgilanmagan filial — ovqatxona soatlari; `pos_device_offline` ogohlantirishi bilan bir xil qoida, Monitoring bo'limiga qarang; ovqatxona "yopiq" qilingan bo'lsa — yo'q). 3 kundan ko'p ko'rinmagan terminallar bu yerda emas | `devices.view` | `warning` | `/pos?device={uuid}` |
| `invoice_overdue` | to'lov muddati (Toshkent sanasi) o'tgan, to'lanmagan obuna hisob-fakturalari | `finance.view` | `warning` | `/pos?invoice={uuid}` |
| `payout_pending` | tasdiq kutayotgan pul yechish so'rovlari (karta raqami ko'rsatilmaydi) | `finance.view` | `warning` | `/finance?payout={id}` |
| `topup_pending` | 30 daqiqadan beri to'lanmagan balans to'ldirishlari (Click/Payme), oxirgi 3 kun | `finance.view` | `info` | `/finance?topup={uuid}` |

Boshqa bo'limlarniki: buyurtmalar (P4-14 — qabul qilinmagan, kechikkan, kuryersiz), monitoring (P4-15 — ochiq
ogohlantirishlar). `/pos?…` va `/finance?…` havolalari shu bo'limlar o'z kartalarini qo'shguncha bo'lim sahifasini ochadi.

Indekslar (`2026_11_02_100100`): `orders(created_at)`, `subscription_invoices(paid_at)`, `(status, due_date)`,
`payout_requests(status, created_at)`, `store_topup_requests(status, created_at)`, `fiscal_receipts(status, created_at)`,
`devices(last_seen_at)` (faqat ulangan, bekor qilinmagan). Migratsiyalar: `2026_11_02_100000` (jadvallar), `2026_11_02_100100`.

Testlar: `tests/Feature/AdminTodayStatsTest.php`, `AdminTodayApiTest.php`, `AdminTodayAttentionTest.php`.

## Buyurtmalar (P4-14)

Boshqaruv markazining **Buyurtmalar** bo'limi: hamma ovqatxonalarning marketpleys buyurtmalari, kechikish (SLA), buyurtma
kartasi, aralashish va pulni qaytarish. Kod: `routes/api_admin_orders.php`, `App\Http\Controllers\Api\V1\Admin\AdminOrder*`,
`AdminRefundRequestController`, `App\Services\Admin\Orders`.

**Qaysi buyurtmalar:** kanal `telegram` yoki `mobile` (RIZQIM boti, Mini App, ilova) — "Bugun"dagi GMV bilan bir xil.
Kassa (`pos_hall`, `pos_takeaway`) va agregator buyurtmalari bu yerda ko'rinmaydi (karta ham 404).

### Ruxsatlar

| Endpoint | Ruxsat | Step-up | `reason` |
|---|---|---|---|
| `GET /admin/orders`, `GET /admin/orders/{uuid}`, `GET /admin/orders/sla`, `GET /admin/orders/store-quality`, `GET /admin/refund-requests` | `orders.view` | — | — |
| `PUT /admin/orders/sla` | `system.manage` | ha | ha |
| `POST /admin/orders/{uuid}/cancel`, `/status`, `/message` | `orders.intervene` | ha | ha |
| `POST /admin/orders/{uuid}/refund-requests` | `orders.refund_request` | ha | ha |
| `POST /admin/refund-requests/{id}/approve`, `/reject` | `orders.refund_approve` | ha | ha |

Har bir amal jurnalga yoziladi (amal nomi — route nomi: `admin.orders.cancelled`, `admin.orders.status_fixed`,
`admin.orders.messaged`, `admin.orders.refund_requested`, `admin.refund_requests.approved`, `admin.refund_requests.rejected`,
`admin.orders.sla.updated`), obyekt — buyurtma, oldin/keyin holati va sabab bilan. Shuning uchun buyurtma kartasining
"Jurnal" qismida ovqatxonaning o'z yozuvlari ham, jamoa yozuvlari ham bor.

### SLA (kechikish chegaralari)

Uch bosqich, daqiqada (`settings` jadvali: `orders.sla.accept_minutes`, `orders.sla.prepare_minutes`,
`orders.sla.deliver_minutes`):

| Bosqich | Qachondan | Qachongacha | Standart |
|---|---|---|---|
| `accept` (qabul qilish) | buyurtma kelgan vaqt. Faqat ovqatxona qabul qilishi kerak bo'lgan yangi buyurtma: naqd yoki keshbek, onlayn to'lov bo'lsa — to'langan. To'lanmagan onlayn buyurtma mijozni kutadi, unga SLA yo'q | qabul qilindi / rad etildi / bekor qilindi | 5 |
| `prepare` (tayyorlash) | tarixdagi birinchi `accepted` | `ready` (yoki undan keyingi holat) | 30 |
| `deliver` (yetkazish) | tarixdagi birinchi `on_the_way` / `delivery` (faqat yetkazib berish) | `delivered` | 45 |

`ready` va `courier_assigned` holatida bosqich yurmaydi (kuryersiz tayyor buyurtma — alohida "Diqqat talab qiladi" qatori).
Holat: `ok`; chegaraning **80%** idan `warning`; chegaradan `late` va `late_by_minutes` (butun daqiqa). Har bir ro'yxat
qatori va kartada `sla`:

```
sla: {stage, stage_label, state: "ok"|"warning"|"late", limit_minutes, started_at, warning_at, due_at,
      late_by_minutes, minutes_left} | null
```

`GET /admin/orders/sla` → `{limits: {accept, prepare, deliver}, defaults, bounds, warning_ratio: 0.8}`.
`PUT /admin/orders/sla {accept?, prepare?, deliver?, reason}` — chegaralar: accept 1–120, prepare 5–300, deliver 5–300.
O'zgarish darhol ishlaydi (ro'yxat, "Diqqat" qatorlari, kuzatuvchi).

**Kuzatuvchi (har daqiqa, `WatchOrderSlaJob`, scheduler):** bosqichi `warning` yoki `late` bo'lgan buyurtmalarni topadi va
har bir o'tishni **bir marta** `order_sla_events` ga yozadi (buyurtma + bosqich + holat yagona). Yangi o'tish
`platform.ops` kanaliga `ops.order.updated` (`change: "sla"`) bo'lib boradi. Ikki ishga tushish orasida to'g'ridan-to'g'ri
`late` ga o'tgan buyurtmaga `warning` ham yoziladi, lekin faqat `late` e'lon qilinadi. Bir ishda ko'pi bilan 500 ta,
faqat yozilishi kerak bo'lgan o'tishi bor buyurtmalar (`late` — `late` yozuvisiz, `warning` oralig'ida — `warning`
yozuvisiz): o'z oralig'ida allaqachon yozilgan buyurtmalar qancha bo'lmasin, yangi o'tishni keyingi ishga surib qo'ymaydi.

### `GET /admin/orders` — ro'yxat

Eng yangisi birinchi, kursor bilan (`cursor` ← `next_cursor`, `per_page` 1–100, standart 30). Filtrlar:

- `status` — guruh (superadmin panelidagi tablar bilan bir xil): `active` (yakunlanmagan), `new`, `kitchen` (accepted,
  preparing, ready), `en_route` (courier_assigned, on_the_way, delivery), `done` (delivered, refunded), `canceled`
  (canceled, rejected);
- `sla` — `late` yoki `warning`; `channel` — `telegram`, `mobile`; `store_id`; `region_id` (filial viloyati);
- `from` / `to` — `Y-m-d`, **Toshkent kuni** (ikkala chet kiradi);
- `q` — qisqa kod (`#` bilan ham), uuid boshi yoki mijoz telefoni: `pii.view` bilan telefonning istalgan 4+ raqami,
  `pii.view` siz faqat **to'liq raqam** (`+998` va 9 raqam yoki faqat 9 raqam; bo'shliq, chiziqcha, qavs e'tiborga
  olinmaydi) — niqoblangan telefonni raqamma-raqam taxmin qilib bo'lmaydi. `counts` ham shu qoida bilan.

Javob: `{data: [qator], next_cursor, per_page, counts: {active, new, kitchen, en_route, done, canceled}, sla_limits,
server_time}`. `counts` — o'sha filtrlar bilan (guruhdan tashqari); `done` va `canceled` sana berilmasa faqat
**bugungi** (Toshkent). Qatorda telefon yo'q: `uuid, short_code, status, status_label, status_group, type, channel,
channel_label, store {id, name}, branch, region, customer_name, total, payment_method, payment_status, courier {id, name},
sla, refund_pending, manual_refund_required, created_at, updated_at`. `manual_refund_required` — bekor qilingan, rad
etilgan yoki qaytarilgan buyurtmada hali qaytarilishi kerak pul bor (kartadagi `refunds.refundable` > 0): qisman
tasdiqdan keyin ham qolgani uchun `true` bo'lib qoladi.

### `GET /admin/orders/{uuid}` — karta

Qator maydonlari va: `customer {id, name, phone, has_telegram}`, `courier {id, name, phone}`, `store {id, name, phone}`
(telefonlar `pii.view` siz niqoblangan, ochilsa `admin.pii.revealed` yoziladi), `branch {id, name, address}`,
`delivery_address`, `notes`, `items[]`, `totals` (subtotal, discount, delivery_price, service_charge, place_fee, total,
commission, payment_owner), `cancel {reason, reason_label, note}`, `payments[]` (provider, amount, status, paid_at,
`manual_refund_required` — belgilangan, to'langan va hali hammasi qo'lda qaytarishga tasdiqlanmagan,
`manual_refund_approved[]` — shu to'lovga tushgan tasdiqlar va summalari), `refunds {paid, refunded, refundable,
requests[]}`, `history[]`
(holat, vaqt, `actor {id, name, role, team}` — `team: true` RIZQIM jamoasi a'zosi, null — tizim, `notes`),
`sla_timeline[]` (bosqich, boshlanishi, tugashi, necha daqiqa, kechikdimi), `sla_events[]`, `fiscal_receipts[]`,
`audit[]` (oxirgi 50) va `actions` — shu a'zo hozir nima qila oladi: `can_cancel`, `cancel_reasons[]`, `transitions[]`,
`message_store`, `message_customer`, `can_request_refund`, `can_approve_refund`.

### Aralashish

Holatni hech qachon to'g'ridan-to'g'ri yozmaydi: hammasi `OrderStatusService::transition` orqali (holat mashinasi,
`OrderPolicy`, ombor, keshbek, qaytarish belgisi, hisob-kitob, mijoz xabarlari, ovqatxona real-time hodisalari). A'zo
o'z nomidan ishlaydi: `OrderPolicy` `platform` rolidagi a'zoga `orders.intervene` bilan buyurtmani o'zgartirishga,
`orders.refund_approve` bilan qaytarishga ruxsat beradi; tarixda va ovqatxona jurnalida a'zo ko'rinadi.

- `POST .../cancel {cancel_reason, note?, reason}` — ovqatxona sabablari (`customer_request`, `no_show` — faqat qabul
  qilingan olib ketish, `out_of_stock`, `store_closed`, `courier_unavailable`, `other`); `note` mijozga ko'rinadi,
  `reason` — jamoaniki (jurnal va tarix). Pul: keshbek balansga qaytadi, Click/Payme yoki yig'ilgan naqd —
  "qo'lda qaytarilishi kerak" belgisi (keyin qaytarish so'rovi). Ovqatxona chatiga xabar boradi. Javob — karta.
  Yakunlangan buyurtma: 422 `illegal`.
- `POST .../status {status, courier_id?, reason}` — faqat hayot siklida joriy holatdan ruxsat etilgan keyingi holat
  (`actions.transitions`). Bekor qilish (`use_cancel`) va qaytarish (`use_refund_request`) bu yerda emas. **Majburiy
  o'tkazish ro'yxati yo'q**: boshqasi 422 `illegal`. Kuryerga o'tishda `courier_id` ovqatxona kuryeri bo'lishi kerak
  (422 `courier`), berilmasa eng bo'sh kuryer tanlanadi. Ovqatxona chatiga xabar boradi.
- `POST .../message {to: "store"|"customer", text, reason}` — Telegram (navbat orqali, oddiy matn): `store` —
  ovqatxonaning buyurtma chati va buyurtma kartasi boradigan menejerlar (yangi buyurtma kartasi bilan bir xil
  qabul qiluvchilar), `customer` — RIZQIM boti. 422 `no_recipient`, bot sozlanmagan — 503 `telegram_not_configured`.
  Javob `{recipients}`.

### Pulni qaytarish: so'rov va ikki kishilik tasdiq

1. `POST /admin/orders/{uuid}/refund-requests {amount, reason}` (`Idempotency-Key` ixtiyoriy) — faqat yakunlangan
   buyurtma (`delivered`, `canceled`, `rejected`, `refunded`; aks holda 422 `order_active`), summa 0 dan katta va
   **to'langan − qaytarilgan** dan oshmaydi (422 `amount_exceeds_refundable`, `data.refundable`), bir buyurtmada bir vaqtda
   bitta kutilayotgan so'rov (409 `refund_request_pending`). Holat `pending` ("Tasdiq kutilmoqda").
2. `POST /admin/refund-requests/{id}/approve {reason}` yoki `/reject {reason}` — **so'ragan a'zo emas** (403
   `four_eyes`, superadmin ham). `Idempotency-Key` yuboring: takror so'rov o'sha javobni qaytaradi va hech narsa ikki marta
   bajarilmaydi (jurnal ham ikki marta yozilmaydi). Bir xil qaror qayta yuborilsa ham shunday; boshqa qaror — 409
   `refund_request_decided`.
3. `GET /admin/refund-requests?status=pending|approved|rejected|all&store_id=` — tasdiq navbati.

**Tasdiq nima qiladi** (`execution`):

| Holat | `mode` | Nima bo'ladi |
|---|---|---|
| Yetkazilgan buyurtma, summa = to'langan hammasi va **oldin hech narsa qaytarilmagan** | `order_refunded` | `OrderStatusService::refund`: buyurtma `refunded`, ovqatxona komissiyasi va o'tkazilgan pul qaytariladi (`LedgerService::reverseDeliveredOrder`), keshbek mijoz balansiga qaytadi, Click/Payme yoki naqd to'lovga "qo'lda qaytarish" belgisi |
| Qolgan hamma holat: yetkazilgan buyurtmaning bir qismi (oldingi qismdan keyingi qoldiq ham), bekor qilingan / rad etilgan / qaytarilgan buyurtma | `balance` yoki `manual` | buyurtma holati o'zgarmaydi. Summa hali to'langan to'lovlarga, eng eskisidan boshlab, har biriga hali tasdiqlanmagan qismigacha bo'linadi (`allocations`). **Keshbek** to'lovning ulushi darhol mijoz balansiga aynan shu summada qaytadi (`OrderStatusService::refundKeshbek` kabi; to'lov `payload.refunded_to_balance_parts`; hammasi qaytgach to'lov `refunded`, boshqa to'langan to'lov qolmasa buyurtmaning `payment_status` ham `refunded` — keyingi qaytarish yo'li uni yana qaytarmaydi). Boshqa to'lovlarning ulushi — qo'lda qaytarish, to'lovga `manual_refund_approved` (o'z ulushi bilan) yoziladi. Hammasi balansga ketgan bo'lsa `balance`, aks holda `manual` |

`automatic_amount` — o'zi qaytgan qism (keshbek balansga), `manual_amount` — tasdiqlovchi to'lov tizimi kabinetida
(Payme, Click) yoki naqd qaytaradigan qism (`provider_refund: "manual"`, 0 bo'lsa `"none"`), `allocations[]` —
`{payment_id, provider, amount, kind: "balance"|"manual"}`. Faqat tasdiq tegishli to'lovlar `payload.refund_request_ids`
oladi; tasdiqdan oldin qaytarilgan to'lov hech qachon belgilanmaydi. **To'lov tizimi orqali avtomatik qaytarish yo'q**:
kodda Payme qaytarishi faqat Payme'ning `CancelTransaction` webhook'i sifatida keladi (kabinetdan qilinganda), Click
qaytarish API (`payment/reversal`) va Uzum ulanmagan.

**Qaytariladigan summa** (`RefundRequestService::balanceOf`): to'langan = `paid`/`refunded` to'lovlar yig'indisi;
qaytarilgan = har bir `refunded` to'lovning **to'liq** summasi (bekor qilishda keshbek, Payme bekor qilishi — oldin uning
bir qismi tasdiqlangan bo'lsa ham) + hali `paid` to'lovlarga tushgan tasdiqlangan ulushlar. Shuning uchun qisman
tasdiqdan keyin Payme butun tranzaksiyani bekor qilsa, qaytariladigan 0 bo'ladi (ikki marta qaytarilmaydi); kutilayotgan
so'rov tasdiqlanayotganda ham qayta tekshiriladi (422 `amount_exceeds_refundable`). Bir nechta to'lovli buyurtmada har bir
to'lov alohida hisoblanadi. Xabarlar va kartadagi summalar tiyin bo'lsa uni ham ko'rsatadi ("50 000,50 so'm").

**Qismlarga bo'lib qaytarish va ledger (P4-23):** yetkazilgan buyurtmaning bir qismi tasdiqlanganda ovqatxona
komissiyasi, o'tkazilgan pul va qarz yopilgani ham **shu ulushda** qaytariladi (`LedgerService::reverseDeliveredOrderShare`,
ulush = shu paytgacha qaytarilgan jami / to'langan, tiyingacha half-up), `execution.ledger_reversal {commission, settlement,
added_to_debt}`. Har so'rov bir marta (`order_ledger_reversals`, so'rov bo'yicha yagona); qismlar jami to'langanga yetsa,
to'liq qaytarishdagi summalarning aynan o'zi qaytgan bo'ladi, keyin buyurtmaning o'z qaytarish yo'li hech narsa qaytarmaydi;
qismdan keyingi to'liq qaytarish faqat qolganini qaytaradi. Buyurtma `delivered` qoladi. Naqd buyurtmada to'lov "to'langan"
deb belgilanmagan bo'lsa qaytarib bo'lmaydi (qaytariladigan 0).

**Buyurtmani qaytarish yo'li va oldingi qismlar:** `OrderStatusService::refund` (Filament superadmin panelidagi
"Qaytarish" tugmasi, Payme qaytarishi va boshqa har qanday chaqiruvchi) keshbekni `refundKeshbek` orqali qaytaradi: har
bir to'langan keshbek to'lovning summasidan tasdiqlangan so'rovlar orqali balansga allaqachon qaytgan ulushlarini
(`RefundRequestService::balanceOf` — bitta ta'rif) ayirib, **faqat qolganini** balansga qo'shadi; hech narsa qolmagan
bo'lsa hech narsa qilmaydi. To'lovda `payload.refunded_to_balance` — shu safar qaytgani,
`refunded_to_balance_by_requests` — oldin so'rovlar orqali qaytgani. Qayta chaqirish hech narsa o'zgartirmaydi.

### `GET /admin/orders/store-quality?store_id=` — ovqatxona sifati (30 kun)

`{period: {from, to, days: 30}, stores: [{store, orders, accepted, acceptance_rate, avg_accept_minutes, avg_prep_minutes,
canceled, cancel_rate, rating, review_count, ratings_in_period, rating_in_period}]}`. `orders` — ovqatxonaga yetib borgan
buyurtmalar (to'lanmagan onlayn va mijoz o'zi yangi holatda bekor qilganlar kirmaydi); `acceptance_rate` — qabul
qilingan ulush (0–1); `avg_accept_minutes` — naqd va keshbek buyurtmalar, kelgandan xodim qabul qilguncha;
`avg_prep_minutes` — qabul qilingandan tayyorgacha; `cancel_rate` — bekor qilingan va rad etilganlar ulushi. `store_id`
siz — buyurtmasi bor 50 ta ovqatxona, eng zaifi birinchi. 5 daqiqa keshlanadi.

### "Diqqat talab qiladi" manbalari (`orders.view`)

| `kind` | Nima | `severity` |
|---|---|---|
| `order_unaccepted` | qabul qilish chegarasidan o'tgan yangi buyurtma | `critical` |
| `order_late` | tayyorlash yoki yetkazish kechikkan | `warning`, chegaradan yana bir chegara ko'p kechiksa `critical` |
| `order_no_courier` | tayyor, kuryeri yo'q yetkazib berish buyurtmasi | `warning`, 10 daqiqadan keyin `critical` |

Har qator `href: /orders/{uuid}`.

### Real vaqt (`platform.ops`)

| Hodisa | Qachon | Tana |
|---|---|---|
| `ops.order.created` | yangi marketpleys buyurtma (checkout tranzaksiyasidan keyin) | `{order: {uuid, short_code, status, channel, type, store_id, branch_id, updated_at}}` |
| `ops.order.updated` | har bir holat o'zgarishi va kuryer biriktirish (`change: "status"`), SLA o'tishi (`change: "sla"`, `sla: {stage, state, late_by_minutes}`) | `{order: {...}, change, sla}` |

Shaxsiy ma'lumot va summa yo'q: ilova `GET /admin/orders` yoki kartani qayta so'raydi. Kassa buyurtmalari e'lon
qilinmaydi.

**Web klient** (`platform/admin/src/lib/realtime`): laravel-echo 2.6.1 + pusher-js 8.6.0, obuna ilovaning proksisi orqali
imzolanadi (`POST /api/backend/broadcasting/auth`, JSON). Sozlama Next serverining muhitidan: `REVERB_APP_KEY`,
`REVERB_HOST`, `REVERB_PORT`, `REVERB_SCHEME` (`GET /api/realtime`); kalit yoki host bo'lmasa real vaqt o'chiq.
`usePlatformOps(event, handler, {pollMs})` hook'i: hodisada `handler(payload)`, qayta ulanganda va real vaqt ishlamayotgan
paytda har `pollMs` da `handler(null)` — Reverb o'chsa ham sahifa so'rov bilan yangilanadi.

### Migratsiyalar va testlar

Migratsiyalar: `2026_11_02_200100` (`order_refund_requests`, bir buyurtmada bitta `pending` — qisman yagona indeks),
`2026_11_02_200200` (`order_sla_events`), `2026_11_02_200300` (`orders(status, created_at)`, CONCURRENTLY).

Testlar: `tests/Feature/AdminOrders/AdminOrderListTest.php`, `AdminOrderSlaTest.php`, `AdminOrderInterventionTest.php`,
`AdminRefundRequestTest.php`.

## Monitoring (P4-15)

Boshqaruv markazining **Monitoring** bo'limi: tizim holati, yiqilgan fon vazifalari, ogohlantirish qoidalari va ogohlantirishlar.
Hammasi `/api/v1/admin/monitoring` ostida, OpenAPI'da **Admin** guruhi. Kod: `routes/api_admin_monitoring.php`,
`app/Http/Controllers/Api/V1/Admin/AdminMonitoring*Controller.php`, `app/Services/Admin/Monitoring`.

| Endpoint | Ruxsat | Nima |
|---|---|---|
| `GET /admin/monitoring/health` | `monitoring.view` | Tizim holati (pastda) |
| `GET /admin/monitoring/failed-jobs` | `monitoring.view` | Yiqilgan vazifalar, kursor bilan |
| `GET /admin/monitoring/failed-jobs/{uuid}` | `monitoring.view` | Bitta vazifa: to'liq xato va ma'lumotlar (yashirilgan) |
| `POST /admin/monitoring/failed-jobs/{uuid}/retry` | `monitoring.manage` | Navbatga qaytarish |
| `DELETE /admin/monitoring/failed-jobs/{uuid}` | `monitoring.manage` + step-up | O'chirish, `reason` majburiy |
| `GET /admin/monitoring/alerts`, `GET .../alerts/{id}` | `monitoring.view` | Ogohlantirishlar |
| `POST /admin/monitoring/alerts/{id}/acknowledge` | `monitoring.manage` | "Men shug'ullanyapman", ixtiyoriy `note` |
| `POST /admin/monitoring/alerts/{id}/resolve` | `monitoring.manage` | Hal qilindi, ixtiyoriy `note` |
| `GET /admin/monitoring/rules` | `monitoring.view` | Qoidalar |
| `PUT /admin/monitoring/rules/{kind}` | `monitoring.manage` + step-up | Qoidani o'zgartirish, `reason` majburiy |

Rollar: Operator va Superadmin ko'radi va o'zgartiradi; "Faqat ko'rish" hammasini ko'radi, hech narsani o'zgartirmaydi;
Yordam, Moliya va Savdo monitoringni umuman ko'rmaydi (403 `forbidden`). Har o'zgartirish jurnalga `admin.monitoring.*`
nomi bilan yoziladi (`failed_job_retried`, `failed_job_deleted`, `alert_acknowledged`, `alert_resolved`, `rule_updated`).

### Tizim holati (`GET /health`)

Ma'lumotlar bazasi, Redis, navbat va scheduler hukmi `GET /health` bilan **bir xil hisobotdan** (`App\Support\HealthReport`)
olinadi, shuning uchun ikkita alohida tekshiruv yo'q. Qo'shimcha: har navbat bo'yicha o'lcham va eng eski vazifaning kutish vaqti,
yiqilgan vazifalar soni, Reverb, oxirgi zaxira, versiya va Sentry.

```json
{"status":"degraded","checked_at":"...",
 "database":{"status":"ok","latency_ms":1.4}, "redis":{"status":"not_used"},
 "queue":{"status":"ok","queues":[{"name":"default","size":0,"oldest_age_seconds":null}],
          "failed_jobs":{"total":3,"last_24h":2,"latest_failed_at":"..."}},
 "scheduler":{"status":"ok","last_heartbeat_age_seconds":20,"max_age_seconds":180},
 "reverb":{"status":"ok","latency_ms":4.2},
 "backup":{"status":"stale","last_backup_at":"...","age_hours":30,"max_age_hours":26},
 "app":{"version":"1.4.0","commit":"a1b2c3d","environment":"production"},
 "sentry":{"configured":true,"environment":"production","project_url":"https://sentry.io/..."},
 "alerts":{"open":2,"acknowledged":1,"critical_unresolved":1}}
```

- `status`: `ok`, `degraded` (ilova ishlaydi, lekin scheduler yoki navbat, Reverb yoki zaxira muammoli) yoki `fail` (baza yoki Redis).
- `reverb`: `ok`, `fail` yoki `not_configured` (`BROADCAST_CONNECTION` reverb emas). Reverb serverining `/up` manziliga
  oddiy so'rov (2 soniya, natija 15 soniya eslab qolinadi); Reverb o'chiq bo'lsa ham javob 200.
- `backup`: `ok`, `stale` (`backup_stale` qoidasidagi soatdan eski) yoki `missing` (zaxira xizmati hali vaqt yozmagan).
  Vaqtni `scripts/backup.sh` har tekshirilgan dump'dan keyin `monitoring_markers` jadvaliga yozadi (kalit `backup`), lekin
  sozlangan tashqi nusxa (`BACKUP_OFFSITE_CMD`, S3) ham muvaffaqiyatli bajarilgandan **keyin**: tashqi nusxa xato bersa belgi
  yozilmaydi (skript `2` kodi bilan tugaydi), belgi eskiradi va `backup_stale` ogohlantirishi chiqadi. `meta.offsite`: `ok`
  yoki `none` (tashqi nusxa sozlanmagan).
- `app.version` va `app.commit`: `APP_VERSION`, `APP_COMMIT` (yo'q bo'lsa `SENTRY_RELEASE`). `sentry.project_url`:
  `SENTRY_PROJECT_URL`; Sentry API'ga so'rov yuborilmaydi.

### Yiqilgan vazifalar

`GET /failed-jobs?cursor=&per_page=&queue=&q=&from=&to=` — eng yangisi birinchi, kursor bilan (`per_page` 1–100,
standart 25). Har qatorda: `uuid`, `job` (sinf nomi), `job_class`, `queue`, `connection`, `exception_line` (xatoning birinchi
qatori) va `failed_at`. `q` — vazifa sinfi nomining bo'lagi. `total` — hamma yiqilgan vazifalar.

`GET /failed-jobs/{uuid}` — qo'shimcha `exception` (stack trace bilan to'liq matn), `attempts`, `max_tries`, `payload` va
`details_hidden`. To'liq xato matni va `payload` (vazifa ma'lumotlari: Telegram matni, mijoz ismi, manzil...) faqat
`monitoring.manage` yoki `pii.view` ruxsati borlarga beriladi (`details_hidden: false`). Faqat `monitoring.view` borlar
(masalan "Faqat ko'rish") `details_hidden: true` oladi: vazifa sinfi, navbat, urinishlar, vaqt, xatoning **birinchi qatori**
(`exception` shu qatorning o'zi) va `payload` da faqat `displayName` va qayta urinish sozlamalari; `command` yo'q.

**Maxfiy qiymatlar hech qachon chiqmaydi.** Vazifaning ma'lumotlari ketma-ketlashtirilgan obyekt; u **hech bir sinfni
yaratmasdan** (`unserialize(..., allowed_classes: false)`) oddiy ma'lumotga aylantiriladi, so'ng `AuditRedactor` kalit nomi
bo'yicha (token, parol, kod, kalit, karta...) `[redacted]` qiladi va telefon raqamlarini yashiradi; matn ichidagi Bearer
tokenlar, Telegram bot manzillari (`/bot<token>`), telefon va karta raqamlari `SentryScrubber` bilan yashiriladi. Shifrlangan
yoki buzilgan vazifa `[o'qib bo'lmadi]` deb ko'rinadi. Jurnalga faqat vazifa nomi, navbat, uuid va xatoning birinchi qatori
(yashirilgan) yoziladi, ma'lumotlar emas.

`POST .../retry` — vazifa qayta navbatga qo'yiladi (urinishlar nolga qaytadi), ro'yxatdan chiqadi (`php artisan queue:retry`).
Qator avval atomik "egallanadi" (uuid vaqtincha almashtiriladi, o'zgargan qatorlar soni tekshiriladi) va faqat egallagan so'rov
navbatga qo'yadi: bir vaqtda ikki marta bosilsa ham vazifa bir marta qo'yiladi, ikkinchisi 404. Navbatga qo'yib bo'lmasa qator
joyida qoladi. `DELETE ...` — butunlay o'chiradi. Vazifa yo'q bo'lsa (allaqachon qayta urinilgan yoki o'chirilgan) — 404 `not_found`.

### Qoidalar va ogohlantirishlar

Yetti ichki qoida (`kind`), har biri `alert_rules` jadvalida bitta qator (yo'q bo'lsa standart qiymat bilan yaratiladi):

| `kind` | Qachon ogohlantirish ochiladi | Standart | Daraja |
|---|---|---|---|
| `failed_jobs_spike` | `window_minutes` ichida `threshold` va undan ko'p vazifa yiqildi | 10 ta, 15 daqiqa | diqqat |
| `queue_backlog` | Navbatda `threshold` dan ko'p vazifa, yoki eng eskisi `threshold_secondary` daqiqadan beri kutmoqda (har navbat alohida) | 500 ta, 10 daqiqa | diqqat |
| `pos_device_offline` | Faol ovqatxonaning kassasi yoki oshxona ekrani **ish vaqti ichida** `threshold` daqiqadan beri aloqada emas (har qurilma alohida) | 15 daqiqa | diqqat |
| `fiscal_failures` | `window_minutes` ichida `threshold` va undan ko'p fiskal chek `failed` bo'ldi | 1 ta, 60 daqiqa | kritik |
| `settlement_failures` | Hisobga yozilmagan (`settlement_failed_at`) buyurtmalar `threshold` va undan ko'p | 1 ta | kritik |
| `backup_stale` | Oxirgi zaxira `threshold` soatdan eski (yoki hech qachon yozilmagan) | 26 soat | kritik |
| `scheduler_stale` | Scheduler yurak urishi yo'q yoki `threshold` soniyadan eski | 180 soniya | kritik |

**"Hozir ochiq" — bitta qoida** (`App\Services\Admin\Monitoring\TerminalBusinessHours`), uni `pos_device_offline` ham,
"Bugun → Ish vaqtida oflayn kassalar" ham ishlatadi, shuning uchun ikkalasi hech qachon ixtilof qilmaydi: ovqatxona faol va
`is_open` (egasi "yopiq" qilmagan) bo'lishi shart; qurilmaning filiali bo'lsa — filial faol va o'z ish vaqti (`ZoneResolver::isBranchOpen`)
ichida; filialning ish vaqti belgilanmagan bo'lsa yoki filial yo'q bo'lsa — ovqatxonaning `open_time`/`close_time`. Vaqt ovqatxonaning
o'z vaqt mintaqasida (ochilish vaqti kiradi, yopilish vaqti kirmaydi; tundan oshadigan ish vaqti ham to'g'ri; vaqt belgilanmagan yoki
ochilish = yopilish bo'lsa — kun bo'yi ochiq). Kassa ish vaqti boshlanganidan beri aloqasiz turgan vaqt hisoblanadi: kechqurun
o'chirilgan kassa ertalab ochilish soatida darhol "oflayn" bo'lmaydi. **Kun bo'yi ochiq** ovqatxona/filialda hisob kassaning o'z
`last_seen_at` idan boshlanadi (mahalliy yarim tun hech narsani nolga tushirmaydi: ogohlantirish o'zi yopilib, 15 daqiqadan keyin
qayta ochilmaydi). Bir haftadan beri ko'rinmagan qurilma yopilgan deb hisoblanadi.

Katta uzilishda (masalan 150 kassa) **hamma** jim qurilmalar baholanadi (nomzodlar ish vaqti qo'llanmasdan oldin kesilmaydi).
Ogohlantirishlar soni `MAX_FINDINGS` (100) bilan cheklanadi, lekin cheklov faqat **yangi** ogohlantirishlarga tegishli: allaqachon
ogohlantirishi bor qurilma muammosi davom etsa har doim yangilanadi (hech qachon "baholanmagan" deb avtomatik yopilmaydi),
ortiqchasi esa bitta umumiy ogohlantirishga (`subject_key = many`, "N ta kassa yoki oshxona ekrani aloqada emas",
`details.offline_total`, `individual_alerts`, `withheld`) yig'iladi; uzilish tugagach u ham yopiladi.

`GET /rules` → `{rules: [...], telegram_configured}`. Qoidada: `kind`, `label`, `description`, `severity`, `enabled`, `telegram`,
`threshold`, `threshold_secondary`, `window_minutes` (qoida ishlatmaydigani `null`), `fields` (har chegara nimani anglatishi:
`label`, `unit`, `min`, `max`), `defaults`, `unresolved_alerts`, `updated_at`, `updated_by`.

`PUT /rules/{kind}` — `enabled`, `telegram`, `threshold`, `threshold_secondary`, `window_minutes` dan ixtiyoriylari va
majburiy `reason` (3–500 belgi). Qiymat qoidaning chegarasida bo'lishi shart; qoida ishlatmaydigan chegarani yuborish — 422.
Javob — hamma qoidalar. **Qoida o'chirilsa, uning ochiq ogohlantirishlari yopiladi** (`resolution: rule_disabled`, Telegram'ga
yuborilmaydi).

**Baholovchi (evaluator).** `EvaluateAlertsJob` har daqiqada ishlaydi (`withoutOverlapping`); u navbatga qo'yilmaydi, scheduler jarayonining o'zida bajariladi, shuning uchun navbat to'lib qolsa yoki ishchi bo'lmasa ham tekshiruv kechikmaydi. Yoqilgan har qoida uchun muammolar
ro'yxatini oladi:

- muammo bor, ochiq ogohlantirish yo'q — yangisi ochiladi (`open`), `ops.alert.opened` yuboriladi, Telegram'ga xabar ketadi;
- muammo bor, ogohlantirish allaqachon bor — u yangilanadi (`last_seen_at`, `count`, `details`), qayta ochilmaydi
  (bir qoida va obyekt uchun bitta tugallanmagan ogohlantirish: qisman yagona indeks `alerts_one_unresolved_per_subject`);
- muammo yo'q, ogohlantirish ochiq — o'zi yopiladi (`resolution: auto`), `ops.alert.resolved`, Telegram'ga "hal qilindi";
- muammo qaytsa — yangi ogohlantirish (yangi `first_seen_at`);
- tekshiruv xato bersa yoki manbani o'qiy olmasa — ogohlantirishlar o'zgarmaydi (buzuq o'lchov "tuzatib" qo'ymaydi).

**Poyga (race).** Qabul qilish, qo'lda hal qilish, avtomatik yopish va yangilash ma'lumotlar bazasidagi **shartli** `UPDATE`
(`WHERE id = ? AND state = 'open'` yoki `state <> 'resolved'`) bilan bajariladi va o'zgargan qatorlar soni tekshiriladi, xotiradagi
nusxaga ishonilmaydi. Yutgan tomon yagona: yopilgan ogohlantirish qayta "qabul qilinmaydi" (qisman yagona indeks bo'yicha 500
bo'lmaydi), ikki marta yopilmaydi, Telegram'ga "Hal qilindi" bir marta boradi, `ops.alert.*` bir marta yuboriladi.

> Eslatma: evaluator ham scheduler bilan ishga tushadi, shuning uchun scheduler butunlay to'xtasa `scheduler_stale` ogohlantirishi
> ochilmaydi (Holat sahifasi va `GET /health` baribir `stale`/`missing` ko'rsatadi). Tashqi uptime monitor (`docs/OPERATIONS.md`, 5-bo'lim) shu
> holatni ushlaydi.

`GET /alerts?state=&kind=&severity=&store_id=&cursor=&per_page=` — `state`: `active` (standart: ochiq va qabul qilingan),
`open`, `acknowledged`, `resolved`, `all`. Tugallanmaganlari hammasi birdan (200 tagacha): ochiqlari avval, kritiklari
avval, yangilari avval. `resolved` va `all` tarix: kursor bilan, eng yangisi birinchi. `counts`: ochiq, qabul qilingan,
oxirgi 7 kunda hal bo'lgan. Ogohlantirishda: `kind`, `kind_label`, `severity`, `state`, `title`, `details` (faqat sonlar va
nomlar), `store`, `first_seen_at`, `last_seen_at`, `count`, `acknowledged {by, at, note}`, `resolved {by, at, note,
resolution}` (`manual`, `auto`, `rule_disabled`). Hal bo'lganlari 90 kundan keyin `model:prune` bilan o'chadi.

`acknowledge` faqat `open` ogohlantirishga (aks holda 409 `alert_not_open`, so'rov o'qilgandan keyin evaluator yopib qo'ygan bo'lsa ham);
`resolve` — tugallanmaganiga (aks holda 409 `alert_resolved`, poygada yutqazsa ham). Qabul qilingan ogohlantirish "Bugun → Diqqat talab qiladi" ro'yxatidan tushadi.

### Telegram

Ogohlantirish ochilganda va hal bo'lganda ops guruhiga oddiy matnli xabar boradi: `TELEGRAM_OPS_CHAT_ID` (bo'sh bo'lsa
`TELEGRAM_SUPERADMIN_CHAT_ID`), mavjud navbatli yo'l (`TelegramMessenger` → `SendTelegramRequest`). Chat yoki bot tokeni
bo'lmasa hech narsa yuborilmaydi. Xabarda faqat sarlavha, qoida nomi, vaqt va havola bor (shaxsiy ma'lumot yo'q).

Cheklovlar (guruh to'lib ketmasin): bir qoida va obyekt uchun `MONITORING_TELEGRAM_COOLDOWN` (standart 15) daqiqada bittadan
ko'p "ochildi" xabari yuborilmaydi; jami soatiga `MONITORING_TELEGRAM_HOURLY_LIMIT` (standart 20) tagacha, undan keyin soatiga
bitta "ogohlantirishlar ko'p" xabari. "Hal qilindi" xabari faqat "ochildi" xabari ketgan ogohlantirish uchun yuboriladi. Qoidada
`telegram: false` bo'lsa ikkalasi ham yuborilmaydi.

### Real vaqt va "Bugun"

`platform.ops` kanalida: `ops.alert.opened`, `ops.alert.acknowledged`, `ops.alert.resolved` — `{id, kind, severity, state}`
(shaxsiy ma'lumotsiz; ilova ro'yxatni API'dan qayta so'raydi). Ochiq (hech kim olmagan) ogohlantirishlar `AttentionSource`
orqali "Bugun → Diqqat talab qiladi"ga tushadi (`kind: alert`, havola `/monitoring?tab=alerts&alert=<id>`,
`monitoring.view` ruxsati bilan).

### Migratsiyalar va testlar

`2026_11_02_300100` (`alert_rules`), `300200` (`alerts`, qisman yagona indeks), `300300` (`monitoring_markers`), `300400`
(`failed_jobs(failed_at)`, CONCURRENTLY). Testlar: `tests/Feature/AdminMonitoringApiTest.php`,
`AdminMonitoringEvaluatorTest.php`, `AdminMonitoringReviewFixesTest.php` (1-to'lqin tekshiruv tuzatishlari), `AdminMonitoringBackupScriptTest.php`.

## Ovqatxonalar (P4-22)

Boshqaruv markazining **Ovqatxonalar** bo'limi: B2B mijozlar ro'yxati, 360° karta, sog'liq bahosi, ulash jarayoni (arizadan
faol ovqatxonagacha), CRM-lite, ovqatxonaga amallar va egalarga ommaviy e'lon. Kod: `routes/api_admin_stores.php`,
`routes/api_store_applications.php` (ommaviy forma), `App\Http\Controllers\Api\V1\Admin\AdminStore*`,
`App\Http\Controllers\Api\V1\Onboarding\StoreApplicationController`, `App\Services\Admin\Stores`,
`App\Providers\AdminStoresServiceProvider`. **Egasi nomidan kirish yo'q** (egasi qarori, 28.10.2026): yordam xodimi
ma'lumotni kartadan ko'radi, sozlashni egasi o'zi qiladi. `admin.stores.impersonate` ruxsati faqat egasining Telegram havolasini
ko'rish uchun (pastda, `telegram-link`); u `PRIVILEGED` ro'yxatida: standart rollarda yo'q, faqat superadmin beradi.

### Ruxsatlar

| Endpoint | Ruxsat | Step-up | `reason` |
|---|---|---|---|
| `GET /admin/stores`, `/stores/{id}`, `/store-applications`, `/store-applications/{uuid}`, `/store-tasks`, `/store-broadcasts` | `stores.view` | — | — |
| `GET /admin/stores/lookup` | `stores.view` yoki `orders.view` | — | — |
| `POST /admin/store-applications`, `PATCH .../{uuid}`, `POST .../{uuid}/stage`, `/checklist`, `/notes` | `stores.manage` | `faol` ga o'tish uchun ha | `faol` ga o'tish uchun ha |
| `POST /admin/stores/{id}/notes`, `PUT .../next-contact`, `POST /admin/store-tasks`, `PATCH .../{id}`, `POST /admin/store-broadcasts/preview` | `stores.manage` | — | — |
| `POST .../{uuid}/convert`, `/reject`; `POST /admin/stores/{id}/suspend`, `/activate`, `/telegram-link`, `/message`; `PUT .../credit-limit`, `/display`; `POST /admin/store-broadcasts` | `stores.manage` | ha | ha |

Har bir yozish amali jurnalga yoziladi (amal nomi — route nomi): `admin.stores.suspended`, `admin.stores.activated`,
`admin.stores.credit_limit_changed`, `admin.stores.display_changed`, `admin.stores.telegram_link_regenerated` (havolaning
o'zi jurnalga yozilmaydi — u kalit), `admin.stores.owner_messaged`, `admin.stores.broadcast_sent`,
`admin.stores.applications.created|updated|stage_changed|checklist_updated|converted|rejected|note_added`,
`admin.stores.note_added`, `admin.stores.next_contact_set`, `admin.stores.tasks.created|updated`. Holat o'zgarishi
qo'shimcha `store_status_logs` ga (kim, qachon, sabab) yoziladi — kartaning "Holat tarixi". Komissiyani alohida
o'zgartirish — P4-21 endpointi `PUT /admin/billing/stores/{id}/commission` (`subscriptions.manage`); kartada "POS va
obunalar" havolasi.

### Ommaviy ariza formasi (sayt va bot uchun)

`POST /api/v1/store-applications` — **token kerak emas**, captcha yo'q, lekin cheklangan:

```
{ name, format, region_id?, city?, owner_name, phone, tin?, comment?, source?: "website" | "telegram", website: "" }
→ 201 {success: true, message: "Arizangiz qabul qilindi...", data: {received: true}}
```

- `format`: `restaurant`, `cafe`, `fast_food`, `canteen`, `coffee_shop`, `bakery`, `teahouse`, `catering`, `other`;
  `region_id` — faol viloyat (`GET /api/v1/locations`); `phone` — `+998 90 123 45 67` (bo'shliq, chiziqcha, qavs, +998
  siz 9 raqam ham bo'ladi), `+998XXXXXXXXX` bo'lib saqlanadi; `tin` — 9 raqam.
- `website` — **yashirin maydon (honeypot)**: saytda ko'rinmaydigan input. To'ldirilgan bo'lsa, javob xuddi shunday 201,
  lekin hech narsa saqlanmaydi.
- Bir telefon 24 soat ichida bir marta qabul qilinadi (takroriy yuborish o'sha arizani qoldiradi). Cheklov: bitta IP dan
  soatiga 5 ta va kuniga 20 ta — keyin 429 (`Retry-After`). Telefon bo'yicha so'rov cheklovi yo'q: telefon
  so'rovda aytilgan raqam xolos, shuning uchun begona odam boshqaning telefonini "band" qila olmasligi kerak; telefon faqat
  qabul qilingan ariza bilan cheklanadi (24 soatda bitta).
- Javobda ariza haqida ma'lumot yo'q (telefonni bilgan odam ariza borligini bilib olmasin).
- Telegram bot buyrug'i hozircha yo'q: bot suhbat holatini saqlamaydi, ko'p qadamli forma uchun alohida ish kerak.
  Bot uchun eng oddiy yo'l — Mini App yoki sayt formasi shu endpointga `source: "telegram"` bilan yuboradi.

### Ulash jarayoni (`/admin/store-applications`)

Bosqichlar (`App\Enums\StoreApplicationStage`): `application` (Ariza) → `review` (Tekshiruv) → `menu` (Menyu) →
`devices` (Qurilmalar) → `training` (O'qitish) → `trial` (Sinov) → `active` (Faol); alohida `rejected` (Rad etildi).

| Bosqich | Ro'yxat (majburiy — *) | Standart muddat |
|---|---|---|
| Ariza | egasi bilan bog'lanildi* | 1 kun |
| Tekshiruv | STIR*, yuridik nom*, bank hisobi*, hujjatlar | 2 kun |
| Menyu | menyu kiritildi* (aniqlanadi: taom bor), rasmlar, narxlar* | 2 kun |
| Qurilmalar | kassa ulandi* (aniqlanadi), oshxona ekrani (aniqlanadi), printer, fiskal (aniqlanadi) | 2 kun |
| O'qitish | xodimlar o'qitildi*, egasi hamkor paneliga kirdi* (aniqlanadi: egasi/menejer tokeni bor) | 1 kun |
| Sinov | birinchi buyurtma* (aniqlanadi: bekor qilinmagan istalgan kanal buyurtmasi), egasi tasdiqladi* | 3 kun |

- Band **belgilanadi** (`POST .../checklist {item, done}`) yoki ovqatxonadan **aniqlanadi** (`detected: true`).
- `POST .../stage {stage, note?, due_on?, reason?}`: oldinga — o'tilayotgan har bosqichning majburiy bandlari bajarilgan
  bo'lsin (422 `checklist_incomplete`, `data.stage`, `data.missing`); `menu` va keyingilar uchun ovqatxona yaratilgan
  bo'lsin (422 `store_required`); `active` — step-up (403 `reauth_required`) va `reason`, ovqatxona `active` va
  marketpleysda ko'rinadigan bo'ladi. Orqaga — erkin (`active` dan emas: 422 `already_active`). Rad etilgan ariza faqat
  `application` yoki `review` da qayta ochiladi (422 `reopen_only`). `rejected` ga — faqat `reject` orqali (`use_reject`).
- Bosqichga kirganda muddat = bugun (Toshkent) + standart kun (yoki `due_on`). Ochiq, muddati o'tgan ariza — `overdue`.
- `POST .../convert` (step-up, `reason`): ovqatxona **Filament superadmin ustasi bilan bir xil yo'l**dan yaratiladi
  (`App\Services\Admin\Stores\StoreProvisioner`, `CreateGlobalStore` ham shuni chaqiradi): do'kon, egasi (SMS bilan kirish
  telefoni tasdiqlangan, bir martalik Telegram taklif tokeni), "Asosiy Filial". Maydonlar: `branch_district_id`,
  `branch_address`, `branch_lat`, `branch_lng` (majburiy), `branch_region_id` (standart — arizaniki), `branch_radius` (5),
  `branch_phone`, `branch_open_time`/`branch_close_time`, `name`, `owner_name`, `owner_phone` (standart — arizaniki), `phone`,
  `tin`, `legal_name`, `commission_rate` (berilmasa `null` yoziladi — Billing v2 kanal stavkalari 15/10/5/0%; qiymat —
  barcha onlayn kanallar uchun yagona foiz), `payment_account_type`. Filament ustasida ham komissiya maydoni endi
  majburiy emas va standart 10 yo'q: bo'sh qoldirilsa `null`. Ovqatxona
  `pending` va **marketpleysda ko'rinmaydi** (`is_active = false`), ariza `faol` bo'lguncha; kassa ishlayveradi.
  Javobda `invite_link` (faqat shu yerda). 409 `already_converted`, 409 `owner_phone_taken`, 422 `owner_phone_invalid`.
- `POST .../reject {reason}` (step-up): ariza yopiladi; yaratilgan ovqatxona `rejected` va yashirin bo'ladi.
- `GET /admin/store-applications?stage=open|overdue|<bosqich>&assignee_id=me|<id>&source=&q=` → `{data, stage_counts,
  stages, formats}`; muddati o'tganlar birinchi. Karta: maydonlar, `phone` (`pii.view` siz niqoblangan), `checklist`,
  `crm`, `audit`, `actions`.
- Mas'ul (`assignee_id`) — `stores.manage` li faol jamoa a'zosi.

### Ro'yxat va 360° karta

`GET /admin/stores?q=&status=&region_id=&health=good|attention|churn_risk|none&plan=<kod>|none&debt=has_debt|over_limit&next_contact=due&sort=newest|name|debt|health&page=&per_page=`
→ `{data, current_page, last_page, per_page, total, health_counts, statuses}`. Qatorda telefon yo'q. `q` — nom, STIR,
do'kon yoki egasining **to'liq** telefoni, uuid boshi. Qatorda `debt_balance`, `subscription_debt`, `commission_debt`,
`credit_limit` (ruxsat etilgan qarz, musbat), `over_limit` — komissiya qarzi limitdan oshgan (P4-21: obuna qarzi kirmaydi;
`debt=over_limit` filtri ham shu qoida). Ro'yxatdagi tarif — joriy obuna qatoridan (sahifa uchun bitta so'rov);
`Entitlements` faqat kartada ishlaydi.

`GET /admin/stores/{id}`: `profile`, `application`, `contacts` (ega, menejerlar, ariza qoldirgan — telefonlar
`AdminPii` orqali), `branches`, `status_history`, `plan` (`source: "billing_v2"` — P4-21 ning do'kon billingi, `GET /admin/billing/stores/{id}`
bilan bir xil: `subscription`, `entitlements` (`Entitlements`: tarif, davr, jonli holat — `locked` ham, funksiyalar,
qurilma limitlari), `debt`, `commission` (kanal bo'yicha stavkalar), `history`, `invoices`; hammasi
`App\Services\Admin\Stores\StorePlanReader` orqali), `devices` (oxirgi 10 daqiqada aloqa — onlayn), `fiscal`, `finance`
(`debt_balance`, `subscription_debt` — to'lanmagan obuna hisob-fakturalari, `commission_debt` = qarz − obuna qarzi,
`credit_limit`, `over_limit` — faqat komissiya qarzi limitdan oshganmi; hamyon, payoutlar — karta niqoblangan, oxirgi
ledger yozuvlari), `orders` (30 kunlik sifat — P4-14
`StoreQualityService`, kanallar, oxirgilari), `complaints`, `health` (shu so'rovda qayta hisoblanadi), `crm`, `audit`
(oxirgi 50), `actions`, `links`.

`GET /admin/stores/lookup?q=&ids[]=&districts_of=` → `{stores (≤20), regions, districts}` — Buyurtmalar filtrlari va
e'lon uchun tanlash ro'yxati.

### Sog'liq bahosi

`App\Services\Admin\Stores\StoreHealthCalculator`, `store_health_scores` jadvali (har soat `RefreshStoreHealthJob`,
qo'lda `php artisan admin:store-health [--store=ID]`; karta ochilganda ham yangilanadi). 100 balldan sabablar ayriladi:

| Sabab (`key`) | E'tibor kerak | Ketish xavfi |
|---|---|---|
| `orders_dropped` — 14 kun oldingi 14 kunga nisbatan (oldin 10+ buyurtma) | 25% kam (−15) | 50% kam (−30) |
| `no_orders` — 14 kundan beri buyurtma yo'q (14 kundan eski, oldin buyurtmasi bo'lgan) | | −35 |
| `no_first_order` — hali birorta buyurtma yo'q (14 kundan eski) | −10 | |
| `devices_silent` — kassa/KDS 3+ kun aloqasiz | bir qismi (−10) | hammasi (−25) |
| `fiscal_failed` — 7 kunda yiqilgan fiskal chek | 1+ (−10), 10+ (−20) | |
| `debt_over_limit` — komissiya qarzi (qarz − obuna qarzi) kredit limitidan oshgan | | −25 |
| `invoice_overdue` — muddati o'tgan to'lanmagan hisob-faktura | 1+ kun (−10) | 7+ kun (−20) |
| `complaints` — 30 kundagi shikoyatlar | 3+ (−10) | 6+ (−20) |
| `owner_inactive` — ega va menejerlarning oxirgi kirishi (token) | 14+ kun (−10) | 30+ kun (−20) |
| `status` — to'xtatilgan, qarz limiti, moliyaviy tekshiruv | −10 | |

Daraja: ketish xavfi sababi yoki < 50 ball — `churn_risk`; boshqa sabab yoki < 80 — `attention`; aks holda `good`.
Buyurtma trendi va faollik faqat ulash jarayonidan o'tgan ovqatxonaga (`active`, `payment_required`, `finance_hold`,
`suspended`) qo'llanadi.

### Amallar (`stores.manage`, step-up, `reason`)

- `POST .../suspend` — `suspended`, marketpleysdan yashiriladi (yangi Mini App buyurtmasi yo'q). **Kassa to'xtamaydi.**
  409 `already_suspended`.
- `POST .../activate` — `active` va ko'rinadi (suspended, pending, under_review, rejected dan). `payment_required` /
  `finance_hold` — 422 `finance_status` (qarz qoidalari hal qiladi); ulash jarayonidagi ovqatxona — 422 `use_pipeline`;
  409 `already_active`.
- `PUT .../credit-limit {credit_limit}` — 0–100 000 000 so'm; ledger qoidasi darhol qo'llanadi (limitdan oshsa `active` →
  `payment_required`, limit ichiga qaytsa aksincha), holat tarixiga yoziladi.
- `PUT .../display {is_premium?, is_featured?}` — premium va "tavsiya etilgan".
- `POST .../telegram-link` — Telegramga hali ulanmagan egasi uchun yangi bir martalik bot havolasi (eskisi ishlamaydi).
  Havolani ochgan Telegram hisobi egasi hisobiga bog'lanadi, shuning uchun u **parol kabi** sir: xodimga ko'rsatilmaydi,
  egasining tasdiqlangan telefoniga SMS bilan yuboriladi (`delivered_via: "sms"`, `invite_link: null`). SMS ketmasa
  (telefon yo'q/tasdiqlanmagan yoki SMS xizmati javob bermasa) havola `invite_link` da faqat `admin.stores.impersonate`
  ruxsati bor xodimga qaytadi; boshqalarga 422 `link_not_deliverable` va eski havola o'zgarmaydi. Egasi Telegramga
  allaqachon ulangan bo'lsa — 409 `owner_already_linked` (hisobni boshqa Telegramga o'tkazib bo'lmaydi; egasiga
  `message` bilan yozing). Auditda havola yo'q (`delivered_via`, `link_shown_to_member` bor). Karta:
  `actions.telegram_link` ulangan egada `false`. 422 `no_owner`.
- `POST .../message {text}` — egasining Telegramiga (navbat orqali, oddiy matn). 422 `no_recipient`, 503
  `telegram_not_configured`.
- P4-32: `PUT .../payment-account {payment_account_type?, min_payout_reserve?}` — onlayn to'lov qayerga tushadi
  (`platform_account`: RIZQIM yig'adi va hamyonga o'tkazadi; `own_account`: ovqatxonaning o'z Click/Payme kassasi, kalitlarni
  egasi hamkor panelida kiritadi, kiritilmaguncha RIZQIM yig'adi) va payoutdan keyin hamyonda qoladigan zaxira (0–100 000 000
  so'm). Kamida bittasi. To'lovi kutilayotgan onlayn buyurtma bo'lsa hisob turi o'zgarmaydi: 409 `payments_in_flight`
  (callback o'sha buyurtma yaratilgan hisob kaliti bilan tekshiriladi). Karta: `actions.payment_account`.
- P4-32: `PUT .../owner-login-phone {phone}` — raqamini yo'qotgan ega uchun yangi kirish raqami (+998XXXXXXXXX). Platforma
  kafolatlaydi (`vouched`): shu raqamga kelgan SMS kod bilan hamkor paneli va ilovalarga ega sifatida kiriladi, eski raqamdagi
  barcha ilova sessiyalari yopiladi, ovqatxona jurnaliga `staff.login_phone_changed` yoziladi (raqam yashirilgan). Raqam
  do'konni egallashga teng, shuning uchun qo'shimcha `admin.stores.impersonate` kerak (standart: faqat superadmin). 409
  `owner_phone_taken` (boshqa xodim hisobida bor), 422 `owner_phone_invalid`, `no_owner`. Karta: `profile.owner_login_phone
  {set, via}`, `actions.owner_login_phone`.

### CRM-lite

Izohlar (`POST /admin/stores/{id}/notes`, `POST /admin/store-applications/{uuid}/notes`, `body` ≤ 2000), vazifalar
(`POST /admin/store-tasks {title, due_on ≥ bugun, assignee_id?, store_id | application_uuid}`, `PATCH /admin/store-tasks/{id}
{done?, title?, due_on?, assignee_id?}`; `GET /admin/store-tasks?assignee_id=me&due=today&done=1` → `{tasks, counts}`),
keyingi aloqa sanasi (`PUT /admin/stores/{id}/next-contact {next_contact_on | null}`). Arizaning izoh va vazifalari
ovqatxona yaratilgach uning kartasida ham ko'rinadi.

### Ommaviy e'lon

1. `POST /admin/store-broadcasts/preview {audience: "all" | "selected", store_ids?, statuses?, region_id?}` →
   `{stores, recipients, without_telegram, sample}` (jurnalga yozilmaydi). `recipients` — botga ulangan egalar, har biri bir
   marta.
2. `POST /admin/store-broadcasts {...auditoriya, text (≤ 2000), recipients, reason}` (step-up): `recipients` ko'rib
   chiqishdagi son bilan bir xil bo'lsin — o'zgargan bo'lsa 409 `count_changed` va yangi `data.preview`. Har bir egaga
   navbat orqali "📣 RIZQIM e'loni" (`SendStoreBroadcastJob`). Cheklov: a'zoga soatiga 5 urinish
   (`throttle:admin-store-broadcast`), platformaga kuniga 10 ta e'lon (429 `daily_limit`). 422 `no_recipient`.
3. `GET /admin/store-broadcasts` → oxirgi 50 ta, `limit_per_day`, `sent_today`.

### "Diqqat talab qiladi" manbalari (`stores.view`)

| `kind` | Nima | `severity` | `href` |
|---|---|---|---|
| `store_application_new` | "Ariza" bosqichidagi, mas'uli yo'q arizalar | `info` | `/stores/applications/{uuid}` |
| `store_application_overdue` | muddati o'tgan ochiq arizalar | `warning` | `/stores/applications/{uuid}` |
| `store_churn_risk` | ketish xavfidagi faol ovqatxonalar (asosiy sabab bilan) | `warning` | `/stores/{id}` |
| `store_task_due` | bugun yoki oldin muddati kelgan ochiq vazifalar (kechikkani `warning`) va aloqa kuni kelgan ovqatxonalar | `info` | `/stores/{id}?tab=crm` |

### Migratsiyalar va testlar

`2026_11_04_200100` (`store_applications`), `200200` (`store_notes`, `store_tasks`, `store_crm_profiles`), `200300`
(`store_broadcasts`), `200400` (`store_health_scores`). Testlar: `tests/Feature/AdminStores/*`.

## Billing v2 (P4-21)

Tariflar, funksiya cheklovlari, kanal komissiyasi va obunalar. Qoidalarning to'liq tavsifi: [BILLING.md](BILLING.md)
("Billing v2" bo'limi). Bu bo'limdagi barcha pul summalari — ikki xonali satr (`"149000.00"`).

### Ega va terminallar: `GET /owner/billing/entitlements`

Do'konning istalgan xodimi (ega, menejer, kassir, ofitsiant, oshpaz) va terminal PIN sessiyasi o'qiydi (`auth:sanctum`,
`staff.store`). Xuddi shu ma'lumot `GET /pos/bootstrap` javobida `entitlements` kaliti ostida keladi.

```json
{
  "plan": {"code": "start", "name": "Start", "tier": "start", "version": 2},
  "legacy": false,
  "period": "monthly",
  "status": "trialing",
  "trial_ends_at": "2026-11-08T05:00:00+00:00",
  "current_period": {"start": "2026-10-25T05:00:00+00:00", "end": "2026-10-31T19:00:00+00:00"},
  "term_ends_at": null,
  "locked_since": null,
  "lock_at": null,
  "settings_locked": false,
  "features": {"hall": true, "room_fee": true, "guest_items": true, "shifts": true, "rizqim_orders": true,
               "bookings": false, "deposits": false, "customer_accounts": false, "kds": false, "service_charge": false,
               "multi_branch_reports": false, "aggregator_orders": false, "banquet": false},
  "required_plans": {"bookings": "asosiy", "deposits": "asosiy", "customer_accounts": "asosiy", "kds": "asosiy",
                     "service_charge": "asosiy", "multi_branch_reports": "pro", "aggregator_orders": "pro", "banquet": "pro"},
  "devices": {
    "pos": {"included": 1, "active": 2, "extra": 1, "extra_price": "59000.00", "can_add": true},
    "kds": {"included": 0, "active": 0, "extra": 0, "extra_price": "29000.00", "can_add": false},
    "waiter": {"included": null, "unlimited": true, "extra_price": "0.00", "can_add": true}
  },
  "notice": null
}
```

- `status`: `none` (obuna yo'q), `trialing`, `active`, `past_due` (muddati o'tgan hisob-faktura), `locked` (muddatdan 7
  kundan ko'p o'tgan). `legacy: true` — do'kon hali v2 tarifda emas (yoki eski P1-23 tarifda): hamma funksiya ochiq,
  `devices.*.included` `null`.
- `required_plans` — har bir yopiq funksiya qaysi eng arzon tarifda bor: ekranda "Asosiy tarifda bor" deb ko'rsatish uchun.
- `notice` — muddati o'tgan hisob-faktura bo'lsa banner uchun: `{level: reminder|locked, message, days_overdue, lock_at,
  invoice: {uuid, number, amount, outstanding, due_date} | null}`. `invoice` (raqam va summalar) faqat o'z kirishidagi
  `finance.view` li a'zoga (odatda ega); boshqa xodimlar, terminaldagi PIN sessiya (ega ham) va `GET /pos/bootstrap` da
  `invoice: null`, `message` da ham raqam va summa yo'q (faqat daraja, kunlar va cheklov sanasi).
- `lock_at` — to'lanmasa qachondan cheklanadi; `locked_since` — cheklangan bo'lsa, qachondan. Faqat v2 tarif bo'yicha
  chiqqan hisob-faktura cheklaydi: eski P1-23 hisob-faktura (migratsiyadan oldingi) do'kon v2 tarifda bo'lsa ham faqat
  `past_due` beradi, `lock_at: null`.

### Rad javoblari (403)

```json
{"success": false, "message": "Bu imkoniyat (Bron) Asosiy tarifida bor.", "code": "plan_feature_missing",
 "feature": "bookings", "required_plan": "asosiy",
 "data": {"reason": "plan_feature_missing", "feature": "bookings", "feature_label": "Bron", "required_plan": "asosiy", "required_plan_name": "Asosiy"}}
```

| `code` | Qachon |
|---|---|
| `plan_feature_missing` | `POST /pos/bookings` (bookings), `POST /pos/bookings/{uuid}/deposits` (deposits), `POST /pos/customer-accounts` (customer_accounts), `PUT /owner/service-fees` xizmat haqi > 0 bilan (service_charge), `POST /owner/devices/pairing-codes` `type: kds` (kds) |
| `subscription_locked` | obuna cheklangan (`data.locked_since`): `PUT /owner/service-fees`, zal va stollar (`/owner/halls`, `/owner/dining-tables` o'zgartirish), `/owner/room-tariffs`, `/owner/auto-guest-items`, `DELETE /owner/service-fees/halls/{id}`, sexlar (`/owner/kitchen-stations`, `/owner/kitchen-station-routes`) yozuvlari |
| `device_limit` | obuna cheklangan va tarifdagi terminallar soni band: yangi terminal ulash kodi (`data.device_type`, `included`, `active`). Eskisini o'chirib, o'rniga ulash mumkin |

**Hech qachon cheklanmaydi:** to'lov qabul qilish, schyot yaratish va yopish, chek chop etish va fiskal chek, smena (ochish,
X/Z, yopish, kassa harakati), qaytarish, avansni qaytarish, nasiyani to'lash, oflayn sinxron, printerlar, menyu va
stop-list. Mavjud bron va avanslar ham (tasdiqlash, kelish, bekor qilish) ishlayveradi.

### Boshqaruv markazi: `/admin/billing` (`routes/api_admin_billing.php`)

| Endpoint | Ruxsat | Nima qiladi |
|---|---|---|
| `GET /admin/billing/plans?all=` | `subscriptions.manage` yoki `finance.view` | Tariflar katalogi (`all=1` — eski P1-23 tariflar ham) va `feature_catalog` |
| `PUT /admin/billing/plans/{code}` | `system.manage` + step-up + `reason` | v2 tarifni o'zgartirish: `name`, `description`, `is_active`, `monthly_fee`, `yearly_fee`, `included_pos`, `included_kds`, `extra_pos_fee`, `extra_kds_fee`, `trial_days`, `sort_order`, `features` (yopiq funksiyalar ro'yxati) |
| `GET /admin/billing/subscriptions?status=&plan=&period=&trial_ends_within=&store_id=&cursor=&per_page=` | `subscriptions.manage` yoki `finance.view` | Obunalar ro'yxati (cursor), `counts` holatlar bo'yicha |
| `GET /admin/billing/stores/{id}` | `subscriptions.manage` yoki `finance.view` | Do'kon billing kartasi: `subscription`, `entitlements`, `debt` (`debt_balance`, `subscription_debt`, `commission_debt`, `credit_limit`, `over_credit_limit`), `commission`, `history`, `invoices` (eslatmalar tarixi `dunning` bilan) |
| `POST /admin/billing/stores/{id}/subscription` | `subscriptions.manage` + step-up + `reason` | `plan`, `period` (monthly/yearly), `immediately`: tarif biriktirish yoki almashtirish |
| `POST /admin/billing/stores/{id}/subscription/period` | shu | `period`: oylik ↔ yillik |
| `POST /admin/billing/stores/{id}/subscription/cancel` | shu | `immediately`: bekor qilish (darhol yoki davr oxirida) |
| `POST /admin/billing/stores/{id}/subscription/trial` | shu | `trial_ends_at` yoki `days`: sinov davrini uzaytirish (ko'pi bilan 90 kun) |
| `PUT /admin/billing/stores/{id}/commission` | shu | `overrides` (`{kanal: foiz|null}`), `flat_rate` (null — kanal stavkalari), `delivery_courier` (own/fleet) |

Har bir o'zgarish harakatlar jurnaliga yoziladi (amal = route nomi: `admin.billing.plan_updated`,
`admin.billing.subscription_assigned`, `admin.billing.period_changed`, `admin.billing.subscription_cancelled`,
`admin.billing.trial_extended`, `admin.billing.commission_updated`; holat oldin va keyin, `reason`). Xizmat ham o'z
yozuvlarini qo'shadi (`subscription.started`, `subscription.plan_changed`, `subscription.trial_extended`,
`subscription.locked`, `subscription.unlocked`, ...). Qoida buzilsa 422 `billing_rule` (masalan, eski tarifga yillik davr),
do'kon yo'q — 404 `not_found`, obuna yo'q — 422 `no_subscription`.

Obuna (`subscription`) shakli: `id`, `store_id`, `status`, `status_label`, `plan {code, name, version}`, `billing_period`,
`next_billing_period`, `next_plan`, `cancel_at_period_end`, `started_at`, `trial_ends_at`, `current_period {start, end}`,
`term_ends_at`, `changes_at` (rejalashtirilgan o'zgarish qachon amal qiladi), `price_locked_until`, `locked_at`,
`cancelled_at`. Hisob-faktura: `uuid`, `number`, `kind` (monthly/yearly/upgrade), `period`, `lines` (`type`: base, discount,
proration, extra_pos, extra_kds, price_lock, yearly, upgrade; P1-23: terminals), `amount`, `paid_amount`, `outstanding`,
`status`, `due_date`, `dunning`.

Ega endpointlariga qo'shilganlar: `GET /owner/subscription` → `subscription.billing_period`, `next_billing_period`,
`term_ends_at`; `GET /owner/invoices` → har bir hisob-fakturada `kind` va `paid_amount` (satr).

### Migratsiyalar, buyruqlar, testlar

`2026_11_04_100100` (tarif v2 ustunlari va Start/Asosiy/Pro), `100200` (obuna: davr, yillik muddat, proratsiya, narx
qulfi, `locked_at`), `100300` (hisob-faktura: `kind`, `sequence`, `paid_amount`), `100400` (do'kon: `subscription_debt`,
`commission_overrides`, `delivery_courier`, `commission_rate` bo'sh bo'lishi mumkin), `100500` (buyurtma:
`commission_channel`, `commission_rate`), `100600` (`subscription_dunning_events`). Buyruq: `billing:migrate-plans
[--dry-run] [--store=]`. Har kuni 10:05 (Toshkent) `SendBillingRemindersJob`. Testlar: `tests/Feature/BillingV2/*`.

## POS va obunalar (P4-24)

Boshqaruv markazining «POS va obunalar» bo'limi (`platform/admin`, `/pos`). Obunalar ro'yxati, tariflar katalogi va
ovqatxonaning obunasi P4-21'ning `/admin/billing` endpointlariga tayanadi (yuqorida). Bu bo'lim faqat yetishmaganini
qo'shadi: `routes/api_admin_pos.php` (`/admin/pos`, nomlar `admin.pos.*`, controllerlar `Admin/AdminPos*`, xizmatlar
`app/Services/Admin/Pos/*`). Hamma o'zgarish step-up (`admin.recent`) va `reason` (3–500 belgi) talab qiladi va
harakatlar jurnaliga yoziladi (amal = route nomi; holat oldin va keyin). Takroriy bosish hech narsani o'zgartirmaydi va
jurnalga ikkinchi yozuv qo'shmaydi. Ro'yxatlar sahifalanadi: `page`, `per_page` 1–100 (30), javobda `current_page`,
`last_page`, `per_page`, `total` va filtrlarga qaramaydigan `counts`. Pul — o'nlik satr (`"249000.00"`), vaqt — ISO 8601,
sana (`business_date`, `due_date`) — `Y-m-d`.

| Endpoint | Ruxsat | Nima qiladi |
|---|---|---|
| `GET /admin/pos/overview` | `subscriptions.manage`, `finance.view` yoki `devices.view` | Tablardagi raqamlar: `subscriptions` (holat bo'yicha, `trial_ending` — 3 kunda tugaydigan sinovlar), `invoices`, `devices`, `shifts` (oxirgi 30 kun), `fiscal` (`by_driver`), `sync_errors`. Ruxsat bo'lmagan guruh `null` |
| `GET /admin/pos/invoices?status=&store_id=&period=&dunning=&q=` | `subscriptions.manage` yoki `finance.view` | Hamma ovqatxonalarning hisob-fakturalari. `status`: `issued`, `paid`, `void` yoki `overdue` (to'lov muddatidan keyin to'lanmagan, Toshkent sanasi bilan). Qatorda `overdue_days`, oxirgi eslatma (`dunning_stage`). `counts`: `issued`, `overdue`, `paid`, `void`, `outstanding` |
| `GET /admin/pos/invoices/{uuid}` | shu | Hisob-faktura (qatorlar, to'langan va qolgan qism, eslatmalar tarixi) va ovqatxona qarzi (`subscription_debt` va `commission_debt` alohida) |
| `POST /admin/pos/invoices/{uuid}/paid` | `finance.manage` + step-up + `reason` | «To'langan» deb belgilash (`SubscriptionService::markPaid`). Balans o'zgarmaydi: pulning o'zi balansni to'ldirish sifatida yoziladi va eng eski hisob-fakturalarni yopadi. Bekor qilinganga 422 `invoice_void` |
| `POST /admin/pos/invoices/{uuid}/void` | shu | Bekor qilish (`SubscriptionService::voidInvoice`): summasi qarzdan ayriladi (ledger'da teskari yozuv). Faqat to'lanmagan: `paid` yoki qisman to'langan hisob-fakturaga 422 `invoice_paid` (pul Moliya → Qo'lda tuzatish bilan qaytariladi). 1 000 000 so'm (`finance.four_eyes_threshold`) va undan kattasini ikki xil xodim: birinchi chaqiruv — so'rov (hisob-faktura `issued` qoladi, ledger o'zgarmaydi, `invoice.void_request {by, at, reason}`, jurnalda `admin.pos.invoice_void_requested`), o'sha xodim takrorlasa — o'sha javob, boshqa xodimning chaqiruvi bekor qiladi (`voided_by`). Kartada `void_needs_second_approval` |
| `GET /admin/pos/devices?state=&type=&terminal=&store_id=&outdated=&q=` | `devices.view` | Do'konga tegishli hamma qurilmalar. `state`: `online` (oxirgi 10 daqiqada aloqa), `offline`, `never`, `revoked`, `all`; bo'sh bo'lsa o'chirilmaganlar. Eng uzoq aloqasizlar birinchi. `outdated=1` — shu turdagi faol qurilmalardagi eng yangi versiyadan eskilar (versiyalar reyestri yo'q, «eng yangi» — ko'rilgan eng katta versiya; `latest_versions`). Qatorda kassaning oflayn navbati (`sync`) |
| `GET /admin/pos/devices/{uuid}` | shu | Qurilma (Bugun'dagi `/pos?device=` havolasi) |
| `POST /admin/pos/devices/{uuid}/revoke` | `stores.manage` + step-up + `reason` | Qurilmani o'chirish (`DeviceService::revoke`): tokenlar va sessiyalar darhol to'xtaydi |
| `GET /admin/pos/shifts?kind=&store_id=&from=&to=` | `devices.view` | Smenalar (faqat o'qish). `kind`: `difference` (yopilgan va kassa farqi bor; farq = sanalgan − hisobdagi, manfiy — pul yetishmaydi), `force_closed`, `open` |
| `GET /admin/pos/shifts/{uuid}` | shu | Smena: Z-hisobot (`summary`), sanalgan pullar, kim majburan yopgani, kassaga qo'lda kirim-chiqim |
| `GET /admin/pos/fiscal-receipts?status=&driver=&store_id=&from=&to=&q=` | `devices.view` | Fiskal cheklar. `status`: `open` (yiqilgan va kutilayotgan, odatda; eskilari ham), `failed`, `pending`, `issued`, `all`. `driver` — so'ralgan fiskal provayder. `summary.by_driver` — provayder bo'yicha xatolar va oxirgi xato. `retryable=false`: Payme/Click o'zi ro'yxatdan o'tkazgan chek (`driver=external`) |
| `GET /admin/pos/fiscal-receipts/{uuid}` | shu | Chek: to'liq xato, qatorlar va to'lovlar. Provayderning xom javobi ko'rsatilmaydi |
| `POST /admin/pos/fiscal-receipts/{uuid}/retry` | `stores.manage` + step-up + `reason` | Hoziroq qayta yuborish (`FiscalService::retryNow`, egasining «Qayta yuborish»i bilan bir xil). Javob — chek; provayder yana rad etsa `status` o'zgarmaydi va `message` sababni aytadi. Ro'yxatdan o'tgan yoki `external` chekka 422 `already_issued` |
| `GET /admin/pos/sync-errors?state=&status=&type=&store_id=&device=&from=&to=` | `devices.view` | Oflayn sinxron xatolari: qabul qilinmagan (`rejected`) va farq bilan qabul qilingan (`conflict`) amallar. `state`: `open` (hal qilinmagan, odatda), `resolved`, `all` |
| `GET /admin/pos/sync-errors/{opId}` | shu | Xato kartasi: kassa yuborgan `payload` (kaliti telefon, karta, PIN, token, parol yoki maxfiy bo'lsa `***`) va serverdagi natija (`refs`) |
| `POST /admin/pos/sync-errors/{opId}/resolve` | `stores.manage` + step-up + `reason` | `resolution`: `resolved` (qo'lda hal qilindi) yoki `dismissed` (hech narsa qilish kerak emas). `reason` hal qilish izohiga yoziladi. Hal qilingan qabul qilinmagan amal kassaning smenasi yopilishiga endi to'sqinlik qilmaydi |

Bugun'ning diqqat havolalari ochiladigan joy: `/pos?invoice=<uuid>`, `/pos?device=<uuid>`, `/pos?fiscal=<uuid>` mos
tabni va qatorni ochadi; `/pos?store=<id>` (ovqatxona kartasidagi «Obuna») shu ovqatxonaning obunasini ochadi;
qo'shimcha `?tab=` (`subscriptions`, `plans`, `invoices`, `devices`, `shifts`, `fiscal`, `sync`), `?shift=`, `?sync=`.

Ruxsatlar: Savdo/ulash roli (`stores.manage`, `subscriptions.manage`, `devices.view`) hamma tabni ko'radi, obunalarni
boshqaradi, qurilmani o'chiradi, chekni qayta yuboradi, lekin hisob-fakturani belgilay yoki bekor qila olmaydi
(`finance.manage` kerak); Operator (`devices.view`)
qurilma, smena, fiskal chek va sinxron xatolarini faqat ko'radi; Moliya hisob-fakturalarni belgilaydi/bekor qiladi;
Faqat ko'rish hammasini ko'radi, hech narsani o'zgartirmaydi.

### Do'kon to'xtatilganda buyurtma (P4-24, topilgan xato)

To'xtatilgan (`suspended`), rad etilgan (`rejected`) yoki o'chirilgan (`is_active=false`) ovqatxona Mini App
buyurtmasini qabul qilmaydi: `POST /orders` 400 «Restoran hozircha buyurtma qabul qilmayapti.» (avval to'xtatish faqat
katalogdan yashirar edi, eskirgan savat yoki to'g'ridan-to'g'ri so'rov o'tib ketardi). Kassa (POS) buyurtmalari, to'lov
olish va chek chiqarish bunga bog'liq emas.

### Migratsiyalar va testlar (P4-24)

Migratsiya yo'q. Testlar: `tests/Feature/AdminPos/*` (hisob-fakturalar, qurilmalar, smenalar, fiskal cheklar, sinxron
xatolari, tab raqamlari va ruxsatlar), `tests/Feature/OrderStoreStatusTest.php`.

## Moliya (P4-23)

Boshqaruv markazining **Moliya** bo'limi (`platform/admin`, `/finance`): payout navbati va ikki kishilik tasdiq, qarzni
ushlab qolish (netting), balans to'ldirishlar, hisob-kitob xatolari, ovqatxonalar balansi va qarzi, qo'lda tuzatish, oylik
hisobot va Excel. Kod: `routes/api_admin_finance.php` (`/admin/finance`, nomlar `admin.finance.*`),
`Admin/AdminFinance*Controller`, `app/Services/Finance/{Netting,Payouts,Adjustments,Reports}`, `config/finance.php`.
Pul — o'nlik satr (`"400000.00"`), vaqt — ISO 8601. Har amal step-up (`admin.recent`), `reason` (3–500 belgi) talab qiladi
va jurnalga yoziladi (amal = route nomi, obyekt, oldin/keyin, sabab); pul amallari `Idempotency-Key` oladi — takroriy so'rov
o'sha javobni qaytaradi, hech narsa ikki marta bajarilmaydi va jurnal ikki marta yozilmaydi.

### Ruxsatlar

| Endpoint | Ruxsat | Step-up + `reason` |
|---|---|---|
| `GET /admin/finance/summary`, `/payouts`, `/payouts/{id}`, `/topups`, `/settlement-failures`, `/balances`, `/stores/{id}/ledger`, `/adjustments`, `/adjustments/{id}`, `/adjustments/{id}/document`, `/reports/monthly` | `finance.view` | — |
| `GET /admin/finance/reports/monthly/export` | `finance.view` + `export` | — (jurnalga `admin.finance.report_exported`) |
| `POST /admin/finance/payouts/{id}/approve`, `/reject` | `payouts.approve` | ha |
| `POST /admin/finance/settlement-failures/{uuid}/retry` | `finance.manage` | ha |
| `POST /admin/finance/adjustments`, `/adjustments/{id}/approve`, `/reject` | `finance.manage` | ha |

**Ikki kishilik tasdiq (egasi qarori, 28.10.2026):** `config('finance.four_eyes_threshold')` = **1 000 000 so'm** va undan
katta payout yoki qo'lda tuzatishni ikki xil xodim tasdiqlaydi: payoutda birinchi tasdiqlovchi, tuzatishda uni yaratgan
xodim yakunlay olmaydi (403 `four_eyes`, superadmin ham). 999 999,99 — bitta tasdiq yetadi. Tuzatishni bo'lib yuborib
chetlab bo'lmaydi: oxirgi `config('finance.four_eyes_window_hours')` (24) soatda **bitta xodim tasdig'i bilan** qo'llangan
tuzatishlar — shu xodimniki (istalgan do'kon) va shu do'konniki (istalgan xodim), eski paneldagi qo'lda to'ldirishlar ham —
yangisi bilan qo'shiladi; ikkalasidan biri chegaraga yetsa yangi tuzatish `pending`. Ikki kishi tasdiqlagan, kutilayotgan
va rad etilganlar sanalmaydi. Hisob-fakturani bekor qilish ham shu chegarada ikki xodim (POS va obunalar, `.../void`).

### Netting: payoutdan va RIZQIM yig'gan puldan avval qarz

`App\Services\Finance\Netting\NettingService` (do'kon qatorini qulflagan holda): pul ovqatxonaga yetishidan oldin uning
qarzi yopiladi — **avval eng eski obuna hisob-fakturalari** (to'lov muddati, keyin oy; `SubscriptionDebt::pay`), **keyin
komissiya qarzi**. Har yopilgan narsa qarz ledger'ida **alohida yozuv** (`store_transactions`, tur `topup`, manfiy):
hisob-faktura yozuvida `subscription_invoice_id` va izohda raqami, komissiya qismi alohida.

- **Payout** tasdiqlanganda: hamyon so'rov summasiga kamayadi (ikki `payout` yozuvi, `payout_request_id` bilan: qarzga
  ushlangan va kartaga o'tgan), qarz yozuvlari `payout_request_id` bilan. Payout kartasi: **Hisoblangan** (so'ralgan) —
  **Ushlangan qarz** (har hisob-faktura va komissiya qatori) — **O'tkaziladi**. Qarz payoutdan katta bo'lsa hammasi
  ushlanadi, kartaga 0.
- **Click, Payme, keshbek** (RIZQIM yig'gan buyurtma puli, `LedgerService::settlePlatformOrder`): avvalgidek faqat keshbek
  emas — har qanday provayder puli avval qarzni yopadi (buyurtmaning o'z komissiyasi qarzga yozilgan bo'lsa, uni ham),
  qolgani hamyonga (`settlement`). Qarz bo'lmasa — hammasi hamyonga, avvalgidek.
- **Qaytarilganda** (`reverseDeliveredOrder`): qarzni yopgan pul qaytib qarzga yoziladi va u yopgan **hisob-faktura qayta
  ochiladi** (`SubscriptionDebt::reopen`: `paid_amount` kamayadi, `paid` → `issued`, obuna holati qayta hisoblanadi —
  muddati o'tgan bo'lsa `past_due` / `locked`). Bekor qilingan hisob-faktura ochilmaydi (ulushi komissiya qarziga). P4-23
  dan oldingi keshbek yozuvlarida hisob-faktura belgisi yo'q: ular komissiya qarzi sifatida qaytadi.

### Payoutlar

- `GET /admin/finance/payouts?status=open|pending|awaiting_second|completed|rejected|all&store_id=&cursor=&per_page=` —
  `open` (odatda) — kutilayotganlar, eng eskisi birinchi; `counts {pending, awaiting_second, open_amount}`, `threshold`.
  Qator: `id, store, amount, card` (doim niqoblangan), `status` (`pending` — tasdiq yo'q, `awaiting_second` — bitta tasdiq
  bor, `completed`, `rejected`), `status_label, needs_second_approval, first_approval {by, at, note}, netted_amount,
  transferred_amount, created_at, processed_at`.
- `GET /admin/finance/payouts/{id}` — qator + `breakdown {accrued, netted, transfer, preview, lines[]}` (ochiq payoutda
  hozir tasdiqlansa nima bo'lishi, `preview: true`; `lines[]`: `kind` invoice|commission, `label`, `amount`, `invoice
  {id, uuid, number, period}`, `transaction_id`), `balances {wallet, debt, subscription_debt, commission_debt,
  credit_limit}`, `notes`, `resolution_notes`, `processed_by`, `actions {can_approve, can_reject, approve_completes}`. Karta
  raqami `pii.view` bilan to'liq (`admin.pii.revealed` yoziladi), aks holda niqoblangan.
- `POST .../approve {reason}` — 1 mln dan kam: darhol o'tkaziladi. 1 mln dan: birinchi tasdiq yoziladi (`awaiting_second`,
  pul harakatlanmaydi), boshqa xodimning tasdig'i o'tkazadi. Hamyon yetmasa 422 `insufficient_wallet` (hech narsa
  yozilmaydi). Ko'rib chiqilgan payout — 409 `payout_decided`; o'sha xodim o'sha qarorni takrorlasa — o'sha javob.
- `POST .../reject {reason}` — ochiq payout (birinchi tasdiqdan keyin ham); sabab egasiga `resolution_notes` bo'lib boradi.
- Jurnal: `admin.finance.payouts.approved` / `.rejected`, `before/after.status`, `meta`: `amount`, `netted`, `transferred`,
  `netting` (qatorlar), `first_approved_by`.
- Egasining so'rovi (`POST /owner/payouts`) va javob shakli o'zgarmagan: `awaiting_second` egaga `pending` bo'lib ko'rinadi.
  Eski superadmin paneli (Filament) ham shu `PayoutService::complete` orqali o'tkazadi (qarz ushlanadi), lekin 1 mln va
  undan kattasini o'tkazmaydi ("Boshqaruv markazida ikki xodim tasdiqlaydi"). Xuddi shunday eski paneldagi "To'lov qabul
  qilish" (qarzni kamaytirish) 1 mln dan (24 soatdagi bo'laklar va bitta tasdiqli tuzatishlar bilan) va 1 mln dan katta yoki
  to'langan hisob-fakturani bekor qilish rad etiladi.

### To'ldirishlar, hisob-kitob xatolari, balans va qarzlar

- `GET /admin/finance/topups?status=pending|paid|cancelled|all&provider=click|payme&store_id=&stuck=1` — egalarning
  Click/Payme to'ldirishlari; `stuck` — 3 kun ichida 30 daqiqadan beri to'lanmagan; `transaction_id` — to'langanining qarz
  yozuvi; `counts {pending, stuck}`.
- `GET /admin/finance/settlement-failures` — hisob-kitobi yozilmagan yetkazilgan buyurtmalar (`attempts`, `max_attempts`,
  `gave_up`, `error`); `POST .../{uuid}/retry {reason}` — `SettlementRecoveryService::retry` (har 10 daqiqadagi ish bilan bir
  yo'l, urinishlar tugagan bo'lsa ham): `{booked, order}` (`order: null` — yozildi yoki endi yetkazilgan emas).
  Hisob-kitob yozilmagan paytda tasdiqlangan qisman qaytarishlar (`execution.ledger_reversal_deferred: true`) shu yozuv
  bilan bir tranzaksiyada qaytariladi (jamlanib, har so'rov bir marta; so'rovning `execution.ledger_reversal` i yangilanadi,
  `ledger_reversal_on_retry: true`).
- `GET /admin/finance/balances?filter=all|debt|over_limit|subscription_debt|wallet&q=&page=&per_page=` — `wallet_balance,
  debt_balance, subscription_debt, commission_debt, credit_limit, over_limit` (faqat **komissiya qarzi** limitdan oshsa),
  `is_order_locked, pending_payouts`; `totals` — hamma do'konlar bo'yicha.
- `GET /admin/finance/stores/{id}/ledger?ledger=debt|wallet&cursor=` — do'kon ledger'i (eng yangisi birinchi), har yozuvda
  `order`, `subscription_invoice` (nima yopilgan yoki qayta ochilgan), `payout_request_id`; izohdagi karta raqamlari niqoblangan.
- `GET /admin/finance/summary` — tablar va "Bugun" uchun sonlar.

### Qo'lda tuzatish

- `POST /admin/finance/adjustments` (JSON yoki multipart) `{store_id, kind, amount, reason, document?}`. `kind`:
  `debt_charge` (qarz oshadi — komissiya qismi, kredit limitiga kiradi, limitdan oshsa do'kon bloklanadi), `debt_credit`
  (komissiya qarzi kamayadi), `debt_payment` (hisob raqami yoki naqd to'lov — eng eski hisob-fakturalarni yopadi, har biri
  alohida yozuv), `wallet_credit`, `wallet_debit` (hamyon manfiy bo'lmaydi: 422 `insufficient_wallet`). `amount` — so'm,
  0 dan katta, ko'pi bilan 2 kasr. `document` — pdf/jpg/png/webp, 10 MB gacha, yopiq diskda (`config('finance.adjustments')`),
  `GET .../{id}/document` bilan yuklab olinadi. 1 mln dan kam — darhol `applied`; 1 mln dan — `pending`, ledger o'zgarmaydi.
- `POST .../{id}/approve {reason}` — yaratgan xodim emas (403 `four_eyes`); ledger yozuvi (`adjustment` turi, `reference_id`
  = tuzatish uuid) shu paytda. `POST .../{id}/reject {reason}` — kutilayotganini (yaratgan ham qaytarib olishi mumkin).
  Qaror qilingan — 409 `adjustment_decided`.
- Javob: `id, uuid, store, kind, kind_label, ledger, amount, signed_amount, reason, status` (`pending|applied|rejected`),
  `needs_second_approval, requested_by, approved_by, rejected_by` (vaqtlari bilan), `document {name, mime, size}`,
  `transactions[]` (yozilgan ledger qatorlari), `mine, can_approve, can_reject`.
- Jurnal: `admin.finance.adjustments.created` / `.approved` / `.rejected`.

### Oylik hisobot

`GET /admin/finance/reports/monthly?period=YYYY-MM` (Toshkent oyi, odatda joriy) — `totals {orders, gmv, commission,
commission_refunded, subscription, total}`, `channels[]` (`rizqim_courier`, `own_delivery`, `pickup`, `pos`, `aggregator`,
`unknown` — kanal yozilmagan eski buyurtmalar), `subscriptions {invoices, invoiced, paid, outstanding}`, `stores[]` (eng
kattasi birinchi, `commission_by_channel`). Komissiya: shu oyda yetkazilgan (birinchi `delivered` yozuvi) va hali yetkazilgan
holatdagi buyurtmalarning `commission_amount` i, **qisman qaytarishlar ayirilgan** (`order_ledger_reversals`); to'liq
qaytarilgan buyurtma kirmaydi. Obuna: oyning bekor qilinmagan hisob-fakturalari. `.../export` — xuddi shu `.xlsx`
("Umumiy", "Ovqatxonalar"; summalar son, matnlar — ovqatxona nomi ham — doim matn katakchasi: `=` bilan boshlangan nom
formula bo'lib qolmaydi).

### "Diqqat talab qiladi" manbalari

| `kind` | Nima | Ruxsat | `href` |
|---|---|---|---|
| `payout_second_approval` | birinchi tasdig'i bor, ikkinchisini kutayotgan payoutlar | `payouts.approve` | `/finance?tab=payouts&payout={id}` |
| `adjustment_second_approval` | tasdiq kutayotgan qo'lda tuzatishlar | `finance.manage` | `/finance?tab=adjustments&adjustment={id}` |

Bugun'ning `payout_pending` manbasi endi faqat hali tasdiqlanmaganlarni sanaydi.

### Boshqaruv markazi proksisi: fayllar

`platform/admin` proksisi (`lib/server/proxy.ts`) JSON'dan tashqari faqat ikki narsani o'tkazadi: `POST
/admin/finance/adjustments` ga multipart (11 MB gacha, boshqa yo'lga 415) va `GET .../reports/monthly/export`,
`.../adjustments/{id}/document` javobidagi fayl (faqat xlsx, pdf, jpeg, png, webp turlari; boshqa javob JSON bo'lmasa —
`bad_gateway`).

Proksi yozuvlarda (POST/PUT/PATCH/DELETE) tanani **o'qishdan oldin** tekshiradi: sessiya cookie'si bo'lmasa — 401
`unauthenticated` (faqat kirish bosqichlari cookie'siz: `auth/login`, `auth/invite/accept`, `auth/2fa/setup|verify|confirm`);
e'lon qilingan `Content-Length` limitdan oshsa — 413; tana oqim bilan o'qiladi va limitga yetganda to'xtatiladi
(`Content-Length` yo'q yoki yolg'on bo'lsa ham): JSON 256 KB, multipart 11 MB.

### Migratsiyalar va testlar

`2026_11_05_100100` (`payout_requests`: `first_approved_by/at/note`, `netted_amount`, `transferred_amount`, `netting`),
`100200` (`store_transactions.subscription_invoice_id`, `payout_request_id`; `store_wallet_transactions.payout_request_id`),
`100300` (`order_ledger_reversals`), `100400` (`finance_adjustments`), `100500` (ledger'larda `(order_id, type)` indekslari,
CONCURRENTLY), `100600` (`subscription_invoices.void_requested_by/at`, `void_request_reason` — ikki kishilik bekor qilish). Testlar: `tests/Feature/AdminFinance/*` (netting, payoutlar, qisman qaytarish, tuzatishlar, ro'yxatlar,
hisobot, hisob-fakturani bekor qilish).

## Mijozlar va yordam (P4-31)

Boshqaruv markazining **Mijozlar va yordam** bo'limi: foydalanuvchilar (mijozlar, kuryerlar, ovqatxona xodimlari) va
foydalanuvchi kartasi, bloklash va sessiyalarni yopish, shikoyatlar qutisi va tayyor javoblar, shubhali holatlar. Kod:
`routes/api_admin_customers.php`, `App\Http\Controllers\Api\V1\Admin\AdminCustomer*`, `AdminComplaintController`,
`AdminSupport*`, `App\Services\Admin\Customers`, `App\Providers\AdminCustomersServiceProvider`, sozlamalar
`config/support.php`. **RIZQIM jamoasi a'zolari bu yerda yo'q**: ro'yxatga kirmaydi, kartasi 404, amal 403
`platform_account` (ularni Tizim → Jamoa boshqaradi). Eski paneldagi "Foydalanuvchilar" va "E'tirozlar" shu yerga ko'chdi.

### Ruxsatlar

| Endpoint | Ruxsat | Step-up | `reason` |
|---|---|---|---|
| `GET /admin/users`, `GET /admin/users/{id}` | `users.view` | — | — |
| `POST /admin/users/{id}/block`, `/unblock`, `/close-sessions` | `support.manage` | ha | ha |
| `GET /admin/complaints`, `/complaints/{uuid}`, `/support/canned-replies`, `/support/suspicious` | `support.view` | — | — |
| `PATCH /admin/complaints/{uuid}`, `POST .../reply`, `POST .../resolve`; `POST/PATCH/DELETE /admin/support/canned-replies` | `support.manage` | — | — |

Standart rollarda: **Yordam** — hammasi; **Faqat ko'rish** — o'qiydi, o'zgartira olmaydi; Moliya, Savdo, Operator — yo'q.
Har bir yozish jurnalga yoziladi (amal nomi — route nomi): `admin.users.blocked`, `admin.users.unblocked`,
`admin.users.sessions_closed` (yopilgan tokenlar soni bilan), `admin.support.complaint_updated`,
`admin.support.complaint_replied` (matn bilan), `admin.support.complaint_resolved`,
`admin.support.canned_reply_created|updated|deleted`. Telefonlar `AdminPii` orqali: `pii.view` siz niqoblangan
(`+998 90 *** ** 67`), `pii.view` bilan to'liq va har bir ko'rsatish `admin.pii.revealed` bo'lib yoziladi.

### Foydalanuvchilar

`GET /admin/users?role=&status=active|blocked&store_id=&q=&cursor=&per_page=` → `{data, next_cursor, per_page, roles}`,
yangilari birinchi, kursor bilan. `role`: `customer`, `courier`, `staff` (kuryerdan boshqa barcha ovqatxona rollari),
`owner`, `manager`, `cashier`, `waiter`, `cook`. `q` — ismning bir qismi yoki telefon: **`pii.view` siz faqat to'liq raqam**
(`+998901234567`, `90 123 45 67`...) topiladi — niqoblangan raqamni raqamma-raqam o'qib bo'lmasin (umumiy qidiruvdagi
qoida); `pii.view` bilan istalgan 4+ raqam. Qator: `id`, `name`, `role`, `role_label`, `phone`, `telegram_linked`,
`store`, `branch`, `orders_count`, `blocked`, `blocked_at`, `created_at`.

`GET /admin/users/{id}` — karta:

- `profile` (telefon, SMS bilan tasdiqlanganmi, Telegram, kuryer turi, kim taklif qilgan, nechta odamni taklif qilgan),
  `block` (`blocked`, `at`, `reason`, `by`);
- `stats`: buyurtmalar soni, yetkazilgan, bekor qilingan, yetkazilganlar summasi, o'rtacha chek, oxirgi buyurtma, toifa
  (`segment`: 10+ buyurtma — doimiy mijoz (VIP), 2+ — qaytgan, 1 — yangi, 0 — buyurtma bermagan; eski paneldagi
  "Mood");
- `orders` (oxirgi 20, `href: /orders/{uuid}`) va `refunds` (uning buyurtmalariga pul qaytarish so'rovlari) —
  `orders.view` bo'lmasa `null`;
- `keshbek`: `balance` (users.balance — asosiy qiymat) va `entries`. Keshbek uchun alohida ledger jadvali yo'q, harakatlar
  yozilgan joyidan tiklanadi: keshbek bilan to'lov (minus), qisman qaytarishlar va bekor qilishda qaytgani (plus), do'st
  taklifi bonusi 5 000 (plus);
- `complaints` (oxirgi 20) — `support.view` bo'lmasa `null`;
- `sessions`: faol tokenlar (Mini App / Telegram, ilova, kassa PIN sessiyasi; admin sessiyasi hech qachon), ilova
  sessiyalari (refresh), oxirgi foydalanish, shaxsiy qurilmalari;
- `membership` — xodim hisobining ovqatxonasi, filiali, roli, SMS bilan kirish (sms / ega kiritgan), kassa PIN (mijozda
  `null`); `linked_accounts` — shu telefon yoki shu Telegramdagi boshqa hisoblar (bir odam mijoz va xodim, yoki ikki
  ovqatxonada);
- `audit` — shu hisob haqidagi jurnal yozuvlari (`audit.view` bo'lmasa `null`), `actions` (`block`, `unblock`,
  `close_sessions`), `links.audit`.

### Bloklash va sessiyalarni yopish (`support.manage`, step-up, `reason`)

- `POST .../block` — `users.blocked_at`, `blocked_by`, `blocked_reason` yoziladi va **barcha sessiyalar darhol yopiladi**.
  Bloklangan hisob hech qayerga kira olmaydi: Telegram (Mini App, bot), SMS kod, refresh, kassa PIN — hammasi 403
  `account_blocked` ("Hisobingiz bloklangan..."); oldin berilgan token ham ishlamaydi (Sanctum tekshiruvi), shuning uchun
  buyurtma bera olmaydi. Bloklangan xodim Telegram'dagi buyurtma tugmalari bilan ham ishlay olmaydi, eski `/admin`
  paneliga ham kirmaydi. **Ovqatxona egasi bloklansa, ovqatxona ishlashda davom etadi** — to'xtatish Ovqatxonalar
  bo'limida. 409 `already_blocked`.
- `POST .../unblock` — blok olib tashlanadi, odam qaytadan kira oladi. 409 `not_blocked`.
- `POST .../close-sessions` — Mini App va ilova tokenlari, refresh tokenlar, terminaldagi PIN sessiyalari o'chiriladi,
  qurilmalarga push ketmaydi. Kassaning o'z ulanishi (terminal tokeni) saqlanadi, admin sessiyalariga tegilmaydi;
  `sanctum:prune-expired` ishlatilmaydi. Odam qaytadan kira oladi (bloklanmagan bo'lsa).
- Javob — yangilangan karta.

### Shikoyatlar qutisi

Shikoyatning ikki tomoni bor: **mijoz ↔ ovqatxona** (`complaints.status`: `pending` → ovqatxona javob berdi
`owner_replied` → mijoz RIZQIMga yubordi `in_review` → `closed`; Mini App va hamkor paneli shuni ishlatadi) va **RIZQIM
yordam xizmati** (`support_status`): `new` (Yangi) → `in_progress` (Ko'rib chiqilmoqda) → `answered` (Javob berildi) →
`resolved` (Hal qilindi), mas'ul xodim (`assigned_to`).

- `GET /admin/complaints?status=open|new|in_progress|answered|resolved|all&assigned=me|none|<id>&type=&waiting=1&q=&cursor=`
  → `{data, next_cursor, counts, waiting_hours, assignees, types, statuses}`. `open` (standart) — yangi va ko'rib
  chiqilayotganlar, eng eskisi birinchi; boshqalari yangilari birinchi. `q` — buyurtma kodi yoki matndagi so'z.
- **Kutayotgan** (`waiting`): yordam tomonida ochiq, ovqatxona javob bermagan (yoki mijoz baribir RIZQIMga yuborgan) va
  `support.complaint_waiting_hours` (standart 4, `SUPPORT_COMPLAINT_WAITING_HOURS`) soatdan eski. Ovqatxona javob bergan
  va mijoz yubormagan shikoyat kutayotganlarga kirmaydi.
- `GET /admin/complaints/{uuid}` — qator + to'liq matn, `customer` (telefon `AdminPii` orqali, `telegram_linked`,
  `href: /users/{id}`), `order`, `store_reply` (ovqatxona javobi), `replies` (RIZQIM javoblari), `resolution`, `history`
  (jurnaldan), `actions` (`reply`, `resolve`, `reopen`).
- `PATCH .../{uuid} {support_status?, assigned_to?}` — holat (`resolved` emas: 422 `use_resolve`) va mas'ul (`support.view`
  li faol jamoa a'zosi yoki `null`; 422 `assignee_invalid`). Mas'ulsiz `in_progress` ga o'tkazgan xodimga biriktiriladi;
  hal qilinganini qaytarish — qayta ochish (ovqatxona tomoni `in_review` ga qaytadi).
- `POST .../reply {text, canned_reply_id?}` — 1–1000 belgi oddiy matn mijozning Telegramiga RIZQIM boti orqali, navbat
  bilan (buyurtma aralashuvlaridagi yo'l): "📣 RIZQIM yordam xizmati / Shikoyatingiz bo'yicha (buyurtma #KOD): ...".
  Javob saqlanadi (`complaint_replies`), holat `answered`. Telegram yo'q — 422 `no_recipient`, bot sozlanmagan — 503
  `telegram_not_configured` (ikkalasida hech narsa saqlanmaydi), hal qilingan — 409 `already_resolved`.
- `POST .../resolve {note}` — 3–1000 belgi. `resolved`, ovqatxona tomoni `closed`, izoh `resolution_comment` ga
  (mijoz Mini App'dagi shikoyatlar ro'yxatida ko'radi), `resolved_by` — xodim (eski paneldagi "Hal qilish" bilan bir
  xil). 409 `already_resolved`.

### Tayyor javoblar

`GET /admin/support/canned-replies` → `[{id, title, body, sort_order, updated_by, updated_at}]`; `POST {title ≤ 120, body
≤ 1000, sort_order?}` (ko'pi bilan 200 ta, 422 `limit_reached`), `PATCH /{id}`, `DELETE /{id}`. Javob matnning o'z
nusxasini saqlaydi: tayyor javobni o'zgartirish yoki o'chirish yuborilgan javoblarga ta'sir qilmaydi.

### Shubhali holatlar (faqat ko'rish)

`GET /admin/support/suspicious` → `{generated_at, groups: [{kind, label, description, rows}]}`. Hech narsa avtomatik hal
qilinmaydi; har qator nima uchun shu yerda ekanini so'z bilan aytadi (`reason`) va foydalanuvchi kartasiga olib boradi
(`href`). Har signal — bitta indeksli vaqt oralig'i, ko'pi bilan 50 qator; chegaralar `config/support.php` da:

| `kind` | Qoida | Misol |
|---|---|---|
| `cancellations` | 7 kunda kamida 3 ta buyurtmasi mijoz tufayli bekor qilingan (o'zi bekor qilgan, uning so'rovi bilan, olib ketmagan) | "7 kunda 4 ta buyurtma bekor qilindi: 3 tasini o'zi bekor qildi, 1 tasini olib ketmadi." |
| `promo_sharing` | 30 kunda bitta promo-kod bir xil telefon raqami yoki bitta Telegram akkauntiga ega 2+ hisobdan ishlatilgan (`accounts` — hammasi) | "BIRINCHI kodi 30 kunda bir xil telefon raqamli 2 ta hisobdan 3 marta ishlatildi." |
| `refunds` | 30 kunda qaytarish so'rovlari (kutilayotgan va tasdiqlangan) jami o'rtacha so'rovdan kamida 3 baravar ko'p va 100 000 so'mdan ortiq | "30 kunda 2 ta qaytarish, jami 300 000 so'm — o'rtacha qaytarishdan (48 889 so'm) 6.1 baravar ko'p." |

Qurilma bo'yicha signal hozircha yo'q: ilova qurilmasi bitta hisobga bog'lanadi (`devices.uuid` noyob, push token oxirgi
kirgan hisobga o'tadi), shuning uchun "bir qurilmadan ko'p hisob"ni o'qib bo'lmaydi.

### "Diqqat talab qiladi" manbasi (`support.view`)

| `kind` | Nima | `severity` | `href` |
|---|---|---|---|
| `complaint_waiting` | kutayotgan shikoyatlar (yuqoridagi qoida), eng eskisi birinchi; mijoz RIZQIMga yuborgani `warning` | `warning` | `/customers?tab=complaints&complaint={uuid}` |

### Eski paneldan

Superadmin panelidagi **Foydalanuvchilar** (GlobalUsers): ro'yxat, rol filtri, qidiruv, ko'rish (profil, analitika,
oxirgi buyurtmalar) — shu yerda. O'chirish o'rniga **bloklash** (buyurtmalar, ledger va jurnal tarixi saqlanadi).
Yaratish va tahrirlash ko'chirilmadi: mijozlar o'zi ro'yxatdan o'tadi (Telegram, SMS), ovqatxona xodimlarini egasi hamkor
panelida qo'shadi va tahrirlaydi (rol, filial, telefon, PIN), ega ovqatxona yaratilganda paydo bo'ladi (Ovqatxonalar →
ulash jarayoni), jamoa a'zolari — Tizim → Jamoa. Xodimning Telegram taklif havolasi — hamkor panelidagi xodimlar ro'yxatida,
egasiniki — Ovqatxonalar kartasida (`telegram-link`). "SMS bilan kirishni yoqish" (eski xodim telefonini tasdiqlash) —
ega yoki menejer hamkor panelida qiladi (`StaffAccountService`), egasiniki ovqatxona yaratishda (`StoreProvisioner`);
jonli ovqatxonalar hali yo'q, eski ma'lumot ham yo'q. Kuryer turini (`fleet`) o'zgartirish — RIZQIM kuryerlari tarmog'i
bilan (keyingi bosqich). **E'tirozlar** (Complaints): ro'yxat, ko'rish, holat va hal qilish izohi — Shikoyatlar qutisida.

### Migratsiyalar va testlar

`2026_11_06_100100` (`users.blocked_at`, `blocked_reason`, `blocked_by`), `100200` (`complaints.support_status`,
`assigned_to`, `support_status_changed_at`, `answered_at`, `resolved_at`, indekslar; yopilganlar `resolved` bo'ladi),
`100300` (`support_canned_replies`), `100400` (`complaint_replies`). Testlar: `tests/Feature/AdminCustomers/*`.

## Marketing (P4-31)

Boshqaruv markazining **Marketing** bo'limi (`platform/admin`, `/marketing`): Mini App bosh sahifasidagi bannerlar va
aksiyalar, promo-kodlar (ishlatilish statistikasi bilan) va kategoriyalar tartibi. Eski `/superadmin` panelidagi
`PromoResource`, `PromoCodeResource` va `Categories` o'rnini bosadi. Kod: `routes/api_admin_marketing.php`
(`/admin/marketing`, nomlar `admin.marketing.*`), `Admin/AdminMarketing*Controller`, `app/Services/Admin/Marketing/*`
(`PromoBanners`, `PromoCodes`, `MarketingCategories`). Pul — o'nlik satr (`"5000.00"`), vaqt — ISO 8601 (Asia/Tashkent,
bazada UTC). Har o'zgarish `reason` (3–500 belgi) talab qiladi va jurnalga yoziladi (amal = route nomi, obyekt, oldin/keyin,
sabab); **step-up faqat o'chirishda** (egasi qarori: marketing tuzatishlari pul ushlamaydi, qaytarib bo'ladi).

### Ruxsatlar

| Endpoint | Ruxsat | Step-up |
|---|---|---|
| `GET /admin/marketing/promos`, `/promos/preview`, `/promos/{id}/image`, `/promo-codes`, `/promo-codes/{id}`, `/categories`, `/categories/{id}/image`, `/lookup` | `marketing.view` | — |
| `POST /admin/marketing/promos`, `/promos/{id}`; `PUT /promos/order` | `marketing.manage` + `reason` | — |
| `POST /admin/marketing/promo-codes`; `PATCH /promo-codes/{id}`; `POST /promo-codes/{id}/activate`, `/deactivate` | `marketing.manage` + `reason` | — |
| `POST /admin/marketing/categories`, `/categories/{id}`; `PUT /categories/order` | `marketing.manage` + `reason` | — |
| `DELETE /admin/marketing/promos/{id}`, `/promo-codes/{id}`, `/categories/{id}` | `marketing.manage` + `reason` | **ha** (`admin.recent`) |

Standart rollarda `marketing.manage` faqat superadminda; «Faqat ko'rish» roli o'qiydi; boshqa rollarga superadmin Rollar
sahifasida beradi. Rasm yuklanadigan so'rovlar `multipart/form-data` (PHP multipart `PATCH`ni o'qimaydi, shuning uchun
o'zgartirish ham `POST`); mantiqiy qiymatlar `1`/`0`. Rasm: JPG, PNG yoki WebP, **4 MB gacha** (SVG yo'q).

### Bannerlar va aksiyalar (`promos`)

Banner — Mini App bosh sahifasi (`GET /catalog`, `store_id`siz) ro'yxatidagi qator: sarlavha, rasm, bosilganda ochiladigan
ovqatxona (`store_id`) yoki taom (`product_id`; ikkalasi berilsa taom shu ovqatxonaniki bo'lishi shart —
`product_store_mismatch`), tartib (`sort_order`), yoqilgan/o'chiq va **jadval** (`starts_at`, `ends_at`; ikkalasi ham bo'sh —
yoqilgan paytda doim ko'rinadi, mavjud bannerlar shunday ishlashda qoladi). Rasm eski panel kabi `public` diskning `promos/`
papkasiga tushadi (`Promo::image_url` shu yerdan quradi).

- `GET /admin/marketing/promos?status=&q=` — hamma banner `sort_order` bo'yicha (ko'pi bilan 200). `status` hozir
  hisoblanadi: `inactive` (o'chiq) → `scheduled` (`starts_at` oldinda) → `ended` (`ends_at` o'tgan) → `live`. Qatorda
  `image_url` (jamoat URL) va `image_preview` (admin yo'li, `GET`, shu sessiya bilan; veb-ilova rasmni shu orqali ko'rsatadi).
- `GET /admin/marketing/promos/preview` — Mini App **hozir** ko'rsatadigan bannerlar, tartibi bilan: `GET /catalog`
  bilan bir xil so'rov (`Promo::live()` va `toCatalogEntry()`), shuning uchun «Mini App'da ko'rinishi» ko'rgazmasi aniq.
- `POST /admin/marketing/promos` — `title` (2–255), `image` (majburiy), `store_id`, `product_id`, `is_active`,
  `starts_at`, `ends_at` (`ends_at` > `starts_at`, aks holda 422 `invalid_schedule`), `reason`. Oxiriga qo'shiladi. 201.
- `POST /admin/marketing/promos/{id}` — shu maydonlardan istalgani (`store_id`, `product_id`, jadval bo'sh yuborilsa
  tozalanadi; yangi `image` eskisini almashtiradi va eski fayl o'chadi). O'zgarmasa jurnalga yozilmaydi.
- `PUT /admin/marketing/promos/order` — `ids`: **hamma** banner, har biri bir marta, yangi tartibda; ro'yxat eskirgan
  bo'lsa (yangi banner qo'shilgan/o'chirilgan) 409 `order_stale` — hamkasbning tartibi bosib ketilmaydi.
- `DELETE /admin/marketing/promos/{id}` — step-up + `reason`; banner va rasmi o'chadi, jurnalda maydonlari qoladi.

### Promo-kodlar (`promo-codes`)

Qoidalar **buyurtma berishdagi bilan bir xil** (`PromoCodeService`, `CreateOrderAction`): kod bosh harfda, kesilgan
holda saqlanadi (harf, raqam, `-`, `_`; 3–50 belgi; katta-kichik farqsiz yagona); `percentage` — 0 dan katta va 100 gacha
(2 kasr), ixtiyoriy `max_discount_amount` (so'm) chegarasi; `fixed` — 0 dan katta **butun so'm** (buyurtma oqimi uni butun
so'mga yaxlitlaydi va chegarani e'tiborsiz qoldiradi, shuning uchun `fixed`da chegara saqlanmaydi). Minimal summa
(`min_order_amount`), umumiy va mijoz uchun limitlar (`usage_limit`, `per_user_limit`, bo'sh — cheklanmagan), muddat
(`expires_at`, yaratishda kelajakda bo'lishi shart) va ovqatxona (`store_id`, bo'sh — hamma) buyurtma paytida tekshiriladi.

- `GET /admin/marketing/promo-codes?q=&status=&store_id=&sort=&page=&per_page=` — sahifalab (30; ko'pi 100). `status`:
  `active`, `inactive` (o'chirilgan), `expired` (muddati o'tgan), `exhausted` (limit tugagan). `sort`: `newest`, `code`, `expiry`.
- Har qatorda `stats` — **limitga sanaladigan** buyurtmalar bo'yicha (bekor qilingan, rad etilgan va qaytarilgan emas;
  `PromoCode::RELEASED_ORDER_STATUSES`):

  | Maydon | Ma'nosi |
  |---|---|
  | `uses` | kodni ishlatgan buyurtmalar soni (limit shuni sanaydi) |
  | `unique_customers` | shu buyurtmalardagi turli mijozlar |
  | `discount_total` | berilgan chegirma yig'indisi (`orders.discount`) |
  | `gmv` | shu buyurtmalarning jami summasi (`orders.total_price`, chegirmadan keyin) |

  Statistika bir so'rovda, sahifadagi kodlar uchun, `(promo_code_id, user_id)` indeksi ustida hisoblanadi.
- `GET /admin/marketing/promo-codes/{id}` — qator va `recent_orders` (oxirgi 10 buyurtma: `uuid`, `status`, `discount`, `total`).
- `POST /admin/marketing/promo-codes` — maydonlar yuqoridagidek (`code`, `type`, `value` majburiy), `reason`. 201.
  Qiymat turiga mos kelmasa 422 `invalid_value`.
- `PATCH /admin/marketing/promo-codes/{id}` — istalgan maydon. **Bu koddan bitta bo'lsa ham buyurtma berilgan bo'lsa**
  (`locked: true`; bekor qilingani ham sanaladi — hisobotlar va buyurtmalar eski chegirmaga tayanadi) `code`, `type`, `value`,
  `max_discount_amount` va `store_id` o'zgarmaydi: 422 `promo_code_used`, `data.fields` — qaysi maydonlar. **Yangi promo-kod
  yaratib, eskisini o'chirib qo'ying.** Limitlar, `min_order_amount`, `expires_at` va yoqish/o'chirish erkin; o'sha qiymatni
  qayta yuborish o'zgarish emas.
- `POST /admin/marketing/promo-codes/{id}/activate`, `/deactivate` — yoqish/o'chirib qo'yish (buyurtma oqimi har buyurtmada
  tekshiradi, shuning uchun darhol ta'sir qiladi; berilgan buyurtmalar o'z chegirmasini saqlaydi).
- `DELETE /admin/marketing/promo-codes/{id}` — faqat hech bir buyurtmada **ishlatilmagan** kod (aks holda 409
  `promo_code_used`: o'chirib qo'ying). Eski paneldagi «o'chirish» ishlatilgan kodlarda buyurtmadagi havolani bo'shatardi;
  endi bunday kod faqat faolsizlantiriladi.

Jurnalda kod `promo` kaliti bilan yoziladi (umumiy tozalagich `code` kalitini maxfiy deb yashirar edi, promo-kod esa maxfiy emas).

### Kategoriyalar (`categories`)

Kategoriyalar butun marketplace uchun umumiy, **ikki daraja**: asosiy va uning ichki kategoriyalari (`parent_id` asosiy
kategoriya bo'lishi shart; ichki kategoriyasi borini boshqasining ichiga qo'yib bo'lmaydi — 422 `invalid_parent`). Ikonka —
kategoriyaning `image`i: `MenuImageService` orqali (menyu rasmlari diski, WebP, kichik rasmlarni navbatdagi vazifa yasaydi).
«Yashirish» — `is_active: false`: kategoriya katalogdan yo'qoladi, taomlari qoladi.

- `GET /admin/marketing/categories` — hamma kategoriya `sort_order` bo'yicha; `products_count`, `children_count`, `parent`.
- `POST /admin/marketing/categories` — `name` (2–255), `parent_id`, `image` (ixtiyoriy), `is_active`, `reason`; oxiriga
  qo'shiladi (slug nomdan). 201.
- `POST /admin/marketing/categories/{id}` — `name` (slug ham yangilanadi), `parent_id` (bo'sh — asosiy), `is_active`,
  `image`, `remove_image`, `reason`.
- `PUT /admin/marketing/categories/order` — `ids`: **hamma** kategoriya, har biri bir marta, yangi tartibda (bir
  so'rovda butun ro'yxat; bitta kategoriya modelidagi «qo'shnilarni siljitish» qoidasi ketma-ket ko'chirishga mos emas, shuning
  uchun tartib to'g'ridan-to'g'ri yoziladi). `updated_at` o'zgaradi — POS menyu versiyasi ham yangilanadi. Ro'yxat eskirgan
  bo'lsa 409 `order_stale`.
- `DELETE /admin/marketing/categories/{id}` — step-up; faqat taomsiz va ichki kategoriyasiz (aks holda 409
  `category_in_use`: yashiring); yumshoq o'chirish (eski panel kabi).

### Tanlagich

`GET /admin/marketing/lookup?q=&store_id=&ids[]=&product_ids[]=` (`marketing.view`): `q` bo'yicha 20 tagacha ovqatxona;
`store_id` + `q` — shu ovqatxonaning 20 tagacha taomi; `ids[]` / `product_ids[]` — tanlangan qiymat nomi uchun. Marketing
xodimi uchun alohida `stores.view` kerak emas.

### Migratsiya va testlar (P4-31)

`2026_11_06_200000_add_schedule_to_promos_table` — `promos.starts_at`, `promos.ends_at` (NULL = doim). `GET /catalog`
endi faqat jadval ichidagi yoqilgan bannerlarni beradi. Testlar: `tests/Feature/AdminMarketing/*` (ruxsat har endpoint va
rolda, step-up, `reason`, jurnal, rasm cheklovlari, jadval, buyurtma oqimi bilan moslik, statistika, qulf).

## Tizim (P4-31)

Boshqaruv markazining **Tizim** sahifasi (`platform/admin`, `/system`, menyuda «Tizim sozlamalari»): platforma
sozlamalari, to'lov tizimlari, viloyatlar va tumanlar, funksiyalar (feature flag'lar). Eski `/superadmin`dagi «Tizim
Sozlamalari», «To'lov Tizimlari», «Regions», «Districts» va «Feature flaglar» shu yerga ko'chdi. Kod:
`routes/api_admin_system.php` (`/admin/system`, nomlar `admin.system.*`), `Admin/AdminSystem*Controller`,
`app/Services/Admin/System/*`. Hammasi **`system.manage`** (faqat Superadmin, rol sozlamasi o'zgarmagan bo'lsa); har
o'zgarish step-up (`admin.recent`) va `reason` (3–500 belgi) talab qiladi va harakatlar jurnaliga route nomi bilan
yoziladi. Hech narsani o'zgartirmagan takroriy so'rov jurnalga yozilmaydi. SLA chegaralari (`PUT /admin/orders/sla`) va
tariflar katalogi (`/admin/billing/plans`) o'z bo'limlarida qoladi; Tizim sahifasi ularga havola beradi.

**Maxfiy kalitlar hech qachon to'liq qaytmaydi:** javobda faqat `configured` (kiritilganmi) va `last4` (oxirgi 4 belgi;
8 belgidan qisqa qiymatda `null`). Maxfiy kalit oddiy forma bilan o'zgartirilmaydi — alohida «almashtirish» amali bor.
Jurnal yozuvida qiymatning o'zi yo'q: faqat qaysi kalit, avval kiritilganmi va o'chirildimi.

| Endpoint | Nima |
|---|---|
| `GET /admin/system/settings` | Guruhlar: `sms`, `telegram`, `general`. Har qatorda `key`, `label`, `description`, `type` (`select`, `email`, `text`, `secret`), `editable`, `secret`, `value` (maxfiyda `null`), `configured`, `last4`, `source`, `default`, `options`, `updated_at` |
| `PATCH /admin/system/settings` | `values: {key: qiymat}` + `reason`. Faqat oddiy SMS sozlamalari: `sms_driver` (`log` / `eskiz`), `eskiz_email`, `eskiz_from`. Bo'sh qiymat saqlanganini o'chiradi (`.env` yoki standart ishlaydi). Maxfiy kalit yuborilsa 422 `secret_separate`, noma'lum kalit 422 `unknown_setting`, Eskiz email yoki paroli yo'q bo'lib Eskizga o'tish (yoki Eskiz ishlab turganda emailni bo'shatish) 422 `eskiz_not_configured`. Javob — hamma guruhlar |
| `PUT /admin/system/settings/secrets/{key}` | `value` (1–500) + `reason`. Hozir faqat `eskiz_password`; boshqasi 404 `not_found` |
| `DELETE /admin/system/settings/secrets/{key}` | `reason`. Saqlangan qiymat o'chadi (`.env` dagisi ishlaydi). SMS Eskiz orqali ketayotganda parolni o'chirish 422 `in_use` |
| `GET /admin/system/payment-providers` | Payme, Click, Uzum Bank, Paynet: `is_active`, `mode` (`test` / `production`), `wired` (to'lov havolasi va webhooklar ishlatadimi: Payme, Click), `credentials` — har kalit uchun `label`, `known`, `configured`, `last4`, `source` |
| `PATCH /admin/system/payment-providers/{slug}` | `is_active` va/yoki `mode` + `reason` |
| `PUT /admin/system/payment-providers/{slug}/credentials/{key}` | `value` + `reason`. Kalit tizim kodi biladiganlardan (Payme: `merchant_id`, `secret_key`; Click: `service_id`, `merchant_id`, `secret_key`, `merchant_user_id`; Uzum: `terminal_id`, `secret_key`; Paynet: `merchant_id`, `secret_key`) yoki allaqachon saqlangan bo'lishi kerak, aks holda 422 `unknown_key` |
| `DELETE /admin/system/payment-providers/{slug}/credentials/{key}` | `reason`. Saqlangan kalit o'chadi, `config/services.php` (`.env`) qiymati ishlaydi |
| `GET /admin/system/regions` | Hamma viloyatlar (`sort_order`, keyin nom bo'yicha) va ichida tumanlari. `usage`: viloyatda `branches`, `addresses` (mijoz manzillari), `applications` (ovqatxona arizalari); tumanda `branches`, `addresses`. `deletable` |
| `POST /admin/system/regions` | `name` (2–120, takrorlanmas), `is_active` (standart `false`), `reason`. Ro'yxat oxiriga qo'shiladi. 201 |
| `PATCH /admin/system/regions/{id}` | `name` va/yoki `is_active` + `reason` |
| `DELETE /admin/system/regions/{id}` | `reason`. Tumanlari bor viloyat 422 `has_districts`; filial, manzil yoki ariza ishlatayotgani 422 `in_use` (`data.usage`) |
| `PUT /admin/system/regions/order` | `ids` — **hamma** viloyatlar yangi tartibda, har biri bir marta, + `reason`; aks holda 422 `order_mismatch`. Javob — ro'yxat |
| `POST /admin/system/regions/{id}/districts` | Tuman qo'shish: `name` (viloyat ichida takrorlanmas), `is_active`, `reason`. 201 |
| `PUT /admin/system/regions/{id}/districts/order` | Viloyat tumanlarining to'liq tartibi (`ids`) + `reason` |
| `PATCH /admin/system/districts/{id}` | `name`, `is_active`, `region_id` (boshqa viloyatga ko'chirish — faqat hech narsa ishlatmayotgan tuman, aks holda 422 `in_use`) + `reason` |
| `DELETE /admin/system/districts/{id}` | `reason`. Filial yoki manzil ishlatayotgan tuman 422 `in_use` |
| `GET /admin/system/feature-flags` | Kodda e'lon qilingan funksiyalar (`App\Features\Features`): `name`, `label`, `description`, `default`, `stores_on`, `stores_off`, `stores_total` |
| `GET /admin/system/feature-flags/stores?q=&page=&per_page=` | Ovqatxonalar (nom bo'yicha) va har funksiya uchun `active` hamda `stored` (o'z qiymati bormi; `false` — standartga amal qiladi). O'qish hech narsa saqlamaydi |
| `PUT /admin/system/feature-flags/{flag}/stores/{storeId}` | `active` (true / false) + `reason`. Pennant orqali (eski sahifadagi kabi) |
| `DELETE /admin/system/feature-flags/stores/{storeId}` | `reason`. Ovqatxonaning o'z qiymatlari unutiladi (eski «Standartga qaytarish») |

Qoidalar va izohlar:
- Sozlama qiymati `setting()` yordamchisi bilan bir xil tartibda aniqlanadi: `settings` jadvalidagi (bo'sh satr ham)
  qiymat, keyin `config/settings.php` (`.env`), keyin kodning standarti. `source`: `settings`, `env`, `default` yoki
  `null`. Saqlangandan keyin `setting()` keshi tozalanadi. Telegram (bot tokeni, bot nomi, Mini App manzili, webhook kaliti,
  superadmin chati, navbatchi guruhi) va Umumiy (ilova nomi, manzili, muhit, vaqt mintaqasi) qatorlari `.env` dan olinadi
  va faqat ko'rsatiladi (`editable: false`).
- To'lov kaliti `PaymentProvider::platformCredential()` kabi aniqlanadi: shu yerda saqlangani, bo'lmasa
  `config/services.php`. Yangi `payment_providers.mode` ustuni eski `test_mode` kalitidan to'ldirildi. P4-32 dan
  `is_active` va `mode` buyurtma oqimida ishlaydi — pastdagi «To'lov tizimini o'chirish va sinov rejimi» bo'limiga qarang. «Ulanishni tekshirish» yo'q: hech bir provayderda kalitni yon ta'sirsiz tekshiradigan so'rov yo'q.
- Viloyatlar va tumanlar tartibi Mini App'ning `GET /locations` javobiga ham ta'sir qiladi (`sort_order`, keyin nom).
- Eski paneldagi to'lov tizimi yaratish/o'chirish va ikonka yuklash ko'chirilmadi: yangi provayder kodsiz ishlamaydi,
  ikonka hech qayerda ko'rsatilmaydi.

### To'lov tizimini o'chirish va sinov rejimi (P4-32, egasi qarori)

`payment_providers.is_active` va `mode` endi buyurtma oqimida ishlaydi (`App\Services\Payment\PaymentProviderSwitch`,
qatorlar keshda, saqlanganda kesh tozalanadi):

- **O'chirilgan** tizim barcha ovqatxonalar uchun o'chadi (o'z hisobiga yig'adiganlar ham). Mini App uni taklif qilmaydi:
  do'kon ma'lumotida `payment_settings.<tizim> = {enabled: false, available: false}`; hamkor panelida
  `unavailable_reason: "switched_off"` (Uzum/Paynet uchun `not_integrated`) va «RIZQIM vaqtincha o'chirgan» belgisi. Yangi
  to'lov rad etiladi: `POST /orders` va `POST /owner/finance/top-up` — 422, `code` va `data.reason`
  `payment_provider_inactive` (`errors.payment_method` / `errors.provider` da o'zbekcha matn). Ovqatxonalar saqlagan
  sozlama saqlanib qoladi: tizim qayta yoqilsa hamma narsa avvalgidek. Yangi ovqatxona o'chirilgan tizimni yoqilgan holda
  boshlaydi.
- **Boshlangan to'lovlar** hech qachon rad etilmaydi: Payme va Click webhooklari kalitni tekshiradi, kalitni emas —
  `is_active`ni hech qachon so'ramaydi (mavjud buyurtma yoki to'ldirish yakunlanadi, bekor qilinadi, qaytariladi).
- **Sinov rejimi** (`mode: test`) platforma hisobi orqali Payme to'lovini Payme sinov sahifasiga (`checkout.test.paycom.uz`)
  yo'naltiradi; ishchi rejim — `checkout.paycom.uz`. Click'da alohida sinov manzili yo'q (`my.click.uz`). Kalitlar rejimdan
  qat'i nazar shu yerda saqlangani (yoki `.env`) — eski `test_mode` belgisi ham kalitlar yonida turgan edi: sinov paytida
  sinov kalitlarini kiriting. O'z hisobiga yig'adigan ovqatxona har doim ishchi manzilda.
- Satr yo'q (kalitlar faqat `.env`da) — tizim yoqilgan va ishchi rejimda.
- To'lovdan qaytish manzili endi eski `/admin` panel emas: buyurtma — Mini App (`TELEGRAM_WEBAPP_URL`), balans to'ldirish —
  hamkor panelining `/finance` sahifasi (`PARTNER_WEB_URL`; bo'sh bo'lsa `APP_URL`).

### Migratsiyalar va testlar (Tizim)

`2026_11_06_300100` (`regions.sort_order`, `districts.sort_order`), `2026_11_06_300200` (`payment_providers.mode`).
Testlar: `tests/Feature/AdminSystem/*`.

## Integratsiyalar (P4-31)

Boshqaruv markazining **Integratsiyalar** sahifasi (`/integrations`) va 5-bosqich ulagichlari uchun ma'lumot modeli
(`docs/PHASE5_PLAN.md` 4.3). Ulagichlar, fon vazifalari va «qayta yuborish» hali yo'q: hamma ro'yxat bo'sh turadi va buni
tinch matn bilan aytadi. Kod: `routes/api_admin_integrations.php` (`/admin/integrations`, nomlar `admin.integrations.*`),
`Admin/AdminIntegration*Controller`, `app/Services/Admin/Integrations/*`, modellar `app/Domain/Integrations`.

**Ma'lumot modeli:**

| Jadval / maydon | Nima |
|---|---|
| `integration_connections` | Ovqatxona (va ixtiyoriy filial) ↔ tashqi tizim: `provider` (`iiko`, `alipos`, `r_keeper`, `clopos`, `poster`, `mison`, `regos`, `yandex_eats`, `uzum_tezkor`, `partner_api`, `fake`), `direction` (`rizqim_to_pos` — A, `aggregator_to_rizqim` — B), `mode` (`sandbox` / `production`), `status` (`configuring`, `active`, `paused`, `disconnected`), `credentials` (Laravel shifrlangan JSON, modelda `hidden`, hech qachon serializatsiya qilinmaydi), `settings` (JSON), sog'liq: `last_success_at`, `last_webhook_at`, `last_error_at`, `last_error`, `errors_today`, `health_score` (0–100), `health_checked_at`; pauza: `paused_at`, `paused_by`, `pause_reason`, `status_before_pause` |
| `integration_events` | Jurnal: ulanish (topilmagan kiruvchi so'rov uchun `null`), `direction` (`inbound` / `outbound`), `type` (`order.push`, `menu.sync`, `webhook.status`...), `idempotency_key` (ulanish + yo'nalish ichida yagona), `subject_type` + `subject_id`, `request_body`, `response_body`, `http_status`, `signature_valid`, `attempts`, `last_attempt_at`, `next_attempt_at`, `status` (`pending`, `sent`, `acknowledged`, `failed`, `dead`), `error`, `duration_ms` |
| `external_refs` | Ulanish ichida RIZQIM obyekti (`branch`, `category`, `product`, `variant`, `modifier`, `order`) ↔ tashqi ID, ikki tomonga yagona |
| `orders.fiscalized_by` | `venue_pos`, `payment_provider`, `aggregator`, `rizqim_marketplace` (zaxira); bo'sh — «fiskal ko'rib chiqish kerak». Hamma mavjud buyurtma bo'sh, 5-bosqichgacha hech narsa o'qimaydi |

Modellar: `IntegrationConnection`, `IntegrationEvent`, `ExternalRef` va enumlar (`IntegrationProvider`,
`IntegrationDirection`, `IntegrationMode`, `IntegrationConnectionStatus`, `IntegrationEventDirection`,
`IntegrationEventStatus`, `ExternalRefEntity`, `FiscalizedBy`); factory'lar `database/factories/Integrations`.
Jurnalga yozuvchilar (5-bosqich) tanaga token va secret qo'ymaydi; o'qishda baribir `AuditRedactor` ishlaydi.

| Endpoint | Ruxsat | Nima |
|---|---|---|
| `GET /admin/integrations/connections?status=&provider=&direction=&mode=&health=&store_id=&q=` | `integrations.view` | Sahifalangan (`page`, `per_page` 1–100, 30), eng kasal birinchi. `health`: `good` (80+), `attention` (50–79), `disconnected` (50 dan past), `unknown` (baho yo'q). Qatorda `health.verdict`, xato matnida telefon yashirilgan. `counts` (filtrsiz) va `stuck_orders` |
| `GET /admin/integrations/connections/{uuid}` | shu | Karta: qator + `credentials` (faqat kalit nomi, `configured`, `last4`), `settings` (maxfiy ko'rinadigan kalitlar `[redacted]`, telefonlar yashirilgan), oxirgi 10 hodisa, `menu_map` (`external_refs` turlari bo'yicha), `stuck_orders` |
| `POST /admin/integrations/connections/{uuid}/pause` | `integrations.manage` + step-up + `reason` | To'xtatish (`status_before_pause` eslab qolinadi). To'xtatilganni qayta to'xtatish hech narsa qilmaydi |
| `POST /admin/integrations/connections/{uuid}/resume` | shu | Pauzadan oldingi holatga qaytarish (noma'lum bo'lsa `configuring`) |
| `GET /admin/integrations/events?connection=&store_id=&status=&type=&direction=&from=&to=` | `integrations.view` | Jurnal, eng yangisi birinchi, kursor bilan (`cursor`, `per_page` 1–100, 25; `from`/`to` — Toshkent sanasi). Qatorlarda tana yo'q |
| `GET /admin/integrations/events/{uuid}` | shu | Hodisa: `idempotency_key`, `signature_valid`, `request_body`, `response_body` — `AuditRedactor` orqali (pii.view bo'lsa ham) |
| `GET /admin/integrations/stuck-orders` | shu | Qotib qolganlar: `order.push` (chiquvchi) 2 daqiqada (`stuck_after_minutes`) tasdiqlanmagan va buyurtma hali ochiq (yetkazilgan, bekor, rad etilgan, qaytarilgan emas); shu buyurtmaning keyingi urinishi bo'lsa hisoblanmaydi. Eng eskisi birinchi, 100 tagacha, `count` — hammasi |

Rollar: Operator (`integrations.view`) va «Faqat ko'rish» ko'radi, o'zgartira olmaydi; to'xtatish/yoqish
`integrations.manage` (standartda Superadmin). Yordam, Moliya va Savdo bo'limni ko'rmaydi.

«Bugun → Diqqat talab qiladi»: yangi manba `integration_order_stuck` («Tashqi POS qabul qilmagan buyurtmalar», `critical`,
`integrations.view`), havola `/integrations?tab=stuck`. 5-bosqichgacha bo'sh.

### Migratsiyalar va testlar (Integratsiyalar)

`2026_11_06_300300` (`integration_connections`), `2026_11_06_300400` (`integration_events`), `2026_11_06_300500`
(`external_refs`), `2026_11_06_300600` (`orders.fiscalized_by`); hammasi orqaga qaytadi. Testlar:
`tests/Feature/AdminIntegrations/*` (model va migratsiyalar, ulanishlar, jurnal, qotib qolganlar).

## Ko'rsatkichlar va Excel eksport (P4-31)

Boshqaruv markazining "Ko'rsatkichlar" sahifasi (`/metrics`) va ro'yxatlarning Excel eksporti. Hammasi `/api/v1/admin` ostida,
`routes/api_admin_metrics.php`. Oylar — **Toshkent oylari** (`Asia/Tashkent`); pul — o'nlik satr (`"831166.67"`), foiz — bir
kasrli satr (`"4.2"`).

### Ruxsatlar

| Endpoint | Ruxsat |
|---|---|
| `GET /admin/metrics`, `GET /admin/metrics/funnel` | `finance.view` **yoki** `stores.view` (faqat o'qish) |
| `GET /admin/exports/*` | `export` **va** ro'yxatning o'z ko'rish ruxsati (pastdagi jadval) |

### Ta'riflar (oddiy so'zlar bilan)

- **MRR** — oylik takrorlanuvchi daromad: hozir pul to'layotgan obunalarning oylik narxi yig'indisi. Obunaning narxi: tarifning
  oylik narxi (yillik obuna — yillik narx ÷ 12), shuningdek tarifga kirgan kassa va oshxona ekranlaridan ortiq har bir qurilma
  uchun qo'shimcha to'lov (qurilmalar shu paytda ulangan va bekor qilinmagan bo'lishi kerak). Eski (P1-23) tarif: oylik narx +
  kiritilgan terminallardan ortiq terminallar × terminal narxi. **Sinov davridagi** obuna hisobga olinmaydi; to'lovi
  kechikkan (`past_due`, `locked`) obuna hisobga olinadi — ovqatxona hali mijoz. Narxlar ro'yxat bo'yicha: 50% chegirma,
  "narx qulfi" va tarif oshirilgan oydagi proratsiya bir martalik hisob-faktura qatorlari, takrorlanuvchi daromad emas. O'tgan
  oylar uchun tarifning **hozirgi** narxi olinadi (narx o'zgarishi oylar bo'yicha saqlanmaydi).
- **ARR** — MRR × 12. **ARPA** — MRR ÷ pul to'layotgan ovqatxonalar soni.
- **MRR harakati** (har oy uchun, oy boshi va oxiridagi MRR ovqatxona bo'yicha solishtiriladi):
  **yangi** — oldin 0, endi bor (to'lay boshlagan, sinovi tugagan yoki qaytgan ovqatxona); **o'sish** — ikkalasida ham bor, oxirida
  ko'p (yuqori tarif, qo'shimcha qurilma); **kamayish** — ikkalasida ham bor, oxirida kam; **ketgan** — oldin bor, endi 0.
  `boshida + yangi + o'sish − kamayish − ketgan = oxirida` (tiyingacha).
- **Faol ovqatxonalar** — holati `active` bo'lganlar; o'tgan oylar uchun holatlar tarixidan (`store_status_logs`) tiklanadi.
- **Ketish (churn)** — oy boshida faol bo'lgan ovqatxonalardan nechtasi oy davomida (1) faol holatdan chiqdi (to'xtatildi, yopildi,
  o'chirildi) yoki (2) oy boshida pul to'lagan bo'lib, oy oxirida to'lamaydi (obunasi tugadi). Foiz = ketganlar ÷ oy boshidagi faollar
  (`rate`; oy boshida faol ovqatxona bo'lmasa `null`). `revenue_rate` — obunasi tugaganlarning MRR'i ÷ oy boshidagi MRR.
  Tugamagan oy (`partial: true`) uchun — o'tgan kunlar bo'yicha.
- **Konversiya (arizadan faolgacha)** — oxirgi N oyda tushgan arizalar: har bosqichga yetganlar soni (oldinga o'tish orada turgan
  bosqichlarni ham o'tadi), ularning hammasiga ulushi, har bosqichda **median necha kun** turgani (bosqichdan chiqqanlar uchun) va
  "ariza → faol" medianasi. Bosqich o'tishlari harakatlar jurnalidan (`admin.stores.applications.*`), birinchi bosqich — ariza vaqtidan
  o'qiladi; jurnali yo'q ariza joriy bosqichi va `stage_changed_at` bo'yicha hisoblanadi. `conversion` = faol ÷ barcha ariza.
- **GMV va komissiya** — Bugun yig'ma jadvalidan (`platform_daily_stats`, P4-13): faqat marketpleys kanallari (Telegram, mobil),
  Bugun hisoblaydigan buyurtmalar; buyurtmalar skanerlanmaydi. Jadvalda hisoblanmagan kun bo'lsa, oy `complete: false` va
  `missing_days` bilan keladi (eski kunlar uchun `php artisan admin:stats-backfill`).

### `GET /admin/metrics?months=12&refresh=1`

`months` 3–24 (standart 12; oxirgisi — tugamagan joriy oy). Javob (5 daqiqa keshlanadi, `generated_at`; `refresh=1` keshni tashlaydi):

- `cards`: `mrr`, `mrr_change` (`direction`, `amount`, `percent` — oy boshiga nisbatan), `arr`, `paying_stores`, `trialing_stores`,
  `arpa`, `active_stores`, `churn_rate` va `churn_month` (oxirgi **tugagan** oy), `gmv_month`, `commission_month`, `revenue_complete`;
- `series[]`: `month`, `partial`, `mrr` (oy oxirida), `paying_stores`, `trialing_stores`, `active_stores`;
- `movement[]`: `opening`, `new` / `expansion` / `contraction` / `churned` (`mrr`, `stores`), `net_new`, `closing`;
- `churn[]`: `active_at_start`, `active_at_end`, `churned`, `went_inactive`, `subscription_ended`, `rate`, `revenue_rate`;
- `revenue[]`: `orders`, `gmv`, `commission`, `subscription_revenue`, `missing_days`, `complete`.

### `GET /admin/metrics/funnel?months=12`

`applications`, `active`, `rejected`, `open`, `conversion`, `median_days_to_active` va `stages[]` (`stage`, `label`, `reached`, `share`,
`median_days`, `samples`).

### Excel eksport (`GET /admin/exports/...`)

Har bir endpoint ro'yxatning **o'sha filtrlarini** oladi (sahifalashsiz) va `.xlsx` fayl qaytaradi (`Content-Disposition`:
`rizqim-<ro'yxat>-YYYYMMDD-HHMM.xlsx`). `X-Export-Rows` — qatorlar soni, `X-Export-Truncated: 1` — ro'yxat chegarada kesilgan.

| Endpoint | Filtrlar (ro'yxatniki) | Ko'rish ruxsati |
|---|---|---|
| `/exports/orders` | `status`, `channel`, `region_id`, `store_id`, `sla`, `from`, `to`, `q` | `orders.view` |
| `/exports/stores` | `q`, `status`, `region_id`, `health`, `plan`, `debt`, `next_contact` | `stores.view` |
| `/exports/store-applications` | `stage`, `assignee_id`, `source`, `q` | `stores.view` |
| `/exports/finance/payouts` | `status`, `store_id` | `finance.view` |
| `/exports/finance/topups` | `status`, `provider`, `store_id`, `stuck` | `finance.view` |
| `/exports/finance/balances` | `filter`, `q` | `finance.view` |
| `/exports/pos/invoices` | `status`, `store_id`, `period`, `dunning`, `q` | `subscriptions.manage` yoki `finance.view` |
| `/exports/pos/devices` | `state`, `type`, `terminal`, `store_id`, `outdated`, `q` | `devices.view` |
| `/exports/audit-logs` | `actor_id`, `action`, `store_id`, `subject_type`, `subject_id`, `from`, `to`, `q` | `audit.view` |

(Oylik daromad hisoboti eksporti avvalgidek: `GET /admin/finance/reports/monthly/export`.)

Qoidalar (hammasi uchun bitta yordamchi, `App\Services\Admin\Export\XlsxExporter`):

- **Oqim bilan yoziladi** (ro'yxat 1000 qatordan sahifalab o'qiladi, xotira o'smaydi) va **eng ko'pi 50 000 qator**
  (`admin.export.max_rows`). Ro'yxat uzun bo'lsa fayl buni aytadi: ma'lumotlar tagida va "Ma'lumot" varag'ida
  "Ro'yxat 50 000 qatordan keyin kesildi: filtrni toraytiring".
- **Formula in'ektsiyasi yo'q:** har bir matn katagi satr katagi (`=HYPERLINK(...)` kabi matn formulaga aylanmaydi); summalar va sonlar —
  son (Excel yig'a oladi). Vaqt — Toshkent vaqti (`YYYY-MM-DD HH:MM:SS`).
- **Telefon raqamlari** `AdminPii` qoidasi bilan: `pii.view`siz — yashirin (`+998 90 *** ** 67`); `pii.view` bilan — to'liq, va butun fayl
  uchun **bitta** `admin.pii.revealed` yozuvi. Karta raqami eksportda **hamisha** yashirin.
- **Jurnalga yoziladi:** `admin.exports.<ro'yxat>` (`orders`, `stores`, `store_applications`, `finance_payouts`, `finance_topups`,
  `finance_balances`, `pos_invoices`, `pos_devices`, `audit_logs`) — kim, qaysi ro'yxat, filtrlar (qidiruvdagi telefon yashiriladi),
  qatorlar soni, `truncated`, `phones_shown`, IP.
- Bir xodim daqiqasiga 10 tagacha eksport boshlay oladi (`admin.export.per_minute`, 429).
- Fayl 2 varaqdan iborat: ma'lumot (sarlavhalar qalin) va "Ma'lumot" (ro'yxat nomi, vaqt, kim, qatorlar, filtrlar).

### Migratsiya va testlar

`2026_11_06_400100_add_metrics_indexes` — `store_status_logs (store_id, id)` va `audit_logs (model_type, model_id)` indekslari.
Testlar: `tests/Feature/AdminMetrics` (MRR, harakat, churn, GMV, konversiya, ruxsatlar; eksport: har ro'yxat, filtrlar, yashirish,
formula, kesish, jurnal, throttle). Veb: `components/metrics`, `components/ExportButton.tsx`, `e2e/metrics.spec.ts`.
