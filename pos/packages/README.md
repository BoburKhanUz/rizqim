# pos/packages

POS ilovalari (kassa, ofitsiant, KDS, mijoz ekrani) uchun umumiy Dart paketlari. Hammasi ildizdagi Dart workspace'da
(`pubspec.yaml` → `workspace:`), bitta `flutter pub get` yetadi.

| Paket | Nima | Flutter kerakmi |
|---|---|---|
| [`pos_core`](pos_core/README.md) | Pul tiyinda (`PosMoney`), miqdor (`PosQuantity`: kg, ulush), zal holatlari; chek (sotuv, qaytarish, bo'nak, kredit), schyot, sex markasi ("O'CHIRILDI" bilan), X/Z hisobot, navbat talonchasi va ularni 58/80 mm qog'ozga joylashtirish | yo'q (`dart test`) |
| [`pos_hardware`](pos_hardware/README.md) | Printerlar: ESC/POS (LAN 9100, USB Windows spooler orqali, Sunmi ichki printeri), kassa qutisi, matnni rasm sifatida chop etish, chop etish navbati (qayta urinish, printer holati), sinov sahifasi; testlar uchun soxta printer va LAN printer emulyatori | ha (`flutter test`) |
| [`pos_ui`](pos_ui/README.md) | Sensor ekran komponentlari: katta tugmalar, raqam va kg/ulush klaviaturasi, miqdor stepper, zal xaritasi, xona hisobi kartochkasi | ha (`flutter test`) |
| [`pos_data`](pos_data/README.md) | Backend bilan ishlash (P3-20): terminal va xodim sessiyasi, menejer tasdig'i, bootstrap, menyu (kesh), zal, buyurtma, schyot, smena, bron, nasiya repozitoriylari, `pos-branch` real-time; testlar uchun soxta backend | yo'q (`dart test`) |

Keyingi bosqichda: `pos_sync` — internetsiz ishlash (lokal navbat va sinxronlash), 3-bosqich 3-to'lqin (P3-31).

Fiskal chek (provayder bilan ishlash) qurilmada emas, backend'da (`app/Domain/Fiscal`): ilova faqat serverdan kelgan
fiskal belgi va QR havolasini chekka chiqaradi.

Uskunaga bog'liq kod faqat `pos_hardware` da (docs/POS_SPEC.md, 2.11–2.12).
