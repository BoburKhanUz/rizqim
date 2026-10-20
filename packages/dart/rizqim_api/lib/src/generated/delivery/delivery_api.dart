// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/get_v1_delivery_quote_response.dart';
import '../models/get_v1_stores_store_zones_response.dart';

part 'delivery_api.g.dart';

@RestApi()
abstract class DeliveryApi {
  factory DeliveryApi(Dio dio, {String? baseUrl}) = _DeliveryApi;

  /// Delivery quote for a point.
  ///
  /// Which zone and branch would serve `lat`/`lng`, what delivery costs (the free-delivery rule applied when `subtotal`.
  /// is given) and the minimum order. 422 with `data.reason` when the point cannot be served:.
  /// `outside_zone` (hududdan tashqarida), `zones_not_configured` (the store has not drawn zones) or `branch_closed`.
  /// Money is a string with two decimals.
  ///
  /// [store] - Do'kon uuid yoki id.
  ///
  /// [lat] - Latitude of the delivery point.
  ///
  /// [lng] - Longitude of the delivery point.
  ///
  /// [subtotal] - Cart total before delivery; enables the free-delivery and minimum-order checks.
  @GET('/v1/delivery/quote')
  Future<GetV1DeliveryQuoteResponse> deliveryZoneQuote({
    @Query('store') required String store,
    @Query('lat') required num lat,
    @Query('lng') required num lng,
    @Query('subtotal') String? subtotal,
  });

  /// Delivery zones of a store.
  ///
  /// Active zones, highest priority first, without internal fields. `{store}` is the store's uuid (or numeric id).
  /// `polygon` is a GeoJSON Polygon / MultiPolygon geometry with [lng, lat] positions. Money is a string with two decimals.
  @GET('/v1/stores/{store}/zones')
  Future<GetV1StoresStoreZonesResponse> deliveryZoneIndex({
    @Path('store') required String store,
  });
}
