// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/delete_v1_owner_brands_id_response.dart';
import '../models/get_v1_owner_brands_response.dart';
import '../models/patch_v1_owner_brands_id_response.dart';
import '../models/post_v1_owner_brands_response.dart';
import '../models/put_v1_owner_brands_id_branches_response.dart';
import '../models/put_v1_owner_brands_id_channels_response.dart';
import '../models/put_v1_owner_product_brands_response.dart';
import '../models/v1_owner_brands_id_branches_request_body.dart';
import '../models/v1_owner_brands_id_channels_request_body.dart';
import '../models/v1_owner_brands_id_request_body.dart';
import '../models/v1_owner_brands_request_body.dart';
import '../models/v1_owner_product_brands_request_body.dart';

part 'owner_brands_api.g.dart';

@RestApi()
abstract class OwnerBrandsApi {
  factory OwnerBrandsApi(Dio dio, {String? baseUrl}) = _OwnerBrandsApi;

  /// The store's brands.
  ///
  /// Each brand: `id`, `uuid`, `name`, `short_code` (on the kitchen ticket and the label), `color` (#RRGGBB),.
  /// `logo_url`, `is_active`, `sort_order`, `products_count`, `channels` ({channel, is_hidden, price_percent}; a.
  /// channel without a row: visible at the dishes' own prices) and `off_branch_ids` (branches it is switched off on).
  /// `channels_available`: the channels a rule may name.
  @GET('/v1/owner/brands')
  Future<GetV1OwnerBrandsResponse> brandIndex();

  /// Create a brand.
  ///
  /// `short_code`: 1–8 letters or digits, unique in the store (upper-cased). `color`: #RRGGBB.
  @POST('/v1/owner/brands')
  Future<PostV1OwnerBrandsResponse> brandStore({
    @Body() required V1OwnerBrandsRequestBody body,
  });

  /// Delete a brand.
  ///
  /// Its dishes go back to the house brand; sold lines keep the brand they were sold under.
  @DELETE('/v1/owner/brands/{id}')
  Future<DeleteV1OwnerBrandsIdResponse> brandDestroy({
    @Path('id') required int id,
  });

  /// Edit a brand.
  ///
  /// Any of name, short_code, color, logo_url, is_active (an inactive brand's dishes are hidden on every channel),.
  /// sort_order.
  @PATCH('/v1/owner/brands/{id}')
  Future<PatchV1OwnerBrandsIdResponse> brandUpdate({
    @Path('id') required int id,
    @Body() V1OwnerBrandsIdRequestBody? body,
  });

  /// Switch a brand on or off on branches.
  ///
  /// `branches`: [{branch_id, is_active}] — off: its dishes are stopped on that branch (like the stop-list, every.
  /// channel); a branch left out keeps its state.
  @PUT('/v1/owner/brands/{id}/branches')
  Future<PutV1OwnerBrandsIdBranchesResponse> brandBranches({
    @Path('id') required int id,
    @Body() required V1OwnerBrandsIdBranchesRequestBody body,
  });

  /// Replace a brand's channel rules.
  ///
  /// `channels`: [{channel, is_hidden, price_percent}] — `is_hidden`: the brand is not sold on that channel;.
  /// `price_percent` (−90…+300, two decimals): its dishes cost that much more (or less) there, half up to whole so'm,.
  /// unless a variant has its own channel price (that one wins). A channel left out: visible at the own prices.
  @PUT('/v1/owner/brands/{id}/channels')
  Future<PutV1OwnerBrandsIdChannelsResponse> brandChannels({
    @Path('id') required int id,
    @Body() required V1OwnerBrandsIdChannelsRequestBody body,
  });

  /// Put dishes under a brand.
  ///
  /// `brand_id`: the brand (null: the house brand); `product_ids`: the store's dishes (up to 500). Returns how many.
  /// changed.
  @PUT('/v1/owner/product-brands')
  Future<PutV1OwnerProductBrandsResponse> brandAssignProducts({
    @Body() required V1OwnerProductBrandsRequestBody body,
  });
}
