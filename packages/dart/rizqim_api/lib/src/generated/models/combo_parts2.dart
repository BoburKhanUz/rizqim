// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'combo_parts2.g.dart';

@JsonSerializable()
class ComboParts2 {
  const ComboParts2({
    required this.slotName,
    required this.name,
    required this.variantName,
    required this.quantity,
    required this.label,
  });

  factory ComboParts2.fromJson(Map<String, Object?> json) =>
      _$ComboParts2FromJson(json);

  @JsonKey(name: 'slot_name')
  final String slotName;
  final String name;
  @JsonKey(name: 'variant_name')
  final String? variantName;
  final int quantity;
  final String label;

  Map<String, Object?> toJson() => _$ComboParts2ToJson(this);
}
