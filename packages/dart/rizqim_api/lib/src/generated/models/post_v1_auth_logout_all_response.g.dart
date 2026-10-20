// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_auth_logout_all_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1AuthLogoutAllResponse _$PostV1AuthLogoutAllResponseFromJson(
  Map<String, dynamic> json,
) => PostV1AuthLogoutAllResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: json['data'],
);

Map<String, dynamic> _$PostV1AuthLogoutAllResponseToJson(
  PostV1AuthLogoutAllResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
