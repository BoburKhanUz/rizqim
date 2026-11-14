// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/delete_v1_owner_ingredients_id_response.dart';
import '../models/delete_v1_owner_ingredients_id_stop_response.dart';
import '../models/get_v1_owner_ingredients_response.dart';
import '../models/patch_v1_owner_ingredients_id_response.dart';
import '../models/post_v1_owner_ingredients_id_link_categories_response.dart';
import '../models/post_v1_owner_ingredients_id_stop_response.dart';
import '../models/post_v1_owner_ingredients_response.dart';
import '../models/put_v1_owner_ingredients_id_products_response.dart';
import '../models/v1_owner_ingredients_id_link_categories_request_body.dart';
import '../models/v1_owner_ingredients_id_products_request_body.dart';
import '../models/v1_owner_ingredients_id_request_body.dart';
import '../models/v1_owner_ingredients_id_stop_request_body.dart';
import '../models/v1_owner_ingredients_request_body.dart';

part 'owner_ingredients_api.g.dart';

@RestApi()
abstract class OwnerIngredientsApi {
  factory OwnerIngredientsApi(Dio dio, {String? baseUrl}) =
      _OwnerIngredientsApi;

  /// The store's ingredients.
  ///
  /// Each: `id`, `uuid`, `name`, `unit`, `links` ({product_id, name, is_critical}), `stopped` (where it is stopped.
  /// now: {branch_id, entry_id, reason, until}) and `stopped_here` (on `branch_id`, when given).
  ///
  /// [branchId] - The branch `stopped_here` is about (a branch-bound caller: their own).
  @GET('/v1/owner/ingredients')
  Future<GetV1OwnerIngredientsResponse> ingredientIndex({
    @Query('branch_id') int? branchId,
  });

  /// Create an ingredient.
  ///
  /// `name` unique in the store (case-insensitive); `unit` optional (kg, dona…), shown only.
  @POST('/v1/owner/ingredients')
  Future<PostV1OwnerIngredientsResponse> ingredientStore({
    @Body() required V1OwnerIngredientsRequestBody body,
  });

  /// Delete an ingredient.
  ///
  /// Its links go, and where it was stopped the dishes reopen.
  @DELETE('/v1/owner/ingredients/{id}')
  Future<DeleteV1OwnerIngredientsIdResponse> ingredientDestroy({
    @Path('id') required int id,
  });

  /// Edit an ingredient
  @PATCH('/v1/owner/ingredients/{id}')
  Future<PatchV1OwnerIngredientsIdResponse> ingredientUpdate({
    @Path('id') required int id,
    @Body() V1OwnerIngredientsIdRequestBody? body,
  });

  /// Link whole categories to an ingredient.
  ///
  /// Every dish of the categories (and their subcategories) is linked, keeping the other links; `added`: how many.
  @POST('/v1/owner/ingredients/{id}/link-categories')
  Future<PostV1OwnerIngredientsIdLinkCategoriesResponse>
  ingredientLinkCategories({
    @Path('id') required int id,
    @Body() required V1OwnerIngredientsIdLinkCategoriesRequestBody body,
  });

  /// Replace the dishes that use an ingredient.
  ///
  /// `links`: [{product_id, is_critical}] (up to 1 000; `is_critical` default true: stopping the ingredient closes.
  /// the dish; false: the dish stays on sale, marked in `limited_products` of the availability).
  @PUT('/v1/owner/ingredients/{id}/products')
  Future<PutV1OwnerIngredientsIdProductsResponse> ingredientLinks({
    @Path('id') required int id,
    @Body() required V1OwnerIngredientsIdProductsRequestBody body,
  });

  /// Stop an ingredient on a branch ("Tovuq tugadi").
  ///
  /// Closes every critically linked dish there at once (all brands, all channels); `until` (optional, a future ISO.
  /// time) reopens by itself; stopping it again updates the reason and time. `closed_product_ids`: the dishes it.
  /// closes. Broadcasts menu.availability_changed. Needs the ingredient_stoplist module on the branch. A till sends an.
  /// Idempotency-Key (optional): a retry with the same key gets the first answer.
  ///
  /// [idempotencyKey] - Optional: a new UUID per action, the same one when retrying it; a repeat returns the first answer.
  @POST('/v1/owner/ingredients/{id}/stop')
  Future<PostV1OwnerIngredientsIdStopResponse> ingredientStop({
    @Path('id') required int id,
    @Header('Idempotency-Key') String? idempotencyKey,
    @Body() V1OwnerIngredientsIdStopRequestBody? body,
  });

  /// Reopen an ingredient on a branch.
  ///
  /// Its dishes come back (a dish stopped by its own entry stays stopped). 404 when it is not stopped there. An.
  /// Idempotency-Key is optional, as for the stop.
  ///
  /// [branchId] - The branch (a branch-bound caller: their own).
  ///
  /// [idempotencyKey] - Optional: a new UUID per action, the same one when retrying it; a repeat returns the first answer.
  @DELETE('/v1/owner/ingredients/{id}/stop')
  Future<DeleteV1OwnerIngredientsIdStopResponse> ingredientReopen({
    @Path('id') required int id,
    @Query('branch_id') int? branchId,
    @Header('Idempotency-Key') String? idempotencyKey,
  });
}
