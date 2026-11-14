// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'ingredients5.g.dart';

@JsonSerializable()
class Ingredients5 {
  const Ingredients5({
    required this.key,
    required this.name,
    required this.unit,
    required this.perKg,
    required this.quantity,
  });

  factory Ingredients5.fromJson(Map<String, Object?> json) =>
      _$Ingredients5FromJson(json);

  final String key;
  final String name;
  final String unit;
  @JsonKey(name: 'per_kg')
  final String perKg;
  final String quantity;

  Map<String, Object?> toJson() => _$Ingredients5ToJson(this);
}
