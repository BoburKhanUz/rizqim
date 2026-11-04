import 'package:flutter/services.dart';

import '../escpos/esc_pos_builder.dart';
import 'lan_transport.dart' show parseStatusBytes, parseTsplStatus;
import 'printer.dart';

/// Android plaginidagi printer (Bluetooth SPP yoki USB OTG) uchun umumiy qism: baytlarni kanal orqali yuboradi, holatni
/// printerning o'zidan so'raydi (`DLE EOT` yoki TSPL `ESC !?`) va kanal xatolarini [PrinterException] ga aylantiradi.
///
/// ### Kanal xatolari (`PlatformException.code`, ikkala kanalda bir xil)
///
/// | Kod | Ma'nosi | [PrinterErrorKind] |
/// |---|---|---|
/// | `unsupported` | qurilmada Bluetooth yoki USB host yo'q | `unsupported` |
/// | `permission_denied` | ruxsat berilmagan (Dart bir marta so'raydi, so'ng qayta urinadi) | `unsupported` |
/// | `bluetooth_off` | Bluetooth o'chiq | `offline` |
/// | `not_found` | printer juftlanmagan (Bluetooth) yoki ulanmagan (USB) | Bluetooth `unsupported`, USB `offline` |
/// | `connect_failed` | ulanib bo'lmadi (printer o'chiq, uzoqda, boshqa telefon ulangan) | `offline` |
/// | `write_failed` | yozish uzildi | `failed` |
/// | `timeout` | printer javob bermadi | `timeout` |
/// | `failed` | boshqa | `failed` |
///
/// Holat so'rovi (`query`) javob bermasa (arzon printerlar `DLE EOT` ni bilmaydi), keyingi chop etishlarda qog'oz
/// tekshiruvi o'tkazib yuboriladi: har chek yarim soniya kutmasin.
abstract class NativePrinterTransport implements PrinterTransport {
  NativePrinterTransport({
    required this.channel,
    this.language = PrinterLanguage.escpos,
    this.statusTimeout = const Duration(milliseconds: 600),
    this.checkPaperBeforePrint = true,
  });

  final MethodChannel channel;
  final PrinterLanguage language;
  final Duration statusTimeout;
  final bool checkPaperBeforePrint;

  /// Printer holat so'roviga javob beradimi (`null` — hali so'ralmagan).
  bool? answersStatus;

  /// Kanal chaqiruvlariga qo'shiladigan manzil (`{'address': mac}` yoki `{'device_id': id}`).
  Map<String, Object?> get addressArgs;

  /// "Bluetooth printer", "USB printer" (xato matnlari uchun).
  String get label;

  /// Kanal xato kodi uchun o'zbekcha xato; `null` — umumiy matn.
  PrinterException? describeError(String code, String? message);

  @override
  Future<void> send(Uint8List bytes) async {
    if (checkPaperBeforePrint && answersStatus != false) {
      final PrinterStatus s = await _query();
      if (s == PrinterStatus.paperOut) {
        throw const PrinterException(
          PrinterErrorKind.paperOut,
          "Qog'oz tugadi. Yangi rulon qo'ying va qayta chop eting.",
        );
      }
      if (s == PrinterStatus.coverOpen) {
        throw const PrinterException(
          PrinterErrorKind.coverOpen,
          "Printer qopqog'i ochiq. Yoping va qayta chop eting.",
        );
      }
    }
    await invoke<void>('send', <String, Object?>{'bytes': bytes});
  }

  @override
  Future<PrinterStatus> status() async {
    try {
      return await _query();
    } on PrinterException catch (e) {
      return switch (e.kind) {
        PrinterErrorKind.offline ||
        PrinterErrorKind.unsupported => PrinterStatus.offline,
        _ => PrinterStatus.unknown,
      };
    }
  }

  /// Kassa qutisi qog'ozga bog'liq emas: tekshiruvsiz yuboriladi (qog'oz tugagan printer ham qutini ochadi).
  @override
  Future<void> openDrawer(Uint8List kickBytes) =>
      invoke<void>('send', <String, Object?>{'bytes': kickBytes});

  @override
  Future<void> close() async {}

  Future<PrinterStatus> _query() async {
    final bool tspl = language == PrinterLanguage.tspl;
    final Uint8List request = tspl
        ? Uint8List.fromList(const <int>[0x1B, 0x21, 0x3F])
        : (EscPosBuilder()
                ..statusRequest(2)
                ..statusRequest(4))
              .build();
    final int expect = tspl ? 1 : 2;
    final Uint8List? answer = await invoke<Uint8List>(
      'query',
      <String, Object?>{
        'bytes': request,
        'expect': expect,
        'timeout_ms': statusTimeout.inMilliseconds,
      },
    );
    if (answer == null || answer.length < expect) {
      answersStatus = false;
      return PrinterStatus.unknown;
    }
    answersStatus = true;
    final int n = answer.length;
    // Oxirgi baytlar — javob (oldin printer boshqa narsa yuborgan bo'lishi mumkin)
    return tspl
        ? parseTsplStatus(answer[n - 1])
        : parseStatusBytes(offline: answer[n - 2], paper: answer[n - 1]);
  }

  /// Kanal chaqiruvi: ruxsat yo'q bo'lsa bir marta so'raydi va qayta urinadi; xatolar [PrinterException].
  Future<T?> invoke<T>(String method, Map<String, Object?> args) async {
    final Map<String, Object?> all = <String, Object?>{...addressArgs, ...args};
    try {
      return await channel.invokeMethod<T>(method, all);
    } on MissingPluginException {
      throw PrinterException(
        PrinterErrorKind.unsupported,
        '$label faqat Android qurilmasida ishlaydi.',
      );
    } on PlatformException catch (e) {
      if (e.code == 'permission_denied' && await _requestPermission()) {
        try {
          return await channel.invokeMethod<T>(method, all);
        } on PlatformException catch (again) {
          throw mapError(again);
        }
      }
      throw mapError(e);
    }
  }

  Future<bool> _requestPermission() async {
    try {
      return await channel.invokeMethod<bool>(
            'requestPermission',
            addressArgs,
          ) ??
          false;
    } on PlatformException {
      return false;
    } on MissingPluginException {
      return false;
    }
  }

  /// Kanal xatosidan [PrinterException].
  PrinterException mapError(PlatformException e) {
    final PrinterException? specific = describeError(e.code, e.message);
    if (specific != null) return specific;
    final String detail = (e.message ?? '').trim();
    return switch (e.code) {
      'unsupported' => PrinterException(
        PrinterErrorKind.unsupported,
        '$label bu qurilmada ishlamaydi.',
      ),
      'connect_failed' => PrinterException(
        PrinterErrorKind.offline,
        "${label}ga ulanib bo'lmadi. Printer yoniqmi va yaqinmi?${detail.isEmpty ? '' : ' ($detail)'}",
      ),
      'write_failed' => PrinterException(
        PrinterErrorKind.failed,
        '$label bilan aloqa uzildi${detail.isEmpty ? '' : ': $detail'}',
      ),
      'timeout' => const PrinterException(
        PrinterErrorKind.timeout,
        'Printer javob bermadi (vaqt tugadi).',
      ),
      _ => PrinterException(
        PrinterErrorKind.failed,
        '$label xatosi: ${detail.isEmpty ? e.code : detail}',
      ),
    };
  }
}

/// Bluetooth (klassik, SPP) printer: kanal `uz.rizqim.pos_hardware/bluetooth` (Android plagini `BluetoothPrinters.kt`).
///
/// | Usul | Argument | Natija |
/// |---|---|---|
/// | `state` | — | `{'supported': bool, 'enabled': bool, 'permission': bool}` |
/// | `requestPermission` | — | `bool` (Android 12+: `BLUETOOTH_CONNECT`, `BLUETOOTH_SCAN`; eskisida doim `true`) |
/// | `bondedDevices` | — | `[{'name', 'address', 'device_class': int, 'major_class': int}]` |
/// | `send` | `{'address', 'bytes'}` | `null` (ulanish ochiq qoladi; uzilgan bo'lsa bir marta qayta ulanadi) |
/// | `query` | `{'address', 'bytes', 'expect': int, 'timeout_ms': int}` | `Uint8List?` (kelgan javob) |
/// | `disconnect` | `{'address'}` | `null` |
class BluetoothPrinterTransport extends NativePrinterTransport {
  BluetoothPrinterTransport(
    this.target, {
    MethodChannel? channel,
    super.language,
    super.statusTimeout,
    super.checkPaperBeforePrint,
  }) : super(channel: channel ?? defaultChannel);

  static const MethodChannel defaultChannel = MethodChannel(
    'uz.rizqim.pos_hardware/bluetooth',
  );

  final BluetoothPrinterTarget target;

  @override
  Map<String, Object?> get addressArgs => <String, Object?>{
    'address': target.address,
  };

  @override
  String get label => 'Bluetooth printer';

  @override
  PrinterException? describeError(
    String code,
    String? message,
  ) => switch (code) {
    'permission_denied' => const PrinterException(
      PrinterErrorKind.unsupported,
      'Bluetooth ruxsati berilmagan. Telefon sozlamalarida Kassa ilovasiga «Yaqin-atrofdagi qurilmalar» '
      'ruxsatini bering.',
    ),
    'bluetooth_off' => const PrinterException(
      PrinterErrorKind.offline,
      "Bluetooth o'chiq. Yoqing va qayta urining.",
    ),
    'not_found' => PrinterException(
      PrinterErrorKind.unsupported,
      'Printer juftlanmagan (${target.address}). Telefonning Bluetooth sozlamalarida printerni juftlang '
      '(PIN odatda 0000 yoki 1234).',
    ),
    _ => null,
  };

  /// Ochiq Bluetooth ulanishini yopadi (keyingi chop etish qayta ulanadi).
  @override
  Future<void> close() async {
    try {
      await channel.invokeMethod<void>('disconnect', addressArgs);
    } on Object {
      // Allaqachon uzilgan yoki plagin yo'q
    }
  }
}

/// USB OTG printer (Android): kanal `uz.rizqim.pos_hardware/usb` (Android plagini `UsbPrinters.kt`).
///
/// | Usul | Argument | Natija |
/// |---|---|---|
/// | `devices` | — | `[{'device_id', 'device_name', 'vendor_id', 'product_id', 'manufacturer', 'product', 'printer_class': bool, 'bulk_out': bool, 'has_permission': bool}]` |
/// | `requestPermission` | `{'device_id'}` | `bool` (tizim oynasi: "Ruxsat berish") |
/// | `send` | `{'device_id', 'bytes'}` | `null` (bulk OUT, bo'laklab) |
/// | `query` | `{'device_id', 'bytes', 'expect', 'timeout_ms'}` | `Uint8List?` (bulk IN bo'lsa) |
///
/// `device_id`: `vvvv:pppp` yoki `vvvv:pppp:<seriya>` (16 lik), tizim nomi (`/dev/bus/usb/...`) yoki bo'sh (birinchi
/// USB printer).
class UsbPrinterTransport extends NativePrinterTransport {
  UsbPrinterTransport(
    this.target, {
    MethodChannel? channel,
    super.language,
    super.statusTimeout,
    super.checkPaperBeforePrint,
  }) : super(channel: channel ?? defaultChannel);

  static const MethodChannel defaultChannel = MethodChannel(
    'uz.rizqim.pos_hardware/usb',
  );

  final UsbPrinterTarget target;

  @override
  Map<String, Object?> get addressArgs => <String, Object?>{
    'device_id': target.deviceId,
  };

  @override
  String get label => 'USB printer';

  @override
  PrinterException? describeError(String code, String? message) =>
      switch (code) {
        'permission_denied' => const PrinterException(
          PrinterErrorKind.unsupported,
          'USB printerga ruxsat berilmadi. Kabelni qayta ulang va chiqqan oynada «Ruxsat berish» ni bosing.',
        ),
        'not_found' => const PrinterException(
          PrinterErrorKind.offline,
          'USB printer ulanmagan. Kabel va OTG adapterni tekshiring.',
        ),
        'unsupported' => const PrinterException(
          PrinterErrorKind.unsupported,
          "Bu qurilma USB printerni qo'llab-quvvatlamaydi (USB host yo'q).",
        ),
        _ => null,
      };
}
