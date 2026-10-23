import 'dart:async';

import 'package:flutter/material.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_ui/pos_ui.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import '../../shell/pos_scope.dart';
import '../../shell/routes.dart';
import '../order/kitchen_tickets.dart';
import '../order/order_format.dart';
import '../order/order_opener.dart';
import 'floor_layout.dart';

/// Zal xaritasi (kassaning asosiy ekrani): zallar — yorliqlar, xona, tapchan va stollar holat rangi bilan (bo'sh, band,
/// bron kutilmoqda, schyot chiqdi, uzoq ochiq), belgilar (necha soat ochiq, mehmonlar, ofitsiant, summa, bron vaqti);
/// o'ngda stolsiz va olib ketish buyurtmalari va "Tez kassa".
///
/// Bo'sh joy bosilsa — buyurtma ochish oynasi (mehmonlar, ofitsiant), so'ng buyurtma muharriri; band joy — uning
/// buyurtmasi (bir nechta bo'lsa — tanlash). Real-time hodisalarida (`order.*`, `booking.changed`) va polling bilan
/// yangilanadi ([PosRefreshScheduler]).
class FloorPage extends StatefulWidget {
  const FloorPage({super.key});

  @override
  State<FloorPage> createState() => _FloorPageState();
}

class _FloorPageState extends State<FloorPage> {
  PosFloor? _floor;
  PosException? _error;
  bool _loading = false;
  int? _hallId;
  PosRefreshScheduler? _scheduler;
  Timer? _ticker;

  PosScope get _scope => PosScope.of(context);

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_scheduler != null) return;
    final PosScope scope = _scope;
    _scheduler = PosRefreshScheduler(
      realtime: scope.realtime,
      pollInterval: scope.services.pollInterval,
      livePollInterval: scope.services.livePollInterval,
      eventFilter: (PosEvent e) =>
          e is PosOrderEvent ||
          e is PosOrderItemsChanged ||
          e is PosOrderBillChanged ||
          e is PosBookingChanged,
      onRefresh: (_) => _load(),
    )..start();
    unawaited(_scheduler!.refreshNow());
    // Ochiq vaqtlar har 30 soniyada yangilanadi
    _ticker = Timer.periodic(const Duration(seconds: 30), (_) {
      if (mounted) setState(() {});
    });
  }

  @override
  void dispose() {
    unawaited(_scheduler?.dispose());
    _ticker?.cancel();
    super.dispose();
  }

  Future<void> _load() async {
    if (!mounted) return;
    setState(() => _loading = true);
    try {
      final PosFloor floor = await _scope.data.floor.load();
      if (!mounted) return;
      setState(() {
        _floor = floor;
        _error = null;
        final List<FloorHall> halls = floor.halls;
        if (halls.isNotEmpty && !halls.any((FloorHall h) => h.id == _hallId)) {
          _hallId = halls.first.id;
        }
      });
    } on PosException catch (e) {
      if (mounted) setState(() => _error = e);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  /// Server soati bilan (necha daqiqa ochiq to'g'ri chiqsin).
  DateTime _now() {
    final PosScope scope = _scope;
    return scope.services.now().add(
      scope.session.bootstrap?.clockSkew ?? Duration.zero,
    );
  }

  Future<void> _openExisting(String uuid) async {
    await CashierRoutes.openOrder<void>(context, orderUuid: uuid);
    if (mounted) unawaited(_scheduler?.refreshNow());
  }

  Future<void> _openNew(FloorTable t) async {
    final PosOrder? order = await openTableOrder(
      context,
      target: OpenTarget.fromFloor(t),
    );
    if (!mounted) return;
    if (order == null) {
      unawaited(_scheduler?.refreshNow());
      return;
    }
    await _openExisting(order.uuid);
  }

  Future<void> _onTap(FloorTable t, {bool long = false}) async {
    if (t.status == FloorTableStatus.inactive && t.orders.isEmpty) return;
    if (t.orders.isEmpty) return _openNew(t);
    if (t.orders.length == 1 && !long) {
      return _openExisting(t.orders.single.uuid);
    }
    final String? choice = await _chooseOrder(t);
    if (choice == null || !mounted) return;
    if (choice.isEmpty) return _openNew(t);
    return _openExisting(choice);
  }

  /// Joydagi buyurtmalardan biri yoki yangi buyurtma (`''`; xonada — yo'q: xonada bitta buyurtma).
  Future<String?> _chooseOrder(FloorTable t) => showModalBottomSheet<String>(
    context: context,
    showDragHandle: true,
    builder: (BuildContext context) {
      final RzColors c = context.rz;
      final DateTime now = _now();
      return SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              Text(t.name, style: RzType.title.copyWith(color: c.fg)),
              const SizedBox(height: 8),
              for (final FloorOrder o in t.orders)
                Card(
                  child: ListTile(
                    minTileHeight: 64,
                    leading: const Icon(Icons.receipt_long_outlined),
                    title: Text(
                      '${o.shortCode ?? 'Buyurtma'} · ${o.guestsCount ?? 0} mehmon',
                    ),
                    subtitle: Text(
                      <String>[
                        if (o.openedAt != null)
                          formatOpenFor(now.difference(o.openedAt!)),
                        if (o.waiter?.name != null) o.waiter!.name!,
                        '${o.itemsCount} taom',
                      ].join(' · '),
                    ),
                    trailing: Text(
                      o.totalPrice.format(),
                      style: RzType.headline.copyWith(color: c.fg),
                    ),
                    onTap: () => Navigator.of(context).pop(o.uuid),
                  ),
                ),
              if (t.kind != FloorTableKind.room) ...<Widget>[
                const SizedBox(height: 8),
                RzButton(
                  label: 'Yangi buyurtma',
                  icon: Icons.add,
                  size: RzButtonSize.lg,
                  variant: RzButtonVariant.secondary,
                  onPressed: () => Navigator.of(context).pop(''),
                ),
              ],
            ],
          ),
        ),
      );
    },
  );

  Future<void> _quickSale() async {
    await CashierRoutes.openOrder<void>(context);
    if (mounted) unawaited(_scheduler?.refreshNow());
  }

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final PosFloor? floor = _floor;
    final PosException? error = _error;
    if (floor == null) {
      return ColoredBox(
        color: c.page,
        child: error != null
            ? InlineError(
                message: posErrorText(error),
                onRetry: () => unawaited(_scheduler?.refreshNow()),
              )
            : const Center(child: CircularProgressIndicator()),
      );
    }
    final List<FloorHall> halls = floor.halls;
    final FloorHall? hall = halls
        .where((FloorHall h) => h.id == _hallId)
        .firstOrNull;
    return ColoredBox(
      color: c.page,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          KitchenPrintBanner(issues: KitchenPrintIssues.of(_scope.printing)),
          _Header(
            halls: halls,
            selected: _hallId,
            onHall: (int id) => setState(() => _hallId = id),
            stale: error != null,
            loading: _loading,
            onRefresh: () => unawaited(_scheduler?.refreshNow()),
            onQuickSale: () => unawaited(_quickSale()),
          ),
          Expanded(
            child: LayoutBuilder(
              builder: (BuildContext context, BoxConstraints box) {
                final bool wide = box.maxWidth >= 900;
                final Widget map = hall == null
                    ? const EmptyState(
                        icon: Icons.table_restaurant_outlined,
                        title: "Zal yo'q",
                        message: "Boshqaruv panelida Zal bo'limidan zal va xonalarni qo'shing. Hozircha «Tez kassa» ishlaydi.",
                      )
                    : _HallView(
                        hall: hall,
                        now: _now(),
                        onTap: (FloorTable t) => unawaited(_onTap(t)),
                        onLongPress: (FloorTable t) =>
                            unawaited(_onTap(t, long: true)),
                      );
                final Widget untabled = _UntabledPanel(
                  orders: floor.untabledOrders,
                  now: _now(),
                  onOpen: (String uuid) => unawaited(_openExisting(uuid)),
                  onQuickSale: () => unawaited(_quickSale()),
                );
                if (!wide) {
                  return Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: <Widget>[
                      Expanded(flex: 3, child: map),
                      Divider(height: 1, color: c.line),
                      Expanded(flex: 2, child: untabled),
                    ],
                  );
                }
                return Row(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: <Widget>[
                    Expanded(child: map),
                    VerticalDivider(width: 1, color: c.line),
                    SizedBox(width: 320, child: untabled),
                  ],
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}

class _Header extends StatelessWidget {
  const _Header({
    required this.halls,
    required this.selected,
    required this.onHall,
    required this.stale,
    required this.loading,
    required this.onRefresh,
    required this.onQuickSale,
  });

  final List<FloorHall> halls;
  final int? selected;
  final ValueChanged<int> onHall;
  final bool stale;
  final bool loading;
  final VoidCallback onRefresh;
  final VoidCallback onQuickSale;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    return Material(
      color: c.surface,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(12, 8, 12, 8),
        child: Row(
          children: <Widget>[
            Expanded(
              child: SingleChildScrollView(
                scrollDirection: Axis.horizontal,
                child: Row(
                  children: <Widget>[
                    for (final FloorHall h in halls)
                      Padding(
                        padding: const EdgeInsets.only(right: 8),
                        child: ChoiceChip(
                          label: Text(
                            '${h.name}${h.isActive ? '' : ' (yopiq)'}'
                            ' · ${h.tables.where((FloorTable t) => t.orders.isNotEmpty).length}/${h.tables.length}',
                          ),
                          labelStyle: RzType.headline,
                          selected: h.id == selected,
                          showCheckmark: false,
                          materialTapTargetSize: MaterialTapTargetSize.padded,
                          onSelected: (_) => onHall(h.id),
                        ),
                      ),
                  ],
                ),
              ),
            ),
            if (stale)
              Padding(
                padding: const EdgeInsets.only(right: 8),
                child: Tooltip(
                  message:
                      "Xaritani yangilab bo'lmadi: oxirgi holat ko'rsatilmoqda",
                  child: Icon(Icons.cloud_off, color: c.warning),
                ),
              ),
            IconButton(
              tooltip: 'Yangilash',
              onPressed: loading ? null : onRefresh,
              icon: loading
                  ? const SizedBox.square(
                      dimension: 20,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Icon(Icons.refresh),
            ),
            const SizedBox(width: 8),
            RzButton(
              label: 'Tez kassa',
              icon: Icons.bolt,
              size: RzButtonSize.lg,
              onPressed: onQuickSale,
            ),
          ],
        ),
      ),
    );
  }
}

class _HallView extends StatelessWidget {
  const _HallView({
    required this.hall,
    required this.now,
    required this.onTap,
    required this.onLongPress,
  });

  final FloorHall hall;
  final DateTime now;
  final ValueChanged<FloorTable> onTap;
  final ValueChanged<FloorTable> onLongPress;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    if (hall.tables.isEmpty) {
      return const EmptyState(
        icon: Icons.table_restaurant_outlined,
        title: "Bu zalda joy yo'q",
        message: "Boshqaruv panelida xona, tapchan yoki stol qo'shing.",
      );
    }
    final HallMap map = hallMap(hall);
    FloorTable byId(String id) =>
        hall.tables.firstWhere((FloorTable t) => '${t.id}' == id);
    final Map<FloorTableStatus, int> counts = statusCounts(hall.tables);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        Expanded(
          child: FloorMap(
            items: map.items,
            layoutSize: map.size,
            now: now,
            showWaiter: true,
            onTap: (FloorMapItem i) => onTap(byId(i.id)),
            onLongPress: (FloorMapItem i) => onLongPress(byId(i.id)),
          ),
        ),
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 0, 16, 12),
          child: Wrap(
            spacing: 12,
            runSpacing: 8,
            crossAxisAlignment: WrapCrossAlignment.center,
            children: <Widget>[
              for (final FloorTableStatus s in FloorTableStatus.values)
                if ((counts[s] ?? 0) > 0)
                  Row(
                    mainAxisSize: MainAxisSize.min,
                    children: <Widget>[
                      FloorStatusBadge(status: s),
                      const SizedBox(width: 4),
                      Text(
                        '${counts[s]}',
                        style: RzType.headline.copyWith(color: c.fg),
                      ),
                    ],
                  ),
            ],
          ),
        ),
      ],
    );
  }
}

class _UntabledPanel extends StatelessWidget {
  const _UntabledPanel({
    required this.orders,
    required this.now,
    required this.onOpen,
    required this.onQuickSale,
  });

  final List<FloorOrder> orders;
  final DateTime now;
  final ValueChanged<String> onOpen;
  final VoidCallback onQuickSale;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    return ColoredBox(
      color: c.surface,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
            child: Text(
              'Stolsiz va olib ketish (${orders.length})',
              style: RzType.headline.copyWith(color: c.fg),
            ),
          ),
          Expanded(
            child: orders.isEmpty
                ? Padding(
                    padding: const EdgeInsets.all(16),
                    child: Text(
                      "Ochiq stolsiz buyurtma yo'q. Bronsiz mehmon yoki olib ketish uchun «Tez kassa».",
                      style: RzType.body.copyWith(color: c.fgMuted),
                    ),
                  )
                : ListView(
                    padding: const EdgeInsets.symmetric(horizontal: 8),
                    children: <Widget>[
                      for (final FloorOrder o in orders)
                        Card(
                          child: ListTile(
                            key: ValueKey<String>('untabled-${o.uuid}'),
                            minTileHeight: 64,
                            leading: Icon(
                              PosChannel.fromWire(o.channel) ==
                                      PosChannel.takeaway
                                  ? Icons.shopping_bag_outlined
                                  : Icons.restaurant_outlined,
                            ),
                            title: Row(
                              children: <Widget>[
                                Expanded(
                                  child: Text(channelPlaceLabel(o.channel)),
                                ),
                                Text(
                                  o.totalPrice.format(),
                                  style: RzType.headline.copyWith(color: c.fg),
                                ),
                              ],
                            ),
                            subtitle: Text(
                              <String>[
                                if (o.shortCode != null) '#${o.shortCode}',
                                if (o.openedAt != null)
                                  formatOpenFor(now.difference(o.openedAt!)),
                                '${o.itemsCount} taom',
                                if (o.unsentCount > 0)
                                  '${o.unsentCount} yuborilmagan',
                              ].join(' · '),
                            ),
                            onTap: () => onOpen(o.uuid),
                          ),
                        ),
                    ],
                  ),
          ),
        ],
      ),
    );
  }
}
