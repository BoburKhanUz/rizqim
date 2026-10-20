// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/get_v1_health_response.dart';

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
}
