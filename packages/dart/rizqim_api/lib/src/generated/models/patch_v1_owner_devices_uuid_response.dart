// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data42.dart';

part 'patch_v1_owner_devices_uuid_response.g.dart';

@JsonSerializable()
class PatchV1OwnerDevicesUuidResponse {
  const PatchV1OwnerDevicesUuidResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PatchV1OwnerDevicesUuidResponse.fromJson(Map<String, Object?> json) =>
      _$PatchV1OwnerDevicesUuidResponseFromJson(json);

  final bool success;
  final String message;
  final Data42 data;

  Map<String, Object?> toJson() =>
      _$PatchV1OwnerDevicesUuidResponseToJson(this);
}
