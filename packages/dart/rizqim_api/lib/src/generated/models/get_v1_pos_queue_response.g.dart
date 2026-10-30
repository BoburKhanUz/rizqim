// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_pos_queue_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1PosQueueResponse _$GetV1PosQueueResponseFromJson(
  Map<String, dynamic> json,
) => GetV1PosQueueResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data233.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1PosQueueResponseToJson(
  GetV1PosQueueResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
