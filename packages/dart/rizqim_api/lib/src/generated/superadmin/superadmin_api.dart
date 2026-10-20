// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/get_v1_superadmin_dashboard_response.dart';
import '../models/get_v1_superadmin_locations_response.dart';
import '../models/get_v1_superadmin_stores_response.dart';
import '../models/post_v1_superadmin_stores_response.dart';
import '../models/v1_superadmin_stores_request_body.dart';

part 'superadmin_api.g.dart';

@RestApi()
abstract class SuperadminApi {
  factory SuperadminApi(Dio dio, {String? baseUrl}) = _SuperadminApi;

  /// Get global dashboard stats for Super Admin
  @GET('/v1/superadmin/dashboard')
  Future<GetV1SuperadminDashboardResponse> superadminDashboard();

  /// Get regions and districts for forms
  @GET('/v1/superadmin/locations')
  Future<GetV1SuperadminLocationsResponse> superadminGetLocations();

  /// Get all global stores
  @GET('/v1/superadmin/stores')
  Future<GetV1SuperadminStoresResponse> superadminStores();

  /// Create a new Global Store
  @POST('/v1/superadmin/stores')
  Future<PostV1SuperadminStoresResponse> superadminCreateStore({
    @Body() required V1SuperadminStoresRequestBody body,
  });
}
