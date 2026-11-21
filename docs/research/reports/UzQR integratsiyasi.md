# UzQR integratsiyasi

**Qisqa xulosa (21.11.2026).** Dinamik UzQR uchun bankni tanlab, bank bilan alohida API ulash shart emas. Asosiy topilma shu. Dinamik QR'ning API kalitini restoranning o'zi **uzqr.uz** kabinetida (Markaziy bank tizimi) yaratadi. Keyin kalitni kassa dasturi sotuvchisiga, ya'ni bizga beradi. Restoran bank bilan faqat bir marta ishlaydi: hisob ochadi va UzQR'ga ro'yxatdan o'tadi. Uning qaysi bankda ekani texnik jihatdan farq qilmaydi. Pul doim **restoranning o'z hisob raqamiga** tushadi, bizning hisobga tushmaydi. Demak Rizqim **bitta** integratsiya qiladi, ya'ni UzQR operatorining API'siga ulanadi, va u hamma restoranlar uchun ishlaydi. Boshqa kassa dasturlari shu yo'l bilan allaqachon ishlayapti: SetRetail10, R-Keeper, REGOS. Ularning hujjatlaridan bilamiz: QR yaratish, holatni so'rash, bekor qilish va qaytarish bor, qaytarish qisman ham bo'ladi. **Sinov muhiti** ham bor. Chekdan keyin UzQR'ga fiskal chek havolasi yuboriladi. **Muammo bitta:** UzQR API'sining hujjati ochiq joyda topilmadi, uni operatordan (Markaziy bank, MUNIS) so'rab olish kerak. Fiskal chekni Rizqim chiqaradi: UzQR ham, bank ham chek chiqarmaydi. Bitta istisno bor: Ipak Yo'li banki o'z sahifasida "chek avtomatik chiqadi" deb yozgan, buni bankdan aniqlash kerak.

## Belgilar

**[PRIMARY]** — manbaning o'zini o'qidim: lex.uz, cbu.uz, bank sahifasi yoki ofertasi, kassa sotuvchisining o'z hujjati. **[MEDIA]** — gazeta yoki vositachi sayt aytgan. **[NOT FOUND]** — men topa olmadim, bu "yo'q" degani emas. Hammasi 21.11.2026 da tekshirildi. Batafsil dalillar va havolalar `docs/research/research_notes/UzQR integratsiyasi/` papkasida.

## 1. UzQR nima, qonunda nima deyilgan

| Savol | Javob | Belgi |
|---|---|---|
| Asos | Prezident Farmoni **PF-246, 10.12.2025**. 01.07.2026 dan savdo va xizmat ko'rsatuvchi **barcha yuridik shaxslar** yagona QR orqali to'lov qabul qilishi shart. Qabul qilmaslik savdo qoidalarini buzish hisoblanadi ([lex.uz/docs/7897677](https://lex.uz/docs/7897677)). Sahifada 16.11.2026 dagi PF-247 bilan o'zgartirish belgisi bor, mazmuni topilmadi | [PRIMARY]; o'zgarish [NOT FOUND] |
| Markaziy bank qoidalari | Boshqaruv qarori 5/11, 25.03.2026. Adliya vazirligida **3817-son**, 15.04.2026 da ro'yxatga olingan, 16.04.2026 dan kuchda ([lex.uz/docs/8139307](https://lex.uz/docs/8139307)) | [PRIMARY] |
| Statik va dinamik QR | **Statik** QR o'zgarmaydi, uni chop etib qo'yasiz, summani mijoz o'zi teradi. **Dinamik** QR kassada har to'lov uchun yasaladi: summasi, bir martalik raqami va amal qilish muddati bo'ladi. Muddati o'tgan QR bilan to'lab bo'lmaydi (qoidalar, 1 va 32-bandlar) | [PRIMARY] |
| YaTT va o'zini o'zi band qilganlar | Ular uchun boshqa yo'l: PQ-247 (12.08.2025) bo'yicha to'lov platformasining maxsus QR'i, masalan Xolis | [MEDIA] |
| Jarima | Yuridik shaxs uchun aniq summa belgilanmagan | [MEDIA] kun.uz 03.07.2026 |
| Boshqa QR'lar | Click, Payme, Uzum QR'larini olib tashlash shart emas. Majburiysi faqat UzQR'ning kassada bo'lishi | [MEDIA] Markaziy bank va Soliq qo'mitasining izohlari, spot.uz 04.07.2026 |
| Kartalar | Faqat Uzcard va Humo. Visa/Mastercard ishlamaydi | [MEDIA] spot.uz 29.07.2026 |
| Ulanganlar | 265 mingdan ortiq yuridik shaxs (29.07.2026) | [MEDIA] |

**Tarif.** Umumiy tarif **0,65%**, uni faqat sotuvchi to'laydi, xaridordan rasmiy komissiya olinmaydi ([cbu.uz, 23.06.2026](https://cbu.uz/uz/press_center/news/4038001/)) [PRIMARY]. Bo'linishi: bank-ekvayer 0,10%, Uzcard/Humo 0,15%, operator 0,20%, mijoz skanerlagan ilova 0,15%, mijozning banki 0,05%. Operator ulushiga "onlayn kassalar bilan integratsiya" ham kiradi. 0,65% — **yuqori chegara**, bank arzonroq berishi mumkin. Masalan, Ipak Yo'li iyulda 0,25% e'lon qilgan [MEDIA], lekin hozir sahifasida "0,65% gacha" deb yozilgan [PRIMARY]. 3817-son qoidalarda foizning yuqori chegarasi yozilmagan, tarifni operator belgilaydi [PRIMARY]. Ba'zi ilovalar mijozdan qo'shimcha 1% olgan, Markaziy bank buni rasmiy tarif emas deydi [MEDIA].

**Kim operator.** UzQR operatori Markaziy bankning kliring tizimi **MUNIS** [MEDIA, gazeta.uz va kun.uz Markaziy bankka tayanib]. Alohida yuridik shaxs nomi topilmadi [NOT FOUND]. Kabinet: **uzqr.uz**, E-IMZO bilan kiriladi. Call-markaz: **1835** ([cbu.uz, 01.07.2026](https://cbu.uz/ru/press_center/adverts/4092130/)) [PRIMARY]. uzqr.uz bizning serverdan ochilmadi.

## 2. Dinamik UzQR kassaga qanday ulanadi

Operator qo'llanmasi (v1.1, nusxasi [KDB saytida](https://www.kdb.uz/storage/sample-forms/July2026/9FsQjQVcAbce8wQ9loEX.pdf)) [PRIMARY] bo'yicha tartib:

1. Restoran bank bilan shartnoma tuzadi, hisob ochadi va bank orqali UzQR'ga ro'yxatdan o'tadi. **UzQR bilan alohida shartnoma kerak emas.**
2. Direktor uzqr.uz ga E-IMZO bilan kiradi.
3. Har kassa uchun bittadan **dinamik QR** yaratadi ("KASSA_1", "KASSA_2").
4. Har QR'ga **API kalit** yaratadi. Kalit faqat bir marta ko'rsatiladi.
5. Kalitni **kassa dasturi sotuvchisiga**, ya'ni Rizqim'ga beradi.
6. Birga sozlaymiz va sinov to'lovini qilamiz.

Qaytarishni kabinetning o'zidan ham qilish mumkin. Summani kiritasiz, u asl to'lovdan oshmasligi kerak, ya'ni **qisman qaytarish bor**. Qaytarish bir necha soniyadan bir necha soatgacha bajariladi [PRIMARY].

**Boshqa kassa dasturi buni qanday qilgan.** SetRetail10 (CSI) o'z hujjatida UzQR plaginini batafsil yozgan ([13–14.07.2026](https://crystals.atlassian.net/wiki/spaces/SR10SUPPORT/pages/6492127238)) [PRIMARY, sotuvchining o'z hujjati]:

- kassa sozlamalarida uchta narsa bor: UzQR bergan manzil, **STIR (INN)** va **API_key**;
- kassa UzQR'ga so'rov yuborib dinamik QR yaratadi, keyin to'langanini **3–10 soniyada bir so'rab turadi**. Webhook haqida gap yo'q;
- to'lanmagan QR'ni bekor qilish, qaytarish yaratish va qaytarish holatini so'rash mumkin. Qaytarish butun chek yoki alohida taomlar bo'yicha bo'ladi;
- chek fiskallashgandan keyin kassa **UzQR'ga fiskal chek havolasini yuboradi**;
- **sinov muhiti bor**, lekin unda haqiqiy to'lov o'tmasligi mumkin;
- javoblarda RRN, UzQR operatsiya raqami (pay_id) va kassa raqami (invoice_id) keladi. Fiskal modul uchun UzQR to'lovi **karta to'lovi** deb yoziladi.

R-Keeper (BUSOFT) ham UZQR'ni ulagan. Narxi: ulash 200 000 so'm, har kassa oyiga 50 000 so'm, ustiga 0,65%. Pul restoranning hisobiga tushadi ([19.08.2026](https://rkeeper.uz/stati/post/uzqr-i-r-keeper-kogda-oplata-stanovitsya-chastyu-avtomatizacii-restorana)) [PRIMARY]. REGOS VCR ham dinamik UzQR'ni ko'rsatadi [PRIMARY]. Demak bu yo'l ishlayapti.

**Bizning UzQrGateway bilan solishtirish:**

| Bizning drayver nima qilishi kerak | UzQR'da bormi |
|---|---|
| Summaga dinamik QR yaratish | Ha [PRIMARY, CSI] |
| Holatni so'rash | Ha, polling [PRIMARY, CSI] |
| Bekor qilish | Ha, to'lanmagan invoice [PRIMARY, CSI] |
| To'liq qaytarish | Ha [PRIMARY] |
| Qisman qaytarish | Kabinetda ha [PRIMARY]. API'da taom bo'yicha qaytarish bor [PRIMARY, CSI], aniq maydon [NOT FOUND] |
| Qaytarish holati | Ha, polling [PRIMARY, CSI] |
| Webhook (provayder o'zi xabar beradi) va uning imzosi | **[NOT FOUND]**. Hozircha faqat so'rab turish deb hisoblash kerak. Bizda bu tayyor: kassa `GET /pos/qr/{uuid}` orqali `status()` ni so'raydi |
| **Yangi:** chek havolasini UzQR'ga yuborish | Interfeysda **yo'q**, qo'shish kerak |
| **Yangi:** har kassa uchun alohida API kalit va STIR | `fiscal_settings.uzqr` da kassa darajasida saqlash kerak |
| QR satrini kim yasaydi | Katta ehtimol bilan UzQR qaytaradi [NOT FOUND, xulosa] |

## 3. Fiskal chek

- Restoranda har bir to'lovga, naqdsiz to'lovga ham, kassa cheki beriladi (VM 75, avvalgi tadqiqot). UzQR qoidalarida chek haqida umuman gap yo'q [PRIMARY].
- Statik UzQR chek chiqarmaydi, faqat pul yig'adi. Dinamik QR'da chekni **kassa** chiqaradi [MEDIA buxgalter.uz 30.06.2026; PRIMARY CSI va R-Keeper].
- UzQR yoki bank pozitsiyalar va IKPU kabi fiskal ma'lumotni qabul qilishi topilmadi [NOT FOUND]. UzQR faqat chek **havolasini** oladi.
- **Xulosa:** sotuv bizniki, chekni Rizqim chiqaradi (P3-32 fiskal drayveri). To'lov turi — naqdsiz (karta), RRN chekka yoziladi. Bu POS_SPEC'dagi "Rizqim chek chiqaradi, ikki marta chek yo'q" qoidasiga mos.
- **Bitta xavf:** Ipak Yo'li sahifasida "fiskal cheklar sotuvchi nomidan avtomatik yaratiladi va Telegram bot orqali yuboriladi" deyilgan [PRIMARY]. Bu dinamik QR'ga ham tegishli bo'lsa, ikki marta chek chiqadi. Bankdan yozma so'rash kerak.

## 4. Banklar va to'lov tashkilotlari

Bank faqat ro'yxatga oladi, hisob yuritadi va tarifni belgilaydi. API hamma bank uchun bir xil, uzqr.uz orqali. Hech bir bankning **o'zining alohida** UzQR API'si borligi tasdiqlanmadi.

| Kim | Topilgani | Tarif | Raqibmi |
|---|---|---|---|
| **Ipak Yo'li** | Dinamik QR, "POS dasturiga API integratsiya", pul 24 soatda, mavjud mijozdan faqat ariza [PRIMARY]; chek haqida yuqoridagi xavf | 0,65% gacha; iyulda 0,25% [MEDIA] | Yo'q |
| **KDB Bank** | To'liq ommaviy oferta: hujjatlar, QR 3 bank kunida, pul klirengdan keyin 1 ish kunida, qaytarish va shikoyat tartibi [PRIMARY] | Foiz ofertada yo'q [NOT FOUND] | Yo'q |
| **Ipoteka bank** | Kabinetda API integratsiya, minimal hujjat [PRIMARY] | 0,65%, ulash bepul | Yo'q |
| **Kapitalbank** | QR'ni Uzum Business Kapitalbank ilovasida filialga bormay olish mumkin [MEDIA] | [NOT FOUND] | POS'da yo'q (Uzum guruhi) |
| **TBC** | TBC Biznes ilovasida onlayn hisob va yagona QR [MEDIA, reklama 17.11.2026] | [NOT FOUND] | Restoran POS'i topilmadi |
| BRB | Biznes 24/7 ilovasida onlayn ariza [MEDIA] | [NOT FOUND] | Yo'q |
| Hamkorbank, Anorbank, Asaka, Agrobank, Xalq banki, NBU, Infinbank, Davr, Orient Finans | UzQR sahifasi yoki tarifi topilmadi | [NOT FOUND] | Yo'q |
| Click, Payme | UzQR'da **xaridor tomoni**: QR'ni skanerlab to'laydi. Payme o'z foydalanuvchilariga UzQR'ni komissiyasiz qilgan [MEDIA]. Ularning o'z QR'lari Rizqim'da allaqachon bor | O'z tariflari | Yo'q |
| Uzum Bank | O'z **Dynamic QR API**'si bor: developer.uzumbank.uz, ochiq hujjat [PRIMARY]. Lekin bu **UzQR emas**: QR'ni faqat Uzum ilovasi to'laydi. Qaytarish faqat to'liq (summa maydoni yo'q). Imzo: sha1(vaqt+kalit). Chek havolasini yuborish usuli bor | [NOT FOUND] | QR'da raqobatchi, POS'da yo'q |
| **Multicard (Rahmat)** | O'z Rahmat QR'i, restoranlar uchun 1,5% [MEDIA]. UzQR ishtirokchisi ekani topilmadi | 1,5% | **Ha, raqib** (Rahmat kassa) |
| Paynet (Xolis) | YaTT va o'zini o'zi band qilganlar uchun platforma | 0,65% [MEDIA] | Qisman |
| Atmos, OCTO, Paylov | UzQR bo'yicha hech narsa topilmadi | — | Yo'q |

Qoidalarda "ekvayer agenti" degan rol bor: bu restoranni UzQR'ga ulab beradigan to'lov tashkiloti (21–26-bandlar) [PRIMARY]. Lekin shu rolda ishlayotgan to'lov tashkiloti topilmadi [NOT FOUND]. Ko'p restoranni bizning nomimizdan bitta "sub-merchant" shartnoma bilan ulash ham kerak emas: har restoran o'z bankida o'zi ulanadi, kalitni bizga beradi.

## 5. Eng yaxshi 3 yo'l

1. **UzQR operatorining API'siga to'g'ridan-to'g'ri ulanish** (Markaziy bank, MUNIS, uzqr.uz). Afzalliklari: bitta integratsiya hamma bank va hamma restoran uchun ishlaydi. Pul restoranning o'z hisobiga tushadi, biz pulni ushlamaymiz, demak to'lov litsenziyasi muammosi yo'q. Narxi eng past (0,65% yoki undan kam). Operator raqib emas, davlat tizimi. Sinov muhiti bor. Boshqa kassa dasturlari shu yo'l bilan ishlayapti. Kamchiligi: API hujjati ochiq emas, operatordan so'rash kerak. Webhook yo'qdek, faqat so'rab turish bor.
2. **Pilot uchun bank — Ipak Yo'li.** U o'z sahifasida dinamik QR va POS uchun API'ni ochiq yozgan. E'lon qilingan foizi eng past (0,25%). Hujjat kam talab qiladi: mavjud mijozdan faqat ariza. Shu bankdan UzQR texnik hujjatini va sinov kalitini tezroq olish mumkin. Kamchiligi: "chek avtomatik chiqadi" degan gapini aniqlash kerak. 0,25% hozir ham amaldami, ma'lum emas.
3. **Onlayn ulanadigan bank — Kapitalbank yoki TBC (zaxira: KDB).** Restoran QR va hisobni ilovada, filialga bormay ochadi, ko'p restoranni tez ulash uchun qulay. KDB'ning esa to'liq ommaviy ofertasi bor: hujjatlar, muddatlar va qaytarish qoidasi yozilgan. Kamchiligi: Kapitalbank va TBC tarifi e'lon qilinmagan.

**Tavsiya qilinmaydi:** Multicard/Rahmat, chunki raqib. Uzum Dynamic QR, chunki UzQR emas va faqat to'liq qaytaradi. Uni xohlasak keyin alohida provayder sifatida qo'shish mumkin. Paylov'da QR yopiq.

## 6. Egasi nima qilishi kerak

1. **Operatorga murojaat qilish.** Markaziy bank call-markazi 1835 ga qo'ng'iroq qiling yoki To'lov tizimlari departamentiga yozing. "Kassa dasturi sotuvchisi sifatida UzQR API texnik hujjati va sinov muhitiga kirish" kerakligini ayting (savollar 7-bo'limda). Qoidalarning 10-bandiga ko'ra operator texnik hujjatni o'z saytida e'lon qilishi kerak, shuni ko'rsating.
2. **Bitta pilot restoran tanlash.** U Ipak Yo'li yoki istalgan bankda hisob ochib, UzQR'ga ulanadi. Restoranga kerak bo'ladigan hujjatlar (KDB ofertasi bo'yicha [PRIMARY]): ulanish arizasi, ta'sis hujjatlari (YaTT uchun guvohnoma), vakilning shaxsi va vakolati hujjati, shu bankdagi hisob rekvizitlari, bank so'ragan KYC hujjatlari. Direktorning **E-IMZO** kaliti ham kerak, u uzqr.uz ga kirish uchun.
3. Restoran uzqr.uz da har kassa uchun dinamik QR va API kalit yaratadi va kalitni xavfsiz yo'l bilan bizga beradi (chatda yubormasin).
4. Dasturchilar haqiqiy drayverni sinov muhitida yozadi, keyin pilotda kichik summa bilan sinov to'lovi va qaytarish qilinadi.
5. Shu vaqtda bankdan ikki marta chek chiqmasligini yozma tasdiqlab oling (3-bo'lim).

**Dasturchilar uchun qisqa eslatma:** drayverga kassa darajasida API kalit va STIR kerak. Fiskal chekdan keyin "chek havolasini yuborish" chaqiruvi kerak (hozirgi `UzQrGateway` da yo'q). Tasdiq polling bilan bo'ladi. Qisman qaytarish va xato kodlari hujjat kelgach aniqlanadi.

## 7. Yuborishga tayyor savollar

**A. UzQR operatoriga (Markaziy bank, MUNIS, 1835):**

1. Biz restoranlar uchun kassa dasturi (Rizqim POS) ishlab chiqamiz. Dinamik UzQR uchun kassa dasturlariga mo'ljallangan API texnik hujjatini qayerdan olamiz? Sinov muhiti manzili va sinov kaliti qanday beriladi?
2. Kassa dasturi sotuvchisi operator bilan shartnoma tuzishi yoki ro'yxatdan o'tishi kerakmi? Yoki restoran bergan API kaliti yetarlimi?
3. To'lov tasdiqlanganda bizning serverga xabar (webhook yoki callback) keladimi? Kelsa, u qanday imzolanadi? Yoki faqat holatni so'rab turish kerakmi, va qanday tezlikda so'rash mumkin?
4. QR satri (EMVCo)ni UzQR qaytaradimi yoki kassa o'zi yasaydimi? Dinamik QR'ning amal qilish muddati qancha va uni o'zimiz belgilay olamizmi?
5. API orqali qisman qaytarish mumkinmi? Bitta to'lovni bir necha marta qaytarsa bo'ladimi? Qaytarish uchun muddat chegarasi bormi?
6. Fiskal chek havolasini yuborish majburiymi? Qanday formatda va qancha vaqt ichida yuboriladi?
7. Bitta API kalit bilan bir nechta kassa ishlasa bo'ladimi yoki har kassaga alohida kalit kerakmi? Kalitni restoran bizga qanday xavfsiz beradi? Kalitni qayta tiklash mumkinmi?
8. Xato kodlari ro'yxati ("UzQR Error Reference") va API o'zgarishlari haqida xabar berish tartibi qanday?
9. 16.11.2026 dagi PF-247 UzQR tartibiga nima o'zgartirdi?

**B. Bankka (Ipak Yo'li; xuddi shu savollarni Kapitalbank, TBC va KDB'ga ham yuboring):**

1. Yuridik shaxs restoran uchun UzQR tarifingiz hozir necha foiz? 0,25% hali amaldami? Oylik yoki ulash haqi bormi?
2. Sahifangizdagi "fiskal chek avtomatik yaratiladi" gapi dinamik QR'ga ham tegishlimi? Chekni restoranning kassa dasturi (Rizqim) chiqarsa, ikki marta chek chiqmasligi uchun nima qilish kerak?
3. Dinamik QR va API kalit faqat uzqr.uz da yaratiladimi yoki sizning o'z API'ngiz ham bormi? Sinov kalitini bera olasizmi?
4. Pul qachon tushadi: darhol yoki 1 ish kunida? Qaytarish kimning hisobidan, qancha muddatda bajariladi?
5. Restoran filialga kelmasdan onlayn ulana oladimi? Qaysi hujjatlar kerak?
6. Ko'p filialli tarmoq bitta hisobda bir nechta dinamik QR ochsa bo'ladimi?

## 8. Noma'lum qolganlar

- UzQR API'ning o'zi: so'rovlar, maydonlar, xato kodlari, QR muddati, qisman qaytarish maydoni [NOT FOUND].
- Webhook va uning imzosi bormi [NOT FOUND].
- Operatorning yuridik nomi va kassa sotuvchisi u bilan shartnoma tuzishi kerakmi [NOT FOUND].
- Kapitalbank, TBC, Hamkorbank, Anorbank, Asaka, Agrobank, Xalq banki, NBU, Infinbank, Davr va Orient Finans tariflari [NOT FOUND].
- Ipak Yo'li 0,25% hali amaldami va uning "avtomatik chek" gapi nimani anglatadi.
- PF-247 (16.11.2026) o'zgarishlari.
- uzqr.uz bizdan ochilmadi, kabinetni o'z ko'zim bilan ko'rmadim. Qo'llanma va boshqa sotuvchi hujjatlariga tayandim.

## Manbalar

- PF-246: https://lex.uz/docs/7897677 · 3817-son qoidalar: https://lex.uz/docs/8139307
- Markaziy bank, tarif: https://cbu.uz/uz/press_center/news/4038001/ · e'lon va kabinet: https://cbu.uz/ru/press_center/adverts/4092130/
- UzQR kabinet qo'llanmasi (KDB): https://www.kdb.uz/storage/sample-forms/July2026/9FsQjQVcAbce8wQ9loEX.pdf · KDB ofertasi: https://kdb.uz/public/storage/sample-forms/July2026/syPsK5NAoOXrxeuROqzL.pdf
- SetRetail10 UzQR: https://crystals.atlassian.net/wiki/spaces/SR10SUPPORT/pages/6492127238
- R-Keeper: https://rkeeper.uz/stati/post/uzqr-i-r-keeper-kogda-oplata-stanovitsya-chastyu-avtomatizacii-restorana · REGOS: https://regos.uz/ru/product/regos-vcr/info
- Ipak Yo'li: https://en.ipakyulibank.uz/legal/payments/qr-pay · Ipoteka: https://www.ipotekabank.uz/en/business/acquiring/qr-code/
- Uzum Dynamic QR: https://developer.uzumbank.uz/en/dynamicqr
- Matbuot: kun.uz 03.07.2026 (https://kun.uz/news/2026/07/03/ozbekistonda-uzqr-tizimi-ishga-tushdi-asosiy-savollarga-javoblar-3dab63), gazeta.uz 23.06.2026 (https://www.gazeta.uz/ru/2026/06/23/uzar-tariff/), spot.uz 02.07, 04.07, 29.07, 17.11.2026, buxgalter.uz 30.06.2026, podrobno.uz 30.07.2026
