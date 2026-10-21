// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'items28.g.dart';

@JsonSerializable()
class Items28 {
  const Items28({
    required this.orderItemId,
    required this.productName,
    required this.variantName,
    required this.unit,
    required this.quantity,
    required this.amount,
    required this.discountAmount,
  });

  factory Items28.fromJson(Map<String, Object?> json) =>
      _$Items28FromJson(json);

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

  Map<String, Object?> toJson() => _$Items28ToJson(this);
}
