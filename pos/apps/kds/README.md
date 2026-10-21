# pos/apps/kds

Oshxona ekrani (KDS): barcha kanallardan (Telegram, ilova, kassa, zal) kelgan buyurtmalar bitta doskada. Android planshet,
Windows kompyuter (monitor yoki televizor) uchun; Linux'da ham ishlaydi (sinov uchun).

## Qanday ishlatiladi

### 1. Terminalni ulash (bir marta)

1. Ega yoki menejer boshqaruv panelida **Qurilmalar → Terminal ulash** bo'limida turini "Oshxona ekrani" (KDS) va filialni
   tanlab, 8 belgili kod oladi (`ABCD-EFGH`, 10 daqiqa amal qiladi, bir marta ishlatiladi).
2. Planshetda ilovani oching va kodni kiriting: chiziqcha bilan yoki usiz, kichik harf bilan ham bo'ladi (maydon o'zi
   `ABCD-EFGH` ko'rinishiga keltiradi). **Ulash** tugmasini bosing.
3. Terminal tokeni qurilmaning maxfiy omborida saqlanadi (Android Keystore, Windows Credential Locker, Linux libsecret):
   ilova qayta ishga tushganda qayta ulash shart emas. Panelda terminal o'chirilsa, ilova o'zi ulash ekraniga qaytadi.

Terminalni bu qurilmadan uzish: PIN ekranidagi zanjir ikonkasi (**Terminalni uzish**), so'ng tasdiqlash.

### 2. Oshpaz PIN bilan kiradi

* PIN ekranida shu terminalda ishlay oladigan, PIN o'rnatilgan xodimlar ko'rinadi (oshpazlar birinchi). Ismingizni
  bosing va 4–6 xonali PIN'ni tering (✓ yoki 6-raqamdan keyin o'zi yuboriladi). Kompyuterda klaviatura ham ishlaydi
  (raqamlar, Backspace, Enter).
* Noto'g'ri PIN: "PIN noto'g'ri. Qayta urinib ko'ring." Ko'p xato bo'lsa server kirishni vaqtincha bloklaydi: klaviatura
  o'chadi va qancha kutish kerakligi sanab turiladi ("0:58 dan keyin qayta urinib ko'ring").
* PIN o'rnatilgan xodim yo'q bo'lsa, panelda **Xodimlar** bo'limidan oshpazga PIN o'rnating va **Yangilash** ni bosing.
* **Chiqish** (qulf) tugmasi oshpazni chiqaradi. Sozlamalarda belgilangan vaqt (standart 15 daqiqa) ekranga tegilmasa ham
  avtomatik chiqiladi. Terminal esa ulangan qoladi va real-time ishlashda davom etadi: hech kim kirmagan paytda yangi
  buyurtma kelsa ovoz chalinadi va "1 ta yangi buyurtma oshxonaga keldi" yozuvi chiqadi.

### 3. Chiptalar doskasi

* Qabul qilingan va tayyorlanayotgan buyurtmalar, **eskisidan yangisiga** (chap yuqoridan o'ngga, keyin pastga). Ustunlar
  ekran kengligiga qarab: planshet (1280×800) — 3, 1080p televizor — 5, tor ekran — 1–2.
* Chiptada: raqam (`#E101`), turi (**Zal**, stol bilan; **Olib ketish**; **Yetkazish**), kanal, holat, qabul qilingan vaqt,
  nechta taom tayyorligi, mijoz izohi (telefon raqamlari niqoblangan), taomlar — miqdor (`2×`) yoki vazn (`1.5 kg`) va
  modifikatorlar (`+ Achchiq`). Narx va mijoz ma'lumoti ko'rsatilmaydi.
* **Kutish vaqti** qabul qilingandan beri sanaladi va rang bilan (ikonka va yozuv ham bor, faqat rang emas):
  odatiy — kulrang; **Shoshiling** — sariq (standart 10 daqiqadan); **Kechikdi** — qizil (standart 20 daqiqadan).
  Chegaralar sozlamalarda o'zgaradi.
* **Taomni tayyor deb belgilash:** taom qatorini bosing — u darhol belgilanadi (✓, ustidan chizilgan), so'ng serverga
  yuboriladi. Server rad etsa (yoki aloqa bo'lmasa) belgi qaytadi va sababi pastda chiqadi. Tayyor qatorni yana bossangiz —
  qaytariladi (xato bosilgan bo'lsa).
* **Hammasi tayyor:** chiptadagi qolgan taomlarning hammasini belgilaydi. Yetkazish va olib ketish buyurtmasi oxirgi taom
  tayyor bo'lganda o'zi "tayyor" bo'ladi va doskadan ketadi (buni server hal qiladi). Zal buyurtmasi stol yopilguncha
  doskada qoladi: hamma taom tayyor bo'lsa **Buyurtma tayyor** tugmasi chiqadi.
* **Yaqinda tayyor** lentasi (pastda): oxirgi 10 daqiqada belgilangan taomlar, har biridan **Qaytarish**. Buyurtma
  allaqachon "tayyor" bo'lib ketgan bo'lsa server qaytarishga ruxsat bermaydi va bu haqda xabar chiqadi.
* Xodimda `kitchen.manage` ruxsati bo'lmasa (masalan, faqat ko'rish huquqi bor kassir), doska faqat ko'rish uchun.
* Yangi chipta kelganda ovoz chalinadi va chiptada **Yangi** belgisi bir daqiqa turadi. Ovozni yuqoridagi karnay
  tugmasi o'chiradi.

### 4. Real-time va aloqa

* Ilova Reverb'ga (WebSocket) ulanadi va filialning `private-pos-branch.{id}` kanalini **terminal tokeni** bilan
  tinglaydi (PIN sessiyasi kerak emas). Hodisa kelsa chiptalar darhol qayta yuklanadi. Yuqorida belgisi: **Jonli**,
  **Qayta ulanmoqda** yoki (real-time sozlanmagan bo'lsa) **Har 15 soniyada**.
* Ulanish uzilsa ilova o'zi qayta ulanadi (1, 2, 4 ... 30 soniya oralig'ida) va qayta ulanganda ro'yxatni to'liq yangilaydi.
  Real-time ishlamayotganda chiptalar har 15 soniyada, ishlayotganda ham ehtiyot uchun har 60 soniyada so'raladi.
* Server bilan aloqa bo'lmasa tepada qizil **Aloqa yo'q** lentasi chiqadi ("Oxirgi yangilanish 14:05"), ekran oxirgi
  chiptalarni ko'rsatishda davom etadi. **Qayta urinish** yoki aloqa tiklanishi bilan lenta yo'qoladi.

### 5. Sozlamalar (tishli g'ildirak)

Ovoz, qorong'i/yorug' mavzu (standart qorong'i), shrift (100/120/140% — televizor uchun kattaroq), "Shoshiling" va "Kechikdi"
chegaralari, avtomatik chiqish vaqti (o'chirib qo'yish ham mumkin). Sozlamalar qurilmada saqlanadi (`shared_preferences`).

Android'da ekran o'chib qolmaydi (`FLAG_KEEP_SCREEN_ON`).

## Tayyorlash

Flutter 3.47.5 kerak. O'rnatish va workspace haqida: [`packages/dart/README.md`](../../../packages/dart/README.md).

```bash
flutter pub get          # repozitoriy ildizida (butun workspace uchun bir marta)
```

## Sozlash (`--dart-define`)

| Kalit | Nima | Standart |
|---|---|---|
| `RIZQIM_API_URL` | Backend manzili, `/api` bilan tugaydi | `http://localhost/api` |
| `RIZQIM_REVERB_KEY` | `REVERB_APP_KEY`. Bo'sh bo'lsa real-time o'chiq, faqat har 15 soniyada so'raladi | bo'sh |
| `RIZQIM_REVERB_URL` | Reverb boshqa xost/portda bo'lsa (`wss://ws.example.uz`, `ws://127.0.0.1:8080`) | backend manzilidan: `wss://<domen>/app/<key>` |
| `RIZQIM_REVERB_ORIGIN` | WebSocket'ga yuboriladigan `Origin` | backend manzilining origin'i |

**Muhim (server):** Reverb `REVERB_ALLOWED_ORIGINS` da `*` bo'lmasa, ilova yuboradigan `Origin` (standart — backend
manzilining xosti, masalan `api.example.uz`) shu ro'yxatda bo'lishi kerak, aks holda ulanish rad etiladi va ekran faqat
polling bilan ishlaydi.

## Testlar

```bash
cd pos/apps/kds
flutter analyze
flutter test
```

Testlar backend'siz ishlaydi: soxta backend (`test/support/fake_kitchen_state.dart`, Dio adapteri) va soxta Reverb
(`test/support/fake_realtime.dart`). Qamrov: ulash; PIN (xato, bloklash va qayta ochilish, tarmoq xatosi, bo'sh ro'yxat,
avtomatik va qo'lda chiqish, server sessiyani yopsa); doska (turi, vazn, modifikator, izoh, tartib, taymer ranglari va
chegaralar, optimistik "tayyor" va xatoda orqaga qaytish, qaytarish, "Hammasi tayyor", zal buyurtmasi, faqat ko'rish
rejimi, "Aloqa yo'q", polling, ovoz va ovozsiz, ustunlar va overflow, 48px tugmalar, sozlamalar); real-time (terminal
tokeni bilan imzo, PIN'siz ishlashi, hodisada darhol yangilanish, uzilish — backoff — polling — qayta ulanib yangilash).

**Haqiqiy HTTP/WebSocket bilan integratsiya testi (P2-31)** — `test/kds_over_http_test.dart`: ilovaning o'z Dio klienti va Pusher
klienti loopback'dagi `FakeKdsServer` (`test/fake_server/fake_kds_server.dart`, `dart:io` `HttpServer` + WebSocket) bilan haqiqiy
socket orqali gaplashadi. Oqim: ulash kodi → PIN → doska → yangi buyurtma **socket hodisasi** bilan keladi (widget testning soati
soxta, shuning uchun polling navbat olmaydi) → ikkala taom "tayyor" → server buyurtmani `ready` qiladi va u doskadan ketadi. Haqiqiy
tarmoq kutish `tester.runAsync` + `pump` bilan (`eventually`); test ~4 soniya, CI'dagi `flutter test` bilan birga ishlaydi.

### Linux'da soxta server bilan ishga tushirish

```bash
cd pos/apps/kds
dart run test/fake_server/fake_kds_server.dart --port 8787        # alohida terminalda
flutter run -d linux --dart-define=RIZQIM_API_URL=http://127.0.0.1:8787/api --dart-define=RIZQIM_REVERB_KEY=kds-key
```

Ulash kodi — istalgan 8 belgi, PIN — `2468` (3 marta xato — 60 soniya blok). Yangi buyurtma:
`curl -X POST http://127.0.0.1:8787/__control/new-order`; aloqani uzish: `.../__control/offline?on=1` (qaytarish `on=0`),
WebSocket'larni uzish: `.../__control/drop`.

## Ishga tushirish va yig'ish

### Android (planshet)

Android SDK va qurilma yoki emulyator kerak (`flutter doctor`):

```bash
cd pos/apps/kds
flutter run -d <qurilma> --dart-define=RIZQIM_API_URL=https://staging.example.uz/api --dart-define=RIZQIM_REVERB_KEY=...
flutter build apk --release --dart-define=...
```

Emulyatorda kompyuterdagi backend manzili `http://10.0.2.2/api`. Ilova identifikatori `uz.rizqim.kds`. Release uchun
imzolash kaliti hali sozlanmagan (`android/app/build.gradle.kts` hozir debug kalitini ishlatadi).

### Windows

Windows mashinasida (Visual Studio 2022, "Desktop development with C++" bilan):

```powershell
cd pos\apps\kds
flutter run -d windows --dart-define=RIZQIM_API_URL=https://staging.example.uz/api --dart-define=RIZQIM_REVERB_KEY=...
flutter build windows --release      # build\windows\x64\runner\Release\
```

Token Windows Credential Locker/DPAPI da saqlanadi. Ovoz Windows Media Foundation orqali. Televizorga ulangan kompyuterda
ekran saqlovchi va uyqu rejimini Windows sozlamalarida o'chiring.

### Linux

```bash
sudo apt install clang cmake ninja-build pkg-config libgtk-3-dev libsecret-1-dev libjsoncpp-dev \
  libgstreamer1.0-dev libgstreamer-plugins-base1.0-dev gstreamer1.0-plugins-base gstreamer1.0-plugins-good
cd pos/apps/kds
flutter build linux --release        # build/linux/x64/release/bundle/
```

Ovoz GStreamer orqali (`audioplayers`): `gstreamer1.0-plugins-base`/`-good` o'rnatilmagan bo'lsa yangi chipta kelganda
ilova yiqilishi mumkin. Tokenni saqlash uchun kalitlar xizmati (`gnome-keyring` yoki KWallet) ishlab turishi kerak; u bo'lmasa
ilova ishlaydi, lekin PIN ekranida "Terminal tokeni qurilmada saqlanmadi" ogohlantirishi chiqadi.

## Tuzilishi

| Fayl | Nima |
|---|---|
| `lib/src/app.dart` | `KdsApp`, ulash → PIN → doska almashinuvi (`AuthGate`, `TerminalHome`), real-time terminal darajasida |
| `lib/src/screens/pairing_screen.dart` | ulash kodi |
| `lib/src/screens/staff_sign_in_screen.dart` | xodimlar ro'yxati, PIN, bloklash, PIN'siz kelgan buyurtmalar soni |
| `lib/src/screens/kitchen_board_screen.dart` | doska, yuqori panel, "Aloqa yo'q", ustunlar, avtomatik chiqish |
| `lib/src/board/kitchen_board_controller.dart` | yuklash (hodisa, qayta ulanish, polling), optimistik amallar va orqaga qaytish |
| `lib/src/realtime/branch_realtime.dart` | `private-pos-branch.{id}` (terminal tokeni bilan) |
| `lib/src/settings/kds_settings.dart` | sozlamalar va ularni saqlash |
| `lib/src/sound/ticket_sound.dart`, `assets/sounds/new_ticket.wav` | yangi chipta ovozi |
| `lib/src/widgets/` | chipta kartochkasi, "Yaqinda tayyor" lentasi, real-time belgisi, sozlamalar oynasi |

Umumiy qismlar (kassa va ofitsiant ilovalari uchun ham) `packages/dart` da: Reverb klienti, oshxona modellari, PIN xatolari
(`rizqim_api`), PIN klaviaturasi, xodim kartochkasi, "Aloqa yo'q" lentasi, harakatsizlikda chiqarish (`rizqim_ui`).

## Eslatma

* Dart paketi nomi `rizqim_kds`; platforma papkalari (`android/`, `linux/`, `windows/`) `flutter create --project-name kds`
  bilan yaratilgan, shuning uchun identifikatorlar `uz.rizqim.kds`, Windows'da `kds.exe`.
* iOS/macOS/web bu ilova uchun yoqilmagan.
* Birinchi versiyada filialga bitta ekran (sexlar — issiq, sovuq, bar — alohida emas, docs/PHASE2_PLAN.md).
