// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_owner_employees_invite_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1OwnerEmployeesInviteRequestBody _$V1OwnerEmployeesInviteRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1OwnerEmployeesInviteRequestBody(
  role: Role.fromJson(json['role'] as String),
  name: json['name'] as String,
  branchId: (json['branch_id'] as num).toInt(),
);

Map<String, dynamic> _$V1OwnerEmployeesInviteRequestBodyToJson(
  V1OwnerEmployeesInviteRequestBody instance,
) => <String, dynamic>{
  'role': instance.role,
  'name': instance.name,
  'branch_id': instance.branchId,
};
