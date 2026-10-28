# 5-bosqich rejasi: integratsiyalar — boshqa POS'lar va agregatorlar

Holat: **loyiha** (28.10.2026), egasi tasdig'ini kutmoqda. Kod 4-bosqichning 2-to'lqini (billing v2, ulash jarayoni)
merge qilingandan keyin boshlanadi; muzokaralar (13-bo'lim) hozirdan parallel boradi.

Asos: [POS va agregator integratsiyalari](research/reports/POS%20va%20agregator%20integratsiyalari.md) (asosiy manba),
[Alipos va agregator POS modeli](research/reports/Alipos%20va%20agregator%20POS%20modeli.md),
[POS bozori narxlar va fiskal terminallar](research/reports/POS%20bozori%20narxlar%20va%20fiskal%20terminallar.md),
`docs/research/research_notes/POS va agregator integratsiyalari/`, [POS_SPEC.md](POS_SPEC.md) (fiskal, VM 393),
[PHASE4_PLAN.md](PHASE4_PLAN.md) (4.10, 7 va 11-bo'limlar). Har ishning kodi bor (`P5-xx`): commit, hisobot va PR'larda
shu kod ishlatiladi. Muddatlar — tadqiqotdagi taxmin (bitta tajribali backend dasturchi uchun), manbadan emas.

## 1. Nima uchun

RIZQIM'ning ikkala biznesi ham boshqa tizimlar bilan gaplashishga bog'liq:

1. **Marketpleys.** iiko yoki Alipos ishlatadigan joy RIZQIM buyurtmasini planshetdan kassaga qo'lda qayta termaydi —
   bunday joy Rizqim'ni rad etadi. Buyurtma o'zi joyning kassasiga tushsa, menyu, narx va stop-list o'zi qaytsa, joy
   RIZQIM'ga ulanishga rozi bo'ladi. Bu marketpleys o'sishining eng tez yo'li.
2. **POS (SaaS).** Yandex Eats va Uzum Tezkor'da ishlaydigan joy Rizqim POS'ini faqat bu buyurtmalar ham kassaga va
   oshxona ekraniga tushsa oladi. Bu Pro tarifining asosiy dalili.

Asosiy topilma: O'zbekistonda **Yandex Eats'ning hamkor protokoli** amalda umumiy tilga aylangan. Alipos o'z hamkor
API'sini shu yo'llar bilan ochgan, REGOS Uzum uchun ham xuddi shu sozlamani ko'rsatadi. Shuning uchun Rizqim bu
protokolni **bir marta** yozadi va ikki tomonga ishlatadi: server sifatida (Yandex va Uzum Rizqim POS'idan so'raydi)
va klient sifatida (RIZQIM Alipos kabi POS'lardan so'raydi).

**Maqsad:** har qanday tashqi tizim bitta umumiy qatlam orqali ulanadi; har bir buyurtma yo'qolmaydi, ikki marta
ochilmaydi, ikki marta chek olmaydi; RIZQIM jamoasi har bir ulanishning sog'lig'ini boshqaruv markazidan ko'radi.

## 2. Muvaffaqiyat o'lchovlari

| O'lchov | Maqsad |
|---|---|
| RIZQIM buyurtmasi joyning tashqi POS'iga yetishi | 95% hollarda 15 soniyadan kam; 99% buyurtma qo'lda aralashuvsiz |
| Tashqi POS'dagi stop-list Mini App'da ko'rinishi | Webhook bo'lsa 1 daqiqa, polling bilan 5 daqiqa ichida |
| Qayta urinish tufayli takror buyurtma | 0 (testlar va production hisoboti) |
| Bitta sotuvga ikkita sotuv cheki | 0; har integratsiyali buyurtmada `fiscalized_by` to'ldirilgan |
| Yandex yoki Uzum buyurtmasi Rizqim kassasida paydo bo'lishi | 30 soniyadan kam |
| Qotib qolgan buyurtmani operator ko'rishi | 5 daqiqa ichida (4-bosqichdagi kabi) |
| Tashqi POS'i bor joyni ulash (menyu xaritasi bilan) | 1 soatdan kam xodim vaqti |
| Yangi POS vendori sandbox'dan sertifikatgacha | 2 haftadan kam, bizning dasturchisiz |
| Biznes natija (raqamni egasi tasdiqlaydi, 13-bo'lim) | Taklif: ishga tushgach 3 oyda 20 ta Alipos/iiko joyi, 5 ta Rizqim POS joyi Yandex/Uzum bilan |

## 3. Ikki yo'nalish

| | **A. RIZQIM buyurtmasi → joyning boshqa POS'i** | **B. Agregator buyurtmasi → Rizqim POS** |
|---|---|---|
| Kimga | Rizqim POS'ini ishlatmaydigan, lekin marketpleysda sotadigan joy | Rizqim POS mijozi, Yandex Eats yoki Uzum Tezkor'da ham sotadi |
| Uch eshik | (1) bizning adapter (Alipos, iiko, keyin R-Keeper, Clopos, Poster); (2) **"Yandex rejimi"**: vendor o'z Yandex serverida "Rizqim" manbasini yoqadi, biz Yandex kabi so'raymiz (Mison, REGOS); (3) **RIZQIM Partner API**: vendor bizga o'zi ulanadi | Rizqim POS backend'i Yandex protokolining **server** tomonini ochadi; Uzum mos chiqsa — o'sha server, mos chiqmasa — alohida adapter |
| Kim server | Tashqi POS (Partner API'da — Rizqim) | Rizqim |
| Tadqiqotdagi raqami | 1 va 2-yo'nalishlar | 3-yo'nalish |
| Buyurtma kanali | `telegram` / `mobile` qoladi, tashqi POS'dagi raqam `external_refs` da | Yangi kanallar: `yandex_eats`, `uzum_tezkor` (`OrderChannel` ga; qo'lda kiritiladigan `aggregator` qoladi) |

### 3.1. Har yo'nalishga kerakli ma'lumot

| Ma'lumot | A. RIZQIM → tashqi POS | B. Agregator → Rizqim POS |
|---|---|---|
| **Menyu** | Tashqi POS'dan import yoki mavjud RIZQIM taomlariga bog'lash; har taom va modifikator `external_refs` da. Bog'lanmagan taom Mini App'da yashiriladi va POS'ga yuborilmaydi | Rizqim menyusi agregatorga beriladi (`/menu/{id}/composition`); har taomda MXIK (17 belgi) shart, narxi 0 bo'lgan taom yuborilmaydi |
| **Narx** | Tashqi POS narxi asos (taklif); o'zgarsa Rizqim narxi yangilanadi, tarix `menu_price_history` da, katta farqda ogohlantirish | Alohida narx ro'yxati: `channel_prices` da `yandex_eats` va `uzum_tezkor` kanallari (komissiyani qoplash uchun ustama qo'yish mumkin; Uzum "narx kafolati" qoidasi eslatiladi) |
| **Stop-list** | Tashqi POS webhooki (Alipos, iiko `StopListUpdate`) + davriy so'rov → Rizqim stop-listi | Rizqim stop-listi → `/menu/{id}/availability`; o'zgarishda `menu/import/initiation` bilan darhol xabar; stop-listdagi taomga `406 unavailable_goods` |
| **Holatlar** | Tashqi holat → Rizqim `OrderStatus` (3.2-jadval); RIZQIM kuryeri bo'lsa "tayyor"dan keyingi holatlar Rizqim'niki | Kassir/KDS amali → Yandex holati; Yandex holatni o'zi so'raydi (`GET /order/{id}/status`); kassadagi "Pauza" → `/block` (`too_busy`) |
| **To'lov** | Usul va kim pul oldi: onlayn (Click, joyning Payme kassasi), platforma Payme kassasi, eshik oldida naqd/karta; yetkazish haqi alohida qator. iiko'da onlayn to'lov `External`, oldindan to'langan, fiskal bo'lmagan "Rizqim onlayn" to'lov turi bilan | Agregatorda oldindan to'langan yoki joy kuryeriga naqd; summa va to'lov farqi `payment_discrepancy` da |
| **Fiskal** (`fiscalized_by`) | To'lov yo'nalishi bo'yicha (7-bo'lim): `payment_provider` yoki `venue_pos`; tashqi POS'ga "chek tashqarida chiqarilgan" belgisi shu qiymatdan | `aggregator` (oldindan to'langan) yoki `venue_pos` (joy kuryeri naqd oldi); tashqi chek havolasi berilsa saqlanadi |
| **Komissiya** | Odatdagi RIZQIM komissiyasi kanal bo'yicha: RIZQIM kuryeri 15%, o'zi yetkazsa 10%, olib ketish 5%. POS'ga yuborilmaydi | RIZQIM komissiyasi 0% (bu marketpleys buyurtmasi emas, GMV'ga kirmaydi). Agregator komissiyasi solishtirish va egaga hisobot uchun yoziladi |
| **Mijoz** | Faqat buyurtmaga kerakli: ism, telefon, manzil, izoh. iiko'da `one-time` mijoz (joyning sodiqlik bazasiga tushmaydi) | Agregator bergan ma'lumot faqat buyurtmani bajarish uchun; RIZQIM reklamasiga ishlatilmaydi |

### 3.2. Holatlar xaritasi

| Rizqim | Yandex / Alipos | iiko | Clopos |
|---|---|---|---|
| `new` | `NEW` | `Unconfirmed` | `PENDING_ACCEPTANCE` |
| `accepted` | `ACCEPTED_BY_RESTAURANT` | `WaitCooking` | `ACCEPTED` |
| `preparing` | `COOKING` | `CookingStarted` | `IN_PRODUCTION` |
| `ready` | `READY` | `CookingCompleted`, `Waiting` | `READY` |
| `on_the_way` / `delivery` | `TAKEN_BY_COURIER` | `OnWay` (faqat joy kuryeri) | `PICKED_UP`, `DELIVERING` |
| `delivered` | `DELIVERED` | `Delivered`, `Closed` | `COMPLETED` |
| `canceled` / `rejected` | `CANCELLED` | `Cancelled` | `CANCELLED`, `REJECTED` |

Holatlar faqat oldinga yuradi (`OrderStatus::allowedTransitions`); kutilmagan tashqi holat faqat jurnalga yoziladi.

## 4. Arxitektura

### 4.1. Bitta qatlam

```
 Yandex Eats, Uzum ──► KIRUVCHI SERVER (Yandex protokoli) ─────┐
 POS vendorlari    ──► RIZQIM PARTNER API v1 ──────────────────┤      ┌─────────────────────────────┐
 Alipos, Mison,    ◄── CHIQUVCHI ADAPTERLAR: Yandex-klient,     ├────► │ UMUMIY MODEL: katalog,      │
 REGOS, iiko, ...      iikoCloud, r_k, Clopos, Poster, Fake ───┘      │ stop-list, buyurtma, to'lov,│
                                                                      │ holat, fiscalized_by        │
 UMUMIY XIZMATLAR: ulanishlar, kalitlar (shifrlangan), external_refs, └─────────────────────────────┘
 hodisalar jurnali, idempotentlik, navbat va qayta urinish, limitlar, sog'liq, qabul muddati nazorati
```

Kod: `backend/app/Domain/Integrations` (yangi) — `Contracts`, `Journal`, `Mapping`, `Health`, `Protocol/Yandex`
(DTO va validatorlar), `Inbound/Yandex`, `PartnerApi`, `Adapters/{YandexClient,Iiko,RKeeper,Clopos,Poster,Fake}`.
Fiskal faqat mavjud `FiscalIssuer` orqali (`recordExternal` — tashqi chek uchun); integratsiya provayder bilan
to'g'ridan-to'g'ri gaplashmaydi.

### 4.2. Adapter interfeysi

Har bir tashqi tizim bitta `IntegrationAdapter` ni amalga oshiradi, nima qila olishini `capabilities()` aytadi
(masalan, Clopos'da webhook yo'q): `testConnection` (kalit, filiallar, terminallar), `fetchMenu` va
`fetchAvailability`, `pushOrder` / `cancelOrder` / `fetchOrderStatus` (idempotentlik kaliti bilan), `isAlive` (kassa
onlaynmi — savatni qabul qilishdan oldin), `verifyWebhook` va `parseWebhook`, `mapStatus` va `mapFiscal`.

Yandex protokoli paketi (`Protocol/Yandex`) Yandex OpenAPI faylidan (v1.0, 01.09.2026) olingan DTO, validatorlar va
Postman to'plami asosidagi qabul testlaridan iborat. `Adapters/YandexClient` va `Inbound/Yandex` ikkalasi shu paketni
ishlatadi; Rizqim o'z klientini o'z serveriga qarshi sinaydi.

### 4.3. Ma'lumot modeli

4-bosqichda (P4-31) loyihalangan jadvallar shu bosqichda to'liq ishlatiladi; yetishmagan ustunlar P5-11 da qo'shiladi.

| Jadval / maydon | Asosiy ustunlar |
|---|---|
| `integration_connections` | do'kon, filial, provayder (`alipos`, `iiko`, `yandex_eats`, `uzum_tezkor`, `partner_api`, ...), yo'nalish (A/B), rejim (`sandbox` / `production`), holat (sozlanmoqda, faol, pauza, uzilgan), kalitlar (shifrlangan), sozlamalar (JSON: terminal, to'lov turi, narx manbai), sog'liq (oxirgi muvaffaqiyatli so'rov, oxirgi webhook, xatolar, baho) |
| `integration_events` | ulanish, yo'nalish (kiruvchi/chiquvchi), tur (`order.push`, `menu.sync`, `webhook.status`, ...), idempotentlik kaliti, bog'liq obyekt, so'rov va javob tanasi (shaxsiy ma'lumot qisman yashirilgan), HTTP kodi, imzo natijasi, urinishlar, keyingi urinish vaqti, holat (kutmoqda, yuborildi, tasdiqlandi, xato, o'lik) |
| `external_refs` | ulanish, ichki obyekt (filial, kategoriya, taom, variant, modifikator, buyurtma), tashqi ID; ikki tomonga yagona |
| `orders.fiscalized_by` | `venue_pos` / `payment_provider` / `aggregator` / `rizqim_marketplace` (kelajak uchun zaxira); bo'sh — "fiskal ko'rib chiqish kerak" |
| `orders.aggregator_commission_amount` | B-yo'nalish: agregator komissiyasi (sozlamadagi foizdan, solishtirishdan keyin aniqlanadi) |
| `OrderChannel` | `yandex_eats`, `uzum_tezkor` qo'shiladi (16 belgiga sig'adi); `channel_prices` ham shu kanallarni oladi |

### 4.4. Ishonchlilik qoidalari

- **Hamma narsa jurnalda** (`integration_events`); markazda "qayta yuborish" tugmasi (sabab bilan).
- **Idempotentlik:** Yandex'da `eatsId`, iiko'da Rizqim oldindan yaratgan UUID (`DuplicatedOrderId`), Clopos va
  Partner API'da `Idempotency-Key`, har hodisada `event_id`. Qayta urinish ikkinchi buyurtma ochmaydi.
- **Webhook darhol 2xx** bilan qabul qilinadi, ishlov Redis navbatida. **Qayta urinish:** 1, 2, 4, 8, 16, 32 daqiqa,
  keyin "o'lik" ro'yxati va ogohlantirish. **Tokenlar keshda** (iiko ~55 daqiqa, r_k 3600 soniya), 401 da qayta olinadi.
- **Qabul muddati:** tashqi POS buyurtmani 2 daqiqada tasdiqlamasa — "qotib qolgan" (markaz va joyga Telegram);
  4-bosqichdagi qabul SLA'si o'tsa — operator bekor qiladi va pulni qaytaradi (avtomatik bekor qilish — egasi qarori).
- **Kassa onlaynligi savatda:** ulanish "uzilgan" yoki kassa oflayn bo'lsa, Mini App'da joy "hozir qabul qilmayapti".
- **Zaxira yo'l:** integratsiya ishlamasa buyurtma baribir Rizqim xodim ekranida va Telegram xabarida chiqadi,
  "tashqi POS'ga tushmadi — qo'lda kiriting" belgisi bilan. Buyurtma hech qachon yo'qolmaydi.

### 4.5. Webhook va polling

| Tizim | Buyurtma holati | Stop-list | Menyu |
|---|---|---|---|
| Alipos (Yandex-klient) | `order-status` webhook + har 60 soniyada ochiq buyurtmalarni so'rash | Webhook + har 5 daqiqada | Kuniga + qo'lda "yangilash" |
| iiko | Webhook + `by_revision` solishtirish (webhook kafolatlanmagan) | `StopListUpdate` + har 5 daqiqada | Kuniga + qo'lda |
| Clopos | Faqat polling (webhook yo'q), har 30 soniyada | Har 2 daqiqada | Kuniga |
| Yandex, Uzum (B) | Ular bizdan so'raydi; biz `menu/import/initiation` va `/block` bilan turtamiz | — | — |
| Partner API | Webhook (imzoli) yoki `GET /v1/events` — vendor tanlaydi | Vendor push qiladi | Vendor push qiladi |

### 4.6. Maxfiy kalitlar, limitlar va sandbox

- **Kalitlar** Laravel shifrlangan maydonida; markazda va hamkor panelida faqat oxirgi 4 belgi. Rizqim bergan
  kalitlar (Yandex uchun filial client_id/secret, Partner API secret) faqat **bir marta** ko'rsatiladi, bazada xesh;
  almashtirish (rotation) tugmasi, eski kalit 24 soat ishlaydi. Jurnal tanalarida token va secret yozilmaydi.
- **Chiquvchi limitlar:** har provayderga token bucket (Clopos 200 so'rov / 10 soniya; iiko va Alipos raqami
  noma'lum — ehtiyotkor boshlanadi), 429 da `Retry-After` bo'yicha kutish.
- **Kiruvchi limitlar:** Partner API'da har klientga (taklif: daqiqasiga 600 so'rov), `X-RateLimit-*` sarlavhalari va
  429. Yandex va Uzum so'rovlari cheklanmaydi, faqat suiiste'molga qarshi chegara.
- **Domen:** kiruvchi server ochiq HTTPS domenda (`integrations.<domen>`); Yandex IP manzilni qabul qilmaydi.
- **Sandbox:** har ulanish `sandbox` yoki `production` rejimida; sandbox buyurtmasi real oshxona va pulga tegmaydi.
  Har hamkorga alohida sandbox kaliti; production kaliti sertifikat yoki birinchi test buyurtmadan keyin.
- **Fake hamkor:** repozitoriyada kichik soxta server (`e2e/fake-partner`): Yandex protokolining server tomoni
  (Alipos o'rnida), Partner API klienti va soxta agregator (Yandex o'rnida Rizqim serverini so'raydi). Hamkor
  kalitlari kelmaguncha butun zanjir shu bilan sinaladi.

### 4.7. RIZQIM Partner API v1

HubRise andozasida, ochiq OpenAPI 3.1 hujjat bilan:

- **Resurslar:** `location` (ochiq, band, yopiq), `catalog` (to'liq push, asinxron), `inventory` (stop-list, `until`
  bilan), `orders` (`accept` tayyorlash vaqti bilan, `reject` sabab bilan, holat yangilash), `events`.
- **Kirish:** OAuth2; ovqatxona egasi hamkor panelida ruxsatni **filial darajasida** tasdiqlaydi, token bitta filialga
  bog'langan; bekor qilish tugmasi.
- **Webhook:** `X-Rizqim-Signature: t=<vaqt>,v1=<HMAC-SHA256(secret, t + "." + tana)>` (eski so'rovni qaytarishdan
  himoya). Har qanday 2xx — qabul. **Polling:** `GET /v1/events` + `DELETE /v1/events/{id}` (lokal serverli POS'lar uchun).
- **Versiya:** `/v1` ichida faqat qo'shimcha o'zgarishlar; o'zgarishlar sahifasi; shartlar o'zgarishi 30 kun oldin.
- **Sertifikatlash:** sandbox'dagi avtomatik testlar — qabul muddati, `delivered` gacha barcha holatlar, sabab bilan
  rad etish, modifikatorli menyu, stop-list, takroriy yetkazish, imzo tekshiruvi. O'tsa — production kaliti.

## 5. Ekranlar

**Boshqaruv markazi → Integratsiyalar** (`platform/admin`, hozir bo'sh joy turibdi; ruxsatlar `admin.integrations.view`
va `admin.integrations.manage` allaqachon bor):

| Ekran | Nima ko'rsatadi va nima qiladi | Kim |
|---|---|---|
| Ulanishlar | Ovqatxona va filial bo'yicha ro'yxat: provayder, yo'nalish, rejim, holat, sog'liq bahosi, oxirgi so'rov va webhook, bugungi xatolar; filtr va eksport | Operator ko'radi; savdo/ulash sozlaydi |
| Ulanish kartasi | Sozlamalar, kalit (oxirgi 4 belgi), "tekshirish", pauza/yoqish, sog'liq grafigi (24 soat), oxirgi hodisalar, menyu xaritasi holati | Savdo/ulash |
| Hodisalar jurnali | So'rov va javob, imzo, urinishlar; holat va tur bo'yicha filtr; "qayta yuborish" (sabab bilan) | Operator |
| Menyu xaritasi | Tashqi taom ↔ RIZQIM taomi, bog'lanmaganlar, narxi o'zgarganlar, buyurtmada uchragan noma'lum ref | Savdo/ulash |
| Qotib qolgan buyurtmalar | Yuborilgan, lekin tasdiqlanmagan, taymer bilan; "Bugun → Diqqat talab qiladi" ga ham tushadi (yangi `Attention` manbai) | Operator |
| Hamkor ilovalar | Partner API klientlari, sandbox/production, sertifikat testlari natijasi, production kalitini tasdiqlash (step-up) | Superadmin |
| Agregator solishtirish | Yandex buyurtmalar tarixi (API, 180 kun) va Uzum Didox aktlari (fayl importi) ↔ Rizqim POS buyurtmalari va komissiyasi; farqlar | Moliya |

Ogohlantirish qoidalari (P4-15 ga qo'shiladi): ulanish uzildi (ish vaqtida 10 daqiqa aloqa yo'q), qotib qolgan
buyurtma, "o'lik" hodisa, token xatosi, bog'lanmagan taom buyurtmada. **Sog'liq bahosi** (0–100): 100 dan oxirgi soatdagi
xato ulushi, aloqa yo'q vaqt, qotib qolgan buyurtmalar va bog'lanmagan taomlar ayiriladi; token xatosi — 0. 80 dan
yuqori "yaxshi", 50–79 "e'tibor kerak", 50 dan past "uzilgan"; ovqatxona sog'liq bahosiga (P4-22) ham kiradi.

**Hamkor paneli → Integratsiyalar** (`partner/web`, egasi o'zi ulaydi): iiko API kalitini kiritish, Alipos yoki
"Yandex rejimi" vendori kalitlarini kiritish, menyuni import qilish yoki bog'lash, test buyurtma, Yandex va Uzum uchun
filial Client ID yaratish (bir marta ko'rsatiladi), `yandex_eats` / `uzum_tezkor` narx ro'yxati, Partner API
ruxsatlari, ulanish sog'lig'i. **Kassa va KDS:** agregator belgisi, qabul taymeri, "chek tashqarida chiqarilgan"
belgisi, "Pauza/band" tugmasi.

## 6. Billing (egasi uchun taklif)

| Integratsiya | Kim to'laydi | Taklif |
|---|---|---|
| RIZQIM buyurtmasi joyning tashqi POS'iga (adapter, "Yandex rejimi", Partner API) | Hech kim alohida | **Bepul**, hamma joy uchun (Rizqim POS'i shart emas — POS marketpleys sharti emas). Rizqim odatdagi komissiyadan topadi |
| Tashqi POS vendorining o'z haqi (Alipos: har agregatorga 10 $/oy va 100 $ ulash) | Joy yoki Rizqim | Taklif: birinchi 50 ta joy yoki 6 oy uchun Rizqim qoplaydi (jalb qilish xarajati), keyin joy o'zi. Muzokarada Alipos'dan Rizqim uchun bepul ulanish so'raladi |
| Yandex Eats va Uzum Tezkor buyurtmalari Rizqim POS'ida | Joy, obuna orqali | **Pro**'da ikkalasi kiradi (4-bosqich tarif jadvaliga mos). Asosiy'da qo'shimcha har agregator 59 000 so'm/oy (taklif). Start'da faqat qo'lda kiritish. Funksiya kalitlari: `aggregator.yandex_eats`, `aggregator.uzum_tezkor` (P4-21 cheklovlari orqali) |
| RIZQIM komissiyasi agregator buyurtmasidan | — | 0%; GMV va "faol hamkor chegirmasi" hisobiga kirmaydi |
| Partner API | Vendor | **Bepul.** Rag'bat: vendor ulagan va marketpleysda faollashgan joy komissiyasining ~10% i, 6 oy (taklif). Birinchi vaqtda hisob-kitob oylik hisobot bilan qo'lda |

Tarifda yo'q bo'lsa kassada va hamkor panelida "Pro tarifida bor" ko'rsatiladi; obuna to'lanmasa ham **allaqachon
kelgan agregator buyurtmasi kassada yopiladi** va chek chiqadi (4-bosqich qoidasi).

## 7. Fiskal va yuridik qoidalar

Qoida: **bitta pulga bitta chek**; chekni pul qayerda olingan bo'lsa, o'sha tomon chiqaradi. Ikkinchi chek joy
aylanmasini ikki marta ko'rsatadi; chek bermaslik — 5 mln so'm jarima, integratsiya qoidasini buzish — choraklik
tushumning 2% i. Jadvalni soliq maslahatchisi tasdiqlashi kerak (13-bo'lim).

| Holat | Kim chek chiqaradi | `fiscalized_by` va tizim nima qiladi |
|---|---|---|
| **A.** Onlayn to'langan: Click yoki joyning o'z Payme kassasi | To'lov tizimi, joy STIRi komitent (P3-14 ishlaydi) | `payment_provider`. Tashqi POS'ga "tashqarida chiqarilgan" (iiko: `isFiscalizedExternally: true`, fiskal bo'lmagan to'lov turi); POS ikkinchi chek chiqarmaydi. Ulash ustasi bu to'lov turi borligini tekshiradi |
| **A.** Platformaning umumiy Payme kassasi | Hozir joy nomidan chek chiqmaydi (komitent maydoni yo'q) | Integratsiyali joyda bu yo'l yopiladi (taklif) yoki `venue_pos` bilan yuboriladi — egasi qarori |
| **A.** Joy kuryeri, eshik oldida naqd yoki karta; olib ketishda joyda to'lov | Joy, o'z kassasidan, **pul olingan paytda** (jo'natishda emas) | `venue_pos`; tashqi POS chekni o'zi chiqaradi |
| **A.** RIZQIM kuryeri naqd oldi | Aniq emas | Hal bo'lguncha RIZQIM kuryeri buyurtmalari faqat onlayn yoki QR bilan (kuryer tarmog'i 7-bosqichda) |
| **B.** Agregatorda oldindan to'langan | Agregator yoki uning to'lov operatori, joy komitent (taxmin, yozma tasdiq kerak) | `aggregator`; Rizqim POS sotuv chekini **bloklaydi**; agregator chek havolasini bersa `recordExternal` bilan saqlanadi |
| **B.** Joy kuryeri naqd oldi | Joy (Rizqim POS), to'lov paytida | `venue_pos`; kassada "pul olindi" qadami, chek shundan keyin |
| **B.** Agregator kuryeri naqd oldi | Noma'lum | `fiscalized_by` bo'sh; buyurtma "fiskal ko'rib chiqish" ro'yxatiga, operator hal qiladi |

- **VM 393 (22.10.2026 dan).** Agregator buyurtmasi tushgan har qanday buyurtma dasturi soliq tizimi bilan
  integratsiya qilingan bo'lishi kerak. B-yo'nalishda bu Rizqim POS'ining fiskal qatlami (haqiqiy drayver bo'lmasa
  agregator ulanishi production'ga o'tmaydi). A-yo'nalishda tashqi POS'ning fiskal integratsiyasi joyning javobgarligi;
  ulash ustasida joy buni tasdiqlaydi.
- **Marketpleys reyestri (VM 943, 1a-ilova).** Rizqim o'zi Marketpleys bo'lsa, platforma Payme kassasi va RIZQIM
  kuryeri naqd olgan holatlar bitta yo'lga keladi (`rizqim_marketplace`). Bu alohida loyiha (virtual kassa "A yo'li"
  bilan birga); 5-bosqich unga bog'liq emas, faqat qiymat zaxirada.
- **ORQ-1173 (E-tijorat qonuni, ~12.12.2026 dan).** Rizqim "buyurtmalar agregatori" operatori bo'lishi mumkin:
  oferta e'loni, qoidalar va komissiya o'zgarishi **30 kun** oldin, tartiblash algoritmi shaffof, sotuvchilar bilan
  alohida bank hisoblari, foydalanuvchi ma'lumoti maxfiyligi. Partner API shartlari ham 30 kunlik qoidaga bo'ysunadi.
- **Shaxsiy ma'lumot.** Mijoz ofertasiga ma'lumotni joyga, uning POS vendoriga va kuryerga (chet eldagi serverlar ham —
  iiko va R-Keeper bulutlari) uzatishga rozilik; rozilik jurnalga yoziladi. Vendorlar bilan qayta ishlash shartnomasi.
  Agregatordan kelgan mijoz ma'lumoti RIZQIM reklamasiga ishlatilmaydi.
- **Raqobat.** Eksklyuzivlik talab qilinmaydi, hamma vendorga bir xil shartlar, POS marketpleysga kirish sharti emas.

## 8. Hamkorsiz nima quriladi, nima hamkorni kutadi

| Hamkorsiz (darhol) | Hamkor ruxsatini kutadi |
|---|---|
| Umumiy qatlam: model, jurnal, qayta urinish, limitlar, kalitlar, sog'liq, menyu xaritasi | Alipos adapteri production'da — Alipos kaliti va test restoran |
| Yandex protokoli paketi va qabul testlari (ochiq OpenAPI va Postman to'plami) | iiko — dasturchi portalida `appId` va diler test tashkiloti |
| Fake hamkor va to'liq e2e zanjir | Yandex serveri production'da — Yandex menejeri, test buyurtma, pilot restoran |
| Yandex serveri (B) — spetsifikatsiya ochiq, fake agregator bilan sinaladi | Uzum — yozma spetsifikatsiya (Yandex'ga mosmi) |
| RIZQIM Partner API, sandbox, sertifikatlash, hujjat | Mison, REGOS — vendor "Rizqim" manbasini yoqishi |
| Markaz va hamkor paneli ekranlari, kassa UI | R-Keeper — BUSOFT kelishuvi; Clopos — dasturchi hisobi tasdig'i; Poster — joy talabi |

## 9. To'lqinlar

Har to'lqin — **ishlatsa bo'ladigan tayyor qism**, bitta PR (5-to'lqinda har konnektor o'z kichik PR'i); siz merge
qilganingizdan keyin keyingisi boshlanadi. Kaliti kelmagan adapter feature flag ortida fake hamkor va spetsifikatsiya
bo'yicha tayyorlanadi va to'lqinni to'xtatmaydi; kalit kelganda faqat sandbox sinovi va pilot qoladi.

```
Oldindan   P5-01 Muzokara to'plami: har hamkorga texnik savollar, oferta va Partner API shartlari loyihasi

To'lqin 1  "Poydevor"  — hamkorsiz; fake hamkor bilan to'liq zanjir                       (~4–5 hafta)
           P5-11 Ma'lumot modeli va kanallar   P5-12 Jurnal, navbat, qayta urinish, kalitlar, limitlar
           P5-13 Menyu xaritasi va stop-list   P5-14 Sog'liq, ogohlantirish, qabul muddati
           P5-15 Yandex protokoli paketi       P5-16 Fake hamkor va e2e
           P5-17 Markaz va hamkor panelida Integratsiyalar ekranlari

To'lqin 2  "Ochiq eshik" — hamkorsiz; har qanday vendor o'zi ulana boshlaydi               (~4–6 hafta)
           P5-21 Partner API v1   P5-22 Sandbox va sertifikatlash   P5-23 Hujjat sayti, Hamkor ilovalar ekrani

To'lqin 3  "RIZQIM buyurtmasi Alipos va iiko'ga" — Alipos va iiko ruxsati kerak            (~4–6 hafta)
           P5-31 Alipos   P5-32 iiko   P5-33 Ulash ustasi   P5-34 Pilot (1–2 joy)

To'lqin 4  "Yandex va Uzum buyurtmasi Rizqim POS'ida" — Yandex, Uzum, pilot restoran kerak  (~5–6 hafta)
           P5-41 Yandex serveri   P5-42 Uzum   P5-43 Kassa va KDS   P5-44 Fiskal qoidalar   P5-45 Solishtirish

To'lqin 5  "Boshqa POS'lar" — talab va kelishuvga qarab, har biri alohida                  (har biri 0,5–3 hafta)
           P5-51 Mison, REGOS ("Yandex rejimi")   P5-52 R-Keeper (BUSOFT)   P5-53 Clopos   P5-54 Poster

To'lqin 6  "Yakun" — P5-61 E2E matritsa va yuklama   P5-62 Mustaqil review   P5-63 Qo'llanmalar
```

Partner API tadqiqotdagi tartibdan oldinga olindi: u muzokara kutmaydi va Alipos yoki Uzum javobi kechiksa ham
vendorlarga ochiq yo'l beradi. P5-01 ni men tayyorlayman (`docs/`), muzokaralarni ega olib boradi.

### To'lqin 1 — Poydevor

| Kod | Ish | Bajaruvchi | Qaysi fayllar | Tekshiruv |
|---|---|---|---|---|
| P5-11 | **Ma'lumot modeli.** `integration_connections`, `integration_events`, `external_refs` (P4-31 dagini kengaytirish), `orders.fiscalized_by`, `aggregator_commission_amount`; `OrderChannel` ga `yandex_eats`, `uzum_tezkor` (kassa navbati, hisobot va GMV'dan tashqari qoidasi bilan); `channel_prices` uchun yangi kanallar; `IntegrationAdapter` interfeysi va `capabilities` | Opus | `Domain/Integrations/Contracts`, migratsiyalar, `Enums` | Migratsiya oldinga va orqaga; mavjud buyurtma va hisobotlar o'zgarmaydi; izolyatsiya testlari |
| P5-12 | **Jurnal va ishonchlilik.** Har so'rovni yozish (secret va tokensiz, shaxsiy ma'lumot qisman yashirin), idempotentlik kalitlari, Redis navbati, 1–32 daqiqalik qayta urinish, "o'lik" ro'yxat, qayta yuborish; shifrlangan kalitlar, bir marta ko'rsatish, almashtirish; chiquvchi token bucket va 429 | Opus | `Domain/Integrations/Journal` | Takroriy yetkazish 2-buyurtma ochmaydi; qayta urinish jadvali; kalit javobda va logda chiqmaydi |
| P5-13 | **Menyu xaritasi va stop-list.** Import yoki bog'lash, bog'lanmagan taomni yashirish, narx o'zgarishi va tarix, stop-list hodisasi → Rizqim stop-listi va Mini App | Opus | `Domain/Integrations/Mapping` | Modifikatorli menyu, narx farqi ogohlantirishi, stop-list 1 daqiqada |
| P5-14 | **Sog'liq va nazorat.** Sog'liq bahosi, ogohlantirish qoidalari (P4-15), "qotib qolgan" `Attention` manbai, qabul muddati taymeri, savatda "hozir qabul qilmayapti", zaxira yo'l (xodim ekrani va Telegram belgisi) | Sonnet | `Domain/Integrations/Health`, `Services/Admin/Today/Attention` | Uzilishni simulyatsiya qilish; ogohlantirish 10 daqiqada; buyurtma yo'qolmaydi |
| P5-15 | **Yandex protokoli paketi.** OpenAPI'dan DTO va validatorlar (MXIK 17 belgi, holatlar, 406 `unavailable_goods`), Postman to'plami asosidagi qabul testlari; server va klient uchun umumiy | Opus | `Domain/Integrations/Protocol/Yandex` | Yandex namunalari bilan testlar; spetsifikatsiya o'zgarsa CI ogohlantiradi |
| P5-16 | **Fake hamkor va e2e.** `e2e/fake-partner`: Yandex server tomoni (Alipos o'rnida), soxta agregator (Yandex o'rnida), xato, kechikish va takror rejimlari; `Adapters/YandexClient` uning ustida | Opus | `e2e/fake-partner`, `Adapters/YandexClient` | E2E: Mini App buyurtmasi → fake POS → holat → yetkazildi; stop-list qaytadi |
| P5-17 | **Ekranlar.** Markazda Ulanishlar, Ulanish kartasi, Hodisalar jurnali, Menyu xaritasi, Qotib qolganlar (5-bo'lim); hamkor panelida Integratsiyalar sahifasi (ro'yxat, sog'liq, xarita) | Sonnet | `platform/admin`, `partner/web`, `routes/api_admin_integrations.php` | Rol testlari; haqiqiy server bilan e2e; skrinshotlar |

**To'qnashuv:** P5-11 birinchi kuni interfeys va jadvallarni beradi, qolganlar soxta amalga oshirish bilan
boshlaydi. `Order` modeli va `OrderChannel` faqat P5-11 da o'zgaradi.

### To'lqin 2 — Ochiq eshik (Partner API)

| Kod | Ish | Bajaruvchi | Tekshiruv |
|---|---|---|---|
| P5-21 | **Partner API v1** (4.7): OAuth2, egasining filial ruxsati, `location`, `catalog`, `inventory`, `orders`, `events`; imzoli webhook va polling; `Idempotency-Key`; kiruvchi limitlar; OpenAPI 3.1 | Opus | Har endpoint: ruxsat, izolyatsiya, imzo, eski so'rovni qaytarish, idempotentlik, 429 |
| P5-22 | **Sandbox va sertifikatlash:** test do'konlar, "buyurtmani simulyatsiya qilish" (yangi, bekor, kuryer holatlari), avtomatik sertifikat testlari, production kalitini faqat testdan keyin berish | Opus | Fake hamkor sertifikatdan o'tadi; testdan o'tmagan klient production kaliti ololmaydi |
| P5-23 | **Hujjat va ekranlar:** vendorlar uchun ochiq hujjat sahifasi (o'zbek va rus tilida qisqa boshlash qo'llanmasi), o'zgarishlar sahifasi; markazda "Hamkor ilovalar"; hamkor panelida "RIZQIM integratsiyalari" katalogi va ruxsat berish/bekor qilish | Sonnet | Hujjatdagi har misol sandbox'da ishlaydi; e2e |

### To'lqin 3 — RIZQIM buyurtmasi Alipos va iiko'ga

| Kod | Ish | Hamkordan kerak | Bajaruvchi | Tekshiruv |
|---|---|---|---|---|
| P5-31 | **Alipos** — `YandexClient` sozlamasi: token, menyu va stop-list, buyurtma (`eatsId`, `paymentInfo`), holat va stop-list webhooklari, `fiscalized_by` → to'lov belgisi | Platforma nomi, client_id/secret, test restoran, webhook manzillari | Opus | Alipos sandbox yoki test restoranida to'liq sikl |
| P5-32 | **iiko** (iikoCloud, 2026-yilgi kirish: `appId` + `clientSecret` + joy API kaliti, token 1 soat): tashkilot va terminallar, menyu, stop-list, `is_alive`, `deliveries/create` (o'z UUID, `one-time` mijoz, `External` to'lov, `isFiscalizedExternally`), webhook + `by_revision`, bekor qilish | Dasturchi portalida ro'yxat, diler test tashkiloti, pilot joyda API tarifi | Opus | Test tashkilotda: buyurtma kassaga tushadi, ikkinchi chek chiqmaydi, oflayn kassada savat yopiladi |
| P5-33 | **Ulash ustasi** (hamkor paneli va markaz, P4-22 ulash jarayoniga qadam): kalit → filial va terminal tanlash → menyu import/bog'lash → to'lov turi tekshiruvi → test buyurtma → faol | — | Sonnet | Joyni 1 soatda ulash ssenariysi e2e |
| P5-34 | **Pilot:** 1–2 ta haqiqiy joy (Alipos va iiko), 2 hafta kuzatuv, sog'liq va qotib qolganlar hisoboti | Pilot joylar | Men + siz | 2-bo'lim o'lchovlari pilotda bajariladi |

### To'lqin 4 — Yandex va Uzum buyurtmasi Rizqim POS'ida

| Kod | Ish | Hamkordan kerak | Bajaruvchi | Tekshiruv |
|---|---|---|---|---|
| P5-41 | **Yandex serveri** (`integrations.<domen>/yandex`): filial client_id/secret, `/security/oauth/token`, menyu va mavjudlik (`yandex_eats` narxlari, MXIK), `/restaurants`, `POST /order` (`eatsId` bo'yicha idempotent, tez javob, 406), holat va bekor qilish; `menu/import/initiation` va `/block` | Yandex menejeri: ulanish tartibi, test buyurtma, push API manzili; pilot restoran | Opus | Yandex Postman to'plami o'tadi; fake agregator bilan e2e; birinchi test buyurtma |
| P5-42 | **Uzum Tezkor:** Yandex'ga mos bo'lsa — P5-41 sozlamasi (~1 hafta); bo'lmasa alohida adapter (3–4 hafta, keyingi PR) | Yozma spetsifikatsiya, test muhit | Opus | Uzum test muhitida to'liq sikl |
| P5-43 | **Kassa va KDS:** agregator belgisi va tashqi raqami, qabul taymeri, "chek tashqarida" belgisi, "Pauza/band", joy kuryeri uchun "pul olindi" qadami; hamkor panelida narx ro'yxatlari va Client ID yaratish | — | Sonnet | Widget testlari, suratlar |
| P5-44 | **Fiskal qoidalar** (7-bo'lim, B qatorlari): `aggregator` da sotuv cheki bloklanadi, `recordExternal`; naqdda chek to'lov paytida; noma'lum holat — ko'rib chiqish ro'yxati; tarif kaliti (Pro) | Agregatordan chek bo'yicha yozma javob | Opus | Hech bir holatda ikki sotuv cheki yo'q; Z hisobotda agregator buyurtmalari alohida |
| P5-45 | **Solishtirish:** Yandex buyurtmalar tarixi (API), Uzum Didox aktlari (fayl importi) → farqlar; egaga "agregator komissiyasi" hisoboti | Uzum Didox hisobot namunasi | Sonnet | Namuna fayllar bilan testlar |

### To'lqin 5 — Boshqa POS'lar (har biri kelishuv bo'lsa)

| Kod | Ish | Hamkordan kerak | Taxmin |
|---|---|---|---|
| P5-51 | **Mison, REGOS** — "Yandex rejimi": `YandexClient` sozlamasi va vendor farqlari uchun testlar | Vendor "Rizqim" manbasini yoqadi, kalitlar beradi | Har biri 0,5–1 hafta |
| P5-52 | **R-Keeper:** avval BUSOFT hub'i (Rizqim hub'ga agregator bo'ladi — ehtimol bizning Partner API orqali); zaxira — r_k Delivery adapteri | BUSOFT kelishuvi yoki joyda Delivery_Api litsenziyasi | Kam kod yoki 2–3 hafta |
| P5-53 | **Clopos:** OAuth ilova, StandardOrder v2, polling, stop-list | dev@clopos.com tasdig'i | ~2 hafta |
| P5-54 | **Poster:** OAuth, `createIncomingOrder`, `sources` bo'yicha narx, MD5 webhook | Joy talabi; bepul dasturchi hisobi | 2–3 hafta |

### To'lqin 6 — Yakun

| Kod | Ish | Tekshiruv |
|---|---|---|
| P5-61 | **E2E matritsa:** har ulanish × to'lov yo'li × fiskal holat; uzilish, takror, kechikish; yuklama (Partner API limitlari, 50 filial bir vaqtda) | CI'da 3 marta ketma-ket barqaror |
| P5-62 | **Mustaqil review:** kalitlar, imzolar, ruxsatlar va filial izolyatsiyasi, shaxsiy ma'lumot jurnalda, fiskal jadval | Topilmalar yopilgan, CI yashil |
| P5-63 | **Qo'llanmalar:** ega (iiko, Alipos, Yandex, Uzum ulash), operator (jurnal, qayta yuborish, qotib qolganlar), vendor (Partner API, sertifikat) | Siz o'qib tasdiqlaysiz |

## 10. Xavflar

| Xavf | Ehtimol | Yechim |
|---|---|---|
| Alipos kalit bermaydi (u POS bozorida raqib) | O'rta | Partner API va "Yandex rejimi" boshqa vendorlar orqali; joy uchun zaxira ekran |
| Uzum protokoli Yandex'ga mos emas | O'rta | +3–4 hafta alohida adapter; P5-42 alohida PR |
| Yandex O'zbekistonda uchinchi tomon POS'ga cheklov qo'yadi (reyting, filial soni) | Noma'lum | Menejerdan yozma so'rash; raqobat qonuni 18-moddasi muzokarada dalil |
| iiko'da ochiq sandbox yo'q | Yuqori | Diler test tashkiloti; fake hamkor bilan avval to'liq tayyorlash |
| O'zbek fiskal plaginlari `isFiscalizedExternally` ni hurmat qilmaydi | Noma'lum | Pilotda tekshirish; bo'lmasa shu joy uchun chekni joy kassasi chiqaradi (`venue_pos`) va Click/Payme cheki shu joyda o'chiriladi |
| Ikki marta chek yoki chek yo'q | O'rta | `fiscalized_by` majburiy, kunlik nazorat hisoboti, soliq maslahatchisi tasdig'i |
| Tashqi spetsifikatsiya o'zgaradi (Yandex OpenAPI 01.09.2026 da yangilangan) | Yuqori | Haftalik CI tekshiruvi e'lon qilingan faylga qarshi; kontrakt testlari |
| Menyu xaritasi buziladi (POS'da taom o'chirildi yoki yangi qo'shildi) | Yuqori | Bog'lanmagan taom yashiriladi, ogohlantirish, kunlik to'liq sinxron |
| Integratsiya uzilishi buyurtmani yo'qotadi | Past | Zaxira yo'l: xodim ekrani va Telegram; qotib qolganlar ro'yxati |
| Chet eldagi bulutga (iiko, R-Keeper) ma'lumot uzatish | O'rta | Ofertadagi rozilik, minimal ma'lumot, `one-time` mijoz |
| 4-bosqich kechikadi (billing cheklovlari, ulash jarayoni, ogohlantirishlar) | O'rta | 1–2-to'lqinlar P4-21/22 siz ham ishlaydi, faqat tarif kaliti va ulash qadami keyin ulanadi |

## 11. Tekshiruv

- Har adapter: hujjat yoki fake hamkor namunalari bilan kontrakt testlari, holat xaritasi, takror yetkazish, token
  muddati, 429, imzo xatosi. Yandex server va klient: Yandex Postman to'plami ikkala tomonda o'tadi.
- Fiskal: 7-bo'limdagi har qator uchun test (chek soni va turi, `fiscalized_by`, Z). Partner API: rol va filial
  izolyatsiyasi, sertifikat testlari fake hamkorda. Web va kassa: lint, typecheck, unit va widget testlar, e2e, suratlar.
- Har hamkor bilan: sandbox'da to'liq sikl, keyin 1–2 joyda pilot; 2-bo'lim o'lchovlari hisobot bilan.

## 12. Bu bosqichga kirmaydi (keyin)

- Rizqim'ning Marketpleys yoki virtual kassa sifatida reyestrga kirishi (alohida loyiha, egasi qarori).
- Express24 (Yandex'ga o'tgan), Wolt (5.03.2026 da chiqdi), Glovo va boshqa xorijiy agregatorlar; JOWi; R-Keeper
  kassa serveriga to'g'ridan-to'g'ri XML; API'si yo'q POS'lar (VKassa, Dastur-xon, Paloma365, RAOS); chakana (BILLZ).
- Uzum'ning "faqat kuryer" xizmati, agregator kuryerini xaritada kuzatish, joyning o'z boti.
- Vendor rag'batini avtomatik to'lash (avval oylik hisobot), sodiqlik va buxgalteriya (1C, Didox) integratsiyasi.

## 13. Egasidan kerak

Belgi: **[H]** — hamkor javobisiz shu yo'nalish production'ga chiqmaydi; qolganlari qaror.

1. Rejani tasdiqlash, ayniqsa tartib: Partner API 2-to'lqinda (tadqiqotdagidan oldin).
2. **[H] Alipos:** Rizqim'ga hamkor API kaliti va "platforma" nomi, test restoran, webhook manzillari; 10 $/oy va
   100 $ ulash haqi — joy to'laydimi yoki Rizqim birinchi N joy uchun (6-bo'lim taklifi).
3. **[H] iiko:** Rizqim'ni iiko dasturchi portalida ro'yxatdan o'tkazish (kompaniya rekvizitlari, email) → `appId`,
   `clientSecret`; 1–2 diler (ITDO, Enter-co, A-ONE, Soft24) bilan hamkorlik va test tashkiloti; pilot iiko joyi.
4. **[H] Yandex Eats:** O'zbekistonda uchinchi tomon POS'ni ulash tartibi va chegaralari, sertifikatlash, push API
   manzili, holat so'rash chastotasi, oldindan to'langan buyurtma chekini kim chiqaradi va chek havolasi API'da bormi
   (yozma); Yandex shartnomasi bor va Rizqim POS'ini ishlatadigan pilot restoran.
5. **[H] Uzum Tezkor:** spetsifikatsiya (Yandex'ga mosmi), test muhit, tasdiqlangan integratorlar ro'yxatiga kirish,
   chek kimdan (yozma), Didox hisobot namunasi.
6. **[H] BUSOFT:** Rizqim'ni hub'ga agregator qilib qo'shish, narxi va muddati; r_k Delivery O'zbekistonda ishlaydimi.
7. **[H] Mison, REGOS, Clopos:** "Yandex rejimi"da Rizqim manbasini yoqish yoki Partner API'ning birinchi
   foydalanuvchisi bo'lish; Clopos dasturchi hisobi (dev@clopos.com).
8. Integratsiyalar domeni (taklif `integrations.rizqim.uz`, ochiq HTTPS) va server O'zbekistonda.
9. Tarif: Yandex va Uzum Pro'da (taklif), Asosiy'da qo'shimcha narx (taklif 59 000 so'm/oy har agregator) yoki yo'q.
10. Hamkor dasturi: Partner API bepul; vendor rag'bati (taklif ~10% komissiya, 6 oy); sertifikatlash talabi.
11. Qabul muddati: tashqi POS tasdig'i 2 daqiqa, keyin avtomatik bekor qilish va pul qaytarishmi yoki operator
    qaroridami (taklif: operator, keyin avtomatik).
12. Menyu manbai: narx va stop-list tashqi POS'dan, rasm va tavsif Rizqim'dan (taklif).
13. Fiskal: 7-bo'lim jadvalini soliq maslahatchisi bilan tekshirish; platforma Payme kassasi integratsiyali joyda
    yopilsinmi; RIZQIM kuryeri naqd olgandagi chek; avans cheki; Marketpleys reyestriga kirish qarori.
14. Yuridik: mijoz ofertasiga rozilik matni (joy, POS vendori, kuryer, chet el); vendorlar bilan qayta ishlash
    shartnomasi shabloni; Partner API shartlari; ORQ-1173 bo'yicha maqom (elektron savdo platformasi yoki
    buyurtmalar agregatori) va 30 kunlik xabar tartibi.
15. 2-bo'limdagi biznes maqsad raqamlari (ulangan joylar soni).
16. Har to'lqin oxirida PR'ni ko'rib chiqish va merge.
