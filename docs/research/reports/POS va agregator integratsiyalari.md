# Bitta Yandex protokoli uch eshikni ochadi

Rizqim integratsiyalarni bitta qatlam sifatida quradi. Uning markazida bitta kod to'plami turadi: **Yandex Eats'ning "hamkor" protokoli**. Bu protokolda POS server bo'ladi, agregator esa klient sifatida undan menyu, stop-list va buyurtma holatini so'raydi. Yandex bu protokolni ochiq OpenAPI fayli bilan e'lon qilgan. Test to'plamida esa **faqat O'zbekiston uchun MXIK (IKPU) kodining 17 belgili tekshiruvi** bor, ya'ni xuddi shu API O'zbekistonda ishlaydi ([Yandex Eda API](https://yandex.ru/dev/eda-vendor/doc/ru/ref/); [llms-full.txt](https://yandex.ru/dev/eda-vendor/doc/ru/llms-full.txt)) [RASMIY HUJJAT]. Eng katta mahalliy restoran POS'i **Alipos ham aynan shu yo'llar bilan hamkor API ochgan**: `/security/oauth/token`, `/menu/{id}/composition`, `/menu/{id}/availability`, `POST /order`, `eatsId`. Bu ochiq GitHub kodida ko'rinadi ([khajiev13/restaurant-mini-app](https://github.com/khajiev13/restaurant-mini-app)) [KOD]. REGOS esa Yandex Eats va Uzum Tezkor uchun bir xil sozlamani ko'rsatadi: Client ID, Secret va EndPoint ([REGOS: Yandex Eats](https://docs.regos.uz/ru/regos-sm/operations/settings-and-security/integrations/yandex-eats); [REGOS: Uzum Tezkor](https://docs.regos.uz/ru/regos-sm/operations/settings-and-security/integrations/uzum-tezkor)) [YB]. Demak, protokolning ikkala tomoni ham kerak bo'ladi. **Server tomoni** Rizqim POS'iga Yandex va (tasdiqlansa) Uzum buyurtmalarini oladi. **Klient tomoni** RIZQIM marketpleysi buyurtmasini Alipos kabi POS'larga yuboradi. Alipos'dan tashqari POS'larda bu hali taxmin. Uzum protokoli Yandex'ga mos ekani ham taxmin, uni Uzum menejeridan yozma so'rash kerak. Uchta yo'nalishning ustuvorligi: **avval RIZQIM buyurtmasi joyning kassasiga** (Alipos, keyin iiko), **keyin Yandex va Uzum buyurtmasi Rizqim kassasiga**, **keyin boshqa POS'lar uchun ochiq hamkor API**. Fiskal chek bo'yicha qoida: **bitta pulga bitta chek**. Uni chiqarish vazifasi pul qayerda olinganiga qarab joy, to'lov tizimi yoki agregator zimmasiga tushadi. Ikkinchi chek esa joy aylanmasini ikki marta hisoblaydi. 4-bosqich rejasida (docs/PHASE4_PLAN.md) "fuqarolarning shaxsiy ma'lumoti qonunga ko'ra O'zbekistondagi serverda saqlanishi kerak" deyilgan. Bu endi to'g'ri emas. **2026-yil 27-martdan** majburiy lokalizatsiya faqat biometrik, genetik va telekom abonent ma'lumotiga qo'llanadi ([lex.uz, ZRU-547](https://lex.uz/uz/docs/-4396419)) [TASDIQLANGAN]. Shunga qaramay serverni O'zbekistonda saqlash tavsiya qilinadi, lekin boshqa sabab bilan: soliq API'si va Marketpleys maqomi buni talab qiladi.

## Hisobotni qanday o'qish kerak

Hisobot 2026-yil 25-oktabrda yozilgan beshta tadqiqot eslatmasiga tayanadi:

- iiko (iikoCloud API) — rasmiy OpenAPI fayli o'qilgan;
- R-Keeper, Poster va mahalliy POS'lar (Alipos, Clopos, Mison, REGOS, BILLZ va boshqalar);
- Yandex Eats, Uzum Tezkor va xorijiy agregatorlarning API'lari;
- integratsiya hublari (HubRise, Deliverect, UrbanPiper, Otter) va ularning hamkor dasturlari;
- fiskal, shaxsiy ma'lumot va raqobat qoidalari.

Avvalgi ikki hisobotdan ("Alipos va agregator POS modeli", "POS bozori narxlar va fiskal terminallar") hali to'g'ri bo'lgan faktlar olindi: bozor ulushlari, Alipos narxlari, VM 393 taqiqi, fiskal yo'llari. Rizqim kodidagi holat ham hisobga olindi. Masalan, Click va Payme orqali to'langan buyurtma uchun fiskal chekni hozir to'lov tizimining o'zi chiqaradi, restoran STIRi chekda komitent bo'lib turadi (docs/API.md, "Onlayn to'lov fiskal cheki (P3-14)"). Zid kelgan joyda yangi eslatmalar ustun.

**Uch yo'nalish** butun hisobot davomida bir xil nomlanadi:

| Yo'nalish | Nima | Kim server, kim klient |
|---|---|---|
| **1. RIZQIM → joyning POS'i** | Marketpleys buyurtmasi joyning iiko, Alipos, R-Keeper va boshqa kassasiga tushadi. Menyu, narx va stop-list Rizqim'ga qaytadi | POS server, Rizqim klient |
| **2. POS vendorlari → RIZQIM** | Boshqa POS ishlab chiqaruvchilar Rizqim'ning ochiq hamkor API'siga o'zi ulanadi | Rizqim server, vendor klient |
| **3. Agregator → Rizqim POS** | Yandex Eats va Uzum Tezkor buyurtmalari Rizqim kassasi va oshxona ekraniga tushadi | Yandex modelida Rizqim server, agregator klient |

**Belgilar:**

| Belgi | Ma'nosi |
|---|---|
| **[TASDIQLANGAN]** | lex.uz matni yoki tadqiqotchi o'zi tekshirgan fakt |
| **[RASMIY HUJJAT]** | Vendor yoki agregatorning dasturchilar uchun hujjati (endpoint va maydonlar bilan) |
| **[KOD]** | Ochiq GitHub repozitoriyasidagi ishlaydigan koddan olingan dalil |
| **[YB]** | Yetkazib beruvchining o'z sahifasi yoki reklamasi |
| **[OAV]** | Yangilik sayti |
| **[ANIQ EMAS]** | Qidiruv parchasi, bitta ikkilamchi manba yoki bir-biriga zid manbalar |
| **[XORIJIY]** | O'zbekistondan tashqari bozor |
| **[XULOSA]** | Manbada yo'q, tahlildan chiqqan fikr. Uni taklif sifatida o'qing |
| **[ESKI]** | 2024-yil oktabrdan oldingi manba |
| **[YURIST]** | Yurist yoki Soliq qo'mitasining yozma javobini talab qiladigan savol |

Mehnat bahosi (haftalarda) hamma joyda **[XULOSA]**: bitta tajribali backend dasturchi uchun taxmin, manbalardan olinmagan. Bu hujjat yuridik xulosa emas.

## 1. Yandex protokoli bozorning yashirin standartiga aylangan

### Protokol qanday ishlaydi

Yandex Eats'ning restoranlar uchun spetsifikatsiyasi "API для интеграции сервиса Яндекс Еда" (v1.0-oas3, oxirgi o'zgarish 01.09.2026) deb ataladi. Unda barcha metodlar **"hamkor tomonida amalga oshirilishi kerak"**. Yandex klient sifatida resurs so'raydi yoki buyurtma yaratadi, ya'ni bu **pull model** ([Yandex Eda API](https://yandex.ru/dev/eda-vendor/doc/ru/ref/); [OpenAPI JSON](https://yandex.ru/dev/eda-vendor/doc/ru/ref/external-api.openapi.json)) [RASMIY HUJJAT]. POS quyidagilarni server sifatida ochadi:

| Endpoint | Vazifasi |
|---|---|
| `POST /security/oauth/token` | OAuth2 client_credentials. Refresh token yo'q. Server domen bo'lishi shart, IP manzil qabul qilinmaydi |
| `GET /menu/{restaurantId}/composition` | To'liq menyu. `lastChange` o'zgarmasa, Yandex menyuni qayta yuklamasligi mumkin |
| `GET /menu/{restaurantId}/availability` | Stop-list va qoldiq. `stock` 0 bo'lsa, taom ro'yxatdan yo'qoladi |
| `GET /restaurants`, `/restaurants/availability` | Hamkorning filiallari va ularning ochiqligi |
| `POST /order` | Buyurtma: Yandex kuryeri, hamkor kuryeri yoki olib ketish. Javobda POS'ning o'z ID'si |
| `GET/PUT/DELETE /order/{id}`, `GET/PUT /order/{id}/status` | Buyurtma, holat va bekor qilish. Yandex holatni POS'dan **o'zi so'raydi** |

Holatlar faqat oldinga yuradi: `NEW → ACCEPTED_BY_RESTAURANT → COOKING → READY → TAKEN_BY_COURIER → DELIVERED`, `CANCELLED` esa istalgan holatdan mumkin. Agar taom stop-listda bo'lsa, `POST /order` ga **406 `unavailable_goods`** javobi qaytariladi. Shunda Yandex mijozga o'zgartirilgan buyurtmani taklif qiladi ([OpenAPI JSON](https://yandex.ru/dev/eda-vendor/doc/ru/ref/external-api.openapi.json)) [RASMIY HUJJAT]. Kichikroq "push" qismi ham bor. U orqali POS Yandex'ni chaqirib menyu yoki stop-listni darhol qayta yuklatadi (`/menu/import/initiation`), filialni vaqtincha yopadi (`/block`, sabab `too_busy` yoki `technical_problem`) va 180 kunlik buyurtmalar tarixini oladi (`/v1/orders-history`) [RASMIY HUJJAT].

### Nima tasdiqlangan, nima taxmin

Bu hisobotning asosiy topilmasi shu jadvalda. Uni ehtiyotkorlik bilan o'qish kerak:

| Da'vo | Holat | Dalil |
|---|---|---|
| Yandex Eats O'zbekistonda shu API bilan ishlaydi | **Tasdiqlangan** | Yandex'ning Postman test to'plami `mxikCodeUz` maydoni aynan 17 belgi ekanini tekshiradi, izohi "faqat O'zbekiston uchun" ([llms-full.txt](https://yandex.ru/dev/eda-vendor/doc/ru/llms-full.txt)) [RASMIY HUJJAT] |
| Mahalliy POS (REGOS) Yandex uchun server tomonni yozgan | **Tasdiqlangan** (vendor hujjati) | REGOS Client ID va Secret yaratadi, "Yandex Eats buyurtmalari keladigan manzil" EndPoint'ini ko'rsatadi. Restoran Yandex bilan shartnomani o'zi tuzadi ([docs.regos.uz](https://docs.regos.uz/ru/regos-sm/operations/settings-and-security/integrations/yandex-eats)) [YB] |
| Alipos hamkor API'si Yandex yo'llarini takrorlaydi | **Tasdiqlangan** (bitta ochiq loyiha kodi) | `web.alipos.uz/api/Integration/v1/...`, token `/security/oauth/token`, `eatsId`, `ACCEPTED_BY_RESTAURANT`, `TAKEN_BY_COURIER`. Alipos uchinchi tomon Telegram botiga (MrPubBot) kalit bergan ([GitHub](https://github.com/khajiev13/restaurant-mini-app)) [KOD]. Rasmiy hujjat yo'q |
| Uzum Tezkor ham Yandex'ga mos protokol ishlatadi | **Taxmin** | REGOS'ning Uzum sahifasidagi sozlamalar Yandex'nikiga bir xil. Matnda hatto "Yandex Eats'ga yuborilmaydi" degan nusxa ko'chirilgan jumla qolgan ([docs.regos.uz](https://docs.regos.uz/ru/regos-sm/operations/settings-and-security/integrations/uzum-tezkor)) [YB]. Uzum ochiq API hujjati e'lon qilmagan, GitHub'da ham hech narsa topilmadi |
| Mison, iiko va BUSOFT ham Yandex serverini uchinchi tomonga ochadi | **Taxmin**, tekshirilmagan | Ular Yandex'ga ulangan, demak serverni yozgan bo'lishi kerak. Lekin R-Keeper Rossiyada Yandex'ga maxsus shlyuz orqali ulanadi (`ws.ucs.ru/yandex/`, `client_id = yandex`), u Rizqim'ni qabul qilmaydi ([docs.rkeeper.ru](https://docs.rkeeper.ru/delivery/yandeks-eda-87557916.html)) [RASMIY HUJJAT] |

### Rizqim uchun ma'nosi

Rizqim **"Yandex protokoli to'plami"**ni bir marta yozadi [XULOSA]. Unga Yandex OpenAPI faylidan olingan DTO va validatorlar hamda Yandex Postman to'plami asosidagi qabul testlari kiradi. Bu to'plam ikki rolda ishlaydi:

1. **Server** (3-yo'nalish): Rizqim POS'i Yandex Eats'ga, Uzum mos chiqsa unga ham, buyurtma qabul qiladigan tomonga aylanadi.
2. **Klient** (1-yo'nalish): RIZQIM marketpleysi Alipos va shu protokolni ochgan boshqa POS'larga buyurtma yuboradi, menyu va stop-listni ulardan oladi.

Bir yon foyda ham bor. Rizqim o'z klientini o'z serveriga qarshi test qila oladi. Yandex Postman to'plami ikkalasining umumiy "imtihoni" bo'ladi.

Bu 2-yo'nalishni ham soddalashtiradi. Yandex'ga ulangan mahalliy POS vendori Rizqim uchun yangi kod yozmasligi mumkin. U o'z Yandex serveriga "Rizqim" manbasini qo'shib, Rizqim'ga host, client_id va secret beradi, Rizqim esa Yandex kabi so'raydi. Bu taxmin. Uni birinchi bo'lib Alipos va Mison bilan suhbatda tekshirish kerak [XULOSA].

## 2. RIZQIM buyurtmasi joyning kassasiga: har POS'ga o'z yo'li

Bu yo'nalish marketpleys o'sishi uchun eng muhimi. iiko yoki Alipos ishlatayotgan joy buyurtmani planshetdan kassaga qo'lda qayta termaydi, aks holda Rizqim'ni rad etadi. Uzum ham hamkorlik e'lonida aynan shu foydani sotgan: buyurtma to'g'ridan-to'g'ri oshxonaga tushadi, stop-list avtomatik olinadi, yetkazish 5–7 daqiqa tezlashadi ([repost.uz](https://repost.uz/uzum-and-iiko)) [OAV].

### Umumiy matritsa (25.10.2026)

| POS | Ochiq hujjat | Buyurtma yuborish | Menyu va narx | Stop-list | Holat qaytishi | Kim ruxsat beradi | Manba |
|---|---|---|---|---|---|---|---|
| **iiko** | Ha, OpenAPI | `POST /api/1/deliveries/create` (asinxron buyruq) | `/api/2/menu` + `/api/menu/v3/by_id` | `/api/1/stop_lists` + `StopListUpdate` webhook | Webhook + `by_revision` polling | Rizqim — iiko dasturchi portalida; joy — iikoWeb'da API kaliti | [RASMIY HUJJAT] |
| **Alipos** | Yo'q | `POST /api/Integration/v1/order` (Yandex shakli) | `/menu/{id}/composition` | `/menu/{id}/availability` + stop-list webhook | `order-status` webhook | Alipos (tijoriy kelishuv) | [KOD] |
| **R-Keeper** | Ha, r_k Delivery | `POST /orders/api/v1/orders` | `GET /menu/view` | `isContainInStopList` maydoni | Webhook (admin-panelda) | Joy, diler orqali; Delivery_Api litsenziyasi | [RASMIY HUJJAT] |
| **Clopos** | Ha | `POST /v1/orders` (StandardOrder v2) | Mahsulot va narx ro'yxati | `GET /v1/menu/products/stop-list` | **Webhook yo'q**, polling | Clopos dasturchi hisobini tasdiqlaydi | [RASMIY HUJJAT] |
| **Poster** | Ha | `incomingOrders.createIncomingOrder` | `menu.getProducts` (filial va manba narxi) | Alohida yo'q (`visible`, qoldiq) | Webhook (MD5) | Restoran OAuth ilovani o'zi o'rnatadi | [RASMIY HUJJAT] |
| **Mison** | Yo'q | "Партнёрское API" faqat "Под ключ" tarifida | ? | ? | ? | Hamkorlik | [YB] |
| **REGOS** (chakana) | Yo'q (faqat sozlash) | Yandex/Uzum EndPoint'i | Narx turi bo'yicha | ? | ? | Hamkorlik | [YB] |
| **VKassa, Dastur-xon, Paloma365, RAOS** | Yo'q | Topilmadi | — | — | — | Hamkorlik so'rovi | [TASDIQLANGAN: saytda yo'q] |

Manbalar: [iikoCloud API](https://api-ru.iiko.services/docs); [docs.rkeeper.ru](https://docs.rkeeper.ru/delivery/vozmoyonosti-api-4041552.html); [developer.clopos.com](https://developer.clopos.com/docs/index.md); [dev.joinposter.com](https://dev.joinposter.com/en/docs/v3/web/incomingOrders/createIncomingOrder); [mison.uz](https://mison.uz/ru/); [raos.uz](https://raos.uz/).

### iiko: eng katta brendli baza, 2026-yilda yangi kirish tartibi

iiko — O'zbekistondagi eng ko'p tarqalgan brendli restoran POS'i. 2023-yilda mamlakatda ~1 000 iiko restoran bo'lgan. Uzum Tezkor'ning Toshkentdagi har uchinchi hamkori (~150 joy) iiko ishlatgan ([spot.uz, 07.08.2023](https://www.spot.uz/ru/2023/08/07/uzum/)) [OAV]. Tashqi marketpleys uchun to'g'ri yo'l — **iikoCloud API** (`api-ru.iiko.services`). Buyurtmani iikoFront'ga faqat shu bulut API yetkazadi, menyu va stop-listni o'qiydi va holatni webhook bilan qaytaradi ([iikoCloud API](https://api-ru.iiko.services/docs)) [RASMIY HUJJAT].

**2026-yilgi muhim o'zgarish — kirish tartibi.** Eski `apiLogin → /api/1/access_token` usuli eskirdi. 01.06.2026 dan unda yangi kalitlar ishlamaydi, **29.08.2026 dan butunlay o'chirilgan** ([iiko.ru, 11.04.2026](https://iiko.ru/news/perehod-na-novuyu-shemu-avtorizaczii-v-api/)) [RASMIY HUJJAT]. Endi integrator iiko dasturchi portalida (`public-api.iikoweb.ru/portal`) kompaniya sifatida ro'yxatdan o'tadi va `appId` hamda bir martalik `clientSecret` oladi. Joy esa iikoWeb'da (Integratsiyalar → API kalitlari) Rizqim uchun API kaliti yaratadi. Token uchala qiymat bilan olinadi va **1 soat** yashaydi, refresh yo'q ([iikoCloud API](https://api-ru.iiko.services/docs)) [RASMIY HUJJAT]. GitHub'dagi jamoat klientlarining ko'pi hali eski usulda. Ularning kirish kodidan nusxa olinmaydi [XULOSA].

**Buyurtma** `deliveries/create` bilan yuboriladi. Bu asinxron buyruq: javobda `creationStatus = InProgress` keladi, natija webhook yoki `commands/status` orqali bilinadi. Standart holatda buyurtma iikoFront'ga **8 soniyada** yetmasa bekor bo'ladi (`transportToFrontTimeout`). Shuning uchun Rizqim savatni qabul qilishdan oldin `terminal_groups/is_alive` bilan kassaning onlayn ekanini tekshirishi kerak [RASMIY HUJJAT; tekshiruv tavsiyasi XULOSA]. Rizqim buyurtma `id` (UUID) sini o'zi yaratadi va oldindan saqlaydi. Shunda qayta urinish ikkinchi buyurtma ochmaydi, `DuplicatedOrderId` xatosini qaytaradi [XULOSA]. Mijoz `customer.type: "one-time"` bilan yuboriladi. Spetsifikatsiyaga ko'ra bu turdagi mijoz joyning sodiqlik bazasiga qo'shilmaydi, ma'lumoti faqat shu buyurtmada ishlatiladi ([iikoCloud API](https://api-ru.iiko.services/docs)) [RASMIY HUJJAT]. Bu 6-bo'limdagi "maqsad cheklovi" talabiga ham to'g'ridan-to'g'ri javob beradi.

**To'lov va fiskal** uchun API to'lov qatoriga uchta belgi beradi: `isProcessedExternally`, `isPrepay`, `isFiscalizedExternally`. To'lov turining `printCheque` belgisi iikoFront fiskal chek chiqarishini hal qiladi [RASMIY HUJJAT]. Rizqim'da onlayn to'langan buyurtma uchun chekni Click yoki Payme chiqaradi. Shuning uchun bunday buyurtma iiko'ga `External`, `isFiscalizedExternally: true` va joyning fiskal bo'lmagan "Rizqim onlayn" to'lov turi bilan yuboriladi. Aks holda kassa ikkinchi chek chiqaradi (5-bo'lim) [XULOSA]. O'zbek fiskal plaginlari (Rahmat, E-POS) bu belgilarni Rossiyadagi kabi hurmat qilishi hujjatlashtirilmagan. Buni pilotda tekshirish kerak.

**Joy tomonidan shartlar.** Joy API'si bor tarifda bo'lishi kerak. ITDO'da bu Pro (81 $/oy), unda "сторонних приложений (API)" bor ([itdo.uz](https://itdo.uz/)) [YB]. Enter-co narxlari terminal uchun 484 000 / 991 500 / 1 332 000 so'm ([enter-co.uz](https://enter-co.uz/)) [YB]. O'zbekistonda iiko'ni dilerlar sotadi va o'rnatadi: ITDO, A-ONE, Soft24, Enter-co. Ular amalda "darvozabon". Bitta yoki ikkita diler bilan hamkorlik test tashkiloti va ulanishni tezlashtiradi [XULOSA]. Ochiq test muhiti (sandbox) topilmadi.

**Mehnat:** ~15 endpointli yupqa klient (kirish, tashkilotlar, terminal guruhlari, menyu, stop-list, lug'atlar, buyurtma, holat, bekor qilish, webhook sozlash), menyu xaritasi va ulash ustasi. **3–4 hafta** [XULOSA].

### Alipos: eng arzon ulanish, lekin faqat kelishuv bilan

Alipos e'lon qilingan eng katta mahalliy restoran POS bazasi: sayt 1 751 muassasa deydi ([alipos.uz/ru](https://alipos.uz/ru)) [YB]. 2025-yil noyabrida esa "1 291 faol restoran" deyilgan ([Pivot.uz](https://pivot.uz/alipos-startup-received-a-200000-investment-offer-on-the-taqdimot-tv-show/)) [OAV]. Choyxona segmentiga ham eng yaqin. Ochiq kodda Alipos API'si quyidagicha ko'rinadi ([GitHub, alipos_api.py](https://github.com/khajiev13/restaurant-mini-app)) [KOD]:

- token: `POST web.alipos.uz/security/oauth/token`;
- menyu: `/api/Integration/v1/menu/{id}/composition`, mavjudlik: `/availability`;
- buyurtma: `POST /order`, payload'da `platform`, `eatsId`, `paymentInfo`, `items`;
- Alipos hamkorga ikki webhook yuboradi: buyurtma holati va stop-list. Ikkalasi hamkor bergan `clientId`/`clientSecret` sarlavhalari bilan tekshiriladi.

Alipos 25.10.2026 da ochilmadi, rasmiy hujjat yo'q. Bu kod bitta repozitoriyadan olingan, uning litsenziyasi o'qilmagan. Shuning uchun undan faqat protokol tushunchasi olinadi, koddan nusxa olinmaydi [XULOSA].

Texnik tomondan bu eng arzon konnektor. Yandex protokoli to'plami tayyor bo'lsa, ustiga **1–2 hafta** kerak bo'ladi [XULOSA]. To'siq tijoriy. Alipos agregator ulanishini faqat Pro tarifidan sotadi: har agregatorga **10 $/oy va 100 $ bir martalik** ([alipos.uz/ru/tariffs](https://alipos.uz/ru/tariffs)) [YB]. Rizqim katalogga "yana bitta agregator" bo'lib qo'shilsa, joy shu pulni to'lashi mumkin. Alipos POS bozorida Rizqim POS'ining raqibi ham. Muzokarada ikki savol hal qilinadi: Alipos Rizqim'ga "platforma" nomi va kalit beradimi, va ulanish pulini kim to'laydi (12-bo'lim).

### R-Keeper: hujjat bor, lekin yo'l litsenziya va diler orqali

R-Keeper'da tashqi buyurtma uchun ochiq yo'l bor. U bulutdagi **r_k Delivery** moduli orqali o'tadi. Restoran admin-panelda "API orqali ulanish" yaratadi va integratorga Client ID (16 belgi) va Client Secret (128 belgi) beradi. Integrator token oladi (3600 soniya), `GET .../menu/view` orqali menyu, narx va `isContainInStopList` maydonini o'qiydi, `POST .../orders` bilan buyurtma yaratadi, holatni webhook bilan oladi. Yetkazilmagan webhook navbatda muddatsiz saqlanadi ([docs.rkeeper.ru, API ulanish](https://docs.rkeeper.ru/delivery/podklyuchenie-po-api-4043842.html); [buyurtma yaratish](https://docs.rkeeper.ru/delivery/sozdanie-zakaza-4043824.html)) [RASMIY HUJJAT].

Lekin har obyektga pullik obuna litsenziyalari kerak: **Delivery_Api**, "XML-interfeys для WSA" (obyektga bitta), Delivery_Base yoki Delivery_Filial. Narxlar e'lon qilinmagan ([docs.rkeeper.ru, litsenziyalar](https://docs.rkeeper.ru/delivery/litsenzirovanie-4043810.html)) [RASMIY HUJJAT]. `delivery.ucs.ru` Rossiyadagi bulut. O'zbekistondagi R-Keeper restoranlari undan foydalanishi tasdiqlanmagan.

O'zbekistonda R-Keeper dileri **BUSOFT** o'z **hub'ini** qurgan. Hub POS'dan menyu va stop-listni olib agregatorga yuklaydi, buyurtmani esa darhol R-Keeper'ga tushiradi. Uzum Tezkor, Express 24 va Yandex Eda bilan ishlaydi ([rkeeper.uz](https://rkeeper.uz/stati/post/r_keeper-integraciya-agregatorov-dostavki-edy-uzum-tezkor-express-24-yandex-eda)) [YB]. Shuning uchun R-Keeper bazasiga eng tez yo'l — Rizqim'ni BUSOFT hub'iga "yana bitta agregator" qilib qo'shish. Bu tijoriy kelishuv, kod Rizqim tomonida kam bo'ladi. Zaxira yo'l r_k Delivery API, **2–3 hafta** [XULOSA]. Kassa serveriga to'g'ridan-to'g'ri XML ulanish hujjati yopiq va eng mo'rt yo'l, u tavsiya qilinmaydi.

### Clopos va Poster: hujjat eng yaxshi, bozordagi ulush kichik

**Clopos** mahalliy POS'lar ichida eng yaxshi hujjatlangan ([developer.clopos.com](https://developer.clopos.com/docs/index.md)) [RASMIY HUJJAT]:

- OAuth ilova. Dasturchi hisobini Clopos tasdiqlaydi (dev@clopos.com).
- Buyurtma "Wolt va Bolt buyurtmalari keladigan" StandardOrder v2 shaklida. `Idempotency-Key` sarlavhasi bor.
- Har yangi buyurtma `PENDING_ACCEPTANCE` holatidan boshlanadi.
- Alohida stop-list endpointi bor.
- Kamchiligi: **webhooklar hali yo'q**, holat polling bilan olinadi. Limit 200 so'rov / 10 soniya, bu yetarli.

**Poster** eng ochiq ([dev.joinposter.com](https://dev.joinposter.com/en/docs/v3/start/account)) [RASMIY HUJJAT]:

- Dasturchi hisobi bepul. Token 2 yil amal qiladi.
- Ilovani katalogga chiqarmasdan tanlangan hisoblarda ishlatish mumkin.
- `createIncomingOrder` yetkazib berish narxini, oldindan to'lovni va mijoz manzilini qabul qiladi.
- Muhim imkoniyat: `menu.getProducts` har **buyurtma manbasi** uchun alohida narx va ko'rinish beradi (`sources[]`). Joy Poster'da "Rizqim" manbasini yaratsa, marketpleys narxi zal narxidan alohida turadi.
- Kamchiligi: alohida stop-list obyekti yo'q, mavjudlik `visible`/`hidden` va qoldiq orqali bilinadi.
- Poster telefon bo'yicha mijozni o'z bazasida topadi yoki yaratadi. Shaxsiy ma'lumot nuqtai nazaridan buni hisobga olish kerak.
- Poster katalogida O'zbekiston uchun fiskal ilova bor, lekin Yandex yoki Uzum ulanishi yo'q ([joinposter.com/en/applications](https://joinposter.com/en/applications)) [TASDIQLANGAN].

Ikkalasining O'zbekistondagi mijozlar soni noma'lum. Shuning uchun ular talab paydo bo'lganda quriladi, har biri **2–3 hafta** [XULOSA].

### Qolganlar va integratsiyasiz joy uchun zaxira

**Mison** "hamkor API"ni faqat eng yuqori, narxi kelishiladigan tarifda e'lon qiladi. Yandex Eda va Uzum Tezkor'ni esa "Полный" tarifida ulaydi ([mison.uz](https://mison.uz/ru/)) [YB]. Demak, Mison'da Yandex serveri bor bo'lishi kerak. Mison bilan "Yandex rejimi" (3-bo'lim) birinchi navbatda so'raladi [XULOSA].

VKassa, Dastur-xon, Paloma365 va RAOS'da ochiq API topilmadi. BILLZ va RAOS chakana savdo uchun, restoran marketpleysi uchun ahamiyati past. Rizqim keyinchalik do'konlarga kengaysa, BILLZ API'si tayyor turibdi ([docs.billz.io](https://docs.billz.io/)) [RASMIY HUJJAT].

Integratsiyasi yo'q joy uchun zaxira avvalgidek qoladi: buyurtma Rizqim'ning xodim ekranida va Telegram bildirishnomasida chiqadi. HubRise'ning "tiny-tablet"i shunga o'xshash oddiy namuna: hubdan kelgan buyurtmani chop etuvchi statik HTML/JS ilova ([github.com/hubrise](https://github.com/hubrise)) [XORIJIY]. Har bir xorijiy agregator ham integratsiya uzilganda planshet yoki kabinetni majburiy zaxira deb hisoblaydi. Delivery Hero buni talab qiladi ([DH POS docs](https://developers.deliveryhero.com/documentation/pos.html)) [XORIJIY].

## 3. Boshqa POS'lar uchun Rizqim ikki xil eshik ochadi

### "Yandex rejimi" va o'z hamkor API'si

2-yo'nalishda Rizqim'ga ikki eshik kerak [XULOSA]:

| Eshik | Kimga | Vendor nima qiladi | Rizqim nima qiladi |
|---|---|---|---|
| **A. Yandex rejimi** | Yandex Eats'ga allaqachon ulangan POS'lar (Alipos, Mison, REGOS va boshqalar) | Mavjud Yandex serverida "Rizqim" manbasini yoqadi, host, client_id va secret beradi | Yandex klientidek so'raydi: menyu, mavjudlik, buyurtma, holat |
| **B. RIZQIM Partner API v1** | Yandex serveri yo'q yoki boyroq funksiya xohlagan vendorlar (bron, olib ketish vaqti, kuryer holati) | Rizqim API'siga OAuth klient sifatida ulanadi, webhook yoki polling bilan buyurtma oladi | Ochiq hujjat, sandbox, sertifikatlash, katalog beradi |

A eshigi vendor uchun deyarli bepul. Shuning uchun u mahalliy bozorga tez kirishning yo'li. B eshigi uzoq muddatli standart va Rizqim'ning o'z modelini aks ettiradi.

### Partner API v1: HubRise andozasi, Uber va DoorDash qoidalari

Xorijiy hublar umumiy shaklga kelishgan. HubRise o'z modelini to'liq ochiq e'lon qilgan, u eng yaxshi namuna ([HubRise Catalog API](https://www.hubrise.com/developers/api/catalog-management); [Orders API](https://www.hubrise.com/developers/api/order-management)) [XORIJIY]:

- Menyu daraxt: kategoriya → mahsulot → SKU (o'lcham) → modifikator guruhi (`min_selections`, `max_selections`) → variant.
- Kombolar alohida, qatorlari bilan.
- Kanal bo'yicha narx va mavjudlik qoida sifatida beriladi (`price_overrides`, `restrictions`).
- Stop-list alohida, tez o'zgaradigan **inventory** resursi.
- Buyurtma qatorlari katalogdan nusxa sifatida saqlanadi. Har biri barqaror `ref` orqali katalogga bog'lanadi.

Rizqim'ning taklif qilinadigan API'si [XULOSA]:

| Qism | Taklif | Namuna |
|---|---|---|
| Resurslar | `location` (ochiq, band, yopiq), `catalog` (to'liq push, asinxron ish), `inventory` (stop-list, `until` bilan), `orders` (`accept` tayyorlash vaqti bilan, `reject` sabab bilan, holat yangilash), `events` | HubRise, DoorDash |
| Kirish | OAuth2. Restoran egasi Rizqim hamkor panelida filial darajasidagi ruxsatni tasdiqlaydi, token bitta filialga bog'lanadi | HubRise ([Authentication](https://www.hubrise.com/developers/api/authentication)) |
| Webhook | `X-Rizqim-Signature: t=<vaqt>,v1=<HMAC-SHA256>` xom tanaga, vaqt bilan (replay himoyasi) | HubRise, Uber ([Uber webhooks](https://developer.uber.com/docs/eats/guides/webhooks)) |
| Polling | `GET /v1/events` + `DELETE /v1/events/{id}`. NAT ortidagi kichik POS'lar va lokal serverlar uchun majburiy | HubRise passiv callback ([Callbacks](https://www.hubrise.com/developers/api/callbacks)) |
| Ishonchlilik | Har qanday 2xx qabul deb hisoblanadi. Qayta urinish 1, 2, 4, 8, 16, 32 daqiqada. Har yozuvda `Idempotency-Key`, har hodisada `event_id` | HubRise; UrbanPiper'dagi "201 → takroriy buyurtma" xatosidan saboq ([UrbanPiper FAQ](https://urbanpiper.helpjuice.com/55452-faqs/order-relay-webhook)) |
| Qabul muddati | ASAP buyurtma 3 daqiqada qabul yoki rad etiladi. 60–90 soniyada Telegram ogohlantirishi, muddat o'tsa avtomatik rad va pul qaytarish | Uber: 11,5 daqiqa va 90 soniyada robocall; DoorDash: 3–8 daqiqa ([DoorDash](https://developer.doordash.com/docs/marketplace/how_to/order_integration)) |
| Versiya | `/v1` yo'lida, versiya ichida faqat qo'shimcha o'zgarishlar, OpenAPI 3.1, o'zgarishlar sahifasi | HubRise, Deliverect |

Qabul muddati raqamlari (3 daqiqa, 60–90 soniya) O'zbekiston ma'lumotidan emas, Uber va DoorDash'dan olingan. Ularni pilot restoranlarda tekshirish kerak.

### Hamkor dasturi: vendorga bepul, Rizqim komissiyadan topadi

Monetizatsiyaning ikki modeli bor. **Hublar** restorandan filial uchun oylik oladi: HubRise **35 €/oy**, tarmoqda 30–27 € ([HubRise Pricing](https://www.hubrise.com/pricing)) [XORIJIY]. **POS platformalari** esa API'ni ariza, tasdiq va sertifikatlash orqasida yopadi va hamkordan daromad ulushi oladi. Toast 2019-yilda hamkorning umumiy mijozlardan tushumining **30%** ini "abadiy" olgani yozilgan, keyingi shartlari ochiq emas ([Reforming Retail](https://reformingretail.com/index.php/2019/07/16/as-we-predicted-toast-has-greatly-increased-their-pos-integration-fees/)) [XORIJIY; ESKI]. Square ilova hamkorlariga yo'naltirish uchun daromad ulushi beradi ([Square App Partner](https://squareup.com/gb/en/partnerships/app-partner)) [XORIJIY].

Rizqim hub emas, marketpleys. Shuning uchun [XULOSA]:

1. **API vendorlar uchun bepul.** Restoran faqat odatdagi komissiyani to'laydi. Toast'ning 30% ulushi yosh bozorda integratorlarni qochiradi.
2. **Sertifikatlash bosqichma-bosqich**, UrbanPiper va Deliverect andozasida ([UrbanPiper Testing](https://api-docs.urbanpiper.com/downstream/integration-certification/testing-and-validation); [Deliverect HMAC](https://developers.deliverect.com/v3.0/docs/validating-orders-in-pos-using-hmac)):
   - test do'konlari va "buyurtmani simulyatsiya qilish" tugmasi bor sandbox;
   - avtomatik test: qabul muddati, `completed` gacha barcha holatlar, sabab bilan rad etish, modifikatorli menyu, stop-list, takroriy yetkazish, imzo tekshiruvi;
   - production kaliti faqat sertifikatdan keyin beriladi;
   - hamkor panelida "RIZQIM integratsiyalari" katalogi.
3. **Vendor uchun rag'bat.** Vendor ulagan va marketpleysda faollashgan har bir joy uchun Rizqim komissiyasidan ulush beriladi. Masalan, birinchi 6 oyda ~10%. Bu avvalgi hisobotdagi agent haqi bilan bir xil. Square ham ulushni muddat bilan cheklaydi. Aniq raqam egasining qarori.

## 4. Yandex va Uzum buyurtmasi Rizqim kassasida

### Yandex Eats: server yoziladi, restoran kabinetda yoqadi

Rizqim POS backend'i Yandex spetsifikatsiyasining server tomonini ochadi. Bu ochiq HTTPS **domenda** turadi, IP manzil qabul qilinmaydi. Har bir filial uchun o'z OAuth client_id va secret'i bo'ladi. Restoran ularni Rizqim hamkor panelida "Yangi Client ID yaratish" tugmasi bilan oladi, REGOS kabi [XULOSA]. Ulanish tartibi Rossiya bozori uchun hujjatlashtirilgan, lekin O'zbekiston uchun tasdiqlanmagan ([agregatory.pro](https://agregatory.pro/blog/yandex-eda-integratsiya-iiko)) [ANIQ EMAS]:

1. Restoran Yandex Eats kabinetida integratsiya arizasini yuboradi.
2. Arizaga POS'ning host, client_id va secret'ini va filial kodini kiritadi.
3. Integratsiya **birinchi test buyurtmadan keyin** yoqiladi.

REGOS sahifasi O'zbekistonda ham shunga o'xshash tartibni ko'rsatadi. Restoran Yandex bilan shartnomani o'zi tuzadi va Client ID, Secret, Endpoint'ni Yandex menejeriga beradi [YB].

**Amalga oshirish qoidalari** [XULOSA, spetsifikatsiyadan]:

- `POST /order` tez va idempotent javob beradi. Takroriy so'rov `eatsId` bo'yicha aniqlanadi. Restoranlar API'si uchun vaqt chegarasi e'lon qilinmagan, chakana savdo API'sida `POST /order` uchun 210 soniya, holat so'rovi uchun 5 soniya ([Yandex retail timeouts](https://yandex.ru/support/retail/ru/ref/overview/timeout.md)) [RASMIY HUJJAT].
- Holatni Yandex o'zi so'raydi. Shuning uchun kassa va oshxona ekranidagi holat har doim `GET /order/{id}/status` orqali o'qiladigan bo'lishi kerak. Kassirning "qabul qildim" tugmasi `ACCEPTED_BY_RESTAURANT` ni o'rnatadi.
- Stop-list o'zgarganda `POST /menu/import/initiation` (`menu_stop_list`) chaqiriladi, shunda kechikish qisqaradi. Stop-listdagi taomga buyurtma kelsa, 406 qaytariladi.
- Har taomda MXIK bo'lishi shart. Rizqim menyusida MXIK maydoni allaqachon bor (fiskal uchun). Narxi 0 bo'lgan taom yuborilmaydi.
- Yandex uchun **alohida narx ro'yxati** kerak. Rizqim'da `channel_prices` bor (POS_SPEC 2.6). Unga `yandex_eats` va `uzum_tezkor` kanallari qo'shiladi.
- Kassadagi "Pauza/band" tugmasi Yandex `/block` (`too_busy`) ga bog'lanadi.
- Kassa va oshxona ekranida qo'shimcha UI kerak: agregator belgisi, qabul taymeri, "chek tashqarida chiqarilgan" belgisi.

**Ochiq savollar.** O'zbekiston uchun Yandex sertifikatlagan POS'lar ro'yxati va ulanish chegaralari yo'q. Qirg'iziston sahifasidagi "reyting ≥4,6, ≥8 filial" O'zbekistonda tekshirilmagan. Push API'ning production manzili, holat so'rovi chastotasi va rasmiy sertifikatlash bor-yo'qligi ham noma'lum. Bularni Yandex menejeri bilan aniqlash kerak.

**Mehnat:** backend **3–4 hafta**, Flutter kassa va oshxona ekrani **1–2 hafta**. Yandex protokoli to'plami (DTO va testlar) Alipos klienti bilan umumiy [XULOSA].

### Uzum Tezkor: ehtimol o'sha protokol, lekin yozma tasdiq kerak

Uzum ochiq API hujjatini e'lon qilmaydi. Hamkorlar sahifasida faqat iiko, R-Keeper va JOWi integratsiyalari, Telegram bot orqali ro'yxatdan o'tish va **Didox'dagi moliyaviy hujjatlar** ko'rsatilgan ([promo.tezkor.com](https://promo.tezkor.com/partners/uz)) [YB]. JOWi esa amalda bozordan chiqqan. REGOS'ning Uzum sahifasi Yandex sahifasining deyarli nusxasi. Restoran Uzum bilan shartnomani o'zi tuzadi va Client ID, Secret, EndPoint va ombor kodini Uzum menejeriga beradi ([docs.regos.uz](https://docs.regos.uz/ru/regos-sm/operations/settings-and-security/integrations/uzum-tezkor)) [YB]. Bundan kelib chiqadigan taxmin: Uzum ham POS'ni server qilib, Yandex'ga o'xshash so'rov yuboradi [XULOSA]. Agar shunday bo'lsa, Uzum konnektori Yandex serverining sozlamasi bo'ladi, **~1 hafta**. Bo'lmasa, alohida konnektor, **3–4 hafta**.

Hisob-kitob uchun API topilmadi. Komissiya va to'lovlar Didox aktlari orqali, ya'ni import yoki qo'lda solishtirish bilan tekshiriladi. Yandex'da esa buyurtmalar tarixini 180 kungacha API orqali olish mumkin. 2026-yil sentabridan Uzum restoranning o'z kanallaridagi buyurtmalar uchun faqat kuryer xizmatini ham sotadi. Bu xizmatning integratsiya usuli va narxi e'lon qilinmagan ([spot.uz, 14.09.2026](https://www.spot.uz/ru/2026/09/14/uzum-tezkor/)) [OAV].

### Xorijiy agregatorlar faqat namuna

Wolt 2026-yil 5-martda O'zbekistondan chiqdi. Glovo, Delivery Hero, Uber Eats va DoorDash bu yerda ishlamaydi. Ularning bitta foydali saboqi bor. G'arb agregatorlari buyurtmani **webhook bilan itaradi**, POS esa qabul yoki tayyor deb qayta chaqiradi. Yandex esa aksincha, **o'zi so'raydi**. Shuning uchun umumiy adapter ikkala transportni bitta ichki holat mashinasi orqasida yashirishi kerak ([Wolt webhook](https://developer.wolt.com/docs/webhook); [Glovo Partners API](https://api-docs.glovoapp.com/partners/index.html)) [XORIJIY]. Bu kelajakda boshqa agregator kirsa, arxitekturani qayta yozmaslik uchun kerak.

## 5. Fiskal chek: bitta pulga bitta chek

### Qonun kimni javobgar qiladi

O'zbekiston qonunchiligi uch turdagi nazorat-kassa texnikasini taniydi ([lex.uz, VM 943](https://lex.uz/uz/docs/-4603329)) [TASDIQLANGAN]:

1. onlayn-NKM;
2. virtual kassa;
3. davlat reyestridagi **"Marketpleys"**. Bu o'z virtual fiskal moduli bilan chekni OFD orqali ro'yxatdan o'tkazadigan to'lov tashkiloti yoki elektron savdo platformasi.

Agregator ilovasi to'liq fiskal chek chiqarishi uchun u Marketpleys yoki virtual kassa sifatida reyestrda bo'lishi kerak. Chekda restoran STIRi **"Komitent (vositachi) STIRi"** maydonida turadi. Yetkazib berilgan buyurtmada yetkazgan tadbirkorning yuridik manzili yoziladi (1-ilova 7-band, VM 610 va VM 23 tahriri) [TASDIQLANGAN].

Javobgarlik baribir sotuvchida: "харид чекини тўлиқ миқдорда шакллантирилиши ... учун масъулият тадбиркорлик субъектлари зиммасига юклатилади" (1-ilova 4-band) [TASDIQLANGAN]. Joyda elektron to'lov bilan qilingan xaridga, jumladan **bank yagona QR-kodi** bilan to'lovga, joyning o'z kassasidan chek berish majburiy. Bu VM 393 tahririda 21.07.2026 dan kuchda (1a-ilova 2-band) [TASDIQLANGAN].

Masofadan onlayn to'langan buyurtmada chekni kim chiqarishi qonunda yozilmagan, shartnoma hal qiladi. Ovqat agregatorlari uchun Soliq qo'mitasining tushuntirishi topilmadi. Yagona rasmiy namuna — taksi. U yerda agregator **komissioner**, taksi parklari **komitent**. Chek agregator ilovasida chiqadi, park o'z aylanmasini "komissionerning kunlik hisoboti"da topadi ([buxgalter.uz, 23.02.2024](https://buxgalter.uz/publish/doc/text197075_agregatory_taksi_i_subekty_predprinimatelstva_okazyvayushchie_uslugi_taksi_nalajivayut_vydachu_chekov_onlayn-kkt_svoim_klientam)) [OAV].

VM 393 taqiqi integratsiya qilinmagan "buyurtma hisobi dasturi"ni taqiqlaydi. Demak, agregator buyurtmasi iiko, R-Keeper yoki Rizqim POS'iga tushgan zahoti, o'sha POS soliq tizimi bilan integratsiya qilingan bo'lishi kerak, chekni kim chiqarishidan qat'i nazar [XULOSA; YURIST].

### Yandex va Rizqim bugun qanday ishlaydi

**Yandex Eats O'zbekiston** foydalanuvchi shartlari (21.08.2026) agent modelini ko'rsatadi ([yandex.uz/legal](https://yandex.uz/legal/termsofuse_eda/ru/)) [YB]:

- Sotuvchi — restoran (1.1-band). Yandex foydalanuvchi va restoran o'rtasidagi shartnomada tomon emas (2.9).
- Karta to'lovini Yandex "restoran va/yoki kuryer topshirig'i bilan" vakolatli to'lov operatori orqali oladi (5.1.1, 5.2).
- Cheklar "qonunda nazarda tutilgan hollarda" ilovadagi "Buyurtmalar tarixi"da beriladi (5.3).
- Restoran o'z kuryeri bilan yetkazganda unga naqd to'langan pul bu shartnomadan tashqarida (5.1.3).

Chekni aynan kim chiqarishi yozilmagan. Eng ehtimolli amaliyot: oldindan to'langan buyurtmaga chekni Yandex yoki uning to'lov operatori restoran komitentligida chiqaradi. Restoran o'z kuryeri bilan naqd olganda chekni o'zi chiqaradi [XULOSA; YURIST].

**Rizqim'da** Mini App buyurtmasi Click yoki do'konning o'z Payme kassasi orqali to'langanda chekni to'lov tizimi chiqaradi, restoran STIRi komitent bo'ladi. Natija `external` chek sifatida yoziladi (P3-14, docs/API.md). Bitta istisno bor. **Platformaning umumiy Payme kassasi** orqali o'tgan to'lovda Merchant API'da komitent maydoni yo'q. Shuning uchun bunday to'lov uchun restoran nomidan chek chiqmaydi, to'lov `payme_platform_cashbox` deb belgilanadi (docs/API.md). Bu 1-yo'nalish uchun muhim. Joyning iiko'siga buyurtma yuborilganda "chek tashqarida chiqarilgan" belgisi taxmin bilan emas, **to'lovning haqiqiy yo'nalishi** bo'yicha qo'yiladi [XULOSA].

### Bu avvalgi hisobotni qayerda tuzatadi

"Alipos va agregator POS modeli" hisoboti "buyurtma hamma tarifda POS'ga tushgani uchun POS bu chekni avtomatik chiqara oladi" degan edi. iiko eslatmasi ham "sof marketpleysda chekni joyning iikoFront'i chiqarishi ehtimol standart" deb taklif qilgan. Endi aniqroq qoida bor [XULOSA]:

- **Onlayn oldindan to'langan** buyurtmada chek to'lov tizimidan keladi. Joy POS'i ikkinchi sotuv chekini chiqarmaydi, faqat tashqi chekning fiskal belgisi va terminal raqamini saqlaydi.
- **Joyda yoki eshik oldida to'langan** buyurtmada chekni joy POS'i to'lov paytida chiqaradi.

Takroriy chek joyning QQS va aylanma solig'i ma'lumotida aylanmani ikki marta ko'rsatadi. Shuning uchun Rizqim POS'iga ham, Rizqim yuboradigan har bir POS buyurtmasiga ham **"kim to'lov oldi va chek chiqardi: joy / to'lov tizimi / agregator"** belgisi kerak. Belgi "tashqi" bo'lsa, ikkinchi sotuv cheki bloklanadi.

Ikki xavf bor:

- **Yetkazib berishdagi naqd to'lov.** 2024-yilda kassirlar chekni kuryer pulni olishidan oldin, jo'natishda chiqargani uchun yetkazib berish korxonalariga 227¹-modda bo'yicha ishlar ochilgan. Soliq qo'mitasi raisi "fiskalizatsiyagacha pauza" funksiyasini va'da qilgan, lekin hujjati topilmadi ([spot.uz, 14.02.2024](https://www.spot.uz/ru/2024/02/14/delivery-receipt/)) [OAV].
- **Bo'nak (avans) cheki.** Oldindan to'langan va keyin yetkaziladigan ovqat uchun avans cheki kerakmi yoki yo'qmi, 1a-ilovada yozilmagan. Amalda to'lov tizimlari to'lov paytida bitta sotuv cheki chiqaradi [YURIST].

**Markirovka.** 28.08.2026 dan umumiy ovqatlanish sotib olgan suv, alkogolsiz ichimlik, pivo va tamakining markirovka kodi sotib olinganda "yakuniy iste'molga sotilgan" hisoblanadi ([kun.uz, 29.08.2026](https://kun.uz/news/2026/08/29/markirovkalash-qoidalarini-buzganlik-uchun-javobgarlik-yengillashtiriladi)) [OAV]. Demak, restoran yoki agregator chekida bu ichimliklar uchun kod talab qilinmasligi kerak. Farmon matni o'qilmagan [YURIST].

### Rizqim o'zi Marketpleys bo'lishi mumkin

1a-ilova Rizqim'ga o'zi Marketpleys sifatida reyestrga kirish yo'lini ochadi. Talablar ([lex.uz, VM 943, 1a-ilova](https://lex.uz/uz/docs/-4603329)) [TASDIQLANGAN]:

- virtual fiskal modul va OFD bilan integratsiya;
- cheklarni shakllantirish va saqlash tizimi, kol-markaz;
- ma'lumotni kamida **5 yil** saqlash;
- har bir restoran bilan shartnoma va uni operator tizimida ro'yxatga olish;
- Soliq qo'mitasi qarori 15 ish kunida.

Bu Payme platforma kassasi muammosini ham, Rizqim kuryeri naqd olgan holatni ham hal qilishi mumkin. Lekin u avvalgi hisobotdagi "A yo'li" (o'z virtual kassasi) bilan bir qatorda alohida loyiha [XULOSA].

## 6. Shaxsiy ma'lumot: lokalizatsiya yumshadi, rozilik qoldi

### 4-bosqich rejasidagi xato

docs/PHASE4_PLAN.md 7-bo'limida "O'zbekiston fuqarolarining shaxsiy ma'lumotlari qonunga ko'ra O'zbekistondagi serverda saqlanishi kerak" deyilgan. 12-bo'limida esa "shaxsiy ma'lumotlar uchun O'zbekistondagi server" so'ralgan. Bu 2026-yil martigacha to'g'ri edi.

ORQ-1125 (26.03.2026) Shaxsga doir ma'lumotlar qonunining 27¹-moddasini qayta yozdi. **27.03.2026 dan** O'zbekistonda saqlanishi shart bo'lgan ma'lumotlar faqat bular ([lex.uz, ZRU-547](https://lex.uz/uz/docs/-4396419); [ORQ-1125](https://lex.uz/docs/8104580)) [TASDIQLANGAN]:

- jismoniy shaxslarning biometrik ma'lumotlari;
- genetik ma'lumotlari;
- telekom operatorlari abonentlarining ma'lumotlari.

Ism, telefon, manzil va buyurtmalar tarixi chet elda saqlanishi mumkin, agar quyidagilardan biri bajarilsa:

- davlat teng himoya beradi deb tan olingan;
- standart shartnoma shartlari yoki majburiy korporativ qoidalar qabul qilingan;
- ro'yxatdagi xalqaro standartlarga rioya qilingan.

Bu ro'yxatlar hali qabul qilinmagan, shuning uchun amaliyotchilar chet elda saqlashni "kulrang zona" deydi ([itts.uz](https://itts.uz/news/pdn_local_review1/)) [OAV]. Ma'lumotlar bazasini davlat reyestriga qo'yish majburiyati (20-modda) endi faqat lokalizatsiya qilinishi shart bo'lgan bazalarga qo'llanadi. Lekin 31-moddada umumiy "bazani ro'yxatdan o'tkazish" majburiyati hali qolgan. Bu ziddiyat [TASDIQLANGAN; YURIST].

**Tavsiya etilgan tuzatish** (PHASE4_PLAN 7 va 12-bo'limlari) [XULOSA]:

> **Ma'lumot joylashuvi.** 27.03.2026 dan oddiy shaxsiy ma'lumot (ism, telefon, manzil, buyurtmalar) uchun O'zbekistonda saqlashning umumiy talabi yo'q, u faqat biometrik, genetik va telekom ma'lumotiga qo'llanadi. Shunga qaramay production O'zbekistonda saqlanadi. Sabablari: (1) chet elga uzatishning qonuniy asoslari hali ishlamaydi, 15-moddaga ko'ra rozilik kerak; (2) Rizqim Marketpleys bo'lsa yoki soliq API'sidan foydalansa, server va IP O'zbekistonda bo'lishi va baza ro'yxatdan o'tgan bo'lishi shart (VM 943, 1a-ilova, 12³-band); (3) bazani ixtiyoriy ro'yxatdan o'tkazish arzon va 31-moddadagi ziddiyatni yopadi.

Soliq API'si sharti alohida qayd etiladi. Elektron savdo platformasi soliq tizimi ma'lumotiga API orqali kirishi uchun shaxsga doir ma'lumotlar bazasini ro'yxatdan o'tkazgani haqida guvohnoma va **O'zbekistonda joylashgan IP manzil va server** bo'lishi shart ([lex.uz, VM 943, 12³-band](https://lex.uz/uz/docs/-4603329)) [TASDIQLANGAN]. Bu umumiy qonundan qat'iyroq.

### Ma'lumotni joyga, uning POS vendoriga va agregatorga uzatish

Ma'lumotni uchinchi shaxsga qayta ishlashga berish uchun asos kerak (31-modda 3-qism) ([lex.uz, ZRU-547](https://lex.uz/uz/docs/-4396419)) [TASDIQLANGAN]:

- subyektning yozma yoki elektron rozilik;
- yoki egasi va subyekt o'rtasidagi shartnomani bajarish;
- yoki qonunda nazarda tutilgan holat.

Subyektga uzatish haqida **yozma xabar berish** ham kerak. Ma'lumot faqat yig'ilgan maqsad uchun ishlatiladi (12-modda). Yandex o'z shartlarida foydalanuvchidan ma'lumotni restoran va kuryerga, jumladan **chet elga**, uzatishga rozilik oladi va uni kamida 3 yil saqlaydi ([yandex.uz/legal](https://yandex.uz/legal/termsofuse_eda/ru/)) [YB].

Rizqim uchun amaliy qoidalar [XULOSA]:

1. **Mijoz ofertasi.** Unga buyurtmani bajarish uchun ma'lumotni joyga, joyning POS vendoriga va kuryerga, jumladan chet eldagi serverlarga, uzatishga aniq elektron rozilik qo'shiladi. Rozilik jurnalga yoziladi. iiko va R-Keeper bulutlari chet elda bo'lishi ehtimoli bor, bu tekshirilmagan.
2. **Minimal ma'lumot.** POS'ga faqat buyurtmaga kerakli narsa yuboriladi. Lekin iiko va R-Keeper API'larida telefon majburiy, iiko'da u "+" bilan boshlanishi kerak. Shuning uchun telefonni yashirishning imkoni cheklangan. iiko'da `one-time` mijoz turi ishlatiladi, shunda mijoz joyning sodiqlik bazasiga tushmaydi. Poster esa mijozni telefon bo'yicha o'z bazasida yaratadi, bu ofertada ochiq yoziladi.
3. **Agregator ma'lumoti.** Rizqim POS'iga Yandex yoki Uzum orqali kelgan mijoz ma'lumoti faqat buyurtmani bajarish uchun ishlatiladi. U Rizqim marketpleysi reklamasiga ishlatilmaydi. Bu 12-moddadagi maqsad cheklovini va, ehtimol, agregatorning API shartlarini buzadi.
4. **Hamkor API shartnomasi.** Vendor bilan qayta ishlash shartnomasi tuziladi: maqsad, saqlash muddati, chet elga uzatish, xavfsizlik, buzilish haqida xabar.

ORQ-1173 (E-tijorat qonuni, kuchga kirishi ~12.12.2026) buyurtma agregatori operatoriga ham foydalanuvchi ma'lumoti maxfiyligini ta'minlash majburiyatini yuklaydi ([lex.uz, ORQ-1173](https://lex.uz/ru/docs/8468724)) [TASDIQLANGAN].

## 7. Raqobat qoidalari Yandex'ni ham, Rizqim'ni ham bog'laydi

Yandex Eats, Express24 va Uzum Tezkor ustun mavqedagi raqamli platformalar reyestrida turadi ([gazeta.uz, 02.04.2025](https://www.gazeta.uz/ru/2025/04/02/uzum-tezkor/)) [OAV]. Raqobat qonunining (ZRU-850) 18-moddasi ustun mavqedagi subyektga quyidagilarni taqiqlaydi ([lex.uz, ZRU-850](https://lex.uz/uz/docs/6518383)) [TASDIQLANGAN]:

- shartnoma predmetiga aloqasiz yoki kamsituvchi shartlar qo'yish;
- shartnomani kontragent **boshqalar bilan ishlamasligi sharti** bilan tuzish (eksklyuzivlik);
- asossiz shartnoma tuzishdan bosh tortish;
- bozorga kirishga to'siq yaratish.

2-qism alohida: ustun mavqedagi raqamli platforma operatori **"ma'lum ma'lumot, texnologiya va raqamli mahsulotlardan foydalanish talabini qo'yish"** orqali raqobatni cheklashi taqiqlanadi.

Agar Yandex yoki Uzum iiko va R-Keeper joylarini ulab, Rizqim POS'idagi joyni ulashdan bosh tortsa, yoki Rizqim marketpleysidagi joylardan eksklyuzivlik talab qilsa, bu 18-modda bo'yicha e'tiroz uchun asos bo'ladi. Shikoyat Raqobat qo'mitasiga beriladi. Bu kafolatlangan himoya emas, muzokaradagi dalil [XULOSA; YURIST].

Qoidalar Rizqim'ga ham tegishli. ORQ-1173 "buyurtmalar agregatori" maqomini yaratdi ([lex.uz, ORQ-1173](https://lex.uz/ru/docs/8468724); [spot.uz, 14.09.2026](https://www.spot.uz/ru/2026/09/14/marketplace-accounts)) [TASDIQLANGAN]. Uning operatori:

- taklif va tartiblash **algoritmlarini shaffof** saqlaydi;
- ofertani e'lon qiladi;
- qoidalar o'zgarishini **kamida 30 kun** oldin bildiradi;
- sotuvchilar bilan hisob-kitobni **alohida bank hisoblari** orqali yuritadi.

Sotuvchi xaridorga birlamchi javobgar, operator subsidiar javobgar. Qonun ~12.12.2026 dan kuchda. Rizqim "elektron savdo platformasi" (13-modda) yoki "buyurtmalar agregatori" (13¹-modda) bo'ladimi, aniq emas. Majburiyatlar deyarli bir xil [YURIST].

Avvalgi hisobotda Rizqim komissiya o'zgarishini 15 kun oldin e'lon qilishni ixtiyoriy qabul qilgan edi. Endi u **30 kun** bo'lishi kerak. Rizqim katta bo'lsa, 18-modda uning hamkor API shartlariga ham qo'llanadi. Masalan, marketpleysga kirishni Rizqim POS'iga bog'lab bo'lmaydi. Bu egasining "POS marketpleys sharti emas" degan qaroriga mos [XULOSA].

## 8. Eslatmalar orasidagi ziddiyatlar

| Mavzu | Bir manba | Boshqa manba | Qaror |
|---|---|---|---|
| **Ma'lumot lokalizatsiyasi** | PHASE4_PLAN: shaxsiy ma'lumot qonunga ko'ra O'zbekistonda | ZRU-547, ORQ-1125 tahriri (27.03.2026): faqat biometrik, genetik, telekom | Qonun matni ustun. Reja matni 6-bo'limdagidek tuzatiladi, server baribir O'zbekistonda |
| **Marketpleys buyurtmasining cheki** | Avvalgi hisobot: POS chekni avtomatik chiqaradi; iiko eslatmasi: standart holatda iikoFront chiqaradi | Rizqim kodi (P3-14): Click va o'z Payme kassasi cheki komitent bilan; fiskal eslatma: ikkinchi chek aylanmani ikkilantiradi | To'lov yo'nalishi hal qiladi: onlayn — tashqi chek, POS bloklanadi; joyda — POS chiqaradi |
| **iiko eski kirish usuli qachon o'chdi** | Diler maqolasi: 1-sentabr | iiko.ru: 29.08.2026 | Rasmiy sana ustun: 29.08.2026 |
| **Uzum'ning POS integratsiyalari** | Hamkorlar sahifasi: iiko, R-Keeper, JOWi | Avvalgi hisobot: JOWi amalda bozordan chiqqan | JOWi rejadan tashqarida |
| **Uzum protokoli** | Hech qanday rasmiy hujjat yo'q | REGOS: Yandex bilan bir xil sozlama | Taxmin sifatida qoladi, Uzum'dan yozma spetsifikatsiya so'raladi |
| **Komissiya o'zgarishi haqida xabar** | Avvalgi hisobot: ixtiyoriy 15 kun | ORQ-1173: agregator operatori uchun 30 kun | 30 kun, ~12.12.2026 dan majburiy |
| **R-Keeper yo'li** | r_k Delivery API ochiq hujjatlangan | O'zbekistonda BUSOFT o'z hub'i bilan ulaydi, r_k Delivery ishlatilishi tasdiqlanmagan | Avval BUSOFT bilan muzokara, API zaxira |

## 9. Arxitektura: bitta integratsiya qatlami

Hublar (Deliverect, Otter, HubRise) har bir kanalni bitta umumiy modelga va undan qaytaradigan adapter sifatida quradi. Shunda har bir tashqi tizimga bitta xarita kerak bo'ladi, admin vositalari esa umumiy ([HubRise Callbacks](https://www.hubrise.com/developers/api/callbacks); [Deliverect order statuses](https://help.deliverect.com/en/articles/7979014-order-statuses)) [XORIJIY]. Rizqim ham shunday qiladi. Bu bo'limdagi hamma narsa **[XULOSA]**.

### Qatlam tuzilishi

```
                    ┌──────────────────────────────────────────────┐
  Yandex Eats ──►   │  KIRUVCHI SERVER (Yandex protokoli)           │
  Uzum Tezkor ──►   │  /security/oauth/token, /menu/*, /order*     │──┐
  (3-yo'nalish)     └──────────────────────────────────────────────┘  │
                    ┌──────────────────────────────────────────────┐  │   ┌──────────────────────┐
  POS vendorlari ─► │  RIZQIM PARTNER API v1 (OAuth, webhook,      │──┼──►│  UMUMIY MODEL        │
  (2-yo'nalish)     │  polling, sandbox)                           │  │   │  katalog, inventory, │
                    └──────────────────────────────────────────────┘  │   │  buyurtma, to'lov,   │
                    ┌──────────────────────────────────────────────┐  │   │  holat mashinasi,    │
  iiko, Alipos,  ◄──│  CHIQUVCHI KLIENTLAR (adapterlar):            │◄─┘   │  fiskal belgisi      │
  R-Keeper,         │  iikoCloud, Yandex-klient (Alipos, Mison,     │      └──────────┬───────────┘
  Clopos, Poster    │  REGOS), r_k Delivery, Clopos, Poster         │                 │
  (1-yo'nalish)     └──────────────────────────────────────────────┘                 │
                    ┌──────────────────────────────────────────────────────────────▼──────┐
                    │  UMUMIY XIZMATLAR: ulanishlar, kalitlar (shifrlangan), xarita (ref), │
                    │  hodisalar jurnali, idempotentlik, navbat va qayta urinish, qabul    │
                    │  muddati nazorati, sog'liq (oxirgi so'rov / webhook), solishtirish   │
                    └──────────────────────────────────────────────────────────────────────┘
```

### Umumiy model

**Katalog** HubRise andozasida quriladi: kategoriya daraxti, mahsulot va uning variantlari, `min/max` bilan modifikator guruhlari, kombolar, turlangan to'lovlar (yetkazib berish, xizmat haqi). Kanal narxi va ko'rinishi mavjud `channel_prices` kengaytmasi bo'ladi, kanallarga `rizqim_marketplace`, `yandex_eats`, `uzum_tezkor` qo'shiladi. Har bir obyektda barqaror `ref` (Rizqim UUID) bor. Bu tashqi tizimdagi `externalId` va PLU uchun asos.

**Stop-list** alohida, tez o'zgaradigan `inventory` resursi bo'ladi, menyu bilan birga emas. Sabab: mavjudlik menyudan ko'p marta tez-tez o'zgaradi. Deliveroo'da menyu o'zgarishi ~5 daqiqada ko'rinadi ([Deliverect, Deliveroo](https://help.deliverect.com/en/articles/7979326-deliveroo-integration-overview)) [XORIJIY].

**Buyurtma** qatorlari katalogdan nusxa sifatida saqlanadi (nom, narx, modifikatorlar va `ref`). Chegirma, to'lovlar (usul, kim to'lagan) va komissiya alohida massivlarda turadi. To'lov va jami summa mos kelmasa, `payment_discrepancy` maydonida yoziladi.

**Fiskal belgi:** `fiscalized_by` = `venue_pos` | `payment_provider` | `aggregator` | `rizqim_marketplace`. Unga tashqi chek havolasi (fiskal belgi, terminal raqami, QR) qo'shiladi. Har bir adapter belgini o'z POS'ining tiliga o'giradi. Masalan, iiko'da `isFiscalizedExternally` va fiskal bo'lmagan to'lov turi.

**Holat mashinasi** — hozirgi `OrderStatus`. Tashqi holatlar unga quyidagicha o'giriladi:

| Rizqim | Yandex / Alipos | iiko | Clopos | Poster |
|---|---|---|---|---|
| `new` | `NEW` | `Unconfirmed` | `PENDING_ACCEPTANCE` | 0 (yangi) |
| `accepted` | `ACCEPTED_BY_RESTAURANT` | `WaitCooking` | `ACCEPTED` / `AUTO_ACCEPTED` | 1 (qabul qilindi) |
| `preparing` | `COOKING` | `CookingStarted` | `IN_PRODUCTION` | — |
| `ready` | `READY` | `CookingCompleted`, `Waiting` | `READY`, `READY_FOR_PICKUP` | — |
| `on_the_way` / `delivery` | `TAKEN_BY_COURIER` | `OnWay` (faqat joy kuryeri) | `PICKED_UP`, `DELIVERING` | — |
| `delivered` | `DELIVERED` | `Delivered`, `Closed` | `COMPLETED` | — |
| `canceled` / `rejected` | `CANCELLED` | `Cancelled` (`cancelInfo`) | `CANCELLED`, `REJECTED`, `FAILED` | 7 (bekor) |

Manbalar: [Yandex OpenAPI](https://yandex.ru/dev/eda-vendor/doc/ru/ref/external-api.openapi.json); [iikoCloud API](https://api-ru.iiko.services/docs); [Clopos orders](https://developer.clopos.com/docs/api-reference/open-api/orders/create-order.md); [Poster](https://dev.joinposter.com/en/docs/v3/web/incomingOrders/createIncomingOrder). R-Keeper holatlari ro'yxati hujjatda yo'q, faqat admin-panelda.

Rizqim kuryeri yetkazsa, "tayyor"dan keyingi holatlar Rizqim'niki bo'ladi. iiko'ga buyurtma `DeliveryByClient` (Rizqim kuryeri olib ketadi) yoki joy sozlagan buyurtma turi bilan yuboriladi.

### Ishonchlilik qoidalari

| Qoida | Nega |
|---|---|
| Har bir kiruvchi va chiquvchi so'rovning xom tanasi, javobi, imzo natijasi va urinishlari `integration_events` jurnalida | Xatoni ko'rish va "qayta yuborish" tugmasi |
| Idempotentlik: Yandex'da `eatsId`, iiko'da Rizqim yaratgan `order.id`, Clopos'da `Idempotency-Key`, Partner API'da `Idempotency-Key` va `event_id` | Qayta urinish ikkinchi buyurtma ochmasligi |
| Webhook darhol 2xx bilan qabul qilinadi, ishlov navbatda (Redis) | Uber, DoorDash va HubRise talabi |
| Qayta urinish eksponensial: 1 daqiqadan 32 daqiqagacha, so'ng "o'lik xatlar" ro'yxati | HubRise jadvali |
| Webhook kafolatlanmagan joyda polling bilan solishtirish: iiko `by_revision` (≤3 soat oyna), Clopos har N soniyada | iiko webhooki kafolatlanmagan, Clopos'da webhook yo'q |
| Tokenlar keshda: iiko ~55 daqiqa, r_k Delivery 3600 soniya, 401 da qayta olinadi | iiko va R-Keeper'da refresh yo'q |
| Qabul muddati nazorati: joy N daqiqada qabul qilmasa, Telegram ogohlantirish, keyin bekor va pul qaytarish | Mijoz kutib qolmasligi |
| Kassa onlaynligi savatda tekshiriladi (iiko `is_alive`), oflayn joy Mini App'da "hozir qabul qilmayapti" bo'ladi | iiko 8 soniyada buyurtmani bekor qiladi |
| Yandex serveri ochiq domenda (masalan `integrations.<domen>`), filial bo'yicha client_id/secret, kalitlar Laravel shifrlangan maydonida | Yandex IP manzilni qabul qilmaydi |

### Boshqaruv markazida "Integratsiyalar" bo'limi

PHASE4_PLAN Yandex va Uzum buyurtmalarini kassaga ulashni bu bosqichdan chiqargan (11-bo'lim). Bu to'g'ri, konnektorlar keyingi bosqichda quriladi. Lekin ikki narsani hozir qo'shish arzon.

Birinchisi, bo'limlar ro'yxatiga **4.10 "Integratsiyalar"** joyini qo'shish:

- **Ulanishlar:** ovqatxona yoki filial bo'yicha tashqi tizim, yo'nalish, holat va sog'liq (oxirgi Yandex so'rovi, oxirgi iiko webhooki, xatolar soni).
- **Hodisalar jurnali:** so'rov va javob tanalari, imzo, urinishlar, "qayta yuborish" tugmasi.
- **Xarita:** POS `ref` ↔ Rizqim `ref`, "bog'lanmagan taomlar" hisoboti, buyurtmada uchragan, lekin katalogda yo'q ref ogohlantirishi.
- **Qotib qolgan buyurtmalar:** yuborilgan, lekin qabul qilinmagan, taymer bilan. Bular "Bugun → Diqqat talab qiladi" ro'yxatiga ham tushadi.
- **Hamkor ilovalar:** Partner API klientlari, sandbox yoki production, sertifikat holati.
- **Agregator solishtirishi:** Yandex buyurtmalar tarixi va Uzum Didox aktlari importi, komissiya farqi.

Ikkinchisi, ma'lumot modelini hozirdan loyihalash: `integration_connections`, `integration_events`, `external_refs` jadvallari va `fiscalized_by` maydoni. Shunda 2-to'lqindagi "Ovqatxonalar → ulash jarayoni" (P4-22) va "Monitoring → ogohlantirish qoidalari" (P4-15) keyin qayta yozilmaydi.

Rollar mavjud jadvalga mos keladi:

- Operator sog'liq va qotib qolgan buyurtmalarni ko'radi.
- Savdo/ulash xodimi ulanish va xaritani sozlaydi.
- Superadmin hamkor ilovalar va kalitlarni boshqaradi.
- Kalitlar hech qachon to'liq ko'rsatilmaydi, rejaning 4.9-bo'limidagi qoida bo'yicha.

Hamkor panelida ovqatxona egasi o'zi ulaydi: iiko API kalitini kiritadi, menyuni bog'laydi, Yandex uchun Client ID yaratadi, ulanish sog'lig'ini ko'radi.

## 10. Ustuvorlik va yo'l xaritasi

Tartib uchta dalilga tayanadi [XULOSA]:

1. **Marketpleys asosiy biznes.** 1-yo'nalish joylarni marketpleysga olib keladi. Avvalgi hisobotga ko'ra choyxona va osh markazlarining ko'pi hech bir agregatorda yo'q. Demak, Rizqim POS'ining asosiy segmenti uchun 3-yo'nalish hozircha Pro tarif funksiyasi, birinchi ehtiyoj emas.
2. **Yandex protokoli to'plami ikki marta ishlaydi.** Alipos klienti va Yandex serveri bir xil DTO va testlardan foydalanadi.
3. **Tijoriy to'siqlar texnikadan sekinroq.** Muzokaralar (Alipos, Uzum, BUSOFT) kod bilan parallel, darhol boshlanadi.

| # | Konnektor | Yo'nalish | Mehnat (1 backend dasturchi) | Hamkordan kerak bo'lgan narsa | Nega shu o'rinda |
|---|---|---|---|---|---|
| 0 | **Muzokaralar va yuridik savollar** | Hammasi | Kodsiz, 2–4 hafta parallel | 12-bo'limdagi ro'yxat | Uzum spetsifikatsiyasi va Alipos ruxsatisiz keyingi qadamlar taxminda qoladi |
| 1 | **Poydevor:** umumiy model, ulanishlar, hodisalar jurnali, idempotentlik, navbat, sog'liq, admin va hamkor panelida minimal ekranlar | — | 3–4 hafta | — | Har bir konnektor shunga quriladi |
| 2 | **Yandex protokoli to'plami** (OpenAPI'dan DTO, validatorlar, Postman testlari) | 1 va 3 | 1 hafta | — | Ikki yo'nalish uchun umumiy |
| 3 | **Alipos** (Yandex-klient) | 1 | 1–2 hafta | Alipos: platforma nomi, client_id/secret, test restoran, webhook manzillari, narx (10 $/oy va 100 $ kim to'laydi) | Eng katta mahalliy baza, choyxonaga yaqin, texnik jihatdan eng arzon |
| 4 | **iiko** (iikoCloud) | 1 | 3–4 hafta | iiko dasturchi portalida Rizqim ilovasi (`appId`, `clientSecret`); joydan API kaliti va ruxsatlar; Pro yoki API litsenziyasi; "Rizqim onlayn" to'lov turi; 1–2 diler va ulardan test tashkiloti | Brendli eng katta baza, shahar kafelari va tarmoqlar |
| 5 | **Yandex Eats serveri** Rizqim POS'ida | 3 | Backend 3–4 hafta, Flutter 1–2 hafta | Yandex menejeri: O'zbekistonda ulanish tartibi, test buyurtma, push API manzili, chek kimdan (yozma); Yandex shartnomasi bor pilot restoran | Bozorning katta qismi Yandex'da; Pro tarifining asosiy dalili |
| 6 | **Uzum Tezkor** | 3 | Yandex'ga mos bo'lsa ~1 hafta, aks holda 3–4 hafta | Uzum integratsiya menejeri: spetsifikatsiya, test muhit, integratorlar ro'yxatiga kirish, Didox hisobot shakli | Ikkinchi agregator. Protokol taxmin, yozma tasdiq shart |
| 7 | **Mison, REGOS va boshqa "Yandex rejimi"** | 1 (2-yo'nalish A eshigi) | Har biriga 0,5–1 hafta | Vendor: Yandex serverida "Rizqim" manbasi, kalitlar | Vendor uchun deyarli kodsiz |
| 8 | **RIZQIM Partner API v1** + sandbox + hujjat + sertifikatlash | 2 | 4–6 hafta | 1–2 do'st vendor (Clopos, Mison yoki Alipos) birinchi foydalanuvchi | Uzoq muddatli standart; Yandex serveri bo'lmagan vendorlar uchun |
| 9 | **R-Keeper** | 1 | BUSOFT hub'i orqali — kam kod; r_k Delivery API — 2–3 hafta | BUSOFT: Rizqim'ni hub'ga agregator qilib qo'shish va narxi; yoki joyda Delivery_Api va boshqa litsenziyalar | Baza bor, lekin yo'l diler va litsenziyaga bog'liq |
| 10 | **Clopos** | 1 | 2 hafta (polling) | dev@clopos.com dasturchi hisobini tasdiqlashi; O'zbekistondagi mijozlar soni | Hujjat yaxshi, bozordagi ulush noma'lum |
| 11 | **Poster** | 1 | 2–3 hafta | Bepul dasturchi hisobi; joyda "Rizqim" buyurtma manbasi | Eng ochiq API, lekin O'zbekistonda kichik |

Birinchi ishlaydigan natija (1–4-qatorlar) **~8–11 haftada** tayyor bo'ladi. Uchala yo'nalishning asosiy qismi (1–8) bitta dasturchi ketma-ket qilsa **~18–29 hafta** oladi. Flutter ishi alohida dasturchida parallel borsa, muddat qisqaradi [XULOSA]. Muddat Alipos va Uzum javobiga ham bog'liq.

## 11. Fiskal javobgarlik jadvali

Hamma qatorlar **[XULOSA]**, qonun asoslari 5-bo'limda. "Tashqi" deganda joy POS'i sotuv chekini chiqarmasligi, faqat tashqi chek havolasini saqlashi tushuniladi.

| # | Holat | Kim chek chiqaradi | Joy POS'i (yoki Rizqim POS) nima qiladi | Ishonch darajasi |
|---|---|---|---|---|
| 1a | RIZQIM buyurtmasi, Mini App'da **Click** yoki **joyning o'z Payme kassasi** bilan oldindan to'langan, joyning iiko/Alipos/boshqa POS'iga yuborilgan | **To'lov tizimi**, restoran STIRi komitent (Rizqim'da P3-14 allaqachon ishlaydi) | `fiscalized_by = payment_provider`. iiko'da `isFiscalizedExternally: true` va fiskal bo'lmagan to'lov turi. Ikkinchi chek yo'q. Qaytarish — o'sha provayder orqali qaytarish cheki | Yuqori: soliq inspektorining 2022-yilgi tushuntirishi — to'lov tizimi chekida restoran STIRi komitent bo'lsa, restoran alohida chek bermasligi mumkin ([buxgalter.uz, 25.07.2022](https://buxgalter.uz/publish/doc/text183145_nujno_li_vydavat_chek_onlayn-kkt_esli_klienty_oplachivayut_cherez_platejnye_sistemy1)) [ESKI]. Payme/Click Marketpleys reyestrida ekani tasdiqlanmagan [YURIST] |
| 1b | RIZQIM buyurtmasi, **platformaning umumiy Payme kassasi** orqali to'langan | Hozir restoran nomidan chek **chiqmaydi** (Merchant API'da komitent maydoni yo'q) | Ikki variant: joy POS'i chiqaradi (`venue_pos`, iiko'da `isFiscalizedExternally: false`) yoki bu yo'l yopiladi | Bo'shliq. Tavsiya: onlayn to'lovni faqat chek komitent bilan chiqadigan yo'llar orqali qabul qilish [YURIST] |
| 1c | RIZQIM buyurtmasi, **joy kuryeri** yetkazdi, naqd yoki karta eshik oldida | **Joy**, o'z onlayn-NKM yoki virtual kassasidan, **to'lov paytida** | `venue_pos`. Chek jo'natishda emas, pul olinganda | Yuqori. 2024-yilgi 227¹ ishlari jo'natishda chiqqan chek xavfini ko'rsatadi |
| 1d | RIZQIM buyurtmasi, **Rizqim kuryeri** naqd oldi | Aniqlanmagan: joy (kuryer joy uchun pul oladi) yoki Rizqim Marketpleys/agent bo'lsa, Rizqim | Hal bo'lguncha Rizqim kuryeri buyurtmalarini faqat onlayn yoki eshik oldida QR bilan to'lash | Past. ORQ-1173 kuryerga uchinchi shaxs uchun pul olishga ruxsat beradi, lekin chek masalasini hal qilmaydi [YURIST] |
| 1e | RIZQIM buyurtmasi, **joyda to'langan** (olib ketish, kassada QR) | **Joy**, o'z kassasidan | `venue_pos`. Bank yagona QR bilan to'lovda ham majburiy | Yuqori (1-ilova 6-band; 1a-ilova 2-band) |
| 2 | **Partner API** orqali kelgan buyurtma | 1a–1e bilan bir xil: pul qayerda olingan bo'lsa | Payload'da `fiscalized_by` va tashqi chek havolasi majburiy | O'rta |
| 3a | **Yandex yoki Uzum** buyurtmasi, agregator ilovasida **oldindan to'langan** | **Agregator yoki uning to'lov operatori**, restoran komitent sifatida (agent modeli) | Rizqim POS: `fiscalized_by = aggregator`, ikkinchi chek yo'q. Agregator chek havolasini bersa, saqlanadi | O'rta. Yandex shartlari kim chiqarishini yozmaydi; Uzum shartlari topilmadi. Yozma tasdiq va pilot restoranning ofertasi kerak [YURIST] |
| 3b | **Yandex** buyurtmasi, **joy kuryeri**, naqd joyga | **Joy** (Rizqim POS), to'lov paytida | `venue_pos` | Yuqori (Yandex shartlari 5.1.3) |
| 3c | Yandex yoki Uzum buyurtmasi, **agregator kuryeri naqd** oldi | Noma'lum | Hal bo'lguncha "tashqi" deb belgilanmaydi, operator ko'rib chiqadi | Past, manbalarda yo'q |
| 3d | Agregator **o'z haqlari** (mijozdan olingan yetkazib berish va servis haqi) | Ehtimol agregatorning o'zi, o'z nomidan | Joy POS'iga tegishli emas. Joy chekiga kirmaydi | Past, manbalarda yo'q |
| 4 | Har qanday onlayn **oldindan to'lov, ovqat keyin** | Amalda to'lov paytida bitta sotuv cheki | Avans (bo'nak) cheki kerakligi aniqlanmaguncha amaldagi tartib | Ochiq savol [YURIST] |
| 5 | **Rizqim Marketpleys sifatida reyestrda** (kelajak varianti) | **Rizqim** o'z virtual fiskal moduli bilan, joy komitent | 1a, 1b, 1d holatlari bitta yo'lga keladi | Qonuniy mumkin. Reyestr, OFD, kol-markaz va 5 yillik saqlash kerak |

## 12. Egasining muzokara va qarorlar ro'yxati

| # | Nima hal qilinadi yoki so'raladi | Kim bilan | Nega |
|---|---|---|---|
| 1 | **Alipos:** Rizqim'ga hamkor API kaliti va "platforma" nomi; test restoran; webhook manzillari; joy Pro'da bo'lishi va 10 $/oy + 100 $ to'lovi shartmi yoki Rizqim ilk N joy uchun to'laydimi | Alipos rahbariyati | 1-yo'nalishdagi eng arzon va eng katta mahalliy baza |
| 2 | **Uzum Tezkor:** POS integratsiyasi spetsifikatsiyasi (Yandex'ga mosmi), test muhit, tasdiqlangan integratorlar ro'yxatiga kirish, oldindan to'langan buyurtma chekini kim chiqarishi (yozma), Didox hisobot shakli, yangi kuryer xizmatining API'si va narxi | Uzum integratsiya menejeri | Protokol hozircha taxmin; fiskal javobgarlik |
| 3 | **Yandex Eats:** O'zbekistonda uchinchi tomon POS'ini ulash tartibi va chegaralari, sertifikatlash bormi, push API manzili, chek kimdan va chek havolasini API orqali berish (yozma) | Yandex Eats hamkor menejeri | 3-yo'nalish; fiskal jadvalning 3a qatori |
| 4 | **Pilot restoran:** Yandex va Uzum bilan ofertasi (PDF) — chek bo'yicha band; haqiqiy chekda komitent STIRi bormi | Yandex/Uzum'da ishlaydigan 1–2 Rizqim POS mijozi | Kim chek chiqarishini eng tez tasdiqlash yo'li |
| 5 | **iiko:** Rizqim'ni iiko dasturchi portalida ro'yxatdan o'tkazish (kompaniya rekvizitlari, email); 1–2 diler bilan hamkorlik va test tashkiloti; diler uchun yo'naltirish rag'bati | ITDO, Enter-co, A-ONE yoki Soft24 | Dilerlar — iiko bazasiga darvoza |
| 6 | **R-Keeper:** Rizqim'ni BUSOFT hub'iga agregator qilib qo'shish, narxi va muddati; yoki r_k Delivery O'zbekistonda ishlatiladimi | BUSOFT (+998 78 333 91 65) | R-Keeper bazasiga eng tez yo'l |
| 7 | **Mison, Clopos, REGOS:** "Yandex rejimi"da Rizqim manbasini yoqish yoki Partner API'ning birinchi foydalanuvchisi bo'lish | Vendorlar | 2-yo'nalishni sinash |
| 8 | **Hamkor dasturi:** API vendorlar uchun bepul; vendorga rag'bat (taklif: ulagan va faollashgan joy komissiyasining ~10% i, 6 oy); sertifikatlash talabi; katalog | Egasi | Integratorlarni jalb qilish va narx |
| 9 | **Ustuvorlik:** 10-bo'limdagi tartibni tasdiqlash (1-yo'nalish avval) va PHASE4_PLAN'ga "Integratsiyalar" bo'limi (4.10) hamda ma'lumot modelini qo'shish | Egasi | Keyin qayta yozmaslik |
| 10 | **Fiskal savollar:** 11-bo'limdagi jadval; Payme/Click Marketpleys reyestridami; platforma Payme kassasidagi bo'shliq (1b); Rizqim kuryeri naqd olgandagi chek (1d); avans cheki (4) | Soliq maslahatchisi, Soliq qo'mitasiga yozma so'rov | Ikki marta hisoblash yoki chek bermaslik jarimasi (5 mln so'm, choraklik tushumning 2%) |
| 11 | **Rizqim Marketpleys maqomi** (1a-ilova): boshlash yoki yo'q, mas'ul, muddat; virtual kassa (A yo'li) bilan birga rejalashtirish | Egasi, OFD, Soliq qo'mitasi | Fiskal bo'shliqlarni bitta yo'lda yopish |
| 12 | **Shaxsiy ma'lumot:** mijoz ofertasiga joy, uning POS vendori va kuryerga (chet el ham) uzatishga rozilik; rozilik jurnali; bazani ixtiyoriy ro'yxatdan o'tkazish; vendorlar bilan qayta ishlash shartnomasi | Yurist | 31-modda va 15-modda talablari |
| 13 | **PHASE4_PLAN matnini tuzatish:** 7 va 12-bo'limlardagi "qonunga ko'ra O'zbekistonda" o'rniga 6-bo'limdagi matn; server tanlovi baribir O'zbekistonda | Egasi, dasturchilar | Noto'g'ri yuridik asos |
| 14 | **ORQ-1173 (~12.12.2026):** Rizqim "elektron savdo platformasi"mi yoki "buyurtmalar agregatori"mi; oferta e'loni; o'zgarishlarga 30 kun; algoritm shaffofligi; alohida bank hisoblari | Yurist | Qonun 1,5 oydan keyin kuchga kiradi |
| 15 | **Shartnoma tamoyillari:** eksklyuzivlik yo'q; POS marketpleys sharti emas; agregatordan kelgan mijoz ma'lumoti Rizqim reklamasiga ishlatilmaydi; vendorlarga bir xil shartlar | Egasi, yurist | Raqobat qonuni 18-moddasi va ishonch |
| 16 | **Tarif:** Yandex va Uzum ulanishi Pro tarifiga kiradimi (avvalgi qaror) yoki Alipos kabi alohida haq (10 $/oy) olinadimi | Egasi | Pro narxi va raqobat dalili |

## Xulosa

Integratsiya savoli boshida uchta alohida loyihaga o'xshardi: marketpleys uchun POS konnektorlari, vendorlar uchun API va kassa uchun agregator konnektorlari. Eslatmalar ularning ko'pi bitta protokol atrofida birlashishini ko'rsatdi. O'zbekistonda Yandex'ning hamkor spetsifikatsiyasi amalda POS va buyurtma kanallari o'rtasidagi umumiy til bo'lib qolgan. Alipos uni o'z hamkorlariga ochgan, REGOS uni Uzum uchun ham takrorlagan. Rizqim uchun bu ikki narsani anglatadi. Birinchidan, birinchi konnektor eng katta mahalliy bazaga (Alipos) bir necha haftada ulanishi mumkin, agar muzokara muvaffaqiyatli bo'lsa. Ikkinchidan, vendorlar uchun "bizga Yandex'ga ulangandek ulaning" degan taklif har qanday yangi API'dan tezroq qabul qilinadi. Bu xulosaning zaif nuqtasi ham aniq: u bitta ochiq repozitoriya va bitta vendorning sozlash sahifasiga tayanadi. Shuning uchun birinchi ish kod emas, Alipos va Uzum'dan olinadigan ikkita yozma javob.

Ikkinchi xulosa javobgarlik haqida. Integratsiya endi faqat texnik masala emas. Rizqim buyurtmani boshqa kassaga yuborgan zahoti chek uchun kim javob berishini ham belgilaydi. 12-dekabrdan esa o'zi qonuniy "agregator operatori" bo'ladi va 30 kunlik xabar, algoritm shaffofligi va alohida hisoblar majburiyatini oladi. Shu sababli integratsiya qatlamidagi eng qimmatli maydon menyu yoki holat emas, `fiscalized_by` belgisi. U har bir buyurtmada pul qayerda olingani va chekni kim chiqargani haqidagi savolga javob beradi. Rizqim'ning Marketpleys sifatida reyestrga kirishi esa bu savolni uzoq muddatda o'z qo'liga olishning yagona yo'li.
