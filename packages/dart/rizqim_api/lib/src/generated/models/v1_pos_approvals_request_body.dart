// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'permission.dart';

part 'v1_pos_approvals_request_body.g.dart';

@JsonSerializable()
class V1PosApprovalsRequestBody {
  const V1PosApprovalsRequestBody({
    required this.permission,
    required this.pin,
    this.userId,
    this.staffCode,
  });

  factory V1PosApprovalsRequestBody.fromJson(Map<String, Object?> json) =>
      _$V1PosApprovalsRequestBodyFromJson(json);

  final Permission permission;
  @JsonKey(name: 'user_id')
  final int? userId;
  @JsonKey(name: 'staff_code')
  final String? staffCode;
  final String pin;

  Map<String, Object?> toJson() => _$V1PosApprovalsRequestBodyToJson(this);
}
