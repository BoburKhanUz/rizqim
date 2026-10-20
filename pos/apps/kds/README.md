# pos/apps/kds

Oshxona ekrani (KDS): barcha kanallardan kelgan buyurtmalar bitta ekranda (docs/ROADMAP.md, 2-bosqich).

Hozir bu **namunaviy skelet**: u Flutter steki (workspace, `rizqim_api`, `rizqim_core`, `rizqim_ui`) ishlashini ko'rsatadi.

1. **Ulash ekrani.** Egasi yoki menejer panelda "Qurilmalar → Terminal ulash" orqali 8 belgili kod oladi (`ABCD-EFGH`, 10 daqiqa
   amal qiladi). Kod chiziqcha bilan yoki usiz, kichik harf bilan ham kiritiladi; maydon uni o'zi `ABCD-EFGH` ko'rinishiga
   keltiradi. Noto'g'ri kod serverga yuborilmaydi; server rad etsa, uning matni ko'rsatiladi.
2. **Oshxona ekrani.** Ulangandan keyin: "Oshxona ekrani", do'kon va filial nomi, hozircha bo'sh ro'yxat. Terminal tokeni
   `flutter_secure_storage` da saqlanadi: qayta ishga tushganda qayta ulash shart emas. Yuqoridagi tugma terminalni uzadi.

Keyingi qadamlar (hali yo'q): buyurtmalar ro'yxati va real-time, xodim PIN kirishi, "tayyor" belgilash.

## Tayyorlash

Flutter 3.47.5 kerak. O'rnatish va workspace haqida: [`packages/dart/README.md`](../../../packages/dart/README.md).

```bash
flutter pub get          # repozitoriy ildizida (butun workspace uchun bir marta)
```

## Testlar

```bash
cd pos/apps/kds
flutter analyze
flutter test
```

Testlar backend'siz ishlaydi (HTTP soxtalashtirilgan): ulash kodini kiritish (chiziqcha bilan/siz), xato matni, qayta ishga
tushirgandan keyin ulanganlik, terminalni uzish, telefon va planshet o'lchamlarida overflow yo'qligi.

## Ishga tushirish

Backend manzili `--dart-define=RIZQIM_API_URL=...` bilan beriladi (`/api` bilan tugaydi; standart `http://localhost/api`).

### Linux

```bash
sudo apt install clang cmake ninja-build pkg-config libgtk-3-dev libsecret-1-dev libjsoncpp-dev
cd pos/apps/kds
flutter run -d linux --dart-define=RIZQIM_API_URL=http://localhost/api
flutter build linux --release        # build/linux/x64/release/bundle/
```

Tokenni saqlash uchun Linux'da kalitlar xizmati (`gnome-keyring` yoki KWallet, `libsecret` orqali) ishlab turishi kerak. U
bo'lmasa (masalan, serversiz muhit) ilova ishlaydi, lekin oshxona ekranida "Terminal tokeni qurilmada saqlanmadi" ogohlantirishi
chiqadi va qayta ishga tushirilsa qayta ulash kerak bo'ladi.

### Windows

Windows mashinasida (Visual Studio 2022, "Desktop development with C++" bilan):

```powershell
cd pos\apps\kds
flutter run -d windows --dart-define=RIZQIM_API_URL=https://staging.example.uz/api
flutter build windows --release      # build\windows\x64\runner\Release\
```

Token Windows Credential Locker/DPAPI da saqlanadi.

### Android (planshet)

Android SDK va qurilma yoki emulyator kerak (`flutter doctor`):

```bash
cd pos/apps/kds
flutter run -d <qurilma> --dart-define=RIZQIM_API_URL=https://staging.example.uz/api
flutter build apk --release
```

Emulyatorda kompyuterdagi backend manzili `http://10.0.2.2/api`. Ilova identifikatori `uz.rizqim.kds`. Release uchun
imzolash kaliti hali sozlanmagan (`android/app/build.gradle.kts` hozir debug kalitini ishlatadi).

## Eslatma

* Dart paketi nomi `rizqim_kds`; platforma papkalari (`android/`, `linux/`, `windows/`) `flutter create --project-name kds`
  bilan yaratilgan, shuning uchun identifikatorlar `uz.rizqim.kds`, Windows'da `kds.exe`.
* iOS/macOS/web bu ilova uchun yoqilmagan.
