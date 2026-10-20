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
- Hozircha buyurtma yaratish (`CreateOrderAction`) hududlardan foydalanmaydi: u hamon filial radiusi va oddiy yetkazib
  berish narxi bilan ishlaydi. Hududlarni buyurtmaga ulash — P1-32.

## Ega hududni qanday chizadi

Do'kon paneli → **Yetkazib berish hududlari** → **Create**. Bo'lim faqat egaga ko'rinadi (`zones.manage`; uni boshqa
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
  qilinmasligi kerak. Narx so'rovi buni **rad etmaydi**, faqat `meets_min_order` bayrog'ini qaytaradi; rad etish
  checkout (P1-32) ishi.
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

### `GET /api/v1/delivery/quote?store=&lat=&lng=&subtotal=`

`store` (uuid yoki id), `lat`, `lng` majburiy; `subtotal` (taomlar summasi, ixtiyoriy) bepul yetkazib berish va
minimal buyurtmani hisoblash uchun. Cheklov: IP bo'yicha daqiqasiga 60 ta (`delivery-quote`).

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

Boshqa xatolar: noto'g'ri `lat`/`lng`/`subtotal` — 422 (Laravel `errors` bilan), noma'lum yoki ko'rinmaydigan
do'kon — 404, cheklov oshsa — 429.

## P1-32 buyurtma yaratishga qanday ulaydi

Hozirgi `CreateOrderAction` filialni radius bo'yicha tanlaydi va yetkazib berish narxini do'kon sozlamasidan oladi.
P1-32 shu qismni `App\Domain\Delivery\DeliveryQuoter::quote($store, $lat, $lng, $subtotal)` bilan almashtiradi:

1. `delivery` turidagi buyurtmada manzil koordinatalari va taomlar summasi bilan `quote()` chaqiriladi.
2. `DeliveryUnavailableException` (`reason`, `getMessage()`) 422 xatoga aylanadi: `outside_zone` hozirgi
   "yetkazib berish hududidan tashqarida" xatosining o'rnini bosadi.
3. Buyurtma filiali — `quote->branch`; yetkazib berish narxi — `quote->deliveryPrice` (Money); `meetsMinOrder === false`
   bo'lsa buyurtma rad etiladi. Narx buyurtmada **server tomonda qayta hisoblanadi**, mijoz yuborgan qiymatga
   ishonilmaydi.
4. Mini App va mobil ilovalar savatda `GET /delivery/quote` bilan narxni ko'rsatadi va xaritada
   `GET /stores/{store}/zones` hududlarini chizadi.
5. Hududlari hali chizilmagan do'konlar (`zones_not_configured`) uchun o'tish davrida eski radius qoidasiga qaytish
   yoki hudud chizishni majburiy qilish — P1-32 qarori.
6. Narx va hudud buyurtma yaratilgan paytdagi holat bilan buyurtmaga yoziladi (keyin hudud o'zgarsa, eski buyurtma
   o'zgarmaydi).

## Kodda

| Nima | Qayerda |
|---|---|
| Geometriya (tekshirish, chegara, maydon, nuqta, haversine) | `App\Domain\Delivery\Geometry` |
| Hudud tanlash, eng yaqin ochiq filial, ish vaqti | `App\Domain\Delivery\ZoneResolver` |
| Narx hisoblash | `App\Domain\Delivery\DeliveryQuoter`, `DeliveryQuote` |
| Model | `App\Models\DeliveryZone` (saqlashda chegara va maydon hisoblanadi) |
| Tekshiruv qoidasi | `App\Rules\ValidGeoJsonPolygon` |
| Panel | `App\Filament\Resources\DeliveryZones`, `resources/views/filament/forms/components/zone-map-editor.blade.php` |
| API | `App\Http\Controllers\Api\V1\Delivery\DeliveryZoneController` |
| Migratsiyalar | `2026_10_22_400000_create_delivery_zones_table`, `2026_10_22_400100_add_courier_type_to_users_table` |
