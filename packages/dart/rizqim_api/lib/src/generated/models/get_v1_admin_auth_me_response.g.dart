// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_admin_auth_me_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1AdminAuthMeResponse _$GetV1AdminAuthMeResponseFromJson(
  Map<String, dynamic> json,
) => GetV1AdminAuthMeResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data10.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1AdminAuthMeResponseToJson(
  GetV1AdminAuthMeResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
