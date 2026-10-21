import 'dart:math' as math;

/// Qayta ulanish oralig'i: 1 s, 2 s, 4 s ... [maxDelay] gacha, ±[jitter] tasodifiy farq bilan (ko'p terminal bir
/// vaqtda serverga yopishmasin).
class Backoff {
  const Backoff({
    this.initial = const Duration(seconds: 1),
    this.maxDelay = const Duration(seconds: 30),
    this.jitter = 0.2,
    this.random,
  });

  final Duration initial;
  final Duration maxDelay;

  /// 0..1: oraliq shuncha ulushga kamayishi yoki oshishi mumkin.
  final double jitter;
  final math.Random? random;

  /// [attempt] — nechanchi muvaffaqiyatsiz urinish (0 dan).
  Duration delay(int attempt) {
    final int cap = maxDelay.inMilliseconds;
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
