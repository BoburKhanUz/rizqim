# Paylov tekshiruvi

**Qisqa xulosa (21.11.2026).** Paylov — Markaziy bank litsenziyasiga ega haqiqiy to'lov tashkiloti: AJ «OCTAGRAM», litsenziya №40, 10.03.2022. U POS yoki kassa dasturi sotmaydi, demak **raqib emas**. Uning rasmiy API hujjati bor: **developer.paylov.uz**. Hujjatda Telegram marketpleys uchun kerakli deyarli hamma narsa yozilgan: tayyor to'lov sahifasi, kartani saqlash, sub-merchantlar va to'lovni bir nechta oluvchiga bo'lish (split), har bir restoran nomidan fiskal chek. Shuning uchun **Paylov onlayn to'lovlar uchun mos keladi**: avval Click va Payme yonida uchinchi provayder sifatida, keyin esa ko'p restoranli split modeli uchun asosiy nomzod sifatida. **POS'dagi QR to'lov uchun hozircha mos emas.** QR bo'limi hujjatda yopiq ("Access denied"), Paylov'ning UzQR ishtirokchisi ekani topilmadi. **Zaldagi naqd savdoga chek chiqarish uchun ishlatmaslik kerak.** "Naqd to'lovni externalId va telefon raqami bilan OFD'da ro'yxatdan o'tkazish" usuli rasmiy hujjatda haqiqatan bor. Lekin uning qonuniy asosi yozilmagan, 22.10.2026 dagi yangi qoidaga ham to'g'ri kelmasligi mumkin. Ikkita jiddiy kamchilik bor. Birinchisi: ochiq sinov muhiti (sandbox) yo'q va narxlar e'lon qilinmagan. Ikkinchisi: 2025-yil dekabrida hamkori Humans ilovasi orqali kartalardan pul o'g'irlangan; Markaziy bank Octagram'ni tekshirib, materiallarni IIBB ga topshirgan.

## Belgilar

**[PRIMARY]** — manbaning o'zini o'qidim: Markaziy bank reyestr fayli, Paylov'ning o'z sayti, oferta, rasmiy API hujjati, App Store. Provayder sahifasi uning o'zi nima deyishini ko'rsatadi, gapi to'g'riligini kafolatlamaydi. **[MEDIA]** — gazeta, ma'lumot agregatori yoki qidiruv xulosasi aytgan. **[NOT FOUND]** — men ko'ra olmadim, bu "yo'q" degani emas. Hamma narsa 21.11.2026 da tekshirildi.

## 1. Paylov kim

| Fakt | Belgi va manba |
|---|---|
| Brend egasi: **AJ «OCTAGRAM»**, STIR 307993034 | [PRIMARY] Markaziy bank reyestri (fayl 03.11.2026 da yangilangan) va Paylov ofertasi |
| **To'lov tashkiloti** litsenziyasi: №40, 10.03.2022. U to'lov tizimi operatori emas. Taqqoslash uchun: Click №1 (28.03.2020), Payme №3 (04.04.2020) | [PRIMARY] [cbu.uz reyestri](https://cbu.uz/ru/payment-systems/registers/payment-organizations/), xlsx fayl |
| Ro'yxatdan o'tgan sanasi 14.12.2020. Ustav kapitali ≈28,7 mlrd so'm. Rahbar Qurbonov Qilichbek. Ta'sischilar ustunida faqat "aksiyadorlar 100%" yozilgan | [MEDIA] [orginfo.uz](https://orginfo.uz/ru/organization/7f846290fa45/) (03.01.2026 holati) |
| **Egalar (aksiyadorlar nomi) ochiq emas** | [NOT FOUND] |
| Asoschilar: Otabek Haqberdiyev (oldin bankda rahbar bo'lgan) va Alisher Tojiyev. Mijozlari 450+ biznes | [MEDIA] digitalbusiness.kz, 29.12.2025 (sahifa menga ochilmadi, qidiruv xulosasidan olindi) |
| Manzil ikki xil yozilgan. Reyestrda: Yunusobod, Kichik halqa yo'li 44/17. Ofertada: Mirzo Ulug'bek, Gulbadanbegim 26. Ofertada OKED 62010 (dasturlash) | [PRIMARY] ikkala hujjat |
| Iste'molchi ilovasi "Paylov" (P2P, kommunal to'lovlar, kartalar) bor. App Store'da sotuvchi "Octagram LLC (MChJ)" deb yozilgan, reyestrda esa AJ. Ilovada biznes yoki POS funksiyasi yo'q | [PRIMARY] [App Store](https://apps.apple.com/uz/app/paylov/id6743964462) |
| Aloqa: Telegram [@paylov_support_bot](https://t.me/paylov_support_bot), instagram/facebook paylov.uz, linkedin.com/company/paylov, kabinet my.paylov.uz | [PRIMARY] paylov.uz sayt kodi |
| Aloqa: ceo@paylov.uz, +998 90 137 44 21 | [MEDIA] orginfo.uz |

**Raqibmi?** Yo'q. Paylov restoranlar uchun POS yoki kassa dasturi sotmaydi. Saytida faqat to'lov API'si va iste'molchi ilovasi bor [PRIMARY sayt; POS mahsuloti NOT FOUND]. Bir narsani kuzatib borish kerak: hujjatda yopiq "QR Payments" bo'limi bor. Agar bu restoranlar uchun QR mahsuloti bo'lsa, u bizning POS'dagi QR funksiyamiz bilan kesishadi, lekin POS'ning o'zi bilan emas.

### 2025-yil dekabridagi hodisa

- Humans kompaniyasi 10.04.2025 dagi shartnoma bo'yicha Paylov'ning agenti edi: to'lovlar va P2P o'tkazmalar. Humans ilovasi foydalanuvchilarining kartalaridan ruxsatsiz pul yechilgan. Markaziy bank P2P'ni to'xtatishni buyurgan. 08.12.2025 dan Humans ichidagi barcha Octagram xizmatlari to'xtatilgan ([gazeta.uz, 09.12.2025](https://www.gazeta.uz/ru/2025/12/09/octagram/); [uznews.uz](https://www.uznews.uz/ru/news/105534)) [MEDIA, Markaziy bankdan iqtibos].
- Tomonlar bir-birini ayblaydi. Humans "hujumchilar Paylov API'siga SMS-kodsiz kirdi" deydi. Octagram esa "zaiflik yo'q, Humans o'ziga berilgan kalitlarni saqlay olmadi" deydi. Humans'ga 825 ta shikoyat kelgan. Boshqa xabarda 2 000+ kishi va ≈7 mlrd so'm deyilgan ([kun.uz, 09.01.2026](https://kun.uz/en/news/2026/01/09/humans-reports-825-complaints-over-fraudulent-withdrawals-linked-to-paylov)) [MEDIA].
- Markaziy bank Octagram'ni tekshirdi va 07.01.2026 da materiallarni Toshkent shahar IIBB ga yubordi. Tekshiruv xulosalari e'lon qilinmagan ([kun.uz, 07.01.2026](https://kun.uz/en/news/2026/01/07/central-bank-urges-victims-of-humans-app-fraud-to-contact-police)) [MEDIA]. Litsenziya 03.11.2026 dagi reyestrda hali ham bor [PRIMARY].

Biz uchun ma'nosi: bu "kalitni kim saqlaydi" degan janjal. Bizning API kalitlarimiz ham serverimizda turadi. Ular sizsa, saqlangan kartalardan pul yechish mumkin bo'ladi. Bu har qanday provayderda bor xavf, lekin Paylov'da u bir marta haqiqatan sodir bo'lgan.

## 2. Rasmiy hujjat qayerda

Rasmiy hujjat manzili: **https://developer.paylov.uz** (o'zbek, rus, ingliz tilida; server sanasi 11.11.2026) [PRIMARY]. Ilgari ko'rilgan paylov-docs.pages.dev shu hujjatning eski nusxasi. Unda sub-merchant, split va QR sahifalari yo'q (404). Undan foydalanmaslik kerak.

Hujjatdagi 3 bo'lim yopiq, "Access denied" chiqadi: **QR Payments**, **Didox** (elektron hujjatlar) va **P2P**. "Merchant configuration" sahifasi bo'sh [PRIMARY].

## 3. Mahsulotlar: nima bor, nima yo'q

| Savol | Javob | Belgi |
|---|---|---|
| Uzcard / Humo qabul qilish | Ha. Karta ulash, to'lov va xato kodlarida Uzcard va Humo bor | [PRIMARY] |
| Visa / Mastercard | Tayyor to'lov sahifasida "Visa/Master USD payments" namunasi bor (dollarda, currency 840). So'mda Visa qabul qilinishi aytilmagan | [PRIMARY] / so'm uchun [NOT FOUND] |
| Tayyor to'lov sahifasi (web va Mini App) | Ha, "Checkout API". Biz havola yasaymiz (my.paylov.uz/checkout/create/...). Mijoz Paylov sahifasida to'laydi. Paylov serverimizga `transaction.check` va `transaction.perform` yuboradi. Bu Click va Payme'dagi tartibga o'xshaydi. Telegram haqida alohida gap yo'q, lekin oddiy web havola Mini App'dan ochiladi | [PRIMARY]; Telegram [NOT FOUND] |
| Kartani saqlash va qayta to'lov (tokenizatsiya, recurring) | Ha. Eng xavfsiz yo'li "Card Bind": karta raqami Paylov sahifasida kiritiladi, bizga faqat cardId keladi. Keyin cardId bilan to'lov olinadi. Pulni ushlab turish (hold) ham bor, 28 kungacha | [PRIMARY] |
| Ko'p restoran (marketpleys): bir to'lovni bir nechta oluvchiga bo'lish | Ha, hujjatda bor. **Sub-merchant** yaratish API'si bor: restoran STIR'i, bank hisobi, MFO, OKED, IKPU va QQS bilan. Sub-merchantni Paylov xodimi faollashtiradi. **Split payment** bitta tranzaksiyani oluvchilarga bo'ladi, har biriga o'z chek pozitsiyalarini beradi. Ikkalasi ham alohida ruxsat bilan ochiladi. **Oluvchiga pul qachon va qanday tushishi yozilmagan** | [PRIMARY]; hisob-kitob muddati [NOT FOUND] |
| Kartaga pul chiqarish (payout, A2C) | Ha, merchant depozitidan kartaga o'tkazish. Kuryerlarga to'lash uchun kerak bo'lishi mumkin | [PRIMARY] |
| Qaytarish (refund) | Faqat **to'liq** qaytarish: `payment/cancel` faqat transactionId oladi, summa olmaydi. Qisman qaytarish yozilmagan. Bekor qilinganda webhook keladi, u Paylov paneli yoki xodimi bekor qilganda ham keladi | [PRIMARY]; qisman qaytarish [NOT FOUND] |
| Webhook'lar va ularning imzosi | Hamma callback va webhook **Basic Auth** (login/parol) bilan keladi. **Kriptografik imzo (HMAC) yo'q.** Bekor qilish webhook'i 3 marta qayta yuboriladi, karta ulash webhook'i qayta yuborilmaydi. To'lov havolasi imzolanmagan, shuning uchun summani `transaction.check` da o'zimiz tekshirishimiz shart | [PRIMARY] |
| Sinov muhiti (sandbox) | **Hujjatda yozilmagan.** Faqat production manzili ko'rsatilgan (gw2.paylov.uz misolda). Hisob Paylov bergan bir martalik token bilan ochiladi va admin faollashtirgunicha ishlamaydi. IP oq ro'yxati bor | [NOT FOUND] sandbox; qolgani [PRIMARY] |
| POS uchun dinamik QR / UzQR | "QR Payments" bo'limi bor, lekin yopiq. UzQR ishtirokchilari ro'yxatida Paylov topilmadi | [PRIMARY] bo'lim mavjud; mazmun va UzQR [NOT FOUND] |
| Narx (komissiya) | E'lon qilinmagan. Sayt FAQ'ida iste'molchi uchun "ko'p operatsiyalar komissiyasiz" deyilgan | merchant tarifi [NOT FOUND] |
| Pul tushish muddati | Yozilmagan | [NOT FOUND] |
| Ulanish talablari | Shartnoma va hujjatlar ro'yxati ochiq emas. Texnik tomoni: onboarding token, login/parol, consumer key/secret, admin faollashtiradi, IP oq ro'yxat, webhook URL va parolni Paylov'ga yuborish kerak | texnik [PRIMARY]; yuridik [NOT FOUND] |

## 4. Fiskal chek: da'vo tekshirildi

**Karta to'lovi uchun chek.** Paylov tranzaksiyasi (transactionId) bo'yicha `fiscalization/register` chaqiriladi. Unga pozitsiyalar yuboriladi: nomi, narxi, IKPU, qadoq kodi, QQS, sotuvchining STIR yoki JShShIR'i. Paylov chek havolasi va fiskal belgini qaytaradi. Chekni qaytarish (`refund`) va holatini tekshirish (`status`) ham bor [PRIMARY]. Bu Payme'ning SetFiscalData usuliga o'xshaydi va onlayn buyurtmalar uchun yaxshi.

**Naqd to'lov uchun chek.** Rasmiy hujjatda shunday yozilgan: "It registers a cash payment in the OFD system". Naqd to'lovda `externalId` va mijoz telefoni majburiy ([developer.paylov.uz/subscribe/ofd/register](https://developer.paylov.uz/subscribe/ofd/register)) [PRIMARY]. Demak avvalgi hisobotdagi [MEDIA] belgisini endi [PRIMARY] ga o'zgartirish mumkin: usul rasmiy hujjatda bor.

Hujjatda yo'q narsalar [NOT FOUND]:
- Qonuniy asos. To'lov tashkiloti o'zi o'tkazmagan naqd savdoga chek chiqarishi qaysi qoidaga asoslanadi?
- Chek kimning virtual kassasidan (TXKM) chiqadi?
- Restoran buni asosiy kassa sifatida ishlata oladimi?
- Bu 22.10.2026 dan kuchga kirgan qoidaga qanday mos keladi? Qoidaga ko'ra, buyurtma dasturi ro'yxatdagi kassaga QR-chek chiqarish uchun ulangan bo'lsa, kassaga xizmat qiluvchi markaz reyestrdan chiqariladi (batafsil: "Fiskal provayder - raqib bo'lmaganlar.md").

Yana bitta kuzatuv bor. Hujjatdagi namuna terminal raqamlari "EP..." bilan boshlanadi. Bu E-POS virtual kassasi bo'lishi mumkin, E-POS esa 2026-yil oktyabrida reyestrdan chiqarilgan. Bu faqat mening taxminim, tasdiqlanmagan. Paylov'dan so'rash kerak.

Restoran uchun yana bir shart bor: chek pozitsiyasidagi STIR yoki JShShIR'ning OFD katalogida faol shartnomasi bo'lishi kerak. Aks holda `ofd_commitent_or_subcommission_not_active` xatosi chiqadi [PRIMARY]. Ya'ni har bir restoran Paylov orqali komitent sifatida OFD'da ro'yxatdan o'tgan bo'lishi kerak.

**Xulosa:** Paylov cheki faqat Paylov orqali o'tgan onlayn to'lovlar uchun ishlatilsin. Zaldagi naqd va karta savdosi uchun avvalgi tavsiya o'zgarmaydi: RIZQIM POS'ning o'zi virtual kassa bo'lishi kerak.

## 5. Click va Payme bilan qisqa solishtirish

| Ish | Paylov | Click / Payme |
|---|---|---|
| Litsenziya | №40 (2022) [PRIMARY] | Click №1, Payme №3 (2020) [PRIMARY] |
| Mini App'dagi onlayn to'lov | Tayyor sahifa bor, ishlatsa bo'ladi | Ikkalasi bizda allaqachon ulangan (sandbox'gacha tayyor kod) |
| Ko'p restoran, to'lovni bo'lish | **Hujjatda bor** (sub-merchant + split + har restoran nomidan chek), lekin ruxsat va xodim faollashtirishi kerak | Bizning kodda hozir har restoran o'z merchant hisobiga yoki platforma hisobiga to'lov oladi (OrderMerchantAccount). Click/Payme split'i bu tekshiruvda ko'rilmadi |
| Saqlangan karta, avto-to'lov | Bor (Card Bind webview, hold) | Payme'da ham karta tokeni bor (bu tekshiruvda qayta ko'rilmadi) |
| Qisman qaytarish | Yo'q (faqat to'liq) | Payme va Click'da ham asosan to'liq bekor qilish (bu safar qayta tekshirilmadi) |
| Webhook xavfsizligi | Basic Auth, imzo yo'q | Click'da imzo (sign_string), Payme'da Basic Auth (avvalgi integratsiyamizdan) |
| Sinov muhiti | Ochiq sandbox yo'q | Payme'da test muhiti bor, Click sinovi ham bizning kodda tayyor |
| POS QR | Bo'lim yopiq | Click va Payme QR provayderlari bizning kodda bor |
| Fiskal (onlayn) | Bor, Payme'ga o'xshash | Payme SetFiscalData, Click ofd_data [avvalgi tadqiqot] |
| Fiskal (zal, naqd) | Hujjatda bor, lekin qonuniy asosi noma'lum, ishlatmaslik kerak | Yo'q |
| Narx | E'lon qilinmagan | Bu tekshiruvda ko'rilmadi |

## 6. Hozir nima qila olamiz, nima uchun rasmiy hujjat yoki shartnoma kerak

**Ochiq hujjat bo'yicha hozir yozsa bo'ladi** (kalitlar kelguncha soxta javoblar bilan test qilinadi):
1. Admin paneldagi "To'lov tizimlari" ro'yxatiga Paylov qo'shish va kalitlarini saqlash (PaymentProviders.php).
2. Tayyor to'lov sahifasiga havola yasash va `transaction.check` / `transaction.perform` qabul qiluvchi webhook. Unda Basic Auth tekshiriladi, summa va buyurtma solishtiriladi, takroriy so'rov e'tiborsiz qoldiriladi.
3. To'liq qaytarish (`payment/cancel`), bekor qilish webhook'i va `getTransactions` orqali holatni tekshirish.
4. Onlayn to'lov uchun fiskal chek (`fiscalization/register` transactionId bilan), chek holati va qaytarish cheki.
5. Kartani saqlash (Card Bind webview + webhook + status), keyinroq kerak bo'lsa.

**Paylov'dan kerak bo'ladigan narsalar:**
- Onboarding token va hisobni faollashtirish. Ularsiz bitta ham haqiqiy so'rov yuborib bo'lmaydi.
- Sinov muhiti (bo'lsa).
- Yopiq QR bo'limi.
- Sub-merchant va split ruxsati, pul oluvchilarga qachon va qanday tushishi.
- Narx va shartnoma.
- Fiskal bo'yicha yuridik javob.
- Ular webhook yuboradigan IP manzillar.

## 7. Xavflar

1. **Xavfsizlik obro'si.** 2025-yil dekabridagi Humans hodisasi bo'yicha Markaziy bank tekshiruvi tugagan, xulosa e'lon qilinmagan [MEDIA].
2. **Sandbox yo'q.** Haqiqiy pul bilan sinash kerak bo'ladi, ehtiyot bilan, kichik summalar bilan.
3. **Webhook imzosiz.** Faqat parol bilan himoyalangan. Shuning uchun har bir "to'landi" xabaridan keyin holatni API orqali qayta tekshirish shart.
4. **Faqat to'liq qaytarish.** Buyurtmadan bitta taom olib tashlansa, qisman qaytarib bo'lmaydi.
5. **Split'da pul oqimi noaniq.** Pul avval RIZQIM'ga tushadimi yoki to'g'ridan-to'g'ri restoranga? Bu bizni qonun oldida "pul yig'uvchi"ga aylantirishi mumkin. Yurist bilan tekshirish kerak.
6. **Kichik o'yinchi.** 450+ biznes [MEDIA]. Narx va pul tushish muddati ochiq emas.
7. **Fiskal xavf.** Naqd chek usulini zalda ishlatish restoranni soliq qoidasiga zid holatga qo'yishi mumkin.
8. **Hujjatdagi nomuvofiqliklar.** Ikki xil manzil, App Store'da MChJ va reyestrda AJ. Bular katta muammo emas, lekin shartnomada aniqlash kerak.

## 8. Paylov'ga yuboriladigan savollar (tayyor matn)

> Assalomu alaykum. Biz RIZQIM: Telegram Mini App'dagi ovqat marketpleysi va restoranlar uchun POS. Paylov'ni ulamoqchimiz. Iltimos, quyidagilarga yozma javob bering:
>
> 1. Sinov (sandbox) muhiti bormi? Uning manzili va test kartalari qanday? Bo'lmasa, production'da qanday xavfsiz sinaymiz?
> 2. Merchant komissiyasi qancha: Uzcard, Humo, Visa/Mastercard, saqlangan karta bilan to'lov, split, A2C bo'yicha? Oylik to'lov yoki ulanish to'lovi bormi?
> 3. Pul hisobimizga qachon tushadi (T+0 / T+1)? Split'da har bir restoranga (sub-merchant) pul to'g'ridan-to'g'ri uning bank hisobiga o'tadimi yoki avval bizning hisobimizga tushadimi?
> 4. Bizga sub-merchant yaratish va split ruxsatini berasizmi? Har bir restoran uchun qanday hujjatlar kerak va faollashtirish qancha vaqt oladi?
> 5. Qisman qaytarish (refund) qilsa bo'ladimi? Split qilingan to'lovni qanday qaytaramiz?
> 6. To'lov sahifasini Telegram Mini App ichida ochsak bo'ladimi? So'mda Visa/Mastercard qabul qilinadimi?
> 7. Webhook'larni Basic Auth'dan tashqari imzo (HMAC) bilan himoyalash mumkinmi? Webhook qaysi IP manzillardan keladi? transaction.check va perform qayta yuboriladimi?
> 8. Yopiq "QR Payments" bo'limini bizga ochib bera olasizmi? Bu dinamik QR'mi? Siz UzQR (Markaziy bank yagona QR) ishtirokchisimisiz? POS kassadan har bir hisob uchun QR yaratib, to'lov natijasini webhook orqali olsak bo'ladimi?
> 9. Fiskal: chek qaysi virtual kassa (TXKM) va qaysi terminal orqali chiqadi? Hujjatdagi "EP..." terminallar kimga tegishli?
> 10. Naqd to'lovni `fiscalization/register` (externalId + telefon) orqali ro'yxatdan o'tkazishning qonuniy asosi nima (qaror va band raqami)? Restoran zalidagi naqd savdo uchun ishlatsa bo'ladimi? 22.10.2026 dan kuchga kirgan VM 943 o'zgarishlariga (ikkilamchi tizimni kassaga integratsiya qilish taqiqi) bu mos keladimi? Yozma tushuntirish bera olasizmi?
> 11. Restoran STIR'ining "OFD katalogida komitent sifatida faol shartnoma"si qanday tuziladi? Buni siz qilasizmi?
> 12. Humans hodisasidan keyin qanday xavfsizlik choralari ko'rildi? Markaziy bank tekshiruvi natijasi qanday bo'ldi? API kalitlari uchun IP cheklov va qo'shimcha himoya bormi?
> 13. Shartnoma namunasi va ulanish uchun hujjatlar ro'yxatini yuboring. Aloqa uchun texnik va biznes mas'ul shaxs kim?
> 14. AJ «OCTAGRAM» manzili qaysi biri to'g'ri: Yunusobod, Kichik halqa yo'li 44/17 yoki Mirzo Ulug'bek, Gulbadanbegim 26?

## Manbalar

- Markaziy bank, to'lov tashkilotlari reyestri: https://cbu.uz/ru/payment-systems/registers/payment-organizations/ (xlsx, 03.11.2026)
- Paylov rasmiy API hujjati: https://developer.paylov.uz (ayniqsa /integration-overview, /merchants/paylov-payment-gateway, /subscribe/authorization, /subscribe/ofd/register, /sub_process/sub_merchant, /subscribe/split-payment/create, /subscribe/cancel-payment, /subscribe/payment/cancel-webhook, /subscribe/card-bind/overview, /subscribe/changes, /error-codes)
- Paylov ommaviy ofertasi: https://paylov.uz/pages/public-offer
- App Store: https://apps.apple.com/uz/app/paylov/id6743964462
- orginfo.uz: https://orginfo.uz/ru/organization/7f846290fa45/
- UzDaily, 04.09.2024: https://www.uzdaily.uz/en/octagram-to-issue-shares-worth-2785-billion-soums
- gazeta.uz, 09.12.2025: https://www.gazeta.uz/ru/2025/12/09/octagram/
- uznews.uz, 09.12.2025: https://www.uznews.uz/ru/news/105534
- kun.uz, 07.01.2026 va 09.01.2026 (havolalar yuqorida)
- kun.uz UzQR, 03.07.2026: https://www.kun.uz/news/2026/07/03/ozbekistonda-uzqr-tizimi-ishga-tushdi-asosiy-savollarga-javoblar-3dab63

Batafsil eslatmalar: `docs/research/research_notes/Paylov tekshiruvi/` (kompaniya_va_litsenziya.md, api_hujjat_xulosasi.md).
