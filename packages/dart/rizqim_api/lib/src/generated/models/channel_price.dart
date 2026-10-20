// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'order_channel.dart';
import 'product.dart';
import 'product_variant.dart';
import 'store.dart';

part 'channel_price.g.dart';

@JsonSerializable()
class ChannelPrice {
  const ChannelPrice({
    required this.id,
    required this.storeId,
    required this.productId,
    required this.productVariantId,
    required this.channel,
    required this.price,
    required this.isHidden,
    required this.createdAt,
    required this.updatedAt,
    this.product,
    this.variant,
    this.store,
  });

  factory ChannelPrice.fromJson(Map<String, Object?> json) =>
      _$ChannelPriceFromJson(json);

  final int id;
  @JsonKey(name: 'store_id')
  final int storeId;
  @JsonKey(name: 'product_id')
  final int productId;
  @JsonKey(name: 'product_variant_id')
  final int? productVariantId;
  final OrderChannel channel;
  final String? price;
  @JsonKey(name: 'is_hidden')
  final bool isHidden;
  @JsonKey(name: 'created_at')
  final DateTime? createdAt;
  @JsonKey(name: 'updated_at')
  final DateTime? updatedAt;
  final Product? product;
  final ProductVariant? variant;
  final Store? store;

  Map<String, Object?> toJson() => _$ChannelPriceToJson(this);
}
