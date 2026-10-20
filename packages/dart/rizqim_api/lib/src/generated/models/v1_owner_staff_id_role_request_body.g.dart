// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_owner_staff_id_role_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1OwnerStaffIdRoleRequestBody _$V1OwnerStaffIdRoleRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1OwnerStaffIdRoleRequestBody(
  role: Role.fromJson(json['role'] as String),
  branchId: (json['branch_id'] as num?)?.toInt(),
);

Map<String, dynamic> _$V1OwnerStaffIdRoleRequestBodyToJson(
  V1OwnerStaffIdRoleRequestBody instance,
) => <String, dynamic>{'role': instance.role, 'branch_id': instance.branchId};
