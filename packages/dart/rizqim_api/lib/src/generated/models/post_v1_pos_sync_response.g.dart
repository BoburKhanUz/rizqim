// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_pos_sync_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1PosSyncResponse _$PostV1PosSyncResponseFromJson(
  Map<String, dynamic> json,
) => PostV1PosSyncResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data178.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1PosSyncResponseToJson(
  PostV1PosSyncResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
