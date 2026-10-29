// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_owner_batches_id_write_off_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1OwnerBatchesIdWriteOffResponse
_$PostV1OwnerBatchesIdWriteOffResponseFromJson(Map<String, dynamic> json) =>
    PostV1OwnerBatchesIdWriteOffResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data38.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$PostV1OwnerBatchesIdWriteOffResponseToJson(
  PostV1OwnerBatchesIdWriteOffResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
