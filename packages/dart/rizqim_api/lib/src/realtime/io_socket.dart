import 'dart:async';
import 'dart:io';

import 'package:web_socket_channel/io.dart';

import 'realtime_socket.dart';

/// `dart:io` WebSocket (Android, Windows, Linux) bilan [RealtimeSocket]. `web_socket_channel` (Dart jamoasi paketi)
/// ustida: sarlavha (`Origin`) yuborish va ulanish ochilishini kutish (`ready`) uchun.
Future<RealtimeSocket> ioRealtimeConnector(
  Uri uri,
  Map<String, String> headers,
) async {
  final IOWebSocketChannel channel = IOWebSocketChannel.connect(
    uri,
    headers: headers,
    connectTimeout: const Duration(seconds: 15),
  );
  await channel.ready;
  return _IoSocket(channel);
}

class _IoSocket implements RealtimeSocket {
  _IoSocket(this._channel);

  final IOWebSocketChannel _channel;

  @override
  Stream<String> get messages =>
      _channel.stream.where((Object? m) => m is String).cast<String>();

  @override
  void send(String message) => _channel.sink.add(message);

  @override
  Future<void> close() async {
    try {
      await _channel.sink.close(WebSocketStatus.normalClosure);
    } on Object {
      // Allaqachon yopilgan
    }
  }
}
