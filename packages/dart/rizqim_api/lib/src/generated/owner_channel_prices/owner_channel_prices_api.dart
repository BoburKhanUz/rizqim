// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/get_v1_owner_products_id_channel_prices_response.dart';
import '../models/put_v1_owner_products_id_channel_prices_response.dart';
import '../models/v1_owner_products_id_channel_prices_request_body.dart';

part 'owner_channel_prices_api.g.dart';

@RestApi()
abstract class OwnerChannelPricesApi {
  factory OwnerChannelPricesApi(Dio dio, {String? baseUrl}) =
      _OwnerChannelPricesApi;

  /// A product's channel prices.
  ///
  /// One row per channel and variant (`product_variant_id` null: the whole product, hidden only). No row: the.
  /// variant's own price, visible.
  @GET('/v1/owner/products/{id}/channel-prices')
  Future<GetV1OwnerProductsIdChannelPricesResponse> channelPriceIndex({
    @Path('id') required int id,
  });

  /// Replace a product's channel prices.
  ///
  /// `prices` replaces every row of the product. A row may set a variant's `price` on a channel and/or hide the.
  /// variant (`is_hidden`); a row without `product_variant_id` hides the whole product there (no price).
  @PUT('/v1/owner/products/{id}/channel-prices')
  Future<PutV1OwnerProductsIdChannelPricesResponse> channelPriceUpdate({
    @Path('id') required int id,
    @Body() required V1OwnerProductsIdChannelPricesRequestBody body,
  });
}
