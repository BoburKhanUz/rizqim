# Tariflar va oylik obuna (hisob-fakturalar)

Rizqim hamkordan ikki xil daromad oladi: **onlayn buyurtmadan komissiya** va **POS obunasi** (har bir terminal uchun
oylik to'lov). Qaysi biri va qancha ekanini do'konga biriktirilgan **tarif rejasi** belgilaydi. Tarif har bir hamkor
uchun superadmin panelida tanlanadi.

## Billing v2 (P4-21, egasi qarorlari 24–28.10.2026)

Bu bo'lim amaldagi qoidalar. Quyidagi 1–8-bo'limlar eski (P1-23) tariflar uchun saqlanadi: ular tarixda qoladi va
`billing:migrate-plans` ishga tushgunicha ularda turgan do'konlar uchun amal qiladi. O'zgargan joylar u yerda belgilangan.

### Tariflar katalogi

| Kod | Oylik | Yillik (12 oy = 10 oy) | Kassa | KDS | Funksiyalar (asosiylardan tashqari) |
|---|---|---|---|---|---|
| `start` | 149 000 | 1 490 000 | 1 | 0 | — |
| `asosiy` | 249 000 | 2 490 000 | 1 | 1 | bron, bo'nak, nasiya, KDS, xizmat haqi |
| `pro` | 449 000 | 4 490 000 | 2 | 1 | + filiallar hisoboti, Yandex/Uzum buyurtmalari, banket |

- Qo'shimcha kassa 59 000, qo'shimcha oshxona ekrani (KDS) 29 000 so'm/oy; ofitsiant telefonlari (shaxsiy ilovalar)
  cheklanmagan va bepul. Sinov 14 kun (faqat do'konning birinchi obunasi).
- "2 terminal" Asosiyda 1 kassa + 1 KDS, "3 terminal" Pro'da 2 kassa + 1 KDS deb olindi (egasi boshqacha desa, admin
  katalogda `included_pos` / `included_kds` ni o'zgartiradi).
- Katalog boshlang'ich qiymatlari `config/billing.php` (`catalog`) da; migratsiya `2026_11_04_100100` ularni `tariff_plans`
  ga `version = 2` qatorlar sifatida yozadi. Keyin narxlar admin API orqali o'zgaradi (`PUT /admin/billing/plans/{code}`,
  `system.manage` + qayta tasdiq + sabab, jurnal bilan). Narx o'zgarsa — keyingi hisob-fakturalardan; chiqqanlari o'zgarmaydi.
- Eski tariflar (`commission`, `pos`, `mixed`) `version = 1` bo'lib qoladi: o'qish mumkin, P1-23 formulasi bilan hisoblanadi.

### Funksiyalar va cheklovlar (Entitlements)

Bitta ro'yxat: `App\Enums\PlanFeature`. **Asosiy** (har tarifda, hech qachon yopilmaydi): `hall` (zal), `room_fee`
(xona puli), `guest_items` (choy-non), `shifts` (smena, Z), `rizqim_orders`. **Yopiq** (tarifga qarab): `bookings`,
`deposits`, `customer_accounts` (nasiya), `kds`, `service_charge`, `multi_branch_reports`, `aggregator_orders`, `banquet`.

Hamma tekshiruv `App\Services\Finance\Billing\Entitlements` orqali:

- v2 tarifdagi do'kon (sinov ham) — asosiylar va tarifniki; eski P1-23 tarifdagi do'kon — hammasi; hech qachon v2 tarifda
  bo'lmagan do'kon (migratsiyadan oldingi hamma do'kon, faqat marketpleysdagilar) — hammasi
  (`billing.unsubscribed_keeps_all_features`, hamma POS do'kon v2 tarifga o'tgach `false` qilinadi); v2 obunasi tugagan
  do'kon — faqat asosiylar.
- Backend yopiq **yozuvlarni** rad etadi (403 `plan_feature_missing`, `feature`, `required_plan` — shu funksiya bor eng
  arzon tarif): yangi bron, yangi bo'nak, yangi nasiya hisobi, xizmat haqi > 0, KDS ulash. Mavjud bron va bo'naklar, nasiyani
  to'lash, hisobni nasiyaga yozib to'lash ishlayveradi.
- **Hech qachon cheklanmaydi**: to'lov olish, schyot yaratish va yopish, chek va fiskal chek, smena, qaytarish, oflayn sinxron.
  Bu yo'llarda Entitlements chaqirilmaydi (testda route'lar tekshiriladi).
- `filiallar hisoboti`, `agregator`, `banket` hozircha faqat ro'yxatda (ekranlar keyin); backend ularni hali tekshirmaydi.
- Ega, xodimlar va terminallar uchun: `GET /owner/billing/entitlements` va `GET /pos/bootstrap` → `entitlements`
  ([API.md](API.md), "Billing v2"). Hamkor paneli va ilovalar "Asosiy tarifda bor" ni shundan ko'rsatadi.

### Davr: oylik va yillik

- **Oylik** — avvalgidek: oy tugagach, keyingi oyning 1-sanasida hisob-faktura chiqadi (Toshkent oyi, proratsiyasiz, sinov
  tekkan oy bepul).
- **Yillik** — 12 billing oyi **oldindan**: yillik muddat birinchi to'lanadigan oydan (sinov tekmagan) boshlanadi va
  12 oy davom etadi (birinchi oy qisman bo'lsa ham to'liq oy hisoblanadi). Hisob-faktura muddat boshlanganda chiqadi
  (`OBY-YYYYMM-<id>`, to'lov muddati chiqqandan 10 kun), summa — tarifning `yearly_fee` (standart 10 × oylik).
  Muddat tugaganda keyingisi avtomatik chiqadi.
- Yillik obuna darhol bekor qilinib, **o'sha oyda** yana yillik olinsa (masalan Start → bekor → Pro), yangi muddat o'z
  hisob-fakturasini oladi: `OBY-YYYYMM-<id>-2` (keyingi tartib raqami). Shu 12 oy uchun avval chiqqan va bekor
  qilinmagan yillik va `OBU` hisob-fakturalar summasi kredit qatori (`yearly_credit`) bilan ayiriladi (Pro 4 490 000 −
  Start 1 490 000 = 3 000 000); bekor qilingan (`void`) hisob-faktura kredit bermaydi va yangi muddatga hech qachon
  qaytarilmaydi. Kredit narxdan katta yoki teng bo'lsa (o'sha yoki arzonroq tarif) yangi hisob-faktura chiqmaydi.
- **Qo'shimcha qurilmalar yillik obunada ham har oy** (oy tugagach) alohida oylik hisob-fakturada (`OB-...`, faqat
  qo'shimcha qurilma qatorlari) hisoblanadi: qurilmalar soni yil davomida o'zgaradi, oldindan bilib bo'lmaydi.
- 50% chegirma faqat oylik asosiy narxga; yillik narx allaqachon chegirmali.
- Oylik → yillik: keyingi oydan (o'sha kuni yillik hisob-faktura chiqadi). Yillik → oylik: to'langan muddat tugagach.
  Sinov davrida (muddat hali boshlanmagan) ikkalasi ham darhol.

### Tarifni o'zgartirish (proratsiya)

- **Yuqoriga (upgrade), darhol:** oylik obunada joriy oy yangi tarif bilan hisoblanadi, oy boshidan o'zgarishgacha bo'lgan
  kunlar uchun kredit qatori: `−(yangi − eski) × kunlar / oy kunlari` (masalan 11-oktyabrda Start → Asosiy:
  249 000 − 100 000 × 10/31 = 216 741,94). Bir oyda ikki marta oshirilsa, ikkinchisi faqat bevosita oldingi tarifga nisbatan
  hisoblanadi. O'sha oyda 50% chegirma bo'lsa, kredit ham chegirmali narxdan: `× (1 − 50%)` (masalan 11-noyabrda
  Start → Asosiy, 30 kunli oy: 249 000 − 124 500 − 100 000 × 10/30 × 50% = 107 833,33 = 149 000 × 10/30 × 50% +
  249 000 × 20/30 × 50%; egasi uchun adolatli, chegirmasiz oyda o'zgarish yo'q). Yillik obunada darhol
  `OBU-YYYYMM-<id>-<n>` hisob-faktura: `(yangi yillik − eski yillik) × qolgan oylar / 12`
  (joriy oy ham qolgan oyga kiradi); muddat tugashi o'zgarmaydi.
- **Yuqoriga, keyingi davrdan:** oylikda oy oxirida, yillikda muddat oxirida.
- **Pastga (downgrade):** har doim davr oxirida (oylik — oy, yillik — to'langan muddat), "darhol" so'ralsa ham.
- **Bekor qilish:** darhol (joriy oy hisoblanmaydi, yillik to'lov qaytarilmaydi — kerak bo'lsa moliya hisob-fakturani
  bekor qiladi yoki tuzatadi) yoki davr oxirida (yillikda — muddat oxirida).
- Eski ↔ yangi tarif o'rtasida o'zgarish P1-23 qoidasi bilan (darhol bo'lsa butun oy yangi tarif bilan, kreditsiz).
- Sinov: admin sinovdagi obunaning sinovini uzaytirishi mumkin (ko'pi bilan bugundan 90 kun; tekkan oylar bepul).

### Oylik hisob-faktura qatorlari (v2)

```
base       = tarifning oylik narxi (yillik muddat ichidagi oyda — yo'q)
discount   = −base × 50%, agar oldingi oyda komissiya > 1 000 000 so'm bo'lsa
proration  = darhol oshirilgan oyda: −(yangi − eski) × kunlar/oy kunlari
extra_pos  = max(0, kassalar − tarifdagi) × 59 000
extra_kds  = max(0, KDS − tarifdagi) × 29 000
price_lock = migratsiyadan keyingi birinchi oy eski narxdan qimmat bo'lmasin (farq minus qilinadi)
```

- `price_lock` faqat migratsiya tanlagan tarif (yoki undan pastroq) uchun: admin darhol yuqoriroq tarifga o'tkazsa,
  narx qulfi tushadi va oy yangi tarif narxida (proratsiya bilan) hisoblanadi.

- Kassa va KDS **turi bo'yicha alohida**: oy davomida bir vaqtda faol bo'lgan eng ko'p soni (`TerminalCounter`).
- Chegirma: "oldingi oy" — hisob-faktura oyidan oldingi Toshkent oyi; **ledger'dan**: o'sha oyda yozilgan barcha `COMMISSION`
  yozuvlari (qarzga va hamyondan), shu buyurtmalar bo'yicha keyinchalik qaytarilgan komissiya ayiriladi (to'liq
  qaytarishda — hammasi, qisman qaytarishda — faqat qaytarilgan ulushi, buyurtma butunlay chiqarilmaydi); referal
  mukofoti komissiya emas.
  Aynan 1 000 000 — chegirma yo'q, 1 000 000,01 — bor. Faqat asosiy narxga (qo'shimcha qurilmalarga emas).
- Sinov = hisob-faktura yo'q; summa 0 bo'lsa ham chiqmaydi.

### Komissiya kanal bo'yicha

| Kanal (`orders.commission_channel`) | Standart |
|---|---|
| `rizqim_courier` — RIZQIM kuryeri yetkazadi | 15% |
| `own_delivery` — ovqatxona o'zi yetkazadi | 10% |
| `pickup` — olib ketish | 5% |
| `pos` — zal va kassadagi olib ketish | 0% |
| `aggregator` — Yandex, Uzum (o'z komissiyasini oladi) | 0% |

- Buyurtma yaratilganda (`CreateOrderAction`) kanal, foiz va summa buyurtmaga yoziladi (`commission_channel`,
  `commission_rate`, `commission_amount`); yetkazilganda ledger aynan shu summani oladi. Ochiq buyurtmalar o'z
  snapshot'ini saqlaydi, stavka o'zgarishi faqat yangi buyurtmalarga.
- Yetkazishda kim yetkazishi buyurtma yaratilganda noma'lum, shuning uchun do'kon sozlamasi `stores.delivery_courier`
  (`own` | `fleet`, admin o'rnatadi) hal qiladi: `fleet` — 15%, `own` — 10%.
- Qaysi foiz (birinchi mos kelgani): 1) do'konning shu kanal uchun o'z stavkasi (`stores.commission_overrides`, admin,
  jurnal bilan); 2) onlayn kanallarda eski qoida: joriy P1-23 tarifning `commission_percent`, bo'lmasa do'konning
  yagona foizi `stores.commission_rate` (bo'sh bo'lmasa); 3) `config/billing.php` → `commission`.
- `stores.commission_rate` endi bo'sh bo'lishi mumkin: bo'sh — kanal stavkalari; qiymat — barcha onlayn kanallar uchun
  yagona foiz (oldingi tartib). Mavjud do'konlarda u 10 bo'lib turibdi, shuning uchun migratsiyagacha hech narsa
  o'zgarmaydi. **Do'kon yaratish formalari (Filament) hali 10 ni yozadi** — yangi do'kon kanal stavkalarida bo'lishi uchun
  foizni bo'sh qoldirish yoki admin API'da `flat_rate: null` qilish kerak.
- `Store::commissionRate()` (eski ekranlar uchun bitta foiz) — `own_delivery` stavkasi.

### Qarzning ikki qismi (kredit limiti faqat komissiya qarzi)

`debt_balance` — egasi ko'radigan va to'laydigan bitta qarz hisobi (o'zgarmagan). Uning ichida
`stores.subscription_debt` — to'lanmagan obuna hisob-fakturalari (`amount − paid_amount`, bekor qilinmaganlari).
Qolgani — **komissiya qarzi** (`debt_balance − subscription_debt`); Mini App buyurtmalarini to'xtatadigan kredit limiti
(`Store::exceedsCreditLimit`, `is_order_locked`, `CreateOrderAction`, `LedgerService` bloklash/ochish) **faqat shuni**
hisoblaydi. Obuna hisob-fakturasi do'konni hech qachon bloklamaydi — uning o'rniga eslatma va cheklov tartibi.

- **To'lov eng eski hisob-fakturalarni birinchi yopadi** (to'lov muddati, keyin oy bo'yicha), qolgani komissiya qarziga:
  online va qo'lda to'ldirish (`reduceDebt`, `creditTopup`), RIZQIM yig'gan buyurtma puli (keshbek, Click, Payme) va
  payoutdan ushlash (P4-23, "Netting") ham shu yo'ldan. To'liq yopilgan hisob-faktura darhol `paid`, obuna `past_due` / `locked` dan chiqadi.
- Hisob-faktura chiqqanda do'konda kredit (manfiy qarz) bo'lsa, u darhol hisob-fakturani yopadi.
- Obuna qismi qarzdan oshmaydi: komissiya qaytarilganda hosil bo'lgan kredit eng eski hisob-fakturalarni yopadi. To'langan
  yoki qisman to'langan hisob-faktura endi bekor qilinmaydi (review W2, quyida "Netting" 6-band); oldin shunday bekor
  qilinganlarning to'langan qismi kreditga aylangan.
- "To'landi" (qo'lda belgi, ledger o'zgarmaydi) eslatmani to'xtatadi; ledger to'lamagan qismi `subscription_debt` da qoladi
  va keyingi to'ldirish uni yopadi.
- P4-23: RIZQIM yig'gan pul (keshbek, Click, Payme) yoki payout bilan yopilgan qarz keyin qaytarilsa (buyurtma
  qaytarilganda), u yopgan **hisob-faktura qayta ochiladi** (`paid` → `issued`, obuna holati qayta hisoblanadi); bekor
  qilingan hisob-faktura ochilmaydi. Batafsil — quyida "Netting".
  - Qayta ochilgan hisob-faktura `issued` ga faqat uni ledger to'liq to'lagan bo'lsa qaytadi (`paid_amount ≥ amount`).
    Qo'lda "To'landi" belgilangani `paid` bo'lib qoladi: faqat `paid_amount` kamayadi va qolgani `subscription_debt` da.
  - Pul yopgan hisob-faktura keyin bekor qilingan bo'lsa (uning to'langan puli kreditga aylanib, boshqa hisob-fakturalarni
    yopgan), o'rniga bekor qilinganidan keyin to'langan hisob-fakturalar (eng oxirgisi birinchi) qayta ochiladi — qarz
    obuna qismidan ortiq bo'lgan miqdorgacha. Shunday qilib bu ulush komissiya qarziga (kredit limitiga) o'tmaydi.
- Migratsiyagacha `subscription_debt = 0` (eskicha: butun qarz limitga kiradi); `billing:migrate-plans` har do'konning
  qarzini ikkiga ajratadi va faqat hisob-faktura tufayli bloklangan do'konni ochadi. Deploydan keyin chiqqan yangi
  hisob-fakturalar darhol ajratiladi.

### Netting: qarz avval ushlanadi (P4-23, egasi qarori 28.10.2026)

RIZQIM ovqatxona uchun yig'gan pul ovqatxonaga yetishidan oldin uning qarzini yopadi (`App\Services\Finance\Netting\NettingService`):

1. **Tartib:** avval eng eski obuna hisob-fakturalari (to'lov muddati, keyin oy — `SubscriptionDebt` qoidasi), keyin
   komissiya qarzi. Qarzdan ortig'i ovqatxonaga.
2. **Qachon:** payout tasdiqlanganda (kartaga faqat qolgani o'tadi; kartada "Hisoblangan — Ushlangan qarz — O'tkaziladi")
   va Click, Payme yoki keshbek bilan to'langan buyurtma puli hamyonga yozilayotganda (avval faqat keshbek qarzni yopardi).
3. **Ledger:** har yopilgan hisob-faktura va komissiya qismi qarz ledger'ida alohida `topup` yozuvi; hisob-faktura yozuvida
   `subscription_invoice_id`, payout yozuvlarida `payout_request_id`. Hamyonda payout ikki `payout` yozuvi (ushlangan va
   kartaga).
4. **Qaytarish:** buyurtma to'liq yoki qisman qaytarilsa, o'sha ulushda qarzga qaytadi va tegishli hisob-faktura qayta ochiladi.
   Qisman qaytarish (qaytarish so'rovlari) komissiya va hisob-kitobni pro-rata qaytaradi, har so'rov bir marta
   (`order_ledger_reversals`). Yetkazilgan buyurtmaning hisob-kitobi yozilmay qolgan paytda (`settlement_failed_at`)
   tasdiqlangan qism ledger'da qaytaradigan narsa topmaydi (`execution.ledger_reversal_deferred`): hisob-kitobni yozgan
   qayta urinish (`SettlementRecoveryService`) o'sha zahoti shunday har qismni qaytaradi — jamlanib, har so'rov bir marta,
   go'yo u hisob-kitobdan keyin tasdiqlangandek (`execution.ledger_reversal_on_retry`).
5. **Qo'lda to'lov** (hisob raqami, naqd) — Moliya → Qo'lda tuzatish → "To'lov qabul qilindi": xuddi shu tartibda yopadi.
   1 000 000 so'm va undan katta tuzatish yoki payoutni ikki xil xodim tasdiqlaydi. Bo'lib yuborish yordam bermaydi: oxirgi
   24 soatda bitta xodim tasdig'i bilan qo'llangan tuzatishlar (shu xodimniki — istalgan do'konda, shu do'konniki —
   istalgan xodimdan; eski superadmin panelidagi qo'lda to'ldirishlar ham) yangisi bilan qo'shib sanaladi.
6. **Hisob-fakturani bekor qilish** pulni bir xodim so'zi bilan siljitmaydi: to'langan yoki qisman to'langan (yoki
   "To'landi" belgili) hisob-faktura bekor qilinmaydi (422 `invoice_paid`) — aks holda to'langan pul kreditga aylanardi;
   pul qaytarilsa — Qo'lda tuzatish orqali. To'lanmagan 1 000 000 so'm va undan kattasini ikki xil xodim bekor qiladi
   (birinchisi so'rov, ikkinchisi bekor qiladi). Eski superadmin paneli bunday hisob-fakturani bekor qilmaydi va
   1 000 000 so'm dan (24 soat ichidagi bo'laklar bilan) qo'lda to'ldirishni rad etadi: "Boshqaruv markazida ikki xodim
   tasdiqlaydi".

API va ekranlar: [API.md](API.md), "Moliya (P4-23)".

### Eslatmalar va cheklov (dunning)

`SendBillingRemindersJob` har kuni 10:05 (Toshkent), `App\Services\Finance\Billing\DunningService`:

- to'lov muddatidan keyin **1, 3 va 7-kuni** eslatma: do'konning admin chatiga va egalarga Telegram (navbat orqali), hamkor
  paneli uchun `entitlements.notice` (jonli hisoblanadi; hisob-faktura raqami va summasi faqat `finance.view` li a'zoga,
  boshqa xodimlar va terminallar faqat darajani, kunlarni va cheklov sanasini ko'radi);
- **8-kundan** (muddat + 7 kundan keyingi kunning 00:00 i, Toshkent) v2 obuna `locked`: yangi sozlamalar va tarifdan
  ortiq imkoniyatlar yopiladi, egaga bir marta xabar. Masalan, muddat 11.11 — eslatmalar 12, 14, 18.11, cheklov 19.11 dan.
  Eski P1-23 tarif faqat `past_due` bo'ladi (eslatmalar bor, cheklov yo'q). Cheklovni faqat **v2 tarif bo'yicha chiqqan**
  hisob-faktura beradi: migratsiyadan oldingi P1-23 hisob-faktura to'lanmay qolsa, do'kon v2 tarifga o'tgan bo'lsa ham
  faqat `past_due` (eslatma bor, `lock_at` yo'q).
- Cheklangan do'konda rad etiladi (403 `subscription_locked`): xizmat haqi va joy puli, zal va stollar, xona narxlari,
  choy-non sozlamalari, sexlar; tarifdagidan ortiq yangi terminal (403 `device_limit`; eskisini o'chirib o'rniga ulash
  mumkin — buzilgan kassa almashtiriladi). **Ochiq qoladi:** to'lov, schyot, chek, smena, qaytarish, printerlar,
  filialning POS sozlamalari, menyu, stop-list, xodimlar.
- **To'lov darhol ochadi**: to'lov hisob-fakturani yopishi bilan obuna holati qayta hisoblanadi (`subscription.unlocked`
  jurnalda), Entitlements esa holatni jonli hisoblaydi — ish kutilmaydi.
- Har bir qadam hisob-faktura bo'yicha bir marta (`subscription_dunning_events`, yagona); qayta ishga tushirish hech narsani
  ikki marta yubormaydi; kunlar o'tkazib yuborilsa faqat oxirgi qadam yuboriladi, oldingilari xabarsiz yoziladi.
- Obuna holatlari: `trialing`, `active`, `past_due`, `locked`, `cancelled` (`locked_at` — qachon cheklangan).

### Mavjud do'konlarni o'tkazish: `php artisan billing:migrate-plans [--dry-run] [--store=ID]`

Avval `--dry-run`: u xuddi haqiqiy ishni tranzaksiya ichida qilib, oxirida bekor qiladi — hisobot aynan haqiqiy ish
qiladigani. Har do'kon uchun:

1. Pullik P1-23 tarif (`pos`, `mixed`, ...) → v2 tarif **keyingi oydan**: do'kon bron, nasiya, KDS yoki xizmat haqi
   ishlatsa — Asosiy, aks holda Start (hech kimning ishlatayotgan funksiyasi yopilmaydi). Joriy oy eski narxda; yangi
   tarifdagi **birinchi oy eski narxdan qimmat emas** (`price_lock` qatori). Hisobotda eski narx, yangi narx va birinchi oy.
2. Faqat komissiya tarifi (`commission`) → darhol tugaydi (u hech narsa hisoblamagan).
3. Komissiya: eski foiz 10% (eski standart) bo'lsa — kanal stavkalariga (o'z yetkazishi 10%, olib ketish 5%); boshqa foiz
   (tarifniki yoki do'konniki) — do'konning yagona foizi sifatida saqlanadi (keyin admin o'zgartiradi).
4. Qarz ikkiga ajratiladi; faqat obuna qarzi tufayli bloklangan do'kon ochiladi.
5. Terminali bor, tarifi yo'q do'kon — hisobotda (`terminals_without_plan`), tarifni admin biriktiradi.

Har o'zgarish `billing.migrated` bilan jurnalga yoziladi. Qayta ishga tushirish hech narsani o'zgartirmaydi.

### Admin API

`routes/api_admin_billing.php` (`/api/v1/admin/billing/...`): katalog, obunalar ro'yxati, do'kon billing kartasi, tarif
biriktirish, davr, bekor qilish, sinovni uzaytirish, komissiya — [API.md](API.md), "Billing v2".

## 1. Tarif rejasi

`tariff_plans` jadvali. Har bir reja:

| Maydon | Ma'nosi |
|---|---|
| `name`, `code` | Nomi va ichki kodi (kod yagona, masalan `pos`) |
| `is_active` | O'chirilgan tarifni yangi do'konga biriktirib bo'lmaydi (eskilari ishlashda davom etadi) |
| `commission_percent` | Onlayn buyurtmadan komissiya, %. **Bo'sh** — do'kon sozlamasidagi foiz (`stores.commission_rate`) olinadi. **0** — komissiya yo'q |
| `monthly_base_fee` | Oylik asosiy to'lov, UZS. Bo'sh yoki 0 — yo'q |
| `monthly_fee_per_terminal` | Bir terminal uchun oylik narx, UZS. Bo'sh yoki 0 — yo'q |
| `included_terminals` | Asosiy to'lovga kirgan terminallar soni (ular uchun alohida to'lanmaydi) |
| `trial_days` | Sinov davri, kun |
| `description` | Tavsif (ega ham ko'radi) |

Shunday qilib reja **faqat komissiya**, **faqat obuna** (komissiya 0 va oylik to'lov) yoki **ikkalasi** bo'lishi mumkin.

### Boshlang'ich tariflar

Migratsiya uchta tarif qo'shadi. **Narxlar vaqtinchalik** — haqiqiy narxlarni ega superadmin panelida qo'yadi.

| Kod | Nomi | Komissiya | Asosiy to'lov | Terminal narxi | Kirgan terminal | Sinov |
|---|---|---|---|---|---|---|
| `commission` | Faqat komissiya | do'kon foizi (hozirgi tartib, standart 10%) | — | — | 0 | 0 kun |
| `pos` | POS obuna | 0% | — | 150 000 UZS | 0 | 14 kun |
| `mixed` | Aralash | 5% | 100 000 UZS | 100 000 UZS | 1 | 14 kun |

Mavjud do'konlarga hech qanday tarif avtomatik biriktirilmaydi: tarifsiz do'kon avvalgidek o'z komissiya foizi bilan
ishlaydi, oylik to'lov yo'q.

## 2. Komissiya qaysi foizdan olinadi

```
komissiya foizi = joriy tarifning commission_percent (agar belgilangan bo'lsa)
                  aks holda do'konning commission_rate (bo'sh bo'lsa 10)
```

P4-21: endi kanal bo'yicha (yuqoridagi "Komissiya kanal bo'yicha"); eski tarif va do'konning yagona foizi onlayn kanallarda
avvalgidek amal qiladi. Kodda: `App\Services\Finance\Billing\CommissionPolicy` (`Store::commissionRate()` — o'z yetkazishi stavkasi). Buyurtma yaratilganda (`CreateOrderAction`) komissiya shu foizdan
hisoblanib buyurtmaga yoziladi (`orders.commission_amount`), yetkazilganda `LedgerService` aynan shu summani oladi.
Ya'ni tarif yoki foiz o'zgarsa, faqat **yangi** buyurtmalarga ta'sir qiladi. Sinov davrida ham tarif komissiyasi
amal qiladi (sinov faqat oylik to'lovni bekor qiladi). Tarifsiz do'kon uchun hech narsa o'zgarmagan.

## 3. Obuna

`store_subscriptions` jadvali: do'kon qaysi tarifda va qachondan. Do'konda bir vaqtda bitta joriy obuna bo'ladi
(holati `cancelled` bo'lmagan qator); tugaganlari tarix sifatida qoladi.

Holatlar: `trialing` (sinov davri), `active` (faol), `past_due` (to'lov muddati o'tgan hisob-faktura bor), `cancelled`
(tugagan).

- **Birinchi biriktirish** — darhol boshlanadi. Sinov davri faqat do'konning **birinchi** obunasiga beriladi.
- **Tarifni o'zgartirish** (superadmin tanlaydi):
  - *Keyingi oydan* (standart): joriy oy eski tarif bilan hisoblanadi, 1-sanadan yangi tarif boshlanadi (yangi qator).
  - *Darhol*: eski qator hozir tugaydi, yangi tarif oy oxirigacha davom etadi va **butun oy faqat yangi tarif bilan**
    hisoblanadi (bir oyga bitta hisob-faktura). Qolgan sinov kunlari yangi tarifga o'tadi, yangi sinov berilmaydi.
  - Joriy tarifni qayta tanlash kutilayotgan o'zgarish yoki bekor qilishni olib tashlaydi.
- **Bekor qilish**: *oy oxirida* — shu oy hisoblanadi, keyin obuna tugaydi; *darhol* — obuna hozir tugaydi va shu oy
  uchun hisob-faktura chiqmaydi.
- **Tugagan oy avval hisoblanadi.** Tarifni o'zgartirish yoki bekor qilish oy tugagandan keyin, lekin kunlik ish (00:15)
  hali ishlamaganida qilinsa (masalan 1-noyabr 00:05 da), avval tugagan oy(lar) odatdagidek, o'sha oyda amalda bo'lgan
  tarif bilan hisoblanadi, keyin o'zgarish qo'llanadi. Shu sababli hech bir oy yo'qolmaydi va davr hech qachon boshlanishidan
  oldin tugamaydi (`current_period_end > current_period_start` — kodda tekshiriladi, buzilsa xato).
- **Do'kon o'chirilsa** (superadmin "O'chirish", soft delete): uning tugagan oylari hisoblanadi (qarz hisobiga yoziladi),
  keyin obuna darhol tugaydi; o'chirish paytidagi joriy oy hisoblanmaydi. Biror sababga ko'ra ochiq qolgan bo'lsa, kunlik
  ish uni xuddi shunday yopadi (o'chirishdan oldin tugagan oylar hisoblanadi), har kuni xato bermaydi.

## 4. Oylik hisob-faktura qanday hisoblanadi

**Davr** — Toshkent vaqti bo'yicha kalendar oy (`config/billing.php`, `timezone`). Obunaning birinchi davri u
boshlangan paytdan oy oxirigacha. Hisob-faktura oy tugagandan keyin (keyingi oyning 1-sanasida) chiqadi.

```
terminallar = davr ichida bir vaqtda faol bo'lgan POS/KDS terminallarining eng ko'p soni
qo'shimcha  = max(0, terminallar − included_terminals)
summa       = monthly_base_fee + qo'shimcha × monthly_fee_per_terminal
```

Barcha hisob `App\Support\Money` bilan, tiyingacha aniq (float ishlatilmaydi).

- **Proratsiya yo'q.** Davr to'liq hisoblanadi, hatto oy o'rtasida boshlangan bo'lsa ham. Kerak bo'lsa superadmin
  hisob-fakturani bekor qilishi mumkin.
- **Sinov davri:** sinov davri oyning biror qismiga to'g'ri kelsa, o'sha oy uchun hisob-faktura **chiqmaydi**.
  Masalan, 25-oktyabrda 14 kunlik sinov bilan boshlangan obuna: oktyabr va noyabr (sinov 8-noyabrgacha) bepul,
  birinchi hisob-faktura dekabr uchun (1-yanvarda).
- Summa 0 bo'lsa (masalan "Faqat komissiya" tarifi) hisob-faktura chiqmaydi.
- Har bir do'kon va oy uchun bitta hisob-faktura (`subscription_invoices`, `store_id + period` yagona).
  Raqami: `OB-YYYYMM-<do'kon id 6 xonali>`, masalan `OB-202610-000042`.
- Qatorlar (`lines`): asosiy to'lov va terminallar (soni, tarifga kirgani, narxi). To'lov muddati — oy tugaganidan
  keyin 10 kun (`invoice_due_days`).

### Qaysi qurilma "terminal" hisoblanadi

`devices` jadvalidagi do'konga ulangan `pos` (kassa) va `kds` (oshxona ekrani) turidagi qurilmalar. Xodimlarning
shaxsiy ilovalari (kuryer, hamkor, mijoz ilovasi, brauzer) hisoblanmaydi.

Terminal ulangan paytdan (`created_at`) o'chirilgan paytgacha (`revoked_at`) faol. Hisobga **davr ichida bir vaqtning
o'zida faol bo'lgan terminallarning eng ko'p soni** olinadi (`App\Services\Finance\TerminalCounter`):

- eski terminal o'chirilib, o'rniga yangisi ulansa — bitta hisoblanadi;
- oy oxirida o'chirilgan terminal o'sha oy uchun baribir hisoblanadi (oy oxiridagi holat emas, shuning uchun oy
  oxirida o'chirib, 1-sanada qayta ulash to'lovni kamaytirmaydi);
- davrdan oldin o'chirilgan yoki davr tugaganidan keyin ulangan terminal hisoblanmaydi.

## 5. Ledger (qarz hisobi)

Chiqqan hisob-faktura do'konning qarz hisobiga (`debt_balance`) yoziladi: `store_transactions` jadvalida
`type = subscription`, musbat summa, `reference_id` = hisob-faktura `uuid`. Hamyondan (`wallet_balance`) olinmaydi.
Ega uni moliya sahifasida va `GET /owner/finance` da boshqa yozuvlar bilan birga ko'radi va odatdagidek (Click/Payme
orqali to'ldirish) to'laydi. ~~Qarz kredit limitidan oshsa, komissiyadagi kabi do'kon bloklanadi (`payment_required`).~~ P4-21: obuna qarzi
endi bloklamaydi — kredit limiti faqat komissiya qarzini hisoblaydi ("Qarzning ikki qismi").

- **Bekor qilish (void):** teskari yozuv — `type = subscription`, manfiy summa; qarz kamayadi, limit ichiga qaytsa
  do'kon blokdan chiqadi. Ikki marta bekor qilib bo'lmaydi. Bekor qilingan hisob-faktura daromadga kirmaydi.
- **To'langan:** do'konning qarzi 0 yoki undan kam bo'lsa, uning barcha to'lanmagan hisob-fakturalari avtomatik
  "to'langan" bo'ladi (P4-21: bundan tashqari har bir to'lov eng eski hisob-fakturalarni darhol yopadi). Superadmin qo'lda ham belgilashi mumkin (bu faqat belgi, balans o'zgarmaydi).
- **Muddati o'tgan:** to'lov muddati o'tgan to'lanmagan hisob-faktura bo'lsa, obuna holati `past_due` bo'ladi.

## 6. Avtomatik ishga tushish

`App\Jobs\BillSubscriptionsJob` har kuni 00:15 da (Toshkent vaqti) scheduler orqali ishlaydi:

- 1-sanada o'tgan oy uchun hisob-fakturalar chiqadi;
- boshqa kunlarda faqat o'tkazib yuborilgan ishga tushishlarni to'ldiradi (bir necha oy o'tkazib yuborilgan bo'lsa,
  har oy alohida) va "to'langan" / "muddati o'tgan" holatlarini yangilaydi;
- qayta ishga tushirish hech narsa o'zgartirmaydi (idempotent).

Qo'lda: `php artisan billing:run`.

## 7. Superadmin paneli ("Tariflar" bo'limi)

- **Tarif rejalari** — yaratish va tahrirlash. Narx o'zgarsa, shu tarifdagi barcha do'konlarning **keyingi**
  hisob-fakturalaridan amal qiladi; komissiya o'zgarsa — yangi buyurtmalardan. Ishlatilgan tarifni o'chirib
  bo'lmaydi: uni "faol emas" qiling.
- **Do'kon obunalari** — "Tarif biriktirish" (do'kon, tarif, keyingi oydan yoki darhol), "Tarifni o'zgartirish",
  "Bekor qilish". Filtr bilan tarixni ham ko'rish mumkin. Har bir o'zgarish harakatlar jurnaliga yoziladi.
- **Hisob-fakturalar** — barcha do'konlar bo'yicha ro'yxat, "Bekor qilish" (sabab bilan, ledger'da teskari yozuv) va
  "To'landi".
- **Rizqim daromadi** — oylar bo'yicha komissiya va obuna daromadi hamda tanlangan oy uchun restoranlar bo'yicha.
  Komissiya: oyda yetkazilgan va qaytarilmagan buyurtmalarning `commission_amount` yig'indisi (yetkazilgan vaqt —
  birinchi `delivered` holat yozuvi). Obuna: shu oy uchun chiqqan, bekor qilinmagan hisob-fakturalar.

### Narxni qanday o'zgartirish kerak

1. Superadmin panel → Tariflar → Tarif rejalari → kerakli tarifni oching.
2. Komissiya, asosiy to'lov, terminal narxi, tarifga kirgan terminallar va sinov kunlarini kiriting va saqlang.
3. Yangi narxlar keyingi oy hisob-fakturalaridan, yangi komissiya yangi buyurtmalardan amal qiladi.

Bitta do'konga alohida narx kerak bo'lsa, yangi tarif yarating va faqat o'sha do'konga biriktiring.

## 8. Ega uchun

- `GET /api/v1/owner/subscription` — joriy tarif, holat, davr, sinov tugashi, keyingi oydan tarif, amaldagi komissiya
  foizi, terminallar (hozir faol va shu davrda hisoblangan) va joriy davr uchun taxminiy hisob-faktura. Tarifsiz
  do'kon uchun `subscription: null`.
- `GET /api/v1/owner/invoices?status=&period=YYYY-MM&per_page=` — hisob-fakturalar (yangi oy birinchi).
- Ikkalasi ham `finance.view` ruxsatini talab qiladi (standart bo'yicha faqat ega; ega menejerga ham berishi mumkin),
  terminaldagi xodim PIN sessiyasi bilan ishlamaydi, faqat o'z do'koni ko'rinadi.
- Do'kon panelida "Tarif va hisob-fakturalar" sahifasi (faqat ko'rish uchun).
- P2-12 (hamkor web paneli): qarz hisobi va hamyon tarixi sahifalab — `GET /api/v1/owner/finance/ledger?type=&from=&to=`
  (bu yerdagi `subscription` yozuvlari va ularning bekor qilinishi shu ro'yxatda), `GET /api/v1/owner/finance/wallet`,
  pul yechish so'rovlari `GET /api/v1/owner/payouts` (karta raqami niqoblangan) va mijozlarning onlayn to'lovlari
  `GET /api/v1/owner/payments`. Hammasi `finance.view` bilan, faqat o'qish uchun, pul — ikki xonali satr, `from`/`to` —
  do'kon vaqt mintaqasidagi kunlar ([API.md](API.md), 12-bo'lim).
- `GET /api/v1/owner/finance` dagi `commission_rate` endi amalda olinadigan foizni ko'rsatadi (tarif yoki do'kon foizi).
- P2-30: `GET /api/v1/owner/finance` ham `finance.view` bilan (avval faqat ega roli; ega menejerga berishi mumkin),
  terminal PIN sessiyasidan emas. Pul yechish (`POST /owner/payouts`) va to'ldirish avvalgidek faqat ega.
- P2-30 (xavfsizlik): karta raqami hech qaysi javobda to'liq qaytmaydi. `POST /owner/payouts` javobi va
  `GET /owner/finance` dagi `payout_requests` — `GET /owner/payouts` shakli (`card`: `"8600 **** **** 9012"`; eski
  mijozlar uchun `card_number` ham bor, lekin u ham niqoblangan), hamyon va qarz yozuvlari izohidagi karta raqamlari ham
  niqoblanadi. To'liq raqam faqat bazada qoladi (superadmin pulni o'tkazishi uchun).
- `GET /owner/invoices`, `/owner/finance/ledger`, `/owner/finance/wallet`, `/owner/payouts`, `/owner/payments` da `page`
  (Laravel sahifasi) OpenAPI'da ham yozilgan (P2-30). Pul summalari bu yangi endpointlarda ikki xonali satr; eski
  `GET /owner/finance` balanslari va hisob-faktura summalari (`/owner/invoices`, `/owner/subscription`) v1 qoidasiga ko'ra
  son bo'lib qoladi.
