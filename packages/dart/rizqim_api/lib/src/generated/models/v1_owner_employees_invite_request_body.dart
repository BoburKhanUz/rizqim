// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'role.dart';

part 'v1_owner_employees_invite_request_body.g.dart';

@JsonSerializable()
class V1OwnerEmployeesInviteRequestBody {
  const V1OwnerEmployeesInviteRequestBody({
    required this.role,
    required this.name,
    required this.branchId,
  });

  factory V1OwnerEmployeesInviteRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1OwnerEmployeesInviteRequestBodyFromJson(json);

  final Role role;
  final String name;
  @JsonKey(name: 'branch_id')
  final int branchId;

  Map<String, Object?> toJson() =>
      _$V1OwnerEmployeesInviteRequestBodyToJson(this);
}
