import 'dart:math';

/// Kassaning bugungi navbat raqami oralig'i (bloki): serverdan olingan (`GET /pos/bootstrap` `queue.blocks` yoki
/// `POST /pos/queue/blocks`), `start`..`end` ikkala chet ham kiradi, `next` — server shu blokdan hali ishlatilmagan
/// deb bilgan birinchi raqam (docs/POS_SPEC.md 2.14).
class QueueRange {
  const QueueRange({required this.start, required this.end, int? next})
    : next = next ?? start;

  factory QueueRange.fromJson(Map<String, Object?> j) => QueueRange(
    start: (j['start']! as num).toInt(),
    end: (j['end']! as num).toInt(),
    next: (j['next'] as num?)?.toInt(),
  );

  final int start;
  final int end;
  final int next;

  Map<String, Object?> toJson() => <String, Object?>{
    'start': start,
    'end': end,
    'next': next,
  };

  @override
  String toString() => 'QueueRange($start..$end, next $next)';
}

/// Navbat raqamini tanlash qoidasi (Flutter'siz, holatsiz): biznes kun ichida kassa ishlatgan oxirgi raqamdan
/// ([last]) keyingi, o'z bloklaridan eng kichik bo'sh raqam; serverning `next` idan ham pastga tushmaydi. Hech qachon
/// takrorlanmaydi: ishlatilgan raqam [last] ga yoziladi va undan pastdagi raqamlar o'tkazib yuboriladi.
abstract final class QueueNumberRule {
  /// Keyingi raqam yoki `null` (bloklar tugagan: buyurtma raqamsiz ochiladi, server raqam beradi).
  static int? next(List<QueueRange> ranges, int last) {
    final List<QueueRange> sorted = List<QueueRange>.of(ranges)
      ..sort((QueueRange a, QueueRange b) => a.start.compareTo(b.start));
    for (final QueueRange r in sorted) {
      final int lo = max(max(r.next, r.start), last + 1);
      if (lo <= r.end) return lo;
    }
    return null;
  }

  /// Bloklarda [last] dan keyin qolgan raqamlar soni (`low_water` dan kam bo'lsa, aloqa borida yangi blok so'raladi).
  static int remaining(List<QueueRange> ranges, int last) {
    var n = 0;
    for (final QueueRange r in ranges) {
      final int lo = max(max(r.next, r.start), last + 1);
      if (lo <= r.end) n += r.end - lo + 1;
    }
    return n;
  }
}
