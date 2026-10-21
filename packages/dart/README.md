# packages/dart

Flutter ilovalari uchun umumiy Dart paketlari. Hammasi bitta **Dart workspace**da (pub workspaces): ildizdagi
`pubspec.yaml` a'zolarni sanaydi, bitta `pubspec.lock` va bitta `flutter pub get` hamma paketga yetadi.

| Paket | Nima | Flutter kerakmi |
|---|---|---|
| `rizqim_core` | Pul (`Money`, butun so'm, `25 000 so'm` ajralmas bo'shliq bilan), sana (`19-okt, 20:12`), davomiylik (`formatDuration`: `4:05`), buyurtma holatlari va rang guruhlari, buyurtma turi va kanali (`OrderType.shortLabel`: "Zal", `OrderChannel`), xodim rollari (`StaffRole`: "Oshpaz"). Mini App (`customer/miniapp/src/lib/format.ts`, `orderStatus.ts`) bilan bir xil natija | yo'q (`dart test`) |
| `rizqim_api` | OpenAPI'dan yaratilgan klient + qo'lda yozilgan kirish qatlami (terminalni ulash, xodim PIN sessiyasi, refresh token aylanishi, PIN xatolari `PinFailure`), oshxona ekrani (`KitchenService`, `KitchenTicket`), Reverb real-time klienti (`PusherClient`, `ChannelAuth`) | yo'q (`dart test`) |
| `rizqim_ui` | Material 3 mavzusi (Rizqim tokenlari, yorug' va qorong'i), `RzButton`, `StatusBadge`, `EmptyState`, `InlineError`; terminal ilovalari uchun `RzPinEntry`/`RzPinPad`, `RzStaffTile`, `RzOfflineBanner` ("Aloqa yo'q"), `RzIdleDetector` (harakatsizlikda chiqarish) | ha (`flutter test`) |

Namunaviy ilova: [`pos/apps/kds`](../../pos/apps/kds/README.md).

## Flutter'ni o'rnatish

Loyiha **Flutter 3.47.5** (stable, Dart 3.13.4) bilan sinalgan; CI ham shu versiyada (`.github/workflows/ci.yml`,
`FLUTTER_VERSION`). Versiyani oshirsangiz, ikkalasini birga yangilang va ildizdagi `pubspec.yaml` (`sdk:`) ni tekshiring.

```bash
# Linux (boshqa tizimlar: https://docs.flutter.dev/install)
curl -O https://storage.googleapis.com/flutter_infra_release/releases/stable/linux/flutter_linux_3.47.5-stable.tar.xz
tar -xf flutter_linux_3.47.5-stable.tar.xz -C ~/        # ~/flutter
export PATH="$HOME/flutter/bin:$PATH"
flutter config --no-analytics
flutter precache --linux                                 # kerakli platformalar uchun (--android, --windows)
flutter doctor
```

Linux'da ilovani yig'ish uchun: `sudo apt install clang cmake ninja-build pkg-config libgtk-3-dev libsecret-1-dev libjsoncpp-dev`
(KDS ovozi uchun yana `libgstreamer1.0-dev libgstreamer-plugins-base1.0-dev gstreamer1.0-plugins-base gstreamer1.0-plugins-good`).
Android uchun Android SDK kerak (testlar va `flutter analyze` uchun kerak emas).

## Kundalik buyruqlar (repozitoriy ildizidan)

```bash
flutter pub get                                  # butun workspace
dart format --set-exit-if-changed .              # CI shuni tekshiradi
flutter analyze                                  # butun workspace

(cd packages/dart/rizqim_core && dart test)
(cd packages/dart/rizqim_api  && dart test)
(cd packages/dart/rizqim_ui   && flutter test)
(cd pos/apps/kds              && flutter test)
```

Workspace'ga yangi ilova qo'shish: papkani `flutter create` bilan yarating, uning `pubspec.yaml`iga `resolution: workspace`
va `environment: sdk: ^3.13.0` qo'shing, papkani ildizdagi `pubspec.yaml` → `workspace:` ro'yxatiga yozing, `analysis_options.yaml`
ichida `include: <ildizga>/analysis_options.yaml` qiling. Paketlarga bog'liqlik: `path:` (masalan, `../../../packages/dart/rizqim_core`).

## rizqim_api: yaratilgan klient

`packages/api-spec/openapi.json` (backend `php artisan api-spec:export` bilan yozadi) → `lib/src/generated`
(Retrofit klientlar + `json_serializable` modellar). **Java kerak emas**: `swagger_parser` Dart'da ishlaydi.
Yaratilgan kod commit qilinadi; spetsifikatsiya o'zgargach qayta yarating:

```bash
bash packages/dart/rizqim_api/tool/generate.sh
```

Skript: `tool/prepare_spec.dart` (bir nechta turni birlashtiruvchi `anyOf`/`oneOf` javoblarni erkin qiymatga
almashtiradi: `swagger_parser` ularga to'g'ri kod yarata olmaydi, masalan `GET /v1/orders` kursor yoki oddiy ro'yxat
qaytaradi; bunday joylar `dynamic` bo'ladi) → `swagger_parser` → `build_runner` → `dart format`. CI shu skriptni
ishga tushirib, natija commit qilingan kod bilan bir xil ekanini tekshiradi. To'lov va Telegram webhook'lari
(`Webhooks` tegi) klientga kirmaydi.

Yaratilgan sinflar (`OrderStatus`, `Product`, ...) `rizqim_core` nomlari bilan to'qnashadi, shuning uchun
`package:rizqim_api/rizqim_api.dart` ularni chiqarmaydi: kerak bo'lsa
`import 'package:rizqim_api/generated.dart' as gen;`. Odatda `RizqimClient.api` (`client.api.orders`, `client.api.terminal`)
yetarli.

### Kirish qatlami (qo'lda yozilgan)

```dart
final client = RizqimClient(baseUrl: 'https://.../api', store: SecureStorageStore()); // ilovada flutter_secure_storage
await client.auth.restore();                       // saqlangan terminal/sessiyani o'qish
await client.auth.pairTerminal('abcd efgh');       // chiziqcha bilan ham, usiz ham; katta-kichik harf farqi yo'q
final staff = await client.auth.loadStaff();       // PIN klaviaturasi uchun
await client.auth.signInStaff(userId: 5, pin: '4821');
final orders = await client.api.orders.orderIndex();   // xodim tokeni o'zi qo'yiladi
```

* **Qaysi token:** yo'l bo'yicha. `/v1/terminal*` va `/v1/devices/{uuid}/staff-session` — terminal tokeni; ulash va
  `/v1/auth/refresh|telegram|phone/*` — tokensiz; qolgani — xodim sessiyasi, bo'lmasa shaxsiy ilova tokeni. Terminal
  tokeni oddiy API'ga hech qachon yuborilmaydi.
* **Refresh single-flight:** access token tugasa (yoki 401 kelsa) `POST /auth/refresh` bir vaqtda faqat **bitta** yuboriladi,
  parallel so'rovlar o'sha natijani kutadi va yangi token bilan qayta yuboriladi (docs/AUTH.md: sarflangan refresh token yana kelsa
  backend butun oilani bekor qiladi). Yangi juftlik qayta yuborishdan oldin omborga yoziladi. Refresh token o'lsa
  (`refresh_reused|invalid|expired`) sessiya o'chadi va `AuthEventKind.userSignedOut` keladi; tarmoq xatosida tokenlar saqlanadi.
* **Saqlash:** `SecureKeyValueStore` interfeysi (`read/write/delete`); tokenlar faqat shu orqali yoziladi. Ilovada
  `flutter_secure_storage` bilan amalga oshiriladi (`pos/apps/kds/lib/src/secure_storage_store.dart`). Xodim (PIN) sessiyasi
  **saqlanmaydi**: ilova qayta ishga tushsa, PIN qayta teriladi. Ombor yozmasa, `auth.storageError` to'ladi.
* **Xatolar:** `ApiException` (`message` backend matni, `reason`, `errors`, `retryAfter`). `AuthReason` ichida
  `pairing_invalid`, `refresh_reused` va boshqalar.

* **PIN xatolari:** `PinFailure.from(apiException)` — o'zbekcha matn va bloklangan bo'lsa qancha kutish (`lockedFor`,
  `Retry-After` dan): `pin_invalid`, `pin_locked` (429), tarmoq, 401/403.

Mini App hech narsani o'zgartirmaydi: u bitta tokenli `POST /auth/telegram` bilan ishlashda davom etadi.

### Oshxona ekrani: `KitchenService`

`client.kitchen` — yaratilgan `api.kitchen`/`api.staffOrders` ustida (xodim PIN sessiyasi tokeni bilan):
`tickets({branchId, includeReady})` → `KitchenTicketList`, `bumpLine(uuid, lineId)`, `recallLine(uuid, lineId)` →
yangilangan `KitchenTicket` (server amalni bajarib, javobni o'qib bo'lmasa `null`: ro'yxatni qayta yuklang),
`markOrderReady(uuid)`. `KitchenTicket` yaratilgan `Data80`/`Data83` nomlariga bog'liq emas: `code` (`#E101`), `lines`
(`quantityLabel`, `modifiers`, `isReady`), `waitingAt(now)` — kutish vaqti serverning `waiting_seconds` va javob kelgan
paytdan sanaladi (planshet soati noto'g'ri bo'lsa ham to'g'ri). Qaysi buyurtma qachon "tayyor" bo'lishi — serverda.

### Real-time: `PusherClient`

Laravel Reverb (Pusher protokoli 7) uchun kichik klient (`web_socket_channel` ustida). Nega o'zimizniki: bizga bitta
private kanal, imzo **terminal tokeni** bilan, boshqariladigan qayta ulanish va testlarda soxta soket kerak; tayyor Pusher
paketlari buni (ayniqsa imzo va soxta ulanishni) qiyinlashtiradi.

```dart
final PusherClient rt = PusherClient(
  uri: realtimeUri(apiBaseUrl: 'https://api.example.uz/api', appKey: reverbKey),   // wss://api.example.uz/app/<key>?protocol=7
  headers: {'Origin': originOf('https://api.example.uz/api')},                    // Reverb allowed_origins
  connector: ioRealtimeConnector,
  authorize: client.channelAuth.terminal,          // POST /api/broadcasting/auth, terminal tokeni bilan
)..subscribe('private-pos-branch.3')..connect();
rt.events.listen((e) => refetch());                // hodisada faqat id va holat: ro'yxatni API'dan qayta so'rang
rt.statuses.listen((s) { if (s == RealtimeStatus.connected) refetch(); });   // qayta ulanganda ham
```

Uzilsa `Backoff` bo'yicha (1, 2, 4 ... 30 s, ±20%) o'zi qayta ulanadi va kanallarga qayta obuna bo'ladi; server
`activity_timeout` jim tursa `pusher:ping`, 30 s ichida javob bo'lmasa ulanishni yangilaydi; imzo olinmasa obunani
qayta so'raydi. Real-time kafolat emas (docs/API.md, 8-bo'lim): ilova baribir polling ham qiladi.

## Mavzu va rang

`rizqim_ui` tokenlari `docs/DESIGN.md` jadvalidan (`RzColors.light` / `RzColors.dark`). `context.rz` orqali olinadi.
`theme_contrast_test.dart` hujjatdagi kontrast sonlarini tekshiradi (matn juftliklari ≥ 4.5). Bosiladigan elementlar
kamida 44px (`RzButton` `lg` — 52px), eng kichik shrift — 12.
