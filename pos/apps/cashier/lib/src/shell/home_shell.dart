import 'dart:async';

import 'package:flutter/material.dart';
import 'package:pos_data/pos_data.dart';
import 'package:rizqim_api/rizqim_api.dart';

import '../features/billing/billing_page.dart';
import '../services.dart';
import 'destinations.dart';
import 'limited_mode.dart';
import 'pos_scope.dart';
import 'top_bar.dart';

/// Ish ekrani (PIN bilan kirilgan, bootstrap yuklangan): yuqori panel, navigatsiya (keng ekranda chapda rail, torda
/// pastda) va bo'lim sahifasi. Bir marta ochilgan bo'lim holati saqlanadi (IndexedStack), ko'rilmagani qurilmaydi.
///
/// Smena holati: `shift.changed` (shu terminal) yoki real-time qayta ulanganda `GET /pos/shifts/current` qayta
/// so'raladi va yuqori paneldagi belgi yangilanadi.
///
/// P3-31: tepada internetsiz rejim lentasi ([LimitedModeBanner]); offlayn to'langan schyotlarning sotuv cheki
/// navbat serverga yetgach shu yerda chop etiladi (to'lov paytida faqat pre-check bor edi: fiskal bo'lmagan to'lov
/// kvitansiyasi chiqmaydi, POS_SPEC 2.11).
class HomeShell extends StatefulWidget {
  const HomeShell({
    super.key,
    required this.services,
    required this.terminal,
    this.realtime,
    this.initial = CashierDestination.floor,
  });

  final CashierServices services;
  final PairedTerminal terminal;
  final PosRealtime? realtime;
  final CashierDestination initial;

  @override
  State<HomeShell> createState() => _HomeShellState();
}

class _HomeShellState extends State<HomeShell> {
  late CashierDestination _current = widget.initial;
  final Set<CashierDestination> _visited = <CashierDestination>{};
  final List<StreamSubscription<Object?>> _subs =
      <StreamSubscription<Object?>>[];

  PosSession get _session => widget.services.session;

  @override
  void initState() {
    super.initState();
    _visited.add(_current);
    _subs.add(_session.changes.listen((_) => _rebuild()));
    final PosOffline? offline = widget.services.data.offline;
    if (offline != null) {
      _subs.add(
        offline.synced.listen(
          (List<SyncOperation> ops) => unawaited(_printSynced(ops)),
        ),
      );
    }
    final PosRealtime? rt = widget.realtime;
    if (rt != null) {
      _subs
        ..add(
          rt.events.listen((PosEvent e) {
            if (e is PosShiftChanged &&
                (e.deviceUuid == null ||
                    e.deviceUuid == widget.terminal.device.uuid)) {
              unawaited(_reloadShift());
            }
          }),
        )
        ..add(rt.resubscribed.listen((_) => unawaited(_reloadShift())));
    }
  }

  @override
  void dispose() {
    for (final StreamSubscription<Object?> s in _subs) {
      unawaited(s.cancel());
    }
    super.dispose();
  }

  void _rebuild() {
    if (mounted) setState(() {});
  }

  Future<void> _reloadShift() async {
    try {
      final PosShiftWithReport current = await widget.services.data.shifts
          .current();
      _session.updateShift(current.shift?.toSummary());
    } on PosException {
      // Aloqa yo'q yoki ruxsat yo'q: oldingi holat qoladi
    }
  }

  /// Offlayn to'langan schyotlar serverga yetdi: sotuv cheki chiqqanlarini chop etadi (bir marta).
  final Set<String> _printed = <String>{};

  Future<void> _printSynced(List<SyncOperation> ops) async {
    final PosData data = widget.services.data;
    for (final SyncOperation op in ops) {
      if (op.type != SyncOpType.billPay ||
          op.status != SyncOpStatus.applied ||
          !_printed.add(op.opId)) {
        continue;
      }
      final String? orderUuid = op.refs['order_uuid'] as String?;
      final String? billUuid = op.refs['bill_uuid'] as String?;
      if (orderUuid == null || billUuid == null) continue;
      try {
        final PosBilling billing = await data.billing.bills(orderUuid);
        final PosOrder order = await data.orders.get(orderUuid);
        for (final PosBill bill in billing.bills) {
          if (bill.uuid != billUuid || bill.fiscal.status != 'issued') continue;
          final BuildContext? ctx = _scopeContext;
          if (ctx == null || !ctx.mounted) return;
          await printSale(ctx, bill, order);
        }
      } on PosException {
        // The receipt stays on the server ("Navbat" → closed orders: print it from there)
      }
    }
  }

  BuildContext? _scopeContext;

  void _go(CashierDestination d) {
    if (!d.visibleFor(_session)) return;
    setState(() {
      _current = d;
      _visited.add(d);
    });
  }

  @override
  Widget build(BuildContext context) {
    final List<CashierDestination> visible = <CashierDestination>[
      for (final CashierDestination d in CashierDestination.values)
        if (d.visibleFor(_session)) d,
    ];
    if (!visible.contains(_current)) _current = visible.first;
    final int index = visible.indexOf(_current);
    return PosScope(
      services: widget.services,
      realtime: widget.realtime,
      navigate: _go,
      child: LayoutBuilder(
        builder: (BuildContext context, BoxConstraints box) {
          _scopeContext = context;
          final bool wide = box.maxWidth >= 720;
          final Widget stack = IndexedStack(
            index: index,
            children: <Widget>[
              for (final CashierDestination d in visible)
                _visited.contains(d)
                    ? KeyedSubtree(
                        key: ValueKey<CashierDestination>(d),
                        child: d.page(),
                      )
                    : const SizedBox.shrink(),
            ],
          );
          final Widget pages = Column(
            children: <Widget>[
              const LimitedModeBanner(),
              Expanded(child: stack),
            ],
          );
          return Scaffold(
            appBar: PosTopBar(compact: box.maxWidth < 1000),
            body: wide
                ? Row(
                    children: <Widget>[
                      NavigationRail(
                        selectedIndex: index,
                        labelType: NavigationRailLabelType.all,
                        minWidth: 88,
                        groupAlignment: -1,
                        onDestinationSelected: (int i) => _go(visible[i]),
                        destinations: <NavigationRailDestination>[
                          for (final CashierDestination d in visible)
                            NavigationRailDestination(
                              icon: Icon(d.icon),
                              selectedIcon: Icon(d.selectedIcon),
                              label: Text(d.label),
                            ),
                        ],
                      ),
                      const VerticalDivider(width: 1),
                      Expanded(child: pages),
                    ],
                  )
                : pages,
            bottomNavigationBar: wide
                ? null
                : NavigationBar(
                    selectedIndex: index,
                    onDestinationSelected: (int i) => _go(visible[i]),
                    destinations: <NavigationDestination>[
                      for (final CashierDestination d in visible)
                        NavigationDestination(
                          icon: Icon(d.icon),
                          selectedIcon: Icon(d.selectedIcon),
                          label: d.label,
                        ),
                    ],
                  ),
          );
        },
      ),
    );
  }
}
