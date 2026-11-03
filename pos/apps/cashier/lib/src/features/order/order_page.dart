import 'dart:async';

import 'package:flutter/material.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_ui/pos_ui.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import '../../counter/counter_format.dart';
import '../../shell/approval_dialog.dart';
import '../../shell/limited_mode.dart';
import '../../shell/pos_scope.dart';
import '../../printing/label_printing.dart';
import '../../shell/routes.dart';
import 'combo_sheet.dart';
import 'discount_dialog.dart';
import 'frequent_items.dart';
import 'kitchen_tickets.dart';
import 'line_dialogs.dart';
import 'menu_panel.dart';
import 'order_controller.dart';
import 'order_dialogs.dart';
import 'order_draft.dart';
import 'order_format.dart';
import 'order_opener.dart';
import 'order_panel.dart';
import 'restaurant_widgets.dart';
import 'takeaway_widgets.dart';

/// Buyurtma muharriri: chapda menyu (kategoriyalar, katta tugmalar, qozon qoldig'i, qidiruv, "tez-tez"), o'ngda
/// buyurtma (bosqichlar, yangi bosqich, choy-non tugmalari, jami, amallar).
///
/// Mavjud buyurtma ([orderUuid]); joyda yangisi ([tableId]: avval mehmonlar oynasi); ikkalasi `null` — tez kassa
/// (stolsiz yoki olib ketish: birinchi "Yuborish"/"Saqlash" buyurtmani ochadi).
///
/// Tezlik: ochiq buyurtmaga 3 ta taomni qo'shib oshxonaga yuborish — 4 bosish (3 taom + "Yuborish"); kg taom
/// qo'shilsa — klaviaturada tez tugma va "Qo'shish" (yana 2 bosish).
class OrderPage extends StatefulWidget {
  const OrderPage({super.key, this.orderUuid, this.tableId});

  final String? orderUuid;
  final int? tableId;

  @override
  State<OrderPage> createState() => _OrderPageState();
}

class _OrderPageState extends State<OrderPage> {
  OrderController? _ctl;
  PosRefreshScheduler? _scheduler;
  StreamSubscription<OrderNotice>? _noticeSub;
  StreamSubscription<PosEvent>? _menuSub;
  bool _opening = false;
  bool _showMenu = true;

  PosScope get _scope => PosScope.of(context);

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_ctl != null || _opening) return;
    if (widget.orderUuid == null && widget.tableId != null) {
      _opening = true;
      WidgetsBinding.instance.addPostFrameCallback((_) => _openForTable());
      return;
    }
    _start(widget.orderUuid);
  }

  Future<void> _openForTable() async {
    final OpenTarget target =
        openTargetFromBootstrap(_scope.session.bootstrap, widget.tableId!) ??
        OpenTarget(tableId: widget.tableId!, name: '${widget.tableId}-joy');
    final PosOrder? order = await openTableOrder(context, target: target);
    if (!mounted) return;
    if (order == null) {
      Navigator.of(context).maybePop();
      return;
    }
    setState(() {
      _opening = false;
      _start(order.uuid);
    });
  }

  void _start(String? uuid) {
    final PosScope scope = _scope;
    final OrderController ctl = OrderController(
      data: scope.data,
      tickets: KitchenTickets(
        printing: scope.printing,
        bootstrap: () => scope.session.bootstrap,
        now: scope.services.now,
      ),
      frequent: FrequentItems.of(scope.data),
      orderUuid: uuid,
      labels: CashierLabels(
        printing: scope.printing,
        bootstrap: () => scope.session.bootstrap,
        catalog: () => _ctl?.catalog,
        now: scope.services.now,
      ),
    );
    _ctl = ctl;
    _noticeSub = ctl.notices.listen(_showNotice);
    unawaited(ctl.load());
    _scheduler = PosRefreshScheduler(
      realtime: scope.realtime,
      pollInterval: scope.services.pollInterval,
      livePollInterval: scope.services.livePollInterval,
      eventFilter: (PosEvent e) => ctl.uuid != null && e.orderUuid == ctl.uuid,
      onRefresh: (_) => ctl.refresh(),
    )..start();
    _menuSub = scope.realtime?.events.listen((PosEvent e) {
      if (e is PosMenuAvailabilityChanged) {
        unawaited(ctl.refreshAvailability());
      }
    });
  }

  @override
  void dispose() {
    unawaited(_scheduler?.dispose());
    unawaited(_noticeSub?.cancel());
    unawaited(_menuSub?.cancel());
    _ctl?.dispose();
    super.dispose();
  }

  void _showNotice(OrderNotice n) {
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
    final OrderController ctl = _ctl!;
    final PosCatalog cat = ctl.catalog!;
    final PosVariant? v =
        p.singleVariant ??
        await showVariantPicker(context, product: p, catalog: cat);
    if (v == null || !mounted || !cat.isVariantAvailable(v)) return;
    // P6-31: a combo opens its slot sheet
    if (p.isCombo) {
      final ComboSheetResult? r = await showComboSheet(
        context,
        product: p,
        variant: v,
        catalog: cat,
      );
      if (r == null || !mounted) return;
      ctl.addLine(p, v, PosQuantity.whole(r.quantity), combo: r.choice);
      return;
    }
    var qty = v.minimum;
    if (v.unit.isMeasured) {
      final PosQuantity? q = await showQuantityKeypad(
        context,
        title: p.name,
        variant: v,
        batch: cat.batchFor(v),
      );
      if (q == null || !mounted) return;
      qty = q;
    }
    // P6-32: the defaults preselected (a quick group's default needs no dialog)
    Map<String, int> mods = defaultModifiers(p, availability: cat.availability);
    if (needsModifierChoice(p, availability: cat.availability)) {
      final Map<String, int>? chosen = await showPosModifierDialog(
        context,
        title: p.name,
        groups: modifierSpecs(p, availability: cat.availability),
        initial: mods,
        basePrice: v.price,
      );
      if (chosen == null || !mounted) return;
      mods = chosen;
    }
    ctl.addLine(p, v, qty, modifiers: mods);
  }

  /// P6-32: hajm tugmasi (menyu tugmasida): taom shu hajm bilan, bir bosishda.
  Future<void> _onQuickSize(PosProduct p, PosModifier size) async {
    final OrderController ctl = _ctl!;
    final PosCatalog cat = ctl.catalog!;
    final PosVariant? v =
        p.singleVariant ??
        await showVariantPicker(context, product: p, catalog: cat);
    if (v == null || !mounted || !cat.isVariantAvailable(v)) return;
    final PosModifierGroup group = sizeGroupOf(p)!;
    Map<String, int> mods = toggleQuickModifier(
      <String, int>{
        for (final MapEntry<String, int> e in defaultModifiers(
          p,
          availability: cat.availability,
        ).entries)
          if (!group.modifiers.any((PosModifier m) => m.uuid == e.key))
            e.key: e.value,
      },
      group,
      size,
    );
    if (needsDialogWith(p, mods)) {
      final Map<String, int>? chosen = await showPosModifierDialog(
        context,
        title: p.name,
        groups: modifierSpecs(p, availability: cat.availability),
        initial: mods,
        basePrice: v.price,
      );
      if (chosen == null || !mounted) return;
      mods = chosen;
    }
    ctl.addLine(p, v, v.minimum, modifiers: mods);
  }

  /// P6-34: qadoq qatorini olib tashlash (sabab bilan) yoki qaytarish.
  Future<void> _onPackaging(PosPackagingLine l) async {
    final OrderController ctl = _ctl!;
    if (l.removed) {
      await ctl.restorePackaging(l.ruleId, approve: _approve);
      return;
    }
    final String? reason = await askPackagingReason(context, l);
    if (reason == null || !mounted) return;
    await ctl.removePackaging(l.ruleId, reason, approve: _approve);
  }

  /// P6-34: "Vaqtga" — olib ketish buyurtmasining vaqti (oshxonaga chiqqunicha o'zgaradi).
  Future<void> _onPickup() async {
    final OrderController ctl = _ctl!;
    final PickupPick? pick = await showPickupSlots(
      context,
      load: ctl.takeaway.slots,
      current: pickupTimeLabel(ctl.order?.pickup),
    );
    if (pick == null || !mounted) return;
    await ctl.setPickupTime(pick.slot?.at, approve: _approve);
  }

  Future<void> _onProductLongPress(PosProduct p) async {
    final OrderController ctl = _ctl!;
    final PosCatalog cat = ctl.catalog!;
    final PosVariant? v =
        p.singleVariant ??
        await showVariantPicker(context, product: p, catalog: cat);
    if (v == null || !mounted || !cat.isVariantAvailable(v)) return;
    final LineEdit? e = await showLineEditor(
      context,
      title: p.name,
      product: p,
      variant: v,
      quantity: v.minimum,
      availability: cat.availability,
      isNew: true,
    );
    if (e == null || e.remove) return;
    ctl.addLine(
      p,
      v,
      e.quantity,
      modifiers: e.modifiers,
      note: e.note,
      seatNo: e.seatNo,
      fireMode: e.fireMode,
    );
  }

  Future<void> _onDraftTap(DraftLine l) async {
    final OrderController ctl = _ctl!;
    // P6-31: a combo line changes its picks and count in the combo sheet
    final PosCatalog? cat = ctl.catalog;
    if (l.combo != null && cat != null && l.product.isCombo) {
      final ComboSheetResult? r = await showComboSheet(
        context,
        product: l.product,
        variant: l.variant,
        catalog: cat,
        initial: l.combo,
        quantity: l.quantity.wholePart,
      );
      if (r == null || !mounted) return;
      ctl.updateCombo(l.key, r.choice, PosQuantity.whole(r.quantity));
      return;
    }
    final LineEdit? e = await showLineEditor(
      context,
      title: l.name,
      product: l.product,
      variant: l.variant,
      quantity: l.quantity,
      modifiers: l.modifiers,
      note: l.note,
      seatNo: l.seatNo,
      fireMode: l.fireMode,
      availability: ctl.catalog?.availability,
    );
    if (e == null) return;
    if (e.remove) {
      ctl.removeLine(l.key);
      return;
    }
    ctl.updateLine(
      l.key,
      quantity: e.quantity,
      modifiers: e.modifiers,
      note: e.note,
      seatNo: e.seatNo,
      fireMode: e.fireMode,
    );
  }

  // --- buyurtma amallari --------------------------------------------------------------------------------------------

  Future<void> _onItemTap(PosOrderItem item) async {
    final OrderController ctl = _ctl!;
    final PosSession session = _scope.session;
    final LineAction? a = await showLineActions(
      context,
      item: item,
      open: ctl.isOpen,
      // P6-25: a waiting line is always weighed (the module only gates new ones); a weighed one again with the module
      weigh:
          item.awaitingWeight ||
          (item.weightStatus == PosWeightStatus.weighed &&
              session.hasModule(PosModule.weightLater)),
      // P6-24: the guest (seat) of the line, for the split by guest
      seat:
          session.hasModule(PosModule.guestSplit) &&
          ctl.channel == PosChannel.hall,
    );
    if (a == null || !mounted) return;
    switch (a) {
      case LineAction.sendNow:
        await ctl.sendItems(<int>[item.id], approve: _approve);
      case LineAction.served:
        await ctl.markServed(item, approve: _approve);
      case LineAction.voidLine:
        await _void(item);
      case LineAction.weigh:
        final PosQuantity? q = await showWeightDialog(context, item: item);
        if (q == null || !mounted) return;
        await ctl.setWeight(item, q, approve: _approve);
      case LineAction.seat:
        final (int?,)? picked = await showSeatPicker(
          context,
          item: item,
          guests: ctl.order?.guestsCount,
        );
        if (picked == null || !mounted || picked.$1 == item.seatNo) return;
        await ctl.setSeat(item, picked.$1, approve: _approve);
    }
  }

  Future<void> _void(PosOrderItem item) async {
    final OrderController ctl = _ctl!;
    final bool sent = item.wasSent;
    final bool needsReason = sent || (ctl.order?.billLocked ?? false);
    final ReasonResult? r = await showReasonDialog(
      context,
      title: "${item.displayName}: o'chirish",
      message: sent
          ? "Taom oshxonaga yuborilgan: sexga «O'CHIRILDI» markasi chiqadi. Menejer tasdig'i kerak bo'lishi mumkin."
          : null,
      presets: const <String>[
        'Xato kiritildi',
        'Mehmon voz kechdi',
        'Uzoq kutdi',
        'Taom sifatsiz',
      ],
      confirmLabel: "O'chirish",
      required: needsReason,
      askRestock: sent,
    );
    if (r == null || !mounted) return;
    await ctl.voidItem(
      item,
      reason: r.reason.isEmpty ? null : r.reason,
      restock: r.restock == true ? true : null,
      approve: _approve,
    );
  }

  Future<void> _submit(bool send) async {
    final OrderController ctl = _ctl!;
    await ctl.submit(send: send, approve: _approve);
  }

  Future<void> _sendHeld() async {
    final OrderController ctl = _ctl!;
    final List<PosOrderItem> held = ctl.held;
    if (held.isEmpty) return;
    await ctl.sendItems(<int>[
      for (final PosOrderItem i in held) i.id,
    ], approve: _approve);
  }

  Future<void> _onCharge(PosChargeKind kind, bool waived) async {
    final OrderController ctl = _ctl!;
    if (waived) {
      await ctl.restoreCharge(kind, approve: _approve);
      return;
    }
    final ReasonResult? r = await showReasonDialog(
      context,
      title: '${kind.label}: olib tashlash',
      message: "Sabab jurnalga yoziladi va egaga xabar boradi. Menejer tasdig'i kerak bo'lishi mumkin.",
      presets: const <String>[
        "Mehmon e'tirozi",
        "Xizmat ko'rsatilmadi",
        'Doimiy mijoz',
        'Menejer qarori',
      ],
      confirmLabel: 'Olib tashlash',
    );
    if (r == null || !mounted) return;
    await ctl.waiveCharge(kind, r.reason, approve: _approve);
  }

  /// P3-37: chegirma oynasi; chegaradan ko'pi — server so'raganda menejer PIN'i ([withApproval]).
  Future<void> _discount() async {
    final OrderController ctl = _ctl!;
    final PosOrder? o = ctl.order;
    if (o == null) return;
    final DiscountRules rules = DiscountRules.of(_scope.session);
    final DiscountInput? d = await showDiscountDialog(
      context,
      order: o,
      unlimited: rules.unlimited,
      limitHundredths: rules.limitHundredths,
    );
    if (d == null || !mounted) return;
    await ctl.discount(d.kind, d.value, reason: d.reason, approve: _approve);
  }

  Future<void> _removeDiscount() async {
    final OrderController ctl = _ctl!;
    final PosOrder? o = ctl.order;
    if (o == null) return;
    final bool ok = await confirmDialog(
      context,
      title: 'Chegirma olib tashlansinmi?',
      message:
          '${discountLabel(o)} (${o.discount.format()}) olib tashlanadi, jami qayta hisoblanadi.',
      confirmLabel: 'Olib tashlash',
      cancelLabel: 'Qolish',
    );
    if (!ok || !mounted) return;
    await ctl.removeDiscount(approve: _approve);
  }

  Future<void> _guests() async {
    final OrderController ctl = _ctl!;
    final int? n = await showGuestsDialog(
      context,
      initial: ctl.order?.guestsCount ?? ctl.draftGuests,
    );
    if (n == null || !mounted) return;
    if (ctl.isDraftOrder) {
      ctl.setDraftGuests(n);
      return;
    }
    await ctl.setGuests(n, approve: _approve);
  }

  Future<void> _move() async {
    final OrderController ctl = _ctl!;
    final FloorTable? t = await showMoveDialog(
      context,
      loadFloor: _scope.data.floor.load,
      currentTableId: ctl.order?.table?.id,
    );
    if (t == null || !mounted) return;
    await ctl.move(t, approve: _approve);
  }

  Future<void> _merge() async {
    final OrderController ctl = _ctl!;
    final FloorOrder? src = await showMergeDialog(
      context,
      loadFloor: _scope.data.floor.load,
      currentUuid: ctl.uuid!,
      now: _scope.services.now(),
    );
    if (src == null || !mounted) return;
    final bool ok = await confirmDialog(
      context,
      title: 'Birlashtirilsinmi?',
      message:
          "${src.shortCode ?? 'Buyurtma'} (${src.totalPrice.format()}) shu buyurtmaga qo'shiladi va yopiladi.",
      confirmLabel: 'Birlashtirish',
      cancelLabel: 'Bekor qilish',
    );
    if (!ok || !mounted) return;
    await ctl.merge(src, approve: _approve);
  }

  Future<void> _cancelOrder() async {
    final OrderController ctl = _ctl!;
    final ReasonResult? r = await showReasonDialog(
      context,
      title: 'Buyurtmani bekor qilish',
      message: "Hamma taom o'chiriladi, oshxonaga yuborilganlari uchun «O'CHIRILDI» markasi chiqadi, joy bo'shaydi. Menejer tasdig'i kerak.",
      presets: const <String>[
        'Mehmonlar ketdi',
        'Xato ochildi',
        'Takroriy buyurtma',
      ],
      confirmLabel: 'Bekor qilish',
    );
    if (r == null || !mounted) return;
    final PosOrder? before = ctl.order;
    final bool ok = await ctl.cancel(r.reason, approve: _approve);
    if (!ok || !mounted) return;
    final PosOrder? after = ctl.order;
    if (before != null && after != null) {
      for (final PosOrderItem i in before.items) {
        final PosOrderItem? now = after.item(i.id);
        if (i.wasSent && !i.isVoid && now != null) {
          unawaited(ctl.tickets.printVoid(after, now));
        }
      }
    }
    if (mounted) Navigator.of(context).maybePop();
  }

  Future<void> _bill() async {
    final OrderController ctl = _ctl!;
    if (ctl.draft.isNotEmpty) {
      _showNotice(
        const OrderNotice(
          'Avval yangi bosqichni yuboring yoki saqlang: schyot faqat saqlangan taomlardan tuziladi.',
          error: true,
        ),
      );
      return;
    }
    final String? id = ctl.uuid;
    if (id == null) return;
    await CashierRoutes.openBilling<void>(context, orderUuid: id);
    if (mounted) unawaited(ctl.refresh());
  }

  Future<bool> _confirmLeave() async {
    final OrderController? ctl = _ctl;
    if (ctl == null || ctl.draft.isEmpty) return true;
    return confirmDialog(
      context,
      title: 'Yangi taomlar saqlanmagan',
      message:
          "Yangi bosqichdagi ${ctl.draft.length} ta taom saqlanmagan. Chiqsangiz, ular yo'qoladi.",
      confirmLabel: 'Chiqish',
      danger: true,
    );
  }

  // --- ko'rinish ------------------------------------------------------------------------------------------------------

  @override
  Widget build(BuildContext context) {
    final OrderController? ctl = _ctl;
    if (ctl == null) {
      // Mehmonlar oynasi ochiq (aylanuvchi belgisiz: oyna ustida turadi)
      return Scaffold(
        appBar: AppBar(title: const Text('Yangi buyurtma')),
        body: const SizedBox.expand(),
      );
    }
    return ListenableBuilder(
      listenable: ctl,
      builder: (BuildContext context, _) => PopScope(
        canPop: ctl.draft.isEmpty,
        onPopInvokedWithResult: (bool didPop, Object? _) async {
          if (didPop) return;
          final NavigatorState nav = Navigator.of(context);
          if (await _confirmLeave()) {
            ctl.clearDraft();
            nav.pop();
          }
        },
        child: Scaffold(appBar: _appBar(ctl), body: _body(ctl)),
      ),
    );
  }

  PreferredSizeWidget _appBar(OrderController ctl) {
    final RzColors c = context.rz;
    final PosOrder? o = ctl.order;
    final DateTime now = _scope.services.now();
    final String title = o != null ? orderPlaceLabel(o) : 'Tez kassa';
    final List<String> facts = <String>[
      if (o?.hall != null && o?.table != null) ?o!.hall!.name,
      if (o?.shortCode != null) '#${o!.shortCode}',
      if (o?.waiter?.name != null) o!.waiter!.name!,
      if (o?.openedAt != null) formatOpenFor(now.difference(o!.openedAt!)),
    ];
    final bool hall = ctl.channel == PosChannel.hall;
    final bool canEdit = o != null && o.isOpen && !ctl.busy;
    // P3-31: offline only rounds, sending, unsent voids and the bill (POS_SPEC 5.1)
    final bool limited = limitedMode(context);
    final bool canChange = canEdit && !limited;
    return AppBar(
      title: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Text(title, style: RzType.title.copyWith(color: c.fg)),
          if (facts.isNotEmpty)
            Text(
              facts.join(' · '),
              style: RzType.footnote.copyWith(color: c.fgMuted),
            ),
        ],
      ),
      actions: <Widget>[
        if (ctl.isDraftOrder)
          Padding(
            padding: const EdgeInsets.only(right: 8),
            child: SegmentedButton<PosChannel>(
              segments: const <ButtonSegment<PosChannel>>[
                ButtonSegment<PosChannel>(
                  value: PosChannel.takeaway,
                  label: Text('Olib ketish'),
                  icon: Icon(Icons.shopping_bag_outlined),
                ),
                ButtonSegment<PosChannel>(
                  value: PosChannel.hall,
                  label: Text('Zalda, stolsiz'),
                  icon: Icon(Icons.restaurant_outlined),
                ),
              ],
              selected: <PosChannel>{ctl.channel},
              onSelectionChanged: (Set<PosChannel> s) =>
                  unawaited(ctl.setDraftChannel(s.first)),
            ),
          ),
        if (hall && (o != null || ctl.isDraftOrder))
          TextButton.icon(
            onPressed: ctl.isDraftOrder || canChange ? _guests : null,
            icon: const Icon(Icons.people_outline),
            label: Text(
              '${o?.guestsCount ?? ctl.draftGuests} mehmon',
              style: RzType.headline,
            ),
          ),
        if (o != null && hall)
          Tooltip(
            message: limited ? offlineOnlyReason : "Ko'chirish",
            child: TextButton.icon(
              onPressed: canChange ? _move : null,
              icon: const Icon(Icons.open_with),
              label: const Text("Ko'chirish"),
            ),
          ),
        if (o != null && hall)
          Tooltip(
            message: limited ? offlineOnlyReason : 'Birlashtirish',
            child: TextButton.icon(
              onPressed: canChange ? _merge : null,
              icon: const Icon(Icons.call_merge),
              label: const Text('Birlashtirish'),
            ),
          ),
        if (o != null && DiscountRules.of(_scope.session).allowed)
          Tooltip(
            message: limited ? discountOfflineReason : 'Chegirma',
            child: TextButton.icon(
              key: const ValueKey<String>('order-discount'),
              onPressed: canChange ? _discount : null,
              icon: const Icon(Icons.percent_rounded),
              label: const Text('Chegirma'),
            ),
          ),
        if (o != null)
          PopupMenuButton<String>(
            tooltip: "Boshqa amallar",
            onSelected: (String v) {
              switch (v) {
                case 'refresh':
                  unawaited(ctl.refresh());
                case 'cancel':
                  unawaited(_cancelOrder());
              }
            },
            itemBuilder: (BuildContext context) => <PopupMenuEntry<String>>[
              const PopupMenuItem<String>(
                value: 'refresh',
                child: ListTile(
                  leading: Icon(Icons.refresh),
                  title: Text('Yangilash'),
                ),
              ),
              PopupMenuItem<String>(
                value: 'cancel',
                enabled: canChange,
                child: ListTile(
                  leading: const Icon(Icons.cancel_outlined),
                  title: const Text('Buyurtmani bekor qilish'),
                  subtitle: limited ? const Text(offlineOnlyReason) : null,
                ),
              ),
            ],
          ),
        const SizedBox(width: 8),
      ],
    );
  }

  Widget _body(OrderController ctl) {
    final PosException? error = ctl.loadError;
    if (error != null && ctl.catalog == null) {
      return InlineError(
        message: posErrorText(error),
        onRetry: () => unawaited(ctl.load()),
      );
    }
    final PosCatalog? cat = ctl.catalog;
    if (cat == null || (ctl.uuid != null && ctl.order == null)) {
      return const Center(child: CircularProgressIndicator());
    }
    final PosOrder? o = ctl.order;
    final bool open = ctl.isOpen;
    // P6-11: choy-non buttons only where the format has them (`guest_items`)
    final List<PosQuickItem> quickItems = quickItemsFor(_scope.session);
    final bool guestItems = _scope.session.hasModule(PosModule.guestItems);
    final Widget menu = MenuPanel(
      catalog: cat,
      enabled: open && !ctl.busy,
      frequent: FrequentItems.of(_scope.data).top(
        cat,
        fallback: <int>[
          for (final PosOrderItem i in o?.items ?? const <PosOrderItem>[])
            for (final PosProduct p in cat.products)
              if (p.variants.any((PosVariant v) => v.id == i.productVariantId))
                p.id,
        ],
      ),
      onTap: (PosProduct p) => unawaited(_onProductTap(p)),
      onLongPress: (PosProduct p) => unawaited(_onProductLongPress(p)),
      onQuickPick: (PosProduct p, PosModifier m) =>
          unawaited(_onQuickSize(p, m)),
    );
    final Widget panel = OrderPanel(
      controller: ctl,
      // P3-26: bootstrap'dagi tez tugmalar (menyudagi belgi va choy-non); ro'yxat bo'sh bo'lsa nom bo'yicha
      quickProducts: quickProducts(
        cat,
        quickItems,
        hallId: o?.hall?.id,
        limit: 4,
        fallback: guestItems ? null : (PosProduct _) => false,
      ),
      onQuickAdd: (PosProduct p) {
        final PosVariant? v = quickVariantOf(p, quickItems);
        if (v != null && cat.isVariantAvailable(v)) {
          ctl.addLine(p, v, v.minimum);
        }
      },
      onItemTap: (PosOrderItem i) => unawaited(_onItemTap(i)),
      onDraftTap: (DraftLine l) => unawaited(_onDraftTap(l)),
      onSubmit: (bool send) => unawaited(_submit(send)),
      onSendHeld: () => unawaited(_sendHeld()),
      onBill: () => unawaited(_bill()),
      onCharge: (PosChargeKind k, bool waived) =>
          unawaited(_onCharge(k, waived)),
      onRemoveDiscount:
          DiscountRules.of(_scope.session).allowed && !limitedMode(context)
          ? () => unawaited(_removeDiscount())
          : null,
      // P6-21: a course order always fires its courses (the module only gates new course numbers)
      onSendCourse: (int n) => unawaited(ctl.sendCourse(n, approve: _approve)),
      onPackaging: (PosPackagingLine l) => unawaited(_onPackaging(l)),
      // P6-34: a takeaway order not in the kitchen yet can be given (or changed to) a pickup time
      onPickup:
          o != null &&
              o.isOpen &&
              o.posChannel == PosChannel.takeaway &&
              (o.tableLabel ?? '').trim().isEmpty &&
              (o.pickup?.waiting ??
                  !o.items.any((PosOrderItem i) => i.wasSent)) &&
              _scope.session.hasModule(PosModule.scheduledPickup) &&
              !(_scope.data.offline?.active ?? false)
          ? () => unawaited(_onPickup())
          : null,
    );
    final RzColors c = context.rz;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        KitchenPrintBanner(issues: ctl.tickets.issues),
        if (o != null && !o.isOpen)
          MaterialBanner(
            backgroundColor: Color.alphaBlend(c.dangerSoft, c.surface),
            content: Text(
              o.status == 'canceled'
                  ? 'Buyurtma bekor qilingan.'
                  : 'Buyurtma yopilgan.',
            ),
            actions: <Widget>[
              TextButton(
                onPressed: () => Navigator.of(context).maybePop(),
                child: const Text('Zalga qaytish'),
              ),
            ],
          ),
        if (o != null && o.billLocked && o.isOpen)
          MaterialBanner(
            backgroundColor: Color.alphaBlend(c.brandSoft, c.surface),
            content: const Text(
              "Schyot chiqarilgan: o'chirish, chegirma va xizmat qatorini olib tashlash menejer tasdig'i bilan.",
            ),
            actions: const <Widget>[SizedBox.shrink()],
          ),
        Expanded(
          child: LayoutBuilder(
            builder: (BuildContext context, BoxConstraints box) {
              if (box.maxWidth >= 900) {
                return Row(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: <Widget>[
                    Expanded(child: menu),
                    VerticalDivider(width: 1, color: c.line),
                    SizedBox(
                      width: box.maxWidth >= 1200 ? 460 : 400,
                      child: panel,
                    ),
                  ],
                );
              }
              return Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: <Widget>[
                  Padding(
                    padding: const EdgeInsets.all(8),
                    child: SegmentedButton<bool>(
                      segments: <ButtonSegment<bool>>[
                        const ButtonSegment<bool>(
                          value: true,
                          label: Text('Menyu'),
                          icon: Icon(Icons.restaurant_menu),
                        ),
                        ButtonSegment<bool>(
                          value: false,
                          label: Text('Buyurtma (${ctl.draft.length})'),
                          icon: const Icon(Icons.receipt_long_outlined),
                        ),
                      ],
                      selected: <bool>{_showMenu},
                      onSelectionChanged: (Set<bool> s) =>
                          setState(() => _showMenu = s.first),
                    ),
                  ),
                  Expanded(child: _showMenu ? menu : panel),
                ],
              );
            },
          ),
        ),
      ],
    );
  }
}
