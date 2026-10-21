// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/delete_v1_owner_auto_guest_items_id_response.dart';
import '../models/get_v1_owner_auto_guest_items_response.dart';
import '../models/patch_v1_owner_auto_guest_items_id_response.dart';
import '../models/post_v1_owner_auto_guest_items_response.dart';
import '../models/v1_owner_auto_guest_items_id_request_body.dart';
import '../models/v1_owner_auto_guest_items_request_body.dart';

part 'owner_auto_guest_items_api.g.dart';

@RestApi()
abstract class OwnerAutoGuestItemsApi {
  factory OwnerAutoGuestItemsApi(Dio dio, {String? baseUrl}) =
      _OwnerAutoGuestItemsApi;

  /// Automatic per-guest items.
  ///
  /// A hall order (pos_hall) gets `quantity_per_guest` of each item per guest when it opens and when guests join.
  /// (pieces rounded up: 0.5 choynak per guest is 2 for 3 guests). A hall's own rule replaces the branch rule of the.
  /// same item in that hall; an inactive hall rule switches it off there.
  ///
  /// [branchId] - The branch; defaults to the caller's.
  @GET('/v1/owner/auto-guest-items')
  Future<GetV1OwnerAutoGuestItemsResponse> autoGuestItemIndex({
    @Query('branch_id') int? branchId,
  });

  /// Add an automatic per-guest item.
  ///
  /// For the branch, or one hall of it (`hall_id`). `product_variant_id`: a variant of the store's menu.
  @POST('/v1/owner/auto-guest-items')
  Future<PostV1OwnerAutoGuestItemsResponse> autoGuestItemStore({
    @Body() required V1OwnerAutoGuestItemsRequestBody body,
  });

  /// Delete an automatic per-guest item
  @DELETE('/v1/owner/auto-guest-items/{id}')
  Future<DeleteV1OwnerAutoGuestItemsIdResponse> autoGuestItemDestroy({
    @Path('id') required String id,
  });

  /// Edit an automatic per-guest item
  @PATCH('/v1/owner/auto-guest-items/{id}')
  Future<PatchV1OwnerAutoGuestItemsIdResponse> autoGuestItemUpdate({
    @Path('id') required String id,
    @Body() V1OwnerAutoGuestItemsIdRequestBody? body,
  });
}
