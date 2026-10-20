// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_addresses_request_body.g.dart';

@JsonSerializable()
class V1AddressesRequestBody {
  const V1AddressesRequestBody({
    required this.title,
    required this.address,
    this.latitude,
    this.longitude,
    this.apartment,
    this.entrance,
    this.floor,
    this.intercom,
    this.comment,
    this.isDefault,
    this.regionId,
    this.districtId,
  });

  factory V1AddressesRequestBody.fromJson(Map<String, Object?> json) =>
      _$V1AddressesRequestBodyFromJson(json);

  final String title;
  final String address;
  final num? latitude;
  final num? longitude;
  final String? apartment;
  final String? entrance;
  final String? floor;
  final String? intercom;
  final String? comment;
  @JsonKey(name: 'is_default')
  final bool? isDefault;
  @JsonKey(name: 'region_id')
  final int? regionId;
  @JsonKey(name: 'district_id')
  final int? districtId;

  Map<String, Object?> toJson() => _$V1AddressesRequestBodyToJson(this);
}
