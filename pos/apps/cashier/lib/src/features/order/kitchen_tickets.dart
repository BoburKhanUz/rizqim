import 'dart:async';

import 'package:flutter/material.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_hardware/pos_hardware.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import '../../printing/printing_service.dart';
import 'order_format.dart';

/// Sex markalari: oshxonaga yuborilgan qatorlar sex (`station_id`) va bosqich bo'yicha markaga bo'linib, shu sexning
/// printeriga ([CashierPrinting.kitchenPrinterFor]) chiqadi; yuborilgan qator o'chirilsa — "O'CHIRILDI" markasi.
///
/// Chop etish sotuvni hech qachon to'xtatmaydi: navbatga qo'yiladi, xato bo'lsa [issues] ga tushadi (ekranda
/// "Qayta urinish" bilan banner). Sexning printeri bo'lmasa marka chiqmaydi (taom KDS'da ko'rinadi).
class KitchenTickets {
  KitchenTickets({
    required this.printing,
    required this.bootstrap,
    required this.now,
  });

  final CashierPrinting printing;
  final PosBootstrap? Function() bootstrap;
  final DateTime Function() now;

  /// Shu kassaning xato bilan qolgan markalari.
  KitchenPrintIssues get issues => KitchenPrintIssues.of(printing);

  /// [before] da yuborilmagan (yoki yo'q), [after] da yuborilgan qatorlar markasi. Qaytaradi: navbatga qo'yilgan
  /// markalar soni.
  Future<int> printSent(PosOrder? before, PosOrder after) {
    final Set<int> alreadySent = <int>{
      for (final PosOrderItem i in before?.items ?? const <PosOrderItem>[])
        if (i.wasSent) i.id,
    };
    final List<PosOrderItem> fresh = <PosOrderItem>[
      for (final PosOrderItem i in after.items)
        if (i.wasSent && !i.isVoid && !alreadySent.contains(i.id)) i,
    ];
    return _print(after, fresh, isVoid: false);
  }

  /// Yuborilgan qator o'chirildi: o'sha sexga "O'CHIRILDI".
  Future<int> printVoid(PosOrder after, PosOrderItem voided) =>
      _print(after, <PosOrderItem>[voided], isVoid: true);

  Future<int> _print(
    PosOrder order,
    List<PosOrderItem> items, {
    required bool isVoid,
  }) async {
    if (items.isEmpty) return 0;
    final Map<(int?, int?), List<PosOrderItem>> groups =
        <(int?, int?), List<PosOrderItem>>{};
    for (final PosOrderItem i in items) {
      (groups[(i.stationId, i.roundNo)] ??= <PosOrderItem>[]).add(i);
    }
    var queued = 0;
    for (final MapEntry<(int?, int?), List<PosOrderItem>> g in groups.entries) {
      final PrinterConfig? printer = printing.kitchenPrinterFor(g.key.$1);
      if (printer == null) continue;
      final StationTicket ticket = buildStationTicket(
        order,
        g.value,
        stationName: _stationName(g.key.$1),
        sentAt: now(),
        isVoid: isVoid,
      );
      try {
        final PrintJob job = await printing.printers.print(
          printer,
          layoutStationTicket(ticket, printer.paper),
        );
        issues.watch(printing.printers.queue, job);
        queued++;
      } on Object catch (e) {
        // Chizib bo'lmadi (masalan, rasm rejimi): sotuv davom etadi, xato bannerda
        issues.reportRender(ticket.stationName, '$e');
      }
    }
    return queued;
  }

  String _stationName(int? stationId) {
    final PosStation? s = bootstrap()?.station(stationId);
    if (s != null) return s.name;
    return stationId == null ? 'Oshxona' : 'Sex $stationId';
  }
}

/// Buyurtma qatorlaridan sex markasi (bitta sex, bitta bosqich).
StationTicket buildStationTicket(
  PosOrder order,
  List<PosOrderItem> items, {
  required String stationName,
  required DateTime sentAt,
  bool isVoid = false,
}) {
  final PosChannel? channel = order.posChannel;
  final DateTime? due = items
      .map((PosOrderItem i) => i.dueAt)
      .whereType<DateTime>()
      .fold<DateTime?>(
        null,
        (DateTime? a, DateTime b) => a == null || b.isBefore(a) ? b : a,
      );
  final PosOrderItem first = items.first;
  return StationTicket(
    stationName: stationName,
    sentAt: sentAt.toLocal(),
    tableLabel: order.table?.name,
    orderLabel: order.table == null
        ? (channel == PosChannel.takeaway ? 'Olib ketish' : 'Zalda, stolsiz')
        : null,
    orderCode: order.shortCode,
    waiter: order.waiter?.name,
    round: first.roundNo,
    guests: order.guestsCount == null || order.guestsCount == 0
        ? null
        : order.guestsCount,
    dueAt: due?.toLocal(),
    isVoid: isVoid,
    voidReason: isVoid ? first.voidReason : null,
    voidedBy: isVoid ? first.voidedBy?.name : null,
    items: <StationTicketItem>[
      for (final PosOrderItem i in items)
        StationTicketItem(
          name: i.displayName,
          quantity: ItemQuantity(
            i.quantity,
            unit: i.unit,
            unitLabel: itemUnitLabel(i),
          ),
          modifiers: itemModifierLabels(i),
          note: i.note,
          seat: i.seatNo,
        ),
    ],
  );
}

/// Chop etilmagan sex markalari (kassa bo'yicha bitta): banner shu ro'yxatni ko'rsatadi, "Qayta urinish" navbatni
/// davom ettiradi, chiqqan marka ro'yxatdan o'zi tushadi.
class KitchenPrintIssues extends ChangeNotifier {
  KitchenPrintIssues._();

  static final Expando<KitchenPrintIssues> _byPrinting =
      Expando<KitchenPrintIssues>();

  static KitchenPrintIssues of(CashierPrinting printing) =>
      _byPrinting[printing] ??= KitchenPrintIssues._();

  static final Expando<KitchenPrintIssues> _receiptsByPrinting =
      Expando<KitchenPrintIssues>();

  /// Chek printeriga ketgan hujjatlar (sotuv, qaytarish, schyot, hisobot): qog'oz tugasa yoki printer javob bermasa
  /// ish ekranlarida banner ko'rinadi (sotuv cheki "jim" yo'qolmasin).
  static KitchenPrintIssues receiptsOf(CashierPrinting printing) =>
      _receiptsByPrinting[printing] ??= KitchenPrintIssues._();

  final Map<String, PrintJob> _failed = <String, PrintJob>{};
  final Set<String> _watched = <String>{};
  final List<String> _renderErrors = <String>[];
  PrintQueue? _queue;
  StreamSubscription<PrintJob>? _sub;

  List<PrintJob> get failed => _failed.values.toList();
  List<String> get renderErrors => List<String>.unmodifiable(_renderErrors);
  bool get isEmpty => _failed.isEmpty && _renderErrors.isEmpty;

  void watch(PrintQueue queue, PrintJob job) {
    if (!identical(_queue, queue)) {
      unawaited(_sub?.cancel());
      _queue = queue;
      _sub = queue.jobUpdates.listen(_onJob);
    }
    _watched.add(job.id);
    _onJob(job);
  }

  void _onJob(PrintJob job) {
    if (!_watched.contains(job.id)) return;
    final bool changed;
    if (job.status == PrintJobStatus.failed) {
      changed = !_failed.containsKey(job.id);
      _failed[job.id] = job;
    } else {
      changed = _failed.remove(job.id) != null;
      if (job.status.isFinal) _watched.remove(job.id);
    }
    if (changed) notifyListeners();
  }

  void reportRender(String station, String error) {
    _renderErrors.add('$station: $error');
    notifyListeners();
  }

  /// Hamma xato markalarni qayta yuborish.
  void retryAll() {
    final PrintQueue? q = _queue;
    if (q != null) {
      for (final String id in _failed.keys.toList()) {
        q.retry(id);
      }
    }
    _renderErrors.clear();
    notifyListeners();
  }

  /// Bannerni yopish: xato markalar bekor qilinadi (oshxonaga og'zaki aytildi).
  void dismissAll() {
    final PrintQueue? q = _queue;
    if (q != null) {
      for (final String id in _failed.keys.toList()) {
        q.cancel(id);
      }
    }
    _failed.clear();
    _renderErrors.clear();
    notifyListeners();
  }
}

/// "Marka chop etilmadi" banneri: sotuvni to'xtatmaydi, "Qayta urinish" va "Yopish" bilan.
class KitchenPrintBanner extends StatelessWidget {
  const KitchenPrintBanner({
    super.key,
    required this.issues,
    this.title = 'Marka chop etilmadi',
  });

  final KitchenPrintIssues issues;

  /// Sarlavha (soni qavsda qo'shiladi).
  final String title;

  @override
  Widget build(BuildContext context) => ListenableBuilder(
    listenable: issues,
    builder: (BuildContext context, _) {
      if (issues.isEmpty) return const SizedBox.shrink();
      final RzColors c = context.rz;
      final List<String> lines = <String>[
        for (final PrintJob j in issues.failed)
          '${j.title}${j.lastError == null ? '' : ' — ${j.lastError}'}',
        ...issues.renderErrors,
      ];
      return Semantics(
        liveRegion: true,
        child: Material(
          color: Color.alphaBlend(c.warningSoft, c.surface),
          child: Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 8, 8),
            child: Row(
              children: <Widget>[
                Icon(Icons.print_disabled_outlined, color: c.warningSoftFg),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisSize: MainAxisSize.min,
                    children: <Widget>[
                      Text(
                        '$title (${lines.length})',
                        style: RzType.headline.copyWith(color: c.warningSoftFg),
                      ),
                      for (final String l in lines.take(3))
                        Text(
                          l,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: RzType.footnote.copyWith(color: c.fg),
                        ),
                    ],
                  ),
                ),
                RzButton(
                  label: 'Qayta urinish',
                  icon: Icons.refresh,
                  variant: RzButtonVariant.secondary,
                  onPressed: issues.retryAll,
                ),
                const SizedBox(width: 4),
                RzButton(
                  label: 'Yopish',
                  variant: RzButtonVariant.ghost,
                  onPressed: issues.dismissAll,
                ),
              ],
            ),
          ),
        ),
      );
    },
  );
}
