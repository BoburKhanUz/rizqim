// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/delete_v1_owner_stop_list_id_response.dart';
import '../models/get_v1_owner_stop_list_response.dart';
import '../models/post_v1_owner_stop_list_response.dart';
import '../models/v1_owner_stop_list_request_body.dart';

part 'owner_stop_list_api.g.dart';

@RestApi()
abstract class OwnerStopListApi {
  factory OwnerStopListApi(Dio dio, {String? baseUrl}) = _OwnerStopListApi;

  /// A branch's stop-list.
  ///
  /// The entries in force (expired ones are not listed). Branch-bound staff always get their own branch. P2-30:.
  /// `created_by_name` (who put it there) for staff with stoplist.manage; null for a menu.view reader.
  ///
  /// [branchId] - Required unless the caller is bound to a branch.
  @GET('/v1/owner/stop-list')
  Future<GetV1OwnerStopListResponse> stopListIndex({
    @Query('branch_id') int? branchId,
  });

  /// Put an item on the stop-list.
  ///
  /// `type`: product, variant or modifier of the store; `until` (optional, ISO time in the future) ends it by.
  /// itself. Adding an item already there updates its reason and time. Broadcasts menu.availability_changed.
  @POST('/v1/owner/stop-list')
  Future<PostV1OwnerStopListResponse> stopListStore({
    @Body() required V1OwnerStopListRequestBody body,
  });

  /// Take an item off the stop-list.
  ///
  /// Broadcasts menu.availability_changed.
  @DELETE('/v1/owner/stop-list/{id}')
  Future<DeleteV1OwnerStopListIdResponse> stopListDestroy({
    @Path('id') required int id,
  });
}
