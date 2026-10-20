// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'modifiers3.g.dart';

@JsonSerializable()
class Modifiers3 {
  const Modifiers3({
    required this.id,
    required this.uuid,
    required this.name,
    required this.nameI18n,
    required this.priceDelta,
    required this.maxQuantity,
    required this.productVariantId,
    required this.sortOrder,
    required this.isActive,
  });

  factory Modifiers3.fromJson(Map<String, Object?> json) =>
      _$Modifiers3FromJson(json);

  final int id;
  final String uuid;
  final String name;
  @JsonKey(name: 'name_i18n')
  final Map<String, String>? nameI18n;
  @JsonKey(name: 'price_delta')
  final String priceDelta;
  @JsonKey(name: 'max_quantity')
  final int maxQuantity;
  @JsonKey(name: 'product_variant_id')
  final int? productVariantId;
  @JsonKey(name: 'sort_order')
  final int sortOrder;
  @JsonKey(name: 'is_active')
  final bool isActive;

  Map<String, Object?> toJson() => _$Modifiers3ToJson(this);
}
