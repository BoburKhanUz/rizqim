import 'dart:async';

import 'package:flutter/material.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_hardware/pos_hardware.dart';
import 'package:rizqim_core/rizqim_core.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import '../../shell/pos_scope.dart';
import '../billing/cashier_ui.dart';

/// Printer nima uchun (sinov chekining turi).
enum DiscoveryPurpose {
  receipt('Chek'),
  kitchen('Oshxona'),
  label('Yorliq');

  const DiscoveryPurpose(this.title);

  final String title;
}

/// "Printerni qidirish" (P6 uskunalar): tarmoqdagi (9100 port), juftlangan Bluetooth, USB va ichki printerlar
/// ro'yxati → printerni tanlash → vazifa (chek, oshxona, yorliq) → "Sinov cheki" va "Pul qutisini ochish".
///
/// Printer shu yerda serverga qo'shilmaydi: ega uni boshqaruv panelida (Sozlamalar → Printerlar) ko'rsatilgan manzil
/// bilan qo'shadi; bu sahifa printer ishlashini va manzilini aniqlash uchun.
class PrinterDiscoveryPage extends StatefulWidget {
  const PrinterDiscoveryPage({super.key, this.autoScan = true});

  /// Ochilganda darhol qidiradi (testlar o'chirib qo'yadi).
  final bool autoScan;

  @override
  State<PrinterDiscoveryPage> createState() => _PrinterDiscoveryPageState();
}

class _PrinterDiscoveryPageState extends State<PrinterDiscoveryPage> {
  final List<DiscoveredPrinter> _lan = <DiscoveredPrinter>[];
  List<DiscoveredPrinter> _bluetooth = const <DiscoveredPrinter>[];
  List<DiscoveredPrinter> _usb = const <DiscoveredPrinter>[];
  DiscoveredPrinter? _sunmi;
  String? _bluetoothError;
  String? _usbError;
  StreamSubscription<DiscoveredPrinter>? _lanSub;
  bool _lanRunning = false;
  bool _nativeRunning = false;
  int _lanDone = 0;
  int _lanTotal = 0;
  bool _started = false;

  PrinterDiscovery get _discovery =>
      PosScope.of(context).services.hardware.discovery;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (!_started) {
      _started = true;
      if (widget.autoScan) unawaited(_scanAll());
    }
  }

  @override
  void dispose() {
    unawaited(_lanSub?.cancel());
    super.dispose();
  }

  Future<void> _scanAll() async {
    _scanLan();
    await _scanNative();
  }

  void _scanLan() {
    unawaited(_lanSub?.cancel());
    setState(() {
      _lan.clear();
      _lanRunning = true;
      _lanDone = 0;
      _lanTotal = 0;
    });
    _lanSub = _discovery
        .scanLan(
          onProgress: (int done, int total) {
            if (!mounted) return;
            setState(() {
              _lanDone = done;
              _lanTotal = total;
            });
          },
        )
        .listen(
          (DiscoveredPrinter p) {
            if (mounted) setState(() => _lan.add(p));
          },
          onError: (Object _) {},
          onDone: () {
            if (mounted) setState(() => _lanRunning = false);
          },
        );
  }

  Future<void> _scanNative() async {
    final PrinterDiscovery d = _discovery;
    if (!d.nativeSupported) return;
    setState(() => _nativeRunning = true);
    final (List<DiscoveredPrinter>, String?) bt = await _guard(
      d.bluetoothPaired,
    );
    final (List<DiscoveredPrinter>, String?) usb = await _guard(d.usbDevices);
    DiscoveredPrinter? sunmi;
    try {
      sunmi = await d.sunmi();
    } on Object {
      sunmi = null;
    }
    if (!mounted) return;
    setState(() {
      _bluetooth = bt.$1;
      _bluetoothError = bt.$2;
      _usb = usb.$1;
      _usbError = usb.$2;
      _sunmi = sunmi;
      _nativeRunning = false;
    });
  }

  static Future<(List<DiscoveredPrinter>, String?)> _guard(
    Future<List<DiscoveredPrinter>> Function() load,
  ) async {
    try {
      return (await load(), null);
    } on DiscoveryException catch (e) {
      return (const <DiscoveredPrinter>[], e.message);
    } on Object catch (e) {
      return (const <DiscoveredPrinter>[], "Qidirib bo'lmadi: $e");
    }
  }

  Future<void> _open(DiscoveredPrinter p) => showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    showDragHandle: true,
    builder: (BuildContext _) => PosScopeRelay(
      scope: PosScope.of(context),
      child: PrinterTestSheet(printer: p),
    ),
  );

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final bool native = _discovery.nativeSupported;
    return Scaffold(
      backgroundColor: c.page,
      appBar: AppBar(
        title: const Text('Printerni qidirish'),
        actions: <Widget>[
          IconButton(
            key: const ValueKey<String>('discovery-rescan'),
            tooltip: 'Qayta qidirish',
            onPressed: _lanRunning || _nativeRunning
                ? null
                : () => unawaited(_scanAll()),
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: <Widget>[
          PosCard(
            title: DiscoveryKind.lan.label,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: <Widget>[
                if (_lanRunning) ...<Widget>[
                  LinearProgressIndicator(
                    value: _lanTotal == 0 ? null : _lanDone / _lanTotal,
                  ),
                  const SizedBox(height: 8),
                  Text(
                    _lanTotal == 0
                        ? 'Tarmoq qidirilmoqda…'
                        : 'Tekshirildi: $_lanDone / $_lanTotal manzil (port 9100)',
                    style: RzType.footnote.copyWith(color: c.fgMuted),
                  ),
                ],
                if (!_lanRunning && _lan.isEmpty)
                  _Empty(
                    _lanTotal == 0
                        ? "Kassa Wi-Fi yoki kabel orqali tarmoqqa ulanmagan ko'rinadi."
                        : "Tarmoqda printer topilmadi. Printer yoniq va kassa bilan bitta Wi-Fi/routerga ulanganmi?",
                  ),
                for (final DiscoveredPrinter p in _lan) _row(p),
              ],
            ),
          ),
          if (native) ...<Widget>[
            const SizedBox(height: 16),
            if (_sunmi != null) ...<Widget>[
              PosCard(title: DiscoveryKind.sunmi.label, child: _row(_sunmi!)),
              const SizedBox(height: 16),
            ],
            _nativeCard(
              c,
              DiscoveryKind.bluetooth,
              _bluetooth,
              _bluetoothError,
              "Juftlangan Bluetooth printer yo'q. Telefon sozlamalari → Bluetooth bo'limida printerni juftlang "
              '(PIN odatda 0000 yoki 1234), so\'ng qayta qidiring.',
            ),
            const SizedBox(height: 16),
            _nativeCard(
              c,
              DiscoveryKind.usb,
              _usb,
              _usbError,
              'USB printer ulanmagan (OTG kabel orqali ulang).',
            ),
          ],
        ],
      ),
    );
  }

  Widget _nativeCard(
    RzColors c,
    DiscoveryKind kind,
    List<DiscoveredPrinter> list,
    String? error,
    String empty,
  ) => PosCard(
    title: kind.label,
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        if (_nativeRunning) const LinearProgressIndicator(),
        if (error != null)
          NoticeBanner(message: error, tone: StatusTone.warning)
        else if (!_nativeRunning && list.isEmpty)
          _Empty(empty),
        for (final DiscoveredPrinter p in list) _row(p),
      ],
    ),
  );

  Widget _row(DiscoveredPrinter p) {
    final RzColors c = context.rz;
    final IconData icon = switch (p.kind) {
      DiscoveryKind.lan => Icons.lan_outlined,
      DiscoveryKind.bluetooth => Icons.bluetooth_rounded,
      DiscoveryKind.usb => Icons.usb_rounded,
      DiscoveryKind.sunmi => Icons.point_of_sale_rounded,
    };
    return ListTile(
      key: ValueKey<String>('discovered-${p.target.description}'),
      contentPadding: EdgeInsets.zero,
      minTileHeight: 56,
      leading: Icon(icon, color: p.likelyPrinter ? c.brand : c.fgMuted),
      title: Text(p.name, style: RzType.headline.copyWith(color: c.fg)),
      subtitle: Text(
        <String>[
          if (p.detail != null && p.detail != p.name) p.detail!,
          if (!p.likelyPrinter) 'printer emasdir',
        ].join(' · '),
        style: RzType.footnote.copyWith(color: c.fgMuted),
      ),
      trailing: const Icon(Icons.chevron_right_rounded),
      onTap: () => unawaited(_open(p)),
    );
  }
}

class _Empty extends StatelessWidget {
  const _Empty(this.text);

  final String text;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 4),
    child: Text(text, style: RzType.body.copyWith(color: context.rz.fgMuted)),
  );
}

/// Pastdan ochiladigan oyna (yangi route) ham [PosScope] ichida bo'lsin.
class PosScopeRelay extends StatelessWidget {
  const PosScopeRelay({super.key, required this.scope, required this.child});

  final PosScope scope;
  final Widget child;

  @override
  Widget build(BuildContext context) => PosScope(
    services: scope.services,
    realtime: scope.realtime,
    navigate: scope.navigate,
    child: child,
  );
}

/// Tanlangan printer: vazifa va qog'oz eni, "Sinov cheki", "Pul qutisini ochish" va panelda kiritiladigan manzil.
class PrinterTestSheet extends StatefulWidget {
  const PrinterTestSheet({super.key, required this.printer});

  final DiscoveredPrinter printer;

  @override
  State<PrinterTestSheet> createState() => _PrinterTestSheetState();
}

class _PrinterTestSheetState extends State<PrinterTestSheet> {
  DiscoveryPurpose _purpose = DiscoveryPurpose.receipt;
  int _paper = 80;
  bool _busy = false;
  String? _result;
  bool _ok = true;

  PrinterConfig get _config => widget.printer.toConfig(
    paper: PaperWidth.fromMillimetres(_paper),
    language: _purpose == DiscoveryPurpose.label
        ? PrinterLanguage.tspl
        : PrinterLanguage.escpos,
  );

  Future<void> _run(Future<PrintJob> Function(PosPrinters p) start) async {
    final PosScope scope = PosScope.of(context);
    setState(() {
      _busy = true;
      _result = null;
    });
    try {
      final PosPrinters printers = scope.printing.printers;
      final PrintJob job = await start(printers);
      final PrintJob done = await printers.queue.whenSettledOrFailed(job);
      if (!mounted) return;
      setState(() {
        _ok = done.status == PrintJobStatus.done;
        _result = _ok
            ? 'Yuborildi: ${job.title}'
            : 'Chiqmadi: ${done.lastError ?? done.status.label}';
      });
      // A failed test job must not hold the lane: the next try starts clean
      if (!_ok) printers.queue.cancel(done.id);
    } on Object catch (e) {
      if (mounted) {
        setState(() {
          _ok = false;
          _result = 'Chiqmadi: $e';
        });
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _testPrint() {
    final PosScope scope = PosScope.of(context);
    final DateTime now = scope.services.now().toLocal();
    final PrinterConfig config = _config;
    if (_purpose == DiscoveryPurpose.label) {
      return _run(
        (PosPrinters p) => p.printLabel(
          config,
          layoutCupSticker(
            CupSticker(
              number: '1',
              itemName: 'Sinov stikeri',
              variantName: widget.printer.name,
              modifiers: const <String>['Printer ishlayapti'],
              printedAt: now,
            ),
            scope.session.bootstrap?.labelSettings?.size ?? LabelSize.mm40x30,
          ),
        ),
      );
    }
    return _run(
      (PosPrinters p) =>
          p.testPrint(config, now: now, storeName: scope.session.storeName),
    );
  }

  Future<void> _drawer() =>
      _run((PosPrinters p) async => p.openDrawer(_config));

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final DiscoveredPrinter p = widget.printer;
    final String? address = switch (p.target) {
      final LanPrinterTarget t =>
        t.port == LanPrinterTarget.defaultPort ? t.host : '${t.host}:${t.port}',
      final BluetoothPrinterTarget t => t.address,
      _ => null,
    };
    return SafeArea(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(20, 0, 20, 20),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: <Widget>[
            Text(p.name, style: RzType.title.copyWith(color: c.fg)),
            const SizedBox(height: 4),
            Text(
              '${p.kind.label} · ${p.target.description}',
              style: RzType.footnote.copyWith(color: c.fgMuted),
            ),
            const SizedBox(height: 16),
            Text('Vazifasi', style: RzType.body.copyWith(color: c.fgMuted)),
            const SizedBox(height: 6),
            SegmentedButton<DiscoveryPurpose>(
              key: const ValueKey<String>('discovery-purpose'),
              segments: <ButtonSegment<DiscoveryPurpose>>[
                for (final DiscoveryPurpose v in DiscoveryPurpose.values)
                  ButtonSegment<DiscoveryPurpose>(
                    value: v,
                    label: Text(v.title),
                  ),
              ],
              selected: <DiscoveryPurpose>{_purpose},
              showSelectedIcon: false,
              style: SegmentedButton.styleFrom(minimumSize: const Size(0, 48)),
              onSelectionChanged: (Set<DiscoveryPurpose> s) =>
                  setState(() => _purpose = s.first),
            ),
            if (_purpose != DiscoveryPurpose.label) ...<Widget>[
              const SizedBox(height: 12),
              SegmentedButton<int>(
                segments: const <ButtonSegment<int>>[
                  ButtonSegment<int>(value: 58, label: Text('58 mm')),
                  ButtonSegment<int>(value: 80, label: Text('80 mm')),
                ],
                selected: <int>{_paper},
                showSelectedIcon: false,
                style: SegmentedButton.styleFrom(
                  minimumSize: const Size(0, 48),
                ),
                onSelectionChanged: (Set<int> s) =>
                    setState(() => _paper = s.first),
              ),
            ],
            const SizedBox(height: 16),
            Wrap(
              spacing: 12,
              runSpacing: 12,
              children: <Widget>[
                RzButton(
                  key: const ValueKey<String>('discovery-test-print'),
                  label: 'Sinov cheki',
                  icon: Icons.print_rounded,
                  loading: _busy,
                  onPressed: _busy ? null : () => unawaited(_testPrint()),
                ),
                if (_purpose == DiscoveryPurpose.receipt)
                  RzButton(
                    key: const ValueKey<String>('discovery-drawer'),
                    label: 'Pul qutisini ochish',
                    icon: Icons.point_of_sale_rounded,
                    variant: RzButtonVariant.secondary,
                    onPressed: _busy ? null : () => unawaited(_drawer()),
                  ),
              ],
            ),
            if (_result != null) ...<Widget>[
              const SizedBox(height: 12),
              NoticeBanner(
                message: _result!,
                tone: _ok ? StatusTone.success : StatusTone.danger,
              ),
            ],
            if (address != null) ...<Widget>[
              const SizedBox(height: 16),
              Text(
                "Doimiy ishlatish uchun boshqaruv panelida (Sozlamalar → Printerlar) printer qo'shing: ulanish — "
                "${p.kind == DiscoveryKind.lan ? 'LAN' : 'Bluetooth'}, manzil — $address.",
                style: RzType.footnote.copyWith(color: c.fgMuted),
              ),
            ],
          ],
        ),
      ),
    );
  }
}
