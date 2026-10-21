// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/delete_v1_owner_dining_tables_id_response.dart';
import '../models/delete_v1_owner_halls_id_response.dart';
import '../models/get_v1_owner_dining_tables_response.dart';
import '../models/get_v1_owner_halls_response.dart';
import '../models/patch_v1_owner_dining_tables_id_response.dart';
import '../models/patch_v1_owner_halls_id_response.dart';
import '../models/post_v1_owner_dining_tables_response.dart';
import '../models/post_v1_owner_halls_response.dart';
import '../models/put_v1_owner_dining_tables_layout_response.dart';
import '../models/v1_owner_dining_tables_id_request_body.dart';
import '../models/v1_owner_dining_tables_layout_request_body.dart';
import '../models/v1_owner_dining_tables_request_body.dart';
import '../models/v1_owner_halls_id_request_body.dart';
import '../models/v1_owner_halls_request_body.dart';

part 'owner_halls_and_tables_api.g.dart';

@RestApi()
abstract class OwnerHallsAndTablesApi {
  factory OwnerHallsAndTablesApi(Dio dio, {String? baseUrl}) =
      _OwnerHallsAndTablesApi;

  /// Tables, rooms and tapchans.
  ///
  /// Of a branch (`branch_id`; without it every branch the caller works in), optionally of one hall, in floor order.
  ///
  /// [branchId] - One branch; omit for every branch the caller works in.
  ///
  /// [hallId] - One hall.
  @GET('/v1/owner/dining-tables')
  Future<GetV1OwnerDiningTablesResponse> diningTableIndex({
    @Query('branch_id') int? branchId,
    @Query('hall_id') int? hallId,
  });

  /// Add a table, room or tapchan.
  ///
  /// In a hall (its branch is the hall's). `kind`: table, room or tapchan. `min_spend` (optional): the least the party.
  /// spends on food, per room (`per_table`) or per guest (`per_person`); the difference is a separate bill line.
  @POST('/v1/owner/dining-tables')
  Future<PostV1OwnerDiningTablesResponse> diningTableStore({
    @Body() required V1OwnerDiningTablesRequestBody body,
  });

  /// Save the floor map.
  ///
  /// The places of one branch at once (the map editor's "Save"): each `id` with its `layout` {x, y, w, h}, and.
  /// optionally `hall_id` (a hall of the same branch) and `sort_order`. All or nothing: an id of another branch is a.
  /// 422 and nothing is saved.
  @PUT('/v1/owner/dining-tables/layout')
  Future<PutV1OwnerDiningTablesLayoutResponse> diningTableLayout({
    @Body() required V1OwnerDiningTablesLayoutRequestBody body,
  });

  /// Delete a table, room or tapchan.
  ///
  /// 422 `table_in_use` while an order is open at it. Past orders keep its name.
  @DELETE('/v1/owner/dining-tables/{id}')
  Future<DeleteV1OwnerDiningTablesIdResponse> diningTableDestroy({
    @Path('id') required String id,
  });

  /// Edit a table, room or tapchan.
  ///
  /// Any field; `hall_id` moves it to another hall of the same branch.
  @PATCH('/v1/owner/dining-tables/{id}')
  Future<PatchV1OwnerDiningTablesIdResponse> diningTableUpdate({
    @Path('id') required String id,
    @Body() V1OwnerDiningTablesIdRequestBody? body,
  });

  /// Halls with their tables.
  ///
  /// The halls of a branch (`branch_id`; without it every branch the caller works in) in floor order, each with its.
  /// own service charge override (null: the branch's applies) and its tables, rooms and tapchans.
  ///
  /// [branchId] - One branch; omit for every branch the caller works in.
  @GET('/v1/owner/halls')
  Future<GetV1OwnerHallsResponse> hallIndex({
    @Query('branch_id') int? branchId,
  });

  /// Add a hall
  @POST('/v1/owner/halls')
  Future<PostV1OwnerHallsResponse> hallStore({
    @Body() required V1OwnerHallsRequestBody body,
  });

  /// Delete a hall.
  ///
  /// With its places. 422 `hall_in_use` while an order is open at one of them. Past orders keep the names.
  @DELETE('/v1/owner/halls/{id}')
  Future<DeleteV1OwnerHallsIdResponse> hallDestroy({
    @Path('id') required String id,
  });

  /// Edit a hall.
  ///
  /// Name, order, and `is_active` (a seasonal zone closed: its places leave the floor map; open orders stay).
  @PATCH('/v1/owner/halls/{id}')
  Future<PatchV1OwnerHallsIdResponse> hallUpdate({
    @Path('id') required String id,
    @Body() V1OwnerHallsIdRequestBody? body,
  });
}
