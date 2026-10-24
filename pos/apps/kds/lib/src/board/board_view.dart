import 'package:flutter/foundation.dart';
import 'package:rizqim_api/rizqim_api.dart';

import '../settings/kds_settings.dart';
import '../stations/station_selection.dart';

/// Server soati bo'yicha "hozir" ([now] qurilma soati): chipta javobi kelgan payt serverning `waiting_seconds` si
/// qabul qilingan vaqtdan hisoblangan, shuning uchun server vaqti = qabul + kutish. Planshet soati serverdan farq qilsa
/// ham oldindan buyurtma muddati va qatorning kutishi to'g'ri chiqadi.
DateTime serverNowAt(KitchenTicket t, DateTime now) {
  final DateTime? start = t.acceptedAt ?? t.createdAt;
  final DateTime atReceipt = start != null && t.isCooking
      ? start.add(Duration(seconds: t.waitingSeconds))
      : t.receivedAt;
  final Duration since = now.difference(t.receivedAt);
  return atReceipt.add(since.isNegative ? Duration.zero : since);
}

/// Doskadagi bitta chipta: ko'rinadigan qatorlar (sex va oldindan buyurtma bo'yicha saralangan) va kutish vaqti.
@immutable
class BoardTicket {
  const BoardTicket({
    required this.ticket,
    required this.waiting,
    this.posOrder = false,
    this.voided = false,
    this.canMarkOrderReady = true,
  });

  /// Faqat ko'rinadigan qatorlar bilan.
  final KitchenTicket ticket;

  /// [now] paytidagi kutish: POS chiptasida eng eski pishayotgan (yuborilgan) qatordan, aks holda server qoidasi.
  final Duration Function(DateTime now) waiting;

  /// POS buyurtmasi (qatorlari bosqich va sexli): "Buyurtma tayyor" tugmasi yo'q, hamma narsani qator bo'yicha hal qiladi.
  final bool posOrder;

  /// Butun buyurtma bekor qilingan va chiptalar ro'yxatidan ketgan: qisqa vaqt "O'CHIRILDI" bilan turadi.
  final bool voided;
  final bool canMarkOrderReady;
}

/// Oldindan buyurtma navbatidagi qator: muddati hali [lead] dan uzoq.
@immutable
class QueuedLine {
  const QueuedLine({
    required this.ticket,
    required this.line,
    required this.dueAt,
    required this.lead,
    required this.dueIn,
    this.station,
  });

  final KitchenTicket ticket;
  final KitchenLine line;
  final KitchenStation? station;

  /// Muddat (server vaqti).
  final DateTime dueAt;

  /// Doskaga chiqish uchun muddatdan oldingi vaqt (sex turiga qarab).
  final Duration lead;

  /// [now] paytidagi qolgan vaqt (manfiy — muddat o'tgan).
  final Duration Function(DateTime now) dueIn;
}

@immutable
class BoardView {
  const BoardView({
    this.active = const <BoardTicket>[],
    this.queue = const <QueuedLine>[],
    this.nextBoundary,
  });

  final List<BoardTicket> active;

  /// Muddat bo'yicha (eng yaqini birinchi).
  final List<QueuedLine> queue;

  /// Qurilma soati bo'yicha keyingi o'zgarish payti (o'chirilgan qator yo'qolishi yoki navbatdagi qator doskaga
  /// chiqishi): shu paytda ekran qayta chiziladi.
  final DateTime? nextBoundary;
}

/// Butun buyurtma bekor qilingani sabab ro'yxatdan ketgan chipta (qisqa vaqt ko'rsatiladi).
@immutable
class VoidedTicket {
  const VoidedTicket(this.ticket, this.seenAt);

  final KitchenTicket ticket;
  final DateTime seenAt;
}

/// Sexi yozilmagan, hali yuborilmagan oldindan buyurtma qatori (`hold`): server sexni yuborishda yozadi, shuning uchun
/// u filial yo'naltirishidan ([routes]: taom, bo'lmasa kategoriya) olinadi. Shunda qator o'z sexi ekranida turadi va
/// doskaga chiqish vaqti o'sha sex turidan hisoblanadi. Sexi bor yoki yuborilgan qator o'zgarmaydi.
KitchenLine routedLine(KitchenLine l, KitchenStationRoutes routes) {
  if (l.stationId != null || l.fireMode != 'hold' || l.sentAt != null) {
    return l;
  }
  final int? station = routes.stationFor(
    productId: l.productId,
    categoryId: l.categoryId,
  );
  return station == null ? l : l.copyWith(stationId: station);
}

/// Doskani yig'adi (toza funksiya: controller va testlar bir xil qoidadan foydalanadi).
///
/// * sex tanlovi: faqat tanlangan sexlar (va "Umumiy") qatorlari; [filter] `false` bo'lsa hammasi; sexsiz ushlab
///   turilgan qatorning sexi [routes] dan ([routedLine]);
/// * o'chirilgan qator [voidSeen] dan [voidWindow] davomida ko'rinadi, keyin yo'qoladi;
/// * ushlab turilgan (`hold`) qator muddati [KdsSettings.leadFor] dan uzoq bo'lsa oldindan buyurtma navbatiga o'tadi,
///   yaqinlashganda yoki yuborilganda faol doskaga chiqadi.
BoardView buildBoardView({
  required List<KitchenTicket> tickets,
  required StationSelection selection,
  required bool filter,
  required List<KitchenStation>? stations,
  required KdsSettings settings,
  required DateTime now,
  required Map<int, DateTime> voidSeen,
  required Duration voidWindow,
  Iterable<VoidedTicket> voidedTickets = const <VoidedTicket>[],
  KitchenStationRoutes routes = const KitchenStationRoutes(),
}) {
  final Map<int, KitchenStation> byId = <int, KitchenStation>{
    for (final KitchenStation s in stations ?? const <KitchenStation>[])
      s.id: s,
  };
  bool visibleStation(KitchenLine l) =>
      !filter || selection.includes(l.stationId);

  DateTime? boundary;
  void bound(DateTime at) {
    if (at.isAfter(now) && (boundary == null || at.isBefore(boundary!))) {
      boundary = at;
    }
  }

  final List<BoardTicket> active = <BoardTicket>[];
  final List<QueuedLine> queue = <QueuedLine>[];

  for (final KitchenTicket t in tickets) {
    final List<KitchenLine> shown = <KitchenLine>[];
    var routed = false;
    for (final KitchenLine raw in t.lines) {
      final KitchenLine l = routedLine(raw, routes);
      if (!identical(l, raw)) routed = true;
      if (!visibleStation(l)) continue;
      if (l.isVoid) {
        final DateTime? seen = voidSeen[l.id];
        if (seen == null) continue;
        final DateTime until = seen.add(voidWindow);
        if (!until.isAfter(now)) continue;
        bound(until);
        shown.add(l);
        continue;
      }
      if (l.isHeld) {
        final DateTime? due = l.dueAt;
        if (due == null) continue;
        final Duration lead = settings.leadFor(byId[l.stationId]?.kind);
        final Duration left = due.difference(serverNowAt(t, now));
        if (left > lead) {
          queue.add(
            QueuedLine(
              ticket: t,
              line: l,
              station: byId[l.stationId],
              dueAt: due,
              lead: lead,
              dueIn: (DateTime at) => due.difference(serverNowAt(t, at)),
            ),
          );
          bound(now.add(left - lead));
          continue;
        }
      }
      shown.add(l);
    }
    if (shown.isEmpty) continue;
    // Hamma qator mehmonga berilgan: oshxonada ko'riladigan narsa qolmadi (stol yopilguncha doskada turmaydi)
    if (shown.every((KitchenLine l) => l.isServed)) continue;
    _byRound(shown);
    final KitchenTicket visible = shown.length == t.lines.length && !routed
        ? t
        : t.copyWith(lines: shown);
    active.add(_boardTicket(visible, t));
  }

  for (final VoidedTicket v in voidedTickets) {
    final DateTime until = v.seenAt.add(voidWindow);
    if (!until.isAfter(now)) continue;
    final List<KitchenLine> shown = <KitchenLine>[
      for (final KitchenLine raw in v.ticket.lines)
        if (routedLine(raw, routes) case final KitchenLine l
            when visibleStation(l))
          l,
    ];
    if (shown.isEmpty) continue;
    bound(until);
    active.add(
      BoardTicket(
        ticket: v.ticket.copyWith(lines: shown),
        waiting: v.ticket.waitingAt,
        posOrder: true,
        voided: true,
        canMarkOrderReady: false,
      ),
    );
  }

  queue.sort((QueuedLine a, QueuedLine b) => a.dueAt.compareTo(b.dueAt));
  return BoardView(active: active, queue: queue, nextBoundary: boundary);
}

/// Bosqichlar tartibida (1, 2, ...), bosqich ichida server tartibi (qator id) saqlanadi. Server qatorlarni id bo'yicha
/// beradi, shuning uchun odatda allaqachon shunday; faqat keyin qo'shilgan qator tartibni buzmasin.
void _byRound(List<KitchenLine> lines) {
  if (lines.every((KitchenLine l) => l.roundNo == null)) return;
  final Map<int, int> index = <int, int>{
    for (var i = 0; i < lines.length; i++) lines[i].id: i,
  };
  lines.sort((KitchenLine a, KitchenLine b) {
    final int byRound = (a.roundNo ?? 0).compareTo(b.roundNo ?? 0);
    return byRound != 0 ? byRound : index[a.id]!.compareTo(index[b.id]!);
  });
}

BoardTicket _boardTicket(KitchenTicket visible, KitchenTicket full) {
  final bool pos = full.lines.any((KitchenLine l) => l.isPos);
  if (!pos) {
    return BoardTicket(ticket: visible, waiting: visible.waitingAt);
  }
  // Boshqa sexlarning qatorlari ham tayyor bo'lmaguncha "Buyurtma tayyor" yo'q: POS buyurtmasini qator bo'yicha yuritiladi
  return BoardTicket(
    ticket: visible,
    posOrder: true,
    canMarkOrderReady: false,
    waiting: (DateTime now) {
      final DateTime server = serverNowAt(visible, now);
      Duration since(DateTime at) {
        final Duration d = server.difference(at);
        return d.isNegative ? Duration.zero : d;
      }

      // Pishayotgan qator bor: eng eskisi yuborilgandan
      final DateTime? oldest = _oldestCooking(visible);
      if (oldest != null) return since(oldest);
      // Faqat oldindan buyurtma (hali yuborilmagan): muddatigacha kechikish yo'q, keyin muddatdan sanaladi
      final DateTime? due = _earliestHeldDue(visible);
      if (due != null) return since(due);
      // Hammasi tayyor (berilishini kutmoqda): oxirgisi tayyor bo'lgandan. Stol ochilganidan emas
      final DateTime? ready = _latestReady(visible);
      if (ready != null) return since(ready);
      return visible.waitingAt(now);
    },
  );
}

/// Ushlab turilgan (yuborilmagan) qatorlarning eng yaqin muddati.
DateTime? _earliestHeldDue(KitchenTicket t) {
  DateTime? earliest;
  for (final KitchenLine l in t.lines) {
    final DateTime? due = l.dueAt;
    if (!l.isHeld || due == null || l.isDone) continue;
    if (earliest == null || due.isBefore(earliest)) earliest = due;
  }
  return earliest;
}

/// Hamma qator tayyor yoki berilgan bo'lsa, oxirgisi tayyor bo'lgan payt.
DateTime? _latestReady(KitchenTicket t) {
  DateTime? latest;
  for (final KitchenLine l in t.lines) {
    if (l.isVoid) continue;
    if (!l.isDone) return null;
    final DateTime? at = l.readyAt;
    if (at != null && (latest == null || at.isAfter(latest))) latest = at;
  }
  return latest;
}

/// Eng eski pishayotgan (yuborilgan, tayyor emas) qatorning yuborilgan vaqti.
DateTime? _oldestCooking(KitchenTicket t) {
  DateTime? oldest;
  for (final KitchenLine l in t.lines) {
    final DateTime? sent = l.sentAt;
    if (sent == null || l.isDone || l.isVoid) continue;
    if (oldest == null || sent.isBefore(oldest)) oldest = sent;
  }
  return oldest;
}
