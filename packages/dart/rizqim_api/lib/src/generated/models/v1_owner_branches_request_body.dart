// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'status3.dart';

part 'v1_owner_branches_request_body.g.dart';

@JsonSerializable()
class V1OwnerBranchesRequestBody {
  const V1OwnerBranchesRequestBody({
    required this.name,
    required this.address,
    required this.regionId,
    required this.districtId,
    required this.latitude,
    required this.longitude,
    this.phone,
    this.radiusKm,
    this.openTime,
    this.closeTime,
    this.isActive,
    this.status,
  });

  factory V1OwnerBranchesRequestBody.fromJson(Map<String, Object?> json) =>
      _$V1OwnerBranchesRequestBodyFromJson(json);

  final String name;
  final String? phone;
  final String address;
  @JsonKey(name: 'region_id')
  final int regionId;
  @JsonKey(name: 'district_id')
  final int districtId;
  final num latitude;
  final num longitude;
  @JsonKey(name: 'radius_km')
  final num? radiusKm;
  @JsonKey(name: 'open_time')
  final String? openTime;
  @JsonKey(name: 'close_time')
  final String? closeTime;
  @JsonKey(name: 'is_active')
  final bool? isActive;
  final Status3? status;

  Map<String, Object?> toJson() => _$V1OwnerBranchesRequestBodyToJson(this);
}
