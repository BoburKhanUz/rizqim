// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_owner_ingredients_id_stop_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1OwnerIngredientsIdStopResponse
_$PostV1OwnerIngredientsIdStopResponseFromJson(Map<String, dynamic> json) =>
    PostV1OwnerIngredientsIdStopResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data107.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$PostV1OwnerIngredientsIdStopResponseToJson(
  PostV1OwnerIngredientsIdStopResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
