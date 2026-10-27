// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_admin_auth_confirm_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1AdminAuthConfirmResponse _$PostV1AdminAuthConfirmResponseFromJson(
  Map<String, dynamic> json,
) => PostV1AdminAuthConfirmResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data7.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1AdminAuthConfirmResponseToJson(
  PostV1AdminAuthConfirmResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
