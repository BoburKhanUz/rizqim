# Tariflar va oylik obuna (hisob-fakturalar)

Rizqim hamkordan ikki xil daromad oladi: **onlayn buyurtmadan komissiya** va **POS obunasi** (har bir terminal uchun
oylik to'lov). Qaysi biri va qancha ekanini do'konga biriktirilgan **tarif rejasi** belgilaydi. Tarif har bir hamkor
uchun superadmin panelida tanlanadi.

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

Kodda bu bitta joyda: `Store::commissionRate()`. Buyurtma yaratilganda (`CreateOrderAction`) komissiya shu foizdan
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
orqali to'ldirish) to'laydi. Qarz kredit limitidan oshsa, komissiyadagi kabi do'kon bloklanadi (`payment_required`).

- **Bekor qilish (void):** teskari yozuv — `type = subscription`, manfiy summa; qarz kamayadi, limit ichiga qaytsa
  do'kon blokdan chiqadi. Ikki marta bekor qilib bo'lmaydi. Bekor qilingan hisob-faktura daromadga kirmaydi.
- **To'langan:** do'konning qarzi 0 yoki undan kam bo'lsa, uning barcha to'lanmagan hisob-fakturalari avtomatik
  "to'langan" bo'ladi. Superadmin qo'lda ham belgilashi mumkin (bu faqat belgi, balans o'zgarmaydi).
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
