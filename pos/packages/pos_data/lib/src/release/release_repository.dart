import 'dart:async';

import 'package:dio/dio.dart';
import 'package:rizqim_api/rizqim_api.dart';

import 'release_models.dart';

/// Reads what the server says about this terminal's app version (`GET /v1/terminal` -> `app_release`) at start and
/// every [interval] (6 hours), and holds the last answer.
///
/// * [release] `null` = no notice (empty registry, or nothing read yet); [notice] is [release] without the
///   `ok` state, so a banner needs only `notice != null`.
/// * Never throws into callers: a network or server error keeps the last state. Only a notice, never a gate on a
///   sale (owner rule): nothing else in the app asks this class for permission.
class PosReleaseRepository {
  PosReleaseRepository({required this.fetch, this.interval = defaultInterval});

  /// Reads `GET /v1/terminal` with the terminal token of [client] (hand-written read of `data.app_release`).
  factory PosReleaseRepository.fromClient(
    RizqimClient client, {
    Duration interval = defaultInterval,
  }) => PosReleaseRepository(
    interval: interval,
    fetch: () async {
      final Response<Object?> r = await client.dio.get<Object?>(
        '/v1/terminal',
        options: scoped(AuthScope.terminal),
      );
      final Object? body = r.data;
      final Object? data = body is Map ? body['data'] : null;
      return PosAppRelease.tryParse(data is Map ? data['app_release'] : null);
    },
  );

  static const Duration defaultInterval = Duration(hours: 6);

  /// One read of the server's answer; may throw (the repository keeps the last state then).
  final Future<PosAppRelease?> Function() fetch;
  final Duration interval;

  final StreamController<PosAppRelease?> _changes =
      StreamController<PosAppRelease?>.broadcast();
  Timer? _timer;
  Future<void>? _running;
  bool _disposed = false;
  PosAppRelease? _release;

  /// The last answer (`null`: no notice, or not read yet).
  PosAppRelease? get release => _release;

  /// [release] when it asks for an update (`update_available` or `update_required`), else `null`.
  PosAppRelease? get notice =>
      _release != null && _release!.state != PosReleaseState.ok
      ? _release
      : null;

  /// Emits [release] whenever it changes (not on a read that found the same answer).
  Stream<PosAppRelease?> get changes => _changes.stream;

  /// Reads now, then again every [interval] until [dispose]. Calling it again restarts the schedule.
  Future<void> start() {
    _timer?.cancel();
    if (_disposed) return Future<void>.value();
    _timer = Timer.periodic(interval, (Timer _) => unawaited(refresh()));
    return refresh();
  }

  /// One read. A read already in flight is shared. Never throws; an error keeps the last state.
  Future<void> refresh() => _running ??= _read().whenComplete(() {
    _running = null;
  });

  Future<void> _read() async {
    final PosAppRelease? next;
    try {
      next = await fetch();
    } on Object {
      // Offline, 401, 5xx, unreadable: keep what we know
      return;
    }
    if (_disposed || next == _release) return;
    _release = next;
    _changes.add(next);
  }

  void dispose() {
    _disposed = true;
    _timer?.cancel();
    _timer = null;
    unawaited(_changes.close());
  }
}
