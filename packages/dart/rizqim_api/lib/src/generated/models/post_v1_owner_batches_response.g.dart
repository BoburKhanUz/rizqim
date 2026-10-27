// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_owner_batches_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1OwnerBatchesResponse _$PostV1OwnerBatchesResponseFromJson(
  Map<String, dynamic> json,
) => PostV1OwnerBatchesResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data53.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1OwnerBatchesResponseToJson(
  PostV1OwnerBatchesResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
