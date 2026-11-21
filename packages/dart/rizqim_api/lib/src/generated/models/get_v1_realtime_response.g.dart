// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_realtime_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1RealtimeResponse _$GetV1RealtimeResponseFromJson(
  Map<String, dynamic> json,
) => GetV1RealtimeResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data364.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1RealtimeResponseToJson(
  GetV1RealtimeResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
