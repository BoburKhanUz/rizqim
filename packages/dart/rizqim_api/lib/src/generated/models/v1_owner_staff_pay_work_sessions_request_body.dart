// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_owner_staff_pay_work_sessions_request_body.g.dart';

@JsonSerializable()
class V1OwnerStaffPayWorkSessionsRequestBody {
  const V1OwnerStaffPayWorkSessionsRequestBody({
    required this.userId,
    required this.clockInAt,
    required this.reason,
    this.branchId,
    this.clockOutAt,
  });

  factory V1OwnerStaffPayWorkSessionsRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1OwnerStaffPayWorkSessionsRequestBodyFromJson(json);

  @JsonKey(name: 'branch_id')
  final int? branchId;
  @JsonKey(name: 'user_id')
  final int userId;
  @JsonKey(name: 'clock_in_at')
  final String clockInAt;
  @JsonKey(name: 'clock_out_at')
  final String? clockOutAt;
  final String reason;

  Map<String, Object?> toJson() =>
      _$V1OwnerStaffPayWorkSessionsRequestBodyToJson(this);
}
