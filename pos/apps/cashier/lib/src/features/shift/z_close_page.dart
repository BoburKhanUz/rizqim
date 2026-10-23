import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:rizqim_core/rizqim_core.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import '../../shell/approval_dialog.dart';
import '../../shell/destinations.dart';
import '../../shell/pos_scope.dart';
import '../billing/cashier_ui.dart';
import '../billing/receipts.dart';
import 'shift_report_mapping.dart';

/// Smenani yopish (Z, POS_SPEC 2.3): naqd kupyura bo'yicha sanaladi, kutilgan summa bilan solishtiriladi va farq
/// ko'rsatiladi. Ochiq buyurtmalar yoki fiskal kutayotgan cheklar bo'lsa server rad etadi — sabab ko'rsatiladi,
/// menejer majburan yopishi mumkin (sabab bilan). Yopilgach Z-hisobot chop etiladi.
class ZClosePage extends StatefulWidget {
  const ZClosePage({super.key, required this.shift, required this.report});

  final PosShift shift;
  final PosShiftReport report;

  @override
  State<ZClosePage> createState() => _ZClosePageState();
}

class _ZClosePageState extends State<ZClosePage> {
  final Map<int, TextEditingController> _counts = <int, TextEditingController>{
    for (final int d in uzsDenominations) d: TextEditingController(),
  };
  bool _busy = false;
  PosConflict? _refusal;

  @override
  void dispose() {
    for (final TextEditingController t in _counts.values) {
      t.dispose();
    }
    super.dispose();
  }

  Map<int, int> get _breakdown => <int, int>{
    for (final MapEntry<int, TextEditingController> e in _counts.entries)
      if ((int.tryParse(e.value.text) ?? 0) > 0) e.key: int.parse(e.value.text),
  };

  PosMoney get _counted => PosMoney.som(
    _breakdown.entries.fold(
      0,
      (int s, MapEntry<int, int> e) => s + e.key * e.value,
    ),
  );

  PosMoney get _expected => ShiftReportView(widget.report).expectedCash;

  void _bump(int nominal, int delta) {
    final TextEditingController t = _counts[nominal]!;
    final int next = ((int.tryParse(t.text) ?? 0) + delta).clamp(0, 99999);
    setState(() => t.text = next == 0 ? '' : '$next');
  }

  Future<void> _close({bool force = false}) async {
    String? reason;
    if (force) {
      reason = await askReason(
        context,
        title: 'Smenani majburan yopish',
        message:
            "Ochiq buyurtmalar keyingi smenaga o'tadi. Menejer tasdiqlaydi.",
        presets: const <String>[
          'Xona ertaga yopiladi',
          'Chek keyin chiqadi',
          'Kassir almashdi',
        ],
        confirmLabel: 'Majburan yopish',
        confirmVariant: RzButtonVariant.danger,
      );
      if (reason == null || !mounted) return;
    }
    setState(() => _busy = true);
    final PosScope scope = PosScope.of(context);
    final Map<int, int> breakdown = _breakdown;
    final PosMoney counted = _counted;
    try {
      final PosShift closed = await withApproval<PosShift>(
        context,
        (String? token) => scope.data.shifts.close(
          widget.shift.uuid,
          countedBreakdown: breakdown,
          force: force,
          reason: reason,
          approvalToken: token,
        ),
      );
      scope.session.updateShift(null);
      if (!mounted) return;
      final PosShiftReport report = closed.summary ?? widget.report;
      final ReceiptPrinter printer = ReceiptPrinter(scope);
      final ShiftReport doc = ShiftReportView(report).toDocument(
        kind: ShiftReportKind.z,
        header: printer.header,
        shift: closed,
        printedAt: scope.services.now(),
        counted: closed.countedCash ?? counted,
        denominations: closed.countedBreakdown ?? breakdown,
      );
      await printer.print(
        context,
        (PaperWidth paper) => layoutShiftReport(doc, paper),
      );
      if (mounted) Navigator.pop(context, closed);
    } on PosConflict catch (e) {
      if (!mounted) return;
      if (e.reason == 'shift_has_open_orders' ||
          e.reason == 'shift_has_pending_receipts') {
        setState(() => _refusal = e);
      } else {
        showPosError(context, e);
      }
    } on Object catch (e) {
      if (mounted) showPosError(context, e);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    return Scaffold(
      backgroundColor: c.page,
      appBar: AppBar(title: const Text('Smenani yopish (Z)')),
      body: LayoutBuilder(
        builder: (BuildContext context, BoxConstraints box) {
          final Widget count = _countCard(c);
          final Widget result = _resultCard(c);
          if (box.maxWidth < 900) {
            return ListView(
              padding: const EdgeInsets.all(16),
              children: <Widget>[result, const SizedBox(height: 16), count],
            );
          }
          return Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Expanded(
                flex: 6,
                child: ListView(
                  padding: const EdgeInsets.all(16),
                  children: <Widget>[count],
                ),
              ),
              Expanded(
                flex: 5,
                child: ListView(
                  padding: const EdgeInsets.fromLTRB(0, 16, 16, 16),
                  children: <Widget>[result],
                ),
              ),
            ],
          );
        },
      ),
    );
  }

  Widget _countCard(RzColors c) => PosCard(
    title: 'Kassadagi naqdni sanang',
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        for (final int d in uzsDenominations)
          Padding(
            padding: const EdgeInsets.symmetric(vertical: 4),
            child: Row(
              children: <Widget>[
                SizedBox(
                  width: 110,
                  child: Text(
                    groupDigits(d, ' '),
                    style: RzType.headline.copyWith(
                      color: c.fg,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ),
                IconButton.filledTonal(
                  tooltip: 'Kamaytirish',
                  onPressed: () => _bump(d, -1),
                  icon: const Icon(Icons.remove_rounded),
                ),
                SizedBox(
                  width: 84,
                  child: TextField(
                    key: ValueKey<String>('denom-$d'),
                    controller: _counts[d],
                    textAlign: TextAlign.center,
                    keyboardType: TextInputType.number,
                    inputFormatters: <TextInputFormatter>[
                      FilteringTextInputFormatter.digitsOnly,
                    ],
                    decoration: const InputDecoration(
                      hintText: '0',
                      isDense: true,
                    ),
                    onChanged: (_) => setState(() {}),
                  ),
                ),
                IconButton.filledTonal(
                  tooltip: "Qo'shish",
                  onPressed: () => _bump(d, 1),
                  icon: const Icon(Icons.add_rounded),
                ),
                const Spacer(),
                Text(
                  PosMoney.som(d * (int.tryParse(_counts[d]!.text) ?? 0))
                      .formatAmount(),
                  style: RzType.body.copyWith(color: c.fgMuted),
                ),
              ],
            ),
          ),
      ],
    ),
  );

  Widget _resultCard(RzColors c) {
    final PosMoney counted = _counted;
    final PosMoney diff = counted - _expected;
    final (String label, StatusTone tone) = diff.isZero
        ? ('Farq yo\'q', StatusTone.success)
        : diff.isNegative
        ? ('Kamomad', StatusTone.danger)
        : ('Ortiqcha', StatusTone.warning);
    final PosConflict? refusal = _refusal;
    final Map<String, Object?> data =
        refusal?.data ?? const <String, Object?>{};
    final int openOrders = (data['open_orders'] as List<Object?>?)?.length ?? 0;
    final int pending = (data['pending_bills'] as List<Object?>?)?.length ?? 0;
    final PosSession session = PosScope.of(context).session;
    final bool canForce =
        session.can(PosPermissions.ordersCancel) ||
        session.needsApproval(PosPermissions.ordersCancel);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        PosCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              AmountRow(label: "Kassada bo'lishi kerak", amount: _expected),
              AmountRow(
                label: 'Sanalgan',
                amount: counted,
                bold: true,
                large: true,
              ),
              const Divider(height: 24),
              Row(
                children: <Widget>[
                  TonePill(label: label, tone: tone),
                  const Spacer(),
                  Text(
                    diff.isNegative ? '−${diff.abs().format()}' : diff.format(),
                    key: const ValueKey<String>('z-difference'),
                    style: RzType.display.copyWith(
                      color: toneColors(c, tone).$2,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 16),
              RzButton(
                key: const ValueKey<String>('z-close'),
                label: 'Smenani yopish (Z)',
                icon: Icons.lock_clock_outlined,
                size: RzButtonSize.lg,
                fullWidth: true,
                onPressed: _busy ? null : () => _close(),
              ),
            ],
          ),
        ),
        if (refusal != null) ...<Widget>[
          const SizedBox(height: 16),
          PosCard(
            title: 'Smena yopilmadi',
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: <Widget>[
                NoticeBanner(message: refusal.message, tone: StatusTone.danger),
                const SizedBox(height: 8),
                if (openOrders > 0)
                  Text(
                    "Ochiq buyurtmalar: $openOrders ta",
                    style: RzType.body.copyWith(color: c.fg),
                  ),
                if (pending > 0)
                  Text(
                    'Fiskal kutayotgan cheklar: $pending ta',
                    style: RzType.body.copyWith(color: c.fg),
                  ),
                const SizedBox(height: 12),
                Wrap(
                  spacing: 12,
                  runSpacing: 12,
                  children: <Widget>[
                    RzButton(
                      label: openOrders > 0
                          ? 'Zalga qaytish'
                          : "Navbatda ko'rish",
                      variant: RzButtonVariant.secondary,
                      onPressed: () {
                        final PosScope scope = PosScope.of(context);
                        Navigator.of(context)
                            .popUntil((Route<Object?> r) => r.isFirst);
                        scope.go(
                          openOrders > 0
                              ? CashierDestination.floor
                              : CashierDestination.queue,
                        );
                      },
                    ),
                    if (canForce)
                      RzButton(
                        key: const ValueKey<String>('z-force'),
                        label: 'Majburan yopish',
                        icon: Icons.warning_amber_rounded,
                        variant: RzButtonVariant.danger,
                        onPressed: _busy ? null : () => _close(force: true),
                      ),
                  ],
                ),
              ],
            ),
          ),
        ],
      ],
    );
  }
}
