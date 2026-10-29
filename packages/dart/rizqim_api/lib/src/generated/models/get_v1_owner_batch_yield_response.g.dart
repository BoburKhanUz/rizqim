// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_owner_batch_yield_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1OwnerBatchYieldResponse _$GetV1OwnerBatchYieldResponseFromJson(
  Map<String, dynamic> json,
) => GetV1OwnerBatchYieldResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data33.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1OwnerBatchYieldResponseToJson(
  GetV1OwnerBatchYieldResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
