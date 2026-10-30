// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'modifiers34.g.dart';

@JsonSerializable()
class Modifiers34 {
  const Modifiers34({
    required this.name,
    this.id,
    this.nameI18n,
    this.priceDelta,
    this.maxQuantity,
    this.productVariantId,
    this.sortOrder,
    this.isActive,
  });

  factory Modifiers34.fromJson(Map<String, Object?> json) =>
      _$Modifiers34FromJson(json);

  final int? id;
  final String name;
  @JsonKey(name: 'name_i18n')
  final Map<String, String>? nameI18n;
  @JsonKey(name: 'price_delta')
  final num? priceDelta;
  @JsonKey(name: 'max_quantity')
  final int? maxQuantity;
  @JsonKey(name: 'product_variant_id')
  final int? productVariantId;
  @JsonKey(name: 'sort_order')
  final int? sortOrder;
  @JsonKey(name: 'is_active')
  final bool? isActive;

  Map<String, Object?> toJson() => _$Modifiers34ToJson(this);
}
