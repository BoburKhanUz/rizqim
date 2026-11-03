// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/delete_v1_owner_modifier_groups_id_response.dart';
import '../models/get_v1_owner_modifier_groups_response.dart';
import '../models/modifier_group_request.dart';
import '../models/post_v1_owner_modifier_groups_response.dart';
import '../models/put_v1_owner_modifier_groups_id_response.dart';
import '../models/put_v1_owner_products_id_modifier_groups_response.dart';
import '../models/v1_owner_products_id_modifier_groups_request_body.dart';

part 'owner_menu_modifiers_api.g.dart';

@RestApi()
abstract class OwnerMenuModifiersApi {
  factory OwnerMenuModifiersApi(Dio dio, {String? baseUrl}) =
      _OwnerMenuModifiersApi;

  /// List the store's modifier groups.
  ///
  /// Every group with its modifiers (inactive ones included) and the ids of the products that offer it.
  @GET('/v1/owner/modifier-groups')
  Future<GetV1OwnerModifierGroupsResponse> modifierGroupIndex();

  /// Create a modifier group with its modifiers.
  ///
  /// `max_select` null means any number; `is_required` raises the minimum to 1. `price_delta` is added to the item's.
  /// price (per piece; once per line for items sold by weight). `product_variant_id` optionally links a modifier to a.
  /// variant of the store (for stock later). P6-32: `display_mode` (a ModifierDisplayMode value, default `dialog`) and.
  /// a modifier's `is_default` are hints for the till (the `quick_modifiers` module): `size` needs `max_select` 1, at.
  /// most `max_select` active modifiers may be defaults; setting a hint on a store whose branches all lack the module.
  /// is 403 `module_disabled`.
  @POST('/v1/owner/modifier-groups')
  Future<PostV1OwnerModifierGroupsResponse> modifierGroupStore({
    @Body() required ModifierGroupRequest body,
  });

  /// Update a modifier group.
  ///
  /// `modifiers` replaces the list: an entry with `id` updates that modifier, one without creates it, and a modifier.
  /// left out is deleted (order history keeps its snapshot). P6-32: `display_mode` and `is_default` left out keep what.
  /// is saved.
  @PUT('/v1/owner/modifier-groups/{id}')
  Future<PutV1OwnerModifierGroupsIdResponse> modifierGroupUpdate({
    @Path('id') required int id,
    @Body() required ModifierGroupRequest body,
  });

  /// Delete a modifier group.
  ///
  /// Products stop offering it; past orders keep their snapshot.
  @DELETE('/v1/owner/modifier-groups/{id}')
  Future<DeleteV1OwnerModifierGroupsIdResponse> modifierGroupDestroy({
    @Path('id') required int id,
  });

  /// Set the modifier groups a product offers.
  ///
  /// Replaces the product's groups. Per product, `min_select`, `max_select` and `is_required` override the group's own.
  /// rules (null: the group's; P2-30: a rule left out of a row keeps the product's saved one); `sort_order` orders.
  /// them (left out: the list order). GET /owner/products returns them as `modifier_group_links`.
  @PUT('/v1/owner/products/{id}/modifier-groups')
  Future<PutV1OwnerProductsIdModifierGroupsResponse> modifierGroupSyncProduct({
    @Path('id') required int id,
    @Body() required V1OwnerProductsIdModifierGroupsRequestBody body,
  });
}
