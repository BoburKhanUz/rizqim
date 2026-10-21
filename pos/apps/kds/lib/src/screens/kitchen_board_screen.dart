import 'dart:async';

import 'package:flutter/material.dart';
import 'package:rizqim_api/rizqim_api.dart';
import 'package:rizqim_core/rizqim_core.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import '../board/kitchen_board_controller.dart';
import '../realtime/branch_realtime.dart';
import '../settings/kds_settings.dart';
import '../widgets/connection_status.dart';
import '../widgets/recent_strip.dart';
import '../widgets/settings_dialog.dart';
import '../widgets/ticket_card.dart';

/// Oshxona doskasi: chiptalar eskisidan yangisiga, ekran kengligiga qarab ustunlarda (planshet 3, 1080p televizor 5).
class KitchenBoardScreen extends StatefulWidget {
  const KitchenBoardScreen({
    super.key,
    required this.controller,
    required this.auth,
    required this.terminal,
    required this.settings,
    this.realtime,
    this.clock,
  });

  final KitchenBoardController controller;
  final AuthSession auth;
  final PairedTerminal terminal;
  final KdsSettingsController settings;
  final BranchRealtime? realtime;
  final DateTime Function()? clock;

  /// Bitta ustunning eng kichik kengligi.
  static const double minColumnWidth = 340;

  static int columnsFor(double width) =>
      (width / minColumnWidth).floor().clamp(1, 6);

  @override
  State<KitchenBoardScreen> createState() => _KitchenBoardScreenState();
}

class _KitchenBoardScreenState extends State<KitchenBoardScreen> {
  late final ValueNotifier<DateTime> _now = ValueNotifier<DateTime>(_read());
  Timer? _tick;
  StreamSubscription<BoardNotice>? _notices;

  DateTime _read() => (widget.clock ?? DateTime.now)();

  @override
  void initState() {
    super.initState();
    _tick = Timer.periodic(
      const Duration(seconds: 1),
      (_) => _now.value = _read(),
    );
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

  @override
  Widget build(BuildContext context) {
    return ValueListenableBuilder<KdsSettings>(
      valueListenable: widget.settings,
      builder: (BuildContext context, KdsSettings settings, _) =>
          RzIdleDetector(
            timeout: settings.idleTimeout,
            onIdle: () => _signOut(reason: 'idle'),
            child: ListenableBuilder(
              listenable: widget.controller,
              builder: (BuildContext context, _) =>
                  _scaffold(context, settings),
            ),
          ),
    );
  }

  Widget _scaffold(BuildContext context, KdsSettings settings) {
    final KitchenBoardController board = widget.controller;
    final RzColors c = context.rz;
    final DateTime? last = board.lastSuccess;
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
              count: board.tickets.length,
              realtime: widget.realtime,
              clock: _now,
              muted: settings.muted,
              onToggleMute: () => widget.settings.update(
                settings.copyWith(muted: !settings.muted),
              ),
              onSettings: () =>
                  unawaited(showKdsSettings(context, widget.settings)),
              onSignOut: _signOut,
            ),
            if (board.error != null && !board.offline && board.loaded)
              Padding(
                padding: const EdgeInsets.fromLTRB(12, 8, 12, 0),
                child: InlineError(
                  message: board.error!.message,
                  onRetry: () => unawaited(board.refresh()),
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
                child: _board(context, settings),
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

  Widget _board(BuildContext context, KdsSettings settings) {
    final KitchenBoardController board = widget.controller;
    if (!board.loaded) {
      final ApiException? error = board.error;
      if (error == null) {
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
    final List<KitchenTicket> tickets = board.tickets;
    if (tickets.isEmpty) {
      return const EmptyState(
        icon: Icons.soup_kitchen_outlined,
        title: "Hozircha buyurtma yo'q",
        message: 'Yangi buyurtmalar shu yerda chiqadi.',
      );
    }
    return LayoutBuilder(
      builder: (BuildContext context, BoxConstraints box) {
        final int n = KitchenBoardScreen.columnsFor(box.maxWidth - 24);
        final List<List<KitchenTicket>> columns = <List<KitchenTicket>>[
          for (var i = 0; i < n; i++) <KitchenTicket>[],
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
                        for (final KitchenTicket t in columns[col])
                          Padding(
                            padding: const EdgeInsets.only(bottom: 12),
                            child: TicketCard(
                              key: ValueKey<String>(t.uuid),
                              ticket: t,
                              clock: _now,
                              settings: settings,
                              canManage: board.canManage,
                              isNew: board.isNew(t.uuid, now),
                              isLineBusy: board.isLineBusy,
                              orderBusy: board.isOrderBusy(t.uuid),
                              onLineTap: (KitchenLine line) => unawaited(
                                line.isReady
                                    ? board.recallLine(t.uuid, line.id)
                                    : board.bumpLine(t, line),
                              ),
                              onBumpAll: () => unawaited(board.bumpAll(t)),
                              onMarkReady: () =>
                                  unawaited(board.markOrderReady(t)),
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
