// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'delete_v1_owner_ingredients_id_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

DeleteV1OwnerIngredientsIdResponse _$DeleteV1OwnerIngredientsIdResponseFromJson(
  Map<String, dynamic> json,
) => DeleteV1OwnerIngredientsIdResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: json['data'],
);

Map<String, dynamic> _$DeleteV1OwnerIngredientsIdResponseToJson(
  DeleteV1OwnerIngredientsIdResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
