// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'type5.dart';

part 'v1_owner_devices_pairing_codes_request_body.g.dart';

@JsonSerializable()
class V1OwnerDevicesPairingCodesRequestBody {
  const V1OwnerDevicesPairingCodesRequestBody({
    required this.type,
    this.branchId,
    this.name,
    this.tillUuid,
  });

  factory V1OwnerDevicesPairingCodesRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1OwnerDevicesPairingCodesRequestBodyFromJson(json);

  final Type5 type;
  @JsonKey(name: 'branch_id')
  final int? branchId;
  final String? name;
  @JsonKey(name: 'till_uuid')
  final String? tillUuid;

  Map<String, Object?> toJson() =>
      _$V1OwnerDevicesPairingCodesRequestBodyToJson(this);
}
