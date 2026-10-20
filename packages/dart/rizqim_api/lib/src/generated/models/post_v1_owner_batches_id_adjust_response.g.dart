// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_owner_batches_id_adjust_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1OwnerBatchesIdAdjustResponse _$PostV1OwnerBatchesIdAdjustResponseFromJson(
  Map<String, dynamic> json,
) => PostV1OwnerBatchesIdAdjustResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data27.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1OwnerBatchesIdAdjustResponseToJson(
  PostV1OwnerBatchesIdAdjustResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
