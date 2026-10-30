// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_pos_display_queue_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1PosDisplayQueueResponse _$GetV1PosDisplayQueueResponseFromJson(
  Map<String, dynamic> json,
) => GetV1PosDisplayQueueResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data217.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1PosDisplayQueueResponseToJson(
  GetV1PosDisplayQueueResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
