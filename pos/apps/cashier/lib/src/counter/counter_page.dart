import 'dart:async';

import 'package:flutter/material.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_ui/pos_ui.dart';
import 'package:rizqim_core/rizqim_core.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import '../features/billing/cashier_ui.dart';
import '../features/billing/payment_page.dart';
import '../features/order/frequent_items.dart';
import '../features/order/kitchen_tickets.dart';
import '../features/order/line_dialogs.dart';
import '../features/order/menu_panel.dart';
import '../features/order/order_controller.dart' show OrderNotice;
import '../features/order/order_draft.dart';
import '../features/order/order_format.dart';
import '../features/shift/z_close_page.dart';
import '../menu_day/menu_day.dart';
import '../shell/approval_dialog.dart';
import '../shell/destinations.dart';
import '../shell/pos_scope.dart';
import '../shell/routes.dart';
import 'counter_controller.dart';
import 'counter_format.dart';
import 'counter_queue.dart';
import 'counter_ready.dart';
import 'counter_talon.dart';

/// Telefon joylashuvi shu endan tor ekranda (portret Android telefon).
const double counterPhoneWidth = 600;

/// P6-12 "Tez kassa" (A1, A4, A5): menyu panjarasi (kategoriyalar, qidiruv, tez-tez, tez miqdor ×2…×10), bosish = +1,
/// majburiy qo'shimchasi bor taomda — tanlov oynasi; savat yonda (planshet) yoki pastda (telefon). "To'lash" — navbat
/// raqami va buyurtma (taomlar darhol oshxonaga), so'ng to'lov sahifasi; to'langach talon chop etiladi yoki ekranda
/// ko'rsatiladi va "Keyingi mijoz" bir bosishda yangi savat ochadi (keyingi taom bosilsa ham).
///
/// Tezlik: bitta taomli buyurtma — 3 bosish (taom, "To'lash", to'lov sahifasida "To'lash").
///
/// `queue_calling` moduli bilan: "Tayyor" ro'yxati ("Berildi", "Qayta chaqirish"), raqam tayyor bo'lganda ovoz va ekranda
/// e'lon. `phone_mode` moduli bilan: smena bir bosishda ochiladi va yopiladi, printer bo'lmasa talon va chek ekranda.
class CounterPage extends StatefulWidget {
  const CounterPage({super.key});

  @override
  State<CounterPage> createState() => _CounterPageState();
}

class _CounterPageState extends State<CounterPage> {
  CounterController? _ctl;
  CounterReadyBoard? _board;
  StreamSubscription<OrderNotice>? _noticeSub;
  StreamSubscription<String>? _boardErrors;
  StreamSubscription<PosEvent>? _menuSub;
  bool _openingShift = false;

  PosScope get _scope => PosScope.of(context);

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_ctl != null) return;
    final PosScope scope = _scope;
    final CounterController ctl = CounterController(
      data: scope.data,
      tickets: KitchenTickets(
        printing: scope.printing,
        bootstrap: () => scope.session.bootstrap,
        now: scope.services.now,
      ),
      frequent: FrequentItems.of(scope.data),
      queue: CounterQueueNumbers.of(scope.data),
      now: scope.services.now,
    );
    _ctl = ctl;
    _noticeSub = ctl.notices.listen(_notice);
    // P6-14: the customer display shows this cart (only with the `customer_display` module)
    scope.data.displayFeed.enabled = scope.session.hasModule(
      PosModule.customerDisplay,
    );
    ctl.addListener(_publishDisplay);
    unawaited(ctl.load());
    unawaited(ctl.queue.topUp());
    if (scope.session.queueCalling) {
      final CounterReadyBoard board = CounterReadyBoard(
        repository: ctl.queue.repository,
        now: scope.services.now,
        realtime: scope.realtime,
        branchId: scope.session.branchId,
        pollInterval: scope.services.pollInterval,
        livePollInterval: scope.services.livePollInterval,
      )..start();
      _board = board;
      _boardErrors = board.errors.listen(
        (String m) => _notice(OrderNotice(m, error: true)),
      );
    }
    _menuSub = scope.realtime?.events.listen((PosEvent e) {
      if (e is PosMenuAvailabilityChanged) {
        unawaited(ctl.refreshAvailability());
      }
    });
  }

  CounterStage? _shownStage;
  int _shownRevision = -1;

  /// Mijoz ekraniga joriy chek: savat, to'lov (summa, raqam), to'landi ("Rahmat"), keyingi mijoz — bo'sh ekran.
  void _publishDisplay() {
    final CounterController ctl = _ctl!;
    final DisplayFeed feed = _scope.data.displayFeed;
    if (!feed.enabled) return;
    final CounterStage stage = ctl.stage;
    final int revision = Object.hash(
      stage,
      ctl.estimate.tiyin,
      ctl.count,
      ctl.total.tiyin,
      ctl.number,
    );
    if (stage == _shownStage && revision == _shownRevision) return;
    _shownStage = stage;
    _shownRevision = revision;
    if (stage == CounterStage.cart && ctl.isEmpty) {
      feed.idle();
      return;
    }
    final List<PosDisplayLine> lines = <PosDisplayLine>[
      for (final DraftLine l in ctl.lines)
        PosDisplayLine(
          name: l.name,
          quantity: l.quantity,
          unit: l.variant.unit.isMeasured ? l.variant.unit.label : null,
          price: l.variant.price + l.modifiersPrice,
          total: l.estimate,
          modifiers: l.modifierLabels,
        ),
    ];
    final PosMoney total = stage == CounterStage.done
        ? ctl.receipt!.total
        : ctl.total;
    feed.publish(
      PosDisplayCart(
        state: switch (stage) {
          CounterStage.cart => PosDisplayScreen.cart,
          CounterStage.awaitingPayment => PosDisplayScreen.payment,
          CounterStage.done => PosDisplayScreen.paid,
        },
        lines: lines,
        subtotal: total,
        total: total,
        queueNumber: stage == CounterStage.done
            ? ctl.receipt!.number
            : ctl.number,
      ),
    );
  }

  @override
  void dispose() {
    _ctl?.removeListener(_publishDisplay);
    unawaited(_noticeSub?.cancel());
    unawaited(_boardErrors?.cancel());
    unawaited(_menuSub?.cancel());
    _board?.dispose();
    _ctl?.dispose();
    super.dispose();
  }

  void _notice(OrderNotice n) {
    if (!mounted) return;
    final RzColors c = context.rz;
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          content: Text(n.message),
          backgroundColor: n.error ? c.danger : null,
          duration: Duration(seconds: n.error ? 5 : 2),
        ),
      );
  }

  Future<T> _approve<T>(Future<T> Function(String? token) action) =>
      withApproval<T>(context, action);

  // --- menyu ---------------------------------------------------------------------------------------------------------

  Future<void> _onProductTap(PosProduct p) async {
    final CounterController ctl = _ctl!;
    final PosCatalog cat = ctl.catalog!;
    if (ctl.stage == CounterStage.awaitingPayment) {
      _notice(
        const OrderNotice(
          "Avval to'lovni yakunlang yoki buyurtmani bekor qiling.",
          error: true,
        ),
      );
      return;
    }
    final PosVariant? v =
        p.singleVariant ??
        await showVariantPicker(context, product: p, catalog: cat);
    if (v == null || !mounted || !cat.isVariantAvailable(v)) return;
    PosQuantity? qty;
    if (v.unit.isMeasured) {
      qty = await showQuantityKeypad(
        context,
        title: p.name,
        variant: v,
        batch: cat.batchFor(v),
      );
      if (qty == null || !mounted) return;
    }
    var mods = const <String, int>{};
    if (needsModifierChoice(p)) {
      final Map<String, int>? chosen = await showPosModifierDialog(
        context,
        title: p.name,
        groups: modifierSpecs(p, availability: cat.availability),
        basePrice: v.price,
      );
      if (chosen == null || !mounted) return;
      mods = chosen;
    }
    ctl.add(p, v, quantity: qty, modifiers: mods);
  }

  /// Uzoq bosish: ixtiyoriy qo'shimchalar bilan (piyoz, achchiq ...).
  Future<void> _onProductLongPress(PosProduct p) async {
    final CounterController ctl = _ctl!;
    final PosCatalog cat = ctl.catalog!;
    if (ctl.stage == CounterStage.awaitingPayment) return;
    final PosVariant? v =
        p.singleVariant ??
        await showVariantPicker(context, product: p, catalog: cat);
    if (v == null || !mounted || !cat.isVariantAvailable(v)) return;
    if (p.modifierGroups.isEmpty) {
      ctl.add(p, v);
      return;
    }
    final Map<String, int>? chosen = await showPosModifierDialog(
      context,
      title: p.name,
      groups: modifierSpecs(p, availability: cat.availability),
      basePrice: v.price,
    );
    if (chosen == null || !mounted) return;
    ctl.add(p, v, modifiers: chosen);
  }

  // --- to'lov ---------------------------------------------------------------------------------------------------------

  Future<void> _pay() async {
    final CounterController ctl = _ctl!;
    final PosBill? bill = await ctl.checkout(approve: _approve);
    if (bill == null || !mounted) return;
    final PaymentOutcome? outcome = await CashierRoutes.push<PaymentOutcome>(
      context,
      PaymentPage(bill: bill, order: ctl.order),
    );
    if (!mounted) return;
    if (outcome == null) {
      await ctl.refreshSale();
    } else {
      ctl.paymentFinished(outcome.bill, orderClosed: outcome.orderClosed);
    }
    final CounterReceipt? r = ctl.receipt;
    if (r == null || !mounted) return;
    // A4: the talon goes with the sale receipt; no printer (phone mode) — the number is on the screen
    await printTalon(context, r, quiet: true);
  }

  Future<void> _cancelSale() async {
    final CounterController ctl = _ctl!;
    final String? reason = await askReason(
      context,
      title: 'Buyurtmani bekor qilish',
      message: "Taomlar oshxonaga yuborilgan: sexga «O'CHIRILDI» markasi chiqadi. Menejer tasdig'i kerak bo'lishi mumkin.",
      presets: const <String>[
        'Mijoz voz kechdi',
        'Xato kiritildi',
        'Pul yetmadi',
      ],
      confirmLabel: 'Bekor qilish',
      confirmVariant: RzButtonVariant.danger,
    );
    if (reason == null || !mounted) return;
    await ctl.cancelSale(reason, approve: _approve);
  }

  // --- smena (telefon rejimi: bir bosishda) ------------------------------------------------------------------------------

  Future<void> _openShift() async {
    final PosScope scope = _scope;
    if (!scope.session.phoneMode) {
      scope.go(CashierDestination.shift);
      return;
    }
    setState(() => _openingShift = true);
    final bool ok = await runGuarded(context, () async {
      final PosShift shift = await withApproval<PosShift>(
        context,
        (String? token) => scope.data.shifts.open(openingCash: PosMoney.zero),
      );
      scope.session.updateShift(shift.toSummary());
    });
    if (!mounted) return;
    // The banner goes away: no snackbar over the phone's cart bar
    setState(() => _openingShift = false);
    if (!ok) return;
  }

  Future<void> _closeShift() async {
    final PosScope scope = _scope;
    final String? uuid = scope.session.shift?.uuid;
    if (uuid == null) return;
    await runGuarded(context, () async {
      final PosShiftWithReport x = await scope.data.shifts.xReport(uuid);
      final PosShift? shift = x.shift;
      if (!mounted || shift == null || x.report == null) return;
      final PosShift? closed = await CashierRoutes.push<PosShift>(
        context,
        ZClosePage(shift: shift, report: x.report!),
      );
      if (closed != null && mounted) {
        showPosMessage(context, 'Smena yopildi.');
      }
    });
  }

  // --- ko'rinish ------------------------------------------------------------------------------------------------------

  @override
  Widget build(BuildContext context) {
    final CounterController ctl = _ctl!;
    final RzColors c = context.rz;
    return Material(
      color: c.page,
      child: ListenableBuilder(
        listenable: Listenable.merge(<Listenable>[ctl, ?_board]),
        builder: (BuildContext context, _) {
          final PosException? error = ctl.loadError;
          if (error != null && ctl.catalog == null) {
            return InlineError(
              message: error.isNetworkError
                  ? "Aloqa yo'q: menyuni yuklab bo'lmadi."
                  : error.message,
              onRetry: () => unawaited(ctl.load()),
            );
          }
          if (ctl.catalog == null) {
            return const Center(child: CircularProgressIndicator());
          }
          return LayoutBuilder(
            builder: (BuildContext context, BoxConstraints box) {
              final bool phone = box.maxWidth < counterPhoneWidth;
              return phone ? _phone(ctl) : _tablet(ctl, box.maxWidth);
            },
          );
        },
      ),
    );
  }

  Widget _tablet(CounterController ctl, double width) {
    final RzColors c = context.rz;
    return Row(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        Expanded(child: _menuColumn(ctl, phone: false)),
        VerticalDivider(width: 1, color: c.line),
        SizedBox(
          width: width >= 1200 ? 420 : 360,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              if (_board != null && _hasQueue)
                ConstrainedBox(
                  constraints: const BoxConstraints(maxHeight: 240),
                  child: _ReadyList(board: _board!, kds: _kds),
                ),
              Expanded(child: _cart(ctl, phone: false)),
            ],
          ),
        ),
      ],
    );
  }

  bool get _hasQueue =>
      _board != null &&
      !_board!.disabled &&
      (_board!.ready.isNotEmpty || (!_kds && _board!.preparing.isNotEmpty));

  bool get _kds => _scope.session.hasModule(PosModule.kds);

  Widget _phone(CounterController ctl) {
    if (ctl.stage == CounterStage.done) {
      return SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: _doneView(ctl, phone: true),
      );
    }
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        Expanded(child: _menuColumn(ctl, phone: true)),
        PosCartBar(
          count: ctl.count,
          total: ctl.total,
          number:
              ctl.stage == CounterStage.awaitingPayment && ctl.number != null
              ? '${ctl.number}'
              : null,
          busy: ctl.busy,
          onOpen: () => unawaited(_openCartSheet()),
          onPay: _canPay(ctl) ? () => unawaited(_pay()) : null,
        ),
      ],
    );
  }

  bool _canPay(CounterController ctl) =>
      !ctl.busy &&
      (ctl.stage == CounterStage.awaitingPayment ||
          (ctl.stage == CounterStage.cart && !ctl.isEmpty));

  Future<void> _openCartSheet() {
    final PosScope scope = _scope;
    final CounterController ctl = _ctl!;
    return showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (BuildContext sheet) => PosScope(
        services: scope.services,
        realtime: scope.realtime,
        navigate: scope.navigate,
        child: ConstrainedBox(
          constraints: BoxConstraints(
            maxHeight: MediaQuery.sizeOf(sheet).height * 0.8,
          ),
          child: ListenableBuilder(
            listenable: ctl,
            builder: (BuildContext context, _) => _cart(
              ctl,
              phone: true,
              onPay: () {
                Navigator.of(sheet).pop();
                unawaited(_pay());
              },
              onCancel: () {
                Navigator.of(sheet).pop();
                unawaited(_cancelSale());
              },
            ),
          ),
        ),
      ),
    );
  }

  Widget _menuColumn(CounterController ctl, {required bool phone}) {
    final PosScope scope = _scope;
    final PosSession session = scope.session;
    final PosCatalog cat = ctl.catalog!;
    final ReadyAnnouncement? said = _board?.announcement;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        if (session.shift == null) _shiftBanner(session, phone: phone),
        if (said != null) _announcementBanner(said),
        Padding(
          padding: EdgeInsets.fromLTRB(12, phone ? 8 : 12, 12, 0),
          child: _header(ctl, phone: phone),
        ),
        Expanded(
          child: MenuPanel(
            catalog: cat,
            enabled: ctl.stage != CounterStage.awaitingPayment && !ctl.busy,
            columns: phone ? 1 : null,
            frequent: FrequentItems.of(scope.data).top(cat),
            onTap: (PosProduct p) => unawaited(_onProductTap(p)),
            onLongPress: (PosProduct p) => unawaited(_onProductLongPress(p)),
          ),
        ),
      ],
    );
  }

  Widget _header(CounterController ctl, {required bool phone}) {
    final PosSession session = _scope.session;
    final bool hall = ctl.channel == PosChannel.hall;
    final Widget channel = SegmentedButton<PosChannel>(
      segments: <ButtonSegment<PosChannel>>[
        ButtonSegment<PosChannel>(
          value: PosChannel.takeaway,
          label: const Text('Olib ketish'),
          icon: phone ? null : const Icon(Icons.shopping_bag_outlined),
        ),
        ButtonSegment<PosChannel>(
          value: PosChannel.hall,
          label: const Text('Zalda'),
          icon: phone ? null : const Icon(Icons.restaurant_outlined),
        ),
      ],
      selected: <PosChannel>{hall ? PosChannel.hall : PosChannel.takeaway},
      onSelectionChanged: ctl.editable || ctl.stage == CounterStage.done
          ? (Set<PosChannel> s) => unawaited(ctl.setChannel(s.first))
          : null,
    );
    final Widget quantity = PosQuickQuantityBar(
      value: ctl.multiplier,
      compact: phone,
      onChanged: ctl.setMultiplier,
    );
    final List<Widget> tools = <Widget>[
      if (phone && _board != null && !_board!.disabled)
        Badge(
          isLabelVisible: _board!.ready.isNotEmpty,
          label: Text('${_board!.ready.length}'),
          child: IconButton(
            key: const ValueKey<String>('counter-ready'),
            tooltip: 'Tayyor raqamlar',
            onPressed: () => unawaited(_openReadySheet()),
            icon: const Icon(Icons.campaign_outlined),
          ),
        ),
      if (session.phoneMode &&
          session.shift != null &&
          (session.can(PosPermissions.closeShift) ||
              session.needsApproval(PosPermissions.closeShift)))
        IconButton(
          key: const ValueKey<String>('counter-close-shift'),
          tooltip: 'Smenani yopish (Z)',
          onPressed: () => unawaited(_closeShift()),
          icon: const Icon(Icons.lock_clock_outlined),
        ),
    ];
    if (phone) {
      return Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          Row(
            children: <Widget>[
              Expanded(child: channel),
              ...tools,
            ],
          ),
          const SizedBox(height: 8),
          quantity,
        ],
      );
    }
    return Row(
      children: <Widget>[
        channel,
        const SizedBox(width: 12),
        Expanded(child: quantity),
        ...tools,
      ],
    );
  }

  Widget _shiftBanner(PosSession session, {required bool phone}) {
    final bool can =
        session.can(PosPermissions.openShift) ||
        session.needsApproval(PosPermissions.openShift);
    final Widget? button = can
        ? RzButton(
            key: const ValueKey<String>('counter-open-shift'),
            label: 'Smenani ochish',
            icon: Icons.play_arrow_rounded,
            variant: phone
                ? RzButtonVariant.primary
                : RzButtonVariant.secondary,
            size: phone ? RzButtonSize.lg : RzButtonSize.md,
            fullWidth: phone,
            loading: _openingShift,
            onPressed: _openingShift ? null : () => unawaited(_openShift()),
          )
        : null;
    const String text =
        "Smena ochilmagan: to'lov olish uchun avval smenani oching.";
    if (!phone) {
      return Padding(
        padding: const EdgeInsets.fromLTRB(12, 12, 12, 0),
        child: NoticeBanner(
          message: text,
          tone: StatusTone.warning,
          action: button,
        ),
      );
    }
    // Phone: the message, then one big button (one tap opens the shift in the phone mode)
    return Padding(
      padding: const EdgeInsets.fromLTRB(12, 8, 12, 0),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          const NoticeBanner(message: text, tone: StatusTone.warning),
          if (button != null) ...<Widget>[const SizedBox(height: 8), button],
        ],
      ),
    );
  }

  Widget _announcementBanner(ReadyAnnouncement a) {
    final RzColors c = context.rz;
    return Semantics(
      liveRegion: true,
      label: a.text,
      child: Container(
        key: const ValueKey<String>('ready-announcement'),
        margin: const EdgeInsets.fromLTRB(12, 12, 12, 0),
        padding: const EdgeInsets.fromLTRB(16, 10, 8, 10),
        decoration: BoxDecoration(
          color: Color.alphaBlend(c.successSoft, c.surface),
          borderRadius: BorderRadius.circular(RzRadius.card),
          border: Border.all(color: c.success),
        ),
        child: Row(
          children: <Widget>[
            Icon(Icons.campaign_rounded, color: c.successSoftFg, size: 32),
            const SizedBox(width: 12),
            Text(
              '${a.number}',
              style: RzType.display.copyWith(
                color: c.successSoftFg,
                fontWeight: FontWeight.w800,
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Text(
                a.recall ? 'Qayta chaqirildi' : 'Tayyor — mijozni chaqiring',
                style: RzType.headline.copyWith(color: c.successSoftFg),
              ),
            ),
            IconButton(
              tooltip: 'Yopish',
              onPressed: _board?.dismissAnnouncement,
              icon: const Icon(Icons.close_rounded),
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _openReadySheet() {
    final PosScope scope = _scope;
    final CounterReadyBoard board = _board!;
    return showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (BuildContext sheet) => PosScope(
        services: scope.services,
        realtime: scope.realtime,
        navigate: scope.navigate,
        child: ConstrainedBox(
          constraints: BoxConstraints(
            maxHeight: MediaQuery.sizeOf(sheet).height * 0.7,
          ),
          child: _ReadyList(board: board, kds: _kds, showEmpty: true),
        ),
      ),
    );
  }

  // --- savat ----------------------------------------------------------------------------------------------------------

  Widget _cart(
    CounterController ctl, {
    required bool phone,
    VoidCallback? onPay,
    VoidCallback? onCancel,
  }) {
    final RzColors c = context.rz;
    if (ctl.stage == CounterStage.done) {
      return SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: _doneView(ctl, phone: phone),
      );
    }
    final bool waiting = ctl.stage == CounterStage.awaitingPayment;
    final PosOrder? order = ctl.order;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 12, 8, 4),
          child: Row(
            children: <Widget>[
              Expanded(
                child: Text(
                  waiting ? "To'lov kutilmoqda" : 'Savat',
                  style: RzType.title.copyWith(color: c.fg),
                ),
              ),
              if (!waiting && !ctl.isEmpty)
                TextButton.icon(
                  key: const ValueKey<String>('counter-clear'),
                  onPressed: ctl.editable || ctl.openUnknown ? ctl.clear : null,
                  icon: const Icon(Icons.delete_sweep_outlined),
                  label: const Text('Tozalash'),
                ),
            ],
          ),
        ),
        if (waiting) ...<Widget>[
          if (ctl.number != null)
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              child: PosQueueNumberCard(
                number: '${ctl.number}',
                orderLabel: counterOrderLabel(ctl.channel),
                dense: true,
              ),
            ),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 0),
            child: Text(
              "Buyurtma ochildi va oshxonaga yuborildi. To'lovni oling yoki mijoz voz kechsa bekor qiling.",
              style: RzType.footnote.copyWith(color: c.fgMuted),
            ),
          ),
        ],
        Expanded(
          child: waiting && order != null
              ? ListView(
                  padding: const EdgeInsets.symmetric(horizontal: 8),
                  children: <Widget>[
                    for (final PosOrderItem i in order.activeItems)
                      PosOrderLineTile(
                        quantityLabel: i.quantity.label(i.unit),
                        name: i.displayName,
                        parts: setPartLines(i.setParts),
                        modifiers: itemModifierLabels(i),
                        amount: i.totalPrice,
                      ),
                  ],
                )
              : ctl.isEmpty
              ? EmptyState(
                  icon: Icons.touch_app_outlined,
                  title: "Savat bo'sh",
                  message: phone ? "Taomni bosing: har bosish +1." : "Menyudan taomni bosing: har bosish +1. Ko'proq bo'lsa — avval ×2…×10.",
                )
              : ListView(
                  padding: const EdgeInsets.symmetric(horizontal: 8),
                  children: <Widget>[
                    for (final DraftLine l in ctl.lines) _lineTile(ctl, l),
                  ],
                ),
        ),
        Container(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 16),
          decoration: BoxDecoration(
            color: c.surface,
            border: Border(top: BorderSide(color: c.line)),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              AmountRow(
                label: waiting ? "To'lanadi" : 'Jami (taxminan)',
                amount: ctl.total,
                bold: true,
              ),
              const SizedBox(height: 12),
              RzButton(
                key: ValueKey<String>(
                  phone ? 'counter-pay-sheet' : 'counter-pay',
                ),
                label: "To'lash",
                icon: Icons.payments_outlined,
                size: RzButtonSize.lg,
                fullWidth: true,
                loading: ctl.busy,
                onPressed: _canPay(ctl)
                    ? (onPay ?? () => unawaited(_pay()))
                    : null,
              ),
              if (waiting) ...<Widget>[
                const SizedBox(height: 8),
                RzButton(
                  key: const ValueKey<String>('counter-cancel'),
                  label: 'Bekor qilish',
                  icon: Icons.cancel_outlined,
                  variant: RzButtonVariant.ghost,
                  fullWidth: true,
                  onPressed: ctl.busy
                      ? null
                      : (onCancel ?? () => unawaited(_cancelSale())),
                ),
              ],
            ],
          ),
        ),
      ],
    );
  }

  Widget _lineTile(CounterController ctl, DraftLine l) => PosOrderLineTile(
    key: ValueKey<String>('cart-${l.key}'),
    quantityLabel: l.quantity.label(l.variant.unit),
    name: l.name,
    // P6-15: a kompleks shows its parts
    parts: setPartLines(l.product.setParts),
    modifiers: l.modifierLabels,
    amount: l.estimate,
    trailing: l.variant.unit.isMeasured
        ? IconButton(
            tooltip: 'Olib tashlash',
            onPressed: () => ctl.remove(l.key),
            icon: const Icon(Icons.delete_outline),
          )
        : Row(
            mainAxisSize: MainAxisSize.min,
            children: <Widget>[
              IconButton(
                key: ValueKey<String>('cart-minus-${l.key}'),
                tooltip: 'Kamaytirish',
                onPressed: () => ctl.step(l.key, -1),
                icon: Icon(
                  l.quantity <= PosQuantity.one
                      ? Icons.delete_outline
                      : Icons.remove,
                ),
              ),
              IconButton(
                key: ValueKey<String>('cart-plus-${l.key}'),
                tooltip: "Ko'paytirish",
                onPressed: () => ctl.step(l.key, 1),
                icon: const Icon(Icons.add),
              ),
            ],
          ),
  );

  Widget _doneView(CounterController ctl, {required bool phone}) {
    final RzColors c = context.rz;
    final CounterReceipt r = ctl.receipt!;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        Row(
          children: <Widget>[
            Icon(Icons.check_circle_rounded, color: c.success, size: 28),
            const SizedBox(width: 8),
            Expanded(
              child: Text(
                "To'landi: ${r.total.format()}",
                style: RzType.title.copyWith(color: c.fg),
              ),
            ),
          ],
        ),
        const SizedBox(height: 12),
        if (r.number != null)
          PosQueueNumberCard(
            number: '${r.number}',
            label: 'Navbat raqami',
            orderLabel:
                r.ticket?.orderLabel ??
                counterOrderLabel(r.order.posChannel ?? ctl.channel),
            message:
                r.ticket?.message ??
                "Buyurtma tayyor bo'lganda raqam chaqiriladi.",
          ),
        const SizedBox(height: 16),
        RzButton(
          key: const ValueKey<String>('counter-next'),
          label: 'Keyingi mijoz',
          icon: Icons.arrow_forward_rounded,
          size: RzButtonSize.lg,
          fullWidth: true,
          onPressed: ctl.nextCustomer,
        ),
        const SizedBox(height: 8),
        Wrap(
          spacing: 8,
          runSpacing: 4,
          alignment: WrapAlignment.center,
          children: <Widget>[
            if (r.number != null)
              TextButton.icon(
                key: const ValueKey<String>('counter-talon'),
                onPressed: () => unawaited(printTalon(context, r)),
                icon: const Icon(Icons.confirmation_number_outlined),
                label: const Text('Talonni chop etish'),
              ),
            TextButton.icon(
              key: const ValueKey<String>('counter-receipt'),
              onPressed: () => unawaited(showCounterReceipt(context, r)),
              icon: const Icon(Icons.receipt_long_outlined),
              label: const Text('Chekni ko\'rsatish'),
            ),
          ],
        ),
        if (!phone)
          Padding(
            padding: const EdgeInsets.only(top: 8),
            child: Text(
              'Keyingi taomni bossangiz ham yangi mijoz boshlanadi.',
              textAlign: TextAlign.center,
              style: RzType.footnote.copyWith(color: c.fgMuted),
            ),
          ),
      ],
    );
  }
}

/// "Tayyor" ro'yxati: tayyor raqamlar ("Berildi", "Qayta chaqirish") va KDS'siz kassada tayyorlanayotganlar
/// ("Tayyor").
class _ReadyList extends StatelessWidget {
  const _ReadyList({
    required this.board,
    required this.kds,
    this.showEmpty = false,
  });

  final CounterReadyBoard board;
  final bool kds;
  final bool showEmpty;

  @override
  Widget build(BuildContext context) => ListenableBuilder(
    listenable: board,
    builder: (BuildContext context, _) {
      final RzColors c = context.rz;
      final DateTime now = PosScope.of(context).services.now();
      String waited(DateTime? since) {
        if (since == null) return '';
        final int m = now.difference(since).inMinutes;
        return m < 1 ? 'hozir' : '$m daq';
      }

      final List<PosQueueEntry> preparing = kds
          ? const <PosQueueEntry>[]
          : board.preparing;
      return Material(
        color: c.surface2,
        child: ListView(
          key: const ValueKey<String>('ready-list'),
          shrinkWrap: true,
          padding: const EdgeInsets.all(12),
          children: <Widget>[
            Text(
              board.ready.isEmpty
                  ? 'Tayyor raqam yo\'q'
                  : 'Tayyor: ${board.ready.length}',
              style: RzType.headline.copyWith(color: c.fg),
            ),
            if (board.ready.isEmpty && showEmpty)
              Padding(
                padding: const EdgeInsets.only(top: 6),
                child: Text(
                  'Oshxona taomni tayyor deb belgilaganda raqam shu yerda chiqadi va ovoz chalinadi.',
                  style: RzType.footnote.copyWith(color: c.fgMuted),
                ),
              ),
            for (final PosQueueEntry e in board.ready)
              Padding(
                padding: const EdgeInsets.only(top: 8),
                child: PosReadyNumberTile(
                  key: ValueKey<String>('ready-${e.number}'),
                  number: '${e.number}',
                  detail: <String>[
                    'Tayyor${e.readyAt == null ? '' : ' · ${waited(e.readyAt)}'}',
                    if (e.callCount > 0) '${e.callCount} marta chaqirildi',
                  ].join(' · '),
                  busy: board.isBusy(e.orderUuid),
                  onCall: () => unawaited(board.call(e)),
                  onHanded: () => unawaited(board.handed(e)),
                ),
              ),
            if (preparing.isNotEmpty) ...<Widget>[
              const SizedBox(height: 12),
              Text(
                'Tayyorlanmoqda: ${preparing.length}',
                style: RzType.headline.copyWith(color: c.fg),
              ),
              for (final PosQueueEntry e in preparing)
                Padding(
                  padding: const EdgeInsets.only(top: 8),
                  child: PosReadyNumberTile(
                    key: ValueKey<String>('preparing-${e.number}'),
                    number: '${e.number}',
                    ready: false,
                    detail: 'Kutmoqda · ${waited(e.openedAt)}',
                    busy: board.isBusy(e.orderUuid),
                    onReady: () => unawaited(board.markReady(e)),
                  ),
                ),
            ],
          ],
        ),
      );
    },
  );
}
