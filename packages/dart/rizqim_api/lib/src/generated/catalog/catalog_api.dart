// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/channel.dart';
import '../models/get_v1_catalog_availability_response.dart';

part 'catalog_api.g.dart';

@RestApi()
abstract class CatalogApi {
  factory CatalogApi(Dio dio, {String? baseUrl}) = _CatalogApi;

  /// Menu: global feed or one store.
  ///
  /// Without `store_id`: the global feed (up to 50 random active products of open stores near the location, the.
  /// categories, the promo banners and a highlighted product). With `store_id`: that store (branches, ratings) with.
  /// its active products and their categories; 404 for an unknown store, 403 when the store is unavailable.
  /// Without a location the user's default address is used.
  ///
  /// P1-21 (docs/MENU.md), additive: variant `price` is the channel's price (`base_price` the variant's own), with.
  /// `unit` (piece|kg|l), `quantity_step` and `min_quantity`; products carry `batch_tracked`, `image_thumb_urls` and.
  /// `modifier_groups` (effective `min_select`/`max_select`/`is_required` for that product, active `modifiers` with.
  /// `price_delta`); hidden products/variants of the channel are left out. Store mode adds `availability`.
  ///
  /// Wave M2: the customer forms are answered from the marketplace read model (App\Domain\Marketplace\MarketplaceBrowse,.
  /// docs/API.md "Market o'qish modeli"; the store carries `accepting_orders`); this method is its fallback and serves.
  /// the POS channels. Orders are always priced and checked against the live tables.
  ///
  /// [storeId] - The store's uuid (or numeric id): store menu instead of the global feed.
  ///
  /// [lat] - Global feed: latitude of the delivery point (with `lng`).
  ///
  /// [lng] - Global feed: longitude of the delivery point (with `lat`).
  ///
  /// [regionId] - Global feed without coordinates: stores with a branch in this region.
  ///
  /// [districtId] - Global feed without coordinates: stores with a branch in this district.
  ///
  /// [branchId] - P3-13, POS channels only: the branch (an owner or a store-wide terminal; a branch-bound staff member always gets their own).
  ///
  /// [categoryId] - Global feed: only this category and its subcategories.
  ///
  /// [brand] - P6-55, store mode: only this brand's dishes (the brand's uuid or id; an unknown or inactive one: 404).
  ///
  /// [view] - Wave M2, global feed: `cards` answers small product cards ({id, uuid, store_id, category_id, name, description, image_url, image_thumb_urls, batch_tracked, price, unit, needs_options, variant, store: {id, uuid, name, address, logo_url, accepting_orders}}) instead of full products; docs/API.md.
  ///
  /// [limit] - Wave M2, global feed with view=cards: how many cards (default 20, at most 50).
  ///
  /// [channel] - P1-21: whose prices and visibility to show: telegram (default) or mobile. P3-13: `pos_hall` or `pos_takeaway` is the POS menu of one branch for the store's own staff (menu.view; `branch_id` for an owner), with the channel prices, `is_available`/`unavailable_reason`, `station_id`, modifiers, units and the branch availability; see docs/API.md (POS bo'limi).
  ///
  /// [ifNoneMatch] - Optional: the ETag of the last 200 answer. While nothing it depends on has changed the answer is 304 with no body: keep the last body and the time it was received.
  @GET('/v1/catalog')
  Future<dynamic> catalogIndex({
    @Query('store_id') String? storeId,
    @Query('lat') num? lat,
    @Query('lng') num? lng,
    @Query('region_id') int? regionId,
    @Query('district_id') int? districtId,
    @Query('branch_id') int? branchId,
    @Query('category_id') int? categoryId,
    @Query('brand') String? brand,
    @Query('view') String? view,
    @Query('limit') int? limit,
    @Query('channel') Channel? channel,
    @Header('If-None-Match') String? ifNoneMatch,
  });

  /// Menu availability per branch.
  ///
  /// What cannot be ordered right now on each active branch of the store (P1-21): `stopped` product/variant/modifier.
  /// ids (stop-list and branch switches, `stop_list` has reasons and `until`), `sold_out.variants` (batch stock:.
  /// "tugadi") and the open `batches` with what is left. Refetch it on the `menu.availability_changed` event (public.
  /// channel `menu.{store_id}`). The same data is in GET /catalog?store_id=… as `availability`.
  ///
  /// Wave M2: without `branch_id` (the Mini App) it comes from the read model's availability overlay, at most 15 s.
  /// old (App\Domain\Marketplace\MarketplaceBrowse); with a branch (tills, staff screens) from the tables.
  ///
  /// [storeId] - The store's uuid or numeric id.
  ///
  /// [branchId] - Only this branch.
  ///
  /// [ifNoneMatch] - Optional: the ETag of the last 200 answer. While nothing it depends on has changed the answer is 304 with no body: keep the last body and the time it was received.
  @GET('/v1/catalog/availability')
  Future<GetV1CatalogAvailabilityResponse> menuAvailabilityIndex({
    @Query('store_id') required String storeId,
    @Query('branch_id') int? branchId,
    @Header('If-None-Match') String? ifNoneMatch,
  });
}
