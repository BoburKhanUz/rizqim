# packages/dart

Flutter ilovalari uchun umumiy Dart paketlari. Hammasi bitta **Dart workspace**da (pub workspaces): ildizdagi
`pubspec.yaml` a'zolarni sanaydi, bitta `pubspec.lock` va bitta `flutter pub get` hamma paketga yetadi.

| Paket | Nima | Flutter kerakmi |
|---|---|---|
| `rizqim_core` | Pul (`Money`, butun so'm, `25 000 so'm` ajralmas bo'shliq bilan), sana (`19-okt, 20:12`), buyurtma holatlari va rang guruhlari. Mini App (`customer/miniapp/src/lib/format.ts`, `orderStatus.ts`) bilan bir xil natija | yo'q (`dart test`) |
| `rizqim_api` | OpenAPI'dan yaratilgan klient + qo'lda yozilgan kirish qatlami (terminalni ulash, xodim PIN sessiyasi, refresh token aylanishi) | yo'q (`dart test`) |
| `rizqim_ui` | Material 3 mavzusi (Rizqim tokenlari, yorug' va qorong'i), `RzButton`, `StatusBadge`, `EmptyState`, `InlineError` | ha (`flutter test`) |

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

Linux'da ilovani yig'ish uchun: `sudo apt install clang cmake ninja-build pkg-config libgtk-3-dev libsecret-1-dev libjsoncpp-dev`.
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

Mini App hech narsani o'zgartirmaydi: u bitta tokenli `POST /auth/telegram` bilan ishlashda davom etadi.

## Mavzu va rang

`rizqim_ui` tokenlari `docs/DESIGN.md` jadvalidan (`RzColors.light` / `RzColors.dark`). `context.rz` orqali olinadi.
`theme_contrast_test.dart` hujjatdagi kontrast sonlarini tekshiradi (matn juftliklari ≥ 4.5). Bosiladigan elementlar
kamida 44px (`RzButton` `lg` — 52px), eng kichik shrift — 12.
