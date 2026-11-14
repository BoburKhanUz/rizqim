// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'unit.dart';

part 'ingredients3.g.dart';

@JsonSerializable()
class Ingredients3 {
  const Ingredients3({
    required this.name,
    required this.unit,
    required this.perKg,
    this.key,
  });

  factory Ingredients3.fromJson(Map<String, Object?> json) =>
      _$Ingredients3FromJson(json);

  final String? key;
  final String name;
  final Unit unit;
  @JsonKey(name: 'per_kg')
  final num perKg;

  Map<String, Object?> toJson() => _$Ingredients3ToJson(this);
}
