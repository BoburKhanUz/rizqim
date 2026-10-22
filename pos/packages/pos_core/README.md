# pos_core

POS'ning Flutter'siz umumiy mantig'i (`dart test`): pul, miqdor, chek hujjatlari va ularni qog'ozga joylashtirish.
Kassa, ofitsiant va KDS ilovalari shu paketdan foydalanadi; uskunaga bog'liq kod yo'q (u `pos_hardware` da).

## Nima bor

- **`PosMoney`** — so'm, tiyingacha aniq (ichida butun son, backend `App\Support\Money` bilan bir xil "half-up"):
  `PosMoney.parse('15000.50')`, `timesQuantity`, `percent('10')`, `vatIncluded('12')` (QQS narx ichida:
  `summa × stavka / (100 + stavka)`), `splitEqually(n)` (yig'indi aniq), `format()` → `25 000 so'm`.
  `rizqim_core.Money` (butun so'm, Mini App formati) bilan aralashtirmang.
- **`PosQuantity`**, **`PosUnit`** — uch kasr raqamgacha miqdor (`1.5 kg`, `0.75`), tez tugmalar
  `PosQuantity.presets` (0.5, 0.7, 0.75, 1, 2), `label(PosUnit.kg, unitLabel: 'kg (guruch)')`.
- **Hujjatlar:** `FiscalReceipt` (sotuv, qaytarish, bo'nak, kredit; MXIK, QQS, to'lov turlari, fiskal belgi va QR;
  bo'nak va kredit chekida QR yo'q), `PreCheck` (schyot: "Bu fiskal chek emas", xona puli, xizmat haqi, minimal
  summaga to'ldirish va chegirma alohida qatorda, avans, kishi boshiga, qayta chop etilsa "NUSXA"), `StationTicket`
  (sex markasi: xona, ofitsiant, bosqich, vaqt, modifikator, izoh, "ushlab turish" va tayyor bo'lish vaqti,
  "O'CHIRILDI"), `ShiftReport` (X/Z: to'lov turlari, avans va nasiya, kassa, kupyuralar, farq), `QueueSlip`.
- **Joylashuv:** `layoutFiscalReceipt`, `layoutPreCheck`, `layoutStationTicket`, `layoutShiftReport`,
  `layoutQueueSlip` → `PrintLayout` (58 mm — 32 belgi, 80 mm — 48 belgi). `PrintWriter` o'zingizning hujjatingiz
  uchun (so'zlarni bo'lish, "nom ...... summa" qatorlari). `renderPlainText(layout)` — oldindan ko'rish va snapshot.
- **Zal:** `FloorTableKind` (stol, xona, tapchan), `FloorTableStatus` (bo'sh, band, bron, schyot chiqdi, uzoq ochiq).

## Sinov

```bash
cd pos/packages/pos_core && dart test
UPDATE_GOLDENS=1 dart test   # joylashuv ataylab o'zgarsa: test/goldens/*.txt qayta yoziladi, farqini ko'rib chiqing
```

`test/goldens/*_58.txt` va `*_80.txt` — har hujjatning qog'ozdagi ko'rinishi (choyxona misoli: 12 kishilik xona,
1 000 000 avans, 4 480 000 jami, QQS 12%).
