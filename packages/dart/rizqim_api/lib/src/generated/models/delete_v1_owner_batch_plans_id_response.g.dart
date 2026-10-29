// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'delete_v1_owner_batch_plans_id_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

DeleteV1OwnerBatchPlansIdResponse _$DeleteV1OwnerBatchPlansIdResponseFromJson(
  Map<String, dynamic> json,
) => DeleteV1OwnerBatchPlansIdResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: json['data'],
);

Map<String, dynamic> _$DeleteV1OwnerBatchPlansIdResponseToJson(
  DeleteV1OwnerBatchPlansIdResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
