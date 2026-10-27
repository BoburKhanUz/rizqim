// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_admin_roles_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1AdminRolesResponse _$GetV1AdminRolesResponseFromJson(
  Map<String, dynamic> json,
) => GetV1AdminRolesResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data13.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1AdminRolesResponseToJson(
  GetV1AdminRolesResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
