# Virtual kassa texnik talablari

**Xulosa.** 2026-yil 22-noyabr holatiga o'z virtual kassamizni qurish uchun kerakli texnik hujjatning katta qismi ochiq ekan. Soliq qo'mitasi huzuridagi "Yangi texnologiyalar" markaziga (OFD) tegishli ko'rinadigan ikki GitHub ombori bor. Ularda fiskal modul (FM 0400) bilan ishlashning to'liq tavsifi berilgan: chek maydonlarining aniq nomlari, chek turlari (sotuv, qaytarish, bo'nak, kredit), Z-hisobot, chek raqamlash, QR havola formati, 48 soat qoidasi, xato kodlari va OFD bilan almashuv protokoli. Fiskal modul dastur emas, **jismoniy qurilma**. U OFD beradigan USB kalit yoki smart-karta bo'lib, kassa kompyuterida turadi. Kassa unga "FiscalDriveService" degan lokal xizmat orqali ulanadi. Sinov uchun modul emulyatori va sinov OFD serveri bor, lekin ular shu xizmat dasturining ichida, dasturning o'zi esa ochiq joyda yo'q. Uni OFD dan so'rash kerak. Biz uchun eng muhim oqibat: **hozir chek serverda chiqariladi, o'z kassamizda esa u restoranning o'zida, asosiy kassada chiqishi kerak.** Internetsiz sotuvni keyinroq chekka aylantirish ham ishlamaydi, chunki modul vaqti orqaga ketgan chekni qabul qilmaydi. Huquqiy tartib o'zgarmagan: OFD xulosasi (10 ish kuni), Kiberxavfsizlik markazi ekspertizasi (15 ish kuni) va Soliq qo'mitasi qarori (20 ish kuni) kerak. Narxlar, reyestrning bugungi ro'yxati va raqib bo'lmagan TXKM hamon topilmadi. Hozir emulyatorga qarshi qurish mumkin bo'lgan ishlar ro'yxati va OFD ga yuboriladigan savollar oxirida.

## Belgilar va cheklovlar

**[BIRLAMCHI]** (PRIMARY) degani manbaning o'zini o'qiganman: lex.uz, rasmiy sayt yoki ishlab chiquvchi hujjati. **[MEDIA]** degani gazeta, buxgalteriya sayti, norasmiy kutubxona yoki qidiruv xulosasi. **[TOPILMADI]** (NOT FOUND) degani ko'ra olmadim, "yo'q" degani emas. **[XULOSA]** mening o'qishim. Hammasi 22.11.2026 da tekshirilgan.

GitHub omborlari to'liq o'qildi (nusxa olib). Ularni joylagan akkaunt ("qo0p") o'zini tanishtirmaydi. OFD ga tegishli ekani quyidagidan ko'rinadi: Java paketi `uz.yt.ofd` deb nomlangan, shu akkaunt E-IMZO hujjatini ham joylagan, oldingi xizmat dasturi (FiscalDriveAPI) noshiri esa "НИЦ НТ ГНК РУз" deb ko'rsatilgan. Shuning uchun belgi **[BIRLAMCHI, noshiri rasman tasdiqlanmagan]**. Omborlarda litsenziya yo'q. Demak tavsifga qarab yozamiz, lekin ulardagi kodni ko'chirib olmaymiz.

soliq.uz va ofd.uz reyestr sahifalari bugun ham ochilmadi (503 xato yoki ulanish uzildi), csec.uz hujjat havolalari 404 berdi. lex.uz dagi VM 943 matni qisqartiruvchi vosita orqali uch bo'lakda o'qildi. Iqtiboslarni yurist tekshirishi kerak.

## 1. Huquqiy asos va tartib

### 1.1. Virtual kassaga texnik talablar (VM 943, 2-ilova, 6-band) [BIRLAMCHI]

Qonunda virtual kassa nima qila olishi kerakligi ro'yxat qilib berilgan. Bizga muhimlari:

- dastur versiyasi va seriya yoki litsenziya raqami;
- **fiskal modulsiz ishlamaydi**; modul buzilsa, xotirasi to'lsa yoki **48 soat internetsiz** ishlansa, avtomatik bloklanadi;
- ruxsatsiz foydalanish yoki tashqi dastur ma'lumotni o'zgartirsa, bloklanadi;
- shtrixkod skaneri; MXIK kodlari milliy katalogdan real vaqtda yuklanadi;
- barcha bank kartalari va QR to'lov **to'g'ridan-to'g'ri shu qurilmada** qabul qilinadi, banklarning yagona QR tizimiga ulanish majburiy;
- markirovka kodi (ISO/IEC 15415), takroriy kod chiqmaydi; retsept dorisi faqat elektron retsept bilan;
- kamida 6 belgili parol; vaqt UTC+05:00 yoki operator serveridan;
- foydalanuvchi qo'llanmasi davlat tilida va boshqa tillarda; chek asosan davlat tilida;
- **fiskal ma'lumot faqat operatorga, to'g'ridan-to'g'ri fiskal modul orqali yuboriladi**;
- **bitta fiskal modul bir vaqtda faqat bitta virtual kassada ishlatiladi**;
- dasturni yoki ma'lumot yuborish usulini o'zgartirsa, operator va Kiberxavfsizlik markazining **qayta xulosasi** kerak.

8-band: versiyasi reyestrda bo'lmagan, chekda majburiy rekvizit yetishmaydigan, TXKM bilan shartnomasi yo'q kassani ishlatib bo'lmaydi.

01.04.2026 dan (PF-246) kassa yana to'rt narsani qilishi kerak: naqdi taqiqlangan MXIK lar (alkogol, pivo, tamaki, yoqilg'i, davlat yig'imlari, bitta MXIK bo'yicha 25 mln so'mdan ortiq) uchun naqdni bloklash, retseptsiz dorini bloklash, muddati o'tgan tovarni bloklash va ijtimoiy kartani karta turi, raqami va tranzaksiya raqami bilan qabul qilish. Chekda bitta taqiqlangan pozitsiya bo'lsa, butun chek naqdsiz to'lanadi ([buxgalter.uz](https://buxgalter.uz/oz/publish/doc/text214454_2026_yil_1_apreldan_boshlab_yangi_talablar_buyicha_onlayn-nkt_bilan_qanday_ishlash_kerak)) [MEDIA].

### 1.2. Reyestrga kirish tartibi (VM 943, 4-ilova) [BIRLAMCHI]

Arizani yuridik shaxs (RIZQIM MChJ) Soliq qo'mitasiga beradi. 11-bandda ilovalar ro'yxati bor:

| № | Hujjat | Virtual kassaga kerakmi | Muddat |
|---|---|---|---|
| 1 | 200 BHM zaxira yoki kafolat | **Yo'q** ("virtual kassa bundan mustasno") | |
| 2 | OFD xulosasi: dastur texnik va funksional tavsiflarga mosmi | Ha | 10 ish kuni |
| 3 | Chek namunalari | Ha | |
| 4 | Dasturning etalon nusxasi | Ha | |
| 5 | Texnik pasport (tavsif va kafolat majburiyatlari) | Ha | |
| 6 | Foydalanuvchi hujjatlari: qo'llanma, o'rnatish qo'llanmasi, soliq inspektori yo'riqnomasi | Ha | |
| 7 | Ehtiyot qismlar kafolat xati | Ha (dastur uchun ma'nosi so'raladi) | |
| 8 | Mualliflik huquqi guvohnomasi (patent) | Ha | |
| 9 | Kiberxavfsizlik markazi ekspertiza xulosasi | Ha | 15 ish kuni |
| 10 | Muvofiqlik sertifikati | Matnda faqat onlayn-NKM uchun | |

Shundan keyin Texnik jihatdan tartibga solish agentligi va "O'zeltexsanoat" 5 ish kunida javob beradi, Soliq qo'mitasi qarorni **20 ish kunida** beradi. Hujjat to'liq bo'lmasa yoki nomuvofiqlik tasdiqlansa, rad etiladi, tuzatib qayta topshirish mumkin.

Bitta qarama-qarshilik bor. 2-ilova 6-bandi virtual kassa uchun ham "sertifikatlashtirish organi bergan muvofiqlik sertifikatlari"ni talab qiladi. 4-ilova esa sertifikatni faqat onlayn-NKM dan so'raydi. Buni Soliq qo'mitasidan so'raymiz [XULOSA].

Narxlar: reyestrga kiritish uchun davlat to'lovi topilmadi. OFD xulosasi va Kiberxavfsizlik ekspertizasi narxi ham topilmadi [TOPILMADI]. Kiberxavfsizlik markazi xizmati pullik, narxi ish hajmi baholangandan keyin shartnomada belgilanadi. Bundan tashqari, IT mahsulotning texnik topshirig'i Markaz bilan kelishiladi (qidiruv xulosasi, aloqa: info@csec.uz, (71) 203-12-24, dastur sertifikatlash: itsoft@csec.uz) [MEDIA]. Amaliy dastur uchun milliy standart O'zMSt 841:2026 tilga olingan [MEDIA], matni o'qilmadi.

Agar ish ketma-ket borsa, faqat kutish taxminan 50 ish kuni (~2,5 oy) davom etadi. Oldingi hisobotdagi 4–6 oylik umumiy reja o'zgarmaydi [XULOSA].

### 1.3. TXKM va har bir restoran

- Restoran kassasi soliq organida TXKM orqali ro'yxatdan o'tadi: TXKM 4 ish soati, soliq xodimi 8 ish soati, bepul (1-ilova 16–23-band) [BIRLAMCHI].
- Fiskal modul uchun operator haqi yiliga 1 BHM [BIRLAMCHI, oldingi tadqiqot].
- TXKM bo'lish uchun xizmat ko'rsatiladigan qurilmalar soniga qarab zaxira kerak: 1000 tagacha 100 BHM. Bundan tashqari call-markaz, 5% ehtiyot qurilma, sertifikatli mutaxassislar va yillik tekshiruv talab qilinadi. Ro'yxatdan o'tish bepul (3-ilova) [BIRLAMCHI].
- 25-band 9-xatboshi (ikkilamchi tizim kassaga ulansa, TXKM reyestrdan chiqariladi) va 10-xatboshi (QR-kodsiz chek chop etish imkoniyati bo'lsa, TXKM chiqariladi) kuchda [BIRLAMCHI].
- Yangi texnik topilma: fiskal modulni bitta kassa dasturiga "bog'lash" (POS lock) mumkin, va hujjatga ko'ra bu kalitni "har bir TXKM" o'zi yaratadi. Demak modul kimning dasturida ishlashini amalda TXKM nazorat qiladi. Dasturchi bilan TXKM har xil kompaniya bo'lsa, kalit kimda turishi yozilmagan [TOPILMADI].

### 1.4. Hozir reyestrda kim bor

Reyestrlar ochilmadi [TOPILMADI]. Qidiruvdagi parchada 2020-yilgi yozuvlar ko'rindi, masalan "SOFT TECH SOLUTIONS MChJ, Virtual kassa POS2K V5.0, 30.04.2020" [MEDIA]. 2026-yilda chiqarilganlar: Uzkass-185 va Bestpos (28.04.2026) [BIRLAMCHI], E-POS (30.10.2026) [MEDIA]. Raqib bo'lmagan TXKM nomi bilan hamon topilmadi [TOPILMADI]. Uchinchi tomon POS'i o'z virtual kassasini shu FiscalDriveService ustida qurganiga Artix misol bo'ladi ([docs.artix.su](https://docs.artix.su/pages/viewpage.action?pageId=93946891)) [BIRLAMCHI]. iiko uchun O'zbekiston restoranlariga yozilgan plagin ham xuddi shunday lokal fiskal xizmatga ulanadi ([davr.dev](https://davr.dev/projects/ofd)) [MEDIA].

## 2. Fiskal modul qanday ishlaydi

### 2.1. Modulning o'zi

Fiskal modul JavaCard smart-karta yoki USB-token bo'lib, ichida "fiskal modul" ilovasi o'rnatilgan. Uni OFD beradi, asosiy yetkazib beruvchi Feitian. Modul ikki rejimda bo'ladi: test va ishchi. Seriya raqami 2 lotin harfi va 12 raqamdan iborat, masalan `UZ724549167320`. Test modul raqami `ZZ000000000000` [BIRLAMCHI].

Bu bulutdagi HSM ham, Soliq qo'mitasi beradigan dastur ham emas. "Virtual fiskal modul" (operator beradigan raqam va elektron imzo sertifikati) qonunda bor, lekin marketpleyslar qoidasida tilga olingan (oldingi tadqiqot). Restoran kassasi uchun jismoniy modul kerak [BIRLAMCHI + XULOSA].

Modul qayerda turadi: "FiscalDriveService va FM kassa dasturi o'rnatilgan kompyuterning o'ziga o'rnatiladi, shu kompyuterda internet bo'lishi kerak" (README, ruschadan tarjima) [BIRLAMCHI]. Qonunning "bitta modul bitta kassada" va "faqat modul orqali" talablari ham shunga ishora qiladi. Modulni data-markazda saqlash yo'li ko'rinmaydi, lekin buni OFD dan so'raymiz [XULOSA].

### 2.2. Kassa modulga qanday ulanadi

- **FiscalDriveService v10** — Windows 11 (x64) yoki Linux (x64, arm) uchun lokal xizmat. U `http://127.0.0.1:3449/` manzilida REST API ochadi. Kassa dasturi unga JSON yuboradi, xizmat buni modul buyruqlariga aylantiradi, chekni modul imzosi bilan shifrlab o'z bazasida saqlaydi va OFD serverlariga yuboradi [BIRLAMCHI].
- Android uchun boshqa yo'l: modul kassa qurilmasining SAM-slotiga qo'yiladi. Uning drayverini dasturchi yozadi, OFD bilan almashuvni ham kassa o'zi qiladi (MessageV6 ikkilik protokoli, TCP, CRC32, O'zDSt 1106 xeshi, GOST 28147 shifri) [BIRLAMCHI, acrsim-android]. Bu ancha og'ir yo'l. Birinchi bosqichda FiscalDriveService yo'li tavsiya qilinadi [XULOSA].
- Kassa ilovasi bilan xizmat orasida TLS (o'z sertifikatlarimiz bilan) yoqish mumkin [BIRLAMCHI].
- **POS lock**: 32 baytli maxfiy kalit modulga o'rnatiladi. Shundan keyin har bir Z ochish, Z yopish va chek ro'yxatdan o'tkazishda `X-POS-Auth = SHA-256(kalit + challenge)` sarlavhasi talab qilinadi. Kalitni faqat OFD serveri buyrug'i bilan olib tashlash mumkin [BIRLAMCHI].

### 2.3. Chek qanday chiqadi

1. Kassa chekni JSON qilib `GetTXID` ga beradi va `TXID` oladi (bir xil chek uchun bir xil TXID qaytadi).
2. `RegisterTXID` modulda chekni ro'yxatdan o'tkazadi va javob qaytaradi: `TerminalID`, `ReceiptSeq` (chek raqami), `DateTime`, `FiscalSign` (12 raqamli fiskal belgi) va `QRCodeURL`.
3. Ulanish uzilsa, `RegisterTXID` xuddi shu TXID bilan takrorlanadi: dublikat ham, bo'sh chek ham chiqmaydi.
4. Javob kassa bazasida saqlanadi, chunki qaytarish chekida u kerak bo'ladi.
5. Keyin `DataBase/Files/Sync/FullReceipts` cheklarni OFD ga yuboradi (bir urinishda 32 tagacha). OFD tasdiq fayl qaytaradi, modul shundan keyin chekni xotirasidan o'chiradi [BIRLAMCHI].

QR havola: `https://ofd.soliq.uz/check?t=<modul raqami>&r=<chek raqami>&c=<YYYYMMDDHHMISS>&s=<fiskal belgi>` [BIRLAMCHI]. Bizning sinov drayverimiz ham shu shakldagi havola chiqaradi.

### 2.4. Chek maydonlari (aniq nomlar)

Chekning yuqori qismi: `Time`, `ReceivedCash` va `ReceivedCard` (tiyinda), `Type` (0 oddiy, 1 bo'nak, 2 kredit), `Operation` (0 sotuv, 1 qaytarish), `PaymentType` (1 naqd, 2 karta, 3 QR, 4 aralash), `Location` (kenglik, uzunlik), `Items`, `ExtraInfo`, `RefundInfo`.

Har bir qator: `Name`, `Barcode`, `Labels` (markirovka kodlari, 300 tagacha), `SPIC` (MXIK), `Units` (o'lchov birligi **kodi**), `PackageCode`, `OwnerType`, `RecipeID`, `Amount` (miqdor ×1000), `Price` (qatorning chegirmasiz jami summasi), `Discount`, `Other`, `VATPercent` (butun son), `VAT`, `CommissionInfo` (komitent STIR yoki JShShIR).

`ExtraInfo`: STIR, JShShIR, telefon, QR to'lov ID va provayder kodi, karta turi (korporativ, shaxsiy, ijtimoiy), ijtimoiy karta raqami, protsessing tranzaksiya ID.

`RefundInfo` (qaytarishda): asl chekning modul raqami, chek raqami, vaqti va fiskal belgisi. Xato bo'lsa, chek ro'yxatdan o'tmaydi.

Qoidalar: olingan pul minus qatorlar jami 100 so'mdan oshmasligi kerak; har bir qator summasi musbat; nom 1–63 bayt, modulning o'z belgilar jadvalida (kirill, lotin, o', g', ö, ş va boshqalar bor, lekin o'zbekcha ʻ belgisi yo'q, oddiy ' bor) [BIRLAMCHI].

### 2.5. Z-hisobot, raqamlash, internetsiz rejim

- Chek berishdan oldin modulda Z ochiladi va kun oxirida yopiladi. Z da sotuv va qaytarishlar soni, naqd, karta va QQS jami, birinchi va oxirgi chek raqami saqlanadi. Yopilgan Z alohida OFD ga yuboriladi. Bitta Z da 29 999 tagacha amal bo'ladi. **Bitta ham sotuv yoki qaytarish bo'lmasa, Z yopilmaydi** [BIRLAMCHI].
- Chek raqamini modul beradi, bitta modulda bitta ketma-ketlik bo'ladi, bo'nak va kredit cheklari ham raqam oladi (lekin fiskal belgi olmaydi) [BIRLAMCHI].
- **Vaqt faqat oldinga yuradi**: har bir amal vaqti modulning oxirgi amalidan kamida 1 soniya keyin bo'lishi kerak. Kompyuter soati 2 kundan ko'p oldinda bo'lsa ham modul ishlamaydi [BIRLAMCHI].
- **48 soat qoidasi modulning ichida**: eng eski yuborilmagan chek oxirgi amaldan 2 kun yoki undan ortiq orqada qolsa, modul yangi chek ham, Z ham qabul qilmaydi, avval yuborish kerak [BIRLAMCHI]. Artix o'z kassasini 24 soatda bloklaydi [BIRLAMCHI].
- Modulda faqat naqd va karta jamlagichlari bor, QR uchun alohida jamlagich yo'q. Demak QR puli karta qismida ketsa kerak [XULOSA].

## 3. Sinov muhiti

FiscalDriveService ichida uchta dasturchi vositasi bor [BIRLAMCHI]:

- **modul emulyatori** (TCP 4387): xotirasi kompyuter RAM'ida turadi, har ishga tushganda yangi zavod raqami oladi. Ulanish uzilishini belgilangan ehtimol bilan ataylab yaratish mumkin;
- **sinov OFD serveri** (TCP 13447) va uning holat sahifasi (`127.0.0.1:13448/state`): modulni bloklash, blokdan chiqarish va kassadan ajratish uchun;
- sinov cheki generatori.

Sinov cheklari faqat sinov serveriga boradi, haqiqiy OFD ularni qabul qilmaydi. Dasturchi haqiqiy modulsiz to'liq ishlay oladi.

**To'siq:** FiscalDriveService dasturining o'zi (Windows o'rnatuvchisi va Linux arxivi) omborda yo'q, relizlar ham yo'q, qidiruvda ham topilmadi [TOPILMADI]. Uni OFD dan so'rash kerak. Shungacha biz o'z emulyatorimizni hujjatdagi API va xato kodlari asosida yozamiz. Rasmiy emulyator kelganda testlarni unga qarshi ham ishlatamiz.

Boshqa ochiq material: `@exode-team/ofd-uz` npm paketi (`test.ofd.uz`, sertifikat bilan imzolash; bu virtual FM yoki marketpleys kanaliga o'xshaydi) [MEDIA]. MXIK katalogi uchun norasmiy, lekin ishlaydigan ochiq API: `tasnif.soliq.uz/api/cls-api`. Unda qidiruv, kod bo'yicha ma'lumot va o'lchov birliklari ro'yxati bor ([mxik-js](https://github.com/azabroflovski/mxik-js)) [MEDIA].

## 4. RIZQIM uchun nima o'zgaradi

Kodimiz bilan solishtirdim (`backend/app/Domain/Fiscal`). Yaxshi xabar: ma'lumot modeli deyarli mos keladi. Chek turlari va to'lov shakli bir xil. Pul tiyinda (×100) va miqdor ×1000 aniq o'tadi. MXIK, qadoq kodi, komitent STIR, QQS qator bo'yicha hisoblanadi, idempotentlik va qayta urinish ham bor. Javob maydonlari (`fiscal_sign`, `receipt_number`, `qr_url`, `terminal_id`) ham bir xil. Lekin quyidagilar o'zgaradi [XULOSA]:

1. **Chek restoranda chiqadi, serverda emas.** Hozir chekni backend chiqaradi va POS uni faqat chop etadi. O'z kassamizda modul asosiy kassa kompyuterida turadi (Windows kassa yoki kichik Linux qurilma, `pos_lan` markazi). Chekni o'sha joy ro'yxatdan o'tkazadi, backend esa natijani yozib boradi. Internet uzilsa ham chek darhol chiqadi.
2. **Internetsiz sotuvni keyin chekka aylantirib bo'lmaydi.** Hozirgi P3-31 rejimida chek aloqa tiklanganda, sotuv vaqti bilan so'raladi. Modul esa vaqti oxirgi chekdan oldin bo'lgan chekni rad etadi. Demak chek sotuv paytida markaz kassada chiqishi kerak, OFD ga esa keyinroq yuboriladi.
3. **48 soat ogohlantirishi moduldan o'qiladi** (eng eski yuborilmagan chek vaqti), faqat server navbatidan emas.
4. **O'lchov birligi kodi** kerak: bizda "dona", "kg" degan matn bor, modul esa raqamli kod kutadi (tasnif ro'yxatidan).
5. **Nomlar** modul jadvaliga o'giriladi (ʻ → '), 63 baytgacha qisqartiriladi.
6. **Bo'nakni sotuvda hisobga olish** (bron avansi) va **nasiyaning to'lanmagan qismi** modul chekida qanday ko'rsatilishi yozilmagan. Bu OFD ga savol.
7. **Bo'sh smena**: savdosiz smenada modul Z ni yopmaydi, shuning uchun smena yopish buni hisobga olishi kerak.
8. **POS lock kaliti** markaz kassada shifrlangan holda saqlanadi.
9. **Naqd taqiqi** (PF-246 ro'yxati) kassada bloklanadi.
10. **Bitta modul bitta kassaga.** Bir restoranda bir nechta to'lov oladigan kassa bo'lsa, yo har biriga modul kerak, yo hamma to'lov markaz kassadan o'tadi. Ofitsiant planshetidagi to'lov alohida kassa hisoblanadimi, bu OFD ga savol.

## 5. Hozir nima qurish mumkin

| Modul | Nima qiladi | Shakli qanchalik aniq |
|---|---|---|
| Chekni modul formatiga o'girish (mapper) | FiscalDocument → FiscalDriveService JSON: tiyin, ×1000, Price/Discount, QQS, SPIC, qadoq, birlik kodi, komitent, to'lov shakli, karta va QR ma'lumoti, qaytarishda RefundInfo | **Yuqori** (maydonlar nomi bilan ochiq) |
| Tekshiruvchi (validator) | Modul rad etadigan chekni oldindan to'xtatadi: summa tenglamasi, nom belgilari va uzunligi, butun QQS foizi, kodlar uzunligi | **Yuqori** |
| FiscalDriveService mijozi | REST chaqiruvlari, qayta urinish, TXID idempotentligi, POS lock autentifikatsiyasi | **O'rta-yuqori** (metod nomlari ochiq, HTTP shakli Swagger'da, dastur kelganda tekshiriladi) |
| O'z emulyatorimiz (modul + xizmat + sinov OFD) | Blok holati, Z ochish va yopish, raqamlash, vaqt qoidasi, 2 kun qoidasi, bo'sh Z, xotira to'lishi, OFD yuborish va tasdiq, xato kodlari, ataylab uzilish | **Yuqori** (xatti-harakat hujjatda batafsil) |
| Smena ↔ modul Z | Smena ochilganda Z ochiladi, yopilganda Z yopiladi va yuboriladi, Z ma'lumoti `pos_shifts` ga yoziladi | **Yuqori** |
| Internetsiz rejim | Chek markaz kassada chiqadi, yuborish sikli (32 tadan), 24 va 36 soatda ogohlantirish, 48 soatda blok | **Yuqori** |
| QR va chek chop etish | QR modul havolasidan; bo'nak va kredit cheki fiskal belgisiz | **Yuqori** |
| Backend tomoni | `rizqim_vkassa` drayveri: markaz kassa ro'yxatdan o'tkazgan chek natijasini qabul qiladi, `fiscal_receipts` ga yozadi | **O'rta** (bizning dizaynimiz) |
| MXIK va birlik ma'lumotnomasi | tasnif ochiq API'sidan birlik kodlari va QQS imtiyozlari | **O'rta** (API norasmiy) |
| Naqd taqiqi ro'yxati | MXIK ro'yxati sozlamada, chekni bloklash | **O'rta** (ro'yxatning rasmiy manbasi va formati kerak) |
| Rasmiy dasturni kutadigan ishlar | Haqiqiy FiscalDriveService va test modul bilan sinov, OFD manzillari, bo'nakni hisobga olish qoidasi, `OwnerType` va QR provayder kodlari, POS lock kaliti kimda turishi, Android SAM-slot yo'li | **Kutiladi** |

## 6. Ariza hujjatlari: nimani hozir yozish mumkin

| Hujjat | Hozir yozsa bo'ladimi | Izoh |
|---|---|---|
| Texnik pasport (tavsif, versiya, arxitektura, chek turlari, bloklash qoidalari, kafolat) | Ha, qoralama | 2-ilova 6-band ro'yxati bo'yicha har bir talabga "qanday bajariladi" ustuni |
| Foydalanuvchi qo'llanmasi (o'zbekcha va ruscha) | Ha | Kassir, menejer, smena, qaytarish, internetsiz rejim |
| O'rnatish qo'llanmasi | Qisman | FiscalDriveService va modulni o'rnatish rasmiy dastur kelgach |
| Soliq inspektori yo'riqnomasi | Ha, qoralama | Chekni, Z ni va modul holatini qanday ko'rish |
| Chek namunalari | Emulyatordan | Sotuv, qaytarish, bo'nak, kredit, aralash to'lov; haqiqiy test modul bilan qayta |
| Etalon nusxa | Keyin | Muzlatilgan versiya, nazorat summasi bilan |
| Kiberxavfsizlik uchun paket: texnik topshiriq, tahdid modeli, kirish nazorati, jurnal, kalit saqlash, yangilanish tartibi | Ha, qoralama | Kiberxavfsizlik markazi talablari ro'yxati so'raladi (O'zMSt 841:2026) |
| OFD sinov protokoli | Emulyatorga qarshi | OFD xulosasi uchun |
| Mualliflik huquqi (EHM dasturi guvohnomasi) | Ariza tayyorlansa bo'ladi | Qayerda va qancha muddatda, bu tadqiqotda tekshirilmadi [TOPILMADI] |
| Ehtiyot qismlar kafolat xati | Shablon | Dastur uchun nima yozilishi so'raladi |

## 7. 2-qadam: qurish rejasi

Hamma ish emulyatorga qarshi, mavjud fiskal interfeysimiz orqasida qilinadi. Real tushum bilan ishlatilmaydi [XULOSA].

1. **`pos_fiscal` Dart paketi.** Ichida: FiscalDriveService REST mijozi, chek mapperi va validatori, modul holati (blok, Z, 2 kun), yuborish sikli, POS lock autentifikatsiyasi. `testing.dart` da soxta FiscalDriveService + modul + sinov OFD bo'ladi, hujjatdagi xato kodlari bilan (`DATETIME_IS_IN_THE_PAST`, `SEND_ALL_RECEIPTS_FIRST`, `CANNOT_CLOSE_EMPTY_ZREPORT`, `LOCKED_SYNC_WITH_SERVER`, `POS_AUTH_FAIL`, `RECEIPTS_MEMORY_FULL` va boshqalar). Qo'lda sinash uchun lokal HTTP server rejimi ham bo'ladi (127.0.0.1:3449 shaklida).
2. **Markaz kassada fiskal agent** (`pos_lan` markazi, Windows va Linux). To'lov yopilganda: chek ma'lumoti → GetTXID → RegisterTXID → QR bilan chop etish → natija backend'ga (onlayn darhol, oflayn `pos_sync` navbati orqali). Smena ochish va yopish modul Z ga bog'lanadi.
3. **Backend: `rizqim_vkassa` drayveri** `config('fiscal.drivers')` ga qo'shiladi. Markaz kassa natijasini qabul qiladigan endpoint ham qo'shiladi. FiscalService chekni provayderni chaqirib emas, shu natija bo'yicha "chiqarilgan" deb belgilaydi. Idempotentlik `fiscal_receipts.uuid` bo'yicha qoladi. Sandbox va smart kassa drayverlari o'zgarmaydi.
4. **PHP tomonida bir xil validator**, toki modul rad etadigan chek markaz kassaga yetib bormasin. Unga o'lchov birligi kodi (menyu birligi va to'lov xizmatlari uchun sozlama) va nomni o'girish qoidasi qo'shiladi.
5. **Qaytarish**: RefundInfo asl chek javobidan olinadi. Boshqa kassa chekini qaytarish uchun QR skanerlash yoki qo'lda kiritish bo'ladi.
6. **Ogohlantirishlar**: modulning `FirstUnacknowledgedReceiptTime` maydoni bo'yicha 24 va 36 soatda ogohlantirish, 48 soatda blok. Menejer panelida modul holati ko'rinadi.
7. **Naqd taqiqi**: MXIK ro'yxati sozlamada (keyin rasmiy manbadan yangilanadi), taqiqlangan pozitsiyali chekda naqd tugmasi o'chadi.
8. **Ochiq savollar uchun bayroqlar**: bo'nakni hisobga olish usuli, nasiya qismi, `OwnerType`, QR provayder kodi sozlanadigan qilib qoldiriladi. Javob kelganda kod o'zgarmaydi, faqat sozlama o'zgaradi.
9. **Hujjat qoralamalari**: texnik pasport, foydalanuvchi va inspektor qo'llanmalari, Kiberxavfsizlik paketi, emulyatordan chek namunalari.
10. **Rasmiy dastur kelganda**: shu testlarni rasmiy emulyator va sinov serveriga qarshi ishga tushirish, farqlarni tuzatish.

Taxminiy hajm: 1–4-bandlar 3–4 hafta, 5–9-bandlar yana 2–3 hafta. Bu oldingi rejadagi 6–8 haftaga mos [XULOSA].

## 8. Yuborishga tayyor savollar

Mavjud xatlar (`docs/fiscal/letters/01`, `02`) asosiy savollarni allaqachon so'raydi. Quyidagilar shu tadqiqotdan chiqqan **qo'shimcha** savollar. Ularni 02-xatga ilova qilish mumkin.

**A. OFD ("Yangi texnologiyalar")**
1. FiscalDriveService v10 o'rnatuvchisi (Windows x64, Linux x64/arm), sinov serveri va FM 0400 emulyatorini qanday olamiz? Swagger (OpenAPI) faylini ham bera olasizmi? github.com/qo0p omborlari sizning rasmiy hujjatingizmi?
2. Ishchi OFD serverlarining manzillari qanday beriladi? Sinov serveringiz (masalan test.ofd.uz) dasturchilar uchun ochiqmi?
3. Bron avansi keyingi sotuv chekida qanday hisobga olinadi: `Other`, `Discount` yoki kamroq `ReceivedCash/ReceivedCard` orqalimi? Nasiyaga sotilgan chekning to'lanmagan qismi-chi?
4. QR to'lovi `ReceivedCard` da beriladimi? `QRPaymentProvider` kodlari ro'yxati va `OwnerType` ma'lumotnomasi qayerda? `Units` uchun qaysi kodlar ro'yxati ishlatiladi?
5. Bir restoranda bitta modul bilan bitta "markaz" kassa ishlasa, ofitsiant planshetlari esa to'lovni unga lokal tarmoq orqali yuborsa, bu "bitta modul bitta virtual kassada" talabiga mosmi? Har bir to'lov oladigan planshetga alohida modul kerakmi?
6. POS lock kalitini kim yaratadi va saqlaydi: virtual kassa ishlab chiquvchisimi yoki TXKMmi? TXKM almashtirilganda kalit qanday almashtiriladi?
7. OFD xulosasi uchun sinov dasturi (test-keyslar) bormi? Xulosa narxi qancha?

**B. Soliq qo'mitasi**
8. 2-ilova 6-band virtual kassa uchun "muvofiqlik sertifikatlari"ni talab qiladi, 4-ilova 11-band esa sertifikatni faqat onlayn-NKM uchun so'raydi. Virtual kassaga sertifikat kerakmi, kerak bo'lsa qaysi organ beradi?
9. Naqd taqiqlangan MXIK ro'yxati kassalarga qaysi rasmiy manbadan (API yoki fayl) beriladi?
10. "Ehtiyot qismlar kafolat xati" dastur (virtual kassa) uchun nimani o'z ichiga olishi kerak?

**C. Kiberxavfsizlik markazi**
11. Virtual kassa dasturiga ekspertiza uchun hujjatlar ro'yxati, ariza shakli, narxi va muddati qanday? Texnik topshiriqni oldindan kelishish kerakmi? O'zMSt 841:2026 qo'llanadimi?

**D. TXKM hamkor**
12. Uchinchi tomon virtual kassasiga (RIZQIM) xizmat ko'rsatasizmi? POS lock kalitini qanday boshqarasiz? Bir kassa uchun modulni faollashtirish va xizmat narxi qancha?

## 9. Hali noma'lum

- FiscalDriveService dasturining o'zi va Swagger shakli (HTTP metodlari, parametrlar).
- Ishchi OFD manzillari; `OwnerType`, QR provayder va o'lchov birligi kodlari.
- Bo'nakni hisobga olish va nasiya qismining ko'rinishi.
- Modulni bir nechta planshet bilan yoki markazda ishlatish mumkinligi; bulut varianti.
- Virtual kassaga muvofiqlik sertifikati kerakmi; OFD xulosasi va Kiberxavfsizlik ekspertizasi narxi.
- Reyestrlarning bugungi ro'yxati; raqib bo'lmagan TXKM.
- POS lock kaliti kimda turadi.
- Mualliflik huquqini ro'yxatdan o'tkazish tartibi (tekshirilmadi).

## Manbalar

- [github.com/qo0p/fiscal-drive-service](https://github.com/qo0p/fiscal-drive-service) — FiscalDriveService v10 dasturchi qo'llanmasi, 16.10.2026 [BIRLAMCHI, noshiri tasdiqlanmagan]
- [github.com/qo0p/acrsim-android](https://github.com/qo0p/acrsim-android) — FM 0400 tavsifi, MessageV6, 28.10.2026 [BIRLAMCHI, noshiri tasdiqlanmagan]
- [github.com/qo0p](https://github.com/qo0p) — akkaunt sahifasi (e-imzo-doc ham shu yerda)
- [lex.uz VM 943](https://lex.uz/docs/-4603329) — 1-, 2-, 3-, 4-ilovalar [BIRLAMCHI]
- [gov.uz/soliq, 28.04.2026](https://gov.uz/ru/soliq/news/view/159141) — reyestrdan chiqarilganlar [BIRLAMCHI]
- [docs.artix.su](https://docs.artix.su/pages/viewpage.action?pageId=93946891) — Artix virtual kassasi [BIRLAMCHI]
- [buxgalter.uz, 01.04.2026 talablari](https://buxgalter.uz/oz/publish/doc/text214454_2026_yil_1_apreldan_boshlab_yangi_talablar_buyicha_onlayn-nkt_bilan_qanday_ishlash_kerak) [MEDIA]
- [buxgalter.uz, FM va ERI to'lovi](https://buxgalter.uz/publish/doc/text173416_oplata_za_ecp_i_fiskalnyy_modul_rekvizity) [MEDIA]
- [Yandex Eda hamkor yordami, QQS 01.06.2026](https://yandex.com/support/eda-vendor-uz/ru/vat) [MEDIA]
- [@exode-team/ofd-uz](https://unpkg.com/@exode-team/ofd-uz@1.0.4/README.md) [MEDIA]
- [mxik-js (tasnif.soliq.uz API)](https://github.com/azabroflovski/mxik-js) [MEDIA]
- [davr.dev, iiko OFD plagini](https://davr.dev/projects/ofd) [MEDIA]
- [fiscaldriveapi.software.informer.com](https://fiscaldriveapi.software.informer.com/) — FiscalDriveAPI noshiri (faqat qidiruv parchasi) [MEDIA]
- csec.uz (Kiberxavfsizlik markazi) — faqat qidiruv parchalari, sahifalar ochilmadi [MEDIA]
- soliq.uz va ofd.uz reyestrlari — ochilmadi [TOPILMADI]

Tafsilotlar va dalillar: `docs/research/research_notes/Virtual kassa texnik talablari/` papkasida (fiskal modul va FiscalDriveService, huquqiy tartib va hujjatlar, RIZQIM kodiga moslik tahlili).
