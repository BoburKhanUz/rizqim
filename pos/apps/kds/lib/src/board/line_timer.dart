import 'package:rizqim_api/rizqim_api.dart';
import 'package:rizqim_core/rizqim_core.dart' show formatDuration;

import 'board_view.dart';
import 'wait_level.dart';

/// P6-33: qatorning vaqt maqsadiga nisbatan taymeri. Maqsad (`target_seconds`) taomniki, bo'lmasa sexniki; qator
/// oshxonaga kelgan paytdan (POS: `sent_at`, onlayn: buyurtma qabul qilingan vaqt) sanaladi.
///
/// Rang: maqsadning [nearFraction] qismi o'tgach sariq ([WaitLevel.warn]), maqsad o'tgach qizil ([WaitLevel.late]).
/// Server `late` bayrog'i ham qizil qiladi (planshet soati serverdan farq qilsa ham). Maqsadsiz, ushlab turilgan,
/// tayyor yoki o'chirilgan qatorda taymer yo'q.
class LineTimer {
  const LineTimer({
    required this.target,
    required this.elapsed,
    this.serverLate = false,
  });

  /// Sexning "sariq"ga o'tish ulushi: maqsadning 80 foizi o'tdi.
  static const double nearFraction = 0.8;

  final Duration target;
  final Duration elapsed;

  /// Serverning oxirgi javobidagi `late`.
  final bool serverLate;

  /// Qolgan vaqt; manfiy — maqsad o'tgan.
  Duration get remaining => target - elapsed;

  bool get overdue => remaining.isNegative || serverLate;

  /// Maqsaddan necha vaqt o'tgan (kechikmagan bo'lsa nol).
  Duration get over => remaining.isNegative ? -remaining : Duration.zero;

  /// Sariq chegara o'tdi (yoki qizil).
  bool get near =>
      !overdue &&
      elapsed.inMilliseconds >= (target.inMilliseconds * nearFraction).round();

  WaitLevel get level => overdue
      ? WaitLevel.late
      : near
      ? WaitLevel.warn
      : WaitLevel.normal;

  /// [line] uchun [ticket] soatida [now] paytidagi taymer, yoki `null`.
  static LineTimer? of(KitchenTicket ticket, KitchenLine line, DateTime now) {
    final int? seconds = line.targetSeconds;
    if (seconds == null || seconds <= 0 || line.isDone || line.isVoid) {
      return null;
    }
    // Ushlab turilgan (yuborilmagan) qator oshxonada emas; onlayn qatorda `sent_at` yo'q: qabul qilingan vaqtdan
    final DateTime? started = line.isPos
        ? line.sentAt
        : (ticket.acceptedAt ?? ticket.createdAt);
    if (started == null) return null;
    final Duration elapsed = serverNowAt(ticket, now).difference(started);
    return LineTimer(
      target: Duration(seconds: seconds),
      elapsed: elapsed.isNegative ? Duration.zero : elapsed,
      serverLate: line.late,
    );
  }

  /// Chiptadagi pishayotgan qatorlarning eng yomon darajasi (maqsadli qator bo'lmasa oddiy).
  static WaitLevel worstLevel(
    KitchenTicket ticket,
    Iterable<KitchenLine> lines,
    DateTime now,
  ) {
    var worst = WaitLevel.normal;
    for (final KitchenLine l in lines) {
      final WaitLevel? level = of(ticket, l, now)?.level;
      if (level != null && level.index > worst.index) worst = level;
    }
    return worst;
  }

  /// Taymer matni: "Maqsad 5:00 · 2:10 qoldi" yoki "Kechikdi +1:15" (rang bilan birga ikonka va matn ham bor).
  String get text => overdue
      ? "Kechikdi +${formatDuration(over)} (maqsad ${formatDuration(target)})"
      : 'Maqsad ${formatDuration(target)} · ${formatDuration(remaining)} qoldi';
}
