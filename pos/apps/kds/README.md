# pos/apps/kds

Oshxona ekrani (KDS): barcha kanallardan (Telegram, ilova, kassa, zal) kelgan buyurtmalar bitta doskada. Android planshet,
Windows kompyuter (monitor yoki televizor) uchun; Linux'da ham ishlaydi (sinov uchun).

## Qanday ishlatiladi

### 1. Terminalni ulash (bir marta)

1. Ega yoki menejer boshqaruv panelida **Sozlamalar → Qurilmalar → Ulash kodini yaratish** ni bosadi, "Qurilmani ulash"
   oynasida turini "Oshxona ekrani" (KDS) va filialni tanlab **Kod yaratish** ni bosadi va 8 belgili kod oladi
   (`ABCD-EFGH`, 10 daqiqa amal qiladi, bir marta ishlatiladi).
2. Planshetda ilovani oching va kodni kiriting: chiziqcha bilan yoki usiz, kichik harf bilan ham bo'ladi (maydon o'zi
   `ABCD-EFGH` ko'rinishiga keltiradi). **Ulash** tugmasini bosing.
3. Terminal tokeni qurilmaning maxfiy omborida saqlanadi (Android Keystore, Windows Credential Locker, Linux libsecret):
   ilova qayta ishga tushganda qayta ulash shart emas. Panelda terminal o'chirilsa, ilova o'zi ulash ekraniga qaytadi.

**Terminalni uzish** faqat menejer yoki ega (`devices.manage`) uchun va faqat PIN bilan kirgandan keyin: PIN ekranida
sozlamalar ham, uzish ham yo'q (PIN'siz kishi planshetni uza olmasin, ovoz yoki avtomatik chiqishni o'chira olmasin).
Ikki yo'l bor, ikkalasi ham oldingi oshpazsiz ishlaydi:

* **Panelda (tavsiya etiladi):** Sozlamalar → Qurilmalar → qurilma qatoridagi **O'chirish**. Server terminal tokenini va
  undagi sessiyalarni darhol yopadi, planshet o'zi ulash ekraniga qaytadi.
* **Planshetda:** menejer o'z PIN'i bilan kiradi (PIN o'rnatilgan menejer ro'yxatda bor) → **Sozlamalar** → **Terminalni
  uzish** → **Uzish**. Xodim sessiyasi serverda yopiladi va token qurilmadan o'chiriladi. Terminal o'z tokenini serverda
  o'chira olmaydi (bunday endpoint yo'q, `/owner/devices/{uuid}/revoke` PIN sessiyasiga yopiq — docs/AUTH.md), shuning
  uchun oyna qurilmani panelda ham o'chirishni eslatadi.

### 2. Oshpaz PIN bilan kiradi

* PIN ekranida shu terminalda ishlay oladigan, PIN o'rnatilgan xodimlar ko'rinadi (oshpazlar birinchi). Ismingizni
  bosing va 4–6 xonali PIN'ni tering (✓ yoki 6-raqamdan keyin o'zi yuboriladi). Kompyuterda klaviatura ham ishlaydi
  (raqamlar, Backspace, Enter).
* Noto'g'ri PIN: "PIN noto'g'ri. Qayta urinib ko'ring." Ko'p xato bo'lsa server kirishni vaqtincha bloklaydi: klaviatura
  o'chadi va qancha kutish kerakligi sanab turiladi ("0:58 dan keyin qayta urinib ko'ring").
* PIN o'rnatilgan xodim yo'q bo'lsa, panelda **Xodimlar** bo'limidan oshpazga PIN o'rnating va **Yangilash** ni bosing.
* **Chiqish** (qulf) tugmasi oshpazni chiqaradi. Sozlamalarda belgilangan vaqt (standart 15 daqiqa) ekranga tegilmasa ham
  avtomatik chiqiladi. Harakatsizlik butun ilova bo'yicha sanaladi (detektor Navigator'dan yuqorida, `MaterialApp.builder`):
  sozlamalar oynasidagi teginish ham hisoblanadi; chiqishda (qo'lda, avtomatik yoki server sessiyani yopsa) ochiq oynalar
  yopiladi. Terminal esa ulangan qoladi va real-time ishlashda davom etadi: hech kim kirmagan paytda yangi
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

### 3a. Sexlar (choyxona: mangal, osh, salat, choy)

* PIN'dan keyin, filialda KDS'da ko'rsatiladigan sexlar bo'lsa (`GET /pos/bootstrap` → `stations`, faqat faol va
  `show_on_kds`), oshpaz **bir yoki bir nechta sexni tanlaydi**. **Umumiy** — sexsiz qatorlar (onlayn buyurtmalar,
  yo'naltirish topilmagan taomlar). **Hammasi** hamma sexni belgilaydi. Tanlov **qurilmada saqlanadi**: ekran qayta
  yoqilganda qayta so'ralmaydi; o'zgartirish uchun yuqoridagi **Sex: Mangal, Osh** tugmasi.
* Doska faqat tanlangan sexlarning qatorlarini ko'rsatadi (chiptada boshqa sex qatorlari yo'q, "Hammasi tayyor" ham faqat
  ko'ringanlarni belgilaydi). Sex nomlari yuqorida, bo'limlar qatorida turadi.
* Filialda sex sozlanmagan bo'lsa (yoki eski server) tanlash ekrani chiqmaydi va hamma chipta ko'rinadi, avvalgidek.
* POS buyurtmasida kutish vaqti **eng eski pishayotgan qatordan** sanaladi (xona hisobi 5 soat ochiq turadi, butun seans
  emas); rang chegaralari avvalgidek. POS buyurtmasida "Buyurtma tayyor" tugmasi yo'q: hammasi qator bo'yicha, stolni ofitsiant
  yopadi. Bir nechta bosqich bo'lsa qatorlar bosqich sarlavhalari bilan.

### 3b. Mangal ko'rinishi (six sanog'i)

Faqat mangal sexlari tanlangan bo'lsa doska o'zi mangal ko'rinishiga o'tadi (yuqoridagi **Oddiy / Mangal** tugmasi bilan
qo'lda ham almashtiriladi, tanlov saqlanadi). Chiptada **xona yoki stol** (katta), bosqich raqami, kutish vaqti
(avvalgi ranglar) va taomlar six sanog'i bilan: **Qo'y shashlik — 6 six**, **Tovuq shashlik — 4 six**; bir bosqichdagi bir xil taom
bitta qatorga qo'shiladi, sarlavhada "Jami: 10 six". Qatorni bosish shu taomning hamma sixini "tayyor" qiladi.

### 3c. Oldindan buyurtmalar

Ushlab turilgan (`hold`) va muddati (`due_at`) bor qatorlar **Oldindan buyurtmalar** bo'limida muddat bo'yicha (eng yaqini
birinchi) turadi: muddat soati, qolgan vaqt, xona/stol, bosqich, sex. Muddatdan oldin **sex turiga qarab** faol doskaga
o'tadi (standart: osh 120 daqiqa, mangal 30 daqiqa, boshqalar 30 daqiqa; Sozlamalarda o'zgaradi), yoki ofitsiant oldinroq
yuborsa. Faol doskada hali yuborilmagan qator "Muddat 14:30 · hali yuborilmagan" belgisi bilan turadi va uni "tayyor" deb
bo'lmaydi (server yuborilmagan qatorni rad etadi). Qolgan vaqt server soatidan hisoblanadi (chiptadagi kutish vaqtidan
olinadi), planshet soati farq qilsa ham to'g'ri.

### 3d. O'chirilgan qatorlar, "Berildi"

* O'chirilgan qator ustidan chizilgan, qizil **O'CHIRILDI** belgisi va sababi ("Sabab: Mehmon xohlamadi") bilan 2 daqiqa
  turadi, keyin yo'qoladi. Ish paytida qator o'chirilsa **alohida ovoz** (yangi chiptadan farqli) chalinadi. Butun buyurtma
  bekor qilinsa u chiptalar ro'yxatidan ketadi: ilova buyurtma tafsilotini so'rab (`GET /staff/orders/{uuid}`), qizil
  "O'CHIRILDI" chiptasini 2 daqiqa ko'rsatadi. Oshpazning o'z "tayyor"i bekor qilish deb o'ylanmaydi.
* `served` (berildi) qator tugatilgan ko'rinishda ("Berildi", ✓✓). "Tayyor → berildi" — ofitsiantning amali, KDS uni faqat
  ko'rsatadi (bosib qaytarib bo'lmaydi). Hamma qatori berilgan chipta doskadan ketadi.

### 3e. Qozon paneli (`batches.manage`)

Ruxsati borlarga (oshpaz, menejer, ega) **Qozon** bo'limi chiqadi: filialning ochiq qozonlari va qolgan porsiyalar (rangli
chiziq: yashil / sariq 20% dan kam / qizil "TUGADI"). Amallar (docs/MENU.md §4): **Qo'shish** (`+5 +10 +20` yoki son;
`POST /owner/batches/{id}/adjust {add}`), **Sanash** (qolganini qayta sanash, `{quantity_left}`), **Yopish** (tasdiq bilan,
`/close`), **Yangi qozon** (qozon bo'yicha sotiladigan taom, `batch_tracked`, `GET /catalog?channel=pos_hall`; `POST
/owner/batches`) va yopilgan qozonni **Qayta ochish**. Panel `menu.availability_changed` hodisasida va har 15 soniyada
yangilanadi; server rad etsa xabar chiqadi.

### 4. Real-time va aloqa

* Ilova Reverb'ga (WebSocket) ulanadi va filialning `private-pos-branch.{id}` kanalini **terminal tokeni** bilan
  tinglaydi (PIN sessiyasi kerak emas). Hodisa kelsa chiptalar darhol qayta yuklanadi. Yuqorida belgisi: **Jonli**
  (ulangan **va** kanal obunasi tasdiqlangan — `pusher_internal:subscription_succeeded`), **Ulanmoqda** (ulanish yoki obuna
  kutilmoqda), **Qayta ulanmoqda** yoki **Har 15 soniyada** (real-time sozlanmagan, yoki ulanish bor, lekin kanal imzosi
  rad etildi — `/broadcasting/auth` 403/429 — va fonda qayta so'ralmoqda).
* Ulanish uzilsa ilova o'zi qayta ulanadi (1, 2, 4 ... 30 soniya oralig'ida) va obuna qayta tasdiqlanganda ro'yxatni to'liq
  yangilaydi (handshake'da emas: obuna tasdiqlanguncha kelgan hodisalar yo'qolardi). Jonli bo'lmagan har qanday holatda
  chiptalar har 15 soniyada, jonli bo'lsa ham ehtiyot uchun har 60 soniyada so'raladi. Belgilangan muddatda ochilmagan ulanish
  tashlanadi va kech ochilsa darhol yopiladi (osilgan TCP ulanishlar to'planmaydi).
* Server javob berib, uni o'qib bo'lmasa (ilova versiyasi eski) bu "Aloqa yo'q" emas: "Server javobini o'qib bo'lmadi"
  xabari chiqadi. Ro'yxatdagi bitta chipta o'qilmasa, faqat o'sha tushiriladi (log'ga yoziladi) va doskada
  "1 ta chiptani o'qib bo'lmadi" ogohlantirishi turadi; qolgan chiptalar ko'rsatiladi.
* Server bilan aloqa bo'lmasa tepada qizil **Aloqa yo'q** lentasi chiqadi ("Oxirgi yangilanish 14:05"), ekran oxirgi
  chiptalarni ko'rsatishda davom etadi. **Qayta urinish** yoki aloqa tiklanishi bilan lenta yo'qoladi.

### 5. Sozlamalar (tishli g'ildirak, faqat PIN bilan kirilgan doskada)

Ovoz, qorong'i/yorug' mavzu (standart qorong'i), shrift (100/120/140% — televizor uchun kattaroq), "Shoshiling" va "Kechikdi"
chegaralari, avtomatik chiqish vaqti (o'chirib qo'yish ham mumkin), oldindan buyurtmaning doskaga chiqish vaqti (osh,
mangal, boshqa sexlar). Sozlamalar va tanlangan sexlar qurilmada saqlanadi (`shared_preferences`).

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
manzilining xosti, masalan `api.example.uz`; `RIZQIM_REVERB_ORIGIN` berilsa — uning xosti) shu ro'yxatda bo'lishi kerak,
aks holda ulanish rad etiladi (4009) va ekran faqat polling bilan ishlaydi. Ro'yxatda Mini App xosti, panel xosti
(`panel.<domen>`) va KDS uchun API xosti bo'ladi: [docs/DEPLOY.md](../../../docs/DEPLOY.md).

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
tokeni bilan imzo, PIN'siz ishlashi, hodisada darhol yangilanish, uzilish — backoff — polling — qayta ulanib yangilash;
imzo 403/429 bo'lsa "Har 15 soniyada" va 15 s polling; obuna kech tasdiqlansa "Ulanmoqda", tasdiqlangach "Jonli" va to'liq
yangilash); PIN ekranida sozlamalar va uzish yo'qligi, uzish faqat menejer PIN'i bilan; dialog ichidagi teginish va
chiqishda dialoglarning yopilishi; o'qib bo'lmagan chipta yoki javob ("Aloqa yo'q" emas).

**P3-24 (choyxona):** `test/pos_kitchen_test.dart` (sex tanlash va saqlash, filtr, "Umumiy", mangal guruhlash va rang, oldindan
buyurtma navbati va muddat — soxta soat bilan, o'chirilgan qator va bekor qilingan buyurtma, ovozli signal, berilgan qator),
`test/kazan_panel_test.dart` (soxta server bilan qozon amallari: qo'shish, sanash, yopish, yangi va qayta ochish, xato, real-time),
`test/board_view_test.dart` (doska qoidalari toza funksiya sifatida), `packages/dart/rizqim_api/test/kitchen_setup_test.dart`.

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

Choyxona namunasi (sexlar, mangal, oldindan buyurtmalar, qozon): serverni `--pos` bilan ishga tushiring
(`dart run test/fake_server/fake_kds_server.dart --port 8787 --pos`). Qator o'chirish: `.../__control/void?reason=...`,
buyurtmani bekor qilish: `.../__control/cancel`.

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
| `lib/src/board/kitchen_board_controller.dart` | yuklash (hodisa, qayta ulanish, polling), optimistik amallar va orqaga qaytish, sexlar, o'chirilgan qatorlar |
| `lib/src/board/board_view.dart` | doska qoidalari (toza funksiya): sex filtri, oldindan buyurtma navbati, o'chirilgan qator oynasi, kutish vaqti |
| `lib/src/board/skewer_groups.dart` | mangal: taomlarni six sanog'i bilan guruhlash |
| `lib/src/stations/station_selection.dart` | tanlangan sexlar va ko'rinish rejimi (qurilmada saqlanadi) |
| `lib/src/realtime/branch_realtime.dart` | `private-pos-branch.{id}` (terminal tokeni bilan) |
| `lib/src/settings/kds_settings.dart` | sozlamalar va ularni saqlash |
| `lib/src/sound/ticket_sound.dart`, `assets/sounds/new_ticket.wav` | yangi chipta ovozi |
| `lib/src/widgets/` | chipta va mangal kartochkasi, sex tanlash, oldindan buyurtmalar, qozon paneli, "Yaqinda tayyor" lentasi, real-time belgisi, sozlamalar oynasi |

Umumiy qismlar (kassa va ofitsiant ilovalari uchun ham) `packages/dart` da: Reverb klienti, oshxona modellari, PIN xatolari
(`rizqim_api`: sexlar va qozon so'rovlari `kitchen_setup.dart`), PIN klaviaturasi, xodim kartochkasi, "Aloqa yo'q" lentasi, harakatsizlikda chiqarish (`rizqim_ui`).

## Eslatma

* Dart paketi nomi `rizqim_kds`; platforma papkalari (`android/`, `linux/`, `windows/`) `flutter create --project-name kds`
  bilan yaratilgan, shuning uchun identifikatorlar `uz.rizqim.kds`, Windows'da `kds.exe`.
* iOS/macOS/web bu ilova uchun yoqilmagan.
* 2-bosqichda filialga bitta ekran edi; 3-bosqichda (P3-24) sexlar, mangal, oldindan buyurtmalar va qozon paneli qo'shildi
  (docs/POS_SPEC.md §2.8, §6.4).
