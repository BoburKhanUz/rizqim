// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_pos_waitlist_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1PosWaitlistResponse _$GetV1PosWaitlistResponseFromJson(
  Map<String, dynamic> json,
) => GetV1PosWaitlistResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data251.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1PosWaitlistResponseToJson(
  GetV1PosWaitlistResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
