import 'dart:typed_data';

import 'package:pos_core/pos_core.dart';

import '../escpos/code_page.dart';

/// Printer qayerda: LAN (raw TCP, odatda 9100), Windows'dagi printer (USB, spooler orqali raw), Sunmi ichki printeri.
sealed class PrinterTarget {
  const PrinterTarget();

  /// Sozlamadan (`toJson` natijasi). Noma'lum tur — `FormatException`.
  factory PrinterTarget.fromJson(Map<String, Object?> json) {
    switch (json['type']) {
      case 'lan':
        return LanPrinterTarget(
          '${json['host']}',
          port: (json['port'] as num?)?.toInt() ?? LanPrinterTarget.defaultPort,
        );
      case 'windows':
        return WindowsPrinterTarget('${json['printer_name']}');
      case 'sunmi':
        return const SunmiPrinterTarget();
    }
    throw FormatException('Unknown printer target', json['type']);
  }

  Map<String, Object?> toJson();

  /// Odam o'qiydigan tavsif ("LAN 192.168.1.50:9100").
  String get description;
}

/// Tarmoqdagi ESC/POS printer (Ethernet yoki Wi-Fi): raw TCP.
final class LanPrinterTarget extends PrinterTarget {
  const LanPrinterTarget(this.host, {this.port = defaultPort});

  static const int defaultPort = 9100;

  final String host;
  final int port;

  @override
  Map<String, Object?> toJson() => <String, Object?>{
    'type': 'lan',
    'host': host,
    'port': port,
  };

  @override
  String get description => 'LAN $host:$port';

  @override
  bool operator ==(Object other) =>
      other is LanPrinterTarget && other.host == host && other.port == port;

  @override
  int get hashCode => Object.hash(host, port);
}

/// Windows'ga o'rnatilgan printer (odatda USB): baytlar spooler orqali `RAW` turida yuboriladi, drayver ularni
/// o'zgartirmaydi. [printerName] — "Qurilmalar va printerlar" dagi nom.
final class WindowsPrinterTarget extends PrinterTarget {
  const WindowsPrinterTarget(this.printerName);

  final String printerName;

  @override
  Map<String, Object?> toJson() => <String, Object?>{
    'type': 'windows',
    'printer_name': printerName,
  };

  @override
  String get description => 'USB (Windows): $printerName';

  @override
  bool operator ==(Object other) =>
      other is WindowsPrinterTarget && other.printerName == printerName;

  @override
  int get hashCode => printerName.hashCode;
}

/// Sunmi (V2, T2, D2 ...) ichki printeri: platform kanali orqali `sendRAWData`.
final class SunmiPrinterTarget extends PrinterTarget {
  const SunmiPrinterTarget();

  @override
  Map<String, Object?> toJson() => <String, Object?>{'type': 'sunmi'};

  @override
  String get description => 'Sunmi ichki printeri';

  @override
  bool operator ==(Object other) => other is SunmiPrinterTarget;

  @override
  int get hashCode => 7;
}

/// Matn qanday chop etiladi.
enum PrintTextMode {
  /// Printer jadvalida hamma belgi bo'lsa matn, bo'lmasa (oʻ, gʻ, ʼ, Қ ...) butun hujjat rasm sifatida.
  auto,

  /// Doim matn: yo'q belgilar almashtiriladi ("oʻ" → "o'"). Eng tez.
  text,

  /// Doim rasm (printer shrifti yomon yoki jadval noto'g'ri bo'lsa).
  image,
}

/// Bitta printer sozlamasi.
final class PrinterConfig {
  const PrinterConfig({
    required this.id,
    required this.name,
    required this.target,
    this.paper = PaperWidth.mm80,
    this.codePage = EscPosCodePage.cp866,
    this.textMode = PrintTextMode.auto,
    this.hasCutter = true,
    this.drawerPin = 0,
    this.fontFamily,
  });

  factory PrinterConfig.fromJson(Map<String, Object?> json) {
    final EscPosCodePage page = EscPosCodePage.byName(
      json['code_page'] as String?,
    );
    final int? table = (json['code_page_table'] as num?)?.toInt();
    return PrinterConfig(
      id: '${json['id']}',
      name: '${json['name'] ?? json['id']}',
      target: PrinterTarget.fromJson(
        (json['target'] as Map<Object?, Object?>).cast<String, Object?>(),
      ),
      paper: PaperWidth.fromMillimetres((json['paper_mm'] as num?)?.toInt()),
      codePage: table == null ? page : page.withTable(table),
      textMode: PrintTextMode.values.firstWhere(
        (PrintTextMode m) => m.name == json['text_mode'],
        orElse: () => PrintTextMode.auto,
      ),
      hasCutter: json['has_cutter'] as bool? ?? true,
      drawerPin: (json['drawer_pin'] as num?)?.toInt() ?? 0,
      fontFamily: json['font_family'] as String?,
    );
  }

  /// Barqaror identifikator (backend `printers.id` yoki lokal uuid).
  final String id;

  /// "Oshxona", "Mangal", "Kassa".
  final String name;
  final PrinterTarget target;
  final PaperWidth paper;
  final EscPosCodePage codePage;
  final PrintTextMode textMode;

  /// Kesgichi yo'q printerda chek oxirida faqat qog'oz suriladi.
  final bool hasCutter;

  /// Kassa qutisi ulagichi oyog'i (0 — 2-oyoq, 1 — 5-oyoq).
  final int drawerPin;

  /// Rasm rejimidagi shrift (ilovaga qo'shilgan monospace shrift tavsiya etiladi); `null` — platforma standarti.
  final String? fontFamily;

  Map<String, Object?> toJson() => <String, Object?>{
    'id': id,
    'name': name,
    'target': target.toJson(),
    'paper_mm': paper == PaperWidth.mm58 ? 58 : 80,
    'code_page': codePage.name,
    'code_page_table': codePage.table,
    'text_mode': textMode.name,
    'has_cutter': hasCutter,
    'drawer_pin': drawerPin,
    if (fontFamily != null) 'font_family': fontFamily,
  };

  PrinterConfig copyWith({
    String? name,
    PrinterTarget? target,
    PaperWidth? paper,
    EscPosCodePage? codePage,
    PrintTextMode? textMode,
    bool? hasCutter,
    int? drawerPin,
  }) => PrinterConfig(
    id: id,
    name: name ?? this.name,
    target: target ?? this.target,
    paper: paper ?? this.paper,
    codePage: codePage ?? this.codePage,
    textMode: textMode ?? this.textMode,
    hasCutter: hasCutter ?? this.hasCutter,
    drawerPin: drawerPin ?? this.drawerPin,
    fontFamily: fontFamily,
  );
}

/// Printer holati (ekranda yorliq bilan).
enum PrinterStatus {
  ready('Tayyor'),
  paperNearEnd("Qog'oz tugayapti"),
  paperOut("Qog'oz tugadi"),
  coverOpen('Qopqoq ochiq'),
  offline('Ulanmagan'),
  error('Printer xatosi'),

  /// Printer holatni aytmaydi (javob bermadi), lekin ulanish bor.
  unknown("Holati noma'lum");

  const PrinterStatus(this.label);

  final String label;

  /// Chop etish mumkinmi (taxminan): tayyor, qog'oz tugayapti yoki noma'lum.
  bool get canPrint => this == ready || this == paperNearEnd || this == unknown;
}

/// Xato turi: navbat qaysisini o'zi qayta urinishini shunga qarab hal qiladi.
enum PrinterErrorKind {
  /// Ulanib bo'lmadi (o'chiq, tarmoq yo'q) — qayta uriniladi.
  offline(retryable: true),

  /// Javob kutish vaqti tugadi — qayta uriniladi.
  timeout(retryable: true),

  /// Qog'oz tugadi — odam aralashuvi kerak.
  paperOut(retryable: false),

  /// Qopqoq ochiq — odam aralashuvi kerak.
  coverOpen(retryable: false),

  /// Bu qurilmada ishlamaydi (masalan, Linux'da Windows printeri, Sunmi bo'lmagan qurilma).
  unsupported(retryable: false),

  /// Boshqa xato (drayver, spooler, printer xatosi) — qayta uriniladi.
  failed(retryable: true);

  const PrinterErrorKind({required this.retryable});

  final bool retryable;
}

class PrinterException implements Exception {
  const PrinterException(this.kind, this.message);

  final PrinterErrorKind kind;

  /// Ekranga chiqadigan o'zbekcha matn.
  final String message;

  bool get retryable => kind.retryable;

  @override
  String toString() => 'PrinterException(${kind.name}): $message';
}

/// Printerga baytlarni yetkazadigan qatlam. Bitta printer uchun navbat bitta vaqtda bitta [send] chaqiradi.
abstract interface class PrinterTransport {
  /// Baytlarni yuboradi. Xatoda [PrinterException].
  Future<void> send(Uint8List bytes);

  /// Holatni so'raydi (bilmasa [PrinterStatus.unknown]; ulanib bo'lmasa [PrinterStatus.offline]).
  Future<PrinterStatus> status();

  /// Kassa qutisini ochadi ([kickBytes] — `ESC p`; Sunmi o'z usulini ishlatadi).
  Future<void> openDrawer(Uint8List kickBytes);

  /// Ochiq resurslarni yopadi.
  Future<void> close();
}
