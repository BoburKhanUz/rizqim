// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'region.dart';

part 'district.g.dart';

@JsonSerializable()
class District {
  const District({
    required this.id,
    required this.regionId,
    required this.nameUz,
    required this.isActive,
    required this.createdAt,
    required this.updatedAt,
    this.region,
  });

  factory District.fromJson(Map<String, Object?> json) =>
      _$DistrictFromJson(json);

  final int id;
  @JsonKey(name: 'region_id')
  final int regionId;
  @JsonKey(name: 'name_uz')
  final String nameUz;
  @JsonKey(name: 'is_active')
  final bool isActive;
  @JsonKey(name: 'created_at')
  final DateTime? createdAt;
  @JsonKey(name: 'updated_at')
  final DateTime? updatedAt;
  final Region? region;

  Map<String, Object?> toJson() => _$DistrictToJson(this);
}
