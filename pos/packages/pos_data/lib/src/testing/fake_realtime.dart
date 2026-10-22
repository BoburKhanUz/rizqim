import 'dart:async';
import 'dart:convert';

import 'package:rizqim_api/rizqim_api.dart';

import 'fake_pos_state.dart';

/// Soxta Reverb (KDS testlaridagi `FakeRealtimeHub` nusxasi): ulanishni o'zi tasdiqlaydi, imzosi
/// [FakePosState.realtimeKey] bilan boshlangan obunani qabul qiladi, hodisalarni kanalga yuboradi.
///
/// `PosRealtime.create(..., appKey: FakePosState.realtimeKey, connector: hub.connect)`.
class FakePosRealtimeHub {
  final List<FakePosSocket> sockets = <FakePosSocket>[];

  /// `true` — ulanish rad etiladi (server o'chiq).
  bool down = false;
  int connects = 0;

  /// `true` — obuna [releaseSubscriptions] gacha tasdiqlanmaydi.
  bool holdSubscriptions = false;

  Iterable<FakePosSocket> get open =>
      sockets.where((FakePosSocket s) => !s.closed);

  Future<RealtimeSocket> connect(Uri uri, Map<String, String> headers) async {
    connects++;
    if (down) throw StateError('connection refused');
    final FakePosSocket s = FakePosSocket(this, headers);
    sockets.add(s);
    scheduleMicrotask(
      () => s.push(
        'pusher:connection_established',
        data: <String, Object?>{
          'socket_id': '${sockets.length}.42',
          'activity_timeout': 120,
        },
      ),
    );
    return s;
  }

  /// Hodisani obuna bo'lganlarga yuboradi ([FakePosState.onEvent] ga ulang).
  void broadcast(String event, Map<String, Object?> data) {
    for (final FakePosSocket s in open) {
      for (final String channel in s.channels) {
        s.push(event, channel: channel, data: data);
      }
    }
  }

  void releaseSubscriptions() {
    holdSubscriptions = false;
    for (final FakePosSocket s in open) {
      for (final String channel in s.held) {
        s.channels.add(channel);
        s.push('pusher_internal:subscription_succeeded', channel: channel);
      }
      s.held.clear();
    }
  }

  /// Hamma ulanish uziladi.
  void dropAll() {
    for (final FakePosSocket s in open.toList()) {
      s.drop();
    }
  }
}

class FakePosSocket implements RealtimeSocket {
  FakePosSocket(this.hub, this.headers);

  final FakePosRealtimeHub hub;
  final Map<String, String> headers;
  final StreamController<String> _incoming = StreamController<String>();
  final Set<String> channels = <String>{};
  final Set<String> held = <String>{};
  final List<Map<String, Object?>> sent = <Map<String, Object?>>[];
  bool closed = false;

  @override
  Stream<String> get messages => _incoming.stream;

  @override
  void send(String message) {
    final Map<String, Object?> m =
        (jsonDecode(message) as Map<Object?, Object?>).cast<String, Object?>();
    sent.add(m);
    if (m['event'] != 'pusher:subscribe') return;
    final Map<String, Object?> data = (m['data']! as Map<Object?, Object?>)
        .cast<String, Object?>();
    final String channel = data['channel']! as String;
    if (!'${data['auth']}'.startsWith('${FakePosState.realtimeKey}:')) {
      scheduleMicrotask(
        () => push(
          'pusher:subscription_error',
          channel: channel,
          data: <String, Object?>{'status': 403},
        ),
      );
      return;
    }
    if (hub.holdSubscriptions) {
      held.add(channel);
      return;
    }
    channels.add(channel);
    scheduleMicrotask(
      () => push('pusher_internal:subscription_succeeded', channel: channel),
    );
  }

  @override
  Future<void> close() async {
    closed = true;
    if (!_incoming.isClosed) unawaited(_incoming.close());
  }

  void push(String event, {String? channel, Object? data}) {
    if (_incoming.isClosed) return;
    _incoming.add(
      jsonEncode(<String, Object?>{
        'event': event,
        'channel': ?channel,
        'data': jsonEncode(data ?? <String, Object?>{}),
      }),
    );
  }

  void drop() {
    closed = true;
    if (!_incoming.isClosed) unawaited(_incoming.close());
  }
}
