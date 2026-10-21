// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'modifiers7.dart';

part 'items6.g.dart';

@JsonSerializable()
class Items6 {
  const Items6({
    required this.id,
    required this.productName,
    required this.variantName,
    required this.quantity,
    required this.unit,
    required this.quantityLabel,
    required this.modifiers,
    required this.kitchenStatus,
    required this.readyAt,
    this.unitPrice,
    this.modifiersTotal,
    this.totalPrice,
  });

  factory Items6.fromJson(Map<String, Object?> json) => _$Items6FromJson(json);

  final int id;
  @JsonKey(name: 'product_name')
  final String productName;
  @JsonKey(name: 'variant_name')
  final String? variantName;
  final num quantity;
  final String unit;
  @JsonKey(name: 'quantity_label')
  final String quantityLabel;
  final List<Modifiers7> modifiers;
  @JsonKey(name: 'kitchen_status')
  final String kitchenStatus;
  @JsonKey(name: 'ready_at')
  final String? readyAt;
  @JsonKey(name: 'unit_price')
  final String? unitPrice;
  @JsonKey(name: 'modifiers_total')
  final String? modifiersTotal;
  @JsonKey(name: 'total_price')
  final String? totalPrice;

  Map<String, Object?> toJson() => _$Items6ToJson(this);
}
