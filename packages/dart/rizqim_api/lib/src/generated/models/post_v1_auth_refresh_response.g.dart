// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_auth_refresh_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1AuthRefreshResponse _$PostV1AuthRefreshResponseFromJson(
  Map<String, dynamic> json,
) => PostV1AuthRefreshResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data4.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1AuthRefreshResponseToJson(
  PostV1AuthRefreshResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
