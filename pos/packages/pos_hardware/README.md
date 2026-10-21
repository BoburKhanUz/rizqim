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

Kanal shartnomasi: `lib/src/printer/sunmi_transport.dart` boshidagi jadval.

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
boshiga o'tadi. Diqqat: printer baytlarni olib javob bermasa, qayta urinish hujjatni ikki marta chiqarishi mumkin.

## Sinov

```bash
cd pos/packages/pos_hardware && flutter test
```

`package:pos_hardware/testing.dart`: `MemoryPrinterTransport` (soxta printer, xato va holatni o'ynaydi),
`LanPrinterEmulator` (127.0.0.1 da 9100 printer: baytlarni yig'adi, `DLE EOT` ga javob beradi, qog'oz tugashini
ko'rsatadi), `EscPosDocument.parse(bytes)` (baytlarni buyruq va matnga ajratadi).
