# Kirish, rollar va qurilmalar

Bu hujjat Rizqim backend'iga kim va qanday kirishini, xodimlar nima qila olishini va qaysi harakatlar jurnalga
yozilishini tushuntiradi (P1-12). Barcha endpointlar `/api/v1` ostida.

## Qisqacha

| Kim | Qanday kiradi | Token |
|---|---|---|
| Telegram Mini App | `POST /auth/telegram` (initData) | Bitta token, `SANCTUM_TOKEN_EXPIRATION` (30 kun). 401 bo'lsa initData bilan qayta kiradi |
| Mobil ilova (mijoz, hamkor, kuryer) | `POST /auth/telegram` yoki `POST /auth/phone/verify`, `device` bilan | Qisqa access token (15 daqiqa) + refresh token (30 kun) |
| Hamkor veb-panel (`partner/web`) | `POST /auth/phone/verify`, `account = staff`, `device.type = web`; so'rovni brauzer emas, panelning Next serveri yuboradi | Access token faqat brauzer xotirasida; refresh token httpOnly cookie'da (Next route handler), `localStorage` yo'q. [partner/web/README.md](../partner/web/README.md) |
| POS/KDS terminal | `POST /devices/pair` (8 belgili kod, `ABCD-EFGH`) | Terminalning o'z tokeni (365 kun yoki o'chirilguncha) |
| Terminaldagi xodim | `POST /devices/{device}/staff-session` (PIN) | Qisqa xodim sessiyasi (12 soat), terminalga bog'langan |
| RIZQIM jamoasi (P4-11, Boshqaruv markazi) | `POST /admin/auth/login` (email + parol) → TOTP kod (2FA) | Alohida `admin` tokeni: 12 soat, 60 daqiqa harakatsizlikda tugaydi; faqat `/admin/*` da ishlaydi, boshqa tokenlar u yerda ishlamaydi. [API.md](API.md) "Admin API" |

## 1. Telegram va SMS kod bilan kirish

**Telegram.** `POST /auth/telegram` `{initData, bot_username, start_param?}`. Javob o'zgarmagan:
`{token, user, store, sms_verification_required}`. Mini App hech narsa o'zgartirmaydi.

**Superadmin va RIZQIM jamoasi Telegram yoki SMS bilan kirmaydi (P4-25).** Superadmin (`users.role = super_admin`) va
jamoa a'zolari (`platform`, `users.platform_role` belgilangan) faqat Boshqaruv markazi orqali kiradi (`/admin/auth/*`,
yuqoridagi jadval). `POST /auth/telegram` va `POST /auth/phone/verify` (mijoz ham, `account: staff` ham) ularga hech qachon
token, refresh token yoki yangi hisob bermaydi:
- Telegram id yoki raqam boshqa hisobda ham bo'lsa (mijoz yoki do'kon xodimi), o'sha hisobga kiriladi (Telegram id'da ega >
  menejer > ... > mijoz tartibi o'zgarmagan, superadmin hisobga olinmaydi);
- faqat superadmin/jamoa hisobida bo'lsa — **403** `data.reason = platform_account`, xabar "Bu hisob uchun Telegram yoki
  telefon orqali kirish yopiq. Boshqaruv markazidan kiring.". Hech narsa yaratilmaydi yoki o'zgarmaydi;
- ularning avvalgi oddiy tokenlari (Mini App, mobil, qurilma tokenlari) ishlamaydi (`sanctum` guard rad etadi, 401), refresh
  token yangi token bermaydi (`refresh_invalid`). Faqat `admin` tokeni (`AdminTokenGuard`) ishlaydi.
- Eski `/superadmin/dashboard|stores|locations` endpointlari va Mini App'dagi superadmin ekranlari olib tashlangan; do'kon
  yaratish va platforma ko'rsatkichlari — Boshqaruv markazida. P4-33 da backend'dagi eski veb-panellar ham olib
  tashlandi: jamoa faqat Boshqaruv markaziga, ovqatxona egalari va xodimlari hamkor paneliga kiradi.

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

**Xodim sifatida SMS bilan kirish (P1-36: hamkor paneli, xodim ilovalari).** `POST /auth/phone/verify` ga
`account: "staff"` va `device` (`type`: `web`, `partner_mobile` yoki `courier`) qo'shiladi. Shunda mijoz hisobi
qidirilmaydi va hech qanday hisob yaratilmaydi: shu raqam **kirish raqami** bo'lgan do'kon xodimi (ega, menejer, kassir,
ofitsiant, oshpaz, kuryer) kiradi. Javob oddiy kirish bilan bir xil (`user`, `store`, refresh token, `device`); sessiya
doim qurilmaga bog'langan (refresh token oilasi, ega "Qurilmalar" da ko'radi va o'chira oladi), xodim PIN sessiyasi
emas (`not-staff-session` yo'llari ochiq, faqat roldagi ruxsatlar bilan). Har kirish jurnalga yoziladi (`staff.signed_in`).
Kod cheklovlari (5 urinish, sutkasiga 30 ta, kod bir martalik) xuddi yuqoridagidek.

- Kirish raqami — `users.phone_verified_at` belgilangan raqam: xodim uni o'zi SMS kod bilan tasdiqlagan (Mini App yoki
  ilovada `POST /phone/verify-code`), yoki hisob ustidan vakolati borlar yozgan: ovqatxona yaratilganda egasining
  arizadagi raqami (`StoreProvisioner`), Boshqaruv markazidagi ovqatxona kartasi → «Kirish raqami» (`PUT /admin/stores/{id}/owner-login-phone`,
  `admin.stores.impersonate`), ega yoki menejer hamkor panelining Xodimlar sahifasida (`POST/PATCH /owner/staff`, menejer —
  o'zidan pastdagilarga). Bir do'konda bitta raqam bitta xodimga. Profilda kodsiz o'zgartirilgan raqam kirish raqami
  bo'lmaydi.
- **Raqam qanday tasdiqlangani saqlanadi (P1-34)** — `users.phone_verified_via`: `sms` (egasi kodni o'zi kiritgan) yoki
  `vouched` (ega, menejer yoki superadmin yozgan). Ustunning o'zi API javoblarida ko'rinmaydi; P2-30 dan xodimlar ro'yxati
  (`GET /owner/staff`) uni `login_phone_proof` (`sms` | `vouched` | kirish raqami bo'lmasa `null`) sifatida beradi: forma
  `sms` raqamni oldindan qulflaydi, rad javobini kutmaydi. Migratsiyadan oldin tasdiqlangan raqamlar
  `sms` deb belgilanadi (ega yozgan raqam shu reliz bilan paydo bo'ladi). Xodimlar formasida:
  - raqam faqat forma uni haqiqatan **o'zgartirganda** kirish raqami bo'ladi: boshqa maydonni (ism, filial) saqlash xodim
    profilida o'zi yozgan raqamni tasdiqlamaydi;
  - xodimning o'zi SMS bilan tasdiqlagan raqamini formada almashtirib ham, o'chirib ham bo'lmaydi ("Bu raqamni xodimning
    o'zi SMS kod bilan tasdiqlagan..." xatosi). Raqam almashsa, xodim yangisini ilovada SMS kod bilan tasdiqlaydi;
    ishdan ketgan xodim o'chiriladi yoki roli o'zgartiriladi;
  - menejer o'zidan pastdagi xodimning (ega yoki menejer yozgan, `vouched`) raqamini baribir o'zgartira oladi — bu uning
    vakolati. Shuning uchun har bir o'zgarish jurnalga yoziladi (pastda) va ega uni ko'radi.
- Kirish raqami qo'yilsa, o'zgarsa yoki o'chirilsa (xodimlar formasi, superadmin "SMS bilan kirishni yoqish", do'kon
  yaratish ustasi, superadmin foydalanuvchini tahrirlaganda raqam yoki rol o'zgarib kirish
  raqami yo'qolsa) — jurnalga `staff.login_phone_set` / `staff.login_phone_changed` / `staff.login_phone_cleared` yoziladi
  (raqam niqoblangan, `via` va `previous_via` bilan). O'zgarsa yoki o'chirilsa, xodimning **ilovadagi barcha sessiyalari**
  (refresh token oilalari va ularning access tokenlari, barcha qurilmalarda; push tokenlar ham tozalanadi) darhol
  yopiladi: eski raqam bilan kirgan kishi qaytadan kira olmaydi. Mini App tokeni (Telegram) va terminal PIN sessiyalari
  qoladi: ular raqamga emas, Telegram hisobiga va PIN'ga bog'langan.
- **Boshqa do'kon va tanlash ro'yxati.** Raqamni har bir do'kon o'z xodimi uchun yozadi, shuning uchun istalgan do'kon
  egasi begona raqamni o'z xodimiga yozib, raqam egasining tanlash ro'yxatiga o'z do'konini qo'shishi mumkin (u hisobga
  faqat raqam egasi kirganda va o'zi tanlaganda kiriladi). Ro'yxatda har bir hisobning **do'kon nomi** (sarlavha), roli
  va filiali ko'rsatiladi, notanish do'kon tanlanmasin. Qo'shimcha qoida: raqam egasi uni **boshqa do'kondagi xodim
  hisobida SMS bilan tasdiqlagan** bo'lsa, u ishlamaydigan do'kon bu raqamni xodimlar formasida yoza olmaydi (xato
  xabari). Bunday xodim raqamsiz qo'shiladi va taklif havolasi orqali kirib raqamini o'zi tasdiqlaydi, yoki superadmin
  "SMS bilan kirishni yoqish" bilan yoqadi. Faqat `vouched` raqam uchun bu cheklov yo'q (egasi uni hali isbotlamagan).
- Raqam hech qaysi xodimniki bo'lmasa (faqat mijozniki yoki hech kimniki) — **403** `data.reason = staff_account_not_found`,
  hech narsa o'zgarmaydi. Do'konsiz hisoblar bu yo'l bilan kirmaydi. Raqam faqat superadmin/jamoa hisobida bo'lsa —
  **403** `data.reason = platform_account` (yuqorida, P4-25).
- Raqam bir nechta xodim hisobida bo'lsa (bir odam ikki do'konda) — **409** `data.reason = account_selection_required`,
  `data.accounts` (`user_id`, `name`, `role`, `role_label`, `store {id, name}`, `branch`) va `data.selection_token`
  (5 daqiqa, bir martalik — bir vaqtda yuborilgan ikki so'rovdan ham faqat bittasi o'tadi; serverda faqat xeshi saqlanadi). Kod shu bilan sarflangan. Ilova hisobni tanlab
  `POST /auth/phone/select-account {selection_token, user_id, device}` yuboradi. Noto'g'ri, eskirgan, ishlatilgan token yoki
  ro'yxatda bo'lmagan hisob — **422** `selection_invalid` (token baribir sarflanadi; qaytadan kod so'raladi).
- Hamkor paneli tokenni brauzerga bermaydi: u httpOnly cookie'da (`partner/web/README.md`).

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
- Xodim hisoblari (ega, menejer va boshqalar) bu qoidaga kirmaydi: ular mijoz hisobi bilan birlashmaydi. Xodim o'z
  hisobiga "Xodim sifatida SMS bilan kirish" orqali kiradi (yuqorida).
- Eski foydalanuvchilarda `phone_verified_at` bo'sh: ular raqamini bir marta qayta tasdiqlaganda bog'lanadi.
- Xodim uchun ega yoki superadmin yozgan (`vouched`) raqam hech qachon mijoz hisobiga kiritmaydi va bog'lamaydi: u faqat
  `phone_verified_via = sms` raqamlarni hisobga oladi. Hisob do'kon rollaridan chiqsa (masalan, superadmin mijozga
  aylantirsa), `vouched` tasdiq o'chiriladi (SMS bilan tasdiqlangani qoladi).

## 2. Refresh token

- `POST /auth/refresh {refresh_token, device_uuid}` — eski refresh token sarflanadi, yangi juftlik qaytadi
  (`token`, `token_expires_at`, `refresh_token`, `refresh_token_expires_at`, `device`).
- Bazada faqat SHA-256 xeshi saqlanadi. Har bir refresh token bitta qurilmaga bog'langan.
- **Qayta ishlatishni aniqlash:** sarflangan refresh token yana kelsa (yoki boshqa qurilma uuid'i bilan kelsa), butun
  "oila" (shu kirishdan boshlangan barcha refresh va access tokenlar) bekor qilinadi, foydalanuvchi qaytadan kiradi.
  Javob 401, `data.reason`: `refresh_reused`, `refresh_invalid` yoki `refresh_expired`.
- Ilova bitta refresh so'rovini bir vaqtda faqat bir marta yuborishi kerak (single-flight), aks holda ikkinchi so'rov
  qayta ishlatish deb hisoblanadi.
- **Javob yo'qolsa (P1-34).** Server tokenni almashtirgan, lekin javob ilovaga yetmagan bo'lsa (tarmoq uzilishi), ilova
  o'sha sarflangan token bilan qayta so'raydi. Agar u 30 soniya ichida (`auth.tokens.refresh_grace_seconds`, ko'pi bilan
  60) kelsa, o'sha qurilmadan bo'lsa va yo'qolgan juftlik hali ishlatilmagan bo'lsa — oila bekor qilinmaydi: yo'qolgan
  juftlik o'chiriladi va yangisi beriladi. Bu bitta token uchun **bir marta**. Kechroq kelsa, yangi juftlik allaqachon
  ishlatilgan bo'lsa yoki boshqa qurilmadan kelsa — oldingidek qayta ishlatish (`refresh_reused`), butun oila yopiladi.
  Agar bu imkonni tokenni o'g'irlagan kishi ishlatsa, haqiqiy ilova keyingi refresh'da yopilgan tokenni yuboradi va oila
  baribir yopiladi.
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
| `PUT /devices/{uuid}/push-token`, `DELETE /devices/{uuid}/push-token` | kirgan foydalanuvchi | o'z qurilmasining push tokenini qo'yish yoki olib tashlash (P1-22, `docs/PUSH.md`); chiqishda server o'zi tozalaydi |
| `GET/PUT /me/notification-preferences` | kirgan foydalanuvchi | xabar turlari va kanallari (Telegram, push) sozlamalari (`docs/PUSH.md`, 5-bo'lim) |
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
   "Sozlamalar → Qurilmalar → Ulash kodini yaratish" orqali 8 belgili kod oladi (`ABCD-EFGH` ko'rinishida; Crockford base32: raqamlar va
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
| `GET /terminal` | terminal, do'kon va filial; P2-30: `branches` — terminal real-time kanaliga qo'shila oladigan filiallar (filialga ulangan — o'z filiali; filialsiz — do'konning hamma filiallari, KDS har biriga obuna bo'ladi) |
| `GET /terminal/staff` | PIN o'rnatilgan, shu terminalda ishlay oladigan xodimlar (PIN klaviaturasi uchun); P2-30: `?role=cook,manager` — faqat shu rollar (noma'lum rol — 422) |
| `POST /devices/{uuid}/staff-session` | xodim PIN bilan kiradi |
| `DELETE /devices/{uuid}/staff-session` | terminaldagi xodimni chiqarish (ekran qulfi, smena oxiri) |
| `DELETE /terminal` (P2-33) | terminal o'zini uzadi (KDS ulanishni unutishdan oldin): egasining `POST /owner/devices/{uuid}/revoke` bilan bir xil — qurilma bekor qilinadi, uning tokeni va undagi barcha PIN sessiyalari darhol ishlamaydi, jurnalda `device.revoked` (`self_revoked: true`, terminal bilan). Daqiqasiga 6 ta. Qayta ulash — yangi kod bilan |

Terminal tokeni bilan real-time obunasi ham imzolanadi (P2-11): `POST /api/broadcasting/auth` uni qabul qiladi, lekin
terminal faqat o'z filialining `private-pos-branch.{branchId}` kanaliga qo'shiladi (filialsiz ulangan terminal — do'konning
istalgan filiali). Buyurtmalarni o'qish va o'zgartirish — xodim PIN sessiyasi bilan ([API.md](API.md), 9.1-bo'lim).

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
  (`POST /user/profile`, `/phone/send-code`, `/phone/verify-code`); P2-12 dan: filiallarni o'zgartirish
  (`POST/PATCH/DELETE /owner/branches*`), hududlar (`/owner/delivery-zones*`), xodimlar (`/owner/staff`), moliya tarixi
  (`/owner/finance/ledger`, `/owner/finance/wallet`, `GET /owner/payouts`, `/owner/payments`). Ega va menejer terminalga PIN bilan kirib buyurtmalar
  bilan ishlashi mumkin, lekin bu amallar uchun o'z ilovasi yoki panel orqali kiradi.
- Terminalda yangi xodim kirsa, oldingisining sessiyasi yopiladi. PIN o'zgarsa yoki o'chirilsa, terminal o'chirilsa —
  sessiyalar darhol yopiladi.
- Xodim boshqa filial terminaliga kira olmaydi (do'kon bo'yicha ishlaydigan xodim — istalgan filialga).
- **P2-33: PIN sessiyasi hamma joyda terminal filiali bilan ishlaydi.** Filialga ulangan terminaldagi sessiya (ega va
  filialsiz xodim ham) faqat shu filial bilan: `/staff/*` dan tashqari eski `POST /orders/{uuid}/status`,
  `/orders/{uuid}/assign-courier`, `GET /orders`, `GET /couriers`, hisobotlar va CSV (`/owner/reports/*`), `GET
  /owner/analytics`, `POST /reports/export`, filiallar ro'yxati va menyu ham (`PermissionService::actingBranchId`).
  Boshqa filial buyurtmasi — 404, boshqa filial filtri — 403. Terminali topilmaydigan sessiya (qurilma yo'q, terminal
  emas; bekor qilingan terminal tokeni kirishda 401) hech narsa qila olmaydi: ruxsatli marshrutlar 403, ro'yxatlar bo'sh
  (yopiq holatda ishdan chiqadi, butun do'konga ochilmaydi).
- **Bloklash:** bitta terminalda bitta xodim uchun `AUTH_PIN_MAX_ATTEMPTS` (5) noto'g'ri urinishdan keyin, yoki
  do'konning barcha terminallarida jami `AUTH_PIN_MAX_ATTEMPTS_PER_USER` (10) noto'g'ri urinishdan keyin (terminal
  almashtirish hisobni nolga tushirmaydi) `AUTH_PIN_LOCKOUT_SECONDS` (60 soniya) bloklanadi, keyingi har bir xatoda vaqt
  ikki baravar oshadi (eng ko'pi 1 soat). Hisob faqat to'g'ri PIN bilan nolga tushadi. Javob 429 va `Retry-After`.
  Bitta xodim uchun urinishlar navbat bilan (cache lock ostida) tekshiriladi: bir vaqtda yuborilgan so'rovlar ham
  bittadan hisoblanadi, chegaraga yetgach qolganlari PIN tekshirilmasdan 429 bilan rad etiladi. Qo'shimcha: terminal bo'yicha
  daqiqasiga 10 ta, bir IP'dan 60 ta urinish.
- Noto'g'ri PIN, mavjud bo'lmagan xodim, boshqa do'kon xodimi, PIN'i yo'q xodim, RIZQIM bloklagan xodim — hammasiga bir
  xil javob va bir xil bcrypt ishi: terminal orqali kim borligini bilib bo'lmaydi. Bloklangan xodim (ega, menejer ham)
  terminal ro'yxatida yo'q, PIN'i kirishga ham, menejer tasdig'iga ham yaramaydi; bloklashda PIN sessiyalari yopiladi va
  hali ishlatilmagan tasdiqlari bekor bo'ladi (review W3).

## 6. Rollar va ruxsatlar

Rollar: `super_admin`, `owner` (ega), `manager` (menejer), `cashier` (kassir), `waiter` (ofitsiant), `cook`
(oshpaz), `courier` (kuryer), `customer` (mijoz). Kassir, ofitsiant va oshpaz — yangi rollar; ular do'konsiz bo'la
olmaydi (POS/KDS ilovalari uchun).

Standart jadval (`App\Services\Auth\RolePermissions`). Superadmin hamma narsaga ega. `orders.refund` hozircha faqat
superadmin uchun.

| Ruxsat | Ma'nosi | Ega | Menejer | Kassir | Ofitsiant | Oshpaz | Kuryer |
|---|---|:-:|:-:|:-:|:-:|:-:|:-:|
| `orders.view` | Buyurtmalarni ko'rish (P2-11: `GET /staff/orders*`, `pos-branch` kanali, o'z filiali) | ✓ | ✓ | ✓ | ✓ | ✓ |  |
| `orders.manage` | Buyurtma yaratish va holatini o'zgartirish | ✓ | ✓ | ✓ | ✓ |  |  |
| `orders.cancel` | Buyurtmani bekor qilish yoki rad etish | ✓ | ✓ |  |  |  |  |
| `orders.discount` | Chegirma berish (har qanday miqdorda). P3-37: kassir (`pos.take_payment`) bu ruxsatsiz ham filial sozlamasidagi `cashier_discount_max_percent` gacha (standart 10%) o'zi beradi, undan ko'pini va schyot chiqqandan keyingisini — menejer PIN tasdig'i bilan | ✓ | ✓ |  |  |  |  |
| `orders.void_item` | Taomni chekdan o'chirish | ✓ | ✓ |  |  |  |  |
| `orders.assign_courier` | Kuryer biriktirish (`GET /couriers`, `POST /orders/{uuid}/assign-courier`) va biriktirilgan kuryer olib ketganini belgilash (`on_the_way`, P2-30) | ✓ | ✓ |  |  |  |  |
| `orders.refund` | Pulni qaytarish |  |  |  |  |  |  |
| `kitchen.manage` | Oshxona ekrani: taom qatorini "tayyor" deb belgilash va qaytarish, buyurtmani `accepted → preparing → ready` (P2-11); P2-30: tayyor bo'lgan buyurtmadan taomni qaytarish (`ready → preparing`, faqat `.../recall` orqali, kuryer biriktirilmaguncha) | ✓ | ✓ |  |  | ✓ |  |
| `deliveries.manage` | Yetkazib berish (kuryer) | ✓ |  |  |  |  | ✓ |
| `tables.manage` | Stollarni boshqarish: POS'da stol tanlash, ko'chirish, birlashtirish; zal xaritasini (zal va stollarni yaratish, o'zgartirish, o'chirish) faqat ega va menejer o'zgartiradi — `tables.manage` + rol (P3 integratsiya tuzatishi) | ✓ | ✓ | ✓ | ✓ |  |  |
| `menu.view` | Menyuni ko'rish (P2-30: `GET /owner/products`, `GET /owner/categories` ham) | ✓ | ✓ | ✓ | ✓ | ✓ |  |
| `menu.manage` | Menyu va narxlarni tahrirlash (P2-30: `/owner/products*` ham) | ✓ |  |  |  |  |  |
| `stoplist.manage` | Stop-list (tugadi belgisi); P2-30: ro'yxatda kim qo'shgani (`created_by_name`) | ✓ | ✓ |  |  |  |  |
| `batches.manage` | Qozon va partiya qoldig'i: ochish, to'ldirish, yopish (P1-21, docs/MENU.md) | ✓ | ✓ |  |  | ✓ |  |
| `staff.view` | Xodimlarni ko'rish | ✓ | ✓ |  |  |  |  |
| `staff.manage` | Xodimlar va rollarni boshqarish | ✓ |  |  |  |  |  |
| `staff.pin` | Xodim PIN kodini o'rnatish | ✓ | ✓ |  |  |  |  |
| `devices.view` | Qurilmalarni ko'rish | ✓ | ✓ |  |  |  |  |
| `devices.manage` | Qurilmalarni ulash va o'chirish | ✓ | ✓ |  |  |  |  |
| `reports.view` | Hisobotlar | ✓ | ✓ |  |  |  |  |
| `finance.view` | Moliyani ko'rish (P2-30: `GET /owner/finance` ham) | ✓ |  |  |  |  |  |
| `finance.manage` | Pul yechish va to'ldirish | ✓ |  |  |  |  |  |
| `settings.manage` | Do'kon sozlamalari | ✓ |  |  |  |  |  |
| `zones.manage` | Yetkazib berish hududlari va narxlari (docs/ZONES.md) | ✓ |  |  |  |  |  |
| `branches.manage` | Filiallar: manzil, joylashuv, ish vaqti, ochiq/yopiq (P2-12; menejer — faqat o'z filiali) | ✓ | ✓ |  |  |  |  |
| `audit.view` | Harakatlar jurnali | ✓ |  |  |  |  |  |
| `permissions.manage` | Ruxsatlarni o'zgartirish | ✓ |  |  |  |  |  |
| `pos.open_shift` | Smenani ochish | ✓ | ✓ | ✓ |  |  |  |
| `pos.close_shift` | Smenani yopish | ✓ | ✓ | ✓ |  |  |  |
| `pos.cash_in_out` | Kassaga pul kiritish va chiqarish (P3-37: kassir ham, menejersiz; chiqim `cash_out_amount` dan oshsa egaga xabar) | ✓ | ✓ | ✓ |  |  |  |
| `pos.take_payment` | To'lov qabul qilish | ✓ | ✓ | ✓ |  |  |  |
| `pos.reprint_bill` | Schyotni qayta chiqarish ("NUSXA"; P3-37: kassir ham, menejersiz) | ✓ | ✓ | ✓ |  |  |  |
| `bookings.manage` | Bron daftari: taqvim, bron, oldindan buyurtma, avans olish, "Keldi" (P3-17: `/pos/bookings*`; avansni qaytarish — `pos.refund`, ushlab qolish — `orders.cancel` yoki menejer PIN tasdig'i) | ✓ | ✓ | ✓ |  |  |  |
| `customer_accounts.manage` | Nasiya hisoblari: ochish, limit, bloklash, qarzni tuzatish (P3-17); ko'rish va qarz to'lovini qabul qilish `pos.take_payment` bilan ham | ✓ | ✓ |  |  |  |  |
| `pos.refund` | Smena ichida pulni qaytarish (P3-18: `POST /pos/refunds` — shu smenada olingan naqd, karta yoki QR to'lov, qaytarish cheki bilan); kassir menejer PIN tasdig'i bilan | ✓ | ✓ |  |  |  |  |

**Do'kon bo'yicha o'zgartirish.** Ega `PUT /owner/permissions {role, permission, allowed: true|false|null}` yoki
panelda "Ruxsatlar" sahifasida menejer, kassir, ofitsiant, oshpaz va kuryer huquqlarini o'zgartira oladi (`null` —
standartga qaytarish). Ega huquqlari o'zgarmaydi. Quyidagilarni boshqa rolga berib bo'lmaydi: `orders.refund`,
`finance.manage`, `settings.manage`, `zones.manage`, `staff.manage`, `audit.view`, `permissions.manage`.
`GET /owner/permissions` — joriy jadval va standartlar (javob shakli: [API.md](API.md), "Kassadagi chegirma va kassir
huquqlari (P3-37)"). P3-37 (egasining 24.10.2026 qarori): kassirning standart huquqlariga `pos.reprint_bill` va
`pos.cash_in_out` qo'shildi; qaytarish (`pos.refund`) va bekor qilish (`orders.cancel`) menejer PIN bilan qoladi. Bu endpointlar chaqiruvchining o'z do'koni uchun: do'konsiz
superadmin 403 (`data.reason = store_required`) oladi: do'kon ruxsatlarini egasi hamkor panelida boshqaradi.

**Hisobotlar.** `GET /owner/analytics` ega va menejer uchun, `reports.view` bilan (do'kon o'zgartirishlari hisobga
olinadi): menejerdan `reports.view` olib qo'yilsa, 403. Web panel hisobotlari (`/owner/reports/*`, P2-12) ham faqat
`reports.view` bilan; ega uni kassirga ham berishi mumkin (filialga biriktirilgan bo'lsa — faqat o'z filiali).

**Boshqaruv (P2-12, web panel).** Filiallar: ro'yxat — buyurtma, menyu, xodim yoki hisobot ruxsati bor xodim (filialga
biriktirilgan — o'z filiali); yaratish va o'chirish — `settings.manage` (ega); tahrirlash — `branches.manage` (ega,
menejer — o'z filiali). Xodimlar: `GET/POST /owner/staff`, `PATCH /owner/staff/{id}` —
`staff.view` va `StaffAccountService` qoidasi: ega hamma xodimni, menejer o'z filialidagi o'zidan past rollarni
(kassir, ofitsiant, oshpaz, kuryer) qo'shadi va tahrirlaydi; formadagi telefon kirish raqami bo'ladi (yuqorida, 1-bo'lim).
P2-33: ro'yxatni filialga biriktirilgan har kim (faqat menejer emas) o'z filiali va filialsiz xodimlar bilan ko'radi.
Ega `staff.view` ni kassir, ofitsiant, oshpaz yoki kuryerga bersa, ular menejeri ko'radigan ro'yxatni (menejerdan past
rollar) ko'radi, lekin rol bera olmaydi, qo'sha va tahrirlay olmaydi (`can_edit: false`). Rol berish faqat ega,
superadmin va menejerda (menejer — o'zidan past rollar).
Filialni o'chirish (P2-33): filialga xodim, bekor qilinmagan qurilma (terminal) yoki yetkazib berish hududi bog'langan
bo'lsa, rad etiladi (422 `branch_in_use`, `data`: `staff`, `devices`, `zones` soni) (`BranchService::referencesRefusal`).
Aks holda ularning `branch_id` si bo'shab, ular butun do'konga tegishli bo'lib qolardi (menejer hamma filialni ko'rardi).
Moliya tarixi — `finance.view`. Hududlar — `zones.manage`. To'liq xarita: [API.md](API.md), 12-bo'lim.

**Filial bo'yicha cheklov.** Filialga biriktirilgan xodim (ega bundan mustasno) faqat o'z filialidagi buyurtma, qurilma
va xodimlar bilan ishlaydi. Filialsiz xodim butun do'konda ishlaydi. Filialga ulangan terminaldagi PIN sessiyasi faqat
shu terminal filiali bilan ishlaydi (filialsiz xodim va ega ham): P2-11 da xodim buyurtmalari va oshxona ekranida,
P2-33 dan hamma endpointlarda (5-bo'lim).

**Kodda.** Huquq nomi Laravel Gate'ga ulangan: `$user->can('orders.cancel', $order)`, Blade `@can`, marshrutda
`->middleware('permission:devices.manage')`. Mavjud policy'lar ishlashda davom etadi; `OrderPolicy` va
`ProductPolicy` shu qatlam orqali ishlaydi (standart jadval bilan ega va menejer avvalgidek). Eski buyurtma API'si
(`/orders/*`) rolni ham tekshiradi (ega, menejer, kuryer) va o'zgarmagan; faqat kuryerlar (`GET /couriers`,
`POST /orders/{uuid}/assign-courier`) P2-30 dan `orders.assign_courier` ruxsati bilan (filialga biriktirilgan xodim —
o'z filiali va filialsiz kuryerlar). Menyu (`/owner/products*`, `GET /owner/categories`) — `menu.view`/`menu.manage`,
`GET /owner/finance` — `finance.view` (terminal PIN sessiyasidan emas); pul yechish va to'ldirish — avvalgidek ega. Kassir, ofitsiant va oshpaz (ega va menejer
ham) buyurtmalar bilan `/staff/orders*` va `/staff/kitchen/tickets` orqali ishlaydi (P2-11, [API.md](API.md) 9.1-bo'lim):
u yerda faqat ruxsatlar tekshiriladi. `OrderPolicy::changeStatus` ga oshxona yo'li qo'shilgan: `kitchen.manage` bilan
`accepted → preparing` va `preparing → ready` (oshpazda `orders.manage` yo'q). Yangi ruxsat qo'shilmagan.
P2-33: **yetkazish buyurtmasini `delivered` qilish** — faqat biriktirilgan kuryer (yoki uning o'rnidagi xodim) va
`orders.assign_courier` egasi (ega, menejer: kuryer o'rniga topshiradi). Kassir va ofitsiant (`orders.manage`) faqat
olib ketish va zal buyurtmasini topshiradi; yetkazish buyurtmasida 403.

**Ilova uchun.** `GET /me/permissions` → `{role, store_id, branch_id, branch_scoped, permissions[], device_uuid, timezone}`
(`timezone` — do'kon vaqt mintaqasi, `orders.view` yoki `reports.view` bor xodimga; boshqalarga `null`; P2-30).
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
| `pos.approval_granted`, `pos.approval_used` | P3-18: menejer terminalda PIN bilan bir martalik tasdiq bersa (kim tasdiqladi, kim so'radi, qaysi ruxsat) va tasdiq ishlatilsa (`POST /pos/approvals`, 60 soniya, bitta ruxsat va bitta terminal; PIN bloklash qoidasi kirish bilan bir xil) |
| `pos.bills_created`, `pos.bill_printed`, `pos.bill_reprinted`, `pos.payment`, `pos.order_closed`, `pos.refund`, `pos.shift_opened`, `pos.cash_movement`, `pos.shift_closed` | P3-18: schyot tuzish/bo'lish, chiqarish va nusxa, to'lov, buyurtmani yopish, qaytarish, smena ochish, kassaga kirim/chiqim, Z (majburan yopish sababi bilan) |
| `staff.signed_in` | xodim hamkor paneli yoki xodim ilovasiga SMS kod bilan kirsa (`account = staff`; qurilma bilan) |
| `staff.login_phone_set`, `staff.login_phone_changed`, `staff.login_phone_cleared` | xodimning kirish raqami qo'yilsa, o'zgartirilsa yoki o'chirilsa (raqam niqoblangan; `via`: `sms`/`vouched`) |
| `permissions.changed` | do'kon ruxsatlari o'zgarsa |
| `device.pairing_code_created`, `device.paired`, `device.renamed`, `device.revoked` | terminal va qurilmalar |
| `delivery_zone.created`, `delivery_zone.updated`, `delivery_zone.deleted` | yetkazib berish hududi (panel yoki API; narx, filial, faollik; shakl o'zgarsa `polygon_changed`) (P2-12) |
| `branch.created`, `branch.updated`, `branch.deleted` | filial API orqali yaratilsa, o'zgarsa yoki o'chirilsa (P2-12) |
| `account.linked` | Telegram hisobi telefon bo'yicha boshqa hisobga birlashtirilsa |

Xodim PIN sessiyasi orqali qilingan har bir amalda terminal ham yoziladi. Jurnalga **hech qachon** PIN, parol, token,
kod, karta ma'lumoti yozilmaydi; telefon raqamlari niqoblanadi (`+99890*****67`), erkin matn ichida ham
(`App\Services\Audit\AuditRedactor`). Sarlavha nomlari (`Authorization`, `Cookie`, `X-Auth`, `sign`, `session`, `xsrf`), `Bearer`/Sanctum/JWT
ko'rinishidagi tokenlar va URL'dagi `token=`, `signature=` qiymatlari ham yashiriladi (Sentry skrubberi bilan bir xil ro'yxat).

P4-11: jurnal **o'zgarmaydi** — yozuvni yangilash yoki o'chirishni model ham, PostgreSQL trigger ham rad etadi
(faqat bog'langan foydalanuvchi, do'kon, filial yoki qurilma o'chirilganda havola `null` bo'ladi). Jamoaning har bir
admin harakati `admin.*` nomi bilan yoziladi (IP va brauzer bilan); butun platforma jurnali — `GET /admin/audit-logs`.

Ega jurnalni `GET /owner/audit-logs` (`audit.view`; filtrlar: `action` yoki `order.*`, `user_id`,
`device_uuid`, `branch_id`, `subject_type`, `subject_id`, `from`, `to`, `per_page`) va panelda "Harakatlar
jurnali" orqali ko'radi. RIZQIM jamoasining do'kon bo'yicha harakatlari (masalan, xodimni bloklash) egaga amal nomi, nima
o'zgargani bilan ko'rinadi, lekin bajaruvchi — `{id: null, name: "RIZQIM jamoasi", role: "platform"}`: jamoa a'zosining
ismi, IP manzili va ichki sababi/izohlari (`reason`, `note`, `email`, `session_id`...) ko'rsatilmaydi, `user_id` filtri
jamoa a'zosini topmaydi (review W3). Butun platforma jurnali va barcha qurilmalar Boshqaruv markazida (Harakatlar jurnali, POS va obunalar → Qurilmalar).

## 8. Panellar

Backend'da veb-panel yo'q (P4-33: eski Filament panellari olib tashlandi). Ega va xodimlar **hamkor panelida**
(`partner/web`, SMS kod bilan kirish, 1-bo'lim) ishlaydi: Xodimlar (rol, filial, kirish raqami, PIN, «Mening PIN
kodim»), Qurilmalar (ulash kodi, nomi, o'chirish), Ruxsatlar (ega o'zgartiradi, menejer faqat ko'radi), Harakatlar
jurnali (faqat ega). Hammasi chaqiruvchining do'koni bilan cheklangan, filial menejeri — o'z filiali bilan. RIZQIM
jamoasi **Boshqaruv markazida** (`platform/admin`, email + parol + 2FA: [API.md](API.md) «Admin API», [OPERATIONS.md](OPERATIONS.md),
10-bo'lim) ishlaydi.

2FA kaliti va zaxira kodlari `users.app_authentication_secret` (shifrlangan, `encrypted` cast) va
`users.app_authentication_recovery_codes` (bcrypt xeshlar ro'yxati, `encrypted:array`) ustunlarida, ilgari Filament
yozgan formatda: P4-33 dan oldin 2FA o'rnatgan a'zolar qayta o'rnatmaydi (`App\Models\User`, `TwoFactorService`).

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
