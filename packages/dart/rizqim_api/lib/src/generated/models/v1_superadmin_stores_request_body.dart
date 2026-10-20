// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_superadmin_stores_request_body.g.dart';

@JsonSerializable()
class V1SuperadminStoresRequestBody {
  const V1SuperadminStoresRequestBody({
    required this.name,
    required this.commissionRate,
    required this.ownerName,
    required this.branchRegionId,
    required this.branchDistrictId,
    required this.branchAddress,
    required this.branchLat,
    required this.branchLng,
    required this.branchRadius,
    this.phone,
    this.ownerPhone,
    this.branchPhone,
    this.branchOpenTime,
    this.branchCloseTime,
  });

  factory V1SuperadminStoresRequestBody.fromJson(Map<String, Object?> json) =>
      _$V1SuperadminStoresRequestBodyFromJson(json);

  final String name;
  final String? phone;
  @JsonKey(name: 'commission_rate')
  final num commissionRate;
  @JsonKey(name: 'owner_name')
  final String ownerName;
  @JsonKey(name: 'owner_phone')
  final String? ownerPhone;
  @JsonKey(name: 'branch_region_id')
  final int branchRegionId;
  @JsonKey(name: 'branch_district_id')
  final int branchDistrictId;
  @JsonKey(name: 'branch_address')
  final String branchAddress;
  @JsonKey(name: 'branch_phone')
  final String? branchPhone;
  @JsonKey(name: 'branch_lat')
  final num branchLat;
  @JsonKey(name: 'branch_lng')
  final num branchLng;
  @JsonKey(name: 'branch_radius')
  final num branchRadius;
  @JsonKey(name: 'branch_open_time')
  final String? branchOpenTime;
  @JsonKey(name: 'branch_close_time')
  final String? branchCloseTime;

  Map<String, Object?> toJson() => _$V1SuperadminStoresRequestBodyToJson(this);
}
