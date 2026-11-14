// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'brand.g.dart';

@JsonSerializable()
class Brand {
  const Brand({
    required this.id,
    required this.name,
    required this.code,
    required this.color,
  });

  factory Brand.fromJson(Map<String, Object?> json) => _$BrandFromJson(json);

  final int id;
  final String name;
  final String code;
  final String color;

  Map<String, Object?> toJson() => _$BrandToJson(this);
}
