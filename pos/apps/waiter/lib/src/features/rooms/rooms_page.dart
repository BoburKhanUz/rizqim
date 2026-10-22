import 'dart:async';

import 'package:flutter/material.dart';
import 'package:pos_data/pos_data.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import '../../common/connection_banner.dart';
import '../../common/errors.dart';
import '../../common/notices.dart';
import '../../services.dart';
import '../../shell/waiter_scope.dart';
import '../order/order_page.dart';
import 'open_place_sheet.dart';
import 'place_card.dart';
import 'rooms_controller.dart';

/// "Mening xonalarim": ofitsiantning ochiq buyurtmalari va bo'sh joylar. Uch yorliq: Mening / Hammasi / Bo'sh.
/// Kartochkani bosish: buyurtma ekrani yoki bo'sh joyni ochish.
class RoomsPage extends StatefulWidget {
  const RoomsPage({super.key});

  @override
  State<RoomsPage> createState() => _RoomsPageState();
}

class _RoomsPageState extends State<RoomsPage> {
  RoomsController? _controller;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_controller != null) return;
    final WaiterScope scope = WaiterScope.of(context);
    final WaiterServices s = scope.services;
    _controller = RoomsController(
      data: scope.data,
      staffId: scope.session.staffId ?? 0,
      realtime: scope.realtime,
      pollInterval: s.pollInterval,
      livePollInterval: s.livePollInterval,
      clock: s.now,
    )..start();
  }

  @override
  void dispose() {
    _controller?.dispose();
    super.dispose();
  }

  Future<void> _openEntry(PlaceEntry e) async {
    final RoomsController ctl = _controller!;
    final WaiterScope scope = WaiterScope.of(context);
    final FloorOrder? order = e.order;
    if (order != null) {
      await WaiterScope.push<void>(
        context,
        OrderPage(
          orderUuid: order.uuid,
          title: e.title,
          hallName: e.hall?.name,
        ),
      );
      if (mounted) unawaited(ctl.refresh());
      return;
    }
    final FloorTable? table = e.table;
    if (table == null) return;
    if (!isOnline(scope.data.connectivity)) {
      showNotice(
        context,
        "Aloqa yo'q: joyni ochib bo'lmaydi. Aloqa qaytgach urinib ko'ring.",
        error: true,
      );
      return;
    }
    final PosOrder? opened = await showOpenPlaceSheet(
      context,
      table: table,
      hall: e.hall,
    );
    if (opened == null || !mounted) return;
    unawaited(ctl.refresh());
    await WaiterScope.push<void>(
      context,
      OrderPage(
        orderUuid: opened.uuid,
        title: e.title,
        hallName: e.hall?.name,
        initial: opened,
      ),
    );
    if (mounted) unawaited(ctl.refresh());
  }

  @override
  Widget build(BuildContext context) {
    final RoomsController ctl = _controller!;
    final WaiterScope scope = WaiterScope.of(context);
    return ColoredBox(
      color: context.rz.page,
      child: ListenableBuilder(
        listenable: ctl,
        builder: (BuildContext context, _) => Column(
          children: <Widget>[
            ConnectionBanner(
              connectivity: scope.data.connectivity,
              onRetry: () => unawaited(ctl.refresh()),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 14, 16, 0),
              child: Align(
                alignment: Alignment.centerLeft,
                child: Text(
                  'Mening xonalarim',
                  style: RzType.title.copyWith(color: context.rz.fg),
                ),
              ),
            ),
            _FilterTabs(controller: ctl),
            Expanded(child: _body(context, ctl, scope)),
          ],
        ),
      ),
    );
  }

  Widget _body(BuildContext context, RoomsController ctl, WaiterScope scope) {
    if (!ctl.hasData) {
      if (ctl.error != null) {
        return Padding(
          padding: const EdgeInsets.all(16),
          child: Align(
            alignment: Alignment.topCenter,
            child: InlineError(
              message: describeError(ctl.error!),
              onRetry: () => unawaited(ctl.refresh()),
            ),
          ),
        );
      }
      return const Center(child: CircularProgressIndicator());
    }
    final List<PlaceEntry> entries = ctl.entries(ctl.filter);
    if (entries.isEmpty) {
      return RefreshIndicator(
        onRefresh: ctl.refresh,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          children: <Widget>[
            SizedBox(
              height: 360,
              child: switch (ctl.filter) {
                RoomsFilter.mine => EmptyState(
                  icon: Icons.meeting_room_outlined,
                  title: "Sizda ochiq buyurtma yo'q",
                  message: "Bo'sh xona yoki stolni tanlab, buyurtma oching.",
                  actionLabel: "Bo'sh joylar",
                  onAction: () => ctl.setFilter(RoomsFilter.free),
                ),
                RoomsFilter.free => const EmptyState(
                  icon: Icons.event_busy_outlined,
                  title: "Bo'sh joy yo'q",
                  message: "Hamma xona va stollarda buyurtma bor.",
                ),
                RoomsFilter.all => const EmptyState(
                  icon: Icons.meeting_room_outlined,
                  title: "Xona va stollar yo'q",
                  message: "Ularni boshqaruv panelida qo'shing.",
                ),
              },
            ),
          ],
        ),
      );
    }
    final bool grouped = ctl.filter != RoomsFilter.mine;
    final List<Widget> children = <Widget>[];
    int? lastHall = -1;
    for (final PlaceEntry e in entries) {
      if (grouped && e.hall?.id != lastHall) {
        lastHall = e.hall?.id;
        children.add(_HallHeader(e.hall?.name ?? 'Stolsiz buyurtmalar'));
      }
      final FloorOrder? o = e.order;
      children.add(
        Padding(
          padding: const EdgeInsets.only(bottom: 10),
          child: PlaceCard(
            key: ValueKey<String>(o?.uuid ?? 'free-${e.table?.id}'),
            entry: e,
            staffId: scope.session.staffId,
            readyCount: o == null ? 0 : ctl.readyCount(o.uuid),
            openFor: o == null ? null : ctl.openFor(o),
            showHall: !grouped,
            onTap: () => unawaited(_openEntry(e)),
          ),
        ),
      );
    }
    return RefreshIndicator(
      onRefresh: ctl.refresh,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 4, 16, 24),
        children: children,
      ),
    );
  }
}

class _HallHeader extends StatelessWidget {
  const _HallHeader(this.name);

  final String name;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.fromLTRB(2, 10, 2, 8),
    child: Text(
      name,
      style: RzType.headline.copyWith(color: context.rz.fgMuted),
    ),
  );
}

/// Mening / Hammasi / Bo'sh: katta (kamida 48 dp) yorliqlar, yonida son.
class _FilterTabs extends StatelessWidget {
  const _FilterTabs({required this.controller});

  final RoomsController controller;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 10, 16, 8),
      child: Row(
        children: <Widget>[
          for (final RoomsFilter f in RoomsFilter.values) ...<Widget>[
            if (f != RoomsFilter.values.first) const SizedBox(width: 8),
            Expanded(
              child: Semantics(
                button: true,
                selected: controller.filter == f,
                label: '${f.label}, ${controller.count(f)} ta',
                excludeSemantics: true,
                child: Material(
                  color: controller.filter == f
                      ? c.brand
                      : Color.alphaBlend(c.surface2, c.surface),
                  borderRadius: BorderRadius.circular(RzRadius.card),
                  child: InkWell(
                    borderRadius: BorderRadius.circular(RzRadius.card),
                    onTap: () => controller.setFilter(f),
                    child: SizedBox(
                      height: 52,
                      child: Center(
                        child: FittedBox(
                          fit: BoxFit.scaleDown,
                          child: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: <Widget>[
                              Text(
                                f.label,
                                style: RzType.headline.copyWith(
                                  color: controller.filter == f
                                      ? c.onBrand
                                      : c.fg,
                                ),
                              ),
                              const SizedBox(width: 6),
                              Text(
                                '${controller.count(f)}',
                                style: RzType.body.copyWith(
                                  color: controller.filter == f
                                      ? c.onBrand
                                      : c.fgMuted,
                                ),
                              ),
                              if (f == RoomsFilter.mine &&
                                  controller.readyOrders > 0) ...<Widget>[
                                const SizedBox(width: 6),
                                Badge(
                                  backgroundColor: c.success,
                                  label: Text(
                                    '${controller.readyOrders}',
                                    style: TextStyle(color: c.onSuccess),
                                  ),
                                ),
                              ],
                            ],
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }
}
