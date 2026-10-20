// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'delete_v1_devices_device_uuid_staff_session_response.g.dart';

@JsonSerializable()
class DeleteV1DevicesDeviceUuidStaffSessionResponse {
  const DeleteV1DevicesDeviceUuidStaffSessionResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory DeleteV1DevicesDeviceUuidStaffSessionResponse.fromJson(
    Map<String, Object?> json,
  ) => _$DeleteV1DevicesDeviceUuidStaffSessionResponseFromJson(json);

  final bool success;
  final String message;
  final dynamic data;

  Map<String, Object?> toJson() =>
      _$DeleteV1DevicesDeviceUuidStaffSessionResponseToJson(this);
}
