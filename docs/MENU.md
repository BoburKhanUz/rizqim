# Menyu va buyurtma modeli

Bu hujjat P1-21 da qo'shilgan menyu imkoniyatlarini tushuntiradi: modifikatorlar, vazn bo'yicha sotuv, qozon
(partiya) qoldig'i, filial stop-listi, kanal bo'yicha narx, idempotency kaliti, xizmat haqi va joy narxi, rasmlar.
Hammasi bitta `Order` modeliga yoziladi va Telegram mini-app, mobil ilova, POS va agregatorlar uchun bir xil ishlaydi.
Barcha endpointlar `/api/v1` ostida. Mavjud javob maydonlari o'zgarmagan, yangilari faqat qo'shilgan.

## 1. Modifikatorlar

- **Guruh** (`modifier_groups`): do'konga tegishli, masalan "Sous tanlang" yoki "Qo'shimchalar". `name` va ixtiyoriy
  `name_i18n` (`{"ru": "..."}`), `min_select`, `max_select` (bo'sh — cheklanmagan), `is_required` (kamida 1 ta),
  `sort_order`, `is_active`.
- **Modifikator** (`modifiers`): `name`, `price_delta` (narx qo'shimchasi, so'm), `max_quantity` (bitta taomga necha
  marta), ixtiyoriy `product_variant_id` (keyinchalik ingredient qoldig'i uchun), `sort_order`, `is_active`.
- **Taomga ulash** (`product_modifier_groups`): har bir taom uchun guruh qoidalarini almashtirish mumkin
  (`min_select`, `max_select`, `is_required`; bo'sh bo'lsa guruhniki). `PUT /owner/products/{id}/modifier-groups`
  ro'yxatni almashtiradi; P2-30: qatorda yuborilmagan qoida taomning saqlangan qoidasini saqlab qoladi (`null` —
  guruhnikiga qaytaradi), shuning uchun faqat bog'lash ro'yxatini saqlash qoidalarni o'chirmaydi. `GET /owner/products`
  har bir taomni `modifier_group_links` bilan qaytaradi (tartib bo'yicha: `modifier_group_id`, `min_select`,
  `max_select`, `is_required`, `sort_order`).
- **Kim nima qiladi (P2-30).** `GET /owner/products`, `GET /owner/categories` — `menu.view` (ega, menejer, kassir,
  ofitsiant, oshpaz); mahsulot yaratish, tahrirlash, o'chirish, rasmni o'chirish — `menu.manage` (standart: ega; ega
  boshqa rolga berishi mumkin). Boshqa do'kon mahsuloti — 404 ("Mahsulot topilmadi."), do'konsiz hisob — 403
  `store_required`. Xatolar o'zbekcha, umumiy shaklda (`{success: false, message, data}`).
- **So'rov tanasi.** `POST /owner/modifier-groups` va `PUT /owner/modifier-groups/{id}` tanasi OpenAPI'da
  (`ModifierGroupRequest`, P2-30): `name`, `name_i18n` (`{"ru": "..."}`), `min_select`, `max_select`, `is_required`,
  `sort_order`, `is_active`, `modifiers[]` (`id?`, `name`, `name_i18n`, `price_delta`, `max_quantity`,
  `product_variant_id`, `sort_order`, `is_active`).

Buyurtmada `items.*.modifiers` — uuid'lar ro'yxati yoki `{id: uuid, quantity}`. Server tekshiradi: modifikator shu
taomga ulangan faol guruhda va o'zi faol, filial stop-listida emas, `max_quantity` dan oshmagan, har bir guruhda tanlovlar
soni `min`–`max` oralig'ida. Xato bo'lsa 422 (`items.N.modifiers`), masalan "'Burger (Katta)': 'Sous' tanlovi
majburiy.". Mijoz yuborgan narx hech qachon ishlatilmaydi.

Bir xil variant va bir xil modifikatorli **donali** qatorlar birlashtiriladi; boshqa modifikatorli qatorlar alohida
qator bo'lib qoladi. Vazn bo'yicha (kg, l) qatorlar hech qachon birlashtirilmaydi: har biri alohida porsiya (o'z eng kam
miqdori, modifikatorlari bir marta, o'z yaxlitlashi), shuning uchun buyurtma summasi mijoz ko'rgan qatorlar yig'indisiga
aynan teng (2 × (0.5 kg + 5 000 sous) = 2 × 55 000 = 110 000). Qoldiq variantning umumiy miqdori bo'yicha tekshiriladi.

## 2. Vazn bo'yicha sotuv (kg, l)

Variantda `unit`: `piece` (dona), `kg`, `l`. `price` — bir birlik narxi (dona yoki 1 kg). Kg va l uchun `quantity_step`
(standart 0.1) va `min_quantity` (standart bitta qadam). Buyurtmada `quantity` kasr bo'lishi mumkin (ko'pi bilan 3 xona):
`0.5`, `1.2`. Dona butun son bo'lishi shart. Qoldiq (`stock`) ham kasr: 0.5 kg ayiriladi, bekor qilinsa qaytadi.
Javoblarda butun miqdor avvalgidek butun son (`2`), kasr esa son (`1.5`).

## 3. Yaxlitlash qoidasi

Har bir qator summasi aniq hisoblanadi va **bir marta**, butun so'mgacha, **yarmi yuqoriga** yaxlitlanadi:

```
qator = yaxlitla( birlik narxi × miqdor + modifikatorlar × (dona bo'lsa miqdor, vaznli bo'lsa 1) )
```

Modifikatorlar donali taomda har bir donaga, vaznli taomda butun qatorga bir marta qo'shiladi. Misollar:
33 333 × 0.5 = 16 666.5 → **16 667**; 12 345.67 × 0.333 = 4 111.108… → **4 111**; (50 000 + 3 000) × 2 = **106 000**.
Buyurtma summasi qatorlar yig'indisi; chegirma, yetkazish, xizmat haqi va joy narxi alohida qo'shiladi. Komissiya avvalgidek
`total_price` dan tiyingacha.

## 4. Qozon / partiya qoldig'i

`menu_batches`: filial + taom (ixtiyoriy variant), `quantity_produced`, `quantity_left`, `started_at`, `closed_at`.
Partiya variantsiz ochilsa, taomning hamma variantlari undan oladi: har bir variant birligiga `batch_consumption`
(masalan "0.7 porsiya" uchun 0.7). Bir filialda bitta taom uchun bitta ochiq partiya.

- Buyurtma partiyadan qatorlar kerak bo'lgan miqdorni oladi (qator qulfi + shartli kamaytirish; bir vaqtdagi
  buyurtmalar hech qachon qolgandan ko'p sotmaydi, qoldiq manfiy bo'lmaydi). Yetmasa 422: "Uzr, 'Osh (1 porsiya)'
  tugadi." yoki "... yetarli emas (faqat 2 ta qoldi)".
- Taom **"qozon bo'yicha sotiladi"** (`products.batch_tracked`) bo'lsa, filialda ochiq partiya bo'lmasa ham "tugadi".
  Belgilanmagan taom partiyasiz oddiy qoldiq bilan sotiladi. `GET /owner/branch-variants` ham har bir taomda
  `batch_tracked` ni beradi (P2-30).
- **Variant qoldig'i (P2-30).** Mahsulot yaratilganda (yoki tahrirlashda yangi variant qo'shilganda) `variants` JSON
  qatorida ixtiyoriy `stock` (0–1 000 000; donali taomda butun son, kg/l da 3 xonagacha) — yuborilmasa avvalgidek 999.
  Mavjud variantning qoldig'i tahrirlash formasida o'zgarmaydi (uni buyurtmalar o'zgartiradi).
- Bekor qilingan buyurtma partiyaga qaytariladi (partiya hali ochiq bo'lsa).
- Xodimlar (`batches.manage`: ega, menejer, oshpaz): `GET /owner/batches?branch_id=&include_closed=1`,
  `POST /owner/batches {branch_id?, product_id, product_variant_id?, quantity, label?}`,
  `POST /owner/batches/{id}/adjust {add | quantity_left, reason?}`, `POST /owner/batches/{id}/close`. Har biri
  harakatlar jurnaliga yoziladi (`menu.batch_opened`, `menu.batch_adjusted`, `menu.batch_closed`). Panelda
  "Qozon qoldig'i" ekrani (har 5 soniyada yangilanadi).

## 5. Stop-list

`stop_list_entries`: filialda mahsulot, variant yoki modifikator vaqtincha yo'q, sababi va ixtiyoriy `until` bilan.
Checkout uni 422 bilan rad etadi: "Uzr, 'Burger (Katta)' hozir ushbu filialda mavjud emas (tugadi). Iltimos, savatni
yangilang." `until` o'tgach yozuv o'zi o'chadi (har daqiqada `menu:expire-stop-list`). Eski `branch-variants` almashtirgichi
ham avvalgidek ishlaydi va stop-list sifatida ko'rinadi.

Xodimlar (`stoplist.manage`: ega, menejer): `GET /owner/stop-list?branch_id=`, `POST /owner/stop-list {branch_id?, type:
product|variant|modifier, id, reason?, until?}`, `DELETE /owner/stop-list/{id}`. Yozuvda `created_by` (id) va P2-30 dan
`created_by_name` — kim qo'shgani (faqat `stoplist.manage` borlarga; `menu.view` bilan o'qiganda `null`). Filialga biriktirilgan xodim faqat o'z
filialida. Panelda "Stop-list" ekrani. Jurnal: `menu.stop_list_added`, `menu.stop_list_removed`.

## 6. Mavjudlik va real-time

`GET /catalog/availability?store_id=&branch_id=` (`store_id` — uuid yoki son; juda katta yoki noto'g'ri son
`store_id`/`branch_id` — 422) — har bir faol filial uchun: `stopped` (`products`, `variants`,
`modifiers` id'lari), `stop_list` (sabab va `until`), `sold_out.variants` (partiya tugagan), `batches` (ochiq partiyalar
va qoldig'i). Xuddi shu ma'lumot `GET /catalog?store_id=` javobida `availability` sifatida keladi.

Har bir o'zgarishda `menu.availability_changed` hodisasi yuboriladi: xodimlarga `private-store.{id}`,
`private-branch.{id}`, mijoz ilovalariga ochiq `menu.{store_id}` kanaliga. Mazmuni:
`{store_id, branch_id, reason: stop_list|batch|branch_override, items: [{type, id, available}], changed_at}`. Ilova
hodisani olgach `GET /catalog/availability` ni qayta so'raydi.

## 7. Kanallar

`orders.channel`: `telegram`, `mobile`, `pos_hall`, `pos_takeaway`, `aggregator` (eski buyurtmalar — `telegram`).
`orders.external_id` — kanalning o'z raqami (agregator, POS sinxronlash), do'kon va kanal ichida yagona.

`POST /orders` da kanalni **server** belgilaydi, kirish usulidan (P1-34): access token mijoz mobil ilovasining qurilmasiga
bog'langan bo'lsa (`/auth/telegram` yoki `/auth/phone/verify` `device.type = customer_mobile` bilan, [AUTH.md](AUTH.md))
— `mobile`, aks holda (Telegram Mini App'ning initData tokeni, qurilmasiz) — `telegram`. Tanadagi `channel` eski ilovalar
uchun qabul qilinadi (`telegram` yoki `mobile`, boshqasi 422), lekin hisobga olinmaydi: Telegram'dan kirgan mijoz
`channel=mobile` yuborib mobil narxni ololmaydi. Hamkor, kuryer va brauzer qurilmalari — `telegram`.

`POST /orders` `type` — `delivery` yoki `pickup`. `dine_in` (zalda ovqatlanish) mijoz ilovalaridan **qabul qilinmaydi**
(422, `errors.type`): zal buyurtmasini ofitsiant yoki kassa (POS) qabul qiladi. Filialsiz buyurtma umuman yaratilmaydi,
chunki har bir qator filialning stop-listi va qozon qoldig'i bo'yicha tekshirilishi shart.

`channel_prices`: variantning kanaldagi narxi va/yoki yashirilgani (`product_variant_id` bo'sh — butun mahsulot
yashiriladi). Katalog (`GET /catalog`, `channel=telegram` standart, `channel=mobile`; faqat ko'rsatish uchun) kanal narxini `price` da,
variantning o'z narxini `base_price` da ko'rsatadi, yashirilganlarni chiqarmaydi; checkout shu narxni oladi va
yashirilganni 422 bilan rad etadi. Ega: `GET/PUT /owner/products/{id}/channel-prices` (`menu.manage`). Analitikada
`by_channel`.

## 8. Idempotency kaliti

`POST /orders` `Idempotency-Key` sarlavhasini qabul qiladi (1–255 ta ko'rinadigan belgi, masalan har bir checkout
urinishi uchun UUID). Bir foydalanuvchi (yoki terminal) shu kalit va **shu so'rov tanasi** bilan 24 soat ichida qayta
yuborsa, birinchi javob o'zgarmasdan qaytadi (`Idempotent-Replayed: true`), yangi buyurtma yaratilmaydi. Boshqa tana bilan
— 409. Muvaffaqiyatsiz so'rov (masalan 422) kalitni bo'shatadi, takrorlash qaytadan ishlaydi. Bir vaqtda kelgan
dublikat birinchisi tugashini kutadi va uning javobini oladi (10 soniyadan oshsa 409). Kalitlar `idempotency_keys`
jadvalida (so'rov xeshi, javob, `expires_at`), har kuni tozalanadi. Boshqa endpointga ulash:
`->middleware(EnsureIdempotency::class . ':scope')`.

Kalit buyurtma bilan **bitta tranzaksiyada** "ishlatilgan" deb belgilanadi (`IdempotencyService::markApplied`, P1-34):
buyurtma saqlangan bo'lsa, kalit ham belgilangan. Shuning uchun server buyurtmani saqlab, javobni yozishga ulgurmay
to'xtasa yoki checkout 60 soniyadan uzoq cho'zilsa ham, takroriy so'rov ikkinchi buyurtma yaratmaydi: javob hali
yozilmagan bo'lsa 409 oladi (ilova buyurtmalar ro'yxatini yangilaydi). Kalitni qo'lga kiritgan har bir urinish uni
o'zining `locked_until` qiymati bilan "egallaydi": kaliti boshqa urinishga o'tib ketgan urinish buyurtmasini saqlay olmaydi
(409, tranzaksiya bekor qilinadi) va boshqaning kalitini bo'shatmaydi. Endpoint `markApplied` ni chaqirmasa, avvalgidek
ishlaydi.

## 9. Xizmat haqi va joy narxi (POS uchun)

`service_fee_settings`: do'kon (va filial bo'yicha almashtirish) uchun `service_charge_percent` (0–30) va "joy narxi"
(`place_fee_amount`, `per_person` yoki `per_table`), har biri o'z kanallari bilan (standart faqat `pos_hall`). Telegram
buyurtmalariga hech qachon qo'llanmaydi. Hisob:

```
xizmat haqi = yaxlitla( (taomlar − chegirma) × foiz / 100 )
joy narxi   = summa × mehmonlar soni (kamida 1)   yoki   summa (stol uchun)
```

Buyurtmada alohida ustunlar: `service_charge_amount`, `place_fee_amount`, `guests_count`, `hall_ref`, `table_ref`.
Telegram kartasida nol bo'lmasa alohida qator. Ega: `GET/PUT /owner/service-fees` (`settings.manage`).

## 10. Rasmlar

Mahsulot va kategoriya rasmlari `MENU_IMAGE_DISK` diskida (standart: `FILESYSTEM_DISK=s3` bo'lsa `s3`, aks holda
`public`). Yuklangan rasm WebP ga o'giriladi (eni ko'pi bilan 1200 px), navbatdagi ish kichik nusxalarni yaratadi
(`sm` 160 px, `md` 480 px). API: `image_url` (avvalgidek) va `image_thumb_urls: {sm, md}` (tayyor bo'lmaguncha `null`). Rasmni o'chirish —
`DELETE /owner/products/{id}/image` (`menu.manage`, P2-30): rasm va kichik nusxalari o'chadi, `image_url` `null`
bo'ladi; rasmsiz mahsulotda ham 200.
**Eslatma:** S3 uchun `league/flysystem-aws-s3-v3` paketi hali o'rnatilmagan; u qo'shilmaguncha rasmlar `public`
diskka yoziladi (log'da ogohlantirish).

## 11. Buyurtma qatori (snapshot)

`order_items`: `quantity` (kasr), `unit`, `unit_price` (kanal narxi), `modifiers` (`[{id, uuid, group_id, group_name,
name, price_delta, quantity, total}]`), `modifiers_total`, `total_price`, `batch_id`, `batch_quantity`. Menyu keyin
o'zgarsa yoki o'chirilsa ham tarix o'zgarmaydi.


## 12. Bugungi menyu, kompleks, keyingi partiya va hisobdan chiqarish (P6-15)

Hammasi `pos.module:<kalit>` ortida: modul filialning format profilida (yoki egasi qo'lda) yoqilmagan bo'lsa 403
`module_disabled`. Sozlash va yangi oqimlar yopiladi; to'lov, chek, smena va qaytarish hech qachon.

**Bugungi menyu** (`daily_menu`; stolovaya profilida o'zi yoqilgan). `daily_menus` (filial + biznes kun) va `daily_menu_items`
(taomlar). `products.daily_rotation` — "kunlik" taom: faqat shu kun menyusida bo'lsa sotiladi. Doimiy taomlar (non, choy)
har kuni sotiladi. Menyuga qo'yilgan taom o'zi kunlik bo'ladi.
- Qoida stop-list manbai sifatida ishlaydi: menyuda bo'lmagan kunlik taom `stopped.products` da ("Bugungi menyuda yo'q"),
  POS va Telegram checkout uni 422 bilan rad etadi, POS katalogida `unavailable_reason: stopped`.
- **Menyusi belgilanmagan kunda hamma narsa sotiladi** (unutilgan menyu kassani to'xtatmasin); `GET /pos/bootstrap`
  `daily_menu.set: false` deydi, panel ogohlantiradi. Modul o'chiq bo'lsa qoida ham o'chiq.
- Kun — filialning biznes kuni (Asia/Tashkent, kun boshlanishi 05:00). Menyu bugundan 30 kun oldinga qo'yiladi.
- `GET /owner/daily-menu?branch_id=&date=` (menu.view | menu.manage), `PUT /owner/daily-menu {branch_id, date?, product_ids[]}`,
  `POST /owner/daily-menu/copy {branch_id, date?, from_date?}` ("kechagisini nusxalash": `from_date` yo'q bo'lsa oxirgi oldingi
  menyu; o'chirilgan yoki faol bo'lmagan taomlar tushib qoladi), `PUT /owner/daily-menu/rotation {product_ids[]}` (do'kon bo'yicha
  qaysi taomlar kunlik). Yozish: `menu.manage` yoki `stoplist.manage` (kunni boshqaradigan menejer). Jurnal:
  `menu.daily_menu_saved`, `menu.daily_rotation_saved`. Menyular `menu.availability_changed` (`reason: daily_menu`) oladi.

**Kompleks** (`combo_meal`). Menyudagi oddiy mahsulot (narxi, MXIK kodi, kanal narxlari o'zining) bir qator va bir fiskal qator
bo'lib sotiladi; tarkibi `daily_sets` + `daily_set_items` (variant, miqdor, nom: "Birinchi", "Salat"; 12 tagacha). Sotilgan qator
tarkibni `order_items.set_parts` ga nusxalaydi (modifikatorlar kabi: keyin tahrirlash o'zgartirmaydi); oshxona chiptasida
(`GET /staff/kitchen/tickets`) va POS buyurtmasida `set_parts` ko'rinadi, POS katalogida mahsulotda `set_parts`, bootstrapda
`meal_sets`. Tarkibdagi taom stop-listda yoki menyuda bo'lmasa kompleks yopiladi. `GET/POST /owner/meal-sets`,
`PUT/DELETE /owner/meal-sets/{id}` (yozish: `menu.manage`). Jurnal: `menu.meal_set_saved/deleted`.
**Ochiq masala:** kompleks sotilganda tarkibdagi taomlarning partiya qoldig'i va ombor qoldig'i kamaymaydi; hosil
hisobotida ular `in_sets` da alohida ko'rsatiladi. Tarkib qismlari alohida sexga bo'linmaydi (qator kompleks mahsulotining sexiga ketadi).

**Keyingi partiya** (`batches`). `menu_batch_plans`: filial + taom (ixtiyoriy variant) + vaqt. Taom "tugadi" bo'lganda kassa, ofitsiant
telefoni va Mini App "keyingi partiya 12:30" ko'rsatadi; ro'yxatda `time` — do'kon vaqti bilan HH:MM. Reja qoldiq surmaydi.
Partiya ochilganda (`BatchService::open`) eng yaqin mos reja `started` bo'ladi; vaqti o'tgan reja mijozga ko'rinmaydi (egasi
ro'yxatida `overdue`). `GET /owner/batch-plans?branch_id=` (+ `modules`), `POST /owner/batch-plans {branch_id?, product_id,
product_variant_id?, time | planned_at, quantity?, label?}` (36 soatgacha oldinga), `DELETE /owner/batch-plans/{id}`.
Ma'lumot: `GET /catalog/availability` va POS katalog availability'sida `next_batches`, POS katalogida mahsulot/variantda
`next_batch`, bootstrapda `next_batches`. Jurnal: `menu.batch_planned`, `menu.batch_plan_canceled`.

**Hisobdan chiqarish** (`batch_writeoff`). `POST /owner/batches/{id}/write-off {quantity, reason, note?, close?}` (Idempotency-Key
tavsiya etiladi; `batches.manage`): partiya qoldig'idan sabab bilan ayiradi (qoldiqdan ko'p — 422; `other` sababi izoh talab
qiladi; `close` — qolganini chiqarib partiyani yopadi). Sabablar: `unsold`, `spoiled`, `staff_meal`, `dropped`, `expired`, `other`.
Yozuv `menu_batch_writeoffs` (kim, nega, qachon, biznes kun, smena), partiyada `written_off_quantity`. Terminaldan (PIN sessiya)
qilinsa terminalning ochiq smenasiga bog'lanadi va **X/Z hisobotda `batch_writeoffs`** blokida ko'rinadi (`count`, `items[]`:
taom, birlik, sabab, miqdor); panelda qilinsa faqat hisobotlarda. `GET /owner/batch-writeoffs?branch_id=&from=&to=`.
Jurnal: `menu.batch_written_off`.

**Hosil hisoboti** (`batches`). `GET /owner/batch-yield?branch_id=&from=&to=` (reports.view | batches.manage; 93 kungacha): har
partiya uchun `produced` (ochilgan + to'ldirilgan), `sold` (partiya olgan qatorlar; bekor qilingan/rad etilgan buyurtma va o'chirilgan qatorlarsiz),
`written_off`, `left`, `oversold`, `variance = produced − sold − written_off − left` (tushuntirilmagan farq: qayta sanash,
oshxona pishirgandan keyin o'chirilgan qator, partiya yopilgach bekor qilish) va foizlar (`sold_percent`, `written_off_percent`);
`days` — kun va taom bo'yicha yig'indi; `write_offs` — sabab bo'yicha; `in_sets` — kompleks ichida sotilgan porsiyalar. Miqdorlar partiya
birligida (porsiya, kg) son bilan.

Panel: "Bugungi menyu" (`/daily-menu`: menyu va Komplekslar), "Qozon" sahifasida (`/stoplist/batches`) "Keyingi partiya" va
"Hisobdan chiqarish", "Hosil hisoboti" (`/yield`: hosil va hisobdan chiqarilganlar), Smenalar X/Z da "Hisobdan chiqarilgan taomlar".
Mini App: sotuvda yo'q taom kartasida "Tugadi" ostida "Keyingi partiya 12:30".

Migratsiyalar: `2026_11_10_400100_create_menu_batch_plans_table`, `..400200_create_menu_batch_writeoffs_table` (+ `menu_batches.written_off_quantity`),
`..400300_create_daily_menus_tables` (+ `products.daily_rotation`), `..400400_create_daily_sets_tables` (+ `order_items.set_parts`).
Testlar: `tests/Feature/PosMenu` (DailyMenuTest, MealSetTest, BatchPlansTest, BatchWriteOffTest).
