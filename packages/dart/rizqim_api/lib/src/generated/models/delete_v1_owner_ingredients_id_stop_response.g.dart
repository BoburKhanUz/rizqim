// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'delete_v1_owner_ingredients_id_stop_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

DeleteV1OwnerIngredientsIdStopResponse
_$DeleteV1OwnerIngredientsIdStopResponseFromJson(Map<String, dynamic> json) =>
    DeleteV1OwnerIngredientsIdStopResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data108.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$DeleteV1OwnerIngredientsIdStopResponseToJson(
  DeleteV1OwnerIngredientsIdStopResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
