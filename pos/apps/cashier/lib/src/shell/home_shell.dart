import 'dart:async';

import 'package:flutter/material.dart';
import 'package:pos_data/pos_data.dart';
import 'package:rizqim_api/rizqim_api.dart';

import '../services.dart';
import 'destinations.dart';
import 'pos_scope.dart';
import 'top_bar.dart';

/// Ish ekrani (PIN bilan kirilgan, bootstrap yuklangan): yuqori panel, navigatsiya (keng ekranda chapda rail, torda
/// pastda) va bo'lim sahifasi. Bir marta ochilgan bo'lim holati saqlanadi (IndexedStack), ko'rilmagani qurilmaydi.
///
/// Smena holati: `shift.changed` (shu terminal) yoki real-time qayta ulanganda `GET /pos/shifts/current` qayta
/// so'raladi va yuqori paneldagi belgi yangilanadi.
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
          final bool wide = box.maxWidth >= 720;
          final Widget pages = IndexedStack(
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
