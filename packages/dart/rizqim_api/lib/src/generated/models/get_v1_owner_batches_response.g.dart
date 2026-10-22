// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_owner_batches_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1OwnerBatchesResponse _$GetV1OwnerBatchesResponseFromJson(
  Map<String, dynamic> json,
) => GetV1OwnerBatchesResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data31.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1OwnerBatchesResponseToJson(
  GetV1OwnerBatchesResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
