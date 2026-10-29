// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'delete_v1_owner_staff_pay_work_sessions_uuid_response.g.dart';

@JsonSerializable()
class DeleteV1OwnerStaffPayWorkSessionsUuidResponse {
  const DeleteV1OwnerStaffPayWorkSessionsUuidResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory DeleteV1OwnerStaffPayWorkSessionsUuidResponse.fromJson(
    Map<String, Object?> json,
  ) => _$DeleteV1OwnerStaffPayWorkSessionsUuidResponseFromJson(json);

  final bool success;
  final String message;
  final dynamic data;

  Map<String, Object?> toJson() =>
      _$DeleteV1OwnerStaffPayWorkSessionsUuidResponseToJson(this);
}
