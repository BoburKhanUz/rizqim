// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/get_v1_health_response.dart';
import '../models/get_v1_realtime_response.dart';

part 'health_api.g.dart';

@RestApi()
abstract class HealthApi {
  factory HealthApi(Dio dio, {String? baseUrl}) = _HealthApi;

  /// Health check.
  ///
  /// 200 when everything works (`ok`) or works with warnings (`degraded`), 503 when a component fails. Each check.
  /// carries only its `status`, unless the request comes from the internal network or sends `X-Health-Token`.
  @GET('/v1/health')
  Future<GetV1HealthResponse> healthApi();

  /// Real-time status and connection settings.
  ///
  /// Public, no database. `live` false (Reverb off, the heartbeat stale or the broadcasts queue backed up): clients.
  /// poll at `poll_seconds.fallback`; live: they listen and poll at `poll_seconds.live` as a safety net, and treat.
  /// `silence_seconds` without any event or `realtime.heartbeat` on a channel as "not live". `reverb` is what a client.
  /// connects with (the public app key; null host = the API's own host); null when real-time is off. Read it on start.
  /// and on every reconnect, not on every poll. Kept for 5 seconds.
  @GET('/v1/realtime')
  Future<GetV1RealtimeResponse> realtimeStatus();
}
