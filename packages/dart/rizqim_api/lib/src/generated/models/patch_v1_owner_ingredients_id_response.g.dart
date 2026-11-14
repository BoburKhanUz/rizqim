// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'patch_v1_owner_ingredients_id_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PatchV1OwnerIngredientsIdResponse _$PatchV1OwnerIngredientsIdResponseFromJson(
  Map<String, dynamic> json,
) => PatchV1OwnerIngredientsIdResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: json['data'] as String,
);

Map<String, dynamic> _$PatchV1OwnerIngredientsIdResponseToJson(
  PatchV1OwnerIngredientsIdResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
