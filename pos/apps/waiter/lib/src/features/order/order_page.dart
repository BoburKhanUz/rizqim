import 'dart:async';

import 'package:flutter/material.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import '../../common/connection_banner.dart';
import '../../common/errors.dart';
import '../../common/modules.dart';
import '../../common/notices.dart';
import '../../services.dart';
import '../../shell/approval_dialog.dart';
import '../../shell/waiter_scope.dart';
import 'add_items_sheet.dart';
import 'courses.dart';
import 'order_controller.dart';
import 'order_widgets.dart';
import 'place_sheets.dart';
import 'restaurant_sheets.dart';
import 'slot_pickers.dart';

/// Buyurtma ekrani: bosqichlar (qatorlar holati bilan), "Taom qo'shish", "Yuborish", "Berildi", "Schyot so'rash".
/// Boshqa ofitsiantning buyurtmasi avval faqat ko'rish uchun; tahrirlash menejer tasdig'ini so'raydi.
class OrderPage extends StatefulWidget {
  const OrderPage({
    super.key,
    required this.orderUuid,
    required this.title,
    this.hallName,
    this.initial,
  });

  final String orderUuid;

  /// Joy nomi (buyurtma yuklanguncha sarlavhada turadi).
  final String title;
  final String? hallName;
  final PosOrder? initial;

  @override
  State<OrderPage> createState() => _OrderPageState();
}

class _OrderPageState extends State<OrderPage> {
  OrderController? _controller;

  OrderController get _ctl => _controller!;

  /// Tasdiq oynasi shu sahifa ustida chiqadi.
  Approving get _approving =>
      <T>(Future<T> Function(String? token) action) =>
          withApproval<T>(context, action);

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_controller != null) return;
    final WaiterScope scope = WaiterScope.of(context);
    final WaiterServices s = scope.services;
    _controller =
        OrderController(
            data: scope.data,
            uuid: widget.orderUuid,
            initial: widget.initial,
            realtime: scope.realtime,
            pollInterval: s.pollInterval,
            livePollInterval: s.livePollInterval,
          )
          ..onNotice = _notice
          ..start();
  }

  void _notice(String message, {bool error = false}) {
    if (mounted) showNotice(context, message, error: error);
  }

  @override
  void dispose() {
    _controller?.dispose();
    super.dispose();
  }

  String get _title => _ctl.order?.table?.name ?? widget.title;

  String? get _hall => _ctl.order?.hall?.name ?? widget.hallName;

  Future<void> _addItems() async {
    PosCatalog catalog;
    try {
      catalog = await _ctl.ensureCatalog();
    } on Object catch (e) {
      _notice(describeError(e), error: true);
      return;
    }
    if (!mounted) return;
    await showAddItemsSheet(
      context,
      controller: _ctl,
      catalog: catalog,
      approving: _approving,
      placeTitle: <String>[_title, ?_hall].join(' · '),
    );
  }

  Future<void> _changeGuests() async {
    final int? guests = await showGuestsSheet(
      context,
      current: _ctl.order?.guestsCount ?? 1,
    );
    if (guests != null) await _ctl.setGuests(_approving, guests);
  }

  Future<void> _move() async {
    final PosOrder? o = _ctl.order;
    if (o == null) return;
    final int? tableId = await showMoveSheet(
      context,
      loadFloor: () => _ctl.data.floor.load(),
      currentTableId: o.table?.id,
    );
    if (tableId != null) await _ctl.moveTo(_approving, tableId);
  }

  /// P6-25: og'irlikni kiritish (birinchi marta — `orders.manage`; qayta tortish menejer tasdig'i bilan).
  Future<void> _weigh(PosOrderItem item) async {
    final PosQuantity? q = await showWeightSheet(context, item: item);
    if (q != null) await _ctl.setWeight(_approving, item.id, q);
  }

  /// P6-24: qatorning mehmoni.
  Future<void> _seat(PosOrderItem item) async {
    final ({int? course, int? seat})? slot = await showSlotSheet(
      context,
      title: item.displayName,
      course: false,
      seat: true,
      guests: _ctl.order?.guestsCount,
      initialSeat: item.seatNo,
    );
    if (slot != null) await _ctl.setSeat(_approving, item.id, slot.seat);
  }

  /// P6-24: stolni boshqa ofitsiantga berish (ruxsat bo'lmasa server tasdiq so'raydi, oyna o'zi ochiladi).
  Future<void> _transfer() async {
    final PosOrder? o = _ctl.order;
    if (o == null) return;
    final List<PosStaffRef> waiters = <PosStaffRef>[
      for (final PosStaffRef s
          in _ctl.session.bootstrap?.staff ?? const <PosStaffRef>[])
        if (s.isWaiter && s.id != o.waiter?.id) s,
    ];
    final ({PosStaffRef waiter, String? reason})? pick =
        await showTransferSheet(
          context,
          waiters: waiters,
          placeTitle: <String>[_title, ?_hall].join(' · '),
        );
    if (pick == null) return;
    await _ctl.transferTo(_approving, pick.waiter, reason: pick.reason);
  }

  /// Menyuda "Boshqa ofitsiantga berish" (modul yoqilgan, ochiq stol buyurtmasi): `orders.transfer` (yolg'iz o'zi
  /// yetadi) yoki `orders.manage` — ikkinchisida server menejer PIN'ini so'raydi (oyna o'zi ochiladi). Ikkalasi ham
  /// yo'q xodimni server bu yo'lga kiritmaydi, shuning uchun band ko'rinmaydi.
  bool get _canTransfer {
    final PosOrder? o = _ctl.order;
    return o != null &&
        !_ctl.closed &&
        o.table != null &&
        _ctl.session.transferOn &&
        (_ctl.session.can(PosPermissions.ordersTransfer) ||
            _ctl.session.can(PosPermissions.ordersManage));
  }

  Future<void> _requestBill() async {
    final bool? yes = await showDialog<bool>(
      context: context,
      builder: (BuildContext context) => AlertDialog(
        title: const Text("Schyot so'rash"),
        content: const Text(
          "Schyot kassirga yuboriladi. To'lovni kassir oladi: ofitsiant "
          "ilovasida to'lov yo'q.",
        ),
        actions: <Widget>[
          RzButton(
            label: 'Bekor qilish',
            variant: RzButtonVariant.ghost,
            onPressed: () => Navigator.of(context).pop(false),
          ),
          RzButton(
            label: "So'rash",
            onPressed: () => Navigator.of(context).pop(true),
          ),
        ],
      ),
    );
    if (yes == true) await _ctl.requestBill(_approving);
  }

  @override
  Widget build(BuildContext context) {
    final WaiterScope scope = WaiterScope.of(context);
    return ListenableBuilder(
      listenable: _ctl,
      builder: (BuildContext context, _) {
        final RzColors c = context.rz;
        final PosOrder? order = _ctl.order;
        return Scaffold(
          backgroundColor: c.page,
          appBar: AppBar(
            toolbarHeight: 64,
            title: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: <Widget>[
                Text(_title, maxLines: 1, overflow: TextOverflow.ellipsis),
                if (_hall != null)
                  Text(
                    _hall!,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: RzType.footnote.copyWith(color: c.fgMuted),
                  ),
              ],
            ),
            actions: <Widget>[
              IconButton(
                tooltip: 'Yangilash',
                onPressed: () => unawaited(_ctl.refresh()),
                icon: const Icon(Icons.refresh_rounded),
              ),
              if (_ctl.editable || _canTransfer)
                PopupMenuButton<String>(
                  tooltip: 'Menyu',
                  enabled: _ctl.editable ? _ctl.canAct : _ctl.online,
                  onSelected: (String v) {
                    if (v == 'guests') unawaited(_changeGuests());
                    if (v == 'move') unawaited(_move());
                    if (v == 'transfer') unawaited(_transfer());
                  },
                  itemBuilder: (BuildContext context) =>
                      <PopupMenuEntry<String>>[
                        if (_ctl.editable)
                          const PopupMenuItem<String>(
                            value: 'guests',
                            height: 52,
                            child: ListTile(
                              leading: Icon(Icons.people_outline),
                              title: Text('Mehmonlar soni'),
                            ),
                          ),
                        if (_ctl.editable && order?.table != null)
                          const PopupMenuItem<String>(
                            value: 'move',
                            height: 52,
                            child: ListTile(
                              leading: Icon(Icons.swap_horiz_rounded),
                              title: Text("Boshqa joyga ko'chirish"),
                            ),
                          ),
                        // P6-24: hand the table to another waiter (orders.transfer or a manager's PIN)
                        if (_canTransfer)
                          const PopupMenuItem<String>(
                            value: 'transfer',
                            height: 52,
                            child: ListTile(
                              leading: Icon(Icons.badge_outlined),
                              title: Text('Boshqa ofitsiantga berish'),
                            ),
                          ),
                      ],
                ),
              const SizedBox(width: 4),
            ],
          ),
          body: Column(
            children: <Widget>[
              ConnectionBanner(
                connectivity: scope.data.connectivity,
                onRetry: () => unawaited(_ctl.refresh()),
              ),
              Expanded(child: _content(context, scope)),
            ],
          ),
          bottomNavigationBar: _ctl.editable ? _actionBar(context) : null,
        );
      },
    );
  }

  Widget _content(BuildContext context, WaiterScope scope) {
    final PosOrder? order = _ctl.order;
    if (order == null) {
      final Object? error = _ctl.loadError;
      if (error != null) {
        return Padding(
          padding: const EdgeInsets.all(16),
          child: Align(
            alignment: Alignment.topCenter,
            child: InlineError(
              message: describeError(error),
              onRetry: () => unawaited(_ctl.refresh()),
            ),
          ),
        );
      }
      return const Center(child: CircularProgressIndicator());
    }
    final Map<int, List<PosOrderItem>> rounds = order.itemsByRound;
    final List<int> numbers = rounds.keys.toList()..sort();
    final bool editable = _ctl.editable;
    final List<PosOrderItem> ready = _ctl.readyItems.toList();
    final List<PosCourse> courses = _ctl.courses;
    final int weightPending = _ctl.weightPending;
    final bool seats = _ctl.session.guestSplitOn;
    final bool weighs = _ctl.session.can(PosPermissions.ordersManage);
    return RefreshIndicator(
      onRefresh: _ctl.refresh,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
        children: <Widget>[
          if (_ctl.isForeign) _foreignCard(context, order),
          if (_ctl.closed) _closedCard(context, order),
          _HeaderCard(
            order: order,
            now: scope.services.now(),
            billRequested: _ctl.billRequested,
            billStale: _ctl.billStale,
            onGuests: editable && _ctl.canAct
                ? () => unawaited(_changeGuests())
                : null,
          ),
          if (ready.isNotEmpty) ...<Widget>[
            const SizedBox(height: 12),
            _ReadyCard(
              count: ready.length,
              onAll: editable && !_ctl.isForeign && ready.length > 1
                  ? (_ctl.canAct
                        ? () => unawaited(_ctl.markAllServed(_approving))
                        : null)
                  : null,
              showAll: editable && !_ctl.isForeign && ready.length > 1,
            ),
          ],
          // P6-25: no bill until every "weight later" dish is weighed
          if (weightPending > 0) ...<Widget>[
            const SizedBox(height: 12),
            _WeightPendingCard(count: weightPending),
          ],
          // P6-21: an order with courses always shows them (even if the module was switched off since)
          if (courses.isNotEmpty) ...<Widget>[
            const SizedBox(height: 12),
            CoursesCard(
              courses: courses,
              editable: editable,
              enabled: _ctl.canAct,
              onSend: (int n) => unawaited(_ctl.sendCourse(_approving, n)),
            ),
          ],
          const SizedBox(height: 12),
          if (numbers.isEmpty)
            const Padding(
              padding: EdgeInsets.symmetric(vertical: 24),
              child: EmptyState(
                icon: Icons.restaurant_menu,
                title: "Hali taom yo'q",
                message: "\"Taom qo'shish\" tugmasi bilan birinchi bosqichni oching.",
              ),
            ),
          for (final int n in numbers) ...<Widget>[
            RoundSection(
              roundNo: n,
              items: rounds[n]!,
              tileBuilder: (PosOrderItem item) => OrderLineTile(
                key: ValueKey<int>(item.id),
                item: item,
                editable: editable,
                enabled: _ctl.canAct,
                onServed: () => unawaited(_ctl.markServed(_approving, item.id)),
                onSend: () => unawaited(_ctl.sendLine(_approving, item.id)),
                onVoid: () => unawaited(_ctl.voidPending(_approving, item.id)),
                onWeigh: weighs ? () => unawaited(_weigh(item)) : null,
                onSeat: seats ? () => unawaited(_seat(item)) : null,
              ),
            ),
            const SizedBox(height: 12),
          ],
          if (order.items.isNotEmpty) OrderTotals(order: order),
        ],
      ),
    );
  }

  Widget _foreignCard(BuildContext context, PosOrder order) {
    final RzColors c = context.rz;
    final String name = order.waiter?.name ?? '';
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: DecoratedBox(
        decoration: BoxDecoration(
          color: c.warningSoft,
          borderRadius: BorderRadius.circular(RzRadius.card),
        ),
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Icon(
                    _ctl.editingForeign
                        ? Icons.admin_panel_settings_outlined
                        : Icons.lock_outline,
                    color: c.warningSoftFg,
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Text(
                      _ctl.editingForeign
                          ? "Tahrirlash yoqildi. Har bir amal uchun menejer yoki ega o'z PIN'ini kiritadi."
                          : "Bu buyurtma $name ofitsiantniki: faqat ko'rish mumkin. O'zgartirish uchun menejer tasdig'i kerak.",
                      style: RzType.body.copyWith(color: c.warningSoftFg),
                    ),
                  ),
                ],
              ),
              if (!_ctl.editingForeign && !_ctl.closed) ...<Widget>[
                const SizedBox(height: 12),
                RzButton(
                  label: "Tahrirlash (menejer tasdig'i)",
                  icon: Icons.verified_user_outlined,
                  variant: RzButtonVariant.secondary,
                  size: RzButtonSize.lg,
                  fullWidth: true,
                  onPressed: _ctl.startEditing,
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }

  Widget _closedCard(BuildContext context, PosOrder order) {
    final RzColors c = context.rz;
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: DecoratedBox(
        decoration: BoxDecoration(
          color: c.surface2,
          borderRadius: BorderRadius.circular(RzRadius.card),
        ),
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Row(
            children: <Widget>[
              Icon(Icons.check_circle_outline, color: c.fgMuted),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  order.status == 'canceled'
                      ? 'Buyurtma bekor qilingan.'
                      : 'Buyurtma yopilgan: o\'zgartirib bo\'lmaydi.',
                  style: RzType.body.copyWith(color: c.fg),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _actionBar(BuildContext context) {
    final RzColors c = context.rz;
    final PosOrder? order = _ctl.order;
    final int pending = _ctl.pendingNow.length;
    final bool hasItems = order?.activeItems.isNotEmpty ?? false;
    final bool act = _ctl.canAct;
    return Material(
      color: c.surface,
      elevation: 8,
      child: SafeArea(
        top: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 10, 16, 10),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              RzButton(
                label: "Taom qo'shish",
                icon: Icons.add_rounded,
                size: RzButtonSize.lg,
                fullWidth: true,
                onPressed: act ? () => unawaited(_addItems()) : null,
              ),
              const SizedBox(height: 8),
              Row(
                children: <Widget>[
                  if (pending > 0) ...<Widget>[
                    Expanded(
                      child: RzButton(
                        label: 'Yuborish ($pending)',
                        icon: Icons.send_rounded,
                        variant: RzButtonVariant.success,
                        size: RzButtonSize.lg,
                        fullWidth: true,
                        onPressed: act
                            ? () => unawaited(_ctl.sendPending(_approving))
                            : null,
                      ),
                    ),
                    const SizedBox(width: 8),
                  ],
                  Expanded(
                    child: RzButton(
                      label: _ctl.billRequested
                          ? "Schyot so'ralgan"
                          : "Schyot so'rash",
                      icon: Icons.receipt_long_outlined,
                      variant: RzButtonVariant.secondary,
                      size: RzButtonSize.lg,
                      fullWidth: true,
                      onPressed: act && hasItems && !_ctl.billRequested
                          ? () => unawaited(_requestBill())
                          : null,
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Buyurtma sarlavhasi: mehmonlar (bosilsa o'zgaradi), ochiq vaqt, ofitsiant, schyot holati.
class _HeaderCard extends StatelessWidget {
  const _HeaderCard({
    required this.order,
    required this.now,
    required this.billRequested,
    required this.billStale,
    this.onGuests,
  });

  final PosOrder order;
  final DateTime now;
  final bool billRequested;
  final bool billStale;
  final VoidCallback? onGuests;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final DateTime? opened = order.openedAt;
    Widget chip(IconData icon, String text, Color bg, Color fg) => DecoratedBox(
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(RzRadius.control),
      ),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: <Widget>[
            Icon(icon, size: 16, color: fg),
            const SizedBox(width: 6),
            Text(text, style: RzType.caption.copyWith(color: fg)),
          ],
        ),
      ),
    );
    final Widget guests = InkWell(
      onTap: onGuests,
      borderRadius: BorderRadius.circular(RzRadius.control),
      child: ConstrainedBox(
        constraints: const BoxConstraints(minHeight: 48),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: <Widget>[
            Icon(Icons.people_outline, size: 20, color: c.fgMuted),
            const SizedBox(width: 6),
            Text(
              '${order.guestsCount ?? 0} mehmon',
              style: RzType.headline.copyWith(color: c.fg),
            ),
            if (onGuests != null) ...<Widget>[
              const SizedBox(width: 4),
              Icon(Icons.edit_outlined, size: 18, color: c.brandSoftFg),
            ],
          ],
        ),
      ),
    );
    return Card(
      margin: EdgeInsets.zero,
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: <Widget>[
            Wrap(
              spacing: 18,
              runSpacing: 4,
              crossAxisAlignment: WrapCrossAlignment.center,
              children: <Widget>[
                guests,
                if (opened != null)
                  Row(
                    mainAxisSize: MainAxisSize.min,
                    children: <Widget>[
                      Icon(Icons.schedule, size: 20, color: c.fgMuted),
                      const SizedBox(width: 6),
                      Text(
                        '${formatReceiptTime(opened.toLocal())} dan, '
                        '${formatOpenDuration(now.difference(opened))}',
                        style: RzType.body.copyWith(color: c.fg),
                      ),
                    ],
                  ),
                if (order.waiter != null)
                  Row(
                    mainAxisSize: MainAxisSize.min,
                    children: <Widget>[
                      Icon(Icons.badge_outlined, size: 20, color: c.fgMuted),
                      const SizedBox(width: 6),
                      Text(
                        order.waiter!.name ?? '',
                        style: RzType.body.copyWith(color: c.fg),
                      ),
                    ],
                  ),
              ],
            ),
            if (order.billLocked || billRequested) ...<Widget>[
              const SizedBox(height: 8),
              Wrap(
                spacing: 8,
                runSpacing: 6,
                children: <Widget>[
                  if (order.billLocked)
                    chip(
                      Icons.receipt_long,
                      'Schyot chiqarilgan',
                      c.brandSoft,
                      c.brandSoftFg,
                    ),
                  if (billRequested && !order.billLocked)
                    chip(
                      Icons.notifications_active_outlined,
                      "Schyot so'ralgan",
                      c.infoSoft,
                      c.infoSoftFg,
                    ),
                  if (order.billLocked && billStale)
                    chip(
                      Icons.update,
                      'Schyot eskirdi: kassir qayta chiqaradi',
                      c.warningSoft,
                      c.warningSoftFg,
                    ),
                ],
              ),
            ],
          ],
        ),
      ),
    );
  }
}

/// P6-25: "Og'irlik kutilmoqda (N)": oshpaz yoki menejer tortmaguncha kassir schyot chiqara olmaydi.
class _WeightPendingCard extends StatelessWidget {
  const _WeightPendingCard({required this.count});

  final int count;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    return DecoratedBox(
      decoration: BoxDecoration(
        color: c.warningSoft,
        borderRadius: BorderRadius.circular(RzRadius.card),
      ),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Row(
          children: <Widget>[
            Icon(Icons.scale_outlined, color: c.warningSoftFg),
            const SizedBox(width: 10),
            Expanded(
              child: Text(
                "Og'irlik kutilmoqda ($count): taom tortilgach schyot chiqadi.",
                style: RzType.body.copyWith(color: c.warningSoftFg),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// "N ta taom tayyor" bloki (olib borish kerak), ko'p bo'lsa "Hammasini berdim".
class _ReadyCard extends StatelessWidget {
  const _ReadyCard({required this.count, required this.showAll, this.onAll});

  final int count;
  final bool showAll;
  final VoidCallback? onAll;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    return DecoratedBox(
      decoration: BoxDecoration(
        color: c.successSoft,
        borderRadius: BorderRadius.circular(RzRadius.card),
        border: Border.all(color: c.success, width: 1.5),
      ),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: <Widget>[
            Row(
              children: <Widget>[
                Icon(Icons.room_service_outlined, color: c.successSoftFg),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    '$count ta taom tayyor: olib borish kerak',
                    style: RzType.headline.copyWith(color: c.successSoftFg),
                  ),
                ),
              ],
            ),
            if (showAll) ...<Widget>[
              const SizedBox(height: 10),
              RzButton(
                label: 'Hammasini berdim ($count)',
                icon: Icons.done_all_rounded,
                variant: RzButtonVariant.success,
                size: RzButtonSize.lg,
                fullWidth: true,
                onPressed: onAll,
              ),
            ],
          ],
        ),
      ),
    );
  }
}
