// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_owner_batch_plans_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1OwnerBatchPlansResponse _$GetV1OwnerBatchPlansResponseFromJson(
  Map<String, dynamic> json,
) => GetV1OwnerBatchPlansResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data31.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1OwnerBatchPlansResponseToJson(
  GetV1OwnerBatchPlansResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
