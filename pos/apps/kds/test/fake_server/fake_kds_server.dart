// Soxta backend + Reverb: oshxona ekranini Linux'da (Xvfb) haqiqiy ilova sifatida ishga tushirib ko'rish va suratga
// olish uchun. Testlar bilan bir xil holat ([FakeKitchenState]) ishlatiladi.
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

Future<void> main(List<String> args) async {
  final int port = int.tryParse(_arg(args, '--port') ?? '') ?? 8787;
  final List<_Ws> sockets = <_Ws>[];
  var offline = false;
  final FakeKitchenState state = FakeKitchenState(
    onEvent: (String event, Map<String, Object?> data) {
      for (final _Ws s in sockets) {
        for (final String channel in s.channels) {
          s.send(event, channel: channel, data: data);
        }
      }
    },
  )..seedDemo();

  final HttpServer server = await HttpServer.bind(
    InternetAddress.loopbackIPv4,
    port,
  );
  stdout.writeln(
    'Soxta KDS backend: http://127.0.0.1:$port/api (Reverb: ws://127.0.0.1:$port/app/kds-key)',
  );

  await for (final HttpRequest request in server) {
    unawaited(
      _handle(request, state, sockets, () => offline, (bool v) => offline = v),
    );
  }
}

Future<void> _handle(
  HttpRequest request,
  FakeKitchenState state,
  List<_Ws> sockets,
  bool Function() isOffline,
  void Function(bool) setOffline,
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
        case '/__control/offline':
          setOffline(request.uri.queryParameters['on'] == '1');
          _json(request, 200, <String, Object?>{'offline': isOffline()});
        case '/__control/drop':
          for (final _Ws s in sockets.toList()) {
            await s.socket.close();
          }
          _json(request, 200, <String, Object?>{'dropped': true});
        default:
          _json(request, 404, <String, Object?>{'message': 'unknown control'});
      }
      return;
    }

    if (isOffline()) {
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
    stdout.writeln('${request.method} $path -> ${r.status}');
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
