import 'package:dio/dio.dart';

import 'pusher_client.dart';

/// `GET /api/v1/realtime` (public, cached 5 s on the server): whether real-time is live, the poll intervals, the silence
/// limit and the Reverb connection (docs/API.md 8, "M2"). Read at start and on every reconnect, never per poll.
class RealtimeConfig {
  const RealtimeConfig({
    required this.status,
    required this.live,
    required this.heartbeatSeconds,
    required this.silenceSeconds,
    required this.pollLiveSeconds,
    required this.pollFallbackSeconds,
    this.reverb,
  });

  factory RealtimeConfig.fromJson(Map<String, Object?> j) {
    int number(Object? v, int fallback) => v is num ? v.toInt() : fallback;
    final Object? poll = j['poll_seconds'];
    final Map<Object?, Object?> p = poll is Map
        ? poll
        : const <Object?, Object?>{};
    final Object? r = j['reverb'];
    return RealtimeConfig(
      status: j['status'] is String ? j['status']! as String : 'ok',
      live: j['live'] != false,
      heartbeatSeconds: number(j['heartbeat_seconds'], 0),
      silenceSeconds: number(j['silence_seconds'], 0),
      pollLiveSeconds: number(p['live'], 60),
      pollFallbackSeconds: number(p['fallback'], 15),
      reverb: r is Map
          ? ReverbEndpoint.fromJson(r.cast<String, Object?>())
          : null,
    );
  }

  /// `ok`, `degraded`, `off`.
  final String status;

  /// `false`: Reverb off, the heartbeat stale or the broadcast queue backed up — poll at the fallback interval.
  final bool live;
  final int heartbeatSeconds;

  /// No event or heartbeat on a channel for this long: treat real-time as down. 0: the server sends no heartbeat.
  final int silenceSeconds;
  final int pollLiveSeconds;
  final int pollFallbackSeconds;

  /// What to connect with; `null` when real-time is off.
  final ReverbEndpoint? reverb;

  /// Reads it; `null` when the server cannot be asked (offline, an old server without the endpoint, a broken body).
  static Future<RealtimeConfig?> fetch(Dio dio) async {
    try {
      final Response<Object?> r = await dio.get<Object?>('/v1/realtime');
      final Object? body = r.data;
      final Object? data = body is Map ? body['data'] : null;
      if (data is! Map) return null;
      return RealtimeConfig.fromJson(data.cast<String, Object?>());
    } on Object {
      return null;
    }
  }

  /// The WebSocket address: the compiled [appKey] (with [override], `RIZQIM_REVERB_URL`) when there is one, otherwise
  /// the server's [reverb] (its host, port and scheme; a null host is the API's own host). `null`: no key anywhere.
  static Uri? socketUri({
    required String apiBaseUrl,
    required String appKey,
    required RealtimeConfig? config,
    String override = '',
    String clientName = 'rizqim-dart',
  }) {
    if (appKey.isNotEmpty) {
      return realtimeUri(
        apiBaseUrl: apiBaseUrl,
        appKey: appKey,
        override: override,
        clientName: clientName,
      );
    }
    final ReverbEndpoint? r = config?.reverb;
    if (r == null || r.key.isEmpty) return null;
    String base = override;
    if (base.isEmpty && r.host != null && r.host!.isNotEmpty) {
      final String scheme = switch (r.scheme) {
        'https' || 'wss' => 'wss',
        'http' || 'ws' => 'ws',
        _ => Uri.parse(apiBaseUrl).scheme == 'https' ? 'wss' : 'ws',
      };
      base = Uri(scheme: scheme, host: r.host, port: r.port).toString();
    }
    return realtimeUri(
      apiBaseUrl: apiBaseUrl,
      appKey: r.key,
      override: base,
      clientName: clientName,
    );
  }
}

/// The `reverb` part of [RealtimeConfig].
class ReverbEndpoint {
  const ReverbEndpoint({required this.key, this.host, this.port, this.scheme});

  factory ReverbEndpoint.fromJson(Map<String, Object?> j) => ReverbEndpoint(
    key: j['key'] is String ? j['key']! as String : '',
    host: j['host'] is String ? j['host']! as String : null,
    port: j['port'] is num ? (j['port']! as num).toInt() : null,
    scheme: j['scheme'] is String ? j['scheme']! as String : null,
  );

  final String key;
  final String? host;
  final int? port;
  final String? scheme;
}
