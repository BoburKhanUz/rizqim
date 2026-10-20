// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_devices_device_uuid_staff_session_request_body.g.dart';

@JsonSerializable()
class V1DevicesDeviceUuidStaffSessionRequestBody {
  const V1DevicesDeviceUuidStaffSessionRequestBody({
    required this.pin,
    this.userId,
    this.staffCode,
  });

  factory V1DevicesDeviceUuidStaffSessionRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1DevicesDeviceUuidStaffSessionRequestBodyFromJson(json);

  @JsonKey(name: 'user_id')
  final int? userId;
  @JsonKey(name: 'staff_code')
  final String? staffCode;
  final String pin;

  Map<String, Object?> toJson() =>
      _$V1DevicesDeviceUuidStaffSessionRequestBodyToJson(this);
}
