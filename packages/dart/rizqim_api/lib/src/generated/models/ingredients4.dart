// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'ingredients4.g.dart';

@JsonSerializable()
class Ingredients4 {
  const Ingredients4({
    required this.key,
    required this.name,
    required this.unit,
    required this.perKg,
    required this.quantity,
  });

  factory Ingredients4.fromJson(Map<String, Object?> json) =>
      _$Ingredients4FromJson(json);

  final String key;
  final String name;
  final String unit;
  @JsonKey(name: 'per_kg')
  final String perKg;
  final String quantity;

  Map<String, Object?> toJson() => _$Ingredients4ToJson(this);
}
