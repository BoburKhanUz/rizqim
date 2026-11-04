# pos_hardware

Printerlar va kassa qutisi (docs/POS_SPEC.md, 2.11–2.12). Chop etishni **qurilmaning o'zi** bajaradi (internet
uzilsa ham marka chiqadi). Hujjat `pos_core` da joylashtiriladi, bu paket uni baytlarga aylantirib yetkazadi.

```dart
final printers = PosPrinters();                       // navbat + chizuvchi
const kitchen = PrinterConfig(id: 'k1', name: 'Oshxona', target: LanPrinterTarget('192.168.1.50'));
await printers.print(kitchen, layoutStationTicket(ticket, kitchen.paper));
await printers.print(cashier, layoutFiscalReceipt(receipt, cashier.paper), openDrawer: true);
await printers.testPrint(kitchen);                    // sozlamadagi "Sinov chop etish"
printers.queue.stateChanges.listen((s) => ...);       // "Qog'oz tugadi", "Ulanmagan", navbatda N ta
printers.queue.retry(jobId);                          // "Chop etilmadi" ro'yxatidan qayta yuborish
```

## Printerlar (`PrinterTarget`)

| Tur | Qanday | Holat |
|---|---|---|
| `LanPrinterTarget(host, port: 9100)` | Raw TCP, har ish uchun yangi ulanish; yuborishdan oldin `DLE EOT` bilan qog'oz va qopqoq so'raladi | ishlaydi, emulyator bilan sinalgan |
| `WindowsPrinterTarget(name)` | USB printer Windows spooler orqali `RAW` turida (`winspool.drv`, dart:ffi, plagin kerak emas; alohida isolate'da) | Dart kodi tayyor, **Windows'da hali sinalmagan** (bu yerda Windows yo'q) |
| `SunmiPrinterTarget()` | Android plagini (`PosHardwarePlugin`, Sunmi `printerlibrary`), ESC/POS baytlari `sendRAWData` bilan | kanal shartnomasi va Dart tomoni sinalgan; Kotlin kodi `kotlinc` bilan Sunmi `printerlibrary:1.0.24`, Flutter embedding va Android stub'lariga qarshi kompilyatsiya qilingan, lekin APK yig'ilmagan va Sunmi qurilmasida **hali sinalmagan** (bu muhitda Android SDK yo'q) |

| `BluetoothPrinterTarget(mac)` | Android: juftlangan klassik Bluetooth printer, SPP (RFCOMM) orqali raw baytlar; ulanish ishlar orasida ochiq turadi, uzilsa bir marta qayta ulanadi; Android 12+ da "Yaqin-atrofdagi qurilmalar" ruxsati (`BLUETOOTH_CONNECT`) bir marta so'raladi | Dart tomoni soxta kanal bilan sinalgan; Kotlin `kotlinc` bilan API 34 va Flutter embedding'ga qarshi kompilyatsiya qilingan, APK CI `android-build` da yig'iladi; **qurilmada hali sinalmagan** |
| `UsbPrinterTarget(deviceId)` | Android: USB OTG (`UsbManager`, bulk OUT, 16 KB bo'laklar); `deviceId` — `vvvv:pppp`, `vvvv:pppp:<seriya>`, `/dev/bus/usb/...` yoki bo'sh (birinchi printer); ruxsat oynasi bir marta, kassa manifestida "har doim" uchun USB filtr | xuddi shunday: kompilyatsiya qilingan, **qurilmada sinalmagan** |

Kanal shartnomalari: `lib/src/printer/sunmi_transport.dart` va `lib/src/printer/native_transport.dart` boshidagi
jadvallar (xato kodlari ikkala native kanalda bir xil). Kassa qutisi har transport orqali ochiladi (`ESC p`; Sunmi o'z
usuli bilan), qog'oz tekshirilmaydi. Bluetooth/USB printer `DLE EOT` ga javob bermasa, keyingi ishlarda qog'oz
tekshiruvi o'tkazib yuboriladi.

## P6 uskunalar: qidirish, skaner, ikkinchi ekran

- **`PrinterDiscovery`** — `scanLan()` (qurilmaning /24 tarmog'ida 9100 porti; 400 ms kutish, bir vaqtda 48 tadan
  ko'p emas, sof Dart, emulyator bilan sinalgan), `bluetoothPaired()`, `usbDevices()`, `sunmi()` (plagin). Kassada:
  Sozlamalar → "Printerni qidirish" → printer → vazifa (chek, oshxona, yorliq) → "Sinov cheki" (`buildTestPage`:
  do'kon nomi, printer, kod sahifasi qatori, QR, kesish; yorliqda TSPL stiker) va "Pul qutisini ochish".
- **`BroadcastScanner`** — qurilmaning ichki skaneri (Sunmi `com.sunmi.scanner.ACTION_DATA_CODE_RECEIVED`; boshqa
  ishlab chiqaruvchilar uchun `ScanBroadcast` qo'shiladi), EventChannel `uz.rizqim.pos_hardware/scanner`. Klaviatura
  sifatida ulangan skaner — `pos_ui` dagi `KeyboardScanListener` (har platformada).
- **`CustomerScreen`** / **`CustomerScreenReceiver`** — Sunmi D2 turidagi ikkinchi ekran: plagin Android
  `Presentation` da plaginlarsiz ikkinchi Flutter dvigatelini ilovaning `customerScreenMain` funksiyasi bilan ishga
  tushiradi va kassa xabarlarini (`store`, `cart`) uzatadi; oxirgilari saqlanadi va ekran `hello` deganda qayta beriladi.

Android qismi (`android/src/main/kotlin/...`): `PosHardwarePlugin` (kanallarni ro'yxatdan o'tkazadi, faoliyat va
ruxsatlar), `SunmiPrinter`, `BluetoothPrinters`, `UsbPrinters`, `ScannerStream`, `CustomerScreen`. Bu muhitda Android
SDK yo'q: Kotlin CI'ning `android-build` jobida (har POS ilovasi uchun `flutter build apk --debug`) kompilyatsiya
qilinadi; haqiqiy qurilmadagi tekshiruv — `docs/HARDWARE_TEST.md`.

## ESC/POS va belgilar

- `EscPosBuilder`: init, belgilar jadvali (`ESC t`), tekislash, qalin, kattalik (1–8×), inversiya, kesish, kassa
  qutisi (`ESC p`), QR (`GS ( k`), rasm (`GS v 0`), holat so'rovi.
- Jadvallar: `cp866` (`ESC t 17`), `cp1251` (`ESC t 46`), `ascii`. Raqam printer modeliga bog'liq — sozlamada
  `codePage.withTable(n)`; sinov sahifasi bilan tekshiring.
- **Matnni rasm sifatida** (`PrintTextMode.auto`, standart): matnda jadvalda yo'q belgi bo'lsa (oʻ, gʻ, ʼ, Қ, Ғ, Ҳ)
  hujjat 1 bitli rasm bo'lib chiqadi (384/576 nuqta), QR va kesish esa oddiy buyruq. `text` — doim matn ("oʻ" →
  "o'"), `image` — doim rasm. Rasm uchun ilovaga ʻ belgisi bor monospace shrift qo'shish tavsiya etiladi
  (`PrinterConfig.fontFamily`); har belgi o'z katagiga chiziladi, shuning uchun ustunlar shrift qanday bo'lsa ham tekis.

## Navbat

Har printerga alohida ketma-ket navbat (printerlar parallel). Ulanmasa yoki vaqt tugasa 1, 3, 10 soniyadan keyin qayta
urinadi; qog'oz tugasa yoki qopqoq ochiq bo'lsa darhol "Chop etilmadi" va shu printer navbati to'xtaydi (keyingi
markalar tartibi buzilmaydi) — `retry` / `resume` bilan davom etadi (`autoResumeAfter` — o'zi). Kassa qutisi navbat
boshiga o'tadi, to'xtagan navbatda ham ochiladi va qog'oz tekshirilmaydi (LAN); ochilmasa keyinga qoldirilmaydi va
navbatni to'xtatmaydi. Diqqat: printer baytlarni olib javob bermasa, qayta urinish hujjatni ikki marta chiqarishi mumkin.

## Sinov

```bash
cd pos/packages/pos_hardware && flutter test
```

`package:pos_hardware/testing.dart`: `MemoryPrinterTransport` (soxta printer, xato va holatni o'ynaydi),
`LanPrinterEmulator` (127.0.0.1 da 9100 printer: baytlarni yig'adi, `DLE EOT` ga javob beradi, qog'oz tugashini
ko'rsatadi), `EscPosDocument.parse(bytes)` (baytlarni buyruq va matnga ajratadi).
