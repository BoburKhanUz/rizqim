// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'menu_batch.dart';
import 'menu_unit.dart';
import 'order.dart';
import 'product_variant.dart';

part 'order_item.g.dart';

@JsonSerializable()
class OrderItem {
  const OrderItem({
    required this.id,
    required this.orderId,
    required this.productVariantId,
    required this.productName,
    required this.variantName,
    required this.quantity,
    required this.unitPrice,
    required this.totalPrice,
    required this.createdAt,
    required this.updatedAt,
    required this.deletedAt,
    required this.unit,
    required this.modifiers,
    required this.modifiersTotal,
    required this.batchId,
    required this.batchQuantity,
    this.order,
    this.productVariant,
    this.batch,
  });

  factory OrderItem.fromJson(Map<String, Object?> json) =>
      _$OrderItemFromJson(json);

  final int id;
  @JsonKey(name: 'order_id')
  final int orderId;
  @JsonKey(name: 'product_variant_id')
  final int? productVariantId;
  @JsonKey(name: 'product_name')
  final String productName;
  @JsonKey(name: 'variant_name')
  final String? variantName;
  final num quantity;
  @JsonKey(name: 'unit_price')
  final String unitPrice;
  @JsonKey(name: 'total_price')
  final String totalPrice;
  @JsonKey(name: 'created_at')
  final DateTime? createdAt;
  @JsonKey(name: 'updated_at')
  final DateTime? updatedAt;
  @JsonKey(name: 'deleted_at')
  final DateTime? deletedAt;
  final MenuUnit unit;
  final List<Map<String, dynamic>>? modifiers;
  @JsonKey(name: 'modifiers_total')
  final String modifiersTotal;
  @JsonKey(name: 'batch_id')
  final int? batchId;
  @JsonKey(name: 'batch_quantity')
  final String? batchQuantity;
  final Order? order;
  @JsonKey(name: 'product_variant')
  final ProductVariant? productVariant;
  final MenuBatch? batch;

  Map<String, Object?> toJson() => _$OrderItemToJson(this);
}
