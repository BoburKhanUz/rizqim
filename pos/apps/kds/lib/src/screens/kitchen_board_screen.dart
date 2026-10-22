import 'dart:async';

import 'package:flutter/material.dart';
import 'package:rizqim_api/rizqim_api.dart';
import 'package:rizqim_core/rizqim_core.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import '../board/board_view.dart';
import '../board/kitchen_board_controller.dart';
import '../board/skewer_groups.dart';
import '../realtime/branch_realtime.dart';
import '../settings/kds_settings.dart';
import '../stations/station_selection.dart';
import '../widgets/connection_status.dart';
import '../widgets/kazan_panel.dart';
import '../widgets/mangal_card.dart';
import '../widgets/preorder_queue.dart';
import '../widgets/recent_strip.dart';
import '../widgets/settings_dialog.dart';
import '../widgets/station_picker.dart';
import '../widgets/ticket_card.dart';
import '../widgets/unpair_dialog.dart';

/// Oshxona doskasi: chiptalar eskisidan yangisiga, ekran kengligiga qarab ustunlarda (planshet 3, 1080p televizor 5).
class KitchenBoardScreen extends StatefulWidget {
  const KitchenBoardScreen({
    super.key,
    required this.controller,
    required this.auth,
    required this.terminal,
    required this.settings,
    this.selection,
    this.setup,
    this.realtime,
    this.clock,
  });

  final KitchenBoardController controller;
  final AuthSession auth;
  final PairedTerminal terminal;
  final KdsSettingsController settings;

  /// Oshpaz tanlagan sexlar (sex tanlash va mangal ko'rinishi); `null` — sexlar yo'q.
  final StationSelectionController? selection;

  /// Qozon paneli uchun (`batches.manage`); `null` — panel yo'q.
  final KitchenSetupService? setup;
  final BranchRealtime? realtime;
  final DateTime Function()? clock;

  /// Bitta ustunning eng kichik kengligi.
  static const double minColumnWidth = 340;

  /// Mangal kartochkasi kengroq: katta yozuv va six soni.
  static const double minMangalColumnWidth = 440;

  static int columnsFor(double width, {double min = minColumnWidth}) =>
      (width / min).floor().clamp(1, 6);

  @override
  State<KitchenBoardScreen> createState() => _KitchenBoardScreenState();
}

/// Doskaning bo'limlari.
enum BoardTab { active, preorder, kazan }

class _KitchenBoardScreenState extends State<KitchenBoardScreen> {
  late final ValueNotifier<DateTime> _now = ValueNotifier<DateTime>(_read());
  Timer? _tick;
  StreamSubscription<BoardNotice>? _notices;
  BoardTab _tab = BoardTab.active;

  /// Oshpaz sexlarni o'zgartirmoqda (tanlash ekrani ochiq).
  bool _choosing = false;

  DateTime _read() => (widget.clock ?? DateTime.now)();

  @override
  void initState() {
    super.initState();
    _tick = Timer.periodic(const Duration(seconds: 1), (_) {
      _now.value = _read();
      // O'chirilgan qator yo'qolishi va oldindan buyurtma doskaga chiqishi vaqti
      widget.controller.tick();
    });
    _notices = widget.controller.notices.listen(_showNotice);
  }

  @override
  void dispose() {
    _tick?.cancel();
    unawaited(_notices?.cancel());
    _now.dispose();
    super.dispose();
  }

  void _showNotice(BoardNotice notice) {
    if (!mounted) return;
    final RzColors c = context.rz;
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          backgroundColor: notice.error ? c.danger : null,
          duration: const Duration(seconds: 5),
          content: Row(
            children: <Widget>[
              Icon(
                notice.error ? Icons.error_outline : Icons.info_outline,
                color: notice.error ? c.onDanger : c.surface,
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Text(
                  notice.message,
                  style: RzType.headline.copyWith(
                    color: notice.error ? c.onDanger : c.surface,
                  ),
                ),
              ),
            ],
          ),
        ),
      );
  }

  void _signOut({String reason = 'logout'}) =>
      unawaited(widget.auth.signOutStaff(reason: reason));

  void _openSettings() => unawaited(
    showKdsSettings(
      context,
      widget.settings,
      onUnpair: canUnpair(widget.auth)
          ? () => unawaited(confirmUnpair(context, widget.auth))
          : null,
    ),
  );

  // Avtomatik chiqish (harakatsizlik) ilova darajasida: `KdsApp` dagi detektor Navigator'dan yuqorida, dialoglarni ham
  // sezadi va chiqishda ularni yopadi.
  @override
  Widget build(BuildContext context) {
    return ValueListenableBuilder<KdsSettings>(
      valueListenable: widget.settings,
      builder: (BuildContext context, KdsSettings settings, _) =>
          ListenableBuilder(
            listenable: widget.controller,
            builder: (BuildContext context, _) => _scaffold(context, settings),
          ),
    );
  }

  Widget _scaffold(BuildContext context, KdsSettings settings) {
    final KitchenBoardController board = widget.controller;
    final RzColors c = context.rz;
    final DateTime? last = board.lastSuccess;
    final BoardView view = board.buildView();
    final bool canKazan =
        widget.setup != null &&
        (widget.auth.staff?.can('batches.manage') ?? false);
    // Ruxsat olinsa yoki bo'lim yo'qolsa doskaga qaytadi
    final BoardTab tab = _tab == BoardTab.kazan && !canKazan
        ? BoardTab.active
        : _tab;
    final bool choosing =
        board.ready && (_choosing || board.needsStationChoice);
    return Scaffold(
      body: SafeArea(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: <Widget>[
            if (board.offline)
              RzOfflineBanner(
                title: "Aloqa yo'q",
                message: last == null
                    ? 'Server bilan bog\'lanib bo\'lmadi. Qayta urinilmoqda.'
                    : 'Oxirgi yangilanish ${formatTime(last)}. Ekrandagi chiptalar eskirgan bo\'lishi mumkin.',
                actionLabel: 'Qayta urinish',
                onAction: () => unawaited(board.refresh()),
              ),
            _TopBar(
              terminal: widget.terminal,
              staffName: widget.auth.staff?.userName ?? '',
              count: view.active.length,
              realtime: widget.realtime,
              clock: _now,
              muted: settings.muted,
              onToggleMute: () => widget.settings.update(
                settings.copyWith(muted: !settings.muted),
              ),
              onSettings: _openSettings,
              onSignOut: _signOut,
            ),
            if (board.ready && !choosing)
              _SectionBar(
                tab: tab,
                activeCount: view.active.length,
                queueCount: view.queue.length,
                showKazan: canKazan,
                onTab: (BoardTab t) => setState(() => _tab = t),
                stationLabel: _stationLabel(board),
                onStations: board.stations?.isNotEmpty ?? false
                    ? () => setState(() => _choosing = true)
                    : null,
                mode: widget.selection?.value.mode,
                mangal: board.mangalView,
                showMode:
                    board.stations?.any((KitchenStation s) => s.isMangal) ??
                    false,
                onMode: (KitchenViewMode m) => widget.selection?.setMode(m),
              ),
            if (board.error != null && !board.offline && board.loaded)
              Padding(
                padding: const EdgeInsets.fromLTRB(12, 8, 12, 0),
                child: InlineError(
                  message: board.error!.message,
                  onRetry: () => unawaited(board.refresh()),
                ),
              ),
            if (board.skippedTickets > 0)
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 8, 16, 0),
                child: Text(
                  "${board.skippedTickets} ta chiptani o'qib bo'lmadi va u doskada ko'rsatilmayapti. Ilovani yangilang yoki menejerga ayting.",
                  style: RzType.body.copyWith(color: c.warningSoftFg),
                ),
              ),
            if (board.truncated)
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 8, 16, 0),
                child: Text(
                  "Faqat eng eski 200 ta chipta ko'rsatilmoqda.",
                  style: RzType.body.copyWith(color: c.warningSoftFg),
                ),
              ),
            Expanded(
              child: MediaQuery.withClampedTextScaling(
                minScaleFactor: settings.fontScale,
                maxScaleFactor: settings.fontScale,
                child: choosing
                    ? _picker(board)
                    : switch (tab) {
                        BoardTab.kazan => KazanPanel(
                          service: widget.setup!,
                          branchId: board.branchId,
                          events: widget.realtime?.events,
                        ),
                        BoardTab.preorder when board.ready => PreorderQueue(
                          items: view.queue,
                          clock: _now,
                        ),
                        _ => _board(context, settings, view),
                      },
              ),
            ),
            if (board.recent.isNotEmpty)
              RecentStrip(
                items: board.recent,
                clock: _now,
                canRecall: board.canManage,
                isBusy: board.isLineBusy,
                onRecall: (RecentBump r) =>
                    unawaited(board.recallLine(r.orderUuid, r.lineId)),
              ),
          ],
        ),
      ),
    );
  }

  /// Sarlavhadagi sex nomlari ("Mangal, Osh"); sexlar ma'lum bo'lmasa `null`.
  String? _stationLabel(KitchenBoardController board) {
    final List<KitchenStation>? stations = board.stations;
    final StationSelection? sel = widget.selection?.value;
    if (stations == null || stations.isEmpty || sel == null || !sel.chosen) {
      return null;
    }
    final List<String> names = <String>[
      for (final KitchenStation s in stations)
        if (sel.stationIds.contains(s.id)) s.name,
      if (sel.general) 'Umumiy',
    ];
    if (names.isEmpty) return null;
    return names.join(', ');
  }

  Widget _picker(KitchenBoardController board) {
    final StationSelectionController sel = widget.selection!;
    return StationPicker(
      stations: board.stations ?? const <KitchenStation>[],
      initial: sel.value,
      onCancel: board.needsStationChoice
          ? null
          : () => setState(() => _choosing = false),
      onConfirm: (Set<int> ids, bool general) {
        sel.choose(stationIds: ids, general: general);
        setState(() {
          _choosing = false;
          _tab = BoardTab.active;
        });
      },
    );
  }

  Widget _board(BuildContext context, KdsSettings settings, BoardView view) {
    final KitchenBoardController board = widget.controller;
    if (!board.ready) {
      final ApiException? error = board.error;
      if (error == null || board.loaded) {
        return const Center(child: CircularProgressIndicator());
      }
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: InlineError(
            message: board.offline
                ? "Chiptalarni yuklab bo'lmadi: server bilan aloqa yo'q."
                : error.message,
            onRetry: () => unawaited(board.refresh()),
          ),
        ),
      );
    }
    final List<BoardTicket> tickets = view.active;
    if (tickets.isEmpty) {
      return const EmptyState(
        icon: Icons.soup_kitchen_outlined,
        title: "Hozircha buyurtma yo'q",
        message: 'Yangi buyurtmalar shu yerda chiqadi.',
      );
    }
    final bool mangal = board.mangalView;
    return LayoutBuilder(
      builder: (BuildContext context, BoxConstraints box) {
        final int n = KitchenBoardScreen.columnsFor(
          box.maxWidth - 24,
          min: mangal
              ? KitchenBoardScreen.minMangalColumnWidth
              : KitchenBoardScreen.minColumnWidth,
        );
        final List<List<BoardTicket>> columns = <List<BoardTicket>>[
          for (var i = 0; i < n; i++) <BoardTicket>[],
        ];
        // Eskisi chap yuqorida, keyin o'ngga va pastga
        for (var i = 0; i < tickets.length; i++) {
          columns[i % n].add(tickets[i]);
        }
        final DateTime now = _now.value;
        return SingleChildScrollView(
          padding: const EdgeInsets.all(12),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              for (var col = 0; col < n; col++)
                Expanded(
                  child: Padding(
                    padding: EdgeInsets.only(left: col == 0 ? 0 : 12),
                    child: Column(
                      children: <Widget>[
                        for (final BoardTicket e in columns[col])
                          Padding(
                            padding: const EdgeInsets.only(bottom: 12),
                            child: mangal
                                ? MangalCard(
                                    key: ValueKey<String>(e.ticket.uuid),
                                    entry: e,
                                    clock: _now,
                                    settings: settings,
                                    canManage: board.canManage,
                                    isNew: board.isNew(e.ticket.uuid, now),
                                    isLineBusy: board.isLineBusy,
                                    orderBusy: board.isOrderBusy(e.ticket.uuid),
                                    onGroupTap: (SkewerGroup g) =>
                                        _onGroupTap(e.ticket, g),
                                    onBumpAll: () =>
                                        unawaited(board.bumpAll(e.ticket)),
                                  )
                                : TicketCard(
                                    key: ValueKey<String>(e.ticket.uuid),
                                    entry: e,
                                    clock: _now,
                                    settings: settings,
                                    canManage: board.canManage,
                                    isNew: board.isNew(e.ticket.uuid, now),
                                    isLineBusy: board.isLineBusy,
                                    orderBusy: board.isOrderBusy(e.ticket.uuid),
                                    onLineTap: (KitchenLine line) => unawaited(
                                      line.isReady
                                          ? board.recallLine(
                                              e.ticket.uuid,
                                              line.id,
                                            )
                                          : board.bumpLine(e.ticket, line),
                                    ),
                                    onBumpAll: () =>
                                        unawaited(board.bumpAll(e.ticket)),
                                    onMarkReady: () => unawaited(
                                      board.markOrderReady(e.ticket),
                                    ),
                                  ),
                          ),
                      ],
                    ),
                  ),
                ),
            ],
          ),
        );
      },
    );
  }

  /// Mangal: taomning hamma donalari birdan "tayyor" bo'ladi; tayyorni bossangiz qaytariladi.
  void _onGroupTap(KitchenTicket ticket, SkewerGroup group) {
    final KitchenBoardController board = widget.controller;
    final List<KitchenLine> todo = group.bumpable;
    if (todo.isNotEmpty) {
      unawaited(board.bumpAll(ticket.copyWith(lines: todo)));
      return;
    }
    for (final KitchenLine l in group.lines) {
      if (l.isReady) unawaited(board.recallLine(ticket.uuid, l.id));
    }
  }
}

/// Doska bo'limlari (faol, oldindan buyurtmalar, qozon), tanlangan sexlar va mangal ko'rinishi.
class _SectionBar extends StatelessWidget {
  const _SectionBar({
    required this.tab,
    required this.activeCount,
    required this.queueCount,
    required this.showKazan,
    required this.onTab,
    required this.stationLabel,
    required this.onStations,
    required this.mode,
    required this.mangal,
    required this.showMode,
    required this.onMode,
  });

  final BoardTab tab;
  final int activeCount;
  final int queueCount;
  final bool showKazan;
  final ValueChanged<BoardTab> onTab;
  final String? stationLabel;
  final VoidCallback? onStations;
  final KitchenViewMode? mode;
  final bool mangal;
  final bool showMode;
  final ValueChanged<KitchenViewMode> onMode;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final double width = MediaQuery.sizeOf(context).width;
    // Tor ekranda yorliqlar yo'q (faqat ikonka va son), o'rtachada faqat tanlangani
    final bool tiny = width < 700;
    final bool narrow = width < 900;
    Widget tabButton(BoardTab t, String label, IconData icon, {int? count}) {
      final bool on = tab == t;
      return Semantics(
        button: true,
        selected: on,
        label: count == null ? label : '$label, $count',
        excludeSemantics: true,
        child: Material(
          color: on ? c.brand : Colors.transparent,
          borderRadius: BorderRadius.circular(RzRadius.control),
          child: InkWell(
            borderRadius: BorderRadius.circular(RzRadius.control),
            onTap: () => onTab(t),
            child: ConstrainedBox(
              constraints: const BoxConstraints(minHeight: 48, minWidth: 48),
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 14),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: <Widget>[
                    Icon(icon, color: on ? c.onBrand : c.fgMuted),
                    if (!tiny && (!narrow || on)) ...<Widget>[
                      const SizedBox(width: 8),
                      Text(
                        count == null ? label : '$label ($count)',
                        style: RzType.headline.copyWith(
                          color: on ? c.onBrand : c.fg,
                        ),
                      ),
                    ] else if (count != null && count > 0) ...<Widget>[
                      const SizedBox(width: 6),
                      Text(
                        '$count',
                        style: RzType.headline.copyWith(color: c.fg),
                      ),
                    ],
                  ],
                ),
              ),
            ),
          ),
        ),
      );
    }

    return Material(
      color: c.surface,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
        decoration: BoxDecoration(
          border: Border(bottom: BorderSide(color: c.line)),
        ),
        child: Row(
          children: <Widget>[
            tabButton(
              BoardTab.active,
              'Buyurtmalar',
              Icons.receipt_long_outlined,
              count: activeCount,
            ),
            const SizedBox(width: 6),
            tabButton(
              BoardTab.preorder,
              'Oldindan buyurtmalar',
              Icons.schedule_rounded,
              count: queueCount,
            ),
            if (showKazan) ...<Widget>[
              const SizedBox(width: 6),
              tabButton(BoardTab.kazan, 'Qozon', Icons.rice_bowl_outlined),
            ],
            // Qolgan joyni oladi, o'ng tomonga tekislanadi: sex nomlari to'liq ko'rinsin
            Expanded(
              child: Align(
                alignment: Alignment.centerRight,
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: <Widget>[
                    if (showMode && mode != null) ...<Widget>[
                      SegmentedButton<bool>(
                        showSelectedIcon: false,
                        style: SegmentedButton.styleFrom(
                          selectedBackgroundColor: c.brand,
                          selectedForegroundColor: c.onBrand,
                          minimumSize: const Size(64, 48),
                        ),
                        segments: const <ButtonSegment<bool>>[
                          ButtonSegment<bool>(
                            value: false,
                            label: Text('Oddiy'),
                          ),
                          ButtonSegment<bool>(
                            value: true,
                            icon: Icon(Icons.local_fire_department_outlined),
                            label: Text('Mangal'),
                          ),
                        ],
                        selected: <bool>{mangal},
                        onSelectionChanged: (Set<bool> v) => onMode(
                          v.first
                              ? KitchenViewMode.mangal
                              : KitchenViewMode.standard,
                        ),
                      ),
                      const SizedBox(width: 8),
                    ],
                    if (onStations != null)
                      Flexible(
                        child: Tooltip(
                          message: "Sexlarni o'zgartirish",
                          child: TextButton.icon(
                            key: const ValueKey<String>('stations-button'),
                            onPressed: onStations,
                            style: TextButton.styleFrom(
                              minimumSize: const Size(48, 48),
                              foregroundColor: c.brandSoftFg,
                            ),
                            icon: const Icon(Icons.tune_rounded),
                            label: Text(
                              stationLabel == null
                                  ? 'Sexlar'
                                  : 'Sex: $stationLabel',
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: RzType.headline,
                            ),
                          ),
                        ),
                      ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _TopBar extends StatelessWidget {
  const _TopBar({
    required this.terminal,
    required this.staffName,
    required this.count,
    required this.realtime,
    required this.clock,
    required this.muted,
    required this.onToggleMute,
    required this.onSettings,
    required this.onSignOut,
  });

  final PairedTerminal terminal;
  final String staffName;
  final int count;
  final BranchRealtime? realtime;
  final ValueNotifier<DateTime> clock;
  final bool muted;
  final VoidCallback onToggleMute;
  final VoidCallback onSettings;
  final VoidCallback onSignOut;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final double width = MediaQuery.sizeOf(context).width;
    final bool compact = width < 900;
    final bool tiny = width < 560;
    return Material(
      color: c.surface,
      child: Container(
        height: 64,
        padding: const EdgeInsets.symmetric(horizontal: 12),
        decoration: BoxDecoration(
          border: Border(bottom: BorderSide(color: c.line)),
        ),
        child: Row(
          children: <Widget>[
            // Chap guruh qolgan joyni oladi; ichida faqat nomlar qisqaradi (o'ng guruh doim to'liq ko'rinadi)
            Expanded(
              child: Row(
                children: <Widget>[
                  if (!tiny) ...<Widget>[
                    Icon(Icons.soup_kitchen_rounded, color: c.brand, size: 28),
                    const SizedBox(width: 10),
                  ],
                  Flexible(
                    flex: 3,
                    child: Text(
                      terminal.branch?.name ?? 'Oshxona',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: RzType.title.copyWith(color: c.fg),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Flexible(
                    flex: 2,
                    child: Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 10,
                        vertical: 4,
                      ),
                      decoration: BoxDecoration(
                        color: Color.alphaBlend(c.brandSoft, c.surface),
                        borderRadius: BorderRadius.circular(RzRadius.control),
                      ),
                      child: Text(
                        tiny ? '$count' : '$count ta chipta',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: RzType.headline.copyWith(color: c.brandSoftFg),
                      ),
                    ),
                  ),
                  if (!tiny) ...<Widget>[
                    const SizedBox(width: 12),
                    Flexible(
                      flex: 2,
                      child: ConnectionStatus(
                        realtime: realtime,
                        compact: compact,
                      ),
                    ),
                  ],
                ],
              ),
            ),
            const SizedBox(width: 12),
            if (!compact)
              ValueListenableBuilder<DateTime>(
                valueListenable: clock,
                builder: (BuildContext context, DateTime now, _) => Text(
                  formatTime(now),
                  style: RzType.title.copyWith(color: c.fg),
                ),
              ),
            const SizedBox(width: 12),
            if (staffName.isNotEmpty && !compact) ...<Widget>[
              Icon(Icons.person_outline, color: c.fgMuted),
              const SizedBox(width: 4),
              ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 180),
                child: Text(
                  staffName,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: RzType.body.copyWith(color: c.fgMuted),
                ),
              ),
              const SizedBox(width: 4),
            ],
            IconButton(
              tooltip: muted ? 'Ovozni yoqish' : "Ovozni o'chirish",
              icon: Icon(
                muted ? Icons.volume_off_rounded : Icons.volume_up_rounded,
              ),
              iconSize: 28,
              onPressed: onToggleMute,
            ),
            IconButton(
              tooltip: 'Sozlamalar',
              icon: const Icon(Icons.settings_outlined),
              iconSize: 28,
              onPressed: onSettings,
            ),
            const SizedBox(width: 4),
            if (compact)
              IconButton(
                tooltip: 'Chiqish',
                icon: const Icon(Icons.lock_outline_rounded),
                iconSize: 28,
                onPressed: onSignOut,
              )
            else
              RzButton(
                label: 'Chiqish',
                icon: Icons.lock_outline_rounded,
                variant: RzButtonVariant.secondary,
                onPressed: onSignOut,
              ),
          ],
        ),
      ),
    );
  }
}
