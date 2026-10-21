import 'dart:async';
import 'dart:convert';

import 'package:rizqim_api/rizqim_api.dart';

/// Soxta Reverb: ulanishni o'zi tasdiqlaydi, imzolangan obunaga javob beradi, hodisalarni kanalga yuboradi.
class FakeRealtimeHub {
  final List<FakeSocket> sockets = <FakeSocket>[];

  /// `true` — ulanish rad etiladi (server o'chiq).
  bool down = false;
  int connects = 0;

  Iterable<FakeSocket> get open => sockets.where((FakeSocket s) => !s.closed);

  Future<RealtimeSocket> connect(Uri uri, Map<String, String> headers) async {
    connects++;
    if (down) throw StateError('connection refused');
    final FakeSocket s = FakeSocket(this, headers);
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

  /// Hodisani obuna bo'lganlarga yuboradi (backend `onEvent`).
  void broadcast(String event, Map<String, Object?> data) {
    for (final FakeSocket s in open) {
      for (final String channel in s.channels) {
        s.push(event, channel: channel, data: data);
      }
    }
  }

  /// Hamma ulanish uziladi.
  void dropAll() {
    for (final FakeSocket s in open.toList()) {
      s.drop();
    }
  }
}

class FakeSocket implements RealtimeSocket {
  FakeSocket(this.hub, this.headers);

  final FakeRealtimeHub hub;
  final Map<String, String> headers;
  final StreamController<String> _incoming = StreamController<String>();
  final Set<String> channels = <String>{};
  final List<Map<String, Object?>> sent = <Map<String, Object?>>[];
  bool closed = false;

  @override
  Stream<String> get messages => _incoming.stream;

  @override
  void send(String message) {
    final Map<String, Object?> m =
        (jsonDecode(message) as Map<Object?, Object?>).cast<String, Object?>();
    sent.add(m);
    if (m['event'] == 'pusher:subscribe') {
      final Map<String, Object?> data = (m['data']! as Map<Object?, Object?>)
          .cast<String, Object?>();
      final String channel = data['channel']! as String;
      if ('${data['auth']}'.startsWith('kds-key:')) {
        channels.add(channel);
        scheduleMicrotask(
          () =>
              push('pusher_internal:subscription_succeeded', channel: channel),
        );
      }
    }
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
