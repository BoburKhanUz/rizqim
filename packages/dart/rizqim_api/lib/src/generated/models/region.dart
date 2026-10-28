// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'district.dart';

part 'region.g.dart';

@JsonSerializable()
class Region {
  const Region({
    required this.id,
    required this.nameUz,
    required this.isActive,
    required this.createdAt,
    required this.updatedAt,
    required this.sortOrder,
    this.districts,
  });

  factory Region.fromJson(Map<String, Object?> json) => _$RegionFromJson(json);

  final int id;
  @JsonKey(name: 'name_uz')
  final String nameUz;
  @JsonKey(name: 'is_active')
  final bool isActive;
  @JsonKey(name: 'created_at')
  final String? createdAt;
  @JsonKey(name: 'updated_at')
  final String? updatedAt;
  @JsonKey(name: 'sort_order')
  final int sortOrder;
  final List<District>? districts;

  Map<String, Object?> toJson() => _$RegionToJson(this);
}
