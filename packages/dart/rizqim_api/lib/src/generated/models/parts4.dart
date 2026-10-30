// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'parts4.g.dart';

@JsonSerializable()
class Parts4 {
  const Parts4({
    required this.variantId,
    required this.variantUuid,
    required this.productId,
    required this.name,
    required this.variantName,
    required this.quantity,
    required this.unit,
    required this.label,
  });

  factory Parts4.fromJson(Map<String, Object?> json) => _$Parts4FromJson(json);

  @JsonKey(name: 'variant_id')
  final int variantId;
  @JsonKey(name: 'variant_uuid')
  final String variantUuid;
  @JsonKey(name: 'product_id')
  final int productId;
  final String name;
  @JsonKey(name: 'variant_name')
  final String? variantName;
  final String quantity;
  final String unit;
  final String label;

  Map<String, Object?> toJson() => _$Parts4ToJson(this);
}
