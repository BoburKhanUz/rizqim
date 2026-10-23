import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:pos_data/pos_data.dart';
import 'package:rizqim_api/rizqim_api.dart';
import 'package:test/test.dart';

/// Where the stack is (set by `scripts/pos-e2e.sh`) and what the seeder wrote (`PosStackSeeder`).
class StackConfig {
  StackConfig._({
    required this.apiUrl,
    required this.reverbUrl,
    required this.reverbKey,
    required this.seed,
  });

  /// Reads `POS_E2E_*`; a missing variable is an error that says how to run the tests.
  factory StackConfig.fromEnvironment() {
    String need(String name) {
      final String? value = Platform.environment[name];
      if (value == null || value.isEmpty) {
        throw StateError(
          '$name is not set. This test drives a real backend: run it with scripts/pos-e2e.sh '
          '(it starts Laravel and Reverb, seeds the database and sets POS_E2E_*).',
        );
      }
      return value;
    }

    final Object? seed = jsonDecode(
      File(need('POS_E2E_SEED_FILE')).readAsStringSync(),
    );
    return StackConfig._(
      apiUrl: need('POS_E2E_API_URL'),
      reverbUrl: need('POS_E2E_REVERB_URL'),
      reverbKey: need('POS_E2E_REVERB_KEY'),
      seed: Seed((seed! as Map<Object?, Object?>).cast<String, Object?>()),
    );
  }

  /// `http://127.0.0.1:18200/api`.
  final String apiUrl;

  /// `ws://127.0.0.1:18280`.
  final String reverbUrl;
  final String reverbKey;
  final Seed seed;
}

/// A member of the staff the seeder made.
class Staff {
  const Staff(this.key, this.id, this.name, this.pin);

  final String key;
  final int id;
  final String name;
  final String? pin;
}

/// The seeder's JSON.
class Seed {
  Seed(this.json);

  final Map<String, Object?> json;

  Map<String, Object?> _map(Object? value) =>
      (value! as Map<Object?, Object?>).cast<String, Object?>();

  int get branchId => (_map(json['branch'])['id']! as num).toInt();
  int get storeId => (_map(json['store'])['id']! as num).toInt();

  Staff staff(String key) {
    final Map<String, Object?> s = _map(_map(json['staff'])[key]);
    return Staff(
      key,
      (s['id']! as num).toInt(),
      s['name']! as String,
      s['pin'] as String?,
    );
  }

  /// The terminal as `POST /devices/pair` answers it.
  Map<String, Object?> terminal(String key) =>
      _map(_map(json['terminals'])[key]);

  String get customerToken => _map(json['customer'])['token']! as String;
  String get accountUuid => _map(json['account'])['uuid']! as String;
}

/// One event the terminal heard, with the moment it arrived.
class HeardEvent {
  HeardEvent(this.event, this.at);

  final PosEvent event;
  final DateTime at;
}

/// Everything a terminal hears on the branch channel, kept so a test can look back and wait for the next one.
class EventLog {
  EventLog(PosRealtime realtime) {
    _sub = realtime.events.listen((PosEvent e) {
      final HeardEvent heard = HeardEvent(e, DateTime.now());
      events.add(heard);
      for (final _Waiter w in List<_Waiter>.of(_waiters)) {
        if (w.test(e)) {
          _waiters.remove(w);
          w.done.complete(heard);
        }
      }
      if (!_signals.isClosed) _signals.add(e);
    });
  }

  late final StreamSubscription<PosEvent> _sub;
  final List<HeardEvent> events = <HeardEvent>[];
  final List<_Waiter> _waiters = <_Waiter>[];
  final StreamController<PosEvent> _signals =
      StreamController<PosEvent>.broadcast();

  /// Every event as it arrives.
  Stream<PosEvent> get stream => _signals.stream;

  /// Events heard so far that satisfy [test].
  List<HeardEvent> where(bool Function(PosEvent e) test) => <HeardEvent>[
    for (final HeardEvent h in events)
      if (test(h.event)) h,
  ];

  /// The first event (already heard, or the next to come) that satisfies [test]; fails after [timeout].
  Future<HeardEvent> waitFor(
    bool Function(PosEvent e) test, {
    Duration timeout = const Duration(seconds: 10),
    String what = 'event',
  }) {
    for (final HeardEvent h in events) {
      if (test(h.event)) return Future<HeardEvent>.value(h);
    }
    final _Waiter waiter = _Waiter(test);
    _waiters.add(waiter);
    return waiter.done.future.timeout(
      timeout,
      onTimeout: () {
        _waiters.remove(waiter);
        throw TimeoutException(
          'No $what within ${timeout.inSeconds}s (heard ${events.length} events: '
          '${events.map((HeardEvent h) => h.event.name).toSet().join(', ')})',
        );
      },
    );
  }

  Future<void> dispose() async {
    await _sub.cancel();
    await _signals.close();
  }
}

class _Waiter {
  _Waiter(this.test);

  final bool Function(PosEvent e) test;
  final Completer<HeardEvent> done = Completer<HeardEvent>();
}

/// A paired terminal with its own `RizqimClient` and `PosData` (the same stack an app builds), a staff member on its
/// PIN pad and the real-time channel it listens on.
class Terminal {
  Terminal._(this.label, this.config, this.client, this.pos);

  final String label;
  final StackConfig config;
  final RizqimClient client;
  final PosData pos;

  PosRealtime? realtime;
  EventLog? log;
  Staff? staff;

  /// Pairs nothing: the seeder paired the terminal; its token is restored from the device store, as an app does after
  /// a restart.
  static Future<Terminal> open(StackConfig config, String terminalKey) async {
    final MemoryKeyValueStore store = MemoryKeyValueStore();
    store.values[CredentialStorage.key] = jsonEncode(<String, Object?>{
      'terminal': config.seed.terminal(terminalKey),
    });
    final RizqimClient client = RizqimClient(
      baseUrl: config.apiUrl,
      store: store,
    );
    await client.auth.restore();
    if (client.auth.terminal == null) {
      throw StateError('Terminal $terminalKey was not restored');
    }
    return Terminal._(terminalKey, config, client, PosData(client));
  }

  PosBootstrap get bootstrap => pos.session.bootstrap!;

  /// The staff member types the PIN, then the app loads its bootstrap.
  Future<PosBootstrap> signIn(String staffKey) async {
    final Staff who = config.seed.staff(staffKey);
    await client.auth.signInStaff(userId: who.id, pin: who.pin!);
    staff = who;
    return pos.session.loadBootstrap();
  }

  /// Listens on `private-pos-branch.{id}` with the terminal's token and waits until the subscription is confirmed.
  Future<EventLog> listen() async {
    final PosRealtime rt = PosRealtime.create(
      client: client,
      terminal: client.auth.terminal!,
      apiBaseUrl: config.apiUrl,
      appKey: config.reverbKey,
      reverbUrl: config.reverbUrl,
      clientName: 'pos-e2e-$label',
    )!;
    realtime = rt;
    final EventLog events = EventLog(rt);
    log = events;
    rt.start();
    await rt.states
        .firstWhere((PosLiveState s) => s == PosLiveState.live)
        .timeout(
          const Duration(seconds: 15),
          onTimeout: () => throw TimeoutException(
            '$label: the real-time channel did not become live (state ${rt.state})',
          ),
        );
    return events;
  }

  Future<void> close() async {
    await realtime?.dispose();
    await log?.dispose();
    await pos.dispose();
    await client.auth.dispose();
  }
}

/// The evening's clock: the test does not wait for hours, it moves a made-up wall clock and logs against it.
class SimClock {
  SimClock({int hour = 18, int minute = 0}) : _minutes = hour * 60 + minute;

  int _minutes;

  /// Moves the clock; [pause] is the real time one simulated minute takes (a few ms, enough to interleave actors).
  Future<void> advance(
    int minutes, {
    Duration pause = const Duration(milliseconds: 3),
  }) async {
    _minutes += minutes;
    await Future<void>.delayed(pause * minutes);
  }

  String get label {
    final int m = _minutes % (24 * 60);
    return '${(m ~/ 60).toString().padLeft(2, '0')}:${(m % 60).toString().padLeft(2, '0')}';
  }
}

/// Prints what happened if the test fails (nothing when it passes).
class StepLog {
  StepLog(this.clock);

  final SimClock clock;

  void call(String who, String what) =>
      printOnFailure('[${clock.label}] $who: $what');
}
