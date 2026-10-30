import 'dart:async';

import 'package:flutter/material.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_ui/pos_ui.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import '../../common/connection_banner.dart';
import '../../common/errors.dart';
import '../../common/modules.dart';
import '../../common/notices.dart';
import '../../services.dart';
import '../../shell/waiter_scope.dart';
import '../order/order_page.dart';
import 'hostess_controller.dart';
import 'hostess_sheets.dart';

/// Hostes stoli (P6-24/P6-26): kutish ro'yxati, stollar holati ("tozalanmoqda" → "tayyor") va bugungi bronlar (bo'nak
/// belgisi bilan). Telefonda — uch yorliq; planshetda (keng ekran) — chapda kutish ro'yxati, o'ngda stollar va bronlar.
class HostessPage extends StatefulWidget {
  const HostessPage({super.key});

  /// Shu kenglikdan boshlab ikki ustun (planshet).
  static const double wideFrom = 840;

  @override
  State<HostessPage> createState() => _HostessPageState();
}

class _HostessPageState extends State<HostessPage> {
  HostessController? _controller;

  HostessController get _ctl => _controller!;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_controller != null) return;
    final WaiterScope scope = WaiterScope.of(context);
    final WaiterServices s = scope.services;
    _controller = HostessController(
      data: scope.data,
      realtime: scope.realtime,
      pollInterval: s.pollInterval,
      livePollInterval: s.livePollInterval,
    )..start();
  }

  @override
  void dispose() {
    _controller?.dispose();
    super.dispose();
  }

  void _say(String? error, String done) {
    if (!mounted || error == '') return;
    showNotice(context, error ?? done, error: error != null);
  }

  Future<void> _addGuest() async {
    final WaitlistDraft? d = await showAddGuestSheet(context);
    if (d == null) return;
    final String? err = await _ctl.add(
      name: d.name,
      partySize: d.partySize,
      phone: d.phone,
      quotedWaitMinutes: d.quotedWaitMinutes,
      note: d.note,
    );
    _say(err, "${d.name} ro'yxatga yozildi.");
  }

  Future<void> _seat(PosWaitlistEntry e) async {
    final PosFloor? floor = _ctl.floor;
    if (floor == null) return;
    final WaiterScope scope = WaiterScope.of(context);
    final PosSession session = scope.session;
    final SeatChoice? pick = await showSeatSheet(
      context,
      entry: e,
      floor: floor,
      canOpenOrder: session.can(PosPermissions.ordersManage),
      waiters: <PosStaffRef>[
        for (final PosStaffRef s
            in session.bootstrap?.staff ?? const <PosStaffRef>[])
          if (s.isWaiter) s,
      ],
      me: session.staffId,
    );
    if (pick == null) return;
    final ({String? error, PosWaitlistEntry? entry}) r = await _ctl.seat(
      e,
      tableId: pick.table.id,
      openOrder: pick.openOrder,
      waiterId: pick.waiterId,
    );
    _say(r.error, '${e.guestName}: ${pick.table.name}');
    final String? order = r.entry?.orderUuid;
    // The order opened for me (or for nobody): straight to it
    if (r.error == null &&
        order != null &&
        mounted &&
        (pick.waiterId == null || pick.waiterId == session.staffId)) {
      await WaiterScope.push<void>(
        context,
        OrderPage(orderUuid: order, title: pick.table.name),
      );
      if (mounted) unawaited(_ctl.refresh());
    }
  }

  Future<void> _cancel(PosWaitlistEntry e) async {
    final bool? yes = await _confirm(
      "${e.guestName}: ketdi",
      "Yozuv bekor qilinadi (mehmon kutmasdan ketdi).",
      'Bekor qilish',
    );
    if (yes != true) return;
    _say(await _ctl.cancel(e), 'Yozuv bekor qilindi.');
  }

  Future<void> _noShow(PosWaitlistEntry e) async {
    final bool? yes = await _confirm(
      "${e.guestName}: kelmadi",
      "Chaqirilgan mehmon kelmadi deb belgilanadi.",
      'Kelmadi',
    );
    if (yes != true) return;
    _say(await _ctl.noShow(e), 'Kelmadi deb belgilandi.');
  }

  Future<bool?> _confirm(String title, String text, String action) =>
      showDialog<bool>(
        context: context,
        builder: (BuildContext context) => AlertDialog(
          title: Text(title),
          content: Text(text),
          actions: <Widget>[
            RzButton(
              label: 'Yopish',
              variant: RzButtonVariant.ghost,
              onPressed: () => Navigator.of(context).pop(false),
            ),
            RzButton(
              label: action,
              variant: RzButtonVariant.danger,
              onPressed: () => Navigator.of(context).pop(true),
            ),
          ],
        ),
      );

  Future<void> _newBooking() async {
    final WaiterScope scope = WaiterScope.of(context);
    final PosSession session = scope.session;
    final HostessBookingInput? input = await showBookingSheet(
      context,
      tables: <PosTable>[
        for (final PosTable t
            in session.bootstrap?.tables ?? const <PosTable>[])
          if (t.kind == FloorTableKind.table) t,
      ],
      now: scope.services.now(),
      mayOverride: mayOverrideLeadTime(session),
      rules: session.tableBookingOn ? session.bootstrap?.bookingRules : null,
    );
    if (input == null) return;
    final ({String? error, PosBooking? booking}) r = await _ctl.createBooking(
      input,
    );
    _say(r.error, 'Bron yozildi: ${input.contactName}.');
  }

  @override
  Widget build(BuildContext context) {
    final WaiterScope scope = WaiterScope.of(context);
    return ColoredBox(
      color: context.rz.page,
      child: ListenableBuilder(
        listenable: _ctl,
        builder: (BuildContext context, _) => Column(
          children: <Widget>[
            ConnectionBanner(
              connectivity: scope.data.connectivity,
              onRetry: () => unawaited(_ctl.refresh()),
            ),
            if (_ctl.error != null && _ctl.hasData)
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 8, 16, 0),
                child: InlineError(
                  message: describeError(_ctl.error!),
                  onRetry: () => unawaited(_ctl.refresh()),
                ),
              ),
            Expanded(
              child: LayoutBuilder(
                builder: (BuildContext context, BoxConstraints box) =>
                    box.maxWidth >= HostessPage.wideFrom
                    ? _wide(context, scope)
                    : _narrow(context, scope),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _waitlist(BuildContext context, WaiterScope scope) => _WaitlistPane(
    controller: _ctl,
    now: scope.services.now(),
    onAdd: () => unawaited(_addGuest()),
    onNotify: (PosWaitlistEntry e) async =>
        _say(await _ctl.notifyGuest(e), '${e.guestName} chaqirildi.'),
    onSeat: (PosWaitlistEntry e) => unawaited(_seat(e)),
    onCancel: (PosWaitlistEntry e) => unawaited(_cancel(e)),
    onNoShow: (PosWaitlistEntry e) => unawaited(_noShow(e)),
  );

  Widget _tables(BuildContext context, WaiterScope scope) => _TablesPane(
    controller: _ctl,
    staffId: scope.session.staffId,
    onCleaning: (FloorTable t, bool cleaning) async => _say(
      await _ctl.setCleaning(t, cleaning: cleaning),
      cleaning ? '${t.name}: tozalanmoqda' : '${t.name}: tayyor',
    ),
  );

  Widget _bookings(BuildContext context, WaiterScope scope) => _BookingsPane(
    controller: _ctl,
    session: scope.session,
    onNew: scope.session.can(PosPermissions.bookingsManage)
        ? () => unawaited(_newBooking())
        : null,
  );

  Widget _narrow(BuildContext context, WaiterScope scope) {
    final RzColors c = context.rz;
    final int waiting = _ctl.waitlist?.summary.waiting ?? 0;
    return DefaultTabController(
      length: 3,
      child: Column(
        children: <Widget>[
          TabBar(
            labelStyle: RzType.headline,
            labelColor: c.fg,
            unselectedLabelColor: c.fgMuted,
            tabs: <Widget>[
              Tab(
                height: 52,
                text: waiting > 0 ? 'Kutish ($waiting)' : 'Kutish',
              ),
              const Tab(height: 52, text: 'Stollar'),
              const Tab(height: 52, text: 'Bronlar'),
            ],
          ),
          Expanded(
            child: TabBarView(
              children: <Widget>[
                _waitlist(context, scope),
                _tables(context, scope),
                _bookings(context, scope),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _wide(BuildContext context, WaiterScope scope) {
    final RzColors c = context.rz;
    return Row(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        Expanded(flex: 5, child: _waitlist(context, scope)),
        VerticalDivider(width: 1, color: c.line),
        Expanded(
          flex: 6,
          child: DefaultTabController(
            length: 2,
            child: Column(
              children: <Widget>[
                TabBar(
                  labelStyle: RzType.headline,
                  labelColor: c.fg,
                  unselectedLabelColor: c.fgMuted,
                  tabs: const <Widget>[
                    Tab(height: 52, text: 'Stollar'),
                    Tab(height: 52, text: 'Bronlar'),
                  ],
                ),
                Expanded(
                  child: TabBarView(
                    children: <Widget>[
                      _tables(context, scope),
                      _bookings(context, scope),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
      ],
    );
  }
}

// --- kutish ro'yxati -----------------------------------------------------------------------------------------------

class _WaitlistPane extends StatelessWidget {
  const _WaitlistPane({
    required this.controller,
    required this.now,
    required this.onAdd,
    required this.onNotify,
    required this.onSeat,
    required this.onCancel,
    required this.onNoShow,
  });

  final HostessController controller;
  final DateTime now;
  final VoidCallback onAdd;
  final ValueChanged<PosWaitlistEntry> onNotify;
  final ValueChanged<PosWaitlistEntry> onSeat;
  final ValueChanged<PosWaitlistEntry> onCancel;
  final ValueChanged<PosWaitlistEntry> onNoShow;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final PosWaitlist? list = controller.waitlist;
    final bool act = controller.online && !controller.busy;
    final List<PosWaitlistEntry> waiting =
        list?.waiting ?? const <PosWaitlistEntry>[];
    final PosWaitlistSummary s = list?.summary ?? const PosWaitlistSummary();
    final List<Widget> children = <Widget>[
      Row(
        children: <Widget>[
          Expanded(
            child: Text(
              "Kutish ro'yxati",
              style: RzType.title.copyWith(color: c.fg),
            ),
          ),
          RzButton(
            label: 'Mehmon yozish',
            icon: Icons.person_add_alt_1_outlined,
            size: RzButtonSize.lg,
            onPressed: act ? onAdd : null,
          ),
        ],
      ),
      const SizedBox(height: 4),
      Text(
        <String>[
          '${s.waiting} kutmoqda',
          '${waiting.fold<int>(0, (int a, PosWaitlistEntry e) => a + e.partySize)} kishi',
          if (s.seated > 0) '${s.seated} joylashdi',
          if ((s.longestWaitingMinutes ?? 0) > 0)
            'eng uzoq ${s.longestWaitingMinutes} daq',
        ].join(' · '),
        style: RzType.footnote.copyWith(color: c.fgMuted),
      ),
      const SizedBox(height: 12),
    ];
    if (list == null) {
      children.add(
        controller.error != null
            ? InlineError(
                message: describeError(controller.error!),
                onRetry: () => unawaited(controller.refresh()),
              )
            : const Padding(
                padding: EdgeInsets.all(24),
                child: Center(child: CircularProgressIndicator()),
              ),
      );
    } else if (waiting.isEmpty) {
      children.add(
        const Padding(
          padding: EdgeInsets.symmetric(vertical: 24),
          child: EmptyState(
            icon: Icons.hourglass_empty,
            title: 'Hech kim kutmayapti',
            message: "Stol bo'sh bo'lmasa, mehmonni ro'yxatga yozing.",
          ),
        ),
      );
    } else {
      for (final PosWaitlistEntry e in waiting) {
        children.add(
          Padding(
            padding: const EdgeInsets.only(bottom: 10),
            child: _EntryCard(
              key: ValueKey<String>(e.uuid),
              entry: e,
              enabled: act,
              onNotify: () => onNotify(e),
              onSeat: () => onSeat(e),
              onCancel: () => onCancel(e),
              onNoShow: () => onNoShow(e),
            ),
          ),
        );
      }
    }
    final List<PosWaitlistEntry> done = <PosWaitlistEntry>[
      for (final PosWaitlistEntry e
          in list?.entries ?? const <PosWaitlistEntry>[])
        if (!e.isWaiting) e,
    ];
    if (done.isNotEmpty) {
      children.add(
        Padding(
          padding: const EdgeInsets.fromLTRB(2, 12, 2, 6),
          child: Text(
            'Bugun',
            style: RzType.headline.copyWith(color: c.fgMuted),
          ),
        ),
      );
      for (final PosWaitlistEntry e in done.reversed.take(20)) {
        children.add(_DoneRow(entry: e));
      }
    }
    return RefreshIndicator(
      onRefresh: controller.refresh,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 14, 16, 24),
        children: children,
      ),
    );
  }
}

class _EntryCard extends StatelessWidget {
  const _EntryCard({
    super.key,
    required this.entry,
    required this.enabled,
    required this.onNotify,
    required this.onSeat,
    required this.onCancel,
    required this.onNoShow,
  });

  final PosWaitlistEntry entry;
  final bool enabled;
  final VoidCallback onNotify;
  final VoidCallback onSeat;
  final VoidCallback onCancel;
  final VoidCallback onNoShow;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final PosWaitlistEntry e = entry;
    final bool over = e.isOverQuoted;
    final String? phone = e.guestPhone ?? e.guestPhoneMasked;
    final String wait = e.quotedWaitMinutes == null
        ? '${e.waitedMinutes} daq kutmoqda'
        : '${e.waitedMinutes} / ${e.quotedWaitMinutes} daq';
    return Material(
      color: c.surface,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(RzRadius.card),
        side: BorderSide(color: over ? c.danger : c.line, width: over ? 2 : 1),
      ),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: <Widget>[
            Row(
              children: <Widget>[
                if (e.position != null) ...<Widget>[
                  CircleAvatar(
                    radius: 16,
                    backgroundColor: c.brandSoft,
                    child: Text(
                      '${e.position}',
                      style: RzType.headline.copyWith(color: c.brandSoftFg),
                    ),
                  ),
                  const SizedBox(width: 10),
                ],
                Expanded(
                  child: Text(
                    e.guestName,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: RzType.title.copyWith(color: c.fg),
                  ),
                ),
                const SizedBox(width: 8),
                Icon(Icons.people_outline, size: 20, color: c.fgMuted),
                const SizedBox(width: 4),
                Text(
                  '${e.partySize} kishi',
                  style: RzType.headline.copyWith(color: c.fg),
                ),
              ],
            ),
            const SizedBox(height: 8),
            Wrap(
              spacing: 14,
              runSpacing: 4,
              children: <Widget>[
                _fact(
                  context,
                  Icons.schedule,
                  wait,
                  over ? c.dangerSoftFg : c.fg,
                ),
                if (phone != null)
                  _fact(context, Icons.phone_outlined, phone, c.fg),
                if (e.isNotified)
                  _fact(
                    context,
                    Icons.notifications_active_outlined,
                    'Chaqirildi ${formatReceiptTime(e.notifiedAt!.toLocal())}',
                    c.infoSoftFg,
                  ),
              ],
            ),
            if ((e.note ?? '').isNotEmpty) ...<Widget>[
              const SizedBox(height: 4),
              Text(
                e.note!,
                style: RzType.footnote.copyWith(
                  color: c.fgMuted,
                  fontStyle: FontStyle.italic,
                ),
              ),
            ],
            const SizedBox(height: 10),
            LayoutBuilder(
              builder: (BuildContext context, BoxConstraints box) {
                final Widget notify = RzButton(
                  label: e.isNotified ? 'Yana chaqirish' : 'Chaqirish',
                  icon: Icons.notifications_outlined,
                  variant: RzButtonVariant.secondary,
                  size: RzButtonSize.lg,
                  fullWidth: true,
                  onPressed: enabled ? onNotify : null,
                );
                final Widget seat = RzButton(
                  label: 'Joylashtirish',
                  icon: Icons.event_seat_outlined,
                  variant: RzButtonVariant.success,
                  size: RzButtonSize.lg,
                  fullWidth: true,
                  onPressed: enabled ? onSeat : null,
                );
                final Widget more = PopupMenuButton<String>(
                  tooltip: "Boshqa amallar: ${e.guestName}",
                  enabled: enabled,
                  onSelected: (String v) {
                    if (v == 'cancel') onCancel();
                    if (v == 'no_show') onNoShow();
                  },
                  itemBuilder: (BuildContext context) =>
                      const <PopupMenuEntry<String>>[
                        PopupMenuItem<String>(
                          value: 'cancel',
                          height: 52,
                          child: ListTile(
                            leading: Icon(Icons.directions_walk),
                            title: Text('Ketdi (bekor qilish)'),
                          ),
                        ),
                        PopupMenuItem<String>(
                          value: 'no_show',
                          height: 52,
                          child: ListTile(
                            leading: Icon(Icons.person_off_outlined),
                            title: Text('Kelmadi'),
                          ),
                        ),
                      ],
                );
                // A phone: "Joylashtirish" on its own line, the full label readable
                if (box.maxWidth < 420) {
                  return Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: <Widget>[
                      seat,
                      const SizedBox(height: 8),
                      Row(
                        children: <Widget>[
                          Expanded(child: notify),
                          more,
                        ],
                      ),
                    ],
                  );
                }
                return Row(
                  children: <Widget>[
                    Expanded(child: notify),
                    const SizedBox(width: 8),
                    Expanded(child: seat),
                    more,
                  ],
                );
              },
            ),
          ],
        ),
      ),
    );
  }
}

Widget _fact(BuildContext context, IconData icon, String text, Color color) =>
    Row(
      mainAxisSize: MainAxisSize.min,
      children: <Widget>[
        Icon(icon, size: 18, color: context.rz.fgMuted),
        const SizedBox(width: 5),
        Text(text, style: RzType.body.copyWith(color: color)),
      ],
    );

class _DoneRow extends StatelessWidget {
  const _DoneRow({required this.entry});

  final PosWaitlistEntry entry;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final PosWaitlistEntry e = entry;
    final String state = switch (e.status) {
      'seated' =>
        e.tableName == null ? 'Joylashdi' : 'Joylashdi: ${e.tableName}',
      'canceled' => 'Ketdi',
      'no_show' => 'Kelmadi',
      _ => e.statusLabel ?? e.status,
    };
    return ConstrainedBox(
      constraints: const BoxConstraints(minHeight: 44),
      child: Row(
        children: <Widget>[
          Expanded(
            child: Text(
              '${e.guestName} · ${e.partySize} kishi',
              style: RzType.body.copyWith(color: c.fg),
            ),
          ),
          Text(state, style: RzType.body.copyWith(color: c.fgMuted)),
        ],
      ),
    );
  }
}

// --- stollar --------------------------------------------------------------------------------------------------------

class _TablesPane extends StatelessWidget {
  const _TablesPane({
    required this.controller,
    required this.staffId,
    required this.onCleaning,
  });

  final HostessController controller;
  final int? staffId;
  final void Function(FloorTable table, bool cleaning) onCleaning;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final PosFloor? floor = controller.floor;
    if (floor == null) {
      return const Center(child: CircularProgressIndicator());
    }
    final bool act = controller.online && !controller.busy;
    final List<FloorHall> halls = <FloorHall>[...floor.halls]
      ..sort((FloorHall a, FloorHall b) => a.sortOrder.compareTo(b.sortOrder));
    final int cleaning = <FloorTable>[
      for (final FloorHall h in halls) ...h.tables,
    ].where((FloorTable t) => t.isCleaning && t.orders.isEmpty).length;
    final List<Widget> children = <Widget>[
      if (cleaning > 0)
        Padding(
          padding: const EdgeInsets.only(bottom: 8),
          child: Text(
            '$cleaning ta stol tozalanmoqda',
            style: RzType.body.copyWith(color: c.warningSoftFg),
          ),
        ),
    ];
    for (final FloorHall h in halls) {
      if (!h.isActive) continue;
      final List<FloorTable> tables = <FloorTable>[
        for (final FloorTable t in h.tables)
          if (t.isActive) t,
      ];
      if (tables.isEmpty) continue;
      children
        ..add(
          Padding(
            padding: const EdgeInsets.fromLTRB(2, 10, 2, 8),
            child: Text(
              h.name,
              style: RzType.headline.copyWith(color: c.fgMuted),
            ),
          ),
        )
        ..add(
          LayoutBuilder(
            builder: (BuildContext context, BoxConstraints box) {
              final int columns = box.maxWidth >= 560 ? 2 : 1;
              final double w = (box.maxWidth - (columns - 1) * 10) / columns;
              return Wrap(
                spacing: 10,
                runSpacing: 10,
                children: <Widget>[
                  for (final FloorTable t in tables)
                    SizedBox(
                      width: w,
                      child: _TableTile(
                        table: t,
                        enabled: act,
                        onCleaning: (bool v) => onCleaning(t, v),
                      ),
                    ),
                ],
              );
            },
          ),
        );
    }
    return RefreshIndicator(
      onRefresh: controller.refresh,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
        children: children,
      ),
    );
  }
}

class _TableTile extends StatelessWidget {
  const _TableTile({
    required this.table,
    required this.enabled,
    required this.onCleaning,
  });

  final FloorTable table;
  final bool enabled;
  final ValueChanged<bool> onCleaning;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final FloorTable t = table;
    final bool open = t.orders.isNotEmpty;
    // The mark shows only on a table without an open order (POS_SPEC §19.3)
    final bool cleaning = t.isCleaning && !open;
    final FloorOrder? o = open ? t.orders.first : null;
    final String state;
    final Color bg;
    final Color fg;
    if (cleaning) {
      state = 'Tozalanmoqda';
      bg = c.warningSoft;
      fg = c.warningSoftFg;
    } else if (o != null) {
      state = <String>[
        'Band',
        if (o.guestsCount != null) '${o.guestsCount} mehmon',
        if (o.waiter?.name != null) o.waiter!.name!,
      ].join(' · ');
      bg = c.infoSoft;
      fg = c.infoSoftFg;
    } else if (t.reservation?.startsAt != null) {
      state = 'Bron: ${formatReceiptTime(t.reservation!.startsAt!.toLocal())}';
      bg = c.brandSoft;
      fg = c.brandSoftFg;
    } else {
      state = "Bo'sh";
      bg = c.successSoft;
      fg = c.successSoftFg;
    }
    return Semantics(
      container: true,
      label: '${t.name}: $state',
      child: Material(
        color: c.surface,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(RzRadius.card),
          side: BorderSide(color: cleaning ? c.warning : c.line),
        ),
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: Row(
            children: <Widget>[
              Icon(FloorStatusStyle.kindIcon(t.kind), color: c.fg),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text(
                      t.seats == null ? t.name : "${t.name} · ${t.seats} o'rin",
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: RzType.headline.copyWith(color: c.fg),
                    ),
                    const SizedBox(height: 4),
                    DecoratedBox(
                      decoration: BoxDecoration(
                        color: bg,
                        borderRadius: BorderRadius.circular(RzRadius.control),
                      ),
                      child: Padding(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 8,
                          vertical: 3,
                        ),
                        child: Text(
                          state,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: RzType.caption.copyWith(color: fg),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
              if (!open) ...<Widget>[
                const SizedBox(width: 8),
                cleaning
                    ? RzButton(
                        label: 'Tayyor',
                        icon: Icons.check_rounded,
                        variant: RzButtonVariant.success,
                        size: RzButtonSize.lg,
                        onPressed: enabled ? () => onCleaning(false) : null,
                      )
                    : RzButton(
                        label: 'Tozalash',
                        icon: Icons.cleaning_services_outlined,
                        variant: RzButtonVariant.secondary,
                        size: RzButtonSize.lg,
                        onPressed: enabled ? () => onCleaning(true) : null,
                      ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

// --- bronlar --------------------------------------------------------------------------------------------------------

class _BookingsPane extends StatelessWidget {
  const _BookingsPane({
    required this.controller,
    required this.session,
    this.onNew,
  });

  final HostessController controller;
  final PosSession session;
  final VoidCallback? onNew;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final List<PosBooking>? all = controller.bookings;
    final List<PosBooking> list = <PosBooking>[
      for (final PosBooking b in all ?? const <PosBooking>[])
        if (b.status != 'canceled') b,
    ]..sort((PosBooking a, PosBooking b) => a.startsAt.compareTo(b.startsAt));
    final PosBookingRules? rules = session.tableBookingOn
        ? session.bootstrap?.bookingRules
        : null;
    final List<Widget> children = <Widget>[
      Row(
        children: <Widget>[
          Expanded(
            child: Text(
              'Bugungi bronlar',
              style: RzType.title.copyWith(color: c.fg),
            ),
          ),
          if (onNew != null)
            RzButton(
              label: "Bron qo'shish",
              icon: Icons.add_rounded,
              variant: RzButtonVariant.secondary,
              size: RzButtonSize.lg,
              onPressed: controller.online && !controller.busy ? onNew : null,
            ),
        ],
      ),
      const SizedBox(height: 10),
    ];
    if (all == null) {
      children.add(
        const Padding(
          padding: EdgeInsets.all(24),
          child: Center(child: CircularProgressIndicator()),
        ),
      );
    } else if (list.isEmpty) {
      children.add(
        const Padding(
          padding: EdgeInsets.symmetric(vertical: 24),
          child: EmptyState(
            icon: Icons.event_outlined,
            title: "Bugun bron yo'q",
          ),
        ),
      );
    } else {
      for (final PosBooking b in list) {
        children.add(
          Padding(
            padding: const EdgeInsets.only(bottom: 8),
            child: _BookingTile(
              booking: b,
              tableName: _tableName(b.diningTableId),
              deposit: _depositOf(b, rules),
            ),
          ),
        );
      }
    }
    return RefreshIndicator(
      onRefresh: controller.refresh,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
        children: children,
      ),
    );
  }

  String? _tableName(int? id) {
    for (final PosTable t in session.bootstrap?.tables ?? const <PosTable>[]) {
      if (t.id == id) return t.name;
    }
    return null;
  }

  /// Bo'nak: serverning `deposit_required` / `deposit_missing` (bron tafsilotida), ro'yxatda bo'lmasa — filial qoidasi
  /// bo'yicha hisob (backenddagidek) va olingan bo'nak.
  ({PosMoney required, PosMoney missing})? _depositOf(
    PosBooking b,
    PosBookingRules? rules,
  ) {
    if (b.depositRequired.isPositive) {
      return (required: b.depositRequired, missing: b.depositMissing);
    }
    final PosMoney? need = depositFor(
      rules,
      guests: b.guestsPlanned,
      isTable: isTableKind(session, b.diningTableId),
    );
    if (need == null) return null;
    final PosMoney taken = b.depositTotal;
    return (
      required: need,
      missing: need > taken ? need - taken : PosMoney.zero,
    );
  }
}

class _BookingTile extends StatelessWidget {
  const _BookingTile({required this.booking, this.tableName, this.deposit});

  final PosBooking booking;
  final String? tableName;
  final ({PosMoney required, PosMoney missing})? deposit;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final PosBooking b = booking;
    final ({PosMoney required, PosMoney missing})? d = deposit;
    return Material(
      color: c.surface,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(RzRadius.card),
        side: BorderSide(color: c.line),
      ),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            SizedBox(
              width: 56,
              child: Text(
                formatReceiptTime(b.startsAt.toLocal()),
                style: RzType.title.copyWith(color: c.fg),
              ),
            ),
            const SizedBox(width: 8),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Text(
                    b.contactName ?? 'Mehmon',
                    style: RzType.headline.copyWith(color: c.fg),
                  ),
                  Text(
                    <String>[
                      ?tableName,
                      if (b.guestsPlanned != null) '${b.guestsPlanned} kishi',
                      b.statusLabel ?? b.status,
                    ].join(' · '),
                    style: RzType.body.copyWith(color: c.fgMuted),
                  ),
                  if (d != null) ...<Widget>[
                    const SizedBox(height: 4),
                    d.missing.isPositive
                        ? _pill(
                            context,
                            Icons.warning_amber_rounded,
                            "Bo'nak yetmaydi: ${d.missing.format()} (kerak ${d.required.format()})",
                            c.warningSoft,
                            c.warningSoftFg,
                          )
                        : _pill(
                            context,
                            Icons.check_circle_outline,
                            "Bo'nak olingan: ${d.required.format()}",
                            c.successSoft,
                            c.successSoftFg,
                          ),
                  ],
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _pill(
    BuildContext context,
    IconData icon,
    String text,
    Color bg,
    Color fg,
  ) => DecoratedBox(
    decoration: BoxDecoration(
      color: bg,
      borderRadius: BorderRadius.circular(RzRadius.control),
    ),
    child: Padding(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          Icon(icon, size: 16, color: fg),
          const SizedBox(width: 6),
          Flexible(
            child: Text(text, style: RzType.caption.copyWith(color: fg)),
          ),
        ],
      ),
    ),
  );
}
