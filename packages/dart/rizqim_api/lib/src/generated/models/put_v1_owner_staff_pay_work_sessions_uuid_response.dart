// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data158.dart';

part 'put_v1_owner_staff_pay_work_sessions_uuid_response.g.dart';

@JsonSerializable()
class PutV1OwnerStaffPayWorkSessionsUuidResponse {
  const PutV1OwnerStaffPayWorkSessionsUuidResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PutV1OwnerStaffPayWorkSessionsUuidResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PutV1OwnerStaffPayWorkSessionsUuidResponseFromJson(json);

  final bool success;
  final String message;
  final Data158 data;

  Map<String, Object?> toJson() =>
      _$PutV1OwnerStaffPayWorkSessionsUuidResponseToJson(this);
}
