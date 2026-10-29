// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'dishes3.g.dart';

@JsonSerializable()
class Dishes3 {
  const Dishes3({
    required this.category,
    required this.name,
    required this.variants,
    required this.exists,
  });

  factory Dishes3.fromJson(Map<String, Object?> json) =>
      _$Dishes3FromJson(json);

  final String category;
  final String name;
  final List<String> variants;
  final bool exists;

  Map<String, Object?> toJson() => _$Dishes3ToJson(this);
}
