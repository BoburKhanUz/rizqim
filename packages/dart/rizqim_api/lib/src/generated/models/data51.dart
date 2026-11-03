// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'working_hours3.dart';

part 'data51.g.dart';

@JsonSerializable()
class Data51 {
  const Data51({
    required this.id,
    required this.name,
    required this.phone,
    required this.address,
    required this.regionId,
    required this.districtId,
    required this.latitude,
    required this.longitude,
    required this.radiusKm,
    required this.status,
    required this.isActive,
    required this.workingHours,
    required this.isOpenNow,
    required this.createdAt,
    required this.updatedAt,
  });

  factory Data51.fromJson(Map<String, Object?> json) => _$Data51FromJson(json);

  final int id;
  final String name;
  final String? phone;
  final String? address;
  @JsonKey(name: 'region_id')
  final int? regionId;
  @JsonKey(name: 'district_id')
  final int? districtId;
  final num? latitude;
  final num? longitude;
  @JsonKey(name: 'radius_km')
  final num radiusKm;
  final String status;
  @JsonKey(name: 'is_active')
  final bool isActive;
  @JsonKey(name: 'working_hours')
  final WorkingHours3 workingHours;
  @JsonKey(name: 'is_open_now')
  final bool isOpenNow;
  @JsonKey(name: 'created_at')
  final String? createdAt;
  @JsonKey(name: 'updated_at')
  final String? updatedAt;

  Map<String, Object?> toJson() => _$Data51ToJson(this);
}
