// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'combo_parts3.dart';

part 'items5.g.dart';

@JsonSerializable()
class Items5 {
  const Items5({
    required this.orderItemId,
    required this.productName,
    required this.variantName,
    required this.unit,
    required this.quantity,
    required this.amount,
    required this.discountAmount,
    required this.comboParts,
  });

  factory Items5.fromJson(Map<String, Object?> json) => _$Items5FromJson(json);

  @JsonKey(name: 'order_item_id')
  final int orderItemId;
  @JsonKey(name: 'product_name')
  final String productName;
  @JsonKey(name: 'variant_name')
  final String? variantName;
  final String unit;
  final num quantity;
  final String amount;
  @JsonKey(name: 'discount_amount')
  final String discountAmount;
  @JsonKey(name: 'combo_parts')
  final List<ComboParts3>? comboParts;

  Map<String, Object?> toJson() => _$Items5ToJson(this);
}
