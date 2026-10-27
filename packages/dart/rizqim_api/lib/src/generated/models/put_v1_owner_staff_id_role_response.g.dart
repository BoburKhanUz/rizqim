// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'put_v1_owner_staff_id_role_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PutV1OwnerStaffIdRoleResponse _$PutV1OwnerStaffIdRoleResponseFromJson(
  Map<String, dynamic> json,
) => PutV1OwnerStaffIdRoleResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data113.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PutV1OwnerStaffIdRoleResponseToJson(
  PutV1OwnerStaffIdRoleResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
