# pos/apps/customer_display

Mijoz ekrani (P6-14): mijozga qaragan ekran. Ikki rejim, xodimsiz (PIN yo'q):

1. **Kassaning ikkinchi ekrani** (`customer_display` moduli): joriy chek (taom, miqdor yoki og'irlik, modifikatorlar), jami,
   to'lov usuli va **to'lov QR'i**, to'langach **"Rahmat!"** (qaytim va navbat raqami bilan).
2. **"Tayyorlanmoqda / Tayyor" raqamlar taxtasi** (`queue_calling` moduli): televizor yoki planshet, yirik raqamlar, tayyor
   raqamga ovoz va "17 raqam tayyor!", qayta chaqirish, sig'maydigan ro'yxat o'zi suriladi, qorong'i mavzu.

Android planshet, Windows kompyuter va Linux'da ishlaydi (qurilma turi `display`: **bepul**, tarifdagi qurilmalar soniga kirmaydi).
Hujjatlar: [docs/POS_SPEC.md](../../../docs/POS_SPEC.md) 2.14a, [docs/API.md](../../../docs/API.md) "Mijoz ekrani (P6-14)",
egasi uchun [docs/guides/mijoz-ekrani.md](../../../docs/guides/mijoz-ekrani.md).

## Qanday ishlaydi

- **Ulash:** egasi panelda **Sozlamalar → Qurilmalar → Ulash kodini yaratish** (turi **Mijoz ekrani**, filial majburiy, ixtiyoriy
  kassa) bilan 8 belgili kod oladi; ekranda kiritiladi. Terminal tokeni qurilmaning maxfiy omborida (Android Keystore, Windows
  Credential Locker, Linux libsecret) saqlanadi; panelda qurilma o'chirilsa, ilova o'zi ulash ekraniga qaytadi.
- **Ma'lumot:** hamma so'rov terminalning o'z tokeni bilan (`pos_data` `PosDisplayRepository`): `GET /pos/display/state`
  (filial, modullar, joriy chek) va `GET /pos/display/queue` (taxta).
- **Kassa → ekran:** kassa chekni `DisplayFeed.publish(PosDisplayCart)` (`pos_data`) bilan yuboradi (`POST /pos/display/cart`);
  server `pos-branch.{id}` kanaliga `display.cart` ishorasini yuboradi, ekran `state` ni qayta o'qiydi. Taom nomi va narx
  xodimlar kanaliga chiqmaydi. Sunmi D2 kabi mahalliy ikkinchi ekran ulanishi keyingi bosqichga qoldi (real-time yo'l hamma
  qurilmada qo'shimcha sozlashsiz ishlaydi).
- **Tezlik:** hodisa kelishi bilan ekran kutmasdan qayta o'qiydi (2 soniyadan tez); tayyor raqam ovozi hodisaning o'zidan.
  Real-time bo'lmasa har 5 soniyada so'raydi (jonli bo'lsa ham 30 soniyada). Aloqa uzilsa oxirgi holat turadi, burchakda "Aloqa yo'q".
- **Rejim:** ekranning pastki chap burchagini 2 soniya bosib turing: *Avto* (chek bor paytda chek, qolgan paytda raqamlar),
  *Ikkinchi ekran*, *Raqamlar taxtasi*; ovoz, mavzu (standart qorong'i), shrift (100/125/150%).
- **QR:** to'lov havolasi ekranda QR qilib chiziladi; kodlagich (`lib/src/qr/qr_encoder.dart`, bayt rejimi, versiya 1–10, daraja
  M yoki L) tashqi paketsiz yozilgan va xalqaro kutubxona bilan modulma-modul solishtirilgan matritsalar bilan tekshirilgan.

## Tayyorlash

Flutter 3.47.5 kerak. O'rnatish va workspace haqida: [`packages/dart/README.md`](../../../packages/dart/README.md).

```bash
flutter pub get          # repozitoriy ildizida (butun workspace uchun bir marta)
```

## Sozlash (`--dart-define`)

| Kalit | Nima | Standart |
|---|---|---|
| `RIZQIM_API_URL` | Backend manzili, `/api` bilan tugaydi | `http://localhost/api` |
| `RIZQIM_REVERB_KEY` | `REVERB_APP_KEY`. Bo'sh bo'lsa real-time o'chiq, faqat har 5 soniyada so'raladi | bo'sh |
| `RIZQIM_REVERB_URL` | Reverb boshqa xost/portda bo'lsa | backend manzilidan |
| `RIZQIM_REVERB_ORIGIN` | WebSocket'ga yuboriladigan `Origin` (Reverb `REVERB_ALLOWED_ORIGINS`) | backend manzilining origin'i |

## Testlar

```bash
cd pos/apps/customer_display
flutter analyze
flutter test
```

Testlar backend'siz ishlaydi: soxta server (`pos_data/testing.dart` `FakePosAdapter` ustida `test/support/harness.dart`) va soxta
Reverb (`FakePosRealtimeHub`). Qamrov: ulash va o'chirilgan terminal; chek (taom, og'irlik, modifikator, chegirma, to'lov QR'i,
naqd va qaytim, "Rahmat", "Keyingi mijoz", eskirgan chek, bir kassaga bog'lash, polling, aloqa uzilishi, uzun chek va telefon);
taxta (tayyor raqam: ovoz bir marta, yozuv, qayta chaqirish, ovozsiz, polling, qaytib tayyor bo'lish, avto-suriladigan ro'yxat,
avto rejim); sozlamalar; QR kodlagich (52 ta etalon matritsa).

Suratlar (ko'z bilan ko'rish uchun; Roboto va Material ikonkalar bilan):

```bash
DISPLAY_SHOTS_DIR=/tmp/shots flutter test test/screenshots_test.dart
```

QR etalonlarini qayta yaratish: `python3 test/fixtures/gen_qr_fixtures.py` (`pip install qrcode`).

## Ishga tushirish va yig'ish

```bash
cd pos/apps/customer_display
flutter run -d linux --dart-define=RIZQIM_API_URL=http://127.0.0.1:8000/api --dart-define=RIZQIM_REVERB_KEY=...
flutter build apk --release --dart-define=...        # Android (uz.rizqim.customer_display)
flutter build windows --release --dart-define=...    # Windows
```

Android'da ekran o'chib qolmaydi (`FLAG_KEEP_SCREEN_ON`). Windows/Linux'da televizorga ulangan kompyuterda ekran saqlovchi va
uyqu rejimini tizim sozlamalarida o'chiring. Ovoz Linux'da GStreamer orqali (`gstreamer1.0-plugins-base`/`-good`).

## Tuzilishi

| Fayl | Nima |
|---|---|
| `lib/src/app.dart` | `DisplayApp`, ulash → ekran almashinuvi (`AuthGate`, `TerminalHome`), real-time terminal darajasida |
| `lib/src/controller/display_controller.dart` | holat: chek (hodisa va so'rov), taxta, tayyor raqamlar e'loni va ovozi, aloqa, "Rahmat" muddati |
| `lib/src/screens/` | ulash, asosiy ekran (rejimga qarab), sozlamalar oynasi |
| `lib/src/cart/cart_view.dart` | chek, to'lov, "Rahmat" |
| `lib/src/board/board_view.dart` | "Tayyorlanmoqda / Tayyor" taxtasi |
| `lib/src/qr/` | QR kodlagich va chizuvchi |
| `lib/src/widgets/auto_scroller.dart` | sig'maydigan ro'yxatni o'zi suradi |
| `lib/src/settings/`, `lib/src/sound/`, `assets/sounds/ready.wav` | sozlamalar va tayyor raqam ovozi |

Umumiy qismlar: `pos_data` (`PosDisplayRepository`, `DisplayFeed`, `PosDisplayCartChanged`, `PosRealtime`), `rizqim_api`
(terminal tokeni, Reverb), `rizqim_ui` (mavzu).

## Eslatma

* Dart paketi nomi `rizqim_customer_display`; platforma papkalari (`android/`, `linux/`, `windows/`) `flutter create` bilan
  yaratilgan, identifikator `uz.rizqim.customer_display`.
* iOS/macOS/web bu ilova uchun yoqilmagan.
