import 'dart:async';
import 'dart:math' as math;

import 'channel_revision.dart';

/// Creates a timer (tests inject their own; `fake_async` also works with the default).
typedef PacingTimer = Timer Function(Duration after, void Function() run);

Timer _defaultTimer(Duration after, void Function() run) => Timer(after, run);

/// How a screen paces the reads that events and heartbeats ask for (wave M2 fix round 2, docs/scale/RESULTS_M2.md:
/// with the same 300 ms debounce everywhere, each committed write made all of a branch's screens re-read within
/// ~300 ms, in bursts that made the tills 3-4x slower at lunch).
///
/// * [spread]: the first read after a quiet moment waits a random U(0, [spread]), so the screens of a branch do not hit
///   the server in the same instant.
/// * [window]: once a read fired, further requests during [window] collapse into ONE read at the end of it (plus a new
///   random U(0, [spread])).
///
/// Only event and heartbeat reads are paced. A user's own action, a resync or reconnect, silence, polls (the safety
/// poll included) and the app coming back read at once.
class RefreshPacing {
  const RefreshPacing({required this.window, required this.spread});

  final Duration window;
  final Duration spread;

  /// KDS board: a kitchen ticket within ~2.5 s.
  static const RefreshPacing kitchen = RefreshPacing(
    window: Duration(seconds: 2),
    spread: Duration(milliseconds: 500),
  );

  /// Floors (cashier floor, waiter rooms): another till's change within ~6.5 s.
  static const RefreshPacing floor = RefreshPacing(
    window: Duration(seconds: 5),
    spread: Duration(milliseconds: 1500),
  );

  /// An open order and its billing page (that order's events only).
  static const RefreshPacing order = RefreshPacing(
    window: Duration(seconds: 1),
    spread: Duration(milliseconds: 300),
  );

  /// Other till and phone lists (queue page, shift, bookings, hostess).
  static const RefreshPacing list = RefreshPacing(
    window: Duration(seconds: 5),
    spread: Duration(milliseconds: 1500),
  );

  /// The till's pickup board (the sound and banner come from the event itself, at once).
  static const RefreshPacing pickup = RefreshPacing(
    window: Duration(seconds: 1),
    spread: Duration(milliseconds: 250),
  );

  /// The customer display.
  static const RefreshPacing display = RefreshPacing(
    window: Duration(milliseconds: 300),
    spread: Duration(milliseconds: 250),
  );

  /// No pacing at all (tests).
  static const RefreshPacing none = RefreshPacing(
    window: Duration.zero,
    spread: Duration.zero,
  );

  @override
  String toString() => 'RefreshPacing(window: $window, spread: $spread)';
}

/// Leading + trailing throttle on timers only (no clock): a [request] with nothing pending fires after U(0, spread); the
/// window starts when it fires; requests during the window collapse into one fire at its end plus a new U(0, spread).
///
/// [onFire] returns whether a read actually started: when it did not (nothing left to read), no window starts.
class RefreshThrottle {
  RefreshThrottle({
    required this.pacing,
    required this.onFire,
    this.random,
    PacingTimer? timer,
  }) : _timer = timer ?? _defaultTimer;

  /// The random source of every throttle without its own (`[0, 1)`). App test harnesses set it to `() => 0` (no spread).
  static double Function() defaultRandom = math.Random().nextDouble;

  final RefreshPacing pacing;
  final bool Function() onFire;

  /// This throttle's random source (`null`: [defaultRandom], read at each request).
  final double Function()? random;
  final PacingTimer _timer;

  Timer? _lead;
  Timer? _window;
  bool _trailing = false;
  bool _disposed = false;

  /// Fires so far (tests).
  int fired = 0;

  /// A fire is waiting (its spread or the end of a window).
  bool get pending => _lead != null || _trailing;

  /// Inside the window of the last fire.
  bool get inWindow => _window != null;

  /// Asks for a fire.
  void request() {
    if (_disposed || _lead != null) return;
    if (_window != null) {
      _trailing = true;
      return;
    }
    _lead = _timer(_jitter(), _fire);
  }

  /// The random part: U(0, spread).
  Duration _jitter() {
    final int us = pacing.spread.inMicroseconds;
    if (us <= 0) return Duration.zero;
    final double r = (random ?? defaultRandom)().clamp(0.0, 1.0);
    return Duration(microseconds: (us * r).floor().clamp(0, us));
  }

  void _fire() {
    _lead = null;
    if (_disposed) return;
    fired++;
    if (!onFire()) return;
    if (pacing.window > Duration.zero) {
      _window = _timer(pacing.window, _windowEnd);
    }
  }

  void _windowEnd() {
    _window = null;
    if (_disposed || !_trailing) return;
    _trailing = false;
    _lead = _timer(_jitter(), _fire);
  }

  void dispose() {
    _disposed = true;
    _lead?.cancel();
    _window?.cancel();
    _lead = null;
    _window = null;
    _trailing = false;
  }
}

/// Why a paced read runs.
enum PacedReason {
  /// A relevant event.
  event,

  /// A heartbeat said a commit was missed, or there is doubt.
  heartbeat,
}

/// What [PacedRefresh.event] did with an event.
enum PacedVerdict {
  /// Not relevant to this screen: it only fed the revision bookkeeping.
  ignored,

  /// Relevant, but a read already shows it (`rev == R`) or one is already asked for its `rev`.
  skipped,

  /// A paced read is asked.
  asked,
}

/// The single entry point for events and heartbeats on every screen that re-reads (the till lists, the waiter's
/// screens, the KDS board, the customer display): feeds [revision] with EVERY event, decides whether a read is needed,
/// and paces it ([RefreshThrottle]).
///
/// The screen calls [readStarted] right before EVERY full read it makes (paced or not: a read started after an event
/// shows it) and [readDone] / [readFailed] after it. When a paced read is due it is dropped if every event and
/// heartbeat that asked for it is covered by R (another read already shows them).
class PacedRefresh {
  PacedRefresh({
    required String? channel,
    required this.pacing,
    required this.onRead,
    this.readOnDoubt = true,
    double Function()? random,
    PacingTimer? timer,
  }) : revision = channel == null ? null : ChannelRevision(channel) {
    _throttle = RefreshThrottle(
      pacing: pacing,
      onFire: _due,
      random: random,
      timer: timer,
    );
  }

  /// `null` without a channel (local events only): every relevant event reads.
  final ChannelRevision? revision;
  final RefreshPacing pacing;

  /// Runs the read (the screen's own single-flight refresh).
  final void Function(PacedReason reason) onRead;

  /// Read on a heartbeat whose `rev` is S while there is doubt (tills, KDS). The customer display does not: its 30 s
  /// safety poll covers it.
  final bool readOnDoubt;

  late final RefreshThrottle _throttle;

  /// Tokens of what asked for the pending read (`null`: cannot be proven covered).
  final List<BigInt?> _pending = <BigInt?>[];
  bool _pendingEvent = false;
  bool _pendingDoubt = false;

  /// The pending entries include fresh asks (not only a retry of a failed read).
  bool _pendingFresh = false;

  /// The read running now took fresh asks: if it fails, they are asked again ONCE ([readFailed]).
  bool _inFlightFresh = false;

  /// The latest relevant `rev` asked for and not yet proven (pending or being read).
  BigInt? _asked;
  bool _disposed = false;

  /// Paced reads dropped because a read already showed everything that asked (tests).
  int dropped = 0;

  /// Paced reads that ran (tests).
  int paced = 0;

  /// Paced retries after a failed read (tests).
  int retries = 0;

  /// A paced read is waiting.
  bool get pending => _pending.isNotEmpty;

  RefreshThrottle get throttle => _throttle;

  /// An event on the channel; [relevant]: the screen's table says it can change what the screen shows.
  PacedVerdict event(Map<String, Object?> data, {required bool relevant}) {
    if (_disposed) return PacedVerdict.ignored;
    final ChannelRevision? rev = revision;
    if (!relevant) {
      rev?.ignored(data);
      return PacedVerdict.ignored;
    }
    final BigInt? token = rev?.revOf(data);
    if (token != null && token == _asked) {
      rev!.sameCount++;
      return PacedVerdict.skipped;
    }
    if (rev != null && rev.event(data) == RevisionVerdict.skip) {
      return PacedVerdict.skipped;
    }
    if (token != null) _asked = token;
    _pending.add(token);
    _pendingEvent = true;
    _pendingFresh = true;
    _throttle.request();
    return PacedVerdict.asked;
  }

  /// A `realtime.heartbeat`. Returns whether a paced read is asked.
  bool heartbeat(Map<String, Object?> data) {
    final ChannelRevision? rev = revision;
    if (_disposed || rev == null) return false;
    final HeartbeatVerdict v = rev.heartbeat(data, readOnDoubt: readOnDoubt);
    switch (v) {
      case HeartbeatVerdict.fine:
        return false;
      case HeartbeatVerdict.missed:
        _pending.add(rev.revOf(data));
      case HeartbeatVerdict.doubt:
        if (!readOnDoubt) return false;
        _pending.add(rev.revOf(data));
        _pendingDoubt = true;
    }
    _pendingFresh = true;
    _throttle.request();
    return true;
  }

  /// A full read of the screen is about to start (paced or not): it will show everything asked so far.
  void readStarted() {
    _inFlightFresh = _pendingFresh;
    _pending.clear();
    _pendingEvent = false;
    _pendingDoubt = false;
    _pendingFresh = false;
  }

  /// The read finished; [proven] is the token [ReadCapture.revisionFor] proved for [ChannelRevision.scope].
  void readDone(BigInt? proven) {
    _inFlightFresh = false;
    revision?.read(proven);
    final BigInt? asked = _asked;
    if (asked != null &&
        proven != null &&
        ChannelRevision.covers(proven, asked)) {
      _asked = null;
    }
  }

  /// The read failed (also when the screen's loader swallowed the error: the read capture tells): R is forgotten and
  /// doubt set, so the next heartbeat re-reads on tills and the KDS. When the read had taken events or heartbeats that
  /// asked for it, they are asked again ONCE, paced (about one window later); a retry that fails is not retried again
  /// (no loop while offline: doubt, the heartbeat and the polls take over).
  void readFailed() {
    revision?.forget();
    _asked = null;
    final bool retry = _inFlightFresh;
    _inFlightFresh = false;
    if (!retry || _disposed) return;
    retries++;
    _pending.add(null);
    _pendingEvent = true;
    _throttle.request();
  }

  bool _due() {
    if (_disposed || _pending.isEmpty) return false;
    if (_covered()) {
      dropped++;
      readStarted();
      return false;
    }
    paced++;
    onRead(_pendingEvent ? PacedReason.event : PacedReason.heartbeat);
    return true;
  }

  bool _covered() {
    final BigInt? r = revision?.known;
    if (r == null) return false;
    if (_pendingDoubt && revision!.doubt) return false;
    for (final BigInt? t in _pending) {
      if (t == null || !ChannelRevision.covers(r, t)) return false;
    }
    return true;
  }

  void dispose() {
    _disposed = true;
    _throttle.dispose();
    _pending.clear();
  }
}
