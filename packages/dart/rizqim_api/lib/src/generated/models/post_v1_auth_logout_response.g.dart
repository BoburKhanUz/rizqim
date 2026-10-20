// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_auth_logout_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1AuthLogoutResponse _$PostV1AuthLogoutResponseFromJson(
  Map<String, dynamic> json,
) => PostV1AuthLogoutResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: json['data'],
);

Map<String, dynamic> _$PostV1AuthLogoutResponseToJson(
  PostV1AuthLogoutResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
