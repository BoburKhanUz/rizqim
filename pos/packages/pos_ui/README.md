# pos_ui

Kassa va ofitsiant ilovalari uchun sensor ekran komponentlari (`rizqim_ui` mavzusi va tokenlari ustida, yorug' va
qorong'i). Bosiladigan hamma narsa kamida 44 px, asosiy tugmalar 52–72 px; holat faqat rang bilan emas, ikonka va
yorliq bilan ham ko'rsatiladi.

| Komponent | Nima |
|---|---|
| `PosTileButton` | Katta tugma (taom, kategoriya): narx, kategoriya rangi, burchak belgisi ("Qoldi: 12"), "Tugadi" |
| `PosNumericKeypad`, `PosKeypadController`, `PosKey` | Raqam klaviaturasi (`000`, nuqta, uzoq bosish — tozalash, jismoniy klaviatura) |
| `PosAmountEntry` | Summa kiritish (to'lov, kirim/chiqim): butun so'm, tez summalar |
| `PosQuantityKeypad` | Kg va ulush: 0.5, 0.7, 0.75, 1, 2 tez tugmalari, kasr terish, birlik yorlig'i, qadam/min/max tekshiruvi |
| `PosQuantityStepper` | − miqdor +, eng kichigida "olib tashlash" |
| `FloorMap`, `FloorTile`, `FloorStatusBadge` | Zal xaritasi: xona, tapchan, stol o'z joyida (`layout` x/y/w/h), holat, ochiq soatlar, mehmonlar, bron vaqti, summa |
| `RoomSummaryCard` | Xona hisobi: holat, mehmonlar, ofitsiant, vaqt, bosqichlar, jami, avans, minimal summagacha qancha |
| `PosLockChip`, `PosPlanButton`, `PosPlanNoticeBar` | Tarif qulflari (P4-21): "Asosiy tarifda bor" yorlig'i, yopiq amal tugmasi (o'chirilgan + sababi), to'lanmagan hisob-faktura lentasi. Faqat yangi yozuv uchun; to'lov, chek, smena va qaytarish hech qachon o'ralmaydi |

PIN klaviaturasi va ekran qulfi — `rizqim_ui` dagi `RzPinEntry` / `RzPinPad` (bu yerda takrorlanmaydi).

## Sinov

```bash
cd pos/packages/pos_ui && flutter test
flutter test --update-goldens test/goldens_test.dart   # ko'rinish ataylab o'zgarsa; test/goldens/*.png ni ko'rib chiqing
```

Oltin suratlarda matn test shriftida (kvadratlar): ular joylashuv, rang va holatlarni tekshiradi.
