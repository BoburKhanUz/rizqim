import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'fake_pos_state.dart';

/// Haqiqiy `HttpServer` (loopback): REST ([FakePosState]) va Reverb WebSocket (`/app/<key>`) bitta portda. Kassa va
/// ofitsiant ilovalarini Linux'da backend'siz ishga tushirish va suratga olish uchun (`tool/fake_pos_server.dart`).
///
/// Boshqaruv: `POST /__control/offline?on=1|0` (server javob bermaydi), `/__control/drop` (WebSocket'lar uziladi),
/// `/__control/new-order?table=2` (yangi buyurtma va real-time hodisa).
class FakePosServer {
  FakePosServer._(this._server, this.state, this._sockets);

  /// [port] 0 — bo'sh port. [demo] — ochiq smena va bir nechta band xona bilan boshlanadi.
  static Future<FakePosServer> start({int port = 0, bool demo = false}) async {
    final List<_Ws> sockets = <_Ws>[];
    final FakePosState state = FakePosState(
      onEvent: (String event, Map<String, Object?> data) {
        for (final _Ws s in sockets) {
          for (final String channel in s.channels) {
            s.send(event, channel: channel, data: data);
          }
        }
      },
    );
    if (demo) {
      state
        ..openShift()
        ..seedOrder(tableId: 1, guests: 8)
        ..seedOrder(tableId: 3, guests: 5, age: const Duration(hours: 7))
        ..seedOrder(
          tableId: 12,
          guests: 3,
          sent: false,
          age: const Duration(minutes: 12),
        )
        ..seedOrder(
          guests: 0,
          lines: const <(int, String)>[(4, '6')],
          age: const Duration(minutes: 4),
        );
    }
    final HttpServer server = await HttpServer.bind(
      InternetAddress.loopbackIPv4,
      port,
    );
    final FakePosServer fake = FakePosServer._(server, state, sockets);
    unawaited(fake._serve());
    return fake;
  }

  final HttpServer _server;
  final List<_Ws> _sockets;
  final FakePosState state;

  /// `true` — server javob bermaydi (tarmoq uzilgandek).
  bool offline = false;

  /// Konsolga so'rovlar jurnali.
  bool verbose = false;

  int get port => _server.port;

  /// `RIZQIM_API_URL`.
  String get apiBaseUrl => 'http://127.0.0.1:$port/api';

  /// `RIZQIM_REVERB_URL`.
  String get reverbUrl => 'ws://127.0.0.1:$port';

  Future<void> _serve() async {
    await for (final HttpRequest request in _server) {
      unawaited(_handle(request));
    }
  }

  Future<void> dropSockets() async {
    for (final _Ws s in _sockets.toList()) {
      await s.socket.close();
    }
  }

  Future<void> stop() async {
    await dropSockets();
    await _server.close(force: true);
  }

  Future<void> _handle(HttpRequest request) async {
    final String path = request.uri.path;
    try {
      if (path.startsWith('/__control/')) {
        switch (path) {
          case '/__control/offline':
            offline = request.uri.queryParameters['on'] == '1';
            _json(request, 200, <String, Object?>{'offline': offline});
          case '/__control/drop':
            await dropSockets();
            _json(request, 200, <String, Object?>{'dropped': true});
          case '/__control/new-order':
            final int? table = int.tryParse(
              request.uri.queryParameters['table'] ?? '',
            );
            final String uuid = state.seedOrder(
              tableId: table,
              sent: false,
              age: Duration.zero,
            );
            state.emit('order.created', <String, Object?>{
              'order': <String, Object?>{
                'uuid': uuid,
                'status': 'accepted',
                'branch_id': FakePosState.branchId,
              },
            });
            _json(request, 200, <String, Object?>{'uuid': uuid});
          default:
            _json(request, 404, <String, Object?>{
              'message': 'unknown control',
            });
        }
        return;
      }
      if (offline) {
        final Socket raw = await request.response.detachSocket(
          writeHeaders: false,
        );
        raw.destroy();
        return;
      }
      if (path.startsWith('/app/') &&
          WebSocketTransformer.isUpgradeRequest(request)) {
        await _upgrade(request);
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
      final String? auth = request.headers.value(
        HttpHeaders.authorizationHeader,
      );
      final FakeResponse r = state.handle(
        request.method,
        path,
        request.uri.queryParameters,
        body,
        auth?.replaceFirst('Bearer ', ''),
        idempotencyKey: request.headers.value('Idempotency-Key'),
      );
      if (verbose) {
        stdout.writeln('${request.method} ${request.uri} -> ${r.status}');
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

  Future<void> _upgrade(HttpRequest request) async {
    final WebSocket ws = await WebSocketTransformer.upgrade(request);
    final _Ws client = _Ws(
      ws,
      '${_sockets.length + 1}.${DateTime.now().millisecond}',
    );
    _sockets.add(client);
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
            if ('${data['auth']}'.startsWith('${FakePosState.realtimeKey}:')) {
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
      onDone: () => _sockets.remove(client),
      onError: (Object _) => _sockets.remove(client),
    );
  }

  static void _json(HttpRequest request, int status, Object? body) {
    request.response
      ..statusCode = status
      ..headers.contentType = ContentType.json
      ..write(jsonEncode(body));
    unawaited(request.response.close());
  }
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
