// Soxta backend + Reverb: oshxona ekranini Linux'da (Xvfb) haqiqiy ilova sifatida ishga tushirib ko'rish va suratga
// olish uchun. Testlar bilan bir xil holat ([FakeKitchenState]) ishlatiladi. [FakeKdsServer] sinfini `flutter test` ham
// ishlatadi (`test/kds_over_http_test.dart`: haqiqiy HTTP/WebSocket bilan butun oqim).
//
//   cd pos/apps/kds
//   dart run test/fake_server/fake_kds_server.dart --port 8787
//   flutter run -d linux --dart-define=RIZQIM_API_URL=http://127.0.0.1:8787/api \
//     --dart-define=RIZQIM_REVERB_KEY=kds-key
//
// Ulash kodi — istalgan 8 belgi (masalan ABCD-EFGH), PIN — 2468 (3 marta xato — 60 soniya bloklanadi).
// Boshqaruv: `curl -X POST http://127.0.0.1:8787/__control/new-order` (yangi buyurtma + real-time hodisa),
// `.../__control/offline?on=1|0` (server javob bermaydi), `.../__control/drop` (WebSocket'larni uzish).
import 'dart:async';
import 'dart:convert';
import 'dart:io';

import '../support/fake_kitchen_state.dart';

/// Haqiqiy `HttpServer` (loopback): REST ([FakeKitchenState]) va Reverb WebSocket (`/app/<key>`) bitta portda. Linux'da
/// qo'lda ishga tushiriladi ([main]) yoki `flutter test` ichida ilovani haqiqiy HTTP/WebSocket orqali sinash uchun
/// (`test/kds_over_http_test.dart`).
class FakeKdsServer {
  FakeKdsServer._(this._server, this.state, this._sockets);

  /// [port] 0 — bo'sh port. [demo] — namunaviy buyurtmalar bilan boshlanadi.
  static Future<FakeKdsServer> start({
    int port = 0,
    bool demo = false,
    bool pos = false,
  }) async {
    final List<_Ws> sockets = <_Ws>[];
    final FakeKitchenState state = FakeKitchenState(
      onEvent: (String event, Map<String, Object?> data) {
        for (final _Ws s in sockets) {
          for (final String channel in s.channels) {
            s.send(event, channel: channel, data: data);
          }
        }
      },
    );
    if (demo) state.seedDemo();
    if (pos) state.seedPosDemo();
    final HttpServer server = await HttpServer.bind(
      InternetAddress.loopbackIPv4,
      port,
    );
    final FakeKdsServer fake = FakeKdsServer._(server, state, sockets);
    unawaited(fake._serve());
    return fake;
  }

  final HttpServer _server;
  final List<_Ws> _sockets;
  final FakeKitchenState state;

  /// `true` — server javob bermaydi (tarmoq uzilgandek).
  bool offline = false;

  /// Konsolga so'rovlar jurnali (qo'lda ishga tushirishda).
  bool verbose = false;

  int get port => _server.port;

  /// `RIZQIM_API_URL`: `/api` bilan tugaydi.
  String get apiBaseUrl => 'http://127.0.0.1:$port/api';

  /// Reverb (`RIZQIM_REVERB_URL`).
  String get reverbUrl => 'ws://127.0.0.1:$port';

  /// Hozir ochiq WebSocket'lar va ular obuna bo'lgan kanallar (imzosi tekshirilgan).
  List<Set<String>> get subscriptions =>
      _sockets.map((_Ws s) => Set<String>.of(s.channels)).toList();

  Future<void> _serve() async {
    await for (final HttpRequest request in _server) {
      unawaited(_handle(request, state, _sockets, this));
    }
  }

  /// Hamma WebSocket'larni uzadi (qayta ulanishni sinash).
  Future<void> dropSockets() async {
    for (final _Ws s in _sockets.toList()) {
      await s.socket.close();
    }
  }

  Future<void> stop() async {
    for (final _Ws s in _sockets.toList()) {
      await s.socket.close();
    }
    await _server.close(force: true);
  }
}

Future<void> main(List<String> args) async {
  final int port = int.tryParse(_arg(args, '--port') ?? '') ?? 8787;
  // `--pos`: choyxona namunasi (sexlar, mangal, oldindan buyurtmalar, qozon), oddiy namunasiz
  final bool pos = args.contains('--pos');
  final FakeKdsServer server =
      await FakeKdsServer.start(port: port, demo: !pos, pos: pos)
        ..verbose = true;
  stdout.writeln(
    'Soxta KDS backend: ${server.apiBaseUrl} (Reverb: ws://127.0.0.1:${server.port}/app/kds-key)',
  );
}

Future<void> _handle(
  HttpRequest request,
  FakeKitchenState state,
  List<_Ws> sockets,
  FakeKdsServer server,
) async {
  final String path = request.uri.path;
  try {
    if (path.startsWith('/__control/')) {
      switch (path) {
        case '/__control/new-order':
          final String uuid = state.addOrder(
            type: request.uri.queryParameters['type'] ?? 'pickup',
            channel: 'telegram',
            notes: request.uri.queryParameters['notes'],
            items: const <(String, String, String, List<String>)>[
              ('Tovuq kabob', '2', 'pcs', <String>['Sous alohida']),
              ('Fri', '1', 'pcs', <String>[]),
            ],
          );
          _json(request, 200, <String, Object?>{'uuid': uuid});
        case '/__control/void':
          // Birinchi POS buyurtmaning birinchi pishayotgan qatori o'chiriladi (ovozli signal va "O'CHIRILDI")
          final Map<String, Object?> order = state.orders.firstWhere(
            (Map<String, Object?> o) => o['channel'] == 'pos_hall',
          );
          final Map<String, Object?> item = (order['items']! as List<Object?>)
              .cast<Map<String, Object?>>()
              .firstWhere(
                (Map<String, Object?> i) => i['kitchen_status'] == 'sent',
              );
          state.voidLine(
            order['uuid']! as String,
            item['id']! as int,
            reason:
                request.uri.queryParameters['reason'] ??
                'Mehmon fikrini o\'zgartirdi',
          );
          _json(request, 200, <String, Object?>{'voided': item['id']});
        case '/__control/cancel':
          final Map<String, Object?> last = state.orders.lastWhere(
            (Map<String, Object?> o) => o['channel'] == 'pos_hall',
          );
          state.cancelOrder(last['uuid']! as String);
          _json(request, 200, <String, Object?>{'canceled': last['uuid']});
        case '/__control/offline':
          server.offline = request.uri.queryParameters['on'] == '1';
          _json(request, 200, <String, Object?>{'offline': server.offline});
        case '/__control/drop':
          await server.dropSockets();
          _json(request, 200, <String, Object?>{'dropped': true});
        default:
          _json(request, 404, <String, Object?>{'message': 'unknown control'});
      }
      return;
    }

    if (server.offline) {
      // Tarmoq uzilgandek: javobsiz yopiladi
      final Socket raw = await request.response.detachSocket(
        writeHeaders: false,
      );
      raw.destroy();
      return;
    }

    if (path.startsWith('/app/') &&
        WebSocketTransformer.isUpgradeRequest(request)) {
      final WebSocket ws = await WebSocketTransformer.upgrade(request);
      final _Ws client = _Ws(
        ws,
        '${sockets.length + 1}.${DateTime.now().millisecond}',
      );
      sockets.add(client);
      client.send(
        'pusher:connection_established',
        data: <String, Object?>{'socket_id': client.id, 'activity_timeout': 30},
      );
      ws.listen(
        (Object? raw) {
          final Map<String, Object?> m = (jsonDecode(
            '$raw',
          ) as Map<Object?, Object?>).cast<String, Object?>();
          final Map<String, Object?> data = m['data'] is Map
              ? (m['data']! as Map<Object?, Object?>).cast<String, Object?>()
              : <String, Object?>{};
          switch (m['event']) {
            case 'pusher:ping':
              client.send('pusher:pong');
            case 'pusher:subscribe':
              final String channel = '${data['channel']}';
              if ('${data['auth']}'.startsWith(
                'kds-key:${client.id}:$channel',
              )) {
                client.channels.add(channel);
                client.send(
                  'pusher_internal:subscription_succeeded',
                  channel: channel,
                );
              } else {
                client.send(
                  'pusher:subscription_error',
                  channel: channel,
                  data: <String, Object?>{'status': 403},
                );
              }
            case 'pusher:unsubscribe':
              client.channels.remove('${data['channel']}');
          }
        },
        onDone: () => sockets.remove(client),
        onError: (Object _) => sockets.remove(client),
      );
      return;
    }

    final String text = await utf8.decodeStream(request);
    Object? body;
    if (text.isNotEmpty) {
      final String type = request.headers.contentType?.mimeType ?? '';
      body = type == 'application/x-www-form-urlencoded'
          ? Uri.splitQueryString(text)
          : jsonDecode(text);
    }
    final String? auth = request.headers.value(HttpHeaders.authorizationHeader);
    final FakeResponse r = state.handle(
      request.method,
      path,
      request.uri.queryParameters,
      body,
      auth?.replaceFirst('Bearer ', ''),
    );
    if (server.verbose) {
      stdout.writeln('${request.method} $path -> ${r.status}');
    }
    r.headers.forEach(request.response.headers.set);
    _json(request, r.status, r.body);
  } on Object catch (e) {
    stderr.writeln('xato: $e');
    try {
      _json(request, 500, <String, Object?>{'message': '$e'});
    } on Object {
      // javob allaqachon yuborilgan
    }
  }
}

void _json(HttpRequest request, int status, Object? body) {
  request.response
    ..statusCode = status
    ..headers.contentType = ContentType.json
    ..write(jsonEncode(body));
  unawaited(request.response.close());
}

String? _arg(List<String> args, String name) {
  final int i = args.indexOf(name);
  return i >= 0 && i + 1 < args.length ? args[i + 1] : null;
}

class _Ws {
  _Ws(this.socket, this.id);

  final WebSocket socket;
  final String id;
  final Set<String> channels = <String>{};

  void send(String event, {String? channel, Object? data}) => socket.add(
    jsonEncode(<String, Object?>{
      'event': event,
      'channel': ?channel,
      'data': jsonEncode(data ?? <String, Object?>{}),
    }),
  );
}
