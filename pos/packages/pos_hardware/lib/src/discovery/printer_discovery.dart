import 'dart:async';
import 'dart:io';

import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';
import 'package:pos_core/pos_core.dart';

import '../printer/native_transport.dart';
import '../printer/printer.dart';
import '../printer/sunmi_transport.dart';
import '../printer/windows_spooler.dart';

/// Printer qanday topildi.
enum DiscoveryKind {
  lan('Tarmoq (LAN)'),
  bluetooth('Bluetooth'),
  usb('USB'),
  sunmi('Ichki printer'),

  /// Windows'da o'rnatilgan printerlar (spooler: USB, tarmoq yoki virtual).
  windows('Windows printerlari');

  const DiscoveryKind(this.label);

  final String label;
}

/// Topilgan printer (yoki printerga o'xshagan qurilma).
class DiscoveredPrinter {
  const DiscoveredPrinter({
    required this.kind,
    required this.target,
    required this.name,
    this.detail,
    this.likelyPrinter = true,
  });

  final DiscoveryKind kind;
  final PrinterTarget target;

  /// "192.168.1.50", "MPT-II", "Xprinter XP-58".
  final String name;

  /// Qo'shimcha: manzil, port, vendor:product.
  final String? detail;

  /// Printer ekani aniq (USB printer sinfi, Bluetooth "printer" sinfi yoki nomi; LAN 9100 porti ochiq) — ro'yxatda
  /// birinchi; `false` — boshqa qurilma bo'lishi mumkin (quloqchin, klaviatura).
  final bool likelyPrinter;

  /// Sinov uchun printer sozlamasi (qurilmada; serverga yozilmaydi).
  PrinterConfig toConfig({
    PaperWidth paper = PaperWidth.mm80,
    PrinterLanguage language = PrinterLanguage.escpos,
    PrintTextMode textMode = PrintTextMode.auto,
  }) => PrinterConfig(
    id: 'discovered-${kind.name}-${target.description}',
    name: name,
    target: target,
    paper: paper,
    language: language,
    textMode: textMode,
  );

  @override
  bool operator ==(Object other) =>
      other is DiscoveredPrinter && other.target == target;

  @override
  int get hashCode => target.hashCode;

  @override
  String toString() => 'DiscoveredPrinter(${kind.name}, $name, $target)';
}

/// Qidiruvni to'xtatgan sabab (o'zbekcha; ekranda ko'rsatiladi).
class DiscoveryException implements Exception {
  const DiscoveryException(this.message);

  final String message;

  @override
  String toString() => 'DiscoveryException: $message';
}

/// TCP ulanishni sinab ko'radi: ulandi — `true`.
typedef PortProbe = Future<bool> Function(
  String host,
  int port,
  Duration timeout,
);

Future<bool> _tcpProbe(String host, int port, Duration timeout) async {
  try {
    final Socket s = await Socket.connect(host, port, timeout: timeout);
    s.destroy();
    return true;
  } on Object {
    return false;
  }
}

/// Printerni qidirish (kassa sozlamalari → "Printerni qidirish"):
///
/// * [scanLan] — qurilma ulangan /24 tarmoqda 9100 porti ochiq manzillar (sof Dart, qisqa kutish, bir vaqtda
///   [PrinterDiscovery.concurrency] tadan ko'p emas);
/// * [bluetoothPaired] — telefonda juftlangan Bluetooth qurilmalar (plagin);
/// * [usbDevices] — USB OTG qurilmalar (plagin);
/// * [sunmi] — Sunmi ichki printeri;
/// * [windowsPrinters] — Windows'da o'rnatilgan printerlar (spooler, `winspool.drv`).
class PrinterDiscovery {
  PrinterDiscovery({
    MethodChannel? bluetoothChannel,
    MethodChannel? usbChannel,
    SunmiPrinterTransport? sunmiTransport,
    bool? android,
    bool? windows,
    this._spooler,
    Future<List<String>> Function()? localAddresses,
    PortProbe? probe,
    this.concurrency = 48,
    this.lanTimeout = const Duration(milliseconds: 400),
  }) : _bluetooth =
           bluetoothChannel ?? BluetoothPrinterTransport.defaultChannel,
       _usb = usbChannel ?? UsbPrinterTransport.defaultChannel,
       _sunmi = sunmiTransport,
       _android =
           android ??
           (!kIsWeb && defaultTargetPlatform == TargetPlatform.android),
       // The host OS, not the target platform override: the spooler is reached through winspool.drv itself
       _windows = windows ?? (!kIsWeb && Platform.isWindows),
       _localAddresses = localAddresses ?? localIPv4Addresses,
       _probe = probe ?? _tcpProbe;

  final MethodChannel _bluetooth;
  final MethodChannel _usb;
  final SunmiPrinterTransport? _sunmi;
  final bool _android;
  final bool _windows;
  final RawSpooler? _spooler;
  final Future<List<String>> Function() _localAddresses;
  final PortProbe _probe;

  /// Bir vaqtdagi TCP ulanishlar chegarasi (telefon va router bo'g'ilmasin).
  final int concurrency;

  /// Bitta manzilni kutish vaqti (LAN'da printer odatda 10–50 ms da javob beradi).
  final Duration lanTimeout;

  /// Bluetooth, USB va ichki printer qidiriladimi (faqat Android).
  bool get nativeSupported => _android;

  /// Windows printerlari ro'yxati bormi (faqat Windows).
  bool get windowsSupported => _windows;

  /// Tarmoqdagi printerlar: har topilgani darhol oqimga. [subnets] — `192.168.1` kabi uchta oktet (berilmasa —
  /// qurilmaning IPv4 manzillaridan); [onProgress] — tekshirilgan/jami manzillar.
  Stream<DiscoveredPrinter> scanLan({
    List<String>? subnets,
    List<int> ports = const <int>[LanPrinterTarget.defaultPort],
    void Function(int done, int total)? onProgress,
  }) {
    late final StreamController<DiscoveredPrinter> out;
    var canceled = false;
    Future<void> run() async {
      final List<String> own = await _localAddresses();
      final List<String> nets = subnets ?? subnetsOf(own);
      final List<(String, int)> work = <(String, int)>[
        for (final String net in nets)
          for (var i = 1; i <= 254; i++)
            if (!own.contains('$net.$i'))
              for (final int port in ports) ('$net.$i', port),
      ];
      var next = 0;
      var done = 0;
      onProgress?.call(0, work.length);
      Future<void> worker() async {
        while (!canceled && next < work.length) {
          final (String host, int port) = work[next++];
          final bool open = await _probe(host, port, lanTimeout);
          done++;
          onProgress?.call(done, work.length);
          if (open && !canceled) {
            out.add(
              DiscoveredPrinter(
                kind: DiscoveryKind.lan,
                target: LanPrinterTarget(host, port: port),
                name: host,
                detail: 'Port $port',
              ),
            );
          }
        }
      }

      await Future.wait(<Future<void>>[
        for (var w = 0; w < concurrency && w < work.length; w++) worker(),
      ]);
    }

    out = StreamController<DiscoveredPrinter>(
      onListen: () {
        run().then(
          (_) => out.close(),
          onError: (Object e, StackTrace st) {
            out
              ..addError(e, st)
              ..close();
          },
        );
      },
      onCancel: () => canceled = true,
    );
    return out.stream;
  }

  /// Juftlangan Bluetooth qurilmalar: printerlar birinchi. Ruxsat yo'q bo'lsa bir marta so'raydi.
  Future<List<DiscoveredPrinter>> bluetoothPaired() async {
    if (!_android) return const <DiscoveredPrinter>[];
    final List<Object?> raw = await _call(
      _bluetooth,
      'bondedDevices',
      const <String, Object?>{},
      bluetooth: true,
    );
    final List<DiscoveredPrinter> found = <DiscoveredPrinter>[
      for (final Object? d in raw)
        if (d is Map && '${d['address'] ?? ''}'.isNotEmpty)
          DiscoveredPrinter(
            kind: DiscoveryKind.bluetooth,
            target: BluetoothPrinterTarget('${d['address']}'),
            name: '${d['name'] ?? d['address']}',
            detail: '${d['address']}',
            likelyPrinter: isLikelyBluetoothPrinter(
              name: d['name'] as String?,
              deviceClass: (d['device_class'] as num?)?.toInt(),
            ),
          ),
    ];
    return _printersFirst(found);
  }

  /// USB qurilmalar (bulk OUT endpointi borlari): printer sinfidagilar birinchi.
  Future<List<DiscoveredPrinter>> usbDevices() async {
    if (!_android) return const <DiscoveredPrinter>[];
    final List<Object?> raw = await _call(
      _usb,
      'devices',
      const <String, Object?>{},
      bluetooth: false,
    );
    final List<DiscoveredPrinter> found = <DiscoveredPrinter>[
      for (final Object? d in raw)
        if (d is Map && d['bulk_out'] != false)
          DiscoveredPrinter(
            kind: DiscoveryKind.usb,
            target: UsbPrinterTarget('${d['device_id'] ?? ''}'),
            name: _usbName(d),
            detail: '${d['device_id'] ?? ''}',
            likelyPrinter: d['printer_class'] == true,
          ),
    ];
    return _printersFirst(found);
  }

  /// Sunmi ichki printeri (bo'lsa).
  Future<DiscoveredPrinter?> sunmi() async {
    if (!_android) return null;
    final SunmiPrinterTransport t = _sunmi ?? SunmiPrinterTransport();
    if (!await t.isAvailable()) return null;
    String name = 'Sunmi ichki printeri';
    try {
      final Map<String, Object?> info = await t.info();
      final Object? model = info['model'];
      if (model is String && model.trim().isNotEmpty) {
        name = 'Sunmi ${model.trim()}';
      }
    } on Object {
      // Model noma'lum: umumiy nom
    }
    return DiscoveredPrinter(
      kind: DiscoveryKind.sunmi,
      target: const SunmiPrinterTarget(),
      name: name,
    );
  }

  /// Windows'da o'rnatilgan printerlar ([RawSpooler.printers]): chek printerlari birinchi, virtual printerlar
  /// ("Microsoft Print to PDF", "OneNote", faks) oxirida va "printer emasdir" belgisi bilan. Spooler ishlamasa —
  /// [DiscoveryException] tushunarli matn bilan.
  Future<List<DiscoveredPrinter>> windowsPrinters() async {
    if (!_windows) return const <DiscoveredPrinter>[];
    final RawSpooler spooler = _spooler ?? const FfiRawSpooler();
    final List<String> names;
    try {
      names = await spooler.printers();
    } on Object catch (e) {
      throw DiscoveryException(
        "Windows printerlarini o'qib bo'lmadi: ${e is PrinterException ? e.message : e}. "
        "«Chop etish navbati» (Print Spooler) xizmati ishlayaptimi? Windows sozlamalari → Bluetooth va qurilmalar → "
        "Printerlar va skanerlar bo'limini tekshiring.",
      );
    }
    final List<DiscoveredPrinter> found = <DiscoveredPrinter>[
      for (final String name in names.toSet())
        if (name.trim().isNotEmpty)
          DiscoveredPrinter(
            kind: DiscoveryKind.windows,
            target: WindowsPrinterTarget(name),
            name: name,
            detail: 'Windows drayveri orqali',
            likelyPrinter: !isVirtualWindowsPrinter(name),
          ),
    ];
    return _printersFirst(found);
  }

  Future<List<Object?>> _call(
    MethodChannel channel,
    String method,
    Map<String, Object?> args, {
    required bool bluetooth,
  }) async {
    Future<List<Object?>> once() async =>
        await channel.invokeMethod<List<Object?>>(method, args) ??
        const <Object?>[];
    try {
      return await once();
    } on MissingPluginException {
      return const <Object?>[];
    } on PlatformException catch (e) {
      if (e.code == 'permission_denied' && bluetooth) {
        bool granted = false;
        try {
          granted =
              await channel.invokeMethod<bool>('requestPermission') ?? false;
        } on PlatformException {
          granted = false;
        }
        if (granted) {
          try {
            return await once();
          } on PlatformException catch (again) {
            throw DiscoveryException(_message(again.code, bluetooth));
          }
        }
      }
      throw DiscoveryException(_message(e.code, bluetooth));
    }
  }

  static String _message(String code, bool bluetooth) => switch (code) {
    'permission_denied' =>
      bluetooth
          ? 'Bluetooth ruxsati berilmagan. Telefon sozlamalarida Kassa ilovasiga «Yaqin-atrofdagi qurilmalar» '
                'ruxsatini bering.'
          : 'USB qurilmalarni ko\'rishga ruxsat yo\'q.',
    'bluetooth_off' => "Bluetooth o'chiq. Yoqing va qayta qidiring.",
    'unsupported' =>
      bluetooth
          ? "Bu qurilmada Bluetooth yo'q."
          : "Bu qurilma USB printerni qo'llab-quvvatlamaydi.",
    _ => "Qidirib bo'lmadi ($code).",
  };

  static String _usbName(Map<Object?, Object?> d) {
    final String product = '${d['product'] ?? ''}'.trim();
    final String maker = '${d['manufacturer'] ?? ''}'.trim();
    final String name = <String>[
      if (maker.isNotEmpty) maker,
      if (product.isNotEmpty) product,
    ].join(' ');
    return name.isEmpty ? 'USB qurilma ${d['device_id'] ?? ''}'.trim() : name;
  }

  static List<DiscoveredPrinter> _printersFirst(List<DiscoveredPrinter> list) =>
      <DiscoveredPrinter>[
        ...list.where((DiscoveredPrinter p) => p.likelyPrinter),
        ...list.where((DiscoveredPrinter p) => !p.likelyPrinter),
      ];
}

/// Qurilmaning IPv4 manzillari (loopback va 169.254 dan tashqari).
Future<List<String>> localIPv4Addresses() async {
  try {
    final List<NetworkInterface> list = await NetworkInterface.list(
      type: InternetAddressType.IPv4,
    );
    return <String>[
      for (final NetworkInterface i in list)
        for (final InternetAddress a in i.addresses)
          if (!a.isLoopback && !a.isLinkLocal) a.address,
    ];
  } on Object {
    return const <String>[];
  }
}

/// IPv4 manzillardan /24 tarmoqlar (`192.168.1.23` → `192.168.1`), takrorlarsiz.
List<String> subnetsOf(List<String> addresses) {
  final List<String> out = <String>[];
  for (final String a in addresses) {
    final List<String> parts = a.split('.');
    if (parts.length != 4) continue;
    final String net = parts.take(3).join('.');
    if (!out.contains(net)) out.add(net);
  }
  return out;
}

/// Windows printeri qog'ozga chiqarmaydigan (virtual) printermi: PDF/XPS yozuvchilar, OneNote, faks, masofaviy
/// ish stoli orqali yo'naltirilgan printerlar. Ro'yxatda qoladi (oxirida), chunki nomdan aniq bilib bo'lmaydi.
bool isVirtualWindowsPrinter(String name) {
  final String n = name.toLowerCase();
  const List<String> hints = <String>[
    'pdf',
    'xps',
    'onenote',
    'fax',
    'факс',
    'document writer',
    'send to',
    '(redirected',
    'перенаправлен',
    'anydesk',
    'snagit',
  ];
  return hints.any(n.contains);
}

/// Bluetooth qurilma printerga o'xshaydimi: sinfi "Imaging / Printer" (0x0680 biti) yoki nomida printer belgilari.
bool isLikelyBluetoothPrinter({String? name, int? deviceClass}) {
  if (deviceClass != null &&
      (deviceClass & 0x1F00) == 0x0600 &&
      (deviceClass & 0x80) != 0) {
    return true;
  }
  final String n = (name ?? '').toLowerCase();
  const List<String> hints = <String>[
    'print',
    'pos',
    'mpt',
    'mtp',
    'xp-',
    'rpp',
    'pt-',
    'thermal',
    'bluetoothprinter',
    'innerprinter',
    'gprinter',
    'xprinter',
  ];
  return hints.any(n.contains);
}
