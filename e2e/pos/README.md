# e2e/pos

POS stekining oxirigacha sinovi: **haqiqiy** Laravel (Postgres) va Reverb, kassa, ofitsiant va oshxona ilovalari kabi faqat
`pos_data` orqali boshqariladi (`PosData` + haqiqiy HTTP, `PosRealtime` + haqiqiy WebSocket).

## Ishga tushirish

```bash
scripts/pos-e2e.sh                 # bazani tozalaydi, ma'lumot yozadi, serverlarni ishga tushiradi, sinovni yurgizadi, to'xtatadi
scripts/pos-e2e.sh --keep          # serverlar ochiq qoladi (to'xtatish: scripts/pos-e2e.sh --stop)
scripts/pos-e2e.sh -- test/realtime_test.dart   # `--` dan keyingisi `dart test` ga beriladi
```

Kerak: PHP 8.3 (`pdo_pgsql`), `backend/` da `composer install`, ildizda `flutter pub get` (`dart` PATH'da) va ishlayotgan
Postgres. Baza: `POS_E2E_DB_DATABASE` (standart `rizqim_pos_e2e`; nomida `e2e` yoki `testing` bo'lishi shart — har safar
tozalanadi). Portlar bo'sh bo'lganini oladi (standart 18200 va 18280). Skript o'z config keshini `e2e/pos/.run` da saqlaydi:
dasturchining `backend/.env` yoki keshiga tegmaydi. Serverlar logi shu yerda (`.run/backend.log`, `.run/reverb.log`).

`dart test` ni to'g'ridan-to'g'ri yurgizmang: `POS_E2E_API_URL`, `POS_E2E_REVERB_URL`, `POS_E2E_REVERB_KEY`,
`POS_E2E_SEED_FILE` kerak, ularni skript beradi.

## Nima tekshiriladi

Ma'lumotni `backend/database/seeders/PosStackSeeder.php` yozadi: fiskal rejimdagi (sandbox) choyxona, "Asosiy zal" (Kabinka
1-10) va "Hovli" (tapchan, stol), xona tariflari (ish kuni/dam olish), minimal summa, zal bo'yicha xizmat haqi, choy-non,
sexlar, menyu (qozondan osh, shashlik, salatlar, modifikatorli lagmon), xodimlar (PIN bilan), to'rtta ulangan terminal,
Telegram mijozi va nasiya hisobi.

| Fayl | Senariy |
|---|---|
| `test/thursday_evening_test.dart` | Payshanba kechqurun: smena → bron, oldindan buyurtma va naqd avans (ADVANCE cheki) → "Keldi" → 10 xona (va hovlida tapchan, stol) ikki ofitsiant va kassir bilan bir vaqtda, har biri 4+ bosqich (yuborish, oshpaz "tayyor", ofitsiant "berildi") → choy-non → ofitsiant schyot so'raydi → schyotlar (bitta, teng 3 ga, taom bo'yicha kg ulushlari bilan) → to'lov (qaytimli naqd, karta, QR, avans, menejer tasdig'i bilan nasiya) → har SOTUV cheki aniq summalar bilan → buyurtmalar yopiladi, xonalar bo'sh → nasiya qaytarish (CREDIT cheki) → qaytarish (REFUND cheki) → X hisobot raqamlari → kupyura sanab Z (farq 0) |
| `test/telegram_order_test.dart` | Telegram buyurtmasi kassada qabul qilinadi, oshxonaga tushadi, tayyor bo'ladi; ikkinchisi menejer PIN'i bilan rad etiladi |
| `test/realtime_test.dart` | Har ofitsiant ikkinchisining stol o'zgarishini, kassa va oshxona ekrani ikkalasini 5 soniya ichida eshitadi |
| `test/conflicts_test.dart` | Ikki qurilma bir buyurtmani bir vaqtda o'zgartiradi: biri `version_conflict` oladi, qayta yuklab davom etadi |

Fayllar ketma-ket yuradi (`dart_test.yaml`: `concurrency: 1`): bitta baza, bitta kassa. Har fayl o'zi ochgan buyurtmalarni
yopadi yoki bekor qiladi.
