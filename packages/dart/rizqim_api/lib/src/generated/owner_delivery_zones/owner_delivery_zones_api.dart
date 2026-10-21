// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/delete_v1_owner_delivery_zones_id_response.dart';
import '../models/get_v1_owner_delivery_zones_id_response.dart';
import '../models/get_v1_owner_delivery_zones_response.dart';
import '../models/patch_v1_owner_delivery_zones_id_response.dart';
import '../models/post_v1_owner_delivery_zones_response.dart';
import '../models/v1_owner_delivery_zones_id_request_body.dart';
import '../models/v1_owner_delivery_zones_request_body.dart';

part 'owner_delivery_zones_api.g.dart';

@RestApi()
abstract class OwnerDeliveryZonesApi {
  factory OwnerDeliveryZonesApi(Dio dio, {String? baseUrl}) =
      _OwnerDeliveryZonesApi;

  /// The store's delivery zones.
  ///
  /// Every zone of the store, inactive ones included, in the order ZoneResolver tries them (priority, then the.
  /// smaller area, then the older). A short list: not paginated. Money is a string with two decimals.
  ///
  /// [active] - Faqat faol (1) yoki faol bo'lmagan (0) hududlar.
  @GET('/v1/owner/delivery-zones')
  Future<GetV1OwnerDeliveryZonesResponse> deliveryZoneManageIndex({
    @Query('active') bool? active,
  });

  /// Create a delivery zone.
  ///
  /// `polygon`: a GeoJSON Polygon or MultiPolygon (also a Feature or a FeatureCollection of one feature, as an object.
  /// or a JSON string), positions [lng, lat]; at most 20 polygons and 500 points; closed rings, no zero area. It is.
  /// stored cleaned (6 decimals, repeated points dropped). `branch_id`: an active branch of the store that alone.
  /// takes this zone's orders; empty = the nearest open branch. Money in UZS, up to two decimals.
  @POST('/v1/owner/delivery-zones')
  Future<PostV1OwnerDeliveryZonesResponse> deliveryZoneManageStore({
    @Body() required V1OwnerDeliveryZonesRequestBody body,
  });

  /// One delivery zone
  @GET('/v1/owner/delivery-zones/{id}')
  Future<GetV1OwnerDeliveryZonesIdResponse> deliveryZoneManageShow({
    @Path('id') required int id,
  });

  /// Delete a delivery zone.
  ///
  /// Past orders keep their copy of the zone (only their link to it is cleared). Without any active zone left the.
  /// store goes back to branch radius delivery (docs/ZONES.md).
  @DELETE('/v1/owner/delivery-zones/{id}')
  Future<DeleteV1OwnerDeliveryZonesIdResponse> deliveryZoneManageDestroy({
    @Path('id') required int id,
  });

  /// Update a delivery zone.
  ///
  /// Any subset of the create fields. Orders already placed keep the price and zone name they were placed with.
  @PATCH('/v1/owner/delivery-zones/{id}')
  Future<PatchV1OwnerDeliveryZonesIdResponse> deliveryZoneManageUpdate({
    @Path('id') required int id,
    @Body() V1OwnerDeliveryZonesIdRequestBody? body,
  });
}
