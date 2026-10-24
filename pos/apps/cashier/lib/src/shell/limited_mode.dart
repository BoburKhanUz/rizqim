import 'dart:async';

import 'package:flutter/material.dart';
import 'package:pos_data/pos_data.dart';
import 'package:rizqim_core/rizqim_core.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import '../features/billing/cashier_ui.dart';
import 'destinations.dart';
import 'pos_scope.dart';

/// Internetsiz rejimda bajarib bo'lmaydigan tugma ostidagi izoh.
const String offlineOnlyReason = "Internet yo'q: bu amal internet tiklangach.";

/// Kassa cheklangan rejimdami (aloqa yo'q yoki oflayn navbat hali serverga yetmagan).
bool limitedMode(BuildContext context) =>
    PosScope.maybeOf(context)?.data.offline?.active ?? false;

/// [PosOffline] holati o'zgarganda qayta chiziladi (oflayn rejimi yo'q ilovada — doim [OfflineStatus.initial]).
class OfflineStatusBuilder extends StatelessWidget {
  const OfflineStatusBuilder({super.key, required this.builder});

  final Widget Function(BuildContext context, OfflineStatus status) builder;

  @override
  Widget build(BuildContext context) {
    final PosOffline? offline = PosScope.of(context).data.offline;
    if (offline == null) return builder(context, OfflineStatus.initial);
    return StreamBuilder<OfflineStatus>(
      stream: offline.changes,
      initialData: offline.status,
      builder: (BuildContext context, AsyncSnapshot<OfflineStatus> snap) =>
          builder(context, snap.data ?? offline.status),
    );
  }
}

/// Ish ekranining tepasidagi lenta (docs/POS_SPEC.md 5.4): cheklangan rejim, navbat va uning yoshi (15 daqiqa —
/// sariq, 2 soat — qizil), fiskal cheklar navbati (24 / 40 / 48 soat), nima uchun sotib bo'lmasligi, "Yuborish" va
/// "Sinxronlash xatolari".
class LimitedModeBanner extends StatelessWidget {
  const LimitedModeBanner({super.key});

  @override
  Widget build(BuildContext context) => OfflineStatusBuilder(
    builder: (BuildContext context, OfflineStatus s) {
      if (!s.active && s.errorsCount == 0) return const SizedBox.shrink();
      final PosScope scope = PosScope.of(context);
      final DateTime now = scope.services.now();
      final List<String> lines = <String>[...limitedModeLines(s, now)];
      final StatusTone tone = !s.active
          ? StatusTone.info
          : s.level == OfflineLevel.alert ||
                s.fiscalLevel.index >= FiscalQueueLevel.urgent.index ||
                !s.canSell
          ? StatusTone.danger
          : s.level == OfflineLevel.warning ||
                s.fiscalLevel == FiscalQueueLevel.notice
          ? StatusTone.warning
          : StatusTone.info;
      final (Color bg, Color fg) = toneColors(context.rz, tone);
      final bool manager = scope.session.can('orders.cancel');
      return Semantics(
        liveRegion: true,
        container: true,
        label: 'Internetsiz rejim',
        child: Material(
          color: Color.alphaBlend(bg, context.rz.surface),
          child: Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 8, 8),
            child: Row(
              children: <Widget>[
                Icon(
                  s.active
                      ? Icons.cloud_off_rounded
                      : Icons.sync_problem_rounded,
                  color: fg,
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisSize: MainAxisSize.min,
                    children: <Widget>[
                      Text(
                        limitedModeTitle(s),
                        style: RzType.headline.copyWith(color: fg),
                      ),
                      for (final String l in lines)
                        Text(l, style: RzType.footnote.copyWith(color: fg)),
                    ],
                  ),
                ),
                if (s.queueSize > 0)
                  TextButton.icon(
                    onPressed: s.syncing
                        ? null
                        : () => unawaited(scope.data.offline?.syncNow()),
                    icon: s.syncing
                        ? const SizedBox.square(
                            dimension: 16,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : const Icon(Icons.cloud_upload_outlined),
                    label: Text(s.syncing ? 'Yuborilmoqda' : 'Yuborish'),
                  ),
                if (s.errorsCount > 0 && manager)
                  TextButton.icon(
                    onPressed: () => scope.go(CashierDestination.settings),
                    icon: const Icon(Icons.report_outlined),
                    label: Text('Xatolar (${s.errorsCount})'),
                  ),
              ],
            ),
          ),
        ),
      );
    },
  );
}

/// P3-35: sinxronlashdan keyin — offlayn sotilgan qator menyuda bo'lmagan narxda edi, server o'z narxini qo'ydi
/// (`offline_price_mismatch`). Kassadagi jami serverdagidan farq qilgan bo'lishi mumkin: buyurtma va schyot serverdan
/// yangilanadi, kassir buni ko'rib "Tushunarli" bosadi.
class SyncPriceBanner extends StatelessWidget {
  const SyncPriceBanner({
    super.key,
    required this.notices,
    required this.onDismiss,
  });

  final List<PosSyncPriceNotice> notices;
  final VoidCallback onDismiss;

  @override
  Widget build(BuildContext context) {
    final (Color bg, Color fg) = toneColors(context.rz, StatusTone.warning);
    return Semantics(
      liveRegion: true,
      container: true,
      label: 'Narx farqi',
      child: Material(
        key: const ValueKey<String>('sync-price-banner'),
        color: Color.alphaBlend(bg, context.rz.surface),
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 8, 8, 8),
          child: Row(
            children: <Widget>[
              Icon(Icons.price_change_outlined, color: fg),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: <Widget>[
                    Text(
                      'Narx serverda boshqacha: offlayn sotuv server narxida saqlandi',
                      style: RzType.headline.copyWith(color: fg),
                    ),
                    for (final PosSyncPriceNotice n in notices.take(5))
                      Text(
                        n.message,
                        style: RzType.footnote.copyWith(color: fg),
                      ),
                    if (notices.length > 5)
                      Text(
                        "Yana ${notices.length - 5} ta — Sozlamalar → Sinxronlash xatolari",
                        style: RzType.footnote.copyWith(color: fg),
                      ),
                  ],
                ),
              ),
              TextButton(onPressed: onDismiss, child: const Text('Tushunarli')),
            ],
          ),
        ),
      ),
    );
  }
}

/// Lenta sarlavhasi.
String limitedModeTitle(OfflineStatus s) {
  if (!s.active) {
    return 'Sinxronlash xatolari: ${s.errorsCount} ta — menejer ko\'rib chiqsin';
  }
  if (s.online) return 'Aloqa tiklandi: offlayn amallar serverga yuborilmoqda';
  return switch (s.level) {
    OfflineLevel.alert =>
      "Internetsiz rejim: aloqa 2 soatdan ko'p yo'q — menejerga ayting",
    OfflineLevel.warning => "Internetsiz rejim: aloqa 15 daqiqadan ko'p yo'q",
    OfflineLevel.none => 'Internetsiz rejim: sotuv davom etadi',
  };
}

/// Lenta qatorlari: navbat, yoshi, chegaralar, fiskal navbat.
List<String> limitedModeLines(OfflineStatus s, DateTime now) {
  final DateTime? oldest = s.oldestPendingAt;
  final DateTime? since = s.offlineSince;
  return <String>[
    if (s.active)
      <String>[
        if (s.queueSize == 0)
          "Navbat bo'sh"
        else
          'Navbatda ${s.queueSize} ta amal (${s.queuedOrders} ta yangi buyurtma)',
        if (oldest != null)
          'eng eskisi ${minutesLabel(now.difference(oldest).inMinutes)} oldin',
        if (!s.online && since != null) 'oxirgi aloqa ${hm(since.toLocal())}',
      ].join(' · '),
    if (s.active && !s.canSell && s.blockReason != null) s.blockReason!,
    if (s.newOrdersBlocked)
      "Navbatda 500 ta buyurtma: yangi buyurtma internet tiklangach ochiladi.",
    if (s.active) "Faqat naqd va karta. Smena yopish (Z), bo'lish, qaytarish, bron va nasiya — internet bilan.",
    switch (s.fiscalLevel) {
      FiscalQueueLevel.notice =>
        "Fiskal cheklar 24 soatdan beri serverga yetmagan: internetni tiklang.",
      FiscalQueueLevel.urgent => "Diqqat: fiskal cheklar 40 soatdan beri yuborilmagan — virtual kassa 48 soatda bloklanadi. Menejer va egaga xabar bering!",
      FiscalQueueLevel.limit =>
        "48 soatlik fiskal chegara o'tdi: kassani darhol internetga ulang.",
      FiscalQueueLevel.none => '',
    },
    if (s.clockWarning) "Qurilma soati serverdan 5 daqiqadan ko'p farq qiladi: soatni to'g'rilang.",
  ].where((String l) => l.isNotEmpty).toList();
}

/// Sozlamalardagi "Sinxronlash xatolari" (menejer va ega): serverda rad etilgan yoki farq bilan qo'llangan offlayn
/// amallar; "Hal qilindi" (qo'lda to'g'rilandi) yoki "Yopish" (hech narsa kerak emas).
class SyncErrorsCard extends StatefulWidget {
  const SyncErrorsCard({super.key});

  @override
  State<SyncErrorsCard> createState() => _SyncErrorsCardState();
}

class _SyncErrorsCardState extends State<SyncErrorsCard> {
  List<PosSyncError>? _errors;
  Object? _error;
  bool _loading = false;
  StreamSubscription<OfflineStatus>? _sub;
  int _lastCount = -1;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_sub == null) {
      final PosOffline? offline = PosScope.of(context).data.offline;
      _sub = offline?.changes.listen((OfflineStatus s) {
        if (s.errorsCount != _lastCount) unawaited(_load());
      });
      unawaited(_load());
    }
  }

  @override
  void dispose() {
    unawaited(_sub?.cancel());
    super.dispose();
  }

  Future<void> _load() async {
    final PosOffline? offline = PosScope.of(context).data.offline;
    if (offline == null || _loading) return;
    _lastCount = offline.status.errorsCount;
    setState(() => _loading = true);
    try {
      final List<PosSyncError> list = offline.active
          ? <PosSyncError>[
              for (final SyncOperation o in offline.store.errors) _local(o),
            ]
          : (await offline.serverErrors()).errors;
      if (mounted) setState(() => _errors = list);
    } on Object catch (e) {
      if (mounted) setState(() => _error = e);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  static PosSyncError _local(SyncOperation o) => PosSyncError(
    opId: o.opId,
    seq: o.seq,
    type: o.type,
    typeLabel: SyncOpType.label(o.type),
    status: o.status.wire,
    reason: o.reason,
    message: o.message ?? PosSyncReasons.describe(o.reason),
    occurredAt: o.occurredAt,
    localNumber: o.payload['local_number'] as String?,
  );

  Future<void> _resolve(PosSyncError e, String resolution) async {
    final PosOffline offline = PosScope.of(context).data.offline!;
    await runGuarded(context, () async {
      await offline.resolveError(e.opId, resolution: resolution);
      if (mounted) {
        showPosMessage(
          context,
          resolution == 'resolved' ? 'Hal qilindi deb belgilandi.' : 'Yopildi.',
        );
      }
    });
    await _load();
  }

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final List<PosSyncError>? errors = _errors;
    final bool canResolve =
        PosScope.of(context).session.can('orders.cancel') &&
        !limitedMode(context);
    return PosCard(
      title: 'Sinxronlash xatolari',
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          if (_error != null && errors == null)
            InlineError(
              message: "Ro'yxatni yuklab bo'lmadi.",
              onRetry: () => unawaited(_load()),
            )
          else if (errors == null)
            const LoadingBox()
          else if (errors.isEmpty)
            Text(
              "Xato yo'q: hamma offlayn amallar serverga yetib bordi.",
              style: RzType.body.copyWith(color: c.fgMuted),
            )
          else
            for (final PosSyncError e in errors) ...<Widget>[
              _row(c, e, canResolve),
              if (e != errors.last) const Divider(height: 20),
            ],
        ],
      ),
    );
  }

  Widget _row(RzColors c, PosSyncError e, bool canResolve) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: <Widget>[
      Row(
        children: <Widget>[
          TonePill(
            label: e.isRejected ? 'Rad etildi' : 'Farq bilan',
            tone: e.isRejected ? StatusTone.danger : StatusTone.warning,
          ),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              <String>[
                e.typeLabel,
                ?e.localNumber,
                ?e.tableRef,
                if (e.amount != null) "${e.amount} so'm",
                if (e.occurredAt != null) hm(e.occurredAt!.toLocal()),
                ?e.staffName,
              ].join(' · '),
              style: RzType.headline.copyWith(color: c.fg),
            ),
          ),
        ],
      ),
      if (e.message != null)
        Padding(
          padding: const EdgeInsets.only(top: 4),
          child: Text(
            e.message!,
            style: RzType.body.copyWith(color: c.fgMuted),
          ),
        ),
      if (canResolve)
        Wrap(
          spacing: 8,
          children: <Widget>[
            TextButton.icon(
              onPressed: () => unawaited(_resolve(e, 'resolved')),
              icon: const Icon(Icons.check_rounded),
              label: const Text('Hal qilindi'),
            ),
            TextButton.icon(
              onPressed: () => unawaited(_resolve(e, 'dismissed')),
              icon: const Icon(Icons.close_rounded),
              label: const Text('Yopish'),
            ),
          ],
        ),
    ],
  );
}
