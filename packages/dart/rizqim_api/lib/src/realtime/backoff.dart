import 'dart:math' as math;

/// Qayta ulanish oralig'i: 1 s, 2 s, 4 s ... [maxDelay] gacha, ±[jitter] tasodifiy farq bilan (ko'p terminal bir
/// vaqtda serverga yopishmasin).
///
/// M2: [firstSpread] > 0 — birinchi urinish (0-chi) 0..[firstSpread] oralig'ida tekis tasodifiy: Reverb qayta ishga
/// tushganda filialning hamma qurilmasi bir soniyada qaytib kelmaydi ([reconnect]).
class Backoff {
  const Backoff({
    this.initial = const Duration(seconds: 1),
    this.maxDelay = const Duration(seconds: 30),
    this.jitter = 0.2,
    this.random,
    this.firstSpread = Duration.zero,
  });

  /// The POS apps' reconnect: the first attempt after a drop anywhere in 0-10 s, then 2, 4, 8, 16, 30 s (+-20 %).
  static const Backoff reconnect = Backoff(firstSpread: Duration(seconds: 10));

  final Duration initial;
  final Duration maxDelay;

  /// The first attempt is spread uniformly over 0..[firstSpread] (zero: the usual [initial] with [jitter]).
  final Duration firstSpread;

  /// 0..1: oraliq shuncha ulushga kamayishi yoki oshishi mumkin.
  final double jitter;
  final math.Random? random;

  /// [attempt] — nechanchi muvaffaqiyatsiz urinish (0 dan).
  Duration delay(int attempt) {
    final int cap = maxDelay.inMilliseconds;
    if (attempt <= 0 && firstSpread > Duration.zero) {
      final int spread = math.min(cap, firstSpread.inMilliseconds);
      return Duration(
        milliseconds: ((random ?? _random).nextDouble() * spread).round(),
      );
    }
    final int capped = math.min(
      cap,
      initial.inMilliseconds * math.pow(2, math.min(attempt, 20)).toInt(),
    );
    if (jitter <= 0) return Duration(milliseconds: capped);
    final double r = (random ?? _random).nextDouble() * 2 - 1; // -1..1
    final int value = (capped * (1 + r * jitter)).round();
    return Duration(milliseconds: math.min(cap, math.max(0, value)));
  }

  static final math.Random _random = math.Random();
}
