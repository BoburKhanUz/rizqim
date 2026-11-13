import 'dart:async';
import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_ui/customer_display.dart' show QrView;
import 'package:rizqim_core/rizqim_core.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import '../../shell/pos_scope.dart';
import '../../counter/counter_format.dart';
import 'cashier_ui.dart';
import 'payment_messages.dart';
import 'receipts.dart';

/// P6-41/P6-42: dinamik QR to'lov kassada (docs/API.md "QR to'lov kassada"). Kassir provayderni (sukut — filialning
/// asosiysi) va summani (sukut — qoldiq; aralash to'lovda — QR qismi) tanlaydi, server QR beradi: u kassada katta
/// ko'rinadi, mijoz ekraniga ketadi (bulut va LAN — [DisplayFeed]) va xohlasa schyotga chop etiladi. Muddat sanaladi,
/// holat real-time (`qr.paid|cancelled|expired`) va har [CashierServices.qrPollInterval] da so'rab turiladi; to'langach
/// schyot qayta o'qiladi va [onPaid] — kassir hech narsa bosmaydi. "Bekor qilish", "Yangi QR" (provayder to'lashni
/// boshlagan — `held` — bo'lsa yo'q). Internet yo'q yoki modul o'chiq — bu panel chiqmaydi (statik QR qo'lda).
class QrPaymentPanel extends StatefulWidget {
  const QrPaymentPanel({
    super.key,
    required this.bill,
    required this.amount,
    required this.onPaid,
    required this.onManual,
    this.order,
    this.billsCount = 1,
    this.onActive,
  });

  final PosBill bill;

  /// QR summasi (qoldiq; ko'pi bilan schyot qarzi).
  final PosMoney amount;
  final PosOrder? order;
  final int billsCount;

  /// Provayder tasdiqladi: schyot va buyurtma serverdan qayta o'qilgan.
  final ValueChanged<PosBillResult> onPaid;

  /// "Statik QR (qo'lda)": avvalgi oqim.
  final VoidCallback onManual;

  /// Kutilayotgan QR bor/yo'q (to'lov sahifasi "To'lash" ni shunga qarab tushuntiradi).
  final ValueChanged<bool>? onActive;

  @override
  State<QrPaymentPanel> createState() => _QrPaymentPanelState();
}

class _QrPaymentPanelState extends State<QrPaymentPanel> {
  late final TextEditingController _amount = TextEditingController(
    text: moneyText(widget.amount),
  );
  PosQrProvider? _provider;
  PosQrPayment? _qr;
  bool _busy = false;
  bool _handled = false;
  String? _error;
  String? _createKey;
  Timer? _poll;
  Timer? _tick;
  StreamSubscription<PosEvent>? _events;
  bool _started = false;
  bool _shown = false;
  PosDisplayCart? _displayBase;

  /// Read in [didChangeDependencies] (also used in [dispose], where a lookup is not allowed).
  late PosScope _scope;

  PosQrSettings get _settings =>
      _scope.session.bootstrap?.qrPayments ?? const PosQrSettings();

  bool get _pending => _qr?.status == PosQrStatus.pending;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _scope = PosScope.of(context);
    if (_started) return;
    _started = true;
    final PosQrSettings s = _settings;
    _provider =
        s.defaultProvider != null && s.providers.contains(s.defaultProvider)
        ? s.defaultProvider
        : (s.providers.isEmpty ? null : s.providers.first);
    _events = _scope.realtime?.events.listen(_onEvent);
    unawaited(_restore());
  }

  @override
  void didUpdateWidget(QrPaymentPanel old) {
    super.didUpdateWidget(old);
    // Parts added or removed (mixed payment): the suggested QR amount follows what is left
    if (old.amount != widget.amount && !_pending) {
      _amount.text = moneyText(widget.amount);
    }
  }

  @override
  void dispose() {
    _poll?.cancel();
    _tick?.cancel();
    unawaited(_events?.cancel());
    _amount.dispose();
    // A QR left on the customer display while the cashier went back: the display shows what it had before
    if (_shown && !_handled) _restoreDisplay();
    super.dispose();
  }

  /// The bill already has a pending QR (the payment page was closed and opened again): it is shown, not a new one.
  Future<void> _restore() async {
    try {
      final List<PosQrPayment> list = await _scope.data.qr.forBill(
        widget.bill.uuid,
      );
      if (!mounted || _qr != null) return;
      final PosQrPayment? pending = list
          .where((PosQrPayment q) => q.status == PosQrStatus.pending)
          .firstOrNull;
      if (pending != null) _show(pending);
    } on PosException {
      // Nothing to restore: the cashier asks for a QR
    }
  }

  void _onEvent(PosEvent e) {
    if (e is! PosQrPaymentChanged) return;
    final PosQrPayment? qr = _qr;
    if (qr == null || e.qrUuid != qr.uuid) return;
    unawaited(_refresh());
  }

  void _show(PosQrPayment qr) {
    setState(() {
      _qr = qr;
      _error = null;
    });
    widget.onActive?.call(qr.status == PosQrStatus.pending);
    _poll?.cancel();
    _tick?.cancel();
    if (qr.status == PosQrStatus.pending) {
      _poll = Timer.periodic(
        _scope.services.qrPollInterval,
        (_) => unawaited(_refresh()),
      );
      _tick = Timer.periodic(const Duration(seconds: 1), (_) {
        if (mounted) setState(() {});
      });
      _publish(qr);
    }
  }

  Future<void> _refresh() async {
    final PosQrPayment? qr = _qr;
    if (qr == null || _handled) return;
    try {
      final PosQrPayment next = await _scope.data.qr.poll(qr.uuid);
      if (!mounted || _qr?.uuid != next.uuid) return;
      _apply(next);
    } on PosException {
      // The next tick (or event) asks again
    }
  }

  void _apply(PosQrPayment next) {
    final PosQrStatus before = _qr?.status ?? PosQrStatus.unknown;
    setState(() => _qr = next);
    if (next.status == PosQrStatus.pending) return;
    _poll?.cancel();
    _tick?.cancel();
    widget.onActive?.call(false);
    if (next.status == PosQrStatus.paid) {
      unawaited(_paid());
    } else if (before == PosQrStatus.pending) {
      _restoreDisplay();
    }
  }

  /// To'landi: schyot va buyurtma qayta o'qiladi, sahifa odatdagidek davom etadi (chek, kassa qutisi emas).
  Future<void> _paid() async {
    if (_handled) return;
    _handled = true;
    for (var attempt = 0; attempt < 3; attempt++) {
      try {
        final PosBilling billing = await _scope.data.billing.bills(
          widget.bill.orderUuid,
          all: true,
        );
        if (!mounted) return;
        final PosBill? bill = billing.bills
            .where((PosBill b) => b.uuid == widget.bill.uuid)
            .firstOrNull;
        if (bill == null) return;
        _publishPaid(bill);
        widget.onPaid(PosBillResult(bill: bill, order: billing.order));
        return;
      } on PosException {
        if (!mounted) return;
        await Future<void>.delayed(const Duration(seconds: 1));
      }
    }
    if (mounted) {
      setState(
        () => _error = "QR to'landi, lekin schyotni o'qib bo'lmadi. Schyotlar ro'yxatini yangilang.",
      );
    }
  }

  PosMoney get _requested {
    final PosMoney? m = parseMoney(_amount.text);
    if (m == null || !m.isPositive) return widget.amount;
    return m;
  }

  Future<void> _create() async {
    final PosMoney amount = _requested;
    if (amount > widget.amount) {
      setState(
        () => _error = "Summa qoldiqdan ko'p: ${widget.amount.format()}.",
      );
      return;
    }
    final PosQrPayment? old = _qr;
    if (old != null && old.held && old.status == PosQrStatus.pending) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    _createKey ??= _randomKey();
    try {
      final PosQrPayment qr = await _scope.data.qr.create(
        widget.bill.uuid,
        provider: _provider,
        amount: amount.toApiString(),
        shiftUuid: _scope.session.shift?.uuid,
        idempotencyKey: _createKey,
      );
      _createKey = null;
      if (!mounted) return;
      _show(qr);
    } on PosNetworkException {
      // The same key next time: the server makes the QR once
      if (mounted) {
        setState(
          () => _error = "Aloqa yo'q: QR yaratilmadi. Qayta urinish xavfsiz yoki statik QR'ni qo'lda tasdiqlang.",
        );
      }
    } on PosException catch (e) {
      _createKey = null;
      if (mounted) {
        setState(() => _error = paymentRefusalText(e) ?? e.message);
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _cancel() async {
    final PosQrPayment? qr = _qr;
    if (qr == null) return;
    setState(() => _busy = true);
    try {
      final PosQrPayment next = await _scope.data.qr.cancel(
        qr.uuid,
        reason: 'cashier',
        idempotencyKey: _randomKey(),
      );
      if (mounted) _apply(next);
    } on PosConflict catch (e) {
      // Paid meanwhile: the bill is read again
      if (e.reason == 'qr_already_paid') {
        unawaited(_refresh());
      } else if (mounted) {
        setState(() => _error = paymentRefusalText(e) ?? e.message);
      }
    } on PosException catch (e) {
      if (mounted) setState(() => _error = paymentRefusalText(e) ?? e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _printOnBill() async {
    final PosQrPayment? qr = _qr;
    if (qr == null) return;
    final ReceiptPrinter printer = ReceiptPrinter.of(context);
    final PreCheck check = preCheckOf(
      bill: widget.bill,
      header: printer.header,
      now: _scope.services.now(),
      order: widget.order,
      billsCount: widget.billsCount,
      paymentQr: qr.payload,
      paymentQrLabel:
          "${qr.providerLabel ?? qr.provider?.label ?? 'QR'} orqali: ${paperAmount(qr.amount)} so'm",
    );
    final bool ok = await printer.print(
      context,
      (PaperWidth paper) => layoutPreCheck(check, paper),
    );
    if (ok && mounted) showPosMessage(context, "QR schyotga chop etildi.");
  }

  // --- mijoz ekrani ---------------------------------------------------------------------------------------------------

  bool get _displayOn =>
      _scope.session.hasModule(PosModule.customerDisplay) &&
      _scope.data.displayFeed.enabled;

  List<PosDisplayLine> _lines() => <PosDisplayLine>[
    for (final PosBillItem i in widget.bill.items)
      PosDisplayLine(
        name: i.variantName == null || i.variantName!.isEmpty
            ? i.productName
            : '${i.productName} (${i.variantName})',
        quantity: i.quantity,
        unit: i.unit.isMeasured ? i.unit.label : null,
        price: unitPriceOf(i.amount, i.quantity),
        total: i.amount,
      ),
  ];

  void _publish(PosQrPayment qr) {
    if (!_displayOn) return;
    final PosDisplayCart? last = _scope.services.lanPublisher.lastCart;
    if (!_shown) _displayBase = last;
    _shown = true;
    final PosDisplayCart? base = _displayBase;
    final bool useBase =
        base != null &&
        base.lines.isNotEmpty &&
        (base.state == PosDisplayScreen.payment ||
            base.state == PosDisplayScreen.cart);
    _scope.data.displayFeed.publish(
      PosDisplayCart(
        state: PosDisplayScreen.payment,
        lines: useBase ? base.lines : _lines(),
        subtotal: useBase ? base.subtotal : widget.bill.subtotal,
        discount: useBase ? base.discount : widget.bill.discount,
        total: qr.amount,
        queueNumber: base?.queueNumber,
        payment: PosDisplayPayment(method: 'qr', qr: qr.display),
      ),
    );
  }

  void _publishPaid(PosBill bill) {
    if (!_shown || !_displayOn) return;
    final PosDisplayCart? base = _displayBase;
    _scope.data.displayFeed.publish(
      PosDisplayCart(
        state: bill.dueAmount.isPositive
            ? PosDisplayScreen.payment
            : PosDisplayScreen.paid,
        lines: base?.lines ?? _lines(),
        subtotal: base?.subtotal ?? bill.subtotal,
        discount: base?.discount ?? bill.discount,
        total: bill.dueAmount.isPositive ? bill.dueAmount : bill.total,
        queueNumber: base?.queueNumber,
        payment: const PosDisplayPayment(method: 'qr'),
      ),
    );
  }

  /// The QR is gone (cancelled, expired, left): the display shows what it showed before the QR, without it.
  void _restoreDisplay() {
    if (!_shown) return;
    _shown = false;
    if (!_displayOn) return;
    final PosDisplayCart? base = _displayBase;
    _scope.data.displayFeed.publish(
      base ??
          PosDisplayCart(
            state: PosDisplayScreen.payment,
            lines: _lines(),
            subtotal: widget.bill.subtotal,
            discount: widget.bill.discount,
            total: widget.bill.dueAmount,
          ),
    );
  }

  // --- ko'rinish ------------------------------------------------------------------------------------------------------

  String _left(PosQrPayment qr) {
    final DateTime? until = qr.expiresAt;
    if (until == null) return '';
    final Duration d = until.difference(_scope.services.now());
    if (d.isNegative) return '0:00';
    final int s = d.inSeconds;
    return '${s ~/ 60}:${(s % 60).toString().padLeft(2, '0')}';
  }

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final PosQrSettings s = _settings;
    final PosQrPayment? qr = _qr;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        if (s.providers.length > 1) ...<Widget>[
          ChoiceRow<PosQrProvider>(
            key: const ValueKey<String>('qr-provider'),
            options: <PosQrProvider, String>{
              for (final PosQrProvider p in s.providers) p: p.label,
            },
            selected: _provider,
            onSelected: _pending
                ? (_) {}
                : (PosQrProvider p) => setState(() => _provider = p),
          ),
          const SizedBox(height: 12),
        ],
        if (qr == null || qr.status != PosQrStatus.pending) ...<Widget>[
          MoneyField(
            fieldKey: const ValueKey<String>('qr-amount'),
            controller: _amount,
            label: 'QR summasi',
            helperText: widget.amount < widget.bill.dueAmount
                ? "Aralash to'lov: qolgani boshqa usulda"
                : "Kamroq yozsangiz — qolgani boshqa usulda (aralash)",
            onChanged: (_) => setState(() => _error = null),
          ),
          const SizedBox(height: 12),
          if (qr != null) _finalNotice(c, qr),
          RzButton(
            key: const ValueKey<String>('qr-create'),
            label: qr == null ? 'QR yaratish' : 'Yangi QR',
            icon: Icons.qr_code_2_rounded,
            size: RzButtonSize.lg,
            fullWidth: true,
            loading: _busy,
            onPressed: _busy || _handled ? null : _create,
          ),
        ] else
          _waiting(c, qr),
        if (_error != null) ...<Widget>[
          const SizedBox(height: 12),
          Text(
            _error!,
            key: const ValueKey<String>('qr-error'),
            style: RzType.body.copyWith(color: c.dangerSoftFg),
          ),
        ],
        const SizedBox(height: 8),
        Align(
          alignment: Alignment.centerLeft,
          child: TextButton.icon(
            key: const ValueKey<String>('qr-manual'),
            onPressed: _pending ? null : widget.onManual,
            icon: const Icon(Icons.edit_note_rounded),
            label: const Text("Statik QR (qo'lda tasdiqlash)"),
          ),
        ),
      ],
    );
  }

  Widget _finalNotice(RzColors c, PosQrPayment qr) => Padding(
    padding: const EdgeInsets.only(bottom: 12),
    child: NoticeBanner(
      key: const ValueKey<String>('qr-final'),
      tone: qr.status == PosQrStatus.paid
          ? StatusTone.success
          : StatusTone.warning,
      icon: qr.status == PosQrStatus.paid
          ? Icons.check_circle_rounded
          : Icons.timer_off_outlined,
      message: switch (qr.status) {
        PosQrStatus.paid =>
          "To'landi: ${qr.amount.format()} (${qr.providerLabel ?? qr.provider?.label ?? 'QR'}).",
        PosQrStatus.expired => "QR muddati tugadi: mijoz to'lamadi. Yangi QR yarating yoki boshqa usulda oling.",
        PosQrStatus.cancelled when qr.cancelReason == 'bill_changed' =>
          "Schyot o'zgardi: QR bekor qilindi. Yangisini yarating.",
        PosQrStatus.cancelled => 'QR bekor qilindi.',
        _ => 'QR holati: ${qr.status.wire}',
      },
    ),
  );

  Widget _waiting(RzColors c, PosQrPayment qr) => LayoutBuilder(
    builder: (BuildContext context, BoxConstraints box) {
      final double size = math
          .min(300.0, box.maxWidth - 32)
          .clamp(160.0, 300.0);
      return Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          Center(
            child: DecoratedBox(
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(RzRadius.card),
              ),
              child: Padding(
                padding: const EdgeInsets.all(12),
                child: QrView(
                  key: const ValueKey<String>('qr-image'),
                  payload: qr.payload,
                  size: size,
                ),
              ),
            ),
          ),
          const SizedBox(height: 12),
          Text(
            '${qr.providerLabel ?? qr.provider?.label ?? 'QR'} · ${qr.amount.format()}',
            key: const ValueKey<String>('qr-amount-label'),
            textAlign: TextAlign.center,
            style: RzType.title.copyWith(
              color: c.fg,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 4),
          Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: <Widget>[
              // Static icons (no endless animation): the countdown ticks every second instead
              Icon(
                qr.held ? Icons.sync_rounded : Icons.hourglass_top_rounded,
                size: 18,
                color: c.fgMuted,
              ),
              const SizedBox(width: 8),
              Flexible(
                child: Text(
                  qr.held
                      ? "Mijoz to'lamoqda (provayder tasdiqlashini kuting)"
                      : "Mijoz to'lashini kutmoqda · ${_left(qr)}",
                  key: const ValueKey<String>('qr-status'),
                  textAlign: TextAlign.center,
                  style: RzType.body.copyWith(color: c.fgMuted),
                ),
              ),
            ],
          ),
          const SizedBox(height: 4),
          Text(
            "To'langach chek o'zi chiqadi.",
            textAlign: TextAlign.center,
            style: RzType.footnote.copyWith(color: c.fgMuted),
          ),
          const SizedBox(height: 12),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            alignment: WrapAlignment.center,
            children: <Widget>[
              RzButton(
                key: const ValueKey<String>('qr-print'),
                label: 'Schyotga chop etish',
                icon: Icons.print_outlined,
                variant: RzButtonVariant.secondary,
                onPressed: _busy ? null : _printOnBill,
              ),
              RzButton(
                key: const ValueKey<String>('qr-renew'),
                label: 'Yangi QR',
                icon: Icons.refresh_rounded,
                variant: RzButtonVariant.secondary,
                onPressed: _busy || qr.held ? null : _create,
              ),
              RzButton(
                key: const ValueKey<String>('qr-cancel'),
                label: 'Bekor qilish',
                icon: Icons.close_rounded,
                variant: RzButtonVariant.ghost,
                onPressed: _busy || qr.held ? null : _cancel,
              ),
            ],
          ),
        ],
      );
    },
  );

  static String _randomKey() {
    final math.Random r = math.Random.secure();
    return List<String>.generate(
      16,
      (_) => r.nextInt(256).toRadixString(16).padLeft(2, '0'),
    ).join();
  }
}
