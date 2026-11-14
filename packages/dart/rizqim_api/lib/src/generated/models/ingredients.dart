// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'ingredients.g.dart';

@JsonSerializable()
class Ingredients {
  const Ingredients({
    required this.key,
    required this.name,
    required this.unit,
    required this.perKg,
  });

  factory Ingredients.fromJson(Map<String, Object?> json) =>
      _$IngredientsFromJson(json);

  final String key;
  final String name;
  final String unit;
  @JsonKey(name: 'per_kg')
  final String perKg;

  Map<String, Object?> toJson() => _$IngredientsToJson(this);
}
