// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'variants.g.dart';

@JsonSerializable()
class Variants {
  const Variants({
    required this.variantId,
    required this.variantUuid,
    required this.name,
    required this.price,
    required this.isActive,
    required this.hiddenReason,
    required this.sortOrder,
  });

  factory Variants.fromJson(Map<String, Object?> json) =>
      _$VariantsFromJson(json);

  @JsonKey(name: 'variant_id')
  final int variantId;
  @JsonKey(name: 'variant_uuid')
  final String variantUuid;
  final String? name;
  final String price;
  @JsonKey(name: 'is_active')
  final bool isActive;
  @JsonKey(name: 'hidden_reason')
  final String? hiddenReason;
  @JsonKey(name: 'sort_order')
  final int sortOrder;

  Map<String, Object?> toJson() => _$VariantsToJson(this);
}
