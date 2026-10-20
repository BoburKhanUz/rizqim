// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data12.dart';

part 'post_v1_devices_device_uuid_staff_session_response.g.dart';

@JsonSerializable()
class PostV1DevicesDeviceUuidStaffSessionResponse {
  const PostV1DevicesDeviceUuidStaffSessionResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1DevicesDeviceUuidStaffSessionResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PostV1DevicesDeviceUuidStaffSessionResponseFromJson(json);

  final bool success;
  final String message;
  final Data12 data;

  Map<String, Object?> toJson() =>
      _$PostV1DevicesDeviceUuidStaffSessionResponseToJson(this);
}
