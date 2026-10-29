// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_pos_queue_blocks_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1PosQueueBlocksResponse _$PostV1PosQueueBlocksResponseFromJson(
  Map<String, dynamic> json,
) => PostV1PosQueueBlocksResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data197.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1PosQueueBlocksResponseToJson(
  PostV1PosQueueBlocksResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
