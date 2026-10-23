// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'items18.g.dart';

@JsonSerializable()
class Items18 {
  const Items18({
    required this.id,
    required this.productVariantId,
    required this.productVariantUuid,
    required this.productName,
    required this.variantName,
    required this.unit,
    required this.quantity,
    required this.modifiers,
    required this.unitPrice,
    required this.note,
    required this.dueAt,
  });

  factory Items18.fromJson(Map<String, Object?> json) =>
      _$Items18FromJson(json);

  final int id;
  @JsonKey(name: 'product_variant_id')
  final int productVariantId;
  @JsonKey(name: 'product_variant_uuid')
  final String? productVariantUuid;
  @JsonKey(name: 'product_name')
  final String productName;
  @JsonKey(name: 'variant_name')
  final String? variantName;
  final String unit;
  final num quantity;
  final List<Map<String, dynamic>> modifiers;
  @JsonKey(name: 'unit_price')
  final String unitPrice;
  final String? note;
  @JsonKey(name: 'due_at')
  final String? dueAt;

  Map<String, Object?> toJson() => _$Items18ToJson(this);
}
