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

Ma'lumotni `backend/database/seeders/PosStackSeeder.php` yozadi (P6-19: qo'shimcha ravishda alohida "Bodomzor osh markazi"
do'koni — ikki kassa, oshxona ekrani, raqamlar taxtasi, o'z xodimlari va menyusi, seed faylida `osh`): fiskal rejimdagi (sandbox) choyxona, "Asosiy zal" (Kabinka
1-10) va "Hovli" (tapchan, stol), xona tariflari (ish kuni/dam olish), minimal summa, zal bo'yicha xizmat haqi, choy-non,
sexlar, menyu (qozondan osh, shashlik, salatlar, modifikatorli lagmon), xodimlar (PIN bilan; egasining tokeni ega API'si
uchun), to'rtta ulangan terminal, Telegram mijozi va nasiya hisobi.

| Fayl | Senariy |
|---|---|
| `test/thursday_evening_test.dart` | Payshanba kechqurun: smena → bron, oldindan buyurtma va naqd avans (ADVANCE cheki) → "Keldi" → 10 xona (va hovlida tapchan, stol) ikki ofitsiant va kassir bilan bir vaqtda, har biri 4+ bosqich (yuborish, oshpaz "tayyor", ofitsiant "berildi") → choy-non → ofitsiant schyot so'raydi → schyotlar (bitta, teng 3 ga, taom bo'yicha kg ulushlari bilan) → to'lov (qaytimli naqd, karta, QR, avans, menejer tasdig'i bilan nasiya) → har SOTUV cheki aniq summalar bilan → buyurtmalar yopiladi, xonalar bo'sh → nasiya qaytarish (CREDIT cheki) → qaytarish (REFUND cheki) → X hisobot raqamlari → kupyura sanab Z (farq 0) |
| `test/telegram_order_test.dart` | Telegram buyurtmasi kassada qabul qilinadi, oshxonaga tushadi, tayyor bo'ladi; ikkinchisi menejer PIN'i bilan rad etiladi |
| `test/realtime_test.dart` | Har ofitsiant ikkinchisining stol o'zgarishini, kassa va oshxona ekrani ikkalasini 5 soniya ichida eshitadi |
| `test/booking_confirmation_test.dart` | Bron tasdig'i (P3-38): ish kunidagi xona broni, oldindan buyurtma va avans → tasdiq matnida xona puli (tarifdagi ish kuni narxi), oldindan buyurtma va bo'nak → Telegrami yo'q raqamga, SMS o'chiq — `no_channel` → ega SMS'ni yoqadi (ega API'si) → SMS bilan ketadi (stekda SMS faqat logga yoziladi). Oxirida SMS o'chiriladi, bron bekor qilinib avans qaytariladi |
| `test/display_test.dart` | Mijoz ekrani (P6-14, qurilma turi `display`): egasi kod yaratadi (kassani tanlab), ekran o'z tokeni bilan ulanadi, xodimsiz (PIN yo'q, `terminal/staff` 403), tarifda bepul; kassaning cheki (`DisplayFeed`: taom, to'lov QR'i, "Rahmat", "Keyingi mijoz") filial kanalida `display.cart` ishorasi bilan **2 soniyadan tez** ekranga yetadi (taom nomi va narx kanalda yo'q); navbatli buyurtma: taxtada "Tayyorlanmoqda", KDS chiptasida `queue_number`, oshpaz "tayyor" bosadi, qayta chaqirish, "Berildi"; "Raqam N tayyor" (yig'ish) tugmasi. Format modullarini egasi yoqadi va oxirida qaytaradi. Oxirida kechikishlar: median ~90 ms, eng ko'pi ~260 ms |
| `test/osh_markazi_lunch_test.dart` | **Osh markazi tushligi** (P6-19, seederdagi alohida do'kon `osh`): egasi formatni `osh_markazi` qiladi (oshxona ekrani, mijoz ekrani, bugungi menyu modullari bilan), bank terminali "faqat terminal" va farq chegarasi, bugungi menyu (menyuda yo'q kunlik taom rad etiladi) → oshpaz ikki qozonni rejalashtiradi va birinchisini ochadi → ikki kassa bir vaqtda 60 ta peshtaxta buyurtmasi: har biri o'z blokidan raqam (1–100 va 101–200), avval to'lov (naqd, karta RRN va oxirgi 4 raqam bilan), talon, KDS chiptasida raqam, oshpaz "tayyor" → raqamlar taxtasi "Tayyor" → qayta chaqirish → "Berildi"; ikkinchi kassa 6 ta buyurtmani internetsiz sotadi va sinxronlaydi (raqamlar saqlanadi) → birinchi qozon tugaydi: taom "tugadi" bilan rad etiladi, "keyingi partiya HH:MM", oshpaz ikkinchi qozonni ochadi → qolgani kassir tomonidan menejer PIN'i bilan hisobdan chiqariladi → ikki kassada Z bank terminali jami bilan (ikkinchisida chegaradan katta farq) → hosil hisoboti: tayyorlangan = sotilgan + chiqarilgan + qolgan, farq 0. Dublikat raqam yo'q. Oxirida: taxtada "tayyorlanmoqda → tayyor" kechikishi |
| `test/conflicts_test.dart` | Ikki qurilma bir buyurtmani bir vaqtda o'zgartiradi: biri `version_conflict` oladi, qayta yuklab davom etadi |

Fayllar ketma-ket yuradi (`dart_test.yaml`: `concurrency: 1`): bitta baza, bitta kassa. Har fayl o'zi ochgan buyurtmalarni
yopadi yoki bekor qiladi.
