// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'channel_price.dart';
import 'menu_unit.dart';
import 'product.dart';
import 'product_variant.dart';
import 'variants_unavailable_reason.dart';

part 'products_variants.g.dart';

@JsonSerializable()
class ProductsVariants {
  const ProductsVariants({
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
    required this.unitLabel,
    required this.basePrice,
    required this.isAvailable,
    required this.unavailableReason,
    this.product,
    this.channelPrices,
  });

  factory ProductsVariants.fromJson(Map<String, Object?> json) =>
      _$ProductsVariantsFromJson(json);

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
  @JsonKey(name: 'unit_label')
  final String? unitLabel;
  final Product? product;
  @JsonKey(name: 'channel_prices')
  final List<ChannelPrice>? channelPrices;
  @JsonKey(name: 'base_price')
  final String basePrice;
  @JsonKey(name: 'is_available')
  final bool isAvailable;
  @JsonKey(name: 'unavailable_reason')
  final VariantsUnavailableReason? unavailableReason;

  Map<String, Object?> toJson() => _$ProductsVariantsToJson(this);
}
