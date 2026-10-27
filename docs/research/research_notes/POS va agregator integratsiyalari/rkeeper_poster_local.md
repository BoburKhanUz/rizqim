# R-Keeper, Poster va mahalliy POS'lar: tashqi marketpleys buyurtma yubora oladimi, menyu, narx va stop-listni o'qiy oladimi (2026-yil oktabr)

> **Sana.** “25.10.2026 da o‘qildi” — sahifa yoki hujjat shu kuni to‘g‘ridan-to‘g‘ri yuklab olinib o‘qilgan (curl yoki WebFetch). Poster va Clopos hujjatlari markdown manbasidan, Yandex Eda spetsifikatsiyasi OpenAPI JSON faylidan o‘qildi.
> **Belgilar.** [RASMIY HUJJAT] — vendorning dasturchilar uchun hujjati, endpoint va maydonlari bilan (tasdiqlangan hujjat); [YETKAZIB BERUVCHI] — vendorning marketing da’vosi (sayt, maqola); [KOD] — ochiq GitHub repozitoriyasidagi ishlaydigan koddan olingan dalil; [TASDIQLANGAN] — tadqiqotchi o‘zi tekshirgan fakt; [ANIQ EMAS] — qidiruv parchasi yoki ikkilamchi manba; [XULOSA] — tadqiqotchining xulosasi.
> **Bu nota nimani kengaytiradi.** [alipos_local_pos_prices.md](../Alipos%20va%20agregator%20POS%20modeli/alipos_local_pos_prices.md) (24.10.2026) narxlar va “qaysi POS qaysi agregator bilan ishlaydi” jadvalini berdi. Bu nota **texnik tomonni** beradi: Rizqim (uchinchi tomon marketpleys) har bir POS'ga buyurtmani qanday yuboradi, menyu/narx/stop-listni qanday o‘qiydi, kimdan ruxsat va litsenziya kerak. Narxlar bu yerda takrorlanmaydi.

---

## 1. R-Keeper (UCS): XML interfeys, r_keeper Delivery API, “White Server”, agregatorlar qanday ulanadi, litsenziya, diler (BUSOFT), test kirish

### Takeaway
R-Keeper'ga tashqi marketpleysning **ochiq, hujjatlashtirilgan yo‘li bor, lekin u bulutdagi r_keeper Delivery moduli orqali o‘tadi va pullik litsenziyalar talab qiladi**. Restoran (diler orqali) r_keeper Delivery'da “API orqali ulanish” yaratadi va Rizqim'ga `client_id` + `client_secret` beradi. Rizqim OAuth2 client_credentials bilan token oladi, `GET .../menu/view` orqali menyu, narx va `isContainInStopList` maydonini o‘qiydi, `POST .../orders` bilan buyurtma yaratadi, statuslarni webhook orqali oladi. Har bir obyektga kamida **Delivery_Api** litsenziyasi kerak, umuman esa Delivery_Base yoki Delivery_Filial va “XML-interfeys для WSA” ham kerak. Narxlar ochiq emas. O‘zbekistonda dileri **BUSOFT** Uzum Tezkor, Express24 va Yandex Eda'ni **o‘zining oraliq hub'i** orqali ulaydi. Shuning uchun Rizqim uchun eng qisqa yo‘l, ehtimol, BUSOFT bilan hamkorlik.

### Cited Findings

**r_keeper Delivery API — autentifikatsiya va kirish** [RASMIY HUJJAT]
- API bo‘limi: “Получение доступа к API”, “Действия с заказами”, “Действия с меню”, “Отмена или смена доступа к API”, “Описание ошибок”. Swagger: `https://delivery.ucs.ru/orders/swagger-ordersource/index.html` — [docs.rkeeper.ru, Возможности API](https://docs.rkeeper.ru/delivery/vozmoyonosti-api-4041552.html), 25.10.2026 da o‘qildi
- Token: `POST https://auth-delivery.ucs.ru/connect/token`, `Content-Type: application/x-www-form-urlencoded`, parametrlar `client_Id`, `client_secret`, `grant_type=client_credentials`, `scopes=orders`. Javobda JWT, **3600 soniya** yashaydi; keyingi so‘rovlarda `Authorization: Bearer <token>`. Muddati tugasa token qayta so‘raladi — [docs.rkeeper.ru, Получение доступа к API](https://docs.rkeeper.ru/delivery/poluchenie-dostupa-k-api-4043829.html), 25.10.2026
- Ulanishni kim yaratadi: “yetkazib berish tizimi foydalanuvchisi” (ya’ni restoran yoki uning diler/admini) admin-panelda **Интеграции / Подключение по API → Добавить подключение по API**. Kiritiladi: nom, kontakt email, “barcha muassasa buyurtmalariga kirish” (ixtiyoriy), **mavjud muassasalar** ro‘yxati, **menyu ko‘rinishi** (представление), onlayn to‘lov valyutalari GUID'lari. Natijada **Client ID (16 belgi)** va **Client Secret (128 belgi, qayta generatsiya qilinadi)** beriladi. Ishlashi uchun **“R-Keeper модуль Delivery_Api”** litsenziyasi kerak — [docs.rkeeper.ru, Подключение по API](https://docs.rkeeper.ru/delivery/podklyuchenie-po-api-4043842.html), 25.10.2026

**Menyu, narx, stop-list** [RASMIY HUJJAT]
- Yagona menyu metodi: `GET https://delivery.ucs.ru/orders/api/v1/menu/view?restaurantId=<GUID>`. Javob: `categories` (id, name, parentId); `products` (id, **externalId**, categoryId, name, **price**, schemeId, description, imageUrls, measure, **isContainInStopList**, kaloriya/BJU, dishTaxes); `ingredientsSchemes`/`ingredientsGroups` (minCount/maxCount) — modifikatorlar; `ingredients` (id, externalId, price). `externalId` — r_keeper 7 dagi taom identifikatori, buyurtmada ishlatilishi mumkin. Alohida stop-list yoki narx endpointi yo‘q; hujjatda `price` turi misol va jadvalda bir-biriga zid (son/satr) — [docs.rkeeper.ru, Действия с меню](https://docs.rkeeper.ru/delivery/dejstviya-s-menyu-10819549.html), 25.10.2026

**Buyurtma yaratish va statuslar** [RASMIY HUJJAT]
- `POST https://delivery.ucs.ru/orders/api/v1/orders`, JSON. Asosiy maydonlar: `restaurantId` (GUID, `/restaurants/geo/first` dan), `persons`, `dishList[]` (`id` yoki `externalId`, `name`, `price`, `measure`, `quantity`, ichida `ingredients[]` — modifikatorlar), `expeditionType` (`delivery`|`pickup`), `soonest` / `expectedAt` (vaqt zonasi bilan majburiy), `paymentTypeId` (`cash`|`card`|`online`), `paymentStatus` (`prepaid`, faqat online bilan), `changeFrom`, `tableCode`, `guest` (`phone` majburiy), `address` (`cityName`, `houseNumber`, `fullAddress` yetkazib berishda majburiy; `lat`/`lon`). Javob **201**, `{"result":{"orderId":"765"}}`. “Har porsiya alohida qator” sozlamali taomlar `quantity: 1` bilan alohida yuboriladi — [docs.rkeeper.ru, Создание заказа](https://docs.rkeeper.ru/delivery/sozdanie-zakaza-4043824.html), 25.10.2026
- Boshqa metodlar (sahifa nomlari): pullik yetkazib berish, vaznli taom, kassa chegirmasi bilan buyurtma; o‘zgartirish; bekor qilish; to‘lov va yakunlash; qidirish; ID bo‘yicha olish; **statuslarga obuna (webhook)**; API orqali oldindan to‘lov — [docs.rkeeper.ru, Действия с заказами](https://docs.rkeeper.ru/delivery/dejstviya-s-zakazami-10819423.html), 25.10.2026
- Webhook: admin-panelda (1) tashqi endpoint (URL + **tashqi API dasturchisi belgilagan token**, har so‘rov sarlavhasida yuboriladi), (2) “Коммуникации” bo‘limida API kanali bilan shablon va shart (masalan, status = “Передан на кухню”). Yetkazilmagan hodisalar navbatda **muddatsiz** saqlanadi va qayta yuboriladi. Payload namunasi faqat admin-panelda; to‘liq statuslar ro‘yxati va sarlavha nomi hujjatda yo‘q — [docs.rkeeper.ru, Подписка на статусы](https://docs.rkeeper.ru/delivery/podpiska-na-statusy-veb-huki-141984100.html), 25.10.2026

**Litsenziyalar** [RASMIY HUJJAT; narxlar yo‘q]
- **Delivery_Api** (kodlar 3279 — 1 oy, 3280 — 12 oy): sayt yoki ilovadan buyurtma qabul qiladigan **har bir obyektga**; manba va so‘rovlar soni cheklanmagan; litsenziyasiz manba yaratib bo‘lmaydi
- **XML-интерфейс для WSA ПО**: HTTP ulanishlar, r_k Delivery va boshqa tashqi integratsiyalar uchun; obyektda generatsiya qilinadi, keyin **White Server Agent (WSA)** qayta ishga tushiriladi; obyektga **aynan bitta**
- **Delivery_Base** (korporatsiya darajasi, 1 obyektga) va **Delivery_Filial** (yetkazib beradigan har obyektga); “Интерфейс к r_keeper Delivery” bepul, avtomatik qo‘shiladi
- Agregator modullari: **“Интерфейс к Яндекс.Еде”** (3508 — 1 oy, 2836 — 12 oy; Delivery_Base/Filial, XML-interfeys va r_k Delivery interfeysi talab qilinadi), **“Интерфейс подключения к WS Сбермаркет”** (3473). **Delivery Club** sahifada umuman yo‘q
- Paketlar: **Delivery Start** (soddalashtirilgan kassa, Yandex Eda integratsiyasi, menejer o‘rni, SIP, **API ulanish**, Tilda sayt), **Delivery Pro** (+ zonalar bo‘yicha avto-taqsimlash, kol-markaz, kuryer ilovasi, xaritada kuryer), **Cloud-RK7 Dark Kitchen** (hammasi). Hamma litsenziyalar **obuna**; l.ucs.ru litsenziyalash tizimida, WSA'ga ~1 soatda sinxronlanadi. Narx, diler yoki mamlakat (jumladan O‘zbekiston) ko‘rsatilmagan — [docs.rkeeper.ru, Лицензирование](https://docs.rkeeper.ru/delivery/litsenzirovanie-4043810.html), 25.10.2026

**Agregatorlar R-Keeper'ga qanday ulanadi**
- **Yandex Eda (Rossiya modeli):** 5 litsenziya (Delivery_Base, Delivery_Filial, XML-interfeys для WSA, Интерфейс к r_keeper Delivery, Интерфейс к Яндекс.Еде) + Yandex bilan shartnoma. r_k Delivery'da integratsiya sozlanadi, menyu ko‘rinishi nashr qilinadi, keyin “Яндекс.Еда Партнеры” kabinetida ariza: **client_id = `yandex`**, **client_secret = WSClient litsenziyasi** (l.ucs.ru dan), **host = `https://ws.ucs.ru/yandex/`**, obyekt kodi. Integratsiya birinchi test buyurtma kelganda yoqiladi — [docs.rkeeper.ru, Яндекс.Еда](https://docs.rkeeper.ru/delivery/yandeks-eda-87557916.html), 25.10.2026 [RASMIY HUJJAT]
- **Wolt/Bolt (Latviya, UCS SIA):** buyurtmalar kassadagi “WEB DELIVERY” tugmasida chiqadi; menejer `delivery.ucs.lv` portalida muassasani ochadi/yopadi, kassa, Wolt va Bolt bilan aloqani tekshiradi, “Sync menu” bilan menyuni yangilaydi yoki pozitsiyani cheklaydi; menyu har **10 daqiqada** avtomatik yangilanadi; buyurtmani qabul qilish uchun 4 ssenariy — [UCS Latvia, R_keeper WOLT/BOLT configuration PDF, 2023](https://www.ucs.lv/wp-content/uploads/2024/12/RK7-Wolt-Bolt-POS-part-ENG-1.pdf) [RASMIY HUJJAT; mamlakat dileri]
- **O‘zbekiston (BUSOFT):** “Biz POS tizimlari bilan ham, agregatorlar bilan ham bir vaqtda integratsiyalangan yechim qildik… **hub** POS'dan restoranlar, menyu, **stop-listlarni** oladi, menyuni agregator restoranlariga bog‘laydi va agregator serveriga yuklaydi… buyurtma darhol R_keeper'ga tushadi. Uzum Tezkor, Express 24 va Yandex Eda bilan integratsiya mavjud” (11.06.2024). Narx yo‘q — [rkeeper.uz (BUSOFT)](https://rkeeper.uz/stati/post/r_keeper-integraciya-agregatorov-dostavki-edy-uzum-tezkor-express-24-yandex-eda), 25.10.2026 da o‘qildi [YETKAZIB BERUVCHI]
- BUSOFT kontaktlari: Toshkent, Yunusobod t., Yangishahar 10, Edutsentr, 3-qavat, 324-ofis; **+998 78 333 91 65**; Du–Ju 10:00–19:00, Sha 10:00–16:00. R_keeper sotib olish yoki **bulutli obuna** (oylik) taklif qiladi (aksiya 31.12.2026 gacha) — o‘sha sahifa [YETKAZIB BERUVCHI]
- **XML interfeys (r_keeper 7 kassa serveri):** ochiq qidiruvda `CreateOrder`/`SaveOrder`/`GetRefData` kabi metodlar bo‘yicha rasmiy ochiq sahifa topilmadi; taxmin qilingan URL 404 qaytardi — [docs.rkeeper.ru](https://docs.rkeeper.ru/rk7/latest/ru/xml-interfejs-41813377.html) [TASDIQLANGAN: 404]. Uchinchi tomonlar XML so‘rovlarini RK7 serveriga IP orqali yuborishi (1C uchun COM kutubxona) tasvirlangan — [infostart.ru](https://infostart.ru/public/287372/) [ANIQ EMAS]
- Yandex Dostavka (kuryer chaqirish) uchun alohida modul, Carbis tomonidan qo‘llab-quvvatlanadi — bu agregatordan buyurtma qabul qilish emas — [yandex.ru/dev, Интеграция с r_keeper](https://yandex.ru/dev/logistics/api/integration/integration-rkeeper.html) [RASMIY HUJJAT]
- r_k Delivery 2024-yilda soniyasiga ~1 100 so‘rovni qayta ishlagan — [Oborot.ru](https://oborot.ru/tag/r_keeper/) [OAV; qidiruv parchasi]

### Inferences
- **“White Server” = UCS'ning bulutli litsenziya va relay infratuzilmasi**: obyektdagi White Server Agent (WSA) XML-interfeys litsenziyasini oladi, agregator esa `ws.ucs.ru/<agregator>/` shlyuzi orqali WSClient litsenziyasi bilan kassaga chiqadi. Ya’ni R-Keeper 7 kassasiga tashqi buyurtma **UCS bulut shlyuzi orqali** keladi, to‘g‘ridan-to‘g‘ri emas [XULOSA, Yandex va litsenziya sahifalaridan].
- **Rizqim uchun uchta yo‘l** [XULOSA]:
  1. **r_k Delivery API** (eng hujjatlashtirilgan): restoranda r_k Delivery (Delivery_Base/Filial + XML-interfeys + Delivery_Api) bo‘lishi kerak. Rizqim — bitta “API ulanish”, har restoran o‘z kalitini beradi. Muammo: `delivery.ucs.ru` Rossiyadagi bulut; O‘zbekistondagi R-Keeper restoranlari r_k Delivery'dan foydalanadimi — tasdiqlanmagan.
  2. **BUSOFT hub'i orqali**: BUSOFT allaqachon menyu va stop-listni olib, agregatorga yuklaydi va buyurtmani kassaga tushiradi. Rizqim'ni “yana bitta agregator” sifatida qo‘shish — tijoriy kelishuv masalasi. O‘zbekistondagi R-Keeper bazasiga eng tez yo‘l shu.
  3. **To‘g‘ridan-to‘g‘ri XML interfeys**: kassa serveriga lokal ulanish (VPN yoki agent) + XML litsenziya + yopiq hujjat. Rizqim uchun eng qimmat va eng mo‘rt yo‘l.
- Litsenziyalarda 1 oylik kodlar (Delivery_Api 3279, Yandex 3508) bor, ya’ni pilot uchun bir oylik litsenziya olish texnik jihatdan mumkin [XULOSA].
- Yandex modelidagi kabi agregator modulini (“Интерфейс к Rizqim”) UCS'da yaratish faqat UCS bilan vendor darajasidagi kelishuv orqali bo‘ladi. O‘zbekiston bozori hajmida bu real emas [XULOSA].

### Gaps
- Litsenziya narxlari (rubl yoki so‘m) va O‘zbekistonda kim sotishi (BUSOFT yoki boshqa diler) ochiq emas.
- O‘zbekistonda r_k Delivery (delivery.ucs.ru) ishlatiladimi yoki BUSOFT hub'i undan mustaqilmi — aniqlanmadi.
- Webhook payload tuzilmasi, statuslar ro‘yxati va token sarlavhasi nomi hujjatda yo‘q (faqat admin-panelda).
- r_keeper 7 XML interfeysi metodlari va “uchinchi tomon dasturi uchun XML-interfeys” litsenziyasi bo‘yicha ochiq hujjat topilmadi; docs.rkeeper.ru curl orqali “connection reset” qaytardi, faqat WebFetch ishladi.
- Test (sandbox) muhit yoki dasturchi hisobi haqida hujjatda hech narsa yo‘q.
- BUSOFT hub'ining texnik tafsilotlari, narxi va uchinchi tomonga ochiqligi noma’lum.

---

## 2. Poster (joinposter): REST API, OAuth ilova, onlayn buyurtma, menyu, ombor/stop-list, webhooklar, marketpleys katalogi, O‘zbekistonda mavjudligi

### Takeaway
Poster — ro‘yxatdagi **eng ochiq** tizim. Dasturchi hisobi bepul. OAuth2 ilova orqali restoran Rizqim'ni o‘zi o‘rnatadi va token **2 yil** amal qiladi. `incomingOrders.createIncomingOrder` buyurtmani kassaga “onlayn buyurtma” sifatida tushiradi. `menu.getProducts` har filial uchun narx va `visible` beradi, buyurtma manbasi bo‘yicha (`sources`) alohida narx va ko‘rinishni ham beradi. Webhooklar MD5 imzo bilan keladi va 2 kun ichida 15 martagacha qayta yuboriladi. Katalogga chiqish uchun Poster moderatsiyasidan o‘tish kerak, lekin **moderatsiyasiz ham** tanlangan hisoblarda ishlatish mumkin. O‘zbekiston uchun Poster'da fiskal ilova bor, katalogdagi agregatorlar esa Glovo va Bolt Food (Yandex/Uzum yo‘q). Kamchilik: API'da alohida “stop-list” obyekti yo‘q; mavjudlik `visible`/`hidden` va ombor qoldig‘i orqali aniqlanadi.

### Cited Findings

**Dasturchi hisobi va ilova** [RASMIY HUJJAT]
- Ro‘yxatdan o‘tish: `https://dev.joinposter.com/en/signup` → All Applications → Poster for Developers → Add application. Sozlamalar: Application ID/Secret, **Access Categories**, OAuth (redirect_url), Manage Platform, POS Platform, Device Platform, **Webhook URL**, “Receive webhooks for”, Connection Page. **Development** sahifasi: ilova **moderatsiyasiz** ishlaydigan hisoblarni qo‘shish (test yoki katalogga chiqmaydigan ishlatish uchun). **Publishing**: moderatsiyaga yuborish (video, test kirish, foydalanuvchi qo‘llanmasi, har mintaqa uchun narx) — [dev.joinposter.com, Developer Account](https://dev.joinposter.com/en/docs/v3/start/account), 25.10.2026 da o‘qildi
- Dasturchi hisobi **bepul**; muddati tugasa support uzaytiradi; API savollari: **contact@joinposter.com**. Katalogga chiqqandan keyin har o‘rnatishda mijoz kontaktlari email'ga keladi; Poster mahsulot ichida reklama va Intercom xabarlari bilan ilovalarni targ‘ib qiladi — [dev.joinposter.com, Release](https://dev.joinposter.com/en/docs/v3/market/release), 25.10.2026

**OAuth2** [RASMIY HUJJAT]
- 1-qadam: `https://joinposter.com/api/auth?client_id={client_id}&redirect_uri={redirect_uri}&response_type=code` (yoki `https://{account}.joinposter.com/api/auth?application_id=...`). 2-qadam: `redirect_uri?code=...&account=...`. 3-qadam: `POST https://{account}.joinposter.com/api/v2/auth/access_token` (form-data: `application_id`, `application_secret`, `grant_type=authorization_code`, `redirect_uri`, `code`). Javob: `access_token` (“yagona token, **2 yil** amal qiladi”), `account_number`, `user`, `ownerInfo` (country, phone…), `tariff` (ilova Poster billing'iga ulangan bo‘lsa) — [dev.joinposter.com, Authorization in API](https://dev.joinposter.com/en/docs/v3/start/authApi), 25.10.2026
- O‘z sayti uchun soddaroq yo‘l: admin-panelda **Access → Integrations → Personal Integration** tokeni. Marketpleys uchun esa OAuth ilova majburiy (“mijoz token kiritmasligi kerak”) — [dev.joinposter.com, Online ordering guideline](https://dev.joinposter.com/en/docs/v3/market/guidelines/e-commerce), 25.10.2026

**Onlayn buyurtma: `incomingOrders.createIncomingOrder`** [RASMIY HUJJAT]
- `POST https://joinposter.com/api/incomingOrders.createIncomingOrder?token=...`. Parametrlar: `spot_id` (majburiy), `client_id` yoki `phone` (Poster telefon bo‘yicha mijozni topadi yoki yaratadi), `first_name`, `last_name`, `email`, `sex`, `birthday`, `client_address_id` / `client_address` (`address1`, `address2`, `comment`, `lat`, `lng`), **`service_mode`** (1 — zal, 2 — olib ketish, 3 — yetkazib berish), **`delivery_price`** (minor birlikda, faqat service_mode=3), `comment`, **`products[]`** (`product_id`, `modificator_id`, `modification` — JSON qator `[{"m":id,"a":soni}]`, `count`, `price` — minor birlikda, berilmasa filial narxi), **`payment`** (`type` 0/1 — oldindan to‘langan, `sum`, `currency` — hisob valyutasiga teng bo‘lishi shart), `promotion[]`, `delivery_time`, `skip_phone_validation` — [dev.joinposter.com, createIncomingOrder](https://dev.joinposter.com/en/docs/v3/web/incomingOrders/createIncomingOrder), 25.10.2026
- Javob: `incoming_order_id`, `status` (**0 — yangi, 1 — qabul qilindi, 7 — bekor qilindi**), `transaction_id` (bog‘langan kassa cheki), `fiscal_spreading`, `fiscal_method` va boshqalar. Hujjat ichida ziddiyat: `type` “1 — onlayn buyurtma, 2 — bron”, lekin “bu metod uchun doim 2” deb yozilgan — o‘sha manba
- Qo‘shimcha metodlar: `getIncomingOrders`, `getIncomingOrder`, **`getOwnIncomingOrders` / `getOwnIncomingOrder`** (faqat o‘z ilovangiz yaratgan buyurtmalar), `createReservation`, `getTablesForReservation`, `getReservations` — [dev.joinposter.com, sidebar](https://dev.joinposter.com/en/docs/v3/web/incomingOrders/index), 25.10.2026
- Tavsiya: buyurtma to‘langan bo‘lsa `payment` yuboriladi, kassir yopganda to‘lanadigan summa 0 ko‘rinadi; `incoming_order` webhook'iga obuna bo‘lib qabul, rad va yopilish hodisalari olinadi — [Online ordering guideline](https://dev.joinposter.com/en/docs/v3/market/guidelines/e-commerce)

**Menyu, narx, mavjudlik** [RASMIY HUJJAT]
- `menu.getProducts` (filtr: `category_id`, `type`): `product_id`, `product_name`, `menu_category_id`, `type` (1 — yarim tayyor, 2 — taom, 3 — tovar), `weight_flag`, `fiscal`, `workshop`, `photo`, `cooking_time`; **`spots[]`** — `spot_id`, **`price`** (minor birlikda), `profit`, **`visible`** (0/1 filialda ko‘rinadimi); **`sources[]`** — buyurtma manbasi bo‘yicha `id`, `name`, **`price`**, **`visible`**; **`group_modifications[]`** — `dish_modification_group_id`, `num_min`, `num_max`, ichida `modifications[]` (`dish_modification_id`, `name`, `price`, rasmlar); javob misolida `hidden` maydoni ham bor — [dev.joinposter.com, getProducts](https://dev.joinposter.com/en/docs/v3/web/menu/getProducts), 25.10.2026
- Menyu sinxronizatsiyasi bo‘yicha Poster tavsiyasi: `menu.getProducts` + `menu.getCategories`, `product`/`dish` webhook'lariga obuna (narx, nom o‘zgarishi, yangi yoki o‘chirilgan mahsulot), har webhook'da `object_id` = `product_id`, keyin `menu.getProduct`. Bog‘lashni (mapping) saqlamaslik uchun “product extras” (`application.setEntityExtras`) — [Online ordering guideline](https://dev.joinposter.com/en/docs/v3/market/guidelines/e-commerce)
- Ombor qoldig‘i: `storage.getStorageLeftovers` (`storage_id`, `type` 1–5, `zero_leftovers`) → `ingredient_left`, `storage_ingredient_left`, `limit_value`, `hidden` — [dev.joinposter.com, getStorageLeftovers](https://dev.joinposter.com/en/docs/v3/web/storage/getStorageLeftovers), 25.10.2026

**Webhooklar** [RASMIY HUJJAT]
- POST JSON: `account`, `account_number`, `object`, `object_id`, `action` (`added`/`changed`/`removed`/`transformed`), `time`, `verify`, ixtiyoriy `data`. Imzo: `md5(account;object;object_id;action;[data;]time;secret)`. HTTP 200 qaytarilmasa Poster **2 kun ichida 15 martagacha** qayta yuboradi. Obyektlar: `transaction`, **`incoming_order`** (yangi → qabul/rad bo‘lganda `changed`, `data.type`), `product`, `dish`, `category`, `ingredient`, **`stock`** (`data`: `type`, `element_id`, `storage_id`, `value_relative`, `value_absolute`), `spot`, `application` (o‘rnatish/o‘chirish; `added` da `data.access_token`) va boshqalar — [dev.joinposter.com, Webhooks](https://dev.joinposter.com/en/docs/v3/web/webhooks), 25.10.2026

**Katalog va O‘zbekiston**
- Poster ilovalar katalogida: “**Fiscalization application in Uzbekistan**”, “Integration of menus and orders from **Glovo**”, “Menu and Order Integration with **Bolt Food**”, Glovo kuryer xizmatlari. Katalog matnida Yandex, Uzum, Wolt yoki Express24 topilmadi — [joinposter.com/en/applications](https://joinposter.com/en/applications), 25.10.2026 [TASDIQLANGAN: sahifa matni qidirildi]
- O‘zbekiston fiskal sahifasi markirovka bo‘yicha ko‘rsatmalarda **so‘m** valyutasini ishlatadi (“narxni 0 so‘m qo‘ying”); fiskal variantlar E-POS, ARCA, Fiscal Me/Box (oldingi notada) — [joinposter.com/fiscalization-in-uzbekistan](https://joinposter.com/fiscalization-in-uzbekistan), 25.10.2026 [YETKAZIB BERUVCHI]

### Inferences
- Poster API'si Rizqim'ning ehtiyojini **to‘liq** qoplaydi: buyurtma (`service_mode=3`, `delivery_price`, oldindan to‘lov), menyu va modifikatorlar, filial narxi va `visible`, status webhook'i. Bitta OAuth ilova Poster'dagi barcha O‘zbekiston restoranlariga xizmat qiladi; har restoran ilovani o‘zi o‘rnatadi [XULOSA].
- **`sources[]` muhim:** restoran Poster'da “Rizqim” buyurtma manbasini yaratsa, Rizqim uchun alohida narx va ko‘rinish o‘rnatiladi (masalan, marketpleys narxi zal narxidan baland). Bu Poster'ning Rizqim uchun tayyor “kanal narxi” mexanizmi [XULOSA].
- **Stop-list:** API'da aniq stop-list yo‘q. Amaliy yechim: `spots[].visible`/`sources[].visible` va `hidden` + `product`/`dish` webhook'lari + kerak bo‘lsa `stock` webhook'i (qoldiq 0). Bu yondashuvni pilotda tekshirish kerak [XULOSA].
- Narxlar **minor birlikda** (Poster “tsent” deb yozadi). So‘m uchun bu tiyin (×100) ekanini real UZS hisobida tekshirish kerak [XULOSA].
- Poster O‘zbekistonda kichik (oldingi nota: diler e’loni 2018-yilgi, narx ochiq emas). Shuning uchun texnik jihatdan oson bo‘lsa ham, Poster integratsiyasi ustuvorlikda R-Keeper va mahalliy POS'lardan keyin turadi [XULOSA].

### Gaps
- O‘zbekistonda nechta restoran Poster ishlatadi va Poster ofisi yoki rasmiy dileri bormi — topilmadi.
- Katalogga chiqish uchun Poster komissiyasi yoki daromad ulushi (agar ilova Poster billing'i orqali to‘lansa) hujjatda yo‘q.
- Poster kassasidagi “stop-list” funksiyasi API'da qanday aks etishi (`visible`mi, `hidden`mi yoki boshqa narsami) aniq emas.
- `incoming_order` webhook'i “yopildi” hodisasini beradimi: qo‘llanma “close order” deydi, webhook sahifasi faqat qabul/rad deydi.

---

## 3. Mahalliy POS'lar (Alipos, Clopos, Mison, VKassa, Dastur-xon, BILLZ, Paloma365, RAOS): ochiq yoki hamkor API, webhook, agregator integratsiyalari, hujjat, kontakt

### Takeaway
Mahalliy bozorda **faqat ikkitasida ochiq dasturchi hujjati bor**: **Clopos** (to‘liq OAuth ilova, buyurtma, menyu, narx ro‘yxati, stop-list; webhook hali yo‘q) va **BILLZ** (chakana savdo API'si, restoran buyurtmasi emas). **Alipos**'da ochiq hujjat yo‘q. Lekin ochiq GitHub kodi Alipos'da **`web.alipos.uz/api/Integration/v1/...`** hamkor API'si borligini ko‘rsatadi va u **Yandex Eda'ning hamkor tomoni spetsifikatsiyasiga aynan mos keladi**: `/security/oauth/token`, `/menu/{id}/composition`, `/menu/{id}/availability`, `POST /order`, `eatsId`, `ACCEPTED_BY_RESTAURANT`. Alipos uchinchi tomon Telegram botiga (MrPubBot) `client_id`/`secret` bergan. Demak Rizqim Alipos'ga “yana bitta agregator” sifatida ulanishi mumkin. Mison “Партнёрское API”ni faqat eng yuqori tarifda e’lon qiladi, RAOS esa “API kirish”ni Pro'da. VKassa, Dastur-xon va Paloma365 da ochiq API topilmadi.

### Cited Findings

**Alipos** (alipos.uz)
- Ochiq dasturchi hujjati yoki “API” sahifasi topilmadi. 25.10.2026 da alipos.uz curl'da TLS sertifikati mos kelmadi, WebFetch'da 503 qaytdi [TASDIQLANGAN]. Integratsiyalar katalogi va narxlar (Yandex Eats, Uzum Tezkor, Yemak, Robosell, Delever; Pro'dan 10 $/oy + 100 $ ulash) — oldingi nota, [alipos.uz/ru/integrations](https://alipos.uz/ru/integrations) (24.10.2026) [YETKAZIB BERUVCHI]
- **Ochiq kod dalili**: [khajiev13/restaurant-mini-app](https://github.com/khajiev13/restaurant-mini-app) — “Telegram Mini App for restaurant ordering with FastAPI, React, PostgreSQL, **AliPOS**”, oxirgi commit **31.07.2026**, 0 yulduz. `backend/app/services/alipos_api.py` va `config.py` da [KOD]:
  - `alipos_api_base_url = "https://web.alipos.uz"`; sozlamalar `alipos_api_client_id`, `alipos_api_client_secret`, `alipos_restaurant_id`
  - Token: `POST {base}/security/oauth/token`, form: `client_id`, `client_secret`, `grant_type=client_credentials`; `expires_in` bo‘lmasa 86 400 s deb olinadi
  - Menyu: `GET /api/Integration/v1/menu/{restaurantId}/composition`; mavjudlik: `GET /api/Integration/v1/menu/{restaurantId}/availability`; zal va stollar: `GET /api/Integration/v1/restaurant/{restaurantId}/halls-and-tables`; to‘lov turlari: `GET /api/Integration/v1/paymentMethod/all`
  - Buyurtma: `POST /api/Integration/v1/order` (payload: `discriminator`, `platform: "MrPubBot"`, `eatsId`, `comment`, `deliveryInfo`, `paymentInfo`, `items[]` — `id`, `quantity`, `price`, `modifications[]`, ixtiyoriy `tableId`), javobda `orderId`; holat: `GET /api/Integration/v1/order/{id}`; bekor qilish: `DELETE /api/Integration/v1/order/{id}`
  - **Alipos'dan hamkorga webhooklar**: `POST /webhooks/order-status` (body: `eatsId`, `status`, `updatedAt`) va `POST /webhooks/stoplist/{product_id}?restaurantId=…&count=…`; ikkalasi `clientId` va `clientSecret` sarlavhalari bilan tekshiriladi (hamkor bergan qiymatlar bilan bir xil)
  - Statuslar: `ACCEPTED_BY_RESTAURANT`, `READY`, `TAKEN_BY_COURIER`, `DELIVERED`, `CANCELLED`
- **Yandex Eda hamkor-tomon spetsifikatsiyasi**: “Barcha metodlar **hamkor tomonida** amalga oshirilishi kerak”; Yandex — klient (pull model). Yo‘llar: `POST /security/oauth/token`, `GET /menu/{restaurantId}/composition`, `GET /menu/{restaurantId}/availability`, `GET /menu/{restaurantId}/promos`, `GET /restaurants`, `POST /order`, `GET|PUT|DELETE /order/{orderId}`, `GET|PUT /order/{orderId}/status`, `PUT /order/{orderId}/courier`, `GET /places/{restaurantId}/zones`, `/schedule`. Spetsifikatsiyada `eatsId`, `discriminator`, `marketplace`, `ACCEPTED_BY_RESTAURANT`, `TAKEN_BY_COURIER` bor — [yandex.ru/dev/eda-vendor, OpenAPI JSON](https://yandex.ru/dev/eda-vendor/doc/ru/ref/external-api.openapi.json); [Yandex Eda API ref](https://yandex.ru/dev/eda-vendor/doc/ru/ref/), 25.10.2026 [RASMIY HUJJAT; mos kelish TASDIQLANGAN — yo‘llar solishtirildi]

**Clopos** (clopos.com; Ozarbayjon, O‘zbekistonda ham sotiladi)
- Dasturchi portali: **developer.clopos.com** (hujjat), API: **open-api.clopos.com**. “Restoran menyusini o‘qing, **buyurtmalarni oshxonaga yuboring**, cheklar va qoldiqni kuzating… restoran bergan va qaytarib olishi mumkin bo‘lgan ruxsatlar bilan.” Buyurtmalar **StandardOrder v2** tuzilmasida, “Wolt va Bolt buyurtmalari keladigan shaklning o‘zi”; menyu sinxronizatsiyasi: “mahsulotlar, kategoriyalar, **narx ro‘yxatlari**, stansiyalar va **stop-list**”. Konventsiyalar: `Authorization: Bearer` + **`X-Venue-Id`**; javob `{success, message, data}`; pul **asosiy birlikda** (9.90); xatolarda barqaror `error` kodi. Eski `integrations.clopos.com/open-api/v1` va `/v2` eskirgan, lekin hali ishlaydi. Kontakt: **dev@clopos.com** — [developer.clopos.com/docs](https://developer.clopos.com/docs/index.md), 25.10.2026 [RASMIY HUJJAT]
- OAuth 2.0: dasturchi ilova yaratadi (scope'lar — “ship”, redirect URI). **Dasturchi hisoblarini Clopos tasdiqlaydi** (dev@clopos.com ga yozish kerak). Brend ilovani o‘rnatib, scope va filiallarni tanlaydi. Token: `POST https://open-api.clopos.com/oauth/token` (`-u client_id:secret`, `grant_type=client_credentials`, `audience=install-<installation_id>`), `expires_in` 3599; token shaffof emas (`GET /v1/whoami`) — [developer.clopos.com, Authentication](https://developer.clopos.com/docs/authentication.md), 25.10.2026 [RASMIY HUJJAT]
- Scope'lar: `menu.products:read` (mahsulot, narx, mavjudlik), `menu.categories:read`, `menu.price-lists:read`, `menu.stations:read`, `sale.orders:read|create|update`, `sale.receipts:read|update`, `crm.customers:*`, `inventory.stock:read` va boshqalar — [developer.clopos.com, Scopes](https://developer.clopos.com/docs/scopes.md) [RASMIY HUJJAT]
- Buyurtma: `POST https://open-api.clopos.com/v1/orders` (StandardOrder v2, camelCase): `orderNumber`, `status`, `type` (`delivery`, `dine_in`…), `saleTypeId`, `consumer` (name, phone, address), `payment[]` (method, amount, currency, status), `deliveryFee`, `subtotal`, `total`, `products[]` (`id`, **`hash` — har qatorga noyob UUID**, `count`, `price`, `totalPrice`, `modifiers[]`). Qoidalar: Clopos **summani qayta hisoblamaydi**; **`Idempotency-Key`** sarlavhasi (`replayed: true`). Hayot sikli statuslari: `CREATED`, `PENDING_PAYMENT`, **`PENDING_ACCEPTANCE`** (har yangi buyurtma shundan boshlanadi), `AUTO_ACCEPTED`, `ACCEPTED`, `REJECTED`, `SCHEDULED`, `IN_PRODUCTION`, `READY`, `PICKED_UP`, `DELIVERING`, `READY_FOR_PICKUP`, `COMPLETED`, `CANCELLED`, `FAILED` — [developer.clopos.com, Create an order](https://developer.clopos.com/docs/api-reference/open-api/orders/create-order.md), 25.10.2026 [RASMIY HUJJAT]
- Stop-list: `GET https://open-api.clopos.com/v1/menu/products/stop-list` (+ `X-Venue-Id`); javobda yo‘q mahsulotda cheklov yo‘q — [developer.clopos.com, Get Stop List](https://developer.clopos.com/docs/api-reference/open-api/products/get-stop-list.md) [RASMIY HUJJAT]
- **Webhooklar “hali mavjud emas”**, yo‘l xaritasida; hozircha buyurtmalar, cheklar va stop-list **polling** qilinadi; beta uchun dev@clopos.com — [developer.clopos.com, Webhooks](https://developer.clopos.com/docs/webhooks.md) [RASMIY HUJJAT]
- Limitlar: **200 so‘rov / 10 s** (bitta ilova, bitta brend), 1000 / 10 s (ilova, barcha brendlar); `X-RateLimit-*` sarlavhalari — [developer.clopos.com, Rate limits](https://developer.clopos.com/docs/rate-limits.md) [RASMIY HUJJAT]
- Saytda: “Integration with aggregators — Wolt kabi tashqi platformalardan buyurtmalarni qabul qiling” — [clopos.com/en](https://www.clopos.com/en), 25.10.2026 [YETKAZIB BERUVCHI]. O‘zbekistonda Uzum Tezkor integratsiyasi — faqat LinkedIn parchasi (oldingi nota) [ANIQ EMAS]

**Mison** (mison.uz)
- Tariflar sahifasida **“Партнёрское API для своих приложений”** faqat **“Под ключ”** tarifida (narx hisob-kitob bo‘yicha). “Полный” tarifda: “Яндекс Еда, Uzum Tezkor, Tez Chakana — под ключ”, “o‘z bot, sayt va kol-markaz — 0% komissiya”; “Agregatorda akkaunt va uning talablariga mos menyuni biz qilamiz”, “3 kunda”; “Zal, yetkazib berish, agregatorlar, bot, QR-menyu va sayt bitta nomenklatura bilan ishlaydi. Pozitsiyani olib tashladingiz — hamma joyda yo‘qoladi” — [mison.uz/ru](https://mison.uz/ru/), 25.10.2026 [YETKAZIB BERUVCHI]. Ochiq API hujjati topilmadi

**VKassa** (vkassa.uz)
- Saytda “API”, “webhook” yoki “dasturchi” so‘zlari yo‘q; integratsiya qatorlari: “Dostavka integratsiya ✓ Cheklangan” (tarif jadvalida), “Yetkazib berish, olib ketish, kuryer holati va **Telegram integratsiya**”. Yandex/Uzum nomlari yo‘q — [vkassa.uz](https://vkassa.uz/), 25.10.2026 [TASDIQLANGAN: sahifa matni qidirildi]

**Dastur-xon** (dastur-xon.uz)
- Sahifa matnida “API”, “integratsiya”, “Yandex” yoki “Uzum” uchramaydi — [dastur-xon.uz](https://dastur-xon.uz/), 25.10.2026 [TASDIQLANGAN]

**BILLZ** (billz.io; chakana savdo, restoran emas)
- Ochiq hujjat: **docs.billz.io** — “BILLZ 2.0 REST API… eCommerce, botlar, BI, **marketpleyslar**”; **OpenAPI 3.0.3** (`https://docs.billz.io/openapi.yaml`), Postman/Insomnia kolleksiyalari, `llms.txt`, MCP — [docs.billz.io](https://docs.billz.io/), [llms.txt](https://docs.billz.io/llms.txt), 25.10.2026 [RASMIY HUJJAT]
- Avtorizatsiya: “integratsiya kaliti” (sirli kalit) → `POST https://api-admin.billz.ai/v1/auth/login` → `access_token` (**15 kun**) + `refresh_token` (30 kun); `POST /v2/auth/refresh` (bir martalik rotatsiya) — [docs.billz.io, Аутентификация](https://docs.billz.io/concepts/auth.md) [RASMIY HUJJAT]
- Internet-buyurtma: alohida “internet-buyurtma” obyekti **yo‘q**, oddiy sotuv sifatida o‘tkaziladi: `POST /v2/order {shop_id, cashbox_id}` → `POST /v2/order-product/:order_id` (har tovar alohida) → mijoz, chegirma → `POST /v2/order-payment/:order_id`; asinxron metodlar `Billz-Response-Channel: HTTP` sarlavhasini talab qiladi — [docs.billz.io, Интернет-заказ](https://docs.billz.io/guides/ecommerce-order.md) [RASMIY HUJJAT]
- Ochiq API'da yo‘q: **webhooklar** (“polling qiling”), **idempotentlik sarlavhasi**, STIR bo‘yicha mijoz qidirish — [docs.billz.io, llms-full.txt](https://docs.billz.io/llms-full.txt) [RASMIY HUJJAT]

**Paloma365** (paloma365.uz; Qozog‘istondan)
- Rasmiy ochiq API hujjati topilmadi; sayt matnida “API” yoki agregator so‘zlari yo‘q [TASDIQLANGAN]. Uchinchi tomon no-code konnektorlari Paloma365 konnektorini e’lon qiladi (ApiMonster, ApiX-Drive) — [apimonster.ru](https://apimonster.ru/connector/service/paloma365/); [apix-drive.com](https://apix-drive.com/ru/paloma365) [ANIQ EMAS: qidiruv parchasi]

**RAOS** (raos.uz, TezCode; asosan do‘kon/chakana)
- **“API kirish”** faqat **Pro** tarifida (799 000 so‘m/oy) ko‘rsatilgan; “1C integratsiya (tez kunda)”. “Uzum Market, Yandex Go va Wildberries integratsiyalari hozirda ishlab chiqilmoqda”; hozir mavjud: Soliq.uz (OFD), TezDetal, **MaxSavdo** (Telegram do‘kon, TezCode mahsuloti) — [raos.uz](https://raos.uz/), 25.10.2026 [YETKAZIB BERUVCHI]. API hujjati topilmadi

**Umumiy matritsa (25.10.2026)**

| POS | Ochiq hujjat | Buyurtma yuborish | Menyu/narx o‘qish | Stop-list | Webhook | Ulanish shakli | Manba turi |
|---|---|---|---|---|---|---|---|
| **R-Keeper** (r_k Delivery) | Ha (docs.rkeeper.ru) | `POST /orders/api/v1/orders` | `GET /menu/view` (narx) | `isContainInStopList` | Ha (admin-panelda sozlanadi) | Restoran beradigan client_id/secret + Delivery_Api litsenziyasi | [RASMIY HUJJAT] |
| **Poster** | Ha (dev.joinposter.com) | `incomingOrders.createIncomingOrder` | `menu.getProducts` (`spots`/`sources` narxi) | Alohida yo‘q (`visible`/`hidden`, qoldiq) | Ha (MD5, 15 urinish) | OAuth ilova, bepul dasturchi hisobi, katalog moderatsiyasi | [RASMIY HUJJAT] |
| **Clopos** | Ha (developer.clopos.com) | `POST /v1/orders` (StandardOrder v2) | Products, price lists | `GET /v1/menu/products/stop-list` | **Yo‘q** (polling) | OAuth ilova; dasturchi hisobini Clopos tasdiqlaydi | [RASMIY HUJJAT] |
| **Alipos** | Yo‘q | `POST /api/Integration/v1/order` | `/menu/{id}/composition` | `/menu/{id}/availability` + stop-list webhook | Ha (order-status, stoplist) | Alipos beradigan client_id/secret; Yandex Eda spetsifikatsiyasi | [KOD] |
| **Mison** | Yo‘q | “Партнёрское API” (Под ключ) | ? | ? | ? | Hamkorlik | [YETKAZIB BERUVCHI] |
| **BILLZ** (chakana) | Ha (docs.billz.io) | Sotuv sifatida (`/v2/order`…) | Katalog, qoldiq, narx | Qoldiq orqali | **Yo‘q** | Integratsiya kaliti | [RASMIY HUJJAT] |
| **RAOS** | Yo‘q | “API kirish” (Pro) | ? | ? | ? | Hamkorlik | [YETKAZIB BERUVCHI] |
| **VKassa, Dastur-xon, Paloma365** | Yo‘q | Topilmadi | — | — | — | Hamkorlik so‘rovi | [TASDIQLANGAN: saytda yo‘q] |

### Inferences
- **Eng muhim topilma — “Yandex Eda protokoli” bozor standarti.** Yandex Eda'ga ulangan har bir POS (Alipos, Mison, R-Keeper/BUSOFT, iiko) Yandex spetsifikatsiyasining hamkor tomonini (`/security/oauth/token`, `/menu/{id}/composition`, `/availability`, `POST /order`, `/order/{id}/status`) **server sifatida** amalga oshirgan bo‘lishi kerak. Alipos holatida bu kod bilan tasdiqlandi: Alipos uni uchinchi tomon botiga ham ochgan. Agar Rizqim'ning “POS konnektori” **Yandex Eda klienti kabi** yozilsa (shu yo‘llarni chaqiruvchi), bitta kod bilan Yandex'ga ulangan bir nechta mahalliy POS'ga, har birining o‘z `client_id`/`secret`i bilan, ulanish mumkin [XULOSA; Alipos'dan boshqa POS'larda tekshirilmagan].
- **Alipos uchun amaliy yo‘l:** Alipos'dan Rizqim uchun “platforma” (`platform`, `discriminator`) va kalitlarni so‘rash, webhook URL'larini (order-status, stoplist) berish. Tijoriy shart, ehtimol, Alipos'ning agregator tarifi: restoran Pro'da bo‘lishi va 10 $/oy + 100 $ ulash to‘lashi. Buni Alipos bilan muzokarada aniqlash kerak [XULOSA].
- **Clopos texnik jihatdan eng yaxshi hujjatlangan mahalliy variant**, lekin webhook yo‘qligi sababli status va stop-list **polling** bilan olinadi (200 so‘rov/10 s yetarli). Clopos'ning O‘zbekistondagi mijozlari soni noma’lum [XULOSA].
- **BILLZ va RAOS** — chakana savdo uchun. Rizqim'ning restoran marketpleysi uchun ahamiyati past. Lekin Rizqim do‘kon yoki oziq-ovqat yetkazib berishga kengaysa, BILLZ API'si tayyor [XULOSA].
- **Mison, VKassa, Dastur-xon, Paloma365, RAOS** bilan integratsiya faqat **hamkorlik kelishuvi** orqali bo‘ladi: ochiq hujjat yo‘q. Mison'da API faqat eng yuqori tarifda. Rizqim ularga o‘z ochiq “Rizqim POS Integration API”sini (Yandex uslubida) taklif qilishi mumkin [XULOSA].

### Gaps
- Alipos API'sining rasmiy hujjati, test muhiti, kalit berish tartibi va narxi — faqat bitta ochiq repozitoriyadagi koddan ma’lum. Alipos uchinchi tomonlarga buni ommaviy taklif qiladimi, tasdiqlanmagan. alipos.uz 25.10.2026 da ochilmadi.
- Mison, iiko va R-Keeper/BUSOFT ham Yandex spetsifikatsiyasini tashqi hamkorga ochadimi — tekshirilmagan.
- Uzum Tezkor va Yemak POS integratsiya protokoli (Yandex'ga o‘xshashmi yoki o‘zinikimi) bu notada o‘rganilmadi.
- Clopos O‘zbekistondagi mijozlari soni va O‘zbekiston agregatorlari bilan rasmiy integratsiyasi tasdiqlanmagan.
- VKassa, Dastur-xon, Paloma365 uchun hamkorlik kontaktlari (saytdagi umumiy telefonlardan tashqari) topilmadi.

---

## 4. GitHub: SDK, klient va misollar (“r_keeper xml”, “rkeeper api”, “joinposter api”, “poster pos api”, “clopos api”, “alipos”)

### Takeaway
GitHub'da **rasmiy SDK'lar zaif**. Poster'ning rasmiy hujjat manbasi (`joinposter/docs`, 138 fork) va eski PHP klienti bor. R-Keeper bo‘yicha faqat kichik shaxsiy loyihalar bor (eng yaxshisi 8 yulduz). Clopos uchun norasmiy Integrify SDK (Python arxivlangan, PHP 2026-09). Eng foydali topilma — **Alipos bilan ishlaydigan ochiq Telegram Mini App** (khajiev13/restaurant-mini-app), u Alipos hamkor API'sini ochib beradi.

### Cited Findings
**R-Keeper** (GitHub qidiruvi “rkeeper”: 37 ta repo, 25.10.2026) [TASDIQLANGAN]
- [nutnetru/rkeeper7-crm-api](https://github.com/nutnetru/rkeeper7-crm-api) — “RKeeper 7 CRM Api Wrapper”, PHP, **8 yulduz**, 3 fork, 2017-yilda yaratilgan, `updated_at` 12.01.2026
- [Afatsum/pos-system](https://github.com/Afatsum/pos-system) — “iiko, arhi, rKeeper, frontpad API SDK” PHP kutubxonasi, 4 yulduz, yangilangan 06.06.2024
- [alexandr-andreyev/rkeeper7-simple-api](https://github.com/alexandr-andreyev/rkeeper7-simple-api) — Go, 0 yulduz, 21.02.2025
- [StakanAgan/adapter-public](https://github.com/StakanAgan/adapter-public) — “Adapter for RKeeper API”, Python, 05.12.2024
- [madeinalyss/CashBeacon](https://github.com/madeinalyss/CashBeacon) — “Messenger-To-RK7 (RKeeper) adapter”, C#, 13.07.2026; o‘sha muallifning [BatUtils](https://github.com/madeinalyss/BatUtils) — “RKeeper, StoreHouse va boshqa UCS dasturlari uchun utilitalar”, 20.08.2026
- UCS'ning rasmiy `rkeeper` tashkiloti: faqat [rkeeper_payment_sdk](https://github.com/rkeeper/rkeeper_payment_sdk) (Kotlin, 2021) va [rkeeper_desktop_hardware_drivers](https://github.com/rkeeper/rkeeper_desktop_hardware_drivers) (Pascal, 2023) — buyurtma yoki Delivery API SDK'si yo‘q
- [Bekmurod3388/rkeeper_mysql](https://github.com/Bekmurod3388/rkeeper_mysql) (PHP, 2022) va [Abduraufdev0788/rkeeper-django](https://github.com/Abduraufdev0788/rkeeper-django) (2025-11) — o‘zbek mualliflarining kichik loyihalari, 0 yulduz

**Poster** [TASDIQLANGAN]
- [joinposter/docs](https://github.com/joinposter/docs) — dev.joinposter.com hujjatining manbasi (docsify, sahifadagi “edit” havolasi shu repoga olib boradi), 3 yulduz, **138 fork**, 5 ochiq issue, yangilangan 20.04.2026
- [joinposter/pos-platform-boilerplate](https://github.com/joinposter/pos-platform-boilerplate) — POS platformasi ilovalari uchun shablon, JS, **21 yulduz**, 26 fork, 10.04.2026
- [joinposter/api-php](https://github.com/joinposter/api-php) — rasmiy PHP klient, 9 yulduz, 01.10.2025; [joinposter/poster-manage-platform](https://github.com/joinposter/poster-manage-platform) (2026-05), [device-platform-example](https://github.com/joinposter/device-platform-example)
- Norasmiy: [VPavelko/joinposter-api](https://github.com/VPavelko/joinposter-api) (TypeScript, 1 yulduz, 2020), [komba/poster](https://github.com/komba/poster) (Ruby, 22.10.2026)

**Clopos** [TASDIQLANGAN]
- [integrify-sdk/integrify-clopos-python](https://github.com/integrify-sdk/integrify-clopos-python) — norasmiy, “rasmiy hujjat asosida”; muhit o‘zgaruvchilari client ID/secret, brand, venue ID; 1 yulduz, **arxivlangan**, 14.08.2026. [integrify-clopos-php](https://github.com/integrify-sdk/integrify-clopos-php) — 18.09.2026, Packagist'da [integrify/clopos](https://packagist.org/packages/integrify/clopos) (Open API v2; brend, venue va integrator JWT ichida) — qidiruv natijasi [ANIQ EMAS: README o‘qilmadi]
- Rasmiy `cloposcom` tashkiloti: faqat [electron-release](https://github.com/cloposcom/electron-release) (Windows ilova relizlari)

**Alipos** [KOD]
- [khajiev13/restaurant-mini-app](https://github.com/khajiev13/restaurant-mini-app) — FastAPI + React Telegram Mini App, AliPOS bilan integratsiya (menyu, stop-list, buyurtma, status webhooklari, zal va stollar), Multicard to‘lovi; oxirgi commit 31.07.2026. Tafsilotlar 3-bo‘limda. Boshqa “alipos” repolari aloqasiz yoki bo‘sh

### Inferences
- R-Keeper Delivery API va Clopos API'si uchun tayyor ishonchli SDK yo‘q. Rizqim klientni OpenAPI/hujjatdan o‘zi yozadi. Bu katta ish emas: ikkalasi oddiy REST + OAuth client_credentials [XULOSA].
- `khajiev13/restaurant-mini-app` — Rizqim'ga juda yaqin loyiha (Telegram Mini App + mahalliy POS). Uning `alipos_api.py`, webhook va qayta urinish (queued/synced) mantiqi Alipos konnektori uchun tayyor ma’lumotnoma. Litsenziyasi tekshirilmagan, shuning uchun koddan nusxa olinmaydi, faqat protokol uchun foydalaniladi [XULOSA].

### Gaps
- GitHub'da “r_keeper xml” bo‘yicha XML interfeys metodlarini (CreateOrder, SaveOrder) to‘liq ko‘rsatadigan faol repo topilmadi. nutnetru va Afatsum kodlari o‘qilmadi.
- Integrify Clopos PHP README'si va restaurant-mini-app litsenziyasi o‘qilmadi.
- Poster, Clopos va Alipos'ning O‘zbekistonda ishlab turgan ochiq integratsiyalari (Uzum Tezkor konnektori va h.k.) GitHub'da topilmadi.
