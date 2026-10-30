// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/delete_v1_owner_kitchen_station_routes_id_response.dart';
import '../models/delete_v1_owner_kitchen_stations_id_response.dart';
import '../models/get_v1_owner_kds_targets_response.dart';
import '../models/get_v1_owner_kitchen_station_routes_response.dart';
import '../models/get_v1_owner_kitchen_stations_response.dart';
import '../models/patch_v1_owner_kitchen_stations_id_response.dart';
import '../models/post_v1_owner_kitchen_stations_response.dart';
import '../models/put_v1_owner_kds_targets_products_response.dart';
import '../models/put_v1_owner_kitchen_station_routes_response.dart';
import '../models/v1_owner_kds_targets_products_request_body.dart';
import '../models/v1_owner_kitchen_station_routes_request_body.dart';
import '../models/v1_owner_kitchen_stations_id_request_body.dart';
import '../models/v1_owner_kitchen_stations_request_body.dart';

part 'owner_kitchen_stations_api.g.dart';

@RestApi()
abstract class OwnerKitchenStationsApi {
  factory OwnerKitchenStationsApi(Dio dio, {String? baseUrl}) =
      _OwnerKitchenStationsApi;

  /// Time targets of a branch's kitchen.
  ///
  /// The branch's stations with their target (`target_seconds`, null: none) and the kind's suggested one.
  /// (`default_target_seconds`), the store's dishes that have their own target, and whether the branch has the.
  /// module (`enabled`: setting a target needs it). Seconds; `limits` are the allowed range.
  ///
  /// [branchId] - The branch (a branch-bound caller: their own).
  @GET('/v1/owner/kds/targets')
  Future<GetV1OwnerKdsTargetsResponse> kdsTargetsShow({
    @Query('branch_id') int? branchId,
  });

  /// Set dishes' own time targets.
  ///
  /// `items`: up to 200 `{product_id, target_seconds}` of the store's dishes; `target_seconds` 10–7200 seconds, or null.
  /// to drop the dish's own target (its station's applies again). A target is store-wide (every branch). Setting one.
  /// needs the module `kds_expo` on a branch of the store (403 module_disabled); clearing never does. Audited.
  ///
  /// [branchId] - The branch whose page to answer with (a branch-bound caller: their own).
  @PUT('/v1/owner/kds/targets/products')
  Future<PutV1OwnerKdsTargetsProductsResponse> kdsTargetsUpdateProducts({
    @Body() required V1OwnerKdsTargetsProductsRequestBody body,
    @Query('branch_id') int? branchId,
  });

  /// Routes of a branch.
  ///
  /// Every route of the branch: `category_id` or `product_id` is set, with the dish's name for the screen.
  ///
  /// [branchId] - The branch (a branch-bound caller: their own).
  @GET('/v1/owner/kitchen-station-routes')
  Future<GetV1OwnerKitchenStationRoutesResponse> kitchenStationRouteIndex({
    @Query('branch_id') int? branchId,
  });

  /// Assign dishes to a station (or free them).
  ///
  /// For `branch_id`: every category in `category_ids` and every product in `product_ids` goes to `station_id`; one.
  /// that was routed to another station moves. `station_id: null` removes the routes of those categories and.
  /// products. Send at least one list. Products must be the store's; the station must be the branch's (active or.
  /// not). `data` is the branch's routes afterwards. P6-33: an `expo` (assembly) station takes no dishes (422).
  @PUT('/v1/owner/kitchen-station-routes')
  Future<PutV1OwnerKitchenStationRoutesResponse> kitchenStationRouteAssign({
    @Body() required V1OwnerKitchenStationRoutesRequestBody body,
  });

  /// Remove one route.
  ///
  /// The dish goes back to the route of its category, or to the default ticket printer without one.
  @DELETE('/v1/owner/kitchen-station-routes/{id}')
  Future<DeleteV1OwnerKitchenStationRoutesIdResponse>
  kitchenStationRouteDestroy({@Path('id') required int id});

  /// The branch's kitchen stations.
  ///
  /// By `branch_id` (a branch-bound caller: their own); without it, every branch of the store. In the order the.
  /// screens show them (`sort_order`, then oldest first). Inactive stations are listed too.
  ///
  /// [branchId] - Only this branch.
  @GET('/v1/owner/kitchen-stations')
  Future<GetV1OwnerKitchenStationsResponse> kitchenStationIndex({
    @Query('branch_id') int? branchId,
  });

  /// Add a kitchen station.
  ///
  /// `kind`: osh, mangal, salad_bar, tea, kitchen (default) or other; P6-33: grill, fryer, drinks, barista, or expo.
  /// (the assembly screen: it gets no dishes). `name` is unique within the branch. `show_on_kds`: the station has a.
  /// KDS screen. The station gets dishes through the routes (PUT /owner/kitchen-station-routes). P6-33:.
  /// `target_seconds` (10–7200, null: none) is the time target of its lines (an expo station: the assembly time);.
  /// setting one needs the module `kds_expo` on the branch (403 module_disabled), clearing never does.
  @POST('/v1/owner/kitchen-stations')
  Future<PostV1OwnerKitchenStationsResponse> kitchenStationStore({
    @Body() required V1OwnerKitchenStationsRequestBody body,
  });

  /// Delete a kitchen station.
  ///
  /// Its routes are removed and its printers keep working without a station. A station that order lines were.
  /// already sent to is kept for the history: 422 `station_in_use`, deactivate it instead.
  @DELETE('/v1/owner/kitchen-stations/{id}')
  Future<DeleteV1OwnerKitchenStationsIdResponse> kitchenStationDestroy({
    @Path('id') required int id,
  });

  /// Edit a kitchen station.
  ///
  /// Any subset of `name`, `kind`, `sort_order`, `is_active`, `show_on_kds`, `target_seconds` (P6-33). A deactivated.
  /// station is not offered to terminals and its routes are ignored: its dishes go to the default ticket printer. A.
  /// station with dishes routed to it cannot become `expo`: 422 `station_has_routes`.
  @PATCH('/v1/owner/kitchen-stations/{id}')
  Future<PatchV1OwnerKitchenStationsIdResponse> kitchenStationUpdate({
    @Path('id') required int id,
    @Body() V1OwnerKitchenStationsIdRequestBody? body,
  });
}
