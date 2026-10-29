// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_owner_staff_pay_work_sessions_uuid_request_body.g.dart';

@JsonSerializable()
class V1OwnerStaffPayWorkSessionsUuidRequestBody {
  const V1OwnerStaffPayWorkSessionsUuidRequestBody({
    required this.clockInAt,
    required this.reason,
    this.clockOutAt,
  });

  factory V1OwnerStaffPayWorkSessionsUuidRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1OwnerStaffPayWorkSessionsUuidRequestBodyFromJson(json);

  @JsonKey(name: 'clock_in_at')
  final String clockInAt;
  @JsonKey(name: 'clock_out_at')
  final String? clockOutAt;
  final String reason;

  Map<String, Object?> toJson() =>
      _$V1OwnerStaffPayWorkSessionsUuidRequestBodyToJson(this);
}
