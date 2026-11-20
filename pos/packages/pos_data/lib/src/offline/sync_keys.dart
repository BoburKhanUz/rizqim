import 'dart:async';

import 'package:pos_sync/pos_sync.dart';
import 'package:rizqim_api/rizqim_api.dart';

import 'pos_offline.dart';

/// The till will not ask the server for another sync key now ([PosSyncKeys.rotate]).
class PosSyncKeyRefused implements Exception {
  const PosSyncKeyRefused(this.reason, this.message);

  /// [PosSyncKeys.queueNotEmpty], [PosSyncKeys.notSigning], [PosSyncKeys.notPaired].
  final String reason;

  /// For the screen (Uzbek).
  final String message;

  @override
  String toString() => 'PosSyncKeyRefused($reason)';
}

/// Wave M5 S3-c (docs/scale/M5_DESIGN.md 4, docs/API.md "Imzolangan navbat"): the key of the signed offline queue on
/// the till.
///
/// * **Where it lives.** In the device's secure storage, in the terminal's own record ([AuthSession.syncKey]: it
///   arrives with the pairing answer and leaves with the terminal). Here only an in-memory [SyncKey] built from it,
///   which the journal asks for at every operation ([current] is the store's `keyProvider`). Never on the plain
///   disk, never in a log or a message.
/// * **When the queue is signed.** Only while the last bootstrap says `offline.signing: true` (the server is deployed
///   before the apps; an older server gets the old unsigned shape) and the terminal holds a usable key.
/// * **A till without a key** (paired before signing existed, or its key is unreadable) asks for one with
///   `POST /terminal/sync-key` by itself ([check]). So does a till whose key is older than the one the server says it
///   holds (`offline.signing_kid`: the key was rotated on the server), and a till whose own operation the server
///   answered as badly signed (`results[].sig_status: bad`, [SyncStore.badKid]: the server no longer holds this key
///   under this number, e.g. after a restore from a backup). A new key that is answered `bad` again is not replaced
///   at every sale: such a fetch is paced like a failed one.
/// * **The server refuses too** (409 `queue_not_empty`) while the last queue the till reported to it was not empty.
///   A till whose queue is empty says so ([PosOffline.reportQueue]) and asks once more; still refused, it waits.
/// * **Never while the queue is not empty.** Every call rotates the key on the server, which keeps only ONE older
///   key: a second call with operations of the first key still queued would turn them into `bad`. [check] waits for
///   the queue to drain; [rotate] refuses ([PosSyncKeyRefused], [queueNotEmpty]).
/// * **A signature never stops a sale.** No key, a failed request, a refusing server: the till sells on and queues
///   unsigned. A failed request is tried again later, with a growing pause ([retryAfter] .. [retryCap]); a timer
///   asks again when the pause is over, so a till that sits idle gets its key too.
class PosSyncKeys {
  PosSyncKeys({
    required AuthSession auth,
    required PosOffline offline,
    DateTime Function()? clock,
  }) : _auth = auth,
       _offline = offline,
       _clock = clock ?? DateTime.now {
    _subs
      ..add(offline.changes.listen((_) => _auto()))
      ..add(
        auth.events.listen((AuthEvent e) {
          if (e.kind == AuthEventKind.terminalPaired) {
            // Another pairing: what the old one was refused does not count
            _settled();
            _afterBad = false;
            _refusedToken = null;
          }
          _auto();
        }),
      );
    scheduleMicrotask(_auto);
  }

  static const String queueNotEmpty = 'queue_not_empty';
  static const String notSigning = 'signing_off';
  static const String notPaired = 'not_paired';

  /// The pause after the first failed request; doubled at every further one, up to [retryCap]. The server allows 6
  /// calls a minute.
  static const Duration retryAfter = Duration(minutes: 1);
  static const Duration retryCap = Duration(minutes: 15);

  final AuthSession _auth;
  final PosOffline _offline;
  final DateTime Function() _clock;
  final List<StreamSubscription<Object?>> _subs =
      <StreamSubscription<Object?>>[];

  TerminalSyncKey? _builtFrom;
  String? _builtFor;
  SyncKey? _built;

  Future<void>? _checking;
  int _failures = 0;
  DateTime? _notBefore;
  Timer? _retry;

  /// The last key was fetched because the server answered `bad`: the pause stands until it verifies an operation.
  bool _afterBad = false;
  String? _refusedToken;
  bool _disposed = false;

  /// The server takes signed operations (the last bootstrap, or its copy on the disk).
  bool get signing => _offline.settings.signing;

  /// The key to sign with now, or `null` (the operation is queued unsigned): the journal's `keyProvider`. Quick: no
  /// storage is read here.
  SyncKey? current() => signing ? _usable() : null;

  /// The number of the key the till holds (`null`: none), whether or not it signs now.
  int? get kid => _usable()?.kid;

  SyncKey? _usable() {
    final PairedTerminal? terminal = _auth.terminal;
    final TerminalSyncKey? saved = terminal?.syncKey;
    if (terminal == null || saved == null) return null;
    if (!identical(saved, _builtFrom) || _builtFor != terminal.device.uuid) {
      _builtFrom = saved;
      _builtFor = terminal.device.uuid;
      try {
        _built = SyncKey.fromBase64(
          saved.key,
          kid: saved.kid,
          deviceUuid: terminal.device.uuid,
        );
      } on Object {
        // Not a key (a damaged record): the till asks for a new one
        _built = null;
      }
    }
    return _built;
  }

  /// Why the till cannot ask for a key now (`null`: it can).
  PosSyncKeyRefused? get _blocked {
    if (_auth.terminal == null) {
      return const PosSyncKeyRefused(notPaired, 'Kassa ulanmagan.');
    }
    if (!signing) {
      return const PosSyncKeyRefused(
        notSigning,
        'Server navbat imzosini hali yoqmagan.',
      );
    }
    if (_offline.store.hasPending || _offline.held) {
      return const PosSyncKeyRefused(
        queueNotEmpty,
        "Navbatda yuborilmagan amallar bor: kalit navbat bo'shagandan keyin almashtiriladi.",
      );
    }
    return null;
  }

  /// Replaces the till's key with a new one from the server and returns its number. Refused ([PosSyncKeyRefused])
  /// while the queue is not empty, the till is not paired or the server does not sign; a network or server error is
  /// thrown as it came ([ApiException]) and the old key stays.
  Future<int> rotate() async {
    // A check on its way is the same request: wait for it, then decide afresh
    await _checking;
    final PosSyncKeyRefused? blocked = _blocked;
    if (blocked != null) throw blocked;
    final TerminalSyncKey? key = await _renew();
    if (key == null) {
      throw const PosSyncKeyRefused(notPaired, 'Kassa ulanmagan.');
    }
    _settled();
    _afterBad = false;
    return key.kid;
  }

  /// `POST /terminal/sync-key`. The server answers 409 `queue_not_empty` while the last queue this till reported was
  /// not empty; the queue IS empty here ([_blocked]), so the till says so and asks once more. Any other error, and a
  /// second refusal, is thrown.
  Future<TerminalSyncKey?> _renew() async {
    try {
      return await _auth.renewSyncKey();
    } on ApiException catch (e) {
      if (e.statusCode != 409 ||
          e.reason != queueNotEmpty ||
          _blocked != null) {
        rethrow;
      }
      if (!await _offline.reportQueue() || _blocked != null) rethrow;
      return _auth.renewSyncKey();
    }
  }

  /// The server answered an operation signed with the key the till holds as badly signed.
  bool get _keyAnsweredBad {
    final SyncKey? key = _usable();
    return key != null && _offline.store.badKid == key.kid;
  }

  void _settled() {
    _failures = 0;
    _notBefore = null;
    _retry?.cancel();
    _retry = null;
  }

  /// Fetches a key when the till needs one and may ask now; otherwise does nothing. Called by itself on every change
  /// of the queue, of the bootstrap copy and of the pairing, so that a till waiting for its queue to drain asks as
  /// soon as it has. Never throws.
  Future<void> check() =>
      _checking ??= _check().whenComplete(() => _checking = null);

  void _auto() {
    if (_disposed) return;
    if (_afterBad && _offline.store.badKid == null) {
      // The server verified an operation under the key fetched after a `bad`: that story is over
      _afterBad = false;
      _settled();
    }
    if (!_wanted) return;
    unawaited(check());
  }

  /// The till has no usable key, the server says it holds a newer one, or it answered this key's operation as badly
  /// signed, and nothing forbids asking.
  bool get _wanted {
    if (_blocked != null) return false;
    final PairedTerminal? terminal = _auth.terminal;
    if (terminal == null || terminal.token == _refusedToken) return false;
    if (!_offline.connectivity.state.online) return false;
    final DateTime? wait = _notBefore;
    if (wait != null && _clock().isBefore(wait)) return false;
    final SyncKey? key = _usable();
    final int? serverKid = _offline.settings.signingKid;
    return key == null ||
        (serverKid != null && serverKid > key.kid) ||
        _keyAnsweredBad;
  }

  Future<void> _check() async {
    if (_disposed || !_wanted) return;
    final String? token = _auth.terminal?.token;
    final bool afterBad = _keyAnsweredBad;
    try {
      await _renew();
      if (afterBad) {
        // Not at every sale if the server goes on answering `bad`: the next such fetch waits, longer each time
        _afterBad = true;
        _pause();
      } else {
        _settled();
      }
    } on ApiException catch (e) {
      final int? status = e.statusCode;
      if (status == 403 || status == 404 || status == 405) {
        // Not a POS terminal (`terminal_required`) or a server without the route: not asked again for this pairing
        // in this run
        _refusedToken = token;
        return;
      }
      _pause();
    } on Object {
      _pause();
    }
  }

  void _pause() {
    Duration wait = retryAfter * (1 << (_failures > 4 ? 4 : _failures));
    if (wait > retryCap) wait = retryCap;
    _failures++;
    _notBefore = _clock().add(wait);
    // Nothing may happen on an idle till when the pause is over: ask then
    _retry?.cancel();
    _retry = Timer(wait + const Duration(seconds: 1), () {
      _retry = null;
      _auto();
    });
  }

  Future<void> dispose() async {
    _disposed = true;
    _retry?.cancel();
    _retry = null;
    for (final StreamSubscription<Object?> s in _subs) {
      await s.cancel();
    }
  }
}
