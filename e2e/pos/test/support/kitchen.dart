import 'dart:async';

import 'package:dio/dio.dart';
import 'package:rizqim_api/rizqim_api.dart';

import 'stack.dart';

/// Polls until [probe] returns a value (not `null`), every [every], and fails after [timeout] saying [what].
Future<T> eventually<T extends Object>(
  Future<T?> Function() probe, {
  required String what,
  Duration timeout = const Duration(seconds: 30),
  Duration every = const Duration(milliseconds: 120),
}) async {
  final Stopwatch watch = Stopwatch()..start();
  Object? last;
  while (watch.elapsed < timeout) {
    try {
      final T? value = await probe();
      if (value != null) return value;
    } on Object catch (e) {
      last = e;
    }
    await Future<void>.delayed(every);
  }
  throw TimeoutException(
    'Timed out after ${timeout.inSeconds}s waiting for $what${last == null ? '' : ' (last error: $last)'}',
  );
}

/// The kitchen display's tickets with the ready ones too (`include_ready=1`).
///
/// Gap found by this test: `KitchenService.tickets(includeReady: true)` sends `include_ready=true`, which the backend's
/// `boolean` rule refuses (422): it accepts `1`/`0`/`true`/`false` as JSON booleans but not the string "true" of a
/// query. The display never asks for ready tickets today, so nobody saw it; the call is made by hand here.
Future<List<KitchenTicket>> ticketsIncludingReady(Terminal kds) async {
  final Response<Object?> response = await kds.client.dio.get<Object?>(
    '/v1/staff/kitchen/tickets',
    queryParameters: <String, Object?>{'include_ready': 1},
  );
  final Object? data = (response.data! as Map<Object?, Object?>)['data'];
  return <KitchenTicket>[
    for (final Object? t in data! as List<Object?>)
      KitchenTicket.fromJson(
        (t! as Map<Object?, Object?>).cast<String, Object?>(),
        receivedAt: DateTime.now(),
      ),
  ];
}

/// The cook at the kitchen display: a KDS terminal with the cook's PIN session that looks at the tickets every moment
/// and marks the lines of the stations with a display "ready" (the ones the till sent). Tea and bread go to a station
/// without a display, so the waiter serves them straight from `sent`.
class CookWorker {
  CookWorker(this.kds, {this.onlyPos = true, this.cookingTime = Duration.zero});

  final Terminal kds;

  /// Only the POS orders' tickets (the Telegram test marks its own).
  final bool onlyPos;

  /// How long a dish "cooks" before it is marked ready.
  final Duration cookingTime;

  bool _running = false;
  Future<void>? _loop;
  final Set<int> _bumped = <int>{};

  /// Lines marked ready, and the answers that were refused (a line voided meanwhile, ...).
  int bumps = 0;
  final List<String> refused = <String>[];

  /// Ids of the stations that have a display (`show_on_kds`), from the cook's bootstrap.
  Set<int> get _displayStations => <int>{
    for (final station in kds.bootstrap.stations)
      if (station.showOnKds) station.id,
  };

  void start() {
    if (_running) return;
    _running = true;
    _loop = _run();
  }

  Future<void> stop() async {
    _running = false;
    await _loop;
  }

  Future<void> _cook(String ticket, int line) async {
    if (cookingTime > Duration.zero) await Future<void>.delayed(cookingTime);
    try {
      await kds.client.kitchen.bumpLine(ticket, line);
      bumps++;
    } on ApiException catch (e) {
      refused.add('$ticket/$line: ${e.message}');
    }
  }

  Future<void> _run() async {
    final Set<int> stations = _displayStations;
    while (_running) {
      try {
        final KitchenTicketList list = await kds.client.kitchen.tickets();
        // The cook works on a few dishes at a time
        final List<Future<void>> cooking = <Future<void>>[];
        for (final KitchenTicket ticket in list.tickets) {
          if (onlyPos && !ticket.channel.startsWith('pos_')) continue;
          for (final KitchenLine line in ticket.lines) {
            // Sent to a station with a display, not ready yet, not voided
            if (line.status != KitchenLineStatus.sent) continue;
            if (!stations.contains(line.stationId)) continue;
            if (!_bumped.add(line.id)) continue;
            cooking.add(_cook(ticket.uuid, line.id));
            if (cooking.length >= 4) {
              await Future.wait(cooking);
              cooking.clear();
            }
          }
        }
        await Future.wait(cooking);
      } on Object catch (e) {
        refused.add('tickets: $e');
      }
      await Future<void>.delayed(const Duration(milliseconds: 100));
    }
  }
}
