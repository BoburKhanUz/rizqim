import 'dart:async';
import 'dart:io';

import 'package:web_socket_channel/io.dart';

import 'realtime_socket.dart';

/// `dart:io` WebSocket (Android, Windows, Linux) bilan [RealtimeSocket]. `web_socket_channel` (Dart jamoasi paketi)
/// ustida: sarlavha (`Origin`) yuborish va ulanish ochilishini kutish (`ready`) uchun.
///
/// TCP ulanish ham, WebSocket handshake (101) ham 15 soniya ichida tugashi kerak; aks holda so'rov majburan yopiladi
/// (server TCP'ni qabul qilib javob bermasa ham ulanish osilib qolmaydi).
Future<RealtimeSocket> ioRealtimeConnector(
  Uri uri,
  Map<String, String> headers,
) => ioRealtimeConnectorWith()(uri, headers);

/// [ioRealtimeConnector] boshqa muddat bilan (testlar uchun ham). [timeout] — TCP ulanish va handshake birgalikda.
RealtimeConnector ioRealtimeConnectorWith({
  Duration timeout = const Duration(seconds: 15),
}) => (Uri uri, Map<String, String> headers) async {
  final HttpClient http = HttpClient()..connectionTimeout = timeout;
  final IOWebSocketChannel channel = IOWebSocketChannel.connect(
    uri,
    headers: headers,
    customClient: http,
  );
  try {
    await channel.ready.timeout(timeout);
  } on Object {
    // Osilgan so'rovni (va uning TCP ulanishini) majburan yopamiz
    http.close(force: true);
    unawaited(channel.sink.close().catchError((Object _) => null));
    rethrow;
  }
  // Ulanish WebSocket'ga o'tdi (HttpClient'dan ajratilgan): klientning o'zi endi kerak emas
  http.close();
  return _IoSocket(channel);
};

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
