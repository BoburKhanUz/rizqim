// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_pos_orders_uuid_merge_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1PosOrdersUuidMergeResponse _$PostV1PosOrdersUuidMergeResponseFromJson(
  Map<String, dynamic> json,
) => PostV1PosOrdersUuidMergeResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data224.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1PosOrdersUuidMergeResponseToJson(
  PostV1PosOrdersUuidMergeResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
