// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_owner_batch_plans_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1OwnerBatchPlansResponse _$PostV1OwnerBatchPlansResponseFromJson(
  Map<String, dynamic> json,
) => PostV1OwnerBatchPlansResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: json['data'] as List<dynamic>,
);

Map<String, dynamic> _$PostV1OwnerBatchPlansResponseToJson(
  PostV1OwnerBatchPlansResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
