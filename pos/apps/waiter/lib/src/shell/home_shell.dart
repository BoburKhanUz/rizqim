import 'dart:async';

import 'package:flutter/material.dart';
import 'package:pos_data/pos_data.dart';
import 'package:rizqim_core/rizqim_core.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import '../features/rooms/rooms_page.dart';
import '../services.dart';
import 'connection_indicator.dart';
import 'plan_notice.dart';
import 'unpair_dialog.dart';
import 'waiter_scope.dart';

/// Ofitsiantning ish ekrani (telefon): yuqorida kim kirgani, filial, aloqa belgisi, qulf va menyu; ostida "Mening
/// xonalarim" ([RoomsPage], P3-25).
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

  PosSession get _session => widget.services.session;

  @override
  void initState() {
    super.initState();
    _sub = _session.changes.listen((_) {
      if (mounted) setState(() {});
    });
    // P4-21: the plan is read again now and then, so paying the invoice removes the notice
    _plan.start();
  }

  @override
  void dispose() {
    _plan.dispose();
    unawaited(_sub.cancel());
    super.dispose();
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
            if (_session.canUnpair)
              PopupMenuButton<String>(
                tooltip: 'Menyu',
                onSelected: (String v) {
                  if (v == 'unpair') {
                    unawaited(confirmUnpair(context, _session));
                  }
                },
                itemBuilder: (BuildContext context) => <PopupMenuEntry<String>>[
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
            const SizedBox(width: 4),
          ],
        ),
        body: Column(
          children: <Widget>[
            // P4-21: an unpaid invoice, in plain words; taking orders is never blocked
            PlanNoticeBanner(
              session: widget.services.session,
              now: widget.services.now,
            ),
            const Expanded(child: RoomsPage()),
          ],
        ),
      ),
    );
  }
}
