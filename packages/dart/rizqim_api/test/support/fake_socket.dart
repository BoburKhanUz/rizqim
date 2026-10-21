import 'dart:async';
import 'dart:convert';

import 'package:rizqim_api/rizqim_api.dart';

/// Soxta Reverb serveri: har bir ulanish [FakeSocket], testlar xabar yuboradi va yuborilganlarni o'qiydi.
class FakeRealtimeServer {
  final List<FakeSocket> sockets = <FakeSocket>[];
  final List<Uri> uris = <Uri>[];
  final List<Map<String, String>> headers = <Map<String, String>>[];

  /// `true` bo'lsa ulanish rad etiladi (server o'chiq).
  bool down = false;

  FakeSocket get last => sockets.last;

  Future<RealtimeSocket> connect(Uri uri, Map<String, String> h) async {
    uris.add(uri);
    headers.add(h);
    if (down) throw StateError('connection refused');
    final FakeSocket s = FakeSocket();
    sockets.add(s);
    return s;
  }
}

class FakeSocket implements RealtimeSocket {
  final StreamController<String> _incoming = StreamController<String>();
  final List<Map<String, Object?>> sent = <Map<String, Object?>>[];
  bool closed = false;

  @override
  Stream<String> get messages => _incoming.stream;

  @override
  void send(String message) => sent.add(
    (jsonDecode(message) as Map<Object?, Object?>).cast<String, Object?>(),
  );

  @override
  Future<void> close() async {
    closed = true;
    if (!_incoming.isClosed) unawaited(_incoming.close());
  }

  /// Serverdan xabar (Pusher: `data` JSON satr).
  void push(String event, {String? channel, Object? data}) => _incoming.add(
    jsonEncode(<String, Object?>{
      'event': event,
      'channel': ?channel,
      'data': data is String ? data : jsonEncode(data ?? <String, Object?>{}),
    }),
  );

  void establish({String socketId = '123.456', int activityTimeout = 30}) =>
      push(
        'pusher:connection_established',
        data: <String, Object?>{
          'socket_id': socketId,
          'activity_timeout': activityTimeout,
        },
      );

  /// Server ulanishni uzdi.
  void drop() {
    if (!_incoming.isClosed) unawaited(_incoming.close());
  }

  Iterable<Map<String, Object?>> sentEvents(String event) =>
      sent.where((Map<String, Object?> m) => m['event'] == event);
}
