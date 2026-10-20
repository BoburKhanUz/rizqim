# Kirish, rollar va qurilmalar

Bu hujjat Rizqim backend'iga kim va qanday kirishini, xodimlar nima qila olishini va qaysi harakatlar jurnalga
yozilishini tushuntiradi (P1-12). Barcha endpointlar `/api/v1` ostida.

## Qisqacha

| Kim | Qanday kiradi | Token |
|---|---|---|
| Telegram Mini App | `POST /auth/telegram` (initData) | Bitta token, `SANCTUM_TOKEN_EXPIRATION` (30 kun). 401 bo'lsa initData bilan qayta kiradi |
| Mobil ilova (mijoz, hamkor, kuryer) | `POST /auth/telegram` yoki `POST /auth/phone/verify`, `device` bilan | Qisqa access token (15 daqiqa) + refresh token (30 kun) |
| POS/KDS terminal | `POST /devices/pair` (8 belgili kod, `ABCD-EFGH`) | Terminalning o'z tokeni (365 kun yoki o'chirilguncha) |
| Terminaldagi xodim | `POST /devices/{device}/staff-session` (PIN) | Qisqa xodim sessiyasi (12 soat), terminalga bog'langan |

## 1. Telegram va SMS kod bilan kirish

**Telegram.** `POST /auth/telegram` `{initData, bot_username, start_param?}`. Javob o'zgarmagan:
`{token, user, store, sms_verification_required}`. Mini App hech narsa o'zgartirmaydi.

**Qurilmani ro'yxatdan o'tkazish (ixtiyoriy).** So'rovga `device` obyekti qo'shilsa
(`{type, uuid?, name?, platform?, app_version?, push_token?}`, `type`: `customer_mobile`, `partner_mobile`,
`courier`, `web`), javobga qo'shimcha maydonlar keladi: `token_expires_at`, `refresh_token`,
`refresh_token_expires_at`, `device`. Bu holda `token` qisqa muddatli bo'ladi. Ilova `device.uuid` ni saqlaydi va
keyingi kirishda yuboradi: o'sha qurilma yangilanadi, yangisi yaratilmaydi. Boshqa odamning uuid'i yuborilsa, yangi
qurilma yaratiladi.

**SMS kod bilan kirish (Telegramsiz ilovalar uchun).**
1. `POST /auth/phone/send-code {phone}` — kod yuboriladi. Javob raqam ro'yxatdan o'tgan-o'tmaganidan qat'i nazar bir xil.
2. `POST /auth/phone/verify {phone, code, device?}` — Telegram javobi bilan bir xil shakl, qo'shimcha `is_new_user`.

Kod 5 daqiqa amal qiladi, 5 marta tekshirish mumkin, daqiqasiga 1 ta va soatiga 5 ta yuboriladi. Yangi kod so'ralsa,
oldingisi o'chmaydi: muddati tugaguncha oxirgi **ikkita** kod qabul qilinadi (har birining o'z 5 ta urinishi bor), biri
to'g'ri kelsa ikkalasi ham sarflanadi. Shunda begona odam qayta-qayta kod so'rab, egasi terayotgan kodni bekor qila
olmaydi. Bitta raqam uchun barcha kodlar bo'yicha jami sutkasiga 30 ta urinish
(`auth.phone_verification.max_attempts_per_day`), keyin "Bugun urinishlar soni tugadi" (422).

**Telefonni tasdiqlash (kirgan foydalanuvchi uchun).** `POST /phone/send-code` va `POST /phone/verify-code` avvalgidek
ishlaydi; tasdiqlangan raqam `users.phone_verified_at` bilan belgilanadi. Profilda kodsiz o'zgartirilgan raqam
tasdiqlanmagan hisoblanadi.

### Hisoblarni telefon bo'yicha birlashtirish

Bir mijoz Telegram va mobil ilovada bitta hisobga ega bo'lishi uchun **faqat SMS bilan tasdiqlangan** raqam ishlatiladi.
Kodsiz yozilgan raqam hech qachon hisoblarni bog'lamaydi: aks holda kimdir begona raqamni o'z profiliga yozib, o'sha
odamning mobil kirishini o'z hisobiga tushirishi mumkin edi.

- **Mobil ilovada SMS bilan kirish:** shu tasdiqlangan raqamli mijoz bitta bo'lsa, o'sha hisobga kiriladi; yo'q bo'lsa,
  yangi mijoz yaratiladi; ikki va undan ko'p bo'lsa — **409**, hech narsa o'zgarmaydi.
- **Mini App'da raqamni tasdiqlash:** raqam boshqa mijozga tegishli bo'lmasa, saqlanadi. Tegishli bo'lsa va joriy
  Telegram hisobi bo'sh bo'lsa (buyurtma, manzil, keshbek, shikoyat, baho, taklif qilingan do'st yo'q) va u boshqa
  hisobda Telegram bo'lmasa — Telegram o'sha hisobga ko'chiriladi (`account_linked: true`), eski tokenlar o'chiriladi,
  Mini App keyingi 401 da avtomatik qayta kiradi va birlashtirilgan hisobga tushadi. Boshqa hollarda — **409**
  (`data.reason = phone_linked_to_another_account`), hech qanday ma'lumot o'chirilmaydi. Bunday hisoblarni birlashtirish
  qo'llab-quvvatlash xizmati orqali qo'lda qilinadi.
- Xodim hisoblari (ega, menejer va boshqalar) bu qoidaga kirmaydi: ularda telefon faqat aloqa ma'lumoti.
- Eski foydalanuvchilarda `phone_verified_at` bo'sh: ular raqamini bir marta qayta tasdiqlaganda bog'lanadi.

## 2. Refresh token

- `POST /auth/refresh {refresh_token, device_uuid}` — eski refresh token sarflanadi, yangi juftlik qaytadi
  (`token`, `token_expires_at`, `refresh_token`, `refresh_token_expires_at`, `device`).
- Bazada faqat SHA-256 xeshi saqlanadi. Har bir refresh token bitta qurilmaga bog'langan.
- **Qayta ishlatishni aniqlash:** sarflangan refresh token yana kelsa (yoki boshqa qurilma uuid'i bilan kelsa), butun
  "oila" (shu kirishdan boshlangan barcha refresh va access tokenlar) bekor qilinadi, foydalanuvchi qaytadan kiradi.
  Javob 401, `data.reason`: `refresh_reused`, `refresh_invalid` yoki `refresh_expired`.
- Ilova bitta refresh so'rovini bir vaqtda faqat bir marta yuborishi kerak (single-flight), aks holda ikkinchi so'rov
  qayta ishlatish deb hisoblanadi.
- Shu qurilmada qaytadan kirilsa (yangi oila boshlansa), qurilmaning eski oilalari bekor qilinadi: eski refresh token va
  access token ishlamay qoladi.
- `POST /auth/logout` — joriy sessiya (qurilma bo'lsa, uning refresh tokenlari ham). `POST /auth/logout-all` — barcha
  qurilmalardagi barcha sessiyalar (Mini App va xodim PIN sessiyalari ham).

## 3. Qurilmalar

`devices` jadvali: `uuid`, `user_id` (shaxsiy ilova) yoki faqat `store_id`/`branch_id` (terminal), `type`
(`pos`, `kds`, `courier`, `partner_mobile`, `customer_mobile`, `web`), `name`, `platform`, `app_version`,
`push_token` (P1-22 uchun, API javoblarida ko'rinmaydi), `last_seen_at`, `revoked_at`.

| Endpoint | Kim | Nima qiladi |
|---|---|---|
| `GET /devices` | kirgan foydalanuvchi | o'z faol qurilmalari |
| `POST /devices` | kirgan foydalanuvchi | qurilmani ro'yxatga olish yoki yangilash (`uuid` bilan), push token |
| `DELETE /devices/{uuid}` | kirgan foydalanuvchi | o'z qurilmasini o'chirish |
| `GET /owner/devices` | `devices.view` | do'kon qurilmalari (`type`, `include_revoked`) |
| `PATCH /owner/devices/{uuid}` | `devices.manage` | nomini o'zgartirish |
| `POST /owner/devices/{uuid}/revoke` | `devices.manage` | o'chirish: undagi barcha token va sessiyalar darhol yopiladi |

Bitta foydalanuvchida ko'pi bilan 20 ta faol qurilma; ortig'i (eng eskisi) avtomatik o'chiriladi. Filialga biriktirilgan
menejer faqat o'z filialining qurilmalarini ko'radi. Boshqa do'kon qurilmasi 404 bo'ladi.

Xodimning shaxsiy ilovasini (`user_id` bor qurilma) faqat uning o'zi yoki undan yuqori rol ko'radi va boshqaradi
(ega > menejer > boshqa xodimlar): menejer eganing yoki boshqa menejerning telefonini ko'rmaydi, o'chira olmaydi (404).
Terminallar (`user_id` yo'q) uchun qoidalar o'zgarmagan. Panelda ham xuddi shunday.

## 4. POS/KDS terminalni ulash

1. Ega yoki menejer `POST /owner/devices/pairing-codes {type: pos|kds, branch_id?, name?}` yoki panelda
   "Qurilmalar → Terminal ulash" orqali 8 belgili kod oladi (`ABCD-EFGH` ko'rinishida; Crockford base32: raqamlar va
   I, L, O, U dan tashqari bosh harflar). Kod 10 daqiqa amal qiladi, bir marta ishlatiladi; bir
   do'konda bir vaqtda ko'pi bilan 5 ta faol kod; bir kishi daqiqasiga 5 ta, soatiga 30 ta kod yarata oladi. Filial
   menejeri faqat o'z filiali uchun kod yaratadi.
2. Terminal `POST /devices/pair {code, name?, platform?, app_version?}` yuboradi (kod chiziqcha bilan yoki usiz, katta-kichik
   harf farqi yo'q; O → 0, I/L → 1 deb o'qiladi) (token kerak emas) va
   `{token, device, store, branch}` oladi. Bu terminalning o'z tokeni (`terminal` huquqi bilan): u foydalanuvchi emas,
   oddiy API'ga (`auth:sanctum`) kira olmaydi, faqat terminal endpointlariga (`auth:device`).
3. Kod bazada faqat HMAC ko'rinishida (APP_KEY bilan) saqlanadi. Noto'g'ri, ishlatilgan yoki eskirgan kodga bir xil
   javob: 422 `pairing_invalid`. Urinishlar bir IP'dan daqiqasiga 10 ta, bir tarmoqdan (IPv4 /24, IPv6 /64) daqiqasiga
   30 ta. Butun platforma uchun umumiy bloklash yo'q (aks holda kimdir hamma terminallarning ulanishini to'xtatib
   qo'yishi mumkin edi): daqiqasiga 600 tadan ko'p urinish bo'lsa, log'ga ogohlantirish yoziladi
   (`auth.pairing.alert_attempts_per_minute`).

Terminal endpointlari (`Authorization: Bearer <terminal token>`):

| Endpoint | Nima qiladi |
|---|---|
| `GET /terminal` | terminal, do'kon va filial |
| `GET /terminal/staff` | PIN o'rnatilgan, shu terminalda ishlay oladigan xodimlar (PIN klaviaturasi uchun) |
| `POST /devices/{uuid}/staff-session` | xodim PIN bilan kiradi |
| `DELETE /devices/{uuid}/staff-session` | terminaldagi xodimni chiqarish (ekran qulfi, smena oxiri) |

Terminal tokeni `AUTH_DEVICE_TOKEN_TTL_DAYS` gacha yoki ega o'chirguncha amal qiladi. Global
`SANCTUM_TOKEN_EXPIRATION` unga ta'sir qilmaydi. Shuning uchun rejalashtirilgan tozalash `sanctum:prune-expired`
o'rniga `auth:prune-tokens` (u terminal tokenlarini o'chirmaydi). **`sanctum:prune-expired` ni qo'lda ishga
tushirmang** — u barcha terminallarni uzib qo'yadi.

## 5. Xodim PIN kodi va terminal sessiyasi

- Ega yoki menejer `PUT /owner/staff/{id}/pin {pin}` (yoki panelda "PIN o'rnatish") bilan 4–6 xonali PIN qo'yadi,
  `DELETE /owner/staff/{id}/pin` bilan o'chiradi. Ega do'kondagi hammaga (o'ziga ham) qo'ya oladi; menejer — o'z
  filialidagi o'zidan pastdagi xodimlarga (kassir, ofitsiant, oshpaz) va o'ziga. PIN faqat ega, menejer, kassir,
  ofitsiant va oshpaz uchun.
- Oddiy PIN'lar qabul qilinmaydi: bir xil raqamlar (0000), ketma-ket (1234, 4321, 7890), takrorlanuvchi (1212, 123123)
  va keng tarqalgan kodlar (2580 va boshqalar).
- PIN faqat bcrypt xeshi bilan saqlanadi. Birinchi PIN qo'yilganda xodimga do'kon ichida yagona 4 xonali **xodim kodi**
  (`staff_code`) beriladi: terminalda ismni tanlash o'rniga terilishi mumkin.
- `POST /devices/{uuid}/staff-session {user_id | staff_code, pin}` — terminal tokeni bilan. Javob:
  `{token, expires_at, user, permissions}`. Bu xodimning o'z tokeni (oddiy API'ga kira oladi, xodim huquqlari bilan),
  terminalga bog'langan va `AUTH_STAFF_SESSION_TTL` (12 soat) amal qiladi.
- **Xodim sessiyasi faqat buyurtma va kassa ishi uchun.** Token faqat `staff-session` huquqiga ega va quyidagi
  endpointlar uni 403 bilan rad etadi (`data.reason = staff_session_not_allowed`, `not-staff-session` middleware): moliya
  va pul yechish (`/owner/finance*`, `/owner/payouts`), sozlamalar (`/owner/settings*`), xodimlar (`/owner/employees*`),
  ruxsatlar jadvali (`/owner/permissions`), PIN va rol (`/owner/staff/*`), qurilmalar va ulash kodlari (`/devices*`,
  `/owner/devices*`), harakatlar jurnali (`/owner/audit-logs`), `POST /auth/logout-all`, profil va telefon
  (`POST /user/profile`, `/phone/send-code`, `/phone/verify-code`). Ega va menejer terminalga PIN bilan kirib buyurtmalar
  bilan ishlashi mumkin, lekin bu amallar uchun o'z ilovasi yoki panel orqali kiradi.
- Terminalda yangi xodim kirsa, oldingisining sessiyasi yopiladi. PIN o'zgarsa yoki o'chirilsa, terminal o'chirilsa —
  sessiyalar darhol yopiladi.
- Xodim boshqa filial terminaliga kira olmaydi (do'kon bo'yicha ishlaydigan xodim — istalgan filialga).
- **Bloklash:** bitta terminalda bitta xodim uchun `AUTH_PIN_MAX_ATTEMPTS` (5) noto'g'ri urinishdan keyin, yoki
  do'konning barcha terminallarida jami `AUTH_PIN_MAX_ATTEMPTS_PER_USER` (10) noto'g'ri urinishdan keyin (terminal
  almashtirish hisobni nolga tushirmaydi) `AUTH_PIN_LOCKOUT_SECONDS` (60 soniya) bloklanadi, keyingi har bir xatoda vaqt
  ikki baravar oshadi (eng ko'pi 1 soat). Hisob faqat to'g'ri PIN bilan nolga tushadi. Javob 429 va `Retry-After`.
  Bitta xodim uchun urinishlar navbat bilan (cache lock ostida) tekshiriladi: bir vaqtda yuborilgan so'rovlar ham
  bittadan hisoblanadi, chegaraga yetgach qolganlari PIN tekshirilmasdan 429 bilan rad etiladi. Qo'shimcha: terminal bo'yicha
  daqiqasiga 10 ta, bir IP'dan 60 ta urinish.
- Noto'g'ri PIN, mavjud bo'lmagan xodim, boshqa do'kon xodimi, PIN'i yo'q xodim — hammasiga bir xil javob va bir xil
  bcrypt ishi: terminal orqali kim borligini bilib bo'lmaydi.

## 6. Rollar va ruxsatlar

Rollar: `super_admin`, `owner` (ega), `manager` (menejer), `cashier` (kassir), `waiter` (ofitsiant), `cook`
(oshpaz), `courier` (kuryer), `customer` (mijoz). Kassir, ofitsiant va oshpaz — yangi rollar; ular do'konsiz bo'la
olmaydi va Filament paneliga kirmaydi (POS/KDS ilovalari uchun).

Standart jadval (`App\Services\Auth\RolePermissions`). Superadmin hamma narsaga ega. `orders.refund` hozircha faqat
superadmin uchun.

| Ruxsat | Ma'nosi | Ega | Menejer | Kassir | Ofitsiant | Oshpaz | Kuryer |
|---|---|:-:|:-:|:-:|:-:|:-:|:-:|
| `orders.view` | Buyurtmalarni ko'rish | ✓ | ✓ | ✓ | ✓ | ✓ |  |
| `orders.manage` | Buyurtma yaratish va holatini o'zgartirish | ✓ | ✓ | ✓ | ✓ |  |  |
| `orders.cancel` | Buyurtmani bekor qilish yoki rad etish | ✓ | ✓ |  |  |  |  |
| `orders.discount` | Chegirma berish | ✓ | ✓ |  |  |  |  |
| `orders.void_item` | Taomni chekdan o'chirish | ✓ | ✓ |  |  |  |  |
| `orders.assign_courier` | Kuryer biriktirish | ✓ | ✓ |  |  |  |  |
| `orders.refund` | Pulni qaytarish |  |  |  |  |  |  |
| `kitchen.manage` | Oshxona ekrani: "tayyor" belgilash | ✓ | ✓ |  |  | ✓ |  |
| `deliveries.manage` | Yetkazib berish (kuryer) | ✓ |  |  |  |  | ✓ |
| `tables.manage` | Stollarni boshqarish | ✓ | ✓ | ✓ | ✓ |  |  |
| `menu.view` | Menyuni ko'rish | ✓ | ✓ | ✓ | ✓ | ✓ |  |
| `menu.manage` | Menyu va narxlarni tahrirlash | ✓ |  |  |  |  |  |
| `stoplist.manage` | Stop-list (tugadi belgisi) | ✓ | ✓ |  |  |  |  |
| `staff.view` | Xodimlarni ko'rish | ✓ | ✓ |  |  |  |  |
| `staff.manage` | Xodimlar va rollarni boshqarish | ✓ |  |  |  |  |  |
| `staff.pin` | Xodim PIN kodini o'rnatish | ✓ | ✓ |  |  |  |  |
| `devices.view` | Qurilmalarni ko'rish | ✓ | ✓ |  |  |  |  |
| `devices.manage` | Qurilmalarni ulash va o'chirish | ✓ | ✓ |  |  |  |  |
| `reports.view` | Hisobotlar | ✓ | ✓ |  |  |  |  |
| `finance.view` | Moliyani ko'rish | ✓ |  |  |  |  |  |
| `finance.manage` | Pul yechish va to'ldirish | ✓ |  |  |  |  |  |
| `settings.manage` | Do'kon sozlamalari | ✓ |  |  |  |  |  |
| `audit.view` | Harakatlar jurnali | ✓ |  |  |  |  |  |
| `permissions.manage` | Ruxsatlarni o'zgartirish | ✓ |  |  |  |  |  |
| `pos.open_shift` | Smenani ochish | ✓ | ✓ | ✓ |  |  |  |
| `pos.close_shift` | Smenani yopish | ✓ | ✓ | ✓ |  |  |  |
| `pos.cash_in_out` | Kassaga pul kiritish va chiqarish | ✓ | ✓ |  |  |  |  |
| `pos.take_payment` | To'lov qabul qilish | ✓ | ✓ | ✓ |  |  |  |
| `pos.reprint_bill` | Schyotni qayta chiqarish | ✓ | ✓ |  |  |  |  |

**Do'kon bo'yicha o'zgartirish.** Ega `PUT /owner/permissions {role, permission, allowed: true|false|null}` yoki
panelda "Ruxsatlar" sahifasida menejer, kassir, ofitsiant, oshpaz va kuryer huquqlarini o'zgartira oladi (`null` —
standartga qaytarish). Ega huquqlari o'zgarmaydi. Quyidagilarni boshqa rolga berib bo'lmaydi: `orders.refund`,
`finance.manage`, `settings.manage`, `staff.manage`, `audit.view`, `permissions.manage`.
`GET /owner/permissions` — joriy jadval va standartlar. Bu endpointlar chaqiruvchining o'z do'koni uchun: do'konsiz
superadmin 403 (`data.reason = store_required`) oladi va do'kon ruxsatlarini panel orqali boshqaradi.

**Hisobotlar.** `GET /owner/analytics` ega va menejer uchun, `reports.view` bilan (do'kon o'zgartirishlari hisobga
olinadi): menejerdan `reports.view` olib qo'yilsa, 403.

**Filial bo'yicha cheklov.** Filialga biriktirilgan xodim (ega bundan mustasno) faqat o'z filialidagi buyurtma, qurilma
va xodimlar bilan ishlaydi. Filialsiz xodim butun do'konda ishlaydi.

**Kodda.** Huquq nomi Laravel Gate'ga ulangan: `$user->can('orders.cancel', $order)`, Blade `@can`, marshrutda
`->middleware('permission:devices.manage')`. Mavjud policy'lar ishlashda davom etadi; `OrderPolicy` va
`ProductPolicy` shu qatlam orqali ishlaydi (standart jadval bilan ega va menejer avvalgidek). Buyurtma API
controllerlari hozircha rolni ham tekshiradi (ega, menejer, kuryer): kassir va ofitsiant uchun buyurtma API'si POS
bosqichida ochiladi.

**Ilova uchun.** `GET /me/permissions` → `{role, store_id, branch_id, branch_scoped, permissions[], device_uuid}`.
Ilova faqat ishlaydigan tugmalarni ko'rsatadi; server baribir har bir amalni o'zi tekshiradi.

**Rolni o'zgartirish.** `PUT /owner/staff/{id}/role {role, branch_id?}` (faqat ega, `staff.manage`): menejer,
kassir, ofitsiant, oshpaz yoki kuryer. Ega o'z rolini va boshqa egani o'zgartira olmaydi. Panelda ham xuddi shunday:
"Xodimlar" ro'yxati `staff.view`, PIN tugmalari `staff.pin`, mavjud xodimning rolini o'zgartirish `staff.manage` bilan.

## 7. Harakatlar jurnali (audit log)

`audit_logs`: do'kon, filial, kim (foydalanuvchi va/yoki terminal), harakat, obyekt (tur va id), oldin/keyin (faqat
o'zgargan maydonlar), qo'shimcha ma'lumot (sabab va h.k.), IP, vaqt. IP faqat xodim yoki terminal harakatida
yoziladi (mijozning manzili saqlanmaydi) va faqat ega hamda superadminga ko'rsatiladi. Yoziladigan harakatlar:

| Harakat | Qachon |
|---|---|
| `order.cancelled`, `order.rejected` | buyurtma bekor qilinsa yoki rad etilsa (sabab bilan; mijoz va tizim ham) |
| `order.status_changed` | xodim buyurtma holatini o'zgartirsa |
| `order.refunded` | pul qaytarilsa |
| `product.price_changed` | variant narxi o'zgarsa (API, panel — hammasi) |
| `staff.role_changed`, `staff.branch_changed` | xodim roli yoki filiali o'zgarsa |
| `staff.pin_set`, `staff.pin_changed`, `staff.pin_reset` | PIN qo'yilsa, almashtirilsa, o'chirilsa |
| `staff.session_started` | xodim terminalga PIN bilan kirsa |
| `permissions.changed` | do'kon ruxsatlari o'zgarsa |
| `device.pairing_code_created`, `device.paired`, `device.renamed`, `device.revoked` | terminal va qurilmalar |
| `account.linked` | Telegram hisobi telefon bo'yicha boshqa hisobga birlashtirilsa |

Xodim PIN sessiyasi orqali qilingan har bir amalda terminal ham yoziladi. Jurnalga **hech qachon** PIN, parol, token,
kod, karta ma'lumoti yozilmaydi; telefon raqamlari niqoblanadi (`+99890*****67`), erkin matn ichida ham
(`App\Services\Audit\AuditRedactor`).

Ega jurnalni `GET /owner/audit-logs` (`audit.view`; filtrlar: `action` yoki `order.*`, `user_id`,
`device_uuid`, `branch_id`, `subject_type`, `subject_id`, `from`, `to`, `per_page`) va panelda "Harakatlar
jurnali" orqali ko'radi. Superadmin panelida butun platforma jurnali va barcha qurilmalar bor.

## 8. Filament

Do'kon panelida "Xodimlar va ruxsatlar" bo'limi:
- **Xodimlar** — yangi rollar bilan xodim qo'shish, rol va filial, PIN o'rnatish va o'chirish, "Mening PIN kodim".
- **Qurilmalar** — terminal ulash kodi, nomini o'zgartirish, o'chirish.
- **Ruxsatlar** — rollar jadvali (ega o'zgartiradi, menejer faqat ko'radi).
- **Harakatlar jurnali** — faqat ega.

Hammasi joriy do'kon (tenant) bilan cheklangan, filial menejeri — o'z filiali bilan.

## 9. Sozlamalar (.env)

| O'zgaruvchi | Standart | Ma'nosi |
|---|---|---|
| `SANCTUM_TOKEN_EXPIRATION` | 43200 | Mini App tokeni, daqiqa |
| `AUTH_ACCESS_TOKEN_TTL` | 15 | qurilma bilan kirgan ilovaning access tokeni, daqiqa |
| `AUTH_REFRESH_TOKEN_TTL_DAYS` | 30 | refresh token, kun |
| `AUTH_DEVICE_TOKEN_TTL_DAYS` | 365 | terminal tokeni, kun (0 — o'chirilguncha) |
| `AUTH_STAFF_SESSION_TTL` | 720 | xodim PIN sessiyasi, daqiqa |
| `AUTH_PAIRING_CODE_TTL` | 10 | ulash kodi, daqiqa |
| `AUTH_PIN_MAX_ATTEMPTS` | 5 | bloklashgacha noto'g'ri PIN soni |
| `AUTH_PIN_MAX_ATTEMPTS_PER_USER` | 10 | bitta xodim uchun barcha terminallarda jami noto'g'ri PIN soni |
| `AUTH_PIN_LOCKOUT_SECONDS` | 60 | birinchi bloklash, soniya (keyin ikki baravar) |
