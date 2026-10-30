// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_pos_clock_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1PosClockResponse _$GetV1PosClockResponseFromJson(
  Map<String, dynamic> json,
) => GetV1PosClockResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data205.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1PosClockResponseToJson(
  GetV1PosClockResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
