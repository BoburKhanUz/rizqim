import 'dart:async';

import 'package:flutter/material.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_hardware/pos_hardware.dart';
import 'package:rizqim_core/rizqim_core.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import '../../counter/counter_format.dart';
import '../../shell/approval_dialog.dart';
import '../../shell/destinations.dart';
import '../../shell/feature_placeholder.dart';
import '../../shell/limited_mode.dart';
import '../../shell/pos_scope.dart';
import '../../shell/routes.dart';
import '../order/discount_dialog.dart';
import '../order/kitchen_tickets.dart';
import '../order/order_format.dart' show comboPartLines;
import 'cashier_ui.dart';
import 'payment_page.dart';
import 'receipts.dart';
import 'refund_dialog.dart';
import 'split_builder.dart';

/// Schyot, hisobni bo'lish va to'lov (POS_SPEC 2.9, 13-ekranlar 7–8, 10): schyot (pre-check) ko'rinishi va chop etish
/// ("Bu fiskal chek emas", qayta — "NUSXA" tasdiq bilan), bitta / teng N / taom bo'yicha bo'lish, har schyotni alohida
/// to'lash, "fiskal kutmoqda" va qayta urinish, qaytarish. Hamma schyot to'langach buyurtma yopiladi va kassa zalga
/// qaytadi.
class BillingPage extends StatefulWidget {
  const BillingPage({super.key, required this.orderUuid});

  final String orderUuid;

  @override
  State<BillingPage> createState() => _BillingPageState();
}

class _BillingPageState extends State<BillingPage> with RefreshingState {
  PosOrder? _order;
  PosBilling? _billing;
  PosBooking? _booking;
  Object? _error;
  bool _notFound = false;
  bool _splitting = false;
  bool _busy = false;
  String? _selected;
  bool _bookingLooked = false;

  // P3-37: a discount whose answer was lost goes again as it went (key, body, token — and the version it carried)
  final LostRequest _lostDiscount = LostRequest();
  String? _discountSignature;
  int? _discountVersion;

  PosScope get _scope => PosScope.of(context);

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_billing == null && _error == null && !_notFound) {
      startRefreshing(
        _scope.realtime,
        filter: (PosEvent e) => e.orderUuid == widget.orderUuid,
        offline: _scope.data.offline,
        orderRef: widget.orderUuid,
        onSynced: _priceChanged,
      );
      unawaited(refresh());
    }
  }

  @override
  Future<void> refresh() async {
    final PosData data = _scope.data;
    try {
      final List<Object> r = await Future.wait(<Future<Object>>[
        data.orders.get(widget.orderUuid),
        data.billing.bills(widget.orderUuid),
      ]);
      final PosOrder order = r[0] as PosOrder;
      final PosBilling billing = r[1] as PosBilling;
      final String? bookingUuid = billing.order.bookingUuid;
      if (!_bookingLooked && bookingUuid != null) {
        // P3-26: buyurtma bronini va avans qoldig'ini o'zi aytadi; bron faqat ismi va vaqti uchun
        _bookingLooked = true;
        _booking = await _loadBooking(data, bookingUuid);
      }
      if (!mounted) return;
      setState(() {
        _order = order;
        _billing = billing;
        _error = null;
        final List<PosBill> bills = billing.bills;
        if (_selected == null ||
            !bills.any((PosBill b) => b.uuid == _selected)) {
          _selected =
              _firstUnpaid(bills)?.uuid ??
              (bills.isEmpty ? null : bills.first.uuid);
        }
      });
    } on PosNotFound {
      if (mounted) setState(() => _notFound = true);
    } on PosException catch (e) {
      if (mounted) setState(() => _error = e);
    }
  }

  /// P3-35: offlayn sotilgan qator server narxida saqlandi: schyot jami o'zgargan bo'lishi mumkin (sahifa qayta
  /// yuklanadi).
  void _priceChanged(List<SyncOperation> ops) {
    final List<PosSyncPriceNotice> notices = PosSyncPriceNotice.of(ops);
    if (notices.isEmpty || !mounted) return;
    showPosMessage(
      context,
      notices.map((PosSyncPriceNotice n) => n.message).join('\n'),
    );
  }

  /// "Keldi" bilan ochilgan buyurtmaning broni (ism va vaqt; avans qoldig'i — [PosBillingOrder.depositAvailable]).
  static Future<PosBooking?> _loadBooking(PosData data, String uuid) async {
    try {
      return await data.bookings.get(uuid);
    } on PosException {
      // Bronni o'qib bo'lmadi: avans summasi baribir buyurtmadan
      return null;
    }
  }

  /// Bron avanslaridan schyotlarga qolgan summa (server hisoblaydi).
  PosMoney get _depositAvailable =>
      _billing?.order.depositAvailable ?? PosMoney.zero;

  /// Schyotning avansdan ulushi (pre-check uchun): bitta avans hamma schyotdan ayrilmasin.
  PosMoney _prepaidFor(PosBill bill) => depositShare(
    _billing?.bills ?? const <PosBill>[],
    bill,
    _depositAvailable,
  );

  static PosBill? _firstUnpaid(List<PosBill> bills) {
    for (final PosBill b in bills) {
      if (!b.isPaid) return b;
    }
    return null;
  }

  static bool _settled(PosBill b) => b.isTaken;

  /// Schyotga hali tushmagan qatorlar (to'lov boshlangan schyotlardagisidan tashqari).
  List<SplitLine> _remainingLines() {
    final PosOrder order = _order!;
    final Map<int, PosQuantity> taken = <int, PosQuantity>{};
    for (final PosBill b in _billing!.bills.where(_settled)) {
      for (final PosBillItem i in b.items) {
        taken[i.orderItemId] =
            (taken[i.orderItemId] ?? PosQuantity.zero) + i.quantity;
      }
    }
    return <SplitLine>[
      for (final PosOrderItem i in order.activeItems)
        if (i.quantity - (taken[i.id] ?? PosQuantity.zero)
            case final PosQuantity left when left.isPositive)
          SplitLine(
            id: i.id,
            name: i.displayName,
            unit: i.unit,
            seatNo: i.seatNo,
            quantity: left,
            amount: i.quantity.isZero
                ? i.totalPrice
                : PosMoney(
                    (i.totalPrice.tiyin * left.milli / i.quantity.milli)
                        .round(),
                  ),
          ),
    ];
  }

  PosMoney get _remainingTotal {
    final PosBilling b = _billing!;
    return b.order.totalPrice -
        PosMoney.sum(b.bills.where(_settled).map((PosBill x) => x.total));
  }

  bool get _needsBills {
    final PosBilling b = _billing!;
    if (b.order.isClosed) return false;
    if (b.bills.isEmpty || b.order.billsOutOfDate) return true;
    final bool open = b.bills.any((PosBill x) => !_settled(x));
    return !open && b.order.unbilledAmount.isPositive;
  }

  Future<void> _createBills(PosSplit split) async {
    setState(() => _busy = true);
    await runGuarded(context, () async {
      final PosBilling billing = await withApproval<PosBilling>(
        context,
        (String? token) => _scope.data.billing.createBills(
          widget.orderUuid,
          version: _order!.version,
          split: split,
          approvalToken: token,
        ),
      );
      if (!mounted) return;
      setState(() {
        _billing = billing;
        _splitting = false;
        _selected = _firstUnpaid(billing.bills)?.uuid;
      });
    });
    if (!mounted) return;
    setState(() => _busy = false);
    await refresh();
  }

  Future<void> _printBill(PosBill bill) async {
    setState(() => _busy = true);
    final bool reprint = bill.printedCount > 0;
    await runGuarded(context, () async {
      final PosBillResult r;
      try {
        r = await withApproval<PosBillResult>(
          context,
          (String? token) => _scope.data.billing.printBill(
            bill.uuid,
            version: _order?.version,
            approvalToken: token,
          ),
        );
      } on PosConflict catch (e) {
        // P3-37: a copy of a bill the order has outgrown is refused like a first print
        if (e.reason == 'order_changed' && mounted) {
          showPosMessage(context, "Buyurtma o'zgargan: schyotni qayta tuzing.");
          return;
        }
        rethrow;
      }
      if (!mounted) return;
      final ReceiptPrinter printer = ReceiptPrinter.of(context);
      final PreCheck check = preCheckOf(
        bill: r.bill,
        order: _order,
        header: printer.header,
        now: _scope.services.now(),
        billsCount: _billing!.bills.length,
        prepaid: _prepaidFor(r.bill),
        copy: r.copy || reprint,
      );
      await printer.print(
        context,
        (PaperWidth paper) => layoutPreCheck(check, paper),
      );
    });
    if (!mounted) return;
    setState(() => _busy = false);
    await refresh();
  }

  Future<void> _pay(PosBill bill) async {
    final PaymentOutcome? outcome = await CashierRoutes.push<PaymentOutcome>(
      context,
      PaymentPage(
        bill: bill,
        order: _order,
        booking: _booking,
        depositAvailable: _depositAvailable,
        billsCount: _billing!.bills.length,
      ),
    );
    if (!mounted) return;
    await refresh();
    if (!mounted) return;
    if (outcome != null && outcome.orderClosed) _finish();
  }

  Future<void> _retryFiscal(PosBill bill) async {
    setState(() => _busy = true);
    await runGuarded(context, () async {
      final PosBillResult r = await _scope.data.billing.fiscalRetry(bill.uuid);
      if (!mounted) return;
      if (r.bill.fiscal.status == 'issued') {
        await printSale(context, r.bill, _order);
        if (r.order.isClosed && mounted) {
          _finish();
          return;
        }
      } else {
        showPosMessage(
          context,
          "Chek hali kutilmoqda: provayder javob bermadi. Birozdan keyin qayta urinib ko'ring.",
        );
      }
    });
    if (!mounted) return;
    setState(() => _busy = false);
    await refresh();
  }

  Future<void> _closeOrder() async {
    await runGuarded(context, () async {
      final PosBilling b = await _scope.data.billing.closeOrder(
        widget.orderUuid,
      );
      if (b.order.isClosed && mounted) _finish();
    });
  }

  /// Buyurtma yopildi: zal xaritasiga qaytish.
  void _finish() {
    showPosMessage(context, "Buyurtma yopildi. Xona bo'shadi.");
    final PosScope scope = _scope;
    Navigator.of(context).popUntil((Route<Object?> r) => r.isFirst);
    scope.go(CashierDestination.floor);
  }

  Future<void> _refund(PosBill bill) async {
    final PosRefund? refund = await showRefundDialog(
      context,
      bill: bill,
      order: _order,
      refunds: _billing?.refunds ?? const <PosRefund>[],
    );
    if (refund != null && mounted) await refresh();
  }

  /// P3-37: chegirma schyotga to'lov olinmaguncha beriladi (keyin server `paid_bill_affected` deydi); internetsiz yo'q.
  bool get _canDiscount {
    final PosBilling? b = _billing;
    final PosOrder? o = _order;
    return b != null &&
        o != null &&
        o.isOpen &&
        !b.order.isClosed &&
        !b.bills.any(_settled) &&
        DiscountRules.of(_scope.session).allowed;
  }

  Future<void> _discount({bool remove = false}) async {
    final PosOrder? o = _order;
    if (o == null) return;
    final DiscountInput? d;
    if (remove) {
      d = DiscountInput(
        kind: o.discountKind ?? PosDiscountKind.percent,
        value: '0',
        reason: '',
      );
    } else {
      final DiscountRules rules = DiscountRules.of(_scope.session);
      d = await showDiscountDialog(
        context,
        order: o,
        unlimited: rules.unlimited,
        limitHundredths: rules.limitHundredths,
      );
    }
    if (d == null || !mounted) return;
    final DiscountInput input = d;
    final String signature =
        '${input.kind.wire}:${input.value}:${input.reason}';
    // A new request carries the order's version; the retry of a lost one the version it went with
    if (!_lostDiscount.pending || _discountSignature != signature) {
      _discountVersion = o.version;
    }
    _discountSignature = signature;
    final int version = _discountVersion ?? o.version;
    setState(() => _busy = true);
    final PosScope scope = _scope;
    await runGuarded(context, () async {
      try {
        await _lostDiscount.run<PosOrder>(
          context,
          signature,
          (String? token, String? key) => scope.data.orders.discount(
            widget.orderUuid,
            version: token != null && key == null ? o.version : version,
            kind: input.kind,
            value: input.value,
            reason: input.reason,
            approvalToken: token,
            idempotencyKey: key,
          ),
        );
      } on PosException catch (e) {
        if (e is PosVersionConflict || e.needsOrderReload) unawaited(refresh());
        rethrow;
      }
      if (!mounted) return;
      showPosMessage(
        context,
        remove
            ? 'Chegirma olib tashlandi.'
            : 'Chegirma berildi. Schyot yangi summa bilan qayta tuziladi.',
      );
    });
    if (!mounted) return;
    setState(() => _busy = false);
    await refresh();
  }

  Widget _discountButton() => RzButton(
    key: const ValueKey<String>('billing-discount'),
    label: (_order?.hasDiscount ?? false)
        ? "Chegirmani o'zgartirish"
        : 'Chegirma',
    icon: Icons.percent_rounded,
    size: RzButtonSize.lg,
    variant: RzButtonVariant.secondary,
    onPressed: _busy ? null : () => unawaited(_discount()),
  );

  /// P3-35: buyurtmaning hamma to'lovi qaytarilgan (`payment_status = refunded`) — endi uni bekor qilish mumkin
  /// (server `has_payments` demaydi). P3-37: yopilgan buyurtma ham (to'lovi to'liq qaytarilgan bo'lsa).
  bool get _cancellableAfterRefund {
    final PosBillingOrder? state = _billing?.order;
    return state != null &&
        state.paymentStatus == 'refunded' &&
        state.status != 'canceled' &&
        _order != null &&
        _order!.status != 'canceled';
  }

  Future<void> _cancelOrder() async {
    final PosOrder? before = _order;
    if (before == null) return;
    final bool wasClosed = _billing?.order.isClosed ?? false;
    final String? reason = await askReason(
      context,
      title: 'Buyurtmani bekor qilish',
      message: wasClosed
          ? "To'lovlar qaytarilgan. Yopilgan buyurtma bekor qilingan deb belgilanadi; qaytarish cheklari o'z joyida "
                "qoladi. Menejer tasdig'i kerak."
          : "To'lovlar qaytarilgan. Hamma taom o'chiriladi, oshxonaga yuborilganlari uchun «O'CHIRILDI» markasi chiqadi, "
                "joy bo'shaydi.",
      presets: const <String>[
        'Mehmonlar ketdi',
        "Xato to'lov",
        'Mijoz voz kechdi',
      ],
      confirmLabel: 'Buyurtmani bekor qilish',
      confirmVariant: RzButtonVariant.danger,
    );
    if (reason == null || !mounted) return;
    setState(() => _busy = true);
    final PosScope scope = _scope;
    await runGuarded(context, () async {
      final PosOrder after = await withApproval<PosOrder>(
        context,
        (String? token) => scope.data.orders.cancel(
          widget.orderUuid,
          version: before.version,
          reason: reason,
          approvalToken: token,
        ),
      );
      if (wasClosed) {
        // The guests have eaten and gone: no kitchen tickets, the page shows the cancelled order
        if (mounted) showPosMessage(context, 'Buyurtma bekor qilindi.');
        return;
      }
      final KitchenTickets tickets = KitchenTickets(
        printing: scope.printing,
        bootstrap: () => scope.session.bootstrap,
        now: scope.services.now,
      );
      for (final PosOrderItem i in before.items) {
        final PosOrderItem? now = after.item(i.id);
        if (i.wasSent && !i.isVoid && now != null) {
          unawaited(tickets.printVoid(after, now));
        }
      }
      if (!mounted) return;
      showPosMessage(context, 'Buyurtma bekor qilindi. Joy bo\'shadi.');
      Navigator.of(context).popUntil((Route<Object?> r) => r.isFirst);
      scope.go(CashierDestination.floor);
    });
    if (!mounted) return;
    setState(() => _busy = false);
    await refresh();
  }

  @override
  Widget build(BuildContext context) {
    if (_notFound) {
      return const FeaturePlaceholder(
        icon: Icons.payments_outlined,
        title: "Schyot va to'lov",
        message: "Buyurtma topilmadi: u yopilgan, bekor qilingan yoki boshqa filialniki.",
        showAppBar: true,
      );
    }
    final RzColors c = context.rz;
    final PosOrder? order = _order;
    return Scaffold(
      backgroundColor: c.page,
      appBar: AppBar(
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisSize: MainAxisSize.min,
          children: <Widget>[
            const Text("Schyot va to'lov"),
            if (order != null)
              Text(
                <String>[
                  ?placeLabel(order),
                  if ((order.guestsCount ?? 0) > 0)
                    '${order.guestsCount} mehmon',
                  if (order.shortCode != null) '#${order.shortCode}',
                  if (order.waiter?.name != null) order.waiter!.name!,
                ].join(' · '),
                style: RzType.footnote.copyWith(color: c.fgMuted),
              ),
          ],
        ),
        actions: <Widget>[
          IconButton(
            tooltip: 'Yangilash',
            icon: const Icon(Icons.refresh_rounded),
            onPressed: () => unawaited(refresh()),
          ),
          const SizedBox(width: 8),
        ],
      ),
      body: _body(c),
    );
  }

  Widget _body(RzColors c) {
    final Object? error = _error;
    final PosBilling? billing = _billing;
    if (billing == null || _order == null) {
      if (error is PosException) {
        return Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: InlineError(
              message: error.isNetworkError
                  ? "Aloqa yo'q: schyotni yuklab bo'lmadi."
                  : error.message,
              onRetry: () => unawaited(refresh()),
            ),
          ),
        );
      }
      return const LoadingBox();
    }
    if (billing.order.isClosed) return _closed(c, billing);
    final bool building = _splitting || _needsBills;
    final PosBill? selected = _selectedBill;
    // P3-31: offline one bill only (splitting needs the server)
    final bool limited = limitedMode(context);
    final Widget left = building && limited
        ? _offlineSingleBill(c)
        : building
        ? SplitBuilder(
            key: ValueKey<int>(_order!.version),
            lines: _remainingLines(),
            total: _remainingTotal,
            guests: _order!.guestsCount,
            busy: _busy,
            // P6-24: one bill per guest (seat) where the format has it
            byGuest: _scope.session.hasModule(PosModule.guestSplit),
            // P6-25: the server refuses a bill while a dish waits for its weight
            blocked: _order!.weightPending > 0
                ? "${_order!.weightPending} ta taomning og'irligi kiritilmagan: buyurtma ekranida taomni bosib "
                      "og'irligini yozing, keyin schyot tuzing."
                : null,
            onSubmit: _createBills,
            onCancel: _splitting && !_needsBills
                ? () => setState(() => _splitting = false)
                : null,
          )
        : _billList(c, billing);
    final Widget right = building
        ? _orderSummary(c)
        : (selected == null
              ? const SizedBox.shrink()
              : _billPanel(c, selected));
    return LayoutBuilder(
      builder: (BuildContext context, BoxConstraints box) {
        if (box.maxWidth < 900) {
          return ListView(
            padding: const EdgeInsets.all(16),
            children: <Widget>[left, const SizedBox(height: 16), right],
          );
        }
        return Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Expanded(
              flex: 5,
              child: ListView(
                padding: const EdgeInsets.all(16),
                children: <Widget>[left],
              ),
            ),
            Expanded(
              flex: 6,
              child: ListView(
                padding: const EdgeInsets.fromLTRB(0, 16, 16, 16),
                children: <Widget>[right],
              ),
            ),
          ],
        );
      },
    );
  }

  Widget _offlineSingleBill(RzColors c) => PosCard(
    title: 'Schyot',
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        const NoticeBanner(
          icon: Icons.cloud_off_rounded,
          message: "Internetsiz rejim: bitta schyot chiqadi. Hisobni bo'lish internet tiklangach.",
        ),
        const SizedBox(height: 12),
        AmountRow(label: 'Jami', amount: _remainingTotal),
        const SizedBox(height: 12),
        RzButton(
          key: const ValueKey<String>('offline-single-bill'),
          label: 'Schyot chiqarish',
          icon: Icons.receipt_long_outlined,
          size: RzButtonSize.lg,
          fullWidth: true,
          loading: _busy,
          onPressed: _busy
              ? null
              : () => unawaited(_createBills(const PosSplit.single())),
        ),
      ],
    ),
  );

  PosBill? get _selectedBill {
    for (final PosBill b in _billing!.bills) {
      if (b.uuid == _selected) return b;
    }
    return _billing!.bills.isEmpty ? null : _billing!.bills.first;
  }

  Widget _closed(RzColors c, PosBilling billing) {
    final bool canceled = billing.order.status == 'canceled';
    return Center(
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 560),
        child: PosCard(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: <Widget>[
              Icon(
                canceled ? Icons.cancel_rounded : Icons.check_circle_rounded,
                size: 56,
                color: canceled ? c.fgMuted : c.success,
              ),
              const SizedBox(height: 12),
              Text(
                canceled ? 'Buyurtma bekor qilingan' : 'Buyurtma yopildi',
                style: RzType.title.copyWith(color: c.fg),
              ),
              const SizedBox(height: 8),
              AmountRow(
                label: "To'langan",
                amount: billing.order.paidAmount,
                bold: true,
              ),
              for (final PosRefund r in billing.refunds)
                AmountRow(
                  label:
                      'Qaytarilgan${r.reason == null ? '' : ' (${r.reason})'}',
                  amount: r.amount,
                  negative: true,
                  muted: true,
                ),
              const SizedBox(height: 12),
              for (final PosBill b in billing.bills.where(
                (PosBill b) => b.isPaid,
              ))
                Padding(
                  padding: const EdgeInsets.only(bottom: 8),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      Text(
                        '${b.number}-schyot · ${b.total.format()}',
                        style: RzType.body.copyWith(color: c.fg),
                      ),
                      Wrap(
                        spacing: 8,
                        children: <Widget>[
                          TextButton(
                            onPressed: () => unawaited(
                              printSale(context, b, _order, copy: true),
                            ),
                            child: const Text('Chek nusxasi'),
                          ),
                          if (!limitedMode(context) &&
                              hasRefundable(b, billing.refunds))
                            TextButton(
                              key: ValueKey<String>('refund-${b.uuid}'),
                              onPressed: () => _refund(b),
                              child: const Text('Pulni qaytarish'),
                            ),
                        ],
                      ),
                    ],
                  ),
                ),
              if (_cancellableAfterRefund && !limitedMode(context)) ...<Widget>[
                const NoticeBanner(
                  message: "Buyurtmaning hamma to'lovi qaytarildi: endi uni bekor qilish mumkin.",
                  tone: StatusTone.info,
                ),
                const SizedBox(height: 8),
                RzButton(
                  key: const ValueKey<String>('cancel-after-refund'),
                  label: 'Buyurtmani bekor qilish',
                  icon: Icons.cancel_outlined,
                  variant: RzButtonVariant.danger,
                  fullWidth: true,
                  onPressed: _busy ? null : () => unawaited(_cancelOrder()),
                ),
                const SizedBox(height: 8),
              ],
              const SizedBox(height: 8),
              RzButton(
                label: 'Zalga qaytish',
                icon: Icons.table_restaurant_outlined,
                size: RzButtonSize.lg,
                fullWidth: true,
                onPressed: canceled ? _toFloor : _finish,
              ),
            ],
          ),
        ),
      ),
    );
  }

  void _toFloor() {
    final PosScope scope = _scope;
    Navigator.of(context).popUntil((Route<Object?> r) => r.isFirst);
    scope.go(CashierDestination.floor);
  }

  Widget _orderSummary(RzColors c) {
    final PosOrder order = _order!;
    final PosBillingOrder state = _billing!.order;
    return PosCard(
      title: 'Buyurtma hisobi',
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          if (state.billsOutOfDate)
            const Padding(
              padding: EdgeInsets.only(bottom: 12),
              child: NoticeBanner(
                message:
                    "Schyotdan keyin buyurtma o'zgardi: schyotni qayta tuzing.",
              ),
            ),
          AmountRow(label: 'Taomlar', amount: order.subtotal),
          // P6-34: the order's packaging lines (removed ones say so)
          for (final PosPackagingLine l in order.packagingLines)
            AmountRow(
              label: l.removed
                  ? '${l.name} (olib tashlandi)'
                  : (l.quantity > 1 ? '${l.name} × ${l.quantity}' : l.name),
              amount: l.amount,
              muted: l.removed,
            ),
          if (!order.roomFeeAmount.isZero)
            AmountRow(label: 'Xona puli', amount: order.roomFeeAmount),
          if (!order.minSpendTopupAmount.isZero)
            AmountRow(
              label: "Minimal summaga qo'shimcha",
              amount: order.minSpendTopupAmount,
            ),
          if (!order.serviceChargeAmount.isZero)
            AmountRow(
              label:
                  'Xizmat haqi ${_percentLabel(order.charges.serviceChargePercent)}',
              amount: order.serviceChargeAmount,
            ),
          if (!order.placeFeeAmount.isZero)
            AmountRow(
              label: order.placeFeeIsCover ? 'Kuver' : 'Joy puli',
              amount: order.placeFeeAmount,
            ),
          if (order.hasDiscount)
            AmountRow(
              label: discountLabel(order),
              amount: order.discount,
              negative: true,
              trailing: order.discountReason,
            ),
          const Divider(height: 20),
          AmountRow(
            label: 'Jami',
            amount: state.totalPrice,
            bold: true,
            large: true,
          ),
          if (state.paidAmount.isPositive)
            AmountRow(
              label: "To'langan",
              amount: state.paidAmount,
              muted: true,
            ),
          if (_depositAvailable.isPositive)
            AmountRow(
              label: 'Bron avansi (mavjud)',
              amount: _depositAvailable,
              tone: StatusTone.success,
            ),
          if (_canDiscount && !limitedMode(context)) ...<Widget>[
            const SizedBox(height: 12),
            Wrap(
              spacing: 12,
              runSpacing: 12,
              children: <Widget>[
                _discountButton(),
                if (order.hasDiscount) _removeDiscountButton(),
              ],
            ),
          ],
        ],
      ),
    );
  }

  Widget _removeDiscountButton() => RzButton(
    key: const ValueKey<String>('billing-remove-discount'),
    label: 'Chegirmani olib tashlash',
    icon: Icons.backspace_outlined,
    size: RzButtonSize.lg,
    variant: RzButtonVariant.ghost,
    onPressed: _busy ? null : () => unawaited(_discount(remove: true)),
  );

  static String _percentLabel(String raw) {
    try {
      return percentHundredths(raw) == 0 ? '' : '${formatPercent(raw)}%';
    } on FormatException {
      return '';
    }
  }

  Widget _billList(RzColors c, PosBilling billing) {
    final bool canResplit =
        !billing.bills.any(_settled) && !limitedMode(context);
    return PosCard(
      title: billing.bills.length == 1
          ? 'Schyot'
          : 'Schyotlar (${billing.bills.length})',
      trailing: canResplit
          ? TextButton.icon(
              onPressed: () => setState(() => _splitting = true),
              icon: const Icon(Icons.call_split_rounded),
              label: const Text("Qayta bo'lish"),
            )
          : null,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          for (final PosBill b in billing.bills) _billTile(c, b),
          if (billing.order.unbilledAmount.isPositive && !_needsBills)
            Padding(
              padding: const EdgeInsets.only(top: 8),
              child: NoticeBanner(
                message:
                    'Schyotga tushmagan: ${billing.order.unbilledAmount.format()}',
                tone: StatusTone.info,
              ),
            ),
          if (_cancellableAfterRefund && !limitedMode(context))
            Padding(
              padding: const EdgeInsets.only(top: 12),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: <Widget>[
                  const NoticeBanner(
                    message: "Buyurtmaning hamma to'lovi qaytarildi: endi uni bekor qilish mumkin.",
                    tone: StatusTone.info,
                  ),
                  const SizedBox(height: 8),
                  RzButton(
                    key: const ValueKey<String>('cancel-after-refund'),
                    label: 'Buyurtmani bekor qilish',
                    icon: Icons.cancel_outlined,
                    variant: RzButtonVariant.danger,
                    onPressed: _busy ? null : () => unawaited(_cancelOrder()),
                  ),
                ],
              ),
            ),
          if (billing.bills.isNotEmpty &&
              billing.bills.every((PosBill b) => b.isPaid) &&
              !billing.order.isClosed &&
              !_cancellableAfterRefund)
            Padding(
              padding: const EdgeInsets.only(top: 12),
              child: RzButton(
                label: 'Buyurtmani yopish',
                icon: Icons.done_all_rounded,
                onPressed: _closeOrder,
              ),
            ),
        ],
      ),
    );
  }

  Widget _billTile(RzColors c, PosBill b) {
    final bool selected = b.uuid == _selectedBill?.uuid;
    final (String label, StatusTone tone, IconData icon) = billStatus(b);
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Material(
        color: selected ? Color.alphaBlend(c.brandSoft, c.surface) : c.surface,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(RzRadius.control),
          side: BorderSide(color: selected ? c.brand : c.line),
        ),
        child: InkWell(
          borderRadius: BorderRadius.circular(RzRadius.control),
          onTap: () => setState(() => _selected = b.uuid),
          child: Padding(
            padding: const EdgeInsets.all(12),
            child: Row(
              children: <Widget>[
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      Text(
                        b.seatNo == null
                            ? '${b.number}-schyot'
                            : '${b.number}-schyot · ${b.seatNo}-mehmon',
                        style: RzType.headline.copyWith(color: c.fg),
                      ),
                      const SizedBox(height: 4),
                      TonePill(label: label, tone: tone, icon: icon),
                    ],
                  ),
                ),
                Column(
                  crossAxisAlignment: CrossAxisAlignment.end,
                  children: <Widget>[
                    Text(
                      b.total.format(),
                      style: RzType.headline.copyWith(color: c.fg),
                    ),
                    if (b.dueAmount.isPositive && b.paidAmount.isPositive)
                      Text(
                        'Qoldi: ${b.dueAmount.format()}',
                        style: RzType.footnote.copyWith(color: c.fgMuted),
                      ),
                  ],
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _billPanel(RzColors c, PosBill bill) {
    final PosSession session = _scope.session;
    final bool fiscalWaiting = bill.awaitingReceipt && bill.fiscal.isPending;
    final bool refundable = hasRefundable(bill, _billing!.refunds);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        if (fiscalWaiting) ...<Widget>[
          NoticeBanner(
            message: "Fiskal kutmoqda: pul olindi, sotuv cheki hali chiqmadi. Chek chiqmaguncha buyurtma yopilmaydi.",
            icon: Icons.hourglass_top_rounded,
            action: RzButton(
              label: 'Qayta urinish',
              variant: RzButtonVariant.secondary,
              onPressed: _busy ? null : () => _retryFiscal(bill),
            ),
          ),
          const SizedBox(height: 12),
        ],
        BillPreview(
          bill: bill,
          order: _order,
          billsCount: _billing!.bills.length,
          prepaid: _prepaidFor(bill),
        ),
        const SizedBox(height: 12),
        Wrap(
          spacing: 12,
          runSpacing: 12,
          children: <Widget>[
            if (!bill.isPaid && bill.dueAmount.isPositive)
              RzButton(
                label: "To'lash: ${bill.dueAmount.format()}",
                icon: Icons.payments_rounded,
                size: RzButtonSize.lg,
                onPressed: _busy ? null : () => _pay(bill),
              ),
            if (!_settled(bill) || bill.dueAmount.isPositive)
              RzButton(
                label: (_order?.weightPending ?? 0) > 0 && !_settled(bill)
                    ? "Og'irlik kutilmoqda (${_order!.weightPending})"
                    : bill.printedCount > 0
                    ? 'Nusxa chiqarish'
                    : 'Schyotni chiqarish',
                icon: Icons.print_outlined,
                size: RzButtonSize.lg,
                variant: RzButtonVariant.secondary,
                onPressed:
                    _busy ||
                        (_order?.weightPending ?? 0) > 0 && !_settled(bill) ||
                        bill.printedCount > 0 &&
                            !session.can(PosPermissions.reprintBill) &&
                            !session.needsApproval(PosPermissions.reprintBill)
                    ? null
                    : () => _printBill(bill),
              ),
            if (bill.isPaid)
              RzButton(
                label: 'Chek nusxasi',
                icon: Icons.receipt_long_outlined,
                size: RzButtonSize.lg,
                variant: RzButtonVariant.secondary,
                onPressed: () =>
                    unawaited(printSale(context, bill, _order, copy: true)),
              ),
            if (_canDiscount && !limitedMode(context)) ...<Widget>[
              _discountButton(),
              if (_order?.hasDiscount ?? false) _removeDiscountButton(),
            ],
            if (refundable && bill.isPaid && !limitedMode(context))
              RzButton(
                label: 'Pulni qaytarish',
                icon: Icons.undo_rounded,
                size: RzButtonSize.lg,
                variant: RzButtonVariant.ghost,
                onPressed: () => _refund(bill),
              ),
          ],
        ),
        if (bill.printedCount > 0 && !bill.isPaid)
          Padding(
            padding: const EdgeInsets.only(top: 8),
            child: Text(
              'Schyot ${bill.printedCount} marta chiqarilgan: qayta chiqarilsa "NUSXA" yoziladi'
              '${session.can(PosPermissions.reprintBill) ? '.' : " (menejer tasdig'i)."}',
              style: RzType.footnote.copyWith(color: c.fgMuted),
            ),
          ),
      ],
    );
  }
}

/// Bron avansi qoldig'ining shu schyotga tushadigan qismi: avans hali to'lanmagan schyotlarga raqam tartibida
/// taqsimlanadi (har biriga qarzidan ko'p emas). 300 000 avans va uchta 200 000 schyot — 200 000, 100 000, 0.
PosMoney depositShare(List<PosBill> bills, PosBill bill, PosMoney deposit) {
  var left = deposit;
  final List<PosBill> open =
      bills
          .where(
            (PosBill b) =>
                b.status != 'void' && !b.isPaid && b.dueAmount.isPositive,
          )
          .toList()
        ..sort((PosBill a, PosBill b) => a.number.compareTo(b.number));
  for (final PosBill b in open) {
    final PosMoney share = left < b.dueAmount ? left : b.dueAmount;
    if (b.uuid == bill.uuid) return share.isPositive ? share : PosMoney.zero;
    left = left - share;
  }
  return PosMoney.zero;
}

/// Schyot holati: yorliq, rang, ikonka.
(String, StatusTone, IconData) billStatus(PosBill b) => switch (b.status) {
  'paid' => (
    "To'langan",
    StatusTone.success,
    Icons.check_circle_outline_rounded,
  ),
  _ when b.awaitingReceipt => (
    'Fiskal kutmoqda',
    StatusTone.warning,
    Icons.hourglass_top_rounded,
  ),
  _ when b.isTaken => (
    "Qisman to'langan",
    StatusTone.info,
    Icons.timelapse_rounded,
  ),
  'printed' => ('Chiqarilgan', StatusTone.info, Icons.print_outlined),
  'void' => ('Bekor qilingan', StatusTone.neutral, Icons.block_rounded),
  _ => ('Ochiq', StatusTone.neutral, Icons.receipt_outlined),
};

/// Schyot ko'rinishi (pre-check): qatorlar, xona puli, minimal summa, xizmat haqi, joy puli, chegirma, jami, to'langan,
/// qoldiq, kishi boshiga va "Bu fiskal chek emas".
class BillPreview extends StatelessWidget {
  const BillPreview({
    super.key,
    required this.bill,
    this.order,
    this.billsCount = 1,
    this.prepaid = PosMoney.zero,
  });

  final PosBill bill;
  final PosOrder? order;
  final int billsCount;
  final PosMoney prepaid;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final int? guests =
        bill.guestsShare ?? (billsCount == 1 ? order?.guestsCount : null);
    final PosMoney? each =
        bill.perGuestAmount ??
        (guests != null && guests > 1 ? perPerson(bill.total, guests) : null);
    return PosCard(
      title:
          (billsCount > 1 ? 'Schyot ${bill.number}/$billsCount' : 'Schyot') +
          (bill.seatNo == null ? '' : ' · ${bill.seatNo}-mehmon'),
      trailing: Text(
        'Bu fiskal chek emas',
        style: RzType.footnote.copyWith(color: c.fgMuted),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          for (final PosBillItem i in bill.items) ...<Widget>[
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 3),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Expanded(
                    child: Text(
                      i.variantName == null || i.variantName!.isEmpty
                          ? i.productName
                          : '${i.productName} (${i.variantName})',
                      style: RzType.body.copyWith(color: c.fg),
                    ),
                  ),
                  SizedBox(
                    width: 120,
                    child: Text(
                      '${i.quantity.label(i.unit)} × ${unitPriceOf(i.amount, i.quantity).formatAmount()}',
                      textAlign: TextAlign.right,
                      style: RzType.footnote.copyWith(color: c.fgMuted),
                    ),
                  ),
                  SizedBox(
                    width: 110,
                    child: Text(
                      i.amount.formatAmount(),
                      textAlign: TextAlign.right,
                      style: RzType.body.copyWith(color: c.fg),
                    ),
                  ),
                ],
              ),
            ),
            // P6-31: a combo's parts, without prices
            for (final String part in comboPartLines(i.comboParts))
              Padding(
                padding: const EdgeInsets.only(left: 16, bottom: 2),
                child: Text(
                  part,
                  style: RzType.footnote.copyWith(color: c.fgMuted),
                ),
              ),
          ],
          const Divider(height: 20),
          AmountRow(label: 'Taomlar', amount: bill.subtotal),
          // P6-34: each packaging line of the bill
          for (final PosPackagingLine l in bill.packagingLines)
            if (!l.amount.isZero)
              AmountRow(
                label: l.quantity > 1 ? '${l.name} × ${l.quantity}' : l.name,
                amount: l.amount,
              ),
          if (!bill.roomFeeAmount.isZero)
            AmountRow(label: 'Xona puli', amount: bill.roomFeeAmount),
          if (!bill.minSpendTopupAmount.isZero)
            AmountRow(
              label: "Minimal summaga qo'shimcha",
              amount: bill.minSpendTopupAmount,
            ),
          if (!bill.serviceChargeAmount.isZero)
            AmountRow(label: 'Xizmat haqi', amount: bill.serviceChargeAmount),
          if (!bill.placeFeeAmount.isZero)
            AmountRow(
              label: (order?.placeFeeIsCover ?? false) ? 'Kuver' : 'Joy puli',
              amount: bill.placeFeeAmount,
            ),
          // P6-22: the service charge notice, as on the printed bill
          if (bill.serviceChargeNotice != null)
            Padding(
              padding: const EdgeInsets.only(bottom: 4),
              child: Text(
                bill.serviceChargeNotice!,
                key: const ValueKey<String>('bill-notice'),
                style: RzType.footnote.copyWith(color: c.fgMuted),
              ),
            ),
          if (!bill.discount.isZero)
            AmountRow(
              label: discountLabel(order),
              amount: bill.discount,
              negative: true,
              trailing: order?.discountReason,
            ),
          const Divider(height: 20),
          AmountRow(label: 'Jami', amount: bill.total, bold: true, large: true),
          if (prepaid.isPositive)
            AmountRow(
              label: 'Avans (bron)',
              amount: prepaid,
              tone: StatusTone.success,
              negative: true,
            ),
          if (bill.paidAmount.isPositive)
            AmountRow(label: "To'langan", amount: bill.paidAmount, muted: true),
          if (bill.paidAmount.isPositive || prepaid.isPositive)
            AmountRow(
              label: "To'lanadi",
              amount: _toPay(bill, prepaid),
              bold: true,
            ),
          if (each != null)
            AmountRow(
              label: 'Kishi boshiga (${guests ?? bill.guestsShare} kishi)',
              amount: each,
              muted: true,
            ),
          // P6-22: tips are on top of the bill (never in its total or on the receipt)
          if (bill.tipsAmount.isPositive)
            AmountRow(
              key: const ValueKey<String>('bill-tips'),
              label: 'Choy puli (chekdan tashqari)',
              amount: bill.tipsAmount,
              muted: true,
            ),
          if (bill.payments.isNotEmpty) ...<Widget>[
            const Divider(height: 20),
            for (final PosPayment p in bill.payments)
              AmountRow(
                label:
                    tenderLabel(p.tender) +
                    (p.cardType == null
                        ? ''
                        : ' (${cardTypes[p.cardType] ?? p.cardType})'),
                amount: p.amount,
                muted: true,
                trailing: p.changeAmount != null && p.changeAmount!.isPositive
                    ? 'qaytim ${p.changeAmount!.formatAmount()}'
                    : p.transactionId,
              ),
          ],
        ],
      ),
    );
  }

  static PosMoney _toPay(PosBill bill, PosMoney prepaid) {
    final PosMoney rest =
        bill.dueAmount - (bill.paidAmount.isZero ? prepaid : PosMoney.zero);
    return rest.isNegative ? PosMoney.zero : rest;
  }
}

/// Sotuv chekini chop etadi: serverdagi chek bilan (fiskal belgi, raqam, QR, kassa — P3-26: to'lov javobidagi
/// `fiscal.receipt`, bo'lmasa `GET /pos/fiscal-receipts/{uuid}`), u ham bo'lmasa schyotdan. Navbatga tushgan ish
/// (chiqqanini `job.done` bilan bilish mumkin) yoki `null`.
Future<PrintJob?> printSale(
  BuildContext context,
  PosBill bill,
  PosOrder? order, {
  bool copy = false,
  bool openDrawer = false,
}) async {
  final PosScope scope = PosScope.of(context);
  final ReceiptPrinter printer = ReceiptPrinter(scope);
  final PosFiscalReceiptDetail? detail = await receiptOf(
    scope.data,
    bill.fiscal,
  );
  if (!context.mounted) return null;
  final FiscalReceipt doc = saleReceiptOf(
    bill: bill,
    order: order,
    detail: detail,
    header: printer.header,
    now: scope.services.now(),
    cashier: printer.cashier,
    copy: copy,
  );
  return printer.submit(
    context,
    (PaperWidth paper) => layoutFiscalReceipt(doc, paper),
    openDrawer: openDrawer,
  );
}
