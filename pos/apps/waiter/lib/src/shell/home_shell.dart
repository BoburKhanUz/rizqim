import 'dart:async';

import 'package:flutter/material.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:rizqim_core/rizqim_core.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import '../common/modules.dart';
import '../common/notices.dart';
import '../features/alerts/waiter_alerts.dart';
import '../features/hostess/hostess_page.dart';
import '../features/me/clock_controller.dart';
import '../features/me/my_tips_page.dart';
import '../features/rooms/rooms_page.dart';
import '../services.dart';
import 'connection_indicator.dart';
import 'plan_notice.dart';
import 'unpair_dialog.dart';
import 'waiter_scope.dart';

/// Ofitsiantning ish ekrani (telefon): yuqorida kim kirgani, filial, aloqa belgisi, qulf va menyu; ostida "Mening
/// xonalarim" ([RoomsPage], P3-25).
///
/// P6 2-to'lqin (faqat modul yoqilganda): hostes stoli ([HostessPage]; hostes uchun bosh sahifa, menejerga pastdagi
/// yorliq), "Mening choy pulim", "Ishga keldim / Ishdan ketdim" va real-time xabarlar ([WaiterAlerts]).
class HomeShell extends StatefulWidget {
  const HomeShell({super.key, required this.services, this.realtime});

  final WaiterServices services;
  final PosRealtime? realtime;

  @override
  State<HomeShell> createState() => _HomeShellState();
}

class _HomeShellState extends State<HomeShell> {
  late final StreamSubscription<PosSessionPhase> _sub;
  late final PlanRefresher _plan = PlanRefresher(widget.services.session);
  late final ClockController _clock = ClockController(
    data: widget.services.data,
  );

  /// Pastki yorliq: 0 — xonalar, 1 — hostes stoli (faqat ikkalasi bo'lsa).
  late int _tab = _session.isHostess ? 1 : 0;

  PosSession get _session => widget.services.session;

  @override
  void initState() {
    super.initState();
    _sub = _session.changes.listen((_) {
      if (mounted) setState(() {});
    });
    // P4-21: the plan is read again now and then, so paying the invoice removes the notice
    _plan.start();
    if (_session.clockOn) unawaited(_clock.refresh());
  }

  @override
  void dispose() {
    _plan.dispose();
    _clock.dispose();
    unawaited(_sub.cancel());
    super.dispose();
  }

  /// Hostes stoli bor, xonalar (buyurtmalar) ham: ikki yorliq.
  bool get _hostess => _session.hostessDeskOn;

  /// Hostes roli buyurtma olmaydi: faqat hostes stoli (xonalar yorlig'i — ko'rish uchun).
  bool get _rooms => _session.can(PosPermissions.ordersView);

  Future<void> _toggleClock(
    BuildContext context, {
    required bool clockIn,
  }) async {
    if (!clockIn) {
      final bool? yes = await showDialog<bool>(
        context: context,
        builder: (BuildContext context) => AlertDialog(
          title: const Text('Ishdan ketdingizmi?'),
          content: const Text(
            "Ish vaqtingiz shu daqiqada yopiladi. Ochiq stollaringiz bo'lsa, ularni boshqa ofitsiantga bering.",
          ),
          actions: <Widget>[
            RzButton(
              label: 'Yopish',
              variant: RzButtonVariant.ghost,
              onPressed: () => Navigator.of(context).pop(false),
            ),
            RzButton(
              label: 'Ishdan ketdim',
              onPressed: () => Navigator.of(context).pop(true),
            ),
          ],
        ),
      );
      if (yes != true) return;
    }
    final ({String text, bool error}) r = await _clock.toggle(clockIn: clockIn);
    if (context.mounted) showNotice(context, r.text, error: r.error);
  }

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final String name = _session.staffName ?? '';
    final String place = <String>[
      StaffRole.labelOf(_session.staffRole ?? ''),
      ?_session.branchName,
    ].join(' · ');
    return WaiterScope(
      services: widget.services,
      realtime: widget.realtime,
      child: Scaffold(
        appBar: AppBar(
          toolbarHeight: 64,
          automaticallyImplyLeading: false,
          title: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: <Widget>[
              Text(name, maxLines: 1, overflow: TextOverflow.ellipsis),
              Text(
                place,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: RzType.footnote.copyWith(color: c.fgMuted),
              ),
            ],
          ),
          actions: <Widget>[
            ConnectionIndicator(
              realtime: widget.realtime,
              connectivity: widget.services.data.connectivity,
              pollSeconds: widget.services.pollInterval.inSeconds,
              compact: true,
            ),
            const SizedBox(width: 4),
            SyncBadge(connectivity: widget.services.data.connectivity),
            IconButton(
              tooltip: 'Ekranni qulflash',
              icon: const Icon(Icons.lock_outline_rounded),
              onPressed: () => unawaited(_session.lock()),
            ),
            if (_session.canUnpair || _session.tipsOn || _session.clockOn)
              Builder(
                // Under the WaiterScope: the tips page opens with it
                builder: (BuildContext menuContext) => ListenableBuilder(
                  listenable: _clock,
                  builder: (BuildContext _, _) => PopupMenuButton<String>(
                    tooltip: 'Menyu',
                    onSelected: (String v) {
                      if (v == 'unpair') {
                        unawaited(confirmUnpair(context, _session));
                      }
                      if (v == 'tips') {
                        unawaited(
                          WaiterScope.push<void>(
                            menuContext,
                            const MyTipsPage(),
                          ),
                        );
                      }
                      if (v == 'clock_in' || v == 'clock_out') {
                        unawaited(
                          _toggleClock(context, clockIn: v == 'clock_in'),
                        );
                      }
                    },
                    itemBuilder: (BuildContext context) => <PopupMenuEntry<String>>[
                      if (_session.clockOn)
                        _clock.clockedIn
                            ? PopupMenuItem<String>(
                                value: 'clock_out',
                                height: 52,
                                enabled: !_clock.busy,
                                child: ListTile(
                                  leading: const Icon(Icons.logout_rounded),
                                  title: const Text('Ishdan ketdim'),
                                  subtitle: _clock.since == null
                                      ? null
                                      : Text(
                                          '${formatReceiptTime(_clock.since!.toLocal())} dan ishdasiz',
                                        ),
                                ),
                              )
                            : PopupMenuItem<String>(
                                value: 'clock_in',
                                height: 52,
                                enabled: !_clock.busy,
                                child: const ListTile(
                                  leading: Icon(Icons.login_rounded),
                                  title: Text('Ishga keldim'),
                                ),
                              ),
                      if (_session.tipsOn)
                        const PopupMenuItem<String>(
                          value: 'tips',
                          height: 52,
                          child: ListTile(
                            leading: Icon(Icons.volunteer_activism_outlined),
                            title: Text('Mening choy pulim'),
                          ),
                        ),
                      if (_session.canUnpair)
                        PopupMenuItem<String>(
                          value: 'unpair',
                          child: ListTile(
                            leading: Icon(
                              Icons.link_off_rounded,
                              color: c.dangerSoftFg,
                            ),
                            title: Text(
                              'Terminalni uzish',
                              style: TextStyle(color: c.dangerSoftFg),
                            ),
                          ),
                        ),
                    ],
                  ),
                ),
              ),
            const SizedBox(width: 4),
          ],
        ),
        body: WaiterAlerts(
          child: Column(
            children: <Widget>[
              // P4-21: an unpaid invoice, in plain words; taking orders is never blocked
              PlanNoticeBanner(
                session: widget.services.session,
                now: widget.services.now,
              ),
              // P6-23: a waiter who has not clocked in yet sees it at once
              if (_session.clockOn)
                ListenableBuilder(
                  listenable: _clock,
                  builder: (BuildContext context, _) => _clock.clockedIn
                      ? const SizedBox.shrink()
                      : _ClockStrip(
                          busy: _clock.busy,
                          onClockIn: () =>
                              unawaited(_toggleClock(context, clockIn: true)),
                        ),
                ),
              Expanded(child: _body()),
            ],
          ),
        ),
        bottomNavigationBar: _hostess && _rooms
            ? NavigationBar(
                selectedIndex: _tab,
                height: 64,
                onDestinationSelected: (int i) => setState(() => _tab = i),
                destinations: const <NavigationDestination>[
                  NavigationDestination(
                    icon: Icon(Icons.meeting_room_outlined),
                    selectedIcon: Icon(Icons.meeting_room),
                    label: 'Xonalar',
                  ),
                  NavigationDestination(
                    icon: Icon(Icons.room_preferences_outlined),
                    selectedIcon: Icon(Icons.room_preferences),
                    label: 'Hostes',
                  ),
                ],
              )
            : null,
      ),
    );
  }

  Widget _body() {
    if (_hostess && (!_rooms || _tab == 1)) return const HostessPage();
    return const RoomsPage();
  }
}

/// "Ish vaqtingiz belgilanmagan — Ishga keldim" (P6-23, `staff_pay.clock` yoqilgan filialda).
class _ClockStrip extends StatelessWidget {
  const _ClockStrip({required this.busy, required this.onClockIn});

  final bool busy;
  final VoidCallback onClockIn;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    return Material(
      color: c.infoSoft,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(16, 8, 12, 8),
        child: Row(
          children: <Widget>[
            Icon(Icons.schedule, color: c.infoSoftFg),
            const SizedBox(width: 10),
            Expanded(
              child: Text(
                'Ish vaqtingiz belgilanmagan.',
                style: RzType.body.copyWith(color: c.infoSoftFg),
              ),
            ),
            RzButton(
              label: 'Ishga keldim',
              icon: Icons.login_rounded,
              size: RzButtonSize.lg,
              loading: busy,
              onPressed: busy ? null : onClockIn,
            ),
          ],
        ),
      ),
    );
  }
}
