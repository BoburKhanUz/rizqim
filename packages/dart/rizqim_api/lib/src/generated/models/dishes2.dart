// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'dishes2.g.dart';

@JsonSerializable()
class Dishes2 {
  const Dishes2({
    required this.category,
    required this.name,
    required this.variants,
    required this.exists,
  });

  factory Dishes2.fromJson(Map<String, Object?> json) =>
      _$Dishes2FromJson(json);

  final String category;
  final String name;
  final List<String> variants;
  final bool exists;

  Map<String, Object?> toJson() => _$Dishes2ToJson(this);
}
