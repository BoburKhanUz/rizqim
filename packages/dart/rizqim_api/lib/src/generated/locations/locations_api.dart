// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/get_v1_locations_response.dart';

part 'locations_api.g.dart';

@RestApi()
abstract class LocationsApi {
  factory LocationsApi(Dio dio, {String? baseUrl}) = _LocationsApi;

  /// Regions and districts
  @GET('/v1/locations')
  Future<GetV1LocationsResponse> locationIndex();
}
