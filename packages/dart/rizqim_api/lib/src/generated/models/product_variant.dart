// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'channel_price.dart';
import 'menu_unit.dart';
import 'product.dart';

part 'product_variant.g.dart';

@JsonSerializable()
class ProductVariant {
  const ProductVariant({
    required this.id,
    required this.uuid,
    required this.productId,
    required this.name,
    required this.sku,
    required this.price,
    required this.stock,
    required this.attributesJson,
    required this.isActive,
    required this.createdAt,
    required this.updatedAt,
    required this.deletedAt,
    required this.unit,
    required this.quantityStep,
    required this.minQuantity,
    required this.batchConsumption,
    this.product,
    this.channelPrices,
  });

  factory ProductVariant.fromJson(Map<String, Object?> json) =>
      _$ProductVariantFromJson(json);

  final int id;
  final String uuid;
  @JsonKey(name: 'product_id')
  final int productId;
  final String? name;
  final String? sku;
  final String price;
  final num stock;
  @JsonKey(name: 'attributes_json')
  final Map<String, dynamic>? attributesJson;
  @JsonKey(name: 'is_active')
  final bool isActive;
  @JsonKey(name: 'created_at')
  final DateTime? createdAt;
  @JsonKey(name: 'updated_at')
  final DateTime? updatedAt;
  @JsonKey(name: 'deleted_at')
  final DateTime? deletedAt;
  final MenuUnit unit;
  @JsonKey(name: 'quantity_step')
  final String? quantityStep;
  @JsonKey(name: 'min_quantity')
  final String? minQuantity;
  @JsonKey(name: 'batch_consumption')
  final String batchConsumption;
  final Product? product;
  @JsonKey(name: 'channel_prices')
  final List<ChannelPrice>? channelPrices;

  Map<String, Object?> toJson() => _$ProductVariantToJson(this);
}
