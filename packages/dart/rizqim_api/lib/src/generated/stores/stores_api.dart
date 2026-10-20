// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/get_v1_stores_response.dart';
import '../models/post_v1_stores_store_id_rate_response.dart';
import '../models/v1_stores_store_id_rate_request_body.dart';

part 'stores_api.g.dart';

@RestApi()
abstract class StoresApi {
  factory StoresApi(Dio dio, {String? baseUrl}) = _StoresApi;

  /// Stores near the customer.
  ///
  /// Active stores with a branch that delivers to the point (nearest first, `min_distance` in km), or with a branch.
  /// in the region/district. Without parameters the user's default address is used. Cash is shown as disabled when.
  /// the customer may not pay cash.
  ///
  /// [lat] - Latitude of the delivery point (with `lng`).
  ///
  /// [lng] - Longitude of the delivery point (with `lat`).
  ///
  /// [regionId] - Without coordinates: stores with a branch in this region.
  ///
  /// [districtId] - Without coordinates: stores with a branch in this district.
  @GET('/v1/stores')
  Future<GetV1StoresResponse> storeIndex({
    @Query('lat') num? lat,
    @Query('lng') num? lng,
    @Query('region_id') int? regionId,
    @Query('district_id') int? districtId,
  });

  /// Rate a store.
  ///
  /// One rating per order (400 when this order was already rated); returns the store's new average and count.
  @POST('/v1/stores/{storeId}/rate')
  Future<PostV1StoresStoreIdRateResponse> storeRatingRate({
    @Path('storeId') required int storeId,
    @Body() required V1StoresStoreIdRateRequestBody body,
  });
}
