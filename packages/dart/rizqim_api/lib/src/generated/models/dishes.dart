// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'dishes.g.dart';

@JsonSerializable()
class Dishes {
  const Dishes({
    required this.category,
    required this.name,
    required this.variants,
    required this.exists,
  });

  factory Dishes.fromJson(Map<String, Object?> json) => _$DishesFromJson(json);

  final String category;
  final String name;
  final List<String> variants;
  final bool exists;

  Map<String, Object?> toJson() => _$DishesToJson(this);
}
