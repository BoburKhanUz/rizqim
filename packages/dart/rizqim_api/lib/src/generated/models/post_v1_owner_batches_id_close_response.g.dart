// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_owner_batches_id_close_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1OwnerBatchesIdCloseResponse _$PostV1OwnerBatchesIdCloseResponseFromJson(
  Map<String, dynamic> json,
) => PostV1OwnerBatchesIdCloseResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data28.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1OwnerBatchesIdCloseResponseToJson(
  PostV1OwnerBatchesIdCloseResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
