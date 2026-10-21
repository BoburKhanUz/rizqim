# Yetkazib berish hududlari (zonalar)

Bu hujjat yetkazib berish hududlari qanday chizilishi, narx, minimal buyurtma va bepul yetkazib berish qanday
ishlashi, API va P1-32 buyurtma yaratishga uni qanday ulashi haqida (P1-24).

## Qisqacha

- Hudud — xaritadagi ko'pburchak. Do'konda bir nechta hudud bo'ladi; har birining o'z narxi, minimal buyurtmasi,
  bepul yetkazib berish chegarasi va taxminiy vaqti bor.
- Ko'pburchak GeoJSON ko'rinishida `delivery_zones.polygon` (`jsonb`) ustunida saqlanadi. PostGIS kerak emas: har
  do'konda hududlar kam, nuqta hududga tushganini PHP'da hisoblaymiz (`App\Domain\Delivery\Geometry`).
- Tez saralash uchun ko'pburchakning chegara to'rtburchagi (`min_lat`, `max_lat`, `min_lng`, `max_lng`) alohida
  ustunlarda va indeksda. Ular ko'pburchakdan avtomatik hisoblanadi, qo'lda yozilmaydi.
- Buyurtma yaratish (`POST /orders`, `CreateOrderAction`) yetkazib berish buyurtmasini hudud bo'yicha narxlaydi va
  filialni hududdan tanlaydi (P1-32, pastda "Buyurtma berishda hududlar"). Hududi yo'q do'konlar avvalgidek filial
  radiusi va do'konning yetkazib berish narxi bilan ishlaydi.

## Ega hududni qanday chizadi

Do'kon paneli → **Yetkazib berish hududlari** → **Create** (hamkor web panelida — xuddi shu qoidalar bilan API orqali,
pastda "API (ega: hududlarni boshqarish)"). Bo'lim faqat egaga ko'rinadi (`zones.manage`; uni boshqa
rolga berib bo'lmaydi, `docs/AUTH.md`). Menejer va boshqa xodimlar bu sahifani ochsa, 403 oladi.

1. **Nomi** (masalan, "Markaz"), kerak bo'lsa **Filial** va **Ustuvorlik**.
2. **Narx va shartlar**: yetkazib berish narxi, minimal buyurtma, bepul yetkazib berish chegarasi, taxminiy vaqt.
3. **Hudud**: xaritada chap tomondagi asbobdan ko'pburchak (yoki to'rtburchak) tanlang, nuqtalarni bosib chizing va
   birinchi nuqtani bosib yoping. Shakl chetlarini sudrab to'g'rilash, tahrirlash va o'chirish tugmalari ham shu yerda.
   Bir nechta shakl chizilsa, ular bitta hududga (MultiPolygon) birlashadi. Qizil nuqtalar — do'konning filiallari.
4. **GeoJSON** maydoni (xaritaning ostida): tayyor GeoJSON'ni shu yerga qo'yish yoki chizilganini nusxalash mumkin.
   Xarita yuklanmasa (internet yo'q), shu maydon yetarli. `Polygon`, `MultiPolygon`, `Feature` va bitta hududli
   `FeatureCollection` qabul qilinadi. Koordinatalar tartibi **[uzunlik, kenglik]** ([lng, lat]), masalan Toshkent
   markazi `[69.2797, 41.3111]`.
5. **Save**. Noto'g'ri GeoJSON bo'lsa, sabab o'zbekcha ko'rsatiladi.

Xarita Leaflet 1.9.4 va Leaflet.draw 1.0.4 (cdnjs, SRI bilan) orqali ishlaydi, plitkalar OpenStreetMap'dan.

### Ko'pburchak qoidalari

- Faqat `Polygon` yoki `MultiPolygon`; ko'pi bilan 20 ta ko'pburchak va jami **500 ta nuqta** (yopuvchi nuqta
  hisobga kirmaydi), ichki bo'shliqlar (teshiklar) bilan birga.
- Halqa yopiq bo'lishi kerak (oxirgi nuqta birinchisi bilan bir xil), kamida 3 ta har xil nuqta, maydoni nolga teng
  bo'lmasin (hammasi bir chiziqda bo'lsa rad etiladi).
- Kenglik -90..90, uzunlik -180..180. Teshik tashqi chegaradan chiqib ketmasligi kerak.
- O'zini kesib o'tuvchi ("kapalak") shakllar tekshirilmaydi: chizishda Leaflet.draw o'zi kesishishga yo'l qo'ymaydi,
  GeoJSON'ni qo'lda qo'ygan odam shaklni o'zi tekshiradi.
- Koordinatalar 6 xonagacha (taxminan 11 sm) yaxlitlanadi, ketma-ket takrorlangan nuqtalar olib tashlanadi.

## Hudud qanday tanlanadi

Mijoz nuqtasi (`lat`, `lng`) uchun `ZoneResolver::resolve($store, $lat, $lng)`:

1. Do'konning **faol** hududlaridan chegara to'rtburchagi nuqtani o'z ichiga olganlari SQL'da tanlanadi (indeks).
2. Ular tartiblanadi: **ustuvorlik** katta bo'lgani birinchi, teng bo'lsa **maydoni kichigi**, yana teng bo'lsa
   eskisi (`id`). Shuning uchun natija har doim bir xil.
3. Tartib bo'yicha birinchi haqiqatan nuqtani o'z ichiga olgani qaytadi (nuqta-ko'pburchak: nur usuli).
   - Hudud chegarasidagi nuqta hududga **kiradi**.
   - Teshik ichidagi nuqta hududga **kirmaydi** (teshik chegarasidagi nuqta kiradi). Teshik ichidagi nuqta keyingi
     hududga tushadi: masalan, kichik hudud "Markaz"da teshik bo'lsa, o'sha joyni katta "Shahar" hududi oladi.
   - MultiPolygon: istalgan bo'lagiga tushsa yetadi.

Hech qaysi hududga tushmasa — `null` (yetkazib berilmaydi).

### Filial

- Hududda **Filial** tanlangan bo'lsa, buyurtmani faqat o'sha filial qabul qiladi. U yopiq (faol emas, vaqtincha
  yopiq yoki ish vaqtidan tashqarida) bo'lsa, narx so'rovi `branch_closed` bilan rad etiladi.
- Bo'sh bo'lsa, `ZoneResolver::nearestOpenBranch($store, $lat, $lng, $at)`: manzilga eng yaqin **ochiq** filial.
  Ochiq filial: holati `active`, `is_active`, koordinatalari bor va ish vaqti `$at` ni qamraydi. Masofa — haversine
  (km). Teng masofada `id` kichigi. Hech bir filial ochiq bo'lmasa, `null`.
- Ish vaqti (`open_time`–`close_time`) do'konning vaqt mintaqasida (standart `Asia/Tashkent`) o'qiladi. Ochilish
  vaqti kiradi, yopilish vaqti kirmaydi. Vaqtlar yo'q yoki teng (`00:00`–`00:00`) bo'lsa — sutka bo'yi ochiq.
  Yopilish ochilishdan oldin bo'lsa (`18:00`–`02:00`) — tungi ish vaqti.

## Narx, minimal buyurtma, bepul yetkazib berish

Pul bilan ishlash `App\Support\Money` orqali (tiyingacha aniq), suzuvchi nuqtasiz.

- **Yetkazib berish narxi** (`delivery_price`): hududning narxi.
- **Minimal buyurtma** (`min_order_amount`): taomlar summasi (yetkazib berishsiz) shundan kam bo'lsa, buyurtma qabul
  qilinmasligi kerak. Narx so'rovi buni **rad etmaydi**, faqat `meets_min_order` bayrog'ini qaytaradi; buyurtmani
  `POST /orders` rad etadi (`below_minimum_order`).
- **Bepul yetkazib berish** (`free_delivery_from`, bo'sh bo'lishi mumkin): taomlar summasi shu miqdorga **teng yoki
  undan ko'p** bo'lsa, yetkazib berish narxi 0. Bo'sh bo'lsa, hech qachon bepul emas. `0` bo'lsa — doim bepul.
- **Taxminiy vaqt** (`eta_minutes`): daqiqada, ixtiyoriy.

## Kuryer turi

`users.courier_type`: `own` (restoranning o'z kuryeri, standart) yoki `fleet` (Rizqim kuryeri). Faqat kuryer rolidagi
xodimlar uchun ma'noga ega. `GET /api/v1/couriers` va `GET /api/v1/owner/employees` javoblarida ko'rinadi; do'kon
panelidagi xodimlar ro'yxatida "Kuryer turi" ustuni bor. O'zgartirish: Superadmin panelidagi foydalanuvchi formasi
yoki do'kon panelida superadmin sifatida; ega `fleet` bera olmaydi (Rizqim kuryerlarini platforma biriktiradi).
Turga qarab buyurtma tarqatish hozircha yo'q (4-bosqich).

## API (ochiq, faqat o'qish)

Token kerak emas. Pul qiymatlari ikki xonali satr (`"12000.00"`).

### `GET /api/v1/stores/{store}/zones`

`{store}` — do'konning uuid'i (yoki raqamli id). Faqat faol hududlar, ustuvorligi katta birinchi; ichki maydonlarsiz
(do'kon id, chegara to'rtburchagi, maydon, `active`, filial yo'q).

```json
{"success": true, "message": "", "data": [
  {"id": 7, "name": "Markaz", "polygon": {"type": "Polygon", "coordinates": [[[69.26, 41.3], ...]]},
   "delivery_price": "12000.00", "min_order_amount": "30000.00", "free_delivery_from": "150000.00",
   "eta_minutes": 40, "priority": 2}
]}
```

Cheklov: IP bo'yicha daqiqasiga 120 ta (`delivery-zones`).

### `GET /api/v1/delivery/quote?store=&lat=&lng=&subtotal=&branch=`

`store` (uuid yoki id), `lat`, `lng` majburiy; `subtotal` (taomlar summasi, ixtiyoriy) bepul yetkazib berish va
minimal buyurtmani hisoblash uchun. `branch` (filial id, ixtiyoriy) — mijoz filialni o'zi tanlagan bo'lsa, narx
buyurtmadagidek shu filial bo'yicha hisoblanadi. Cheklov: IP bo'yicha daqiqasiga 60 ta (`delivery-quote`).

200:

```json
{"success": true, "message": "", "data": {
  "zone": {"id": 7, "name": "Markaz", "delivery_price": "12000.00", "min_order_amount": "30000.00",
           "free_delivery_from": "150000.00", "eta_minutes": 40},
  "branch": {"id": 3, "name": "Markaz filial", "distance_km": 0.31},
  "delivery_price": "12000.00",
  "min_order_amount": "30000.00",
  "free_delivery_applies": false,
  "meets_min_order": true,
  "eta": {"minutes": 40}
}}
```

- `delivery_price` — mijoz to'laydigan yakuniy narx (bepul bo'lsa `"0.00"`); `zone.delivery_price` — hududning
  asl narxi.
- `free_delivery_applies` — `subtotal` berilgan va `free_delivery_from` ga yetgan bo'lsa `true`.
- `meets_min_order` — `subtotal` berilmasa `null`.
- `eta` — hududda vaqt yo'q bo'lsa `null`.

422 (`success: false`, `data.reason` mashina uchun kod, `message` o'zbekcha):

| `data.reason` | Qachon |
|---|---|
| `outside_zone` | do'konning hududlari bor, nuqta birortasiga ham tushmadi ("... hududdan tashqarida") |
| `zones_not_configured` | do'konda faol hudud yo'q (ega hali chizmagan) |
| `branch_closed` | hudud topildi, lekin unga xizmat qiladigan filial hozir yopiq |
| `branch_outside_zone` | `branch` berilgan, lekin u nuqtani qamraydigan birorta hududga xizmat qilmaydi (yoki boshqa do'konniki) |

Boshqa xatolar: noto'g'ri `lat`/`lng`/`subtotal` — 422 (Laravel `errors` bilan), noma'lum yoki ko'rinmaydigan
do'kon — 404, cheklov oshsa — 429.

## API (ega: hududlarni boshqarish, P2-12)

Hamkor web paneli hududlarni shu endpointlar bilan chizadi va tahrirlaydi. Faqat ega (`zones.manage`), terminal PIN
sessiyasidan emas; boshqa do'kon hududi — 404. Qoidalar panel tahrirlovchisi bilan bir xil: yuqoridagi "Ko'pburchak
qoidalari" (`Geometry::normalize`, `ValidGeoJsonPolygon`), narxlar 0–99 999 999 (ko'pi bilan 2 kasr), ustuvorlik
−1000..1000, taxminiy vaqt 1–600 daqiqa, filial — do'konning **faol** filiali yoki bo'sh. Chegaralar bitta joyda:
`DeliveryZone::NAME_MAX`, `AMOUNT_MAX`, `PRIORITY_*`, `ETA_*` (panel ham shularni ishlatadi).

| Endpoint | Nima qiladi |
|---|---|
| `GET /api/v1/owner/delivery-zones?active=` | do'konning barcha hududlari (faol bo'lmaganlari ham), `ZoneResolver` tartibida |
| `GET /api/v1/owner/delivery-zones/{id}` | bitta hudud |
| `POST /api/v1/owner/delivery-zones` | yaratish: `name`, `polygon` majburiy; `branch_id`, `priority` (0), `active` (true), `delivery_price` (0), `min_order_amount` (0), `free_delivery_from`, `eta_minutes` |
| `PATCH /api/v1/owner/delivery-zones/{id}` | istalgan maydonlar |
| `DELETE /api/v1/owner/delivery-zones/{id}` | o'chirish (eski buyurtmalardagi nusxa qoladi) |

Javobda ochiq API'dagi maydonlardan tashqari `branch_id`, `branch {id, name}`, `active`, `bbox {min_lat, max_lat,
min_lng, max_lng}` va `area_km2` bor; pul — ikki xonali satr. `polygon` obyekt yoki JSON satr bo'lishi mumkin
(`Feature`, bitta hududli `FeatureCollection` ham), saqlashda tozalanadi va chegara to'rtburchagi qayta hisoblanadi.
Noto'g'ri GeoJSON — 422, `errors.polygon` da o'zbekcha sabab.

**Jurnal.** Hudud yaratilsa, o'zgarsa yoki o'chirilsa (panel yoki API — farqi yo'q) harakatlar jurnaliga
`delivery_zone.created`, `delivery_zone.updated`, `delivery_zone.deleted` yoziladi: kim, qachon, narx, minimal summa,
bepul chegara, filial, ustuvorlik, faollikning oldingi va yangi qiymati; shakl o'zgarsa `polygon_changed: true`
(ko'pburchakning o'zi jurnalga yozilmaydi).

**Filiallar va ish vaqti** ("Filial" bo'limidagi qoidalar) web panelda `GET/POST /api/v1/owner/branches`,
`PATCH/DELETE /api/v1/owner/branches/{id}` orqali o'zgartiriladi: `open_time`/`close_time` (`"HH:MM"`, ikkalasi birga),
`status` (`active`, `inactive`, `temporarily_closed`), `is_active`; javobdagi `is_open_now` — `ZoneResolver::isBranchOpen`
hozir nima deydi ([API.md](API.md), 12-bo'lim).

## Buyurtma berishda hududlar (P1-32)

`POST /api/v1/orders` da `type: delivery` bo'lsa va do'konda kamida bitta **faol** hudud bo'lsa:

1. Filial **taomlarni tekshirishdan oldin** tanlanadi (taom bor-yo'qligi, stop-list va qozon qoldig'i filialga
   bog'liq). Buning uchun narx so'rovidagi xuddi o'sha `DeliveryQuoter::quote()` chaqiriladi, shuning uchun narx
   so'rovi va buyurtma doim bir xil hudud, filial va narxni beradi.
   - `branch_id` yuborilmasa — avtomatik: hududda filial ko'rsatilgan bo'lsa o'sha, bo'lmasa manzilga eng yaqin
     ochiq filial ("Filial" bo'limi).
   - `branch_id` yuborilsa — o'sha filial nuqtani qamraydigan hududga xizmat qilishi kerak: shu filialga bog'langan
     yoki filiali ko'rsatilmagan hududlar orasidan odatiy tartib (ustuvorlik, maydon, `id`) bo'yicha birinchisi
     olinadi. Bunday hudud yo'q bo'lsa yoki filial boshqa do'konniki bo'lsa — `branch_outside_zone`. Filial yopiq
     bo'lsa — `branch_closed`.
2. Taomlar summasi (chegirmagacha, yetkazib berishsiz) hisoblangach, **o'sha hudud** shu summa bilan narxlanadi
   (hudud bazadan qayta o'qilmaydi): yetkazib berish narxi hududniki (bepul chegaraga yetsa 0), minimal buyurtma
   hududniki. Do'konning umumiy `min_order_amount` va `payment_settings.delivery_fee` bu buyurtmaga **qo'llanmaydi**.
   Mijoz yuborgan narx (`delivery_price`, `delivery_fee` va h.k.) o'qilmaydi.
3. Yetkazib berish narxi buyurtma summasiga, to'lovga va komissiya asosiga avvalgi yetkazib berish narxi qanday
   kirgan bo'lsa, xuddi shunday kiradi (`orders.delivery_price` → `total_price` → `commission_amount`, ledger).
   Komissiya hisobi o'zgarmagan.
4. Buyurtmaga hududning o'sha paytdagi holati yoziladi: `delivery_zone_id`, `delivery_zone_name`,
   `delivery_zone_price` (hududning asl narxi), `delivery_min_order_amount`. Ega keyin hududni o'zgartirsa yoki
   o'chirsa, eski buyurtma o'zgarmaydi (o'chirilsa faqat `delivery_zone_id` bo'shaydi). Narx, minimal buyurtma
   tekshiruvi va yozilgan nusxa bitta o'qilgan hududdan olinadi, shuning uchun buyurtma paytida hudud tahrirlansa ham
   ular bir-biriga zid bo'lmaydi.

Rad etish — 422, narx so'rovidagi shakl (`success: false`, `message` o'zbekcha, `data.reason`):

| `data.reason` | Qachon | Qo'shimcha |
|---|---|---|
| `outside_zone` | nuqta do'konning birorta faol hududiga tushmadi (filial radiusi qamrasa ham) | — |
| `branch_outside_zone` | tanlangan filial bu nuqtaga yetkazib bermaydi | — |
| `branch_closed` | hududga xizmat qiladigan (yoki tanlangan) filial hozir yopiq | — |
| `below_minimum_order` | taomlar summasi hudud minimalidan kam ("Bu hududda eng kam buyurtma summasi: 30 000 UZS.") | `data.min_order_amount`, `data.subtotal` |

Koordinatalarsiz yetkazib berish buyurtmasi (faqat ichki chaqiruvda bo'lishi mumkin, API ularni talab qiladi) —
oddiy 422 validatsiya xatosi.

**Hududi yo'q do'konlar** (yoki hamma hududi o'chirilgan): o'zgarishsiz eski qoida — manzil radiusiga tushadigan eng
yaqin faol filial, narx `payment_settings.delivery_fee`, do'konning `min_order_amount`; radiusdan tashqarida 400
"... yetkazib berish hududidan tashqarida joylashgan.". `branch_id` yetkazib berishda e'tiborga olinmaydi. Ega
birinchi hududni chizgan zahoti do'kon hudud rejimiga o'tadi.

**Olib ketish (`pickup`) va zal (POS) buyurtmalari** hududlardan foydalanmaydi: filial, do'kon minimal summasi va
yetkazib berish narxi (0) avvalgidek.

Mini App va mobil ilovalar savatda `GET /delivery/quote` (summa bilan, filial tanlangan bo'lsa `branch` bilan) narxni
ko'rsatadi va xaritada `GET /stores/{store}/zones` hududlarini chizadi; buyurtmadagi yakuniy narx baribir serverda
qayta hisoblanadi.

## Kodda

| Nima | Qayerda |
|---|---|
| Geometriya (tekshirish, chegara, maydon, nuqta, haversine) | `App\Domain\Delivery\Geometry` |
| Hudud tanlash, eng yaqin ochiq filial, ish vaqti | `App\Domain\Delivery\ZoneResolver` |
| Narx hisoblash | `App\Domain\Delivery\DeliveryQuoter`, `DeliveryQuote` |
| Buyurtmaga ulash (P1-32) | `App\Actions\Order\CreateOrderAction::zoneQuote()`, rad etish `DeliveryUnavailableException` |
| Model | `App\Models\DeliveryZone` (saqlashda chegara va maydon hisoblanadi) |
| Tekshiruv qoidasi | `App\Rules\ValidGeoJsonPolygon` |
| Panel | `App\Filament\Resources\DeliveryZones`, `resources/views/filament/forms/components/zone-map-editor.blade.php` |
| API | `App\Http\Controllers\Api\V1\Delivery\DeliveryZoneController` (ochiq), `App\Http\Controllers\Api\V1\Management\DeliveryZoneManageController` (ega, P2-12) |
| Migratsiyalar | `2026_10_22_400000_create_delivery_zones_table`, `2026_10_22_400100_add_courier_type_to_users_table`, `2026_10_23_100000_add_delivery_zone_snapshot_to_orders_table` |
| Testlar | `DeliveryZoneResolverTest`, `DeliveryQuoteApiTest`, `DeliveryZoneCheckoutTest` (P1-32), `ManagementZonesApiTest`, `ManagementBranchesApiTest` (P2-12) |
