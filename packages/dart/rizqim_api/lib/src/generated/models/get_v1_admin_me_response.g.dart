// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_admin_me_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1AdminMeResponse _$GetV1AdminMeResponseFromJson(
  Map<String, dynamic> json,
) => GetV1AdminMeResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data12.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1AdminMeResponseToJson(
  GetV1AdminMeResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
