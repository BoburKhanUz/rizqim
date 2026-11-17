import 'dart:async';
import 'dart:math';

import 'limits.dart';
import 'operation.dart';
import 'store.dart';

/// Bitta paket: navbat boshidan ko'pi bilan `batchSize` amal, qurilma soati va navbat holati bilan.
class SyncBatchRequest {
  const SyncBatchRequest({
    required this.operations,
    required this.sentAt,
    required this.queueSize,
    this.oldestAt,
  });

  final List<SyncOperation> operations;
  final DateTime sentAt;

  /// Navbatdagi hamma amallar (shu paket bilan).
  final int queueSize;
  final DateTime? oldestAt;

  /// `POST /pos/sync` tanasi.
  Map<String, Object?> toJson() => <String, Object?>{
    'sent_at': sentAt.toUtc().toIso8601String(),
    'queue': <String, Object?>{
      'size': queueSize,
      'oldest_at': ?oldestAt?.toUtc().toIso8601String(),
    },
    'operations': <Map<String, Object?>>[
      for (final SyncOperation o in operations) o.toJson(),
    ],
  };
}

/// `POST /pos/sync` javobi.
class SyncBatchResponse {
  const SyncBatchResponse({
    required this.results,
    this.clockSkewSeconds,
    this.clockWarning = false,
    this.stoppedAt,
  });

  factory SyncBatchResponse.fromJson(Map<String, Object?> j) =>
      SyncBatchResponse(
        results: <SyncResult>[
          for (final Object? r
              in (j['results'] as List<Object?>?) ?? const <Object?>[])
            if (r is Map) SyncResult.fromJson(r.cast<String, Object?>()),
        ],
        clockSkewSeconds: (j['clock_skew_seconds'] as num?)?.toInt(),
        clockWarning: j['clock_warning'] == true,
        stoppedAt: (j['stopped_at'] as num?)?.toInt(),
      );

  final List<SyncResult> results;
  final int? clockSkewSeconds;
  final bool clockWarning;

  /// Server shu `seq` da to'xtadi (bazada vaqtinchalik xato): u va keyingilari keyin qayta yuboriladi.
  final int? stoppedAt;
}

/// Paketni serverga yetkazadi. Tarmoq yo'q — [SyncUnreachable]; server rad etdi — [SyncRefused].
abstract interface class SyncTransport {
  Future<SyncBatchResponse> send(SyncBatchRequest request);
}

class SyncUnreachable implements Exception {
  const SyncUnreachable([this.cause]);

  final Object? cause;

  @override
  String toString() => 'SyncUnreachable($cause)';
}

class SyncRefused implements Exception {
  const SyncRefused(this.message, {this.status, this.reason});

  final String message;
  final int? status;
  final String? reason;

  @override
  String toString() => 'SyncRefused($status $reason: $message)';
}

/// Qayta urinishlar oralig'i: 2, 4, 8 ... soniya, ko'pi bilan [max], ±20% tasodifiy (bir nechta kassa bir vaqtda
/// urinmasin).
class SyncBackoff {
  const SyncBackoff({
    this.base = const Duration(seconds: 2),
    this.max = const Duration(minutes: 1),
    this.jitter = 0.2,
  });

  final Duration base;
  final Duration max;
  final double jitter;

  Duration delay(int failures, [Random? random]) {
    final int exp = min(failures - 1, 20).clamp(0, 20);
    final int ms = min(base.inMilliseconds * (1 << exp), max.inMilliseconds);
    final double j = random == null
        ? 0
        : (random.nextDouble() * 2 - 1) * jitter;
    return Duration(milliseconds: (ms * (1 + j)).round());
  }
}

/// Bir marta yuborishning natijasi.
class SyncDrainResult {
  const SyncDrainResult({required this.sent, required this.left, this.error});

  /// Server javob bergan amallar.
  final int sent;

  /// Navbatda qolgani.
  final int left;
  final Object? error;

  bool get drained => left == 0 && error == null;
}

/// Navbatni tartib bilan yuboradi (docs/POS_SPEC.md 5.2): navbat boshidan paket-paket, har javobdan keyin amallar
/// navbatdan olinadi; tarmoq yoki server xatosida to'xtaydi va [SyncBackoff] bo'yicha kutib qayta urinadi
/// ([start] dan keyin). Bir vaqtda faqat bitta yuborish ketadi; amal ikki marta yetib borsa ham server `op_id` bo'yicha
/// bir marta qo'llaydi (javob yo'qolsa — o'sha amallar qayta yuboriladi).
class SyncSender {
  SyncSender({
    required this.store,
    required this.transport,
    this.limits = const OfflineLimits(),
    this.backoff = const SyncBackoff(),
    DateTime Function()? clock,
    Random? random,
    this.onDrained,
  }) : _clock = clock ?? DateTime.now,
       _random = random; // ignore: prefer_initializing_formals

  final SyncStore store;
  final SyncTransport transport;
  OfflineLimits limits;
  final SyncBackoff backoff;
  final DateTime Function() _clock;
  final Random? _random;

  /// Navbat to'liq bo'shadi (server holatini qayta yuklash vaqti).
  Future<void> Function()? onDrained;

  final StreamController<void> _changes = StreamController<void>.broadcast();
  Future<SyncDrainResult>? _running;

  /// The running attempt was started for an explicit request ([drain], [nudge]), not by the queue or the timer.
  bool _runningExplicit = false;

  /// An explicit request that came while an older attempt was on the wire: it waits for that one, then sends afresh.
  Future<SyncDrainResult>? _fresh;
  Timer? _retry;
  StreamSubscription<void>? _storeSub;
  bool _started = false;

  int failures = 0;
  DateTime? lastSuccessAt;
  Object? lastError;
  int? clockSkewSeconds;
  bool clockWarning = false;
  DateTime? nextRetryAt;

  Stream<void> get changes => _changes.stream;

  bool get syncing => _running != null || _fresh != null;

  /// Navbatni hozir yuboradi va shu urinishning natijasini qaytaradi ("Yuborish" tugmasi).
  ///
  /// An attempt the queue or the retry timer started earlier may have left while the network was down and still be on
  /// its way back with that failure: the answer to this call is not that one. It waits for it (one send at a time, in
  /// order) and, if the queue is not empty then, sends once more at once, without the backoff. An attempt started for
  /// an earlier explicit request is joined.
  Future<SyncDrainResult> drain() {
    final Future<SyncDrainResult>? running = _running;
    if (running == null || _runningExplicit) return _join(explicit: true);
    return _fresh ??= _after(running);
  }

  Future<SyncDrainResult> _after(Future<SyncDrainResult> running) async {
    SyncDrainResult? before;
    try {
      before = await running;
    } on Object {
      // That attempt's own caller sees its error; this one sends afresh
    } finally {
      _fresh = null;
    }
    if (!store.hasPending) {
      // Nothing left to send: a wait set before this request is moot
      if (!syncing) _cancelRetry();
      return before != null && before.drained
          ? before
          : SyncDrainResult(sent: before?.sent ?? 0, left: 0);
    }
    return _join(explicit: true);
  }

  Future<SyncDrainResult> _join({bool explicit = false}) {
    final Future<SyncDrainResult>? running = _running;
    if (running != null) return running;
    _runningExplicit = explicit;
    return _running = _attempt();
  }

  Future<SyncDrainResult> _attempt() async {
    final SyncDrainResult r;
    try {
      r = await _drain();
    } finally {
      _running = null;
      _runningExplicit = false;
    }
    _settle(r);
    _emit();
    return r;
  }

  /// After an attempt: a wait before the next one if it failed (unless a fresh one is already asked for), none if the
  /// queue is empty; what was queued while the server state was being reloaded goes at once.
  void _settle(SyncDrainResult r) {
    if (r.drained) {
      if (_fresh != null) return;
      _cancelRetry();
      if (_started && store.hasPending) unawaited(_auto());
      return;
    }
    if (!_started || r.left == 0 || _fresh != null || _retry != null) return;
    final Duration wait = backoff.delay(max(1, failures), _random);
    nextRetryAt = _clock().add(wait);
    _retry = Timer(wait, () {
      _retry = null;
      nextRetryAt = null;
      if (_started) unawaited(_auto());
    });
  }

  /// Avtomatik rejim: navbatga amal tushsa yoki xatodan keyin kutish tugasa — yuboradi.
  void start() {
    if (_started) return;
    _started = true;
    _storeSub = store.changes.listen((_) {
      if (store.hasPending && !syncing && _retry == null) unawaited(_auto());
    });
    if (store.hasPending) unawaited(_auto());
  }

  /// Aloqa qaytdi: kutmasdan darhol urinish (oldin chiqqan urinish yo'lda bo'lsa — u tugagach, bir marta yangidan).
  void nudge() {
    _cancelRetry();
    if (_started && store.hasPending) unawaited(_auto(explicit: true));
  }

  Future<void> stop() async {
    _started = false;
    _cancelRetry();
    await _storeSub?.cancel();
    _storeSub = null;
  }

  Future<void> dispose() async {
    await stop();
    unawaited(_changes.close());
  }

  void _cancelRetry() {
    _retry?.cancel();
    _retry = null;
    nextRetryAt = null;
  }

  Future<void> _auto({bool explicit = false}) async {
    await (explicit ? drain() : _join());
  }

  Future<SyncDrainResult> _drain() async {
    var sent = 0;
    _emit();
    while (store.hasPending) {
      final List<SyncOperation> pending = store.pending;
      final List<SyncOperation> batch = pending
          .take(max(1, limits.batchSize))
          .toList();
      final SyncBatchResponse response;
      try {
        response = await transport.send(
          SyncBatchRequest(
            operations: batch,
            sentAt: _clock(),
            queueSize: pending.length,
            oldestAt: pending.first.occurredAt,
          ),
        );
      } on Object catch (e) {
        failures++;
        lastError = e;
        return SyncDrainResult(sent: sent, left: store.pendingCount, error: e);
      }
      clockSkewSeconds = response.clockSkewSeconds;
      clockWarning = response.clockWarning;
      final int before = store.pendingCount;
      try {
        await store.markContact();
        await store.applyResults(response.results);
      } on Object catch (e) {
        // The answers could not be written down: the operations stay queued and go again (the server knows them by
        // op_id), the retry timer keeps running
        failures++;
        lastError = e;
        return SyncDrainResult(
          sent: sent + before - store.pendingCount,
          left: store.pendingCount,
          error: e,
        );
      }
      sent += before - store.pendingCount;
      if (response.stoppedAt != null || store.pendingCount == before) {
        // The server could not take the next one now: try again later
        failures++;
        lastError = const SyncRefused(
          "Server amalni hozir qabul qila olmadi, keyinroq qayta yuboriladi.",
        );
        return SyncDrainResult(
          sent: sent,
          left: store.pendingCount,
          error: lastError,
        );
      }
      failures = 0;
      lastError = null;
      lastSuccessAt = _clock();
      _emit();
    }
    final Future<void> Function()? done = onDrained;
    if (done != null && sent > 0) {
      try {
        await done();
      } on Object {
        // Reloading the server state failed: the next refresh does it
      }
    }
    return SyncDrainResult(sent: sent, left: 0);
  }

  void _emit() {
    if (!_changes.isClosed) _changes.add(null);
  }
}
