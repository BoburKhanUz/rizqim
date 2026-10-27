// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'put_v1_admin_roles_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PutV1AdminRolesResponse _$PutV1AdminRolesResponseFromJson(
  Map<String, dynamic> json,
) => PutV1AdminRolesResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data14.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PutV1AdminRolesResponseToJson(
  PutV1AdminRolesResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
