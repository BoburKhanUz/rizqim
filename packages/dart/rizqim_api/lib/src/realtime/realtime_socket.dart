import 'dart:async';

/// WebSocket ulanishining eng kichik ko'rinishi: [PusherClient] shu orqali ishlaydi, testlarda soxta ulanish beriladi.
abstract interface class RealtimeSocket {
  /// Serverdan kelgan matnli kadrlar. Ulanish yopilganda oqim tugaydi (yoki xato bilan tugaydi).
  Stream<String> get messages;

  void send(String message);

  Future<void> close();
}

/// [uri] ga ulanadi; ulanish ochilgach (101) qaytadi, bo'lmasa xato tashlaydi.
typedef RealtimeConnector = Future<RealtimeSocket> Function(
  Uri uri,
  Map<String, String> headers,
);
