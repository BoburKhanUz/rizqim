// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'dart:convert';
import 'dart:io';

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/delete_v1_owner_products_id_image_response.dart';
import '../models/delete_v1_owner_products_id_response.dart';
import '../models/get_v1_owner_categories_response.dart';
import '../models/get_v1_owner_products_response.dart';
import '../models/post_v1_owner_categories_response.dart';
import '../models/post_v1_owner_products_response.dart';
import '../models/v1_owner_categories_request_body.dart';

part 'owner_catalog_api.g.dart';

@RestApi()
abstract class OwnerCatalogApi {
  factory OwnerCatalogApi(Dio dio, {String? baseUrl}) = _OwnerCatalogApi;

  /// Get global categories for the dropdown.
  ///
  /// menu.view (the route's).
  @GET('/v1/owner/categories')
  Future<GetV1OwnerCategoriesResponse> ownerCatalogGetCategories();

  /// Create a new global category
  @POST('/v1/owner/categories')
  Future<PostV1OwnerCategoriesResponse> ownerCatalogStoreCategory({
    @Body() required V1OwnerCategoriesRequestBody body,
  });

  /// Get all products for the owner's store.
  ///
  /// menu.view. Each product with its category and variants, and `modifier_group_links`: the modifier groups it offers.
  /// in order, with this product's own rules (`min_select`, `max_select`, `is_required`; null: the group's), as.
  /// PUT /owner/products/{id}/modifier-groups saves them.
  @GET('/v1/owner/products')
  Future<GetV1OwnerProductsResponse> ownerCatalogGetProducts();

  /// Create a new product with variants.
  ///
  /// multipart/form-data, menu.manage. `variants` is a JSON array of {name, price, unit?, quantity_step?,.
  /// min_quantity?, batch_consumption?, stock?}; `stock` left out starts the variant at 999.
  ///
  /// [image] - Max 5MB.
  ///
  /// [variants] - JSON array of variants: [{"name": "...", "price": 25000}, ...]; P1-21 adds optional "unit" (piece|kg|l),.
  /// "quantity_step", "min_quantity" (kg/l) and "batch_consumption"; P2-30: optional "stock" (left out: 999).
  ///
  /// [sortOrder] - P2-12: position in the menu, as the panel's product form sets it (left out = 0 / kept).
  @MultiPart()
  @POST('/v1/owner/products')
  Future<PostV1OwnerProductsResponse> ownerCatalogStoreProduct({
    @Part(name: 'name') required String name,
    @Part(name: 'category_id') required int categoryId,
    @Part(name: 'variants') required String variants,
    @Part(name: 'description') String? description,
    @Part(name: 'is_active') bool? isActive,
    @Part(name: 'image') File? image,
    @Part(name: 'batch_tracked') bool? batchTracked,
    @Part(name: 'sort_order') int? sortOrder,
  });

  /// Update an existing product.
  ///
  /// multipart/form-data (POST, so the image can be sent). With `toggle_active` (any value) only the product's.
  /// active status is flipped and the other fields are not needed. menu.manage. A variant's stock is not changed.
  /// here (orders move it); a new variant takes `stock` like on create.
  ///
  /// [toggleActive] - Any value: only switch the product on/off (no other field needed).
  ///
  /// [image] - Maximum file size: 5120 kilobytes.
  ///
  /// [variants] - JSON array of variants: [{"id": 1, "name": "...", "price": 25000}, ...]; one without an id is created,.
  /// an existing one left out is deleted. P1-21: optional "unit", "quantity_step", "min_quantity", "batch_consumption".
  @MultiPart()
  @POST('/v1/owner/products/{id}')
  Future<dynamic> ownerCatalogUpdateProduct({
    @Path('id') required String id,
    @Part(name: 'name') required String name,
    @Part(name: 'category_id') required int categoryId,
    @Part(name: 'variants') required String variants,
    @Query('toggle_active') int? toggleActive,
    @Part(name: 'description') String? description,
    @Part(name: 'is_active') bool? isActive,
    @Part(name: 'image') File? image,
    @Part(name: 'batch_tracked') bool? batchTracked,
    @Part(name: 'sort_order') int? sortOrder,
  });

  /// Delete a product
  @DELETE('/v1/owner/products/{id}')
  Future<DeleteV1OwnerProductsIdResponse> ownerCatalogDeleteProduct({
    @Path('id') required String id,
  });

  /// Remove a product's image.
  ///
  /// menu.manage. The image and its thumbnails are deleted; the product shows no picture until a new one is uploaded.
  /// (POST /owner/products/{id} with `image`). Without an image: 200, nothing changes.
  @DELETE('/v1/owner/products/{id}/image')
  Future<DeleteV1OwnerProductsIdImageResponse> ownerCatalogDeleteImage({
    @Path('id') required String id,
  });
}
