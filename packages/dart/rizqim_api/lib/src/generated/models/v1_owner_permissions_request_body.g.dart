// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_owner_permissions_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1OwnerPermissionsRequestBody _$V1OwnerPermissionsRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1OwnerPermissionsRequestBody(
  role: Role.fromJson(json['role'] as String),
  permission: Permission.fromJson(json['permission'] as String),
  allowed: json['allowed'] as bool?,
);

Map<String, dynamic> _$V1OwnerPermissionsRequestBodyToJson(
  V1OwnerPermissionsRequestBody instance,
) => <String, dynamic>{
  'role': instance.role,
  'permission': instance.permission,
  'allowed': instance.allowed,
};
