// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'combo_parts.g.dart';

@JsonSerializable()
class ComboParts {
  const ComboParts({
    required this.name,
    required this.inCombos,
    required this.alone,
    required this.total,
  });

  factory ComboParts.fromJson(Map<String, Object?> json) =>
      _$ComboPartsFromJson(json);

  final String name;
  @JsonKey(name: 'in_combos')
  final num inCombos;
  final num alone;
  final num total;

  Map<String, Object?> toJson() => _$ComboPartsToJson(this);
}
