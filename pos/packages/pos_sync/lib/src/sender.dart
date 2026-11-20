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
      // M5 S3: a signed operation goes as `{signed, mac, prev, kid}`, an unsigned one in the old shape
      for (final SyncOperation o in operations) o.toWire(),
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
    this.unreadable = const <SyncUnreadable>[],
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
        unreadable: <SyncUnreadable>[
          for (final Object? u
              in (j['unreadable'] as List<Object?>?) ?? const <Object?>[])
            if (u is Map && u['index'] is int)
              SyncUnreadable(
                index: u['index']! as int,
                reason: u['reason'] as String?,
                message: u['message'] as String?,
              ),
        ],
      );

  final List<SyncResult> results;
  final int? clockSkewSeconds;
  final bool clockWarning;

  /// Server shu `seq` da to'xtadi (bazada vaqtinchalik xato): u va keyingilari keyin qayta yuboriladi.
  final int? stoppedAt;

  /// M5 S3 (`data.unreadable`): signed elements of the batch whose text the server could not use and recorded
  /// nothing for, by their place in the batch. They leave the queue ([SyncResult.unreadable]).
  final List<SyncUnreadable> unreadable;
}

/// One entry of `data.unreadable`: [index] is the element's place in the `operations` that were sent (from 0).
class SyncUnreadable {
  const SyncUnreadable({required this.index, this.reason, this.message});

  final int index;
  final String? reason;
  final String? message;
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

/// The sender was stopped ([SyncSender.stop]: logout, re-pair, shutdown): nothing more is sent, the queue is left as
/// it is.
class SyncStopped implements Exception {
  const SyncStopped();

  @override
  String toString() => 'SyncStopped()';
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

  /// M5 S1 review (design bug 5): the server's `last_seq` for this device (`GET /pos/sync/status`), `null` when it
  /// cannot be read. Asked when the server answers 409 `seq_reused`: the journal is behind the server (lost or
  /// damaged while the pairing survived), the store adopts the number ([SyncStore.adoptServerSeq]) and the queue is
  /// sent again in the same attempt. Not set: such a refusal fails the attempt as any other.
  Future<int?> Function()? serverLastSeq;

  final StreamController<void> _changes = StreamController<void>.broadcast();
  Future<SyncDrainResult>? _running;

  /// The running attempt was started for an explicit request ([drain], [nudge]), not by the queue or the timer.
  bool _runningExplicit = false;

  /// An explicit request that came while an older attempt was on the wire: it waits for that one, then sends afresh.
  Future<SyncDrainResult>? _fresh;
  Timer? _retry;
  StreamSubscription<void>? _storeSub;
  bool _started = false;

  /// [stop] was called (and no [start] since): nothing may be sent, whoever asks.
  bool _stopped = false;

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
  ///
  /// After [stop] it sends nothing and answers [SyncStopped] at once.
  Future<SyncDrainResult> drain() {
    if (_stopped) return Future<SyncDrainResult>.value(_stoppedResult(0));
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
    if (_stopped) return _stoppedResult(0);
    if (!store.hasPending) {
      // Nothing left to send: a wait set before this request is moot
      if (!syncing) _cancelRetry();
      return before != null && before.drained
          ? before
          : SyncDrainResult(sent: before?.sent ?? 0, left: 0);
    }
    final Object? error = before?.error;
    if (before != null && error != null && error is! SyncUnreachable) {
      // That attempt reached the server and was answered (refused, stopped, 5xx): the answer stands, and so does the
      // backoff it sets; only an attempt that never got through is made again
      _settle(before);
      _emit();
      return before;
    }
    return _join(explicit: true);
  }

  SyncDrainResult _stoppedResult(int sent) => SyncDrainResult(
    sent: sent,
    left: store.pendingCount,
    error: const SyncStopped(),
  );

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
    _stopped = false;
    _storeSub = store.changes.listen((_) {
      if (store.hasPending && !syncing && _retry == null) unawaited(_auto());
    });
    if (store.hasPending) unawaited(_auto());
  }

  /// Aloqa qaytdi: kutmasdan darhol urinish. Call it when the network comes BACK (offline -> online) or the cashier
  /// asks ("send now"), not on every answer.
  ///
  /// What starts a send: an operation queued while nothing runs and no wait is set; the end of the backoff wait;
  /// [nudge]; [drain]; a successful attempt that ends with operations queued meanwhile.
  /// What resets the backoff: [nudge] (the wait is cancelled and an attempt goes at once) and an attempt the server
  /// accepts (`failures` back to 0). What does not: a failed attempt (the wait grows), an attempt the server answered
  /// but refused or stopped (the wait grows; a [nudge] that came while it ran does not send it again at once).
  /// If an attempt is running when [nudge] comes, it is not doubled: when it ends, the queue is sent once more at once
  /// only if that attempt never got through ([SyncUnreachable]). After [stop] nothing is sent.
  void nudge() {
    _cancelRetry();
    if (_started && store.hasPending) unawaited(_auto(explicit: true));
  }

  Future<void> stop() async {
    _started = false;
    _stopped = true;
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
      // Stopped between batches (logout, re-pair): the rest is not sent with credentials that are going away
      if (_stopped) return _stoppedResult(sent);
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
        // (after a renumbering that changed something the batch cannot collide the same way: no loop)
        if (await _renumbered(e)) continue;
        failures++;
        lastError = e;
        return SyncDrainResult(sent: sent, left: store.pendingCount, error: e);
      }
      clockSkewSeconds = response.clockSkewSeconds;
      clockWarning = response.clockWarning;
      final int before = store.pendingCount;
      try {
        await store.markContact();
        await store.applyResults(<SyncResult>[
          ...response.results,
          // M5 S3: a line the server could not read is answered by its place in the batch, with no row behind it.
          // Left in the queue it would be sent for ever
          for (final SyncUnreadable u in response.unreadable)
            if (u.index >= 0 && u.index < batch.length)
              SyncResult.unreadable(
                opId: batch[u.index].opId,
                seq: batch[u.index].seq,
                reason: u.reason,
                message: u.message,
              ),
        ]);
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

  /// The server refused the batch for a number it already holds: adopt its `last_seq`. `true`: the waiting
  /// operations have new numbers and go again.
  Future<bool> _renumbered(Object refusal) async {
    final Future<int?> Function()? ask = serverLastSeq;
    if (ask == null ||
        refusal is! SyncRefused ||
        refusal.reason != 'seq_reused' ||
        _stopped) {
      return false;
    }
    try {
      final int? last = await ask();
      return last != null && !_stopped && await store.adoptServerSeq(last);
    } on Object {
      return false;
    }
  }

  void _emit() {
    if (!_changes.isClosed) _changes.add(null);
  }
}
