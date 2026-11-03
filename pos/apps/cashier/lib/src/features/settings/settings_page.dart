import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_hardware/pos_hardware.dart';
import 'package:rizqim_api/rizqim_api.dart';
import 'package:rizqim_core/rizqim_core.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import '../../printing/printing_service.dart';
import '../../shell/limited_mode.dart';
import '../../shell/pos_scope.dart';
import '../../shell/unpair_dialog.dart';
import '../billing/cashier_ui.dart';
import 'cashier_prefs.dart';

/// Sozlamalar (13-ekran 17; P3-31: menejerga "Sinxronlash xatolari"): bootstrap'dagi printerlar (holati, sinov chop etish, xato ishlarni qayta yuborish; shu
/// qurilmada qog'oz eni va "matnni rasm sifatida"), yangi onlayn buyurtma ovozi, qurilma ma'lumoti va terminalni uzish.
class SettingsPage extends StatefulWidget {
  const SettingsPage({super.key});

  @override
  State<SettingsPage> createState() => _SettingsPageState();
}

class _SettingsPageState extends State<SettingsPage> {
  final Map<int, PrinterStatus> _status = <int, PrinterStatus>{};
  final Set<int> _checking = <int>{};
  StreamSubscription<PrinterQueueState>? _states;
  bool _started = false;

  CashierPrefs get _prefs => CashierPrefs.instance;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (!_started) {
      _started = true;
      unawaited(_prefs.load());
      _prefs.addListener(_rebuild);
      _states = PosScope.of(context).printing.printers.queue.stateChanges
          .listen((_) => _rebuild());
    }
  }

  @override
  void dispose() {
    _prefs.removeListener(_rebuild);
    unawaited(_states?.cancel());
    super.dispose();
  }

  void _rebuild() {
    if (mounted) setState(() {});
  }

  Future<void> _test(PrinterConfig config, PosPrinterInfo info) async {
    final PosScope scope = PosScope.of(context);
    try {
      // P6-32: a label (TSPL) printer gets a sample cup sticker, not the receipt test page
      final PrintJob job = config.language == PrinterLanguage.tspl
          ? await scope.printing.printers.printLabel(
              config,
              layoutCupSticker(
                CupSticker(
                  number: '1',
                  itemName: 'Sinov stikeri',
                  variantName: info.name,
                  modifiers: const <String>['Printer ishlayapti'],
                  printedAt: scope.services.now().toLocal(),
                ),
                scope.session.bootstrap?.labelSettings?.size ??
                    LabelSize.mm40x30,
              ),
              options: LabelPrintOptions(textMode: config.textMode),
            )
          : await scope.printing.printers.testPrint(
              _prefs.apply(config),
              now: scope.services.now().toLocal(),
            );
      if (mounted) {
        showPosMessage(context, 'Sinov sahifasi yuborildi: ${info.name}.');
      }
      final PrintJob done = await scope.printing.printers.queue
          .whenSettledOrFailed(job);
      if (mounted && done.status == PrintJobStatus.failed) {
        showPosMessage(
          context,
          '${info.name}: chop etilmadi — ${done.lastError ?? done.status.label}',
        );
      }
    } on Object catch (e) {
      if (mounted) showPosMessage(context, 'Chop etilmadi: $e');
    }
  }

  Future<void> _check(PrinterConfig config, PosPrinterInfo info) async {
    setState(() => _checking.add(info.id));
    try {
      final PrinterStatus s = await PosScope.of(context).printing.printers
          .status(config);
      if (mounted) setState(() => _status[info.id] = s);
    } on Object {
      if (mounted) setState(() => _status[info.id] = PrinterStatus.offline);
    } finally {
      if (mounted) setState(() => _checking.remove(info.id));
    }
  }

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final PosScope scope = PosScope.of(context);
    final PosBootstrap? boot = scope.session.bootstrap;
    final List<PosPrinterInfo> printers =
        boot?.printers ?? const <PosPrinterInfo>[];
    return Material(
      color: c.page,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: <Widget>[
          // P3-31: offline operations the server rejected or applied with a difference (managers settle them)
          if (scope.data.offline != null &&
              scope.session.can('orders.cancel')) ...<Widget>[
            const SyncErrorsCard(),
            const SizedBox(height: 16),
          ],
          PosCard(
            title: 'Printerlar',
            child: printers.isEmpty
                ? Text(
                    "Bu kassa uchun printer sozlanmagan. Boshqaruv panelida Sozlamalar → Printerlar bo'limidan qo'shing.",
                    style: RzType.body.copyWith(color: c.fgMuted),
                  )
                : Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: <Widget>[
                      for (final PosPrinterInfo p in printers) ...<Widget>[
                        _printer(c, scope.printing, boot!, p),
                        if (p != printers.last) const Divider(height: 28),
                      ],
                    ],
                  ),
          ),
          const SizedBox(height: 16),
          PosCard(
            title: 'Ovoz',
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: <Widget>[
                SwitchListTile(
                  key: const ValueKey<String>('sound-switch'),
                  contentPadding: EdgeInsets.zero,
                  value: _prefs.sound,
                  title: const Text('Yangi onlayn buyurtma ovozi'),
                  subtitle: const Text(
                    'Telegram yoki ilovadan buyurtma kelganda signal',
                  ),
                  onChanged: (bool v) => unawaited(_prefs.setSound(v)),
                ),
                Align(
                  alignment: Alignment.centerLeft,
                  child: TextButton.icon(
                    onPressed: () =>
                        unawaited(SystemSound.play(SystemSoundType.alert)),
                    icon: const Icon(Icons.volume_up_outlined),
                    label: const Text('Sinab eshitish'),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),
          _device(c, scope, boot),
        ],
      ),
    );
  }

  Widget _printer(
    RzColors c,
    CashierPrinting printing,
    PosBootstrap boot,
    PosPrinterInfo p,
  ) {
    final PrinterConfig? config = printing.configFor(p.id);
    final String purpose = switch (p.purpose) {
      'receipt' => 'Chek va schyot',
      'kitchen' =>
        'Sex markasi${boot.station(p.stationId) == null ? '' : ': ${boot.station(p.stationId)!.name}'}',
      'label' => 'Yorliq',
      _ => p.purpose,
    };
    final String connection = switch (p.connection) {
      'lan' => 'LAN ${p.address ?? ''}${p.port == null ? '' : ':${p.port}'}',
      'usb' => 'USB (Windows)',
      'builtin' => 'Ichki printer (Sunmi)',
      'bluetooth' => 'Bluetooth',
      _ => p.connection,
    };
    String? unsupported;
    for (final UnsupportedPrinter u in printing.unsupported) {
      if (u.info.id == p.id) unsupported = u.reason;
    }
    final PrinterQueueState? state = config == null
        ? null
        : printing.printers.queue.stateOf(config.id);
    final PrinterStatus? status =
        _status[p.id] ??
        (state?.status == PrinterStatus.unknown ? null : state?.status);
    final StatusTone tone = switch (status) {
      PrinterStatus.ready => StatusTone.success,
      PrinterStatus.paperNearEnd => StatusTone.warning,
      null || PrinterStatus.unknown => StatusTone.neutral,
      _ => StatusTone.danger,
    };
    final int paper = config == null
        ? p.paperWidth
        : (_prefs.paperOf(config.id) ?? p.paperWidth);
    final bool image = config == null
        ? p.printAsImage
        : (_prefs.imageOf(config.id) ?? p.printAsImage);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        Row(
          children: <Widget>[
            Icon(
              p.isKitchen ? Icons.soup_kitchen_outlined : Icons.print_outlined,
              color: c.fgMuted,
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Text(p.name, style: RzType.headline.copyWith(color: c.fg)),
                  Text(
                    '$purpose · $connection · $paper mm',
                    style: RzType.footnote.copyWith(color: c.fgMuted),
                  ),
                ],
              ),
            ),
            if (unsupported == null)
              TonePill(
                label: status?.label ?? 'Tekshirilmagan',
                tone: tone,
                icon: status == PrinterStatus.ready
                    ? Icons.check_circle_outline_rounded
                    : Icons.print_outlined,
              ),
          ],
        ),
        if (unsupported != null) ...<Widget>[
          const SizedBox(height: 8),
          NoticeBanner(message: unsupported, tone: StatusTone.danger),
        ],
        if (state != null &&
            (state.failed > 0 || state.pending > 0)) ...<Widget>[
          const SizedBox(height: 8),
          NoticeBanner(
            message: state.failed > 0
                ? 'Chop etilmadi: ${state.failed} ta${state.message == null ? '' : ' (${state.message})'}'
                : 'Navbatda: ${state.pending} ta',
            tone: state.failed > 0 ? StatusTone.danger : StatusTone.info,
            action: state.failed > 0
                ? TextButton(
                    onPressed: () => printing.printers.queue.resume(config!.id),
                    child: const Text('Qayta yuborish'),
                  )
                : null,
          ),
        ],
        if (config != null) ...<Widget>[
          const SizedBox(height: 12),
          Wrap(
            spacing: 12,
            runSpacing: 12,
            crossAxisAlignment: WrapCrossAlignment.center,
            children: <Widget>[
              SegmentedButton<int>(
                segments: const <ButtonSegment<int>>[
                  ButtonSegment<int>(value: 58, label: Text('58 mm')),
                  ButtonSegment<int>(value: 80, label: Text('80 mm')),
                ],
                selected: <int>{paper},
                showSelectedIcon: false,
                style: SegmentedButton.styleFrom(
                  minimumSize: const Size(0, 48),
                ),
                onSelectionChanged: (Set<int> s) =>
                    unawaited(_prefs.setPaper(config.id, s.first)),
              ),
              FilterChip(
                key: ValueKey<String>('image-${p.id}'),
                label: const Text('Matnni rasm sifatida'),
                selected: image,
                materialTapTargetSize: MaterialTapTargetSize.padded,
                onSelected: (bool v) =>
                    unawaited(_prefs.setImage(config.id, v)),
              ),
              RzButton(
                key: ValueKey<String>('test-print-${p.id}'),
                label: 'Sinov chop etish',
                icon: Icons.print_rounded,
                variant: RzButtonVariant.secondary,
                onPressed: () => _test(config, p),
              ),
              RzButton(
                label: 'Holatini tekshirish',
                icon: Icons.sensors_rounded,
                variant: RzButtonVariant.ghost,
                loading: _checking.contains(p.id),
                onPressed: () => _check(config, p),
              ),
            ],
          ),
          if (image)
            Padding(
              padding: const EdgeInsets.only(top: 6),
              child: Text(
                "Rasm rejimi: oʻ, gʻ va kirill harflari printer shriftisiz to'g'ri chiqadi (biroz sekinroq).",
                style: RzType.footnote.copyWith(color: c.fgMuted),
              ),
            ),
        ],
      ],
    );
  }

  Widget _device(RzColors c, PosScope scope, PosBootstrap? boot) {
    final PosSession session = scope.session;
    final PairedTerminal? terminal = session.terminal;
    final PosDeviceInfo? device = boot?.device;
    Widget row(String label, String? value) => value == null
        ? const SizedBox.shrink()
        : Padding(
            padding: const EdgeInsets.symmetric(vertical: 4),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                SizedBox(
                  width: 180,
                  child: Text(
                    label,
                    style: RzType.body.copyWith(color: c.fgMuted),
                  ),
                ),
                Expanded(
                  child: SelectableText(
                    value,
                    style: RzType.body.copyWith(color: c.fg),
                  ),
                ),
              ],
            ),
          );
    return PosCard(
      title: 'Qurilma',
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          row('Nomi', device?.name ?? terminal?.device.name),
          row("Do'kon", session.storeName),
          row('Filial', session.branchName),
          row('Terminal ID', device?.uuid ?? terminal?.device.uuid),
          row(
            'Onlayn buyurtma markasi',
            (device?.isPrintingDevice ?? false)
                ? 'Shu kassadan chiqadi'
                : 'Boshqa kassadan',
          ),
          row(
            'Kirgan xodim',
            '${session.staffName ?? ''} · ${StaffRole.labelOf(session.staffRole ?? '')}',
          ),
          const SizedBox(height: 12),
          Align(
            alignment: Alignment.centerLeft,
            child: RzButton(
              label: 'Terminalni uzish',
              icon: Icons.link_off_rounded,
              variant: RzButtonVariant.danger,
              onPressed: session.canUnpair
                  ? () => unawaited(confirmUnpair(context, session))
                  : null,
            ),
          ),
          if (!session.canUnpair)
            Padding(
              padding: const EdgeInsets.only(top: 6),
              child: Text(
                'Terminalni faqat menejer yoki ega uza oladi.',
                style: RzType.footnote.copyWith(color: c.fgMuted),
              ),
            ),
        ],
      ),
    );
  }
}
