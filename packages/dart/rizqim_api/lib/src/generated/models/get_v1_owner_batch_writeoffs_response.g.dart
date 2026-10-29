// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_owner_batch_writeoffs_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1OwnerBatchWriteoffsResponse _$GetV1OwnerBatchWriteoffsResponseFromJson(
  Map<String, dynamic> json,
) => GetV1OwnerBatchWriteoffsResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data32.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1OwnerBatchWriteoffsResponseToJson(
  GetV1OwnerBatchWriteoffsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
