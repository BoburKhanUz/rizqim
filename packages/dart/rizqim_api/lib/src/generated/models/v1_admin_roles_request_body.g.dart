// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_admin_roles_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1AdminRolesRequestBody _$V1AdminRolesRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1AdminRolesRequestBody(
  role: PlatformRole.fromJson(json['role'] as String),
  permission: AdminPermission.fromJson(json['permission'] as String),
  allowed: json['allowed'] as bool?,
);

Map<String, dynamic> _$V1AdminRolesRequestBodyToJson(
  V1AdminRolesRequestBody instance,
) => <String, dynamic>{
  'role': instance.role,
  'permission': instance.permission,
  'allowed': instance.allowed,
};
