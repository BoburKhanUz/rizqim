import 'dart:async';

import 'package:flutter/material.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:rizqim_core/rizqim_core.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import '../../shell/approval_dialog.dart';
import '../../shell/pos_scope.dart';
import '../../shell/routes.dart';
import '../billing/cashier_ui.dart';
import '../billing/payment_page.dart';
import 'banquet_format.dart';
import 'banquet_printing.dart';

/// P6-54 (E5): "Yakuniy hisob" kassada: hisobdagi mehmonlar (kelgan, kafolatlangan, minimal — eng kattasi; hech
/// qachon kafolatlangandan kam emas), paket, zal, qo'shimchalar va tadbir kuni buyurtmasi, to'langan zakladlar,
/// qoldiq yoki ortig'i. "Yakuniy schyot" — bitta schyot (zakladlar oldindan to'lov bo'lib ayiriladi), qoldiq oddiy
/// to'lov sahifasida istalgan usulda; to'langach BITTA sotuv cheki. Ortiqcha zaklad — menejer tasdig'i bilan qaytariladi.
class BanquetSettlementPage extends StatefulWidget {
  const BanquetSettlementPage({super.key, required this.event});

  final PosBanquetEvent event;

  @override
  State<BanquetSettlementPage> createState() => _BanquetSettlementPageState();
}

class _BanquetSettlementPageState extends State<BanquetSettlementPage> {
  PosBanquetSettlement? _s;
  String? _error;
  bool _busy = false;
  bool _started = false;
  final LostRequest _lost = LostRequest();

  PosScope get _scope => PosScope.of(context);

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (!_started) {
      _started = true;
      unawaited(_load());
    }
  }

  Future<void> _load() async {
    try {
      final PosBanquetSettlement s = await _scope.data.banquetOps.settlement(
        widget.event.uuid,
      );
      if (mounted) {
        setState(() {
          _s = s;
          _error = null;
        });
      }
    } on PosException catch (e) {
      if (mounted) {
        setState(
          () => _error = e is PosOfflineUnavailable
              ? banquetsOfflineText
              : e.message,
        );
      }
    }
  }

  Future<void> _settle() async {
    final PosScope scope = _scope;
    setState(() => _busy = true);
    try {
      final PosSettleResult r = await _lost.run<PosSettleResult>(
        context,
        'settle|${widget.event.uuid}',
        (String? token, String? key) => scope.data.banquetOps.settle(
          widget.event.uuid,
          shiftUuid: scope.session.shift?.uuid,
          approvalToken: token,
          idempotencyKey: key,
        ),
      );
      if (!mounted) return;
      setState(() => _s = r.settlement);
      final String? order = r.settlement.orderUuid;
      if (r.bill.dueAmount.isPositive && order != null) {
        // The remainder on the normal payment page: one sale receipt at the full total
        final PosOrder o = await scope.data.orders.get(order);
        if (!mounted) return;
        await CashierRoutes.push<void>(
          context,
          PaymentPage(bill: r.bill, order: o),
        );
      } else if (order != null) {
        showPosMessage(
          context,
          "Schyot zakladlardan to'liq yopildi: sotuv cheki — schyot sahifasida.",
        );
        await CashierRoutes.openBilling<void>(context, orderUuid: order);
      }
      if (mounted) await _load();
    } on PosApprovalCancelled {
      // nothing
    } on Object catch (e) {
      if (mounted) {
        showPosError(
          context,
          e is PosOfflineUnavailable ? banquetsOfflineText : e,
        );
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _refundExcess() async {
    final PosScope scope = _scope;
    final PosMoney excess = _s?.excess ?? PosMoney.zero;
    final bool ok = await confirmAction(
      context,
      title: 'Ortiqcha zaklad',
      message:
          "${excess.format()} mijozga qaytariladi (naqd — shu kassadan). Menejer tasdig'i kerak bo'lishi mumkin.",
      confirmLabel: 'Qaytarish',
    );
    if (!ok || !mounted) return;
    setState(() => _busy = true);
    try {
      final PosBanquetSchedule before = await scope.data.banquetPayments
          .schedule(widget.event.uuid);
      if (!mounted) return;
      final PosBanquetSettlement s = await _lost.run<PosBanquetSettlement>(
        context,
        'excess|${widget.event.uuid}',
        (String? token, String? key) => scope.data.banquetOps.refundExcess(
          widget.event.uuid,
          approvalToken: token,
          idempotencyKey: key,
        ),
      );
      if (!mounted) return;
      setState(() => _s = s);
      final Set<String> seen = <String>{
        for (final PosBanquetPaymentEntry e in before.entries) e.uuid,
      };
      final PosBanquetSchedule after = await scope.data.banquetPayments
          .schedule(widget.event.uuid);
      for (final PosBanquetPaymentEntry e in after.entries) {
        if (seen.contains(e.uuid) || e.type != 'refund' || !mounted) continue;
        await printBanquetEntry(
          context,
          event: widget.event,
          entry: e,
          openDrawer: e.tender == 'cash',
        );
      }
      if (mounted) showPosMessage(context, 'Ortiqcha zaklad qaytarildi.');
    } on PosApprovalCancelled {
      // nothing
    } on Object catch (e) {
      if (mounted) showPosError(context, e);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final PosBanquetSettlement? s = _s;
    return Scaffold(
      backgroundColor: c.page,
      appBar: AppBar(
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Text('Yakuniy hisob', style: RzType.title.copyWith(color: c.fg)),
            Text(
              '${widget.event.displayName} · ${banquetDate(widget.event.eventDate)}',
              style: RzType.footnote.copyWith(color: c.fgMuted),
            ),
          ],
        ),
      ),
      body: s == null
          ? (_error == null
                ? const LoadingBox()
                : Padding(
                    padding: const EdgeInsets.all(16),
                    child: NoticeBanner(
                      message: _error!,
                      action: TextButton(
                        onPressed: _load,
                        child: const Text('Qayta urinish'),
                      ),
                    ),
                  ))
          : Align(
              alignment: Alignment.topCenter,
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 720),
                child: SingleChildScrollView(
                  key: const ValueKey<String>('banquet-settlement'),
                  padding: const EdgeInsets.all(16),
                  child: _body(c, s),
                ),
              ),
            ),
    );
  }

  Widget _body(RzColors c, PosBanquetSettlement s) {
    final PosBanquetEvent e = widget.event;
    final bool settled = s.eventStatus == PosBanquetStatus.settled;
    final bool canPay = _scope.session.can(PosPermissions.takePayment);
    final int? actual = s.guestsActual;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        PosCard(
          title: 'Mehmonlar',
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              Align(
                alignment: Alignment.centerLeft,
                child: BanquetStatusPill(s.eventStatus),
              ),
              const SizedBox(height: 8),
              Text(
                'Hisobda: ${s.guestsBilled} kishi',
                key: const ValueKey<String>('banquet-settlement-billed'),
                style: RzType.headline.copyWith(color: c.fg),
              ),
              const SizedBox(height: 4),
              Text(
                'Kelgan: ${actual ?? '—'} · kafolatlangan: ${e.guestsGuaranteed} · minimal: ${e.guestsMinimum}. '
                "Eng kattasi olinadi: hisob kafolatlangandan kam bo'lmaydi.",
                style: RzType.footnote.copyWith(color: c.fgMuted),
              ),
            ],
          ),
        ),
        const SizedBox(height: 12),
        PosCard(
          title: 'Hisob',
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              for (final PosSettlementLine l in s.lines)
                AmountRow(
                  label: l.persons == null
                      ? l.name
                      : '${l.name} · ${l.unitPrice.format()} × ${l.persons}',
                  amount: l.amount,
                ),
              if (!s.ordersTotal.isZero)
                AmountRow(
                  label: 'Tadbir kuni buyurtmalari',
                  amount: s.ordersTotal,
                ),
              const Divider(height: 20),
              AmountRow(
                key: const ValueKey<String>('banquet-settlement-total'),
                label: 'Jami',
                amount: s.total,
                bold: true,
                large: true,
              ),
              AmountRow(
                label: "To'langan zakladlar",
                amount: s.advances,
                negative: true,
              ),
              if (s.due.isPositive)
                AmountRow(
                  key: const ValueKey<String>('banquet-settlement-due'),
                  label: 'Qoldiq (kassada)',
                  amount: s.due,
                  bold: true,
                  large: true,
                  tone: StatusTone.warning,
                ),
              if (s.excess.isPositive)
                AmountRow(
                  key: const ValueKey<String>('banquet-settlement-excess'),
                  label: s.excessStatus == 'refunded'
                      ? 'Ortiqcha zaklad (qaytarildi)'
                      : 'Ortiqcha zaklad (qaytariladi)',
                  amount: s.excess,
                  bold: true,
                  tone: StatusTone.info,
                ),
            ],
          ),
        ),
        for (final PosBanquetWarning w in s.warnings) ...<Widget>[
          const SizedBox(height: 8),
          NoticeBanner(message: w.message),
        ],
        const SizedBox(height: 12),
        Text(
          "Bitta sotuv cheki to'liq summaga chiqadi: zakladlar — oldindan to'langan qism.",
          style: RzType.footnote.copyWith(color: c.fgMuted),
        ),
        const SizedBox(height: 12),
        if (!settled && canPay)
          RzButton(
            key: const ValueKey<String>('banquet-settle'),
            label: s.status == PosSettlementStatus.open
                ? "Qoldiqni to'lash"
                : 'Yakuniy schyot',
            icon: Icons.receipt_long_rounded,
            size: RzButtonSize.lg,
            onPressed: _busy ? null : _settle,
          ),
        if (s.excess.isPositive &&
            s.excessStatus == 'pending' &&
            canPay) ...<Widget>[
          const SizedBox(height: 8),
          RzButton(
            key: const ValueKey<String>('banquet-refund-excess'),
            label: 'Ortiqcha zakladni qaytarish',
            icon: Icons.undo_rounded,
            variant: RzButtonVariant.secondary,
            onPressed: _busy ? null : _refundExcess,
          ),
        ],
        if (settled)
          const NoticeBanner(
            key: ValueKey<String>('banquet-settled'),
            message: 'Tadbir yakunlandi: hisob yopildi, zal bo\'sh.',
            tone: StatusTone.success,
            icon: Icons.task_alt_rounded,
          ),
      ],
    );
  }
}
