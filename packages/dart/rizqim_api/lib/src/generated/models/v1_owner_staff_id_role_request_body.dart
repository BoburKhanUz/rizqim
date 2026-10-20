// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'role.dart';

part 'v1_owner_staff_id_role_request_body.g.dart';

@JsonSerializable()
class V1OwnerStaffIdRoleRequestBody {
  const V1OwnerStaffIdRoleRequestBody({required this.role, this.branchId});

  factory V1OwnerStaffIdRoleRequestBody.fromJson(Map<String, Object?> json) =>
      _$V1OwnerStaffIdRoleRequestBodyFromJson(json);

  final Role role;
  @JsonKey(name: 'branch_id')
  final int? branchId;

  Map<String, Object?> toJson() => _$V1OwnerStaffIdRoleRequestBodyToJson(this);
}
