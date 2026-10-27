// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_admin_auth_sessions_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1AdminAuthSessionsResponse _$GetV1AdminAuthSessionsResponseFromJson(
  Map<String, dynamic> json,
) => GetV1AdminAuthSessionsResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: (json['data'] as List<dynamic>)
      .map((e) => Data11.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$GetV1AdminAuthSessionsResponseToJson(
  GetV1AdminAuthSessionsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
